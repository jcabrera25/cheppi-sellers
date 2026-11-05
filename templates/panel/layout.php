<?php
/**
 * Panel document. Standalone: does not load the theme, only Cheppi assets.
 *
 * @var string $title
 * @var string $view
 * @var string $content
 * @var bool $withShell
 * @var WP_User $user
 * @var \Cheppi\Sellers\Infrastructure\WordPress\Assets $assets
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelController;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$css = $assets->url('panel.css');
$js = $assets->url('panel.js');
$vendor = $vendor ?? null;
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
    <?php if ($js) : ?><script type="module" src="<?php echo esc_url($js); ?>"></script><?php endif; ?>
    <?php wp_site_icon(); ?>
</head>
<body class="ch-app ch-view-<?php echo esc_attr($view); ?>">
<?php if ($withShell) : ?>
    <div class="ch-shell">
        <aside class="ch-sidebar" aria-label="<?php esc_attr_e('Menú del panel', 'cheppi-sellers'); ?>">
            <a class="ch-brand" href="<?php echo esc_url(home_url('/')); ?>">
                <span class="ch-brand__word">cheppi</span><span class="ch-brand__tag">MARKET</span>
            </a>
            <nav class="ch-nav">
                <a class="ch-nav__link" href="<?php echo esc_url(Routes::panelUrl()); ?>" aria-current="page"><?php echo Icons::svg('home'); ?><span><?php esc_html_e('Inicio', 'cheppi-sellers'); ?></span></a>
                <a class="ch-nav__link" aria-disabled="true"><?php echo Icons::svg('box'); ?><span><?php esc_html_e('Productos', 'cheppi-sellers'); ?></span><em class="ch-nav__soon"><?php esc_html_e('Pronto', 'cheppi-sellers'); ?></em></a>
                <a class="ch-nav__link" aria-disabled="true"><?php echo Icons::svg('receipt'); ?><span><?php esc_html_e('Pedidos', 'cheppi-sellers'); ?></span><em class="ch-nav__soon"><?php esc_html_e('Pronto', 'cheppi-sellers'); ?></em></a>
                <a class="ch-nav__link" aria-disabled="true"><?php echo Icons::svg('wallet'); ?><span><?php esc_html_e('Pagos', 'cheppi-sellers'); ?></span><em class="ch-nav__soon"><?php esc_html_e('Pronto', 'cheppi-sellers'); ?></em></a>
                <a class="ch-nav__link" aria-disabled="true"><?php echo Icons::svg('store'); ?><span><?php esc_html_e('Mi tienda', 'cheppi-sellers'); ?></span><em class="ch-nav__soon"><?php esc_html_e('Pronto', 'cheppi-sellers'); ?></em></a>
            </nav>
            <div class="ch-sidebar__foot">
                <a class="ch-nav__link" href="<?php echo esc_url('mailto:' . get_option('admin_email')); ?>"><?php echo Icons::svg('help'); ?><span><?php esc_html_e('Ayuda', 'cheppi-sellers'); ?></span></a>
                <a class="ch-nav__link" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php echo Icons::svg('logout'); ?><span><?php esc_html_e('Salir', 'cheppi-sellers'); ?></span></a>
            </div>
        </aside>
        <main class="ch-main" id="contenido">
            <header class="ch-topbar">
                <h1><?php echo esc_html($title); ?></h1>
                <div class="ch-user">
                    <span><?php echo esc_html($vendor ? $vendor->storeName : $user->display_name); ?></span>
                    <span class="ch-user__avatar" aria-hidden="true"><?php echo esc_html(PanelController::initials($user, $vendor)); ?></span>
                </div>
            </header>
            <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in its template ?>
        </main>
    </div>
<?php else : ?>
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in its template ?>
<?php endif; ?>
</body>
</html>
