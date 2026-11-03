<?php
/**
 * Checkout: cupón dentro del resumen, miniaturas, textos claros y garantías.
 * Mismo planteamiento que el checkout de Pifarré y de desastre-store.com.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp',
	function () {
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
	}
);

add_action(
	'woocommerce_checkout_order_review',
	function () {
		if ( ! wc_coupons_enabled() ) {
			return;
		}
		?>
	<div class="mpc-coupon" data-mpc-coupon>
		<div class="mpc-coupon__row">
			<input type="text" class="mpc-coupon__input" placeholder="Código de descuento" aria-label="Código de descuento" autocomplete="off">
			<button type="button" class="mpc-coupon__btn">Aplicar</button>
		</div>
		<p class="mpc-coupon__msg" role="status" hidden></p>
	</div>
		<?php
	},
	5
);

add_action(
	'wp_footer',
	function () {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}
		?>
<script>
jQuery(function($){
	function say($w,t,err){ $w.find('.mpc-coupon__msg').text(t).toggleClass('is-error',!!err).prop('hidden',!t); }
	function apply($w){
		var $in=$w.find('.mpc-coupon__input'), $btn=$w.find('.mpc-coupon__btn'), code=$.trim($in.val());
		if(!code){ say($w,'Escribe tu código de descuento.',true); return; }
		$btn.prop('disabled',true).text('Aplicando…');
		$.ajax({ type:'POST', url: wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%','apply_coupon'),
			data:{ security: wc_checkout_params.apply_coupon_nonce, coupon_code: code }, dataType:'html' })
		.done(function(html){
			var err=$('<div>').html(html).find('.woocommerce-error').text().trim();
			if(err){ say($w,err,true); } else { $in.val(''); $(document.body).trigger('update_checkout',{update_shipping_method:false}); }
		})
		.fail(function(){ say($w,'No se pudo aplicar el código. Inténtalo de nuevo.',true); })
		.always(function(){ $btn.prop('disabled',false).text('Aplicar'); });
	}
	$(document).on('click','.mpc-coupon__btn',function(){ apply($(this).closest('[data-mpc-coupon]')); });
	$(document).on('keydown','.mpc-coupon__input',function(e){ if(e.key==='Enter'){ e.preventDefault(); apply($(this).closest('[data-mpc-coupon]')); } });
});
</script>
		<?php
	},
	50
);

/* Resumen con miniatura y cantidad bajo el nombre. */
add_filter(
	'woocommerce_cart_item_name',
	function ( $name, $item ) {
		if ( is_checkout() || defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			$img = $item['data']->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '' ) );
			return '<span class="mpc-rv"><span class="mpc-rv__img">' . $img . '</span><span class="mpc-rv__name">' . $name
			. '<span class="mpc-rv__qty">Cantidad: ' . (int) $item['quantity'] . '</span></span></span>';
		}
		return $name;
	},
	10,
	2
);
add_filter( 'woocommerce_checkout_cart_item_quantity', '__return_empty_string' );

/* Textos del checkout. */
add_filter(
	'gettext',
	function ( $t, $text, $domain ) {
		if ( 'woocommerce' !== $domain || ! is_checkout() ) {
			return $t;
		}
		$map = array(
			'Detalles de facturación'            => 'Tus datos',
			'Tu pedido'                          => 'Resumen del pedido',
			'¿Enviar a una dirección diferente?' => 'Enviar a otra dirección',
			'Información adicional'              => 'Notas del pedido',
			'¿Ya eres cliente?'                  => '¿Ya tienes cuenta?',
		);
		return isset( $map[ $t ] ) ? $map[ $t ] : $t;
	},
	20,
	3
);
add_filter(
	'woocommerce_order_button_text',
	function () {
		return 'Pagar y finalizar el pedido';
	}
);

add_action(
	'woocommerce_review_order_after_submit',
	function () {
		echo '<ul class="mpc-secure"><li>Pago seguro</li><li>Tarjeta, PayPal o Bizum</li><li>Envío refrigerado con SEUR</li></ul>';
	}
);

/* Pie mínimo mientras se paga: ayuda a mano y nada que distraiga. */
add_action(
	'wp_footer',
	function () {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}
		$phone = trim( mpc_opt( 'phone' ) );
		$links = array(
			'Envíos y devoluciones' => mpc_page_url( 'envios-y-devoluciones' ),
			'Preguntas frecuentes'  => mpc_page_url( 'faqs' ),
		);
		$out   = array();
		foreach ( array_filter( $links ) as $l => $u ) {
			$out[] = '<a href="' . esc_url( $u ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '<div class="mpc-checkout-help"><p>¿Dudas con tu pedido? ' . ( $phone ? 'Llámanos al <a href="tel:' . esc_attr( preg_replace( '/\s+/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a> (9:00-14:00) o escribe a <a href="mailto:info@meatprotein.eu">info@meatprotein.eu</a>.' : '' ) . '</p><p>' . implode( ' · ', $out ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	5
);

/* Menos fricción en el formulario: sin «empresa», el email primero (permite recuperar el carrito) y autocompletado del navegador. */
add_filter(
	'woocommerce_checkout_fields',
	function ( $f ) {
		unset( $f['billing']['billing_company'], $f['shipping']['shipping_company'] );
		$set = function ( &$field, $autocomplete, $inputmode = '' ) {
			$field['autocomplete'] = $autocomplete;
			if ( $inputmode ) {
				$field['custom_attributes']['inputmode'] = $inputmode;
			}
		};
		if ( isset( $f['billing']['billing_email'] ) ) {
			$f['billing']['billing_email']['priority'] = 5;
			$f['billing']['billing_email']['class']    = array( 'form-row-wide' );
			$f['billing']['billing_email']['label']    = 'Correo electrónico';
			$set( $f['billing']['billing_email'], 'email', 'email' );
		}
		foreach ( array( 'billing', 'shipping' ) as $g ) {
			if ( isset( $f[ $g ][ $g . '_first_name' ] ) ) {
				$set( $f[ $g ][ $g . '_first_name' ], 'given-name' ); }
			if ( isset( $f[ $g ][ $g . '_last_name' ] ) ) {
				$set( $f[ $g ][ $g . '_last_name' ], 'family-name' ); }
			if ( isset( $f[ $g ][ $g . '_address_1' ] ) ) {
				$set( $f[ $g ][ $g . '_address_1' ], 'address-line1' ); }
			if ( isset( $f[ $g ][ $g . '_address_2' ] ) ) {
				$set( $f[ $g ][ $g . '_address_2' ], 'address-line2' );
				$f[ $g ][ $g . '_address_2' ]['placeholder'] = 'Piso, puerta, escalera… (opcional)'; }
			if ( isset( $f[ $g ][ $g . '_postcode' ] ) ) {
				$set( $f[ $g ][ $g . '_postcode' ], 'postal-code', 'numeric' ); }
			if ( isset( $f[ $g ][ $g . '_city' ] ) ) {
				$set( $f[ $g ][ $g . '_city' ], 'address-level2' ); }
		}
		if ( isset( $f['billing']['billing_phone'] ) ) {
			$set( $f['billing']['billing_phone'], 'tel', 'tel' );
			$f['billing']['billing_phone']['label'] = 'Teléfono (para el repartidor)';
		}
		return $f;
	},
	20
);
