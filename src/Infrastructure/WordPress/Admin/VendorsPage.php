<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\ReviewVendor;
use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpDocumentStore;
use DomainException;
use InvalidArgumentException;

/**
 * wp-admin → Cheppi → Vendedores: list by status, review an application, download its documents.
 */
final class VendorsPage
{
    public const SLUG = 'cheppi-vendors';
    private const ACTION_REVIEW = 'cheppi_review_vendor';
    private const ACTION_DOWNLOAD = 'cheppi_vendor_document';
    private const PER_PAGE = 20;

    private string $hook = '';

    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly WpDocumentStore $documents,
        private readonly BusinessRulesRepository $rules,
        private readonly ReviewVendor $review,
        private readonly Assets $assets
    ) {
    }

    public static function detailUrl(int $vendorId): string
    {
        return admin_url('admin.php?page=' . self::SLUG . '&vendor=' . $vendorId);
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 11);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_' . self::ACTION_REVIEW, [$this, 'handleReview']);
        add_action('admin_post_' . self::ACTION_DOWNLOAD, [$this, 'handleDownload']);
    }

    public function addMenu(): void
    {
        $pending = $this->vendors->countByStatus()[VendorStatus::Pending->value] ?? 0;
        $badge = $pending > 0 ? sprintf(' <span class="awaiting-mod">%d</span>', $pending) : '';
        $this->hook = (string) add_submenu_page(
            BusinessRulesPage::SLUG,
            __('Vendedores', 'cheppi-sellers'),
            __('Vendedores', 'cheppi-sellers') . $badge,
            Capabilities::MANAGE,
            self::SLUG,
            [$this, 'render'],
            0
        );
    }

    public function enqueue(string $hook): void
    {
        if ($hook === $this->hook) {
            $this->assets->enqueue('admin-vendors');
        }
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('No tienes permiso para ver esta página.', 'cheppi-sellers'));
        }
        $vendorId = isset($_GET['vendor']) ? absint($_GET['vendor']) : 0;
        if ($vendorId > 0) {
            $this->renderDetail($vendorId);
            return;
        }

        $status = VendorStatus::tryFrom(sanitize_key((string) ($_GET['estado'] ?? '')));
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $vendors = $this->vendors->list($status, self::PER_PAGE, ($paged - 1) * self::PER_PAGE);
        $counts = $this->vendors->countByStatus();
        $rules = $this->rules->current();
        $page = self::SLUG;

        require CHEPPI_SELLERS_DIR . '/templates/admin/vendors-list.php';
    }

    private function renderDetail(int $vendorId): void
    {
        $vendor = $this->vendors->find($vendorId);
        if ($vendor === null) {
            wp_die(esc_html__('Vendedor no encontrado.', 'cheppi-sellers'));
        }
        $user = get_userdata($vendor->userId);
        $documents = $this->documents->forVendor($vendorId);
        $rules = $this->rules->current();
        $flash = get_transient('cheppi_review_flash_' . get_current_user_id());
        delete_transient('cheppi_review_flash_' . get_current_user_id());
        $action = self::ACTION_REVIEW;
        $downloadUrl = static fn (int $docId): string => wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_DOWNLOAD . '&doc=' . $docId),
            self::ACTION_DOWNLOAD . $docId
        );
        $listUrl = admin_url('admin.php?page=' . self::SLUG);

        require CHEPPI_SELLERS_DIR . '/templates/admin/vendor-detail.php';
    }

    public function handleReview(): void
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('No tienes permiso para hacer esto.', 'cheppi-sellers'), 403);
        }
        $vendorId = absint($_POST['vendor_id'] ?? 0);
        check_admin_referer(self::ACTION_REVIEW . $vendorId);

        $next = VendorStatus::tryFrom(sanitize_key((string) ($_POST['next_status'] ?? '')));
        $note = sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? '')));
        $planId = sanitize_key((string) ($_POST['plan_id'] ?? ''));
        $overrideRaw = trim(sanitize_text_field(wp_unslash((string) ($_POST['commission_override'] ?? ''))));

        try {
            $override = $overrideRaw === '' ? null : Percent::toBps($overrideRaw);
            ($this->review)($vendorId, $next, $note, $planId, $override);
            $flash = ['type' => 'success', 'message' => __('Cambios guardados. Si cambió el estado, el vendedor recibió un correo.', 'cheppi-sellers')];
        } catch (DomainException|InvalidArgumentException $e) {
            $flash = ['type' => 'error', 'message' => $e->getMessage()];
        }
        set_transient('cheppi_review_flash_' . get_current_user_id(), $flash, 120);
        wp_safe_redirect(self::detailUrl($vendorId));
        exit;
    }

    /** Streams a private document to an authorized admin. */
    public function handleDownload(): void
    {
        $docId = absint($_GET['doc'] ?? 0);
        if (!current_user_can(Capabilities::MANAGE) || !wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), self::ACTION_DOWNLOAD . $docId)) {
            wp_die(esc_html__('No tienes permiso para ver este documento.', 'cheppi-sellers'), 403);
        }
        $file = $this->documents->pathFor($docId);
        if ($file === null) {
            wp_die(esc_html__('Documento no encontrado.', 'cheppi-sellers'), 404);
        }
        nocache_headers();
        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . (string) filesize($file['path']));
        header('Content-Disposition: inline; filename="' . rawurlencode($file['name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($file['path']);
        exit;
    }
}
