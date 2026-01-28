<?php
if (!defined('ABSPATH')) exit;

if (!defined('WPCF7_VERSION')) return;

function wpftab_get_cf7_forms() {
    $forms = get_posts([
        'post_type' => 'wpcf7_contact_form',
        'posts_per_page' => -1
    ]);
    return $forms ?: [];
}

function wpftab_get_cf7_form_fields($form_id) {
    if (!class_exists('WPCF7_ContactForm')) {
        return [];
    }
    
    $contact_form = WPCF7_ContactForm::get_instance($form_id);
    if (!$contact_form) {
        return [];
    }
    
    $form_tags = $contact_form->scan_form_tags();
    $fields = [];
    
    foreach ($form_tags as $tag) {
        if (empty($tag->name) || $tag->name === '_wpcf7' || $tag->name === '_wpcf7_version' || $tag->name === '_wpcf7_locale' || $tag->name === '_wpcf7_unit_tag' || $tag->name === '_wpcf7_container_post') {
            continue;
        }
        $fields[$tag->name] = [
            'name' => $tag->name,
            'type' => $tag->type,
            'label' => $tag->labels ? (is_array($tag->labels) ? implode(', ', $tag->labels) : $tag->labels) : $tag->name
        ];
    }
    
    return $fields;
}

add_action('wpcf7_mail_sent', function($contact_form) {
    try {
        if (!is_object($contact_form)) {
            return;
        }
        
        $submission = WPCF7_Submission::get_instance();
        if (!$submission || !is_object($submission)) {
            return;
        }

        $posted_data = $submission->get_posted_data();
        if (!is_array($posted_data)) {
            $posted_data = [];
        }

        $cookie_data = [];
        if (!empty($_COOKIE['referrer_source'])) {
            $decoded = json_decode(urldecode(stripslashes((string)$_COOKIE['referrer_source'])), true);
            if (is_array($decoded)) {
                $cookie_data = $decoded;
            }
        }

        $field_map = get_option('wpftab_field_map', []);
        if (!is_array($field_map)) {
            $field_map = [];
        }
        
        $utm_map = get_option('wpftab_utm_map', []);
        if (!is_array($utm_map)) {
            $utm_map = [];
        }

        $form_id = (int) $contact_form->id();
        if ($form_id <= 0) {
            return;
        }

        $data = [];

        foreach ($posted_data as $key => $value) {
            if (empty($key)) continue;
            
            $mapped = isset($field_map[$form_id][$key]) && !empty($field_map[$form_id][$key]) 
                ? $field_map[$form_id][$key] 
                : $key;
            
            $data[$mapped] = is_array($value) ? array_values($value) : (string)$value;
        }

        foreach ($utm_map as $cookie_key => $api_key_name) {
            if (empty($api_key_name)) continue;
            
            $data[$api_key_name] = isset($cookie_data[$cookie_key]) 
                ? (string)$cookie_data[$cookie_key] 
                : '';
        }

        $api_url = (string) get_option('wpftab_api_url', '');
        if (empty($api_url)) {
            return;
        }

        $api_key = (string) get_option('wpftab_api_key', '');

        $custom_fields = get_option('wpftab_custom_fields', []);
        if (is_array($custom_fields) && isset($custom_fields[$form_id])) {
            foreach ($custom_fields[$form_id] as $custom_field) {
                if (isset($custom_field['key']) && isset($custom_field['value']) && 
                    !empty($custom_field['key']) && !empty($custom_field['value'])) {
                    $data[sanitize_text_field($custom_field['key'])] = sanitize_text_field($custom_field['value']);
                }
            }
        }

        try {
            $debug_file = plugin_dir_path(__FILE__) . '../debug-cf7.txt';
            $json_payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            $debug_content = "\n--- FINAL JSON PAYLOAD (sent to API) ---\n";
            $debug_content .= $json_payload."\n\n";
            
            $debug_content .= "--- REQUEST HEADERS ---\n";
            $debug_content .= "Content-Type: application/json\n";
            $debug_content .= "x-api-key: ".(!empty($api_key) ? substr($api_key, 0, 8).'...'.substr($api_key, -4) : 'NOT SET')."\n\n";
            
            $debug_content .= "--- REQUEST SUMMARY ---\n";
            $debug_content .= "Method: POST\n";
            $debug_content .= "URL: {$api_url}\n";
            $debug_content .= "Body Size: ".strlen($json_payload)." bytes\n";
            $debug_content .= "Total Fields: ".count($data)."\n";
            
            @file_put_contents($debug_file, $debug_content, FILE_APPEND);
        } catch (Exception $e) {
        }

        wp_remote_post($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key
            ],
            'body' => json_encode($data),
            'timeout' => 5,
            'blocking' => false,
            'sslverify' => true
        ]);
        
    } catch (Exception $e) {
        try {
            $debug_file = plugin_dir_path(__FILE__) . '../debug-cf7.txt';
            $error_msg = "\n\n[ERROR] ".date('Y-m-d H:i:s').": ".$e->getMessage()."\n";
            $error_msg .= "File: ".$e->getFile()." Line: ".$e->getLine()."\n";
            @file_put_contents($debug_file, $error_msg, FILE_APPEND);
        } catch (Exception $debug_error) {
        }
        return;
    } catch (Error $e) {
        try {
            $debug_file = plugin_dir_path(__FILE__) . '../debug-cf7.txt';
            $error_msg = "\n\n[FATAL ERROR] ".date('Y-m-d H:i:s').": ".$e->getMessage()."\n";
            $error_msg .= "File: ".$e->getFile()." Line: ".$e->getLine()."\n";
            @file_put_contents($debug_file, $error_msg, FILE_APPEND);
        } catch (Exception $debug_error) {
        }
        return;
    }
});
