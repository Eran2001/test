<?php
/**
 * DeviceHub — Order receipt download button.
 *
 * Appends a Download Invoice button to the "Thank you" text inside the
 * WooCommerce Blocks order-confirmation-status block via the
 * woocommerce_thankyou_order_received_text filter — the order object is
 * passed directly so no URL key lookup is needed.
 *
 * @package DeviceHub
 */

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_thankyou', function ( int $order_id ): void {
    $order = wc_get_order( $order_id );
    if ( ! $order instanceof WC_Order ) {
        return;
    }
    $date_created = $order->get_date_created();
    if ( ! $date_created ) {
        return;
    }
    $time_str = $date_created->date_i18n( wc_time_format() );
    echo '<script>window.devhubOrderTime = ' . wp_json_encode( $time_str ) . ';</script>';
} );

add_filter( 'woocommerce_thankyou_order_received_text', 'devhub_append_invoice_btn_to_thankyou_text', 10, 2 );

function devhub_append_invoice_btn_to_thankyou_text( string $text, $order ): string {
	if ( ! function_exists( 'WPO_WCPDF' ) || ! $order instanceof WC_Order ) {
		return $text;
	}

	$pdf_url = WPO_WCPDF()->endpoint->get_document_link(
		$order,
		'invoice',
		[ 'my-account' => 'true' ]
	);

	if ( empty( $pdf_url ) ) {
		return $text;
	}

	$btn = '<a href="' . esc_url( $pdf_url ) . '" id="devhub-download-invoice-btn" target="_blank" rel="noopener noreferrer">'
		. '<i class="fas fa-download" aria-hidden="true"></i> '
		. esc_html__( 'Download Invoice', 'devicehub-theme' )
		. '</a>';

	return $text . $btn;
}
