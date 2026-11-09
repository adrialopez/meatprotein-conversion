/**
 * Carrito lateral de Betheme: nombres limpios, barra de envío gratis, sabores plegados y garantías.
 * El carrito se repinta con fragmentos de WooCommerce, así que se vuelve a mejorar tras cada cambio.
 */
(function () {
	'use strict';
	var holder = document.querySelector('.mfn-cart-holder');
	if (!holder) { return; }

	function cleanName(t) {
		t = String(t).replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{FE0F}]/gu, '').replace(/^\s*promoci[oó]n\s*/i, '').replace(/\s+/g, ' ').trim();
		t = t.toLowerCase().replace(/\bbadeja\b/g, 'bandeja').replace(/\bmamgo\b/g, 'mango');
		t = t.charAt(0).toUpperCase() + t.slice(1);
		return t.replace(/meatprotein/gi, 'Meatprotein');
	}

	function enhance() {
		if (holder.dataset.mpcBusy) { return; }
		holder.dataset.mpcBusy = '1';
		try {
			// 1) nombres de los packs limpios
			holder.querySelectorAll('.mfn-chp-name a').forEach(function (a) {
				var t = a.textContent, letters = t.replace(/[^A-Za-zÁÉÍÓÚÑáéíóúñ]/g, ''), up = t.replace(/[^A-ZÁÉÍÓÚÑ]/g, '');
				if ((/bandejas/i.test(t) && /promoci/i.test(t)) || (letters.length > 4 && up.length / letters.length > 0.7)) { a.textContent = cleanName(t); }
			});
			// filas de impuestos fuera: el total ya los incluye
			holder.querySelectorAll('.mfn-chft-row').forEach(function (r) { if (/^\s*(VAT|IVA|Impuestos?)\b/i.test(r.textContent)) { r.classList.add('mpc-hide'); } });
			// 2) sabores plegados en un <details>
			holder.querySelectorAll('.mfn-chp-info dl.variation').forEach(function (dl) {
				if (dl.parentNode.classList.contains('mpc-sc-flavours')) { return; }
				var wrap = document.createElement('details');
				wrap.className = 'mpc-sc-flavours';
				var sum = document.createElement('summary');
				sum.textContent = 'Ver tus sabores';
				dl.parentNode.insertBefore(wrap, dl);
				wrap.appendChild(sum); wrap.appendChild(dl);
			});
			// 3) barra de envío gratis, arriba del listado
			var data = document.querySelector('.mpc-cartdata');
			var content = holder.querySelector('.mfn-ch-content-wrapper');
			if (content && data) {
				var bar = content.querySelector('.mpc-ship--sc');
				var count = parseInt(data.getAttribute('data-count') || '0', 10);
				if (!count) {
					if (bar) { bar.remove(); }
				} else {
					var done = data.getAttribute('data-done') === '1';
					if (!bar) {
						bar = document.createElement('div');
						bar.className = 'mpc-ship mpc-ship--sc';
						bar.innerHTML = '<p></p><span class="mpc-ship__bar"><i></i></span>';
						content.insertBefore(bar, content.firstChild);
					}
					var pt = data.getAttribute('data-pt');
					bar.classList.toggle('is-pt', !!pt);
					bar.classList.toggle('is-done', done);
					bar.querySelector('p').innerHTML = pt ? 'Envío a <strong>Portugal: ' + pt + ' €</strong>, también con el pack' : done ? '<strong>Envío gratis conseguido</strong>' : (function () { var t = parseInt(data.getAttribute('data-trays-left') || '10', 10); return 'Te faltan <strong>' + t + (t === 1 ? ' bandeja más' : ' bandejas más') + '</strong> para el envío gratis'; })();
					bar.querySelector('i').style.width = data.getAttribute('data-pct') + '%';
				}
			}
			// 3b) extras («Completa tu pedido») debajo de los productos
			var up = document.querySelector('.mpc-upsell--side');
			var list = holder.querySelector('.mfn-ch-content');
			if (up && list && !holder.contains(up)) {
				if (!up.hidden) { list.parentNode.insertBefore(up, list.nextSibling); }
			} else if (up && holder.contains(up) && list && up.previousElementSibling !== list) {
				list.parentNode.insertBefore(up, list.nextSibling);
			}
			// 4) garantías junto al botón de pago
			var footer = holder.querySelector('.mfn-ch-footer');
			if (footer && !footer.querySelector('.mpc-sc-trust')) {
				var t = document.createElement('ul');
				t.className = 'mpc-sc-trust';
				t.innerHTML = '<li>Refrigerado, antes de las 14:00</li><li>Pago seguro</li><li>Reposición si llega mal</li>';
				footer.appendChild(t);
			}
			// 5) «Seguir comprando» cierra el panel
			if (footer && !footer.querySelector('.mpc-sc-continue')) {
				var b = document.createElement('button');
				b.type = 'button'; b.className = 'mpc-sc-continue'; b.textContent = 'Seguir comprando';
				b.addEventListener('click', function () { var x = holder.querySelector('.mfn-ch-header .close, .mfn-ch-header a, .mfn-ch-header button'); if (x) { x.click(); } });
				var tr = footer.querySelector('.mpc-sc-trust');
				if (tr && tr.parentNode === footer) { footer.insertBefore(b, tr); } else { footer.appendChild(b); }
			}
		} finally {
			delete holder.dataset.mpcBusy;
		}
	}

	var timer = null;
	function later() { clearTimeout(timer); timer = setTimeout(enhance, 60); }
	new MutationObserver(function (m) {
		// ignora los cambios que hace este mismo script
		if (holder.dataset.mpcBusy) { return; }
		later();
	}).observe(holder, { childList: true, subtree: true });
	var data0 = document.querySelector('.mpc-cartdata');
	if (data0) { new MutationObserver(later).observe(data0.parentNode || document.body, { childList: true, attributes: true, subtree: true, attributeFilter: ['data-count', 'data-pct', 'data-trays-left', 'data-done'] }); }
	if (window.jQuery) { jQuery(document.body).on('wc_fragments_refreshed wc_fragments_loaded added_to_cart removed_from_cart updated_cart_totals', later); }
	enhance();
})();
