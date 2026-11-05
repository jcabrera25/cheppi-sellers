<?php
/**
 * Seller dashboard (Inicio).
 *
 * @var \Cheppi\Sellers\Domain\Vendor\Vendor $vendor
 * @var list<array{id: int, type: \Cheppi\Sellers\Domain\Vendor\DocumentType, original_name: string, mime: string, size: int, uploaded_at: string}> $documents
 * @var \Cheppi\Sellers\Domain\Rules\BusinessRules $rules
 * @var string $notice
 * @var array<string, int> $byStatus
 * @var list<array{name: string, count: int}> $byCategory
 * @var int $lowStock
 * @var list<array{date: string, cents: int}> $salesByDay
 */

use Cheppi\Sellers\Domain\Payout\PayoutSchedule;
use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Front\ApplicationHandler;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Charts;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$status = $vendor->status;
$plan = $rules->plan($vendor->planId);
$commissionBps = $vendor->commissionOverrideBps ?? ($plan?->commissionBps ?? $rules->defaultCommissionBps);
$nextCutoff = (new PayoutSchedule($rules))->nextCutoff(new DateTimeImmutable('now', wp_timezone()));
$uploaded = array_map(static fn (array $d): string => $d['type']->value, $documents);

$hero = match ($status) {
    VendorStatus::Pending => ['clock', __('Tu solicitud está en revisión', 'cheppi-sellers'), __('La revisamos en un máximo de 2 días hábiles. Te avisaremos por correo.', 'cheppi-sellers'), 'ch-chip'],
    VendorStatus::NeedsInfo => ['alert', __('Necesitamos un dato más', 'cheppi-sellers'), __('Revisa el comentario del equipo y sube lo que falta.', 'cheppi-sellers'), 'ch-chip ch-chip--warning'],
    VendorStatus::Approved => ['check', __('¡Tu tienda está aprobada!', 'cheppi-sellers'), __('Muy pronto podrás subir tus productos desde aquí.', 'cheppi-sellers'), 'ch-chip ch-chip--success'],
    VendorStatus::Rejected => ['x', __('No pudimos aprobar tu tienda', 'cheppi-sellers'), __('Revisa el motivo. Si crees que es un error, escríbenos.', 'cheppi-sellers'), 'ch-chip ch-chip--danger'],
    VendorStatus::Suspended => ['alert', __('Tu tienda está suspendida', 'cheppi-sellers'), __('Tus productos no se muestran por ahora. Revisa el motivo.', 'cheppi-sellers'), 'ch-chip ch-chip--danger'],
};
?>
<?php if ($notice === 'solicitud' || $notice === 'documentos') : ?>
    <div class="ch-alert ch-alert--success" role="status" style="margin-bottom:16px">
        <?php echo Icons::svg('check'); ?>
        <p><?php echo esc_html($notice === 'solicitud' ? __('¡Listo! Recibimos tu solicitud.', 'cheppi-sellers') : __('Recibimos tus documentos. Tu solicitud volvió a revisión.', 'cheppi-sellers')); ?></p>
    </div>
<?php endif; ?>

<?php
$monthStart = (new DateTimeImmutable('first day of this month', wp_timezone()))->format('Y-m-d');
$monthCents = array_sum(array_map(static fn (array $r): int => $r['date'] >= $monthStart ? $r['cents'] : 0, $salesByDay));
$published = $byStatus['publish'] ?? 0;
$inReview = $byStatus['pending'] ?? 0;
?>
<section class="ch-grid ch-grid--stats" aria-label="<?php esc_attr_e('Resumen', 'cheppi-sellers'); ?>">
    <div class="ch-card ch-stat ch-stat--accent">
        <span class="ch-stat__label"><?php esc_html_e('Ventas del mes', 'cheppi-sellers'); ?></span>
        <span class="ch-stat__value"><?php echo esc_html('S/ ' . number_format($monthCents / 100, 2)); ?></span>
    </div>
    <div class="ch-card ch-stat">
        <span class="ch-stat__label"><?php esc_html_e('Productos publicados', 'cheppi-sellers'); ?></span>
        <span class="ch-stat__value"><?php echo (int) $published; ?></span>
        <?php if ($inReview > 0) : ?><span class="ch-chip ch-chip--warning"><?php echo esc_html(sprintf(_n('%d en revisión', '%d en revisión', $inReview, 'cheppi-sellers'), $inReview)); ?></span><?php endif; ?>
    </div>
    <div class="ch-card ch-stat">
        <span class="ch-stat__label"><?php esc_html_e('Stock bajo', 'cheppi-sellers'); ?></span>
        <span class="ch-stat__value"><?php echo (int) $lowStock; ?></span>
        <span class="ch-muted ch-small"><?php esc_html_e('3 unidades o menos', 'cheppi-sellers'); ?></span>
    </div>
    <div class="ch-card ch-stat">
        <span class="ch-stat__label"><?php esc_html_e('Estado de la tienda', 'cheppi-sellers'); ?></span>
        <span><span class="<?php echo esc_attr($hero[3]); ?>"><?php echo esc_html($status->label()); ?></span></span>
    </div>
</section>

