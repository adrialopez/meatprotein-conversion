// Pedidos con HPOS: columnas, filtro y consulta propios de la pantalla WooCommerce > Pedidos.
// Añadir las columnas de empresa, correo electrónico del cliente y método de pago
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'anadir_columnas_personalizadas_pedido' );
function anadir_columnas_personalizadas_pedido( $columns ) {
    $nuevas_columnas = array();

    // Añadir las nuevas columnas después de la columna de estado del pedido
    foreach ( $columns as $nombre_columna => $info_columna ) {
        $nuevas_columnas[ $nombre_columna ] = $info_columna;
        if ( 'order_status' === $nombre_columna ) {
            $nuevas_columnas['billing_company'] = 'Empresa';
            $nuevas_columnas['customer_email'] = 'Correo electrónico';
            $nuevas_columnas['payment_method'] = 'Método de pago';
        }
    }

    return $nuevas_columnas;
}

// Rellenar las nuevas columnas con datos
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'rellenar_columnas_personalizadas_pedido', 10, 2 );
function rellenar_columnas_personalizadas_pedido( $column, $pedido ) {
    if ( ! $pedido instanceof WC_Order ) {
        $pedido = wc_get_order( $pedido );
    }
    if ( ! $pedido ) {
        return;
    }

    switch ( $column ) {
        case 'billing_company':
            echo esc_html( $pedido->get_billing_company() );
            break;
        case 'customer_email':
            echo esc_html( $pedido->get_billing_email() );
            break;
        case 'payment_method':
            echo esc_html( $pedido->get_payment_method_title() );
            break;
    }
}

// Añadir el filtro de método de pago en la pantalla de pedidos
add_action( 'woocommerce_order_list_table_restrict_manage_orders', 'anadir_filtro_metodo_pago', 10, 2 );
function anadir_filtro_metodo_pago( $order_type = 'shop_order', $which = 'top' ) {
    if ( 'shop_order' !== $order_type ) {
        return;
    }
    $metodo_pago_actual = isset( $_GET['payment_method'] ) ? wc_clean( wp_unslash( $_GET['payment_method'] ) ) : '';

    // Métodos de pago de los pedidos reales: identificador y el título más reciente
    global $wpdb;
    $metodos = $wpdb->get_results(
        "SELECT payment_method, MAX(payment_method_title) AS titulo
         FROM {$wpdb->prefix}wc_orders
         WHERE type = 'shop_order' AND payment_method != ''
         GROUP BY payment_method
         ORDER BY titulo ASC"
    );

    ?>
    <select name="payment_method" id="dropdown_payment_method">
        <option value=""><?php esc_html_e( 'Todos los métodos de pago', 'woocommerce' ); ?></option>
        <?php foreach ( $metodos as $metodo ) : ?>
            <option value="<?php echo esc_attr( $metodo->payment_method ); ?>" <?php selected( $metodo_pago_actual, $metodo->payment_method ); ?>>
                <?php echo esc_html( $metodo->titulo ? $metodo->titulo : $metodo->payment_method ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

// Filtrar pedidos por método de pago
add_filter( 'woocommerce_order_list_table_prepare_items_query_args', 'filtrar_pedidos_por_metodo_pago' );
function filtrar_pedidos_por_metodo_pago( $args ) {
    if ( ! empty( $_GET['payment_method'] ) ) {
        $args['payment_method'] = wc_clean( wp_unslash( $_GET['payment_method'] ) );
    }
    return $args;
}
