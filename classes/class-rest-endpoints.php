<?php
/**
 * Rest endpoints
 *
 * @package Premia
 * @since 1.0
 */

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
		);

		foreach ( $endpoints as $endpoint ) {
			register_rest_route(
				'license-updater/v1',
				$endpoint['name'],
				array(
					'methods'             => array( 'GET', 'POST' ),
					'callback'            => $endpoint['callback'],
					'permission_callback' => array( $this, 'validate_request' ),
				)
			);
			register_rest_route(
				'premia/v1',
				$endpoint['name'],
				array(
					'methods'             => array( 'GET', 'POST' ),
					'callback'            => $endpoint['callback'],
					'permission_callback' => array( $this, 'validate_request' ),
				)
			);
		}
	}

	/**
	 * Return a zip file.
	 *
	 * @param object $request The request object.
	 */
	public function download_update( $request ) {

		$post_id = self::get_plugin_post_id( $request );

		if ( is_wp_error( $post_id ) ) {
			return new \WP_REST_Response( array( 'error' => $post_id->get_error_message() ), 400 );
		}

		// Latest release information.
		$latest_release      = get_post_meta( $post_id, '_premia_latest_release_version', true );
		$latest_release_path = get_post_meta( $post_id, '_premia_latest_release_path', true );

		// Validate the license info.
		// @todo - should not be here.
		$result = $this->validate( $request );

		// Can't validate? bail.
		if ( is_wp_error( $result ) ) {
			Debug::log( 'Failed validation.', $result );
			return new \WP_REST_Response( array( 'error' => 'Validation failed.' . $result->get_error_message() ), 400 );
		}

		// Set post.
		$post = get_post( $post_id );

		// Get Github data.
		$github_data = Github::get_meta_data( $post_id );

		// Get or update transient.
		$transient_key = '_premia_latest_release_info_' . $post_id;
		$data          = get_transient( $transient_key );
		if ( false === $data ) {
			$data = array();

			// Get the result.
			// @todo - Specific tag should be possible here?
			$result = Github::request( $github_data, '/releases/latest' );

			if ( is_wp_error( $result ) || wp_remote_retrieve_response_code( $result ) !== 200 ) {
				Debug::log( 'Failed to talk to Github.', array( $github_data, $result, wp_remote_retrieve_response_code( $result ) ) );
				return new \WP_REST_Response( array( 'error' => 'Failed to communicate with Github.' ), 400 );
			}
			$data = json_decode( wp_remote_retrieve_body( $result ) );
			set_transient( $transient_key, $data, 3600 );
		}

		$version = $data->tag_name;

		$directories = File_Directory::prepare_directories( $post->post_name, $version );

		// Set the download url.
		if ( is_array( $data->assets ) && ! empty( $data->assets ) ) {
			$release = reset( $data->assets );
			$zip_url = str_replace( $github_data['api_url'], '', $release->url );
		} else {
			return new \WP_REST_Response( array( 'error' => 'No assets found.' ), 400 );
		}

		// Check if new releases are published.
		$redownload = false;
		if ( $latest_release !== $version ) {
			$redownload = true;
			Debug::log(
				'Updated release detected.',
				array(
					'cached_release' => $latest_release,
					'new_release'    => $version,
				),
				2
			);
		}

		// Double check to see if files exist.
		if ( empty( $latest_release_path ) || ! file_exists( $latest_release_path ) ) {
			$redownload = true;
			Debug::log( 'Unknown cached release.', $latest_release, 2 );
		}

		// Always get latest release while debugging.
		if ( defined( 'PREMIA_NOCACHE' ) && false !== PREMIA_NOCACHE ) {
			$redownload = true;
		}

		// Redownload.
		if ( true === $redownload ) {
			Debug::log( 'Downloading latest release.', false, 2 );

			// Get the ZIP.
			$file_path = Github::download_asset( $github_data, $zip_url, $directories['base_dir'] . $directories['current_release'] );

			update_post_meta( $post->ID, '_premia_latest_release_path', $file_path );
			update_post_meta( $post->ID, '_premia_latest_release_version', $version );
			$latest_release_path = $file_path;
		} else {
			Debug::log( 'Using cached file.', $latest_release_path, 2 );
		}

		// Protected file check.
		File_Directory::is_protected_file( $directories['current_release'] . basename( $latest_release_path ) );

		Debug::log( 'Premia will serve this file: ', $latest_release_path, 2 );

		// Double check on existence before serving the file.
		if ( ! file_exists( $latest_release_path ) ) {
			Debug::log( 'The file could not be found. ', $latest_release_path, 2 );
			return new \WP_REST_Response( array( 'error' => 'An error occured while retrieving the file.' ), 400 );
		}

		// Set the correct headers.
		header( 'content-disposition: attachment; filename=' . basename( $latest_release_path ) );

		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		//phpcs:ignore
		echo $wp_filesystem->get_contents( $latest_release_path );

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

		Debug::log( 'Check updates' );

		$output = array(
			'name'         => '',
			'version'      => '',
			'download_url' => '',
			'sections'     => array(
				'description' => '',
			),
		);

		$post_id = self::get_plugin_post_id( $request );

		if ( is_wp_error( $post_id ) ) {
			return new \WP_REST_Response( array( 'error' => $post_id->get_error_message() ), 400 );
		}

		if ( ! is_wp_error( $post_id ) ) {

			$output['name'] = get_the_title( $post_id );

			$github_data = Github::get_meta_data( $post_id );

			Debug::log( 'Post ID: ' . $post_id );
			Debug::log( 'Repo used for Github API: ' . $github_data['api_url'] );

			$version = 'latest';

			if ( ! empty( $request->get_param( 'tag' ) ) ) {
				$version = $request->get_param( 'tag' );
			}

			$release_data = Github::get_release_data( $github_data, $version );

			$output['version']               = $release_data['version'];
			$output['sections']['changelog'] = $release_data['changelog'];
			$output['last_updated']          = $release_data['published_at'];

			// @todo: Should be logo.
			$output['icons']['2x']     = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ) );
			$output['icons']['1x']     = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ) );
			$output['banners']['high'] = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
			$output['banners']['low']  = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'large' );

			// Extra params: required_php, tested, requires, active_installs, api, slug, donate_link, rating, num_ratings, contributors.
			$output['author'] = '<a href="' . get_site_url() . '">' . get_bloginfo( 'name' ) . '</a>';

			$download_url           = add_query_arg( $request->get_params(), get_rest_url() . 'premia/v1/download_update' );
			$output['download_url'] = $download_url;
		}

		Debug::log( 'Check updates answer: ', $output );

		return apply_filters( 'premia_customize_update_info', $output, $request->get_params(), $post_id );
	}

	/**
	 * Validate the license by checking if the url is saved as meta for this license key.
	 *
	 * @param object $request WP_Rest_Request object.
	 *
	 * @return boolean true or false.
	 */
	public function validate( $request ) {
		return apply_filters( 'premia_validate', true, $request );
	}

	/**
	 * Validate that this request comes from WordPress
	 *
	 * @param object $request The WP_Request object.
	 *
	 * @return bool Validation passed?
	 */
	public static function validate_request( $request ) {

		$params = $request->get_params();

		$success = true;

		// Filter can be used to change validation state.
		return apply_filters( 'premia_validate_request', $success, $params );
	}

	/**
	 * Get plugin information
	 * Retrieves all information from a plugin, based on passed parameters.
	 *
	 * @param object $request The WP_REST_Request object.
	 *
	 * @return mixed int or WP_Error object.
	 */
	public static function get_plugin_post_id( $request ) {

		$post_id = false;

		// If an ID is provided.
		if ( ! empty( $request->get_param( 'post_id' ) ) ) {
			$post_id = intval( $request->get_param( 'post_id' ) );
		}

		// Search by name.
		if ( false === $post_id && ! empty( $request->get_param( 'plugin' ) ) ) {
			// Get the post that contains information about this download.
			$posts = get_posts(
				array(
					'post_type'   => 'any',
					'name'        => $request->get_param( 'plugin' ),
					'post_status' => 'publish',
				)
			);
			if ( is_array( $posts ) && ! empty( $posts ) ) {
				$post = reset( $posts );
				if ( ! is_wp_error( $post ) ) {
					$post_id = $post->ID;
				}
			}
		}

		if ( false === $post_id ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Resource not found.', 'premia' ),
				array( 'status' => 404 )
			);
		}

		$github_data = Github::get_meta_data( $post_id );
		// Check if post is configured with Premia.
		if ( ! isset( $github_data['api_url'] ) || empty( $github_data['api_url'] ) ) {
			return new \WP_Error(
				'rest_error',
				__( 'Cannot find Premia configuration.', 'premia' ),
				array( 'status' => 404 )
			);
		}

		return $post_id;
	}
}
