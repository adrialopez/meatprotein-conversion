#!/usr/bin/env python3
"""Genera el contenido de las páginas de texto (FAQs, Envíos y devoluciones, Reportar incidencia) como bloques de WordPress.
Uso: python3 build_pages.py   (escribe faqs.html, envios-y-devoluciones.html y reportar-incidencia.html junto a este archivo)."""
import os, html

OUT = os.path.dirname(os.path.abspath(__file__))
SHIP, SHIP_PT = '9,00', '15,00'

CSS = """<style>
.mp-text{max-width:780px;margin:0 auto;padding:36px 20px 72px;color:#14161c;font-size:1.05rem;line-height:1.7}
.mp-text *{box-sizing:border-box}
.mp-text h2{font-size:1.55rem;line-height:1.2;margin:2.8rem 0 1rem;scroll-margin-top:110px}
.mp-text h3{font-size:1.2rem;line-height:1.3;margin:1.9rem 0 .6rem}
.mp-text p,.mp-text ul,.mp-text ol{margin:0 0 1.1rem}
.mp-text ul,.mp-text ol{padding-left:1.35rem}
.mp-text li{margin-bottom:.45rem}
.mp-text a{color:#b8306f;text-decoration:underline;text-underline-offset:2px}
.mp-text .mp-lead{font-size:1.15rem;line-height:1.6}
.mp-text .mp-toc{display:flex;flex-wrap:wrap;gap:8px;margin:1.4rem 0 2.2rem;padding:0;list-style:none}
.mp-text .mp-toc a{display:inline-block;padding:.45rem .95rem;border-radius:99px;background:#cde8f2;color:#14161c;text-decoration:none;font-size:.95rem;font-weight:600}
.mp-text .mp-toc a:hover{background:#b8dcea}
.mp-text .mp-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:1.6rem 0 2rem}
.mp-text .mp-fact{padding:1rem 1.1rem;border-radius:14px;background:#eef7fb;border:1px solid #cde8f2}
.mp-text .mp-fact strong{display:block;font-size:1.35rem;line-height:1.2;margin-bottom:.25rem}
.mp-text .mp-fact span{display:block;font-size:.92rem;line-height:1.4;color:#3a3f4b}
.mp-text .wp-block-table{margin:0 0 1.4rem;overflow-x:auto}
.mp-text table{width:100%;border-collapse:collapse;font-size:.98rem}
.mp-text th,.mp-text td{padding:.7rem .8rem;border:1px solid #d9e6ec;text-align:left;vertical-align:top}
.mp-text thead th{background:#cde8f2}
.mp-text .mp-note{padding:1rem 1.2rem;border-radius:14px;background:#fdf0f6;border:1px solid #f3cde0;margin:1.4rem 0}
.mp-text .mp-note p{margin:0}
.mp-text details{border-bottom:1px solid #d9e6ec;padding:.2rem 0}
.mp-text details>summary{cursor:pointer;list-style:none;padding:.95rem 2rem .95rem 0;font-weight:700;position:relative;line-height:1.4}
.mp-text details>summary::-webkit-details-marker{display:none}
.mp-text details>summary::after{content:"+";position:absolute;right:.2rem;top:.8rem;font-size:1.4rem;font-weight:400;color:#b8306f}
.mp-text details[open]>summary::after{content:"\\2212"}
.mp-text details>*:not(summary){margin-left:0}
.mp-text details[open]{padding-bottom:.4rem}
@media (max-width:600px){.mp-text{padding:24px 16px 56px;font-size:1rem}.mp-text th,.mp-text td{padding:.6rem .55rem;font-size:.92rem}.mp-text h2{font-size:1.4rem;margin-top:2.2rem}.mp-text .mp-fact strong{font-size:1.2rem}}
</style>"""

def blk(name, inner, attrs=''):
    a = (' ' + attrs) if attrs else ''
    return f'<!-- wp:{name}{a} -->\n{inner}\n<!-- /wp:{name} -->\n\n'

def p(t, cls=''):
    c = f' class="{cls}"' if cls else ''
    return blk('paragraph', f'<p{c}>{t}</p>')

def h2(id_, t):
    return blk('heading', f'<h2 class="wp-block-heading" id="{id_}">{t}</h2>', '{"anchor":"%s"}' % id_)

def h3(t):
    return blk('heading', f'<h3 class="wp-block-heading">{t}</h3>', '{"level":3}')

def ul(items):
    return blk('list', '<ul class="wp-block-list">' + ''.join(f'<li>{i}</li>' for i in items) + '</ul>')

