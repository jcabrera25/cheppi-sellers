<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Persistence;

use Cheppi\Sellers\Application\DocumentStore;
use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;
use Cheppi\Sellers\Infrastructure\WordPress\PrivateUploads;

/**
 * Stores documents under uploads/cheppi-private/<vendor id>/ with random names; metadata in wp_cheppi_vendor_documents.
 */
final class WpDocumentStore implements DocumentStore
{
    public function store(int $vendorId, DocumentType $type, UploadedFile $file): void
    {
        global $wpdb;
        $file->assertAcceptable('doc_' . $type->value);

        PrivateUploads::protect();
        $dir = PrivateUploads::dir() . '/' . $vendorId;
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            throw new VendorValidationException(['doc_' . $type->value => 'No pudimos guardar el archivo. Intenta de nuevo.']);
        }

        $fileName = $type->value . '-' . bin2hex(random_bytes(12)) . '.' . $file->extension();
        $target = $dir . '/' . $fileName;
        $moved = is_uploaded_file($file->tmpPath) ? move_uploaded_file($file->tmpPath, $target) : rename($file->tmpPath, $target);
        if (!$moved) {
            throw new VendorValidationException(['doc_' . $type->value => 'No pudimos guardar el archivo. Intenta de nuevo.']);
        }
        @chmod($target, 0640);

        $table = Installer::tables()['documents'];
        $previous = $wpdb->get_var($wpdb->prepare(
            "SELECT file_name FROM {$table} WHERE vendor_id = %d AND type = %s",
            $vendorId,
            $type->value
        ));
        $wpdb->replace($table, [
            'vendor_id' => $vendorId,
            'type' => $type->value,
            'file_name' => $fileName,
            'original_name' => mb_substr(sanitize_file_name($file->originalName), 0, 200),
            'mime' => $file->mime,
            'size' => $file->size,
            'uploaded_at' => current_time('mysql', true),
        ]);
        if (is_string($previous) && $previous !== '' && $previous !== $fileName) {
            wp_delete_file($dir . '/' . basename($previous));
        }
    }

    public function forVendor(int $vendorId): array
    {
        global $wpdb;
        $table = Installer::tables()['documents'];
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, type, original_name, mime, size, uploaded_at FROM {$table} WHERE vendor_id = %d ORDER BY id",
            $vendorId
        ), ARRAY_A);
        $out = [];
        foreach ($rows as $row) {
            $type = DocumentType::tryFrom((string) $row['type']);
            if ($type === null) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'type' => $type,
                'original_name' => (string) $row['original_name'],
                'mime' => (string) $row['mime'],
                'size' => (int) $row['size'],
                'uploaded_at' => (string) $row['uploaded_at'],
            ];
        }
        return $out;
    }

    public function delete(int $vendorId, DocumentType $type): void
    {
        global $wpdb;
        $table = Installer::tables()['documents'];
        $file = $wpdb->get_var($wpdb->prepare(
            "SELECT file_name FROM {$table} WHERE vendor_id = %d AND type = %s",
            $vendorId,
            $type->value
        ));
        if (is_string($file) && $file !== '') {
            wp_delete_file(PrivateUploads::dir() . '/' . $vendorId . '/' . basename($file));
        }
        $wpdb->delete($table, ['vendor_id' => $vendorId, 'type' => $type->value], ['%d', '%s']);
    }

    public function deleteAllFor(int $vendorId): void
    {
        global $wpdb;
        foreach ($this->forVendor($vendorId) as $doc) {
            $this->delete($vendorId, $doc['type']);
        }
        $dir = PrivateUploads::dir() . '/' . $vendorId;
        if (is_dir($dir) && count((array) scandir($dir)) <= 2) {
            @rmdir($dir);
        }
        $wpdb->delete(Installer::tables()['documents'], ['vendor_id' => $vendorId], ['%d']);
    }

    /** Absolute path of a stored document, for the admin download handler. */
    public function pathFor(int $documentId): ?array
    {
        global $wpdb;
        $table = Installer::tables()['documents'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT vendor_id, file_name, original_name, mime FROM {$table} WHERE id = %d", $documentId), ARRAY_A);
        if (!is_array($row)) {
            return null;
        }
        $path = PrivateUploads::dir() . '/' . (int) $row['vendor_id'] . '/' . basename((string) $row['file_name']);
        return is_file($path) ? ['path' => $path, 'name' => (string) $row['original_name'], 'mime' => (string) $row['mime']] : null;
    }
}
