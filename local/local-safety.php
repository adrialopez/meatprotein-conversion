<?php
/**
 * Solo local: sin correo saliente y las imagenes se sirven desde produccion.
 */
add_filter( 'pre_wp_mail', '__return_false' );
add_filter( 'upload_dir', function ( $d ) {
	$d['baseurl'] = 'https://meatprotein.eu/wp-content/uploads';
	return $d;
} );
add_filter( 'wp_get_attachment_url', function ( $url ) {
	return preg_replace( '#^https?://[^/]+/wp-content/uploads#', 'https://meatprotein.eu/wp-content/uploads', $url );
} );
add_filter( 'wp_calculate_image_srcset', function ( $s ) {
	if ( ! is_array( $s ) ) { return $s; }
	foreach ( $s as $k => $v ) { $s[ $k ]['url'] = preg_replace( '#^https?://[^/]+/wp-content/uploads#', 'https://meatprotein.eu/wp-content/uploads', $v['url'] ); }
	return $s;
} );
