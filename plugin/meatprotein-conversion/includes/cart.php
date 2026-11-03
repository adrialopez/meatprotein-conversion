<?php
/** Barra de envío gratis en carrito, checkout y minicarrito. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mpc_free_ship_html() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return '';
	}
	$min = (float) mpc_opt( 'free_min' );
	if ( $min <= 0 ) {
		return '';
	}
	$sub = (float) WC()->cart->get_displayed_subtotal();
	if ( WC()->cart->display_prices_including_tax() ) {
		$sub -= (float) WC()->cart->get_discount_tax();
	}
	$sub  = max( 0, $sub - (float) WC()->cart->get_discount_total() );
	$left = max( 0, $min - $sub );
	$pct  = min( 100, round( $sub / $min * 100 ) );
	if ( WC()->cart->is_empty() ) {
		return '';
	}
	$txt = $left > 0
		? 'Te faltan <strong>' . wc_price( $left ) . '</strong> para el envío gratis'
		: '<strong>Envío gratis conseguido</strong>';
	return '<div class="mpc-ship' . ( $left > 0 ? '' : ' is-done' ) . '"><p>' . $txt . '</p><span class="mpc-ship__bar"><i style="width:' . (int) $pct . '%"></i></span></div>';
}

add_action( 'woocommerce_before_cart', function () {
	echo mpc_free_ship_html(); // phpcs:ignore WordPress.Security.EscapeOutput
} );
add_action( 'woocommerce_checkout_order_review', function () {
	echo mpc_free_ship_html(); // phpcs:ignore WordPress.Security.EscapeOutput
}, 4 );
add_action( 'woocommerce_before_mini_cart_contents', function () {
	echo mpc_free_ship_html(); // phpcs:ignore WordPress.Security.EscapeOutput
} );
add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	$f['.mpc-ship'] = mpc_free_ship_html() ?: '<div class="mpc-ship" hidden></div>';
	return $f;
} );

/** Los 10 «Bandeja N» del pack se muestran como una sola línea («Pizza ×2, Kebab…»), en carrito y checkout.
 *  El pedido conserva cada bandeja por separado. */
add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
	$flavours = array();
	$rest     = array();
	foreach ( $data as $row ) {
		if ( isset( $row['key'] ) && preg_match( '/^bandeja\s*\d+$/i', trim( wp_strip_all_tags( $row['key'] ) ) ) ) {
			$v = trim( wp_strip_all_tags( (string) ( $row['display'] ?? $row['value'] ) ) );
			if ( '' !== $v ) {
				$v = mb_strtolower( html_entity_decode( $v ), 'UTF-8' );
				$v = mb_strtoupper( mb_substr( $v, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $v, 1, null, 'UTF-8' );
				$flavours[ $v ] = ( $flavours[ $v ] ?? 0 ) + 1;
			}
		} else {
			$rest[] = $row;
		}
	}
	if ( count( $flavours ) < 2 ) {
		return $data;
	}
	arsort( $flavours );
	$parts = array();
	foreach ( $flavours as $name => $n ) {
		$parts[] = $n > 1 ? $name . ' ×' . $n : $name;
	}
	array_unshift( $rest, array( 'key' => 'Tus sabores', 'value' => implode( ', ', $parts ) ) );
	return $rest;
}, 20, 2 );
