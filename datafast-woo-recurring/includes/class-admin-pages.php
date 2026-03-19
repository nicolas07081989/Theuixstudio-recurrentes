<?php
namespace DFWR;

class Admin_Pages
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function menu(): void
    {
        add_menu_page('Datafast', 'Datafast', 'manage_woocommerce', 'dfwr-transactions', [__CLASS__, 'transactions_page'], 'dashicons-money-alt');
        add_submenu_page('dfwr-transactions', 'Transacciones', 'Transacciones', 'manage_woocommerce', 'dfwr-transactions', [__CLASS__, 'transactions_page']);
        add_submenu_page('dfwr-transactions', 'Suscripciones', 'Suscripciones', 'manage_woocommerce', 'dfwr-subscriptions', [__CLASS__, 'subscriptions_page']);
        add_submenu_page('dfwr-transactions', 'Tokens', 'Tokens', 'manage_woocommerce', 'dfwr-tokens', [__CLASS__, 'tokens_page']);
        add_submenu_page('dfwr-transactions', 'Herramientas', 'Herramientas', 'manage_woocommerce', 'dfwr-tools', [__CLASS__, 'tools_page']);
    }

    public static function transactions_page(): void { echo '<div class="wrap"><h1>Datafast Transacciones</h1><p>Use filtros por order_id, merchantTransactionId, payment_id, status y environment.</p></div>'; }
    public static function subscriptions_page(): void { echo '<div class="wrap"><h1>Datafast Suscripciones</h1></div>'; }
    public static function tokens_page(): void { echo '<div class="wrap"><h1>Datafast Tokens</h1></div>'; }
    public static function tools_page(): void { echo '<div class="wrap"><h1>Datafast Diagnóstico</h1></div>'; }
}
