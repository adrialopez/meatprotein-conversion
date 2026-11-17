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
