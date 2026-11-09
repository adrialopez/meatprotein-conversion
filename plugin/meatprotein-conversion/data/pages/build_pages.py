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
.mp-text details[open]>summary::after{content:"−"}
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
REP = '<a href="https://meatprotein.eu/reportar-incidencia/">Reportar incidencia</a>'
e = p('Aquí te explicamos cómo enviamos tu pedido, cuándo lo recibirás y qué hacemos si surge algún problema.', 'mp-lead')
e += p('Trabajamos con producto fresco refrigerado y cuidamos especialmente el transporte para que recibas tus hamburguesas en las mejores condiciones.')
e += facts([('13:00 h', 'Hora límite para realizar pedidos con envío según el calendario habitual.'), ('Antes de las 14:00 h', 'Hora prevista de entrega con SEUR refrigerado.'), ('Pack o 10 bandejas o más', 'Envío gratis a la Península.'), ('24 a 72 h', 'Plazo habitual de reposición cuando un producto llega en mal estado y corresponde su sustitución.')])
e += toc([('envios', 'Envíos'), ('plazos', 'Plazos de entrega'), ('cancelacion', 'Cancelación y modificación'), ('garantia', 'Garantía y producto en mal estado'), ('desistimiento', 'Derecho de desistimiento'), ('clausulas', 'Cláusulas adicionales')])
e += h2('envios', 'Envíos')
e += p('Preparamos y enviamos tus pedidos con transporte refrigerado para mantener la cadena de frío durante todo el trayecto.')
e += p('Actualmente realizamos entregas a domicilio en la Península y Portugal. Por el momento, no enviamos a Baleares, Canarias, Ceuta, Melilla ni otros destinos fuera de estas zonas.')
e += h3('Gastos de envío')
e += p('Los gastos de envío dependen del destino y se muestran durante el proceso de compra, antes de realizar el pago. Todos los importes incluyen IVA.')
e += table(['Zona', 'Gastos de envío', 'Envío gratis'], [['Península', f'{SHIP} €', 'Con el pack o 10 bandejas de hamburguesas o más'], ['Portugal', f'{SHIP_PT} €', 'No disponible']])
e += p('En los pedidos con destino a la Península, el envío es gratuito cuando incluyen el pack o al menos 10 bandejas de hamburguesas.')
e += p('En Portugal, los gastos de envío son siempre de 15 €, independientemente de los productos o de la cantidad que incluya el pedido. El envío gratuito no se aplica a este destino, tampoco cuando se compra el pack.')
e += p('Los envíos se realizan exclusivamente a domicilio. No admitimos apartados de correos como dirección de entrega.')
e += p('Si recibimos un pedido con una dirección fuera de las zonas de reparto indicadas, tendremos que cancelarlo y devolveremos el importe abonado.')
e += h3('Transporte refrigerado y seguimiento')
e += p('Los productos refrigerados o congelados se envían mediante transporte preparado para mantener la cadena de frío durante el trayecto.')
e += p('Cuando entreguemos tu pedido a la empresa de transporte, recibirás un código de seguimiento para consultar su estado.')
e += p('Si detectas algún problema con la entrega, falta algún producto o el embalaje llega abierto o dañado, comunícanoslo lo antes posible, preferiblemente en las primeras 24 horas. Puedes hacerlo desde nuestra página de ' + REP + '.')
e += p('Es importante que los datos de entrega sean correctos y estén completos. Si el transportista no puede entregar el pedido por una dirección incorrecta, la ausencia del destinatario u otra causa ajena a Meatprotein, pueden producirse retrasos o gastos adicionales de transporte.')
e += h2('plazos', 'Plazos de entrega')
e += p('Enviamos de lunes a jueves mediante SEUR y transporte refrigerado. La entrega está prevista para el día siguiente a la expedición, antes de las 14:00 h.')
e += p('La hora límite para realizar un pedido y que salga según el calendario habitual es las <strong>13:00 h (hora peninsular)</strong>.')
e += p('Estos son los plazos habituales:')
e += ul(['<strong>Pedidos de lunes a jueves antes de las 13:00 h:</strong> recibirás tu pedido al día siguiente, antes de las 14:00 h.', '<strong>Pedidos de lunes a miércoles después de las 13:00 h:</strong> recibirás tu pedido dos días después, antes de las 14:00 h.', '<strong>Pedidos desde el jueves a las 13:00 h hasta el lunes a las 13:00 h:</strong> recibirás tu pedido el martes, antes de las 14:00 h.'])
e += p('No expedimos pedidos los viernes para evitar que los productos refrigerados pasen el fin de semana en tránsito.')
e += p('Si tu dirección está en una población alejada de los principales núcleos urbanos o con menos de 5.000 habitantes, la entrega puede sufrir alguna demora.')
e += p('En las semanas con festivos tampoco expedimos pedidos el día festivo ni el día anterior, ya que la entrega podría coincidir con un día sin reparto.')
e += p('Los plazos indicados son estimados y pueden variar por circunstancias ajenas al servicio habitual de transporte.')
e += h2('cancelacion', 'Cancelación y modificación del pedido')
e += p('¿Necesitas cambiar algo de tu pedido? Puedes hacerlo hasta las <strong>11:00 h del día en que está prevista su salida</strong>, es decir, el día anterior a la entrega.')
e += p('Puedes solicitar:')
e += ul(['Un cambio en la dirección de entrega.', 'Retirar algún producto.', 'Reducir cantidades.', 'Sustituir productos por otros.', 'Añadir productos al pedido.'])
e += p('Para solicitar cualquier cambio, puedes escribirnos por WhatsApp al <strong>+34 944 99 91 94</strong>, enviarnos un correo a ' + MAIL + ' o gestionarlo desde tu área personal.')
e += p('Si el cambio aumenta el importe del pedido, tendrás que abonar la diferencia. Si lo reduce, te devolveremos el importe correspondiente.')
e += p('Ten en cuenta que algunas modificaciones pueden no ser posibles una vez iniciado el proceso de preparación. En ese caso, te informaremos.')
e += h2('garantia', 'Garantía y producto en mal estado')
e += p('Trabajamos para que todos los pedidos lleguen en perfectas condiciones. Si algo no va bien, queremos saberlo y solucionarlo.')
e += h3('¿Qué pasa si el pedido llega en mal estado?')
e += note('Si recibes bandejas infladas o productos en malas condiciones, Meatprotein se hace cargo de la incidencia y, cuando corresponda, te enviará una reposición del pedido en un plazo aproximado de <strong>24 a 72 horas</strong>.')
e += p('Si recibes un sabor distinto al que habías pedido por un error nuestro, compensaremos el producto afectado en tu siguiente pedido.')
e += h3('¿Cómo comunico una incidencia?')
e += p('Si algún producto no llega en las condiciones esperadas, falta algún artículo o has recibido algo diferente a lo que compraste, ponte en contacto con nosotros cuanto antes.')
e += p('Puedes hacerlo de cualquiera de estas formas:')
e += ul(['WhatsApp: <strong>+34 944 99 91 94</strong>', 'Email: ' + MAIL, REP])
e += p('Indícanos el número de pedido y qué productos están afectados. Si puedes, adjunta fotografías para que podamos valorar el problema más rápidamente.')
e += p('Te responderemos en un plazo de 24 a 48 horas y, si procede, gestionaremos la reposición en unas 24 a 72 horas.')
e += h3('Garantía legal')
e += p('Todos nuestros productos están sujetos a las garantías de conformidad previstas en la legislación vigente.')
e += p('Si un producto no se corresponde con lo que has comprado, presenta algún defecto o no cumple las condiciones acordadas, tienes derecho a las soluciones que establece la normativa aplicable.')
e += p('La garantía no cubre los daños derivados de un uso inadecuado, de no seguir las indicaciones de conservación del producto o de actuaciones de terceros ajenos a Meatprotein, cuando legalmente corresponda.')
e += h2('desistimiento', 'Derecho de desistimiento')
e += p('Nuestros productos son alimentos frescos que necesitan mantenerse refrigerados y pueden deteriorarse o caducar rápidamente. Por este motivo, <strong>no se admite el desistimiento ni la devolución de pedidos que estén en perfectas condiciones</strong>, de acuerdo con las excepciones previstas en el artículo 103 del Real Decreto Legislativo 1/2007, de 16 de noviembre.')
e += p('Esta excepción contempla, entre otros supuestos:')
e += ul(['El suministro de bienes que puedan deteriorarse o caducar con rapidez.', 'El suministro de bienes precintados que no sean aptos para su devolución por razones de protección de la salud o de higiene y que hayan sido desprecintados tras la entrega.', 'El suministro de bienes que, después de su entrega y teniendo en cuenta su naturaleza, se hayan mezclado de forma indisociable con otros bienes.'])
e += p('Esto no afecta a tus derechos cuando un producto llega defectuoso, en mal estado o no se corresponde con lo que has comprado.')
e += p('Si tienes algún problema con tu pedido, consulta el apartado <a href="#garantia">Garantía y producto en mal estado</a> y comunícanos la incidencia.')
e += h2('clausulas', 'Cláusulas adicionales')
e += h3('Responsabilidad')
e += p('Meatprotein responde de las obligaciones que le corresponden como vendedor conforme a la legislación vigente.')
e += p('En la medida permitida por la ley, no asumimos responsabilidad por pérdidas indirectas o consecuencias económicas que no sean una consecuencia directa y previsible de nuestro incumplimiento, como pérdidas de ingresos, ventas, negocio, contratos, ahorros previstos o datos.')
e += p('Tampoco seremos responsables de retrasos o incumplimientos cuando se deban a circunstancias ajenas a nuestro control razonable, siempre dentro de los límites establecidos por la legislación aplicable.')
e += h3('Fuerza mayor')
e += p('Se consideran causas de fuerza mayor aquellas circunstancias excepcionales que escapan a nuestro control y que impiden o dificultan el cumplimiento de nuestras obligaciones.')
e += p('Entre ellas se encuentran:')
e += ul(['Huelgas, cierres patronales y otros conflictos laborales.', 'Disturbios, revueltas, invasiones, amenazas terroristas o situaciones de guerra.', 'Incendios, explosiones, tormentas, inundaciones, terremotos, epidemias y otros desastres naturales.', 'Interrupciones o imposibilidad de utilizar medios de transporte públicos o privados.', 'Fallos o interrupciones de los sistemas de telecomunicaciones.', 'Actos, decretos, leyes, normativas o restricciones de las autoridades públicas.'])
e += p('Mientras se mantenga una situación de fuerza mayor, las obligaciones afectadas podrán quedar suspendidas durante el tiempo que dure dicha circunstancia. Los plazos de cumplimiento se ampliarán en consecuencia, siempre de acuerdo con la legislación aplicable.')
open(os.path.join(OUT, 'envios-y-devoluciones.html'), 'w').write(page(e))

