<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Infrastructure\WordPress\Front\ManageContext;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\CreateVendorByAdmin;
use Cheppi\Sellers\Application\DeleteVendor;
use Cheppi\Sellers\Application\ReviewVendor;
use Cheppi\Sellers\Application\UpdateVendorProfile;
use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\VendorApplication;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Accounts;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpDocumentStore;
use Cheppi\Sellers\Infrastructure\WordPress\RequestFiles;
use DomainException;
use InvalidArgumentException;
use WP_Error;

/**
 * wp-admin → Cheppi → Vendedores: list and search, add a vendor manually, edit its data,
 * manage its documents, decide its status, and delete it.
 */
final class VendorsPage
{
    public const SLUG = 'cheppi-vendors';
    private const ACTION_REVIEW = 'cheppi_review_vendor';
    private const ACTION_DOWNLOAD = 'cheppi_vendor_document';
    private const ACTION_CREATE = 'cheppi_create_vendor';
    private const ACTION_UPDATE = 'cheppi_update_vendor';
    private const ACTION_DOCS = 'cheppi_vendor_docs';
    private const ACTION_DELETE = 'cheppi_delete_vendor';
    private const PER_PAGE = 20;
    private const PROFILE_FIELDS = ['store_name', 'store_slug', 'category', 'ruc', 'phone', 'legal_name', 'district', 'instagram', 'website'];

