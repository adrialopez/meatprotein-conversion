<?php
/**
 * Funciones de apoyo.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indica si un producto es el pack de 10 bandejas.
 *
 * @param WC_Product|null $product Producto.
 * @return bool
 */
function mpc_is_pack( $product ) {
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return false;
	}
	$n = 0;
	foreach ( $product->get_attributes() as $attr ) {
		if ( $attr->get_variation() && preg_match( '/^bandeja\s*\d+$/i', trim( $attr->get_name() ) ) ) {
			++$n;
		}
	}
	return $n >= 4;
}

/**
 * Título limpio: sin emojis, sin «PROMOCIÓN» y con erratas corregidas.
 *
 * @param string $title Título.
 * @return string
 */
function mpc_clean_title( $title ) {
	$t = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' );
	$t = preg_replace( '/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $t );
	$t = preg_replace( '/^\s*promoci[oó]n\s*/iu', '', $t );
	$t = preg_replace( '/\s+/', ' ', trim( $t ) );
	$t = preg_replace( '/\bbadeja\b/iu', 'bandeja', $t );
	$t = preg_replace( '/\bmamgo\b|\bmango\b/iu', 'mango', $t );
	$t = mb_strtolower( $t, 'UTF-8' );
	$t = mb_strtoupper( mb_substr( $t, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $t, 1, null, 'UTF-8' );
	return str_ireplace( 'meatprotein', 'Meatprotein', $t );
}

/**
 * Icono SVG en línea.
 *
 * @param string $name Nombre.
 * @return string
 */
function mpc_icon( $name ) {
	$p = array(
		'truck'  => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7.5" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
		'ship'   => '<path d="M20 12v8H4v-8M2 7h20v5H2zM12 7v13M12 7c-2-4-6-4-6-1.5S10 7 12 7zM12 7c2-4 6-4 6-1.5S14 7 12 7z"/>',
		'shield' => '<path d="M12 3l8 3v6c0 4.5-3.2 7.8-8 9-4.8-1.2-8-4.5-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
		'snow'   => '<path d="M12 2v20M4.2 7l15.6 10M19.8 7L4.2 17M9 3.5l3 2.5 3-2.5M9 20.5l3-2.5 3 2.5"/>',
		'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
		'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		'lab'    => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 002 3h10a2 2 0 002-3l-5-9V3"/><path d="M7.5 15h9"/>',
	);
	return '<svg class="mpc-i" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
}

/**
 * URL de una página por slug.
 *
 * @param string $slug Slug de la página.
 * @return string
 */
function mpc_page_url( $slug ) {
	$p = get_page_by_path( $slug );
	return $p ? get_permalink( $p ) : '';
}

/**
 * Preguntas frecuentes de compra.
 *
 * @param string $context Contexto: burger o basic.
 * @return string
 */
function mpc_faq_html( $context = 'burger' ) {
	$items = array(
		'¿Puedo repetir sabores?'      => 'Sí. Eliges 10 bandejas entre todos los sabores disponibles y puedes repetir los que más te gusten.',
		'¿Cómo llega el pedido?'       => 'Refrigerado con SEUR, en 24-48 h. Al recibirlo, congela lo que no vayas a comer esos días.',
		'¿Cuánto duran congeladas?'    => 'Hasta 12 meses en el congelador y unas 72 horas en la nevera.',
		'¿Qué pasa si algo llega mal?' => 'Lo reponemos o te devolvemos el dinero. Cuéntanoslo desde la página de incidencias.',
		'¿Y si se agota un sabor?'     => 'Solo aparecen los sabores con stock en ese momento, así que no pedirás nada que no podamos enviarte.',
		'¿Cuándo sale mi pedido?'      => 'Enviamos de lunes a jueves. Lo que pidas el jueves por la tarde o el viernes sale el lunes, para que llegue en perfecto estado.',
	);
	if ( 'basic' === $context ) {
		$items = array(
			'¿Cuándo sale mi pedido?'           => 'Enviamos de lunes a jueves. Lo que pidas el jueves por la tarde o el viernes sale el lunes, para que llegue en perfecto estado.',
			'¿Cuándo es gratis el envío?'       => 'Cuando compras el pack o 10 bandejas de hamburguesas o más. En cualquier otro pedido, el envío cuesta ' . (int) mpc_opt( 'ship_cost' ) . ' € a toda la Península.',
			'¿Puedo juntarlo con hamburguesas?' => 'Sí. Todo va en el mismo pedido y el mismo envío.',
			'¿Qué pasa si algo llega mal?'      => 'Lo reponemos o te devolvemos el dinero. Cuéntanoslo desde la página de incidencias.',
		);
	}
	$h = '<div class="mpc-faq__list">';
	foreach ( $items as $q => $a ) {
		$h .= '<details><summary>' . esc_html( $q ) . '</summary><p>' . esc_html( $a ) . '</p></details>';
	}
	return $h . '</div>';
}


/**
 * Etiquetas y atributos SVG permitidos en los iconos.
 *
 * @return array Reglas para wp_kses().
 */
function mpc_svg_kses() {
	return array(
		'svg'    => array(
			'class'           => true,
			'viewbox'         => true,
			'width'           => true,
			'height'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'aria-hidden'     => true,
			'xmlns'           => true,
		),
		'path'   => array( 'd' => true ),
		'circle' => array(
			'cx' => true,
			'cy' => true,
			'r'  => true,
		),
	);
}

/**
 * Pinta un icono SVG ya filtrado.
 *
 * @param string $name Nombre del icono.
 * @return void
 */
function mpc_the_icon( $name ) {
	echo wp_kses( mpc_icon( $name ), mpc_svg_kses() );
}
