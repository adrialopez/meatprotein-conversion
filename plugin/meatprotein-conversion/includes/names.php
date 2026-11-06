<?php
/**
 * Nombres de producto legibles en toda la web.
 *
 * El catálogo trae títulos con mayúsculas, «NEW», entidades HTML y erratas. En lugar de reescribir los datos
 * (que son del equipo y de los proveedores), se normaliza al mostrarlos: listas, ficha, carrito, pedido y búsqueda.
 * Solo en la parte pública: ni el administrador, ni la API REST, ni WP-CLI ven el nombre modificado.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Erratas conocidas del catálogo (en minúsculas) y su corrección.
 *
 * @return array<string,string>
 */
function mpc_name_typos() {
	return apply_filters(
		'mpc_name_typos',
		array(
			'mamgo'     => 'mango',
			'badeja'    => 'bandeja',
			'espicanas' => 'espinacas',
			'barcbacoa' => 'barbacoa',
		)
	);
}

/**
 * Siglas y marcas que se mantienen en mayúsculas.
 *
 * @return string[]
 */
function mpc_name_acronyms() {
	return apply_filters( 'mpc_name_acronyms', array( 'CFM', 'ISO', 'BCAA', 'EAA', 'MCT', 'ZMA', 'EPA', 'DHA', 'UHT', 'XL', 'BBQ', 'IFS', 'BRC' ) );
}

/**
 * Palabras de enlace que van en minúscula en un título.
 *
 * @return string[]
 */
function mpc_name_connectors() {
	return array( 'de', 'del', 'la', 'el', 'los', 'las', 'y', 'e', 'o', 'con', 'sin', 'en', 'al', 'a', 'por', 'para', 'un', 'una' );
}

/**
 * Indica si un texto está casi todo en mayúsculas.
 *
 * @param string $text Texto.
 * @return bool
 */
function mpc_is_shouting( $text ) {
	$letters = preg_replace( '/[^\p{L}]+/u', '', $text );
	$upper   = preg_replace( '/[^\p{Lu}]+/u', '', $text );
	$total   = mb_strlen( $letters, 'UTF-8' );
	return $total > 4 && ( mb_strlen( $upper, 'UTF-8' ) / $total ) > 0.7;
}

/**
 * Pasa a «Tipo Título» respetando siglas y palabras de enlace.
 *
 * @param string $text Texto en mayúsculas.
 * @return string
 */
