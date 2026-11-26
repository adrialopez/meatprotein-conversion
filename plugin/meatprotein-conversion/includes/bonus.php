<?php
/**
 * Regalos del pack: hasta tres. El primero es el de siempre (cuadro del pack, `_mpc_gift_ids`); el segundo y el
 * tercero son opcionales, con texto propio, hasta cinco opciones cada uno, publicación y fechas. También una imagen
 * temporal para el pack. Todo se configura en la ficha del pack y se puede revisar antes con la vista previa privada.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MPC_GIFT_MAX_OPTIONS = 5;
const MPC_BONUS_SLOTS      = array( 2, 3 );

/**
 * Configuración guardada.
 *
 * @return array
 */
function mpc_bonus_cfg() {
	$c     = get_option( 'mpc_bonus', array() );
	$c     = is_array( $c ) ? $c : array();
	$dates = array(
		'pub'   => 0,
		'from'  => '',
		'until' => '',
	);
	$slots = array();
	foreach ( MPC_BONUS_SLOTS as $n ) {
		$slots[ $n ] = array_merge(
			array(
				'label' => '',
				'ids'   => '',
			),
			$dates,
			(array) ( $c['slots'][ $n ] ?? array() )
		);
	}
	return array(
		'token'  => (string) ( $c['token'] ?? '' ),
		'label1' => (string) ( $c['label1'] ?? '' ),
		'slots'  => $slots,
		'image'  => array_merge( array( 'id' => 0 ), $dates, (array) ( $c['image'] ?? array() ) ),
	);
}

/**
 * Texto del primer regalo («bandeja» si no se ha escrito otro).
 *
 * @return string
 */
function mpc_gift_label() {
	$l = trim( mpc_bonus_cfg()['label1'] );
	return '' !== $l ? $l : 'bandeja';
}

/**
 * Fecha local «AAAA-MM-DD HH:MM» a marca de tiempo.
 *
 * @param string $v Fecha.
 * @return int 0 si está vacía o no es válida.
 */
function mpc_bonus_ts( $v ) {
	$v = trim( str_replace( 'T', ' ', (string) $v ) );
	if ( '' === $v ) {
		return 0;
	}
	try {
		return ( new DateTimeImmutable( $v, new DateTimeZone( 'Europe/Madrid' ) ) )->getTimestamp();
	} catch ( Exception $e ) {
		return 0;
	}
}

/**
 * ¿Esta visita es la vista previa privada?
 *
 * @return bool
 */