    private string $hook = '';

    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly WpDocumentStore $documents,
        private readonly BusinessRulesRepository $rules,
        private readonly ReviewVendor $review,
        private readonly CreateVendorByAdmin $create,
        private readonly UpdateVendorProfile $update,
        private readonly DeleteVendor $delete,
        private readonly Assets $assets
    ) {
    }

    public static function detailUrl(int $vendorId): string
    {
        return ManageContext::url(self::SLUG, ['vendor' => $vendorId]);
    }

    public static function listUrl(): string
    {
        return ManageContext::url(self::SLUG);
    }

    public static function newUrl(): string
    {
        return ManageContext::url(self::SLUG, ['nuevo' => 1]);
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 11);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        foreach ([
            self::ACTION_REVIEW => 'handleReview',
            self::ACTION_DOWNLOAD => 'handleDownload',
            self::ACTION_CREATE => 'handleCreate',
            self::ACTION_UPDATE => 'handleUpdate',
            self::ACTION_DOCS => 'handleDocuments',
            self::ACTION_DELETE => 'handleDelete',
        ] as $action => $method) {
            add_action('admin_post_' . $action, [$this, $method]);
        }
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
            $this->assets->enqueue('admin');
        }
    }

    public function render(): void
    {
        $this->authorize();
        $flash = $this->takeFlash();

        if (!empty($_GET['nuevo'])) {
            $this->view('vendor-new', [
                'rules' => $this->rules->current(),
                'errors' => $flash['errors'] ?? [],
                'values' => $flash['values'] ?? [],
                'action' => self::ACTION_CREATE,
                'listUrl' => self::listUrl(),
            ]);
            return;
        }

        $vendorId = isset($_GET['vendor']) ? absint($_GET['vendor']) : 0;
        if ($vendorId > 0) {
            $this->renderDetail($vendorId, $flash);
            return;
        }

        $status = VendorStatus::tryFrom(sanitize_key((string) ($_GET['estado'] ?? '')));
        $search = sanitize_text_field(wp_unslash((string) ($_GET['s'] ?? '')));
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $this->view('vendors-list', [
            'vendors' => $this->vendors->list($status, self::PER_PAGE, ($paged - 1) * self::PER_PAGE, $search),
            'counts' => $this->vendors->countByStatus(),
            'status' => $status,
            'search' => $search,
            'rules' => $this->rules->current(),
            'paged' => $paged,
            'page' => self::SLUG,
            'flash' => $flash,
            'newUrl' => self::newUrl(),
        ]);
    }

    /** @param array<string, mixed> $flash */
    private function renderDetail(int $vendorId, array $flash): void
    {
        $vendor = $this->vendors->find($vendorId);
        if ($vendor === null) {
            wp_die(esc_html__('Vendedor no encontrado.', 'cheppi-sellers'));
        }
        $this->view('vendor-detail', [
            'vendor' => $vendor,
            'user' => get_userdata($vendor->userId),
            'documents' => $this->documents->forVendor($vendorId),
            'rules' => $this->rules->current(),
            'flash' => $flash,
            'errors' => $flash['errors'] ?? [],
            'values' => $flash['values'] ?? [],
            'actions' => [
                'review' => self::ACTION_REVIEW,
                'update' => self::ACTION_UPDATE,
                'docs' => self::ACTION_DOCS,
                'delete' => self::ACTION_DELETE,
            ],
            'downloadUrl' => static fn (int $docId): string => wp_nonce_url(
                admin_url('admin-post.php?action=' . self::ACTION_DOWNLOAD . '&doc=' . $docId),
                self::ACTION_DOWNLOAD . $docId
            ),
            'listUrl' => self::listUrl(),
        ]);
    }

    public function handleCreate(): void
    {
        $this->authorize();
        check_admin_referer(self::ACTION_CREATE);
        $post = wp_unslash($_POST);
        $values = $this->textFields($post, array_merge(self::PROFILE_FIELDS, ['full_name', 'email', 'status', 'plan_id', 'commission_override']));
        $values['send_welcome'] = !empty($post['send_welcome']) ? '1' : '';

        $errors = [];
        $email = sanitize_email($values['email']);
        if (!is_email($email)) {
            $errors['email'] = __('Escribe un correo válido.', 'cheppi-sellers');
        }
        $override = null;
        if ($values['commission_override'] !== '') {
            try {
                $override = Percent::toBps($values['commission_override']);
            } catch (InvalidArgumentException) {
                $errors['commission_override'] = __('Comisión especial no válida.', 'cheppi-sellers');
            }
        }
        $existing = is_email($email) ? get_user_by('email', $email) : false;
        if (!$existing && mb_strlen($values['full_name']) < 3) {
            $errors['full_name'] = __('Escribe el nombre del responsable de la cuenta.', 'cheppi-sellers');
        }
        $status = VendorStatus::tryFrom($values['status']) ?? VendorStatus::Approved;
        $files = RequestFiles::documents();

        // Validate the vendor before creating any account.
        try {
            VendorApplication::profile($values);
            if ($existing && $this->vendors->findByUserId($existing->ID) !== null) {
                $errors['email'] = __('Esa cuenta ya tiene una tienda registrada.', 'cheppi-sellers');
            }
        } catch (VendorValidationException $e) {
            $errors += $e->errors;
        }
        if ($errors !== []) {
            $this->flash(['errors' => $errors, 'values' => $values], self::newUrl());
        }

        $userId = $existing ? $existing->ID : Accounts::create($values['full_name'], $email, null, $values['send_welcome'] === '1');
        if ($userId instanceof WP_Error) {
            $this->flash(['errors' => ['email' => $userId->get_error_message()], 'values' => $values], self::newUrl());
        }

        try {
            $vendor = ($this->create)((int) $userId, $values, $files, $status, $values['plan_id'], $override);
        } catch (VendorValidationException $e) {
            $this->flash(['errors' => $e->errors, 'values' => $values], self::newUrl());
        }
        (new \WP_User((int) $userId))->add_role(Installer::VENDOR_ROLE);

        $message = $existing
            ? __('Vendedor creado con la cuenta existente.', 'cheppi-sellers')
            : ($values['send_welcome'] === '1'
                ? __('Vendedor creado. Le enviamos un correo para crear su contraseña.', 'cheppi-sellers')
                : __('Vendedor creado. La cuenta no recibió correo de bienvenida.', 'cheppi-sellers'));
        $this->flash(['type' => 'success', 'message' => $message], self::detailUrl((int) $vendor->id));
    }

    public function handleUpdate(): void
    {
        $this->authorize();
        $vendorId = absint($_POST['vendor_id'] ?? 0);
        check_admin_referer(self::ACTION_UPDATE . $vendorId);
        $values = $this->textFields(wp_unslash($_POST), self::PROFILE_FIELDS);
        try {
            ($this->update)($vendorId, $values);
            $this->flash(['type' => 'success', 'message' => __('Datos de la tienda actualizados.', 'cheppi-sellers')], self::detailUrl($vendorId));
        } catch (VendorValidationException $e) {
            $this->flash(['type' => 'error', 'message' => __('No se guardó: revisa los datos.', 'cheppi-sellers'), 'errors' => $e->errors, 'values' => $values, 'open' => 'edit'], self::detailUrl($vendorId));
        } catch (DomainException $e) {
            $this->flash(['type' => 'error', 'message' => $e->getMessage()], self::listUrl());
        }
    }

    public function handleDocuments(): void
    {
        $this->authorize();
        $vendorId = absint($_POST['vendor_id'] ?? 0);
        check_admin_referer(self::ACTION_DOCS . $vendorId);
        if ($this->vendors->find($vendorId) === null) {
            $this->flash(['type' => 'error', 'message' => __('Vendedor no encontrado.', 'cheppi-sellers')], self::listUrl());
        }

        $removed = 0;
        foreach ((array) ($_POST['remove'] ?? []) as $typeValue) {
            $type = DocumentType::tryFrom(sanitize_key((string) $typeValue));
            if ($type !== null) {
                $this->documents->delete($vendorId, $type);
                $removed++;
            }
        }
        $uploaded = 0;
        $errors = [];
        foreach (RequestFiles::documents() as $typeValue => $file) {
            try {
                $this->documents->store($vendorId, DocumentType::from($typeValue), $file);
                $uploaded++;
            } catch (VendorValidationException $e) {
                $errors += $e->errors;
            }
        }

        $message = $errors !== []
            ? implode(' ', $errors)
            : sprintf(__('Documentos actualizados: %1$d subidos, %2$d eliminados.', 'cheppi-sellers'), $uploaded, $removed);
        $this->flash(['type' => $errors !== [] ? 'error' : 'success', 'message' => $message], self::detailUrl($vendorId));
    }

    public function handleDelete(): void
    {
        $this->authorize();
        $vendorId = absint($_POST['vendor_id'] ?? 0);
        check_admin_referer(self::ACTION_DELETE . $vendorId);
        try {
            $vendor = ($this->delete)($vendorId, sanitize_text_field(wp_unslash((string) ($_POST['confirm_name'] ?? ''))));
            $user = get_userdata($vendor->userId);
            if ($user) {
                $user->remove_role(Installer::VENDOR_ROLE);
            }
            $this->flash(['type' => 'success', 'message' => sprintf(
                __('Se eliminó la tienda «%s» y sus documentos. La cuenta de usuario se conserva.', 'cheppi-sellers'),
                $vendor->storeName
            )], self::listUrl());
        } catch (DomainException $e) {
            $this->flash(['type' => 'error', 'message' => $e->getMessage(), 'open' => 'delete'], self::detailUrl($vendorId));
        }
    }

    public function handleReview(): void
    {
        $this->authorize();
        $vendorId = absint($_POST['vendor_id'] ?? 0);
        check_admin_referer(self::ACTION_REVIEW . $vendorId);

        $next = VendorStatus::tryFrom(sanitize_key((string) ($_POST['next_status'] ?? '')));
        $note = sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? '')));
        $planId = sanitize_key((string) ($_POST['plan_id'] ?? ''));
        $overrideRaw = trim(sanitize_text_field(wp_unslash((string) ($_POST['commission_override'] ?? ''))));

        try {
            $override = $overrideRaw === '' ? null : Percent::toBps($overrideRaw);
            $vendor = ($this->review)($vendorId, $next, $note, $planId, $override);
            if ($vendor->status === VendorStatus::Approved) {
                $user = get_userdata($vendor->userId);
                if ($user) {
                    $user->add_role(Installer::VENDOR_ROLE);
                }
            }
            $flash = ['type' => 'success', 'message' => __('Cambios guardados. Si cambió el estado, el vendedor recibió un correo.', 'cheppi-sellers')];
        } catch (DomainException|InvalidArgumentException $e) {
            $flash = ['type' => 'error', 'message' => $e->getMessage()];
        }
        $this->flash($flash, self::detailUrl($vendorId));
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

    private function authorize(): void
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('No tienes permiso para hacer esto.', 'cheppi-sellers'), 403);
        }
    }

    /** @param array<string, mixed> $vars */
    private function view(string $template, array $vars): void
    {
        extract($vars, EXTR_SKIP);
        require CHEPPI_SELLERS_DIR . '/templates/admin/' . $template . '.php';
    }

    /** @param array<string, mixed> $data */
    private function flash(array $data, string $redirect): never
    {
        set_transient('cheppi_vendors_flash_' . get_current_user_id(), $data, 300);
        wp_safe_redirect($redirect);
        exit;
    }

    /** @return array<string, mixed> */
    private function takeFlash(): array
    {
        $key = 'cheppi_vendors_flash_' . get_current_user_id();
        $flash = get_transient($key);
        delete_transient($key);
        return is_array($flash) ? $flash : [];
    }

    /**
     * @param array<string, mixed> $post
     * @param list<string> $keys
     * @return array<string, string>
     */
    private function textFields(array $post, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = sanitize_text_field((string) ($post[$key] ?? ''));
        }
        return $out;
    }
}
