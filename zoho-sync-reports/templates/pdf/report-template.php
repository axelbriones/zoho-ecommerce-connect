<?php
/**
 * Plantilla PDF para reportes de ventas
 */
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        .report-header {
            text-align: center;
            color: #2271b1;
            margin-bottom: 30px;
        }
        
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .stats-table th,
        .stats-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .stats-table th {
            background-color: #f8f9fa;
        }
        
        .summary-box {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <h1><?php _e('Reporte de Ventas', 'zoho-sync-reports'); ?></h1>
        <p><?php echo esc_html(sprintf(
            __('Período: %s - %s', 'zoho-sync-reports'),
            $report_data['date_range']['start'],
            $report_data['date_range']['end']
        )); ?></p>
    </div>

    <div class="summary-box">
        <h2><?php _e('Resumen', 'zoho-sync-reports'); ?></h2>
        <p><strong><?php _e('Ventas Totales:', 'zoho-sync-reports'); ?></strong> 
           <?php echo wc_price($report_data['metrics']['total_sales']); ?></p>
        <p><strong><?php _e('Promedio por Orden:', 'zoho-sync-reports'); ?></strong> 
           <?php echo wc_price($report_data['metrics']['average_order']); ?></p>
        <p><strong><?php _e('Tasa de Crecimiento:', 'zoho-sync-reports'); ?></strong> 
           <?php echo number_format($report_data['metrics']['growth_rate'], 2); ?>%</p>
    </div>

    <?php if (!empty($report_data['metrics']['top_distributors'])): ?>
    <h2><?php _e('Top Distribuidores', 'zoho-sync-reports'); ?></h2>
    <table class="stats-table">
        <thead>
            <tr>
                <th><?php _e('Distribuidor', 'zoho-sync-reports'); ?></th>
                <th><?php _e('Ventas', 'zoho-sync-reports'); ?></th>
                <th><?php _e('Órdenes', 'zoho-sync-reports'); ?></th>
                <th><?php _e('Promedio', 'zoho-sync-reports'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data['metrics']['top_distributors'] as $distributor): ?>
            <tr>
                <td><?php echo esc_html($distributor['name']); ?></td>
                <td><?php echo wc_price($distributor['total_sales']); ?></td>
                <td><?php echo esc_html($distributor['order_count']); ?></td>
                <td><?php echo wc_price($distributor['average_order']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h2><?php _e('Estadísticas Mensuales', 'zoho-sync-reports'); ?></h2>
    <table class="stats-table">
        <thead>
            <tr>
                <th><?php _e('Mes', 'zoho-sync-reports'); ?></th>
                <th><?php _e('Ventas', 'zoho-sync-reports'); ?></th>
                <th><?php _e('Órdenes', 'zoho-sync-reports'); ?></th>
                <th><?php _e('Promedio', 'zoho-sync-reports'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data['metrics']['monthly_stats'] as $month => $stats): ?>
            <tr>
                <td><?php echo esc_html(date('F Y', strtotime($month))); ?></td>
                <td><?php echo wc_price($stats['total']); ?></td>
                <td><?php echo esc_html($stats['orders']); ?></td>
                <td><?php echo wc_price($stats['average']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>