function mpc_bonus_is_preview() {
	$t = mpc_bonus_cfg()['token'];
	if ( '' === $t ) {
		return false;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo compara un token de vista previa.
	$got = isset( $_GET['vista'] ) ? sanitize_key( wp_unslash( $_GET['vista'] ) ) : ( isset( $_COOKIE['mpc_vista'] ) ? sanitize_key( wp_unslash( $_COOKIE['mpc_vista'] ) ) : '' );
	return '' !== $got && hash_equals( $t, $got );
}

/**
 * ¿Está visible algo con publicación y fechas? En la vista previa se ve todo lo configurado.
 *
 * @param array $x Elemento con pub, from y until.
 * @return bool
 */
function mpc_bonus_live( $x ) {
	if ( mpc_bonus_is_preview() ) {
		return true;
	}
	$from  = mpc_bonus_ts( $x['from'] );
	$until = mpc_bonus_ts( $x['until'] );
	return ! empty( $x['pub'] ) && ( ! $from || time() >= $from ) && ( ! $until || time() < $until );
}

/* La vista previa nunca se cachea y se recuerda con una cookie. */
add_action(
	'init',
	function () {
		if ( ! mpc_bonus_is_preview() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( isset( $_GET['vista'] ) && ! headers_sent() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			setcookie( 'mpc_vista', mpc_bonus_cfg()['token'], time() + DAY_IN_SECONDS, '/', '', true, true );
		}
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'vista previa de promo' );
	},
	0
);
add_action(
	'wp',
	function () {
		if ( mpc_bonus_is_preview() ) {
			do_action( 'litespeed_control_set_nocache', 'vista previa de promo' );
		}
	}
);

/**
 * Opciones publicadas de un regalo extra.
 *
 * @param int $slot 2 o 3.
 * @return WC_Product[]
 */
function mpc_bonus_choices( $slot ) {
	$cfg = mpc_bonus_cfg()['slots'][ $slot ];
	$ids = array_slice( array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', (string) $cfg['ids'] ) ) ), 0, MPC_GIFT_MAX_OPTIONS );
	if ( ! $ids || ! mpc_bonus_live( $cfg ) ) {
		return array();
	}
	$out = array();
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( $p && 'publish' === get_post_status( $id ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Texto de un regalo extra.
 *
 * @param int $slot 2 o 3.
 * @return string
 */
function mpc_bonus_label( $slot ) {
	$l = trim( (string) mpc_bonus_cfg()['slots'][ $slot ]['label'] );
	return '' !== $l ? $l : 'regalo';
}

/**
 * Regalos extra que se aplican: lo pedido si es válido y hay stock; si no, el primero con stock.
 *
 * @param array $requested Elección por regalo (slot => ID).
 * @return int[] slot => ID. Un regalo sin stock en ninguna opción no aparece.
 */
function mpc_bonus_picks( $requested = array() ) {
	$out = array();
	foreach ( MPC_BONUS_SLOTS as $slot ) {
		$ok = array();
		foreach ( mpc_bonus_choices( $slot ) as $p ) {
			if ( $p->is_in_stock() ) {
				$ok[] = (int) $p->get_id();
			}
		}
		if ( $ok ) {
			$want         = (int) ( $requested[ $slot ] ?? 0 );
			$out[ $slot ] = in_array( $want, $ok, true ) ? $want : $ok[0];
		}
	}
	return $out;
}

/**
 * Elección pedida desde la ficha.
 *
 * @return int[]
 */
function mpc_bonus_requested() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- La lista blanca decide.
	return isset( $_REQUEST['mpc_bonus'] ) && is_array( $_REQUEST['mpc_bonus'] ) ? array_map( 'absint', wp_unslash( $_REQUEST['mpc_bonus'] ) ) : array();
}

/**
 * Regalos extra de una línea del carrito (solo los que siguen en marcha).
 *
 * @param array $values Línea del carrito.
 * @return int[]
 */
function mpc_bonus_of_line( $values ) {
	return empty( $values['mpc_bonus'] ) ? array() : mpc_bonus_picks( (array) $values['mpc_bonus'] );
}

/**
 * Valor normal de los regalos extra, para el precio tachado del pack.
 *
 * @return float
 */
function mpc_bonus_value() {
	$sum = 0.0;
	foreach ( mpc_bonus_picks() as $id ) {
		$p    = wc_get_product( $id );
		$sum += $p ? (float) wc_get_price_to_display( $p, array( 'price' => (float) $p->get_regular_price() ) ) : 0.0;
	}
	return $sum;
}

/**
 * Selectores de los regalos extra en la ficha del pack.
 *
 * @return string
 */
function mpc_bonus_pick_html() {
	$h = '';
	foreach ( mpc_bonus_picks() as $slot => $default ) {
		$choices = mpc_bonus_choices( $slot );
		$many    = count( $choices ) > 1;
		$h      .= '<section class="mpc-q mpc-q--gift mpc-q--giftpick mpc-q--bonus"><h3 class="mpc-q__t">' . ( $many ? 'Elige tu ' : 'Tu ' ) . esc_html( mpc_bonus_label( $slot ) ) . ' <small>De regalo con tu pack</small></h3><div class="mpc-giftpick" role="radiogroup">';
		foreach ( $choices as $p ) {
			$id   = $p->get_id();
			$gone = ! $p->is_in_stock();
			$h   .= '<label class="mpc-giftopt' . ( $gone ? ' is-out' : '' ) . '"><input type="radio" name="mpc_bonus[' . (int) $slot . ']" value="' . (int) $id . '"' . checked( $id === $default, true, false ) . disabled( $gone, true, false ) . '>';
			$h   .= '<span class="mpc-giftopt__img">' . wp_kses_post( $p->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ) . '</span>';
			$h   .= '<span class="mpc-giftopt__name">' . esc_html( mpc_display_name( wp_strip_all_tags( $p->get_name() ) ) ) . '</span><span class="mpc-giftopt__tag">' . ( $gone ? 'Agotado' : 'Regalo' ) . '</span></label>';
		}
		$h .= '</div></section>';
	}
	return $h;
}

/* ---- Carrito y pedido ---- */
add_filter(
	'woocommerce_add_cart_item_data',
	function ( $data, $product_id ) {
		$p = wc_get_product( $product_id );
		if ( $p && mpc_is_pack( $p ) ) {
			$picks = mpc_bonus_picks( mpc_bonus_requested() );
			if ( $picks ) {
				$data['mpc_bonus'] = $picks;
			}
		}
		return $data;
	},
	11,
	2
);

/* Packs que ya estaban en el carrito antes de empezar la promo: también se llevan los regalos extra. */
add_filter(
	'woocommerce_get_cart_item_from_session',
	function ( $item ) {
		$p = wc_get_product( $item['product_id'] ?? 0 );
		if ( $p && mpc_is_pack( $p ) ) {
			$picks = mpc_bonus_picks( (array) ( $item['mpc_bonus'] ?? array() ) );
			if ( $picks ) {
				$item['mpc_bonus'] = $picks;
			}
		}
		return $item;
	},
	20
);

/**
 * Nombre de la línea de un regalo extra en carrito y pedido.
 *
 * @param int $slot 2 o 3.
 * @return string
 */
function mpc_bonus_line_key( $slot ) {
	$l = mpc_bonus_label( $slot );
	return 'Regalo (' . $l . ')';
}

add_filter(
	'woocommerce_get_item_data',
	function ( $data, $cart_item ) {
		foreach ( mpc_bonus_of_line( $cart_item ) as $slot => $id ) {
			$data[] = array(
				'key'   => mpc_bonus_line_key( $slot ),
				'value' => mpc_display_name( wp_strip_all_tags( get_the_title( $id ) ) ),
			);
		}
		return $data;
	},
	21,
	2
);

add_action(
	'woocommerce_checkout_create_order_line_item',
	function ( $item, $cart_key, $values ) {
		foreach ( mpc_bonus_of_line( $values ) as $slot => $id ) {
			$item->add_meta_data( mpc_bonus_line_key( $slot ), mpc_display_name( wp_strip_all_tags( get_the_title( $id ) ) ) );
		}
	},
	11,
	3
);

/* ---- Imagen del pack mientras dura la promo ---- */
/**
 * Sustituye la imagen del pack por la de la promo.
 *
 * @param int $image_id Imagen actual.
 * @param int $post_id Producto.
 * @return int
 */
function mpc_bonus_image( $image_id, $post_id ) {
	$cfg = mpc_bonus_cfg()['image'];
	$img = (int) $cfg['id'];
	if ( ! $img || ( is_admin() && ! wp_doing_ajax() ) || ! mpc_bonus_live( $cfg ) ) {
		return $image_id;
	}
	static $pack = null;
	if ( null === $pack ) {
		$pack = 0; // Evita recursión mientras se localiza el pack.
		$all  = mpc_get_packs();
		$pack = $all ? (int) $all[0]->get_id() : 0;
	}
	return $pack && (int) $post_id === $pack ? $img : $image_id;
}
add_filter(
	'woocommerce_product_get_image_id',
	function ( $image_id, $product ) {
		return mpc_bonus_image( $image_id, $product->get_id() );
	},
	20,
	2
);
add_filter(
	'post_thumbnail_id',
	function ( $image_id, $post ) {
		$post = get_post( $post );
		return $post && 'product' === $post->post_type ? mpc_bonus_image( $image_id, $post->ID ) : $image_id;
	},
	20,
	2
);

/* ---- Ajustes en la ficha del pack (dentro del cuadro «Regalos y extras del pack») ---- */
/**
 * Selector de productos del cuadro del pack.
 *
 * @param string $name Nombre del campo.
 * @param string $csv IDs guardados.
 */
function mpc_bonus_product_select( $name, $csv ) {
	echo '<select class="wc-product-search" multiple="multiple" style="width:100%" name="' . esc_attr( $name ) . '[]" data-placeholder="Busca un producto (hasta ' . (int) MPC_GIFT_MAX_OPTIONS . ')…" data-maximum-selection-length="' . (int) MPC_GIFT_MAX_OPTIONS . '" data-action="woocommerce_json_search_products" data-exclude_type="variable">';
	foreach ( array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', (string) $csv ) ) ) as $id ) {
		$p = wc_get_product( $id );
		if ( $p ) {
			echo '<option value="' . esc_attr( $id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $p->get_formatted_name() ) ) . '</option>';
		}
	}
	echo '</select>';
}

/**
 * Publicado y fechas.
 *
 * @param string $name Prefijo de los campos.
 * @param array  $x Valores.
 */
function mpc_bonus_date_fields( $name, $x ) {
	echo '<p style="display:flex;gap:16px;flex-wrap:wrap;align-items:center"><label><input type="checkbox" name="' . esc_attr( $name ) . '[pub]" value="1"' . checked( ! empty( $x['pub'] ), true, false ) . '> <strong>Publicado</strong></label>';
	foreach ( array(
		'from'  => 'Desde (hora de España)',
		'until' => 'Hasta',
	) as $k => $l ) {
		echo '<label>' . esc_html( $l ) . ' <input type="datetime-local" name="' . esc_attr( $name . '[' . $k . ']' ) . '" value="' . esc_attr( str_replace( ' ', 'T', $x[ $k ] ) ) . '"></label>';
	}
	$state = ( ! empty( $x['pub'] ) && ( ! mpc_bonus_ts( $x['from'] ) || time() >= mpc_bonus_ts( $x['from'] ) ) && ( ! mpc_bonus_ts( $x['until'] ) || time() < mpc_bonus_ts( $x['until'] ) ) );
	echo '<span class="description">' . ( $state ? 'Ahora: visible en la web' : 'Ahora: no se ve en la web' ) . '</span></p>';
}

/**
 * Regalos 2 y 3, imagen temporal y vista previa.
 *
 * @param WP_Post $post Pack.
 */
function mpc_bonus_box_fields( $post ) {
	$c = mpc_bonus_cfg();
	wp_nonce_field( 'mpc_bonus', 'mpc_bonus_nonce' );
	foreach ( MPC_BONUS_SLOTS as $n ) {
		$s = $c['slots'][ $n ];
		echo '<hr><h4 style="margin:12px 0 4px">Regalo ' . (int) $n . ' (opcional)</h4>';
		echo '<p><label>Qué es (texto que ve el cliente)<br><input type="text" name="mpc_bonus_slot[' . (int) $n . '][label]" value="' . esc_attr( $s['label'] ) . '" placeholder="Ej.: bollo sin azúcar" style="width:100%;max-width:420px"></label></p>';
		mpc_bonus_product_select( 'mpc_bonus_slot[' . (int) $n . '][ids]', $s['ids'] );
		mpc_bonus_date_fields( 'mpc_bonus_slot[' . (int) $n . ']', $s );
	}
	echo '<hr><h4 style="margin:12px 0 4px">Imagen temporal del pack (opcional)</h4><p><label>ID de la imagen en la biblioteca de medios<br><input type="number" min="0" name="mpc_bonus_image[id]" value="' . (int) $c['image']['id'] . '"></label>';
	if ( $c['image']['id'] ) {
		echo '<br>' . wp_get_attachment_image( (int) $c['image']['id'], array( 90, 90 ) );
	}
	echo '</p>';
	mpc_bonus_date_fields( 'mpc_bonus_image', $c['image'] );
	if ( $c['token'] ) {
		echo '<p><strong>Vista previa privada</strong> (enseña todo lo configurado, publicado o no): <a href="' . esc_url( add_query_arg( 'vista', $c['token'], get_permalink( $post->ID ) ) ) . '" target="_blank" rel="noopener">abrir</a></p>';
	}
	echo '<p class="description">Con una sola opción, el regalo va incluido; con dos o más, el cliente elige. Guarda el producto para aplicar los cambios.</p>';
}

/**
 * Vacía la caché al empezar y al terminar cada elemento.
 */
function mpc_bonus_schedule() {
	wp_unschedule_hook( 'mpc_bonus_purge' );
	$c = mpc_bonus_cfg();
	foreach ( array_merge( $c['slots'], array( $c['image'] ) ) as $n => $x ) {
		foreach ( array( 'from', 'until' ) as $k ) {
			$ts = mpc_bonus_ts( $x[ $k ] );
			if ( ! empty( $x['pub'] ) && $ts && $ts > time() ) {
				wp_schedule_single_event( $ts + 5, 'mpc_bonus_purge', array( $k . $n ) );
			}
		}
	}
}
add_action(
	'mpc_bonus_purge',
	function () {
		do_action( 'litespeed_purge_all' );
	}
);

add_action(
	'save_post_product',
	function ( $post_id ) {
		if ( empty( $_POST['mpc_bonus_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mpc_bonus_nonce'] ) ), 'mpc_bonus' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$c     = mpc_bonus_cfg();
		$dates = function ( $in ) {
			return array(
				'pub'   => empty( $in['pub'] ) ? 0 : 1,
				'from'  => str_replace( 'T', ' ', sanitize_text_field( $in['from'] ?? '' ) ),
				'until' => str_replace( 'T', ' ', sanitize_text_field( $in['until'] ?? '' ) ),
			);
		};
		$c['label1'] = sanitize_text_field( wp_unslash( $_POST['mpc_gift_label'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cada campo se sanea abajo.
		$slots = isset( $_POST['mpc_bonus_slot'] ) ? (array) wp_unslash( $_POST['mpc_bonus_slot'] ) : array();
		foreach ( MPC_BONUS_SLOTS as $n ) {
			$in               = (array) ( $slots[ $n ] ?? array() );
			$c['slots'][ $n ] = array_merge(
				array(
					'label' => sanitize_text_field( $in['label'] ?? '' ),
					'ids'   => implode( ',', array_slice( array_filter( array_map( 'absint', (array) ( $in['ids'] ?? array() ) ) ), 0, MPC_GIFT_MAX_OPTIONS ) ),
				),
				$dates( $in )
			);
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cada campo se sanea abajo.
		$img        = isset( $_POST['mpc_bonus_image'] ) ? (array) wp_unslash( $_POST['mpc_bonus_image'] ) : array();
		$c['image'] = array_merge( array( 'id' => absint( $img['id'] ?? 0 ) ), $dates( $img ) );
		$c['token'] = $c['token'] ? $c['token'] : strtolower( wp_generate_password( 14, false ) );
		update_option( 'mpc_bonus', $c );
		mpc_bonus_schedule();
		do_action( 'litespeed_purge_all' );
	}
);
