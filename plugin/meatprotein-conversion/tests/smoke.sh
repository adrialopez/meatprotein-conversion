#!/usr/bin/env bash
# Prueba de humo: páginas clave responden y llevan lo esperado. Uso: tests/smoke.sh [http://localhost:8095]
BASE="${1:-http://localhost:8095}"
fail=0
check() { # url, marcador (grep -E)
	local body code
	body=$(curl -sL -w '\n%{http_code}' "$BASE$1")
	code=$(printf '%s' "$body" | tail -n1)
	if [ "$code" != "200" ]; then echo "FALLA $1 -> HTTP $code"; fail=1; return; fi
	if [ -n "$2" ] && ! printf '%s' "$body" | grep -Eq "$2"; then echo "FALLA $1 -> falta: $2"; fail=1; return; fi
	echo "ok    $1"
}
check "/" 'class="mpc-hero"'
check "/" 'mpc-assure'
check "/packs/" 'mpc-pack-card'
check "/producto/10-bandejas-de-hamburguesas-meatprotein-1-paquete-de-wraps-de-regalo/" 'data-mpc-pack|mpc-bundle'
check "/producto/hamburguesa-de-sabor-ternera/" 'data-kind="single"'
# Productos básicos: con el alcance de la primera iteración conservan la ficha de Betheme (SCOPE_ALL=1 si «Alcance» está activado).
absent() { # url, marcador que NO debe aparecer
	if curl -sL "$BASE$1" | grep -Eq "$2"; then echo "FALLA $1 -> aparece: $2"; fail=1; else echo "ok    $1 (sin $2)"; fi
}
if [ "${SCOPE_ALL:-0}" = "1" ]; then
	check "/producto/pan-de-hamburguesas-cosa-nostra/" 'data-kind="basic"'
	check "/producto/diet-pizza-barbacoa-440g/" 'Combínalo con'
else
	absent "/producto/pan-de-hamburguesas-cosa-nostra/" 'data-kind="basic"'
	absent "/producto/diet-pizza-barbacoa-440g/" 'Combínalo con'
fi
check "/tienda/" 'woocommerce'
check "/carrito/" 'woocommerce'
check "/finalizar-compra/" 'woocommerce'
# Sin avisos de PHP en el HTML.
for u in "/" "/packs/" "/tienda/"; do
	if curl -s "$BASE$u" | grep -Eqi 'Warning:|Notice:|Fatal error|Deprecated:'; then echo "FALLA $u -> aviso de PHP"; fail=1; fi
done
exit $fail
