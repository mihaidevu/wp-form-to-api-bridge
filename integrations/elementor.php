<?php
if (!defined('ABSPATH')) exit;

if (!defined('ELEMENTOR_PRO_VERSION') && !class_exists('ElementorPro\Modules\Forms\Module', false)) {
    return;
}

function wpftab_get_elementor_forms() {
    $forms = [];
    $seen_keys = [];
    $post_types = ['page', 'post'];
    if (post_type_exists('elementor_library')) {
        $post_types[] = 'elementor_library';
    }
    $posts = get_posts([
        'post_type'      => $post_types,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_key'       => '_elementor_data',
    ]);
    foreach ($posts as $post) {
        $data = get_post_meta($post->ID, '_elementor_data', true);
        if (!is_string($data) || $data === '') continue;
        $data = stripslashes($data);
        $decoded = json_decode($data, true);
        if (!is_array($decoded)) continue;
        $elements = wpftab_elementor_get_root_elements($decoded);
        $context = $post->post_type === 'elementor_library' ? __('Template', 'wpftab') : ($post->post_type === 'page' ? __('Page', 'wpftab') : __('Post', 'wpftab'));
        wpftab_collect_elementor_forms($elements, $post->ID, $post->post_title, $context, $forms, $seen_keys);
    }
    return array_values($forms);
}

function wpftab_elementor_get_root_elements($decoded) {
    if (isset($decoded['content']) && is_array($decoded['content'])) return $decoded['content'];
    if (isset($decoded['elements']) && is_array($decoded['elements'])) return $decoded['elements'];
    return is_array($decoded) ? $decoded : [];
}

function wpftab_collect_elementor_forms($elements, $post_id, $post_title, $context, &$forms, &$seen_keys) {
    if (!is_array($elements)) return;
    foreach ($elements as $el) {
        if (!is_array($el)) continue;
        if (isset($el['widgetType']) && $el['widgetType'] === 'form' && isset($el['settings']['form_name'])) {
            $form_name = $el['settings']['form_name'];
            $form_key = $post_id . '|' . $form_name;
            if (!isset($seen_keys[$form_key])) {
                $seen_keys[$form_key] = true;
                $forms[$form_key] = [
                    'key'     => $form_key,
                    'label'   => $form_name . ' (' . $context . ': ' . $post_title . ')',
                    'post_id' => $post_id,
                ];
            }
        }
        if (!empty($el['elements'])) {
            wpftab_collect_elementor_forms($el['elements'], $post_id, $post_title, $context, $forms, $seen_keys);
        }
    }
}

function wpftab_get_elementor_form_fields($form_key) {
    $form_name = $form_key;
    $post_id = 0;
    if (strpos($form_key, '|') !== false) {
        $parts = explode('|', $form_key, 2);
        if (count($parts) === 2) {
            $post_id = (int) $parts[0];
            $form_name = $parts[1];
        }
    }
    if ($post_id > 0) {
        $data = get_post_meta($post_id, '_elementor_data', true);
        if (!is_string($data)) return [];
        $data = stripslashes($data);
        $decoded = json_decode($data, true);
        if (!is_array($decoded)) return [];
        $elements = wpftab_elementor_get_root_elements($decoded);
        $fields = [];
        wpftab_find_elementor_form_fields($elements, $form_name, $fields);
        return $fields;
    }
    $post_types = ['page', 'post'];
    if (post_type_exists('elementor_library')) $post_types[] = 'elementor_library';
    $posts = get_posts([
        'post_type'      => $post_types,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_key'       => '_elementor_data',
    ]);
    foreach ($posts as $post) {
        $data = get_post_meta($post->ID, '_elementor_data', true);
        if (!is_string($data) || $data === '') continue;
        $data = stripslashes($data);
        $decoded = json_decode($data, true);
        if (!is_array($decoded)) continue;
        $elements = wpftab_elementor_get_root_elements($decoded);
        $fields = [];
        wpftab_find_elementor_form_fields($elements, $form_name, $fields);
        if (!empty($fields)) return $fields;
    }
    return [];
}

function wpftab_find_elementor_form_fields($elements, $form_name, &$fields) {
    if (!is_array($elements)) return;
    foreach ($elements as $el) {
        if (!is_array($el)) continue;
        if (isset($el['widgetType'], $el['settings']) && $el['widgetType'] === 'form' && isset($el['settings']['form_name']) && $el['settings']['form_name'] === $form_name) {
            $form_fields = isset($el['settings']['form_fields']) && is_array($el['settings']['form_fields']) ? $el['settings']['form_fields'] : [];
            $index = 0;
            foreach ($form_fields as $f) {
                if (!is_array($f)) continue;
                $id = isset($f['custom_id']) && (string) $f['custom_id'] !== '' ? (string) $f['custom_id']
                    : (isset($f['id']) && (string) $f['id'] !== '' ? (string) $f['id']
                    : (isset($f['field_id']) && (string) $f['field_id'] !== '' ? (string) $f['field_id'] : ''));
                if ($id === '') {
                    $id = (isset($f['field_type']) ? $f['field_type'] : 'field') . '_' . $index;
                }
                $index++;
                $fields[$id] = [
                    'name'  => $id,
                    'type'  => isset($f['field_type']) ? $f['field_type'] : 'text',
                    'label' => isset($f['field_label']) ? $f['field_label'] : $id,
                ];
            }
        }
        if (!empty($el['elements'])) {
            wpftab_find_elementor_form_fields($el['elements'], $form_name, $fields);
        }
    }
}

