<?php
/**
 * Compra a Google Analytics desde el servidor (Measurement Protocol), como respaldo del evento del navegador.
 *
 * Al hacer el pedido se guardan los identificadores de Google solo si el cliente aceptó las cookies de analítica.
 * Diez minutos después del pago se envía la compra con el mismo «transaction_id» que usa Site Kit:
 * Google Analytics descarta la repetida, así que no se cuenta dos veces.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes de Google Analytics guardados por Site Kit.
 *
 * @return array
 */
function mpc_ga_sitekit() {
	return (array) get_option( 'googlesitekit_analytics-4_settings', array() );
}

/**
 * Identificadores de Google a partir de las cookies del cliente. Vacío si no aceptó la analítica.
 *
 * @param array  $cookies        Cookies de la petición.
 * @param string $measurement_id ID de medición (G-XXXX).
 * @return array cid, sid y ads; vacío si no se puede medir.
 */
function mpc_ga_from_cookies( $cookies, $measurement_id ) {
	$consent = array();
	$raw     = isset( $cookies['cookieyes-consent'] ) ? (string) wp_unslash( $cookies['cookieyes-consent'] ) : '';
	foreach ( explode( ',', $raw ) as $part ) {
		$pair = explode( ':', $part, 2 );
		if ( 2 === count( $pair ) ) {
			$consent[ trim( $pair[0] ) ] = trim( $pair[1] );
		}
	}
	if ( 'yes' !== ( $consent['action'] ?? '' ) || 'yes' !== ( $consent['analytics'] ?? '' ) ) {
		return array();
	}
	// _ga = GA1.1.<aleatorio>.<fecha>.
	if ( ! preg_match( '/^GA\d\.\d\.(\d+\.\d+)$/', (string) ( $cookies['_ga'] ?? '' ), $m ) ) {
		return array();
	}
	$out = array(
		'cid' => $m[1],
		'sid' => '',
		'ads' => 'yes' === ( $consent['advertisement'] ?? '' ),
	);
	// Sesión: «GS1.1.<sesión>.…» o «GS2.1.s<sesión>$o…».
	$key = '_ga_' . preg_replace( '/^G-/', '', (string) $measurement_id );
	if ( preg_match( '/^GS\d\.\d\.s?(\d+)/', (string) wp_unslash( $cookies[ $key ] ?? '' ), $s ) ) {
		$out['sid'] = $s[1];
	}
	return $out;
}

/**
 * Al crear el pedido desde la tienda: guarda los identificadores en el pedido.
 *
 * @param int|WC_Order $order Pedido o su ID.
 */
