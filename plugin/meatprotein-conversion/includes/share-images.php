<?php
/**
 * Imágenes para compartir (Open Graph / X) de 1200 × 630 px, generadas solas para el pack, las hamburguesas y las categorías.
 * Se rehacen cuando cambia lo que muestran (título, precio, proteína, foto o regalo) y se guardan en uploads/mpc-share/.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carpeta y URL de las imágenes generadas. */
function mpc_share_dir() {
	$u = wp_upload_dir();
	return array( trailingslashit( $u['basedir'] ) . 'mpc-share/', trailingslashit( $u['baseurl'] ) . 'mpc-share/' );
}

/**
 * Datos que dibuja la imagen de un producto o categoría.
 *
 * @param string $type  product|term.
 * @param int    $id    ID.
 * @return array|null
 */
function mpc_share_data( $type, $id ) {
	if ( 'term' === $type ) {
		$t = get_term( $id, 'product_cat' );
		if ( ! $t || is_wp_error( $t ) ) {
			return null;
		}
		$thumb = (int) get_term_meta( $id, 'thumbnail_id', true );
		if ( ! $thumb ) {
			return null;
		}
		return array(
			'slug'  => 'cat-' . $t->slug,
			'title' => $t->name,
			'price' => '',
			'old'   => '',
			'pill'  => $t->count . ' productos',
			'foot'  => 'Envío refrigerado · Gratis con el pack o 10 bandejas',
			'img'   => $thumb,
		);
	}
	$p = wc_get_product( $id );
	if ( ! $p || 'publish' !== $p->get_status() ) {
		return null;
	}
	$img = (int) $p->get_image_id();
	if ( ! $img ) {
		return null;
	}
	$name = function_exists( 'mpc_display_name' ) ? mpc_display_name( $p->get_name() ) : $p->get_name();
	$foot = 'Envío refrigerado · Gratis con el pack o 10 bandejas';
	if ( function_exists( 'mpc_is_pack' ) && mpc_is_pack( $p ) ) {
		$vars  = $p->get_available_variations();
		$sale  = $vars ? (float) $vars[0]['display_price'] : (float) $p->get_price();
		$reg   = $vars ? (float) $vars[0]['display_regular_price'] : (float) $p->get_regular_price();
		$extra = function_exists( 'mpc_pack_extras' ) ? mpc_pack_extras( $p->get_name() ) : array();
		$name  = trim( preg_replace( '/\s+Meatprotein$/iu', '', $name ) );
		return array(
			'slug'  => $p->get_slug(),
			'title' => $name,
			'price' => $sale ? number_format( $sale, 2, ',', '.' ) . ' €' : '',
			'old'   => $reg > $sale ? number_format( $reg, 2, ',', '.' ) . ' €' : '',
			'pill'  => $extra ? preg_replace( '/^1 bandeja de /iu', 'Incluye ', $extra[0] ) : '10 bandejas · 40 hamburguesas',
			'foot'  => 'Tú eliges los sabores · Envío gratis',
			'img'   => $img,
		);
	}
	$n    = function_exists( 'mpc_get_label_data' ) ? mpc_get_label_data( $id ) : array();
	$pill = '';
	if ( ! empty( $n['proteinas'] ) ) {
		$pill = str_replace( '.', ',', (string) (float) $n['proteinas'] ) . ' g de proteína por 100 g';
	} elseif ( has_term( 'hamburguesas', 'product_cat', $id ) ) {
		$pill = 'Bandeja de 400 g';
	}
	$reg   = (float) $p->get_regular_price();
	$price = (float) $p->get_price();
	return array(
		'slug'  => $p->get_slug(),
		'title' => $name,
		'price' => $price ? number_format( $price, 2, ',', '.' ) . ' €' : '',
		'old'   => $reg > $price ? number_format( $reg, 2, ',', '.' ) . ' €' : '',
		'pill'  => $pill,
		'foot'  => $foot,
		'img'   => $img,
	);
}

/** Huella de lo que se dibuja: si cambia, se regenera. */
function mpc_share_hash( array $d ) {
	$f = get_attached_file( $d['img'] );
	return substr( md5( wp_json_encode( array( $d['title'], $d['price'], $d['old'], $d['pill'], $d['foot'], $d['img'], $f && file_exists( $f ) ? filemtime( $f ) : 0, 5 ) ) ), 0, 10 );
}

/** Rectángulo redondeado relleno. */
function mpc_share_rrect( $im, $x1, $y1, $x2, $y2, $r, $col ) {
	imagefilledrectangle( $im, $x1 + $r, $y1, $x2 - $r, $y2, $col );
	imagefilledrectangle( $im, $x1, $y1 + $r, $x2, $y2 - $r, $col );
	imagefilledellipse( $im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $col );
	imagefilledellipse( $im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $col );
	imagefilledellipse( $im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $col );
	imagefilledellipse( $im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $col );
}

