<?php
/**
 * Add a vendor manually.
 *
 * @var \Cheppi\Sellers\Domain\Rules\BusinessRules $rules
 * @var array<string, string> $errors
 * @var array<string, string> $values
 * @var string $action
 * @var string $listUrl
 */

use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Domain\Vendor\DocumentType;

defined('ABSPATH') || exit;

$val = static fn (string $k, string $default = ''): string => (string) ($values[$k] ?? $default);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) {
        printf('<span class="ch-field__error">%s</span>', esc_html($errors[$k]));
    }
};
$status = $val('status', 'approved');
$plan = $val('plan_id', $rules->defaultPlanId);
$isNewForm = $values === [];
?>
<div class="wrap ch-admin">
    <p><a href="<?php echo esc_url($listUrl); ?>">&larr; <?php esc_html_e('Vendedores', 'cheppi-sellers'); ?></a></p>
    <header class="ch-admin__head">
        <h1><?php esc_html_e('Añadir vendedor', 'cheppi-sellers'); ?></h1>
        <p class="ch-muted"><?php esc_html_e('Registra una marca directamente. Los documentos son opcionales y puedes dejarla aprobada.', 'cheppi-sellers'); ?></p>
    </header>

    <?php if ($errors !== []) : ?>
        <div class="notice notice-error"><p><?php esc_html_e('No se creó: revisa los campos marcados.', 'cheppi-sellers'); ?></p></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-grid ch-grid--2">
        <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
        <?php wp_nonce_field($action); ?>

        <div class="ch-grid">
            <section class="ch-card">
                <h2 class="ch-card__title"><?php esc_html_e('Cuenta del vendedor', 'cheppi-sellers'); ?></h2>
                <p class="ch-muted ch-card__lead"><?php esc_html_e('Si el correo ya tiene cuenta en la web, se usa esa cuenta. Si no, se crea una nueva.', 'cheppi-sellers'); ?></p>
                <div class="ch-form-grid">
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Correo', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" type="email" name="email" required value="<?php echo esc_attr($val('email')); ?>"<?php echo isset($errors['email']) ? ' aria-invalid="true"' : ''; ?>>
                        <?php $err('email'); ?>
                    </label>
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Nombre del responsable', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" name="full_name" value="<?php echo esc_attr($val('full_name')); ?>"<?php echo isset($errors['full_name']) ? ' aria-invalid="true"' : ''; ?>>
                        <span class="ch-field__help"><?php esc_html_e('Solo para cuentas nuevas.', 'cheppi-sellers'); ?></span>
                        <?php $err('full_name'); ?>
                    </label>
                    <label class="ch-check ch-span-2">
                        <input type="checkbox" name="send_welcome" value="1" <?php checked($isNewForm || $val('send_welcome') === '1'); ?>>
                        <span><?php esc_html_e('Enviar correo para que cree su contraseña (cuentas nuevas)', 'cheppi-sellers'); ?></span>
                    </label>
                </div>
            </section>

            <section class="ch-card">
                <h2 class="ch-card__title"><?php esc_html_e('Datos de la tienda', 'cheppi-sellers'); ?></h2>
                <?php
                $showSlug = false;
                require __DIR__ . '/_vendor-fields.php';
                ?>
            </section>

            <section class="ch-card">
                <h2 class="ch-card__title"><?php esc_html_e('Documentos', 'cheppi-sellers'); ?> <small class="ch-muted"><?php esc_html_e('opcional', 'cheppi-sellers'); ?></small></h2>
                <div class="ch-grid">
                    <?php foreach (DocumentType::cases() as $type) : ?>
                        <label class="ch-file" data-file>
                            <input type="file" name="<?php echo esc_attr('doc_' . $type->value); ?>" accept="application/pdf,image/jpeg,image/png,image/webp">
                            <span class="ch-file__icon" aria-hidden="true">&#8593;</span>
                            <span class="ch-file__text">
                                <span class="ch-file__name" data-file-name><?php echo esc_html($type->label()); ?></span>
                                <span class="ch-field__help" data-file-help><?php echo esc_html($type->help()); ?></span>
                            </span>
                        </label>
                        <?php $err('doc_' . $type->value); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <section class="ch-card ch-sticky-card">
            <h2 class="ch-card__title"><?php esc_html_e('Condiciones', 'cheppi-sellers'); ?></h2>
            <div class="ch-grid">
                <fieldset class="ch-review__options">
                    <legend class="ch-field__label"><?php esc_html_e('Estado inicial', 'cheppi-sellers'); ?></legend>
                    <label class="ch-check"><input type="radio" name="status" value="approved" <?php checked($status, 'approved'); ?>> <span><?php esc_html_e('Aprobada: puede vender', 'cheppi-sellers'); ?></span></label>
                    <label class="ch-check"><input type="radio" name="status" value="pending" <?php checked($status, 'pending'); ?>> <span><?php esc_html_e('En revisión', 'cheppi-sellers'); ?></span></label>
                    <?php $err('status'); ?>
                </fieldset>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Plan', 'cheppi-sellers'); ?></span>
                    <select class="ch-input" name="plan_id">
                        <?php foreach ($rules->plans as $p) : ?>
                            <option value="<?php echo esc_attr($p->id); ?>" <?php selected($plan, $p->id); ?>><?php echo esc_html($p->name . ' · ' . Percent::fromBps($p->commissionBps) . '%'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php $err('plan_id'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Comisión especial (%)', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" name="commission_override" inputmode="decimal" placeholder="<?php esc_attr_e('Vacío = la del plan', 'cheppi-sellers'); ?>" value="<?php echo esc_attr($val('commission_override')); ?>">
                    <?php $err('commission_override'); ?>
                </label>
                <button type="submit" class="ch-btn ch-btn--primary"><?php esc_html_e('Crear vendedor', 'cheppi-sellers'); ?></button>
            </div>
        </section>
    </form>
</div>
