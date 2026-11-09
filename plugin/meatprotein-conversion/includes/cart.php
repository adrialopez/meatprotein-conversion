<?php
/**
 * Barra de envío gratis en carrito, checkout y minicarrito.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bandejas de hamburguesas en el carrito: cada pack cuenta 10; las bandejas sueltas, una por unidad. */
function mpc_cart_trays() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}
	$n = 0;
	foreach ( WC()->cart->get_cart() as $item ) {
		$p = isset( $item['data'] ) ? $item['data'] : null;
		if ( ! $p instanceof WC_Product ) {
			continue;
		}
		$parent = $p->is_type( 'variation' ) ? wc_get_product( $p->get_parent_id() ) : $p;
		if ( $parent && mpc_is_pack( $parent ) ) {
			$n += 10 * (int) $item['quantity'];
		} elseif ( $parent && mpc_is_single_burger( $parent ) ) {
			$n += (int) $item['quantity'];
		}
	}
	return $n;
}

/**
 * Regla de envío gratis: solo cuando compran el pack o 10 bandejas de hamburguesas (el pack cuenta como 10).
 * Cualquier otro pedido paga el envío. Lo aplica el plugin Advanced Dynamic Pricing; aquí solo se calcula para mostrarlo.
 *
 * @return string
 */
function mpc_free_ship_rule() {
	return 'Gratis con el pack o con 10 bandejas de hamburguesas';
}

/** Estado del envío gratis: gratis con el pack o con 10 bandejas o más. */
function mpc_free_ship_state() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return null;
	}
	$trays   = mpc_cart_trays();
	$country = WC()->customer ? WC()->customer->get_shipping_country() : '';
	$pt      = 'PT' === $country;
	$free    = ! $pt && $trays >= 10;
	return array(
		'pt'         => $pt,
		'free'       => $free,
		'trays'      => $trays,
		'trays_left' => max( 0, 10 - $trays ),
		'pct'        => $free ? 100 : (int) min( 99, round( $trays / 10 * 100 ) ),
		'count'      => (int) WC()->cart->get_cart_contents_count(),
	);
}

/**
 * Texto de la barra de envío gratis.
 *
 * @param array $st Estado del envío gratis.
 * @return string
 */
function mpc_free_ship_text( $st ) {
	if ( ! empty( $st['pt'] ) ) {
		return 'Envío a <strong>Portugal: ' . esc_html( number_format_i18n( (float) mpc_opt( 'ship_cost_pt' ), 2 ) ) . ' €</strong>, también con el pack';
	}
	if ( $st['free'] ) {
		return '<strong>Envío gratis conseguido</strong>';
	}
	$bandejas = $st['trays_left'] . ( 1 === $st['trays_left'] ? ' bandeja más' : ' bandejas más' );
	return 'Te faltan <strong>' . $bandejas . '</strong> para el envío gratis';
}

/**
 * Barra de envío gratis para carrito y pago.
 *
 * @return string
 */
function mpc_free_ship_html() {
	$st = mpc_free_ship_state();
	if ( ! $st || 0 === $st['count'] ) {
		return '';
	}
	if ( $st['pt'] ) {
		return '<div class="mpc-ship is-pt"><p>' . mpc_free_ship_text( $st ) . '</p></div>';
	}
	return '<div class="mpc-ship' . ( $st['free'] ? ' is-done' : '' ) . '"><p>' . mpc_free_ship_text( $st ) . '</p><span class="mpc-ship__bar"><i style="width:' . (int) $st['pct'] . '%"></i></span></div>';
}

