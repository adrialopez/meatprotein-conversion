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

	var INFO = {};
	try { INFO = JSON.parse(host.getAttribute('data-flavours') || '{}') || {}; } catch (e) { INFO = {}; }
	/* Stock de cada hamburguesa individual: s = hay stock, m = bandejas disponibles. */
	function soldOut(k) { return !!(INFO[k] && INFO[k].s === 0); }
	function maxOf(k) { return (INFO[k] && INFO[k].m) ? INFO[k].m : 99; }
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
	var keys = Object.keys(flavours).filter(function (k) { return !soldOut(k); });
	if (!keys.length) { return; }

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
		html += '<details class="mpc-pack__group" data-g="' + g + '"' + ((window.innerWidth >= 850 || g === groups[0]) ? ' open' : '') + '><summary><span>' + g + '</span><em class="mpc-pack__gc"></em></summary><div class="mpc-pack__grid">';
		items.forEach(function (k) {
			html += '<div class="mpc-flavour" data-k="' + k + '"><span class="mpc-flavour__name">' + flavours[k].label + (INFO[k] ? ' <button type="button" class="mpc-info-btn" data-info="' + k + '" aria-label="Información de ' + flavours[k].label + '">Info</button>' : '') + (INFO[k] && INFO[k].d ? '<small class="mpc-flavour__sub">' + INFO[k].d.prot + ' g de proteína · ' + INFO[k].d.kcal + ' kcal</small>' : '') + '</span>' +
				'<span class="mpc-step"><button type="button" class="mpc-step__b" data-d="-1" aria-label="Quitar ' + flavours[k].label + '">−</button><output class="mpc-step__n">0</output><button type="button" class="mpc-step__b" data-d="1" aria-label="Añadir ' + flavours[k].label + '">+</button></span></div>';
		});
		html += '</div></details>';
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
		$(host).find('.mpc-pack__group').each(function () {
			var n = 0; $(this).find('.mpc-flavour').each(function () { n += counts[this.getAttribute('data-k')] || 0; });
			$(this).find('.mpc-pack__gc').text(n ? n + (n === 1 ? ' elegida' : ' elegidas') : '').toggleClass('is-on', n > 0);
		});
		$(host).find('.mpc-flavour').each(function () {
			var k = this.getAttribute('data-k'), n = counts[k] || 0, c2 = $.extend({}, counts); c2[k] = n + 1;
			$(this).toggleClass('is-on', n > 0).find('.mpc-step__n').text(n);
			$(this).find('[data-d="-1"]').prop('disabled', n === 0);
			$(this).find('[data-d="1"]').prop('disabled', t >= S || n >= maxOf(k) || !assign(c2));
		});
		/* Volcado a los desplegables de WooCommerce. */
		var a = assign(counts);
		selects.forEach(function (sel, i) {
			var v = a && a.slotUnit[i] >= 0 ? slots[i][a.units[a.slotUnit[i]]] : '';
			if (sel.value !== v) { sel.value = v; }
		});
		$form.trigger('check_variations');
		window.mpcPackState = { count: t, size: S };
		$(document).trigger('mpc:change', [t, S]);
	}

	$(host).on('click', '.mpc-step__b', function () {
		var k = $(this).closest('.mpc-flavour').attr('data-k'), d = parseInt(this.getAttribute('data-d'), 10);
		counts[k] = Math.max(0, (counts[k] || 0) + d);
		if (!counts[k]) { delete counts[k]; }
		if ((counts[k] || 0) > maxOf(k) || !assign(counts)) { counts[k] = (counts[k] || 0) - d; if (!counts[k]) { delete counts[k]; } }
		sync();
	});
	/* Ficha de ingredientes y valores nutricionales de cada sabor. */
	var dlg = null;
	function esc(t) { return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
	function infoHtml(d) {
		var x = d.d;
		if (!x) { return '<img class="mpc-dialog__img" src="' + esc(d.i) + '" alt="Ingredientes y valores nutricionales de ' + esc(d.t) + '">'; }
		var ing = esc(x.ing).replace(/\b([A-ZÁÉÍÓÚÑ]{3,})\b/g, '<strong>$1</strong>');
		var row = function (a, b) { return '<tr><th scope="row">' + a + '</th><td>' + b + '</td></tr>'; };
		return '<p class="mpc-dialog__chips"><strong>' + esc(x.prot) + ' g</strong> de proteína · <strong>' + esc(x.kcal) + ' kcal</strong> por 100 g</p>' +
			'<h4>Ingredientes</h4><p>' + ing + '</p>' +
			'<h4>Peso neto</h4><p>' + esc(x.peso) + '</p>' +
			'<h4>Información nutricional (por 100 g)</h4><table class="mpc-table mpc-table--dlg"><tbody>' +
			row('Valor energético', esc(x.kj) + ' kJ / ' + esc(x.kcal) + ' kcal') + row('Grasas', esc(x.fat) + ' g') + row('de las cuales saturadas', esc(x.sat) + ' g') +
			row('Hidratos de carbono', esc(x.carb) + ' g') + row('de los cuales azúcares', esc(x.sug) + ' g') + row('Proteínas', esc(x.prot) + ' g') + row('Sal', esc(x.salt) + ' g') + '</tbody></table>';
	}
	function openInfo(k) {
		var d = INFO[k]; if (!d) { return; }
		if (!dlg) {
			dlg = document.createElement('dialog');
			dlg.className = 'mpc-dialog';
			dlg.innerHTML = '<form method="dialog"><button class="mpc-dialog__x" aria-label="Cerrar">×</button></form><h3 class="mpc-dialog__t"></h3><div class="mpc-dialog__b"></div><p class="mpc-dialog__l"></p>';
			document.body.appendChild(dlg);
			dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
		}
		dlg.querySelector('.mpc-dialog__t').textContent = d.t;
		dlg.querySelector('.mpc-dialog__b').innerHTML = infoHtml(d);
		dlg.querySelector('.mpc-dialog__l').innerHTML = (d.d ? '<a href="' + esc(d.i) + '" target="_blank" rel="noopener">Ver la etiqueta original</a> · ' : '') + '<a href="' + esc(d.u) + '" target="_blank" rel="noopener">Ver la hamburguesa</a>';
		if (dlg.showModal) { dlg.showModal(); } else { window.open(d.u, '_blank'); }
	}
	$(host).on('click', '[data-info]', function (e) { e.preventDefault(); e.stopPropagation(); openInfo(this.getAttribute('data-info')); });
	$(host).on('click', '[data-act="clear"]', function () { counts = {}; sync(); });
	$(host).on('click', '[data-act="random"]', function () {
		var guard = 0;
		while (total(counts) < S && guard++ < 400) {
			var k = keys[Math.floor(Math.random() * keys.length)], c2 = $.extend({}, counts); c2[k] = (c2[k] || 0) + 1;
			if (c2[k] <= maxOf(k) && assign(c2)) { counts = c2; }
		}
		sync();
	});
	/* «Restablecer» de WooCommerce vacía también el selector. (reset_data se dispara con cada pack incompleto: no sirve.) */
	$form.on('click', '.reset_variations', function () { counts = {}; setTimeout(sync, 0); });
	sync();

	/* Estado del pack para el botón fijo (bundle.js) y «subir a pack» desde una bandeja suelta (?mpc_pre=clave). */
	window.mpcPackState = { count: total(counts), size: S };
	try {
		var pre = new URLSearchParams(location.search).get('mpc_pre');
		if (pre && flavours[pre] && !soldOut(pre) && assign($.extend({}, counts, (function () { var o = {}; o[pre] = 1; return o; })()))) {
			counts[pre] = 1; sync();
			setTimeout(function () { host.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 400);
		}
	} catch (e) { /* sin parámetro: nada que hacer */ }
})(jQuery);
