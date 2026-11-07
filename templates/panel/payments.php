<?php
/**
 * Vendor payments: balance, bank account, payouts with statements and latest movements.
 *
 * @var \Cheppi\Sellers\Domain\Ledger\Balance $balance
 * @var ?\Cheppi\Sellers\Domain\Payout\BankDetails $bank
 * @var list<\Cheppi\Sellers\Domain\Payout\Payout> $payouts
 * @var list<\Cheppi\Sellers\Domain\Ledger\LedgerEntry> $movements
 * @var DateTimeImmutable $nextCutoff
 * @var \Cheppi\Sellers\Domain\Rules\BusinessRules $rules
 * @var array<string, mixed> $flash
 */

use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Payout\BankDetails;
use Cheppi\Sellers\Domain\Payout\PayoutStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelTools;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;
use Cheppi\Sellers\Infrastructure\WordPress\Payouts\PayoutFiles;

defined('ABSPATH') || exit;

$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$values = is_array($flash['values'] ?? null) ? $flash['values'] : ($bank?->toArray() ?? []);
$soles = static fn (int $cents): string => ($cents < 0 ? '− ' : '') . 'S/ ' . number_format(abs($cents) / 100, 2);
$held = LedgerEntry::untilDelivered();
?>
<section class="ch-grid ch-grid--stats">
    <div class="ch-card ch-stat ch-stat--accent">
        <span class="ch-stat__label"><?php esc_html_e('Disponible para el corte', 'cheppi-sellers'); ?></span>
        <span class="ch-stat__value"><?php echo esc_html($soles($balance->available->cents)); ?></span>
        <span class="ch-small"><?php echo esc_html(sprintf(__('Mínimo S/ %s', 'cheppi-sellers'), $rules->minimumPayout->toDecimal())); ?></span>
    </div>
    <div class="ch-card ch-stat">
        <span class="ch-stat__label"><?php esc_html_e('Retenido', 'cheppi-sellers'); ?></span>
        <span class="ch-stat__value"><?php echo esc_html($soles($balance->pending->cents)); ?></span>
        <span class="ch-muted ch-small"><?php esc_html_e('Pedidos sin entregar o en plazo de devolución', 'cheppi-sellers'); ?></span>
    </div>
    <div class="ch-card ch-stat">
        <span class="ch-stat__label"><?php esc_html_e('Próximo corte', 'cheppi-sellers'); ?></span>
        <span class="ch-stat__value"><?php echo esc_html(wp_date('j M', $nextCutoff->getTimestamp())); ?></span>
        <span class="ch-muted ch-small"><?php echo esc_html(sprintf(__('Cortes los días %s', 'cheppi-sellers'), implode(' y ', $rules->payoutDays))); ?></span>
    </div>
    <div class="ch-card ch-stat">
        <span class="ch-stat__label"><?php esc_html_e('Cuenta de abono', 'cheppi-sellers'); ?></span>
        <span class="ch-stat__value" style="font-size:1.2rem"><?php echo $bank ? esc_html($bank->bankLabel()) : '—'; ?></span>
        <span class="ch-muted ch-small"><?php echo $bank ? esc_html('CCI ' . $bank->maskedCci()) : esc_html__('Agrégala para recibir tus pagos', 'cheppi-sellers'); ?></span>
    </div>
</section>

