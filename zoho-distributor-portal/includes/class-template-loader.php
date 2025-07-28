<?php

class ZSDP_Template_Loader {
    
    public static function get_template($template_name, $args = []) {
        if ($args && is_array($args)) {
            extract($args);
        }

        $template_path = self::locate_template($template_name);
        
        if (!$template_path) {
            return;
        }

        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    private static function locate_template($template_name) {
        $template_path = false;
        $possible_paths = [
            // Theme specific template
            get_stylesheet_directory() . '/zoho-distributor-portal/' . $template_name,
            // Parent theme template
            get_template_directory() . '/zoho-distributor-portal/' . $template_name,
            // Plugin default template
            ZSDP_PLUGIN_DIR . 'templates/' . $template_name
        ];

        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $template_path = $path;
                break;
            }
        }

        return apply_filters('zsdp_locate_template', $template_path, $template_name);
    }
}