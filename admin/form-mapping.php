<?php
if (!defined('ABSPATH')) exit;

function wpftab_render_form_mapping() {
    require_once plugin_dir_path(__FILE__) . '../integrations/cf7.php';

    $forms = wpftab_get_cf7_forms();
    $field_map = get_option('wpftab_field_map', []);
    $custom_fields = get_option('wpftab_custom_fields', []);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('wpftab_save_form_map', 'wpftab_nonce')) {
        $form_id = intval($_POST['form_id'] ?? 0);
        
        if ($form_id > 0) {
            if (isset($_POST['wpftab_custom_fields']) && is_array($_POST['wpftab_custom_fields'])) {
                $custom_fields[$form_id] = [];
                
                foreach ($_POST['wpftab_custom_fields'] as $field_data) {
                    if (!is_array($field_data)) continue;
                    
                    $key = sanitize_text_field($field_data['key'] ?? '');
                    $value = sanitize_text_field($field_data['value'] ?? '');
                    
                    if (!empty($key) && !empty($value)) {
                        $custom_fields[$form_id][] = [
                            'key' => $key,
                            'value' => $value
                        ];
                    }
                }
            } else {
                unset($custom_fields[$form_id]);
            }
            
            if (isset($custom_fields[$form_id]) && empty($custom_fields[$form_id])) {
                unset($custom_fields[$form_id]);
            }
            
            update_option('wpftab_custom_fields', $custom_fields);
        }
        
        if ($form_id > 0 && isset($_POST['wpftab_field_map']) && is_array($_POST['wpftab_field_map'])) {
            $form_fields = wpftab_get_cf7_form_fields($form_id);
            $current_field_names = array_keys($form_fields);
            
            if (!isset($field_map[$form_id])) {
                $field_map[$form_id] = [];
            }
            
            foreach ($_POST['wpftab_field_map'] as $key => $value) {
                $clean_key = sanitize_text_field($key);
                $clean_value = sanitize_text_field($value);
                
                if ($clean_value === '__DELETE__') {
                    unset($field_map[$form_id][$clean_key]);
                } elseif (!empty($clean_value)) {
                    $field_map[$form_id][$clean_key] = $clean_value;
                } else {
                    unset($field_map[$form_id][$clean_key]);
                }
            }
            
            foreach ($field_map[$form_id] as $mapped_field => $mapped_value) {
                if (!in_array($mapped_field, $current_field_names)) {
                    unset($field_map[$form_id][$mapped_field]);
                }
            }
            
            if (empty($field_map[$form_id])) {
                unset($field_map[$form_id]);
            }
            
            update_option('wpftab_field_map', $field_map);
            echo '<div class="notice notice-success"><p>Form mapping saved! Old mappings for removed fields have been automatically cleaned.</p></div>';
        }
    }
    
    $existing_form_ids = array_map(function($f) { return $f->ID; }, $forms);
    foreach ($field_map as $saved_form_id => $mappings) {
        if (!in_array($saved_form_id, $existing_form_ids)) {
            unset($field_map[$saved_form_id]);
        }
    }
    if (count($field_map) !== count(get_option('wpftab_field_map', []))) {
        update_option('wpftab_field_map', $field_map);
    }

    $selected_form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : (isset($_POST['form_id']) ? intval($_POST['form_id']) : 0);
    $form_fields = [];
    $form_custom_fields = [];
    if ($selected_form_id > 0) {
        $form_fields = wpftab_get_cf7_form_fields($selected_form_id);
        
        if (isset($custom_fields[$selected_form_id]) && is_array($custom_fields[$selected_form_id])) {
            $form_custom_fields = array_filter($custom_fields[$selected_form_id], function($field) {
                return isset($field['key']) && isset($field['value']) && 
                       !empty($field['key']) && !empty($field['value']);
            });
            $form_custom_fields = array_values($form_custom_fields);
            
            if (count($form_custom_fields) !== count($custom_fields[$selected_form_id])) {
                if (empty($form_custom_fields)) {
                    unset($custom_fields[$selected_form_id]);
                } else {
                    $custom_fields[$selected_form_id] = $form_custom_fields;
                }
                update_option('wpftab_custom_fields', $custom_fields);
            }
        } else {
            $form_custom_fields = [];
        }
    }
    ?>
    <div class="wpftab-admin-container">
        <h2>Form Mapping - CF7</h2>
        <form method="post" id="wpftab-form-mapping-form">
            <?php wp_nonce_field('wpftab_save_form_map', 'wpftab_nonce'); ?>
            <div class="wpftab-field-group">
                <label for="form_id">Select CF7 Form</label>
                <select name="form_id" id="form_id" onchange="window.location.href='?page=wpftab_form_mapping&form_id='+this.value">
                    <option value="">-- Select Form --</option>
                    <?php foreach ($forms as $f): ?>
                        <option value="<?php echo esc_attr($f->ID); ?>" <?php selected($selected_form_id, $f->ID); ?>>
                            <?php echo esc_html($f->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selected_form_id > 0): ?>
                <input type="hidden" name="form_id" value="<?php echo esc_attr($selected_form_id); ?>">
            <?php endif; ?>

            <?php if ($selected_form_id > 0 && !empty($form_fields)): ?>
                <h3>Field Mapping</h3>
                <p>Map each CF7 field to your API field name. Leave empty to use the original field name.</p>
                <p><em>Note: Mappings for fields that no longer exist in the form will be automatically removed when you save.</em></p>
                <div id="form-fields-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>CF7 Field Name</th>
                                <th>Field Type</th>
                                <th>API Field Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($form_fields as $field_name => $field_info): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($field_name); ?></strong></td>
                                    <td><?php echo esc_html($field_info['type']); ?></td>
                                    <td>
                                        <input type="text" 
                                               name="wpftab_field_map[<?php echo esc_attr($field_name); ?>]" 
                                               value="<?php echo esc_attr($field_map[$selected_form_id][$field_name] ?? ''); ?>"
                                               placeholder="<?php echo esc_attr($field_name); ?>"
                                               class="regular-text">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 style="margin-top: 30px;">Additional Custom Fields</h3>
                <p>Add custom fields (key -> value) that will be sent to API for this form. For example, you can add a field with the form name.</p>
                <div id="custom-fields-container">
                    <table class="wp-list-table widefat fixed striped" id="custom-fields-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Field Key</th>
                                <th style="width: 40%;">Field Value</th>
                                <th style="width: 20%;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="custom-fields-tbody">
                            <?php if (!empty($form_custom_fields)): ?>
                                <?php foreach ($form_custom_fields as $index => $field): ?>
                                    <tr>
                                        <td>
                                            <input type="text" 
                                                   name="wpftab_custom_fields[<?php echo $index; ?>][key]" 
                                                   value="<?php echo esc_attr($field['key'] ?? ''); ?>"
                                                   placeholder="e.g. form_name"
                                                   class="regular-text"
                                                   style="width: 100%;">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="wpftab_custom_fields[<?php echo $index; ?>][value]" 
                                                   value="<?php echo esc_attr($field['value'] ?? ''); ?>"
                                                   placeholder="e.g. Contact Form 1"
                                                   class="regular-text"
                                                   style="width: 100%;">
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small remove-custom-field" onclick="this.closest('tr').remove();">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button" id="add-custom-field">+ Add Custom Field</button>
                    </p>
                </div>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Mapping">
                </p>
            <?php elseif ($selected_form_id > 0 && empty($form_fields)): ?>
                <div class="notice notice-warning">
                    <p>No fields found in this form or form does not exist.</p>
                </div>
            <?php else: ?>
                <div id="form-fields-container">
                    <p>Select a form above to load and map its fields.</p>
                </div>
            <?php endif; ?>
        </form>
    </div>
    <script>
    (function() {
        let fieldIndex = <?php echo isset($form_custom_fields) && !empty($form_custom_fields) ? count($form_custom_fields) : 0; ?>;
        
        document.getElementById('add-custom-field')?.addEventListener('click', function() {
            const tbody = document.getElementById('custom-fields-tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <input type="text" 
                           name="wpftab_custom_fields[${fieldIndex}][key]" 
                           placeholder="e.g. form_name"
                           class="regular-text"
                           style="width: 100%;">
                </td>
                <td>
                    <input type="text" 
                           name="wpftab_custom_fields[${fieldIndex}][value]" 
                           placeholder="e.g. Contact Form 1"
                           class="regular-text"
                           style="width: 100%;">
                </td>
                <td>
                    <button type="button" class="button button-small remove-custom-field" onclick="this.closest('tr').remove();">
                        Remove
                    </button>
                </td>
            `;
            tbody.appendChild(row);
            fieldIndex++;
        });
    })();
    </script>
    <?php
}
