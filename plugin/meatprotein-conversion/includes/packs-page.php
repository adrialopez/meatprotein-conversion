<?php
/** [mpc_packs]: comparador de packs para la página «Packs». */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mpc_get_packs() {
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 40,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_product_attributes', 'value' => 'Bandeja 1', 'compare' => 'LIKE' ) ),
		'tax_query'      => array( array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'variable' ) ),
	) );
	$packs = array();
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( $p && mpc_is_pack( $p ) && $p->is_in_stock() ) {
			$packs[] = $p;
		}
	}
	usort( $packs, function ( $a, $b ) {
		return (int) $b->get_total_sales() <=> (int) $a->get_total_sales();
	} );
	return $packs;
}

/** Sabores con stock para «Empieza por tu favorita»: los más vendidos primero, con su foto y proteína. */
function mpc_fav_flavours( $limit = 8 ) {
	$seen = array();
	$rows = array();
	foreach ( mpc_flavour_products() as $key => $id ) {
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$p = wc_get_product( $id );
		if ( ! $p || 'publish' !== get_post_status( $id ) ) {
			continue;
		}
		$avail = mpc_available_trays( $id );
		if ( null !== $avail && $avail < 1 ) {
			continue;
		}
		$nut    = mpc_flavour_nutrition( $id );
		$rows[] = array(
			'key'   => $key,
			'name'  => mpc_nice_name( $p->get_name() ),
			'img'   => wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ),
			'prot'  => $nut ? $nut['prot'] : '',
			'sales' => (int) $p->get_total_sales(),
		);
	}
	usort( $rows, function ( $a, $b ) {
		return $b['sales'] <=> $a['sales'];
	} );
	return array_slice( $rows, 0, $limit );
}

