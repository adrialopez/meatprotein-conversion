/* Galería de la ficha: flechas y contador sobre la imagen (se apoya en las miniaturas de WooCommerce/FlexSlider). */
(function () {
	'use strict';
	var tries = 0;
	function init() {
		var g = document.querySelector('.single-product .woocommerce-product-gallery');
		if (!g || g.getAttribute('data-mp-arrows')) { return true; }
		var vp = g.querySelector('.flex-viewport');
		var thumbs = g.querySelectorAll('.flex-control-nav li img');
		if (!vp || thumbs.length < 2) { return false; }
		g.setAttribute('data-mp-arrows', '1');
		function cur() {
			for (var i = 0; i < thumbs.length; i++) { if (thumbs[i].classList.contains('flex-active')) { return i; } }
			return 0;
		}
		function go(d) {
			var i = cur() + d;
			if (i < 0) { i = thumbs.length - 1; }
			if (i >= thumbs.length) { i = 0; }
			thumbs[i].click();
		}
		function btn(cls, label, svg, d) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'mp-gal-btn ' + cls;
			b.setAttribute('aria-label', label);
			b.innerHTML = svg;
			b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); go(d); });
			vp.appendChild(b);
		}
		btn('mp-gal-prev', 'Foto anterior', '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>', -1);
		btn('mp-gal-next', 'Foto siguiente', '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>', 1);
		var count = document.createElement('span');
		count.className = 'mp-gal-count';
		count.setAttribute('aria-live', 'polite');
		vp.appendChild(count);
		function upd() { count.textContent = (cur() + 1) + ' / ' + thumbs.length; }
		upd();
		if (window.MutationObserver) {
			new MutationObserver(upd).observe(thumbs[0].parentNode.parentNode, { attributes: true, subtree: true, attributeFilter: ['class'] });
		}
		return true;
	}
	function loop() {
		if (init() || ++tries > 30) { return; }
		setTimeout(loop, 250);
	}
	if (document.readyState === 'complete') { loop(); } else { window.addEventListener('load', loop); }
})();
