# Cambios

## 0.27.4
- «Información nutricional y alérgenos» solo en hamburguesas. En el resto de productos hay un cuadro opcional en la ficha (admin) que, si se rellena, muestra el desplegable; vacío no muestra nada.

## Tema 1.0.53
- Carrusel de categorías en escritorio: margen arriba y abajo para que la tarjeta elevada con sombra no se corte.

## 0.27.3
- «Quién lo fabrica» solo en hamburguesas y pack. Productos con variaciones (p. ej. Suplementación) usan la misma cabecera de ficha que el resto. Imagen de Suplementación en el menú lateral.

## 0.27.2
- Carrito de página: los sabores del pack salen en lista a todo el ancho (con ×N destacado) en una caja bajo el producto; precio tachado y final alineados. Tema hijo 1.0.48.

## 0.27.1
- Imágenes para compartir: los títulos largos reducen la letra antes de cortarse.

## 0.27.0
- **Imágenes para compartir dinámicas** (1200 × 630 px) del pack, las hamburguesas y las categorías: fondo azul de la marca, nombre, precio (con el tachado si hay oferta), dato de proteína o regalo del pack y la foto de la ficha. Se regeneran solas cuando cambia el título, el precio, la proteína, el regalo o la foto, y Rank Math las usa en Open Graph y X. Fuentes Dela Gothic One e Instrument Sans (licencia OFL) en `assets/fonts/`.

## 0.26.0
- **Fichas de producto sin «/producto/»** (`/mango-protein-chicken/`): Rank Math quita la base y el plugin redirige con 301 `/producto/<slug>/` a `/<slug>/`. Enlaces internos y llms.txt actualizados.

## 0.25.0
- **URLs de categoría cortas:** sin «categoria-producto» ni la categoría padre (`/pan/`, `/saladas/`, `/hamburguesas-de-pollo/`; Rank Math: quitar base y slugs de padres). «Suplantacion» pasa a `/suplementacion/`. Las direcciones antiguas redirigen con 301 a la nueva (también las de categorías anidadas y los slugs renombrados).

## 0.24.4
- Categoría «Snacks y bebidas» con URL nueva (`/categoria-producto/snacks-y-bebidas/`, la antigua redirige con 301); los snacks sugeridos del carrito y llms.txt usan el slug nuevo.

## 0.24.3
- Al guardar el pack aparece un aviso verde con el regalo, las salsas, el pan y el queso que han quedado guardados («automático» si están vacíos).

## 0.24.2
- «frente al precio por separado» solo en el pack; en el resto de productos con oferta: «Oferta: ahorras X (N %)».

## 0.24.1
- Título de Google (Rank Math) de las fichas con el nombre normalizado.

## 0.24.0
- Listados: los productos agotados van al final de cada categoría, etiqueta, tienda y búsqueda.
- Nombres de todo el catálogo sin mayúsculas gritadas ni «NEW» (antes solo hamburguesas y pack): «Grandmas BBQ Louisiana» en lugar de «GRANDMAS BBQ LOUISIANA».

## 0.23.1
- Cuadro de extras del pack: con el modo automático se muestra qué productos salen ahora.

## 0.23.0
- **Extras del pack elegibles en la ficha del pack:** el cuadro «Regalo y extras del pack» permite escoger qué salsas, panes y quesos se ofrecen al comprar (buscador de productos, en el orden elegido). Vacío = automático (los más vendidos). Lo elegido manda sobre los ajustes de Conversión.

## 0.22.3
- Ficha del pack: el regalo de «Qué recibes» se escribe con el nombre real del producto («1 bandeja de Mango Protein Chicken de regalo»), sin pasarlo a minúsculas.

## 0.22.2
- Ficha del pack: «Envío gratis» como etiqueta propia bajo la del regalo (en vez de dentro de la misma frase).

## 0.22.1
- Ficha del pack: la píldora del regalo dice «Incluye 1 bandeja de X de regalo y envío gratis». El cuadro «Etiqueta y pack» ya no aparece en el pack (solo «Regalo del pack»); queda para las hamburguesas.

## 0.22.0
- **El regalo se elige en la ficha del pack** (cuadro «Meatprotein: regalo del pack», buscador de productos; con varios, el cliente elige). Portada, ficha, selector, carrito, pedidos y stock salen de ese producto. El título del pack ya no necesita nombrar el regalo y puede ser solo «10 bandejas de hamburguesas Meatprotein». El ajuste de Conversión queda como respaldo.

## 0.21.6
- El regalo del pack se muestra solo con el nombre del producto regalo («Regalo: Mango Protein Chicken»), tomado del ID de WooCommerce > Conversión, en carrito, pago, pedidos y correos.

## 0.21.5
- Pedidos, correos y administración: el regalo del pack se muestra como «Regalo: 1 bandeja de Mango Protein Chicken» en lugar de «CHICKEN, MANGO: REGALO 🎁» (también en pedidos ya hechos).

