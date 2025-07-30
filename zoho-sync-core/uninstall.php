<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = array(
    'zoho_sync_settings',
    'zoho_sync_logs',
    'zoho_sync_tokens',
    'zoho_sync_modules'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}

delete_option('zoho_sync_core_db_version');
