<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Vendor;

use Cheppi\Sellers\Domain\Shipping\LimaDistricts;

/**
 * What a business submits to sell on Cheppi. Validates all fields at once and reports per-field messages.
 * Account fields (email, password) belong to the WordPress adapter, not here.
 */
final class VendorApplication
{
    private function __construct(
        public readonly string $storeName,
        public readonly Ruc $ruc,
        public readonly string $legalName,
        public readonly string $phone,
        public readonly string $category,
        public readonly string $district,
        public readonly string $instagram,
        public readonly string $website
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param list<DocumentType>   $documentsProvided
     * @throws VendorValidationException
     */
    public static function fromArray(array $input, array $documentsProvided, bool $termsAccepted): self
    {
        $errors = [];
        foreach (DocumentType::required() as $type) {
            if (!in_array($type, $documentsProvided, true)) {
                $errors['doc_' . $type->value] = 'Falta subir: ' . $type->label() . '.';
            }
        }
        if (!$termsAccepted) {
            $errors['terms'] = 'Debes aceptar los términos para vendedores.';
        }
        return self::validate($input, $errors);
    }

    /**
     * Business data only: used when an admin creates or edits a vendor (documents and terms are not required).
     *
     * @param array<string, mixed> $input
     * @throws VendorValidationException
     */
    public static function profile(array $input): self
    {
        return self::validate($input, []);
    }

    /**
     * @param array<string, mixed>  $input
     * @param array<string, string> $errors errors already found by the caller
     * @throws VendorValidationException
     */
    private static function validate(array $input, array $errors): self
    {
        $text = static fn (string $key): string => trim((string) ($input[$key] ?? ''));

        $storeName = $text('store_name');
        if (mb_strlen($storeName) < 2 || mb_strlen($storeName) > 120) {
            $errors['store_name'] = 'El nombre de la tienda debe tener entre 2 y 120 caracteres.';
        }

        $ruc = null;
        if (!Ruc::isValid(preg_replace('/\D/', '', $text('ruc')) ?? '')) {
            $errors['ruc'] = 'El RUC no es válido. Revisa los 11 dígitos.';
        } else {
            $ruc = Ruc::fromString($text('ruc'));
        }

        $legalName = $text('legal_name');
        if (mb_strlen($legalName) < 3 || mb_strlen($legalName) > 200) {
            $errors['legal_name'] = 'Escribe la razón social o nombre del titular del RUC.';
        }

        $phone = preg_replace('/[^\d+]/', '', $text('phone')) ?? '';
        if (!preg_match('/^(\+?51)?9\d{8}$/', $phone)) {
            $errors['phone'] = 'Escribe un celular peruano de 9 dígitos que empiece con 9.';
        }

        $category = $text('category');
        if (!StoreCategory::isValid($category)) {
            $errors['category'] = 'Elige la categoría principal de tu tienda.';
        }

        $district = $text('district');
        if (!LimaDistricts::isValid($district)) {
            $errors['district'] = 'Elige el distrito desde donde despachas.';
        }

        $instagram = ltrim($text('instagram'), '@');
        if ($instagram !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $instagram)) {
            $errors['instagram'] = 'Usuario de Instagram no válido.';
        }

        $website = $text('website');
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            $errors['website'] = 'La web debe empezar con https://';
        }

        if ($errors !== [] || $ruc === null) {
            throw new VendorValidationException($errors);
        }

        return new self($storeName, $ruc, $legalName, $phone, $category, $district, $instagram, $website);
    }
}
