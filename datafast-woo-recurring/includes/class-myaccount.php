<?php
namespace DFWR;

class MyAccount
{
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'add_endpoints']);
        add_action('template_redirect', [__CLASS__, 'maybe_cancel_subscription']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'menu_items']);
        add_action('woocommerce_account_datafast-tokens_endpoint', [__CLASS__, 'render_tokens']);
        add_action('woocommerce_account_datafast-subscriptions_endpoint', [__CLASS__, 'render_subscriptions']);
    }

    public static function maybe_cancel_subscription(): void
    {
        if (! is_user_logged_in() || empty($_GET['dfwr_cancel_subscription'])) {
            return;
        }
        $id = absint($_GET['dfwr_cancel_subscription']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, 'dfwr_cancel_sub_' . $id)) {
            return;
        }
        global $wpdb;
        $owner = (int) $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM {$wpdb->prefix}df_subscriptions WHERE id = %d", $id));
        if ($owner !== get_current_user_id()) {
            return;
        }
        (new Subscription_Repository())->cancel($id);
        wc_add_notice(__('Suscripción cancelada.', 'datafast-woo-recurring'));
        wp_safe_redirect(wc_get_account_endpoint_url('datafast-subscriptions'));
        exit;
    }

    public static function add_endpoints(): void
    {
        add_rewrite_endpoint('datafast-tokens', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('datafast-subscriptions', EP_ROOT | EP_PAGES);
    }

    public static function query_vars(array $vars): array
    {
        $vars[] = 'datafast-tokens';
        $vars[] = 'datafast-subscriptions';
        return $vars;
    }

    public static function menu_items(array $items): array
    {
        $items['datafast-tokens'] = __('Tarjetas guardadas', 'datafast-woo-recurring');
        $items['datafast-subscriptions'] = __('Suscripciones Datafast', 'datafast-woo-recurring');
        return $items;
    }

    public static function render_tokens(): void
    {
        $repo = new Token_Repository();
        $tokens = $repo->by_user(get_current_user_id());
        wc_get_template('myaccount-tokens.php', ['tokens' => $tokens], '', DFWR_PLUGIN_DIR . 'templates/');
    }

    public static function render_subscriptions(): void
    {
        global $wpdb;
        $subs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}df_subscriptions WHERE wp_user_id = %d ORDER BY id DESC", get_current_user_id()), ARRAY_A) ?: [];
        wc_get_template('myaccount-subscriptions.php', ['subscriptions' => $subs], '', DFWR_PLUGIN_DIR . 'templates/');
    }
}
