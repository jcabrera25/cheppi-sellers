<?php
/**
 * One vendor: data (view + edit), documents (view, upload, replace, delete), decision, and delete.
 *
 * @var \Cheppi\Sellers\Domain\Vendor\Vendor $vendor
 * @var WP_User|false $user
 * @var list<array{id: int, type: \Cheppi\Sellers\Domain\Vendor\DocumentType, original_name: string, mime: string, size: int, uploaded_at: string}> $documents
 * @var \Cheppi\Sellers\Domain\Rules\BusinessRules $rules
 * @var array<string, mixed> $flash
 * @var array<string, string> $errors
 * @var array<string, string> $values
 * @var array{review: string, update: string, docs: string, delete: string} $actions
 * @var callable(int): string $downloadUrl
 * @var string $listUrl
 */

use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Domain\Shipping\LimaDistricts;
use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\StoreCategory;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;

defined('ABSPATH') || exit;

$chip = match ($vendor->status) {
    VendorStatus::Approved => 'ch-chip ch-chip--success',
    VendorStatus::NeedsInfo => 'ch-chip ch-chip--warning',
    VendorStatus::Rejected, VendorStatus::Suspended => 'ch-chip ch-chip--danger',
    VendorStatus::Pending => 'ch-chip',
};
$byType = [];
foreach ($documents as $doc) {
    $byType[$doc['type']->value] = $doc;
}
$decisionLabels = [
    VendorStatus::Approved->value => __('Aprobar tienda', 'cheppi-sellers'),
    VendorStatus::NeedsInfo->value => __('Pedir corrección o documento', 'cheppi-sellers'),
    VendorStatus::Rejected->value => __('Rechazar', 'cheppi-sellers'),
    VendorStatus::Suspended->value => __('Suspender', 'cheppi-sellers'),
    VendorStatus::Pending->value => __('Volver a revisión', 'cheppi-sellers'),
];
$allowed = array_map(static fn (VendorStatus $s): string => $s->value, $vendor->status->allowedTransitions());
$open = (string) ($flash['open'] ?? '');
// Edit form starts from the stored data, or from what the admin typed if saving failed.
$values = $values !== [] ? $values : [
    'store_name' => $vendor->storeName, 'store_slug' => $vendor->storeSlug, 'ruc' => $vendor->ruc,
    'phone' => $vendor->phone, 'legal_name' => $vendor->legalName, 'category' => $vendor->category,
    'district' => $vendor->district, 'instagram' => $vendor->instagram, 'website' => $vendor->website,
];
$id = (int) $vendor->id;
?>
<div class="wrap ch-admin">
    <p><a href="<?php echo esc_url($listUrl); ?>">&larr; <?php esc_html_e('Vendedores', 'cheppi-sellers'); ?></a></p>
    <header class="ch-admin__head ch-admin__head--row">
        <div>
            <h1><?php echo esc_html($vendor->storeName); ?></h1>
            <p class="ch-muted"><?php echo esc_html($vendor->legalName . ' · RUC ' . $vendor->ruc); ?></p>
        </div>
        <span class="<?php echo esc_attr($chip); ?>"><?php echo esc_html($vendor->status->label()); ?></span>
    </header>

    <?php if (isset($flash['message'])) : ?>
        <div class="notice notice-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html((string) $flash['message']); ?></p></div>
    <?php endif; ?>

    <div class="ch-grid ch-grid--2">
        <div class="ch-grid">
            <section class="ch-card">
                <div class="ch-card__head">
                    <h2 class="ch-card__title"><?php esc_html_e('Datos de la tienda', 'cheppi-sellers'); ?></h2>
                </div>
                <dl class="ch-summary">
                    <dt><?php esc_html_e('Cuenta', 'cheppi-sellers'); ?></dt><dd><?php echo $user ? esc_html($user->display_name . ' · ' . $user->user_email) : '—'; ?></dd>
                    <dt><?php esc_html_e('Celular', 'cheppi-sellers'); ?></dt><dd><a href="<?php echo esc_url('https://wa.me/51' . substr($vendor->phone, -9)); ?>" target="_blank" rel="noopener"><?php echo esc_html($vendor->phone); ?></a></dd>
                    <dt><?php esc_html_e('Tipo', 'cheppi-sellers'); ?></dt><dd><?php echo str_starts_with($vendor->ruc, '10') ? esc_html__('Persona natural con negocio', 'cheppi-sellers') : esc_html__('Empresa', 'cheppi-sellers'); ?></dd>
                    <dt><?php esc_html_e('Categoría', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html(StoreCategory::label($vendor->category)); ?></dd>
                    <dt><?php esc_html_e('Despacha desde', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html(LimaDistricts::label($vendor->district)); ?></dd>
                    <dt>Instagram</dt><dd><?php echo $vendor->instagram !== '' ? '<a href="' . esc_url('https://instagram.com/' . $vendor->instagram) . '" target="_blank" rel="noopener">@' . esc_html($vendor->instagram) . '</a>' : '—'; ?></dd>
                    <dt><?php esc_html_e('Web', 'cheppi-sellers'); ?></dt><dd><?php echo $vendor->website !== '' ? '<a href="' . esc_url($vendor->website) . '" target="_blank" rel="noopener">' . esc_html($vendor->website) . '</a>' : '—'; ?></dd>
                    <dt><?php esc_html_e('Tienda', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html('/tienda/' . $vendor->storeSlug); ?></dd>
                    <dt><?php esc_html_e('Alta', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $vendor->createdAt->getTimestamp())); ?></dd>
                </dl>
                <p class="ch-muted ch-note"><?php printf(
                    /* translators: %s: SUNAT link */
                    esc_html__('Verifica que el RUC esté activo y habido en %s.', 'cheppi-sellers'),
                    '<a href="https://e-consultaruc.sunat.gob.pe/" target="_blank" rel="noopener">SUNAT</a>'
                ); ?></p>

                <details class="ch-disclosure" <?php echo $open === 'edit' ? 'open' : ''; ?>>
                    <summary class="ch-btn ch-btn--ghost ch-btn--sm"><?php esc_html_e('Editar datos', 'cheppi-sellers'); ?></summary>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-grid ch-disclosure__body">
                        <input type="hidden" name="action" value="<?php echo esc_attr($actions['update']); ?>">
                        <input type="hidden" name="vendor_id" value="<?php echo $id; ?>">
                        <?php wp_nonce_field($actions['update'] . $id); ?>
                        <?php $showSlug = true; require __DIR__ . '/_vendor-fields.php'; ?>
                        <div><button type="submit" class="ch-btn ch-btn--primary"><?php esc_html_e('Guardar datos', 'cheppi-sellers'); ?></button></div>
                    </form>
                </details>
            </section>

            <section class="ch-card">
                <h2 class="ch-card__title"><?php esc_html_e('Documentos', 'cheppi-sellers'); ?></h2>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-grid" data-docs-form>
                    <input type="hidden" name="action" value="<?php echo esc_attr($actions['docs']); ?>">
                    <input type="hidden" name="vendor_id" value="<?php echo $id; ?>">
                    <?php wp_nonce_field($actions['docs'] . $id); ?>
                    <ul class="ch-tasks">
                        <?php foreach (DocumentType::cases() as $type) : $doc = $byType[$type->value] ?? null; ?>
                            <li class="ch-task ch-doc" data-done="<?php echo $doc ? 'true' : 'false'; ?>">
                                <span class="ch-task__mark"><?php echo $doc ? '✓' : '–'; ?></span>
                                <span class="ch-doc__info">
                                    <span class="ch-task__title"><?php echo esc_html($type->label()); ?><?php echo $type->isRequired() ? '' : ' <small class="ch-muted">(' . esc_html__('opcional', 'cheppi-sellers') . ')</small>'; ?></span>
                                    <span class="ch-task__meta"><?php echo $doc ? esc_html($doc['original_name'] . ' · ' . size_format($doc['size'])) : esc_html__('No enviado', 'cheppi-sellers'); ?></span>
                                </span>
                                <span class="ch-doc__actions">
                                    <?php if ($doc) : ?>
                                        <a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url($downloadUrl($doc['id'])); ?>" target="_blank" rel="noopener"><?php esc_html_e('Ver', 'cheppi-sellers'); ?></a>
                                    <?php endif; ?>
                                    <label class="ch-btn ch-btn--ghost ch-btn--sm ch-upload-btn">
                                        <input type="file" name="<?php echo esc_attr('doc_' . $type->value); ?>" accept="application/pdf,image/jpeg,image/png,image/webp" data-doc-input>
                                        <span data-doc-label><?php echo $doc ? esc_html__('Reemplazar', 'cheppi-sellers') : esc_html__('Subir', 'cheppi-sellers'); ?></span>
                                    </label>
                                    <?php if ($doc) : ?>
                                        <label class="ch-check ch-doc__remove"><input type="checkbox" name="remove[]" value="<?php echo esc_attr($type->value); ?>"> <span><?php esc_html_e('Eliminar', 'cheppi-sellers'); ?></span></label>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div><button type="submit" class="ch-btn ch-btn--secondary"><?php esc_html_e('Guardar documentos', 'cheppi-sellers'); ?></button></div>
                </form>
            </section>
        </div>

        <div class="ch-grid">
            <section class="ch-card">
                <h2 class="ch-card__title"><?php esc_html_e('Decisión y condiciones', 'cheppi-sellers'); ?></h2>
                <?php if ($vendor->statusNote !== '') : ?>
                    <div class="ch-alert ch-alert--warning ch-mb"><p><strong><?php esc_html_e('Último comentario:', 'cheppi-sellers'); ?></strong> <?php echo nl2br(esc_html($vendor->statusNote)); ?></p></div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-review" data-review-form>
                    <input type="hidden" name="action" value="<?php echo esc_attr($actions['review']); ?>">
                    <input type="hidden" name="vendor_id" value="<?php echo $id; ?>">
                    <?php wp_nonce_field($actions['review'] . $id); ?>

                    <fieldset class="ch-review__options">
                        <legend class="ch-field__label"><?php esc_html_e('Estado', 'cheppi-sellers'); ?></legend>
                        <label class="ch-check"><input type="radio" name="next_status" value="" checked> <span><?php echo esc_html(sprintf(__('Mantener: %s', 'cheppi-sellers'), $vendor->status->label())); ?></span></label>
                        <?php foreach ($allowed as $value) : ?>
                            <label class="ch-check"><input type="radio" name="next_status" value="<?php echo esc_attr($value); ?>" data-needs-note="<?php echo in_array($value, ['needs_info', 'rejected', 'suspended'], true) ? 'true' : 'false'; ?>"> <span><?php echo esc_html($decisionLabels[$value] ?? $value); ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>

                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Comentario para el vendedor', 'cheppi-sellers'); ?></span>
                        <textarea class="ch-input" name="note" rows="4" data-note placeholder="<?php esc_attr_e('Obligatorio si pides corrección, rechazas o suspendes. Lo recibe por correo.', 'cheppi-sellers'); ?>"></textarea>
                    </label>

                    <div class="ch-row">
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Plan', 'cheppi-sellers'); ?></span>
                            <select class="ch-input" name="plan_id">
                                <?php foreach ($rules->plans as $plan) : ?>
                                    <option value="<?php echo esc_attr($plan->id); ?>" <?php selected($vendor->planId, $plan->id); ?>><?php echo esc_html($plan->name . ' · ' . Percent::fromBps($plan->commissionBps) . '%'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Comisión especial (%)', 'cheppi-sellers'); ?></span>
                            <input class="ch-input" name="commission_override" inputmode="decimal" placeholder="<?php esc_attr_e('Vacío = la del plan', 'cheppi-sellers'); ?>" value="<?php echo esc_attr($vendor->commissionOverrideBps === null ? '' : Percent::fromBps($vendor->commissionOverrideBps)); ?>">
                        </label>
                    </div>

                    <button type="submit" class="ch-btn ch-btn--primary"><?php esc_html_e('Guardar decisión', 'cheppi-sellers'); ?></button>
                </form>
            </section>

            <?php do_action('cheppi_sellers_vendor_detail', $vendor); // extra cards: premium page, payouts ?>

            <section class="ch-card ch-danger">
                <h2 class="ch-card__title"><?php esc_html_e('Eliminar vendedor', 'cheppi-sellers'); ?></h2>
                <p class="ch-muted"><?php esc_html_e('Borra la tienda y sus documentos. La cuenta de usuario se conserva (puede seguir comprando). No se puede deshacer; si la tienda ya tiene ventas o pagos, suspéndela en lugar de eliminarla.', 'cheppi-sellers'); ?></p>
                <details class="ch-disclosure" <?php echo $open === 'delete' ? 'open' : ''; ?>>
                    <summary class="ch-btn ch-btn--danger ch-btn--sm"><?php esc_html_e('Eliminar esta tienda', 'cheppi-sellers'); ?></summary>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-grid ch-disclosure__body" data-delete-form>
                        <input type="hidden" name="action" value="<?php echo esc_attr($actions['delete']); ?>">
                        <input type="hidden" name="vendor_id" value="<?php echo $id; ?>">
                        <?php wp_nonce_field($actions['delete'] . $id); ?>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php echo esc_html(sprintf(__('Escribe «%s» para confirmar', 'cheppi-sellers'), $vendor->storeName)); ?></span>
                            <input class="ch-input" name="confirm_name" autocomplete="off" required data-confirm-name data-expected="<?php echo esc_attr($vendor->storeName); ?>">
                        </label>
                        <div><button type="submit" class="ch-btn ch-btn--danger" data-confirm-submit disabled><?php esc_html_e('Eliminar definitivamente', 'cheppi-sellers'); ?></button></div>
                    </form>
                </details>
            </section>
        </div>
    </div>
</div>
