<?php
/**
 * Extras para el pack (producto estrella): se ofrecen en la ficha, en el carrito lateral, en el carrito
 * y en el checkout. Por defecto se eligen solos: el producto más vendido de cada categoría que no sea
 * hamburguesas (queso, pan, pizza, salsas, snacks…). Se pueden fijar a mano en WooCommerce > Conversión.
 *
 * @package Meatprotein_Conversion
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
			$ids     = array();
			$seen    = array();
			$ids_all = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 150, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Una sola vez y en caché (transient).
					'fields'         => 'ids',
					'meta_key'       => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'            => 'meta_value_num',
				'order'              => 'DESC',
				'tax_query'          => array(
				array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => 'simple',
				),
				), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			$prods   = array_filter(
				array_map( 'wc_get_product', $ids_all ),
				function ( $p ) {
					return $p && $p->is_in_stock();
				}
			);
			foreach ( $prods as $p ) {
				$cats = $p->get_category_ids();
				if ( ! $cats || has_term( 'hamburguesas', 'product_cat', $p->get_id() ) || (float) $p->get_price() <= 0 ) {
					continue;
				}
				$key = (int) $cats[0];
				if ( isset( $seen[ $key ] ) ) {
					continue; // Uno por categoría, para que la oferta sea variada.
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

/**
 * Extras sugeridos: los más vendidos con stock.
 *
 * @param int $limit Máximo de resultados.
 * @return mixed Resultado.
 */
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

/**
 * Indica si un producto está entre los más vendidos de los extras.
 *
 * @param WC_Product|null $product Producto.
 * @return bool
 */
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

// 1) Ficha del pack: bundle a medida por pasos (sabores → regalo → salsa → pan → queso → otros extras).

/**
 * Nombre más cómodo de leer: sin «NEW», con unidades normalizadas y sin mayúsculas gritadas.
 *
 * @param string $name Nombre.
 * @return string
 */
function mpc_nice_name( $name ) {
	return mpc_display_name( $name );
}

/**
 * Producto básico: simple, y ni el pack ni una bandeja de hamburguesas.
 *
 * @param WC_Product|null $product Producto.
 * Fuera del alcance de la primera iteración (ver mpc_scope_all()): sin él, estos productos conservan la ficha de Betheme.
 *
 * @return bool
 */
function mpc_is_basic( $product ) {
	return mpc_scope_all() && $product instanceof WC_Product && $product->is_type( 'simple' ) && ! mpc_is_pack( $product ) && ! mpc_is_single_burger( $product );
}

/**
 * Nombre corto para tarjetas y resumen: sin gramos ni relleno de marca.
 *
 * @param string $name Nombre.
 * @return string
 */
function mpc_short_name( $name ) {
	$n = mpc_nice_name( $name );
	$n = preg_replace( '/\s*[-–]\s*\d+\s?(g|gr|ml)\b/iu', '', $n );
	$n = preg_replace( '/\s+\d+\s?(g|gr|ml)\b/iu', '', $n );
	$n = preg_replace( '/^Pan de hamburguesas\s+/iu', 'Pan ', $n );
	$n = preg_replace( '/\s+Meatprotein\b/iu', '', $n );
	return trim( $n );
}

/**
 * Bandeja suelta: producto simple de la categoría hamburguesas.
 *
 * @param WC_Product|null $product Producto.
 * @return bool
 */
function mpc_is_single_burger( $product ) {
	return $product instanceof WC_Product && $product->is_type( 'simple' ) && has_term( 'hamburguesas', 'product_cat', $product->get_id() ) && ! mpc_is_pack( $product );
}

/**
 * Productos de un paso del bundle: los fijados en ajustes o los más vendidos de esas categorías.
 *
 * @param string   $setting Ajuste que fija los IDs.
 * @param string[] $cats Slugs de categoría.
 * @param string   $include_pattern Patrón que debe cumplir el nombre.
 * @param string   $exclude_pattern Patrón que no debe cumplir el nombre.
 * @param int      $limit Máximo de resultados.
 * @param int      $skip_id Producto que se omite.
 * @return mixed Resultado.
 */
