<?php
if (!defined('ABSPATH')) exit;

function wpftab_render_global_settings() {
    $api_url = get_option('wpftab_api_url', '');
    $api_key = get_option('wpftab_api_key', '');
    $debug_log_only = get_option('wpftab_debug_log_only', '0');
    $last_debug_payload = get_option('wpftab_last_debug_payload', '');
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
            <h3 style="margin-top: 20px;">Last API call (payload)</h3>
            <div class="wpftab-debug-payload" style="background: #f6f7f7; border: 1px solid #c3c4c7; padding: 12px; max-height: 400px; overflow: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all;">
                <?php
                if ($last_debug_payload === '') {
                    echo esc_html('No submission recorded yet. Enable debug mode above and submit a form to see the payload here.');
                } else {
                    $decoded = json_decode($last_debug_payload, true);
                    echo esc_html($decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $last_debug_payload);
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