add_action('elementor_pro/forms/new_record', function($record, $handler) {
    try {
        if (!is_object($record) || !method_exists($record, 'get_form_settings')) return;
        $form_name = $record->get_form_settings('form_name');
        if ($form_name === null || $form_name === '') return;
        $raw_fields = $record->get('fields');
        if (!is_array($raw_fields)) $raw_fields = [];
        $posted_data = [];
        foreach ($raw_fields as $id => $field) {
            if (is_array($field) && array_key_exists('value', $field)) {
                $posted_data[$id] = $field['value'];
            }
        }
        $cookie_data = [];
        if (!empty($_COOKIE['referrer_source'])) {
            $decoded = json_decode(urldecode(stripslashes((string)$_COOKIE['referrer_source'])), true);
            if (is_array($decoded)) $cookie_data = $decoded;
        }
        $field_map = get_option('wpftab_elementor_field_map', []);
        $utm_map = get_option('wpftab_utm_map', []);
        if (!is_array($field_map)) $field_map = [];
        if (!is_array($utm_map)) $utm_map = [];
        $post_id = 0;
        if (isset($posted_data['post_id']) && (int) $posted_data['post_id'] > 0) {
            $post_id = (int) $posted_data['post_id'];
        }
        if ($post_id === 0 && method_exists($record, 'get') && is_array($record->get('meta'))) {
            $meta = $record->get('meta');
            if (isset($meta['post_id']) && (int) $meta['post_id'] > 0) {
                $post_id = (int) $meta['post_id'];
            } elseif (isset($meta['page_url'])) {
                $url = $meta['page_url'];
                if (preg_match('/\?p=(\d+)/', $url, $m)) $post_id = (int) $m[1];
                elseif (preg_match('#/post/(\d+)/#', $url, $m)) $post_id = (int) $m[1];
            }
        }
        $form_key_with_post = $post_id > 0 ? $post_id . '|' . $form_name : $form_name;
        $field_map_used = $field_map[$form_key_with_post] ?? $field_map[$form_name] ?? [];
        $data = [];
        foreach ($posted_data as $key => $value) {
            if ($key === '') continue;
            $mapped = isset($field_map_used[$key]) && $field_map_used[$key] !== ''
                ? $field_map_used[$key]
                : $key;
            $data[$mapped] = is_array($value) ? array_values($value) : (string) $value;
        }
        foreach ($utm_map as $cookie_key => $api_key_name) {
            if ($api_key_name === '') continue;
            $raw = isset($cookie_data[$cookie_key]) ? (string) $cookie_data[$cookie_key] : '';
            $data[$api_key_name] = sanitize_text_field($raw);
        }
        $api_url = (string) get_option('wpftab_api_url', '');
        if ($api_url === '') return;
        $api_key = (string) get_option('wpftab_api_key', '');
        $custom_fields = get_option('wpftab_elementor_custom_fields', []);
        $custom_fields_used = $custom_fields[$form_key_with_post] ?? $custom_fields[$form_name] ?? [];
        if (is_array($custom_fields_used)) {
            foreach ($custom_fields_used as $custom_field) {
                if (isset($custom_field['key'], $custom_field['value']) && $custom_field['key'] !== '' && $custom_field['value'] !== '') {
                    $data[sanitize_text_field($custom_field['key'])] = sanitize_text_field($custom_field['value']);
                }
            }
        }
        $questions_answers_config = get_option('wpftab_elementor_questions_answers', []);
        $questions_answers_used = $questions_answers_config[$form_key_with_post] ?? $questions_answers_config[$form_name] ?? [];
        $questionsAndAnswers = [];
        if (is_array($questions_answers_used)) {
            foreach ($questions_answers_used as $qa) {
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
                $questionsAndAnswers[] = [ 'question' => $question, 'answers' => $answers ];
            }
        }
        if (!empty($questionsAndAnswers)) {
            $data['questionsAndAnswers'] = $questionsAndAnswers;
        }
        wp_remote_post($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key'    => $api_key,
            ],
            'body'    => json_encode($data),
            'timeout' => 5,
            'blocking' => false,
            'sslverify' => true,
        ]);
    } catch (Exception $e) {
    } catch (Error $e) {
    }
}, 10, 2);
