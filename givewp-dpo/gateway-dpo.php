<?php
/**
 * Plugin Name: DPO Pay for GiveWP
 * Plugin URI: https://github.com/DPO-Group/DPO_GiveWP
 * Description: Integrates GiveWP with DPO Pay, an African payment gateway.
 * Version: 1.0.1
 * Requires at least: 5.6
 * Requires PHP: 8.0
 * Author: DPO Group
 * Author URI: https://dpogroup.com/
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * Copyright: © 2024 DPO Group
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

use Dpo\Common\Dpo;
use Dpo\Give\DpoGive;
use Dpo\Give\DpoGiveCron;
use Dpo\Give\DpoGiveSettings;
use Give\Helpers\Hooks;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once 'vendor/autoload.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Check if the GiveWP plugin is active
if (!is_plugin_active('give/give.php')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(
        "<strong>DPO Pay</strong> requires the
 <strong>GiveWP</strong> plugin in order to work. Please activate or install it.<br><br>
 Back to the WordPress <a href='" . get_admin_url(null, 'plugins.php')
        . "'>Plugins Page</a>"
    );
}

add_action('givewp_register_payment_gateway', static function ($paymentGatewayRegister) {
    $paymentGatewayRegister->registerGateway(DpoGive::class);
});

add_filter('plugin_action_links_givewp-dpo/gateway-dpo.php', [DpoGive::class, 'giveDpoPluginActionLinks']);

add_filter('give_get_settings_gateways', [DpoGiveSettings::class, 'addSettings']);
add_filter('give_get_sections_gateways', [DpoGive::class, 'addGatewaysSection']);
add_filter('give_payment_gateways', [DpoGive::class, 'getOptions'], 10, 1);

Hooks::addAction('init', DpoGive::class, 'giveDpoListener');

// Setup cron job
$next = wp_next_scheduled('give_dpo_query_cron_hook');
if (!$next) {
    wp_schedule_event(time(), 'hourly', 'give_dpo_query_cron_hook');
}
add_action('give_dpo_query_cron_hook', [DpoGiveCron::class, 'giveDpoQueryCron']);
