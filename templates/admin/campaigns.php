<?php
/**
 * @var list<\Cheppi\Sellers\Domain\Promotion\Campaign> $campaigns
 * @var array<string, int> $members
 * @var array<string, mixed> $flash
 */

use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\CampaignsPage;

defined('ABSPATH') || exit;

$values = is_array($flash['values'] ?? null) ? $flash['values'] : [];
$now = new DateTimeImmutable('now', wp_timezone());
?>
<div class="wrap ch-admin">
    <header class="ch-admin__head">
        <h1><?php esc_html_e('Campañas', 'cheppi-sellers'); ?></h1>
        <p class="ch-muted"><?php esc_html_e('Los vendedores que se unen pagan la comisión de la campaña en las ventas hechas durante esas fechas (si es menor que la suya).', 'cheppi-sellers'); ?></p>
    </header>
    <?php if (isset($flash['message'])) : ?>
        <div class="notice notice-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html((string) $flash['message']); ?></p></div>
    <?php endif; ?>
    <div class="ch-grid ch-grid--2">
        <div class="ch-card ch-admin__table-card">
            <?php if ($campaigns === []) : ?>
                <div class="ch-empty"><p class="ch-muted"><?php esc_html_e('Aún no hay campañas.', 'cheppi-sellers'); ?></p></div>
            <?php else : ?>
                <table class="ch-table-clean">
                    <thead><tr><th><?php esc_html_e('Campaña', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Fechas', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Comisión', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Vendedores', 'cheppi-sellers'); ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($campaigns as $c) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($c->name); ?></strong><?php if ($c->isActive($now)) : ?> <span class="ch-chip ch-chip--success"><?php esc_html_e('En curso', 'cheppi-sellers'); ?></span><?php elseif ($c->isOver($now)) : ?> <span class="ch-chip ch-chip--neutral"><?php esc_html_e('Terminada', 'cheppi-sellers'); ?></span><?php endif; ?><br><span class="ch-muted"><?php echo esc_html($c->description); ?></span></td>
                            <td><?php echo esc_html($c->startsOn->format('d/m/Y') . ' – ' . $c->endsOn->format('d/m/Y')); ?></td>
                            <td><?php echo esc_html(Percent::fromBps($c->commissionBps)); ?>%</td>
                            <td><?php echo (int) ($members[$c->id] ?? 0); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('¿Eliminar la campaña?', 'cheppi-sellers')); ?>')">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(CampaignsPage::ACTION_DELETE); ?>">
                                    <input type="hidden" name="campaign" value="<?php echo esc_attr($c->id); ?>">
                                    <?php wp_nonce_field(CampaignsPage::ACTION_DELETE . $c->id); ?>
                                    <button class="ch-btn ch-btn--ghost ch-btn--sm" type="submit"><?php esc_html_e('Eliminar', 'cheppi-sellers'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <form class="ch-card ch-grid" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(CampaignsPage::ACTION_SAVE); ?>">
            <?php wp_nonce_field(CampaignsPage::ACTION_SAVE); ?>
            <h2 class="ch-card__title"><?php esc_html_e('Nueva campaña', 'cheppi-sellers'); ?></h2>
            <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Nombre', 'cheppi-sellers'); ?></span><input class="ch-input" name="name" required maxlength="60" value="<?php echo esc_attr((string) ($values['name'] ?? '')); ?>" placeholder="Cyber Wow"></label>
            <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Descripción', 'cheppi-sellers'); ?></span><input class="ch-input" name="description" maxlength="300" value="<?php echo esc_attr((string) ($values['description'] ?? '')); ?>"></label>
            <div class="ch-row">
                <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Inicio', 'cheppi-sellers'); ?></span><input class="ch-input" type="date" name="starts_on" required value="<?php echo esc_attr((string) ($values['starts_on'] ?? '')); ?>"></label>
                <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Fin', 'cheppi-sellers'); ?></span><input class="ch-input" type="date" name="ends_on" required value="<?php echo esc_attr((string) ($values['ends_on'] ?? '')); ?>"></label>
            </div>
            <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Comisión de campaña (%)', 'cheppi-sellers'); ?></span><input class="ch-input" name="commission" inputmode="decimal" required value="<?php echo esc_attr((string) ($values['commission'] ?? '')); ?>" placeholder="5"></label>
            <div><button class="ch-btn ch-btn--primary" type="submit"><?php esc_html_e('Crear campaña', 'cheppi-sellers'); ?></button></div>
        </form>
    </div>
</div>
