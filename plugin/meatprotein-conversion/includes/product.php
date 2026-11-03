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
	echo '<div class="mpc-pack" data-mpc-pack hidden></div>';
	echo '<script>document.documentElement.classList.add("mpc-has-pack");</script>';
} );

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
}, 11 );

/* Garantías junto al botón de compra. */
add_action( 'woocommerce_after_add_to_cart_form', function () {
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
	<ul class="mpc-trust">
		<li><?php echo mpc_icon( 'truck' ); ?><span><strong>Llega refrigerado con SEUR</strong>En 24-48 h, de lunes a jueves antes de las 14:00.</span></li>
		<li><?php echo mpc_icon( 'ship' ); ?><span><strong>Envío gratis desde <?php echo esc_html( $free ); ?> €</strong>Por debajo, <?php echo esc_html( $ship ); ?> € a toda la Península.</span></li>
		<li><?php echo mpc_icon( 'shield' ); ?><span><strong>Si llega en mal estado, lo reponemos</strong>O te devolvemos el dinero del pedido.</span></li>
		<li><?php echo mpc_icon( 'snow' ); ?><span><strong>Congélalas al recibirlas</strong>Duran 12 meses en el congelador y unas 72 h en la nevera.</span></li>
	</ul>
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
	if ( $norm( $desc ) === $norm( get_the_title() ) ) {
		return '';
	}
	return $desc;
} );
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

	$title_clean = mpc_clean_title( get_the_title() );
	$extras      = array_slice( array_map( 'trim', explode( '+', $title_clean ) ), 1 );
	/* Si el contenido es solo el título repetido, no aporta nada. */
	$norm = function ( $x ) {
		return mb_strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', html_entity_decode( wp_strip_all_tags( $x ) ) ), 'UTF-8' );
	};
	if ( $norm( $content ) === $norm( get_the_title() ) || $norm( $content ) === $norm( $title_clean ) ) {
		$content = '';
	}
	if ( $is_pack ) {
		$gift_txt = $extras ? ' Además recibes: ' . esc_html( mb_strtolower( implode( ' y ', $extras ), 'UTF-8' ) ) . '.' : '';
		$blocks[] = array(
			'title' => 'Qué recibes',
			'body'  => '<p>10 bandejas de hamburguesas a tu elección: tú decides los sabores y puedes repetir los que más te gusten.' . $gift_txt . '</p>',
			'open'  => true,
		);
	}
	$blocks[] = array(
		'title' => 'Cómo llega y cómo se conserva',
		'body'  => '<ul><li>Se envía <strong>refrigerado</strong> con SEUR, en transporte refrigerado y en 24-48 h.</li><li>Al recibirlo, <strong>congélalo</strong>: en el congelador dura 12 meses; en la nevera, unas 72 horas.</li><li>Entregamos de lunes a jueves antes de las 14:00. Los pedidos del jueves por la tarde y del viernes salen el lunes, para preservar la calidad.</li><li>Si llega en mal estado, nos hacemos cargo de la devolución o de la reposición del pedido.</li></ul>',
		'open'  => true,
	);
	$cert = trim( (string) mpc_opt( 'certs' ) );
	$q    = mpc_page_url( 'controles-de-calidad' );
	$blocks[] = array(
		'title' => 'Calidad y seguridad',
		'body'  => '<p>Controlamos las materias primas en la recepción, el proceso de producción y el peso y aspecto de cada pedido antes de enviarlo. Los alérgenos están identificados y documentados en cada receta.</p>' . ( $cert ? wpautop( $cert ) : '' ) . ( $q ? '<p><a href="' . esc_url( $q ) . '">Ver los controles de calidad</a></p>' : '' ),
		'open'  => false,
	);
	$nut = trim( (string) mpc_opt( 'nutrition' ) );
	if ( $nut ) {
		$blocks[] = array( 'title' => 'Información nutricional y alérgenos', 'body' => wpautop( $nut ), 'open' => false );
	} elseif ( 'local' === wp_get_environment_type() ) {
		$blocks[] = array( 'title' => 'Información nutricional y alérgenos', 'body' => '<p class="mpc-draft">Pendiente: proteína y calorías por hamburguesa, peso por bandeja, ingredientes y alérgenos. Se rellena en WooCommerce &gt; Conversión.</p>', 'open' => false );
	}
	$origin = trim( (string) mpc_opt( 'origin' ) );
	if ( $origin ) {
		$blocks[] = array( 'title' => 'Quién lo fabrica', 'body' => wpautop( $origin ), 'open' => false );
	} elseif ( 'local' === wp_get_environment_type() ) {
		$blocks[] = array( 'title' => 'Quién lo fabrica', 'body' => '<p class="mpc-draft">Pendiente: fabricante, origen de la carne y certificaciones.</p>', 'open' => false );
	}

	$html = '<div class="mpc-info">';
	foreach ( $blocks as $b ) {
		$html .= '<details class="mpc-info__item"' . ( $b['open'] ? ' open' : '' ) . '><summary>' . esc_html( $b['title'] ) . '</summary><div class="mpc-info__body">' . $b['body'] . '</div></details>';
	}
	$html .= '</div>';
	return $content . $html;
}, 20 );
