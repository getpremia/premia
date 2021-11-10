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
 * Version:           1.1
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
 * @since    1.0
 */
function run_premia() {

	$classes = array(
		'Github',
		'REST_Endpoints',
		'Custom_Fields',
		'Shortcodes',
		'Debug',
		'Updater',
		'Admin_Options',
		'Admin_Notices',
		'Woocommerce_Helper',
		'Licenses',
		'Woocommerce_License_Manager_Helper',
	);

	foreach ( $classes as $class ) {
		$class = "Premia\\{$class}";
		if ( class_exists( $class ) ) {
			new $class( 'premia', __FILE__ );
		}
	}
}

run_premia();
