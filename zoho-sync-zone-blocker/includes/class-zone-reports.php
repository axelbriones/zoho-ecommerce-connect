<?php

class ZSZB_Zone_Reports {
    
    public static function get_blocked_attempts($start_date = null, $end_date = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zszb_access_log';
        $where = ['1=1'];
        $args = [];
        
        if ($start_date) {
            $where[] = 'created_at >= %s';
            $args[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = 'created_at <= %s';
            $args[] = $end_date;
        }
        
        $sql = $wpdb->prepare(
            "SELECT 
                postal_code,
                COUNT(*) as attempts,
                MAX(created_at) as last_attempt
            FROM $table
            WHERE " . implode(' AND ', $where) . "
            GROUP BY postal_code
            ORDER BY attempts DESC",
            $args
        );
        
        return $wpdb->get_results($sql);
    }
    
    public static function export_csv($data) {
        $filename = 'zone-blocker-report-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, [
            'Código Postal',
            'Intentos Bloqueados',
            'Último Intento'
        ]);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row->postal_code,
                $row->attempts,
                $row->last_attempt
            ]);
        }
        
        fclose($output);
        exit;
    }
}