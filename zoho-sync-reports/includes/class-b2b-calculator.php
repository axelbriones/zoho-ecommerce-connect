<?php

class ZSRP_B2B_Calculator {
    
    public function calculate_b2b_metrics($sales_data) {
        return [
            'total_sales' => $this->calculate_total_sales($sales_data),
            'average_order' => $this->calculate_average_order($sales_data),
            'top_distributors' => $this->get_top_distributors($sales_data),
            'growth_rate' => $this->calculate_growth_rate($sales_data),
            'monthly_stats' => $this->calculate_monthly_stats($sales_data)
        ];
    }

    public function calculate_total_sales($sales_data) {
        return $sales_data['total_sales'] ?? 0;
    }

    public function calculate_average_order($sales_data) {
        $total_orders = count($sales_data['monthly'] ?? []);
        if ($total_orders === 0) return 0;
        
        return $sales_data['total_sales'] / $total_orders;
    }

    private function get_top_distributors($sales_data, $limit = 5) {
        $distributors = [];
        foreach ($sales_data['by_distributor'] as $id => $total) {
            $distributor_data = $this->get_distributor_info($id);
            if ($distributor_data) {
                $distributors[] = [
                    'id' => $id,
                    'name' => $distributor_data['name'],
                    'total_sales' => $total,
                    'order_count' => $this->count_distributor_orders($id),
                    'average_order' => $this->calculate_distributor_average($id, $total)
                ];
            }
        }

        usort($distributors, function($a, $b) {
            return $b['total_sales'] - $a['total_sales'];
        });

        return array_slice($distributors, 0, $limit);
    }

    private function calculate_growth_rate($sales_data) {
        $monthly = $sales_data['monthly'] ?? [];
        if (count($monthly) < 2) return 0;

        $months = array_keys($monthly);
        $current_month = end($monthly);
        $previous_month = prev($monthly);

        if ($previous_month == 0) return 0;

        return (($current_month - $previous_month) / $previous_month) * 100;
    }

    private function calculate_monthly_stats($sales_data) {
        $monthly = $sales_data['monthly'] ?? [];
        if (empty($monthly)) return [];

        $stats = [];
        foreach ($monthly as $month => $total) {
            $stats[$month] = [
                'total' => $total,
                'orders' => $this->count_month_orders($month),
                'average' => $this->calculate_month_average($month, $total)
            ];
        }

        return $stats;
    }

    private function get_distributor_info($distributor_id) {
        // Utilizar la clase de info de distribuidores del core
        if (class_exists('ZSCU_Distributor_Info')) {
            return ZSCU_Distributor_Info::get_distributor_data($distributor_id);
        }
        
        // Fallback a datos básicos de WordPress
        $user = get_userdata($distributor_id);
        return $user ? [
            'name' => $user->display_name,
            'email' => $user->user_email
        ] : null;
    }

    private function count_distributor_orders($distributor_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND pm.meta_key = '_billing_distributor_id'
            AND pm.meta_value = %d
        ", $distributor_id));
    }

    private function calculate_distributor_average($distributor_id, $total_sales) {
        $order_count = $this->count_distributor_orders($distributor_id);
        return $order_count > 0 ? $total_sales / $order_count : 0;
    }

    private function count_month_orders($month) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'shop_order'
            AND post_status IN ('wc-completed', 'wc-processing')
            AND DATE_FORMAT(post_date, '%%Y-%%m') = %s
        ", $month));
    }

    private function calculate_month_average($month, $total) {
        $order_count = $this->count_month_orders($month);
        return $order_count > 0 ? $total / $order_count : 0;
    }
}