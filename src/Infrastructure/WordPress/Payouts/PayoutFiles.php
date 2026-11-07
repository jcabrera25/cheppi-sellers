<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Payouts;

use Cheppi\Sellers\Application\Ledger;
use Cheppi\Sellers\Application\Payouts;
use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorExtras;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;

/**
 * Downloads: statement PDF of a payout (its vendor or an admin) and CSV files.
 */
final class PayoutFiles
{
    public const ACTION_STATEMENT = 'cheppi_statement';

    public function __construct(
        private readonly Payouts $payouts,
        private readonly Ledger $ledger,
        private readonly WpVendorRepository $vendors,
        private readonly WpVendorExtras $extras
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION_STATEMENT, [$this, 'statement']);
    }

    public static function statementUrl(int $payoutId): string
    {
        return wp_nonce_url(admin_url('admin-post.php?action=' . self::ACTION_STATEMENT . '&payout=' . $payoutId), self::ACTION_STATEMENT . $payoutId);
    }

    public function statement(): void
    {
        $id = absint($_GET['payout'] ?? 0);
        $payout = $this->payouts->find($id);
        $vendor = $payout !== null ? $this->vendors->find($payout->vendorId) : null;
        $allowed = $vendor !== null && (current_user_can(Capabilities::MANAGE) || $vendor->userId === get_current_user_id());
        if (!$allowed || !wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), self::ACTION_STATEMENT . $id)) {
            wp_die(esc_html__('No tienes permiso para ver este documento.', 'cheppi-sellers'), 403);
        }
        $pdf = StatementPdf::build($vendor, $payout, $this->ledger->forPayout($id), $this->extras->bank($vendor->id ?? 0));
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="estado-de-cuenta-' . $payout->cutoffDate->format('Y-m-d') . '-' . $id . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput -- binary PDF
        exit;
    }

    /**
     * Streams a CSV that Excel opens with accents (UTF-8 BOM) and Peruvian separators (;).
     *
     * @param list<list<string>> $rows
     */
    public static function sendCsv(string $filename, array $rows): never
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            // Long digit strings (CCI) stay text in Excel; formulas are neutralised.
            fputcsv($out, array_map(static fn (string $v): string => preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row), ';', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
