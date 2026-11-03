<?php
/** Portada interactiva: «Monta tu pack» en el hero. Sustituye al hero de Betheme (que se oculta con CSS, sin borrarlo). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mpc_hero_enabled() {
	return function_exists( 'is_front_page' ) && is_front_page() && '0' !== (string) mpc_opt( 'hero' ) && apply_filters( 'mpc_hero_enabled', true );
}

add_filter( 'body_class', function ( $classes ) {
	if ( mpc_hero_enabled() ) {
		$classes[] = 'mpc-hero-on';
	}
	return $classes;
} );

add_action( 'mfn_hook_content_before', function () {
	if ( ! mpc_hero_enabled() ) {
		return;
	}
	$packs = mpc_get_packs();
	if ( ! $packs ) {
		return;
	}
	$pack  = $packs[0];
	$vars  = $pack->get_available_variations();
	$reg   = $vars ? (float) $vars[0]['display_regular_price'] : (float) $pack->get_regular_price();
	$sale  = $vars ? (float) $vars[0]['display_price'] : (float) $pack->get_price();
	$parts = array_map( 'trim', explode( '+', mpc_clean_title( $pack->get_name() ) ) );
	$gift  = isset( $parts[1] ) ? $parts[1] : '';
	$favs  = mpc_fav_flavours( 8 );
	$free  = (int) mpc_opt( 'free_min' );
	$prot  = mpc_max_protein();
	$photo = apply_filters( 'mpc_hero_image', 'https://meatprotein.eu/wp-content/uploads/2024/06/meat-protein-home-scaled.jpg' );
	$pimg  = wp_get_attachment_image_url( $pack->get_image_id(), 'woocommerce_thumbnail' );
	$price = wp_strip_all_tags( wc_price( $sale ) );
	$packs_url = mpc_page_url( 'packs' );
	$quality   = mpc_page_url( 'controles-de-calidad' );
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
									<?php if ( $f['img'] ) : ?><img src="<?php echo esc_url( $f['img'] ); ?>" alt="" loading="lazy" width="300" height="300"><?php endif; ?>
									<span class="mpc-tile__n"><?php echo esc_html( $f['name'] ); ?></span>
									<?php if ( $f['prot'] ) : ?><span class="mpc-tile__p"><?php echo esc_html( $f['prot'] ); ?> g proteína</span><?php endif; ?>
								</button>
								<span class="mpc-tile__c" hidden>0</span>
								<button type="button" class="mpc-tile__m" aria-label="Quitar <?php echo esc_attr( $f['name'] ); ?>" hidden>−</button>
							</li>
						<?php endforeach; ?>
					</ul>
					<a class="mpc-hero__cta" href="<?php echo esc_url( $pack->get_permalink() ); ?>"><span data-mpc-hcta>Elegir mis sabores</span><b data-mpc-hprice>· <?php echo esc_html( $price ); ?></b></a>
					<?php if ( $packs_url ) : ?><a class="mpc-hero__more" href="<?php echo esc_url( $packs_url ); ?>">Ver todos los packs</a><?php endif; ?>
				</div>
			</div>
			<div class="mpc-hero__visual">
				<img src="<?php echo esc_url( $photo ); ?>" alt="" fetchpriority="high">
				<?php if ( $prot ) : ?>
					<p class="mpc-hero__stat"><strong><span data-mpc-count="<?php echo (int) $prot; ?>"><?php echo (int) $prot; ?></span> g</strong><span>de proteína por hamburguesa</span></p>
				<?php endif; ?>
				<a class="mpc-hero__pack" href="<?php echo esc_url( $pack->get_permalink() ); ?>">
					<?php if ( $pimg ) : ?><img src="<?php echo esc_url( $pimg ); ?>" alt="" loading="lazy" width="300" height="300"><?php endif; ?>
					<span><b>Pack de 10 + 1 de regalo</b><?php echo $reg > $sale ? '<del>' . esc_html( wp_strip_all_tags( wc_price( $reg ) ) ) . '</del> ' : ''; ?><strong><?php echo esc_html( $price ); ?></strong></span>
				</a>
			</div>
		</div>
	</section>
	<div class="mpc-strip-wrap"><ul class="mpc-strip">
		<li><?php echo mpc_icon( 'truck' ); ?><span><strong>Refrigerado con SEUR</strong>En 24-48 h</span></li>
		<li><?php echo mpc_icon( 'shield' ); ?><span><strong>Reposición garantizada</strong>Si llega en mal estado</span></li>
		<li><?php echo mpc_icon( 'ship' ); ?><span><strong>Envío gratis</strong>Con 10 bandejas o desde <?php echo esc_html( $free ); ?> €</span></li>
		<li><?php echo mpc_icon( 'lab' ); ?><span><strong><?php echo $quality ? '<a href="' . esc_url( $quality ) . '">Controles de calidad</a>' : 'Controles de calidad'; ?></strong>En cada pedido</span></li>
	</ul></div>
	<?php
}, 5 );

/* «Compra con tranquilidad»: bloque de confianza que hero.js coloca antes de la newsletter (si no hay JS se queda al final). */
add_action( 'mfn_hook_content_after', function () {
	if ( ! mpc_hero_enabled() ) {
		return;
	}
	$phone   = trim( (string) mpc_opt( 'phone' ) );
	$origin  = trim( wp_strip_all_tags( (string) mpc_opt( 'origin' ) ) );
	$links   = array_filter( array(
		'Envíos y devoluciones' => mpc_page_url( 'envios-y-devoluciones' ),
		'Preguntas frecuentes'  => mpc_page_url( 'faqs' ),
		'Controles de calidad'  => mpc_page_url( 'controles-de-calidad' ),
	) );
	$cards   = array(
		array( 'snow', 'Cadena de frío', 'Sale refrigerado y llega en 24-48 h con SEUR. Al recibirlo, lo congelas y dura 12 meses.' ),
		array( 'shield', 'Si algo llega mal, lo reponemos', 'Nos hacemos cargo de la reposición o te devolvemos el dinero. Sin discusiones.' ),
		array( 'lab', 'Controles de calidad', 'Revisamos las materias primas, la producción y cada pedido antes de enviarlo.' ),
		array( 'check', 'Pago seguro', 'Paga con tarjeta, PayPal o Bizum.' ),
	);
	?>
	<section class="mpc-assure" data-mpc-assure aria-labelledby="mpc-assure-t">
		<div class="mpc-assure__in">
			<h2 id="mpc-assure-t">Compra con tranquilidad</h2>
			<ul class="mpc-assure__grid">
				<?php foreach ( $cards as $c ) : ?>
					<li><?php echo mpc_icon( $c[0] ); ?><strong><?php echo esc_html( $c[1] ); ?></strong><span><?php echo esc_html( $c[2] ); ?></span></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $origin ) : ?><p class="mpc-assure__origin"><?php echo esc_html( $origin ); ?></p><?php endif; ?>
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
}, 5 );

/* Enlace «Packs» en el menú principal: lo añade ui.js si la página existe y el menú no lo trae ya. */
add_action( 'wp_footer', function () {
	$url = mpc_page_url( 'packs' );
	if ( $url ) {
		$phone = trim( (string) mpc_opt( 'phone' ) );
		echo '<script>window.mpcNav=' . wp_json_encode( array(
			'packs'     => $url,
			'phone'     => $phone,
			'phoneHref' => preg_replace( '/[^\d+]/', '', $phone ? ( 0 === strpos( $phone, '+' ) ? $phone : '+34' . $phone ) : '' ),
			'free'      => 'Envío gratis con 10 bandejas o desde ' . (int) mpc_opt( 'free_min' ) . ' €',
			'ship'      => mpc_page_url( 'envios-y-devoluciones' ),
		) ) . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 5 );
