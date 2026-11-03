<?php
/**
 * Ficha de producto: selector de sabores (packs), garantías junto al botón de compra,
 * bloques de información al final y limpieza de textos.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Contenedor del selector de sabores: el JS lo rellena a partir de los desplegables de WooCommerce. */
add_action( 'woocommerce_before_variations_form', function () {
	global $product;
	if ( ! mpc_is_pack( $product ) ) {
		return;
	}
	echo '<div class="mpc-pack" data-mpc-pack data-flavours="' . esc_attr( wp_json_encode( mpc_flavours_data() ) ) . '" hidden></div>';
	echo '<script>document.documentElement.classList.add("mpc-has-pack");</script>';
} );

/* Datos clave del pack bajo el precio: lo que se recibe, de un vistazo. */
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! mpc_is_pack( $product ) ) {
		return;
	}
	$trays = 0;
	foreach ( $product->get_attributes() as $attr ) {
		if ( $attr->get_variation() && preg_match( '/^bandeja\s*\d+$/i', trim( $attr->get_name() ) ) ) {
			$trays++;
		}
	}
	$chips = array(
		array( 'ship', $trays . ' bandejas · ' . ( $trays * 4 ) . ' hamburguesas' ),
	);
	if ( mpc_max_protein() ) {
		$chips[] = array( 'check', 'Hasta ' . (int) mpc_max_protein() . ' g de proteína por hamburguesa' );
	}
	$chips[] = array( 'truck', 'Refrigerado · 24-48 h' );
	echo '<ul class="mpc-facts">';
	foreach ( $chips as $c ) {
		echo '<li>' . mpc_icon( $c[0] ) . '<span>' . esc_html( $c[1] ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</ul>';
}, 7 );

/* Ahorro del pack, bajo el precio. */
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$reg  = (float) $product->get_regular_price();
	$sale = (float) $product->get_price();
	if ( $product->is_type( 'variable' ) ) {
		$vars = $product->get_available_variations();
		if ( $vars ) {
			$reg  = (float) $vars[0]['display_regular_price'];
			$sale = (float) $vars[0]['display_price'];
		}
	}
	if ( $reg > $sale && $sale > 0 ) {
		printf(
			'<p class="mpc-save"><strong>Ahorras %s</strong> (%d %%) frente al precio por separado</p>',
			wp_kses_post( wc_price( $reg - $sale ) ),
			(int) round( ( $reg - $sale ) / $reg * 100 )
		);
	}
	if ( mpc_is_pack( $product ) && $sale > 0 ) {
		printf(
			'<p class="mpc-per">%s por bandeja · %s por hamburguesa</p>',
			wp_kses_post( wc_price( $sale / 10 ) ),
			wp_kses_post( wc_price( $sale / 40 ) )
		);
	}
}, 11 );

