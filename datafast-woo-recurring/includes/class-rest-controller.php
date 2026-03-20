<?php
namespace DFWR;

class Rest_Controller
{
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route('datafast/v1', '/tokens/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'delete_token'],
            'permission_callback' => [__CLASS__, 'can_manage_own_token'],
        ]);
    }

    public static function can_manage_own_token(\WP_REST_Request $request): bool
    {
        return is_user_logged_in() && wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest');
    }

    public static function delete_token(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $token_id = (int) $request['id'];
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'df_tokens';
        $token = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $token_id), ARRAY_A);
        if (! $token || (int) $token['wp_user_id'] !== $user_id) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'Token no encontrado.'], 404);
        }

        $cfg = Environment::checkout_config();
        $endpoint = rtrim($cfg['base_url'], '/') . '/v1/registrations/' . rawurlencode($token['registration_id']) . '?entityId=' . rawurlencode($cfg['entity_id']);
        $response = wp_remote_request($endpoint, [
            'method' => 'DELETE',
            'headers' => ['Authorization' => 'Bearer ' . $cfg['bearer']],
            'sslverify' => $cfg['mode'] !== 'prod',
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response(['ok' => false, 'message' => $response->get_error_message()], 500);
        }

        $wpdb->update($table, ['is_active' => 0, 'updated_at' => current_time('mysql')], ['id' => $token_id]);
        return new \WP_REST_Response(['ok' => true, 'message' => 'Token desactivado.']);
    }
}
