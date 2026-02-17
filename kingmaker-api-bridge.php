<?php
/**
 * Plugin Name: Kingmaker API Bridge
 * Description: Sends form submissions to Kingmaker API with UTM and traffic cookie support.
 * Version: 1.1.0
 * Author: Whitebox Digital
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'admin/global-settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/form-mapping.php';
require_once plugin_dir_path(__FILE__) . 'admin/form-mapping-elementor.php';

add_action('admin_menu', function() {
    add_menu_page(
        'Kingmaker API Bridge',        
        'Kingmaker API Bridge',        
        'manage_options',               
        'wpftab_global_settings',       
        'wpftab_render_global_settings',
        'dashicons-admin-generic',      
        60                              
    );

    add_submenu_page(
        'wpftab_global_settings',
        'CF7 Form Mapping',
        'CF7 Form Mapping',
        'manage_options',
        'wpftab_form_mapping',
        'wpftab_render_form_mapping'
    );

    add_submenu_page(
        'wpftab_global_settings',
        'Elementor Form Mapping',
        'Elementor Form Mapping',
        'manage_options',
        'wpftab_form_mapping_elementor',
        'wpftab_render_form_mapping_elementor'
    );
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_wpftab_global_settings' && $hook !== 'wpftab_global_settings_page_wpftab_form_mapping' && $hook !== 'wpftab_global_settings_page_wpftab_form_mapping_elementor') return;

    wp_enqueue_style(
        'wpftab-admin-style',
        plugin_dir_url(__FILE__) . 'assets/admin-style.css',
        [],
        '1.0'
    );
});

add_action('wp_enqueue_scripts', function() {
    $js_file_path = plugin_dir_path(__FILE__) . 'assets/traffic-cookie.js';
    if (file_exists($js_file_path)) {
        $inline_js = file_get_contents($js_file_path);
        wp_register_script('kingmaker-api-bridge-inline-handle', false);
        wp_enqueue_script('kingmaker-api-bridge-inline-handle');
        wp_add_inline_script('kingmaker-api-bridge-inline-handle', $inline_js);
    }
});

function wpftab_clean_numeric($value) {
    if ($value === null) return null;
    $value = (string) $value;
    if ($value === '') return null;
    $numeric = preg_replace('/\D+/', '', $value);
    return $numeric !== '' ? (int) $numeric : null;
}

function wpftab_split_id_name($value) {
    $value = (string) $value;
    if ($value === '') {
        return ['id' => '', 'name' => ''];
    }
    if (strpos($value, '|') !== false) {
        $parts = explode('|', $value, 2);
        return ['id' => trim($parts[0]), 'name' => trim($parts[1])];
    }
    return ['id' => trim($value), 'name' => ''];
}

function wpftab_expand_utm_fields($cookie_data) {
    if (!is_array($cookie_data)) return [];
    $expanded = $cookie_data;
    if (array_key_exists('utm_campaign', $expanded)) {
        $campaign = wpftab_split_id_name($expanded['utm_campaign']);
        $expanded['utm_campaign_id'] = wpftab_clean_numeric($campaign['id']);
        $expanded['utm_campaign_name'] = $campaign['name'];
    }
    if (array_key_exists('utm_adgroup', $expanded)) {
        $adgroup = wpftab_split_id_name($expanded['utm_adgroup']);
        $expanded['utm_adgroup_id'] = wpftab_clean_numeric($adgroup['id']);
        $expanded['utm_adgroup_name'] = $adgroup['name'];
    }
    return $expanded;
}

function wpftab_split_full_name($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return ['firstName' => '', 'lastName' => ''];
    }
    $parts = preg_split('/\s+/', $value);
    $first = $parts[0] ?? '';
    $last = '';
    if (count($parts) > 1) {
        $last = trim(implode(' ', array_slice($parts, 1)));
    }
    return ['firstName' => $first, 'lastName' => $last];
}

function wpftab_is_checked_value($value) {
    if (is_array($value)) {
        $value = reset($value);
    }
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null) {
        return false;
    }
    $value = strtolower(trim((string) $value));
    if ($value === '') return false;
    if (in_array($value, ['0', 'no', 'false', 'off'], true)) return false;
    return true;
}

function wpftab_consent_value($value) {
    return wpftab_is_checked_value($value) ? 'YES' : 'NO';
}

/**
 * Dacă debug log-only e activ (checkbox în setări), salvează payload-ul în
 * wpftab_last_debug_payload (suprascris la fiecare submit) și returnează true (nu trimite).
 * Altfel returnează false.
 */
function wpftab_debug_log_payload($api_url, $headers, $data) {
    if (get_option('wpftab_debug_log_only') !== '1') {
        return false;
    }
    $entry = [
        'timestamp' => current_time('Y-m-d H:i:s'),
        'api_url'   => $api_url,
        'headers'   => $headers,
        'body'      => $data,
    ];
    update_option('wpftab_last_debug_payload', json_encode($entry, JSON_UNESCAPED_UNICODE));
    return true;
}

foreach (glob(plugin_dir_path(__FILE__) . 'integrations/*.php') as $file) {
    require_once $file;
}
