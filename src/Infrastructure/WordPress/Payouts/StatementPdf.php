<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Payouts;

use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Payout\BankDetails;
use Cheppi\Sellers\Domain\Payout\Payout;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Infrastructure\Pdf\SimplePdf;

/** Account statement of one payout: every movement it includes, in Cheppi's colours. */
final class StatementPdf
{
    private const VIOLET = [0.427, 0.231, 1.0];
    private const NIGHT = [0.086, 0.067, 0.18];
    private const MUTED = [0.36, 0.34, 0.44];
    private const LIME = [0.776, 0.957, 0.196];

    /** @param list<LedgerEntry> $entries */
    public static function build(Vendor $vendor, Payout $payout, array $entries, ?BankDetails $bank): string
    {
        $pdf = new SimplePdf(sprintf('Estado de cuenta %s - %s', $vendor->storeName, $payout->cutoffDate->format('d/m/Y')));
        $site = (string) get_bloginfo('name');
        $money = static fn (int $cents): string => ($cents < 0 ? '- ' : '') . 'S/ ' . number_format(abs($cents) / 100, 2);

        $header = static function (SimplePdf $pdf) use ($site): void {
            $pdf->rect(0, 0, SimplePdf::WIDTH, 70, self::NIGHT);
            $pdf->text(40, 42, 'cheppi', 24, true, [1, 1, 1]);
            $pdf->rect(128, 26, 62, 18, self::LIME);
            $pdf->text(159, 39, 'MARKET', 9, true, self::NIGHT, 'center');
            $pdf->text(555, 40, 'Estado de cuenta', 14, true, [1, 1, 1], 'right');
            $pdf->text(555, 56, $site, 9, false, [0.8, 0.78, 0.9], 'right');
        };
        $header($pdf);

        $y = 104;
        $pdf->text(40, $y, $vendor->storeName, 15, true, self::NIGHT);
        $pdf->text(40, $y + 18, sprintf('%s · RUC %s', $vendor->legalName, $vendor->ruc), 9.5, false, self::MUTED);
        if ($bank !== null) {
            $pdf->text(40, $y + 32, sprintf('%s · CCI %s · Titular: %s', $bank->bankLabel(), $bank->cci, $bank->holder), 9.5, false, self::MUTED);
        }
        $pdf->text(555, $y, sprintf('Corte del %s', $payout->cutoffDate->format('d/m/Y')), 11, true, self::NIGHT, 'right');
        $pdf->text(555, $y + 18, sprintf('Liquidación #%d · %s', (int) $payout->id, $payout->status->label()), 9.5, false, self::MUTED, 'right');
        if ($payout->paidAt !== null) {
            $pdf->text(555, $y + 32, sprintf('Pagado el %s · Op. %s', wp_date('d/m/Y', $payout->paidAt->getTimestamp()), $payout->reference), 9.5, false, self::MUTED, 'right');
        }

        // Totals by type
        $byType = [];
        foreach ($entries as $e) {
            if ($e->type !== LedgerEntryType::Payout) {
                $byType[$e->type->value] = ($byType[$e->type->value] ?? 0) + $e->amount->cents;
            }
        }
        $y = 168;
        $pdf->rect(40, $y, 515, 64, [0.965, 0.957, 0.984]);
        $x = 56;
        foreach ([LedgerEntryType::Sale, LedgerEntryType::Commission, LedgerEntryType::Shipping, LedgerEntryType::Refund, LedgerEntryType::ServiceFee, LedgerEntryType::Adjustment] as $type) {
            if (!isset($byType[$type->value])) {
                continue;
            }
            $pdf->text($x, $y + 24, $type->label(), 8.5, false, self::MUTED);
            $pdf->text($x, $y + 42, $money($byType[$type->value]), 11, true, self::NIGHT);
            $x += 98;
        }
        $pdf->rect(415, $y + 10, 130, 44, self::VIOLET);
        $pdf->text(530, $y + 26, 'A transferir', 8.5, false, [1, 1, 1], 'right');
        $pdf->text(530, $y + 45, $money($payout->amount->cents), 14, true, [1, 1, 1], 'right');

        // Movements
        $y = 256;
        $row = static function (SimplePdf $pdf, float $y): void {
            $pdf->text(40, $y, 'Fecha', 8.5, true, self::MUTED);
            $pdf->text(110, $y, 'Concepto', 8.5, true, self::MUTED);
            $pdf->text(380, $y, 'Pedido', 8.5, true, self::MUTED);
            $pdf->text(555, $y, 'Monto', 8.5, true, self::MUTED, 'right');
            $pdf->line(40, $y + 6, 555, $y + 6);
        };
        $row($pdf, $y);
        $y += 22;
        foreach ($entries as $e) {
            if ($e->type === LedgerEntryType::Payout) {
                continue;
            }
            if ($y > 790) {
                $pdf->addPage();
                $header($pdf);
                $y = 100;
                $row($pdf, $y);
                $y += 22;
            }
            $concept = $e->type->label() . ($e->commissionBps !== null && $e->type === LedgerEntryType::Commission ? sprintf(' (%s%%)', rtrim(rtrim(number_format($e->commissionBps / 100, 2), '0'), '.')) : '') . ($e->note !== '' ? ' · ' . $e->note : '');
            $pdf->text(40, $y, wp_date('d/m/Y', $e->createdAt->getTimestamp()), 9, false, self::NIGHT);
            $pdf->text(110, $y, SimplePdf::fit($concept, 260, 9), 9, false, self::NIGHT);
            $pdf->text(380, $y, $e->orderId !== null ? '#' . $e->orderId : '—', 9, false, self::NIGHT);
            $pdf->text(555, $y, $money($e->amount->cents), 9, $e->amount->cents > 0, self::NIGHT, 'right');
            $pdf->line(40, $y + 7, 555, $y + 7, [0.93, 0.92, 0.96], 0.4);
            $y += 18;
        }

        $y = min($y + 20, 810);
        $pdf->text(40, $y, 'Las ventas se liquidan cuando el pedido fue entregado y terminó el plazo de devolución. Consultas: ' . get_option('admin_email'), 8, false, self::MUTED);
        return $pdf->output();
    }
}