/** Divide un texto en líneas que caben en $w píxeles. */
function mpc_share_wrap( $text, $font, $size, $w, $max = 3 ) {
	$words = preg_split( '/\s+/u', trim( $text ) );
	$lines = array();
	$cur   = '';
	foreach ( $words as $word ) {
		$try = '' === $cur ? $word : $cur . ' ' . $word;
		$bb  = imagettfbbox( $size, 0, $font, $try );
		if ( $bb[2] - $bb[0] > $w && '' !== $cur ) {
			$lines[] = $cur;
			$cur     = $word;
		} else {
			$cur = $try;
		}
	}
	if ( '' !== $cur ) {
		$lines[] = $cur;
	}
	if ( count( $lines ) > $max ) {
		$lines    = array_slice( $lines, 0, $max );
		$lines[ $max - 1 ] = rtrim( $lines[ $max - 1 ], '.,; ' ) . '…';
	}
	return $lines;
}

/**
 * Dibuja y guarda la imagen. Devuelve la URL o '' si no se pudo.
 *
 * @param string $type product|term.
 * @param int    $id   ID.
 * @param bool   $force Regenerar aunque la huella no haya cambiado.
 * @return string
 */
function mpc_share_build( $type, $id, $force = false ) {
	if ( ! function_exists( 'imagettftext' ) ) {
		return '';
	}
	$d = mpc_share_data( $type, (int) $id );
	if ( ! $d ) {
		return '';
	}
	list( $dir, $url ) = mpc_share_dir();
	$file = $dir . $d['slug'] . '.jpg';
	$hash = mpc_share_hash( $d );
	$key  = 'term' === $type ? get_term_meta( $id, '_mpc_share_hash', true ) : get_post_meta( $id, '_mpc_share_hash', true );
	if ( ! $force && $key === $hash && file_exists( $file ) ) {
		return $url . $d['slug'] . '.jpg?v=' . $hash;
	}
	$src_path = get_attached_file( $d['img'] );
	$raw      = $src_path && file_exists( $src_path ) ? file_get_contents( $src_path ) : ''; // phpcs:ignore WordPress.WP_Filesystem.FileSystemReadsDisallowed
	$src      = $raw ? @imagecreatefromstring( $raw ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! $src ) {
		return '';
	}
	$S      = 2; // Se dibuja al doble y se reduce: bordes suaves.
	$W      = 1200 * $S;
	$H      = 630 * $S;
	$dela   = MPC_DIR . 'assets/fonts/DelaGothicOne-Regular.ttf';
	$sans   = MPC_DIR . 'assets/fonts/InstrumentSans.ttf';
	$im     = imagecreatetruecolor( $W, $H );
	$sky    = imagecolorallocate( $im, 205, 230, 242 );
	$ink    = imagecolorallocate( $im, 11, 11, 11 );
	$pink   = imagecolorallocate( $im, 194, 58, 122 );
	$green  = imagecolorallocate( $im, 20, 87, 59 );
	$grey   = imagecolorallocate( $im, 74, 85, 96 );
	$white  = imagecolorallocate( $im, 255, 255, 255 );
	imagefilledrectangle( $im, 0, 0, $W, $H, $sky );
	// Tarjeta blanca con la foto.
	$cx1 = 650 * $S;
	$cy1 = 75 * $S;
	$cs  = 480 * $S;
	mpc_share_rrect( $im, $cx1, $cy1, $cx1 + $cs, $cy1 + $cs, 40 * $S, $white );
	$sw = imagesx( $src );
	$sh = imagesy( $src );
	$pad = 20 * $S;
	$k  = min( ( $cs - 2 * $pad ) / $sw, ( $cs - 2 * $pad ) / $sh );
	$dw = (int) round( $sw * $k );
	$dh = (int) round( $sh * $k );
	imagecopyresampled( $im, $src, $cx1 + (int) ( ( $cs - $dw ) / 2 ), $cy1 + (int) ( ( $cs - $dh ) / 2 ), 0, 0, $dw, $dh, $sw, $sh );
	imagedestroy( $src );
	// Texto.
	$x = 64 * $S;
	imagettftext( $im, 20 * $S, 0, $x, 92 * $S, $pink, $dela, 'MEATPROTEIN' );
	$tw = 530 * $S;
	$ts = 46 * $S;
	// Si una palabra sola no cabe, se reduce la letra (mínimo 30 px) para no invadir la tarjeta.
	foreach ( preg_split( '/\s+/u', $d['title'] ) as $word ) {
		while ( $ts > 30 * $S ) {
			$bb = imagettfbbox( $ts, 0, $dela, $word );
			if ( $bb[2] - $bb[0] <= $tw ) {
				break;
			}
			$ts -= 2 * $S;
		}
	}
	// Si no cabe en 3 líneas, se reduce la letra antes de cortar el título.
	while ( $ts > 32 * $S && count( mpc_share_wrap( $d['title'], $dela, $ts, $tw, 9 ) ) > 3 ) {
		$ts -= 2 * $S;
	}
	$lines = mpc_share_wrap( $d['title'], $dela, $ts, $tw, 3 );
	$y     = 160 * $S;
	foreach ( $lines as $ln ) {
		imagettftext( $im, $ts, 0, $x, $y, $ink, $dela, $ln );
		$y += (int) ( $ts * 1.35 );
	}
	if ( '' !== $d['price'] ) {
		$y += 14 * $S;
		imagettftext( $im, 52 * $S, 0, $x, $y, $pink, $dela, $d['price'] );
		if ( '' !== $d['old'] ) {
			$bb = imagettfbbox( 52 * $S, 0, $dela, $d['price'] );
			$ox = $x + ( $bb[2] - $bb[0] ) + 24 * $S;
			imagettftext( $im, 28 * $S, 0, $ox, $y, $grey, $dela, $d['old'] );
			$ob = imagettfbbox( 28 * $S, 0, $dela, $d['old'] );
			imagesetthickness( $im, 3 * $S );
			imageline( $im, $ox, $y - 10 * $S, $ox + ( $ob[2] - $ob[0] ), $y - 10 * $S, $grey );
		}
		$y += 30 * $S;
	} else {
		$y += 10 * $S;
	}
	if ( '' !== $d['pill'] ) {
		$ps = 26 * $S;
		while ( $ps > 17 * $S ) {
			$bb = imagettfbbox( $ps, 0, $sans, $d['pill'] );
			if ( ( $bb[2] - $bb[0] ) + 44 * $S <= 560 * $S ) {
				break;
			}
			$ps -= 1 * $S;
		}
		$bb = imagettfbbox( $ps, 0, $sans, $d['pill'] );
		$pw = ( $bb[2] - $bb[0] ) + 44 * $S;
		mpc_share_rrect( $im, $x, $y, $x + $pw, $y + 54 * $S, 27 * $S, $white );
		imagettftext( $im, $ps, 0, $x + 22 * $S, $y + 36 * $S, $green, $sans, $d['pill'] );
	}
	imagettftext( $im, 22 * $S, 0, $x, 580 * $S, $grey, $sans, $d['foot'] );
	// Reducir y guardar.
	$out = imagecreatetruecolor( 1200, 630 );
	imagecopyresampled( $out, $im, 0, 0, 0, 0, 1200, 630, $W, $H );
	imagedestroy( $im );
	wp_mkdir_p( $dir );
	imagejpeg( $out, $file, 88 );
	imagedestroy( $out );
	if ( 'term' === $type ) {
		update_term_meta( $id, '_mpc_share_hash', $hash );
	} else {
		update_post_meta( $id, '_mpc_share_hash', $hash );
	}
	return $url . $d['slug'] . '.jpg?v=' . $hash;
}

