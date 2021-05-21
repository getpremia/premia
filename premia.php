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
		if ( ! $license ) {
			return false;
		}
		if ( $license->getTimesActivated() > 0 ) {
			lmfwc_deactivate_license( $license->getDecryptedLicenseKey() );
		}
		lmfwc_delete_license_meta( $license->getId(), 'installations', $license_info['site_url'] );
		return true;
	}

	/**
	 * Activate the license
	 *
	 * @param array $license_info An array with license information.
	 */
	public static function activate_license( $license_info ) {
		$license = lmfwc_get_license( $license_info['license_key'] );
		if ( ! $license ) {
			return false;
		}
		$activate = lmfwc_activate_license( $license_info['license_key'] );
		$installs = lmfwc_get_license_meta( $license->getId(), 'installations', false );
		if ( ! in_array( $license_info['site_url'], $installs, true ) ) {
			$activate = lmfwc_add_license_meta( $license->getId(), 'installations', $license_info['site_url'] );
		}
		return true;
	}

}

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

$woocommerce_license_updater = new Premia();