function mpc_step_products( $setting, $cats, $include_pattern, $exclude_pattern, $limit, $skip_id = 0 ) {
	$cfg = trim( (string) mpc_opt( $setting ) );
	if ( '' !== $cfg ) {
		$ids = array_values( array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', $cfg ) ) ) );
	} else {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 80,
				'fields'         => 'ids',
				'meta_key'       => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'            => 'meta_value_num',
			'order'              => 'DESC',
			'tax_query'          => array(
			array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $cats,
			),
			), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
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
		if ( '' === $cfg && ( ( $include_pattern && ! preg_match( $include_pattern, $name ) ) || ( $exclude_pattern && preg_match( $exclude_pattern, $name ) ) ) ) {
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
 *
 * @param WC_Product|null $product Producto de la ficha.
 * @return array Pasos con título, ayuda y productos.
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
	} elseif ( mpc_is_basic( $product ) ) {
		$cats  = $product->get_category_ids();
		$items = array();
		foreach ( mpc_addon_ids() as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p || (int) $id === (int) $skip || ! $p->is_in_stock() || array_intersect( $cats, $p->get_category_ids() ) ) {
				continue;
			}
			$items[] = $p;
			if ( count( $items ) >= 6 ) {
				break;
			}
		}
		$steps = array(
			'combo' => array(
				'title' => 'Combínalo con',
				'hint'  => 'Lo que más se pide',
				'items' => $items,
			),
		);
	} else {
		$steps = array(
			'salsa' => $salsa,
			'pan'   => $pan,
			'queso' => $queso,
		);
	}
	return apply_filters(
		'mpc_bundle_steps',
		array_filter(
			$steps,
			function ( $st ) {
				return ! empty( $st['items'] );
			}
		),
		$product
	);
}

/**
 * HTML de la tarjeta de un extra.
 *
 * @param WC_Product $p Producto.
 * @return string
 */
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

/**
 * Paso «Elige tu regalo»: una tarjeta por regalo, con el primero con stock marcado.
 *
 * @param WC_Product[] $choices Regalos publicados.
 * @return string
 */
function mpc_gift_pick_html( $choices ) {
	$default = mpc_pick_gift( 0 );
	$h       = '<section class="mpc-q mpc-q--gift mpc-q--giftpick"><h3 class="mpc-q__t">Elige tu regalo <small>1 bandeja incluida en el pack</small></h3><div class="mpc-giftpick" role="radiogroup">';
	foreach ( $choices as $p ) {
		$id    = $p->get_id();
		$avail = mpc_available_trays( $id );
		$gone  = null !== $avail && $avail <= 0;
		$name  = mpc_display_name( wp_strip_all_tags( $p->get_name() ) );
		$h    .= '<label class="mpc-giftopt' . ( $gone ? ' is-out' : '' ) . '"><input type="radio" name="mpc_gift" value="' . (int) $id . '"' . checked( $id === $default, true, false ) . disabled( $gone, true, false ) . '>';
		$h    .= '<span class="mpc-giftopt__img">' . wp_kses_post( $p->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ) . '</span>';
		$h    .= '<span class="mpc-giftopt__name">' . esc_html( $name ) . '</span><span class="mpc-giftopt__tag">' . ( $gone ? 'Agotado' : 'Regalo' ) . '</span></label>';
	}
	return $h . '</div></section>';
}

