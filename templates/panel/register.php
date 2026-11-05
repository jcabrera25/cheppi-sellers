<?php
/**
 * Seller registration in 4 steps. Works without JavaScript (all steps visible); the panel module turns it into a stepper.
 *
 * @var WP_User $user
 * @var array<string, string> $errors
 * @var array<string, mixed> $values
 */

use Cheppi\Sellers\Domain\Shipping\LimaDistricts;
use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\StoreCategory;
use Cheppi\Sellers\Infrastructure\WordPress\Front\ApplicationHandler;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\View;

defined('ABSPATH') || exit;

$val = static fn (string $key): string => isset($values[$key]) && is_scalar($values[$key]) ? (string) $values[$key] : '';
$err = static fn (string $key): string => $errors[$key] ?? '';
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true"' : '';
$loggedIn = $user->exists();
$termsUrl = (string) apply_filters('cheppi_sellers_terms_url', home_url('/terminos-para-vendedores/'));
$general = $errors['form'] ?? ($errors['account'] ?? '');
?>
<div class="ch-auth">
    <?php echo View::capture('panel/_aside'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <main class="ch-auth__main">
        <div class="ch-auth__panel">
            <div>
                <h1><?php esc_html_e('Crea tu tienda en Cheppi', 'cheppi-sellers'); ?></h1>
                <p class="ch-muted"><?php esc_html_e('Toma unos 5 minutos. Ten a mano tu RUC, tu DNI y la constancia de tu cuenta bancaria.', 'cheppi-sellers'); ?></p>
            </div>

            <div class="ch-alert ch-alert--danger" role="alert" data-form-error <?php echo $general === '' ? 'hidden' : ''; ?>>
                <?php echo Icons::svg('alert'); ?><p><?php echo esc_html($general !== '' ? $general : __('Revisa los campos marcados.', 'cheppi-sellers')); ?></p>
            </div>

            <form class="ch-form" method="post" enctype="multipart/form-data" novalidate
                  action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-register-form>
                <input type="hidden" name="action" value="<?php echo esc_attr(ApplicationHandler::ACTION_APPLY); ?>">
                <?php wp_nonce_field(ApplicationHandler::ACTION_APPLY, '_cheppi_nonce'); ?>
                <div class="ch-honeypot" aria-hidden="true">
                    <label>Empresa web <input type="text" name="company_url" tabindex="-1" autocomplete="off"></label>
                </div>

                <ol class="ch-steps" aria-label="<?php esc_attr_e('Pasos', 'cheppi-sellers'); ?>">
                    <li class="ch-steps__item" data-step-label="1"><?php esc_html_e('Cuenta', 'cheppi-sellers'); ?></li>
                    <li class="ch-steps__item" data-step-label="2"><?php esc_html_e('Negocio', 'cheppi-sellers'); ?></li>
                    <li class="ch-steps__item" data-step-label="3"><?php esc_html_e('Documentos', 'cheppi-sellers'); ?></li>
                    <li class="ch-steps__item" data-step-label="4"><?php esc_html_e('Confirmar', 'cheppi-sellers'); ?></li>
                </ol>

                <fieldset class="ch-fieldset" data-step="1">
                    <legend><?php esc_html_e('1. Tu cuenta', 'cheppi-sellers'); ?></legend>
                    <?php if ($loggedIn) : ?>
                        <div class="ch-alert">
                            <?php echo Icons::svg('check'); ?>
                            <p><?php echo esc_html(sprintf(__('Registrarás la tienda con tu cuenta %s.', 'cheppi-sellers'), $user->user_email)); ?></p>
                        </div>
                    <?php else : ?>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Nombre y apellido', 'cheppi-sellers'); ?></span>
                            <input class="ch-input" name="full_name" autocomplete="name" required minlength="3" maxlength="80" value="<?php echo esc_attr($val('full_name')); ?>"<?php echo $invalid('full_name'); ?>>
                            <span class="ch-field__error" data-error-for="full_name"><?php echo esc_html($err('full_name')); ?></span>
                        </label>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Correo', 'cheppi-sellers'); ?></span>
                            <input class="ch-input" type="email" name="email" autocomplete="email" required value="<?php echo esc_attr($val('email')); ?>"<?php echo $invalid('email'); ?>>
                            <span class="ch-field__error" data-error-for="email"><?php echo esc_html($err('email')); ?></span>
                        </label>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Contraseña', 'cheppi-sellers'); ?> <small><?php esc_html_e('mínimo 8 caracteres', 'cheppi-sellers'); ?></small></span>
                            <input class="ch-input" type="password" name="password" autocomplete="new-password" required minlength="8"<?php echo $invalid('password'); ?>>
                            <span class="ch-field__error" data-error-for="password"><?php echo esc_html($err('password')); ?></span>
                        </label>
                        <p class="ch-field__help"><?php printf(
                            /* translators: %s: login link */
                            esc_html__('¿Ya tienes cuenta en Cheppi? %s', 'cheppi-sellers'),
                            '<a href="' . esc_url(wp_login_url(\Cheppi\Sellers\Infrastructure\WordPress\Front\Routes::registerUrl())) . '">' . esc_html__('Ingresa primero', 'cheppi-sellers') . '</a>'
                        ); ?></p>
                    <?php endif; ?>
                    <div class="ch-actions" data-step-nav>
                        <button type="button" class="ch-btn ch-btn--primary" data-next><?php esc_html_e('Continuar', 'cheppi-sellers'); ?></button>
                    </div>
                </fieldset>

                <fieldset class="ch-fieldset" data-step="2">
                    <legend><?php esc_html_e('2. Tu negocio', 'cheppi-sellers'); ?></legend>
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Nombre de tu tienda', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" name="store_name" required minlength="2" maxlength="120" value="<?php echo esc_attr($val('store_name')); ?>"<?php echo $invalid('store_name'); ?>>
                        <span class="ch-field__help"><?php esc_html_e('Así te verán los clientes.', 'cheppi-sellers'); ?></span>
                        <span class="ch-field__error" data-error-for="store_name"><?php echo esc_html($err('store_name')); ?></span>
                    </label>
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Categoría principal', 'cheppi-sellers'); ?></span>
                        <select class="ch-input" name="category" required<?php echo $invalid('category'); ?>>
                            <option value=""><?php esc_html_e('Elige una', 'cheppi-sellers'); ?></option>
                            <?php foreach (StoreCategory::ALL as $code => $label) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($val('category'), $code); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="ch-field__error" data-error-for="category"><?php echo esc_html($err('category')); ?></span>
                    </label>
                    <div class="ch-row">
                        <label class="ch-field">
                            <span class="ch-field__label">RUC</span>
                            <input class="ch-input" name="ruc" inputmode="numeric" required maxlength="13" value="<?php echo esc_attr($val('ruc')); ?>" data-ruc<?php echo $invalid('ruc'); ?>>
                            <span class="ch-field__error" data-error-for="ruc"><?php echo esc_html($err('ruc')); ?></span>
                        </label>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Celular', 'cheppi-sellers'); ?></span>
                            <input class="ch-input" type="tel" name="phone" inputmode="tel" autocomplete="tel" required value="<?php echo esc_attr($val('phone')); ?>" placeholder="9XX XXX XXX"<?php echo $invalid('phone'); ?>>
                            <span class="ch-field__error" data-error-for="phone"><?php echo esc_html($err('phone')); ?></span>
                        </label>
                    </div>
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Razón social o titular del RUC', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" name="legal_name" required minlength="3" maxlength="200" value="<?php echo esc_attr($val('legal_name')); ?>"<?php echo $invalid('legal_name'); ?>>
                        <span class="ch-field__error" data-error-for="legal_name"><?php echo esc_html($err('legal_name')); ?></span>
                    </label>
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Distrito desde donde despachas', 'cheppi-sellers'); ?></span>
                        <select class="ch-input" name="district" required<?php echo $invalid('district'); ?>>
                            <option value=""><?php esc_html_e('Elige tu distrito', 'cheppi-sellers'); ?></option>
                            <?php foreach (LimaDistricts::ALL as $code => $label) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($val('district'), $code); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="ch-field__help"><?php esc_html_e('Por ahora atendemos Lima y Callao.', 'cheppi-sellers'); ?></span>
                        <span class="ch-field__error" data-error-for="district"><?php echo esc_html($err('district')); ?></span>
                    </label>
                    <div class="ch-row">
                        <label class="ch-field">
                            <span class="ch-field__label">Instagram <small><?php esc_html_e('opcional', 'cheppi-sellers'); ?></small></span>
                            <input class="ch-input" name="instagram" placeholder="@tutienda" value="<?php echo esc_attr($val('instagram')); ?>"<?php echo $invalid('instagram'); ?>>
                            <span class="ch-field__error" data-error-for="instagram"><?php echo esc_html($err('instagram')); ?></span>
                        </label>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Web', 'cheppi-sellers'); ?> <small><?php esc_html_e('opcional', 'cheppi-sellers'); ?></small></span>
                            <input class="ch-input" type="url" name="website" placeholder="https://" value="<?php echo esc_attr($val('website')); ?>"<?php echo $invalid('website'); ?>>
                            <span class="ch-field__error" data-error-for="website"><?php echo esc_html($err('website')); ?></span>
                        </label>
                    </div>
                    <div class="ch-actions" data-step-nav>
                        <button type="button" class="ch-btn ch-btn--ghost" data-prev><?php esc_html_e('Atrás', 'cheppi-sellers'); ?></button>
                        <button type="button" class="ch-btn ch-btn--primary" data-next><?php esc_html_e('Continuar', 'cheppi-sellers'); ?></button>
                    </div>
                </fieldset>

                <fieldset class="ch-fieldset" data-step="3">
                    <legend><?php esc_html_e('3. Documentos', 'cheppi-sellers'); ?></legend>
                    <p class="ch-field__help"><?php esc_html_e('PDF, JPG, PNG o WebP de hasta 5 MB. Solo el equipo de Cheppi los ve.', 'cheppi-sellers'); ?></p>
                    <?php foreach (DocumentType::cases() as $type) : $name = 'doc_' . $type->value; ?>
                        <div class="ch-field">
                            <label class="ch-file" data-file>
                                <input type="file" name="<?php echo esc_attr($name); ?>" accept="application/pdf,image/jpeg,image/png,image/webp" <?php echo $type->isRequired() ? 'required' : ''; ?><?php echo $invalid($name); ?>>
                                <span class="ch-file__icon"><?php echo Icons::svg('upload'); ?></span>
                                <span class="ch-file__text">
                                    <span class="ch-file__name" data-file-name><?php echo esc_html($type->label()); ?><?php echo $type->isRequired() ? '' : ' <small style="font-weight:400;color:#8a85a0">(' . esc_html__('opcional', 'cheppi-sellers') . ')</small>'; ?></span>
                                    <span class="ch-field__help" data-file-help><?php echo esc_html($type->help()); ?></span>
                                </span>
                            </label>
                            <span class="ch-field__error" data-error-for="<?php echo esc_attr($name); ?>"><?php echo esc_html($err($name)); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="ch-actions" data-step-nav>
                        <button type="button" class="ch-btn ch-btn--ghost" data-prev><?php esc_html_e('Atrás', 'cheppi-sellers'); ?></button>
                        <button type="button" class="ch-btn ch-btn--primary" data-next><?php esc_html_e('Continuar', 'cheppi-sellers'); ?></button>
                    </div>
                </fieldset>

                <fieldset class="ch-fieldset" data-step="4">
                    <legend><?php esc_html_e('4. Revisa y envía', 'cheppi-sellers'); ?></legend>
                    <div class="ch-card" data-summary hidden>
                        <dl class="ch-summary" data-summary-list></dl>
                    </div>
                    <label class="ch-check">
                        <input type="checkbox" name="terms" value="1" required<?php echo $invalid('terms'); ?>>
                        <span><?php printf(
                            /* translators: %s: link to seller terms */
                            esc_html__('Acepto los %s y confirmo que los datos son verdaderos.', 'cheppi-sellers'),
                            '<a href="' . esc_url($termsUrl) . '" target="_blank" rel="noopener">' . esc_html__('términos y condiciones para vendedores', 'cheppi-sellers') . '</a>'
                        ); ?></span>
                    </label>
                    <span class="ch-field__error" data-error-for="terms"><?php echo esc_html($err('terms')); ?></span>
                    <div class="ch-actions">
                        <button type="button" class="ch-btn ch-btn--ghost" data-prev data-step-nav><?php esc_html_e('Atrás', 'cheppi-sellers'); ?></button>
                        <button type="submit" class="ch-btn ch-btn--primary" data-submit><?php esc_html_e('Enviar solicitud', 'cheppi-sellers'); ?></button>
                    </div>
                </fieldset>
            </form>
        </div>
    </main>
</div>