/* Garantías junto al botón de compra. */
add_action( 'woocommerce_after_add_to_cart_form', function () {
	global $product;
	$free  = (int) mpc_opt( 'free_min' );
	$ship  = (int) mpc_opt( 'ship_cost' );
	$phone = trim( mpc_opt( 'phone' ) );
	$wa    = preg_replace( '/\D+/', '', (string) mpc_opt( 'whatsapp' ) );
	$links = array_filter( array(
		'Envíos y devoluciones' => mpc_page_url( 'envios-y-devoluciones' ),
		'Preguntas frecuentes'  => mpc_page_url( 'faqs' ),
		'Controles de calidad'  => mpc_page_url( 'controles-de-calidad' ),
	) );
	?>
	<?php $cold = $product instanceof WC_Product && has_term( array( 'hamburguesas', 'pizza-fitness' ), 'product_cat', $product->get_id() ); ?>
	<p class="mpc-eta" data-mpc-eta<?php echo $cold ? ' data-cold="1"' : ''; ?> hidden><?php echo mpc_icon( 'truck' ); ?><span></span></p>
	<ul class="mpc-trust">
		<li><?php echo mpc_icon( 'truck' ); ?><span><strong><?php echo $cold ? 'Llega refrigerado con SEUR' : 'Envío con SEUR en 24-48 h'; ?></strong><?php echo $cold ? 'En 24-48 h, de lunes a jueves antes de las 14:00.' : 'Enviamos de lunes a jueves antes de las 14:00.'; ?></span></li>
		<?php if ( ! mpc_is_pack( $product ) ) : ?>
		<li><?php echo mpc_icon( 'ship' ); ?><span><strong>Envío gratis con 10 bandejas o desde <?php echo esc_html( $free ); ?> €</strong>Por debajo, <?php echo esc_html( $ship ); ?> € a toda la Península.</span></li>
		<?php endif; ?>
		<li><?php echo mpc_icon( 'shield' ); ?><span><strong>Si llega en mal estado, lo reponemos</strong>O te devolvemos el dinero del pedido.</span></li>
		<?php if ( $cold ) : ?>
		<li><?php echo mpc_icon( 'snow' ); ?><span><strong>Congélalas al recibirlas</strong>Duran 12 meses en el congelador y unas 72 h en la nevera.</span></li>
		<?php endif; ?>
	</ul>
	<?php
	if ( $product instanceof WC_Product && mpc_is_basic( $product ) ) {
		$packs = mpc_get_packs();
		if ( $packs ) {
			$pk   = $packs[0];
			$pv   = $pk->get_available_variations();
			$pp   = $pv ? (float) $pv[0]['display_price'] : (float) $pk->get_price();
			$pimg = wp_get_attachment_image_url( $pk->get_image_id(), 'woocommerce_thumbnail' );
			echo '<a class="mpc-packban" href="' . esc_url( $pk->get_permalink() ) . '">' . ( $pimg ? '<img src="' . esc_url( $pimg ) . '" alt="" loading="lazy" width="300" height="300">' : '' ) . '<span><b>¿Hamburguesas? Pack de 10 + 1 de regalo</b><span>Desde ' . wp_kses_post( wc_price( $pp ) ) . ' · envío gratis</span></span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
	?>
	<p class="mpc-trust__links">
		<?php
		$out = array();
		foreach ( $links as $label => $url ) {
			$out[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		if ( $phone ) {
			$out[] = '<a href="tel:' . esc_attr( preg_replace( '/\s+/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
		}
		if ( $wa ) {
			$out[] = '<a href="https://wa.me/' . esc_attr( $wa ) . '" rel="noopener">WhatsApp</a>';
		}
		echo implode( ' <span aria-hidden="true">·</span> ', $out ); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
	</p>
	<?php
}, 20 );

/* La descripción corta que repite el título no aporta nada. */
add_filter( 'woocommerce_short_description', function ( $desc ) {
	if ( ! is_product() ) {
		return $desc;
	}
	$norm = function ( $s ) {
		return mb_strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', wp_strip_all_tags( html_entity_decode( $s ) ) ), 'UTF-8' );
	};
	$a = $norm( $desc );
	$b = $norm( get_the_title() );
	if ( $a === $b ) {
		return '';
	}
	/* Mismo texto con erratas («badeja», «mango» / «mamgo»…): también sobra. */
	if ( '' !== $a && '' !== $b ) {
		similar_text( $a, $b, $pct );
		if ( $pct >= 85 ) {
			return '';
		}
	}
	return $desc;
} );
/* Betheme saca la descripción corta del extracto automático del contenido: si repite el título, se quita. */
add_filter( 'get_the_excerpt', function ( $excerpt, $post ) {
	if ( ! is_singular( 'product' ) || ! $post || (int) $post->ID !== (int) get_queried_object_id() ) {
		return $excerpt;
	}
	$norm = function ( $x ) {
		return mb_strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', html_entity_decode( wp_strip_all_tags( $x ) ) ), 'UTF-8' );
	};
	$a = $norm( $excerpt );
	$b = $norm( get_the_title( $post ) );
	if ( '' !== $a && '' !== $b ) {
		similar_text( $a, $b, $pct );
		if ( $pct >= 85 ) {
			return '';
		}
	}
	return $excerpt;
}, 20, 2 );
add_filter( 'wc_product_sku_enabled', function ( $on ) {
	return is_product() ? false : $on;
} );

/* Bloques de información al final de la descripción. */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'product' ) || ! in_the_loop() || ! is_main_query() || doing_filter( 'get_the_excerpt' ) || doing_filter( 'woocommerce_short_description' ) ) {
		return $content;
	}
	global $product;
	$product = wc_get_product( get_the_ID() );
	if ( ! $product ) {
		return $content;
	}
	$is_pack = mpc_is_pack( $product );
	$blocks  = array();

	$title_clean = mpc_clean_title( $product->get_name() );
	$extras      = array_slice( array_map( 'trim', explode( '+', $title_clean ) ), 1 );
	/* Si el contenido es solo el título repetido, no aporta nada. */
	$norm = function ( $x ) {
		return mb_strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', html_entity_decode( wp_strip_all_tags( $x ) ) ), 'UTF-8' );
	};
	$c = $norm( $content );
	similar_text( $c, $norm( $product->get_name() ), $pct_c );
	if ( '' !== $c && ( $c === $norm( $product->get_name() ) || $c === $norm( $title_clean ) || $pct_c >= 85 ) ) {
		$content = '';
	}
	/* En el pack, una descripción corta casi igual al título (con erratas, mayúsculas o emojis) tampoco aporta nada. */
	if ( $is_pack && mb_strlen( trim( wp_strip_all_tags( $content ) ), 'UTF-8' ) <= 260 ) {
		$content = '';
	}
	if ( $is_pack ) {
		$gift_one = $extras ? mb_strtolower( preg_replace( '/\s+de regalo$/iu', '', $extras[0] ), 'UTF-8' ) : '';
		$prot     = mpc_max_protein();
		$tiles    = array(
			array( 'check', '10', 'bandejas a tu elección, puedes repetir sabores' ),
			array( 'ship', '40', 'hamburguesas de 100 g (4 por bandeja)' ),
		);
		if ( $prot ) {
			$tiles[] = array( 'lab', (int) $prot . ' g', 'de proteína como máximo en cada hamburguesa' );
		}
		if ( $gift_one ) {
			$tiles[] = array( 'ship', '+1', $gift_one . ', de regalo' );
		}
		$grid = '<ul class="mpc-incl">';
		foreach ( $tiles as $t ) {
			$grid .= '<li' . ( '+1' === $t[1] ? ' class="is-gift"' : '' ) . '>' . mpc_icon( $t[0] ) . '<strong>' . esc_html( $t[1] ) . '</strong><span>' . esc_html( $t[2] ) . '</span></li>';
		}
		$grid    .= '</ul>';
		$blocks[] = array(
			'title' => 'Qué recibes',
			'body'  => $grid . '<p>Pulsa «Info» junto a cada sabor en el selector para ver sus ingredientes y valores nutricionales.</p>',
			'open'  => true,
		);
	}
	/* Bandeja suelta: ingredientes, alérgenos y valores nutricionales de esa hamburguesa, en texto. */
	if ( mpc_is_single_burger( $product ) && ( $d = mpc_flavour_nutrition( $product->get_id() ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$ing  = preg_replace( '/\b([A-ZÁÉÍÓÚÑ]{3,})\b/u', '<strong>$1</strong>', esc_html( $d['ing'] ) );
		$rows = array(
			'Valor energético' => $d['kj'] . ' kJ / ' . $d['kcal'] . ' kcal',
			'Grasas'           => $d['fat'] . ' g',
			'de las cuales saturadas' => $d['sat'] . ' g',
			'Hidratos de carbono' => $d['carb'] . ' g',
			'de los cuales azúcares' => $d['sug'] . ' g',
			'Proteínas'        => $d['prot'] . ' g',
			'Sal'              => $d['salt'] . ' g',
		);
		$tbl = '<table class="mpc-table mpc-table--dlg"><tbody>';
		foreach ( $rows as $k => $v ) {
			$tbl .= '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
		$tbl     .= '</tbody></table>';
		$blocks[] = array(
			'title' => 'Ingredientes, alérgenos y valores nutricionales',
			'body'  => '<p><strong>' . esc_html( $d['prot'] ) . ' g</strong> de proteína · <strong>' . esc_html( $d['kcal'] ) . ' kcal</strong> por 100 g (cada hamburguesa pesa 100 g).</p><p><b>Ingredientes:</b> ' . $ing . '</p><p><b>Peso neto:</b> ' . esc_html( $d['peso'] ) . '</p>' . $tbl,
			'open'  => true,
		);
	}
	$cold = has_term( array( 'hamburguesas', 'pizza-fitness' ), 'product_cat', $product->get_id() );
	$flow = '<li>' . mpc_icon( 'truck' ) . '<span><strong>' . ( $cold ? 'Refrigerado con SEUR' : 'Envío con SEUR' ) . '</strong>' . ( $cold ? 'En transporte refrigerado y en 24-48 h.' : 'En 24-48 h.' ) . '</span></li>'
		. '<li>' . mpc_icon( 'ship' ) . '<span><strong>Envíos de lunes a jueves</strong>Lo que pidas el jueves por la tarde o el viernes sale el lunes, para preservar la calidad.</span></li>';
	if ( $cold ) {
		$flow .= '<li>' . mpc_icon( 'snow' ) . '<span><strong>Congélalo al recibirlo</strong>12 meses en el congelador; en la nevera, unas 72 horas.</span></li>';
	}
	$flow    .= '<li>' . mpc_icon( 'shield' ) . '<span><strong>Si llega mal, lo reponemos</strong>Nos hacemos cargo de la devolución o de la reposición del pedido.</span></li>';
	$blocks[] = array(
		'title' => 'Cómo llega' . ( $cold ? ' y cómo se conserva' : '' ),
		'body'  => '<ul class="mpc-flow">' . $flow . '</ul>',
		'open'  => true,
	);
	/* Calidad, nutrición genérica y fabricante hablan de la producción propia (hamburguesas y pizzas): no se muestran en productos de otras marcas. */
	if ( $cold || ! mpc_is_basic( $product ) ) {
		$cert     = trim( (string) mpc_opt( 'certs' ) );
		$q        = mpc_page_url( 'controles-de-calidad' );
		$blocks[] = array(
			'title' => 'Calidad y seguridad',
			'body'  => '<p>Controlamos las materias primas en la recepción, el proceso de producción y el peso y aspecto de cada pedido antes de enviarlo. Los alérgenos están identificados y documentados en cada receta.</p>' . ( $cert ? wpautop( $cert ) : '' ) . ( $q ? '<p><a href="' . esc_url( $q ) . '">Ver los controles de calidad</a></p>' : '' ),
			'open'  => false,
		);
		$nut_html = mpc_nutrition_table_html();
		$nut      = trim( (string) mpc_opt( 'nutrition' ) );
		if ( ! mpc_is_single_burger( $product ) && ! $is_pack && ! mpc_is_basic( $product ) && ( $nut_html || $nut ) ) {
			$blocks[] = array(
				'title' => 'Información nutricional y alérgenos',
				'body'  => '<p>Pulsa «Info» junto a cada sabor en el selector para ver sus ingredientes y alérgenos.</p>' . $nut_html . ( $nut ? wpautop( $nut ) : '' ),
				'open'  => false,
			);
		}
		$origin = trim( (string) mpc_opt( 'origin' ) );
		if ( $origin ) {
			$blocks[] = array( 'title' => 'Quién lo fabrica', 'body' => wpautop( $origin ), 'open' => false );
		}
	}

	$html = '<div class="mpc-info">';
	foreach ( $blocks as $b ) {
		$html .= '<details class="mpc-info__item"' . ( $b['open'] ? ' open' : '' ) . '><summary>' . esc_html( $b['title'] ) . '</summary><div class="mpc-info__body">' . $b['body'] . '</div></details>';
	}
	$html .= '</div>';
	return $content . $html;
}, 20 );

/* El título del pack en la ficha sale limpio (sin emojis, sin «PROMOCIÓN» ni erratas). Solo el título visible: no toca <title> ni el SEO. */
add_filter( 'the_title', function ( $title, $id = 0 ) {
	if ( ! $id || ! did_action( 'wp_head' ) || is_admin() || ! is_singular( 'product' ) || (int) $id !== (int) get_queried_object_id() || doing_filter( 'wp_head' ) || doing_filter( 'get_the_excerpt' ) ) {
		return $title;
	}
	$p = wc_get_product( $id );
	if ( $p && mpc_is_basic( $p ) ) {
		return mpc_nice_name( $title );
	}
	if ( ! $p || ! mpc_is_pack( $p ) ) {
		return $title;
	}
	/* El título largo se reparte: «10 bandejas de hamburguesas Meatprotein» arriba y el regalo en una píldora aparte. */
	$parts = explode( '+', mpc_clean_title( $title ), 2 );
	return trim( $parts[0] );
}, 20, 2 );


/* Pestañas de la ficha: sin «Información adicional» (solo repetía todas las opciones de cada bandeja),
   sin «Valoraciones» mientras no haya ninguna, y con valores nutricionales y preguntas frecuentes. */
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	global $product;
	if ( ! $product instanceof WC_Product || ! ( mpc_is_pack( $product ) || mpc_is_single_burger( $product ) || mpc_is_basic( $product ) ) ) {
		return $tabs;
	}
	$is_pack = mpc_is_pack( $product );
	unset( $tabs['additional_information'] );
	if ( isset( $tabs['reviews'] ) && 0 === (int) $product->get_review_count() ) {
		unset( $tabs['reviews'] );
	}
	if ( isset( $tabs['description'] ) ) {
		$tabs['description']['title'] = $is_pack ? 'Qué incluye' : 'Detalles';
	}
	if ( $is_pack && mpc_nutrition_table_html() ) {
		$tabs['mpc_nutrition'] = array(
			'title'    => 'Nutrición',
			'priority' => 15,
			'callback' => function () {
				echo '<div class="mpc-info__body"><p>Valores de cada sabor por 100 g. Para ver los ingredientes y alérgenos, pulsa «Info» junto al sabor en el selector.</p>' . mpc_nutrition_table_html(); // phpcs:ignore WordPress.Security.EscapeOutput
				$nut = trim( (string) mpc_opt( 'nutrition' ) );
				echo $nut ? wpautop( esc_html( $nut ) ) : '';
				echo '</div>';
			},
		);
	}
	$tabs['mpc_faq'] = array(
		'title'    => 'Preguntas',
		'priority' => 25,
		'callback' => function () {
			global $product;
			echo '<div class="mpc-faq mpc-faq--tab">' . mpc_faq_html( $product instanceof WC_Product && mpc_is_basic( $product ) ? 'basic' : 'burger' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		},
	);
	return $tabs;
}, 98 );


/* Clases de cuerpo para dar estilo a la cabecera de la ficha solo en packs y bandejas. */
add_filter( 'body_class', function ( $classes ) {
	if ( is_singular( 'product' ) ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p && mpc_is_pack( $p ) ) {
			$classes[] = 'mpc-pack-page';
		} elseif ( $p && mpc_is_single_burger( $p ) ) {
			$classes[] = 'mpc-single-page';
		} elseif ( $p && mpc_is_basic( $p ) ) {
			$classes[] = 'mpc-basic-page';
		}
	}
	return $classes;
} );
