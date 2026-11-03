<?php
/**
 * Extras para el pack (producto estrella): se ofrecen en la ficha, en el carrito lateral, en el carrito
 * y en el checkout. Por defecto se eligen solos: el producto más vendido de cada categoría que no sea
 * hamburguesas (queso, pan, pizza, salsas, snacks…). Se pueden fijar a mano en WooCommerce > Conversión.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** IDs de los extras, en orden. */
function mpc_addon_ids() {
	$cfg = trim( (string) mpc_opt( 'addons' ) );
	if ( '' !== $cfg ) {
		$ids = array_values( array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', $cfg ) ) ) );
	} else {
		$ids = get_transient( 'mpc_addon_ids_auto' );
		if ( ! is_array( $ids ) ) {
			$ids   = array();
			$seen  = array();
			$ids_all = get_posts( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 150,
				'fields'         => 'ids',
				'meta_key'       => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'tax_query'      => array( array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'simple' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			) );
			$prods = array_filter( array_map( 'wc_get_product', $ids_all ), function ( $p ) {
				return $p && $p->is_in_stock();
			} );
			foreach ( $prods as $p ) {
				$cats = $p->get_category_ids();
				if ( ! $cats || has_term( 'hamburguesas', 'product_cat', $p->get_id() ) || (float) $p->get_price() <= 0 ) {
					continue;
				}
				$key = (int) $cats[0];
				if ( isset( $seen[ $key ] ) ) {
					continue; // uno por categoría, para que la oferta sea variada
				}
				$seen[ $key ] = true;
				$ids[]        = $p->get_id();
				if ( count( $ids ) >= 6 ) {
					break;
				}
			}
			set_transient( 'mpc_addon_ids_auto', $ids, 6 * HOUR_IN_SECONDS );
		}
	}
	return apply_filters( 'mpc_addon_ids', $ids );
}

/** Extras disponibles que no están ya en el carrito. */
function mpc_addons_available( $limit = 6 ) {
	$in_cart = array();
	if ( function_exists( 'WC' ) && WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $item ) {
			$in_cart[ (int) $item['product_id'] ] = true;
		}
	}
	$out = array();
	foreach ( mpc_addon_ids() as $id ) {
		if ( isset( $in_cart[ $id ] ) ) {
			continue;
		}
		$p = wc_get_product( $id );
		if ( $p && $p->is_purchasable() && $p->is_in_stock() && 'publish' === get_post_status( $id ) ) {
			$out[] = $p;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
	}
	return $out;
}

/** El primero de la lista por ventas se marca como «Más pedido». */
function mpc_addon_is_top( $product ) {
	static $top = null;
	if ( null === $top ) {
		$best = 0;
		$top  = 0;
		foreach ( mpc_addon_ids() as $id ) {
			$p = wc_get_product( $id );
			if ( $p && (int) $p->get_total_sales() > $best ) {
				$best = (int) $p->get_total_sales();
				$top  = $id;
			}
		}
	}
	return $top && (int) $product->get_id() === (int) $top && (int) $product->get_total_sales() >= 100;
}

/* ---------------------------------------------------------------------------------------------
   1) Ficha del pack: bundle a medida por pasos (sabores → regalo → salsa → pan → queso → otros extras).
   --------------------------------------------------------------------------------------------- */

/** Nombre más cómodo de leer: sin «NEW», sin gramos sueltos y sin mayúsculas gritadas. */
function mpc_nice_name( $name ) {
	$n = trim( preg_replace( '/\s+(NEW!?)$/iu', '', wp_strip_all_tags( html_entity_decode( $name ) ) ) );
	$letters = preg_replace( '/[^\p{L}]+/u', '', $n );
	$upper   = preg_replace( '/[^\p{Lu}]+/u', '', $n );
	if ( mb_strlen( $letters, 'UTF-8' ) > 4 && mb_strlen( $upper, 'UTF-8' ) / mb_strlen( $letters, 'UTF-8' ) > 0.7 ) {
		$n = mb_strtolower( $n, 'UTF-8' );
		$n = mb_strtoupper( mb_substr( $n, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $n, 1, null, 'UTF-8' );
		$n = preg_replace( '/\b(\d+)\s?(ml|g|gr)\b/u', '$1 $2', $n );
	}
	return $n;
}

/** Nombre corto para tarjetas y resumen: sin gramos ni relleno de marca («Pan cosa nostra», «Queso curado proteico»). */
function mpc_short_name( $name ) {
	$n = mpc_nice_name( $name );
	$n = preg_replace( '/\s*[-–]\s*\d+\s?(g|gr|ml)\b/iu', '', $n );
	$n = preg_replace( '/\s+\d+\s?(g|gr|ml)\b/iu', '', $n );
	$n = preg_replace( '/^Pan de hamburguesas\s+/iu', 'Pan ', $n );
	$n = preg_replace( '/\s+Meatprotein\b/iu', '', $n );
	return trim( $n );
}

/** Hamburguesa suelta (bandeja): producto simple de la categoría «hamburguesas». */
function mpc_is_single_burger( $product ) {
	return $product instanceof WC_Product && $product->is_type( 'simple' ) && has_term( 'hamburguesas', 'product_cat', $product->get_id() ) && ! mpc_is_pack( $product );
}

/** Productos de un paso del bundle: los fijados en ajustes o, si no, los más vendidos de esas categorías que cumplan el nombre. */
function mpc_step_products( $setting, $cats, $include, $exclude, $limit, $skip_id = 0 ) {
	$cfg = trim( (string) mpc_opt( $setting ) );
	if ( '' !== $cfg ) {
		$ids = array_values( array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', $cfg ) ) ) );
	} else {
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 80,
			'fields'         => 'ids',
			'meta_key'       => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cats ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
	}
	$out = array();
	foreach ( $ids as $id ) {
		if ( (int) $id === (int) $skip_id ) {
			continue;
		}
		$p = wc_get_product( $id );
		if ( ! $p || ! $p->is_type( 'simple' ) || ! $p->is_purchasable() || ! $p->is_in_stock() ) {
			continue;
		}
		$name = $p->get_name();
		if ( '' === $cfg && ( ( $include && ! preg_match( $include, $name ) ) || ( $exclude && preg_match( $exclude, $name ) ) ) ) {
			continue;
		}
		$out[] = $p;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * Preguntas del flujo de compra, como en una app de pedidos: cada una es un carrusel de tarjetas con «+».
 * Pack: salsa, pan, queso. Bandeja suelta: pan, queso, salsa y «¿otra hamburguesa?».
 */
function mpc_bundle_steps( $product = null ) {
	$skip  = $product instanceof WC_Product ? $product->get_id() : 0;
	$salsa = array(
		'title' => '¿Quieres salsa?',
		'hint'  => 'Añade las que quieras',
		'items' => mpc_step_products( 'step_salsa', array( 'salsas-y-cremas', 'saladas' ), '/(deluxe|burger|mayo|barbacoa|ketchup|mostaza|grandmas|salsa)/i', '/(sirope|t[eé] fr[ií]o|crema)/i', 6 ),
	);
	$pan   = array(
		'title' => '¿Añades pan?',
		'hint'  => 'Para tus hamburguesas',
		'items' => mpc_step_products( 'step_pan', array( 'pan' ), '/^pan\b/i', '', 3 ),
	);
	$queso = array(
		'title' => '¿Y queso?',
		'hint'  => 'Para fundir o picar',
		'items' => mpc_step_products( 'step_queso', array( 'queso' ), '', '', 3 ),
	);
	if ( mpc_is_single_burger( $product ) ) {
		$steps = array(
			'pan'   => $pan,
			'queso' => $queso,
			'salsa' => $salsa,
			'otras' => array(
				'title' => '¿Otra hamburguesa?',
				'hint'  => 'Las más pedidas',
				'items' => mpc_step_products( 'step_otras', array( 'hamburguesas' ), '', '/(pack|promoci)/i', 8, $skip ),
			),
		);
	} else {
		$steps = array( 'salsa' => $salsa, 'pan' => $pan, 'queso' => $queso );
	}
	return apply_filters( 'mpc_bundle_steps', array_filter( $steps, function ( $st ) {
		return ! empty( $st['items'] );
	} ), $product );
}

/** Tarjeta de un extra: foto, nombre, precio y «+» que pasa a +/− al elegirlo. */
function mpc_card_html( $p ) {
	$id    = (int) $p->get_id();
	$price = (float) wc_get_price_to_display( $p );
	$max   = $p->managing_stock() ? max( 1, min( 10, (int) $p->get_stock_quantity() ) ) : 10;
	$name  = mpc_short_name( $p->get_name() );
	$h     = '<div class="mpc-card" data-id="' . $id . '" data-price="' . esc_attr( $price ) . '" data-name="' . esc_attr( $name ) . '" data-max="' . (int) $max . '">';
	$h    .= '<span class="mpc-card__img">' . wp_kses_post( $p->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) );
	$h    .= '<button type="button" class="mpc-card__add" aria-label="Añadir ' . esc_attr( $name ) . '">+</button>';
	$h    .= '<span class="mpc-card__qty" hidden><button type="button" data-d="-1" aria-label="Quitar uno">−</button><output>0</output><button type="button" data-d="1" aria-label="Añadir uno">+</button></span></span>';
	$h    .= '<span class="mpc-card__name">' . esc_html( $name ) . '</span>';
	$h    .= '<span class="mpc-card__price">+ ' . wp_kses_post( wc_price( $price ) ) . '</span>';
	if ( mpc_addon_is_top( $p ) ) {
		$h .= '<span class="mpc-addon__tag">Más pedido</span>';
	}
	return $h . '<input type="hidden" name="mpc_extra[' . $id . ']" value="0"></div>';
}

/** Raíz del flujo: datos para el JS (precio base, pack al que «subir») y las preguntas. */
add_action( 'woocommerce_before_add_to_cart_button', function () {
	global $product;
	$is_pack   = mpc_is_pack( $product );
	$is_single = mpc_is_single_burger( $product );
	if ( ! $is_pack && ! $is_single ) {
		return;
	}
	$base = (float) wc_get_price_to_display( $product );
	if ( $is_pack ) {
		$vars = $product->get_available_variations();
		$base = $vars ? (float) $vars[0]['display_price'] : $base;
	}
	$attrs = ' data-kind="' . ( $is_pack ? 'pack' : 'single' ) . '" data-base="' . esc_attr( $base ) . '" data-product="' . (int) $product->get_id() . '"';
	if ( $is_single ) {
		$packs = mpc_get_packs();
		if ( $packs ) {
			$pv    = $packs[0]->get_available_variations();
			$attrs .= ' data-pack-url="' . esc_url( $packs[0]->get_permalink() ) . '" data-pack-price="' . esc_attr( $pv ? $pv[0]['display_price'] : (float) $packs[0]->get_price() ) . '" data-pack-name="' . esc_attr( mpc_clean_title( $packs[0]->get_name() ) ) . '"';
		}
		$key = array_search( (int) $product->get_id(), mpc_flavour_products(), true );
		if ( false !== $key ) {
			$attrs .= ' data-flavour-key="' . esc_attr( $key ) . '"';
		}
	}
	$out = '<div class="mpc-bundle"' . $attrs . '>';

	/* Regalo del pack: tarjeta incluida. */
	if ( $is_pack ) {
		$title  = mpc_clean_title( $product->get_name() );
		$extras = array_slice( array_map( 'trim', explode( '+', $title ) ), 1 );
		if ( $extras ) {
			$gp    = (int) mpc_opt( 'gift_product' ) ? wc_get_product( (int) mpc_opt( 'gift_product' ) ) : null;
			$out  .= '<section class="mpc-q mpc-q--gift"><h3 class="mpc-q__t">Tu regalo <small>Incluido en el pack</small></h3><div class="mpc-cards"><div class="mpc-card mpc-card--gift"><span class="mpc-card__img">' . ( $gp ? wp_kses_post( $gp->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ) : '<span class="mpc-gift__ico">' . mpc_icon( 'ship' ) . '</span>' ) . '<span class="mpc-card__ribbon">Gratis</span></span><span class="mpc-card__name">' . esc_html( mb_strtoupper( mb_substr( $extras[0], 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $extras[0], 1, null, 'UTF-8' ) ) . '</span><span class="mpc-card__price is-free">Incluido</span></div></div></section>';
		}
	}
	foreach ( mpc_bundle_steps( $product ) as $key => $st ) {
		$out .= '<section class="mpc-q mpc-q--' . esc_attr( $key ) . '"><h3 class="mpc-q__t">' . esc_html( $st['title'] ) . ' <small>' . esc_html( $st['hint'] ) . '</small></h3><div class="mpc-cards">';
		foreach ( $st['items'] as $p ) {
			$out .= mpc_card_html( $p );
		}
		$out .= '</div></section>';
	}
	$out .= '<div class="mpc-bundle__sum" hidden><span class="mpc-bundle__sumt"><small>Tu pedido</small><span class="mpc-chips" data-mpc-sumtxt></span></span><strong>+ <span data-mpc-extras>0,00 €</span></strong></div></div>';
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput
}, 5 );

/** Productos que se pueden pedir desde el bundle (los pasos + los extras generales). */
function mpc_bundle_allowed_ids( $product = null ) {
	$ids = mpc_addon_ids();
	foreach ( mpc_bundle_steps( $product ) as $st ) {
		foreach ( $st['items'] as $p ) {
			$ids[] = $p->get_id();
		}
	}
	return array_map( 'intval', array_unique( $ids ) );
}

/** Al añadir el pack, se añaden también salsa, pan, queso y demás extras elegidos. */
add_action( 'woocommerce_add_to_cart', function ( $cart_key, $product_id ) {
	static $busy = false;
	if ( $busy ) {
		return;
	}
	$p = wc_get_product( $product_id );
	if ( ! $p || ( ! mpc_is_pack( $p ) && ! mpc_is_single_burger( $p ) ) ) {
		return;
	}
	$want = array(); // id => cantidad
	if ( ! empty( $_REQUEST['mpc_extra'] ) && is_array( $_REQUEST['mpc_extra'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( wp_unslash( $_REQUEST['mpc_extra'] ) as $id => $qty ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( absint( $qty ) > 0 ) {
				$want[ absint( $id ) ] = min( 10, absint( $qty ) );
			}
		}
	}
	if ( ! empty( $_REQUEST['mpc_sauce'] ) && absint( $_REQUEST['mpc_sauce'] ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification
		$want[ absint( $_REQUEST['mpc_sauce'] ) ] = 1; // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( ! $want ) {
		return;
	}
	$allowed = mpc_bundle_allowed_ids( $p );
	$busy    = true;
	$added   = 0;
	foreach ( $want as $id => $qty ) {
		if ( in_array( $id, $allowed, true ) && WC()->cart->add_to_cart( $id, $qty ) ) {
			$added++;
		}
	}
	$busy = false;
	if ( $added ) {
		wc_add_notice( sprintf( _n( 'También hemos añadido %d extra a tu pedido.', 'También hemos añadido %d extras a tu pedido.', $added, 'meatprotein-conversion' ), $added ) );
	}
}, 20, 2 );

/* ---------------------------------------------------------------------------------------------
   2) Bloque «Completa tu pedido» reutilizable: carrito lateral, carrito y checkout.
   --------------------------------------------------------------------------------------------- */
function mpc_upsell_html( $context = 'cart', $limit = 3 ) {
	$list = mpc_addons_available( $limit );
	if ( ! $list || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return '';
	}
	$h = '<div class="mpc-upsell mpc-upsell--' . esc_attr( $context ) . '"><h4>Completa tu pedido</h4><ul>';
	foreach ( $list as $p ) {
		$price = (float) wc_get_price_to_display( $p );
		$h    .= '<li><span class="mpc-upsell__img">' . wp_kses_post( $p->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '' ) ) ) . '</span>';
		$h    .= '<span class="mpc-upsell__txt"><a href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( preg_replace( '/\s+(NEW!?|[0-9]+\s?g)$/iu', '', wp_strip_all_tags( $p->get_name() ) ) ) . '</a><strong>' . wp_kses_post( wc_price( $price ) ) . '</strong></span>';
		if ( 'side' === $context ) {
			$h .= '<a href="' . esc_url( '?add-to-cart=' . $p->get_id() ) . '" data-quantity="1" data-product_id="' . (int) $p->get_id() . '" data-product_sku="' . esc_attr( $p->get_sku() ) . '" class="mpc-upsell__add button add_to_cart_button ajax_add_to_cart" rel="nofollow" aria-label="Añadir ' . esc_attr( wp_strip_all_tags( $p->get_name() ) ) . ' al carrito">Añadir</a>';
		} else {
			$h .= '<a href="' . esc_url( add_query_arg( 'add-to-cart', $p->get_id(), 'checkout' === $context ? wc_get_checkout_url() : wc_get_cart_url() ) ) . '" class="mpc-upsell__add button" rel="nofollow" aria-label="Añadir ' . esc_attr( wp_strip_all_tags( $p->get_name() ) ) . ' al pedido">Añadir</a>';
		}
		$h .= '</li>';
	}
	return $h . '</ul></div>';
}

/* Carrito lateral: el bloque vive en el pie y el JS lo coloca bajo los productos; se refresca con los fragmentos. */
add_action( 'wp_footer', function () {
	if ( ! is_admin() && ! is_checkout() && ! is_cart() ) {
		echo mpc_upsell_html( 'side', 3 ) ?: '<div class="mpc-upsell mpc-upsell--side" hidden></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 4 );
add_filter( 'woocommerce_add_to_cart_fragments', function ( $f ) {
	$f['.mpc-upsell--side'] = mpc_upsell_html( 'side', 3 ) ?: '<div class="mpc-upsell mpc-upsell--side" hidden></div>';
	return $f;
} );

/* Carrito: bajo la tabla. */
add_action( 'woocommerce_after_cart_table', function () {
	echo mpc_upsell_html( 'cart', 4 ); // phpcs:ignore WordPress.Security.EscapeOutput
} );

/* Checkout: una sola sugerencia, justo antes del pago, la de menor precio de las más vendidas. */
add_action( 'woocommerce_review_order_before_payment', function () {
	echo mpc_upsell_html( 'checkout', 1 ); // phpcs:ignore WordPress.Security.EscapeOutput
}, 5 );