function mpc_ga_capture( $order ) {
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
	if ( ! $order ) {
		return;
	}
	$sk = mpc_ga_sitekit();
	$ga = mpc_ga_from_cookies( $_COOKIE, $sk['measurementID'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Se validan con expresiones regulares.
	if ( $ga ) {
		$order->update_meta_data( '_mpc_ga', $ga );
		$order->save_meta_data();
	}
}
add_action( 'woocommerce_checkout_order_processed', 'mpc_ga_capture', 20 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'mpc_ga_capture', 20 );

/**
 * Al quedar pagado: programa el envío con margen para que llegue antes el evento del navegador.
 *
 * @param int $order_id ID del pedido.
 */
function mpc_ga_schedule( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! $order->get_meta( '_mpc_ga' ) || $order->get_meta( '_mpc_ga_sent' ) || ! function_exists( 'as_schedule_single_action' ) ) {
		return;
	}
	$args = array( (int) $order_id, 1 );
	if ( ! as_next_scheduled_action( 'mpc_ga_send', $args, 'mpc' ) ) {
		as_schedule_single_action( time() + 10 * MINUTE_IN_SECONDS, 'mpc_ga_send', $args, 'mpc' );
	}
}
add_action( 'woocommerce_order_status_processing', 'mpc_ga_schedule' );
add_action( 'woocommerce_order_status_completed', 'mpc_ga_schedule' );

/**
 * Respeta el ajuste de Site Kit sobre a quién no medir.
 *
 * @param WC_Order $order Pedido.
 * @return bool
 */
function mpc_ga_user_excluded( $order ) {
	$off  = (array) ( mpc_ga_sitekit()['trackingDisabled'] ?? array() );
	$user = (int) $order->get_customer_id();
	if ( ! $user ) {
		return false;
	}
	if ( in_array( 'loggedinUsers', $off, true ) ) {
		return true;
	}
	return in_array( 'contentCreators', $off, true ) && user_can( $user, 'edit_posts' );
}

/**
 * Cuerpo del envío, con los mismos datos que manda Site Kit desde el navegador. Sin datos personales.
 *
 * @param WC_Order $order Pedido.
 * @param array    $ga    Identificadores guardados.
 * @return array
 */
function mpc_ga_payload( $order, $ga ) {
	$items = array();
	foreach ( $order->get_items() as $line ) {
		$qty  = max( 1, (int) $line->get_quantity() );
		$item = array(
			'item_id'   => (string) $line->get_product_id(),
			'item_name' => wp_strip_all_tags( $line->get_name() ),
			'price'     => round( ( (float) $line->get_total() + (float) $line->get_total_tax() ) / $qty, 2 ),
			'quantity'  => $qty,
		);
		$cats = wc_get_product_terms( $line->get_product_id(), 'product_cat', array( 'number' => 5 ) );
		foreach ( array_values( (array) $cats ) as $n => $cat ) {
			$item[ 'item_category' . ( $n ? $n + 1 : '' ) ] = $cat->name;
		}
		$items[] = $item;
	}
	$params = array(
		'transaction_id'       => (string) $order->get_id(),
		'affiliation'          => get_bloginfo( 'name' ),
		'currency'             => $order->get_currency(),
		'value'                => round( (float) $order->get_total(), 2 ),
		'tax'                  => round( (float) $order->get_total_tax(), 2 ),
		'shipping'             => round( (float) $order->get_shipping_total(), 2 ),
		'engagement_time_msec' => 1,
		'items'                => $items,
	);
	if ( ! empty( $ga['sid'] ) ) {
		$params['session_id'] = (string) $ga['sid'];
	}
	$ads  = empty( $ga['ads'] ) ? 'DENIED' : 'GRANTED';
	$body = array(
		'client_id' => (string) $ga['cid'],
		'consent'   => array(
			'ad_user_data'       => $ads,
			'ad_personalization' => $ads,
		),
		'events'    => array(
			array(
				'name'   => 'purchase',
				'params' => $params,
			),
		),
	);
	$paid = $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created();
	// Google admite fechas de hasta 72 horas atrás.
	if ( $paid && time() - $paid->getTimestamp() < 70 * HOUR_IN_SECONDS ) {
		$body['timestamp_micros'] = $paid->getTimestamp() * 1000000;
	}
	return $body;
}

/**
 * Envía la compra. Reintenta dos veces si falla la conexión.
 *
 * @param int $order_id ID del pedido.
 * @param int $try      Intento.
 */
function mpc_ga_send( $order_id, $try = 1 ) {
	$order  = wc_get_order( $order_id );
	$secret = trim( (string) mpc_opt( 'ga_api_secret' ) );
	$mid    = (string) ( mpc_ga_sitekit()['measurementID'] ?? '' );
	if ( ! $order || '' === $secret || '' === $mid || $order->get_meta( '_mpc_ga_sent' ) ) {
		return;
	}
	$ga = $order->get_meta( '_mpc_ga' );
	if ( empty( $ga['cid'] ) || ! $order->has_status( array( 'processing', 'completed' ) ) || mpc_ga_user_excluded( $order ) ) {
		return;
	}
	$res = wp_remote_post(
		add_query_arg(
			array(
				'measurement_id' => rawurlencode( $mid ),
				'api_secret'     => rawurlencode( $secret ),
			),
			'https://region1.google-analytics.com/mp/collect'
		),
		array(
			'timeout' => 10,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( mpc_ga_payload( $order, $ga ) ),
		)
	);
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	if ( $code < 200 || $code >= 300 ) {
		if ( $try < 3 && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + HOUR_IN_SECONDS, 'mpc_ga_send', array( (int) $order_id, $try + 1 ), 'mpc' );
		}
		return;
	}
	$order->update_meta_data( '_mpc_ga_sent', time() );
	$order->delete_meta_data( '_mpc_ga' );
	$order->save_meta_data();
	update_option(
		'mpc_ga_last',
		array(
			'order' => (int) $order_id,
			'time'  => time(),
		),
		false
	);
}
add_action( 'mpc_ga_send', 'mpc_ga_send', 10, 2 );

/**
 * Estado para la página de ajustes.
 *
 * @return string
 */
function mpc_ga_status_text() {
	if ( '' === trim( (string) mpc_opt( 'ga_api_secret' ) ) ) {
		return 'Sin clave: no se envía nada.';
	}
	$last = (array) get_option( 'mpc_ga_last', array() );
	if ( empty( $last['order'] ) ) {
		return 'Clave guardada. Todavía no se ha enviado ninguna compra.';
	}
	$when = new DateTime( '@' . (int) $last['time'] );
	$when->setTimezone( new DateTimeZone( 'Europe/Madrid' ) );
	return sprintf( 'Último envío: pedido %d, %s.', (int) $last['order'], $when->format( 'd/m/Y H:i' ) );
}
