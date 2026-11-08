<?php
/**
 * Panel document. Standalone: does not load the theme, only Cheppi assets.
 *
 * @var string $title
 * @var string $view
 * @var string $content
 * @var bool $withShell
 * @var ?string $nav
 * @var ?\Cheppi\Sellers\Domain\Vendor\Vendor $vendor
 * @var WP_User $user
 * @var \Cheppi\Sellers\Infrastructure\WordPress\Assets $assets
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\Branding;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelController;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$flash = $flash ?? [];
$css = $assets->url('panel.css');
$js = $assets->url('panel.js');
$extraCss = $extraCss ?? [];   // management panel: admin.css
$extraJs = $extraJs ?? [];
$navItems = $navItems ?? null; // [key => [icon, label, url, enabled]]; null = vendor menu
$footItems = $footItems ?? null;
$userLabel = $userLabel ?? null;
$bodyClass = $bodyClass ?? '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#6D3BFF">
    <title><?php echo esc_html($title . ' · Cheppi Market'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&display=swap">
    <?php if ($css) : ?><link rel="stylesheet" href="<?php echo esc_url($css); ?>"><?php endif; ?>
    <?php foreach ($extraCss as $file) : ?><link rel="stylesheet" href="<?php echo esc_url((string) $assets->url($file)); ?>"><?php endforeach; ?>
    <?php if ($js) : ?><script type="module" src="<?php echo esc_url($js); ?>"></script><?php endif; ?>
    <?php foreach ($extraJs as $file) : ?><script type="module" src="<?php echo esc_url((string) $assets->url($file)); ?>"></script><?php endforeach; ?>
    <?php wp_site_icon(); ?>
</head>
<body class="ch-app ch-view-<?php echo esc_attr($view); ?> <?php echo esc_attr($bodyClass); ?>">
<?php if ($withShell) : ?>
    <div class="ch-shell">
        <aside class="ch-sidebar" aria-label="<?php esc_attr_e('Menú del panel', 'cheppi-sellers'); ?>">
            <?php echo Branding::logo(); // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped ?>
            <nav class="ch-nav">
                <?php
                $open = $vendor !== null && $vendor->isPublic();
                $items = $navItems ?? [
                    'inicio' => ['home', __('Inicio', 'cheppi-sellers'), Routes::panelUrl(), true],
                    'productos' => ['box', __('Productos', 'cheppi-sellers'), Routes::productsUrl(), $open],
                    'pedidos' => ['receipt', __('Pedidos', 'cheppi-sellers'), Routes::ordersUrl(), $open],
                    'envios' => ['truck', __('Envíos', 'cheppi-sellers'), Routes::shippingUrl(), $open],
                    'pagos' => ['wallet', __('Pagos', 'cheppi-sellers'), Routes::paymentsUrl(), $open],
                    'promociones' => ['tag', __('Promociones', 'cheppi-sellers'), Routes::promotionsUrl(), $open],
                    'metricas' => ['chart', __('Métricas', 'cheppi-sellers'), Routes::metricsUrl(), $open],
                    'mi-tienda' => ['store', __('Mi tienda', 'cheppi-sellers'), Routes::storeSettingsUrl(), $open],
                ];
                foreach ($items as $key => [$icon, $label, $url, $enabled]) :
                    if ($enabled) : ?>
                        <a class="ch-nav__link" href="<?php echo esc_url($url); ?>" <?php echo $nav === $key ? 'aria-current="page"' : ''; ?>><?php echo Icons::svg($icon); ?><span><?php echo esc_html($label); ?></span></a>
                    <?php else : ?>
                        <span class="ch-nav__link" aria-disabled="true"><?php echo Icons::svg($icon); ?><span><?php echo esc_html($label); ?></span><em class="ch-nav__soon"><?php esc_html_e('Pronto', 'cheppi-sellers'); ?></em></span>
                    <?php endif;
                endforeach; ?>
            </nav>
            <div class="ch-sidebar__foot">
                <?php foreach ($footItems ?? [] as [$icon, $label, $url]) : ?>
                    <a class="ch-nav__link" href="<?php echo esc_url($url); ?>"><?php echo Icons::svg($icon); ?><span><?php echo esc_html($label); ?></span></a>
                <?php endforeach; ?>
                <?php if ($footItems === null) : ?>
<?php if ($vendor !== null && $vendor->isPublic()) : ?>
                    <a class="ch-nav__link" href="<?php echo esc_url(Routes::storeUrl($vendor->storeSlug)); ?>" target="_blank" rel="noopener"><?php echo Icons::svg('store'); ?><span><?php esc_html_e('Ver mi tienda', 'cheppi-sellers'); ?></span></a>
                <?php endif; ?>
                <a class="ch-nav__link" href="<?php echo esc_url('mailto:' . get_option('admin_email')); ?>"><?php echo Icons::svg('help'); ?><span><?php esc_html_e('Ayuda', 'cheppi-sellers'); ?></span></a>
                <a class="ch-nav__link" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php echo Icons::svg('logout'); ?><span><?php esc_html_e('Salir', 'cheppi-sellers'); ?></span></a>
                <?php endif; ?>
            </div>
        </aside>
        <main class="ch-main" id="contenido">
            <header class="ch-topbar">
                <h1><?php echo esc_html($title); ?></h1>
                <div class="ch-user">
                    <span><?php echo esc_html($userLabel ?? ($vendor ? $vendor->storeName : $user->display_name)); ?></span>
                    <span class="ch-user__avatar" aria-hidden="true"><?php echo esc_html(PanelController::initials($user, $vendor)); ?></span>
                </div>
            </header>
            <?php if (!empty($flash['message'])) : ?>
                <div class="ch-alert ch-alert--<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'danger'; ?> ch-flash" role="status">
                    <?php echo Icons::svg(($flash['type'] ?? '') === 'success' ? 'check' : 'alert'); ?><p><?php echo esc_html((string) $flash['message']); ?></p>
                </div>
            <?php endif; ?>
            <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in its template ?>
        </main>
    </div>
<?php else : ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in its template ?>
<?php endif; ?>
</body>
</html>
