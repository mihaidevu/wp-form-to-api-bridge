<?php
if (!defined('ABSPATH')) exit;

function wpftab_elementor_is_available() {
    return defined('ELEMENTOR_PRO_VERSION') || class_exists('ElementorPro\Modules\Forms\Module', false);
}


function wpftab_elementor_decode_data($data) {
    if (is_array($data)) return $data;
    if (!is_string($data) || $data === '') return null;
    $raw = $data;
    $data = stripslashes($data);
    $decoded = json_decode($data, true);
    if (is_array($decoded)) return $decoded;
    if (function_exists('is_serialized') && is_serialized($raw)) {
        $unser = @unserialize($raw);
        if (is_array($unser)) return $unser;
        if (is_string($unser)) {
            $decoded = json_decode($unser, true);
            if (is_array($decoded)) return $decoded;
        }
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return $decoded;
    return null;
}

function wpftab_get_elementor_forms() {
    $forms = [];
    $seen_keys = [];
    $seen_templates = [];
    $post_types = array_unique(array_merge(
        get_post_types(['public' => true], 'names'),
        get_post_types(['show_ui' => true], 'names')
    ));
    if (post_type_exists('elementor_library')) $post_types[] = 'elementor_library';
    $posts = get_posts([
        'post_type'      => $post_types,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => [
            [
                'key'     => '_elementor_data',
                'compare' => 'EXISTS',
            ],
        ],
    ]);
    foreach ($posts as $post) {
        $data = get_post_meta($post->ID, '_elementor_data', true);
        $decoded = wpftab_elementor_decode_data($data);
        if (!is_array($decoded)) {
            continue;
        }
        $elements = wpftab_elementor_get_root_elements($decoded);
        $context = $post->post_type === 'elementor_library' ? __('Template', 'wpftab') : ($post->post_type === 'page' ? __('Page', 'wpftab') : __('Post', 'wpftab'));
        wpftab_collect_elementor_forms($elements, $post->ID, $post->post_title, $context, $forms, $seen_keys, $seen_templates);
    }
    return array_values($forms);
}

function wpftab_elementor_get_root_elements($decoded) {
    if (isset($decoded['content']) && is_array($decoded['content'])) return $decoded['content'];
    if (isset($decoded['elements']) && is_array($decoded['elements'])) return $decoded['elements'];
    return is_array($decoded) ? $decoded : [];
}

function wpftab_collect_elementor_forms($elements, $post_id, $post_title, $context, &$forms, &$seen_keys, &$seen_templates) {
    if (!is_array($elements)) return;
    foreach ($elements as $el) {
        if (!is_array($el)) continue;
        if (isset($el['widgetType']) && $el['widgetType'] === 'form' && isset($el['settings']['form_name'])) {
            $form_name = $el['settings']['form_name'];
            $widget_id = isset($el['id']) ? (string) $el['id'] : '';
            $form_key = $post_id . '|' . $form_name . ($widget_id !== '' ? '|' . $widget_id : '');
            if (!isset($seen_keys[$form_key])) {
                $seen_keys[$form_key] = true;
                $forms[$form_key] = [
                    'key'     => $form_key,
                    'label'   => $form_name . ($widget_id !== '' ? ' (ID: ' . $widget_id . ')' : '') . ' (' . $context . ': ' . $post_title . ')',
                    'post_id' => $post_id,
                ];
            }
        }
        if (isset($el['widgetType']) && $el['widgetType'] === 'template' && !empty($el['settings']['template_id'])) {
            $template_id = (int) $el['settings']['template_id'];
            if ($template_id > 0 && !isset($seen_templates[$template_id])) {
                $seen_templates[$template_id] = true;
                $tdata = get_post_meta($template_id, '_elementor_data', true);
                $tdecoded = wpftab_elementor_decode_data($tdata);
                if (is_array($tdecoded)) {
                    $telements = wpftab_elementor_get_root_elements($tdecoded);
                    $tcontext = __('Template', 'wpftab');
                    wpftab_collect_elementor_forms($telements, $template_id, get_the_title($template_id), $tcontext, $forms, $seen_keys, $seen_templates);
                }
            }
        }
        if (!empty($el['elements'])) {
            wpftab_collect_elementor_forms($el['elements'], $post_id, $post_title, $context, $forms, $seen_keys, $seen_templates);
        }
    }
}

function wpftab_get_elementor_form_fields($form_key) {
    $form_name = $form_key;
    $post_id = 0;
    $widget_id = '';
    if (strpos($form_key, '|') !== false) {
        $parts = explode('|', $form_key);
        if (count($parts) >= 2) {
            $post_id = (int) $parts[0];
            $form_name = $parts[1];
            if (isset($parts[2])) $widget_id = (string) $parts[2];
        }
    }
    if ($post_id > 0) {
        $data = get_post_meta($post_id, '_elementor_data', true);
        $decoded = wpftab_elementor_decode_data($data);
        if (!is_array($decoded)) return [];
        $elements = wpftab_elementor_get_root_elements($decoded);
        $fields = [];
        $seen_templates = [];
        wpftab_find_elementor_form_fields($elements, $form_name, $fields, $seen_templates, $widget_id);
        return $fields;
    }
    $post_types = array_unique(array_merge(
        get_post_types(['public' => true], 'names'),
        get_post_types(['show_ui' => true], 'names')
    ));
    if (post_type_exists('elementor_library')) $post_types[] = 'elementor_library';
    $posts = get_posts([
        'post_type'      => $post_types,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => [
            [
                'key'     => '_elementor_data',
                'compare' => 'EXISTS',
            ],
        ],
    ]);
    foreach ($posts as $post) {
        $data = get_post_meta($post->ID, '_elementor_data', true);
        $decoded = wpftab_elementor_decode_data($data);
        if (!is_array($decoded)) continue;
        $elements = wpftab_elementor_get_root_elements($decoded);
        $fields = [];
        $seen_templates = [];
        wpftab_find_elementor_form_fields($elements, $form_name, $fields, $seen_templates, $widget_id);
        if (!empty($fields)) return $fields;
    }
    return [];
}

function wpftab_find_elementor_form_fields($elements, $form_name, &$fields, &$seen_templates, $widget_id = '') {
    if (!is_array($elements)) return;
    foreach ($elements as $el) {
        if (!is_array($el)) continue;
        if (isset($el['widgetType'], $el['settings']) && $el['widgetType'] === 'form' && isset($el['settings']['form_name']) && $el['settings']['form_name'] === $form_name) {
            if ($widget_id !== '' && (!isset($el['id']) || (string) $el['id'] !== $widget_id)) {
            } else {
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
                if ($widget_id !== '') return;
            }
        }
        if (isset($el['widgetType']) && $el['widgetType'] === 'template' && !empty($el['settings']['template_id'])) {
            $template_id = (int) $el['settings']['template_id'];
            if ($template_id > 0 && !isset($seen_templates[$template_id])) {
                $seen_templates[$template_id] = true;
                $tdata = get_post_meta($template_id, '_elementor_data', true);
                $tdecoded = wpftab_elementor_decode_data($tdata);
                if (is_array($tdecoded)) {
                    $telements = wpftab_elementor_get_root_elements($tdecoded);
                    wpftab_find_elementor_form_fields($telements, $form_name, $fields, $seen_templates, $widget_id);
                }
            }
        }
        if (!empty($el['elements'])) {
            wpftab_find_elementor_form_fields($el['elements'], $form_name, $fields, $seen_templates, $widget_id);
        }
    }
}

add_action('elementor_pro/forms/new_record', function($record, $handler) {
    try {
        if (!is_object($record) || !method_exists($record, 'get_form_settings')) {
            return;
        }
        $form_name = $record->get_form_settings('form_name');
        if ($form_name === null || $form_name === '') {
            return;
        }
        $raw_fields = $record->get('fields');
        if (!is_array($raw_fields)) $raw_fields = [];
        $posted_data = [];
        foreach ($raw_fields as $id => $field) {
            if (is_array($field) && array_key_exists('value', $field)) {
                $posted_data[$id] = $field['value'];
            }
        }
        if (method_exists($record, 'get') && is_array($record->get('meta'))) {
            $meta = $record->get('meta');
            foreach (['post_id','page_id','form_id','page_url'] as $mk) {
                if (isset($meta[$mk])) {
                    $val = $meta[$mk];
                    if (is_array($val) && isset($val[0])) $val = $val[0];
                }
            }
        }
        $cookie_data = [];
        if (!empty($_COOKIE['referrer_source'])) {
            $decoded = json_decode(urldecode(stripslashes((string)$_COOKIE['referrer_source'])), true);
            if (is_array($decoded)) $cookie_data = $decoded;
        }
        $cookie_data = wpftab_expand_utm_fields($cookie_data);
        $field_map = get_option('wpftab_elementor_field_map', []);
        $utm_map = get_option('wpftab_utm_map', []);
        if (!is_array($field_map)) $field_map = [];
        if (!is_array($utm_map)) $utm_map = [];
        $post_id = 0;
        $form_widget_id = '';
        if (isset($_POST['post_id']) && (int) $_POST['post_id'] > 0) {
            $post_id = (int) $_POST['post_id'];
        }
        if (isset($_POST['form_id']) && $_POST['form_id'] !== '') {
            $form_widget_id = (string) $_POST['form_id'];
        }
        if ($post_id === 0 && isset($posted_data['post_id']) && (int) $posted_data['post_id'] > 0) {
            $post_id = (int) $posted_data['post_id'];
        }
        if ($post_id === 0 && method_exists($record, 'get') && is_array($record->get('meta'))) {
            $meta = $record->get('meta');
            if (isset($meta['post_id'])) {
                $pid = $meta['post_id'];
                if (is_array($pid) && isset($pid[0])) $pid = $pid[0];
                if ((int) $pid > 0) $post_id = (int) $pid;
            }
            if ($post_id === 0 && isset($meta['page_url'])) {
                $url = $meta['page_url'];
                if (is_array($url)) {
                    foreach ($url as $candidate) {
                        if (is_string($candidate)) { $url = $candidate; break; }
                    }
                }
                if (is_string($url)) {
                    if (preg_match('/\?p=(\d+)/', $url, $m)) $post_id = (int) $m[1];
                    elseif (preg_match('#/post/(\d+)/#', $url, $m)) $post_id = (int) $m[1];
                }
            }
        }
        if ($form_widget_id === '' && isset($posted_data['form_id']) && $posted_data['form_id'] !== '') {
            $form_widget_id = (string) $posted_data['form_id'];
        } elseif ($form_widget_id === '' && method_exists($record, 'get') && is_array($record->get('meta'))) {
            $meta = $record->get('meta');
            if (isset($meta['form_id'])) {
                $fid = $meta['form_id'];
                if (is_array($fid) && isset($fid[0])) $fid = $fid[0];
                if ($fid !== '') $form_widget_id = (string) $fid;
            }
        }
        $form_key_with_post = $post_id > 0 ? $post_id . '|' . $form_name : $form_name;
        if ($form_widget_id !== '') {
            $form_key_with_post .= '|' . $form_widget_id;
        }
        $field_map_used = $field_map[$form_key_with_post] ?? $field_map[$post_id . '|' . $form_name] ?? $field_map[$form_name] ?? [];
        $name_fields = get_option('wpftab_elementor_name_field', []);
        if (!is_array($name_fields)) $name_fields = [];
        $name_field_used = $name_fields[$form_key_with_post] ?? $name_fields[$post_id . '|' . $form_name] ?? $name_fields[$form_name] ?? '';
        if (!is_string($name_field_used)) $name_field_used = '';
        $gdpr_fields = get_option('wpftab_elementor_gdpr_fields', []);
        if (!is_array($gdpr_fields)) $gdpr_fields = [];
        $gdpr_fields_used = $gdpr_fields[$form_key_with_post] ?? $gdpr_fields[$post_id . '|' . $form_name] ?? $gdpr_fields[$form_name] ?? [];
        if (!is_array($gdpr_fields_used)) $gdpr_fields_used = [];
        $marketing_fields = get_option('wpftab_elementor_marketing_fields', []);
        if (!is_array($marketing_fields)) $marketing_fields = [];
        $marketing_fields_used = $marketing_fields[$form_key_with_post] ?? $marketing_fields[$post_id . '|' . $form_name] ?? $marketing_fields[$form_name] ?? [];
        if (!is_array($marketing_fields_used)) $marketing_fields_used = [];
        $data = [];
        $gdpr_yes = false;
        $marketing_yes = false;
        foreach ($posted_data as $key => $value) {
            if ($key === '') continue;
            if ($name_field_used !== '' && $key === $name_field_used) {
                $full = is_array($value) ? (string) reset($value) : (string) $value;
                if (trim($full) !== '') {
                    $name_parts = wpftab_split_full_name($full);
                    $data['firstName'] = sanitize_text_field($name_parts['firstName']);
                    $data['lastName'] = sanitize_text_field($name_parts['lastName']);
                }
                continue;
            }
            if (in_array($key, $gdpr_fields_used, true)) {
                if (wpftab_is_checked_value($value)) {
                    $gdpr_yes = true;
                }
                continue;
            }
            if (in_array($key, $marketing_fields_used, true)) {
                if (wpftab_is_checked_value($value)) {
                    $marketing_yes = true;
                }
                continue;
            }
            $mapped = isset($field_map_used[$key]) ? (string) $field_map_used[$key] : '';
            if ($mapped === '') continue;
            $data[$mapped] = is_array($value) ? array_values($value) : (string) $value;
        }
        if (!empty($gdpr_fields_used)) {
            $data['gdprConsent'] = $gdpr_yes ? 'YES' : 'NO';
        }
        if (!empty($marketing_fields_used)) {
            $data['marketingConsent'] = $marketing_yes ? 'YES' : 'NO';
        }
        foreach ($utm_map as $cookie_key => $api_key_name) {
            if ($api_key_name === '') continue;
            $raw = $cookie_data[$cookie_key] ?? '';
            if ($raw === null) {
                $data[$api_key_name] = null;
                continue;
            }
            if (is_int($raw) || is_float($raw)) {
                $data[$api_key_name] = $raw;
                continue;
            }
            $data[$api_key_name] = sanitize_text_field((string) $raw);
        }
        $api_url = (string) get_option('wpftab_api_url', '');
        $api_key = (string) get_option('wpftab_api_key', '');
        $custom_fields = get_option('wpftab_elementor_custom_fields', []);
        $custom_fields_used = $custom_fields[$form_key_with_post] ?? $custom_fields[$post_id . '|' . $form_name] ?? $custom_fields[$form_name] ?? [];
        if (is_array($custom_fields_used)) {
            foreach ($custom_fields_used as $custom_field) {
                if (isset($custom_field['key'], $custom_field['value']) && $custom_field['key'] !== '' && $custom_field['value'] !== '') {
                    $data[sanitize_text_field($custom_field['key'])] = sanitize_text_field($custom_field['value']);
                }
            }
        }
        $questions_answers_config = get_option('wpftab_elementor_questions_answers', []);
        $questions_answers_used = $questions_answers_config[$form_key_with_post] ?? $questions_answers_config[$post_id . '|' . $form_name] ?? $questions_answers_config[$form_name] ?? [];
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
        if ($api_url === '') {
            return;
        }
        $headers = [
            'Content-Type' => 'application/json',
            'x-api-key'    => $api_key,
        ];
        if (wpftab_debug_log_payload($api_url, $headers, $data)) {
            return;
        }
        wp_remote_post($api_url, [
            'headers' => $headers,
            'body'    => json_encode($data),
            'timeout' => 5,
            'blocking' => false,
            'sslverify' => true,
        ]);
    } catch (Exception $e) {
    } catch (Error $e) {
    }
}, 10, 2);
