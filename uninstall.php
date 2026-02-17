<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$wpftab_options = [
    'wpftab_field_map',
    'wpftab_utm_map',
    'wpftab_api_url',
    'wpftab_api_key',
    'wpftab_debug_log_only',
    'wpftab_last_debug_payload',
    'wpftab_custom_fields',
    'wpftab_questions_answers',
    'wpftab_elementor_field_map',
    'wpftab_elementor_custom_fields',
    'wpftab_elementor_questions_answers',
    'wpftab_elementor_name_field',
    'wpftab_elementor_gdpr_fields',
    'wpftab_elementor_marketing_fields',
    'wpftab_cf7_name_field',
    'wpftab_cf7_gdpr_fields',
    'wpftab_cf7_marketing_fields',
];

foreach ($wpftab_options as $option_name) {
    delete_option($option_name);
    delete_site_option($option_name);
}