function mpc_title_case( $text ) {
	$acronyms   = array_map( 'mb_strtolower', mpc_name_acronyms() );
	$connectors = mpc_name_connectors();
	$words      = preg_split( '/(\s+)/u', mb_strtolower( $text, 'UTF-8' ), -1, PREG_SPLIT_DELIM_CAPTURE );
	$first      = true;
	foreach ( $words as $i => $word ) {
		if ( '' === trim( $word ) ) {
			continue;
		}
		$core = trim( $word, '-()®™' );
		if ( in_array( $core, $acronyms, true ) ) {
			$words[ $i ] = str_replace( $core, mb_strtoupper( $core, 'UTF-8' ), $word );
		} elseif ( ! $first && in_array( $core, $connectors, true ) ) {
			$words[ $i ] = $word;
		} else {
			$words[ $i ] = preg_replace_callback(
				'/\p{L}[\p{L}\p{N}]*/u',
				static function ( $m ) use ( $acronyms ) {
					$piece = $m[0];
					if ( in_array( $piece, $acronyms, true ) ) {
						return mb_strtoupper( $piece, 'UTF-8' );
					}
					return mb_strtoupper( mb_substr( $piece, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $piece, 1, null, 'UTF-8' );
				},
				$word,
				1
			);
		}
		$first = false;
	}
	return implode( '', $words );
}

/**
 * En un título mezclado, baja a «Tipo Título» las palabras sueltas que están gritando («ROQUEFORT») y las de enlace («Y»).
 *
 * @param string $text Texto con mayúsculas y minúsculas.
 * @return string
 */
function mpc_soften_caps( $text ) {
	$acronyms   = mpc_name_acronyms();
	$connectors = mpc_name_connectors();
	return preg_replace_callback(
		'/(?<![\p{L}\p{N}])\p{Lu}[\p{Lu}]*(?![\p{L}\p{N}])/u',
		static function ( $m ) use ( $acronyms, $connectors ) {
			$word = $m[0];
			if ( in_array( $word, $acronyms, true ) ) {
				return $word;
			}
			if ( in_array( mb_strtolower( $word, 'UTF-8' ), $connectors, true ) ) {
				return mb_strtolower( $word, 'UTF-8' );
			}
			if ( mb_strlen( $word, 'UTF-8' ) < 4 ) {
				return $word;
			}
			return mb_strtoupper( mb_substr( $word, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_strtolower( mb_substr( $word, 1, null, 'UTF-8' ), 'UTF-8' );
		},
		$text
	);
}

/**
 * Nombre de producto listo para mostrar.
 *
 * @param string $name Nombre tal y como está guardado.
 * @return string
 */
function mpc_display_name( $name ) {
	$n = html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' );
	$n = preg_replace( '/(?<=\s|^)NEW!?(?=\s|$)/iu', '', $n );
	$n = preg_replace( '/\s+/u', ' ', trim( $n ) );
	foreach ( mpc_name_typos() as $wrong => $right ) {
		$n = preg_replace( '/\b' . preg_quote( $wrong, '/' ) . '\b/iu', $right, $n );
	}
	if ( mpc_is_shouting( $n ) ) {
		$n = mpc_title_case( $n );
	} else {
		$n = mpc_soften_caps( $n );
	}
	// Unidades siempre en minúscula y separadas: «440G» pasa a «440 g».
	$n = preg_replace_callback(
		'/\b(\d+)\s?(kg|gr|g|ml|cl|l)\b/iu',
		static function ( $m ) {
			return $m[1] . ' ' . mb_strtolower( $m[2], 'UTF-8' );
		},
		$n
	);
	return trim( $n );
}

/**
 * Primera iteración: los nombres se normalizan en hamburguesas y pack siempre, y en el resto solo dentro del carrito, el pago y el mini carrito.
 *
 * @param int $product_id ID de producto.
 * @return bool
 */
function mpc_name_in_scope( $product_id ) {
	if ( mpc_scope_all() || is_cart() || is_checkout() || wp_doing_ajax() || doing_action( 'woocommerce_mini_cart_contents' ) || did_action( 'woocommerce_before_mini_cart' ) > did_action( 'woocommerce_after_mini_cart' ) ) {
		return true;
	}
	$p = wc_get_product( $product_id );
	return $p && ( mpc_is_pack( $p ) || mpc_is_single_burger( $p ) );
}

/**
 * Indica si hay que normalizar nombres en esta petición (solo parte pública).
 *
 * @return bool
 */
function mpc_should_normalize_names() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) || is_feed() ) {
		return false;
	}
	return true;
}

/**
 * Normaliza el título de un producto en listas, ficha y búsqueda.
 *
 * @param string $title   Título.
 * @param int    $post_id ID de la entrada.
 * @return string
 */
function mpc_filter_product_title( $title, $post_id = 0 ) {
	if ( ! $post_id || ! mpc_should_normalize_names() || 'product' !== get_post_type( $post_id ) || ! mpc_name_in_scope( $post_id ) ) {
		return $title;
	}
	return mpc_display_name( $title );
}
add_filter( 'the_title', 'mpc_filter_product_title', 5, 2 );

/**
 * Normaliza el nombre de un producto (carrito, pedido, lista).
 *
 * @param string     $name    Nombre.
 * @param WC_Product $product Producto.
 * @return string
 */
function mpc_filter_product_name( $name, $product ) {
	if ( ! $product instanceof WC_Product || ! mpc_should_normalize_names() || ! mpc_name_in_scope( $product->get_id() ) ) {
		return $name;
	}
	$name = mpc_display_name( $name );
	// Con varios regalos, el título del pack no nombra ninguno: el elegido va en «Regalo».
	if ( mpc_gift_multi() && mpc_is_pack( $product ) ) {
		$name = trim( explode( '+', $name, 2 )[0] );
	}
	return $name;
}
add_filter( 'woocommerce_product_get_name', 'mpc_filter_product_name', 20, 2 );
