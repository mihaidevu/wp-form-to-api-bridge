<?php
if (!defined('ABSPATH')) exit;

function wpftab_render_form_mapping_elementor() {
    if (!function_exists('wpftab_elementor_is_available') || !wpftab_elementor_is_available()) {
        echo '<div class="wrap"><div class="wpftab-admin-container"><h2>Elementor Form Mapping</h2>';
        echo '<p>Elementor Pro (Form widget) nu este activ. Activează Elementor Pro pentru a folosi maparea formularelor Elementor.</p></div></div>';
        return;
    }
    $forms = wpftab_get_elementor_forms();
    $field_map = get_option('wpftab_elementor_field_map', []);
    $send_to_api = get_option('wpftab_elementor_send_to_api', []);
    if (!is_array($send_to_api)) $send_to_api = [];
    $custom_fields = get_option('wpftab_elementor_custom_fields', []);
    $questions_answers = get_option('wpftab_elementor_questions_answers', []);
    $name_fields = get_option('wpftab_elementor_name_field', []);
    if (!is_array($name_fields)) $name_fields = [];
    $gdpr_fields = get_option('wpftab_elementor_gdpr_fields', []);
    if (!is_array($gdpr_fields)) $gdpr_fields = [];
    $marketing_fields = get_option('wpftab_elementor_marketing_fields', []);
    if (!is_array($marketing_fields)) $marketing_fields = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('wpftab_save_form_map_elementor', 'wpftab_nonce_elementor')) {
        if (!current_user_can('manage_options')) {
            wp_die(__('Cheating?', 'wpftab'));
        }
        $form_key = sanitize_text_field($_POST['form_key'] ?? '');
        if ($form_key !== '') {
            $send_to_api[$form_key] = isset($_POST['wpftab_elementor_send_to_api']) && $_POST['wpftab_elementor_send_to_api'] === '1' ? '1' : '0';
            if ($send_to_api[$form_key] === '0') unset($send_to_api[$form_key]);
            update_option('wpftab_elementor_send_to_api', $send_to_api);
            if (isset($_POST['wpftab_elementor_custom_fields']) && is_array($_POST['wpftab_elementor_custom_fields'])) {
                $custom_fields[$form_key] = [];
                foreach ($_POST['wpftab_elementor_custom_fields'] as $field_data) {
                    if (!is_array($field_data)) continue;
                    $key = sanitize_text_field($field_data['key'] ?? '');
                    $value = sanitize_text_field($field_data['value'] ?? '');
                    if ($key !== '' && $value !== '') {
                        $custom_fields[$form_key][] = ['key' => $key, 'value' => $value];
                    }
                }
            } else {
                unset($custom_fields[$form_key]);
            }
            if (isset($custom_fields[$form_key]) && empty($custom_fields[$form_key])) {
                unset($custom_fields[$form_key]);
            }
            update_option('wpftab_elementor_custom_fields', $custom_fields);
        }
        if ($form_key !== '' && isset($_POST['wpftab_elementor_questions_answers']) && is_array($_POST['wpftab_elementor_questions_answers'])) {
            $questions_answers[$form_key] = [];
            foreach ($_POST['wpftab_elementor_questions_answers'] as $qa) {
                if (!is_array($qa)) continue;
                $question = sanitize_text_field($qa['question'] ?? '');
                if ($question === '') continue;
                $source = isset($qa['source']) && $qa['source'] === 'field' ? 'field' : 'custom';
                $questions_answers[$form_key][] = [
                    'question' => $question,
                    'source'   => $source,
                    'value'    => sanitize_text_field($qa['value'] ?? ''),
                    'field'    => sanitize_text_field($qa['field'] ?? '')
                ];
            }
            update_option('wpftab_elementor_questions_answers', $questions_answers);
        }
        if ($form_key !== '' && function_exists('wpftab_get_elementor_form_fields') && isset($_POST['wpftab_elementor_field_map']) && is_array($_POST['wpftab_elementor_field_map'])) {
            $form_fields = wpftab_get_elementor_form_fields($form_key);
            $current_field_names = array_keys($form_fields);
            $name_fields[$form_key] = '';
            if (isset($_POST['wpftab_elementor_name_field'])) {
                $clean_name = sanitize_text_field($_POST['wpftab_elementor_name_field']);
                if ($clean_name !== '' && $clean_name !== '__none__' && in_array($clean_name, $current_field_names, true)) {
                    $name_fields[$form_key] = $clean_name;
                }
            }
            if ($name_fields[$form_key] === '') unset($name_fields[$form_key]);
            update_option('wpftab_elementor_name_field', $name_fields);
            $gdpr_fields[$form_key] = [];
            if (isset($_POST['wpftab_elementor_gdpr_fields']) && is_array($_POST['wpftab_elementor_gdpr_fields'])) {
                foreach ($_POST['wpftab_elementor_gdpr_fields'] as $key => $enabled) {
                    if ($enabled !== '1') continue;
                    $clean = sanitize_text_field($key);
                    if (in_array($clean, $current_field_names, true)) {
                        $gdpr_fields[$form_key][] = $clean;
                    }
                }
            }
            if (empty($gdpr_fields[$form_key])) unset($gdpr_fields[$form_key]);
            update_option('wpftab_elementor_gdpr_fields', $gdpr_fields);
            $marketing_fields[$form_key] = [];
            if (isset($_POST['wpftab_elementor_marketing_fields']) && is_array($_POST['wpftab_elementor_marketing_fields'])) {
                foreach ($_POST['wpftab_elementor_marketing_fields'] as $key => $enabled) {
                    if ($enabled !== '1') continue;
                    $clean = sanitize_text_field($key);
                    if (in_array($clean, $current_field_names, true)) {
                        $marketing_fields[$form_key][] = $clean;
                    }
                }
            }
            if (empty($marketing_fields[$form_key])) unset($marketing_fields[$form_key]);
            update_option('wpftab_elementor_marketing_fields', $marketing_fields);
            if (!isset($field_map[$form_key])) $field_map[$form_key] = [];
            foreach ($_POST['wpftab_elementor_field_map'] as $key => $value) {
                $clean_key = sanitize_text_field($key);
                $clean_value = sanitize_text_field($value);
                $enabled = isset($_POST['wpftab_elementor_field_map_enabled'][$clean_key]) && $_POST['wpftab_elementor_field_map_enabled'][$clean_key] === '1';
                if (!$enabled || $clean_value === '__DELETE__') {
                    unset($field_map[$form_key][$clean_key]);
                } elseif ($clean_value !== '') {
                    $field_map[$form_key][$clean_key] = $clean_value;
                } else {
                    $field_map[$form_key][$clean_key] = $clean_key;
                }
            }
            foreach ($field_map[$form_key] as $mapped_field => $mapped_value) {
                if (!in_array($mapped_field, $current_field_names)) {
                    unset($field_map[$form_key][$mapped_field]);
                }
            }
            if (empty($field_map[$form_key])) unset($field_map[$form_key]);
            update_option('wpftab_elementor_field_map', $field_map);
            echo '<div class="notice notice-success"><p>Elementor form mapping saved! Old mappings for removed fields have been automatically cleaned.</p></div>';
        }
    }

    $existing_form_keys = array_column($forms, 'key');
    $selected_form_key = isset($_GET['form_key']) ? sanitize_text_field($_GET['form_key']) : (isset($_POST['form_key']) ? sanitize_text_field($_POST['form_key']) : '');
    if ($selected_form_key !== '' && !in_array($selected_form_key, $existing_form_keys)) {
        $existing_form_keys[] = $selected_form_key;
    }
    foreach ($field_map as $saved_key => $m) {
        if (!in_array($saved_key, $existing_form_keys)) unset($field_map[$saved_key]);
    }
    if (count($field_map) !== count(get_option('wpftab_elementor_field_map', []))) update_option('wpftab_elementor_field_map', $field_map);
    foreach ($custom_fields as $saved_key => $v) {
        if (!in_array($saved_key, $existing_form_keys)) unset($custom_fields[$saved_key]);
    }
    if (count($custom_fields) !== count(get_option('wpftab_elementor_custom_fields', []))) update_option('wpftab_elementor_custom_fields', $custom_fields);
    foreach ($questions_answers as $saved_key => $v) {
        if (!in_array($saved_key, $existing_form_keys)) unset($questions_answers[$saved_key]);
    }
    if (count($questions_answers) !== count(get_option('wpftab_elementor_questions_answers', []))) update_option('wpftab_elementor_questions_answers', $questions_answers);
    foreach ($name_fields as $saved_key => $v) {
        if (!in_array($saved_key, $existing_form_keys)) unset($name_fields[$saved_key]);
    }
    if (count($name_fields) !== count(get_option('wpftab_elementor_name_field', []))) update_option('wpftab_elementor_name_field', $name_fields);
    foreach ($gdpr_fields as $saved_key => $v) {
        if (!in_array($saved_key, $existing_form_keys)) unset($gdpr_fields[$saved_key]);
    }
    if (count($gdpr_fields) !== count(get_option('wpftab_elementor_gdpr_fields', []))) update_option('wpftab_elementor_gdpr_fields', $gdpr_fields);
    foreach ($marketing_fields as $saved_key => $v) {
        if (!in_array($saved_key, $existing_form_keys)) unset($marketing_fields[$saved_key]);
    }
    if (count($marketing_fields) !== count(get_option('wpftab_elementor_marketing_fields', []))) update_option('wpftab_elementor_marketing_fields', $marketing_fields);
    foreach ($send_to_api as $saved_key => $v) {
        if (!in_array($saved_key, $existing_form_keys)) unset($send_to_api[$saved_key]);
    }
    if (count($send_to_api) !== count(get_option('wpftab_elementor_send_to_api', []))) update_option('wpftab_elementor_send_to_api', $send_to_api);

    if ($selected_form_key === '' && !empty($forms)) $selected_form_key = $forms[0]['key'];
    $form_fields = [];
    $form_custom_fields = [];
    $form_questions_answers = [];
    $form_name_field = '';
    $form_gdpr_fields = [];
    $form_marketing_fields = [];
    if ($selected_form_key !== '' && function_exists('wpftab_get_elementor_form_fields')) {
        $form_fields = wpftab_get_elementor_form_fields($selected_form_key);
        $form_name_field = isset($name_fields[$selected_form_key]) ? (string) $name_fields[$selected_form_key] : '';
        $form_gdpr_fields = isset($gdpr_fields[$selected_form_key]) && is_array($gdpr_fields[$selected_form_key]) ? $gdpr_fields[$selected_form_key] : [];
        $form_marketing_fields = isset($marketing_fields[$selected_form_key]) && is_array($marketing_fields[$selected_form_key]) ? $marketing_fields[$selected_form_key] : [];
        if (isset($custom_fields[$selected_form_key]) && is_array($custom_fields[$selected_form_key])) {
            $form_custom_fields = array_values(array_filter($custom_fields[$selected_form_key], function($f) {
                return isset($f['key'], $f['value']) && $f['key'] !== '' && $f['value'] !== '';
            }));
            if (count($form_custom_fields) !== count($custom_fields[$selected_form_key])) {
                if (empty($form_custom_fields)) {
                    unset($custom_fields[$selected_form_key]);
                } else {
                    $custom_fields[$selected_form_key] = $form_custom_fields;
                }
                update_option('wpftab_elementor_custom_fields', $custom_fields);
            }
        } else {
            $form_custom_fields = [];
        }
        if (isset($questions_answers[$selected_form_key]) && is_array($questions_answers[$selected_form_key])) {
            $current_field_names = array_keys($form_fields);
            $form_questions_answers = array_values(array_filter($questions_answers[$selected_form_key], function($qa) use ($current_field_names) {
                if (empty($qa['question'])) return false;
                if (isset($qa['source']) && $qa['source'] === 'field' && !empty($qa['field'])) {
                    if (!in_array($qa['field'], $current_field_names)) return false;
                }
                return true;
            }));
            if (count($form_questions_answers) !== count($questions_answers[$selected_form_key])) {
                $questions_answers[$selected_form_key] = $form_questions_answers;
                if (empty($form_questions_answers)) unset($questions_answers[$selected_form_key]);
                update_option('wpftab_elementor_questions_answers', $questions_answers);
            }
        }
    }
    ?>
    <div class="wpftab-admin-container">
        <h2>Elementor Form Mapping</h2>
        <?php if (empty($forms)): ?>
            <p>Nu s-au găsit formulare Elementor. Adaugă un widget Form într-o pagină construită cu Elementor.</p>
        <?php endif; ?>
        <?php if (!empty($forms)): ?>
            <div class="wpftab-field-group">
                <label for="form_key">Select Elementor Form</label>
                <select name="form_key" id="form_key" onchange="window.location.href='?page=wpftab_form_mapping_elementor&form_key='+encodeURIComponent(this.value)">
                    <option value="">-- Select Form --</option>
                    <?php foreach ($forms as $f): ?>
                        <option value="<?php echo esc_attr($f['key']); ?>" <?php selected($selected_form_key, $f['key']); ?>><?php echo esc_html($f['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($selected_form_key !== ''): ?>
        <form method="post" id="wpftab-form-mapping-elementor-form">
            <?php wp_nonce_field('wpftab_save_form_map_elementor', 'wpftab_nonce_elementor'); ?>
            <input type="hidden" name="form_key" value="<?php echo esc_attr($selected_form_key); ?>">
            <p style="margin: 12px 0;">
                <label>
                    <input type="checkbox" name="wpftab_elementor_send_to_api" value="1" <?php checked(!empty($send_to_api[$selected_form_key])); ?>>
                    <strong>Trimite la API</strong> – când e bifat, submit-urile acestui formular sunt trimise la Kingmaker API. Dacă nu e bifat, formularul nu se trimite.
                </label>
            </p>
            <p><strong>Form key:</strong> <code><?php echo esc_html($selected_form_key); ?></code></p>
            <?php if (!empty($form_fields)): ?>
                <h3>Field Mapping</h3>
                <p>Bifează câmpurile pe care vrei să le trimiți la API. Dacă bifezi și lași gol, se trimite cu numele original. Dacă completezi, se trimite cu numele din API Field Name.</p>
                <p>
                    <label>
                        <input type="radio" name="wpftab_elementor_name_field" value="__none__" <?php checked($form_name_field === '', true); ?>>
                        Fără split nume
                    </label>
                </p>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th style="width:6%;">Map?</th><th>Elementor Field</th><th>Field Type</th><th>API Field Name</th><th style="width:12%;">Full name?</th><th style="width:10%;">GDPR?</th><th style="width:12%;">Marketing?</th></tr></thead>
                    <tbody>
                        <?php foreach ($form_fields as $field_name => $field_info): ?>
                            <?php $mapped_value = $field_map[$selected_form_key][$field_name] ?? ''; ?>
                            <?php $is_mapped = $mapped_value !== ''; ?>
                            <?php $is_name = $form_name_field === $field_name; ?>
                            <?php $is_gdpr = in_array($field_name, $form_gdpr_fields, true); ?>
                            <?php $is_marketing = in_array($field_name, $form_marketing_fields, true); ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="wpftab_elementor_field_map_enabled[<?php echo esc_attr($field_name); ?>]" value="1" <?php checked($is_mapped, true); ?>>
                                </td>
                                <td><strong><?php echo esc_html($field_name); ?></strong></td>
                                <td><?php echo esc_html($field_info['type'] ?? ''); ?></td>
                                <td>
                                    <input type="text" name="wpftab_elementor_field_map[<?php echo esc_attr($field_name); ?>]" value="<?php echo esc_attr($mapped_value); ?>" placeholder="<?php echo esc_attr($field_name); ?>" class="regular-text">
                                </td>
                                <td style="text-align:center;">
                                    <input type="radio" name="wpftab_elementor_name_field" value="<?php echo esc_attr($field_name); ?>" <?php checked($is_name, true); ?>>
                                </td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="wpftab_elementor_gdpr_fields[<?php echo esc_attr($field_name); ?>]" value="1" <?php checked($is_gdpr, true); ?>>
                                </td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="wpftab_elementor_marketing_fields[<?php echo esc_attr($field_name); ?>]" value="1" <?php checked($is_marketing, true); ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 style="margin-top: 30px;">Additional Custom Fields</h3>
                <p>Add custom key-value pairs sent to the API for this form.</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th style="width:40%;">Field Key</th><th style="width:40%;">Field Value</th><th style="width:20%;">Action</th></tr></thead>
                    <tbody id="elementor-custom-fields-tbody">
                        <?php foreach ($form_custom_fields as $index => $field): ?>
                            <tr>
                                <td><input type="text" name="wpftab_elementor_custom_fields[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($field['key'] ?? ''); ?>" placeholder="e.g. form_name" class="regular-text" style="width:100%;"></td>
                                <td><input type="text" name="wpftab_elementor_custom_fields[<?php echo esc_attr($index); ?>][value]" value="<?php echo esc_attr($field['value'] ?? ''); ?>" placeholder="e.g. Contact" class="regular-text" style="width:100%;"></td>
                                <td><button type="button" class="button button-small" onclick="this.closest('tr').remove();">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="elementor-add-custom-field">+ Add Custom Field</button></p>

                <h3 style="margin-top: 30px;">Questions &amp; Answers fields</h3>
                <p>Build <code>questionsAndAnswers</code>: question name + custom value or form field.</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:22%;">Question</th>
                            <th style="width:18%;">Source</th>
                            <th style="width:28%;">Custom value (if Custom)</th>
                            <th style="width:22%;">Form field (if Form field)</th>
                            <th style="width:10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="elementor-questions-answers-tbody">
                        <?php foreach ($form_questions_answers as $idx => $qa): $src = isset($qa['source']) && $qa['source'] === 'field' ? 'field' : 'custom'; ?>
                            <tr>
                                <td><input type="text" name="wpftab_elementor_questions_answers[<?php echo esc_attr($idx); ?>][question]" value="<?php echo esc_attr($qa['question'] ?? ''); ?>" placeholder="e.g. project" class="regular-text" style="width:100%;"></td>
                                <td>
                                    <select name="wpftab_elementor_questions_answers[<?php echo esc_attr($idx); ?>][source]" class="wpftab-qa-source-el" style="width:100%;">
                                        <option value="custom" <?php selected($src, 'custom'); ?>>Custom</option>
                                        <option value="field" <?php selected($src, 'field'); ?>>Form field</option>
                                    </select>
                                </td>
                                <td class="wpftab-qa-value-cell"><input type="text" name="wpftab_elementor_questions_answers[<?php echo esc_attr($idx); ?>][value]" value="<?php echo esc_attr($qa['value'] ?? ''); ?>" placeholder="e.g. PRIV" class="regular-text" style="width:100%;"></td>
                                <td class="wpftab-qa-field-cell">
                                    <select name="wpftab_elementor_questions_answers[<?php echo esc_attr($idx); ?>][field]" class="regular-text" style="width:100%;">
                                        <option value="">-- Select field --</option>
                                        <?php foreach ($form_fields as $fname => $finfo): ?>
                                            <option value="<?php echo esc_attr($fname); ?>" <?php selected($qa['field'] ?? '', $fname); ?>><?php echo esc_html($fname); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><button type="button" class="button button-small wpftab-remove-qa" onclick="this.closest('tr').remove();">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="elementor-add-qa-row">+ Add Questions &amp; Answers row</button></p>

                <p class="submit"><input type="submit" name="submit" class="button button-primary" value="Save Mapping"></p>
            <?php else: ?>
                <div class="notice notice-warning"><p>No fields found for this form in the template. Form key <code><?php echo esc_html($selected_form_key); ?></code> e corect (ex.: Contact Form sau 47|Contact Form). La trimitere, API-ul primește câmpurile din HTML: name, field_a3104ef, field_ba59d0f, field_8bf71d6, message, etc. Poți salva Custom Fields și Questions &amp; Answers mai jos.</p></div>
                <h3 style="margin-top: 30px;">Additional Custom Fields</h3>
                <p>Add custom key-value pairs sent to the API for this form.</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th style="width:40%;">Field Key</th><th style="width:40%;">Field Value</th><th style="width:20%;">Action</th></tr></thead>
                    <tbody id="elementor-custom-fields-tbody-nofields">
                        <?php foreach ($form_custom_fields as $index => $field): ?>
                            <tr>
                                <td><input type="text" name="wpftab_elementor_custom_fields[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($field['key'] ?? ''); ?>" placeholder="e.g. form_name" class="regular-text" style="width:100%;"></td>
                                <td><input type="text" name="wpftab_elementor_custom_fields[<?php echo esc_attr($index); ?>][value]" value="<?php echo esc_attr($field['value'] ?? ''); ?>" placeholder="e.g. Contact" class="regular-text" style="width:100%;"></td>
                                <td><button type="button" class="button button-small" onclick="this.closest('tr').remove();">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="elementor-add-custom-field-nofields">+ Add Custom Field</button></p>
                <h3 style="margin-top: 30px;">Questions &amp; Answers fields</h3>
                <p>Build <code>questionsAndAnswers</code>: question name + custom value or form field (use field IDs from HTML: name, field_a3104ef, field_ba59d0f, field_8bf71d6, message).</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:22%;">Question</th>
                            <th style="width:18%;">Source</th>
                            <th style="width:28%;">Custom value (if Custom)</th>
                            <th style="width:22%;">Form field (if Form field)</th>
                            <th style="width:10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="elementor-questions-answers-tbody-nofields">
                        <?php foreach ($form_questions_answers as $idx => $qa): $src = isset($qa['source']) && $qa['source'] === 'field' ? 'field' : 'custom'; ?>
                            <tr>
                                <td><input type="text" name="wpftab_elementor_questions_answers[<?php echo esc_attr($idx); ?>][question]" value="<?php echo esc_attr($qa['question'] ?? ''); ?>" placeholder="e.g. project" class="regular-text" style="width:100%;"></td>
                                <td>
                                    <select name="wpftab_elementor_questions_answers[<?php echo esc_attr($idx); ?>][source]" class="wpftab-qa-source-el" style="width:100%;">
                                        <option value="custom" <?php selected($src, 'custom'); ?>>Custom</option>
                                        <option value="field" <?php selected($src, 'field'); ?>>Form field</option>
                                    </select>
                                </td>
                                <td class="wpftab-qa-value-cell"><input type="text" name="wpftab_elementor_questions_answers[<?php echo esc_attr($idx); ?>][value]" value="<?php echo esc_attr($qa['value'] ?? ''); ?>" placeholder="e.g. PRIV" class="regular-text" style="width:100%;"></td>
                                <td class="wpftab-qa-field-cell">
                                    <input type="text" name="wpftab_elementor_questions_answers[<?php echo esc_attr($idx); ?>][field]" value="<?php echo esc_attr($qa['field'] ?? ''); ?>" placeholder="e.g. name, field_a3104ef, message" class="regular-text" style="width:100%;" list="el-qa-field-list">
                                </td>
                                <td><button type="button" class="button button-small wpftab-remove-qa" onclick="this.closest('tr').remove();">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <datalist id="el-qa-field-list"><option value="name"><option value="field_a3104ef"><option value="field_ba59d0f"><option value="field_8bf71d6"><option value="message"></datalist>
                <p><button type="button" class="button" id="elementor-add-qa-row-nofields">+ Add Questions &amp; Answers row</button></p>
                <p class="submit"><input type="submit" name="submit" class="button button-primary" value="Save Mapping"></p>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <script>
    (function() {
        var fieldIndex = <?php echo count($form_custom_fields); ?>;
        document.getElementById('elementor-add-custom-field')?.addEventListener('click', function() {
            var tbody = document.getElementById('elementor-custom-fields-tbody');
            var row = document.createElement('tr');
            row.innerHTML = '<td><input type="text" name="wpftab_elementor_custom_fields['+fieldIndex+'][key]" placeholder="e.g. form_name" class="regular-text" style="width:100%;"></td><td><input type="text" name="wpftab_elementor_custom_fields['+fieldIndex+'][value]" placeholder="e.g. Contact" class="regular-text" style="width:100%;"></td><td><button type="button" class="button button-small" onclick="this.closest(\'tr\').remove();">Remove</button></td>';
            tbody.appendChild(row);
            fieldIndex++;
        });
        var formFieldNames = <?php echo json_encode(array_keys($form_fields)); ?>;
        var qaRowIndex = <?php echo count($form_questions_answers); ?>;
        document.querySelectorAll('input[name^="wpftab_elementor_field_map["]').forEach(function(input) {
            input.addEventListener('input', function() {
                var checkbox = input.closest('tr')?.querySelector('input[type="checkbox"][name^="wpftab_elementor_field_map_enabled["]');
                if (!checkbox) return;
                if (input.value.trim() !== '') checkbox.checked = true;
            });
        });
        function applyNameSplitState() {
            var selected = document.querySelector('input[name="wpftab_elementor_name_field"]:checked');
            if (!selected) return;
            var selectedValue = selected.value;
            document.querySelectorAll('input[name^="wpftab_elementor_field_map["]').forEach(function(input) {
                var row = input.closest('tr');
                if (!row) return;
                var fieldName = input.getAttribute('name').replace('wpftab_elementor_field_map[', '').replace(']', '');
                var checkbox = row.querySelector('input[type="checkbox"][name^="wpftab_elementor_field_map_enabled["]');
                if (selectedValue !== '__none__' && fieldName === selectedValue) {
                    input.disabled = true;
                    if (checkbox) checkbox.checked = false;
                    if (checkbox) checkbox.disabled = true;
                } else {
                    input.disabled = false;
                    if (checkbox) checkbox.disabled = false;
                }
            });
        }
        document.querySelectorAll('input[name="wpftab_elementor_name_field"]').forEach(function(radio) {
            radio.addEventListener('change', applyNameSplitState);
        });
        applyNameSplitState();
        function applyQaState(row) {
            var src = row.querySelector('.wpftab-qa-source-el');
            var valueInput = row.querySelector('.wpftab-qa-value-cell input');
            var fieldSelect = row.querySelector('.wpftab-qa-field-cell select');
            if (!src || !valueInput || !fieldSelect) return;
            if (src.value === 'field') { valueInput.disabled = true; valueInput.value = ''; fieldSelect.disabled = false; }
            else { valueInput.disabled = false; fieldSelect.disabled = true; fieldSelect.value = ''; }
        }
        document.getElementById('elementor-questions-answers-tbody')?.addEventListener('change', function(e) {
            if (e.target.classList.contains('wpftab-qa-source-el')) applyQaState(e.target.closest('tr'));
        });
        [].forEach.call(document.querySelectorAll('#elementor-questions-answers-tbody tr'), applyQaState);
        document.getElementById('elementor-add-qa-row')?.addEventListener('click', function() {
            var tbody = document.getElementById('elementor-questions-answers-tbody');
            var fieldOpts = formFieldNames.map(function(n){ return '<option value="'+n.replace(/"/g,'&quot;')+'">'+n.replace(/</g,'&lt;')+'</option>'; }).join('');
            var row = document.createElement('tr');
            row.innerHTML = '<td><input type="text" name="wpftab_elementor_questions_answers['+qaRowIndex+'][question]" placeholder="e.g. project" class="regular-text" style="width:100%;"></td><td><select name="wpftab_elementor_questions_answers['+qaRowIndex+'][source]" class="wpftab-qa-source-el" style="width:100%;"><option value="custom">Custom</option><option value="field">Form field</option></select></td><td class="wpftab-qa-value-cell"><input type="text" name="wpftab_elementor_questions_answers['+qaRowIndex+'][value]" placeholder="e.g. PRIV" class="regular-text" style="width:100%;"></td><td class="wpftab-qa-field-cell"><select name="wpftab_elementor_questions_answers['+qaRowIndex+'][field]" class="regular-text" style="width:100%;"><option value="">-- Select field --</option>'+fieldOpts+'</select></td><td><button type="button" class="button button-small wpftab-remove-qa" onclick="this.closest(\'tr\').remove();">Remove</button></td>';
            tbody.appendChild(row);
            applyQaState(row);
            qaRowIndex++;
        });
        var noFieldsCustomIdx = <?php echo count($form_custom_fields); ?>;
        document.getElementById('elementor-add-custom-field-nofields')?.addEventListener('click', function() {
            var tbody = document.getElementById('elementor-custom-fields-tbody-nofields');
            if (!tbody) return;
            var row = document.createElement('tr');
            row.innerHTML = '<td><input type="text" name="wpftab_elementor_custom_fields['+noFieldsCustomIdx+'][key]" placeholder="e.g. form_name" class="regular-text" style="width:100%;"></td><td><input type="text" name="wpftab_elementor_custom_fields['+noFieldsCustomIdx+'][value]" placeholder="e.g. Contact" class="regular-text" style="width:100%;"></td><td><button type="button" class="button button-small" onclick="this.closest(\'tr\').remove();">Remove</button></td>';
            tbody.appendChild(row);
            noFieldsCustomIdx++;
        });
        var noFieldsQaIdx = <?php echo count($form_questions_answers); ?>;
        document.getElementById('elementor-add-qa-row-nofields')?.addEventListener('click', function() {
            var tbody = document.getElementById('elementor-questions-answers-tbody-nofields');
            if (!tbody) return;
            var row = document.createElement('tr');
            row.innerHTML = '<td><input type="text" name="wpftab_elementor_questions_answers['+noFieldsQaIdx+'][question]" placeholder="e.g. project" class="regular-text" style="width:100%;"></td><td><select name="wpftab_elementor_questions_answers['+noFieldsQaIdx+'][source]" class="wpftab-qa-source-el" style="width:100%;"><option value="custom">Custom</option><option value="field">Form field</option></select></td><td class="wpftab-qa-value-cell"><input type="text" name="wpftab_elementor_questions_answers['+noFieldsQaIdx+'][value]" placeholder="e.g. PRIV" class="regular-text" style="width:100%;"></td><td class="wpftab-qa-field-cell"><input type="text" name="wpftab_elementor_questions_answers['+noFieldsQaIdx+'][field]" placeholder="e.g. name, field_a3104ef" class="regular-text" style="width:100%;" list="el-qa-field-list"></td><td><button type="button" class="button button-small wpftab-remove-qa" onclick="this.closest(\'tr\').remove();">Remove</button></td>';
            tbody.appendChild(row);
            noFieldsQaIdx++;
        });
    })();
    </script>
    <?php
}
