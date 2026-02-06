<?php
if (!defined('ABSPATH')) exit;

function wpftab_render_global_settings() {
    $api_url = get_option('wpftab_api_url', '');
    $api_key = get_option('wpftab_api_key', '');
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
        foreach ($utm_map as $key => $v) {
            $utm_map[$key] = sanitize_text_field($_POST['wpftab_utm_map'][$key] ?? '');
        }
        update_option('wpftab_utm_map', $utm_map);
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

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}
