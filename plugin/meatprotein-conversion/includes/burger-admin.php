<?php
/**
 * Edición de las hamburguesas desde el administrador: cuadro «Etiqueta y pack» en cada ficha,
 * generación automática de la descripción, sellos, SEO y llms.txt, y sincronización de los
 * sabores del pack. Sustituye a los scripts que se usaron para cargar las etiquetas.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Formatea un número con coma decimal («22.2» => «22,2»). */
function mpc_fmt( $v ) {
	return str_replace( '.', ',', (string) (float) $v );
}

/** Lee un número escrito con coma o punto; vacío = null. */
function mpc_parse_num( $s ) {
	$s = trim( str_replace( ',', '.', (string) $s ) );
	return '' === $s ? null : (float) $s;
}

/** Alérgenos que se reconocen por las palabras en MAYÚSCULAS de los ingredientes. */
function mpc_allergen_words() {
	return array(
		'HUEVO'          => 'Huevo',
		'LECHE'          => 'Leche',
		'LACTOSA'        => 'Leche (lactosa)',
		'TRIGO'          => 'Trigo (gluten)',
		'GLUTEN'         => 'Gluten',
		'MOSTAZA'        => 'Mostaza',
		'SOJA'           => 'Soja',
		'PESCADO'        => 'Pescado',
		'APIO'           => 'Apio',
		'SÉSAMO'         => 'Sésamo',
		'SESAMO'         => 'Sésamo',
		'ALTRAMUCES'     => 'Altramuces',
		'CACAHUETES'     => 'Cacahuetes',
		'ALMENDRAS'      => 'Frutos de cáscara',
		'AVELLANAS'      => 'Frutos de cáscara',
		'NUECES'         => 'Frutos de cáscara',
		'CRUSTÁCEOS'     => 'Crustáceos',
		'MOLUSCOS'       => 'Moluscos',
		'SULFITOS'       => 'Sulfitos',
	);
}

/**
 * Alérgenos de una lista de ingredientes: las palabras escritas en MAYÚSCULAS, en orden de aparición.
 *
 * @param string $ing Ingredientes.
 * @return string[]
 */
function mpc_allergens_from( $ing ) {
	$words = mpc_allergen_words();
	$out   = array();
	if ( preg_match_all( '/\b([A-ZÁÉÍÓÚÑ]{4,})\b/u', (string) $ing, $m ) ) {
		foreach ( $m[1] as $w ) {
			if ( isset( $words[ $w ] ) && ! in_array( $words[ $w ], $out, true ) ) {
				$out[] = $words[ $w ];
			}
		}
	}
	if ( in_array( 'Leche', $out, true ) ) {
		$out = array_values( array_diff( $out, array( 'Leche (lactosa)' ) ) );
	}
	return $out;
}

/** Datos de etiqueta guardados de un producto (o un esquema vacío). */
function mpc_get_label_data( $post_id ) {
	$raw = get_post_meta( $post_id, '_mp_nutrition', true );
	$d   = $raw ? json_decode( $raw, true ) : array();
	return is_array( $d ) ? $d : array();
}

/**
 * Comprobaciones de coherencia de una etiqueta.
 *
 * @param array $d Datos.
 * @return string[] Avisos.
 */
function mpc_check_label( $d ) {
	$w = array();
	if ( empty( $d['ingredientes'] ) ) {
		$w[] = 'Faltan los ingredientes.';
	}
	foreach ( array( 'grasas', 'saturadas', 'hidratos', 'azucares', 'proteinas', 'sal' ) as $k ) {
		if ( ! isset( $d[ $k ] ) || null === $d[ $k ] ) {
			$w[] = 'Falta un valor de la tabla nutricional: ' . $k . '.';
		}
	}
	if ( $w ) {
		return $w;
	}
	if ( $d['saturadas'] > $d['grasas'] ) {
		$w[] = 'Las grasas saturadas no pueden superar a las grasas.';
	}
	if ( $d['azucares'] > $d['hidratos'] ) {
		$w[] = 'Los azúcares no pueden superar a los hidratos de carbono.';
	}
	if ( $d['proteinas'] + $d['grasas'] + $d['hidratos'] > 100 ) {
		$w[] = 'Proteínas + grasas + hidratos superan los 100 g.';
	}
	$calc = $d['proteinas'] * 4 + $d['grasas'] * 9 + $d['hidratos'] * 4;
	if ( ! empty( $d['kcal'] ) && abs( $calc - $d['kcal'] ) / $d['kcal'] > 0.06 ) {
		$w[] = 'Las kcal (' . mpc_fmt( $d['kcal'] ) . ') no cuadran con los macronutrientes (salen unas ' . round( $calc ) . '). Revisa la etiqueta o desmarca «Publicar la energía».';
	}
	if ( ! empty( $d['kj'] ) && ! empty( $d['kcal'] ) && abs( $d['kj'] / 4.184 - $d['kcal'] ) / $d['kcal'] > 0.03 ) {
		$w[] = 'Los kJ y las kcal no son equivalentes (1 kcal = 4,184 kJ).';
	}
	return $w;
}

