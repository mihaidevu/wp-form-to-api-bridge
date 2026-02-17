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
        if (!empty($_COOKIE['kmb_session_data'])) {
            $decoded = json_decode(urldecode(stripslashes((string)$_COOKIE['kmb_session_data'])), true);
            if (is_array($decoded)) {
                $cookie_data = $decoded;
            }
        }
        $cookie_data = wpftab_expand_utm_fields($cookie_data);

        $field_map = get_option('wpftab_field_map', []);
        if (!is_array($field_map)) {
            $field_map = [];
        }
        
        $utm_map = get_option('wpftab_utm_map', []);
        if (!is_array($utm_map)) {
            $utm_map = [];
        }
        $name_fields = get_option('wpftab_cf7_name_field', []);
        if (!is_array($name_fields)) {
            $name_fields = [];
        }
        $gdpr_fields = get_option('wpftab_cf7_gdpr_fields', []);
        if (!is_array($gdpr_fields)) {
            $gdpr_fields = [];
        }
        $marketing_fields = get_option('wpftab_cf7_marketing_fields', []);
        if (!is_array($marketing_fields)) {
            $marketing_fields = [];
        }

        $form_id = (int) $contact_form->id();
        if ($form_id <= 0) {
            return;
        }

        $data = [];

        $gdpr_list = $gdpr_fields[$form_id] ?? [];
        if (!is_array($gdpr_list)) $gdpr_list = [];
        $marketing_list = $marketing_fields[$form_id] ?? [];
        if (!is_array($marketing_list)) $marketing_list = [];
        $gdpr_yes = false;
        $marketing_yes = false;
        foreach ($posted_data as $key => $value) {
            if (empty($key)) continue;
            $name_field = $name_fields[$form_id] ?? '';
            if ($name_field !== '' && $key === $name_field) {
                $full = is_array($value) ? (string) reset($value) : (string) $value;
                if (trim($full) !== '') {
                    $name_parts = wpftab_split_full_name($full);
                    $data['firstName'] = sanitize_text_field($name_parts['firstName']);
                    $data['lastName'] = sanitize_text_field($name_parts['lastName']);
                }
                continue;
            }
            if (in_array($key, $gdpr_list, true)) {
                if (wpftab_is_checked_value($value)) {
                    $gdpr_yes = true;
                }
                continue;
            }
            if (in_array($key, $marketing_list, true)) {
                if (wpftab_is_checked_value($value)) {
                    $marketing_yes = true;
                }
                continue;
            }
            $mapped = isset($field_map[$form_id][$key]) ? (string) $field_map[$form_id][$key] : '';
            if ($mapped === '') continue;
            $data[$mapped] = is_array($value) ? array_values($value) : (string)$value;
        }
        if (!empty($gdpr_list)) {
            $data['gdprConsent'] = $gdpr_yes ? 'YES' : 'NO';
        }
        if (!empty($marketing_list)) {
            $data['marketingConsent'] = $marketing_yes ? 'YES' : 'NO';
        }

        foreach ($utm_map as $cookie_key => $api_key_name) {
            if (empty($api_key_name)) continue;

            $raw_value = $cookie_data[$cookie_key] ?? '';
            if ($raw_value === null) {
                $data[$api_key_name] = null;
                continue;
            }
            if (is_int($raw_value) || is_float($raw_value)) {
                $data[$api_key_name] = $raw_value;
                continue;
            }
            $data[$api_key_name] = sanitize_text_field((string) $raw_value);
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

        $questions_answers_config = get_option('wpftab_questions_answers', []);
        $questionsAndAnswers = [];
        if (is_array($questions_answers_config) && isset($questions_answers_config[$form_id])) {
            foreach ($questions_answers_config[$form_id] as $qa) {
                if (empty($qa['question'])) continue;
                $question = sanitize_text_field($qa['question']);
                $source = isset($qa['source']) && $qa['source'] === 'field' ? 'field' : 'custom';
                if ($source === 'custom') {
                    $answers = [ sanitize_text_field($qa['value'] ?? '') ];
                } else {
                    $field_name = sanitize_text_field($qa['field'] ?? '');
                    if ($field_name === '') continue;
                    $raw = isset($posted_data[$field_name]) ? $posted_data[$field_name] : null;
                    if (is_array($raw)) {
                        $answers = array_values(array_map('strval', $raw));
                    } elseif ($raw !== null && $raw !== '') {
                        $answers = [ (string) $raw ];
                    } else {
                        $answers = [];
                    }
                }
                if (strtolower($question) === 'category') {
                    $answers = array_map(function($answer) {
                        return strtolower((string) $answer);
                    }, $answers);
                }
                $questionsAndAnswers[] = [ 'question' => $question, 'answers' => $answers ];
            }
        }
        if (!empty($questionsAndAnswers)) {
            $data['questionsAndAnswers'] = $questionsAndAnswers;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key
        ];
        if (wpftab_debug_log_payload($api_url, $headers, $data)) {
            return;
        }
        wp_remote_post($api_url, [
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 5,
            'blocking' => false,
            'sslverify' => true
        ]);
        
    } catch (Exception $e) {
        return;
    } catch (Error $e) {
        return;
    }
});
