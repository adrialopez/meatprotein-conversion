/* Categorías de la portada: el carrusel de Betheme (Swiper con autoplay y bucle) va a trompicones en móvil.
   Se sustituye por desplazamiento nativo con imán, que es fluido y respeta el gesto del dedo. */
(function () {
	'use strict';
	var tries = 0;
	function init() {
		if (window.innerWidth >= 768) { return true; }
		var els = document.querySelectorAll('.mfn-looped-items-slider-wrapper .swiper');
		if (!els.length) { return false; }
		var pending = false;
		Array.prototype.forEach.call(els, function (el) {
			if (el.getAttribute('data-mp-native')) { return; }
			if (!el.swiper) { pending = true; return; }
			try { el.swiper.destroy(true, true); } catch (e) { /* sin Swiper activo */ }
			el.setAttribute('data-mp-native', '1');
			el.classList.add('mp-native-scroll');
		});
		return !pending;
	}
	function loop() { if (init() || ++tries > 40) { return; } setTimeout(loop, 250); }
	if (document.readyState === 'complete') { loop(); } else { window.addEventListener('load', loop); }
})();