def raw(t):
    return blk('html', t)

def table(head, rows):
    th = ''.join(f'<th>{c}</th>' for c in head)
    tr = ''.join('<tr>' + ''.join(f'<td>{c}</td>' for c in r) + '</tr>' for r in rows)
    return blk('table', f'<figure class="wp-block-table"><table><thead><tr>{th}</tr></thead><tbody>{tr}</tbody></table></figure>')

def details(q, answer_blocks):
    return blk('details', f'<details class="wp-block-details"><summary>{q}</summary>\n{answer_blocks}</details>')

def note(t):
    return raw(f'<div class="mp-note"><p>{t}</p></div>')

def toc(items):
    return raw('<ul class="mp-toc">' + ''.join(f'<li><a href="#{i}">{t}</a></li>' for i, t in items) + '</ul>')

def facts(items):
    return raw('<div class="mp-facts">' + ''.join(f'<div class="mp-fact"><strong>{a}</strong><span>{b}</span></div>' for a, b in items) + '</div>')

def page(body):
    return raw(CSS) + '<!-- wp:group {"className":"mp-text"} -->\n<div class="wp-block-group mp-text">\n\n' + body + '</div>\n<!-- /wp:group -->\n'

WA = '<a href="https://wa.me/34944999194">944 999 194</a>'
MAIL = '<a href="mailto:info@meatprotein.eu">info@meatprotein.eu</a>'
INC = '<a href="/reportar-incidencia/">Reportar incidencia</a>'
ENV = '<a href="/envios-y-devoluciones/">Envíos y devoluciones</a>'

REGLAS = [
    'Pedidos de lunes a jueves <strong>antes de las 13:00</strong>: lo recibes al día siguiente, antes de las 14:00.',
    'Pedidos de lunes a miércoles <strong>después de las 13:00</strong>: lo recibes dos días después, antes de las 14:00.',
    'Pedidos <strong>desde el jueves a las 13:00 hasta el lunes a las 13:00</strong>: lo recibes el martes, antes de las 14:00.',
]

