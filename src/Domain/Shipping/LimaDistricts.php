<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Shipping;

/**
 * Districts of Lima Metropolitana and Callao, keyed by stable code. Used by vendors and delivery zones.
 */
final class LimaDistricts
{
    public const ALL = [
        'ancon' => 'Ancón', 'ate' => 'Ate', 'barranco' => 'Barranco', 'brena' => 'Breña',
        'carabayllo' => 'Carabayllo', 'chaclacayo' => 'Chaclacayo', 'chorrillos' => 'Chorrillos',
        'cieneguilla' => 'Cieneguilla', 'comas' => 'Comas', 'el-agustino' => 'El Agustino',
        'independencia' => 'Independencia', 'jesus-maria' => 'Jesús María', 'la-molina' => 'La Molina',
        'la-victoria' => 'La Victoria', 'lima' => 'Lima (Cercado)', 'lince' => 'Lince',
        'los-olivos' => 'Los Olivos', 'lurigancho' => 'Lurigancho-Chosica', 'lurin' => 'Lurín',
        'magdalena' => 'Magdalena del Mar', 'miraflores' => 'Miraflores', 'pachacamac' => 'Pachacámac',
        'pucusana' => 'Pucusana', 'pueblo-libre' => 'Pueblo Libre', 'puente-piedra' => 'Puente Piedra',
        'punta-hermosa' => 'Punta Hermosa', 'punta-negra' => 'Punta Negra', 'rimac' => 'Rímac',
        'san-bartolo' => 'San Bartolo', 'san-borja' => 'San Borja', 'san-isidro' => 'San Isidro',
        'sjl' => 'San Juan de Lurigancho', 'sjm' => 'San Juan de Miraflores', 'san-luis' => 'San Luis',
        'smp' => 'San Martín de Porres', 'san-miguel' => 'San Miguel', 'santa-anita' => 'Santa Anita',
        'santa-maria' => 'Santa María del Mar', 'santa-rosa' => 'Santa Rosa', 'surco' => 'Santiago de Surco',
        'surquillo' => 'Surquillo', 'ves' => 'Villa El Salvador', 'vmt' => 'Villa María del Triunfo',
        'callao' => 'Callao', 'bellavista' => 'Bellavista', 'carmen-legua' => 'Carmen de la Legua',
        'la-perla' => 'La Perla', 'la-punta' => 'La Punta', 'ventanilla' => 'Ventanilla',
        'mi-peru' => 'Mi Perú',
    ];

    public static function isValid(string $code): bool
    {
        return isset(self::ALL[$code]);
    }

    /** Code for a district typed or chosen by its name ("Jesús María", "jesus maria", "Surco"...); null if unknown. */
    public static function fromName(string $name): ?string
    {
        $key = self::normalize($name);
        if ($key === '') {
            return null;
        }
        if (isset(self::ALL[$key]) || isset(self::ALL[str_replace(' ', '-', $key)])) {
            return isset(self::ALL[$key]) ? $key : str_replace(' ', '-', $key);
        }
        static $byName = null;
        if ($byName === null) {
            $byName = [];
            foreach (self::ALL as $code => $label) {
                $byName[self::normalize($label)] = $code;
                $byName[self::normalize((string) preg_replace('/\s*\(.*\)$/', '', $label))] = $code;
            }
            $byName['surco'] = 'surco';
            $byName['cercado de lima'] = 'lima';
            $byName['chosica'] = 'lurigancho';
            $byName['magdalena'] = 'magdalena';
        }
        return $byName[$key] ?? null;
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $text));
    }

    public static function label(string $code): string
    {
        return self::ALL[$code] ?? $code;
    }
}
