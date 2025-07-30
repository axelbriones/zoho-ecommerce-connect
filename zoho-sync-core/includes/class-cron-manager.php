<?php

if (!defined('ABSPATH')) {
    exit;
}

class Zoho_Sync_Core_Cron_Manager {

    public static function unschedule_all_cron_jobs() {
        $crons = _get_cron_array();
        if (empty($crons)) {
            return;
        }

        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $dings) {
                if (strpos($hook, 'zoho_sync_core_') === 0) {
                    foreach ($dings as $sig => $data) {
                        wp_unschedule_event($timestamp, $hook, $data['args']);
                    }
                }
            }
        }
    }

    public static function schedule_cron_jobs() {
        if (!wp_next_scheduled('zoho_sync_core_refresh_tokens')) {
            wp_schedule_event(time(), 'hourly', 'zoho_sync_core_refresh_tokens');
        }
        if (!wp_next_scheduled('zoho_sync_core_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'zoho_sync_core_cleanup_logs');
        }
    }

    public static function clear_cron_jobs() {
        $timestamp = wp_next_scheduled('zoho_sync_core_refresh_tokens');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'zoho_sync_core_refresh_tokens');
        }
        $timestamp = wp_next_scheduled('zoho_sync_core_cleanup_logs');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'zoho_sync_core_cleanup_logs');
        }
    }
}
