/* Fichas de producto en móvil: la barra inferior de iconos se sustituye por el botón fijo de compra, así que se añade un acceso al carrito dentro de ese botón. */
(function () {
	'use strict';
	var tries = 0;
	function count() {
		var b = document.querySelector('.header-cart-count');
		return b ? parseInt(b.textContent, 10) || 0 : 0;
	}
	function init() {
		var bar = document.querySelector('.mpc-cta');
		if (!bar) { return false; }
		if (bar.querySelector('.mpc-cta__cart')) { return true; }
		var a = document.createElement('button');
		a.type = 'button';
		a.className = 'mpc-cta__cart';
		a.setAttribute('aria-label', 'Ver el carrito');
		a.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1.2 11.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8L5 8z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg><span class="mpc-cta__badge" hidden></span>';
		var badge = a.querySelector('.mpc-cta__badge');
		function upd() { var n = count(); badge.textContent = n; badge.hidden = !n; }
		a.addEventListener('click', function (e) {
			e.preventDefault();
			var t = document.querySelector('a.toggle-mfn-cart:not(.close-mfn-cart)');
			if (t) { t.click(); } else { window.location.href = '/carrito/'; }
		});
		bar.insertBefore(a, bar.firstChild);
		upd();
		var src = document.querySelector('.header-cart-count');
		if (src && window.MutationObserver) { new MutationObserver(upd).observe(src, { childList: true, characterData: true, subtree: true }); }
		document.body.addEventListener('added_to_cart', function () { setTimeout(upd, 400); });
		return true;
	}
	function loop() { if (init() || ++tries > 40) { return; } setTimeout(loop, 250); }
	if (document.readyState === 'complete') { loop(); } else { window.addEventListener('load', loop); }
})();
