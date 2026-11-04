# Cambios

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