<div class="ch-grid ch-grid--2" style="margin-top:16px">
    <div class="ch-grid">
        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Liquidaciones', 'cheppi-sellers'); ?></h2>
            <?php if ($payouts === []) : ?>
                <p class="ch-muted"><?php esc_html_e('Aún no tienes liquidaciones. En cada corte juntamos lo que ya está disponible y te lo transferimos.', 'cheppi-sellers'); ?></p>
            <?php else : ?>
                <ul class="ch-orders">
                    <?php foreach ($payouts as $p) : ?>
                        <li class="ch-order ch-order--static">
                            <span class="ch-order__id">#<?php echo (int) $p->id; ?></span>
                            <span class="ch-order__main">
                                <span class="ch-order__who"><?php echo esc_html(sprintf(__('Corte del %s', 'cheppi-sellers'), $p->cutoffDate->format('d/m/Y'))); ?></span>
                                <span class="ch-order__meta"><?php echo $p->paidAt ? esc_html(sprintf(__('Transferido el %1$s · Op. %2$s', 'cheppi-sellers'), wp_date('d/m/Y', $p->paidAt->getTimestamp()), $p->reference)) : esc_html($p->status->label()); ?> · <a href="<?php echo esc_url(PayoutFiles::statementUrl((int) $p->id)); ?>" target="_blank"><?php esc_html_e('Estado de cuenta (PDF)', 'cheppi-sellers'); ?></a></span>
                            </span>
                            <span class="ch-order__total"><?php echo esc_html($soles($p->amount->cents)); ?></span>
                            <span class="ch-chip <?php echo $p->status === PayoutStatus::Paid ? 'ch-chip--success' : ($p->status === PayoutStatus::Pending ? 'ch-chip--warning' : 'ch-chip--neutral'); ?>"><?php echo esc_html($p->status->label()); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Movimientos', 'cheppi-sellers'); ?></h2>
            <?php if ($movements === []) : ?>
                <p class="ch-muted"><?php esc_html_e('Aquí verás cada venta, comisión, envío y pago.', 'cheppi-sellers'); ?></p>
            <?php else : ?>
                <table class="ch-moves">
                    <thead><tr><th><?php esc_html_e('Fecha', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Concepto', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Estado', 'cheppi-sellers'); ?></th><th class="ch-num"><?php esc_html_e('Monto', 'cheppi-sellers'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($movements as $m) :
                        $state = $m->payoutId !== null ? sprintf(__('Liquidación #%d', 'cheppi-sellers'), $m->payoutId)
                            : ($m->availableAt >= $held ? __('Por entregar', 'cheppi-sellers')
                            : ($m->availableAt > new DateTimeImmutable('now') ? sprintf(__('Libre el %s', 'cheppi-sellers'), wp_date('d/m', $m->availableAt->getTimestamp())) : __('Disponible', 'cheppi-sellers')));
                        ?>
                        <tr>
                            <td><?php echo esc_html(wp_date('d/m/Y', $m->createdAt->getTimestamp())); ?></td>
                            <td><?php echo esc_html($m->type->label()); ?><?php if ($m->orderId) : ?> · <a href="<?php echo esc_url(Routes::orderUrl($m->orderId)); ?>">#<?php echo (int) $m->orderId; ?></a><?php endif; ?><?php echo $m->note !== '' ? '<br><span class="ch-muted ch-small">' . esc_html($m->note) . '</span>' : ''; ?></td>
                            <td class="ch-muted ch-small"><?php echo esc_html($state); ?></td>
                            <td class="ch-num"><?php echo esc_html($soles($m->amount->cents)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>

    <div class="ch-grid ch-sticky">
        <form class="ch-card ch-stack" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(PanelTools::SAVE_BANK); ?>">
            <?php wp_nonce_field(PanelTools::SAVE_BANK); ?>
            <h2 class="ch-card__title"><?php esc_html_e('Cuenta bancaria', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-small"><?php esc_html_e('Debe estar a nombre de tu empresa o del titular del RUC.', 'cheppi-sellers'); ?></p>
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('Banco', 'cheppi-sellers'); ?></span>
                <select class="ch-input" name="bank" required>
                    <option value=""><?php esc_html_e('Elige', 'cheppi-sellers'); ?></option>
                    <?php foreach (BankDetails::BANKS as $code => $label) : ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected((string) ($values['bank'] ?? ''), $code); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="ch-field__error"><?php echo esc_html($errors['bank'] ?? ''); ?></span>
            </label>
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('Titular', 'cheppi-sellers'); ?></span>
                <input class="ch-input" name="holder" maxlength="120" required value="<?php echo esc_attr((string) ($values['holder'] ?? '')); ?>">
                <span class="ch-field__error"><?php echo esc_html($errors['holder'] ?? ''); ?></span>
            </label>
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('N.º de cuenta', 'cheppi-sellers'); ?> <small><?php esc_html_e('opcional', 'cheppi-sellers'); ?></small></span>
                <input class="ch-input" name="account" inputmode="numeric" maxlength="30" value="<?php echo esc_attr((string) ($values['account'] ?? '')); ?>">
                <span class="ch-field__error"><?php echo esc_html($errors['account'] ?? ''); ?></span>
            </label>
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('CCI (20 dígitos)', 'cheppi-sellers'); ?></span>
                <input class="ch-input" name="cci" inputmode="numeric" maxlength="30" required value="<?php echo esc_attr((string) ($values['cci'] ?? '')); ?>">
                <span class="ch-field__error"><?php echo esc_html($errors['cci'] ?? ''); ?></span>
            </label>
            <button class="ch-btn ch-btn--primary" type="submit"><?php esc_html_e('Guardar cuenta', 'cheppi-sellers'); ?></button>
        </form>
    </div>
</div>
