<?php
/**
 * No products found loop template.
 *
 * @package DeviceHub
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="devhub-archive-empty-state" role="status">
    <span class="devhub-archive-empty-state__icon" aria-hidden="true">
        <i class="far fa-copy"></i>
    </span>
    <p class="devhub-archive-empty-state__message">
        <?php esc_html_e( 'No products were found matching your selection.', 'woocommerce' ); ?>
    </p>
</div>
