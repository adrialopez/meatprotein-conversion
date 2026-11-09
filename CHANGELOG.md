# Cambios

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
