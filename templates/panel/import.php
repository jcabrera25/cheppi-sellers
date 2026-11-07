<?php
/**
 * Bulk products from a spreadsheet.
 *
 * @var array<string, mixed> $flash
 */

use Cheppi\Sellers\Domain\Catalog\ProductSheet;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelTools;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$report = is_array($flash['report'] ?? null) ? $flash['report'] : [];
$sheetErrors = is_array($flash['sheetErrors'] ?? null) ? $flash['sheetErrors'] : [];
?>
<p><a class="ch-back" href="<?php echo esc_url(Routes::productsUrl()); ?>">← <?php esc_html_e('Productos', 'cheppi-sellers'); ?></a></p>
<div class="ch-grid ch-grid--2">
    <div class="ch-grid">
        <form class="ch-card ch-stack" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(PanelTools::IMPORT); ?>">
            <?php wp_nonce_field(PanelTools::IMPORT); ?>
            <h2 class="ch-card__title"><?php esc_html_e('Importar desde Excel', 'cheppi-sellers'); ?></h2>
            <ol class="ch-muted ch-steps-text">
                <li><?php esc_html_e('Descarga la plantilla y ábrela en Excel o Google Sheets.', 'cheppi-sellers'); ?></li>
                <li><?php esc_html_e('Una fila por producto. Si el SKU ya existe en tu tienda, se actualiza.', 'cheppi-sellers'); ?></li>
                <li><?php esc_html_e('Guárdalo como «CSV UTF-8» y súbelo aquí.', 'cheppi-sellers'); ?></li>
            </ol>
            <label class="ch-file" data-file>
                <input type="file" name="sheet" accept=".csv,text/csv" required>
                <span class="ch-file__icon"><?php echo Icons::svg('upload'); ?></span>
                <span class="ch-file__text"><span class="ch-file__name" data-file-name><?php esc_html_e('Archivo CSV', 'cheppi-sellers'); ?></span><span class="ch-field__help" data-file-help><?php echo esc_html(sprintf(__('Hasta %d productos, 2 MB', 'cheppi-sellers'), ProductSheet::MAX_ROWS)); ?></span></span>
            </label>
            <div class="ch-actions">
                <a class="ch-btn ch-btn--ghost" href="<?php echo esc_url(PanelTools::downloadUrl(PanelTools::TEMPLATE)); ?>"><?php esc_html_e('Descargar plantilla', 'cheppi-sellers'); ?></a>
                <button class="ch-btn ch-btn--primary" type="submit"><?php esc_html_e('Importar', 'cheppi-sellers'); ?></button>
            </div>
        </form>

        <?php if ($report !== [] || $sheetErrors !== []) : ?>
            <section class="ch-card">
                <h2 class="ch-card__title"><?php esc_html_e('Resultado', 'cheppi-sellers'); ?></h2>
                <?php foreach ($sheetErrors as $error) : ?><div class="ch-alert ch-alert--warning ch-mb"><p><?php echo esc_html((string) $error); ?></p></div><?php endforeach; ?>
                <table class="ch-moves">
                    <thead><tr><th><?php esc_html_e('Fila', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Resultado', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Detalle', 'cheppi-sellers'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($report as [$line, $kind, $detail]) : ?>
                        <tr><td><?php echo (int) $line; ?></td><td><span class="ch-chip <?php echo $kind === 'error' ? 'ch-chip--danger' : ($kind === 'aviso' ? 'ch-chip--warning' : 'ch-chip--success'); ?>"><?php echo esc_html((string) $kind); ?></span></td><td><?php echo esc_html((string) $detail); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    </div>
    <div class="ch-grid ch-sticky">
        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Columnas', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-small"><code><?php echo esc_html(implode(' · ', ProductSheet::HEADERS)); ?></code></p>
            <p class="ch-muted ch-small"><?php esc_html_e('Obligatorias: nombre y precio. «categoria» con el nombre exacto de una categoría de Cheppi. «imagen_url» con un enlace público a la foto (JPG, PNG o WebP); sin foto el producto queda como borrador.', 'cheppi-sellers'); ?></p>
            <p class="ch-muted ch-small"><?php esc_html_e('Los productos con opciones (tonos, tamaños) se crean desde el formulario.', 'cheppi-sellers'); ?></p>
            <a class="ch-btn ch-btn--ghost" href="<?php echo esc_url(PanelTools::downloadUrl(PanelTools::EXPORT)); ?>"><?php esc_html_e('Exportar mis productos', 'cheppi-sellers'); ?></a>
        </section>
    </div>
</div>