/**
 * Genera descripción, extracto, meta descripción y JSON de una hamburguesa a partir de su etiqueta.
 *
 * @param WP_Post $post Producto.
 * @param array   $d    Datos de etiqueta (ingredientes, alergenos, trazas, kj, kcal, grasas, saturadas, hidratos, azucares, proteinas, sal, energia_publicable).
 * @return array content, excerpt, desc.
 */
function mpc_render_burger( $post, $d ) {
	$base  = home_url();
	$title = $post->post_title;
	$is_alb = has_term( 'albondigas-proteicas', 'product_cat', $post->ID );
	$pub   = ! empty( $d['energia_publicable'] );
	$low   = $d['grasas'] <= 3;
	$en    = $d['proteinas'] * 4 + $d['grasas'] * 9 + $d['hidratos'] * 4;
	$high  = $en > 0 && ( $d['proteinas'] * 4 / $en ) >= 0.20; // Alto contenido en proteínas: al menos el 20 % de la energía.
	$ids   = get_option( 'mp_sellos_ids' );
	$seal  = array(
		'alto' => is_array( $ids ) && ! empty( $ids['alto'] ) ? wp_get_attachment_url( $ids['alto'] ) : '',
		'bajo' => is_array( $ids ) && ! empty( $ids['bajo'] ) ? wp_get_attachment_url( $ids['bajo'] ) : '',
	);

	preg_match_all( '/<p>\s*(<img\b[^>]*>)\s*<\/p>/i', $post->post_content, $m );
	$imgs = '';
	foreach ( $m[1] as $img ) {
		$imgs .= '<p>' . $img . "</p>\n";
	}

	$ing = preg_replace( '/\b([A-ZÁÉÍÓÚÑ]{4,})\b/u', '<strong>$1</strong>', esc_html( $d['ingredientes'] ) );
	$al  = array_map( 'mb_strtolower', (array) $d['alergenos'] );
	$gl  = ! empty( $d['trazas'] );

	$rows = '';
	if ( $pub ) {
		$rows .= '<tr><th scope="row">Valor energético</th><td>' . ( ! empty( $d['kj'] ) ? mpc_fmt( $d['kj'] ) . ' kJ / ' : '' ) . mpc_fmt( $d['kcal'] ) . ' kcal</td></tr>';
	}
	foreach ( array(
		array( 'Grasas', 'grasas' ),
		array( 'de las cuales saturadas', 'saturadas' ),
		array( 'Hidratos de carbono', 'hidratos' ),
		array( 'de los cuales azúcares', 'azucares' ),
		array( 'Proteínas', 'proteinas' ),
		array( 'Sal', 'sal' ),
	) as $r ) {
		$rows .= '<tr><th scope="row">' . $r[0] . '</th><td>' . mpc_fmt( $d[ $r[1] ] ) . ' g</td></tr>';
	}

	$claim = mpc_fmt( $d['proteinas'] ) . ' g de proteína por cada 100 g';
	if ( $pub ) {
		$claim .= ( $d['kcal'] <= 130 ? ' y solo ' : ' y ' ) . mpc_fmt( $d['kcal'] ) . ' kcal';
	}
	$parts = array();
	if ( $high ) {
		$parts[] = 'alto contenido en proteínas';
	}
	if ( $low ) {
		$parts[] = 'bajo en grasa (' . mpc_fmt( $d['grasas'] ) . ' g)';
	}
	$claim .= ( $parts ? ': ' . implode( ' y ', $parts ) : '' ) . '.';
	if ( ! preg_match( '/achiote|colorante|conservante|sulfito|\bE-?\d{3}\b/iu', $d['ingredientes'] ) ) {
		$claim .= ' Sin sulfitos, colorantes ni conservantes añadidos.';
	}

	$fmt  = $is_alb ? 'bandeja de 400 g de albóndigas proteicas frescas' : 'bandeja de 400 g con 4 hamburguesas de 100 g, frescas y proteicas';
	$html = '<p><strong>' . esc_html( $title ) . '</strong> de Meatprotein: ' . $fmt . ".</p>\n";
	$html .= '<p class="mp-claims">' . esc_html( $claim ) . "</p>\n";
	$html .= "<h2>Ingredientes y alérgenos</h2>\n<p><strong>Ingredientes:</strong> " . $ing . "</p>\n";
	$html .= '<p><strong>Alérgenos:</strong> ' . ( $al ? 'contiene ' . esc_html( mpc_list_es( $al ) ) . '.' : 'no contiene alérgenos de declaración obligatoria.' ) . ( $gl ? ' Puede contener trazas de gluten.' : '' ) . "</p>\n";
	$html .= "<h2>Información nutricional por 100 g</h2>\n<table class=\"mp-nutri\"><tbody>" . $rows . "</tbody></table>\n";
	$html .= '<p>Peso neto: ' . ( $is_alb ? '400 g por bandeja.' : '100 g por unidad, 400 g por bandeja (4 unidades).' ) . "</p>\n";
	$html .= "<h2>Cómo la recibes</h2>\n<ul>\n<li>Producto fresco, enviado con transporte refrigerado (SEUR).</li>\n<li>Entrega antes de las 14:00 en Península y Portugal. Haz tu pedido antes de las 13:00 para el envío según el calendario habitual.</li>\n";
	$html .= '<li>Envío gratis a la Península con el <a href="' . esc_url( $base . '/producto/pack-10-bandejas-hamburguesas/' ) . "\">pack de 10 bandejas</a>.</li>\n</ul>\n";
	$html .= '<p>Más detalles en <a href="' . esc_url( $base . '/envios-y-devoluciones/' ) . '">Envíos y devoluciones</a> y en <a href="' . esc_url( $base . '/controles-de-calidad/' ) . '">Controles de calidad</a>.</p>' . "\n" . $imgs;

	$core    = $pub ? mpc_fmt( $d['proteinas'] ) . ' g de proteína y ' . mpc_fmt( $d['kcal'] ) . ' kcal por 100 g' : mpc_fmt( $d['proteinas'] ) . ' g de proteína por 100 g';
	$excerpt = ( $is_alb ? "$title: bandeja de 400 g de albóndigas proteicas con $core." : "$title: bandeja de 400 g (4 x 100 g) con $core." ) . ' Entrega refrigerada antes de las 14:00 en Península y Portugal.';
	if ( ( $high && $seal['alto'] ) || ( $low && $seal['bajo'] ) ) {
		$excerpt .= "\n" . '<p class="mp-sellos">' . ( $high && $seal['alto'] ? '<img src="' . esc_url( $seal['alto'] ) . '" alt="Alto contenido en proteínas" width="68" height="64" loading="lazy" />' : '' )
			. ( $low && $seal['bajo'] ? '<img src="' . esc_url( $seal['bajo'] ) . '" alt="Bajo en grasa" width="92" height="64" loading="lazy" />' : '' ) . '</p>';
	}
	$desc = "$title: $core. Bandeja de 400 g (4 x 100 g). Entrega refrigerada antes de las 14:00 en Península y Portugal.";
	foreach ( array( "$title: $core. Bandeja de 400 g (4 x 100 g). Entrega refrigerada antes de las 14:00.", "$title: $core. Bandeja de 400 g. Entrega refrigerada antes de las 14:00." ) as $c ) {
		if ( mb_strlen( $desc ) <= 160 ) {
			break;
		}
		$desc = $c;
	}
	if ( $is_alb ) {
		$desc = str_replace( ' (4 x 100 g)', '', $desc );
	}
	return array(
		'content' => $html,
		'excerpt' => $excerpt,
		'desc'    => $desc,
	);
}

