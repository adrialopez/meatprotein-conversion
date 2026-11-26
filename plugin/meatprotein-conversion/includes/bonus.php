<?php
/**
 * Promo con regalo extra en el pack: además del regalo habitual, el cliente elige un segundo regalo (p. ej. un bollo).
 *
 * Se configura en la ficha del pack (cuadro «Promo: regalo extra»). Solo está activa entre las fechas indicadas;
 * antes de eso se puede revisar con el enlace de vista previa sin que los clientes vean nada.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configuración guardada de la promo.
 *
 * @return array
 */
function mpc_bonus_cfg() {
	$c = get_option( 'mpc_bonus', array() );
	return array_merge(
		array(
			'ids'   => '',
			'label' => 'bollo sin azúcar',
			'from'  => '',
			'until' => '',
			'image' => 0,
			'token' => '',
		),
		is_array( $c ) ? $c : array()
	);
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
		return ( new DateTimeImmutable( $v, wp_timezone() ) )->getTimestamp();
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
 * La promo está en marcha para esta visita.
 *
 * @return bool
 */
function mpc_bonus_active() {
	$c = mpc_bonus_cfg();
	if ( '' === trim( $c['ids'] ) ) {
		return false;
	}
	if ( mpc_bonus_is_preview() ) {
		return true;
	}
	$from  = mpc_bonus_ts( $c['from'] );
	$until = mpc_bonus_ts( $c['until'] );
	return $from && time() >= $from && ( ! $until || time() < $until );
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
 * Opciones del regalo extra, publicadas.
 *
 * @return WC_Product[]
 */
function mpc_bonus_choices() {
	if ( ! mpc_bonus_active() ) {
		return array();
	}
	$out = array();
	foreach ( array_filter( array_map( 'intval', preg_split( '/[\s,;]+/', mpc_bonus_cfg()['ids'] ) ) ) as $id ) {
		$p = wc_get_product( $id );
		if ( $p && 'publish' === get_post_status( $id ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Regalo extra que se aplica: el pedido si es válido y hay stock; si no, el primero con stock.
 *
 * @param int $requested ID elegido.
 * @return int 0 si no hay promo o no queda ninguno.
 */
function mpc_bonus_pick( $requested = 0 ) {
	$ok = array();
	foreach ( mpc_bonus_choices() as $p ) {
		if ( $p->is_in_stock() ) {
			$ok[] = (int) $p->get_id();
		}
	}
	if ( ! $ok ) {
		return 0;
	}
	return in_array( (int) $requested, $ok, true ) ? (int) $requested : $ok[0];
}

/**
 * Regalo extra pedido desde la ficha.
 *
 * @return int
 */
function mpc_bonus_requested() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- La lista blanca decide.
	return isset( $_REQUEST['mpc_bonus'] ) ? absint( wp_unslash( $_REQUEST['mpc_bonus'] ) ) : 0;
}

/**
 * Regalo extra de una línea del carrito (0 si la promo ya no está en marcha).
 *
 * @param array $values Línea del carrito.
 * @return int
 */
function mpc_bonus_of_line( $values ) {
	return empty( $values['mpc_bonus'] ) ? 0 : mpc_bonus_pick( (int) $values['mpc_bonus'] );
}

/**
 * Precio normal del regalo extra, para el precio tachado del pack.
 *
 * @return float
 */
function mpc_bonus_value() {
	$id = mpc_bonus_pick( 0 );
	$p  = $id ? wc_get_product( $id ) : null;
	return $p ? (float) wc_get_price_to_display( $p, array( 'price' => (float) $p->get_regular_price() ) ) : 0.0;
}

/**
 * Selector del regalo extra en la ficha del pack.
 *
 * @return string
 */
function mpc_bonus_pick_html() {
	$choices = mpc_bonus_choices();
	$default = mpc_bonus_pick( 0 );
	if ( ! $choices || ! $default ) {
		return '';
	}
	$h = '<section class="mpc-q mpc-q--gift mpc-q--giftpick mpc-q--bonus"><h3 class="mpc-q__t">Elige tu ' . esc_html( mpc_bonus_cfg()['label'] ) . ' <small>De regalo con tu pack</small></h3><div class="mpc-giftpick" role="radiogroup">';
	foreach ( $choices as $p ) {
		$id   = $p->get_id();
		$gone = ! $p->is_in_stock();
		$h   .= '<label class="mpc-giftopt' . ( $gone ? ' is-out' : '' ) . '"><input type="radio" name="mpc_bonus" value="' . (int) $id . '"' . checked( $id === $default, true, false ) . disabled( $gone, true, false ) . '>';
		$h   .= '<span class="mpc-giftopt__img">' . wp_kses_post( $p->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) ) . '</span>';
		$h   .= '<span class="mpc-giftopt__name">' . esc_html( mpc_display_name( wp_strip_all_tags( $p->get_name() ) ) ) . '</span><span class="mpc-giftopt__tag">' . ( $gone ? 'Agotado' : 'Regalo' ) . '</span></label>';
	}
	return $h . '</div></section>';
}

/* ---- Carrito y pedido ---- */
add_filter(
	'woocommerce_add_cart_item_data',
	function ( $data, $product_id ) {
		$p = wc_get_product( $product_id );
		if ( $p && mpc_is_pack( $p ) ) {
			$id = mpc_bonus_pick( mpc_bonus_requested() );
			if ( $id ) {
				$data['mpc_bonus'] = $id;
			}
		}
		return $data;
	},
	11,
	2
);

/* Packs que ya estaban en el carrito antes de empezar la promo: también se llevan el regalo extra. */
add_filter(
	'woocommerce_get_cart_item_from_session',
	function ( $item ) {
		if ( empty( $item['mpc_bonus'] ) && mpc_bonus_active() ) {
			$p = wc_get_product( $item['product_id'] ?? 0 );
			if ( $p && mpc_is_pack( $p ) ) {
				$item['mpc_bonus'] = mpc_bonus_pick( 0 );
			}
		}
		return $item;
	},
	20
);

add_filter(
	'woocommerce_get_item_data',
	function ( $data, $cart_item ) {
		$id = mpc_bonus_of_line( $cart_item );
		if ( $id ) {
			$data[] = array(
				'key'   => 'Regalo extra',
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
		$id = mpc_bonus_of_line( $values );
		if ( $id ) {
			$item->add_meta_data( 'Regalo extra', mpc_display_name( wp_strip_all_tags( get_the_title( $id ) ) ) );
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
	$img = (int) mpc_bonus_cfg()['image'];
	if ( ! $img || ( is_admin() && ! wp_doing_ajax() ) || ! mpc_bonus_active() ) {
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

/* ---- Ajustes en la ficha del pack ---- */
add_action(
	'add_meta_boxes_product',
	function ( $post ) {
		$p = wc_get_product( $post->ID );
		if ( $p && mpc_is_pack( $p ) ) {
			add_meta_box( 'mpc_bonus', 'Meatprotein: promo con regalo extra', 'mpc_bonus_box', 'product', 'normal', 'default' );
		}
	}
);

/**
 * Cuadro de la promo.
 */
function mpc_bonus_box() {
	$c = mpc_bonus_cfg();
	wp_nonce_field( 'mpc_bonus', 'mpc_bonus_nonce' );
	$f = function ( $key, $label, $type, $help ) use ( $c ) {
		$v = 'datetime-local' === $type ? str_replace( ' ', 'T', $c[ $key ] ) : $c[ $key ];
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input type="' . esc_attr( $type ) . '" name="mpc_bonus_cfg[' . esc_attr( $key ) . ']" value="' . esc_attr( $v ) . '" style="width:100%;max-width:420px"></label><br><span class="description">' . esc_html( $help ) . '</span></p>';
	};
	$f( 'ids', 'Productos a elegir (IDs separados por comas)', 'text', 'Vacío = sin promo. El cliente elige uno.' );
	$f( 'label', 'Cómo se llama en la web', 'text', 'Ejemplo: bollo sin azúcar' );
	$f( 'from', 'Empieza', 'datetime-local', 'Hora de España. Vacío = no se publica.' );
	$f( 'until', 'Termina', 'datetime-local', 'Vacío = sin fecha de fin.' );
	$f( 'image', 'Imagen del pack durante la promo (ID de la biblioteca)', 'number', 'Vacío = la imagen normal.' );
	if ( $c['token'] ) {
		echo '<p><strong>Vista previa privada:</strong> <a href="' . esc_url( add_query_arg( 'vista', $c['token'], get_permalink() ) ) . '" target="_blank" rel="noopener">abrir</a></p>';
	}
}

/**
 * Vacía la caché al empezar y al terminar la promo.
 */
function mpc_bonus_schedule() {
	wp_clear_scheduled_hook( 'mpc_bonus_purge' );
	$c = mpc_bonus_cfg();
	foreach ( array( 'from', 'until' ) as $k ) {
		$ts = mpc_bonus_ts( $c[ $k ] );
		if ( $ts && $ts > time() ) {
			wp_schedule_single_event( $ts + 5, 'mpc_bonus_purge', array( $k ) );
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
		$in = isset( $_POST['mpc_bonus_cfg'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['mpc_bonus_cfg'] ) ) : array();
		$c  = mpc_bonus_cfg();
		foreach ( array( 'ids', 'label', 'from', 'until' ) as $k ) {
			$c[ $k ] = str_replace( 'T', ' ', $in[ $k ] ?? '' );
		}
		$c['image'] = absint( $in['image'] ?? 0 );
		$c['token'] = $c['token'] ? $c['token'] : strtolower( wp_generate_password( 14, false ) );
		update_option( 'mpc_bonus', $c );
		mpc_bonus_schedule();
		do_action( 'litespeed_purge_all' );
	}
);
