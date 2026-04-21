<?php
/**
 * View Order — DeviceHub override
 *
 * Shows the details of a particular order on the account page.
 * Status summary paragraph is intentionally suppressed.
 *
 * Based on WooCommerce template version 10.6.0.
 *
 * @package DeviceHub
 */

defined( 'ABSPATH' ) || exit;

$notes = $order->get_customer_order_notes();

$devhub_get_order_stage_label = static function ( WC_Order $order ): string {
	$stage = sanitize_key( (string) $order->get_meta( 'devicehub_order_status', true ) );

	if ( '' === $stage ) {
		return '';
	}

	$labels = [
		'order_received'         => __( 'Order received', 'devicehub-theme' ),
		'payment_pending'        => __( 'Payment pending', 'devicehub-theme' ),
		'payment_confirmed'      => __( 'Payment confirmed', 'devicehub-theme' ),
		'packaging_in_progress'  => __( 'Packaging in progress', 'devicehub-theme' ),
		'ready_for_dispatch'     => __( 'Ready for dispatch', 'devicehub-theme' ),
		'dispatched'             => __( 'Dispatched', 'devicehub-theme' ),
		'out_for_delivery'       => __( 'Out for delivery', 'devicehub-theme' ),
		'ready_for_pickup'       => __( 'Ready for pickup', 'devicehub-theme' ),
		'completed'              => __( 'Completed', 'devicehub-theme' ),
		'cancelled'              => __( 'Cancelled', 'devicehub-theme' ),
	];

	if ( isset( $labels[ $stage ] ) ) {
		return $labels[ $stage ];
	}

	return ucwords( str_replace( '_', ' ', $stage ) );
};

$devhub_order_stage_label = $devhub_get_order_stage_label( $order );
?>

<?php /*
<p>
<?php
echo wp_kses_post(
    apply_filters(
        'woocommerce_order_details_status',
        sprintf(
            esc_html__( 'Order #%1$s was placed on %2$s and is currently %3$s.', 'woocommerce' ),
            '<mark class="order-number">' . $order->get_order_number() . '</mark>',
            '<mark class="order-date">' . wc_format_datetime( $order->get_date_created() ) . '</mark>',
            '<mark class="order-status">' . wc_get_order_status_name( $order->get_status() ) . '</mark>'
        ),
        $order
    )
);
?>
</p>
*/ ?>

<?php
$devhub_tracking_number = sanitize_text_field( (string) $order->get_meta( 'tracking_code', true ) );
$devhub_courier_name    = sanitize_text_field( (string) $order->get_meta( 'courier_name', true ) );
$devhub_tracking_url    = esc_url( (string) $order->get_meta( 'tracking_link', true ) );
?>

<?php if ( '' !== $devhub_tracking_number ) : ?>
<section class="devhub-tracking-info">
	<h2><?php esc_html_e( 'Shipment Tracking', 'devicehub-theme' ); ?></h2>
	<div class="devhub-tracking-info__card">
		<?php if ( '' !== $devhub_courier_name ) : ?>
		<div class="devhub-tracking-info__row">
			<span class="devhub-tracking-info__label"><?php esc_html_e( 'Courier', 'devicehub-theme' ); ?></span>
			<span class="devhub-tracking-info__value"><?php echo esc_html( $devhub_courier_name ); ?></span>
		</div>
		<?php endif; ?>
		<div class="devhub-tracking-info__row">
			<span class="devhub-tracking-info__label"><?php esc_html_e( 'Tracking number', 'devicehub-theme' ); ?></span>
			<span class="devhub-tracking-info__value">
				<?php if ( '' !== $devhub_tracking_url ) : ?>
					<a href="<?php echo $devhub_tracking_url; ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $devhub_tracking_number ); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html( $devhub_tracking_number ); ?>
				<?php endif; ?>
			</span>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="devhub-order-stage-summary">
	<?php if ( '' !== $devhub_order_stage_label ) : ?>
		<p>
			<strong><?php esc_html_e( 'Order stage:', 'devicehub-theme' ); ?></strong>
			<?php echo esc_html( $devhub_order_stage_label ); ?>
		</p>
	<?php endif; ?>
	<p>
		<strong><?php esc_html_e( 'Order status:', 'devicehub-theme' ); ?></strong>
		<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
	</p>
</section>

<?php if ( $notes ) : ?>
    <h2><?php esc_html_e( 'Order updates', 'woocommerce' ); ?></h2>
    <ol class="woocommerce-OrderUpdates commentlist notes">
        <?php foreach ( $notes as $note ) : ?>
        <li class="woocommerce-OrderUpdate comment note">
            <div class="woocommerce-OrderUpdate-inner comment_container">
                <div class="woocommerce-OrderUpdate-text comment-text">
                    <p class="woocommerce-OrderUpdate-meta meta"><?php echo date_i18n( esc_html__( 'l jS \o\f F Y, h:ia', 'woocommerce' ), strtotime( $note->comment_date ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                    <div class="woocommerce-OrderUpdate-description description">
                        <?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
                    </div>
                    <div class="clear"></div>
                </div>
                <div class="clear"></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php do_action( 'woocommerce_view_order', $order_id ); ?>
