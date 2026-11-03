# Meatprotein Conversion

Plugin de WordPress/WooCommerce con mejoras de conversión para meatprotein.eu (tema Betheme).
Es una propuesta: se probó solo en una copia local y **no está instalado en producción**.

## Qué hace

| Pieza | Para qué |
|---|---|
| Selector de sabores del pack | Sustituye 10 desplegables de ~24 opciones por una cuadrícula con +/-, contador y barra de progreso. Rellena los desplegables de WooCommerce por detrás (emparejamiento sabor -> bandeja), así carrito, pedidos y emails no cambian. El regalo (atributo con una sola opción) se elige solo. |
| Barra fija en móvil | Progreso del pack y botón «Añadir al carrito», por encima de la barra de iconos de Betheme. |
| Garantías junto al botón | Refrigerado SEUR 24-48 h, envío gratis desde X €, reposición si llega mal, congelación. Datos tomados de las páginas Envíos y FAQs de la web. |
| Info de cada sabor | Botón «Info» junto a cada sabor: abre la imagen de ingredientes, peso y valores nutricionales que ya está en la descripción de esa hamburguesa (mapa sabor -> producto en `includes/flavours.php`). |
| Bloques de información | Qué recibes, cómo llega y se conserva, calidad y seguridad, información nutricional y fabricante (estos dos se muestran solo si se rellenan en WooCommerce > Conversión). |
| Barra de envío gratis | Carrito, checkout y minicarrito: «Te faltan X € para el envío gratis». |
| Promo puntual | Barra superior con texto, enlace y cuenta atrás; desaparece sola al terminar. |
| `[mpc_packs]` | Comparador de packs para una página «Packs». |
| Checkout | Cupón dentro del resumen, miniatura, sabores en una línea, campos y métodos de pago como en Pifarré, cabecera y pie mínimos. |
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
