<?php
/**
 * Payouts admin.
 *
 * @var list<\Cheppi\Sellers\Domain\Payout\Payout> $list
 * @var array<int, ?\Cheppi\Sellers\Domain\Vendor\Vendor> $vendors
 * @var array<int, ?\Cheppi\Sellers\Domain\Payout\BankDetails> $banks
 * @var \Cheppi\Sellers\Domain\Payout\PayoutStatus $status
 * @var array<string, array{count: int, cents: int}> $totals
 * @var DateTimeImmutable $nextCutoff
 * @var \Cheppi\Sellers\Domain\Rules\BusinessRules $rules
 * @var array<string, mixed> $flash
 * @var callable(array): string $pageUrl
 */

use Cheppi\Sellers\Domain\Payout\PayoutStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\PayoutsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\VendorsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Payouts\PayoutFiles;

defined('ABSPATH') || exit;

$soles = static fn (int $cents): string => 'S/ ' . number_format($cents / 100, 2);
?>
<div class="wrap ch-admin">
    <header class="ch-admin__head ch-admin__head--row">
        <div>
            <h1><?php esc_html_e('Liquidaciones', 'cheppi-sellers'); ?></h1>
            <p class="ch-muted"><?php echo esc_html(sprintf(
                __('Corte automático los días %1$s de cada mes. Mínimo por liquidación: S/ %2$s. Próximo corte: %3$s.', 'cheppi-sellers'),
                implode(' y ', $rules->payoutDays),
                $rules->minimumPayout->toDecimal(),
                wp_date(get_option('date_format'), $nextCutoff->getTimestamp())
            )); ?></p>
        </div>
        <div class="ch-actions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(PayoutsPage::ACTION_GENERATE); ?>">
                <?php wp_nonce_field(PayoutsPage::ACTION_GENERATE); ?>
                <button class="ch-btn ch-btn--ghost" type="submit"><?php esc_html_e('Hacer corte hoy', 'cheppi-sellers'); ?></button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(PayoutsPage::ACTION_EXPORT); ?>">
                <?php wp_nonce_field(PayoutsPage::ACTION_EXPORT); ?>
                <button class="ch-btn ch-btn--primary" type="submit"><?php esc_html_e('Exportar transferencias (CSV)', 'cheppi-sellers'); ?></button>
            </form>
        </div>
    </header>

    <?php if (isset($flash['message'])) : ?>
        <div class="notice notice-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html((string) $flash['message']); ?></p></div>
    <?php endif; ?>

    <div class="ch-grid ch-grid--stats ch-admin__stats">
        <div class="ch-card ch-stat ch-stat--accent"><span class="ch-stat__label"><?php esc_html_e('Por transferir', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo esc_html($soles($totals['pending']['cents'] ?? 0)); ?></span><span class="ch-muted ch-small"><?php echo (int) ($totals['pending']['count'] ?? 0); ?> <?php esc_html_e('liquidaciones', 'cheppi-sellers'); ?></span></div>
        <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Pagado', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo esc_html($soles($totals['paid']['cents'] ?? 0)); ?></span><span class="ch-muted ch-small"><?php echo (int) ($totals['paid']['count'] ?? 0); ?> <?php esc_html_e('liquidaciones', 'cheppi-sellers'); ?></span></div>
        <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Anuladas', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo (int) ($totals['cancelled']['count'] ?? 0); ?></span></div>
        <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Próximo corte', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo esc_html(wp_date('j M', $nextCutoff->getTimestamp())); ?></span></div>
    </div>

    <div class="ch-toolbar">
        <nav class="ch-tabs">
            <?php foreach (PayoutStatus::cases() as $case) : ?>
                <a class="ch-tab" href="<?php echo esc_url($pageUrl(['estado' => $case->value])); ?>" <?php echo $status === $case ? 'aria-current="page"' : ''; ?>><?php echo esc_html($case->label()); ?> <span><?php echo (int) ($totals[$case->value]['count'] ?? 0); ?></span></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="ch-card ch-admin__table-card">
        <?php if ($list === []) : ?>
            <div class="ch-empty"><p class="ch-muted"><?php esc_html_e('No hay liquidaciones en este estado.', 'cheppi-sellers'); ?></p></div>
        <?php else : ?>
            <table class="ch-table-clean">
                <thead><tr>
                    <th>#</th><th><?php esc_html_e('Vendedor', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Cuenta', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Corte', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Monto', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Acciones', 'cheppi-sellers'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($list as $p) :
                    $vendor = $vendors[$p->vendorId] ?? null;
                    $bank = $banks[$p->vendorId] ?? null;
                    ?>
                    <tr>
                        <td data-label="#"><?php echo (int) $p->id; ?></td>
                        <td data-label="<?php esc_attr_e('Vendedor', 'cheppi-sellers'); ?>"><a class="ch-strong-link" href="<?php echo esc_url(VendorsPage::detailUrl($p->vendorId)); ?>"><?php echo esc_html($vendor?->storeName ?? '#' . $p->vendorId); ?></a><br><span class="ch-muted">RUC <?php echo esc_html($vendor?->ruc ?? ''); ?></span></td>
                        <td data-label="<?php esc_attr_e('Cuenta', 'cheppi-sellers'); ?>"><?php if ($bank) : ?><?php echo esc_html($bank->bankLabel()); ?><br><code><?php echo esc_html($bank->cci); ?></code><?php else : ?><span class="ch-chip ch-chip--warning"><?php esc_html_e('Sin cuenta bancaria', 'cheppi-sellers'); ?></span><?php endif; ?></td>
                        <td data-label="<?php esc_attr_e('Corte', 'cheppi-sellers'); ?>"><?php echo esc_html($p->cutoffDate->format('d/m/Y')); ?><?php if ($p->paidAt) : ?><br><span class="ch-muted"><?php echo esc_html(sprintf(__('Pagado %1$s · Op. %2$s', 'cheppi-sellers'), wp_date('d/m/Y', $p->paidAt->getTimestamp()), $p->reference)); ?></span><?php endif; ?></td>
                        <td data-label="<?php esc_attr_e('Monto', 'cheppi-sellers'); ?>"><strong><?php echo esc_html($soles($p->amount->cents)); ?></strong></td>
                        <td data-label="<?php esc_attr_e('Acciones', 'cheppi-sellers'); ?>">
                            <a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url(PayoutFiles::statementUrl((int) $p->id)); ?>" target="_blank"><?php esc_html_e('PDF', 'cheppi-sellers'); ?></a>
                            <?php if ($p->status === PayoutStatus::Pending) : ?>
                                <form class="ch-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(PayoutsPage::ACTION_PAY); ?>">
                                    <input type="hidden" name="payout_id" value="<?php echo (int) $p->id; ?>">
                                    <?php wp_nonce_field(PayoutsPage::ACTION_PAY . $p->id); ?>
                                    <input class="ch-input ch-input--sm" name="reference" required maxlength="120" placeholder="<?php esc_attr_e('N.º de operación', 'cheppi-sellers'); ?>">
                                    <button class="ch-btn ch-btn--primary ch-btn--sm" type="submit"><?php esc_html_e('Pagado', 'cheppi-sellers'); ?></button>
                                </form>
                                <form class="ch-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('¿Anular esta liquidación? El monto vuelve al saldo del vendedor.', 'cheppi-sellers')); ?>')">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(PayoutsPage::ACTION_CANCEL); ?>">
                                    <input type="hidden" name="payout_id" value="<?php echo (int) $p->id; ?>">
                                    <?php wp_nonce_field(PayoutsPage::ACTION_CANCEL . $p->id); ?>
                                    <button class="ch-btn ch-btn--ghost ch-btn--sm" type="submit"><?php esc_html_e('Anular', 'cheppi-sellers'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
