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


/** Pestañas de la ficha como control segmentado con indicador que se desliza. */
(function () {
	'use strict';
	var nav = document.querySelector('.mfn-woocommerce-tabs-nav');
	if (!nav) { return; }
	var ind = document.createElement('li');
	ind.className = 'mpc-tabind'; ind.setAttribute('aria-hidden', 'true'); ind.setAttribute('role', 'presentation');
	nav.appendChild(ind);
	function place() {
		var a = nav.querySelector('li.active');
		if (!a) { return; }
		ind.style.width = a.offsetWidth + 'px';
		ind.style.transform = 'translateX(' + a.offsetLeft + 'px)';
		if (nav.scrollWidth > nav.clientWidth + 2) { nav.scrollTo({ left: a.offsetLeft - (nav.clientWidth - a.offsetWidth) / 2, behavior: 'smooth' }); }
	}
	nav.classList.add('has-ind');
	if ('MutationObserver' in window) { new MutationObserver(place).observe(nav, { attributes: true, subtree: true, attributeFilter: ['class'] }); }
	window.addEventListener('resize', place);
	place(); setTimeout(place, 250);
	if (document.fonts && document.fonts.ready) { document.fonts.ready.then(place); }
})();


/** «Packs» como primera opción del menú principal (escritorio y menú móvil). */
(function () {
	'use strict';
	var url = window.mpcNav && window.mpcNav.packs;
	if (!url) { return; }
	var path = url.replace(/^https?:\/\/[^/]+/, '');
	Array.prototype.forEach.call(document.querySelectorAll('ul.mfn-header-mainmenu, #menu-main-menu, #menu-menu-principal'), function (ul) {
		if (ul.querySelector('a[href="' + path + '"], a[href="' + url + '"]')) { return; }
		var first = ul.querySelector('li');
		if (!first) { return; }
		var li = first.cloneNode(true);
		li.removeAttribute('id');
		li.className = li.className.replace(/\bcurrent[-_\w]*/g, '');
		var a = li.querySelector('a');
		a.setAttribute('href', path || url);
		var label = li.querySelector('.menu-label');
		if (label) { label.textContent = 'PACKS'; } else { a.textContent = 'PACKS'; }
		var sub = li.querySelector('.menu-sub'); if (sub) { sub.remove(); }
		ul.insertBefore(li, first);
	});
})();
