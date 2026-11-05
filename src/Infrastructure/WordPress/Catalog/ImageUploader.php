<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Catalog;

use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Domain\Catalog\ProductValidationException;

/**
 * Puts a photo in the media library as WebP (when the server supports it), capped at 1600 px,
 * so product pages stay light no matter what the vendor uploads.
 */
final class ImageUploader
{
    public const MAX_SIDE = 1600;

    /** @throws ProductValidationException */
    public function upload(UploadedFile $file, int $parentId, string $title): int
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $toWebp = static fn (array $formats): array => wp_image_editor_supports(['mime_type' => 'image/webp'])
            ? $formats + ['image/jpeg' => 'image/webp', 'image/png' => 'image/webp']
            : $formats;
        $threshold = static fn (): int => self::MAX_SIDE;
        add_filter('image_editor_output_format', $toWebp);
        add_filter('big_image_size_threshold', $threshold);

        $id = media_handle_sideload(
            ['name' => sanitize_file_name($file->originalName ?: 'foto.' . $file->extension()), 'tmp_name' => $file->tmpPath],
            $parentId,
            $title
        );

        remove_filter('image_editor_output_format', $toWebp);
        remove_filter('big_image_size_threshold', $threshold);

        if (is_wp_error($id)) {
            throw new ProductValidationException(['images' => 'No pudimos guardar una foto: ' . $id->get_error_message()]);
        }
        update_post_meta((int) $id, '_wp_attachment_image_alt', $title);
        return (int) $id;
    }
}
