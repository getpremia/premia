<?php
/**
 * Premia
 *
 * @package           Premia
 * @author            Conference7
 * @copyright         2021 Conference7
 *
 * @wordpress-plugin
 * Plugin Name:       Premia
 * Plugin URI:        https://mklasen.com
 * Description:       Premia
 * Version:           0.9.9.9
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Marinus Klasen
 * Author URI:        https://getpremia.com
 * Text Domain:       premia
 */

namespace Premia;

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
/**
 * Start the plugin
 *
 * @since    1.5.0
 */
function run_premia() {
	new Github();
	new REST_Endpoints();
	new Custom_Fields();
	new Shortcodes();
	new Debug();
	new Woocommerce_Helper();
	new Licenses();
	new Woocommerce_License_Manager_Helper();
	new Updater( 'premia', __FILE__ );
	new Admin_Options();
	new Admin_Notices();
}

run_premia();
