<?php
/**
 * Theme wrapper for the public store page: the active theme (and Divi's Theme Builder) supplies header and footer.
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\StoreController;

defined('ABSPATH') || exit;

get_header();
StoreController::renderCurrent();
get_footer();