/** Lista «a, b y c». */
function mpc_list_es( array $a ) {
	if ( count( $a ) < 2 ) {
		return implode( '', $a );
	}
	$last = array_pop( $a );
	return implode( ', ', $a ) . ' y ' . $last;
}

/**
 * Guarda la etiqueta de un producto y regenera su ficha.
 *
 * @param int   $post_id Producto.
 * @param array $d       Datos de etiqueta (los alérgenos se calculan aquí).
 * @return string[] Avisos de coherencia.
 */
function mpc_apply_label( $post_id, array $d ) {
	$post         = get_post( $post_id );
	$d['alergenos'] = mpc_allergens_from( $d['ingredientes'] );
	$warn         = mpc_check_label( $d );
	foreach ( array( 'grasas', 'saturadas', 'hidratos', 'azucares', 'proteinas', 'sal' ) as $k ) {
		if ( ! isset( $d[ $k ] ) || null === $d[ $k ] ) {
			return $warn; // Incompleta: no se toca la ficha.
		}
	}
	$d['peso_unidad_g']  = 100;
	$d['peso_bandeja_g'] = 400;
	$d['unidades']       = 4;
	update_post_meta( $post_id, '_mp_nutrition', wp_slash( wp_json_encode( $d, JSON_UNESCAPED_UNICODE ) ) );
	$r = mpc_render_burger( $post, $d );
	remove_action( 'save_post_product', 'mpc_save_burger_box', 20 );
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $r['content'] ),
			'post_excerpt' => wp_slash( $r['excerpt'] ),
		)
	);
	add_action( 'save_post_product', 'mpc_save_burger_box', 20 );
	update_post_meta( $post_id, 'rank_math_description', $r['desc'] );
	if ( ! get_post_meta( $post_id, 'rank_math_title', true ) ) {
		update_post_meta( $post_id, 'rank_math_title', $post->post_title . ' | Meatprotein' );
	}
	if ( ! get_post_meta( $post_id, 'rank_math_focus_keyword', true ) ) {
		update_post_meta( $post_id, 'rank_math_focus_keyword', mb_strtolower( $post->post_title ) );
	}
	return $warn;
}