# --------------------------------------------------- Reportar incidencia
i = p('¿Tu pedido ha llegado con algún problema? Cuéntanoslo y lo resolvemos. Rellena el formulario con tu número de pedido y una breve descripción de lo ocurrido.', 'mp-lead')
i += facts([('24 a 48 h', 'Te contestamos en este plazo'), ('24 a 72 h', 'Reposición de un pedido igual si llegó en mal estado'), ('Mejor cuanto antes', 'Avísanos en las primeras 24 horas desde la entrega')])
i += ul(['<strong>Bandejas infladas o en malas condiciones:</strong> te reponemos un pedido igual.', '<strong>Sabor equivocado por un error nuestro:</strong> lo compensamos en tu siguiente pedido.', '<strong>Te has equivocado al pedir:</strong> escríbenos por WhatsApp y lo modificamos, hasta las 11:00 del día en que sale el pedido.'])
i += p('También puedes escribirnos por WhatsApp al ' + WA + ' (a cualquier hora; contestamos en 24 a 48 horas) o llamarnos de lunes a viernes, de 9:30 a 13:00.')
i += blk('shortcode', '[contact-form-7 id="99b1a19" title="Incidencias"]')
open(os.path.join(OUT, 'reportar-incidencia.html'), 'w').write(page(i))
print('ok')
