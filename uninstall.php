<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$wpftab_options = [
    'wpftab_field_map',
    'wpftab_utm_map',
    'wpftab_api_url',
    'wpftab_api_key',
    'wpftab_custom_fields',
    'wpftab_questions_answers',
];

foreach ($wpftab_options as $option_name) {
    delete_option($option_name);
    delete_site_option($option_name);
}

$debug_file = plugin_dir_path(__FILE__) . 'debug-cf7.txt';
if (file_exists($debug_file)) {
    @unlink($debug_file);
}