/* ------------------------------------------------------------------ Cuadro en la ficha */

add_action(
	'add_meta_boxes_product',
	function () {
		global $post;
		$pr = $post ? wc_get_product( $post->ID ) : null;
		if ( $pr && mpc_is_pack( $pr ) ) {
			return; // El pack no tiene etiqueta: su cuadro es «Regalo del pack».
		}
		add_meta_box( 'mpc_burger', 'Meatprotein: etiqueta y pack', 'mpc_burger_box', 'product', 'normal', 'high' );
	}
);

/**
 * Pinta el cuadro «Etiqueta y pack».
 *
 * @param WP_Post $post Producto.
 */
function mpc_burger_box( $post ) {
	wp_nonce_field( 'mpc_burger_box', 'mpc_burger_nonce' );
	$d    = mpc_get_label_data( $post->ID );
	$name = get_post_meta( $post->ID, '_mpc_pack_name', true );
	$g    = function ( $k ) use ( $d ) {
		return isset( $d[ $k ] ) && null !== $d[ $k ] ? mpc_fmt( $d[ $k ] ) : '';
	};
	$pub  = ! isset( $d['energia_publicable'] ) || ! empty( $d['energia_publicable'] );
	echo '<style>.mpc-bx label{font-weight:600}.mpc-bx .row{display:flex;flex-wrap:wrap;gap:12px;margin:8px 0}.mpc-bx .row>div{min-width:130px}.mpc-bx input[type=text]{width:100%}.mpc-bx .help{color:#646970;font-size:12px;margin:2px 0 0}.mpc-bx h4{margin:14px 0 4px}</style><div class="mpc-bx">';
	echo '<p class="help">Rellena este cuadro con lo que dice la etiqueta. Al guardar, la ficha (descripción, tabla nutricional, sellos, SEO y datos para Google) se genera sola. <strong>No edites a mano la descripción:</strong> se sobrescribe. Las imágenes que añadas a la descripción se conservan.</p>';
	echo '<h4>Pack</h4><div class="row"><div style="flex:1"><label for="mpc_pack_name">Nombre en el pack</label><input type="text" id="mpc_pack_name" name="mpc_pack_name" value="' . esc_attr( $name ) . '" placeholder="Por ejemplo: Pollo con arándanos"><p class="help">Así se llama este sabor en el selector del pack. Déjalo vacío si el producto no es un sabor del pack. El tipo de carne (grupo en el selector) sale de la <strong>subcategoría</strong> del producto: Hamburguesas de pollo, de ternera, de pavo, de potro o Albóndigas.</p></div></div>';
	echo '<h4>Ingredientes y alérgenos</h4><div class="row"><div style="flex:1"><label for="mpc_ing">Ingredientes (tal como figuran en la etiqueta)</label><textarea id="mpc_ing" name="mpc_ing" rows="4" style="width:100%">' . esc_textarea( $d['ingredientes'] ?? '' ) . '</textarea><p class="help">Escribe <strong>EN MAYÚSCULAS</strong> los alérgenos, como en la etiqueta (HUEVO, LECHE, TRIGO, MOSTAZA, SOJA...). Con eso se calcula solo la frase «Alérgenos: contiene…».</p></div></div>';
	echo '<div class="row"><div><label><input type="checkbox" name="mpc_trazas" value="1"' . checked( ! empty( $d['trazas'] ), true, false ) . '> La etiqueta dice «puede contener trazas de gluten»</label></div></div>';
	if ( ! empty( $d['alergenos'] ) ) {
		echo '<p class="help">Alérgenos detectados ahora: <strong>' . esc_html( implode( ', ', $d['alergenos'] ) ) . '</strong></p>';
	}
	echo '<h4>Información nutricional por 100 g</h4><div class="row">';
	foreach ( array(
		'kj'        => 'Valor energético (kJ)',
		'kcal'      => 'Valor energético (kcal)',
		'grasas'    => 'Grasas (g)',
		'saturadas' => 'de las cuales saturadas (g)',
		'hidratos'  => 'Hidratos de carbono (g)',
		'azucares'  => 'de los cuales azúcares (g)',
		'proteinas' => 'Proteínas (g)',
		'sal'       => 'Sal (g)',
	) as $k => $label ) {
		echo '<div><label for="mpc_n_' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label><input type="text" inputmode="decimal" id="mpc_n_' . esc_attr( $k ) . '" name="mpc_n[' . esc_attr( $k ) . ']" value="' . esc_attr( $g( $k ) ) . '"></div>';
	}
	echo '</div><div class="row"><div><label><input type="checkbox" name="mpc_pub" value="1"' . checked( $pub, true, false ) . '> Publicar la energía (kJ y kcal)</label><p class="help">Desmárcalo si los kJ/kcal de la etiqueta no cuadran con el resto, hasta que se corrijan.</p></div></div>';
	echo '<div class="row"><div><label><input type="checkbox" name="mpc_regen" value="1" checked> Regenerar la ficha al guardar</label><p class="help">Descripción, extracto, sellos, meta descripción de Google y archivo llms.txt.</p></div></div></div>';
}