/** Datos mínimos para que el JS pinte la barra en el carrito lateral de Betheme (se refrescan con los fragmentos de WooCommerce). */
function mpc_cartdata_html() {
	$st = mpc_free_ship_state();
	if ( ! $st ) {
		return '<span class="mpc-cartdata" hidden></span>';
	}
	return '<span class="mpc-cartdata" hidden data-count="' . (int) $st['count'] . '" data-pct="' . (int) $st['pct'] . '" data-trays-left="' . (int) $st['trays_left'] . '" data-done="' . ( $st['free'] ? '1' : '0' ) . '" data-pt="' . ( $st['pt'] ? esc_attr( number_format_i18n( (float) mpc_opt( 'ship_cost_pt' ), 2 ) ) : '' ) . '"></span>';
}
add_action(
	'wp_footer',
	function () {
		if ( ! is_admin() ) {
			echo mpc_cartdata_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	3
);

add_action(
	'woocommerce_before_cart',
	function () {
		echo mpc_free_ship_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
);
add_action(
	'woocommerce_checkout_order_review',
	function () {
		echo mpc_free_ship_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	},
	4
);

/** Aviso de entrega con cuenta atrás: lo rellena ui.js según la hora de Madrid (ver `data-mpc-eta`). */
function mpc_eta_html() {
	return '<p class="mpc-eta mpc-eta--cart" data-mpc-eta hidden>' . mpc_icon( 'truck' ) . '<span></span></p>';
}
add_action(
	'woocommerce_before_cart',
	function () {
		echo mpc_eta_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	},
	11
);
add_action(
	'woocommerce_checkout_order_review',
	function () {
		echo mpc_eta_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	},
	5
);
add_filter(
	'woocommerce_add_to_cart_fragments',
	function ( $f ) {
		$ship                              = mpc_free_ship_html();
		$f['.mpc-ship:not(.mpc-ship--sc)'] = '' !== $ship ? $ship : '<div class="mpc-ship" hidden></div>';
		$f['.mpc-cartdata']                = mpc_cartdata_html();
		return $f;
	}
);

/** Los 10 «Bandeja N» del pack se muestran como una sola línea («Pizza ×2, Kebab…»), en carrito y checkout.
 *  El pedido conserva cada bandeja por separado. */
add_filter(
	'woocommerce_get_item_data',
	function ( $data ) {
		$flavours = array();
		$rest     = array();
		foreach ( $data as $row ) {
			if ( isset( $row['key'] ) && preg_match( '/^bandeja\s*\d+$/i', trim( wp_strip_all_tags( $row['key'] ) ) ) ) {
				$v = trim( wp_strip_all_tags( (string) ( $row['display'] ?? $row['value'] ) ) );
				if ( '' !== $v ) {
					$v              = mb_strtolower( html_entity_decode( $v ), 'UTF-8' );
					$v              = mb_strtoupper( mb_substr( $v, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $v, 1, null, 'UTF-8' );
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
		array_unshift(
			$rest,
			array(
				'key'   => 'Tus sabores',
				'value' => implode( ', ', $parts ),
			)
		);
		return $rest;
	},
	20,
	1
);

/** Los packs se llaman en carrito y checkout con un nombre limpio (sin emojis, sin «PROMOCIÓN» ni erratas). */
add_filter(
	'woocommerce_cart_item_name',
	function ( $name, $item ) {
		$p = $item['data'] ?? null;
		if ( $p instanceof WC_Product_Variation ) {
			$p = wc_get_product( $p->get_parent_id() );
		}
		if ( ! $p ) {
			return $name;
		}
		$clean = esc_html( mpc_is_pack( $p ) ? mpc_clean_title( $p->get_name() ) : mpc_nice_name( $p->get_name() ) );
		if ( false !== strpos( $name, '</a>' ) ) {
			$linked = preg_replace( '#(<a[^>]*>)[^<]+(</a>)#u', '${1}' . $clean . '${2}', $name, 1 );
			return null !== $linked ? $linked : $name;
		}
		return $clean;
	},
	5,
	2
);

/** Garantías bajo el botón de pago del carrito. */
add_action(
	'woocommerce_after_cart_totals',
	function () {
		echo '<ul class="mpc-sc-trust mpc-sc-trust--cart"><li>Envío refrigerado con SEUR, entrega antes de las 14:00</li><li>Pago seguro con tarjeta, PayPal o Bizum</li><li>Si llega en mal estado, te reponemos el pedido</li></ul>';
	}
);

/** Móvil: barra fija con el total y el botón de pago mientras el botón real no se ve. */
add_action(
	'wp_footer',
	function () {
		if ( ! is_cart() || WC()->cart->is_empty() ) {
			return;
		}
		echo '<div class="mpc-cartbar" hidden><span><small>Total</small><strong>' . wp_kses_post( WC()->cart->get_total() ) . '</strong></span><a class="button alt" href="' . esc_url( wc_get_checkout_url() ) . '">Finalizar compra</a></div>';
		echo '<script>(function(){var b=document.querySelector(".mpc-cartbar"),c=document.querySelector(".checkout-button, .wc-proceed-to-checkout a");if(!b||!c||!("IntersectionObserver" in window)){return;}new IntersectionObserver(function(e){b.hidden=e[0].isIntersecting||window.innerWidth>849;}).observe(c);})();</script>';
	},
	20
);
