<?php

class ZSRP_Reports_Generator {
    private $analyzer;
    private $calculator;
    private $exporter;
    
    public function __construct() {
        $this->analyzer = new ZSRP_Sales_Analyzer();
        $this->calculator = new ZSRP_B2B_Calculator();
        $this->exporter = new ZSRP_Export_Manager();
    }

    public static function ajax_generate_report() {
        check_ajax_referer('zsrp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'zoho-sync-reports')]);
        }

        $type = sanitize_text_field($_POST['report_type']);
        $date_range = [
            'start' => sanitize_text_field($_POST['start_date']),
            'end' => sanitize_text_field($_POST['end_date'])
        ];

        try {
            $generator = new self();
            $report_data = $generator->generate_report($type, $date_range);
            
            wp_send_json_success([
                'data' => $report_data,
                'download_url' => $generator->get_download_url($report_data)
            ]);
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 
                'Error generando reporte: ' . $e->getMessage(),
                ['type' => $type], 
                'reports'
            );
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function generate_report($type, $date_range) {
        switch ($type) {
            case 'b2b':
                return $this->generate_b2b_report($date_range);
            case 'b2c':
                return $this->generate_b2c_report($date_range);
            case 'combined':
                return $this->generate_combined_report($date_range);
            default:
                throw new Exception(__('Tipo de reporte no válido', 'zoho-sync-reports'));
        }
    }

    private function generate_b2b_report($date_range) {
        $sales_data = $this->analyzer->get_b2b_sales($date_range);
        $metrics = $this->calculator->calculate_b2b_metrics($sales_data);
        
        return [
            'type' => 'b2b',
            'date_range' => $date_range,
            'sales_data' => $sales_data,
            'metrics' => $metrics,
            'charts' => [
                'sales_by_distributor' => $this->generate_distributor_chart($sales_data),
                'monthly_trend' => $this->generate_trend_chart($sales_data)
            ]
        ];
    }

    private function generate_b2c_report($date_range) {
        $sales_data = $this->analyzer->get_b2c_sales($date_range);
        
        return [
            'type' => 'b2c',
            'date_range' => $date_range,
            'sales_data' => $sales_data,
            'metrics' => [
                'total_sales' => $this->calculator->calculate_total_sales($sales_data),
                'average_order' => $this->calculator->calculate_average_order($sales_data),
                'top_products' => $this->analyzer->get_top_products($sales_data)
            ]
        ];
    }

    private function generate_trend_chart($sales_data) {
        return [
            'labels' => array_keys($sales_data['monthly']),
            'datasets' => [
                [
                    'label' => __('Ventas Mensuales', 'zoho-sync-reports'),
                    'data' => array_values($sales_data['monthly'])
                ]
            ]
        ];
    }

    public function get_download_url($report_data) {
        $format = get_option('zsrp_export_format', 'pdf');
        return $this->exporter->generate_download_url($report_data, $format);
    }

    public static function generate_scheduled_report() {
        $generator = new self();
        $report = $generator->generate_report('combined', [
            'start' => date('Y-m-d', strtotime('-7 days')),
            'end' => date('Y-m-d')
        ]);

        // Enviar por email
        $recipients = get_option('zsrp_email_recipients', '');
        if ($recipients) {
            $generator->send_report_email($report, explode(',', $recipients));
        }
    }

    private function send_report_email($report, $recipients) {
        $subject = sprintf(
            __('Reporte Semanal de Ventas %s', 'zoho-sync-reports'),
            date('d/m/Y')
        );

        $attachments = [];
        if ($pdf_path = $this->exporter->generate_pdf($report)) {
            $attachments[] = $pdf_path;
        }

        foreach ($recipients as $email) {
            wp_mail(
                trim($email),
                $subject,
                $this->get_email_content($report),
                ['Content-Type: text/html; charset=UTF-8'],
                $attachments
            );
        }
    }

    private function get_email_content($report) {
        ob_start();
        include ZSRP_PLUGIN_DIR . 'templates/email/report-email.php';
        return ob_get_clean();
    }
}