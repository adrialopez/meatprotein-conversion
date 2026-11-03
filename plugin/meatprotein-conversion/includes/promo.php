<?php
/**
 * Barra superior con promo puntual; con fecha de fin muestra cuenta atrás y se retira sola.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML de la barra superior de promoción.
 *
 * @return string
 */
function mpc_promo_html() {
	$txt = trim( (string) mpc_opt( 'promo_text' ) );
	if ( '' === $txt ) {
		return '';
	}
	$ends = trim( (string) mpc_opt( 'promo_ends' ) );
	$ts   = 0;
	if ( $ends ) {
		$ts = strtotime( $ends . ' ' . wp_timezone_string() );
		if ( $ts && $ts < time() ) {
			return '';
		}
	}
	$url = trim( (string) mpc_opt( 'promo_url' ) );
	$cta = trim( (string) mpc_opt( 'promo_cta' ) );
	$h   = '<div class="mpc-promo" data-mpc-promo' . ( $ts ? ' data-ends="' . (int) $ts . '"' : '' ) . '><p>' . esc_html( $txt );
	if ( $ts ) {
		$h .= ' <span class="mpc-promo__count" data-mpc-count aria-live="off"></span>';
	}
	if ( $url && $cta ) {
		$h .= ' <a href="' . esc_url( $url ) . '">' . esc_html( $cta ) . '</a>';
	}
	return $h . '</p></div>';
}

/**
 * Atributos permitidos en la barra de promoción.
 *
 * @return array
 */
function mpc_promo_kses() {
	return array(
		'div'  => array(
			'class'          => true,
			'data-mpc-promo' => true,
			'data-ends'      => true,
		),
		'p'    => array(),
		'span' => array(
			'class'          => true,
			'data-mpc-count' => true,
			'aria-live'      => true,
		),
		'a'    => array( 'href' => true ),
	);
}

/**
 * Betheme no llama a wp_body_open: la barra se imprime en una plantilla y ui.js la coloca al principio del body.
 *
 * @return void
 */
function mpc_print_promo() {
	$html = mpc_promo_html();
	if ( '' !== $html ) {
		echo '<template id="mpc-promo-tpl">' . wp_kses( $html, mpc_promo_kses() ) . '</template>';
	}
}
add_action( 'wp_footer', 'mpc_print_promo', 1 );
