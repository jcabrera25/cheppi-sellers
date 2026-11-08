<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress;

use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Domain\Vendor\DocumentType;

/**
 * Reads vendor document uploads (inputs named doc_<type>) from $_FILES, detecting the real MIME type from content.
 */
final class RequestFiles
{
    /** @return array<string, UploadedFile> keyed by DocumentType value; empty inputs are skipped */
    public static function documents(): array
    {
        $files = [];
        foreach (DocumentType::cases() as $type) {
            $raw = $_FILES['doc_' . $type->value] ?? null;
            if (!is_array($raw) || ($raw['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $tmp = (string) ($raw['tmp_name'] ?? '');
            $ok = ($raw['error'] ?? 1) === UPLOAD_ERR_OK && $tmp !== '' && is_uploaded_file($tmp);
            $mime = $ok ? self::detectMime($tmp) : '';
            $files[$type->value] = new UploadedFile($tmp, (string) ($raw['name'] ?? ''), $mime, $ok ? (int) $raw['size'] : 0);
        }
        return $files;
    }

    private static function detectMime(string $path): string
    {
        if (class_exists(\finfo::class)) {
            // finfo objects free themselves; finfo_close() is deprecated since PHP 8.5.
            return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        }
        return (string) (wp_get_image_mime($path) ?: '');
    }
}