# ----------------------------------------------------------------- FAQs
faq = p('Respondemos las dudas más habituales sobre pedidos, envíos, conservación y problemas con tu pedido. Si no encuentras lo que buscas, escríbenos por WhatsApp al ' + WA + '.', 'mp-lead')
faq += toc([('pedidos', 'Pedidos'), ('conservacion', 'Conservación'), ('envios', 'Envíos'), ('problemas', 'Problemas con el pedido'), ('contacto', 'Contacto')])
faq += h2('pedidos', 'Pedidos')
faq += details('Es mi primera compra, ¿qué tengo que hacer?', p('Rellena todos los campos del pedido. Si tienes algún problema, escríbenos a ' + MAIL + '.'))
faq += details('¿Puedo cancelar o modificar mi pedido?', p('Sí. Puedes cancelarlo o modificarlo (cambiar la dirección, quitar productos o cambiarlos por otros) como máximo hasta las <strong>11:00 del día en que sale el pedido</strong>, que es el día anterior a la entrega. Escríbenos por WhatsApp al ' + WA + ' o a ' + MAIL + '.'))
faq += details('¿Puedo usar varios cupones de descuento?', p('No, los cupones no se pueden combinar con otros vales de descuento.'))
faq += h2('conservacion', 'Conservación')
faq += details('¿Cómo llegan las hamburguesas?', p('Refrigeradas, en transporte refrigerado. Al recibirlas, congela lo que no vayas a comer en los próximos días.'))
faq += details('¿Cuánto duran?', ul(['En el congelador, <strong>12 meses</strong>.', 'En la nevera, unas <strong>72 horas</strong>.']))
faq += h2('envios', 'Envíos')
faq += details('¿Cuánto cuesta el envío?', ul([f'Península: <strong>{SHIP} €</strong> (IVA incluido).', f'Portugal: <strong>{SHIP_PT} €</strong>.']))
faq += details('¿Cuándo es gratis el envío?', p('Cuando compras <strong>el pack o 10 bandejas de hamburguesas o más</strong> con destino a la Península. En cualquier otro pedido se paga el envío. A Portugal el envío siempre se paga, también con el pack.'))
faq += details('¿A dónde enviáis?', p('A toda la <strong>Península y a Portugal</strong>, siempre a domicilio (no admitimos apartados de correos). Por ahora quedan fuera Canarias, Baleares, Ceuta y Melilla.'))
faq += details('¿Cuándo lo recibo?', p('Enviamos de lunes a jueves, con SEUR y en transporte refrigerado, y entregamos <strong>antes de las 14:00</strong>. La hora de corte es las <strong>13:00</strong> (hora peninsular).') + ul(REGLAS) + p('No enviamos los viernes: así el producto refrigerado no pasa el fin de semana en tránsito.'))
faq += details('¿Qué pasa cuando hay festivos?', p('Si el día de la entrega fuera festivo, no enviamos el día anterior, porque SEUR no reparte en festivo. En esas semanas, la fecha de entrega se desplaza al siguiente día válido.'))
faq += details('¿Y si no estoy en casa cuando llega?', p('La empresa de transporte te enviará un SMS al teléfono que has indicado en el pedido, para que acordéis el momento y el horario de entrega que mejor te vengan.'))
faq += details('Vivo en un pueblo pequeño, ¿tardará más?', p('Si tu población está alejada de un gran núcleo urbano o tiene menos de 5.000 habitantes, puede haber alguna demora.'))
faq += h2('problemas', 'Problemas con el pedido')
faq += details('Mi pedido ha llegado en mal estado', p('Si llega con las bandejas infladas o en malas condiciones, Meatprotein se hace responsable y te <strong>repone un pedido igual en unas 24 a 72 horas</strong>. Cuéntanoslo por WhatsApp o desde ' + INC + '.'))
faq += details('Me habéis enviado un sabor equivocado', p('Si nos hemos equivocado, no lo volvemos a enviar: lo compensamos en tu siguiente pedido.'))
faq += details('Me he equivocado yo al hacer el pedido', p('Escríbenos por WhatsApp y lo modificamos, como máximo hasta las 11:00 del día en que sale el pedido.'))
faq += details('¿Puedo devolver un pedido?', p('Vendemos alimentos frescos, que pueden deteriorarse con rapidez, así que no admitimos devoluciones ni desistimiento de pedidos sin defecto (artículo 103 del Real Decreto Legislativo 1/2007). Si hay un problema con tu pedido, lo resolvemos como se explica arriba. Más detalles en ' + ENV + '.'))
faq += h2('contacto', 'Contacto')
faq += ul(['<strong>WhatsApp:</strong> ' + WA + ', a cualquier hora. Te contestamos en 24 a 48 horas.', '<strong>Teléfono:</strong> ' + WA + ', de lunes a viernes, de 9:30 a 13:00.', '<strong>Correo:</strong> ' + MAIL + '.', '<strong>Una incidencia con tu pedido:</strong> ' + INC + '.'])
open(os.path.join(OUT, 'faqs.html'), 'w').write(page(faq))

