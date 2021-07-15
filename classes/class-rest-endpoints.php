<?php
namespace Premia;

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

		$endpoints = array(
			array(
				'name'     => 'check_updates',
				'callback' => array( $this, 'check_updates' ),
			),
			array(
				'name'     => 'download_update',
				'callback' => array( $this, 'download_update' ),
			),
			array(
				'name'     => 'activate',
				'callback' => array( $this, 'activate' ),
			),
			array(
				'name'     => 'deactivate',
				'callback' => array( $this, 'deactivate' ),
			),
		);

		foreach ( $endpoints as $endpoint ) {
			register_rest_route(
				'license-updater/v1',
				$endpoint['name'],
				array(
					'methods'             => array( 'GET', 'POST' ),
					'callback'            => $endpoint['callback'],
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/**
	 * Rest callback for deactivation.
	 *
	 * @param object $request The request object.
	 */
	public function activate( $request ) {
		return $this->manage_license( $request->get_params(), 'activate' );
	}

	/**
	 * Rest callback for activation.
	 *
	 * @param object $request The request object.
	 */
	public function deactivate( $request ) {
		return $this->manage_license( $request->get_params(), 'deactivate' );
	}

	/**
	 * Return a zip file.
	 *
	 * @param object $request The request object.
	 */
	public function download_update( $request ) {

		$license_info = $request->get_params();

		// If the user is not logged in and we can't validate the license_info, bail.
		if ( ! $this->validate_request( $license_info ) ) {
			Debug::log( 'Validate failed.', $license_info );
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request.' ), 400 );
		}

		if ( isset( $license_info['post_id'] ) ) {
			$post = get_post( intval( $license_info['post_id'] ) );
		} else {
			// Get the post that contains information about this download.
			$post = get_page_by_path( $license_info['plugin'], OBJECT, 'product' );
		}

		if ( is_wp_error( $post ) || $post === null ) {
			Debug::log( 'Error on $post.', $post );
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request.' ), 400 );
		}

		// Build the plugin file name from the slug.
		$plugin_file = $post->post_name . '.zip';

		// Validate the license info.
		$validate = $this->validate( $license_info );

		$do_not_validate = get_post_meta( $post->ID, '_updater_do_not_validate_licenses', true );

		// Can't validate? bail.
		if ( ! $validate && $do_not_validate !== 'on' ) {
			Debug::log( 'Failed validation.', array( $validate, $do_not_validate ) );
			return new \WP_REST_Response( array( 'error' => 'Validation failed.' ), 400 );
		}

		$github_data = Github::get_meta_data( $post->ID );

		// Can't authenticate? bail.
		if ( empty( $github_data['api_url'] ) || empty( $github_data['api_token'] ) ) {
			Debug::log( 'Missing github data.', $github_data );
			return new \WP_REST_Response( array( 'error' => 'No API URL and token provided.' ), 400 );
		}
		// Get the result.
		$result = Github::request( $github_data, '/releases/latest' );

		if ( is_wp_error( $result ) || wp_remote_retrieve_response_code( $result ) !== 200 ) {
			Debug::log( 'Failed to talk to Github.', array( $github_data, $result, wp_remote_retrieve_response_code( $result ) ) );
			return new \WP_REST_Response( array( 'error' => 'Failed to communicate with Github.' ), 400 );
		}

		$body    = json_decode( wp_remote_retrieve_body( $result ) );
		$version = $body->tag_name;

		Debug::log( 'Github response: ', $body );

		$base_dir = plugin_dir_path( dirname( __FILE__ ) );
		Compressor::prepare_directories( $base_dir, $version );

		// Set the download url.
		if ( is_array( $body->assets ) && ! empty( $body->assets ) ) {
			$release = reset( $body->assets );
			$zip_url = str_replace( $github_data['api_url'], '', $release->url );
		} else {
			$zip_url = str_replace( $github_data['api_url'], '', $body->zipball_url );
		}

		// Get the ZIP.
		$file_path = Compressor::download_zip( $github_data, $zip_url, $base_dir, $plugin_file );

		$archive_path = $base_dir . 'tmp/zip/' . $version . '/' . $plugin_file;

		// If the ZIP does not exist, generate it.
		// @todo Maybe check the modified time of the file, if older then (plugin settings option) then..
		// @todo use the file name of the github repo, not from WordPress post/product..
		if ( ! is_file( $archive_path ) || ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) ) {
			$archive_path = Compressor::generate_zip( $base_dir, $version, $archive_path, $post->post_name, $file_path );
		}

		// Set the correct headers.
		if ( file_exists( $archive_path ) ) {
			header( 'content-disposition: attachment; filename=' . $plugin_file );
		}

		// Bring back the ZIP!
		//phpcs:disable
		echo file_get_contents($archive_path);

		// Delete files
		Compressor::clean($base_dir . 'tmp');

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

		$license_info = $request->get_params();

		Debug::log('Check updates', $license_info);

		$output = array(
			'name'         => '',
			'version'      => '0.1',
			'download_url' => '',
			'sections'     => array(
				'description' => '',
			),
		);

		if (!isset($license_info['plugin']) || empty($license_info['plugin'])) {
			$output['name'] = 'No plugin provided';
			Debug::log('Missing plugin name');
		}

		if ( ! isset( $license_info['license_key'] ) || ! isset( $license_info['site_url'] ) || ! isset( $license_info['plugin'] ) ) {
			$output['name'] = 'License information incomplete.';
			Debug::log('Missing license key or site url');
		}

		if ( ! $this->validate_request( $license_info ) ) {
			$output['name'] = 'Cannot validate request.';
		}
		
		if ( isset( $license_info['plugin'] ) && !empty( $license_info['plugin'] ) ) { 

			$posts = get_posts(array(
				'post_type' => array('post', 'page', 'product'),
				'post_status' => 'publish',
				'name' => $license_info['plugin']
			));

			if (!is_array($posts) || empty($posts)) {
				$output['name'] = 'Plugin cannot be found.';
				Debug::log('No results for query. ', $posts);
			} else {
				$post = reset($posts);
				$output['name'] = $post->post_title;

				$github_data = Github::get_meta_data( $post->ID );

				Debug::log('Post ID: ' . $post->ID);
				Debug::log('Repo used for Github API: ' . $github_data['api_url']);

				if (isset($license_info['tag']) && !empty($license_info['tag'])) {
					$latest      = Github::request( $github_data, '/releases/tags/' . $license_info['tag'] );
				} else {
					$latest      = Github::request( $github_data, '/releases/latest' );
				}

				if ( ! is_wp_error($latest) && wp_remote_retrieve_response_code( $latest ) === 200 ) {
					$latest_info = json_decode( wp_remote_retrieve_body( $latest ) );
					$output['version'] = $latest_info->tag_name;
					$output['sections']['description'] = $latest_info->body;
				} else {
					$output['name'] = 'Failed to get the latest version information.';
					Debug::log('Failed to get the latest version information. Did you set the right token?', $latest);
				}

				if (empty($output['sections']['description'])) {
					$output['sections']['description'] = '<p>This release contains version '.$output['version'].' of the '.$output['name'].' plugin</p>';
				}

				$download_url = add_query_arg( $license_info, get_rest_url() . 'license-updater/v1/download_update' );
				$do_not_validate = get_post_meta($post->ID, '_updater_do_not_validate_licenses', true);

				if ($do_not_validate === 'on') {
					$output['download_url'] = $download_url;
				} else {
					$validate = $this->validate( $license_info );
					if ( $validate ) {
						$output['download_url'] = $download_url;
					}
				}

			}
		}

		return $output;
	}

	/**
	 * Validate the license by checking if the url is saved as meta for this license key.
	 *
	 * @return boolean true or false.
	 */
	public function validate( $license_info ) {
		if (!isset($license_info['license_key'])) {
			Debug::log('Cannot validate', $license_info);
			return false;
		}
		$license  = lmfwc_get_license( $license_info['license_key'] );

		if ($license !== false) {
			$installs = lmfwc_get_license_meta( $license->getId(), 'installations', false );
			if ( in_array( $license_info['site_url'], $installs, true ) || is_user_logged_in() ) {
				return true;
			} else {
				Debug::log('Cannot validate site', $license_info);
			}
		} else {
			Debug::log('Something went wrong while getting the license.', $license);
		}

		return false;
	}

	/**
	 * Rest callback for activation.
	 *
	 * @param object $request The request object.
	 */
	public function manage_license( $license_info, $action ) {

		$defaults = array(
			'license_key' => '',
			'site_url' => '',
			'action' => '',
		);

		$license_info = wp_parse_args($license_info, $defaults);

		switch ( $action ) {
			case 'deactivate':
				$deactivate = Licenses::deactivate( $license_info );
				if ( ! $deactivate ) {
					Debug::log( 'Failed to deactivate license', $license_info );
					return new \WP_REST_Response( array( 'error' => 'Failed to deactivate license' ), 400 );
				}
				break;

			case 'activate':
				$activate = Licenses::activate( $license_info );
				if ( ! $activate ) {
					Debug::log( 'Failed to activate license', $license_info );
					return new \WP_REST_Response( array( 'error' => 'Failed to activate license' ), 400 );
				}
				break;

			default:
				Debug::log( 'No action?', array( $action, $license_info ) );
				return new \WP_REST_Response( array( 'error' => 'No action provided.' ), 400 );
				break;
		}
		return $activate;
	}

	/**
	 * Validate that this request comes from WordPress
	 */
	public function validate_request( $license_info ) {

		$success = true;
		$do_not_validate = false;

		if ( isset( $license_info['post_id'] ) ) {
			$do_not_validate = get_post_meta($license_info['post_id'], '_updater_do_not_validate_licenses', true);
		}

		if ( ! is_user_logged_in() && $do_not_validate !== 'on') {

			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'WordPress' ) === false ) {
				$success = false;
				Debug::log('Can\'t verify if request came from WordPress.', $_SERVER['HTTP_USER_AGENT']);
			}

			if ( isset( $license_info['site_url']) && !empty( $license_info['site_url']) && ( isset($_SERVER['HTTP_USER_AGENT']) && strpos( $_SERVER['HTTP_USER_AGENT'], $license_info['site_url'] ) === false) ) {
				$success = false;
				Debug::log('Can\'t verify if request came from website.', array($_SERVER['HTTP_USER_AGENT'], $license_info['site_url']));
			}
			
		}

		return $success;
	}
}
