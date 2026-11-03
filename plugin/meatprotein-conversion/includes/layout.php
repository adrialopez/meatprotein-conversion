<?php
/**
 * Monta el diseño con las herramientas de Betheme en lugar de taparlo con CSS o moverlo con JS:
 * - la portada: secciones del constructor con un elemento «Código» (hero, franja de confianza y «Compra con tranquilidad»);
 * - el menú: «Packs» como elemento real del menú principal (barra superior y panel lateral);
 * - el panel lateral (plantilla de Betheme): su pie con envío gratis y teléfono.
 * Se aplica desde WooCommerce > Conversión o con `wp mpc layout apply`; `restore` lo deja como estaba.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secciones de la portada original que el nuevo diseño sustituye (uid de Betheme).
 *
 * @return string[]
 */
function mpc_layout_obsolete_uids() {
	return array( 'd377ff003', '1adb9246c', 'e8d6c5d57' ); // hero original, promoción global repetida y «Burgers saludables».
}

/**
 * Lee los elementos del constructor de Betheme de una entrada.
 *
 * @param int $post_id ID de entrada.
 * @return mixed Resultado.
 */
function mpc_builder_get( $post_id ) {
	$raw = get_post_meta( $post_id, 'mfn-page-items', true );
	$arr = $raw ? @unserialize( base64_decode( $raw ) ) : null; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.NoSilencedErrors
	return is_array( $arr ) ? $arr : null;
}

/**
 * Guarda los elementos del constructor de Betheme de una entrada.
 *
 * @param int   $post_id ID de entrada.
 * @param array $items Elementos del constructor.
 * @return mixed Resultado.
 */