/** Imagen para compartir de la página actual (la genera si falta o cambió). */
function mpc_share_current() {
	static $cache = array();
	if ( is_product() ) {
		$id = get_queried_object_id();
		if ( ! isset( $cache[ 'p' . $id ] ) ) {
			$cache[ 'p' . $id ] = mpc_share_build( 'product', $id );
		}
		return $cache[ 'p' . $id ];
	}
	if ( is_tax( 'product_cat' ) ) {
		$id = get_queried_object_id();
		if ( ! isset( $cache[ 't' . $id ] ) ) {
			$cache[ 't' . $id ] = mpc_share_build( 'term', $id );
		}
		return $cache[ 't' . $id ];
	}
	return '';
}

foreach ( array( 'rank_math/opengraph/facebook/image', 'rank_math/opengraph/twitter/image' ) as $mpc_hook ) {
	add_filter(
		$mpc_hook,
		function ( $image ) {
			if ( is_admin() ) {
				return $image;
			}
			$u = mpc_share_current();
			return $u ? $u : $image;
		},
		99
	);
}
foreach ( array( 'rank_math/opengraph/facebook/image_width' => 1200, 'rank_math/opengraph/facebook/image_height' => 630 ) as $mpc_hook => $mpc_val ) {
	add_filter(
		$mpc_hook,
		function ( $v ) use ( $mpc_val ) {
			return ( ! is_admin() && mpc_share_current() ) ? $mpc_val : $v;
		},
		99
	);
}

/** Al guardar un producto se rehace en el acto (si cambió algo). */
add_action(
	'woocommerce_update_product',
	function ( $id ) {
		if ( function_exists( 'mpc_share_build' ) && 'publish' === get_post_status( $id ) ) {
			mpc_share_build( 'product', $id );
		}
	},
	40
);
add_action(
	'edited_product_cat',
	function ( $id ) {
		mpc_share_build( 'term', $id );
	},
	40
);

/**
 * Regenera todas (pack, hamburguesas y categorías). Uso: wp eval 'mpc_share_build_all( true );'
 *
 * @param bool $force Regenerar aunque no haya cambiado nada.
 * @return int Imágenes generadas.
 */
function mpc_share_build_all( $force = false ) {
	$n   = 0;
	$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( 'hamburguesas' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( mpc_get_packs() as $pk ) {
		$ids[] = $pk->get_id();
	}
	foreach ( array_unique( $ids ) as $id ) {
		if ( mpc_share_build( 'product', $id, $force ) ) {
			++$n;
		}
	}
	foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ) ) as $tid ) {
		if ( mpc_share_build( 'term', $tid, $force ) ) {
			++$n;
		}
	}
	return $n;
}
