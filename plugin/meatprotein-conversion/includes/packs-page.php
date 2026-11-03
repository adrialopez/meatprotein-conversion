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

add_shortcode( 'mpc_packs', function () {
	$packs = mpc_get_packs();
	if ( ! $packs ) {
		return '';
	}
	$free = (int) mpc_opt( 'free_min' );
	ob_start();
	?>
	<div class="mpc-packs">
		<ol class="mpc-steps">
			<li><b>1</b><span>Elige tu pack</span></li>
			<li><b>2</b><span>Elige tus sabores</span></li>
			<li><b>3</b><span>Lo recibes refrigerado y lo congelas</span></li>
		</ol>
		<div class="mpc-packs__grid">
			<?php
			foreach ( $packs as $i => $p ) :
				$vars = $p->get_available_variations();
				$reg  = $vars ? (float) $vars[0]['display_regular_price'] : (float) $p->get_regular_price();
				$sale = $vars ? (float) $vars[0]['display_price'] : (float) $p->get_price();
				$name = mpc_clean_title( $p->get_name() );
				$parts = array_map( 'trim', explode( '+', $name ) );
				array_shift( $parts );
				?>
				<article class="mpc-pack-card<?php echo 0 === $i ? ' is-top' : ''; ?>">
					<?php if ( 0 === $i && $p->get_total_sales() > 0 ) : ?>
						<span class="mpc-pack-card__badge">Más vendido</span>
					<?php endif; ?>
					<a class="mpc-pack-card__img" href="<?php echo esc_url( $p->get_permalink() ); ?>"><?php echo wp_kses_post( $p->get_image( 'woocommerce_single' ) ); ?></a>
					<h3><a href="<?php echo esc_url( $p->get_permalink() ); ?>"><?php echo esc_html( $name ); ?></a></h3>
					<ul class="mpc-pack-card__inc">
						<li><?php echo mpc_icon( 'check' ); ?>10 bandejas de hamburguesas, tú eliges los sabores</li>
						<?php foreach ( $parts as $extra ) : ?>
							<li><?php echo mpc_icon( 'check' ); ?><?php echo esc_html( mb_strtoupper( mb_substr( $extra, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $extra, 1, null, 'UTF-8' ) ); ?></li>
						<?php endforeach; ?>
						<li><?php echo mpc_icon( 'check' ); ?>Envío refrigerado y gratis (desde <?php echo esc_html( $free ); ?> €)</li>
					</ul>
					<p class="mpc-pack-card__price">
						<?php if ( $reg > $sale ) : ?><del><?php echo wp_kses_post( wc_price( $reg ) ); ?></del><?php endif; ?>
						<strong><?php echo wp_kses_post( wc_price( $sale ) ); ?></strong>
						<?php if ( $reg > $sale ) : ?><small>Ahorras <?php echo wp_kses_post( wc_price( $reg - $sale ) ); ?></small><?php endif; ?>
					</p>
					<a class="mpc-btn" href="<?php echo esc_url( $p->get_permalink() ); ?>">Elegir sabores</a>
				</article>
			<?php endforeach; ?>
		</div>
		<ul class="mpc-trust mpc-trust--row">
			<li><?php echo mpc_icon( 'truck' ); ?><span><strong>Refrigerado con SEUR</strong>24-48 h</span></li>
			<li><?php echo mpc_icon( 'snow' ); ?><span><strong>12 meses congelado</strong>Congélalo al recibirlo</span></li>
			<li><?php echo mpc_icon( 'shield' ); ?><span><strong>Reposición garantizada</strong>Si llega en mal estado</span></li>
		</ul>
	</div>
	<?php
	return ob_get_clean();
} );
