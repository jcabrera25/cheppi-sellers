<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;

/**
 * Pieces for the "Vende en Cheppi" page, to drop into Divi with its native Shortcode/Code module:
 *   [cheppi_calculadora]                         earnings calculator, fed by Business Rules
 *   [cheppi_boton_vender texto="Crear mi tienda"] link to the registration
 */
final class Shortcodes
{
    public function __construct(
        private readonly BusinessRulesRepository $rules,
        private readonly Assets $assets
    ) {
    }

    public function register(): void
    {
        add_shortcode('cheppi_calculadora', [$this, 'calculator']);
        add_shortcode('cheppi_boton_vender', [$this, 'button']);
    }

    /** @param array<string, string>|string $atts */
    public function button($atts): string
    {
        $atts = shortcode_atts(['texto' => __('Crear mi tienda', 'cheppi-sellers'), 'estilo' => 'primario'], (array) $atts, 'cheppi_boton_vender');
        $this->assets->enqueue('calculator'); // carries the kit styles for the button too
        $class = $atts['estilo'] === 'secundario' ? 'ch-btn ch-btn--secondary' : 'ch-btn ch-btn--primary';
        return sprintf('<a class="%s" href="%s">%s</a>', esc_attr($class), esc_url(Routes::registerUrl()), esc_html($atts['texto']));
    }

    public function calculator(): string
    {
        $this->assets->enqueue('calculator');
        $rules = $this->rules->current();
        $plans = array_map(static fn ($p): array => [
            'id' => $p->id,
            'name' => $p->name,
            'fee' => $p->monthlyFee->toDecimal(),
            'bps' => $p->commissionBps,
        ], $rules->plans);

        ob_start();
        ?>
        <div class="ch-calc" data-cheppi-calc data-plans="<?php echo esc_attr((string) wp_json_encode($plans)); ?>">
            <div class="ch-calc__inputs">
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Precio de tu producto (S/)', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="number" min="1" step="0.10" value="100" inputmode="decimal" data-calc-price>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Ventas al mes', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="number" min="1" step="1" value="20" inputmode="numeric" data-calc-qty>
                </label>
            </div>
            <div class="ch-calc__plans">
                <?php foreach ($plans as $plan) : ?>
                    <div class="ch-calc__plan" data-calc-plan="<?php echo esc_attr($plan['id']); ?>">
                        <span class="ch-calc__plan-name"><?php echo esc_html($plan['name']); ?></span>
                        <span class="ch-calc__meta"><?php echo esc_html(sprintf(
                            /* translators: 1: commission %, 2: monthly fee */
                            __('%1$s%% de comisión · S/ %2$s al mes', 'cheppi-sellers'),
                            Percent::fromBps($plan['bps']),
                            $plan['fee']
                        )); ?></span>
                        <span class="ch-calc__label"><?php esc_html_e('Recibes por venta', 'cheppi-sellers'); ?></span>
                        <span class="ch-calc__value" data-calc-net>—</span>
                        <span class="ch-calc__meta" data-calc-month></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="ch-calc__note"><?php esc_html_e('Cálculo referencial antes del costo de la pasarela de pago. Sin costos ocultos: verás la comisión de cada venta en tu panel.', 'cheppi-sellers'); ?></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
