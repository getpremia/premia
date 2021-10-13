<?php
/**
 * Woocommerce License Manager Helper
 *
 * @package Premia
 * @since 1.0
 */

namespace Premia;

/**
 * Woocommerce License Manager integration

 * @since 1.0
 */
class Woocommerce_License_Manager_Helper {

	/**
	 * Consructor function.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initiatior.
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'start' ), 5 );
	}

	/**
	 * Is license manager active.
	 *
	 * @return bool The current state.
	 */
	public static function is_license_manager_active() {
		return \class_exists( 'LicenseManagerForWooCommerce\Main' );
	}

	/**
	 * Starter for License Manager Helper.
	 */
	public function start() {
		if ( $this->is_license_manager_active() ) {
			add_action( 'woocommerce_account_view-license-keys_endpoint', array( $this, 'manage_installs' ), 20 );
			add_filter( 'premia_activate_license', array( $this, 'activate' ), 10, 2 );
			add_filter( 'premia_deactivate_license', array( $this, 'deactivate' ), 10, 2 );
			add_filter( 'premia_get_license', array( $this, 'get_license' ) );
			add_filter( 'premia_validate_license', array( $this, 'validate_license' ), 10, 2 );
			add_filter( 'premia_order_downloads', array( $this, 'add_order_downloads' ), 10, 2 );
			add_filter( 'premia_woocommerce_downloads', array( $this, 'add_woocommerce_downloads' ) );
			add_filter( 'premia_get_license_by_license_key', array( $this, 'get_license_by_license_key' ), 10, 2 );
			add_filter( 'premia_get_installations', array( $this, 'get_installations' ), 10, 2 );
			add_filter( 'premia_is_license_active', array( $this, 'is_license_active' ), 10, 2 );
		}
	}

	/**
	 * Is the license active?
	 *
	 * @param bool   $status Current state.
	 * @param string $license_key  The license key.
	 * @return bool Current state.
	 */
	public function is_license_active( $status, $license_key ) {
		$license = $this->get_license( $license_key );
		if ( false !== $license ) {
			$status = true;
		} else {
			$status = false;
		}
		return $status;
	}

	/**
	 * Get the license object with the license key.
	 *
	 * @param object $post The Post object.
	 * @param string $license_key The license key.
	 * @return object The post object.
	 */
	public function get_license_by_license_key( $post, $license_key ) {
		return $this->get_license( $license_key );
	}

	/**
	 * Add Woocommerce downloads
	 *
	 * @param array $downloads The customized array.
	 * @return array The customized downloads array.
	 */
	public function add_woocommerce_downloads( $downloads ) {

		$downloads = array();

		$user_licenses = apply_filters( 'lmfwc_get_all_customer_license_keys', get_current_user_id() );

		if ( is_array( $user_licenses ) && ! empty( $user_licenses ) ) {
			foreach ( $user_licenses as $data ) {
				foreach ( $data['licenses'] as $license ) {

					$post = get_post( $license->getproductId() );

					$license_info = array(
						'license_key' => $license->getDecryptedLicenseKey(),
						'site_url'    => '',
						'plugin'      => $post->post_name,
						'_wpnonce'    => wp_create_nonce( 'wp_rest' ),
					);

					$file_name = $post->post_name . '.zip';

					$download_url = get_rest_url() . 'license-updater/v1/download_update';
					$download_url = add_query_arg( $license_info, $download_url );
					$downloads[]  = array(
						'download_url'        => $download_url,
						'download_id'         => false,
						'product_id'          => $post->ID,
						'product_name'        => $data['name'],
						'product_url'         => get_permalink( $post->ID ),
						'download_name'       => $file_name,
						'order_id'            => $license->getOrderId(),
						'downloads_remaining' => '',
						'access_expires'      => 'yes',
						'file'                => array(
							'name' => $file_name,
							'file' => $download_url,
						),
					);
				}
			}
		}

		return $downloads;
	}

