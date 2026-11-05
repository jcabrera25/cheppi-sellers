<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Vendor;

/**
 * Documents a vendor uploads to be approved.
 */
enum DocumentType: string
{
    case Dni = 'dni';
    case BankAccount = 'bank_account';
    case SanitaryNotice = 'sanitary_notice';
    case BrandAuthorization = 'brand_authorization';

    public const MAX_BYTES = 5 * 1024 * 1024;
    public const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function label(): string
    {
        return match ($this) {
            self::Dni => 'DNI del titular o representante',
            self::BankAccount => 'Constancia de cuenta bancaria',
            self::SanitaryNotice => 'Notificación Sanitaria Obligatoria',
            self::BrandAuthorization => 'Autorización o registro de marca',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::Dni => 'Ambas caras en un PDF o imagen.',
            self::BankAccount => 'A nombre del titular del RUC.',
            self::SanitaryNotice => 'Obligatoria para cosméticos. Puedes subirla luego.',
            self::BrandAuthorization => 'Si revendes marcas de terceros.',
        };
    }

    public function isRequired(): bool
    {
        return $this === self::Dni || $this === self::BankAccount;
    }

    /** @return list<self> */
    public static function required(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $t): bool => $t->isRequired()));
    }
}
