<?php
/**
 * Cada sabor del pack apunta a su hamburguesa: la imagen de ingredientes, peso y valores nutricionales
 * que ya está en la descripción de ese producto (así no se duplican datos).
 * Clave = nombre del sabor normalizado (minúsculas, sin tildes, «&» = «y»), igual que en el JS.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Correspondencia entre el nombre normalizado de cada sabor y el ID de su hamburguesa.
 *
 * @return mixed Resultado.
 */
function mpc_flavour_products() {
	$base = array(
		'chorizo'                       => 2499,
		'barbacoa'                      => 5098,
		'potro trufa'                   => 13621,
		'potro'                         => 2458,
		'espinacas y queso'             => 2427,
		'albondigas'                    => 2500,
		'pollo de corral'               => 2497,
		'miel y tomate'                 => 43882,
		'miel con tomate'               => 43882,
		'miel y mostaza'                => 43882,
		'ternera a la parrilla'         => 2460,
		'pollo con miel y mostaza'      => 43882,
		'mango protein chicken'         => 45578,
		'pollo con pepinillos y queso cheddar' => 42057,
		'pollo con rulo de cabra'       => 2459,
		'pizza'                         => 2455,
		'pechuga con puerro y calabaza' => 34859,
		'pollo'                         => 2462,
		'chees bacon'                   => 2457,
		'cheese bacon'                  => 2457,
		'rulo de cabra'                 => 2459,
		'ternera queso'                 => 2450,
		'ternera queso y trufa'         => 20065,
		'ternera y queso trufa'         => 20065,
		'ternera cebolla caramelizada'  => 36141,
		'ternera cebolla'               => 36141,
		'ternera'                       => 2502,
		'mexican'                       => 2461,
		'bacon'                         => 4142,
		'kebab'                         => 2501,
		'pavo cebolla'                  => 34470,
		'pavo con cebolla caramelizada' => 34470,
		'pavo'                          => 2463,
		'chicken mango'                 => 45578,
		'satay'                         => 2498,
		'parrilla'                      => 2460,
		'pepinillo con queso cheddar'   => 42057,
	);
	foreach ( function_exists( 'mpc_pack_names' ) ? mpc_pack_names() : array() as $pid => $pname ) {
		$base[ mpc_flavour_key( $pname ) ] = (int) $pid;
	}
	return apply_filters( 'mpc_flavour_products', $base );
}

/**
 * Grupo de un sabor según la subcategoría de su producto (Pollo, Ternera, Pavo, Potro, Albóndigas).
 * Así un sabor nuevo cae en su grupo con solo asignarle la subcategoría, sin tocar código.
 *
 * @param int $product_id ID de producto.
 * @return string Etiqueta del grupo o cadena vacía.
 */
function mpc_flavour_group( $product_id ) {
	$parent = get_term_by( 'slug', 'hamburguesas', 'product_cat' );
	if ( ! $parent ) {
		return '';
	}
	foreach ( (array) wp_get_post_terms( $product_id, 'product_cat' ) as $t ) {
		if ( (int) $t->parent === (int) $parent->term_id ) {
			$label = trim( preg_replace( '/^Hamburguesas?\s+(de\s+)?/iu', '', $t->name ) );
			return mb_strtoupper( mb_substr( $label, 0, 1 ) ) . mb_substr( $label, 1 );
		}
	}
	return '';
}

/** Datos para el JS: título, imagen de información y enlace de cada sabor. */
function mpc_flavours_data() {
	$out = array();
	foreach ( mpc_flavour_products() as $key => $id ) {
		$p = get_post( $id );
		if ( ! $p || 'publish' !== $p->post_status ) {
			continue;
		}
		$m = array( '', '' );
		preg_match( '/<img[^>]+src="([^"]+)"/i', $p->post_content, $m );
		$out[ $key ]      = array(
			't' => html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' ),
			'i' => esc_url_raw( $m[1] ),
			'u' => get_permalink( $id ),
		);
		$avail            = mpc_available_trays( $id );
		$out[ $key ]['s'] = ( null === $avail || $avail > 0 ) ? 1 : 0;
		$out[ $key ]['m'] = null === $avail ? 99 : (int) $avail;
		$grp              = mpc_flavour_group( $id );
		if ( $grp ) {
			$out[ $key ]['g'] = $grp;
		}
		$nut              = mpc_flavour_nutrition( $id );
		if ( $nut ) {
			$out[ $key ]['d'] = $nut;
		}
	}
	return $out;
}

/**
 * Ingredientes y valores nutricionales de cada hamburguesa, transcritos de las etiquetas que hay en
 * la descripción de cada producto (por 100 g; cada hamburguesa pesa 100 g). Se editan en data/flavours.json.
 */
