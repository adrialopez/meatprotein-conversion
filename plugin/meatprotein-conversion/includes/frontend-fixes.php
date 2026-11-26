<?php
/**
 * Arreglos de la parte pública que no dependen del diseño.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * La rejilla de categorías de la portada (Betheme, «Query loop» de términos, 7 resultados) solo debe mostrar
 * las categorías principales: sin esto aparecían subcategorías como «Hamburguesas de pollo».
 */
add_filter(
	'get_terms_args',
	function ( $args, $taxonomies ) {
		if ( is_admin() || ! is_front_page() || ! in_array( 'product_cat', (array) $taxonomies, true ) ) {
			return $args;
		}
		if ( 7 === (int) ( $args['number'] ?? 0 ) && empty( $args['parent'] ) && empty( $args['child_of'] ) ) {
			$args['parent'] = 0;
		}
		return $args;
	},
	10,
	2
);

/**
 * YITH Order Tracking carga el selector de medios de WordPress en la parte pública (en «Mi cuenta»), lo que añade
 * ~30 scripts, plantillas y un error de JavaScript para los visitantes. Solo lo necesitan quienes pueden subir archivos.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() || current_user_can( 'upload_files' ) ) {
			return;
		}
		foreach ( array( 'media-editor', 'media-audiovideo', 'media-views', 'media-models', 'media-grid', 'wp-plupload', 'plupload-all', 'mce-view', 'imgareaselect', 'image-edit' ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
		remove_action( 'wp_footer', 'wp_print_media_templates' );
	},
	100
);

/**
 * Listados de productos: los agotados van al final (en categorías, etiquetas, tienda y búsqueda), sin cambiar el resto del orden.
 */
add_filter(
	'posts_clauses',
	function ( $clauses, $query ) {
		global $wpdb;
		if ( is_admin() || ! $query->is_main_query() || 'product' !== $query->get( 'post_type' ) && ! ( $query->is_post_type_archive( 'product' ) || $query->is_tax( array( 'product_cat', 'product_tag' ) ) ) ) {
			return $clauses;
		}
		if ( false === strpos( $clauses['join'], 'mpc_ml' ) ) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup AS mpc_ml ON mpc_ml.product_id = {$wpdb->posts}.ID";
		}
		$clauses['orderby'] = "(CASE WHEN mpc_ml.stock_status = 'outofstock' THEN 1 ELSE 0 END) ASC" . ( $clauses['orderby'] ? ', ' . $clauses['orderby'] : '' );
		return $clauses;
	},
	20,
	2
);

/**
 * Título de Google de las fichas: con el nombre del producto ya normalizado («Deluxe 0% Azúcar 330 ml» en vez de «DELUXE 0% AZÚCAR 330ML»).
 */
add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'mpc_display_name' ) ) {
			return $title;
		}
		$id  = get_queried_object_id();
		$raw = (string) get_post_field( 'post_title', $id );
		if ( '' !== $raw && false !== strpos( $title, $raw ) ) {
			$title = str_replace( $raw, mpc_display_name( $raw ), $title );
		}
		return $title;
	},
	20
);

/**
 * URLs de categoría sin «categoria-producto» ni la categoría padre: /pan/, /saladas/, /hamburguesas-de-pollo/…
 * Las direcciones antiguas (con la base, con el padre o con un slug renombrado) redirigen con 301 a la nueva.
 * Se ejecuta antes que la redirección propia de Rank Math, que con categorías anidadas apunta a una dirección que no existe.
 */
add_action(
	'parse_request',
	function ( $wp ) {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $path ) {
			return;
		}
		$renamed = array(
			'suplantacion'  => 'suplementacion',
			'snacks-dulces' => 'snacks-y-bebidas',
		);
		$parts = explode( '/', $path );
		// Fichas sin «/producto/»: /producto/mango-protein-chicken/ → /mango-protein-chicken/.
		if ( 'producto' === $parts[0] && isset( $parts[1] ) && '' !== $parts[1] ) {
			$q = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_safe_redirect( home_url( '/' . implode( '/', array_slice( $parts, 1 ) ) . '/' ) . $q, 301 );
			exit;
		}
		$base  = 'categoria-producto' === $parts[0];
		if ( $base ) {
			array_shift( $parts );
		} elseif ( ! isset( $renamed[ $parts[0] ] ) ) {
			return;
		}
		$slug = '';
		$rest = array();
		foreach ( $parts as $i => $seg ) {
			$cand = isset( $renamed[ $seg ] ) ? $renamed[ $seg ] : $seg;
			if ( term_exists( $cand, 'product_cat' ) ) {
				$slug = $cand;
				$rest = array_slice( $parts, $i + 1 );
			}
		}
		if ( '' === $slug ) {
			return;
		}
		$target = home_url( '/' . $slug . '/' . ( $rest ? implode( '/', $rest ) . '/' : '' ) );
		$q      = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		wp_safe_redirect( $target . $q, 301 );
		exit;
	},
	1
);


/**
 * Indicador de carga al añadir al carrito. Va en línea y sin optimizar para que funcione desde el primer toque
 * (LiteSpeed retrasa los scripts externos hasta la primera interacción).
 */
