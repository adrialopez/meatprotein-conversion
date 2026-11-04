<?php
/**
 * Tema hijo de Betheme para meatprotein.eu.
 *
 * Reparto de trabajo:
 * - Plugin «Meatprotein Conversion»: lógica (pack a medida, stock, carrito, pago), componentes (.mpc-*) y variables.
 * - Este tema hijo: la adaptación a Betheme y a WooCommerce (selectores del tema, plantillas y ajustes visuales).
 *
 * @package Meatprotein_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MPC_CHILD_VERSION', '1.0.8' );

/**
 * Hoja de estilos de la adaptación a Betheme. Va después de la del plugin (que define las variables --mpc-*)
 * para que, a igualdad de especificidad, gane la adaptación.
 *
 * @return void
 */
function mpc_child_enqueue_styles() {
	$deps = wp_style_is( 'mpc-design', 'registered' ) ? array( 'mpc-design' ) : array();
	$path = get_stylesheet_directory() . '/assets/css/betheme.css';
	$ver  = 'local' === wp_get_environment_type() && file_exists( $path ) ? (string) filemtime( $path ) : MPC_CHILD_VERSION;
	wp_enqueue_style( 'mpc-child-betheme', get_stylesheet_directory_uri() . '/assets/css/betheme.css', $deps, $ver );
}
add_action( 'wp_enqueue_scripts', 'mpc_child_enqueue_styles', 20 );

/**
 * Textos traducibles del tema hijo.
 *
 * @return void
 */
function mpc_child_setup() {
	load_child_theme_textdomain( 'meatprotein-child', get_stylesheet_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'mpc_child_setup' );

/**
 * Al activar el tema hijo, copia los ajustes del tema padre (ubicaciones de menús, áreas de widgets, logotipos...).
 * Sin esto WordPress arranca el hijo con los ajustes vacíos y se pierde la configuración de Betheme.
 *
 * @return void
 */
function mpc_child_copy_parent_mods() {
	$parent = get_option( 'theme_mods_' . get_template() );
	if ( ! is_array( $parent ) ) {
		return;
	}
	$child = get_option( 'theme_mods_' . get_stylesheet(), array() );
	update_option( 'theme_mods_' . get_stylesheet(), array_merge( $parent, is_array( $child ) ? $child : array() ) );
}
add_action( 'after_switch_theme', 'mpc_child_copy_parent_mods' );