function mpc_nutrition_data() {
	static $data = null;
	if ( null === $data ) {
		$file = MPC_DIR . 'data/flavours.json';
		$data = is_readable( $file ) ? wp_json_file_decode( $file, array( 'associative' => true ) ) : array();
		$data = is_array( $data ) ? $data : array();
		$live = get_transient( 'mpc_nutri_cache' );
		if ( false === $live ) {
			$live = array();
			$ids  = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_mp_nutrition', 'meta_compare' => 'EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			foreach ( $ids as $id ) {
				$d = function_exists( 'mpc_get_label_data' ) ? mpc_get_label_data( $id ) : array();
				if ( empty( $d['ingredientes'] ) || ! isset( $d['proteinas'] ) ) {
					continue;
				}
				$pub = ! empty( $d['energia_publicable'] );
				$f   = function ( $k ) use ( $d ) {
					return isset( $d[ $k ] ) && null !== $d[ $k ] ? str_replace( '.', ',', (string) (float) $d[ $k ] ) : '';
				};
				$live[ (string) $id ] = array(
					'ing'  => $d['ingredientes'] . ( ! empty( $d['trazas'] ) ? ' (Puede contener trazas de gluten)' : '' ),
					'kj'   => $pub ? $f( 'kj' ) : '',
					'kcal' => $pub ? $f( 'kcal' ) : '',
					'fat'  => $f( 'grasas' ),
					'sat'  => $f( 'saturadas' ),
					'carb' => $f( 'hidratos' ),
					'sug'  => $f( 'azucares' ),
					'prot' => $f( 'proteinas' ),
					'salt' => $f( 'sal' ),
					'peso' => has_term( 'albondigas-proteicas', 'product_cat', $id ) ? '400 g/bandeja' : '100 g/unidad · 400 g/bandeja de 4 unidades',
				);
			}
			set_transient( 'mpc_nutri_cache', $live, DAY_IN_SECONDS );
		}
		$data = array_replace( $data, $live );
	}
	return $data;
}

/**
 * Datos nutricionales de un producto.
 *
 * @param int $product_id ID de producto.
 * @return mixed Resultado.
 */
function mpc_flavour_nutrition( $product_id ) {
	$all = mpc_nutrition_data();
	return $all[ (string) $product_id ] ?? null;
}

/**
 * Convierte «22,2» en 22.2.
 *
 * @param string $s Texto con un número.
 * @return float
 */
function mpc_num( $s ) {
	return (float) str_replace( ',', '.', (string) $s );
}

/** Proteína máxima por hamburguesa entre los sabores con datos, redondeada hacia abajo. */
function mpc_max_protein() {
	$max = 0.0;
	foreach ( mpc_nutrition_data() as $d ) {
		$max = max( $max, mpc_num( $d['prot'] ?? 0 ) );
	}
	return (int) floor( $max );
}

/**
 * Nombre corto para la tabla nutricional.
 *
 * @param string $title Título.
 * @return string
 */
function mpc_table_name( $title ) {
	$t = preg_replace( '/\s*NEW!?$/u', '', $title );
	$t = preg_replace( '/^Hamburguesa\s+(de\s+sabor\s+|de\s+|sabor\s+)?/iu', '', $t );
	$t = trim( $t );
	return mb_strtoupper( mb_substr( $t, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $t, 1, null, 'UTF-8' );
}

/** Tabla de valores nutricionales de todos los sabores con datos (por 100 g). */
function mpc_nutrition_table_html() {
	$rows = array();
	$seen = array();
	foreach ( mpc_flavour_products() as $id ) {
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$d           = mpc_flavour_nutrition( $id );
		if ( ! $d ) {
			continue;
		}
		$rows[ $id ] = array( html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' ), $d );
	}
	if ( ! $rows ) {
		return '';
	}
	uasort(
		$rows,
		function ( $a, $b ) {
			return mpc_num( $b[1]['prot'] ) <=> mpc_num( $a[1]['prot'] );
		}
	);
	$h = '<div class="mpc-table-wrap"><table class="mpc-table"><thead><tr><th>Hamburguesa</th><th>Kcal</th><th>Proteínas</th><th>Grasas</th><th>Hidratos</th><th>Sal</th></tr></thead><tbody>';
	foreach ( $rows as $id => $r ) {
		list( $title, $d ) = $r;
		$h                .= '<tr><th scope="row">' . esc_html( mpc_table_name( $title ) ) . '</th><td>' . ( '' === (string) $d['kcal'] ? '&ndash;' : esc_html( $d['kcal'] ) ) . '</td><td>' . esc_html( $d['prot'] ) . ' g</td><td>' . esc_html( $d['fat'] ) . ' g</td><td>' . esc_html( $d['carb'] ) . ' g</td><td>' . esc_html( $d['salt'] ) . ' g</td></tr>';
	}
	return $h . '</tbody></table></div><p class="mpc-table__note">Valores por 100 g. Cada hamburguesa pesa 100 g y cada bandeja trae 4. Las albóndigas vienen en bandeja de 400 g.</p>';
}
