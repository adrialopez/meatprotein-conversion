<?php
/** Barra de envío gratis en carrito, checkout y minicarrito. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Estado del envío gratis: importe de productos (sin envío, con descuentos), lo que falta y el porcentaje. */
function mpc_free_ship_state() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return null;
	}
	$min = (float) mpc_opt( 'free_min' );
	$cnt = (int) WC()->cart->get_cart_contents_count();
	$sub = (float) WC()->cart->get_displayed_subtotal();
	if ( WC()->cart->display_prices_including_tax() ) {
		$sub -= (float) WC()->cart->get_discount_tax();
	}
	$sub = max( 0, $sub - (float) WC()->cart->get_discount_total() );
	return array(
		'min'   => $min,
		'sub'   => $sub,
		'left'  => max( 0, $min - $sub ),
		'pct'   => $min > 0 ? (int) min( 100, round( $sub / $min * 100 ) ) : 100,
		'count' => $cnt,
	);
}

function mpc_free_ship_html() {
	$st = mpc_free_ship_state();
	if ( ! $st || $st['min'] <= 0 || 0 === $st['count'] ) {
		return '';
	}
	$txt = $st['left'] > 0
		? 'Te faltan <strong>' . wc_price( $st['left'] ) . '</strong> para el envío gratis'
		: '<strong>Envío gratis conseguido</strong>';
	return '<div class="mpc-ship' . ( $st['left'] > 0 ? '' : ' is-done' ) . '"><p>' . $txt . '</p><span class="mpc-ship__bar"><i style="width:' . (int) $st['pct'] . '%"></i></span></div>';
}

/** Datos mínimos para que el JS pinte la barra en el carrito lateral de Betheme (se refrescan con los fragmentos de WooCommerce). */
function mpc_cartdata_html() {
	$st = mpc_free_ship_state();
	if ( ! $st ) {
		return '<span class="mpc-cartdata" hidden></span>';
	}
	return '<span class="mpc-cartdata" hidden data-count="' . (int) $st['count'] . '" data-min="' . esc_attr( $st['min'] ) . '" data-pct="' . (int) $st['pct'] . '" data-left="' . esc_attr( wp_strip_all_tags( wc_price( $st['left'] ) ) ) . '" data-done="' . ( $st['left'] > 0 ? '0' : '1' ) . '"></span>';
}
add_action( 'wp_footer', function () {
	if ( ! is_admin() ) {
		echo mpc_cartdata_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 3 );

add_action( 'woocommerce_before_cart', function () {
	echo mpc_free_ship_html(); // phpcs:ignore WordPress.Security.EscapeOutput
} );
add_action( 'woocommerce_checkout_order_review', function () {
	echo mpc_free_ship_html(); // phpcs:ignore WordPress.Security.EscapeOutput
}, 4 );
add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	$f['.mpc-ship:not(.mpc-ship--sc)'] = mpc_free_ship_html() ?: '<div class="mpc-ship" hidden></div>';
	$f['.mpc-cartdata'] = mpc_cartdata_html();
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

/** Los packs se llaman en carrito y checkout con un nombre limpio (sin emojis, sin «PROMOCIÓN» ni erratas). */
add_filter( 'woocommerce_cart_item_name', function ( $name, $item ) {
	$p = $item['data'] ?? null;
	if ( $p instanceof WC_Product_Variation ) {
		$p = wc_get_product( $p->get_parent_id() );
	}
	if ( ! $p ) {
		return $name;
	}
	$clean = esc_html( mpc_is_pack( $p ) ? mpc_clean_title( $p->get_name() ) : mpc_nice_name( $p->get_name() ) );
	if ( false !== strpos( $name, '</a>' ) ) {
		return preg_replace( '#(<a[^>]*>)[^<]+(</a>)#u', '${1}' . $clean . '${2}', $name, 1 ) ?: $name;
	}
	return $clean;
}, 5, 2 );

/** Garantías bajo el botón de pago del carrito. */
add_action( 'woocommerce_after_cart_totals', function () {
	echo '<ul class="mpc-sc-trust mpc-sc-trust--cart"><li>Envío refrigerado con SEUR en 24-48 h</li><li>Pago seguro con tarjeta, PayPal o Bizum</li><li>Si llega en mal estado, lo reponemos o te devolvemos el dinero</li></ul>';
} );

/** Móvil: barra fija con el total y el botón de pago mientras el botón real no se ve. */
add_action( 'wp_footer', function () {
	if ( ! is_cart() || WC()->cart->is_empty() ) {
		return;
	}
	echo '<div class="mpc-cartbar" hidden><span><small>Total</small><strong>' . wp_kses_post( WC()->cart->get_total() ) . '</strong></span><a class="button alt" href="' . esc_url( wc_get_checkout_url() ) . '">Finalizar compra</a></div>';
	echo '<script>(function(){var b=document.querySelector(".mpc-cartbar"),c=document.querySelector(".checkout-button, .wc-proceed-to-checkout a");if(!b||!c||!("IntersectionObserver" in window)){return;}new IntersectionObserver(function(e){b.hidden=e[0].isIntersecting||window.innerWidth>849;}).observe(c);})();</script>';
}, 20 );
