<?php
/**
 * One customer: contact details, summary and every order (in the seller panel, only the store's part),
 * with the orders table's filters, print and PDF / Excel / CSV export.
 *
 * @var array<string, mixed> $customer from WpCustomers::find()
 * @var bool $manage
 * @var array<int, string> $stores
 * @var string $listUrl
 * Plus the variables of templates/panel/_orders-table.php.
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;

defined('ABSPATH') || exit;

$c = $customer;
$money = static fn (int $cents): string => 'S/ ' . number_format($cents / 100, 2);
$initials = strtoupper(mb_substr($c['first_name'] ?: ($c['email'] ?: '?'), 0, 1) . mb_substr($c['last_name'], 0, 1));
$fields = [
    [__('Correo', 'cheppi-sellers'), $c['email'] !== '' ? '<a href="mailto:' . esc_attr($c['email']) . '">' . esc_html($c['email']) . '</a>' : '—'],
    [__('Nombre', 'cheppi-sellers'), esc_html($c['first_name'] ?: '—')],
    [__('Apellido', 'cheppi-sellers'), esc_html($c['last_name'] ?: '—')],
    [__('Teléfono', 'cheppi-sellers'), $c['phone'] !== '' ? '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $c['phone'])) . '">' . esc_html($c['phone']) . '</a>' : '—'],
    [__('Usuario', 'cheppi-sellers'), $c['user'] ? esc_html($c['user']->user_login) : esc_html__('Compró como invitado', 'cheppi-sellers')],
    [__('Ubicación', 'cheppi-sellers'), esc_html($c['location'] ?: '—')],
];
if ($c['user']) {
    $fields[] = [__('Registrado', 'cheppi-sellers'), esc_html(wp_date('d/m/Y', strtotime($c['user']->user_registered . ' UTC')))];
}
if ($manage) {
    $fields[] = [__('Compró en', 'cheppi-sellers'), esc_html(implode(', ', array_map(static fn (int $id): string => $stores[$id] ?? '#' . $id, $c['vendors'])))];
}
?>
<p class="ch-no-print"><a href="<?php echo esc_url($listUrl); ?>">← <?php esc_html_e('Todos los clientes', 'cheppi-sellers'); ?></a></p>

<section class="ch-card ch-customer">
    <div class="ch-customer__head">
        <span class="ch-customer__avatar" aria-hidden="true"><?php echo esc_html($initials); ?></span>
        <div>
            <h2 class="ch-customer__name"><?php echo esc_html($c['name']); ?></h2>
            <p class="ch-muted ch-small"><?php echo $c['user'] ? esc_html__('Cliente registrado', 'cheppi-sellers') : esc_html__('Cliente invitado', 'cheppi-sellers'); ?><?php if ($c['first_order']) : ?> · <?php echo esc_html(sprintf(__('compra desde el %s', 'cheppi-sellers'), wp_date('d/m/Y', $c['first_order']->getTimestamp()))); ?><?php endif; ?></p>
        </div>
    </div>
    <div class="ch-customer__stats">
        <div><span class="ch-muted ch-small"><?php echo $manage ? esc_html__('Pedidos', 'cheppi-sellers') : esc_html__('Pedidos en tu tienda', 'cheppi-sellers'); ?></span><strong><?php echo (int) $c['orders']; ?></strong></div>
        <div><span class="ch-muted ch-small"><?php esc_html_e('Dinero gastado', 'cheppi-sellers'); ?></span><strong><?php echo esc_html($money($c['spent'])); ?></strong></div>
        <div><span class="ch-muted ch-small"><?php esc_html_e('Último pedido', 'cheppi-sellers'); ?></span><strong><?php echo $c['last_order'] ? esc_html(wp_date('d/m/Y', $c['last_order']->getTimestamp())) : '—'; ?></strong></div>
    </div>
    <dl class="ch-customer__info">
        <?php foreach ($fields as [$label, $html]) : ?>
            <div><dt><?php echo esc_html($label); ?></dt><dd><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?></dd></div>
        <?php endforeach; ?>
    </dl>
</section>

<h2 class="ch-section-title"><?php echo Icons::svg('receipt', 20); ?> <?php esc_html_e('Pedidos de este cliente', 'cheppi-sellers'); ?></h2>
<?php require CHEPPI_SELLERS_DIR . '/templates/panel/_orders-table.php'; ?>
