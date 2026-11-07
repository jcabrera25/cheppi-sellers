<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\GeneratePayouts;
use Cheppi\Sellers\Application\SettlePayout;
use Cheppi\Sellers\Domain\Payout\PayoutSchedule;
use Cheppi\Sellers\Domain\Payout\PayoutStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Mail\WpMailNotifier;
use Cheppi\Sellers\Infrastructure\WordPress\Payouts\PayoutFiles;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpPayouts;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorExtras;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Cheppi → Liquidaciones: run the cut-off, export the transfers, record each payment or cancel it.
 */
final class PayoutsPage extends AdminScreen
{
    public const SLUG = 'cheppi-payouts';
    public const ACTION_GENERATE = 'cheppi_payouts_generate';
    public const ACTION_PAY = 'cheppi_payout_pay';
    public const ACTION_CANCEL = 'cheppi_payout_cancel';
    public const ACTION_EXPORT = 'cheppi_payouts_export';

    public function __construct(
        Assets $assets,
        private readonly WpPayouts $payouts,
        private readonly WpVendorRepository $vendors,
        private readonly WpVendorExtras $extras,
        private readonly GeneratePayouts $generate,
        private readonly SettlePayout $settle,
        private readonly BusinessRulesRepository $rules,
        private readonly WpMailNotifier $mailer
    ) {
        parent::__construct($assets);
    }

    protected function slug(): string
    {
        return self::SLUG;
    }

    protected function menuTitle(): string
    {
        return __('Liquidaciones', 'cheppi-sellers');
    }

    protected function position(): int
    {
        return 1;
    }

    public function register(): void
    {
        $this->hooks();
        add_action('admin_post_' . self::ACTION_GENERATE, [$this, 'generate']);
        add_action('admin_post_' . self::ACTION_PAY, [$this, 'pay']);
        add_action('admin_post_' . self::ACTION_CANCEL, [$this, 'cancel']);
        add_action('admin_post_' . self::ACTION_EXPORT, [$this, 'export']);
    }

    public function render(): void
    {
        $this->authorize();
        $status = PayoutStatus::tryFrom(sanitize_key((string) ($_GET['estado'] ?? 'pending'))) ?? PayoutStatus::Pending;
        $list = $this->payouts->list($status, null, 200, 0);
        $vendorIds = array_values(array_unique(array_map(static fn ($p): int => $p->vendorId, $list)));
        $vendors = [];
        foreach ($vendorIds as $id) {
            $vendors[$id] = $this->vendors->find($id);
        }
        $this->view('payouts', [
            'list' => $list,
            'vendors' => $vendors,
            'banks' => array_combine($vendorIds, array_map(fn (int $id) => $this->extras->bank($id), $vendorIds)) ?: [],
            'status' => $status,
            'totals' => $this->payouts->totals(),
            'nextCutoff' => (new PayoutSchedule($this->rules->current()))->nextCutoff(new DateTimeImmutable('now', wp_timezone())),
            'rules' => $this->rules->current(),
            'flash' => $this->takeFlash(),
            'pageUrl' => fn (array $args = []): string => $this->url($args),
        ]);
    }

    public function generate(): void
    {
        $this->authorize(self::ACTION_GENERATE);
        $created = ($this->generate)(new DateTimeImmutable('now', wp_timezone()), new DateTimeImmutable('now'));
        $this->flash([
            'type' => 'success',
            'message' => $created === []
                ? __('No hay montos liberados que alcancen el mínimo de liquidación.', 'cheppi-sellers')
                : sprintf(_n('Se creó %d liquidación.', 'Se crearon %d liquidaciones.', count($created), 'cheppi-sellers'), count($created)),
        ], $this->url());
    }

    public function pay(): void
    {
        $id = absint($_POST['payout_id'] ?? 0);
        $this->authorize(self::ACTION_PAY . $id);
        try {
            $payout = $this->settle->pay($id, sanitize_text_field(wp_unslash((string) ($_POST['reference'] ?? ''))), new DateTimeImmutable('now'));
            $vendor = $this->vendors->find($payout->vendorId);
            if ($vendor !== null) {
                $this->mailer->payoutPaid($vendor, $payout->amount->toDecimal(), $payout->reference);
            }
            $this->flash(['type' => 'success', 'message' => sprintf(__('Liquidación #%d marcada como pagada.', 'cheppi-sellers'), $id)], $this->url());
        } catch (DomainException $e) {
            $this->flash(['type' => 'error', 'message' => $e->getMessage()], $this->url());
        }
    }

    public function cancel(): void
    {
        $id = absint($_POST['payout_id'] ?? 0);
        $this->authorize(self::ACTION_CANCEL . $id);
        try {
            $this->settle->cancel($id, new DateTimeImmutable('now'));
            $this->flash(['type' => 'success', 'message' => sprintf(__('Liquidación #%d anulada. El monto vuelve al saldo del vendedor.', 'cheppi-sellers'), $id)], $this->url());
        } catch (DomainException $e) {
            $this->flash(['type' => 'error', 'message' => $e->getMessage()], $this->url());
        }
    }

    /** CSV of pending payouts with bank data, ready to load in the bank's bulk-transfer screen or Excel. */
    public function export(): void
    {
        $this->authorize(self::ACTION_EXPORT);
        $rows = [['Liquidación', 'Corte', 'Tienda', 'RUC', 'Razón social', 'Banco', 'Titular', 'Cuenta', 'CCI', 'Monto']];
        foreach ($this->payouts->list(PayoutStatus::Pending, null, 1000, 0) as $p) {
            $vendor = $this->vendors->find($p->vendorId);
            $bank = $this->extras->bank($p->vendorId);
            $rows[] = [
                (string) $p->id,
                $p->cutoffDate->format('d/m/Y'),
                $vendor?->storeName ?? '',
                $vendor?->ruc ?? '',
                $vendor?->legalName ?? '',
                $bank?->bankLabel() ?? '',
                $bank?->holder ?? '',
                $bank?->account ?? '',
                $bank?->cci ?? '',
                $p->amount->toDecimal(),
            ];
        }
        PayoutFiles::sendCsv('liquidaciones-' . wp_date('Y-m-d') . '.csv', $rows);
    }
}
