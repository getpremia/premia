<?php
/**
 * Woocommerce License Updater
 *
 * @package           Woocommerce License Updater
 * @author            Conference7
 * @copyright         2021 Conference7
 *
 * @wordpress-plugin
 * Plugin Name:       Woocommerce License Updater
 * Plugin URI:        https://mklasen.com
 * Description:       Woocommerce License Updater
 * Version:           1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Marinus Klasen
 * Author URI:        httsps://sympose.net
 * Text Domain:       woocommerce-license-updater
 */

namespace Woocomerce_License_Updater;

/**
 * WooCommerce License Updater
 *
 * @since 1.0
 */
class Woocommerce_License_Updater {

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
		new Woocommerce();
		new Github_API();
		new REST_Endpoints();
	}

	/**
	 * Deactivate the license
	 *
	 * @param array $license_info An array with license information.
	 */
	public static function deactivate_license( $license_info ) {
		$license = lmfwc_get_license( $license_info['license_key'] );
		if ( $license->getTimesActivated() > 0 ) {
			lmfwc_deactivate_license( $license->getDecryptedLicenseKey() );
		}
		lmfwc_delete_license_meta( $license->getId(), 'installations', $license_info['site_url'] );
	}

	/**
	 * Activate the license
	 *
	 * @param array $license_info An array with license information.
	 */
	public static function activate_license( $license_info ) {
		$license = lmfwc_get_license( $license_info['license_key'] );
		lmfwc_activate_license( $license_info['license_key'] );
		$installs = lmfwc_get_license_meta( $license->getId(), 'installations', false );
		if ( ! in_array( $license_info['site_url'], $installs, true ) ) {
			$activate = lmfwc_add_license_meta( $license->getId(), 'installations', $license_info['site_url'] );
		}
	}

}

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

$woocommerce_license_updater = new Woocommerce_License_Updater();
