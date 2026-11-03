/** Hero interactivo de la portada: se tocan sabores y el botón lleva al pack con ellos ya elegidos (?mpc_pre=clave,clave,...). */
(function () {
	'use strict';
	var host = document.querySelector('[data-mpc-hero]');
	if (!host) { return; }
	var size = parseInt(host.getAttribute('data-size'), 10) || 10;
	var url = host.getAttribute('data-url');
	var price = host.getAttribute('data-price') || '';
	var tiles = Array.prototype.slice.call(host.querySelectorAll('.mpc-tile'));
	var counts = {};
	var elCount = host.querySelector('[data-mpc-hcount]');
	var bar = host.querySelector('.mpc-hero__bar i');
	var cta = host.querySelector('.mpc-hero__cta');
	var ctaTxt = host.querySelector('[data-mpc-hcta]');
	var ctaPrice = host.querySelector('[data-mpc-hprice]');
	var clear = host.querySelector('[data-mpc-hclear]');

	function total() { return Object.keys(counts).reduce(function (a, k) { return a + counts[k]; }, 0); }
	function href() {
		var keys = [];
		tiles.forEach(function (t) {
			var k = t.getAttribute('data-key');
			for (var i = 0; i < (counts[k] || 0); i++) { keys.push(encodeURIComponent(k)); }
		});
		return keys.length ? url + (url.indexOf('?') > -1 ? '&' : '?') + 'mpc_pre=' + keys.join(',') : url;
	}
	function render() {
		var n = total();
		tiles.forEach(function (t) {
			var k = t.getAttribute('data-key'), c = counts[k] || 0;
			var badge = t.querySelector('.mpc-tile__c'), minus = t.querySelector('.mpc-tile__m');
			badge.hidden = !c; minus.hidden = !c; badge.textContent = c;
			t.classList.toggle('is-on', c > 0);
			t.querySelector('.mpc-tile__add').disabled = n >= size && !c;
		});
		elCount.textContent = n + ' de ' + size;
		bar.style.width = (n / size * 100) + '%';
		bar.parentNode.classList.toggle('is-full', n >= size);
		clear.hidden = n === 0;
		if (n === 0) { ctaTxt.textContent = 'Elegir mis sabores'; ctaPrice.hidden = false; }
		else if (n < size) { ctaTxt.textContent = 'Seguir con ' + n + (n === 1 ? ' sabor' : ' sabores'); ctaPrice.hidden = true; }
		else { ctaTxt.textContent = 'Pack listo: añadir extras'; ctaPrice.hidden = true; }
		cta.setAttribute('href', href());
	}
	host.addEventListener('click', function (e) {
		var t = e.target.closest('.mpc-tile');
		if (!t) { return; }
		var k = t.getAttribute('data-key');
		if (e.target.closest('.mpc-tile__m')) { counts[k] = Math.max(0, (counts[k] || 0) - 1); if (!counts[k]) { delete counts[k]; } render(); return; }
		if (e.target.closest('.mpc-tile__add') && total() < size) { counts[k] = (counts[k] || 0) + 1; render(); }
	});
	clear.addEventListener('click', function () { counts = {}; render(); });
	render();

	/* El número de proteína sube desde cero una sola vez, salvo que se prefiera menos movimiento. */
	var num = document.querySelector('[data-mpc-count]');
	if (num && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
		var to = parseInt(num.getAttribute('data-mpc-count'), 10) || 0, t0 = null;
		num.textContent = '0';
		var step = function (ts) {
			if (t0 === null) { t0 = ts; }
			var p = Math.min(1, (ts - t0) / 900);
			num.textContent = Math.round(to * (1 - Math.pow(1 - p, 3)));
			if (p < 1) { requestAnimationFrame(step); }
		};
		requestAnimationFrame(step);
	}
})();