function mpc_builder_put( $post_id, $items ) {
	update_post_meta( $post_id, 'mfn-page-items', base64_encode( serialize( $items ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	delete_post_meta( $post_id, 'mfn-page-object' ); // El editor visual lo regenera desde los elementos.
}

/**
 * Sección de Betheme con un único elemento «Código».
 *
 * @param string $uid UID de Betheme.
 * @param string $shortcode Shortcode.
 * @return mixed Resultado.
 */
function mpc_builder_code_section( $uid, $shortcode ) {
	$one = array(
		'size'           => '1/1',
		'tablet_size'    => '1/1',
		'laptop_size'    => '1/1',
		'mobile_size'    => '1/1',
		'tablet_resized' => '0',
	);
	return array(
		'icon'    => 'section',
		'jsclass' => 'section',
		'title'   => 'Section',
		'uid'     => $uid,
		'attr'    => array(
			'classes'           => 'mpc-sec',
			'scroll-visibility' => 'show',
		),
		'ver'     => 'default',
		'wraps'   => array(
			array_merge(
				$one,
				array(
					'icon'    => 'wrap',
					'jsclass' => 'wrap',
					'title'   => 'Wrap',
					'uid'     => $uid . 'w',
					'attr'    => array(),
					'items'   => array(
						array_merge(
							$one,
							array(
								'type'    => 'code',
								'jsclass' => 'code',
								'title'   => 'Code',
								'icon'    => 'code',
								'uid'     => $uid . 'c',
								'attr'    => array( 'content' => $shortcode ),
							)
						),
					),
				)
			),
		),
	);
}

/**
 * Indica si los elementos ya contienen un shortcode.
 *
 * @param array  $items Elementos del constructor.
 * @param string $shortcode Shortcode.
 * @return mixed Resultado.
 */
function mpc_layout_has_code( $items, $shortcode ) {
	return false !== strpos( wp_json_encode( $items ), str_replace( '/', '\\/', $shortcode ) );
}

/**
 * ID del menú principal.
 *
 * @return mixed Resultado.
 */
function mpc_layout_menu_id() {
	$menu = wp_get_nav_menu_object( 'menu-principal' );
	if ( ! $menu ) {
		$menu = wp_get_nav_menu_object( 'Menú principal' );
	}
	return $menu ? (int) $menu->term_id : 0;
}

/** Plantilla de Betheme del panel lateral del menú (la que lleva #menu-categorias). */
function mpc_layout_sidemenu_template() {
	$posts = get_posts(
		array(
			'post_type'      => 'template',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'fields'         => 'ids',
		)
	);
	foreach ( $posts as $id ) {
		if ( 'sidemenu' === get_post_meta( $id, 'mfn_template_type', true ) ) {
			return (int) $id;
		}
	}
	return 0;
}

/**
 * Aplica el diseño en Betheme (portada, menú y panel lateral).
 *
 * @return mixed Resultado.
 */
function mpc_layout_apply() {
	$msgs = array();
	$home = (int) get_option( 'page_on_front' );
	$bk   = (array) get_option( 'mpc_layout_backup', array() );

	/* 1) Portada */
	$items = $home ? mpc_builder_get( $home ) : null;
	if ( $items ) {
		if ( ! isset( $bk['home'] ) ) {
			$bk['home'] = array(
				'id'     => $home,
				'items'  => get_post_meta( $home, 'mfn-page-items', true ),
				'object' => get_post_meta( $home, 'mfn-page-object', true ),
			);
		}
		if ( ! mpc_layout_has_code( $items, '[mpc_hero]' ) ) {
			$keep = array();
			foreach ( $items as $sec ) {
				if ( ! in_array( $sec['uid'] ?? '', mpc_layout_obsolete_uids(), true ) ) {
					$keep[] = $sec;
				}
			}
			$out = array( mpc_builder_code_section( 'mpchero01', '[mpc_hero]' ), mpc_builder_code_section( 'mpcstrip1', '[mpc_strip]' ) );
			foreach ( $keep as $sec ) {
				if ( '6b7281057' === ( $sec['uid'] ?? '' ) ) {
					$out[] = mpc_builder_code_section( 'mpcassur1', '[mpc_assure]' ); // Justo antes de la newsletter.
				}
				$out[] = $sec;
			}
			if ( ! mpc_layout_has_code( $out, '[mpc_assure]' ) ) {
				$out[] = mpc_builder_code_section( 'mpcassur1', '[mpc_assure]' );
			}
			mpc_builder_put( $home, $out );
			$msgs[] = 'Portada montada en el constructor de Betheme (' . count( $out ) . ' secciones).';
		} else {
			$msgs[] = 'La portada ya estaba montada.';
		}
		update_option( 'mpc_layout_native', 1 );
	} else {
		$msgs[] = 'No se ha encontrado la portada con constructor de Betheme.';
	}

	/* 2) «Packs» en el menú principal */
	$menu = mpc_layout_menu_id();
	$page = get_page_by_path( 'packs' );
	if ( $menu && $page ) {
		$exists = false;
		foreach ( (array) wp_get_nav_menu_items( $menu ) as $mi ) {
			if ( (int) $mi->object_id === (int) $page->ID ) {
				$exists = true;
			}
		}
		if ( ! $exists ) {
			$id = wp_update_nav_menu_item(
				$menu,
				0,
				array(
					'menu-item-title'     => 'PACKS',
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $page->ID,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => 1,
				)
			);
			if ( ! is_wp_error( $id ) ) {
				$bk['menu_item'] = (int) $id;
				/* El resto de elementos pasan a continuación. */
				$pos = 2;
				foreach ( (array) wp_get_nav_menu_items( $menu ) as $mi ) {
					if ( (int) $mi->ID !== (int) $id && ! $mi->menu_item_parent ) {
						wp_update_nav_menu_item(
							$menu,
							$mi->ID,
							array(
								'menu-item-position'  => $pos++,
								'menu-item-title'     => $mi->title,
								'menu-item-url'       => $mi->url,
								'menu-item-object'    => $mi->object,
								'menu-item-object-id' => $mi->object_id,
								'menu-item-type'      => $mi->type,
								'menu-item-status'    => 'publish',
							)
						);
					}
				}
				$msgs[] = 'Añadido «Packs» al menú principal.';
			}
		} else {
			$msgs[] = '«Packs» ya estaba en el menú.';
		}
	}

	/* 3) Pie del panel lateral */
	$side = mpc_layout_sidemenu_template();
	if ( $side ) {
		$sitems = mpc_builder_get( $side );
		if ( $sitems && ! mpc_layout_has_code( $sitems, '[mpc_menu_foot]' ) ) {
			$bk['sidemenu'] = array(
				'id'    => $side,
				'items' => get_post_meta( $side, 'mfn-page-items', true ),
			);
			$last           = count( $sitems ) - 1;
			$wi             = isset( $sitems[ $last ]['wraps'] ) ? count( $sitems[ $last ]['wraps'] ) - 1 : -1;
			if ( $wi >= 0 ) {
				$code                                       = mpc_builder_code_section( 'mpcmenuf1', '[mpc_menu_foot]' );
				$sitems[ $last ]['wraps'][ $wi ]['items'][] = $code['wraps'][0]['items'][0];
				mpc_builder_put( $side, $sitems );
				$msgs[] = 'Pie añadido a la plantilla del panel lateral.';
			}
		}
	}

	update_option( 'mpc_layout_backup', $bk, false );
	return $msgs;
}

/**
 * Restaura el diseño original guardado antes de aplicar el nuevo.
 *
 * @return mixed Resultado.
 */
function mpc_layout_restore() {
	$bk   = (array) get_option( 'mpc_layout_backup', array() );
	$msgs = array();
	if ( ! empty( $bk['home'] ) ) {
		update_post_meta( $bk['home']['id'], 'mfn-page-items', $bk['home']['items'] );
		if ( $bk['home']['object'] ) {
			update_post_meta( $bk['home']['id'], 'mfn-page-object', $bk['home']['object'] );
		}
		$msgs[] = 'Portada original restaurada.';
	}
	if ( ! empty( $bk['menu_item'] ) ) {
		wp_delete_post( $bk['menu_item'], true );
		$msgs[] = 'Quitado «Packs» del menú.';
	}
	if ( ! empty( $bk['sidemenu'] ) ) {
		update_post_meta( $bk['sidemenu']['id'], 'mfn-page-items', $bk['sidemenu']['items'] );
		$msgs[] = 'Plantilla del panel lateral restaurada.';
	}
	delete_option( 'mpc_layout_native' );
	delete_option( 'mpc_layout_backup' );
	return empty( $msgs ) ? array( 'No había nada que restaurar.' ) : $msgs;
}

/* WP-CLI: wp mpc layout apply | restore */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'mpc layout',
		function ( $args ) {
			$out = ( isset( $args[0] ) && 'restore' === $args[0] ) ? mpc_layout_restore() : mpc_layout_apply();
			foreach ( $out as $m ) {
				WP_CLI::log( $m );
			}
			WP_CLI::success( 'Hecho.' );
		}
	);
}

/* Botones en WooCommerce > Conversión */
add_action(
	'admin_post_mpc_layout',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mpc_layout' ) ) {
			wp_die( 'No autorizado.' );
		}
		$do   = isset( $_POST['do'] ) && 'restore' === $_POST['do'] ? 'restore' : 'apply';
		$msgs = 'restore' === $do ? mpc_layout_restore() : mpc_layout_apply();
		set_transient( 'mpc_layout_msg', $msgs, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=mpc' ) );
		exit;
	}
);

/**
 * Caja de ajustes con los botones para aplicar o restaurar el diseño.
 *
 * @return mixed Resultado.
 */
function mpc_layout_admin_box() {
	$msgs = get_transient( 'mpc_layout_msg' );
	if ( $msgs ) {
		delete_transient( 'mpc_layout_msg' );
		echo '<div class="notice notice-success"><p>' . esc_html( implode( ' ', (array) $msgs ) ) . '</p></div>';
	}
	echo '<h2>Diseño en Betheme</h2><p>Monta la portada con secciones del constructor, añade «Packs» al menú y el pie del panel lateral. Guarda una copia de lo anterior: «Restaurar» lo deja como estaba.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:8px">';
	wp_nonce_field( 'mpc_layout' );
	echo '<input type="hidden" name="action" value="mpc_layout">';
	echo '<button class="button button-primary" name="do" value="apply">Aplicar diseño</button> ';
	echo '<button class="button" name="do" value="restore">Restaurar el original</button>';
	echo '</form>';
}
