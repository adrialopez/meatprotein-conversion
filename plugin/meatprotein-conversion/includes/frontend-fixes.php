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
