=== Meatprotein Conversion ===
Contributors: adrialopez
Tags: woocommerce, conversion, bundle, checkout
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 8.0
WC tested up to: 9.4
Stable tag: 0.18.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Conversión para meatprotein.eu: pack a medida con stock por bandeja, fichas de producto, carrito, pago, portada y menú.

== Description ==

* Pack de 10 bandejas con selector de sabores, regalo, salsa, pan y queso, y stock descontado por bandeja con trazabilidad.
* Fichas de bandejas sueltas y de productos básicos con barra de compra fija.
* Barra de envío gratis (10 bandejas o importe mínimo), extras en carrito lateral, carrito y pago.
* Portada interactiva y menú montados con las herramientas de Betheme (`wp mpc layout apply`).
* Revisión del catálogo con WP-CLI (`wp mpc products audit`, `fix`, `revert`).

Compatibilidad: pedidos en tablas propias (HPOS) sí. Bloques de carrito y pago de WooCommerce, no (usa el carrito y el pago clásicos).

== Installation ==

1. Sube la carpeta a `wp-content/plugins/` y actívala (WooCommerce debe estar activo).
2. Instala y activa el tema hijo `meatprotein-child`.
3. En WooCommerce > Conversión, pulsa «Aplicar diseño».

== Changelog ==

= 0.15.0 =
* Fichas de productos básicos, diseño montado con Betheme, normalización de nombres, revisión del catálogo con WP-CLI.
* Buenas prácticas de WordPress y WooCommerce: PHPCS (WordPress Coding Standards) limpio, HPOS declarado, scripts diferidos, cachés con invalidación, desinstalación limpia.