/**
 * Guarda el cuadro y regenera la ficha.
 *
 * @param int $post_id Producto.
 */
function mpc_save_burger_box( $post_id ) {
	$pr = wc_get_product( $post_id );
	if ( $pr && mpc_is_pack( $pr ) ) {
		return;
	}
	if ( ! isset( $_POST['mpc_burger_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mpc_burger_nonce'] ) ), 'mpc_burger_box' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) || 'product' !== get_post_type( $post_id ) ) {
		return;
	}
	$name = isset( $_POST['mpc_pack_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['mpc_pack_name'] ) ) ) : '';
	if ( '' === $name ) {
		delete_post_meta( $post_id, '_mpc_pack_name' );
	} else {
		update_post_meta( $post_id, '_mpc_pack_name', $name );
	}
	delete_transient( 'mpc_pack_names' );
	delete_transient( 'mpc_nutri_cache' );
	$ing = isset( $_POST['mpc_ing'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['mpc_ing'] ) ) ) : '';
	$n   = isset( $_POST['mpc_n'] ) && is_array( $_POST['mpc_n'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mpc_n'] ) ) : array();
	if ( '' === $ing && ! array_filter( $n, 'strlen' ) ) {
		return; // Producto sin etiqueta (no es una hamburguesa o aún no se ha rellenado).
	}
	$d = array(
		'ingredientes'       => $ing,
		'trazas'             => ! empty( $_POST['mpc_trazas'] ) ? 'gluten' : '',
		'energia_publicable' => ! empty( $_POST['mpc_pub'] ),
	);
	foreach ( array( 'kj', 'kcal', 'grasas', 'saturadas', 'hidratos', 'azucares', 'proteinas', 'sal' ) as $k ) {
		$d[ $k ] = mpc_parse_num( $n[ $k ] ?? '' );
	}
	$d['alergenos'] = mpc_allergens_from( $ing );
	$warn           = mpc_check_label( $d );
	if ( ! empty( $_POST['mpc_regen'] ) ) {
		$warn = mpc_apply_label( $post_id, $d );
		mpc_write_llms();
	} else {
		$d['peso_unidad_g']  = 100;
		$d['peso_bandeja_g'] = 400;
		$d['unidades']       = 4;
		update_post_meta( $post_id, '_mp_nutrition', wp_slash( wp_json_encode( $d, JSON_UNESCAPED_UNICODE ) ) );
	}
	delete_transient( 'mpc_nutri_cache' );
	set_transient( 'mpc_notice_' . get_current_user_id(), $warn ? $warn : array( 'OK' ), 120 );
}
add_action( 'save_post_product', 'mpc_save_burger_box', 20 );

add_action(
	'admin_notices',
	function () {
		$msgs = get_transient( 'mpc_notice_' . get_current_user_id() );
		if ( ! $msgs ) {
			return;
		}
		delete_transient( 'mpc_notice_' . get_current_user_id() );
		if ( array( 'OK' ) === $msgs ) {
			echo '<div class="notice notice-success is-dismissible"><p>Etiqueta guardada y ficha regenerada.</p></div>';
			return;
		}
		if ( 1 === count( $msgs ) && ( 0 === strpos( (string) $msgs[0], 'Pack guardado' ) || 0 === strpos( (string) $msgs[0], 'Sabores del pack' ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[0] ) . '</p></div>';
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Revisa la etiqueta:</strong></p><ul style="list-style:disc;margin-left:20px">';
		foreach ( $msgs as $m ) {
			echo '<li>' . esc_html( $m ) . '</li>';
		}
		echo '</ul></div>';
	}
);

/* ------------------------------------------------------------------ llms.txt */

/** Reescribe /llms.txt con las hamburguesas publicadas por tipo de carne. */
function mpc_write_llms() {
	$base = home_url();
	$L    = "# Meatprotein\n\n> Tienda online española de hamburguesas, albóndigas y productos fitness altos en proteína. Bandejas frescas de 400 g (4 x 100 g) con entrega refrigerada en Península y Portugal.\n\n## Información clave\n\n";
	$L   .= "- Envío: transporte refrigerado con SEUR. Entrega antes de las 14:00 en Península y Portugal; pedidos antes de las 13:00 para el envío según el calendario habitual. No se envía a Baleares, Canarias, Ceuta ni Melilla.\n- Envío gratis a la Península con el pack de 10 bandejas.\n- Pago con tarjeta, Bizum, Apple Pay, Google Pay y PayPal.\n- Formato de las hamburguesas: bandeja de 400 g con 4 hamburguesas de 100 g.\n";
	$L   .= "- Calidad: todas las hamburguesas y albóndigas son de alto contenido en proteínas (más del 20 % de la energía procede de las proteínas) y las que llevan el sello \"Bajo en grasa\" tienen 3 g de grasa o menos por 100 g. Sin sulfitos, colorantes ni conservantes añadidos, salvo indicación en la ficha.\n";
	$L   .= "- Cada ficha de producto incluye ingredientes, alérgenos e información nutricional por 100 g, tal como figura en la etiqueta.\n\n";
	$L   .= "## Packs\n\n- [Pack de 10 bandejas de hamburguesas, envío gratis](" . $base . "/producto/pack-10-bandejas-hamburguesas/): elige 10 bandejas entre todos los sabores y recibe una de regalo.\n\n## Hamburguesas y albóndigas por tipo de carne (proteína y energía por 100 g)\n";
	$parent = get_term_by( 'slug', 'hamburguesas', 'product_cat' );
	$kids   = $parent ? get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $parent->term_id, 'hide_empty' => true ) ) : array();
	foreach ( (array) $kids as $term ) {
		$L  .= "\n### " . $term->name . "\n\n";
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term->term_id, 'include_children' => false ) ) ) );
		foreach ( $ids as $pid ) {
			$n = mpc_get_label_data( $pid );
			$x = '';
			if ( $n && isset( $n['proteinas'] ) ) {
				$x = ': ' . mpc_fmt( $n['proteinas'] ) . ' g de proteína' . ( ! empty( $n['energia_publicable'] ) ? ', ' . mpc_fmt( $n['kcal'] ) . ' kcal' : '' );
			}
			$L .= '- [' . get_the_title( $pid ) . '](' . get_permalink( $pid ) . ')' . $x . "\n";
		}
	}
	$L .= "\n## Otras categorías\n\n";
	foreach ( array( 'pizza-fitness', 'pan', 'salsas-y-cremas', 'snacks-y-bebidas', 'pasta-konjac', 'suplementacion', 'queso' ) as $s ) {
		$t = get_term_by( 'slug', $s, 'product_cat' );
		if ( $t ) {
			$L .= '- [' . $t->name . '](' . get_term_link( $t ) . ")\n";
		}
	}
	$L .= "\n## Información\n\n- [Envíos y devoluciones](" . $base . "/envios-y-devoluciones/)\n- [Controles de calidad](" . $base . "/controles-de-calidad/)\n- [Preguntas frecuentes](" . $base . "/faqs/)\n- [Contacto](" . $base . "/contacto/)\n";
	@file_put_contents( ABSPATH . 'llms.txt', $L ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP_Filesystem.FileSystemWritesDisallowed
}

