/**
 * Flujo de compra en una sola pantalla (pack y bandejas sueltas), al estilo de las apps de pedidos:
 * preguntas «¿Quieres…?» con tarjetas en carrusel, «+» que pasa a +/−, botón fijo con el precio vivo
 * («Añadir 1 por 61,50 €») y una ventana de upsell antes de añadir.
 */
(function ($) {
	'use strict';
	var root = document.querySelector('.mpc-bundle');
	if (!root) { return; }
	var $form = $(root).closest('form.cart');
	var isPack = root.getAttribute('data-kind') === 'pack';
	var base = parseFloat(root.getAttribute('data-base')) || 0;
	var pid = root.getAttribute('data-product') || '0';
	var mobile = window.matchMedia('(max-width: 849px)');
	var fmt = function (n) { return n.toFixed(2).replace('.', ',') + ' €'; };
	var $cards = $(root).find('.mpc-card[data-id]');

	/* ---- Tarjetas: «+» y cantidad ---- */
	function qty($c) { return parseInt($c.find('input[type="hidden"]').val(), 10) || 0; }
	function max($c) { return parseInt($c.attr('data-max'), 10) || 10; }
	function paint($c) {
		var v = qty($c);
		$c.toggleClass('is-on', v > 0);
		$c.find('.mpc-card__add').prop('hidden', v > 0);
		$c.find('.mpc-card__qty').prop('hidden', v <= 0).find('output').text(v);
		$c.find('[data-d="1"]').prop('disabled', v >= max($c));
	}
	function setQty($c, v) { $c.find('input[type="hidden"]').val(Math.max(0, Math.min(max($c), v))); paint($c); refresh(); }
	$(root).on('click', '.mpc-card__add', function () { setQty($(this).closest('.mpc-card'), 1); });
	$(root).on('click', '.mpc-card__qty button', function () { var $c = $(this).closest('.mpc-card'); setQty($c, qty($c) + parseInt(this.getAttribute('data-d'), 10)); });

	function extras() {
		var items = [], total = 0;
		$cards.each(function () { var $c = $(this), q = qty($c); if (q > 0) { items.push(q > 1 ? $c.attr('data-name') + ' ×' + q : $c.attr('data-name')); total += (parseFloat($c.attr('data-price')) || 0) * q; } });
		return { items: items, total: total };
	}
	function mainQty() { return Math.max(1, parseInt($form.find('input.qty').first().val(), 10) || 1); }
	function packState() { return window.mpcPackState || { count: 0, size: 10 }; }

	/* ---- Botón fijo inferior ---- */
	var bar = document.createElement('div');
	bar.className = 'mpc-cta'; bar.hidden = true;
	bar.innerHTML = (isPack ? '' : '<div class="mpc-cta__qty" role="group" aria-label="Cantidad"><button type="button" data-d="-1" aria-label="Quitar una bandeja">−</button><output>1</output><button type="button" data-d="1" aria-label="Añadir una bandeja">+</button></div>') + '<button type="button" class="mpc-cta__btn"><span class="mpc-cta__txt"></span></button>';
	document.body.appendChild(bar);
	var $btn = $(bar).find('.mpc-cta__btn'), $txt = $(bar).find('.mpc-cta__txt');
	document.body.classList.add('mpc-has-cta');

	function refresh() {
		var ex = extras(), q = mainQty(), st = packState();
		$(root).find('[data-mpc-extras]').text(fmt(ex.total));
		$(root).find('[data-mpc-sumtxt]').text(ex.items.join(', '));
		$(root).find('.mpc-bundle__sum').prop('hidden', ex.items.length === 0);
		if (!isPack) { $(bar).find('.mpc-cta__qty output').text(q); $(bar).find('.mpc-cta__qty [data-d="-1"]').prop('disabled', q <= 1); }
		var incomplete = isPack && st.count < st.size;
		$btn.toggleClass('is-wait', incomplete);
		$txt.text(incomplete ? 'Elige ' + (st.size - st.count) + (st.size - st.count === 1 ? ' sabor más' : ' sabores más') : 'Añadir ' + q + ' por ' + fmt(base * q + ex.total));
	}
	$(document).on('mpc:change', refresh);
	$form.on('change input', 'input.qty', refresh);
	$(bar).on('click', '.mpc-cta__qty button', function () {
		var $i = $form.find('input.qty').first(), mx = parseInt($i.attr('max'), 10) || 99;
		$i.val(Math.max(1, Math.min(mx, mainQty() + parseInt(this.getAttribute('data-d'), 10)))).trigger('change');
	});

	/* ---- Ventana de upsell, una vez por producto y sesión ---- */
	var dlg = null;
	function askedKey() { return 'mpc_combo_' + pid; }
	function wasAsked() { try { return sessionStorage.getItem(askedKey()) === '1'; } catch (e) { return false; } }
	function markAsked() { try { sessionStorage.setItem(askedKey(), '1'); } catch (e) { /* nada */ } }
	function submitReal() {
		var b = $form.find('.single_add_to_cart_button').get(0);
		if (b && !b.disabled && !$(b).hasClass('disabled')) { b.click(); }
		else if (isPack) { var h = document.querySelector('[data-mpc-pack]'); if (h) { h.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
	}
	function suggestion() {
		if (wasAsked()) { return null; }
		if (isPack) {
			if (extras().items.length) { return null; }
			var picks = [];
			['pan', 'queso', 'salsa'].forEach(function (k) { var $c = $(root).find('.mpc-q--' + k + ' .mpc-card[data-id]').first(); if ($c.length) { picks.push($c); } });
			if (!picks.length) { return null; }
			var tot = 0, names = [];
			picks.forEach(function ($c) { tot += parseFloat($c.attr('data-price')) || 0; names.push($c.attr('data-name')); });
			return { type: 'extras', picks: picks, total: tot, names: names };
		}
		var url = root.getAttribute('data-pack-url');
		if (!url || mainQty() >= 10) { return null; }
		return { type: 'pack', url: url, price: parseFloat(root.getAttribute('data-pack-price')) || 0, name: root.getAttribute('data-pack-name') || 'el pack', key: root.getAttribute('data-flavour-key') || '' };
	}
	function ensureDialog() {
		if (dlg) { return dlg; }
		dlg = document.createElement('dialog');
		dlg.className = 'mpc-combo';
		dlg.innerHTML = '<button type="button" class="mpc-combo__x" aria-label="Cerrar">×</button><h3 class="mpc-combo__t"></h3><p class="mpc-combo__p"></p><div class="mpc-combo__b"><button type="button" class="mpc-combo__no"></button><button type="button" class="mpc-combo__yes"></button></div>';
		document.body.appendChild(dlg);
		dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
		dlg.querySelector('.mpc-combo__x').addEventListener('click', function () { dlg.close(); });
		return dlg;
	}
	function ask(sg) {
		var d = ensureDialog(), t = d.querySelector('.mpc-combo__t'), p = d.querySelector('.mpc-combo__p'), no = d.querySelector('.mpc-combo__no'), yes = d.querySelector('.mpc-combo__yes');
		var cNo = no.cloneNode(true), cYes = yes.cloneNode(true); no.replaceWith(cNo); yes.replaceWith(cYes);
		if (sg.type === 'extras') {
			t.textContent = 'Completa tu pedido por ' + fmt(sg.total) + ' más';
			p.textContent = sg.names.join(' + ') + '.';
			cNo.textContent = 'No, gracias'; cYes.textContent = 'Sí, añadir';
			cYes.addEventListener('click', function () { sg.picks.forEach(function ($c) { setQty($c, 1); }); markAsked(); d.close(); submitReal(); });
		} else {
			t.textContent = 'Hazlo pack: 10 bandejas por ' + fmt(sg.price);
			p.textContent = 'Envío refrigerado gratis y eliges los sabores. Empezamos por esta hamburguesa.';
			cNo.textContent = 'No, solo esta'; cYes.textContent = 'Sí, quiero el pack';
			cYes.addEventListener('click', function () { markAsked(); window.location.href = sg.url + (sg.key ? (sg.url.indexOf('?') > -1 ? '&' : '?') + 'mpc_pre=' + encodeURIComponent(sg.key) : ''); });
		}
		cNo.addEventListener('click', function () { markAsked(); d.close(); submitReal(); });
		cYes.setAttribute('autofocus', '');
		if (d.showModal) { d.showModal(); cYes.focus({ preventScroll: true }); } else { markAsked(); submitReal(); }
	}
	$btn.on('click', function () {
		if (isPack && packState().count < packState().size) { var h = document.querySelector('[data-mpc-pack]'); if (h) { h.scrollIntoView({ behavior: 'smooth', block: 'start' }); } return; }
		var sg = suggestion();
		if (sg) { ask(sg); } else { submitReal(); }
	});
	/* También si se pulsa el botón de siempre. */
	$form.on('click', '.single_add_to_cart_button', function (e) {
		if (this.dataset.mpcGo) { delete this.dataset.mpcGo; return; }
		var sg = suggestion();
		if (sg && !($(this).hasClass('disabled'))) { e.preventDefault(); e.stopImmediatePropagation(); ask(sg); }
	});

	/* ---- Visibilidad: en móvil siempre; en escritorio solo cuando el botón real no se ve ---- */
	var realVisible = false;
	function place() { bar.hidden = mobile.matches ? false : realVisible; }
	var real = $form.find('.single_add_to_cart_button').get(0);
	if (real && 'IntersectionObserver' in window) { new IntersectionObserver(function (e) { realVisible = e[0].isIntersecting; place(); }, { threshold: 0.2 }).observe(real); }
	if (mobile.addEventListener) { mobile.addEventListener('change', place); }
	$cards.each(function () { paint($(this)); });
	refresh(); place();
})(jQuery);
