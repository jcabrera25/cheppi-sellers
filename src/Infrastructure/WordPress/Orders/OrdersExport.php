<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Orders;

use Cheppi\Sellers\Application\OrderFilter;
use Cheppi\Sellers\Infrastructure\Export\XlsxWriter;
use Cheppi\Sellers\Infrastructure\Pdf\SimplePdf;
use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;
use Cheppi\Sellers\Infrastructure\WordPress\Payouts\PayoutFiles;
use Cheppi\Sellers\Infrastructure\WordPress\Customers\WpCustomers;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;

/**
 * Orders as PDF (landscape table), Excel (.xlsx) or CSV, with the filters of the screen (also one customer).
 * A vendor always gets only their own orders; the Cheppi team gets every store (or the one filtered).
 */
final class OrdersExport
{
    public const ACTION = 'cheppi_orders_export';

    public function __construct(
        private readonly OrdersReport $report,
        private readonly WpVendorRepository $vendors,
        private readonly WpCustomers $customers
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'export']);
    }

    /** @param array<string, string> $filters */
    public static function url(string $format, bool $manage, array $filters): string
    {
        return wp_nonce_url(add_query_arg($filters + ['action' => self::ACTION, 'formato' => $format, 'ambito' => $manage ? 'gestion' : 'vendedor'], admin_url('admin-post.php')), self::ACTION);
    }

    public function export(): void
    {
        if (!wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), self::ACTION)) {
            wp_die(esc_html__('El enlace venció. Vuelve a la lista de pedidos.', 'cheppi-sellers'), 403);
        }
        $manage = ($_GET['ambito'] ?? '') === 'gestion' && current_user_can(Capabilities::MANAGE);
        $vendor = $manage ? null : $this->vendors->findByUserId(get_current_user_id());
        if (!$manage && ($vendor === null || !$vendor->isPublic())) {
            wp_die(esc_html__('No tienes permiso para hacer esto.', 'cheppi-sellers'), 403);
        }
        $query = array_map(static fn ($v): string => sanitize_text_field(wp_unslash((string) $v)), $_GET);
        $filter = OrderFilter::fromQuery($query, $manage ? null : (int) $vendor->id, wp_timezone());
        $rows = $this->report->rows($filter, OrdersReport::EXPORT_LIMIT, 0);
        $scope = $manage ? ($filter->vendorId ? ($this->vendors->find($filter->vendorId)?->storeName ?? '') : __('Todas las tiendas', 'cheppi-sellers')) : $vendor->storeName;
        if ($filter->customer !== null) {
            $customer = $this->customers->find($filter->customer, $filter->vendorId);
            $scope = sprintf(__('Cliente %s', 'cheppi-sellers'), $customer['name'] ?? '') . ($manage && $filter->vendorId === null ? '' : ' · ' . $scope);
        }
        $period = self::period($filter);
        $name = 'pedidos-' . sanitize_title($scope) . '-' . wp_date('Y-m-d');

        if (($_GET['formato'] ?? '') === 'pdf') {
            $pdf = $this->pdf($rows, $manage, $scope, $period);
            nocache_headers();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $name . '.pdf"');
            echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput -- binary
            exit;
        }

        $headers = array_merge(['Pedido', 'Fecha'], $manage ? ['Tienda'] : [], ['Estado', 'Cliente', 'Productos', 'Dirección de facturación', 'Dirección de envío', 'Celular', 'Venta bruta', 'Comisión Cheppi', 'Ganancia del vendedor', 'Seguimiento']);
        $data = [];
        foreach ($rows as $r) {
            $data[] = array_merge(
                [$r['part']->orderId, $r['date']],
                $manage ? [$r['store']] : [],
                [$r['part']->status->label(), $r['customer'], implode("\n", $r['products']), $r['billing'], $r['shipping'], $r['phone'], $r['gross'] / 100, $r['commission'] / 100, $r['net'] / 100, $r['part']->tracking]
            );
        }
        if (($_GET['formato'] ?? '') !== 'csv' && XlsxWriter::available()) {
            $widths = array_merge([9, 16], $manage ? [22] : [], [12, 22, 40, 40, 40, 14, 13, 14, 16, 24]);
            $file = XlsxWriter::build('Pedidos', $headers, $data, $widths);
            nocache_headers();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $name . '.xlsx"');
            header('Content-Length: ' . strlen($file));
            echo $file; // phpcs:ignore WordPress.Security.EscapeOutput -- binary
            exit;
        }
        PayoutFiles::sendCsv($name . '.csv', array_merge([$headers], array_map(static fn (array $row): array => array_map(static fn ($v): string => is_float($v) ? number_format($v, 2, '.', '') : (string) $v, $row), $data)));
    }

    private static function period(OrderFilter $filter): string
    {
        if ($filter->from === null && $filter->to === null) {
            return __('Todas las fechas', 'cheppi-sellers');
        }
        return sprintf('%s – %s', $filter->from?->format('d/m/Y') ?? '…', $filter->to?->format('d/m/Y') ?? wp_date('d/m/Y'));
    }

    /** @param list<array<string, mixed>> $rows */
    private function pdf(array $rows, bool $manage, string $scope, string $period): string
    {
        $pdf = new SimplePdf('Pedidos ' . $scope, true);
        $night = [0.086, 0.067, 0.18];
        $muted = [0.36, 0.34, 0.44];
        $cols = $manage
            ? [['Pedido', 38], ['Fecha', 56], ['Tienda', 78], ['Productos', 140], ['Facturación', 125], ['Envío', 125], ['Bruto', 56, 'r'], ['Comisión', 52, 'r'], ['Ganancia', 58, 'r']]
            : [['Pedido', 40], ['Fecha', 58], ['Productos', 175], ['Facturación', 150], ['Envío', 150], ['Bruto', 60, 'r'], ['Comisión', 56, 'r'], ['Ganancia', 62, 'r']];
        $money = static fn (int $c): string => 'S/ ' . number_format($c / 100, 2);
        $left = 30.0;

        $header = static function (SimplePdf $pdf) use ($cols, $left, $scope, $period, $night, $muted): float {
            $pdf->rect(0, 0, $pdf->width, 46, $night);
            $pdf->text($left, 29, 'cheppi', 18, true, [1, 1, 1]);
            $pdf->text($left + 74, 29, 'Pedidos · ' . $scope, 11, true, [1, 1, 1]);
            $pdf->text($pdf->width - 30, 29, $period . ' · generado ' . wp_date('d/m/Y H:i'), 8.5, false, [0.85, 0.83, 0.95], 'right');
            $x = $left;
            foreach ($cols as $c) {
                $pdf->text(($c[2] ?? '') === 'r' ? $x + $c[1] - 4 : $x, 66, $c[0], 8, true, $muted, ($c[2] ?? '') === 'r' ? 'right' : 'left');
                $x += $c[1];
            }
            $pdf->line($left, 71, $pdf->width - 30, 71);
            return 84.0;
        };
        $y = $header($pdf);
        foreach ($rows as $r) {
            $cells = array_merge(
                ['#' . $r['part']->orderId, $r['date']],
                $manage ? [$r['store']] : [],
                [implode(', ', $r['products']), $r['billing'], trim($r['shipping'] . ($r['phone'] !== '' ? ' · ' . $r['phone'] : '')), $money($r['gross']), $money($r['commission']), $money($r['net'])]
            );
            $wrapped = [];
            foreach ($cols as $i => $c) {
                $wrapped[$i] = ($c[2] ?? '') === 'r' ? [$cells[$i]] : SimplePdf::wrap((string) $cells[$i], $c[1] - 6, 7.5, 3);
            }
            $height = max(array_map('count', $wrapped)) * 9.5 + 6;
            if ($y + $height > $pdf->height - 40) {
                $pdf->addPage();
                $y = $header($pdf);
            }
            $x = $left;
            foreach ($cols as $i => $c) {
                foreach ($wrapped[$i] as $n => $line) {
                    $right = ($c[2] ?? '') === 'r';
                    $pdf->text($right ? $x + $c[1] - 4 : $x, $y + $n * 9.5, $line, 7.5, $right && $i === count($cols) - 1, $night, $right ? 'right' : 'left');
                }
                $x += $c[1];
            }
            $pdf->line($left, $y + $height - 6, $pdf->width - 30, $y + $height - 6, [0.92, 0.91, 0.95], 0.4);
            $y += $height;
        }
        $sum = OrdersReport::sum($rows);
        $y += 8;
        $pdf->text($left, $y, sprintf('%d pedidos · Venta bruta %s · Comisión %s · Ganancia de las tiendas %s', $sum['count'], $money($sum['gross']), $money($sum['commission']), $money($sum['net'])), 9, true, $night);
        return $pdf->output();
    }
}
