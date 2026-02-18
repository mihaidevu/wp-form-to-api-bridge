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
    $js_file_path = plugin_dir_path(__FILE__) . 'assets/kmb-session.js';
    if (file_exists($js_file_path)) {
        $inline_js = file_get_contents($js_file_path);
        wp_register_script('kmb-init', false);
        wp_enqueue_script('kmb-init');
        wp_add_inline_script('kmb-init', $inline_js);
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
    $value = trim((string) $value);
    if ($value === '') {
        return ['id' => '', 'name' => ''];
    }
    $id = '';
    $name = '';
    if (strpos($value, '|') !== false) {
        $parts = array_map('trim', explode('|', $value, 2));
        $p0 = $parts[0] ?? '';
        $p1 = $parts[1] ?? '';
        if (preg_match('/^\d+$/', $p0) && $p1 !== '') {
            $id = $p0;
            $name = $p1;
        } elseif (preg_match('/^\d+$/', $p1) && $p0 !== '') {
            $id = $p1;
            $name = $p0;
        } elseif (preg_match('/^\d+$/', $p0)) {
            $id = $p0;
        } else {
            $name = $p0;
            if ($p1 !== '' && !preg_match('/^\d+$/', $p1)) {
                $name = $p0 . '|' . $p1;
            } elseif (preg_match('/^\d+$/', $p1)) {
                $id = $p1;
            }
        }
    } else {
        if (preg_match('/^\d+$/', $value)) {
            $id = $value;
        } else {
            $name = $value;
        }
    }
    return ['id' => $id, 'name' => $name];
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
 * $context opțional: [ 'form_type' => 'CF7'|'Elementor', 'form_id' => id (CF7), 'form_key' => key (Elementor), 'send_to_api' => bool ]
 */
/**
 * Salvează mereu ultimul formular care a triggeruit (fie a trimis la API, fie doar logat în debug).
 * $context: form_type, form_id sau form_key, send_to_api, sent_to_api (true = s-a trimis efectiv, false = doar logat).
 */
function wpftab_save_last_trigger_info($context) {
    if (!is_array($context)) return;
    $context['timestamp'] = current_time('Y-m-d H:i:s');
    update_option('wpftab_last_trigger_info', $context);
}

function wpftab_debug_log_payload($api_url, $headers, $data, $context = []) {
    if (get_option('wpftab_debug_log_only') !== '1') {
        return false;
    }
    $entry = [
        'timestamp'  => current_time('Y-m-d H:i:s'),
        'api_url'    => $api_url,
        'headers'    => $headers,
        'body'       => $data,
        'debug_info' => is_array($context) ? $context : [],
    ];
    update_option('wpftab_last_debug_payload', json_encode($entry, JSON_UNESCAPED_UNICODE));
    return true;
}

foreach (glob(plugin_dir_path(__FILE__) . 'integrations/*.php') as $file) {
    require_once $file;
}
