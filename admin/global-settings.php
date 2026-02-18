<?php
if (!defined('ABSPATH')) exit;

function wpftab_render_global_settings() {
    $api_url = get_option('wpftab_api_url', '');
    $api_key = get_option('wpftab_api_key', '');
    $debug_log_only = get_option('wpftab_debug_log_only', '0');
    $last_debug_payload = get_option('wpftab_last_debug_payload', '');
    $last_trigger_info = get_option('wpftab_last_trigger_info', []);
    if (!is_array($last_trigger_info)) $last_trigger_info = [];
    $default_utm_map = [
        'traffic_source' => '',
        'utm_source' => '',
        'utm_medium' => '',
        'utm_campaign_id' => '',
        'utm_campaign_name' => '',
        'utm_term' => '',
        'utm_adgroup_id' => '',
        'utm_adgroup_name' => '',
        'utm_content' => '',
        'clid' => ''
    ];
    $utm_map = get_option('wpftab_utm_map', []);
    if (!is_array($utm_map)) $utm_map = [];
    $utm_map = array_merge($default_utm_map, $utm_map);
    unset($utm_map['utm_campaign'], $utm_map['utm_adgroup']);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('wpftab_save_global', 'wpftab_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(__('Cheating?', 'wpftab'));
        }
        update_option('wpftab_api_url', esc_url_raw($_POST['wpftab_api_url'] ?? ''));
        update_option('wpftab_api_key', sanitize_text_field($_POST['wpftab_api_key'] ?? ''));
        update_option('wpftab_debug_log_only', isset($_POST['wpftab_debug_log_only']) ? '1' : '0');
        foreach ($utm_map as $key => $v) {
            $utm_map[$key] = sanitize_text_field($_POST['wpftab_utm_map'][$key] ?? '');
        }
        update_option('wpftab_utm_map', $utm_map);
        $debug_log_only = get_option('wpftab_debug_log_only', '0');
        $last_debug_payload = get_option('wpftab_last_debug_payload', '');
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }

    ?>
    <div class="wpftab-admin-container">
        <h2>API Config</h2>
        <form method="post">
            <?php wp_nonce_field('wpftab_save_global', 'wpftab_nonce'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>API URL</strong></td>
                        <td>
                            <input type="url" 
                                   name="wpftab_api_url" 
                                   id="wpftab_api_url" 
                                   value="<?php echo esc_attr($api_url); ?>"
                                   class="regular-text"
                                   style="width: 100%; max-width: 500px;">
                        </td>
                    </tr>
                    <tr>
                        <td><strong>API Key</strong></td>
                        <td>
                            <input type="text" 
                                   name="wpftab_api_key" 
                                   id="wpftab_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>"
                                   class="regular-text"
                                   style="width: 100%; max-width: 500px;">
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top: 30px;">Cookie Mapping</h2>
            <p>Map each cookie field to your API field name. Leave empty to skip mapping.</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Cookie Field Name</th>
                        <th>API Field Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($utm_map as $key => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html($key); ?></strong></td>
                            <td>
                                <input type="text" 
                                       name="wpftab_utm_map[<?php echo esc_attr($key); ?>]" 
                                       id="utm_<?php echo esc_attr($key); ?>" 
                                       value="<?php echo esc_attr($value); ?>"
                                       placeholder="Leave empty to skip"
                                       class="regular-text"
                                       style="width: 100%; max-width: 400px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top: 30px;">Debug</h2>
            <?php if (!empty($last_trigger_info)): ?>
                <?php
                $tr = $last_trigger_info;
                $form_label = '';
                if (!empty($tr['form_type'])) {
                    if ($tr['form_type'] === 'CF7' && isset($tr['form_id'])) {
                        $form_label = 'CF7, form ID: ' . (int) $tr['form_id'];
                    } elseif ($tr['form_type'] === 'Elementor' && !empty($tr['form_key'])) {
                        $form_label = 'Elementor, form key: ' . esc_html($tr['form_key']);
                    } else {
                        $form_label = esc_html($tr['form_type']);
                    }
                }
                $sent = !empty($tr['sent_to_api']);
                $when = !empty($tr['timestamp']) ? ' la ' . esc_html($tr['timestamp']) : '';
                ?>
                <p class="wpftab-last-trigger" style="margin-bottom: 16px; padding: 12px 14px; background: #f0f6fc; border: 1px solid #2271b1; border-left-width: 4px;">
                    <strong>Ultimul formular triggeruit</strong><?php echo $when; ?>: <strong><?php echo $form_label ?: '—'; ?></strong><br>
                    <strong>Trimis la API:</strong> <?php echo $sent ? 'Da' : 'Nu'; ?> (<?php echo $sent ? 's-a trimis efectiv' : 'doar logat, debug activ'; ?>)
                </p>
            <?php endif; ?>
            <p>When enabled, form submissions are not sent to the API. The last payload is stored and shown below (overwritten on each new submit).</p>
            <table class="wp-list-table widefat fixed striped">
                <tr>
                    <td style="width: 220px;"><strong>Log only (do not send to API)</strong></td>
                    <td>
                        <label>
                            <input type="checkbox" name="wpftab_debug_log_only" value="1" <?php checked($debug_log_only, '1'); ?>>
                            Enable debug mode
                        </label>
                    </td>
                </tr>
            </table>
            <h3 style="margin-top: 20px;">Ultimul payload (JSON)</h3>
            <?php if ($last_debug_payload !== ''): ?>
                <?php if ($debug_log_only === '1'): ?>
                    <p style="margin-bottom: 8px; color: #d63638;"><strong>Cu debug activ acest payload nu s-a trimis la API</strong> – a fost doar salvat aici pentru verificare.</p>
                <?php else: ?>
                    <p style="margin-bottom: 8px; color: #00a32a;"><strong>Acest payload s-a trimis la API.</strong></p>
                <?php endif; ?>
            <?php endif; ?>
            <?php
            if ($last_debug_payload !== '') {
                $decoded = json_decode($last_debug_payload, true);
                if (is_array($decoded) && !empty($decoded['debug_info'])) {
                    $info = $decoded['debug_info'];
                    $form_label = '';
                    if (!empty($info['form_type'])) {
                        if ($info['form_type'] === 'CF7' && isset($info['form_id'])) {
                            $form_label = 'CF7, form ID: ' . (int) $info['form_id'];
                        } elseif ($info['form_type'] === 'Elementor' && !empty($info['form_key'])) {
                            $form_label = 'Elementor, form key: ' . esc_html($info['form_key']);
                        } else {
                            $form_label = esc_html($info['form_type']);
                        }
                    }
                    $send_label = isset($info['send_to_api']) && $info['send_to_api'] ? 'Da' : 'Nu';
                    echo '<p class="wpftab-debug-meta" style="margin-bottom: 8px; padding: 8px 12px; background: #f0f0f1; border-left: 4px solid #2271b1;"><strong>Formular trigger:</strong> ' . $form_label . ' &nbsp;|&nbsp; <strong>Se trimite la API:</strong> ' . $send_label . ' (în mod normal; cu debug activ doar se loghează)</p>';
                }
            }
            ?>
            <div class="wpftab-debug-payload" style="background: #f6f7f7; border: 1px solid #c3c4c7; padding: 12px; max-height: 400px; overflow: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all;">
                <?php
                if ($last_debug_payload === '') {
                    echo esc_html('No submission recorded yet. Enable debug mode above and submit a form to see the payload here.');
                } else {
                    $decoded = json_decode($last_debug_payload, true);
                    if ($decoded) {
                        echo esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    } else {
                        echo esc_html($last_debug_payload);
                    }
                }
                ?>
            </div>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}