/* ------------------------------------------------------------------ Sabores del pack */

/** Nombres de pack definidos en las fichas: id => nombre (solo publicados). */
function mpc_pack_names() {
	$c = get_transient( 'mpc_pack_names' );
	if ( false === $c ) {
		$c   = array();
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_mpc_pack_name', 'meta_compare' => 'EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		foreach ( $ids as $id ) {
			$n = trim( (string) get_post_meta( $id, '_mpc_pack_name', true ) );
			if ( '' !== $n ) {
				$c[ $id ] = $n;
			}
		}
		set_transient( 'mpc_pack_names', $c, DAY_IN_SECONDS );
	}
	return $c;
}

/** Productos pack (variables con atributos «Bandeja N»). */
function mpc_find_pack_ids() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'variable' ) ) ) ) as $id ) {
		if ( mpc_is_pack( wc_get_product( $id ) ) ) {
			$out[] = $id;
		}
	}
	return $out;
}

/**
 * Pone en los desplegables «Bandeja N» del pack los sabores definidos en las fichas.
 *
 * @param bool $dry Solo calcular.
 * @return array pack, names, backup.
 */
function mpc_sync_pack( $dry = false ) {
	$names = array();
	foreach ( mpc_pack_names() as $id => $name ) {
		$names[ $name ] = mpc_flavour_group( $id ) . '|' . $name;
	}
	asort( $names );
	$list  = array_keys( $names );
	$packs = mpc_find_pack_ids();
	if ( $dry || ! $packs || ! $list ) {
		return array( 'packs' => $packs, 'names' => $list );
	}
	foreach ( $packs as $pid ) {
		$meta = get_post_meta( $pid, '_product_attributes', true );
		if ( ! is_array( $meta ) ) {
			continue;
		}
		update_option( 'mpc_pack_backup_' . $pid, $meta, false );
		foreach ( $meta as $k => $a ) {
			if ( isset( $a['name'] ) && preg_match( '/^bandeja\s*\d+$/i', trim( $a['name'] ) ) ) {
				$meta[ $k ]['value'] = implode( ' | ', $list );
			}
		}
		update_post_meta( $pid, '_product_attributes', $meta );
		clean_post_cache( $pid );
		wc_delete_product_transients( $pid );
	}
	return array( 'packs' => $packs, 'names' => $list );
}

