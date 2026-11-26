<?php
/**
 * Medición: consentimiento ya dado, compra en «pedido recibido», añadir al carrito e inicio de pago.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quien ya eligió en el aviso de cookies: se aplica su elección antes de que Google mida la página.
 * El script de CookieYes se carga con la primera interacción, así que sin esto la visita contaba como «sin consentimiento».
 * Solo refleja lo guardado en la cookie «cookieyes-consent»; sin una elección previa no hace nada.
 */
function mpc_consent_restore_js() {
	return <<<'JS'
(function () {
	var m = document.cookie.match(/(?:^|;\s*)cookieyes-consent=([^;]*)/);
	if (!m) { return; }
	var c = {};
	try {
		decodeURIComponent(m[1]).split(',').forEach(function (p) {
			var i = p.indexOf(':');
			if (i > 0) { c[p.slice(0, i)] = p.slice(i + 1); }
		});
	} catch (e) { return; }
	if (c.action !== 'yes') { return; }
	function g(v) { return v === 'yes' ? 'granted' : 'denied'; }
	window.dataLayer = window.dataLayer || [];
	(function () { window.dataLayer.push(arguments); })('consent', 'update', {
		analytics_storage: g(c.analytics),
		ad_storage: g(c.advertisement),
		ad_user_data: g(c.advertisement),
		ad_personalization: g(c.advertisement),
		functionality_storage: g(c.functional),
		personalization_storage: g(c.functional)
	});
})();
JS;
}
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( wp_script_is( 'google_gtagjs', 'registered' ) ) {
			wp_add_inline_script( 'google_gtagjs', mpc_consent_restore_js(), 'before' );
		}
	},
	999
);

/**
 * «Pedido recibido»: sin optimización de JavaScript. El evento de compra de Site Kit necesita jQuery al cargar
 * y el cliente que vuelve de la pasarela no siempre toca la pantalla.
 */
add_action(
	'template_redirect',
	function () {
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() && ! defined( 'LITESPEED_NO_OPTM' ) ) {
			define( 'LITESPEED_NO_OPTM', true );
		}
	},
	0
);

/**
 * Artículo en el formato de GA4.
 *
 * @param WC_Product $product Producto.
 * @param int        $qty     Cantidad.
 * @param float|null $price   Precio unitario; por defecto el de la tienda.
 */
function mpc_ga_item( $product, $qty = 1, $price = null ) {
	return array(
		'item_id'   => (string) $product->get_id(),
		'item_name' => wp_strip_all_tags( $product->get_name() ),
		'price'     => round( (float) ( null === $price ? wc_get_price_to_display( $product ) : $price ), 2 ),
		'quantity'  => (int) $qty,
	);
}

/**
 * Ficha de producto: Betheme añade al carrito por AJAX desde un botón sin «data-product_id» y Site Kit no lo registra.
 * Va sin atributos de exclusión para ejecutarse después de jQuery (en la primera interacción, antes de cualquier compra).
 */
add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) {
			return;
		}
		$data = wp_json_encode(
			array(
				'currency' => get_woocommerce_currency(),
				'item'     => mpc_ga_item( $product ),
			)
		);
		echo "<script>\n(function (data) {\n" . <<<'JS'
	var $ = window.jQuery;
	if (!$) { return; }
	var items = null;
	function snapshot() {
		var form = $('form.cart').first(), qty = parseInt(form.find('input.qty').val(), 10) || 1;
		var list = [$.extend({}, data.item, { quantity: qty })];
		form.find('input[name^="mpc_extra["]').each(function () {
			var n = parseInt(this.value, 10) || 0, id = (this.name.match(/\d+/) || [])[0];
			var card = $('.mpc-card[data-id="' + id + '"]').first();
			if (n > 0 && card.length) {
				list.push({ item_id: String(id), item_name: String(card.data('name')), price: parseFloat(card.data('price')) || 0, quantity: n });
			}
		});
		return list;
	}
	$(document.body).on('adding_to_cart', function () { items = snapshot(); });
	$(document.body).on('added_to_cart', function (e, fragments, hash, $button) {
		if (!$button || !$button.hasClass || !$button.hasClass('single_add_to_cart_button') || $button.data('product_id')) { return; }
		var list = items || snapshot();
		items = null;
		var value = list.reduce(function (t, i) { return t + i.price * i.quantity; }, 0);
		if (typeof window.gtag === 'function') {
			window.gtag('event', 'add_to_cart', { currency: data.currency, value: Math.round(value * 100) / 100, items: list });
		}
	});
JS
		. "\n})(" . $data . ");\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
	},
	99
);

/**
 * Inicio de pago: una vez por carrito y pestaña.
 */
add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || is_wc_endpoint_url( 'order-pay' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		$items = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			$qty     = max( 1, (int) $line['quantity'] );
			$items[] = mpc_ga_item( $line['data'], $qty, ( $line['line_total'] + $line['line_tax'] ) / $qty );
		}
		$data = wp_json_encode(
			array(
				'key'   => WC()->cart->get_cart_hash(),
				'event' => array(
					'currency' => get_woocommerce_currency(),
					'value'    => round( (float) WC()->cart->get_cart_contents_total() + (float) WC()->cart->get_cart_contents_tax(), 2 ),
					'items'    => $items,
				),
			)
		);
		echo '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false">' . "\n(function (d) {\n" . <<<'JS'
	if (typeof window.gtag !== 'function') { return; }
	try {
		if (sessionStorage.getItem('mpc_bc') === d.key) { return; }
		sessionStorage.setItem('mpc_bc', d.key);
	} catch (e) {}
	window.gtag('event', 'begin_checkout', d.event);
JS
		. "\n})(" . $data . ");\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
	},
	99
);
