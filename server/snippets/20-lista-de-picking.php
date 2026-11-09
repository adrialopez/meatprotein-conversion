/**
 * Lista de Picking. Por defecto muestra los pedidos por procesar (en proceso) y «Desde pedido #» se rellena con
 * el más antiguo que sigue en proceso. Ambos valores se pueden cambiar en el formulario.
 */

// Primer pedido en proceso (el más antiguo por procesar): valor por defecto de «Desde pedido #».
function picking_list_default_min_order() {
    $ids = wc_get_orders([
        'type'    => 'shop_order',
        'status'  => ['processing'],
        'limit'   => 1,
        'orderby' => 'id',
        'order'   => 'ASC',
        'return'  => 'ids',
    ]);
    return !empty($ids) ? (int) $ids[0] : 0;
}

// Estado elegido en el selector: processing (por defecto), completed o both.
function picking_list_estado() {
    $e = isset($_GET['estado']) ? sanitize_key($_GET['estado']) : 'processing';
    return in_array($e, ['processing', 'completed', 'both'], true) ? $e : 'processing';
}

function picking_list_statuses() {
    $e = picking_list_estado();
    return $e === 'both' ? ['processing', 'completed'] : [$e];
}

// «Desde pedido #»: el indicado o, si está vacío, el primero que sigue en proceso.
function picking_list_min_order() {
    if (isset($_GET['min_order']) && $_GET['min_order'] !== '') {
        return intval($_GET['min_order']);
    }
    return picking_list_default_min_order();
}

// Crear página de administración
add_action('admin_menu', 'picking_list_add_admin_page');

function picking_list_add_admin_page() {
    add_menu_page(
        'Lista de Picking',           
        'Lista Picking',           
        'manage_woocommerce',   
        'picking-list-report',     
        'picking_list_render_page',
        'dashicons-clipboard', 
        56                      
    );
}

