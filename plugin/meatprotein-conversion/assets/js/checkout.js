/** Checkout: el snack sugerido se añade con un clic, sin recargar, y el resumen del pedido se refresca. */
(function ($) {
	'use strict';
	$(document).on('click', '.mpc-upsell--checkout a.mpc-upsell__add[data-product_id]', function (e) {
		var $a = $(this), $box = $a.closest('.mpc-upsell');
		if (!window.wc_checkout_params || $a.hasClass('is-busy')) { return; }
		e.preventDefault();
		$a.addClass('is-busy').text('Añadiendo…');
		$.post(wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'), {
			product_id: $a.data('product_id'),
			quantity: 1
		}).done(function (res) {
			if (res && res.error) { window.location.href = $a.attr('href'); return; }
			$box.html('<p class="mpc-upsell__done">Añadido a tu pedido. ¡Buena elección!</p>');
			$(document.body).trigger('update_checkout');
		}).fail(function () {
			window.location.href = $a.attr('href');
		});
	});
})(jQuery);
