<?php
/**
 * Limpieza al desinstalar: ajustes y cachés propios. No toca pedidos, productos ni la portada montada en Betheme.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mpc_settings' );
delete_option( 'mpc_layout_native' );
delete_option( 'mpc_layout_backup' );
delete_transient( 'mpc_addon_ids_auto' );
delete_transient( 'mpc_pack_ids' );
delete_transient( 'mpc_favs' );
delete_transient( 'mpc_layout_msg' );