// Renderizar la página
function picking_list_render_page() {
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        export_picking_list_to_csv();
    }
    
    $min_order_id = picking_list_min_order();
    $estado       = picking_list_estado();
    $orders       = get_picking_list_orders($min_order_id, picking_list_statuses());
    $estado_txt   = ['processing' => 'por procesar', 'completed' => 'completados', 'both' => 'por procesar y completados'][$estado];
    ?>
    <div class="wrap">
        <h1>Lista de Picking - Pedidos <?php echo esc_html($estado_txt); ?><?php echo $min_order_id ? ' desde #' . intval($min_order_id) : ''; ?></h1>
        
        <div style="margin: 20px 0;">
            <form method="get" style="display: inline-block;">
                <input type="hidden" name="page" value="picking-list-report">
                <label>Pedidos: </label>
                <select name="estado">
                    <option value="processing" <?php selected($estado, 'processing'); ?>>Por procesar</option>
                    <option value="completed" <?php selected($estado, 'completed'); ?>>Completados</option>
                    <option value="both" <?php selected($estado, 'both'); ?>>Ambos</option>
                </select>
                <label style="margin-left: 10px;">Desde pedido #: </label>
                <input type="number" name="min_order" value="<?php echo intval($min_order_id); ?>" style="width: 100px;">
                <button type="submit" class="button">Filtrar</button>
            </form>
            
            <a href="<?php echo admin_url('admin.php?page=picking-list-report&min_order=' . intval($min_order_id) . '&estado=' . $estado . '&export=csv'); ?>" 
               class="button button-primary" style="margin-left: 10px;">
                📥 Exportar a CSV
            </a>
        </div>
        
        <?php if (!empty($orders)) : ?>
            <p><strong>Total de pedidos: <?php echo count($orders); ?></strong></p>
            
            <?php foreach ($orders as $order_data) : ?>
                <div style="background: #fff; border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; page-break-inside: avoid;">
                    <div style="border-bottom: 2px solid #2271b1; margin-bottom: 10px; padding-bottom: 10px;">
                        <h2 style="margin: 0;">
                            Pedido #<?php echo $order_data['order_id']; ?> 
                            <span style="font-size: 14px; color: #666;">
                                | <?php echo $order_data['order_date']; ?>
                            </span>
                            <span style="background: <?php echo $order_data['status_color']; ?>; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 12px; margin-left: 10px;">
                                <?php echo $order_data['status']; ?>
                            </span>
                        </h2>
                        <p style="margin: 5px 0 0 0;">
                            <strong>Cliente:</strong> <?php echo esc_html($order_data['customer']); ?> | 
                            <strong>Email:</strong> <?php echo esc_html($order_data['email']); ?> | 
                            <strong>Teléfono:</strong> <?php echo esc_html($order_data['phone']); ?>
                        </p>
                        <?php if (!empty($order_data['shipping_address'])) : ?>
                            <p style="margin: 5px 0 0 0;">
                                <strong>Envío:</strong> <?php echo nl2br(esc_html($order_data['shipping_address'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f0f0f1;">
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Producto</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Atributos</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: center; width: 80px;">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_data['items'] as $item) : ?>
                                <tr>
                                    <td style="border: 1px solid #ddd; padding: 8px;">
                                        <strong><?php echo esc_html($item['product_name']); ?></strong>
                                        <?php if ($item['sku']) : ?>
                                            <br><small style="color: #666;">SKU: <?php echo esc_html($item['sku']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="border: 1px solid #ddd; padding: 8px;">
                                        <?php if (!empty($item['attributes'])) : ?>
                                            <?php foreach ($item['attributes'] as $attr_name => $attr_value) : ?>
                                                <div style="margin-bottom: 5px;">
                                                    <strong><?php echo esc_html($attr_name); ?>:</strong> 
                                                    <?php echo esc_html($attr_value); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <em style="color: #999;">Sin atributos</em>
                                        <?php endif; ?>
                                    </td>
                                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center; font-size: 18px;">
                                        <strong><?php echo $item['quantity']; ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (!empty($order_data['notes'])) : ?>
                        <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
                            <strong>📝 Notas del pedido:</strong><br>
                            <?php echo nl2br(esc_html($order_data['notes'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
        <?php else : ?>
            <div class="notice notice-warning">
                <p>No hay pedidos <?php echo esc_html($estado_txt); ?><?php echo $min_order_id ? ' desde el #' . intval($min_order_id) : ''; ?>.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        @media print {
            .wrap > div:first-child, .button { display: none; }
            body { background: white; }
        }
    </style>
    <?php
}

// Obtener lista de pedidos para picking
function get_picking_list_orders($min_order_id = 0, $statuses = ['processing']) {
    // Pedidos en proceso o completados desde el ID indicado, con la API de WooCommerce (válida con HPOS, sin leer wp_posts).
    $args = [
        'type'    => 'shop_order',
        'status'  => $statuses,
        'limit'   => -1,
        'orderby' => 'id',
        'order'   => 'ASC',
        'return'  => 'ids',
    ];
    // Para no cargar todo el histórico, se parte de la fecha del primer pedido del rango.
    $first = $min_order_id ? wc_get_order($min_order_id) : false;
    if ($first && $first->get_date_created()) {
        $args['date_created'] = '>=' . ($first->get_date_created()->getTimestamp() - DAY_IN_SECONDS);
    } elseif (in_array('completed', $statuses, true)) {
        // Sin pedido de partida, los completados se limitan a los últimos 30 días para no cargar todo el histórico.
        $args['date_created'] = '>=' . (time() - 30 * DAY_IN_SECONDS);
    }
    $order_ids = array_values(array_filter(array_map('intval', wc_get_orders($args)), function ($id) use ($min_order_id) {
        return $id >= $min_order_id;
    }));
    sort($order_ids);

    $orders_data = [];
    
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;
        
        $items_data = [];
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_name = $item->get_name();
            $sku = $product ? $product->get_sku() : '';
            
            // Obtener todos los metadatos del item (atributos personalizados)
            $attributes = [];
            $item_meta = $item->get_meta_data();
            
            foreach ($item_meta as $meta) {
                $key = $meta->key;
                $value = $meta->value;
                
                // Excluir metadatos internos que empiezan con _
                if (substr($key, 0, 1) !== '_' && !empty($value)) {
                    $attributes[$key] = $value;
                }
            }
            
            $items_data[] = [
                'product_name' => $product_name,
                'sku' => $sku,
                'quantity' => $item->get_quantity(),
                'attributes' => $attributes
            ];
        }
        
        // Información del cliente
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($customer_name)) {
            $customer_name = 'Sin nombre';
        }
        
        // Dirección de envío
        $shipping_address = '';
        if ($order->has_shipping_address()) {
            $shipping_parts = [
                $order->get_shipping_address_1(),
                $order->get_shipping_address_2(),
                $order->get_shipping_postcode() . ' ' . $order->get_shipping_city(),
                $order->get_shipping_state(),
                $order->get_shipping_country()
            ];
            $shipping_address = implode("\n", array_filter($shipping_parts));
        }
        
        // Color del estado
        $status = $order->get_status();
        $status_colors = [
            'processing' => '#c6e1c6',
            'completed' => '#2271b1'
        ];
        
        $orders_data[] = [
            'order_id' => $order_id,
            'order_date' => $order->get_date_created()->date('d/m/Y H:i'),
            'status' => wc_get_order_status_name($status),
            'status_color' => isset($status_colors[$status]) ? $status_colors[$status] : '#999',
            'customer' => $customer_name,
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'shipping_address' => $shipping_address,
            'notes' => $order->get_customer_note(),
            'items' => $items_data
        ];
    }
    
    return $orders_data;
}

// Exportar a CSV
function export_picking_list_to_csv() {
    $min_order_id = picking_list_min_order();
    $orders = get_picking_list_orders($min_order_id, picking_list_statuses());
    
    if (empty($orders)) {
        wp_die('No hay pedidos para exportar.');
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=picking-list-' . date('Y-m-d-His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Pedido', 'Fecha', 'Estado', 'Cliente', 'Email', 'Teléfono', 'Producto', 'SKU', 'Atributos', 'Cantidad'], ';');
    
    foreach ($orders as $order) {
        foreach ($order['items'] as $item) {
            // Formatear atributos
            $attributes_str = '';
            if (!empty($item['attributes'])) {
                $attr_parts = [];
                foreach ($item['attributes'] as $key => $value) {
                    $attr_parts[] = "$key: $value";
                }
                $attributes_str = implode(' | ', $attr_parts);
            }
            
            fputcsv($output, [
                $order['order_id'],
                $order['order_date'],
                $order['status'],
                $order['customer'],
                $order['email'],
                $order['phone'],
                $item['product_name'],
                $item['sku'],
                $attributes_str,
                $item['quantity']
            ], ';');
        }
    }
    
    fclose($output);
    exit;
}
