<?php
/**
 * Plugin Name: WP Form to API Bridge
 * Description: Sends CF7/other form submissions to an external API using UTM/traffic cookie. Full debug included.
 * Version: 1.1.0
 * Author: You
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'admin/global-settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/form-mapping.php';

add_action('admin_menu', function() {
    add_menu_page(
        'WP Form to API Bridge',        
        'WP Form to API Bridge',        
        'manage_options',               
        'wpftab_global_settings',       
        'wpftab_render_global_settings',
        'dashicons-admin-generic',      
        60                              
    );

    add_submenu_page(
        'wpftab_global_settings',
        'Form Mapping',
        'Form Mapping',
        'manage_options',
        'wpftab_form_mapping',
        'wpftab_render_form_mapping'
    );
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_wpftab_global_settings' && $hook !== 'wp-form-to-api-bridge_page_wpftab_form_mapping') return;

    wp_enqueue_style(
        'wpftab-admin-style',
        plugin_dir_url(__FILE__) . 'assets/admin-style.css',
        [],
        '1.0'
    );
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'wpftab-traffic-cookie',
        plugin_dir_url(__FILE__) . 'assets/traffic-cookie.js',
        [],
        '1.0',
        false
    );
});

foreach (glob(plugin_dir_path(__FILE__) . 'integrations/*.php') as $file) {
    require_once $file;
}