add_action(
	'admin_post_mpc_sync_pack',
	function () {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'mpc_sync_pack' ) ) {
			wp_die( 'Sin permiso.' );
		}
		$r = mpc_sync_pack();
		set_transient( 'mpc_notice_' . get_current_user_id(), array( 'Sabores del pack actualizados: ' . count( $r['names'] ) . ' opciones en ' . count( $r['packs'] ) . ' pack(s).' ), 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=mpc' ) );
		exit;
	}
);

/** Cuadro «Sabores del pack» en WooCommerce > Conversión. */
function mpc_pack_admin_box() {
	$names = mpc_pack_names();
	echo '<div class="card" style="max-width:900px;margin:16px 0;padding:12px 18px"><h2 style="margin-top:0">Sabores del pack</h2><p>Cada hamburguesa con «Nombre en el pack» (cuadro <em>Meatprotein: etiqueta y pack</em> de su ficha) y publicada es un sabor del pack. Este botón copia esa lista a los desplegables «Bandeja 1…10». Hazlo cuando añadas, renombres o retires una hamburguesa.</p>';
	if ( $names ) {
		echo '<table class="widefat striped" style="max-width:700px"><thead><tr><th>Sabor en el pack</th><th>Producto</th><th>Grupo</th></tr></thead><tbody>';
		foreach ( $names as $id => $n ) {
			echo '<tr><td>' . esc_html( $n ) . '</td><td><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></td><td>' . esc_html( mpc_flavour_group( $id ) ?: 'sin subcategoría' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p><em>Aún no hay ninguna ficha con nombre de pack.</em></p>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px"><input type="hidden" name="action" value="mpc_sync_pack">';
	wp_nonce_field( 'mpc_sync_pack' );
	submit_button( 'Actualizar los desplegables del pack', 'secondary', 'submit', false );
	echo '</form></div>';
}

/** Vacía las cachés de datos al guardar o cambiar de estado un producto. */
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( 'product' === $post->post_type ) {
			delete_transient( 'mpc_pack_names' );
			delete_transient( 'mpc_nutri_cache' );
		}
	},
	10,
	3
);

/* ------------------------------------------------------------------ Regalo del pack */

add_action(
	'add_meta_boxes_product',
	function ( $post ) {
		$p = wc_get_product( $post->ID );
		if ( $p && mpc_is_pack( $p ) ) {
			add_meta_box( 'mpc_gift', 'Meatprotein: regalo y extras del pack', 'mpc_gift_box', 'product', 'normal', 'high' );
		}
	}
);

/**
 * Cuadro «Regalo del pack»: se elige aquí (producto de la tienda) y el resto sale solo: hero, ficha, selector, carrito, pedidos y stock.
 *
 * @param WP_Post $post Pack.
 */
function mpc_gift_box( $post ) {
	wp_nonce_field( 'mpc_gift_box', 'mpc_gift_nonce' );
	$raw = trim( (string) get_post_meta( $post->ID, '_mpc_gift_ids', true ) );
	if ( '' === $raw ) {
		$raw = trim( (string) mpc_opt( 'gift_product' ) );
	}
	$ids = array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', $raw ) ) );
	echo '<p class="description">Producto que se regala con cada pack. Su stock se descuenta solo. Con más de uno, el cliente elige.</p>';
	echo '<select class="wc-product-search" multiple="multiple" style="width:100%" name="mpc_gift_ids[]" data-placeholder="Busca un producto…" data-action="woocommerce_json_search_products" data-exclude_type="variable">';
	foreach ( $ids as $id ) {
		$pr = wc_get_product( $id );
		if ( $pr ) {
			echo '<option value="' . esc_attr( $id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $pr->get_formatted_name() ) ) . '</option>';
		}
	}
	echo '</select>';
	echo '<hr><p><strong>Extras que se ofrecen al comprar el pack</strong><br><span class="description">Elige qué productos salen en cada pregunta, en el orden que los selecciones. Si dejas uno vacío, se eligen solos los más vendidos de su categoría.</span></p>';
	foreach ( array(
		'step_salsa' => '¿Quieres salsa?',
		'step_pan'   => '¿Añades pan?',
		'step_queso' => '¿Y queso?',
	) as $key => $label ) {
		$cur = array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', trim( (string) get_post_meta( $post->ID, '_mpc_' . $key, true ) ) ) ) );
		echo '<p style="margin:10px 0 2px"><label><strong>' . esc_html( $label ) . '</strong></label></p>';
		echo '<select class="wc-product-search" multiple="multiple" style="width:100%" name="mpc_' . esc_attr( $key ) . '[]" data-placeholder="Automático: los más vendidos…" data-action="woocommerce_json_search_products" data-exclude_type="variable">';
		foreach ( $cur as $id ) {
			$pr = wc_get_product( $id );
			if ( $pr ) {
				echo '<option value="' . esc_attr( $id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $pr->get_formatted_name() ) ) . '</option>';
			}
		}
		echo '</select>';
		if ( ! $cur ) {
			$steps = mpc_bundle_steps( wc_get_product( $post->ID ) );
			$k2    = str_replace( 'step_', '', $key );
			$names = ! empty( $steps[ $k2 ]['items'] ) ? array_map( function ( $pr ) {
				return mpc_nice_name( $pr->get_name() );
			}, $steps[ $k2 ]['items'] ) : array();
			echo '<p class="description" style="margin:2px 0 0">Ahora salen (automático): ' . esc_html( $names ? implode( ', ', $names ) : 'ninguno' ) . '.</p>';
		}
	}
	echo '<p class="description" style="margin-top:8px">Qué se muestra: «Incluye 1 bandeja de <em>nombre del producto</em> de regalo» en la portada y la ficha, y «Regalo: <em>nombre</em>» en carrito, pedidos y correos. El título del pack ya no hace falta que lo nombre.</p>';
}

