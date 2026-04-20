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
