<?php

class ZSRP_Export_Manager {
    private $temp_dir;
    
    public function __construct() {
        $this->temp_dir = ZSRP_PLUGIN_DIR . 'temp/';
        
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
        }
    }

    public function generate_download_url($report_data, $format = 'pdf') {
        $file_id = uniqid('report_');
        $file_path = $this->generate_file($report_data, $format, $file_id);
        
        if (!$file_path) {
            return false;
        }

        return add_query_arg([
            'action' => 'zsrp_download_report',
            'file' => $file_id,
            'format' => $format,
            'nonce' => wp_create_nonce('zsrp_download_' . $file_id)
        ], admin_url('admin-ajax.php'));
    }

    public function generate_file($report_data, $format, $file_id) {
        switch ($format) {
            case 'pdf':
                return $this->generate_pdf($report_data, $file_id);
            case 'excel':
                return $this->generate_excel($report_data, $file_id);
            case 'csv':
                return $this->generate_csv($report_data, $file_id);
            default:
                return false;
        }
    }

    private function generate_pdf($report_data, $file_id) {
        require_once ZSRP_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle(__('Reporte de Ventas', 'zoho-sync-reports'));

        // Añadir página
        $pdf->AddPage();

        // Generar contenido
        $html = $this->get_pdf_template($report_data);
        $pdf->writeHTML($html, true, false, true, false, '');

        // Guardar archivo
        $file_path = $this->temp_dir . $file_id . '.pdf';
        $pdf->Output($file_path, 'F');

        return $file_path;
    }

    private function generate_excel($report_data, $file_id) {
        require_once ZSRP_PLUGIN_DIR . 'vendor/phpoffice/phpspreadsheet/src/Bootstrap.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Configurar encabezados
        $sheet->setCellValue('A1', __('Fecha', 'zoho-sync-reports'));
        $sheet->setCellValue('B1', __('Ventas', 'zoho-sync-reports'));
        $sheet->setCellValue('C1', __('Órdenes', 'zoho-sync-reports'));
        $sheet->setCellValue('D1', __('Promedio', 'zoho-sync-reports'));

        // Añadir datos
        $row = 2;
        foreach ($report_data['monthly_stats'] as $month => $stats) {
            $sheet->setCellValue('A' . $row, $month);
            $sheet->setCellValue('B' . $row, $stats['total']);
            $sheet->setCellValue('C' . $row, $stats['orders']);
            $sheet->setCellValue('D' . $row, $stats['average']);
            $row++;
        }

        // Guardar archivo
        $file_path = $this->temp_dir . $file_id . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($file_path);

        return $file_path;
    }

    private function generate_csv($report_data, $file_id) {
        $file_path = $this->temp_dir . $file_id . '.csv';
        $fp = fopen($file_path, 'w');

        // Escribir encabezados
        fputcsv($fp, [
            __('Fecha', 'zoho-sync-reports'),
            __('Ventas', 'zoho-sync-reports'),
            __('Órdenes', 'zoho-sync-reports'),
            __('Promedio', 'zoho-sync-reports')
        ]);

        // Escribir datos
        foreach ($report_data['monthly_stats'] as $month => $stats) {
            fputcsv($fp, [
                $month,
                $stats['total'],
                $stats['orders'],
                $stats['average']
            ]);
        }

        fclose($fp);
        return $file_path;
    }

    private function get_pdf_template($report_data) {
        ob_start();
        include ZSRP_PLUGIN_DIR . 'templates/pdf/report-template.php';
        return ob_get_clean();
    }

    public function cleanup_temp_files($hours = 24) {
        $files = glob($this->temp_dir . '*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= $hours * 3600) {
                    unlink($file);
                }
            }
        }
    }
}