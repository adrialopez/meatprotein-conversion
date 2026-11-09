/** La barra de promoción llega en una plantilla (Betheme no llama a wp_body_open): se coloca al principio del body. */
(function () {
	'use strict';
	var tpl = document.getElementById('mpc-promo-tpl');
	if (tpl && tpl.content && tpl.content.firstElementChild && !document.querySelector('.mpc-promo')) {
		document.body.insertBefore(tpl.content.firstElementChild, document.body.firstChild);
	}
})();

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


/**
 * Aviso de entrega con cuenta atrás. Se envía de lunes a jueves; hora de corte 13:00 (Madrid) y entrega
 * al día siguiente del envío antes de las 14:00. El envío es el primer día lunes-jueves cuyo corte no ha pasado:
 * lun-jue antes de las 13 → mañana; lun-mié después de las 13 → pasado mañana; jue 13:00 a lun 13:00 → el martes.
 */
(function () {
	'use strict';
	var DAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
	function madrid() {
		try { return new Date(new Date().toLocaleString('en-US', { timeZone: 'Europe/Madrid' })); } catch (e) { return new Date(); }
	}
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function calc(now) {
		for (var k = 0; k < 8; k++) {
			var cut = new Date(now.getFullYear(), now.getMonth(), now.getDate() + k, 13, 0, 0);
			var dw = cut.getDay();
			if (dw >= 1 && dw <= 4 && now < cut) {
				var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
				var arrive = new Date(cut.getFullYear(), cut.getMonth(), cut.getDate() + 1);
				var off = Math.round((arrive - today) / 864e5);
				return { ms: cut - now, when: off === 1 ? 'mañana' : (off === 2 && arrive.getDay() !== 2) ? 'pasado mañana' : 'el ' + DAYS[arrive.getDay()] };
			}
		}
		return null;
	}
	window.mpcEta = function (d) { return calc(d); };
	function paint() {
		var els = document.querySelectorAll('[data-mpc-eta]');
		if (!els.length) { return; }
		var r = calc(madrid());
		if (!r) { return; }
		var s = Math.floor(r.ms / 1000), d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
		var left = (d ? d + ' d ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s % 60);
		for (var i = 0; i < els.length; i++) {
			var span = els[i].querySelector('span');
			if (!span) { continue; }
			var key = r.when + (els[i].getAttribute('data-cold') || '');
			if (span.getAttribute('data-w') !== key) {
				span.innerHTML = 'Tienes <strong class="mpc-eta__t">' + left + '</strong> para pedir y recibirlo <strong>' + r.when + '</strong> antes de las 14:00' + (els[i].getAttribute('data-cold') === '1' ? ', refrigerado.' : '.');
				span.setAttribute('data-w', key);
			} else {
				var t = span.querySelector('.mpc-eta__t');
				if (t) { t.textContent = left; }
			}
			els[i].hidden = false;
		}
	}
	paint();
	setInterval(paint, 1000);
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


/** Menú: «Packs» como primera opción (barra y panel lateral), imágenes de categoría sin texto alternativo erróneo y pie con contacto. */
(function () {
	'use strict';
	var nav = window.mpcNav || {};
	var url = nav.packs;
	if (url) {
		var path = url.replace(/^https?:\/\/[^/]+/, '');
		Array.prototype.forEach.call(document.querySelectorAll('ul.mfn-header-mainmenu, ul.mfn-sidemenu-menu[id^="menu-menu-principal"], #menu-main-menu'), function (ul) {
			if (ul.querySelector('a[href="' + path + '"], a[href="' + url + '"]')) { return; }
			var first = ul.querySelector('li');
			if (!first) { return; }
			var li = first.cloneNode(true);
			li.removeAttribute('id');
			li.className = li.className.replace(/\bcurrent[-_\w]*/g, '') + ' mpc-nav-packs';
			var as = li.querySelectorAll('a');
			as[0].setAttribute('href', path || url);
			as[0].removeAttribute('aria-current');
			var label = li.querySelector('.menu-label');
			if (label) { label.textContent = 'PACKS'; } else { as[0].textContent = 'PACKS'; }
			Array.prototype.forEach.call(li.querySelectorAll('.menu-sub, .outer-menu-sub'), function (x) { x.remove(); });
			ul.insertBefore(li, first);
		});
	}
	Array.prototype.forEach.call(document.querySelectorAll('.mfn-menu-item-icon img'), function (img) { img.setAttribute('alt', ''); });
	var cats = document.querySelector('#mfn-sidemenu-tmpl-38 #menu-categorias, .mfn-sidemenu-tmpl-builder #menu-categorias');
	if (cats && !document.querySelector('.mpc-sm-foot')) {
		var col = cats.closest('.column') || cats.parentNode;
		var foot = document.createElement('div');
		foot.className = 'mpc-sm-foot';
		var h = '';
		if (nav.free) { h += '<p class="mpc-sm-free">' + nav.free + '</p>'; }
		if (nav.phone && nav.phoneHref) { h += '<a class="mpc-sm-call" href="tel:' + nav.phoneHref + '"><span>Llámanos</span><strong>' + nav.phone + '</strong></a>'; }
		if (nav.ship) { h += '<a class="mpc-sm-link" href="' + nav.ship + '">Envíos y devoluciones</a>'; }
		foot.innerHTML = h;
		col.appendChild(foot);
	}
})();