add_action(
	'save_post_product',
	function ( $post_id ) {
		if ( ! isset( $_POST['mpc_gift_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mpc_gift_nonce'] ) ), 'mpc_gift_box' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$ids = isset( $_POST['mpc_gift_ids'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['mpc_gift_ids'] ) ) ) : array();
		update_post_meta( $post_id, '_mpc_gift_ids', implode( ',', $ids ) );
		foreach ( array( 'step_salsa', 'step_pan', 'step_queso' ) as $k ) {
			$sel = isset( $_POST[ 'mpc_' . $k ] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ 'mpc_' . $k ] ) ) ) : array();
			update_post_meta( $post_id, '_mpc_' . $k, implode( ',', $sel ) );
		}
		delete_transient( 'mpc_pack_ids' );
		$names = function ( $csv ) {
			$o = array();
			foreach ( array_filter( array_map( 'intval', explode( ',', (string) $csv ) ) ) as $i ) {
				$o[] = mpc_nice_name( get_the_title( $i ) );
			}
			return $o ? implode( ', ', $o ) : 'automático';
		};
		set_transient(
			'mpc_notice_' . get_current_user_id(),
			array(
				'Pack guardado. Regalo: ' . $names( implode( ',', $ids ) ) . '. Salsas: ' . $names( get_post_meta( $post_id, '_mpc_step_salsa', true ) ) . '. Pan: ' . $names( get_post_meta( $post_id, '_mpc_step_pan', true ) ) . '. Queso: ' . $names( get_post_meta( $post_id, '_mpc_step_queso', true ) ) . '.',
			),
			120
		);
	},
	25
);
