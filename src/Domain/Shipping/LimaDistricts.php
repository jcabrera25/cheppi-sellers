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

    public static function label(string $code): string
    {
        return self::ALL[$code] ?? $code;
    }
}