<div class="ch-grid ch-grid--2" style="margin-top:16px">
    <div class="ch-grid">
        <section class="ch-card">
            <div class="ch-status">
                <span class="ch-status__icon"><?php echo Icons::svg($hero[0], 26); ?></span>
                <div>
                    <h2><?php echo esc_html($hero[1]); ?></h2>
                    <p class="ch-muted"><?php echo esc_html($hero[2]); ?></p>
                </div>
            </div>
            <?php if ($vendor->statusNote !== '' && $status !== VendorStatus::Approved) : ?>
                <div class="ch-alert ch-alert--warning" style="margin-top:16px">
                    <?php echo Icons::svg('alert'); ?>
                    <p><strong><?php esc_html_e('Comentario del equipo:', 'cheppi-sellers'); ?></strong> <?php echo nl2br(esc_html($vendor->statusNote)); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($status === VendorStatus::NeedsInfo) : ?>
                <form class="ch-form" style="margin-top:20px" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(ApplicationHandler::ACTION_RESUBMIT); ?>">
                    <?php wp_nonce_field(ApplicationHandler::ACTION_RESUBMIT, '_cheppi_nonce'); ?>
                    <?php foreach (DocumentType::cases() as $type) : ?>
                        <label class="ch-file" data-file>
                            <input type="file" name="<?php echo esc_attr('doc_' . $type->value); ?>" accept="application/pdf,image/jpeg,image/png,image/webp">
                            <span class="ch-file__icon"><?php echo Icons::svg('upload'); ?></span>
                            <span class="ch-file__text">
                                <span class="ch-file__name" data-file-name><?php echo esc_html($type->label()); ?></span>
                                <span class="ch-field__help" data-file-help><?php echo esc_html(in_array($type->value, $uploaded, true) ? __('Ya enviado. Sube otro solo si lo pidieron.', 'cheppi-sellers') : $type->help()); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <div class="ch-actions"><button type="submit" class="ch-btn ch-btn--primary"><?php esc_html_e('Enviar documentos', 'cheppi-sellers'); ?></button></div>
                </form>
            <?php endif; ?>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Ventas por día', 'cheppi-sellers'); ?></h2>
            <?php echo Charts::salesBars($salesByDay); // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped ?>
        </section>
        <?php if ($vendor->isPublic()) : ?>
            <section class="ch-card">
                <h2 class="ch-card__title"><?php esc_html_e('Productos por categoría', 'cheppi-sellers'); ?></h2>
                <?php if ($byCategory === []) : ?>
                    <p class="ch-muted"><?php esc_html_e('Cuando publiques productos verás aquí cómo se reparten.', 'cheppi-sellers'); ?></p>
                    <a class="ch-btn ch-btn--primary" href="<?php echo esc_url(Routes::productUrl()); ?>"><?php esc_html_e('Crear producto', 'cheppi-sellers'); ?></a>
                <?php else : ?>
                    <?php echo Charts::donut($byCategory, __('publicados', 'cheppi-sellers')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <div class="ch-grid">
        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Pendientes', 'cheppi-sellers'); ?></h2>
            <ul class="ch-tasks">
                <li class="ch-task" data-done="true"><span class="ch-task__mark"><?php echo Icons::svg('check', 16); ?></span><span><span class="ch-task__title"><?php esc_html_e('Enviar solicitud', 'cheppi-sellers'); ?></span><br><span class="ch-task__meta"><?php echo esc_html(wp_date(get_option('date_format'), $vendor->createdAt->getTimestamp())); ?></span></span></li>
                <li class="ch-task" data-done="<?php echo $status === VendorStatus::Approved ? 'true' : 'false'; ?>"><span class="ch-task__mark"><?php echo $status === VendorStatus::Approved ? Icons::svg('check', 16) : '2'; ?></span><span><span class="ch-task__title"><?php esc_html_e('Aprobación de Cheppi', 'cheppi-sellers'); ?></span><br><span class="ch-task__meta"><?php echo esc_html($status->label()); ?></span></span></li>
                <li class="ch-task" data-done="<?php echo in_array('sanitary_notice', $uploaded, true) ? 'true' : 'false'; ?>"><span class="ch-task__mark"><?php echo in_array('sanitary_notice', $uploaded, true) ? Icons::svg('check', 16) : '3'; ?></span><span><span class="ch-task__title"><?php esc_html_e('Notificación Sanitaria', 'cheppi-sellers'); ?></span><br><span class="ch-task__meta"><?php esc_html_e('Obligatoria para cosméticos', 'cheppi-sellers'); ?></span></span></li>
                <?php $hasProducts = array_sum($byStatus) > 0; ?>
                <li class="ch-task" data-done="<?php echo $hasProducts ? 'true' : 'false'; ?>"><span class="ch-task__mark"><?php echo $hasProducts ? Icons::svg('check', 16) : '4'; ?></span><span><span class="ch-task__title"><?php esc_html_e('Subir tu primer producto', 'cheppi-sellers'); ?></span><br><span class="ch-task__meta"><?php if ($vendor->isPublic()) : ?><a href="<?php echo esc_url(Routes::productUrl()); ?>"><?php esc_html_e('Crear producto', 'cheppi-sellers'); ?></a><?php else : ?><?php esc_html_e('Cuando te aprobemos', 'cheppi-sellers'); ?><?php endif; ?></span></span></li>
            </ul>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Tu plan', 'cheppi-sellers'); ?></h2>
            <dl class="ch-summary">
                <dt><?php esc_html_e('Plan', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html($plan?->name ?? $vendor->planId); ?></dd>
                <dt><?php esc_html_e('Comisión', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html(Percent::fromBps($commissionBps)); ?>%</dd>
                <dt><?php esc_html_e('Próximo corte', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html(wp_date(get_option('date_format'), $nextCutoff->getTimestamp())); ?></dd>
                <dt><?php esc_html_e('Tu tienda', 'cheppi-sellers'); ?></dt><dd><?php if ($vendor->isPublic()) : ?><a href="<?php echo esc_url(Routes::storeUrl($vendor->storeSlug)); ?>" target="_blank" rel="noopener"><?php echo esc_html('/tienda/' . $vendor->storeSlug); ?></a><?php else : ?><?php echo esc_html('/tienda/' . $vendor->storeSlug); ?><?php endif; ?></dd>
            </dl>
        </section>
    </div>
</div>
