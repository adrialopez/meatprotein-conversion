<?php
/**
 * El pack está unido a las hamburguesas individuales: cada «Bandeja N» es 1 bandeja (4 hamburguesas) de ese producto.
 * - Solo se ofrecen sabores con stock; no se puede pedir más bandejas de una hamburguesa de las que quedan.
 * - Al pedirse el pack se descuenta el stock de cada hamburguesa (y de la bandeja de regalo, si se configura)
 * y se restituye si el pedido se cancela o se devuelve el stock.
 * - El pedido guarda de qué productos se compone cada pack (trazabilidad), visible en el admin.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clave normalizada de un sabor.
 *
 * @param string $text Texto.
 * @return string
 */
function mpc_flavour_key( $text ) {
	$k = strtolower( remove_accents( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) );
	$k = str_replace( '&', ' y ', $k );
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', $k ) );
}

/**
 * IDs de los productos de regalo configurados (uno o varios, separados por comas).
 *
 * @return int[]
 */
function mpc_gift_ids() {
	$raw = trim( (string) mpc_opt( 'gift_product' ) );
	if ( '' === $raw ) {
		return array();
	}
	return array_values( array_unique( array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', $raw ) ) ) ) );
}

/**
 * Hay más de un regalo y el cliente puede elegir.
 *
 * @return bool
 */
function mpc_gift_multi() {
	return count( mpc_gift_ids() ) > 1;
}

/**
 * Regalos publicados, en el orden de los ajustes.
 *
 * @return WC_Product[]
 */
function mpc_gift_choices() {
	$out = array();
	foreach ( mpc_gift_ids() as $id ) {
		$p = wc_get_product( $id );
		if ( $p && 'publish' === get_post_status( $id ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Regalo que se aplica: el pedido por el cliente si está en la lista blanca o, si no, el primero con stock.
 *
 * @param int $requested ID elegido (0 = ninguno).
 * @return int ID de producto, o 0 si no hay regalo configurado.
 */
function mpc_pick_gift( $requested = 0 ) {
	$ids       = mpc_gift_ids();
	$requested = (int) $requested;
	if ( ! $ids ) {
		return 0;
	}
	if ( $requested && in_array( $requested, $ids, true ) ) {
		return $requested;
	}
	foreach ( $ids as $id ) {
		$avail = mpc_available_trays( $id );
		if ( null === $avail || $avail > 0 ) {
			return $id;
		}
	}
	return $ids[0];
}

/**
 * Regalo elegido en el formulario de la ficha (el formulario de WooCommerce no lleva nonce; se sanea y la lista blanca decide).
 *
 * @return int
 */
function mpc_requested_gift() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Ver el comentario de la función.
	return isset( $_REQUEST['mpc_gift'] ) ? absint( wp_unslash( $_REQUEST['mpc_gift'] ) ) : 0;
}

/**
 * Texto del regalo para titulares y listas: con varios regalos no se nombra uno concreto.
 *
 * @param string $named Texto con el regalo concreto (el del título del pack).
 * @return string
 */
function mpc_gift_text( $named ) {
	return mpc_gift_multi() ? '1 bandeja de regalo a tu elección' : $named;
}

/**
 * Bandejas elegidas en un pack, a partir de sus atributos.
 *
 * @param array $attrs   Atributos de variación.
 * @param int   $gift_id Regalo elegido (0 = el primero con stock).
 * @return mixed Resultado.
 */
function mpc_components_from_attributes( $attrs, $gift_id = 0 ) {
	$map  = mpc_flavour_products();
	$out  = array();
	$miss = array();
	foreach ( (array) $attrs as $name => $value ) {
		if ( ! preg_match( '/^attribute_bandeja-\d+$/i', $name ) || '' === trim( (string) $value ) ) {
			continue;
		}
		$key = mpc_flavour_key( $value );
		if ( isset( $map[ $key ] ) ) {
			$id         = (int) $map[ $key ];
			$out[ $id ] = ( $out[ $id ] ?? 0 ) + 1;
		} else {
			$miss[] = (string) $value;
		}
	}
	$gift = mpc_pick_gift( $gift_id );
	if ( $gift && $out ) {
		$out[ $gift ] = ( $out[ $gift ] ?? 0 ) + 1;
	}
	return array( $out, $miss );
}

/**
 * Bandejas disponibles de una hamburguesa individual.
 *
 * @param int $product_id ID de producto.
 * @return mixed Resultado.
 */
function mpc_available_trays( $product_id ) {
	$p = wc_get_product( $product_id );
	if ( ! $p || 'publish' !== get_post_status( $product_id ) ) {
		return 0;
	}
	if ( ! $p->is_in_stock() ) {
		return 0;
	}
	return $p->managing_stock() ? max( 0, (int) $p->get_stock_quantity() ) : null;
}

/**
 * Bandejas de cada hamburguesa que ya pide el carrito.
 *
 * @param string $skip_key Línea del carrito que se omite.
 * @return mixed Resultado.
 */
function mpc_cart_tray_demand( $skip_key = '' ) {
	$need = array();
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $need;
	}
	foreach ( WC()->cart->get_cart() as $key => $item ) {
		if ( $key === $skip_key ) {
			continue;
		}
		$qty = (int) $item['quantity'];
		$p   = wc_get_product( $item['product_id'] );
		if ( $p && mpc_is_pack( $p ) ) {
			list( $comp ) = mpc_components_from_attributes( $item['variation'] ?? array(), $item['mpc_gift'] ?? 0 );
			foreach ( $comp as $id => $n ) {
				$need[ $id ] = ( $need[ $id ] ?? 0 ) + $n * $qty;
			}
		} else {
			$need[ (int) $item['product_id'] ] = ( $need[ (int) $item['product_id'] ] ?? 0 ) + $qty;
		}
	}
	return $need;
}

/**
 * Comprueba que haya stock de cada bandeja de un componente.
 *
 * @param array $comp Componente del pack.
 * @param int   $qty Cantidad.
 * @param array $already Bandejas ya contadas.
 * @return mixed Resultado.
 */
function mpc_check_components( $comp, $qty, $already = array() ) {
	foreach ( $comp as $id => $n ) {
		$avail = mpc_available_trays( $id );
		if ( null === $avail ) {
			continue;
		}
		$want = $n * $qty + ( $already[ $id ] ?? 0 );
		if ( $want > $avail ) {
			$name = html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' );
			return 0 === $avail
				? sprintf( 'Lo sentimos, «%s» se ha agotado. Elige otro sabor.', $name )
				: sprintf( 'Lo sentimos, de «%s» solo quedan %d bandejas. Elige otro sabor o reduce la cantidad.', $name, $avail );
		}
	}
	return '';
}

/* ---- Validación al añadir y al finalizar la compra ---- */
add_filter(
	'woocommerce_add_to_cart_validation',
	function ( $ok, $product_id, $qty, $variation_id = 0, $variation = array() ) {
		if ( ! $ok ) {
			return $ok;
		}
		$p = wc_get_product( $product_id );
		if ( ! $p || ! mpc_is_pack( $p ) ) {
			return $ok;
		}
		list( $comp ) = mpc_components_from_attributes( $variation, mpc_requested_gift() );
		$msg          = mpc_check_components( $comp, max( 1, (int) $qty ), mpc_cart_tray_demand() );
		if ( $msg ) {
			wc_add_notice( $msg, 'error' );
			return false;
		}
		return $ok;
	},
	20,
	5
);

add_action(
	'woocommerce_check_cart_items',
	function () {
		if ( ! WC()->cart ) {
			return;
		}
		$need = mpc_cart_tray_demand();
		foreach ( $need as $id => $n ) {
			$avail = mpc_available_trays( $id );
			if ( null !== $avail && $n > $avail ) {
				wc_add_notice(
					0 === $avail
					? sprintf( 'Lo sentimos, «%s» se ha agotado. Quítalo de tu pack para continuar.', wp_strip_all_tags( get_the_title( $id ) ) )
					: sprintf( 'De «%s» solo quedan %d bandejas y tienes %d en tu pedido.', wp_strip_all_tags( get_the_title( $id ) ), $avail, $n ),
					'error'
				);
			}
		}
	}
);

/* ---- Trazabilidad: componentes guardados en la línea del pedido ---- */
add_action(
	'woocommerce_checkout_create_order_line_item',
	function ( $item, $cart_key, $values ) {
		$p = wc_get_product( $values['product_id'] ?? 0 );
		if ( ! $p || ! mpc_is_pack( $p ) ) {
			return;
		}
		list( $comp, $miss ) = mpc_components_from_attributes( $values['variation'] ?? array(), $values['mpc_gift'] ?? 0 );
		if ( ! $comp ) {
			return;
		}
		$rows = array();
		foreach ( $comp as $id => $n ) {
			$cp     = wc_get_product( $id );
			$rows[] = array(
				'product_id' => (int) $id,
				'sku'        => $cp ? $cp->get_sku() : '',
				'name'       => html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' ),
				'trays'      => (int) $n,
			);
		}
		$item->add_meta_data( '_mpc_components', $rows );
		if ( mpc_gift_multi() ) {
			// El título del pack nombra un regalo concreto; con elección, la línea del pedido va sin él y el elegido en «Regalo».
			$item->set_name( mpc_display_name( trim( explode( '+', mpc_clean_title( $item->get_name() ), 2 )[0] ) ) );
		}
		if ( ! empty( $values['mpc_gift'] ) ) {
			$item->add_meta_data( 'Regalo', mpc_display_name( wp_strip_all_tags( get_the_title( (int) $values['mpc_gift'] ) ) ) );
		}
		if ( $miss ) {
			$item->add_meta_data( '_mpc_unmapped', $miss );
		}
	},
	10,
	3
);

add_action(
	'woocommerce_after_order_itemmeta',
	function ( $item_id, $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$rows = $item->get_meta( '_mpc_components' );
		if ( ! is_array( $rows ) || ! $rows ) {
			return;
		}
		$qty  = (int) $item->get_quantity();
		$done = 'yes' === $item->get_meta( '_mpc_stock_reduced' );
		echo '<div class="mpc-trace" style="margin:.6em 0 0;padding:.6em .8em;background:#f6f7f7;border-left:3px solid #2271b1"><strong>Composición del pack (trazabilidad)</strong> · stock ' . ( $done ? 'descontado' : 'pendiente de descontar' ) . '<ul style="margin:.4em 0 0;list-style:disc;padding-left:1.4em">';
		foreach ( $rows as $r ) {
			echo '<li>' . esc_html( $r['name'] ) . ( $r['sku'] ? ' <code>' . esc_html( $r['sku'] ) . '</code>' : '' ) . ' &times; ' . (int) ( $r['trays'] * $qty ) . ' bandejas <a href="' . esc_url( get_edit_post_link( $r['product_id'] ) ) . '">#' . (int) $r['product_id'] . '</a></li>';
		}
		echo '</ul>';
		if ( $item->get_meta( '_mpc_unmapped' ) ) {
			echo '<p style="color:#b32d2e;margin:.4em 0 0">Sabores sin producto asociado: ' . esc_html( implode( ', ', (array) $item->get_meta( '_mpc_unmapped' ) ) ) . '</p>';
		}
		echo '</div>';
	},
	10,
	2
);

/* ---- Stock: se descuenta de cada hamburguesa y se devuelve si se restituye el stock del pedido ---- */
add_action(
	'woocommerce_reduce_order_stock',
	function ( $order ) {
		foreach ( $order->get_items() as $item ) {
			$rows = $item->get_meta( '_mpc_components' );
			if ( ! is_array( $rows ) || 'yes' === $item->get_meta( '_mpc_stock_reduced' ) ) {
				continue;
			}
			$note = array();
			foreach ( $rows as $r ) {
				$cp = wc_get_product( $r['product_id'] );
				if ( $cp && $cp->managing_stock() ) {
					$n      = (int) $r['trays'] * (int) $item->get_quantity();
					$new    = wc_update_product_stock( $cp, $n, 'decrease' );
					$note[] = sprintf( '%s −%d (quedan %s)', $r['name'], $n, is_wp_error( $new ) ? '?' : $new );
				}
			}
			$item->update_meta_data( '_mpc_stock_reduced', 'yes' );
			$item->save();
			if ( $note ) {
				$order->add_order_note( 'Stock de las hamburguesas del pack: ' . implode( '; ', $note ) );
			}
		}
	}
);
add_action(
	'woocommerce_restore_order_stock',
	function ( $order ) {
		foreach ( $order->get_items() as $item ) {
			$rows = $item->get_meta( '_mpc_components' );
			if ( ! is_array( $rows ) || 'yes' !== $item->get_meta( '_mpc_stock_reduced' ) ) {
				continue;
			}
			foreach ( $rows as $r ) {
				$cp = wc_get_product( $r['product_id'] );
				if ( $cp && $cp->managing_stock() ) {
					wc_update_product_stock( $cp, (int) $r['trays'] * (int) $item->get_quantity(), 'increase' );
				}
			}
			$item->update_meta_data( '_mpc_stock_reduced', 'no' );
			$item->save();
			$order->add_order_note( 'Stock de las hamburguesas del pack restituido.' );
		}
	}
);

/* ---- Varios regalos: el elegido va en la línea del carrito (líneas distintas por regalo), se ve en carrito y pedido ---- */
add_filter(
	'woocommerce_add_cart_item_data',
	function ( $data, $product_id ) {
		$p = wc_get_product( $product_id );
		if ( $p && mpc_is_pack( $p ) && mpc_gift_multi() ) {
			$data['mpc_gift'] = mpc_pick_gift( mpc_requested_gift() );
		}
		return $data;
	},
	10,
	2
);
add_filter(
	'woocommerce_get_item_data',
	function ( $data, $cart_item ) {
		if ( ! empty( $cart_item['mpc_gift'] ) ) {
			$data[] = array(
				'key'   => 'Regalo',
				'value' => mpc_display_name( wp_strip_all_tags( get_the_title( (int) $cart_item['mpc_gift'] ) ) ),
			);
		}
		return $data;
	},
	20,
	2
);
