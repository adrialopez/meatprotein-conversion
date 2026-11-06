<?php
/**
 * Plugin Name:       Meatprotein Conversion
 * Plugin URI:        https://github.com/adrialopez/meatprotein-conversion
 * Description:       Mejoras de conversión para meatprotein.eu: pack a medida, bandejas y productos básicos, carrito, pago, portada y menú.
 * Version:           0.18.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Adrià López
 * Author URI:        https://adria-lopez.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       meatprotein-conversion
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.4
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MPC_VERSION', '0.18.0' );
define( 'MPC_FILE', __FILE__ );
define( 'MPC_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Compatibilidad declarada con WooCommerce: pedidos en tablas propias (HPOS) sí; bloques de carrito y pago, no
 * (el plugin trabaja sobre el carrito y el pago clásicos de WooCommerce).
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MPC_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', MPC_FILE, false );
		}
	}
);

/**
 * Aviso si falta WooCommerce.
 *
 * @return void
 */
function mpc_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Meatprotein Conversion necesita WooCommerce activo para funcionar.', 'meatprotein-conversion' ) . '</p></div>';
}

/**
 * Arranque: carga los módulos cuando WooCommerce está disponible.
 *
 * @return void
 */
function mpc_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'mpc_missing_woocommerce_notice' );
		return;
	}
	$modules = array( 'settings', 'helpers', 'names', 'flavours', 'components', 'product', 'cart', 'addons', 'promo', 'packs-page', 'checkout', 'hero', 'layout' );
	foreach ( $modules as $module ) {
		require_once MPC_DIR . 'includes/' . $module . '.php';
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once MPC_DIR . 'includes/class-mpc-products-command.php';
	}
	add_action( 'wp_enqueue_scripts', 'mpc_enqueue_assets' );
}
add_action( 'plugins_loaded', 'mpc_bootstrap', 20 );

/**
 * Textos traducibles.
 *
 * @return void
 */
function mpc_load_textdomain() {
	load_plugin_textdomain( 'meatprotein-conversion', false, dirname( plugin_basename( MPC_FILE ) ) . '/languages' );
}
add_action( 'init', 'mpc_load_textdomain' );

/**
 * Versión de un recurso: la fecha del archivo en local (cada cambio se ve) y la del plugin en producción.
 *
 * @param string $rel Ruta relativa al plugin.
 * @return string
 */
function mpc_asset_version( $rel ) {
	return 'local' === wp_get_environment_type() ? (string) filemtime( MPC_DIR . $rel ) : MPC_VERSION;
}

/**
 * Datos que necesitan los scripts de la web (proteína por tarjeta y enlaces del menú).
 *
 * @return string JavaScript con las variables globales.
 */
function mpc_inline_data() {
	$js  = '';
	$map = array();
	foreach ( mpc_nutrition_data() as $id => $d ) {
		$map[ (int) $id ] = array(
			'p' => $d['prot'],
			'k' => $d['kcal'],
		);
	}
	if ( $map && ! is_checkout() && ! is_cart() ) {
		$js .= 'window.mpcNut=' . wp_json_encode( $map ) . ';';
	}
	$packs = mpc_page_url( 'packs' );
	if ( $packs ) {
		$js .= 'window.mpcNav=' . wp_json_encode( mpc_nav_data( $packs ) ) . ';';
	}
	return $js;
}

/**
 * Estilos y scripts: solo lo que cada página necesita, con versión y en el pie (diferidos).
 *
 * @return void
 */
function mpc_enqueue_assets() {
	wp_enqueue_style( 'mpc', MPC_URL . 'assets/css/mpc.css', array(), mpc_asset_version( 'assets/css/mpc.css' ) );
	wp_enqueue_style( 'mpc-design', MPC_URL . 'assets/css/design.css', array( 'mpc' ), mpc_asset_version( 'assets/css/design.css' ) );
	// Betheme pone overflow-x:hidden en html y body a la vez y en iOS bloquea el scroll vertical: clip recorta igual sin crear un contenedor de scroll.
	wp_add_inline_style( 'mpc', '@supports (overflow: clip){html,body{overflow-x:clip!important}}' );

	$defer = array(
		'in_footer' => true,
		'strategy'  => 'defer',
	);
	if ( is_product() ) {
		wp_enqueue_script( 'mpc-bundle', MPC_URL . 'assets/js/bundle.js', array( 'jquery' ), mpc_asset_version( 'assets/js/bundle.js' ), $defer );
		wp_enqueue_script( 'mpc-pack', MPC_URL . 'assets/js/pack-builder.js', array( 'jquery', 'wc-add-to-cart-variation' ), mpc_asset_version( 'assets/js/pack-builder.js' ), $defer );
	}
	if ( is_checkout() && ! is_order_received_page() ) {
		wp_enqueue_script( 'mpc-checkout', MPC_URL . 'assets/js/checkout.js', array( 'jquery' ), mpc_asset_version( 'assets/js/checkout.js' ), $defer );
	}
	if ( mpc_hero_enabled() ) {
		wp_enqueue_script( 'mpc-hero', MPC_URL . 'assets/js/hero.js', array(), mpc_asset_version( 'assets/js/hero.js' ), $defer );
	}
	wp_enqueue_script( 'mpc-cart', MPC_URL . 'assets/js/cart-ui.js', array( 'jquery' ), mpc_asset_version( 'assets/js/cart-ui.js' ), $defer );
	wp_enqueue_script( 'mpc-ui', MPC_URL . 'assets/js/ui.js', array( 'jquery' ), mpc_asset_version( 'assets/js/ui.js' ), $defer );
	$data = mpc_inline_data();
	if ( '' !== $data ) {
		wp_add_inline_script( 'mpc-ui', $data, 'before' );
	}
}

/**
 * Al activar: valores por defecto de los ajustes (sin cargarlos en cada petición).
 *
 * @return void
 */
function mpc_activate() {
	if ( false === get_option( 'mpc_settings', false ) ) {
		add_option( 'mpc_settings', array(), '', false );
	}
}
register_activation_hook( __FILE__, 'mpc_activate' );

/**
 * Al desactivar: vaciar cachés propias.
 *
 * @return void
 */
function mpc_deactivate() {
	delete_transient( 'mpc_addon_ids_auto' );
	delete_transient( 'mpc_pack_ids' );
	delete_transient( 'mpc_favs' );
}
register_deactivation_hook( __FILE__, 'mpc_deactivate' );
