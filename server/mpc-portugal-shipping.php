<?php
/**
 * Plugin Name: Meat Protein - Envío a Portugal
 * Description: El envío a Portugal cuesta siempre lo que indica su zona de WooCommerce (15 € con IVA), también con el pack o 10 bandejas. El envío gratis lo aplica Advanced Dynamic Pricing; este filtro se ejecuta después (prioridad máxima, ADP usa PHP_INT_MAX - 1) y devuelve a Portugal su tarifa. Lo sustituirá el plugin meatprotein-conversion (0.19+).
 * Version: 1.0.0
 * Author: Adrià López
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'woocommerce_package_rates',
	function ( $rates, $package ) {
		if ( empty( $package['destination']['country'] ) || 'PT' !== $package['destination']['country'] ) {
			return $rates;
		}
		foreach ( $rates as $key => $rate ) {
			if ( 'flat_rate' !== $rate->get_method_id() ) {
				unset( $rates[ $key ] );
				continue;
			}
			$opt  = get_option( 'woocommerce_flat_rate_' . $rate->get_instance_id() . '_settings', array() );
			$cost = isset( $opt['cost'] ) ? (float) str_replace( ',', '.', (string) $opt['cost'] ) : 0.0;
			if ( $cost <= 0 ) {
				continue;
			}
			$rate->set_cost( $cost );
			$rate->set_taxes( WC_Tax::calc_shipping_tax( $cost, WC_Tax::get_shipping_tax_rates() ) );
		}
		return $rates;
	},
	PHP_INT_MAX,
	2
);