# ------------------------------------------------- Envíos y devoluciones
e = p('Aquí tienes cómo enviamos, cuándo llega tu pedido y qué hacemos si algo no sale bien. Trabajamos con producto fresco refrigerado, por eso las reglas son estas.', 'mp-lead')
e += facts([('13:00', 'Hora de corte para cada pedido'), ('Antes de las 14:00', 'Hora de entrega, con SEUR refrigerado'), ('Pack o 10 bandejas', 'Envío gratis a la Península'), ('24 a 72 h', 'Reposición si llega en mal estado')])
e += toc([('envios', 'Envíos'), ('plazos', 'Plazos de entrega'), ('cancelacion', 'Cancelación y modificación'), ('garantia', 'Garantía y mal estado'), ('desistimiento', 'Desistimiento'), ('clausulas', 'Cláusulas adicionales')])
e += h2('envios', 'Envíos')
e += p('Los gastos de envío van a cargo del cliente y se indican con el coste total antes del pago, durante el proceso de compra. Dependen del lugar de entrega. Enviamos a la Península y a Portugal; Canarias, Baleares, Ceuta, Melilla y el resto de destinos quedan excluidos por el momento. Para garantizar que cada pedido llegue en perfectas condiciones, contamos con el apoyo de partners especializados en logística ecommerce.')
e += p('Únicamente realizamos envíos con entrega a domicilio. Se indica el importe, con IVA, de los gastos de envío para los productos acumulados.')
e += table(['Zona', 'Gastos de envío', 'Gratis con'], [['Península', f'{SHIP} €', 'El pack o 10 bandejas de hamburguesas o más'], ['Portugal', f'{SHIP_PT} €', 'No hay envío gratis a Portugal, tampoco con el pack']])
e += p('Para pedidos con destino en la Península en los que el pedido incluya el pack o 10 bandejas de hamburguesas o más, los gastos de envío serán gratuitos. En el resto de pedidos se aplican los gastos de envío indicados en la tabla. Los envíos a Portugal tienen siempre los gastos indicados, también cuando el pedido incluye el pack.')
e += p('Los lugares de entrega únicamente serán los que se indican expresamente en la tabla de gastos de envío. Cualquier pedido con dirección de envío distinta a estas áreas geográficas será cancelado y se procederá a la devolución de los importes recibidos. No se admiten apartados de correos como direcciones válidas de envío a domicilio.')
e += p('El envío de productos refrigerados o congelados se realiza en la modalidad idónea para no romper con la cadena de frío de los productos del pedido. Podrás seguir tu pedido cuando haya sido entregado a la empresa de transporte y hayas recibido el código de seguimiento.')
e += p('No asumimos responsabilidad alguna respecto de la entrega del pedido que no pudiera realizarse como consecuencia de datos falsos, inexactos o incompletos proporcionados por el usuario o consumidor o bien, cuando la entrega no pueda efectuarse por causas ajenas a la empresa de transporte, como puede ser la ausencia del destinatario en el domicilio.')
e += p('Para poder tramitar con celeridad cualquier incidente relacionado con el envío, rogamos que nos lo comuniques en el menor plazo posible (a poder ser en menos de 24 horas), indicando si se trata de falta o pérdida de alguno de los productos, si están dañados, si el empaque está abierto, etc. Puedes hacerlo desde ' + INC + '.')
e += h2('plazos', 'Plazos de entrega')
e += p('Enviamos de lunes a jueves, con SEUR y en transporte refrigerado, y entregamos antes de las 14:00. La hora de corte es las 13:00 (hora peninsular). Los viernes no enviamos, para que el producto refrigerado no pase el fin de semana en tránsito.')
e += ul(REGLAS)
e += p('Si tu población se encuentra alejada de un gran núcleo urbano o ciudad, o tiene menos de 5.000 habitantes, puede que tenga una posible demora. En semanas con festivo no enviamos ese día ni el anterior, porque la entrega caería en festivo.')
e += h2('cancelacion', 'Cancelación y modificación del pedido')
e += p('Puedes cancelar o modificar tu pedido (cambiar la dirección, retirar productos, reducir la cantidad o sustituirlos por otros) como máximo hasta las <strong>11:00 del día en que sale el pedido</strong>, que es el día anterior a la entrega. Escríbenos por WhatsApp al +34 944 99 91 94, a ' + MAIL + ' o hazlo desde tu área personal.')
e += p('Si la modificación sube el importe del pedido, pagarás la diferencia; si lo baja, te reembolsaremos la diferencia. El vendedor se reserva el derecho a rechazar modificaciones por razones organizativas.')
e += h2('garantia', 'Garantía y producto en mal estado')
e += note('<strong>Producto en mal estado.</strong> Si tu pedido llega con las bandejas infladas o en malas condiciones, Meatprotein se hace responsable y te repone un pedido igual en unas 24 a 72 horas. Si por un error nuestro recibes un sabor distinto al que pediste, lo compensamos en tu siguiente pedido.')
e += p('Salvo prueba en contrario, se entenderá que los productos son conformes con el contrato siempre que cumplan todos los requisitos que se expresan a continuación, salvo que por las circunstancias del caso alguno de ellos no resulte aplicable:')
e += ul(['Se ajusten a la descripción realizada por el vendedor y posean las cualidades del producto que el vendedor haya presentado al consumidor y usuario en forma de muestra o modelo.', 'Sean aptos para los usos a que ordinariamente se destinen los productos del mismo tipo.', 'Sean aptos para cualquier uso especial requerido por el consumidor y usuario cuando lo haya puesto en conocimiento del vendedor en el momento de celebración del contrato, siempre que éste haya admitido que el producto es apto para dicho uso.', 'Presenten la calidad y prestaciones habituales de un producto del mismo tipo que el consumidor y usuario pueda fundadamente esperar.'])
e += p('Si el producto no fuera conforme con el contrato, el consumidor y usuario podrá optar entre exigir la reparación del producto, su sustitución, la rebaja del precio o la resolución del contrato.')
e += p('El vendedor no se hace responsable de los daños en los productos de un mal uso de los mismos, así como de actos u omisiones del usuario que no se ajusten a las especificaciones de cada producto o de los daños causados por terceros que no estén vinculados al vendedor.')
e += p('Si algún producto no es conforme con el contrato o llega en mal estado, comunícalo cuanto antes por WhatsApp al +34 944 99 91 94, en ' + MAIL + ' o desde ' + INC + ', indicando tu pedido y los productos afectados. Te contestamos en un plazo de 24 a 48 horas y, si procede, te reponemos un pedido igual en unas 24 a 72 horas.')
e += h2('desistimiento', 'Derecho de desistimiento')
e += p('Los productos que vendemos son alimentos frescos, que pueden deteriorarse o caducar con rapidez. Por eso no tienen derecho de desistimiento y no se admiten devoluciones de pedidos sin defecto, de acuerdo con el artículo 103 del Real Decreto Legislativo 1/2007, de 16 de noviembre, que excluye el derecho de desistimiento en:')
e += ul(['El suministro de bienes que puedan deteriorarse o caducar con rapidez.', 'El suministro de bienes precintados que no sean aptos para ser devueltos por razones de protección de la salud o de higiene y que hayan sido desprecintados tras la entrega.', 'El suministro de bienes que después de su entrega y teniendo en cuenta su naturaleza se hayan mezclado de forma indisociable con otros bienes.'])
e += p('Si tu pedido llega en mal estado, consulta el apartado «Garantía y producto en mal estado».')
e += h2('clausulas', 'Cláusulas adicionales')
e += h3('Responsabilidad y exoneración de responsabilidad')
e += p('No obstante, y salvo disposición legal en sentido contrario, no aceptaremos ninguna responsabilidad por las siguientes pérdidas, con independencia de su origen:')
e += ul(['Pérdidas de ingresos o ventas.', 'Pérdida de negocio.', 'Lucro cesante o pérdida de contratos.', 'Pérdida de ahorros previstos.', 'Pérdida de datos.', 'Pérdida de tiempo de gestión o de horario de oficina.'])
e += p('No seremos responsables por ningún incumplimiento o retraso en el cumplimiento de alguna de las obligaciones asumidas, cuando el mismo se deba a acontecimientos que están fuera de nuestro control razonable («causa de fuerza mayor»).')
e += p('Las causas de fuerza mayor incluirán cualquier acto, acontecimiento, falta de ejercicio, omisión o accidente que esté fuera de nuestro control razonable. A continuación se indica una lista de ejemplos, sin carácter exhaustivo ni limitativo:')
e += ul(['Huelgas, cierres patronales u otras medidas reivindicativas.', 'Conmoción civil, revuelta, invasión, amenaza o ataque terrorista, guerra (declarada o no) o amenaza o preparativos de guerra.', 'Incendio, explosión, tormenta, inundación, terremoto, hundimiento, epidemia o cualquier otro desastre natural.', 'Imposibilidad de uso de trenes, barcos, aviones, transportes de motor u otros medios de transporte, públicos o privados.', 'Imposibilidad de utilizar sistemas públicos o privados de telecomunicaciones.', 'Actos, decretos, legislación, normativa o restricciones de cualquier gobierno o autoridad pública.'])
e += p('Se entenderá que las obligaciones quedarán suspendidas durante el período en que la causa de fuerza mayor continúe, y dispondremos de una ampliación en el plazo para cumplir dichas obligaciones por un periodo de tiempo igual al que dure la causa de fuerza mayor.')
open(os.path.join(OUT, 'envios-y-devoluciones.html'), 'w').write(page(e))

