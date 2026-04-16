<?php
/**
 * Store pickup code support for WooCommerce orders.
 *
 * @package DeviceHub
 */

defined( 'ABSPATH' ) || exit;

const DEVHUB_PICKUP_CODE_META_KEY = 'pickup_code';

add_action( 'woocommerce_order_status_processing', 'devhub_maybe_generate_pickup_code', 20, 1 );
add_action( 'woocommerce_order_status_completed', 'devhub_maybe_generate_pickup_code', 20, 1 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'devhub_sync_pickup_shipping_meta_on_checkout', 30, 1 );

add_action( 'woocommerce_order_details_after_order_table', 'devhub_render_pickup_code_order_details', 10, 1 );
add_action( 'woocommerce_email_order_meta', 'devhub_render_pickup_code_email', 10, 4 );
add_action( 'woocommerce_admin_order_data_after_order_details', 'devhub_render_pickup_code_admin', 10, 1 );

/**
 * Generate and persist a pickup code once a pickup order has been paid.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function devhub_maybe_generate_pickup_code( int $order_id ): void {
	$order = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	devhub_ensure_pickup_code( $order );
}

/**
 * Check whether an order is a store pickup order.
 *
 * Checks the native WC Local Pickup shipping method first, then falls back to
 * legacy custom meta for orders placed before the switch.
 *
 * @param WC_Order $order WooCommerce order.
 * @return bool
 */
function devhub_is_pickup_order( WC_Order $order ): bool {
	// Native WC Local Pickup (method_id = pickup_location).
	foreach ( $order->get_shipping_methods() as $shipping_item ) {
		if ( 'pickup_location' === $shipping_item->get_method_id() ) {
			return true;
		}
	}

	// Legacy: custom additional-fields approach.
	$delivery_method = devhub_get_pickup_delivery_method( $order );
	if ( 'pickup' === $delivery_method ) {
		return true;
	}

	$pickup_store = sanitize_text_field( (string) $order->get_meta( '_wc_other/' . DEVHUB_CHECKOUT_PICKUP_STORE_FIELD, true ) );
	if ( '' !== $pickup_store ) {
		return true;
	}

	return '' !== devhub_get_pickup_store_label( $order );
}

/**
 * Get the human-readable pickup store label for an order.
 *
 * Reads from the native WC shipping line meta first, then falls back to
 * legacy _devhub_pickup_store_label for older orders.
 *
 * @param WC_Order $order WooCommerce order.
 * @return string
 */
function devhub_get_pickup_store_label( WC_Order $order ): string {
	foreach ( $order->get_shipping_methods() as $shipping_item ) {
		if ( 'pickup_location' === $shipping_item->get_method_id() ) {
			$label = sanitize_text_field( (string) $shipping_item->get_meta( 'pickup_location' ) );
			if ( '' !== $label ) {
				return $label;
			}
		}
	}

	$label = sanitize_text_field( (string) $order->get_meta( DEVHUB_ORDER_PICKUP_STORE_LABEL, true ) );
	if ( '' !== $label ) {
		return $label;
	}

	return sanitize_text_field( (string) $order->get_meta( '_devhub_pickup_store_label', true ) );
}

/**
 * Read the persisted delivery method for a checkout-block order.
 *
 * WooCommerce Blocks saves contact/order fields under the "other" group,
 * so we check both our custom summary meta and the native checkout-field meta.
 *
 * @param WC_Order $order WooCommerce order.
 * @return string
 */
function devhub_get_pickup_delivery_method( WC_Order $order ): string {
	$delivery_method = sanitize_text_field( (string) $order->get_meta( DEVHUB_ORDER_DELIVERY_METHOD_META, true ) );

	if ( '' !== $delivery_method ) {
		return $delivery_method;
	}

	$delivery_method = sanitize_text_field( (string) $order->get_meta( '_devhub_delivery_method', true ) );
	if ( '' !== $delivery_method ) {
		return $delivery_method;
	}

	return sanitize_text_field( (string) $order->get_meta( '_wc_other/' . DEVHUB_CHECKOUT_DELIVERY_METHOD_FIELD, true ) );
}

/**
 * Read the pickup code stored against an order.
 *
 * @param WC_Order $order WooCommerce order.
 * @return string
 */
function devhub_get_pickup_code( WC_Order $order ): string {
	return strtoupper( sanitize_text_field( (string) $order->get_meta( DEVHUB_PICKUP_CODE_META_KEY, true ) ) );
}

/**
 * Collect every pickup code currently stored on the order and shipping items.
 *
 * @param WC_Order $order WooCommerce order.
 * @return array<int, string>
 */