## 0.21.4
- Portada: las burgers del «Tu pack» dejan aire respecto a la caja (padding y `scroll-padding`), se difuminan por la derecha y llevan una flecha que avisa de que se deslizan (desaparece al desplazar). Tema 1.0.21: nombre de categoría de la portada que no se corta y botones «Añadir al carrito» más pequeños.

## 0.21.3
- Portada: la rejilla de categorías solo muestra las principales (ya no «Hamburguesas de pollo»).
- Parte pública: se evita que YITH Order Tracking cargue el selector de medios de WordPress para visitantes (quitaba velocidad y daba un error de JavaScript en «Mi cuenta»).

## 0.21.1
- El sello y la frase «alto contenido en proteínas» solo se generan si la proteína aporta al menos el 20 % de la energía (regla del reglamento europeo); «bajo en grasa» solo con 3 g o menos de grasa por 100 g.

## 0.21.0
- **Edición desde el administrador, sin scripts:** cada ficha de producto tiene el cuadro «Meatprotein: etiqueta y pack» con ingredientes, trazas de gluten, tabla nutricional, «Publicar la energía» y «Nombre en el pack». Al guardar se regenera sola la ficha (descripción, tabla, frase de calidad, sellos «Alto contenido en proteínas» / «Bajo en grasa» según los datos, meta descripción de Google, datos estructurados) y el archivo `llms.txt`. Los alérgenos se calculan de las palabras en MAYÚSCULAS de los ingredientes y se avisa si la etiqueta no cuadra (kcal frente a macros, saturadas > grasas...).
- **Sabores del pack automáticos:** WooCommerce > Conversión > «Sabores del pack» lista las fichas con «Nombre en el pack» y el botón «Actualizar los desplegables del pack» los copia a «Bandeja 1…10». El grupo del selector (Pollo, Ternera, Pavo, Potro, Albóndigas...) sale de la subcategoría de la ficha, también para tipos de carne nuevos. Ya no hace falta tocar código para añadir un sabor.
- El selector, la tabla y el cuadro «Info» leen los datos nutricionales de las fichas (`data/flavours.json` queda solo como respaldo).

## 0.20.1
- **Pack:** la opción «Ternera a la parrilla» no tenía correspondencia con su producto (no se descontaba su stock ni salía su información); añadida al mapa. Los sabores se agrupan ahora por la subcategoría de su producto (Pollo, Ternera, Pavo, Potro, Albóndigas), no por palabras del nombre (antes «Bacon» y «Cheese bacon» caían en Ternera y varios sabores en «Otros sabores»).
- Selector: nuevo grupo «Albóndigas» (sin él, las albóndigas no aparecían).
- Hero de la portada: «el envío llega refrigerado antes de las 14:00» en vez de «24-48 h».

## 0.20.0
- **Datos nutricionales a partir de las etiquetas nuevas (nov 2026):** `data/flavours.json` regenerado (26 hamburguesas; ingredientes, alérgenos y tabla); fuera «Pollo y trufa» y «Pollo sabor jamón ibérico» (descatalogadas). Energía sin publicar en Pollo de corral y Pepinillos y cheddar (kcal y kJ no cuadran con los macros): el selector, la tarjeta y la tabla omiten la kcal cuando está vacía.
- La ficha de la bandeja suelta ya no repite el bloque de ingredientes y valores si la descripción del producto lleva su propia tabla (`class="mp-nutri"`).
- Mapa de sabores con los nombres nuevos (miel y mostaza, Mango Protein Chicken, pollo con pepinillos y queso cheddar, pollo con rulo de cabra).

## 0.19.6
- «Envíos y devoluciones» con el texto redactado por el cliente (estructura en tarjetas, índice y secciones); «Meatprotein» unificado.
- Corregido el signo de los desplegables abiertos de las FAQs (aparecía «2212»): `wp_update_post` quita las barras invertidas del CSS, ahora se guarda con `wp_slash` y el signo es un carácter directo.
## 0.19.5
- **Páginas de texto maquetadas** (FAQs, Envíos y devoluciones, Reportar incidencia): columna de lectura centrada, resumen con datos clave, índice por secciones, FAQs en desplegables, tabla de tarifas sin desbordar en móvil y aviso destacado de «producto en mal estado». El contenido final vive en `data/pages/*.html` (se generan con `data/pages/build_pages.py`) y `wp mpc pages fix` lo aplica; ya está en producción.
- Formulario de incidencias: texto de ayuda con plazos y mensajes del formulario en español.
## 0.19.4
- `wp mpc pages fix` quita de «Envíos y devoluciones» el párrafo de la garantía de dos años (alimentos frescos); ya aplicado en producción.

