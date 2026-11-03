# Meatprotein Conversion

Plugin de WordPress/WooCommerce con mejoras de conversión para meatprotein.eu (tema Betheme).
Es una propuesta: se probó solo en una copia local y **no está instalado en producción**.

## Qué hace

| Pieza | Para qué |
|---|---|
| Selector de sabores del pack | Sustituye 10 desplegables de ~24 opciones por una cuadrícula con +/-, contador y barra de progreso. Rellena los desplegables de WooCommerce por detrás (emparejamiento sabor -> bandeja), así carrito, pedidos y emails no cambian. El regalo (atributo con una sola opción) se elige solo. |
| Barra fija en móvil | Progreso del pack y botón «Añadir al carrito», por encima de la barra de iconos de Betheme. |
| Garantías junto al botón | Refrigerado SEUR 24-48 h, envío gratis desde X €, reposición si llega mal, congelación. Datos tomados de las páginas Envíos y FAQs de la web. |
| Info de cada sabor | Botón «Info» junto a cada sabor con ingredientes, peso y valores nutricionales **en texto** (transcritos de las etiquetas que hay como imagen en cada hamburguesa; datos en `data/flavours.json`, mapa sabor -> producto en `includes/flavours.php`). Cada tarjeta muestra proteína y kcal. La ficha del pack incluye una tabla con todos los sabores. **Revisar los datos antes de publicar** (ver más abajo). |
| Bloques de información | Qué recibes, cómo llega y se conserva, calidad y seguridad, información nutricional y fabricante (estos dos se muestran solo si se rellenan en WooCommerce > Conversión). |
| Barra de envío gratis | Carrito, checkout y minicarrito: «Te faltan X € para el envío gratis». |
| Promo puntual | Barra superior con texto, enlace y cuenta atrás; desaparece sola al terminar. |
| `[mpc_packs]` | Comparador de packs para una página «Packs». |
| Checkout | Cupón dentro del resumen, miniatura, sabores en una línea, campos y métodos de pago como en Pifarré, cabecera y pie mínimos. |
| Capa de diseño (`assets/css/design.css`) | Tipografía fluida (cuerpo 16-17 px, interlineado 1,65, títulos con `text-wrap: balance`), contraste AA (rosa de texto y botones #c23a7a, 5,0:1; el original #d65491 daba 3,8:1), botones de 48 px, foco visible, `prefers-reduced-motion`, transiciones entre páginas (`@view-transition`). Hero con velo oscuro y titular blanco; categorías como tarjetas en cuadrícula (el carrusel mostraba una sola diapositiva de 1300 px); tarjetas de producto con nombre/precio más grandes, proteína y kcal por hamburguesa y botón a todo el ancho; banners «Snacks» y «Pizza fit» sin texto pisado por la imagen. Los selectores `.mcb-section-XXXX` son IDs de las secciones de la portada en Betheme: si se rehace la portada hay que revisarlos. |
| Scroll en iOS | `overflow-x: clip` en `html` y `body` (Betheme pone `hidden` en ambos y bloquea el scroll). |

## Detección de packs

Un producto es «pack» si es variable y tiene al menos 4 atributos de variación llamados `Bandeja N`.
En producción es un solo producto (#38190) que se renombra con cada promo.

## Local

```
cd local
docker compose up -d      # http://localhost:8095
```

La base de datos local se exporta **sin clientes ni pedidos** (solo productos, páginas, plantillas de Betheme y ajustes) y con
`--default-character-set=utf8mb4` (si no, los emojis pasan a `?` y WooCommerce no puede leer los atributos del pack).
`site-copy/` y `backup/` no se versionan. `local/local-safety.php` es el mu-plugin que bloquea el correo y sirve las imágenes desde producción.

## Despliegue

ZIP `meatprotein-conversion-x.y.z.zip` (carpeta `meatprotein-conversion/`). Antes de activar en producción: copia de la web,
rellenar WooCommerce > Conversión y comprobar un pedido de prueba con cada pasarela.

## Datos nutricionales: revisar antes de publicar

`data/flavours.json` se transcribió a mano de las imágenes de etiqueta (por 100 g). Son datos de etiquetado alimentario:
hay que cotejarlos con las etiquetas reales y con la ficha técnica. Dudas detectadas al transcribir:

- **Pollo y trufa (#18999):** la imagen de su descripción da 404, no hay datos.
- Tres etiquetas con valores idénticos (543,9 kJ / 130 kcal / 3,9 / 0,9 / 1,9 / 0,3 / 21,7 / 0,9): Pollo Cheese-Bacon, Ternera y queso, Ternera queso y trufa.
- Tres con 505,3 kJ / 120,7 kcal / 2,7 / 0,8 / 4,8 / 1,4 / 19,3: Pavo y cebolla, Mango Protein Chicken, Pollo miel y tomate.
- En las etiquetas de Pollo miel y tomate (mostaza) y Pollo, pepinillos y cheddar (leche, queso) los alérgenos no están en mayúsculas como en el resto.
