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
 * Version:           1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Marinus Klasen
 * Author URI:        https://getpremia.com
 * Text Domain:       premia
 */

namespace Premia;

/**
 * WooCommerce License Updater
 *
 * @since 1.0
 */
class Premia {

	/**
	 * Constructor function
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Add hooks and filters
	 */
	public function init() {
		new Github();
		new REST_Endpoints();
		new Custom_Fields();
		new Shortcodes();
		new Debug();
		new Woocommerce_Helper();
		new Licenses();
		new Woocommerce_License_Manager_Helper();
		new Compressor();
		new Updater('premia');
		new Admin_Options();
	}

}

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

$premia = new Premia();
