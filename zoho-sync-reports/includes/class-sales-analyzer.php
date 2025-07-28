<?php

class ZSRP_Sales_Analyzer {
    
    public function get_b2b_sales($date_range) {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT 
                p.ID as order_id,
                p.post_date,
                pm.meta_value as order_total,
                distributor.meta_value as distributor_id
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            JOIN {$wpdb->postmeta} distributor ON p.ID = distributor.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_order_total'
            AND distributor.meta_key = '_billing_distributor_id'
            AND p.post_date BETWEEN %s AND %s
        ", $date_range['start'], $date_range['end']);

        $orders = $wpdb->get_results($sql);

        return $this->process_b2b_data($orders);
    }

    private function process_b2b_data($orders) {
        $data = [
            'total_sales' => 0,
            'by_distributor' => [],
            'monthly' => [],
            'products' => []
        ];

        foreach ($orders as $order) {
            // Totales por distribuidor
            if (!isset($data['by_distributor'][$order->distributor_id])) {
                $data['by_distributor'][$order->distributor_id] = 0;
            }
            $data['by_distributor'][$order->distributor_id] += $order->order_total;

            // Totales mensuales
            $month = date('Y-m', strtotime($order->post_date));
            if (!isset($data['monthly'][$month])) {
                $data['monthly'][$month] = 0;
            }
            $data['monthly'][$month] += $order->order_total;

            // Total general
            $data['total_sales'] += $order->order_total;

            // Productos vendidos
            $this->process_order_items($order->order_id, $data['products']);
        }

        // Ordenar datos
        arsort($data['by_distributor']);
        ksort($data['monthly']);

        return $data;
    }

    public function get_b2c_sales($date_range) {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT 
                p.ID as order_id,
                p.post_date,
                pm.meta_value as order_total
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} distributor ON p.ID = distributor.post_id 
                AND distributor.meta_key = '_billing_distributor_id'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_order_total'
            AND distributor.meta_value IS NULL
            AND p.post_date BETWEEN %s AND %s
        ", $date_range['start'], $date_range['end']);

        $orders = $wpdb->get_results($sql);

        return $this->process_b2c_data($orders);
    }

    private function process_order_items($order_id, &$products) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (!isset($products[$product_id])) {
                $products[$product_id] = [
                    'name' => $item->get_name(),
                    'quantity' => 0,
                    'total' => 0
                ];
            }
            $products[$product_id]['quantity'] += $item->get_quantity();
            $products[$product_id]['total'] += $item->get_total();
        }
    }

    public function get_top_products($sales_data, $limit = 10) {
        $products = $sales_data['products'];
        uasort($products, function($a, $b) {
            return $b['total'] - $a['total'];
        });
        
        return array_slice($products, 0, $limit);
    }
}