/** Raíz del flujo: datos para el JS (precio base, pack al que «subir») y las preguntas. */
add_action(
	'woocommerce_before_add_to_cart_button',
	function () {
		global $product;
		$is_pack   = mpc_is_pack( $product );
		$is_single = mpc_is_single_burger( $product );
		$is_basic  = mpc_is_basic( $product );
		if ( ! $is_pack && ! $is_single && ! $is_basic ) {
			return;
		}
		$base = (float) wc_get_price_to_display( $product );
		if ( $is_pack ) {
			$vars = $product->get_available_variations();
			$base = $vars ? (float) $vars[0]['display_price'] : $base;
		}
		$attrs = ' data-kind="' . ( $is_pack ? 'pack' : ( $is_basic ? 'basic' : 'single' ) ) . '" data-base="' . esc_attr( $base ) . '" data-product="' . (int) $product->get_id() . '"';
		if ( $is_pack ) {
			$gift_parts = array_slice( array_map( 'trim', explode( '+', mpc_clean_title( $product->get_name() ) ) ), 1 );
			if ( $gift_parts || mpc_gift_multi() ) {
				$attrs .= ' data-gift="' . esc_attr( mpc_gift_multi() ? 'una bandeja de regalo a tu elección' : ( $gift_parts[0] ?? '' ) ) . '"';
			}
			$all_packs = mpc_get_packs();
			if ( $all_packs && (int) $all_packs[0]->get_id() === (int) $product->get_id() && $product->get_total_sales() > 0 ) {
				$attrs .= ' data-top="1"';
			}
		}
		if ( $is_single ) {
			$packs = mpc_get_packs();
			if ( $packs ) {
				$pv     = $packs[0]->get_available_variations();
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
			if ( mpc_gift_multi() ) {
				$out .= mpc_gift_pick_html( mpc_gift_choices() );
			} elseif ( $extras ) {
				$gp   = (int) mpc_opt( 'gift_product' ) ? wc_get_product( (int) mpc_opt( 'gift_product' ) ) : null;
				$out .= '<section class="mpc-q mpc-q--gift"><h3 class="mpc-q__t">Tu regalo <small>Incluido en el pack</small></h3><div class="mpc-cards"><div class="mpc-card mpc-card--gift"><span class="mpc-card__img">' . ( $gp ? wp_kses_post( $gp->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ) : '<span class="mpc-gift__ico">' . mpc_icon( 'ship' ) . '</span>' ) . '<span class="mpc-card__ribbon">Gratis</span></span><span class="mpc-card__name">' . esc_html( mb_strtoupper( mb_substr( $extras[0], 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $extras[0], 1, null, 'UTF-8' ) ) . '</span><span class="mpc-card__price is-free">Incluido</span></div></div></section>';
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
	},
	5
);

/**
 * IDs de extras que se pueden añadir junto a un producto (lista blanca).
 *
 * @param WC_Product|null $product Producto.
 * @return int[]
 */
function mpc_bundle_allowed_ids( $product = null ) {
	$ids = mpc_addon_ids();
	foreach ( mpc_bundle_steps( $product ) as $st ) {
		foreach ( $st['items'] as $p ) {
			$ids[] = $p->get_id();
		}
	}
	return array_map( 'intval', array_unique( $ids ) );
}

/**
 * Extras que el cliente ha marcado en la ficha (ID => cantidad).
 *
 * El formulario de añadir al carrito de WooCommerce no lleva nonce (así funciona su propio flujo), por eso aquí
 * todo se sanea con absint, se limita la cantidad y, al añadir, solo se aceptan los IDs de la lista blanca del producto.
 *
 * @return int[] Cantidades por ID de producto.
 */
function mpc_requested_extras() {
	$want = array();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Ver el comentario de la función.
	if ( ! empty( $_REQUEST['mpc_extra'] ) && is_array( $_REQUEST['mpc_extra'] ) ) {
		foreach ( wp_unslash( $_REQUEST['mpc_extra'] ) as $id => $qty ) {
			if ( absint( $qty ) > 0 ) {
				$want[ absint( $id ) ] = min( 10, absint( $qty ) );
			}
		}
	}
	if ( ! empty( $_REQUEST['mpc_sauce'] ) && absint( $_REQUEST['mpc_sauce'] ) > 0 ) {
		$want[ absint( $_REQUEST['mpc_sauce'] ) ] = 1;
	}
	// phpcs:enable
	return $want;
}

/**
 * Al añadir el pack, una bandeja o un producto básico, se añaden también los extras elegidos.
 *
 * @param string $cart_key   Clave de la línea del carrito (no se usa).
 * @param int    $product_id ID del producto añadido.
 * @return void
 */
function mpc_add_requested_extras( $cart_key, $product_id ) {
	static $busy = false;
	if ( $busy ) {
		return;
	}
	$p = wc_get_product( $product_id );
	if ( ! $p || ! ( mpc_is_pack( $p ) || mpc_is_single_burger( $p ) || mpc_is_basic( $p ) ) ) {
		return;
	}
	$want = mpc_requested_extras();
	if ( ! $want ) {
		return;
	}
	$allowed = mpc_bundle_allowed_ids( $p );
	$busy    = true;
	$added   = 0;
	foreach ( $want as $id => $qty ) {
		if ( in_array( $id, $allowed, true ) && WC()->cart->add_to_cart( $id, $qty ) ) {
			++$added;
		}
	}
	$busy = false;
	if ( $added ) {
		/* translators: %d: número de extras añadidos al pedido. */
		wc_add_notice( sprintf( _n( 'También hemos añadido %d extra a tu pedido.', 'También hemos añadido %d extras a tu pedido.', $added, 'meatprotein-conversion' ), $added ) );
	}
}
add_action( 'woocommerce_add_to_cart', 'mpc_add_requested_extras', 20, 2 );

/**
 * Pinta una lista de productos como bloque de venta cruzada (tarjetas con foto, precio y botón «Añadir»).
 *
 * @param WC_Product[] $products Productos a ofrecer.
 * @param string       $context Dónde se pinta: cart, checkout, side o snacks.
 * @param string       $title   Título del bloque.
 * @param string       $sub     Subtítulo opcional (HTML ya escapado).
 * @return string HTML del bloque, o cadena vacía si no hay nada que ofrecer.
 */
function mpc_upsell_render( $products, $context, $title, $sub = '' ) {
	if ( ! $products || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return '';
	}
	$h = '<div class="mpc-upsell mpc-upsell--' . esc_attr( $context ) . '"><h4>' . esc_html( $title ) . '</h4>' . ( $sub ? '<p class="mpc-upsell__sub">' . $sub . '</p>' : '' ) . '<ul>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $sub llega escapado.
	foreach ( $products as $p ) {
		$price = (float) wc_get_price_to_display( $p );
		$name  = mpc_display_name( preg_replace( '/\s+(NEW!?|[0-9]+\s?g)$/iu', '', wp_strip_all_tags( $p->get_name() ) ) );
		$h    .= '<li><span class="mpc-upsell__img">' . wp_kses_post( $p->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '' ) ) ) . '</span>';
		$h    .= '<span class="mpc-upsell__txt"><a href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( $name ) . '</a><strong>' . wp_kses_post( wc_price( $price ) ) . '</strong></span>';
		if ( 'side' === $context ) {
			$h .= '<a href="' . esc_url( '?add-to-cart=' . $p->get_id() ) . '" data-quantity="1" data-product_id="' . (int) $p->get_id() . '" data-product_sku="' . esc_attr( $p->get_sku() ) . '" class="mpc-upsell__add button add_to_cart_button ajax_add_to_cart" rel="nofollow" aria-label="Añadir ' . esc_attr( $name ) . ' al carrito">Añadir</a>';
		} else {
			$h .= '<a href="' . esc_url(
				add_query_arg(
					array(
						'add-to-cart' => $p->get_id(),
						'mpc_up'      => 1,
					),
					'checkout' === $context ? wc_get_checkout_url() : wc_get_cart_url()
				)
			) . '" class="mpc-upsell__add button" data-product_id="' . (int) $p->get_id() . '" rel="nofollow" aria-label="Añadir ' . esc_attr( $name ) . ' al pedido">Añadir</a>';
		}
		$h .= '</li>';
	}
	return $h . '</ul></div>';
}

/**
 * Bloque «Completa tu pedido» reutilizable: carrito lateral, carrito y checkout.
 *
 * @param string $context Dónde se pinta: cart, checkout o side.
 * @param int    $limit   Máximo de extras.
 * @return string HTML del bloque, o cadena vacía si no hay nada que ofrecer.
 */
function mpc_upsell_html( $context = 'cart', $limit = 3 ) {
	$list = mpc_addons_available( $limit + count( mpc_snack_ids() ) );
	if ( 'cart' === $context ) {
		// Los snacks tienen su propio bloque en el carrito: no se repiten aquí.
		$snacks = mpc_snack_ids();
		$list   = array_filter(
			$list,
			function ( $p ) use ( $snacks ) {
				return ! in_array( $p->get_id(), $snacks, true );
			}
		);
	}
	return mpc_upsell_render( array_slice( array_values( $list ), 0, $limit ), $context, 'Completa tu pedido' );
}

/**
 * IDs de los snacks que se ofrecen en el carrito: los de los ajustes o, si están vacíos, los más vendidos de «Snacks dulces».
 *
 * @return int[]
 */
function mpc_snack_ids() {
	$ids = get_transient( 'mpc_snack_ids' );
	if ( ! is_array( $ids ) ) {
		$ids = array_map(
			function ( $p ) {
				return $p->get_id();
			},
			mpc_step_products( 'snacks', array( 'snacks-dulces' ), '', '', 8 )
		);
		set_transient( 'mpc_snack_ids', $ids, 6 * HOUR_IN_SECONDS );
	}
	return apply_filters( 'mpc_snack_ids', $ids );
}

/**
 * El snack que se sugiere en el checkout: ninguno si ya tienen el envío gratis; si no, el primero de la lista de snacks
 * que lo consigue o, si ninguno llega, el primero disponible. Así manda el orden de los ajustes.
 *
 * @return WC_Product|null
 */
function mpc_checkout_snack() {
	$st = mpc_free_ship_state();
	if ( ! $st || $st['min'] <= 0 || $st['left'] <= 0 || 0 === $st['count'] ) {
		return null;
	}
	$in_cart = array();
	foreach ( WC()->cart->get_cart() as $item ) {
		$in_cart[ (int) $item['product_id'] ] = true;
	}
	$first = null;
	foreach ( mpc_snack_ids() as $id ) {
		$p = isset( $in_cart[ $id ] ) ? null : wc_get_product( $id );
		if ( ! $p || ! $p->is_purchasable() || ! $p->is_in_stock() || 'publish' !== get_post_status( $id ) ) {
			continue;
		}
		if ( (float) wc_get_price_to_display( $p ) >= $st['left'] ) {
			return $p;
		}
		if ( ! $first ) {
			$first = $p;
		}
	}
	return $first;
}

/**
 * Tarjeta de snack del checkout, con el envío gratis como motivo.
 *
 * @return string
 */
function mpc_checkout_snack_html() {
	$p  = mpc_checkout_snack();
	$st = mpc_free_ship_state();
	if ( ! $p || ! $st ) {
		return '';
	}
	$reaches = (float) wc_get_price_to_display( $p ) >= $st['left'];
	$sub     = $reaches ? 'Con este snack tu envío sale gratis.' : 'Te faltan ' . wp_kses_post( wc_price( $st['left'] ) ) . ' para el envío gratis.';
	return mpc_upsell_render( array( $p ), 'checkout', '¿Un snack para el camino?', $sub );
}

/**
 * Bloque de snacks del carrito: los que aún no están en el pedido, con el envío gratis como empujón.
 *
 * @param int $limit Máximo de snacks.
 * @return string
 */
function mpc_snacks_html( $limit = 4 ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return '';
	}
	$in_cart = array();
	foreach ( WC()->cart->get_cart() as $item ) {
		$in_cart[ (int) $item['product_id'] ] = true;
	}
	$list = array();
	foreach ( mpc_snack_ids() as $id ) {
		$p = isset( $in_cart[ $id ] ) ? null : wc_get_product( $id );
		if ( $p && $p->is_purchasable() && $p->is_in_stock() && 'publish' === get_post_status( $id ) ) {
			$list[] = $p;
			if ( count( $list ) >= $limit ) {
				break;
			}
		}
	}
	$st  = mpc_free_ship_state();
	$sub = $st && $st['min'] > 0 && $st['left'] > 0 ? 'Te faltan ' . wp_kses_post( wc_price( $st['left'] ) ) . ' para el envío gratis.' : '';
	return mpc_upsell_render( $list, 'snacks', '¿Un snack para el camino?', $sub );
}

/**
 * Tras añadir desde «Completa tu pedido» o los snacks, vuelve a la misma página sin el parámetro, para que recargar no lo añada otra vez.
 * WooCommerce ya ha añadido el producto y guardado el aviso en la sesión (wp_loaded, prioridad 20).
 *
 * @return void
 */
function mpc_clean_upsell_redirect() {
	if ( isset( $_GET['mpc_up'], $_GET['add-to-cart'] ) && ! wp_doing_ajax() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo decide a dónde volver; el alta la hace WooCommerce.
		wp_safe_redirect( remove_query_arg( array( 'mpc_up', 'add-to-cart', 'quantity' ) ) );
		exit;
	}
}
add_action( 'wp_loaded', 'mpc_clean_upsell_redirect', 30 );

/* Carrito lateral: el bloque vive en el pie y el JS lo coloca bajo los productos; se refresca con los fragmentos. */
add_action(
	'wp_footer',
	function () {
		if ( ! is_admin() && ! is_checkout() && ! is_cart() ) {
			$side = mpc_upsell_html( 'side', 3 );
			echo '' !== $side ? $side : '<div class="mpc-upsell mpc-upsell--side" hidden></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	4
);
add_filter(
	'woocommerce_add_to_cart_fragments',
	function ( $f ) {
		$side                   = mpc_upsell_html( 'side', 3 );
		$f['.mpc-upsell--side'] = '' !== $side ? $side : '<div class="mpc-upsell mpc-upsell--side" hidden></div>';
		return $f;
	}
);

/* Carrito: bajo la tabla. */
add_action(
	'woocommerce_after_cart_table',
	function () {
		echo mpc_snacks_html( 4 ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo mpc_upsell_html( 'cart', 4 ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

/* Checkout: una sola sugerencia (un snack) justo antes del pago, solo si aún no tienen el envío gratis. */
add_action(
	'woocommerce_review_order_before_payment',
	function () {
		echo mpc_checkout_snack_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	},
	5
);