add_shortcode( 'mpc_packs', function () {
	$packs = mpc_get_packs();
	if ( ! $packs ) {
		return '';
	}
	$free     = (int) mpc_opt( 'free_min' );
	$maxprot  = mpc_max_protein();
	$favs     = mpc_fav_flavours();
	$hamb_url = get_term_link( 'hamburguesas', 'product_cat' );
	$top_url  = $packs[0]->get_permalink();
	ob_start();
	?>
	<div class="mpc-packs">
		<header class="mpc-packs__intro">
			<p class="mpc-packs__lead">Elige tus sabores, congela y come proteína durante semanas. Cada pack lleva regalo y envío refrigerado.</p>
			<ul class="mpc-packs__stats">
				<li><strong>10</strong><span>bandejas</span></li>
				<li><strong>40</strong><span>hamburguesas</span></li>
				<?php if ( $maxprot ) : ?><li><strong><?php echo (int) $maxprot; ?> g</strong><span>de proteína por hamburguesa</span></li><?php endif; ?>
				<li><strong>Gratis</strong><span>el envío desde <?php echo esc_html( $free ); ?> €</span></li>
			</ul>
		</header>
		<div class="mpc-packs__grid">
			<?php
			foreach ( $packs as $i => $p ) :
				$vars  = $p->get_available_variations();
				$reg   = $vars ? (float) $vars[0]['display_regular_price'] : (float) $p->get_regular_price();
				$sale  = $vars ? (float) $vars[0]['display_price'] : (float) $p->get_price();
				$name  = mpc_clean_title( $p->get_name() );
				$parts = array_map( 'trim', explode( '+', $name ) );
				array_shift( $parts );
				$pct   = $reg > $sale ? (int) round( ( $reg - $sale ) / $reg * 100 ) : 0;
				?>
				<article class="mpc-pack-card<?php echo 0 === $i ? ' is-top' : ''; ?>">
					<?php if ( 0 === $i && $p->get_total_sales() > 0 ) : ?>
						<span class="mpc-pack-card__badge">Más vendido</span>
					<?php endif; ?>
					<a class="mpc-pack-card__img" href="<?php echo esc_url( $p->get_permalink() ); ?>"><?php echo wp_kses_post( $p->get_image( 'woocommerce_single' ) ); ?><?php if ( $pct ) : ?><span class="mpc-pack-card__pct">-<?php echo (int) $pct; ?> %</span><?php endif; ?></a>
					<div class="mpc-pack-card__body">
						<h3><a href="<?php echo esc_url( $p->get_permalink() ); ?>"><?php echo esc_html( $name ); ?></a></h3>
						<ul class="mpc-pack-card__inc">
							<li><?php echo mpc_icon( 'check' ); ?><span>10 bandejas de hamburguesas, tú eliges los sabores</span></li>
							<?php foreach ( $parts as $extra ) : ?>
								<li class="is-gift"><?php echo mpc_icon( 'check' ); ?><span><?php echo esc_html( mb_strtoupper( mb_substr( $extra, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $extra, 1, null, 'UTF-8' ) ); ?></span><em>Regalo</em></li>
							<?php endforeach; ?>
							<li><?php echo mpc_icon( 'check' ); ?><span>Envío refrigerado y gratis (desde <?php echo esc_html( $free ); ?> €)</span></li>
						</ul>
						<p class="mpc-pack-card__price">
							<?php if ( $reg > $sale ) : ?><del><?php echo wp_kses_post( wc_price( $reg ) ); ?></del><?php endif; ?>
							<strong><?php echo wp_kses_post( wc_price( $sale ) ); ?></strong>
							<?php if ( $reg > $sale ) : ?><small>Ahorras <?php echo wp_kses_post( wc_price( $reg - $sale ) ); ?></small><?php endif; ?>
							<span class="mpc-pack-card__per"><?php echo wp_kses_post( wc_price( $sale / 10 ) ); ?> por bandeja · <?php echo wp_kses_post( wc_price( $sale / 40 ) ); ?> por hamburguesa</span>
						</p>
						<a class="mpc-btn" href="<?php echo esc_url( $p->get_permalink() ); ?>">Elegir mis sabores</a>
						<p class="mpc-pack-card__note">Si llega en mal estado, lo reponemos.</p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<?php if ( $favs ) : ?>
		<section class="mpc-fav" aria-labelledby="mpc-fav-t">
			<h2 id="mpc-fav-t">Empieza por tu favorita</h2>
			<p class="mpc-fav__sub">Toca un sabor y lo dejamos ya elegido en tu pack. Solo ves los que tenemos en stock.</p>
			<ul class="mpc-fav__rail">
				<?php foreach ( $favs as $f ) : ?>
					<li><a class="mpc-fav__card" href="<?php echo esc_url( add_query_arg( 'mpc_pre', rawurlencode( $f['key'] ), $top_url ) ); ?>">
						<?php if ( $f['img'] ) : ?><img src="<?php echo esc_url( $f['img'] ); ?>" alt="" loading="lazy" width="300" height="300"><?php endif; ?>
						<span class="mpc-fav__name"><?php echo esc_html( $f['name'] ); ?></span>
						<?php if ( $f['prot'] ) : ?><span class="mpc-fav__prot"><?php echo esc_html( $f['prot'] ); ?> g proteína</span><?php endif; ?>
					</a></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php endif; ?>

		<section class="mpc-how" aria-labelledby="mpc-how-t">
			<h2 id="mpc-how-t">Así funciona</h2>
			<ol class="mpc-steps">
				<li><b>1</b><span>Elige tus 10 sabores</span></li>
				<li><b>2</b><span>Añade salsa, pan o queso si quieres</span></li>
				<li><b>3</b><span>Lo recibes refrigerado y lo congelas</span></li>
			</ol>
		</section>

		<section class="mpc-faq" aria-labelledby="mpc-faq-t">
			<h2 id="mpc-faq-t">Preguntas frecuentes</h2>
			<?php echo mpc_faq_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</section>

		<ul class="mpc-trust mpc-trust--row">
			<li><?php echo mpc_icon( 'truck' ); ?><span><strong>Refrigerado con SEUR</strong>24-48 h</span></li>
			<li><?php echo mpc_icon( 'snow' ); ?><span><strong>12 meses congelado</strong>Congélalo al recibirlo</span></li>
			<li><?php echo mpc_icon( 'shield' ); ?><span><strong>Reposición garantizada</strong>Si llega en mal estado</span></li>
		</ul>
		<?php if ( ! is_wp_error( $hamb_url ) ) : ?>
			<p class="mpc-packs__alt">¿Prefieres probar primero? <a href="<?php echo esc_url( $hamb_url ); ?>">Compra bandejas sueltas</a></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
} );
