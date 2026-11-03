/** Cuenta atrás de la promo y cierre de la barra superior. */
/* Proteína y kcal bajo el precio de cada hamburguesa en las tarjetas. */
(function () {
	'use strict';
	var nut = window.mpcNut || {};
	var cards = document.querySelectorAll('li.product[class*="post-"]');
	Array.prototype.forEach.call(cards, function (li) {
		var m = li.className.match(/\bpost-(\d+)\b/);
		if (!m || !nut[m[1]]) { return; }
		var d = li.querySelector('.desc');
		if (!d || d.querySelector('.mpc-chip')) { return; }
		var el = document.createElement('p');
		el.className = 'mpc-chip';
		el.textContent = nut[m[1]].p + ' g de proteína · ' + nut[m[1]].k + ' kcal';
		var price = d.querySelector('.price');
		if (price && price.parentNode) { price.parentNode.insertBefore(el, price.nextSibling); } else { d.appendChild(el); }
	});
})();

/** Cuenta atrás de la promo y cierre de la barra superior. */
(function () {
	'use strict';
	var bar = document.querySelector('[data-mpc-promo]');
	if (!bar) { return; }
	var ends = parseInt(bar.getAttribute('data-ends') || '0', 10) * 1000;
	var out = bar.querySelector('[data-mpc-count]');
	if (!ends || !out) { return; }
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function tick() {
		var d = ends - Date.now();
		if (d <= 0) { bar.remove(); return; }
		var h = Math.floor(d / 36e5), m = Math.floor(d % 36e5 / 6e4), s = Math.floor(d % 6e4 / 1e3);
		var days = Math.floor(h / 24);
		out.textContent = days >= 1 ? 'Quedan ' + days + ' d ' + pad(h % 24) + ' h' : 'Quedan ' + pad(h) + ':' + pad(m) + ':' + pad(s);
		setTimeout(tick, 1000);
	}
	tick();
})();
