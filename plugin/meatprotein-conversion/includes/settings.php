<?php
/**
 * Ajustes del plugin.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valores por defecto de los ajustes.
 *
 * @return array
 */
function mpc_defaults() {
	return array(
		'free_min'     => '60',
		'addons'       => '',
		'snacks'       => '',
		'gift_product' => '',
		'step_salsa'   => '',
		'step_pan'     => '',
		'step_queso'   => '',
		'ship_cost'    => '9',
		'promo_text'   => 'Envío refrigerado en 24-48 h · Gratis con 10 bandejas o desde 60 €',
		'promo_url'    => '',
		'promo_ends'   => '',
		'promo_cta'    => '',
		'nutrition'    => '',
		'origin'       => 'Meatprotein es la marca de Servicarfres S.L. (Calle Marino Archer 43, 48013 Bilbao), que fabrica estas hamburguesas.',
		'certs'        => '',
		'phone'        => '944 999 194',
		'whatsapp'     => '',
		'hero'         => '1',
	);
}

/**
 * Lee un ajuste del plugin.
 *
 * @param string $key Clave del ajuste.
 * @return mixed
 */
function mpc_opt( $key ) {
	$o = wp_parse_args( (array) get_option( 'mpc_settings', array() ), mpc_defaults() );
	return isset( $o[ $key ] ) ? $o[ $key ] : '';
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'woocommerce', 'Conversión', 'Conversión', 'manage_woocommerce', 'mpc', 'mpc_settings_page' );
	}
);
add_action(
	'admin_init',
	function () {
		register_setting(
			'mpc',
			'mpc_settings',
			array(
				'sanitize_callback' => function ( $in ) {
					$out = array();
					foreach ( mpc_defaults() as $k => $d ) {
						$v = isset( $in[ $k ] ) ? wp_unslash( $in[ $k ] ) : '';
						$out[ $k ] = in_array( $k, array( 'nutrition', 'origin', 'certs' ), true ) ? wp_kses_post( $v ) : sanitize_text_field( $v );
					}
					return $out;
				},
			)
		);
	}
);

/**
 * Pinta la página de ajustes en WooCommerce > Conversión.
 *
 * @return mixed Resultado.
 */
function mpc_settings_page() {
	$f = function ( $key, $label, $help = '', $type = 'text' ) {
		$v = mpc_opt( $key );
		echo '<tr><th><label for="mpc_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'textarea' === $type ) {
			echo '<textarea id="mpc_' . esc_attr( $key ) . '" name="mpc_settings[' . esc_attr( $key ) . ']" rows="4" class="large-text">' . esc_textarea( $v ) . '</textarea>';
		} else {
			echo '<input id="mpc_' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" name="mpc_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $v ) . '" class="regular-text">';
		}
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	};
	echo '<div class="wrap"><h1>Conversión</h1>';
	mpc_layout_admin_box();
	echo '<form method="post" action="options.php">';
	settings_fields( 'mpc' );
	echo '<h2>Envío</h2><table class="form-table">';
	$f( 'free_min', 'Envío gratis desde (€)', 'Importe de productos a partir del cual el envío es gratis (además, 10 bandejas o más siempre lo tienen gratis). Debe coincidir con WooCommerce.', 'number' );
	$f( 'ship_cost', 'Coste de envío si no llega (€)', 'Solo informativo, para los textos.', 'number' );
	$f( 'gift_product', 'Producto de la bandeja de regalo (ID)', 'Si el pack incluye una bandeja de regalo que es un producto de la tienda, indica su ID y su stock se descontará con cada pack. Cámbialo cuando cambie el regalo de la promo.', 'number' );
	$f( 'step_salsa', 'Bundle: salsas (IDs)', 'Vacío = automático: salsas más vendidas con stock.' );
	$f( 'step_pan', 'Bundle: pan (IDs)', 'Vacío = automático.' );
	$f( 'step_queso', 'Bundle: queso (IDs)', 'Vacío = automático.' );
	$f( 'addons', 'Extras del pack (IDs de producto)', 'Separados por comas, en orden. Vacío = automático: el más vendido de cada categoría que no sea hamburguesas.' );
	$f( 'snacks', 'Snacks del carrito (IDs)', 'Separados por comas, en orden. Vacío = automático: los más vendidos de la categoría «Snacks dulces».' );
	echo '</table><h2>Promo puntual</h2><table class="form-table">';
	$f( 'promo_text', 'Texto de la barra superior', 'Déjalo vacío para ocultar la barra.' );
	$f( 'promo_cta', 'Texto del enlace', 'Por ejemplo: Ver el pack.' );
	$f( 'promo_url', 'Enlace de la barra', 'URL completa.' );
	$f( 'promo_ends', 'La promo termina', 'Si la rellenas, se muestra una cuenta atrás y la barra desaparece sola al terminar.', 'datetime-local' );
	echo '</table><h2>Portada</h2><table class="form-table">';
	$f( 'hero', 'Portada interactiva (1 = sí, 0 = no)', 'Muestra el hero «Monta tu pack» en lugar del hero de siempre. Ponlo a 0 para volver al original.' );
	echo '</table><h2>Información de producto</h2><table class="form-table">';
	$f( 'nutrition', 'Información nutricional y alérgenos', 'Aparece en la ficha de cada pack. Si está vacío, el bloque no se muestra.', 'textarea' );
	$f( 'origin', 'Origen y fabricación', 'Quién fabrica, de dónde viene la carne.', 'textarea' );
	$f( 'certs', 'Certificaciones', 'Por ejemplo: IFS Food, BRC, registro sanitario.', 'textarea' );
	echo '</table><h2>Contacto</h2><table class="form-table">';
	$f( 'phone', 'Teléfono' );
	$f( 'whatsapp', 'WhatsApp (solo números, con prefijo)', 'Ejemplo: 34600000000. Si lo rellenas se añade el enlace.' );
	echo '</table>';
	submit_button();
	echo '</form></div>';
}
