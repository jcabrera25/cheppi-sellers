<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Mail;

use Cheppi\Sellers\Application\VendorNotifier;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\VendorsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

/**
 * Branded HTML emails through wp_mail (works with any SMTP plugin the site uses).
 */
final class WpMailNotifier implements VendorNotifier
{
    public function applicationReceived(Vendor $vendor): void
    {
        $this->toVendor(
            $vendor,
            'Recibimos tu solicitud para vender en Cheppi',
            sprintf('Hola, recibimos la solicitud de <strong>%s</strong>. La revisaremos en un máximo de 2 días hábiles y te avisaremos por este medio.', esc_html($vendor->storeName)),
            'Ver mi solicitud',
            Routes::panelUrl()
        );
        $this->toAdmin(
            'Nueva solicitud de vendedor: ' . $vendor->storeName,
            sprintf('%s (RUC %s) envió su solicitud para vender en Cheppi.', esc_html($vendor->storeName), esc_html($vendor->ruc)),
            'Revisar solicitud',
            VendorsPage::detailUrl((int) $vendor->id)
        );
    }

    public function statusChanged(Vendor $vendor): void
    {
        $note = $vendor->statusNote !== '' ? '<p style="margin:16px 0;padding:12px 16px;background:#F1ECFF;border-radius:10px;">' . nl2br(esc_html($vendor->statusNote)) . '</p>' : '';
        [$subject, $body, $cta] = match ($vendor->status) {
            VendorStatus::Approved => ['¡Tu tienda fue aprobada en Cheppi!', 'Ya puedes ingresar a tu panel. Pronto podrás subir tus productos.', 'Ir a mi panel'],
            VendorStatus::NeedsInfo => ['Necesitamos un dato más para aprobar tu tienda', 'Revisamos tu solicitud y falta corregir algo:' . $note, 'Completar mi solicitud'],
            VendorStatus::Rejected => ['Sobre tu solicitud en Cheppi', 'Por ahora no podemos aprobar tu tienda.' . $note . 'Si tienes dudas, responde este correo.', 'Ver mi solicitud'],
            VendorStatus::Suspended => ['Tu tienda en Cheppi fue suspendida', 'Tu tienda dejó de mostrarse temporalmente.' . $note, 'Ver detalles'],
            VendorStatus::Pending => ['Tu solicitud está en revisión', 'Volvimos a poner tu solicitud en revisión.', 'Ver mi solicitud'],
        };
        $this->toVendor($vendor, $subject, $body, $cta, Routes::panelUrl());
    }

    public function documentsResubmitted(Vendor $vendor): void
    {
        $this->toAdmin(
            'Documentos actualizados: ' . $vendor->storeName,
            sprintf('%s subió los documentos que faltaban. La solicitud volvió a revisión.', esc_html($vendor->storeName)),
            'Revisar solicitud',
            VendorsPage::detailUrl((int) $vendor->id)
        );
    }

    private function toVendor(Vendor $vendor, string $subject, string $body, string $cta, string $url): void
    {
        $user = get_userdata($vendor->userId);
        if ($user && is_email($user->user_email)) {
            $this->send($user->user_email, $subject, $body, $cta, $url);
        }
    }

    private function toAdmin(string $subject, string $body, string $cta, string $url): void
    {
        $email = (string) apply_filters('cheppi_sellers_admin_email', get_option('admin_email'));
        if (is_email($email)) {
            $this->send($email, $subject, $body, $cta, $url);
        }
    }

    private function send(string $to, string $subject, string $body, string $cta, string $url): void
    {
        $html = '<!doctype html><html><body style="margin:0;background:#F6F4FB;font-family:Arial,Helvetica,sans-serif;color:#16112E;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:16px;">'
            . '<tr><td style="padding:24px 32px;border-bottom:1px solid #EEEAF7;"><span style="font-size:22px;font-weight:800;letter-spacing:-0.04em;">cheppi</span>'
            . ' <span style="display:inline-block;background:#C6F432;border-radius:999px;padding:2px 8px;font-size:10px;font-weight:800;letter-spacing:.14em;">MARKET</span></td></tr>'
            . '<tr><td style="padding:28px 32px;font-size:15px;line-height:1.55;">'
            . '<h1 style="font-size:20px;margin:0 0 12px;">' . esc_html($subject) . '</h1>'
            . '<div>' . wp_kses_post($body) . '</div>'
            . '<p style="margin:24px 0 0;"><a href="' . esc_url($url) . '" style="display:inline-block;background:#C6F432;color:#16112E;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:999px;">' . esc_html($cta) . '</a></p>'
            . '</td></tr><tr><td style="padding:16px 32px 24px;color:#5B5670;font-size:12px;">Cheppi Market · ' . esc_html(wp_parse_url(home_url(), PHP_URL_HOST) ?: '') . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    }
}
