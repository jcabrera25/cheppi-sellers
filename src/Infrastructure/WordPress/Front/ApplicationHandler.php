<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\ResubmitVendorDocuments;
use Cheppi\Sellers\Application\SubmitVendorApplication;
use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\Ruc;
use Cheppi\Sellers\Domain\Vendor\VendorApplication;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Accounts;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;
use Cheppi\Sellers\Infrastructure\WordPress\RequestFiles;
use WP_Error;

/**
 * Receives the registration and document re-upload forms (admin-post.php).
 * Answers JSON when the panel script sends the form, or re-renders the page when JavaScript is off.
 */
final class ApplicationHandler
{
    public const ACTION_APPLY = 'cheppi_vendor_apply';
    public const ACTION_RESUBMIT = 'cheppi_vendor_resubmit';
    private const MAX_ATTEMPTS_PER_HOUR = 6;

    public function __construct(
        private readonly SubmitVendorApplication $submit,
        private readonly ResubmitVendorDocuments $resubmit,
        private readonly VendorRepository $vendors,
        private readonly PanelController $panel
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION_APPLY, [$this, 'apply']);
        add_action('admin_post_nopriv_' . self::ACTION_APPLY, [$this, 'apply']);
        add_action('admin_post_' . self::ACTION_RESUBMIT, [$this, 'resubmit']);
    }

    public function apply(): void
    {
        PanelController::sendNoCacheHeaders();
        $post = wp_unslash($_POST);
        $values = self::textFields($post, ['full_name', 'email', 'store_name', 'category', 'ruc', 'phone', 'legal_name', 'district', 'instagram', 'website']);

        if (!wp_verify_nonce((string) ($post['_cheppi_nonce'] ?? ''), self::ACTION_APPLY)) {
            $this->fail(['form' => __('La página expiró. Recárgala e inténtalo de nuevo.', 'cheppi-sellers')], $values);
        }
        if (trim((string) ($post['company_url'] ?? '')) !== '') {
            $this->fail(['form' => __('No pudimos procesar la solicitud.', 'cheppi-sellers')], $values); // honeypot filled: bot
        }
        if (!$this->withinRateLimit()) {
            $this->fail(['form' => __('Demasiados intentos. Espera unos minutos y vuelve a intentarlo.', 'cheppi-sellers')], $values);
        }

        $files = RequestFiles::documents();
        $terms = !empty($post['terms']);
        $user = wp_get_current_user();

        // 1) Validate everything before creating an account, so failed attempts leave nothing behind.
        $errors = $user->exists() ? [] : $this->accountErrors($values['full_name'], $values['email'], (string) ($post['password'] ?? ''));
        $provided = [];
        foreach ($files as $typeValue => $file) {
            try {
                $file->assertAcceptable('doc_' . $typeValue);
                $provided[] = DocumentType::from($typeValue);
            } catch (VendorValidationException $e) {
                $errors += $e->errors;
            }
        }
        try {
            VendorApplication::fromArray($values, $provided, $terms);
        } catch (VendorValidationException $e) {
            $errors += $e->errors;
        }
        if (!isset($errors['ruc']) && Ruc::isValid(preg_replace('/\D/', '', $values['ruc']) ?? '') && $this->vendors->rucExists(Ruc::fromString($values['ruc'])->value)) {
            $errors['ruc'] = __('Este RUC ya tiene una tienda en Cheppi. Escríbenos si es un error.', 'cheppi-sellers');
        }
        if ($errors !== []) {
            $this->fail($errors, $values);
        }

        // 2) Create the account if needed and sign in.
        if (!$user->exists()) {
            $userId = Accounts::create($values['full_name'], $values['email'], (string) $post['password']);
            if ($userId instanceof WP_Error) {
                $this->fail(['email' => $userId->get_error_message()], $values);
            }
            wp_set_current_user($userId);
            wp_set_auth_cookie($userId, true, is_ssl());
            $user = wp_get_current_user();
        }

        // 3) Submit the application.
        try {
            ($this->submit)($user->ID, $values, $files, $terms);
        } catch (VendorValidationException $e) {
            $this->fail($e->errors, $values);
        }
        $user->add_role(Installer::VENDOR_ROLE);

        $this->succeed(add_query_arg('enviado', 'solicitud', Routes::panelUrl()));
    }

    public function resubmit(): void
    {
        PanelController::sendNoCacheHeaders();
        check_admin_referer(self::ACTION_RESUBMIT, '_cheppi_nonce');
        try {
            ($this->resubmit)(get_current_user_id(), RequestFiles::documents());
        } catch (VendorValidationException $e) {
            wp_die(esc_html(implode(' ', $e->errors)), esc_html__('No se enviaron los documentos', 'cheppi-sellers'), ['response' => 422, 'back_link' => true]);
        }
        wp_safe_redirect(add_query_arg('enviado', 'documentos', Routes::panelUrl()));
        exit;
    }

    /** @return array<string, string> */
    private function accountErrors(string $name, string $email, string $password): array
    {
        $errors = [];
        if (mb_strlen($name) < 3) {
            $errors['full_name'] = __('Escribe tu nombre y apellido.', 'cheppi-sellers');
        }
        if (!is_email($email)) {
            $errors['email'] = __('Escribe un correo válido.', 'cheppi-sellers');
        } elseif (email_exists($email)) {
            $errors['email'] = __('Ese correo ya tiene cuenta en Cheppi. Ingresa primero y vuelve a esta página.', 'cheppi-sellers');
        }
        if (strlen($password) < 8) {
            $errors['password'] = __('La contraseña debe tener al menos 8 caracteres.', 'cheppi-sellers');
        }
        return $errors;
    }

    private function withinRateLimit(): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $key = 'cheppi_apply_' . md5($ip . wp_salt('nonce'));
        $attempts = (int) get_transient($key);
        if ($attempts >= self::MAX_ATTEMPTS_PER_HOUR) {
            return false;
        }
        set_transient($key, $attempts + 1, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * @param array<string, mixed> $post
     * @param list<string> $keys
     * @return array<string, string>
     */
    private static function textFields(array $post, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = sanitize_text_field((string) ($post[$key] ?? ''));
        }
        $out['email'] = sanitize_email($out['email']);
        return $out;
    }

    private static function wantsJson(): bool
    {
        return isset($_SERVER['HTTP_ACCEPT']) && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'application/json');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $values
     */
    private function fail(array $errors, array $values): never
    {
        if (self::wantsJson()) {
            wp_send_json(['ok' => false, 'errors' => $errors], 422);
        }
        $this->panel->renderRegister($errors, $values);
        exit;
    }

    private function succeed(string $redirect): never
    {
        if (self::wantsJson()) {
            wp_send_json(['ok' => true, 'redirect' => $redirect]);
        }
        wp_safe_redirect($redirect);
        exit;
    }
}