function devhub_get_all_pickup_codes( WC_Order $order ): array {
	$codes = array_filter( devhub_get_wc_meta_values( $order, DEVHUB_PICKUP_CODE_META_KEY ) );

	foreach ( $order->get_shipping_methods() as $shipping_item ) {
		$codes = array_merge( $codes, array_filter( devhub_get_wc_meta_values( $shipping_item, DEVHUB_PICKUP_CODE_META_KEY ) ) );
	}

	$codes = array_values(
		array_unique(
			array_map(
				static function ( string $code ): string {
					return strtoupper( sanitize_text_field( $code ) );
				},
				$codes
			)
		)
	);

	return $codes;
}

/**
 * Collect all values stored under a specific WC meta key.
 *
 * @param WC_Data $object Order or order item object.
 * @param string  $key    Meta key.
 * @return array<int, string>
 */
function devhub_get_wc_meta_values( WC_Data $object, string $key ): array {
	$values = [];

	foreach ( $object->get_meta_data() as $meta ) {
		if ( ! $meta instanceof WC_Meta_Data || $meta->key !== $key ) {
			continue;
		}

		$values[] = sanitize_text_field( (string) $meta->value );
	}

	return $values;
}

/**
 * Replace all instances of a meta key with a single sanitized value.
 *
 * @param WC_Data $object Order or order item object.
 * @param string  $key    Meta key.
 * @param string  $value  Sanitized meta value.
 * @return bool True when a write is required.
 */
function devhub_set_unique_wc_meta( WC_Data $object, string $key, string $value ): bool {
	$value          = sanitize_text_field( $value );
	$existing_values = devhub_get_wc_meta_values( $object, $key );

	if ( 1 === count( $existing_values ) && $existing_values[0] === $value ) {
		return false;
	}

	$object->delete_meta_data( $key );

	if ( '' !== $value ) {
		$object->add_meta_data( $key, $value, true );
	}

	return true;
}

/**
 * Sync pickup-specific data onto the shipping line item for backend consumers.
 *
 * @param WC_Order $order       WooCommerce order.
 * @param string   $pickup_code Pickup code already assigned to the order.
 * @return bool True when any shipping item was updated.
 */
function devhub_sync_pickup_shipping_meta( WC_Order $order, string $pickup_code ): bool {
	$updated       = false;
	$pickup_label  = devhub_get_pickup_store_label( $order );
	$pickup_address = sanitize_text_field( (string) $order->get_meta( DEVHUB_ORDER_PICKUP_STORE_ADDRESS, true ) );
	$pickup_details = sanitize_text_field( (string) $order->get_meta( DEVHUB_ORDER_PICKUP_STORE_DETAILS, true ) );

	foreach ( $order->get_shipping_methods() as $shipping_item ) {
		if ( 'pickup_location' !== $shipping_item->get_method_id() ) {
			continue;
		}

		$item_changed = false;
		$location     = sanitize_text_field( (string) $shipping_item->get_meta( 'pickup_location', true ) );
		$address      = sanitize_text_field( (string) $shipping_item->get_meta( 'pickup_address', true ) );
		$details      = sanitize_text_field( (string) $shipping_item->get_meta( 'pickup_details', true ) );

		if ( '' === $location ) {
			$location = $pickup_label;
		}

		if ( '' === $address ) {
			$address = $pickup_address;
		}

		if ( '' === $details ) {
			$details = $pickup_details;
		}

		$item_changed = devhub_set_unique_wc_meta( $shipping_item, 'pickup_location', $location ) || $item_changed;
		$item_changed = devhub_set_unique_wc_meta( $shipping_item, 'pickup_address', $address ) || $item_changed;
		$item_changed = devhub_set_unique_wc_meta( $shipping_item, 'pickup_details', $details ) || $item_changed;
		$item_changed = devhub_set_unique_wc_meta( $shipping_item, DEVHUB_PICKUP_CODE_META_KEY, $pickup_code ) || $item_changed;

		if ( $item_changed ) {
			$shipping_item->save();
			$updated = true;
		}
	}

	return $updated;
}

/**
 * Sync pickup shipping-line meta as soon as the checkout order is created.
 *
 * This keeps the draft/store-api order payload aligned before payment
 * completion and before a pickup code exists.
 *
 * @param WC_Order $order WooCommerce order.
 * @return void
 */
function devhub_sync_pickup_shipping_meta_on_checkout( WC_Order $order ): void {
	if ( ! devhub_is_pickup_order( $order ) ) {
		return;
	}

	devhub_sync_pickup_shipping_meta( $order, devhub_get_pickup_code( $order ) );
}

/**
 * Ensure a pickup order has a code and return it.
 *
 * This is used both by lifecycle hooks and by render hooks so older orders or
 * timing edge cases still end up with a persisted code when first accessed.
 *
 * @param WC_Order $order WooCommerce order.
 * @return string
 */
