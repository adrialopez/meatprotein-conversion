<?php
/** Barra superior con promo puntual; con fecha de fin muestra cuenta atrás y se retira sola. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mpc_promo_html() {
	$txt = trim( (string) mpc_opt( 'promo_text' ) );
	if ( '' === $txt ) {
		return '';
	}
	$ends = trim( (string) mpc_opt( 'promo_ends' ) );
	$ts   = 0;
	if ( $ends ) {
		$ts = strtotime( $ends . ' ' . wp_timezone_string() );
		if ( $ts && $ts < time() ) {
			return '';
		}
	}
	$url = trim( (string) mpc_opt( 'promo_url' ) );
	$cta = trim( (string) mpc_opt( 'promo_cta' ) );
	$h   = '<div class="mpc-promo" data-mpc-promo' . ( $ts ? ' data-ends="' . (int) $ts . '"' : '' ) . '><p>' . esc_html( $txt );
	if ( $ts ) {
		$h .= ' <span class="mpc-promo__count" data-mpc-count aria-live="off"></span>';
	}
	if ( $url && $cta ) {
		$h .= ' <a href="' . esc_url( $url ) . '">' . esc_html( $cta ) . '</a>';
	}
	return $h . '</p></div>';
}

/* Betheme no llama a wp_body_open: la barra se imprime en una plantilla y se coloca al principio del <body>. */
add_action( 'wp_footer', function () {
	if ( is_checkout() && ! is_order_received_page() ) {
		return;
	}
	$html = mpc_promo_html();
	if ( '' === $html ) {
		return;
	}
	echo '<template id="mpc-promo-tpl">' . $html . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<script>(function(){var t=document.getElementById("mpc-promo-tpl");if(t&&t.content&&t.content.firstElementChild){document.body.insertBefore(t.content.firstElementChild,document.body.firstChild);}})();</script>';
}, 1 );
