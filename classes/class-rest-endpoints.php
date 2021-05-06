<?php
namespace Woocomerce_License_Updater;

/**
 * Rest Endpoints class
 *
 * @since 1.0
 */
class REST_Endpoints {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initiator.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register the REST endpoints.
	 */
	public function register_endpoints() {
		register_rest_route(
			'license-updater/v1',
			'check_updates',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'check_updates' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'license-updater/v1',
			'download_update',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'download_update' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'license-updater/v1',
			'activate',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'activate' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return a zip file.
	 *
	 * @param object $request The request object.
	 */
	public function download_update( $request ) {

		$params = $request->get_params();

		$product = wc_get_product( get_page_by_path( $params['plugin'], OBJECT, 'product' ) );

		$license_info = array(
			'license_key' => $params['license_key'],
			'site_url'    => $params['site_url'],
			'id'          => $product->get_id(),
		);

		if ( ! is_user_logged_in() && ! $this->validate_request( $license_info ) ) {
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request.' ), 400 );
		}

		$validate = $this->validate( $license_info );

		if ( ! $validate ) {
			return array( 'error' => 'error' );
		}

		$api_url   = get_post_meta( $license_info['id'], '_updater_repo', true );
		$api_token = get_post_meta( $license_info['id'], '_updater_api_token', true );

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token,
			),
		);

		$result = wp_remote_get( $api_url . 'releases/latest', $args );
		$body   = json_decode( wp_remote_retrieve_body( $result ) );

		$zip = wp_remote_get( $body->zipball_url, $args );

		$product = wc_get_product( $license_info['id'] );

		foreach ( $zip['headers'] as $key => $header ) {
			if ( $key === 'content-disposition' ) {
				header( $key . ': attachment; filename=' . $product->get_slug() . '.zip' );
			} else {
				header( $key . ': ' . $header );
			}
		}

		//phpcs:disable
		echo $zip['body'];

		exit();
	}

	/**
	 * Rest callback for check_updates
	 *
	 * @param object $request The request object.
	 *
	 * @return $array The results.
	 */
	public function check_updates( $request ) {

		$params = $request->get_params();
		
		$product = wc_get_product( get_page_by_path( $params['plugin'], OBJECT, 'product' ) );

		if ( ! isset( $params['license_key'] ) || ! isset( $params['site_url'] ) || ! isset( $params['plugin'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'Missing parameters.' ), 400 );
		}

		$license_info = array(
			'license_key'  => $params['license_key'],
			'site_url' => $params['site_url'],
			'id'          => $product->get_id(),
		);

		if ( ! $this->validate_request( $license_info ) ) {
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request.' ), 400 );
		}

		$validate = $this->validate( $license_info );

		$api_url   = get_post_meta( $license_info['id'], '_updater_repo', true );
		$api_token = get_post_meta( $license_info['id'], '_updater_api_token', true );

		$latest      = Github_API::request( $api_url, $api_token, 'releases/latest' );
		$latest_info = json_decode( wp_remote_retrieve_body( $latest ) );

		$readme      = Github_API::request( $api_url, $api_token, 'readme' );
		if ( is_wp_error($readme) ) {
			return new \WP_REST_Response( array( 'error' => 'Cannot read readme.' ), 400 );	
		}
		$readme_body = json_decode( $readme['body'] );

		$readme_text = base64_decode( $readme_body->content );

		$product = wc_get_product( $license_info['id'] );

		$download_url = '';
		if ( $validate ) {
			$download_url = get_rest_url() . 'license-updater/v1/download_update';
			$download_url = add_query_arg( $license_info, $download_url );
		}

		return array(
			'name'         => $product->get_name(),
			'version'      => $latest_info->tag_name,
			'download_url' => $download_url,
			'sections'     => array(
				'description' => $readme_text,
			),
		);
	}

	/**
	 * Rest callback for activation.
	 *
	 * @param object $request The request object.
	 */
	public function activate( $request ) {
		$activate     = false;
		$license_info = $request->get_params();

		if ( $license_info['action'] === 'deactivate' ) {
			$deactivate = Woocommerce_License_Updater::deactivate_license( $license_info );
			if (!$deactivate) {
				return new \WP_REST_Response( array( 'error' => 'Failed to deactivate license' ), 400 );	
			}
		} else {
			$activate = Woocommerce_License_Updater::activate_license( $license_info );
			if (!$activate) {
				return new \WP_REST_Response( array( 'error' => 'Failed to activate license' ), 400 );	
			}
			
		}
		return $activate;
	}

	/**
	 * Validate the license by checking if the url is saved as meta for this license key.
	 *
	 * @return boolean true or false.
	 */
	public function validate( $license_info ) {
		$license  = lmfwc_get_license( $license_info['license_key'] );

		if ($license !== false) {
			$installs = lmfwc_get_license_meta( $license->getId(), 'installations', false );
			if ( in_array( $license_info['site_url'], $installs, true ) || is_user_logged_in() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate that this request comes from WordPress
	 */
	public function validate_request( $license_info ) {

		if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'WordPress' ) === false ) {
			return false;
		}

		if ( strpos( $_SERVER['HTTP_USER_AGENT'], $license_info['site_url'] ) === false ) {
			return false;
		}

		return true;
	}
}