function devhub_ensure_pickup_code( WC_Order $order ): string {
	$existing_code  = devhub_get_pickup_code( $order );
	$existing_codes = devhub_get_all_pickup_codes( $order );
	$created_code  = false;

	if ( '' === $existing_code && ! empty( $existing_codes ) ) {
		$existing_code = $existing_codes[0];
	}

	if ( '' === $existing_code && ! devhub_is_pickup_order( $order ) ) {
		return '';
	}

	if ( '' === $existing_code && $order->has_status( [ 'failed', 'cancelled', 'refunded' ] ) ) {
		return '';
	}

	if ( '' === $existing_code ) {
		$existing_code = devhub_generate_unique_pickup_code();
		$created_code  = true;
	}

	$order_changed = devhub_set_unique_wc_meta( $order, DEVHUB_PICKUP_CODE_META_KEY, $existing_code );
	$order_changed = devhub_set_unique_wc_meta( $order, 'source', 'DeviceHub' ) || $order_changed;

	if ( $order_changed ) {
		$order->save_meta_data();
	}

	devhub_sync_pickup_shipping_meta( $order, $existing_code );

	if ( $created_code ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: pickup code */
				__( 'Store pickup code generated: %s', 'devicehub-theme' ),
				$existing_code
			)
		);
		$order->save();
	}

	return $existing_code;
}

/**
 * Create a unique pickup code.
 *
 * @return string
 */
function devhub_generate_unique_pickup_code(): string {
	for ( $attempt = 0; $attempt < 5; $attempt++ ) {
		$code = 'PU-' . strtoupper( wp_generate_password( 8, false, false ) );

		$matches = wc_get_orders(
			[
				'limit'      => 1,
				'return'     => 'ids',
				'meta_key'   => DEVHUB_PICKUP_CODE_META_KEY,
				'meta_value' => $code,
			]
		);

		if ( empty( $matches ) ) {
			return $code;
		}
	}

	return 'PU-' . strtoupper( uniqid() );
}

/**
 * Render the pickup code on customer-facing order details pages.
 *
 * Skipped on the order-received (thank-you) page because the code is already
 * rendered inside the overview list in woocommerce/checkout/thankyou.php.
 *
 * @param WC_Order $order WooCommerce order.
 * @return void
 */
function devhub_render_pickup_code_order_details( WC_Order $order ): void {
	// Already shown in the overview ul on the thank-you page.
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}

	if ( ! devhub_is_pickup_order( $order ) ) {
		return;
	}

	$code = devhub_ensure_pickup_code( $order );

	if ( '' === $code ) {
		return;
	}

	$pickup_store = devhub_get_pickup_store_label( $order );

	echo '<section class="devhub-pickup-code">';
	echo '<h2>' . esc_html__( 'Store pickup code', 'devicehub-theme' ) . '</h2>';
	echo '<p><strong>' . esc_html( $code ) . '</strong></p>';

	if ( '' !== $pickup_store ) {
		echo '<p>' . esc_html(
			sprintf(
				/* translators: %s: store name */
				__( 'Present this code when collecting from %s.', 'devicehub-theme' ),
				$pickup_store
			)
		) . '</p>';
	}

	echo '</section>';
}

/**
 * Render the pickup code in WooCommerce emails.
 *
 * @param WC_Order        $order         WooCommerce order.
 * @param bool            $sent_to_admin Whether the email goes to admin.
 * @param bool            $plain_text    Whether the email is plain text.
 * @param WC_Email|string $email         Email object when available.
 * @return void
 */
function devhub_render_pickup_code_email( WC_Order $order, bool $sent_to_admin, bool $plain_text, $email ): void {
	unset( $sent_to_admin, $email );

	if ( ! devhub_is_pickup_order( $order ) ) {
		return;
	}

	$code = devhub_ensure_pickup_code( $order );

	if ( '' === $code ) {
		return;
	}

	if ( $plain_text ) {
		echo "\n" . sprintf(
			/* translators: %s: pickup code */
			esc_html__( 'Store pickup code: %s', 'devicehub-theme' ),
			$code
		) . "\n";
		return;
	}

	echo '<h2>' . esc_html__( 'Store pickup code', 'devicehub-theme' ) . '</h2>';
	echo '<p><strong>' . esc_html( $code ) . '</strong></p>';
}

/**
 * Render the pickup code in the WooCommerce admin order screen.
 *
 * @param WC_Order $order WooCommerce order.
 * @return void
 */
function devhub_render_pickup_code_admin( WC_Order $order ): void {
	if ( ! devhub_is_pickup_order( $order ) ) {
		return;
	}

	$code = devhub_ensure_pickup_code( $order );

	if ( '' === $code ) {
		return;
	}

	echo '<p><strong>' . esc_html__( 'Store pickup code:', 'devicehub-theme' ) . '</strong> ' . esc_html( $code ) . '</p>';
}
