<?php
/**
 * Auditoría y limpieza segura del catálogo con WP-CLI.
 *
 * Uso: wp mpc products audit, wp mpc products fix y wp mpc products revert.
 *
 * El subcomando audit no cambia nada. El subcomando fix solo aplica correcciones seguras (entidades y erratas
 * en el título, URLs que no corresponden al producto, texto de las pizzas en el campo equivocado) y guarda los
 * valores anteriores para poder deshacerlas con revert. Lo que necesita una decisión de una persona (impuestos,
 * descripciones repetidas...) solo se informa.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Revisión del catálogo de productos.
	 */
	class MPC_Products_Command {

		/**
		 * Informe de calidad de datos del catálogo. No modifica nada.
		 *
		 * ## OPTIONS
		 *
		 * [--format=<format>]
		 * : Formato de salida.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - json
		 * ---
		 *
		 * @param array $args       Argumentos posicionales.
		 * @param array $assoc_args Argumentos con nombre.
		 * @return void
		 */
		public function audit( $args, $assoc_args ) {
			$rows   = $this->collect_issues();
			$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
			\WP_CLI\Utils\format_items( $format, $rows, array( 'gravedad', 'id', 'producto', 'tipo', 'detalle' ) );
			\WP_CLI::success( count( $rows ) . ' hallazgos.' );
		}

		/**
		 * Aplica las correcciones seguras y guarda lo anterior para poder deshacerlas.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Muestra qué cambiaría, sin guardar nada.
		 *
		 * [--only=<reglas>]
		 * : Reglas separadas por comas: titulos, slugs, pizzas. Por defecto, todas.
		 *
		 * @param array $args       Argumentos posicionales.
		 * @param array $assoc_args Argumentos con nombre.
		 * @return void
		 */
		public function fix( $args, $assoc_args ) {
			$dry   = isset( $assoc_args['dry-run'] );
			$only  = isset( $assoc_args['only'] ) ? array_map( 'trim', explode( ',', $assoc_args['only'] ) ) : array( 'titulos', 'slugs', 'pizzas' );
			$log   = (array) get_option( 'mpc_products_fix_log', array() );
			$count = 0;
			foreach ( $this->product_ids() as $id ) {
				$post = get_post( $id );
				if ( ! $post ) {
					continue;
				}
				$update = array();
				$before = array();
				if ( in_array( 'titulos', $only, true ) ) {
					$title = $this->clean_stored_title( $post->post_title );
					if ( $title !== $post->post_title ) {
						$update['post_title'] = $title;
						$before['post_title'] = $post->post_title;
					}
				}
				if ( in_array( 'slugs', $only, true ) && $this->slug_mismatch( $post ) ) {
					$update['post_name'] = sanitize_title( $this->clean_stored_title( $post->post_title ) );
					$before['post_name'] = $post->post_name;
				}
				if ( in_array( 'pizzas', $only, true ) && $this->pizza_text_misplaced( $post ) ) {
					$update['post_content'] = $post->post_excerpt;
					$update['post_excerpt'] = $this->first_sentence( $post->post_excerpt );
					$before['post_content'] = $post->post_content;
					$before['post_excerpt'] = $post->post_excerpt;
				}
				if ( ! $update ) {
					continue;
				}
				++$count;
				\WP_CLI::log( sprintf( '#%d %s -> %s', $id, implode( ',', array_keys( $update ) ), isset( $update['post_title'] ) ? $update['post_title'] : $post->post_title ) );
				if ( $dry ) {
					continue;
				}
				$log[ $id ] = array_merge( isset( $log[ $id ] ) ? $log[ $id ] : array(), $before );
				wp_update_post( array_merge( array( 'ID' => $id ), wp_slash( $update ) ) );
			}
			if ( ! $dry ) {
				update_option( 'mpc_products_fix_log', $log, false );
			}
			\WP_CLI::success( sprintf( '%d productos %s.', $count, $dry ? 'cambiarían' : 'corregidos' ) );
		}

		/**
		 * Deshace las correcciones de «fix».
		 *
		 * @param array $args       Argumentos posicionales.
		 * @param array $assoc_args Argumentos con nombre.
		 * @return void
		 */
		public function revert( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- WP-CLI siempre pasa los dos argumentos.
			$log = (array) get_option( 'mpc_products_fix_log', array() );
			foreach ( $log as $id => $before ) {
				wp_update_post( array_merge( array( 'ID' => (int) $id ), wp_slash( $before ) ) );
			}
			delete_option( 'mpc_products_fix_log' );
			\WP_CLI::success( count( $log ) . ' productos restaurados.' );
		}

		/**
		 * IDs de todos los productos (cualquier estado visible para el equipo).
		 *
		 * @return int[]
		 */
		private function product_ids() {
			return wc_get_products(
				array(
					'limit'  => -1,
					'status' => array( 'publish', 'draft', 'private', 'pending' ),
					'return' => 'ids',
				)
			);
		}

		/**
		 * Título guardado sin entidades, «NEW» ni erratas conocidas (sin cambiar mayúsculas: eso se arregla al mostrar).
		 *
		 * @param string $title Título guardado.
		 * @return string
		 */
		private function clean_stored_title( $title ) {
			$n = html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
			$n = preg_replace( '/(?<=\s|^)NEW!?(?=\s|$)/iu', '', $n );
			foreach ( mpc_name_typos() as $wrong => $right ) {
				$n = preg_replace_callback(
					'/\b' . preg_quote( $wrong, '/' ) . '\b/iu',
					static function ( $m ) use ( $right ) {
						return mpc_is_shouting( $m[0] ) ? mb_strtoupper( $right, 'UTF-8' ) : $right;
					},
					$n
				);
			}
			return trim( preg_replace( '/\s+/u', ' ', $n ) );
		}

		/**
		 * La URL no se parece al título (por ejemplo «...-ternera-queso-copia» en una hamburguesa de pavo).
		 *
		 * @param WP_Post $post Producto.
		 * @return bool
		 */
		private function slug_mismatch( $post ) {
			$wanted = sanitize_title( $this->clean_stored_title( $post->post_title ) );
			if ( '' === $wanted || $wanted === $post->post_name ) {
				return false;
			}
			$base = preg_replace( '/-\d+$/', '', $post->post_name );
			if ( $base === $wanted && $base !== $post->post_name ) {
				// «nombre-2»: sobra el sufijo si la URL limpia ya está libre.
				$owner = get_page_by_path( $wanted, OBJECT, 'product' );
				return ! $owner || (int) $owner->ID === (int) $post->ID;
			}
			if ( $base === $wanted || 0 === strpos( $wanted, $base ) || 0 === strpos( $base, $wanted ) ) {
				return false;
			}
			return (bool) preg_match( '/copia|^producto(-\d+)?$/', $post->post_name ) || similar_text( $wanted, $base ) / max( 1, strlen( $wanted ) ) < 0.6;
		}

		/**
		 * La «Nuestra filosofía» de las pizzas está en la descripción corta y la larga está vacía.
		 *
		 * @param WP_Post $post Producto.
		 * @return bool
		 */
		private function pizza_text_misplaced( $post ) {
			return '' === trim( wp_strip_all_tags( $post->post_content ) ) && 0 === stripos( trim( wp_strip_all_tags( $post->post_excerpt ) ), 'NUESTRA FILOSOF' );
		}

		/**
		 * Primera frase con contenido, sin el encabezado.
		 *
		 * @param string $text Texto.
		 * @return string
		 */
		private function first_sentence( $text ) {
			$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
			$plain = trim( preg_replace( '/^NUESTRA FILOSOF[ÍI]A\s*/iu', '', $plain ) );
			$parts = preg_split( '/(?<=[.!?])\s+/u', $plain, 2 );
			return isset( $parts[0] ) ? $parts[0] : $plain;
		}

		/**
		 * Texto normalizado para comparar.
		 *
		 * @param string $text Texto.
		 * @return string
		 */
		private function fingerprint( $text ) {
			return mb_substr( preg_replace( '/[^a-z0-9]/', '', remove_accents( mb_strtolower( wp_strip_all_tags( $text ), 'UTF-8' ) ) ), 0, 160 );
		}

		/**
		 * Recorre el catálogo y recoge los hallazgos.
		 *
		 * @return array<int,array<string,mixed>>
		 */
		private function collect_issues() {
			$rows     = array();
			$seen     = array();
			$tax_by   = array();
			$products = array();
			foreach ( $this->product_ids() as $id ) {
				$p = wc_get_product( $id );
				if ( $p ) {
					$products[] = $p;
				}
			}
			foreach ( $products as $p ) {
				if ( 'publish' !== $p->get_status() ) {
					continue;
				}
				$cat                                     = (int) current( $p->get_category_ids() );
				$tax_by[ $cat ][ $p->get_tax_class() ][] = $p->get_id();
			}
			$add = static function ( $sev, $p, $type, $detail ) use ( &$rows ) {
				$rows[] = array(
					'gravedad' => $sev,
					'id'       => $p->get_id(),
					'producto' => mb_substr( html_entity_decode( $p->get_name( 'edit' ), ENT_QUOTES, 'UTF-8' ), 0, 48 ),
					'tipo'     => $type,
					'detalle'  => $detail,
				);
			};
			foreach ( $products as $p ) {
				if ( 'publish' !== $p->get_status() ) {
					continue;
				}
				$post  = get_post( $p->get_id() );
				$plain = trim( wp_strip_all_tags( $post->post_content ) );
				$name  = $p->get_name( 'edit' );
				if ( '' === $plain && ! preg_match( '/<img/i', $post->post_content ) ) {
					$add( 'media', $p, 'Sin descripción', 'La ficha no tiene descripción.' );
				} elseif ( strlen( $plain ) < 60 && preg_match( '/<img/i', $post->post_content ) ) {
					$add( 'media', $p, 'Datos en una imagen', 'La información (etiqueta) solo está como imagen: no se puede buscar ni leer.' );
				}
				if ( strlen( $plain ) > 60 ) {
					$fp = $this->fingerprint( $plain );
					if ( isset( $seen[ $fp ] ) ) {
						$allergen = (bool) preg_match( '/ingredientes|al[eé]rgeno|trazas/i', $plain );
						$add( $allergen ? 'alta' : 'media', $p, 'Descripción repetida', sprintf( 'Idéntica a la de #%d%s.', $seen[ $fp ], $allergen ? ' (lleva ingredientes o alérgenos: revisar con el fabricante)' : '' ) );
					} else {
						$seen[ $fp ] = $p->get_id();
					}
				}
				if ( '' === $p->get_sku() ) {
					$add( 'baja', $p, 'Sin SKU', 'Sin referencia interna.' );
				}
				if ( '' === (string) $p->get_weight() ) {
					$add( 'media', $p, 'Sin peso', 'Sin peso: no se puede calcular envío por peso ni etiquetas.' );
				}
				if ( 'outofstock' === $p->get_stock_status() ) {
					$add( 'media', $p, 'Agotado y publicado', 'Sigue visible en la tienda estando agotado.' );
				}
				if ( 'visible' !== $p->get_catalog_visibility() ) {
					$add( 'baja', $p, 'Oculto del catálogo', 'Publicado pero oculto en tienda y búsqueda.' );
				}
				if ( $this->slug_mismatch( $post ) ) {
					$add( 'media', $p, 'URL que no corresponde', 'La URL «' . $post->post_name . '» no se parece al nombre.' );
				}
				if ( preg_match( '/&amp;|(?<=\s|^)NEW!?(?=\s|$)|mamgo|badeja|espicanas/iu', $name ) ) {
					$add( 'media', $p, 'Título con ruido', 'Entidades HTML, «NEW» o erratas en el título.' );
				}
				if ( mpc_is_shouting( $name ) ) {
					$add( 'baja', $p, 'Título en mayúsculas', 'Se muestra normalizado en la web; el dato guardado sigue en mayúsculas.' );
				}
				$cat = (int) current( $p->get_category_ids() );
				if ( isset( $tax_by[ $cat ] ) && count( $tax_by[ $cat ] ) > 1 ) {
					$majority = '';
					$max      = 0;
					foreach ( $tax_by[ $cat ] as $class => $ids ) {
						if ( count( $ids ) > $max ) {
							$max      = count( $ids );
							$majority = $class;
						}
					}
					if ( $p->get_tax_class() !== $majority && $max >= 3 ) {
						$add( 'alta', $p, 'Impuesto distinto al de su categoría', sprintf( 'Clase «%s» frente a «%s» en la mayoría: confirmar con la gestoría.', $p->get_tax_class() ? $p->get_tax_class() : 'estándar', $majority ? $majority : 'estándar' ) );
					}
				}
			}
			$order = array(
				'alta'  => 0,
				'media' => 1,
				'baja'  => 2,
			);
			usort(
				$rows,
				static function ( $a, $b ) use ( $order ) {
					return array( $order[ $a['gravedad'] ], $a['id'] ) <=> array( $order[ $b['gravedad'] ], $b['id'] );
				}
			);
			return $rows;
		}
	}

	\WP_CLI::add_command( 'mpc products', 'MPC_Products_Command' );
}
