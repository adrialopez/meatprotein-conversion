<?php
/**
 * Plugin Name: Meatprotein Conversion
 * Description: Mejoras de conversión para meatprotein.eu: selector de sabores del pack, bloque de confianza, barra de envío gratis, página de packs, promos puntuales y checkout.
 * Version: 0.6.0
 * Author: Adrià López
 * Author URI: https://adria-lopez.com
 * Text Domain: meatprotein-conversion
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MPC_VERSION', '0.6.0' );
define( 'MPC_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPC_URL', plugin_dir_url( __FILE__ ) );

require_once MPC_DIR . 'includes/settings.php';
require_once MPC_DIR . 'includes/helpers.php';
require_once MPC_DIR . 'includes/flavours.php';
require_once MPC_DIR . 'includes/components.php';
require_once MPC_DIR . 'includes/product.php';
require_once MPC_DIR . 'includes/cart.php';
require_once MPC_DIR . 'includes/addons.php';
require_once MPC_DIR . 'includes/promo.php';
require_once MPC_DIR . 'includes/packs-page.php';
require_once MPC_DIR . 'includes/checkout.php';

/* Estilos y scripts: un solo CSS, cargado solo en tienda. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return;
	}
	$ver = function ( $rel ) {
		return 'local' === wp_get_environment_type() ? (string) filemtime( MPC_DIR . $rel ) : MPC_VERSION;
	};
	wp_enqueue_style( 'mpc', MPC_URL . 'assets/css/mpc.css', array(), $ver( 'assets/css/mpc.css' ) );
	wp_enqueue_style( 'mpc-design', MPC_URL . 'assets/css/design.css', array( 'mpc' ), $ver( 'assets/css/design.css' ) );

	if ( is_product() ) {
		wp_enqueue_script( 'mpc-bundle', MPC_URL . 'assets/js/bundle.js', array( 'jquery' ), $ver( 'assets/js/bundle.js' ), true );
		wp_enqueue_script( 'mpc-pack', MPC_URL . 'assets/js/pack-builder.js', array( 'jquery', 'wc-add-to-cart-variation' ), $ver( 'assets/js/pack-builder.js' ), true );
	}
	wp_enqueue_script( 'mpc-cart', MPC_URL . 'assets/js/cart-ui.js', array( 'jquery' ), $ver( 'assets/js/cart-ui.js' ), true );
	wp_enqueue_script( 'mpc-ui', MPC_URL . 'assets/js/ui.js', array( 'jquery' ), $ver( 'assets/js/ui.js' ), true );
} );

/* Betheme pone overflow-x:hidden en html y body a la vez: en iOS bloquea el scroll vertical.
   overflow-x:clip recorta igual sin crear un contenedor de scroll. */
add_action( 'wp_head', function () {
	echo '<style id="mpc-ios-scroll">@supports (overflow: clip){html,body{overflow-x:clip!important}}</style>' . "\n";
}, 99 );

/* Proteína y kcal por hamburguesa en las tarjetas de producto (las pinta ui.js). */
add_action( 'wp_footer', function () {
	if ( is_admin() || is_checkout() || is_cart() ) {
		return;
	}
	$map = array();
	foreach ( mpc_nutrition_data() as $id => $d ) {
		$map[ (int) $id ] = array( 'p' => $d['prot'], 'k' => $d['kcal'] );
	}
	if ( $map ) {
		echo '<script>window.mpcNut=' . wp_json_encode( $map ) . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 2 );
