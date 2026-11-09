<?php
/**
 * WP-CLI: limpieza del contenido de las páginas de texto (FAQs y Envíos y devoluciones), con copia para revertir.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! class_exists( 'MPC_Pages_Command' ) ) {
	/**
	 * Comandos `wp mpc pages ...`.
	 */
	class MPC_Pages_Command {

		/**
		 * Limpia FAQs y Envíos y devoluciones, guardando el contenido anterior.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Muestra qué cambiaría, sin guardar nada.
		 *
		 * @param array $args       Argumentos posicionales.
		 * @param array $assoc_args Argumentos con nombre.
		 * @return void
		 */
		public function fix( $args, $assoc_args ) {
			$dry = isset( $assoc_args['dry-run'] );
			$log = (array) get_option( 'mpc_pages_fix_log', array() );
			$new = array();

			// Contenido final de cada página: data/pages/*.html (se generan con data/pages/build_pages.py).
			foreach ( array(
				'faqs'                  => 'faqs.html',
				'envios-y-devoluciones' => 'envios-y-devoluciones.html',
				'reportar-incidencia'   => 'reportar-incidencia.html',
			) as $slug => $file ) {
				$page = get_page_by_path( $slug );
				$path = MPC_DIR . 'data/pages/' . $file;
				if ( ! $page || ! is_readable( $path ) ) {
					WP_CLI::warning( 'Falta la página «' . $slug . '» o su contenido (' . $file . ').' );
					continue;
				}
				$content = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( $content !== $page->post_content ) {
					$new[ $page->ID ] = $content;
				}
			}
			foreach ( $new as $id => $content ) {
				$post = get_post( $id );
				WP_CLI::log( sprintf( '#%d %s: %d -> %d caracteres', $id, $post->post_name, strlen( $post->post_content ), strlen( $content ) ) );
				if ( $dry ) {
					continue;
				}
				if ( ! isset( $log[ $id ] ) ) {
					$log[ $id ] = $post->post_content;
				}
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $id,
							'post_content' => $content,
						)
					)
				);
			}
			if ( ! $dry ) {
				update_option( 'mpc_pages_fix_log', $log, false );
			}
			WP_CLI::success( ( $dry ? 'Simulación: ' : '' ) . count( $new ) . ' página(s).' );
		}

		/**
		 * Devuelve las páginas al contenido que tenían antes de `fix`.
		 *
		 * @return void
		 */
		public function revert() {
			$log = (array) get_option( 'mpc_pages_fix_log', array() );
			foreach ( $log as $id => $content ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'           => (int) $id,
							'post_content' => $content,
						)
					)
				);
			}
			delete_option( 'mpc_pages_fix_log' );
			WP_CLI::success( count( $log ) . ' página(s) restauradas.' );
		}
	}

	WP_CLI::add_command( 'mpc pages', 'MPC_Pages_Command' );
}