add_action(
	'wp_footer',
	function () {
		echo "<script data-no-optimize=\"1\" data-no-defer=\"1\" data-cfasync=\"false\" nowrap>\n" . <<<'JS'
(function () {
	'use strict';
	var timer = 0;
	function stop() {
		document.documentElement.classList.remove('mpc-busy');
		document.querySelectorAll('.mpc-loading').forEach(function (b) { b.classList.remove('mpc-loading'); });
		clearTimeout(timer);
	}
	function start(btn) {
		document.documentElement.classList.add('mpc-busy');
		if (btn) { btn.classList.add('mpc-loading'); }
		clearTimeout(timer);
		timer = setTimeout(stop, 25000);
	}
	window.mpcBusy = start;
	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (!f || !f.classList || !f.classList.contains('cart')) { return; }
		setTimeout(function () {
			if (e.defaultPrevented) { return; }
			start(f.querySelector('.single_add_to_cart_button'));
		}, 0);
	});
	/* Enlaces de compra directa (?add-to-cart=) */
	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('a[href*="add-to-cart="]') : null;
		if (a && !a.classList.contains('ajax_add_to_cart') && !e.defaultPrevented) { start(a); }
	});
	window.addEventListener('pageshow', function (e) { if (e.persisted) { stop(); } });
	/* En fichas de hamburguesa y pack la compra va por AJAX (admin-ajax: woocommerce_add_to_cart): el indicador dura lo que tarda la respuesta. */
	var xs = XMLHttpRequest.prototype.send;
	XMLHttpRequest.prototype.send = function (body) {
		var x = this, act = '';
		try { act = (body instanceof FormData ? body.get('action') : (typeof body === 'string' ? body : '')) || ''; } catch (err) { act = ''; }
		if (/add_to_cart/.test(String(act))) {
			start(document.querySelector('.single_add_to_cart_button'));
			x.addEventListener('loadend', function () { setTimeout(stop, 500); });
		}
		return xs.apply(this, arguments);
	};
})();
JS
		. "\n</script>\n";
	},
	99
);

/**
 * Medicion: que las etiquetas de Google no las retrase LiteSpeed.
 *
 * En la version para visitantes nuevos LiteSpeed retrasa todo el JS hasta la
 * primera interaccion. Tras pagar, la pasarela devuelve al cliente a "pedido
 * recibido" sin que toque nada, asi que el evento purchase no llegaba a salir.
 * Marcamos las etiquetas de Google con los atributos que LiteSpeed respeta.
 */
function mpc_gtag_is_google_tag( $id, $src = '' ) {
	// El script de eventos de GLA necesita wp.hooks, que carga retrasado: debe retrasarse con el.
	if ( 0 === strpos( (string) $id, 'gla-gtag-events' ) || false !== strpos( (string) $src, 'gtag-events' ) ) {
		return false;
	}
	return 0 === strpos( (string) $id, 'google_gtagjs' )
		|| 0 === strpos( (string) $id, 'gla-gtag' )
		|| 0 === strpos( (string) $id, 'google-tag' )
		|| ( $src && preg_match( '#googletagmanager\.com|google-listings-and-ads|google-site-kit#', $src ) );
}
add_filter(
	'script_loader_tag',
	function ( $tag, $handle, $src ) {
		if ( mpc_gtag_is_google_tag( $handle, $src ) && false === strpos( $tag, 'data-no-defer' ) ) {
			$tag = preg_replace( '/<script\b/', '<script data-no-defer="1" data-no-optimize="1" data-cfasync="false"', $tag, 1 );
		}
		return $tag;
	},
	20,
	3
);
add_filter(
	'wp_inline_script_attributes',
	function ( $attrs ) {
		if ( is_array( $attrs ) && mpc_gtag_is_google_tag( isset( $attrs['id'] ) ? $attrs['id'] : '' ) ) {
			$attrs['data-no-defer']    = '1';
			$attrs['data-no-optimize'] = '1';
			$attrs['data-cfasync']     = 'false';
		}
		return $attrs;
	}
);
add_filter(
	'wp_script_attributes',
	function ( $attrs ) {
		if ( is_array( $attrs ) && mpc_gtag_is_google_tag( isset( $attrs['id'] ) ? $attrs['id'] : '', isset( $attrs['src'] ) ? $attrs['src'] : '' ) ) {
			$attrs['data-no-defer']    = '1';
			$attrs['data-no-optimize'] = '1';
			$attrs['data-cfasync']     = 'false';
		}
		return $attrs;
	}
);
add_filter(
	'litespeed_optm_js_defer_exc',
	function ( $list ) {
		return array_merge( (array) $list, array( 'googletagmanager.com/gtag/js' ) );
	},
	20
);

/**
 * Google Listings & Ads imprime su etiqueta de Ads a pelo, sin filtro: la
 * interceptamos en la cabecera para marcarla igual que las demas.
 */
add_action(
	'wp_head',
	function () {
		ob_start(
			function ( $html ) {
				$attrs = ' data-no-defer="1" data-no-optimize="1" data-cfasync="false"';
				$html  = str_replace( '<script async src="https://www.googletagmanager.com/gtag/js?id=AW-', '<script' . $attrs . ' async src="https://www.googletagmanager.com/gtag/js?id=AW-', $html );
				return preg_replace( '/<script>(\s*window\.dataLayer\s*=\s*window\.dataLayer\s*\|\|\s*\[\];\s*function gtag\(\))/', '<script' . $attrs . '>$1', $html );
			}
		);
	},
	0
);
add_action(
	'wp_head',
	function () {
		if ( ob_get_level() ) {
			ob_end_flush();
		}
	},
	9999
);
