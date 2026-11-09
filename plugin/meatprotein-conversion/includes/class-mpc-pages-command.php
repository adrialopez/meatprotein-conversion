<?php
/**
 * WP-CLI: limpieza del contenido de las páginas de texto (FAQs y Envíos y devoluciones), con copia para revertir.
 *
 * @package Meatprotein_Conversion
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! class_exists( 'MPC_Pages_Command' ) ) {
	/**
	 * Comandos `wp mpc pages ...`.
	 */
	class MPC_Pages_Command {

		/**
		 * Contenido limpio de las FAQs: mismas condiciones, sin erratas ni mayúsculas y con la regla de envío vigente.
		 *
		 * @return string
		 */
		private function faqs_html() {
			$cost    = number_format_i18n( (float) mpc_opt( 'ship_cost' ), 2 );
			$cost_pt = number_format_i18n( (float) mpc_opt( 'ship_cost_pt' ), 2 );
			return '<p>Aquí aclaramos las preguntas más frecuentes sobre nuestra política de envío.</p>
<h2>Pedidos</h2>
<p>Si es la primera vez que compras en <strong>Meatprotein</strong>, rellena todos los campos del pedido. Si tienes algún problema, escríbenos a <a href="mailto:info@meatprotein.eu">info@meatprotein.eu</a>.</p>
<h2>Conservación</h2>
<ul>
<li>Enviamos las hamburguesas refrigeradas: cuando te lleguen, hay que congelarlas.</li>
<li>En el congelador duran 12 meses.</li>
<li>En la nevera, aproximadamente 72 horas.</li>
</ul>
<h2>Gastos de envío</h2>
<p>Enviamos a toda la Península y a Portugal. Los gastos de envío a la Península son de ' . esc_html( $cost ) . ' € y son gratis cuando compras el pack o 10 bandejas de hamburguesas o más; en cualquier otro pedido se pagan. A Portugal el envío cuesta ' . esc_html( $cost_pt ) . ' € y siempre se paga, también cuando compras el pack.</p>
<p>Por el momento quedan excluidos como zona de envío Ceuta, Melilla, Islas Canarias y Baleares.</p>
<ul>
<li>No es compatible con otros vales de descuento.</li>
</ul>
<h2>Entregas</h2>
<p>Enviamos de lunes a jueves, con SEUR y en transporte refrigerado, y entregamos antes de las 14:00. La hora de corte es las 13:00 (hora peninsular). Los viernes no enviamos, para que el producto refrigerado no pase el fin de semana en tránsito.</p>
<ul>
<li>Si compras de lunes a jueves antes de las 13:00, lo recibes al día siguiente antes de las 14:00.</li>
<li>Si compras de lunes a miércoles después de las 13:00, lo recibes dos días después antes de las 14:00.</li>
<li>Si compras desde el jueves a las 13:00 hasta el lunes a las 13:00, lo recibes el martes antes de las 14:00.</li>
</ul>
<p>Si tu población está alejada de un gran núcleo urbano o ciudad, o tiene menos de 5000 habitantes, puede haber una posible demora.</p>
<p>Si no te encuentras en tu domicilio, la empresa de transporte te enviará un SMS al teléfono que has indicado en el pedido, para que te pongas en contacto con ellos y acordéis el momento y el horario de entrega que mejor te vengan.</p>
<h2>Devoluciones</h2>
<p>Si el producto te llega en mal estado, Meatprotein se hace cargo de la devolución de tu pedido o de su reposición.</p>
<h2>Servicio de atención al cliente</h2>
<p>Para cualquier incidencia, reclamación o ejercicio de tus derechos puedes escribirnos a <a href="mailto:info@meatprotein.eu">info@meatprotein.eu</a>, llamar a la central, 944 999 194, de 9:00 a 14:00, o escribir por WhatsApp al 944 999 194 de 10:00 a 18:00.</p>';
		}

		/**
		 * Cambios de texto en «Envíos y devoluciones»: la tabla y el párrafo de gastos de envío (decían 50 €).
		 *
		 * @return array<string,string> Texto actual => texto nuevo.
		 */
		private function envios_replacements() {
			$cost    = number_format_i18n( (float) mpc_opt( 'ship_cost' ), 2 );
			$cost_pt = number_format_i18n( (float) mpc_opt( 'ship_cost_pt' ), 2 );
			$when    = 'Antes de las 14:00 (ver plazos más abajo)';
			return array(
				'Los pedidos a Canarias, Ceuta y Melilla o internacionales podrán estar sujetos a cargos de despacho de aduanas, así como por impuestos que graven su importación en dichos territorios, que también irán cargo del cliente.' =>
					'Enviamos a la Península y a Portugal. Canarias, Baleares, Ceuta, Melilla y el resto de destinos quedan excluidos por el momento.',
				'<tr><td>Zona</td><td>&lt;50€</td><td>&gt;10 bandejas</td><td>Plazos de entrega (*)</td></tr><tr><td>Península</td><td>9,00€</td><td>GRATIS</td><td>24-48 horas</td></tr>' =>
					'<tr><td>Zona</td><td>Gastos de envío</td><td>Gratis con</td><td>Plazos de entrega (*)</td></tr><tr><td>Península</td><td>' . $cost . ' €</td><td>El pack o 10 bandejas de hamburguesas o más</td><td>' . $when . '</td></tr><tr><td>Portugal</td><td>' . $cost_pt . ' €</td><td>No hay envío gratis a Portugal, tampoco con el pack</td><td>' . $when . '</td></tr>',
				'Para pedidos con destino/entrega en península, en los que el importe de todos los productos sea superior a 50.00€, (el equivalente a 10 bandejas de hamburguesas), los gastos de envío serán gratuitos.<br><br><strong>(*)</strong> Los pedidos realizados después de las 08.00h, se envían al día siguiente hábil, por lo que el plazo de entrega debe computarse a partir de la fecha en la que enviamos el pedido.<br><br>Si tu población se encuentra alejada de un gran núcleo urbano o ciudad, o tiene menos de 5000 habitantes, puede que tenga una posible demora. Los pedidos recibidos los jueves después de las 8h y viernes que sean fuera de Madrid serán enviados a partir del lunes para preservar la calidad del producto.' =>
					'Para pedidos con destino en la Península en los que el pedido incluya el pack o 10 bandejas de hamburguesas o más, los gastos de envío serán gratuitos. En el resto de pedidos se aplican los gastos de envío indicados en la tabla. Los envíos a Portugal tienen siempre los gastos indicados, también cuando el pedido incluye el pack.<br><br><strong>(*)</strong> Enviamos de lunes a jueves, con SEUR y en transporte refrigerado, y entregamos antes de las 14:00. La hora de corte es las 13:00 (hora peninsular). Los viernes no enviamos, para que el producto refrigerado no pase el fin de semana en tránsito.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><li>Pedidos de lunes a jueves antes de las 13:00: lo recibes al día siguiente antes de las 14:00.</li><li>Pedidos de lunes a miércoles después de las 13:00: lo recibes dos días después antes de las 14:00.</li><li>Pedidos desde el jueves a las 13:00 hasta el lunes a las 13:00: lo recibes el martes antes de las 14:00.</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Si tu población se encuentra alejada de un gran núcleo urbano o ciudad, o tiene menos de 5000 habitantes, puede que tenga una posible demora.',
			);
		}

		/**
		 * Limpia FAQs y Envíos y devoluciones, guardando el contenido anterior.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Muestra qué cambiaría, sin guardar nada.
		 *
		 * @param array $args       Argumentos posicionales.
		 * @param array $assoc_args Argumentos con nombre.
		 * @return void
		 */
		public function fix( $args, $assoc_args ) {
			$dry = isset( $assoc_args['dry-run'] );
			$log = (array) get_option( 'mpc_pages_fix_log', array() );
			$new = array();

			$faqs = get_page_by_path( 'faqs' );
			if ( $faqs ) {
				$new[ $faqs->ID ] = $this->faqs_html();
			}
			$env = get_page_by_path( 'envios-y-devoluciones' );
			if ( $env ) {
				$content = $env->post_content;
				foreach ( $this->envios_replacements() as $old => $repl ) {
					if ( false === strpos( $content, $old ) ) {
						WP_CLI::warning( 'Envíos y devoluciones: no se encontró un texto a sustituir (¿ya está limpio?): ' . mb_substr( wp_strip_all_tags( $old ), 0, 60 ) );
						continue;
					}
					$content = str_replace( $old, $repl, $content );
				}
				if ( $content !== $env->post_content ) {
					$new[ $env->ID ] = $content;
				}
			}
			foreach ( $new as $id => $content ) {
				$post = get_post( $id );
				WP_CLI::log( sprintf( '#%d %s: %d -> %d caracteres', $id, $post->post_name, strlen( $post->post_content ), strlen( $content ) ) );
				if ( $dry ) {
					continue;
				}
				if ( ! isset( $log[ $id ] ) ) {
					$log[ $id ] = $post->post_content;
				}
				wp_update_post(
					array(
						'ID'           => $id,
						'post_content' => $content,
					)
				);
			}
			if ( ! $dry ) {
				update_option( 'mpc_pages_fix_log', $log, false );
			}
			WP_CLI::success( ( $dry ? 'Simulación: ' : '' ) . count( $new ) . ' página(s).' );
		}

		/**
		 * Devuelve las páginas al contenido que tenían antes de `fix`.
		 *
		 * @return void
		 */
		public function revert() {
			$log = (array) get_option( 'mpc_pages_fix_log', array() );
			foreach ( $log as $id => $content ) {
				wp_update_post(
					array(
						'ID'           => (int) $id,
						'post_content' => $content,
					)
				);
			}
			delete_option( 'mpc_pages_fix_log' );
			WP_CLI::success( count( $log ) . ' página(s) restauradas.' );
		}
	}

	WP_CLI::add_command( 'mpc pages', 'MPC_Pages_Command' );
}
