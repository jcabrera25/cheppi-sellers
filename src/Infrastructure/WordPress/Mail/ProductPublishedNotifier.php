<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Mail;

use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use WP_Post;

/**
 * Tells the vendor when a product they sent to review goes live.
 */
final class ProductPublishedNotifier
{
    public function __construct(
        private readonly WpVendorRepository $vendors,
        private readonly WpMailNotifier $mailer
    ) {
    }

    public function register(): void
    {
        add_action('transition_post_status', [$this, 'onTransition'], 10, 3);
    }

    public function onTransition(string $new, string $old, WP_Post $post): void
    {
        if ($post->post_type !== 'product' || $new !== 'publish' || $old !== 'pending') {
            return;
        }
        $vendor = $this->vendors->findByUserId((int) $post->post_author);
        if ($vendor === null || get_current_user_id() === $vendor->userId) {
            return;
        }
        $this->mailer->productPublished($vendor, $post->post_title, (string) get_permalink($post));
    }
}
