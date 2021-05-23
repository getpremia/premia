<?php
namespace Premia;

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

		register_rest_route(
			'license-updater/v1',
			'deactivate',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'deactivate' ),
				'permission_callback' => '__return_true',
			)
		);
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
	 * Rest callback for activation.
	 *
	 * @param object $request The request object.
	 */
	public function manage_license( $license_info, $action ) {

		switch ( $action ) {
			case 'deactivate':
				$deactivate = Licenses::deactivate( $license_info );
				if ( ! $deactivate ) {
					return new \WP_REST_Response( array( 'error' => 'Failed to deactivate license' ), 400 );
				}
				break;

			case 'activate':
				$activate = Licenses::activate( $license_info );
				if ( ! $activate ) {
					return new \WP_REST_Response( array( 'error' => 'Failed to activate license' ), 400 );
				}
				break;

			default:
				return new \WP_REST_Response( array( 'error' => 'No action provided.' ), 400 );
				// $license = Licenses::get_license( $license_info );
				// if ( ! $license ) {
				// return new \WP_REST_Response( array( 'error' => 'License key does not exist.' ), 400 );
				// }
				// $installs = lmfwc_get_license_meta( $license->getId(), 'installations', false );
				// if ( ! in_array( $license_info['site_url'], $installs, true ) ) {
				// return new \WP_REST_Response( array( 'error' => 'This website is not activated for this license.' ), 400 );
				// }
				break;
		}
		return $activate;
	}

	public function get_github_data( $post_id ) {

		$data = array();
		// Get Github information from post.
		$data['api_url']   = get_post_meta( $post_id, '_updater_repo', true );
		$data['api_token'] = get_post_meta( $post_id, '_updater_api_token', true );

		return $data;
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
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request.' ), 400 );
		}

		if ( is_user_logged_in() && isset( $license_info['post_id'] ) ) {
			$post = get_post( intval( $license_info['post_id'] ) );
		} else {
			// Get the post that contains information about this download.
			$post = get_page_by_path( $license_info['plugin'], OBJECT, 'product' );
		}

		if ( is_wp_error( $post ) ) {
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request.' ), 400 );
		}

		// Build the plugin file name from the slug.
		$plugin_file = $post->post_name . '.zip';

		// Validate the license info.
		$validate = $this->validate( $license_info );

		$do_not_validate = get_post_meta( $post->ID, '_updater_do_not_validate_licenses', true );

		// Can't validate? bail.
		if ( ! $validate && $do_not_validate !== 'on' ) {
			return new \WP_REST_Response( array( 'error' => 'Validation failed.' ), 400 );
		}

		$github_data = $this->get_github_data( $post->ID );

		// Can't authenticate? bail.
		if ( empty( $github_data['api_url'] ) || empty( $github_data['api_token'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'No API URL and token provided.' ), 400 );
		}
		// Get the result.
		$result = Github_API::request( $github_data, '/releases/latest' );

		if ( is_wp_error( $result ) || wp_remote_retrieve_response_code( $result ) !== 200 ) {
			return new \WP_REST_Response( array( 'error' => 'Failed to communicate with Github.' ), 400 );
		}

		$body    = json_decode( wp_remote_retrieve_body( $result ) );
		$version = $body->tag_name;

		$base_dir = plugin_dir_path( dirname( __FILE__ ) );
		$this->prepare_directories( $base_dir, $version );

		// Get the ZIP.
		$zip_url   = str_replace( $github_data['api_url'], '', $body->zipball_url );
		$file_path = $this->download_zip( $github_data, $zip_url, $base_dir );

		$archive_path = $base_dir . 'tmp/zip/' . $version . '/' . $plugin_file;

		// If the ZIP does not exist, generate it.
		// @todo Maybe check the modified time of the file, if older then (plugin settings option) then..
		// @todo use the file name of the github repo, not from WordPress post/product..
		if ( ! is_file( $archive_path ) || ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) ) {
			$archive_path = $this->generate_zip( $base_dir, $version, $archive_path, $post->post_name, $file_path );
		}

		// Set the correct headers.

		if ( file_exists( $archive_path ) ) {
			header( 'content-disposition: attachment; filename=' . $plugin_file );
		}

		// Bring back the ZIP!
		//phpcs:disable
		echo file_get_contents($archive_path);

		exit();
	}

	public function download_zip($github_data, $path, $base_dir) {
		$zip = Github_API::request( $github_data, $path );

		if ( is_wp_error( $zip ) ) {
			return new \WP_REST_Response( array( 'error' => 'Zip is invalid.' ), 400 );
		}

		$parts    = explode( 'filename=', $zip['headers']['content-disposition'] );
		$zip_name = $parts[1];

		$file_path = $base_dir . 'tmp/zip/' . $zip_name;

		// Save the zip file.
		file_put_contents( $file_path, $zip['body'] );

		return $file_path;
	}

	public function generate_zip($base_dir, $version, $archive_path, $plugin_name, $file_path) {

		// This is where magic unpack and repacking happens.

		$extract_path = $base_dir . 'tmp/unpacked/' . $version;

		// Extract the zip file.
		$package = new ZipArchive();
		$package->open( $file_path );
		$package->extractTo( $extract_path );

		// Scan the unpacked contents
		$unpacked_contents = scandir( $base_dir . 'tmp/unpacked/' . $version );

		// Get the name of the unpacked folder.
		foreach ( $unpacked_contents as $folder ) {
			if ( ! is_file( $folder ) ) {
				$unpacked_folder = $folder;
			}
		}

		// Full path to unpacked folder.
		$unpacked_path = $base_dir . 'tmp/unpacked/' . $version . '/' . $unpacked_folder;

		// Create a new zip file.
		$repack = new ZipArchive();
		$repack->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		$dir = $unpacked_path;
		$it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new \RecursiveIteratorIterator($it,
					\RecursiveIteratorIterator::CHILD_FIRST);

		// Add the files to the repack.
		foreach ( $files as $name => $file ) {
			if ( ! $file->isDir() ) {
				// Get actual path
				$file_to_add = $file->getRealPath();

				// Somehow generate the relative path.
				$relative_path = substr( $file_to_add, strlen( $unpacked_path ) + 1 );

				$zip_file_path = $plugin_name . '/' . $relative_path;

				// Add the files but slice in a subfolder.
				$repack->addFile( $file_to_add, $zip_file_path );
			}
		}

		// Close the ZIP.
		$repack->close();

		/**
		 * Clean up
		 */

		// Remove original package
		unlink($file_path);

		// Remove files and directories
		foreach($files as $file) {
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}

		// Remove subdir
		rmdir($dir);
		
		// Remove vrsion dirr
		rmdir($extract_path);

		return $archive_path;
	}

	public function prepare_directories($base_dir, $version) {
	
		if ( ! is_dir( $base_dir . 'tmp' ) ) {
			mkdir( $base_dir . 'tmp/' );
		}

		if ( ! is_dir( $base_dir . 'tmp/zip' ) ) {
			mkdir( $base_dir . 'tmp/zip' );
		}

		if ( ! is_dir( $base_dir . 'tmp/unpacked' ) ) {
			mkdir( $base_dir . 'tmp/unpacked' );
		}	

		if ( ! is_dir( $base_dir . 'tmp/unpacked/' . $version ) ) {
			mkdir( $base_dir . 'tmp/unpacked/' . $version );
		}

		if ( ! is_dir( $base_dir . 'tmp/zip/' . $version ) ) {
			mkdir( $base_dir . 'tmp/zip/' . $version );
		}
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

		// Always show that there's new updates.
		//$version = implode('.', str_split(floatval(str_replace('.', '', $license_info['installed_version'])) + 1));

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
		
		if (isset($license_info['plugin']) && !empty($license_info['plugin'])) { 
	
			$posts = get_post(array(
				'post_type' => array('post', 'page', 'product'),
				'post_status' => 'publish',
				'post_name' => $license_info['plugin']
			));

			$post = reset($posts);

			if (is_wp_error($post) || !$post) {
				$output['name'] = 'Plugin cannot be found.';
				Debug::log('wp error on $post', $post);
			} else {
				$output['name'] = $post->post_title;

				$github_data = $this->get_github_data( $post->ID );

				if (isset($license_info['tag']) && !empty($license_info['tag'])) {
					$latest      = Github_API::request( $github_data, '/releases/tags/' . $license_info['tag'] );
				} else {
					$latest      = Github_API::request( $github_data, '/releases/latest' );
				}

				if ( ! is_wp_error($latest) && wp_remote_retrieve_response_code( $latest ) === 200 ) {
					$latest_info = json_decode( wp_remote_retrieve_body( $latest ) );
					$output['version'] = $latest_info->tag_name;
					$output['sections']['description'] = $latest_info->body;
				} else {
					$output['name'] = 'Failed to get the latest version information.';
					Debug::log('Failed to get the latest version information', $latest);
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
	 * Validate that this request comes from WordPress
	 */
	public function validate_request( $license_info ) {

		$success = true;

		if ( !is_user_logged_in(  )) {

			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'WordPress' ) === false ) {
				$success = false;
				Debug::log('Can\'t verify if request came from WordPress.', $_SERVER['HTTP_USER_AGENT']);
			}

			if ( isset($license_info['site_url']) && strpos( $_SERVER['HTTP_USER_AGENT'], $license_info['site_url'] ) === false ) {
				$success = false;
				Debug::log('Can\'t verify if request came from website.', array($_SERVER['HTTP_USER_AGENT'], $license_info['site_url']));
			}
			
		}

		return $success;
	}
}