## 0.19.3
- **Políticas y FAQs revisadas con lo que ha confirmado el cliente:** sin desistimiento (alimento fresco, art. 103); sabor equivocado por su error → se compensa en el siguiente pedido, no se reenvía; cancelar o modificar hasta las 11:00 del día de envío (fuera el mínimo de 27 € y las ventanas de las 08:00); incidencias → reposición en 24-72 h; WhatsApp a cualquier hora con respuesta en 24-48 h y teléfono de lunes a viernes 9:30-13:00. `wp mpc pages fix` reescribe esas secciones y quita «recogida», «aduanas» y el procedimiento de devolución con reembolso.
## 0.19.2
- **Producto en mal estado, errores y contacto, según el cliente:** bandejas infladas o en malas condiciones → reposición de un pedido igual en unas 24 a 72 horas (ya no «o te devolvemos el dinero» en portada, ficha, carrito, FAQs y reseñas rápidas); sabor equivocado por su error → regalo en el siguiente pedido; error del cliente → se modifica por WhatsApp. Atención de lunes a viernes de 9:30 a 13:00, con mensaje de WhatsApp contestado en 24-48 h.
## 0.19.1
- **Festivos en el cálculo de entrega.** Ajuste nuevo «Festivos y días sin envío» (WooCommerce > Conversión): una fecha por línea o un rango para vacaciones, ya con los festivos nacionales de fin de 2026 y 2027. No se envía en festivo ni el día antes (la entrega caería en festivo); el aviso salta al siguiente día válido.
- El aviso da el día de llegada con fecha («el miércoles 11/11 antes de las 14:00») en vez de «mañana» o «pasado mañana».
## 0.19.0
- **Horario y zonas de envío del cliente:** hora de corte 13:00 (Madrid), se envía de lunes a jueves y se entrega antes de las 14:00. Aviso con cuenta atrás en ficha, pack, carrito y pago («Tienes 02:14:09 para pedir y recibirlo mañana antes de las 14:00»): lun-jue antes de las 13 → mañana; lun-mié después de las 13 → pasado mañana; jueves 13:00 a lunes 13:00 → el martes. Sustituye al «sale hoy… 24-48 h».
- **Portugal:** 15 € de envío y nunca gratis, tampoco con el pack. La barra de envío del carrito, del carrito lateral y del pago lo avisa cuando el país es Portugal. Ajuste nuevo «Coste de envío a Portugal».
- Textos de portada, ficha, pack, carrito, FAQs y Envíos y devoluciones sin «24-48 h»; `wp mpc pages fix` reescribe plazos, tabla (con fila Portugal) y quita «fuera de Madrid» y el párrafo de aduanas.
## 0.18.3
- Catálogo del pack: «Pollo y trufa» y «Pollo sabor jamón ibérico» descatalogadas (en borrador); «Pollo de corral» entra como sabor del pack (mapa de sabores y etiqueta nutricional). En producción las 10 bandejas del pack ya comparten la misma lista de 26 sabores.
## 0.18.2
- **Envío gratis confirmado: solo con el pack o con 10 bandejas de hamburguesas; el resto de pedidos paga el envío.** Fuera la regla de «desde 60 €» (nunca existió en producción, donde la aplica el plugin Advanced Dynamic Pricing): ajuste `free_min` eliminado, barra de progreso solo con bandejas, textos de portada, ficha, FAQs, Envíos y devoluciones y promo actualizados.
- Los snacks del carrito y del checkout ya no se presentan como ayuda para el envío gratis; el del checkout es el primero de la lista que no esté en el pedido.

## 0.18.1
- Páginas de texto en el alcance: FAQs, Envíos y devoluciones y Controles de calidad con diseño de lectura (tema hijo 1.0.10, clase `mpc-text-page`, filtro `mpc_text_pages`).
- `wp mpc pages fix|revert`: limpia las FAQs (erratas, mayúsculas, estructura) y corrige la tabla y el párrafo de gastos de envío de Envíos y devoluciones (decían 50 €) con la regla vigente; guarda el contenido anterior para revertir.

## 0.18.0
- **Alcance de la primera iteración:** hamburguesas, pack, portada, carrito, carrito lateral y pago. Fichas de otros productos, listados, nombres del resto del catálogo y páginas de texto quedan como estaban (Betheme). Se activan con **WooCommerce > Conversión > Alcance** (segunda iteración).
- `wp mpc products fix` solo toca hamburguesas y pack salvo `--all`.
- **Varios regalos:** «Regalos del pack (IDs)» admite una lista; con más de uno, el cliente elige (paso «Elige tu regalo»). El elegido se guarda en la línea del carrito y del pedido («Regalo»), el stock se descuenta del elegido y se devuelve al cancelar. Con un solo regalo todo sigue igual.
- Con varios regalos, el nombre del pack en carrito, pago y pedido no nombra ninguno.

## Tema hijo 1.0.9
- Las reglas de listados (tienda, categorías) y de páginas de texto solo se aplican con el alcance completo (`body.mpc-scope-all`).

## Tema hijo 1.0.8
- Checkout: solo el logo; fuera la banda con el título y los pasos (Carrito > Finalizar compra > Pedido). La confirmación del pedido no cambia.

## 0.17.1
- El snack del checkout respeta el orden de «Snacks del carrito (IDs)»: el primero que consigue el envío gratis o, si ninguno, el primero de la lista.

## Tema hijo 1.0.7
- Cabecera: sin el círculo de Betheme al pasar el ratón por los iconos (solo el fondo suave).

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
