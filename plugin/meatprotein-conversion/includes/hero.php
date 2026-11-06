<?php
/**
 * Portada interactiva: «Monta tu pack» en el hero. Sustituye al hero de Betheme (que se oculta con CSS, sin borrarlo).
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indica si se muestra la portada interactiva.
 *
 * @return mixed Resultado.
 */
function mpc_hero_enabled() {
	return function_exists( 'is_front_page' ) && is_front_page() && '0' !== (string) mpc_opt( 'hero' ) && apply_filters( 'mpc_hero_enabled', true );
}

add_filter(
	'body_class',
	function ( $classes ) {
		if ( mpc_hero_enabled() ) {
			$classes[] = 'mpc-hero-on';
		}
		return $classes;
	}
);

/**
 * HTML del hero «Monta tu pack» de la portada.
 *
 * @return string
 */
function mpc_hero_html() {
	$packs = mpc_get_packs();
	if ( ! $packs ) {
		return '';
	}
	ob_start();
	$pack      = $packs[0];
	$vars      = $pack->get_available_variations();
	$reg       = $vars ? (float) $vars[0]['display_regular_price'] : (float) $pack->get_regular_price();
	$sale      = $vars ? (float) $vars[0]['display_price'] : (float) $pack->get_price();
	$parts     = array_map( 'trim', explode( '+', mpc_clean_title( $pack->get_name() ) ) );
	$gift      = mpc_gift_text( isset( $parts[1] ) ? $parts[1] : '' );
	$favs      = mpc_fav_flavours( 8 );
	$prot      = mpc_max_protein();
	$photo     = apply_filters( 'mpc_hero_image', 'https://meatprotein.eu/wp-content/uploads/2024/06/meat-protein-home-scaled.jpg' );
	$pimg      = wp_get_attachment_image_url( $pack->get_image_id(), 'woocommerce_thumbnail' );
	$price     = wp_strip_all_tags( wc_price( $sale ) );
	$packs_url = mpc_page_url( 'packs' );
	?>
	<section class="mpc-hero" aria-labelledby="mpc-hero-t">
		<div class="mpc-hero__in">
			<div class="mpc-hero__copy">
				<p class="mpc-hero__eyebrow">Hamburguesas fit<?php echo $prot ? ' · hasta ' . (int) $prot . ' g de proteína' : ''; ?></p>
				<h1 id="mpc-hero-t">Monta tu pack de <span>10 sabores</span></h1>
				<p class="mpc-hero__lead">Toca tus favoritos. <?php echo $gift ? 'Incluye ' . esc_html( false === stripos( $gift, 'regalo' ) ? $gift . ' de regalo' : $gift ) . ' y el' : 'El'; ?> envío sale refrigerado en 24-48 h.</p>

				<div class="mpc-hero__builder" data-mpc-hero data-url="<?php echo esc_url( $pack->get_permalink() ); ?>" data-size="10" data-price="<?php echo esc_attr( $price ); ?>">
					<div class="mpc-hero__bhead"><b>Tu pack</b><span data-mpc-hcount aria-live="polite">0 de 10</span><button type="button" class="mpc-hero__clear" data-mpc-hclear hidden>Vaciar</button></div>
					<div class="mpc-hero__bar" role="presentation"><i></i></div>
					<ul class="mpc-hero__tiles">
						<?php foreach ( $favs as $f ) : ?>
							<li class="mpc-tile" data-key="<?php echo esc_attr( $f['key'] ); ?>">
								<button type="button" class="mpc-tile__add" aria-label="Añadir <?php echo esc_attr( $f['name'] ); ?>">
									<?php
									if ( $f['img'] ) :
										?>
										<img src="<?php echo esc_url( $f['img'] ); ?>" alt="" loading="lazy" width="300" height="300"><?php endif; ?>
									<span class="mpc-tile__n"><?php echo esc_html( $f['name'] ); ?></span>
									<?php
									if ( $f['prot'] ) :
										?>
										<span class="mpc-tile__p"><?php echo esc_html( $f['prot'] ); ?> g proteína</span><?php endif; ?>
								</button>
								<span class="mpc-tile__c" hidden>0</span>
								<button type="button" class="mpc-tile__m" aria-label="Quitar <?php echo esc_attr( $f['name'] ); ?>" hidden>−</button>
							</li>
						<?php endforeach; ?>
					</ul>
					<a class="mpc-hero__cta" href="<?php echo esc_url( $pack->get_permalink() ); ?>"><span data-mpc-hcta>Elegir mis sabores</span><b data-mpc-hprice>· <?php echo esc_html( $price ); ?></b></a>
					<?php
					if ( $packs_url ) :
						?>
						<a class="mpc-hero__more" href="<?php echo esc_url( $packs_url ); ?>">Ver todos los packs</a><?php endif; ?>
				</div>
			</div>
			<div class="mpc-hero__visual">
				<img src="<?php echo esc_url( $photo ); ?>" alt="" fetchpriority="high">
				<?php if ( $prot ) : ?>
					<p class="mpc-hero__stat"><strong><span data-mpc-count="<?php echo (int) $prot; ?>"><?php echo (int) $prot; ?></span> g</strong><span>de proteína por hamburguesa</span></p>
				<?php endif; ?>
				<a class="mpc-hero__pack" href="<?php echo esc_url( $pack->get_permalink() ); ?>">
					<?php
					if ( $pimg ) :
						?>
						<img src="<?php echo esc_url( $pimg ); ?>" alt="" loading="lazy" width="300" height="300"><?php endif; ?>
					<span><b>Pack de 10 + 1 de regalo</b><?php echo $reg > $sale ? '<del>' . esc_html( wp_strip_all_tags( wc_price( $reg ) ) ) . '</del> ' : ''; ?><strong><?php echo esc_html( $price ); ?></strong></span>
				</a>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * HTML de la franja de confianza bajo el hero.
 *
 * @return string
 */
function mpc_strip_html() {
	$quality = mpc_page_url( 'controles-de-calidad' );
	ob_start();
	?>
	<div class="mpc-strip-wrap"><ul class="mpc-strip">
		<li><?php mpc_the_icon( 'truck' ); ?><span><strong>Refrigerado con SEUR</strong>En 24-48 h</span></li>
		<li><?php mpc_the_icon( 'shield' ); ?><span><strong>Reposición garantizada</strong>Si llega en mal estado</span></li>
		<li><?php mpc_the_icon( 'ship' ); ?><span><strong>Envío gratis</strong>Con el pack o 10 bandejas</span></li>
		<li><?php mpc_the_icon( 'lab' ); ?><span><strong><?php echo $quality ? '<a href="' . esc_url( $quality ) . '">Controles de calidad</a>' : 'Controles de calidad'; ?></strong>En cada pedido</span></li>
	</ul></div>
	<?php
	return ob_get_clean();
}



/**
 * Bloque de confianza «Compra con tranquilidad».
 *
 * @return string HTML.
 */
function mpc_assure_html() {
	$phone  = trim( (string) mpc_opt( 'phone' ) );
	$origin = trim( wp_strip_all_tags( (string) mpc_opt( 'origin' ) ) );
	$links  = array_filter(
		array(
			'Envíos y devoluciones' => mpc_page_url( 'envios-y-devoluciones' ),
			'Preguntas frecuentes'  => mpc_page_url( 'faqs' ),
			'Controles de calidad'  => mpc_page_url( 'controles-de-calidad' ),
		)
	);
	$cards  = array(
		array( 'snow', 'Cadena de frío', 'Sale refrigerado y llega en 24-48 h con SEUR. Al recibirlo, lo congelas y dura 12 meses.' ),
		array( 'shield', 'Si algo llega mal, lo reponemos', 'Nos hacemos cargo de la reposición o te devolvemos el dinero. Sin discusiones.' ),
		array( 'lab', 'Controles de calidad', 'Revisamos las materias primas, la producción y cada pedido antes de enviarlo.' ),
		array( 'check', 'Pago seguro', 'Paga con tarjeta, PayPal o Bizum.' ),
	);
	ob_start();
	?>
	<section class="mpc-assure" data-mpc-assure aria-labelledby="mpc-assure-t">
		<div class="mpc-assure__in">
			<h2 id="mpc-assure-t">Compra con tranquilidad</h2>
			<ul class="mpc-assure__grid">
				<?php foreach ( $cards as $c ) : ?>
					<li><?php mpc_the_icon( $c[0] ); ?><strong><?php echo esc_html( $c[1] ); ?></strong><span><?php echo esc_html( $c[2] ); ?></span></li>
				<?php endforeach; ?>
			</ul>
			<?php
			if ( $origin ) :
				?>
				<p class="mpc-assure__origin"><?php echo esc_html( $origin ); ?></p><?php endif; ?>
			<p class="mpc-assure__links">
				<?php
				$out = array();
				foreach ( $links as $label => $url ) {
					$out[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
				}
				if ( $phone ) {
					$out[] = '<a href="tel:' . esc_attr( preg_replace( '/\s+/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
				}
				echo implode( ' · ', $out ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</p>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Pie del panel lateral del menú: envío gratis, teléfono y enlace a envíos.
 *
 * @return string HTML.
 */
function mpc_menu_foot_html() {
	$phone = trim( (string) mpc_opt( 'phone' ) );
	$href  = preg_replace( '/[^\d+]/', '', $phone ? ( 0 === strpos( $phone, '+' ) ? $phone : '+34' . $phone ) : '' );
	$ship  = mpc_page_url( 'envios-y-devoluciones' );
	$h     = '<div class="mpc-sm-foot"><p class="mpc-sm-free">Envío gratis con el pack o 10 bandejas</p>';
	if ( $phone && $href ) {
		$h .= '<a class="mpc-sm-call" href="tel:' . esc_attr( $href ) . '"><span>Llámanos</span><strong>' . esc_html( $phone ) . '</strong></a>';
	}
	if ( $ship ) {
		$h .= '<a class="mpc-sm-link" href="' . esc_url( $ship ) . '">Envíos y devoluciones</a>';
	}
	return $h . '</div>';
}

/**
 * HTML en una sola línea, para elementos «Código» de Betheme.
 *
 * @param string $html HTML.
 * @return string
 */
function mpc_compact( $html ) {
	return trim( preg_replace( '/>\s+</', '><', preg_replace( '/\s+/', ' ', $html ) ) );
}

/* Bloques para el constructor de Betheme: se colocan con un elemento «Código» (ver includes/layout.php). */
add_shortcode(
	'mpc_hero',
	function () {
		return mpc_compact( mpc_hero_html() );
	}
);
add_shortcode(
	'mpc_strip',
	function () {
		return mpc_compact( mpc_strip_html() );
	}
);
add_shortcode(
	'mpc_assure',
	function () {
		return mpc_compact( mpc_assure_html() );
	}
);
add_shortcode(
	'mpc_menu_foot',
	function () {
		return mpc_compact( mpc_menu_foot_html() );
	}
);

/* Respaldo: si la portada aún no se ha montado en el constructor, se inyecta con los ganchos de Betheme. */
add_action(
	'mfn_hook_content_before',
	function () {
		if ( mpc_hero_enabled() && ! get_option( 'mpc_layout_native' ) ) {
			echo mpc_hero_html() . mpc_strip_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	5
);
add_action(
	'mfn_hook_content_after',
	function () {
		if ( mpc_hero_enabled() && ! get_option( 'mpc_layout_native' ) ) {
			echo mpc_assure_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	5
);

/**
 * Datos del menú para ui.js: enlace a packs, teléfono y aviso de envío gratis.
 *
 * @param string $packs_url URL de la página de packs.
 * @return array
 */
function mpc_nav_data( $packs_url ) {
	$phone = trim( (string) mpc_opt( 'phone' ) );
	$href  = '';
	if ( '' !== $phone ) {
		$href = preg_replace( '/[^\d+]/', '', 0 === strpos( $phone, '+' ) ? $phone : '+34' . $phone );
	}
	return array(
		'packs'     => $packs_url,
		'phone'     => $phone,
		'phoneHref' => $href,
		'free'      => 'Envío gratis con el pack o 10 bandejas',
		'ship'      => mpc_page_url( 'envios-y-devoluciones' ),
	);
}
