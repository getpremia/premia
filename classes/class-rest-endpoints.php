<?php
namespace Woocomerce_License_Updater;

use ZipArchive;

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

		$params       = $request->get_params();
		$license_info = $params;

		$product = wc_get_product( get_page_by_path( $params['plugin'], OBJECT, 'product' ) );

		$plugin_file = $product->get_slug() . '.zip';

		if ( ! is_user_logged_in() && ! $this->validate_request( $license_info ) ) {
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request.' ), 400 );
		}

		$validate = $this->validate( $license_info );

		if ( ! $validate ) {
			return array( 'error' => 'error' );
		}

		$api_url = get_post_meta( $product->get_id(), '_updater_repo', true );

		$api_token = get_post_meta( $product->get_id(), '_updater_api_token', true );

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token,
			),
		);

		$result = wp_remote_get( $api_url . 'releases/latest', $args );
		$body   = json_decode( wp_remote_retrieve_body( $result ) );

		$zip = wp_remote_get( $body->zipball_url, $args );

		$base_dir = plugin_dir_path( dirname( __FILE__ ) );

		if ( ! is_dir( $base_dir . 'tmp' ) ) {
			mkdir( $base_dir . 'tmp/' );
		}

		if ( ! is_dir( $base_dir . 'tmp/zip' ) ) {
			mkdir( $base_dir . 'tmp/zip' );
		}

		if ( ! is_dir( $base_dir . 'tmp/unpacked' ) ) {
			mkdir( $base_dir . 'tmp/unpacked' );
		}

		if ( ! is_dir( $base_dir . 'tmp/unpacked/' . $body->tag_name ) ) {
			mkdir( $base_dir . 'tmp/unpacked/' . $body->tag_name );
		}

		$archive_path = $base_dir . 'tmp/zip/' . $body->tag_name . '/' . $plugin_file;

		/**
		 * If the ZIP does not exist, generate it.
		 */
		if ( ! is_file( $archive_path ) || 1 === 1 ) {

			if ( ! is_dir( $base_dir . 'tmp/zip/' . $body->tag_name ) ) {
				mkdir( $base_dir . 'tmp/zip/' . $body->tag_name );
			}

			$parts    = explode( 'filename=', $zip['headers']['content-disposition'] );
			$zip_name = $parts[1];

			$file_name = $base_dir . 'tmp/zip/' . $zip_name;

			file_put_contents( $file_name, $zip['body'] );

			$package = new ZipArchive();
			$package->open( $file_name );
			$package->extractTo( $base_dir . 'tmp/unpacked/' . $body->tag_name );

			$unpacked_contents = scandir( $base_dir . 'tmp/unpacked/' . $body->tag_name );

			foreach ( $unpacked_contents as $folder ) {
				if ( ! is_file( $folder ) ) {
					$unpacked_folder = $folder;
				}
			}

			$unpacked_path = $base_dir . 'tmp/unpacked/' . $body->tag_name . '/' . $unpacked_folder;

			$repack = new ZipArchive();
			$repack->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $unpacked_path ),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $files as $name => $file ) {
				if ( ! $file->isDir() ) {
					$file_path     = $file->getRealPath();
					$relative_path = substr( $file_path, strlen( $unpacked_path ) + 1 );
					$repack->addFile( $file_path, $product->get_slug() . '/' . $relative_path );
				}
			}

			$repack->close();

		}

		header( 'content-disposition: attachment; filename=' . $product->get_slug() . '.zip' );

		//phpcs:disable
		echo file_get_contents($archive_path);

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

		$output = array(
			'name'         => 'Too bad.',
			'version'      => '0.0.1',
			'download_url' => '',
			'sections'     => array(
				'description' => 'Failed to get update.',
			),
		);

		$params = $request->get_params();
		$license_info = $params;

		if (!isset($params['plugin']) || empty($params['plugin'])) {
			return $output;
		}
		
		$product = wc_get_product( get_page_by_path( $params['plugin'], OBJECT, 'product' ) );

		if (is_wp_error($product) || !$product) {
			return $output;
			return new \WP_REST_Response( array( 'error' => 'The plugin can not be found.' ), 400 );
		}

		if ( ! isset( $params['license_key'] ) || ! isset( $params['site_url'] ) || ! isset( $params['plugin'] ) ) {
			return $output;
			return new \WP_REST_Response( array( 'error' => 'Missing parameters.' ), 400 );
		}

		if ( ! $this->validate_request( $license_info ) ) {
			return $output;
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request.' ), 400 );
		}

		$validate = $this->validate( $license_info );

		$api_url   = get_post_meta( $product->get_id(), '_updater_repo', true );
		$api_token = get_post_meta( $product->get_id(), '_updater_api_token', true );

		if (isset($params['tag']) && !empty($params['tag'])) {
			$latest      = Github_API::request( $api_url, $api_token, 'releases/tags/' . $params['tag'] );
			$latest_info = json_decode( wp_remote_retrieve_body( $latest ) );
		} else {
			
			$latest      = Github_API::request( $api_url, $api_token, 'releases/latest' );
			$latest_info = json_decode( wp_remote_retrieve_body( $latest ) );
		}

		$readme      = Github_API::request( $api_url, $api_token, 'readme' );
		if ( is_wp_error($readme) ) {
			return $output;
			return new \WP_REST_Response( array( 'error' => 'Cannot read readme.' ), 400 );	
		}
		$readme_body = json_decode( $readme['body'] );

		$readme_text = base64_decode( $readme_body->content );

		$download_url = '';
		if ( $validate ) {
			$download_url = get_rest_url() . 'license-updater/v1/download_update';
			$download_url = add_query_arg( $license_info, $download_url );
		}

		$output = array(
			'name'         => $product->get_name(),
			'version'      => $latest_info->tag_name,
			'download_url' => $download_url,
			'sections'     => array(
				'description' => $readme_text,
			),
		);

		return $output;
	}

	/**
	 * Rest callback for activation.
	 *
	 * @param object $request The request object.
	 */
	public function activate( $request ) {
		$activate     = false;
		$license_info = $request->get_params();

		switch ($license_info['action']) {
			case 'deactivate':
			$deactivate = Woocommerce_License_Updater::deactivate_license( $license_info );
			if (!$deactivate) {
					return new \WP_REST_Response( array( 'error' => 'Failed to deactivate license' ), 400 );	
			}
			break;

			case 'status':
			$license = lmfwc_get_license( $license_info['license_key'] );
			if ( ! $license ) {
					return new \WP_REST_Response( array( 'error' => 'License key does not exist.' ), 400 );	
			}
			$installs = lmfwc_get_license_meta( $license->getId(), 'installations', false );
			if ( ! in_array( $license_info['site_url'], $installs, true ) ) {
					return new \WP_REST_Response( array( 'error' => 'This website is not activated for this license.' ), 400 );	
			}
			break;

			default: 
			$activate = Woocommerce_License_Updater::activate_license( $license_info );
			if (!$activate) {
					return new \WP_REST_Response( array( 'error' => 'Failed to activate license' ), 400 );	
			}
			break;
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