# --------------------------------------------------- Reportar incidencia
i = p('¿Tu pedido ha llegado con algún problema? Cuéntanoslo y lo resolvemos. Rellena el formulario con tu número de pedido y una breve descripción de lo ocurrido.', 'mp-lead')
i += facts([('24 a 48 h', 'Te contestamos en este plazo'), ('24 a 72 h', 'Reposición de un pedido igual si llegó en mal estado'), ('Mejor cuanto antes', 'Avísanos en las primeras 24 horas desde la entrega')])
i += ul(['<strong>Bandejas infladas o en malas condiciones:</strong> te reponemos un pedido igual.', '<strong>Sabor equivocado por un error nuestro:</strong> lo compensamos en tu siguiente pedido.', '<strong>Te has equivocado al pedir:</strong> escríbenos por WhatsApp y lo modificamos, hasta las 11:00 del día en que sale el pedido.'])
i += p('También puedes escribirnos por WhatsApp al ' + WA + ' (a cualquier hora; contestamos en 24 a 48 horas) o llamarnos de lunes a viernes, de 9:30 a 13:00.')
i += blk('shortcode', '[contact-form-7 id="99b1a19" title="Incidencias"]')
open(os.path.join(OUT, 'reportar-incidencia.html'), 'w').write(page(i))
print('ok')
