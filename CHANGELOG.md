# Cambios

## 0.17.0
- Checkout: una sola tarjeta de snack antes del pago (solo si aún no tienen el envío gratis: el más barato que lo consigue o el que más se acerca), que se añade con un clic sin recargar y refresca el resumen.
- Tema hijo 1.0.6: cabecera mínima en el checkout (logo pequeño y centrado, sin menú, iconos, botón de productos ni barra promo) y banda del título centrada. La confirmación del pedido conserva la cabecera completa.

## 0.16.0
- Carrito: bloque «¿Un snack para el camino?» con los más vendidos de «Snacks dulces» (o los IDs de los ajustes), con el envío gratis como empujón. «Completa tu pedido» ya no repite snacks.
- Al añadir desde esos bloques se vuelve al carrito sin `?add-to-cart=` (recargar ya no duplica).

## 0.15.2
- /packs/: fuera el aviso «Si llega en mal estado, lo reponemos» (tarjeta y fila de garantías).
- Tema hijo 1.0.5: título de la ficha centrado en su banda y sin la línea de categoría; las preguntas ocupan el ancho de la pestaña (antes se encogían a 240 px).

## 0.15.1
- Portada: más aire bajo la cabecera y entre el pack y la foto.
- Tema hijo 1.0.3: logo centrado, iconos de cabecera claros y contador del carrito legible.

## 0.15.0
- Fichas de productos básicos (barra fija, «Combínalo con», garantías según tipo).
- Diseño montado con las herramientas de Betheme (`wp mpc layout apply|restore`).
- Tema hijo `meatprotein-child` con la adaptación a Betheme.
- Nombres de producto normalizados al mostrarlos (sin tocar los datos).
- `wp mpc products audit|fix|revert` para revisar y corregir el catálogo con copia de lo anterior.
- Buenas prácticas: WPCS limpio, HPOS declarado, dependencia de WooCommerce comprobada, scripts diferidos y datos con `wp_add_inline_script`, cachés con invalidación, `uninstall.php`, prueba de humo (`tests/smoke.sh`).
- Arreglo: los extras elegidos en un producto básico no se añadían al carrito.
