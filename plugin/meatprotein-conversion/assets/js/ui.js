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


/** «Sale hoy / mañana / el lunes» según la hora de Madrid (se envía de lunes a jueves; el jueves por la tarde y el viernes salen el lunes). */
(function () {
	'use strict';
	var el = document.querySelector('[data-mpc-eta]');
	if (!el) { return; }
	var now;
	try { now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Europe/Madrid' })); } catch (e) { now = new Date(); }
	var d = now.getDay(), h = now.getHours(), when;
	if (d >= 1 && d <= 4 && h < 14) { when = 'sale hoy'; }
	else if (d >= 1 && d <= 3) { when = 'sale mañana'; }
	else { when = 'sale el lunes'; }
	var span = el.querySelector('span');
	span.innerHTML = '';
	span.appendChild(document.createTextNode('Pídelo ahora y '));
	var b = document.createElement('strong'); b.textContent = when; span.appendChild(b);
	span.appendChild(document.createTextNode('. Lo recibes en 24-48 h, refrigerado.'));
	el.hidden = false;
})();
