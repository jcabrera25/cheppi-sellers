<?php
/**
 * Vendors list.
 *
 * @var list<\Cheppi\Sellers\Domain\Vendor\Vendor> $vendors
 * @var array<string, int> $counts
 * @var ?\Cheppi\Sellers\Domain\Vendor\VendorStatus $status
 * @var \Cheppi\Sellers\Domain\Rules\BusinessRules $rules
 * @var int $paged
 * @var string $page
 * @var string $search
 * @var array<string, mixed> $flash
 * @var string $newUrl
 */

use Cheppi\Sellers\Domain\Vendor\StoreCategory;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\VendorsPage;

use Cheppi\Sellers\Infrastructure\WordPress\Front\ManageContext;

defined('ABSPATH') || exit;

$chip = static fn (VendorStatus $s): string => match ($s) {
    VendorStatus::Approved => 'ch-chip ch-chip--success',
    VendorStatus::NeedsInfo => 'ch-chip ch-chip--warning',
    VendorStatus::Rejected, VendorStatus::Suspended => 'ch-chip ch-chip--danger',
    VendorStatus::Pending => 'ch-chip',
};
$total = array_sum($counts);
$tabs = ['' => [__('Todos', 'cheppi-sellers'), $total]];
foreach (VendorStatus::cases() as $case) {
    $tabs[$case->value] = [$case->label(), $counts[$case->value] ?? 0];
}
$current = $status?->value ?? '';
?>
<div class="wrap ch-admin">
    <header class="ch-admin__head ch-admin__head--row">
        <div>
            <h1><?php esc_html_e('Vendedores y marcas', 'cheppi-sellers'); ?></h1>
            <p class="ch-muted"><?php esc_html_e('Revisa solicitudes, añade marcas, edita sus datos y documentos, o elimínalas.', 'cheppi-sellers'); ?></p>
        </div>
        <a class="ch-btn ch-btn--primary" href="<?php echo esc_url($newUrl); ?>">+ <?php esc_html_e('Añadir vendedor', 'cheppi-sellers'); ?></a>
    </header>

    <?php if (isset($flash['message'])) : ?>
        <div class="notice notice-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html((string) $flash['message']); ?></p></div>
    <?php endif; ?>

    <div class="ch-grid ch-grid--stats ch-admin__stats">
        <div class="ch-card ch-stat ch-stat--accent"><span class="ch-stat__label"><?php esc_html_e('Por revisar', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo (int) ($counts['pending'] ?? 0); ?></span></div>
        <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Aprobados', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo (int) ($counts['approved'] ?? 0); ?></span></div>
        <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Esperando documentos', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo (int) ($counts['needs_info'] ?? 0); ?></span></div>
        <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Total', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo (int) $total; ?></span></div>
    </div>

    <div class="ch-toolbar">
    <nav class="ch-tabs" aria-label="<?php esc_attr_e('Filtrar por estado', 'cheppi-sellers'); ?>">
        <?php foreach ($tabs as $value => [$label, $count]) : ?>
            <a class="ch-tab" href="<?php echo esc_url(ManageContext::url($page, $value !== '' ? ['estado' => $value] : [])); ?>" <?php echo $current === $value ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html($label); ?> <span><?php echo (int) $count; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <form method="get" class="ch-search" role="search" action="<?php echo esc_url(strtok(ManageContext::url($page), '?')); ?>">
        <?php if (!ManageContext::useFront()) : ?><input type="hidden" name="page" value="<?php echo esc_attr($page); ?>"><?php endif; ?>
        <?php if ($current !== '') : ?><input type="hidden" name="estado" value="<?php echo esc_attr($current); ?>"><?php endif; ?>
        <input class="ch-input" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar tienda, razón social o RUC', 'cheppi-sellers'); ?>" aria-label="<?php esc_attr_e('Buscar vendedores', 'cheppi-sellers'); ?>">
    </form>
    </div>

    <div class="ch-card ch-admin__table-card">
        <?php if ($vendors === []) : ?>
            <div class="ch-empty">
                <p class="ch-muted"><?php echo $search !== '' ? esc_html(sprintf(__('No hay resultados para «%s».', 'cheppi-sellers'), $search)) : esc_html__('No hay vendedores en este estado.', 'cheppi-sellers'); ?></p>
                <a class="ch-btn ch-btn--ghost" href="<?php echo esc_url($newUrl); ?>"><?php esc_html_e('Añadir vendedor', 'cheppi-sellers'); ?></a>
            </div>
        <?php else : ?>
            <table class="ch-table-clean">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Tienda', 'cheppi-sellers'); ?></th>
                        <th>RUC</th>
                        <th><?php esc_html_e('Categoría', 'cheppi-sellers'); ?></th>
                        <th><?php esc_html_e('Plan', 'cheppi-sellers'); ?></th>
                        <th><?php esc_html_e('Estado', 'cheppi-sellers'); ?></th>
                        <th><?php esc_html_e('Solicitud', 'cheppi-sellers'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v) : ?>
                        <tr>
                            <td data-label="<?php esc_attr_e('Tienda', 'cheppi-sellers'); ?>">
                                <a class="ch-strong-link" href="<?php echo esc_url(VendorsPage::detailUrl((int) $v->id)); ?>"><?php echo esc_html($v->storeName); ?></a>
                                <br><span class="ch-muted"><?php echo esc_html($v->legalName); ?></span>
                            </td>
                            <td data-label="RUC"><?php echo esc_html($v->ruc); ?></td>
                            <td data-label="<?php esc_attr_e('Categoría', 'cheppi-sellers'); ?>"><?php echo esc_html(StoreCategory::label($v->category)); ?></td>
                            <td data-label="<?php esc_attr_e('Plan', 'cheppi-sellers'); ?>"><?php echo esc_html($rules->plan($v->planId)?->name ?? $v->planId); ?></td>
                            <td data-label="<?php esc_attr_e('Estado', 'cheppi-sellers'); ?>"><span class="<?php echo esc_attr($chip($v->status)); ?>"><?php echo esc_html($v->status->label()); ?></span></td>
                            <td data-label="<?php esc_attr_e('Solicitud', 'cheppi-sellers'); ?>"><?php echo esc_html(wp_date(get_option('date_format'), $v->createdAt->getTimestamp())); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $shown = $current === '' ? $total : ($counts[$current] ?? 0);
            if ($search === '' && $shown > 20) :
                echo '<div class="ch-pager">' . wp_kses_post((string) paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $paged,
                    'total' => (int) ceil($shown / 20),
                ])) . '</div>';
            endif;
            ?>
        <?php endif; ?>
    </div>
</div>
