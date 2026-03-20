<?php
/**
 * Plugin Name: Datafast Woo Recurring
 * Description: Integración Datafast Dataweb para WooCommerce con tokenización y recurrencias.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Theuixstudio
 * Text Domain: datafast-woo-recurring
 */

if (! defined('ABSPATH')) {
    exit;
}

define('DFWR_VERSION', '1.0.0');
define('DFWR_PLUGIN_FILE', __FILE__);
define('DFWR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DFWR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once DFWR_PLUGIN_DIR . 'includes/class-activator.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once DFWR_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, ['DFWR\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['DFWR\\Deactivator', 'deactivate']);

add_action('plugins_loaded', static function () {
    DFWR\Plugin::instance()->bootstrap();
});
