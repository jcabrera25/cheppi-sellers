<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Catalog;

use Cheppi\Sellers\Domain\Rules\BusinessRules;

/** Publication state of a vendor product (same names as WordPress post statuses). */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'publish';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Pending => 'En revisión',
            self::Published => 'Publicado',
        };
    }

    /**
     * Status after a vendor saves.
     * Drafts stay drafts when asked; published products stay published when edited;
     * anything sent for publication goes to review when moderation is on.
     */
    public static function afterSave(?self $current, bool $saveAsDraft, BusinessRules $rules): self
    {
        if ($saveAsDraft) {
            return self::Draft;
        }
        if ($current === self::Published) {
            return self::Published;
        }
        return $rules->moderateNewProducts ? self::Pending : self::Published;
    }
}
