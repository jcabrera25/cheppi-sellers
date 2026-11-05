<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use WP_Post;

/**
 * WooCommerce → Productos: "Vendedor" column, one-click "Aprobar y publicar" for products in review.
 */
final class ProductModeration
{
    private const ACTION = 'cheppi_approve_product';

    public function __construct(private readonly WpVendorRepository $vendors)
    {
    }

    public function register(): void
    {
        add_filter('manage_edit-product_columns', [$this, 'addColumn'], 20);
        add_action('manage_product_posts_custom_column', [$this, 'renderColumn'], 10, 2);
        add_filter('the_posts', [$this, 'preloadVendors'], 10, 2);
        add_filter('post_row_actions', [$this, 'rowActions'], 10, 2);
        add_action('admin_post_' . self::ACTION, [$this, 'approve']);
    }

    /** @param array<string, string> $columns */
    public function addColumn(array $columns): array
    {
        $out = [];
        foreach ($columns as $key => $label) {
            $out[$key] = $label;
            if ($key === 'name') {
                $out['cheppi_vendor'] = __('Vendedor', 'cheppi-sellers');
            }
        }
        return $out;
    }

    /** One query for all vendors on the current list page instead of one per row. */
    public function preloadVendors(array $posts, \WP_Query $query): array
    {
        if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'product') {
            $this->vendors->findByUserIds(array_map(static fn (WP_Post $p): int => (int) $p->post_author, $posts));
        }
        return $posts;
    }

    public function renderColumn(string $column, int $postId): void
    {
        if ($column !== 'cheppi_vendor') {
            return;
        }
        $authorId = (int) get_post_field('post_author', $postId);
        $vendor = $this->vendors->findByUserIds([$authorId])[$authorId] ?? null;
        if ($vendor === null) {
            echo '<span aria-hidden="true">—</span>';
            return;
        }
        printf(
            '<a href="%s">%s</a><br><a class="row-title-small" href="%s">%s</a>',
            esc_url(admin_url('edit.php?post_type=product&author=' . $authorId)),
            esc_html($vendor->storeName),
            esc_url(VendorsPage::detailUrl((int) $vendor->id)),
            esc_html__('Ficha', 'cheppi-sellers')
        );
    }

    /** @param array<string, string> $actions */
    public function rowActions(array $actions, WP_Post $post): array
    {
        if ($post->post_type !== 'product' || $post->post_status !== 'pending' || !current_user_can(Capabilities::MANAGE)) {
            return $actions;
        }
        if ($this->vendors->findByUserIds([(int) $post->post_author]) === []) {
            return $actions;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=' . self::ACTION . '&product=' . $post->ID), self::ACTION . $post->ID);
        return ['cheppi_approve' => sprintf('<a href="%s" style="font-weight:600">%s</a>', esc_url($url), esc_html__('Aprobar y publicar', 'cheppi-sellers'))] + $actions;
    }

    public function approve(): void
    {
        $productId = absint($_GET['product'] ?? 0);
        if (!current_user_can(Capabilities::MANAGE) || !wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), self::ACTION . $productId)) {
            wp_die(esc_html__('No tienes permiso para hacer esto.', 'cheppi-sellers'), 403);
        }
        if (get_post_status($productId) === 'pending') {
            // wp_update_post (not wp_publish_post) so WordPress gives the product its URL slug.
            wp_update_post(['ID' => $productId, 'post_status' => 'publish']);
        }
        wp_safe_redirect(admin_url('edit.php?post_type=product&post_status=pending'));
        exit;
    }
}
