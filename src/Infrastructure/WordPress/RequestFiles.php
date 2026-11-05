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
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string) finfo_file($finfo, $path) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            return $mime;
        }
        return (string) (wp_get_image_mime($path) ?: '');
    }
}
