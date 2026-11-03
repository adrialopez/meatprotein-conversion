<?php
/**
 * Cada sabor del pack apunta a su hamburguesa: la imagen de ingredientes, peso y valores nutricionales
 * que ya está en la descripción de ese producto (así no se duplican datos).
 * Clave = nombre del sabor normalizado (minúsculas, sin tildes, «&» = «y»), igual que en el JS.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mpc_flavour_products() {
	return apply_filters( 'mpc_flavour_products', array(
		'chorizo'                       => 2499,
		'barbacoa'                      => 5098,
		'potro trufa'                   => 13621,
		'potro'                         => 2458,
		'espinacas y queso'             => 2427,
		'albondigas'                    => 2500,
		'pollo trufa'                   => 18999,
		'miel y tomate'                 => 43882,
		'miel con tomate'               => 43882,
		'pizza'                         => 2455,
		'pechuga con puerro y calabaza' => 34859,
		'pollo'                         => 2462,
		'chees bacon'                   => 2457,
		'cheese bacon'                  => 2457,
		'rulo de cabra'                 => 2459,
		'ternera queso'                 => 2450,
		'ternera queso y trufa'         => 20065,
		'ternera y queso trufa'         => 20065,
		'ternera cebolla caramelizada'  => 36141,
		'ternera cebolla'               => 36141,
		'ternera'                       => 2502,
		'mexican'                       => 2461,
		'bacon'                         => 4142,
		'kebab'                         => 2501,
		'pavo cebolla'                  => 34470,
		'pavo con cebolla caramelizada' => 34470,
		'pavo'                          => 2463,
		'chicken mango'                 => 45578,
		'satay'                         => 2498,
		'parrilla'                      => 2460,
		'pepinillo con queso cheddar'   => 42057,
	) );
}

/** Datos para el JS: título, imagen de información y enlace de cada sabor. */
function mpc_flavours_data() {
	$out = array();
	foreach ( mpc_flavour_products() as $key => $id ) {
		$p = get_post( $id );
		if ( ! $p || 'publish' !== $p->post_status ) {
			continue;
		}
		if ( ! preg_match( '/<img[^>]+src="([^"]+)"/i', $p->post_content, $m ) ) {
			continue;
		}
		$out[ $key ] = array(
			't' => html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' ),
			'i' => esc_url_raw( $m[1] ),
			'u' => get_permalink( $id ),
		);
	}
	return $out;
}