	/**
	 * Show downloads in order.
	 *
	 * @param array  $downloads List of downloads.
	 * @param object $order The order object.
	 * @return array Customised list of downloads.
	 */
	public function add_order_downloads( $downloads, $order ) {

		$downloads = array();

		$user_licenses = apply_filters( 'lmfwc_get_customer_license_keys', $order );

		if ( is_array( $user_licenses ) && ! empty( $user_licenses ) ) {

			foreach ( $user_licenses as $data ) {
				foreach ( $data['keys'] as $license ) {
					$post         = get_post( $license->getproductId() );
					$license_info = array(
						'license_key' => $license->getDecryptedLicenseKey(),
						'site_url'    => '',
						'plugin'      => $post->post_name,
						'_wpnonce'    => wp_create_nonce( 'wp_rest' ),
					);
					$download_url = get_rest_url() . 'license-updater/v1/download_update';
					$download_url = add_query_arg( $license_info, $download_url );
					$downloads[]  = array(
						'link' => $download_url,
						'name' => $data['name'],
					);
				}
			}
		}

		return $downloads;
	}

	/**
	 * Validate license
	 *
	 * @param bool  $validate The current state.
	 * @param array $license_info The license information.
	 * @return bool The new state.
	 */
	public function validate_license( $validate, $license_info ) {
		$validate = false;
		$license  = lmfwc_get_license( $license_info['license_key'] );

		if ( false !== $license ) {
			$installs = lmfwc_get_license_meta( $license->getId(), 'installations', false );
			if ( in_array( $license_info['site_url'], $installs, true ) || is_user_logged_in() ) {
				$validate = true;
			} else {
				Debug::log( 'Cannot validate site', $license_info );
			}
		} else {
			Debug::log( 'Something went wrong while getting the license.', $license );
		}

		return $validate;
	}

	/**
	 * Manage installs
	 * This section is shown below the license manager output in Woocommerce -> My account
	 */
	public function manage_installs() {

		if ( isset( $_GET['_wpnonce'] ) ) {
			$nonce = sanitize_key( $_GET['_wpnonce'] );
			if ( wp_verify_nonce( $nonce ) ) {

				if ( isset( $_GET['site_url'] ) && isset( $_GET['action'] ) && isset( $_GET['license_key'] ) ) {
					if ( ! empty( $_GET['site_url'] ) && ! empty( $_GET['action'] ) && ! empty( $_GET['license_key'] ) ) {
						$action      = sanitize_text_field( $_GET['action'] );
						$site_url    = sanitize_text_field( $_GET['site_url'] );
						$license_key = sanitize_text_field( $_GET['license_key'] );

						if ( $action === 'deactivate' ) {
							$deactivate = self::deactivate(
								array(
									'license_key' => $license_key,
									'site_url'    => $site_url,
								)
							);
						}
					}
				}
			}
		}

		echo '<h2>' . esc_html__( 'Manage installations', 'premia' ) . '</h2>';
		$user_licenses = apply_filters( 'lmfwc_get_all_customer_license_keys', get_current_user_id() );
		foreach ( $user_licenses as $data ) {
			foreach ( $data['licenses'] as $license ) {
				$installs = lmfwc_get_license_meta( $license->getId(), 'installations', false );
				echo '<table>';
				echo '<tr><th>' . esc_html__( 'Site', 'premia' ) . '</th><th>' . esc_html__( 'Action', 'premia' ) . '</th></tr>';
				foreach ( $installs as $site ) {
						echo '<tr>';
						echo '<td>' . $site . '</td>';
						$deactivate_link = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . 'view-license-keys';
						$deactivate_link = add_query_arg(
							array(
								'site_url'    => esc_url( $site ),
								'action'      => 'deactivate',
								'license_key' => $license->getDecryptedLicenseKey(),
							)
						);
						echo '<td><a href="' . esc_url( wp_nonce_url( $deactivate_link ) ) . '" class="button" data-site="' . $site . '">Deactivate</td>';
						echo '</tr>';
				}
				echo '</table>';
			}
		}
	}

	/**
	 * Deactivate the license
	 *
	 * @param array $license_info An array with license information.
	 */
	public static function deactivate( $license_info ) {
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
	public static function activate( $status, $license_info ) {
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

	public function get_license( $license_key ) {
		$license = lmfwc_get_license( $license_key );
		return $license;
	}

	public function get_installations( $sites, $license_key ) {
		$license = $this->get_license( $license_key );
		if ( $license !== false ) {
			$sites = lmfwc_get_license_meta( $license->getId(), 'installations', false );
		}
		return $sites;
	}
}
