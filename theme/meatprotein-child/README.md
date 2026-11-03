# Meatprotein (tema hijo de Betheme)

Reparto de trabajo con el plugin `meatprotein-conversion`:

| Dónde | Qué |
|---|---|
| **Plugin** | Lógica (pack a medida, stock por bandeja, carrito, pago, extras, ajustes), componentes `.mpc-*`, variables `--mpc-*`, instalador del diseño en Betheme (`wp mpc layout apply`) y revisión del catálogo (`wp mpc products audit`). |
| **Tema hijo** | Adaptación a Betheme y WooCommerce: selectores del tema (`.mfn-*`, `.mcb-*`, `.column_*`, `body.single-product`…), cabecera, menú, fichas, carrito y pago. |

Al activar el tema hijo se copian los ajustes del tema padre (menús, áreas de widgets, CSS adicional).

Instalación: subir la carpeta `meatprotein-child` a `wp-content/themes/`, activar y, después, aplicar el diseño desde **WooCommerce > Conversión**.
