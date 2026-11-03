/**
 * Selector de sabores del pack.
 * Sustituye los 10 desplegables «Bandeja N» por una cuadrícula con +/-; los desplegables siguen
 * existiendo (ocultos) y se rellenan solos, así WooCommerce y el carrito funcionan igual que antes.
 */
(function ($) {
	'use strict';

	var $form = $('form.variations_form').first();
	var host = document.querySelector('[data-mpc-pack]');
	if (!$form.length || !host) { return; }

	var selects = $form.find('select[name^="attribute_bandeja-"]').toArray().sort(function (a, b) {
		return parseInt(a.name.replace(/\D+/g, ''), 10) - parseInt(b.name.replace(/\D+/g, ''), 10);
	});
	var S = selects.length;
	if (S < 4) { return; }

	/* ---- Sabores: unión de las opciones de todos los desplegables ---- */
	/* Un mismo sabor escrito de varias formas en los desplegables se trata como uno solo. */
	var ALIAS = { 'ternera y queso trufa': 'ternera queso y trufa', 'miel con tomate': 'miel y tomate', 'pavo con cebolla caramelizada': 'pavo cebolla', 'ternera cebolla': 'ternera cebolla caramelizada', 'cheese bacon': 'chees bacon' };
	function norm(s) {
		var k = String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/&amp;|&/g, ' y ').replace(/[^a-z0-9]+/g, ' ').trim();
		return ALIAS[k] || k;
	}
	var FIX = { 'chees bacon': 'Cheese bacon', 'cheese bacon': 'Cheese bacon', 'mexican': 'Mexican', 'espinacas y queso': 'Espinacas y queso', 'pollo trufa': 'Pollo trufa', 'ternera queso y trufa': 'Ternera, queso y trufa', 'ternera queso': 'Ternera y queso', 'miel y tomate': 'Miel y tomate', 'miel con tomate': 'Miel y tomate', 'pechuga con puerro y calabaza': 'Pechuga, puerro y calabaza', 'kebab': 'Kebab', 'satay': 'Satay', 'chicken mango': 'Pollo y mango', 'pavo cebolla': 'Pavo y cebolla', 'pavo con cebolla caramelizada': 'Pavo y cebolla caramelizada', 'ternera cebolla caramelizada': 'Ternera y cebolla caramelizada', 'ternera cebolla': 'Ternera y cebolla' };
	function pretty(raw, key) {
		if (FIX[key]) { return FIX[key]; }
		var t = String(raw).replace(/&amp;/g, '&').replace(/\s*&\s*/g, ' y ').trim().toLowerCase();
		return t.charAt(0).toUpperCase() + t.slice(1);
	}
	function group(key) {
		if (/pollo|chicken|kebab|satay|rulo de cabra/.test(key)) { return 'Pollo'; }
		if (/ternera|parrilla|cheese bacon|bacon/.test(key)) { return 'Ternera'; }
		if (/potro/.test(key)) { return 'Potro'; }
		if (/pavo/.test(key)) { return 'Pavo'; }
		return 'Otros sabores';
	}

	var slots = selects.map(function (sel) {
		var m = {};
		Array.prototype.forEach.call(sel.options, function (o) { if (o.value) { m[norm(o.textContent || o.value)] = o.value; } });
		return m;
	});
	var flavours = {}; // key -> {key,label,group}
	selects.forEach(function (sel) {
		Array.prototype.forEach.call(sel.options, function (o) {
			if (!o.value) { return; }
			var k = norm(o.textContent || o.value);
			if (!flavours[k]) { flavours[k] = { key: k, label: pretty(o.textContent || o.value, k), group: group(k) }; }
		});
	});
	var keys = Object.keys(flavours);
	if (!keys.length) { return; }

	var INFO = {};
	try { INFO = JSON.parse(host.getAttribute('data-flavours') || '{}') || {}; } catch (e) { INFO = {}; }
	var counts = {};
	function total(c) { return Object.keys(c).reduce(function (a, k) { return a + (c[k] || 0); }, 0); }

	/* Asignación sabor -> bandeja (una opción por desplegable), con emparejamiento bipartito. */
	function assign(c) {
		var units = [];
		Object.keys(c).forEach(function (k) { for (var i = 0; i < c[k]; i++) { units.push(k); } });
		if (units.length > S) { return null; }
		var slotUnit = []; for (var s = 0; s < S; s++) { slotUnit.push(-1); }
		function tryUnit(u, seen) {
			for (var s2 = 0; s2 < S; s2++) {
				if (!slots[s2][units[u]] || seen[s2]) { continue; }
				seen[s2] = true;
				if (slotUnit[s2] < 0 || tryUnit(slotUnit[s2], seen)) { slotUnit[s2] = u; return true; }
			}
			return false;
		}
		for (var u = 0; u < units.length; u++) { if (!tryUnit(u, {})) { return null; } }
		return { units: units, slotUnit: slotUnit };
	}

	/* ---- Interfaz ---- */
	var groups = ['Pollo', 'Ternera', 'Potro', 'Pavo', 'Otros sabores'];
	var html = '<div class="mpc-pack__head"><h3>Elige tus sabores</h3><p class="mpc-pack__count" aria-live="polite"></p></div>' +
		'<div class="mpc-pack__bar" role="progressbar" aria-valuemin="0" aria-valuemax="' + S + '"><i></i></div>' +
		'<div class="mpc-pack__tools"><button type="button" class="mpc-link" data-act="random">Sorpréndeme con los que faltan</button><button type="button" class="mpc-link" data-act="clear">Vaciar</button></div>';
	groups.forEach(function (g) {
		var items = keys.filter(function (k) { return flavours[k].group === g; }).sort(function (a, b) { return flavours[a].label.localeCompare(flavours[b].label, 'es'); });
		if (!items.length) { return; }
		html += '<fieldset class="mpc-pack__group"><legend>' + g + '</legend><div class="mpc-pack__grid">';
		items.forEach(function (k) {
			html += '<div class="mpc-flavour" data-k="' + k + '"><span class="mpc-flavour__name">' + flavours[k].label + (INFO[k] ? ' <button type="button" class="mpc-info-btn" data-info="' + k + '" aria-label="Información de ' + flavours[k].label + '">Info</button>' : '') + '</span>' +
				'<span class="mpc-step"><button type="button" class="mpc-step__b" data-d="-1" aria-label="Quitar ' + flavours[k].label + '">−</button><output class="mpc-step__n">0</output><button type="button" class="mpc-step__b" data-d="1" aria-label="Añadir ' + flavours[k].label + '">+</button></span></div>';
		});
		html += '</div></fieldset>';
	});
	host.innerHTML = html;
	host.hidden = false;
	selects.forEach(function (sel) { var tr = sel.closest('tr'); if (tr) { tr.classList.add('mpc-hide'); } });

	/* Atributos extra con una sola opción (el regalo): se eligen solos y se avisa de que van incluidos. */
	var gifts = 0;
	$form.find('select[name^="attribute_"]').not(selects).each(function () {
		var vals = Array.prototype.filter.call(this.options, function (o) { return o.value; });
		if (vals.length === 1) {
			this.value = vals[0].value; gifts++;
			var tr = this.closest('tr'); if (tr) { tr.classList.add('mpc-hide'); }
		}
	});
	if (gifts) {
		$(host).find('.mpc-pack__head').after('<p class="mpc-pack__gift">Regalo incluido en el pack, no hace falta elegirlo.</p>');
	}

	var $count = $(host).find('.mpc-pack__count'), $barI = $(host).find('.mpc-pack__bar i'), $bar = $(host).find('.mpc-pack__bar');

	function sync() {
		var t = total(counts), left = S - t;
		$count.html(left > 0 ? '<strong>' + t + ' de ' + S + '</strong> · faltan ' + left : '<strong>Pack completo</strong> · ' + S + ' de ' + S);
		$barI.css('width', (t / S * 100) + '%');
		$bar.attr('aria-valuenow', t).toggleClass('is-full', left === 0);
		$(host).find('.mpc-flavour').each(function () {
			var k = this.getAttribute('data-k'), n = counts[k] || 0, c2 = $.extend({}, counts); c2[k] = n + 1;
			$(this).toggleClass('is-on', n > 0).find('.mpc-step__n').text(n);
			$(this).find('[data-d="-1"]').prop('disabled', n === 0);
			$(this).find('[data-d="1"]').prop('disabled', t >= S || !assign(c2));
		});
		/* Volcado a los desplegables de WooCommerce. */
		var a = assign(counts);
		selects.forEach(function (sel, i) {
			var v = a && a.slotUnit[i] >= 0 ? slots[i][a.units[a.slotUnit[i]]] : '';
			if (sel.value !== v) { sel.value = v; }
		});
		$form.trigger('check_variations');
		$(document).trigger('mpc:change', [t, S]);
	}

	$(host).on('click', '.mpc-step__b', function () {
		var k = $(this).closest('.mpc-flavour').attr('data-k'), d = parseInt(this.getAttribute('data-d'), 10);
		counts[k] = Math.max(0, (counts[k] || 0) + d);
		if (!counts[k]) { delete counts[k]; }
		if (!assign(counts)) { counts[k] -= d; if (!counts[k]) { delete counts[k]; } }
		sync();
	});
	/* Ficha de ingredientes y valores nutricionales de cada sabor. */
	var dlg = null;
	function openInfo(k) {
		var d = INFO[k]; if (!d) { return; }
		if (!dlg) {
			dlg = document.createElement('dialog');
			dlg.className = 'mpc-dialog';
			dlg.innerHTML = '<form method="dialog"><button class="mpc-dialog__x" aria-label="Cerrar">×</button></form><h3 class="mpc-dialog__t"></h3><img class="mpc-dialog__img" alt=""><p class="mpc-dialog__l"><a href="#" target="_blank" rel="noopener">Ver la hamburguesa</a></p>';
			document.body.appendChild(dlg);
			dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
		}
		dlg.querySelector('.mpc-dialog__t').textContent = d.t;
		dlg.querySelector('.mpc-dialog__img').src = d.i;
		dlg.querySelector('.mpc-dialog__img').alt = 'Ingredientes y valores nutricionales de ' + d.t;
		dlg.querySelector('.mpc-dialog__l a').href = d.u;
		if (dlg.showModal) { dlg.showModal(); } else { window.open(d.i, '_blank'); }
	}
	$(host).on('click', '[data-info]', function (e) { e.preventDefault(); e.stopPropagation(); openInfo(this.getAttribute('data-info')); });
	$(host).on('click', '[data-act="clear"]', function () { counts = {}; sync(); });
	$(host).on('click', '[data-act="random"]', function () {
		var guard = 0;
		while (total(counts) < S && guard++ < 400) {
			var k = keys[Math.floor(Math.random() * keys.length)], c2 = $.extend({}, counts); c2[k] = (c2[k] || 0) + 1;
			if (assign(c2)) { counts = c2; }
		}
		sync();
	});
	/* «Restablecer» de WooCommerce vacía también el selector. (reset_data se dispara con cada pack incompleto: no sirve.) */
	$form.on('click', '.reset_variations', function () { counts = {}; setTimeout(sync, 0); });
	sync();

	/* ---- Barra fija inferior en móvil ---- */
	var $price = $('.summary .price, .entry-summary .price').first();
	var $btn = $form.find('.single_add_to_cart_button').first();
	var bar = document.createElement('div');
	bar.className = 'mpc-sticky'; bar.hidden = true;
	bar.innerHTML = '<div class="mpc-sticky__txt"><strong class="mpc-sticky__n"></strong><span class="mpc-sticky__p"></span></div><button type="button" class="mpc-sticky__btn"></button>';
	document.body.appendChild(bar);
	/* Betheme tiene su propia barra de iconos abajo en móvil: la mía va justo encima. */
	function placeBar() {
		var sm = document.querySelector('.mfn-footer-stickymenu'), h = 0;
		if (sm && getComputedStyle(sm).display !== 'none' && getComputedStyle(sm).position === 'fixed') { h = Math.round(sm.getBoundingClientRect().height); }
		bar.style.bottom = h + 'px';
		bar.style.paddingBottom = h ? '.7rem' : '';
	}
	placeBar(); $(window).on('resize load', placeBar);
	var $n = $(bar).find('.mpc-sticky__n'), $p = $(bar).find('.mpc-sticky__p'), $b = $(bar).find('.mpc-sticky__btn');
	function stickyText() {
		var t = total(counts), done = t >= S;
		$n.text(done ? 'Pack completo' : t + ' de ' + S + ' sabores');
		var priceTxt = ($price.find('ins .amount').last().text() || $price.find('.amount').last().text() || '').trim();
		$p.text(priceTxt);
		$b.text(done ? 'Añadir al carrito' : 'Elegir sabores').toggleClass('is-ready', done);
	}
	$(document).on('mpc:change', stickyText); stickyText();
	$b.on('click', function () {
		if (total(counts) >= S && $btn.length) { $btn.trigger('click'); }
		else { host.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
	});
	if ('IntersectionObserver' in window && $btn.length) {
		new IntersectionObserver(function (e) {
			var visible = e[0].isIntersecting;
			bar.hidden = visible || window.innerWidth > 849;
		}, { threshold: 0.2 }).observe($btn[0]);
	}
})(jQuery);
