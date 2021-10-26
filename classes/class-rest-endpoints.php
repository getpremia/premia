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
			register_rest_route(
				'premia/v1',
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

		$post = false;

		// Check if requests comes from user, or from WordPress dashboard.
		if ( ! $this->validate_request( $license_info ) ) {
			Debug::log( 'Validate failed.', $license_info );
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request.' ), 400 );
		}

		if ( isset( $license_info['post_id'] ) ) {
			$post = get_post( intval( $license_info['post_id'] ) );
		} else {
			// Get the post that contains information about this download.
			$posts = get_posts(
				array(
					'post_type'   => 'any',
					'name'        => $license_info['plugin'],
					'post_status' => 'publish',
				)
			);
			if ( is_array( $posts ) && ! empty( $posts ) ) {
				$post = reset( $posts );
				if ( ! is_wp_error( $post ) ) {
					$license_info['post_id'] = $post->ID;
				}
			}
		}

		if ( is_wp_error( $post ) || null === $post || false === $post ) {
			Debug::log( 'Error on $post.', $post );
			Debug::log( 'License info:', $license_info );
			return new \WP_REST_Response( array( 'error' => 'Cannot fulfill this request (missing object information).' ), 400 );
		}

		// Check if license is expired.
		if ( Licenses::license_is_expired( $license_info ) ) {
			return new \WP_REST_Response( array( 'error' => 'License is expired.' ), 400 );
		}

		// Latest release information.
		$latest_release      = get_post_meta( $post->ID, '_premia_latest_release_version', true );
		$latest_release_path = get_post_meta( $post->ID, '_premia_latest_release_path', true );

		// Validate the license info.
		$validate = $this->validate( $license_info );

		// Can't validate? bail.
		if ( ! $validate ) {
			Debug::log( 'Failed validation.', $validate );
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

		Debug::log( 'Github response: ', $body, 3 );

		$base_dir    = trailingslashit( apply_filters( 'premia_plugin_assets_download_path', plugin_dir_path( dirname( __FILE__ ) ) ) );
		$directories = File_Directory::prepare_directories( $base_dir, $post->post_name, $version );

		// Set the download url.
		if ( is_array( $body->assets ) && ! empty( $body->assets ) ) {
			$release = reset( $body->assets );
			$zip_url = str_replace( $github_data['api_url'], '', $release->url );
		} else {
			return new \WP_REST_Response( array( 'error' => 'No assets found.' ), 400 );
		}

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

		if ( empty( $latest_release_path ) || ! file_exists( $latest_release_path ) ) {
			$redownload = true;
			Debug::log( 'Unknown cached release.', $latest_release, 2 );
		}

		if ( defined( 'PREMIA_DEBUG' ) ) {
			$redownload = true;
		}

		if ( $redownload ) {
			Debug::log( 'Downloading latest release.', false, 2 );

			// Get the ZIP.
			$file_path = Github::download_asset( $github_data, $zip_url, $base_dir . $directories['current_release'] );

			update_post_meta( $post->ID, '_premia_latest_release_path', $file_path );
			update_post_meta( $post->ID, '_premia_latest_release_version', $version );
			$latest_release_path = $file_path;
		} else {
			Debug::log( 'Using cached file.', $latest_release_path, 2 );
		}

		File_Directory::is_protected_file( $directories['current_release'] . basename( $latest_release_path ) );

		// Set the correct headers.
		if ( file_exists( $latest_release_path ) ) {
			header( 'content-disposition: attachment; filename=' . basename( $latest_release_path ) );
		}

		Debug::log( 'Premia will serve this file: ', $latest_release_path, 2 );

		// Bring back the ZIP!
		//phpcs:ignore
		echo file_get_contents( $latest_release_path );

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

		$post_id      = false;
		$license_info = $request->get_params();

		Debug::log( 'Check updates', $license_info );

		$output = array(
			'name'         => '',
			'version'      => '',
			'download_url' => '',
			'sections'     => array(
				'description' => '',
			),
		);

		if ( ! isset( $license_info['plugin'] ) || empty( $license_info['plugin'] ) ) {
			$output['name'] = 'No plugin provided';
			Debug::log( 'Missing plugin name' );
		}

		if ( ! isset( $license_info['license_key'] ) || ! isset( $license_info['site_url'] ) || ! isset( $license_info['plugin'] ) ) {
			$output['name'] = 'License information incomplete.';
			Debug::log( 'Missing license key or site url' );
		}

		if ( ! $this->validate_request( $license_info ) ) {
			$output['name'] = 'Cannot validate request.';
		}

		if ( isset( $license_info['plugin'] ) && ! empty( $license_info['plugin'] ) ) {

			$posts = get_posts(
				array(
					'post_type'   => 'any',
					'post_status' => 'publish',
					'name'        => $license_info['plugin'],
				)
			);

			if ( ! is_array( $posts ) || empty( $posts ) ) {
				$output['name'] = 'Plugin cannot be found.';
				Debug::log( 'No results for query. ', $posts );
			} else {
				$post           = reset( $posts );
				$post_id        = $post->ID;
				$output['name'] = $post->post_title;

				$github_data = Github::get_meta_data( $post->ID );

				Debug::log( 'Post ID: ' . $post->ID );
				Debug::log( 'Repo used for Github API: ' . $github_data['api_url'] );

				if ( isset( $license_info['tag'] ) && ! empty( $license_info['tag'] ) ) {
					$latest = Github::request( $github_data, '/releases/tags/' . $license_info['tag'] );
				} else {
					$latest = Github::request( $github_data, '/releases/latest' );
				}

				if ( ! is_wp_error( $latest ) && wp_remote_retrieve_response_code( $latest ) === 200 ) {
					$latest_info                     = json_decode( wp_remote_retrieve_body( $latest ) );
					$output['version']               = $latest_info->tag_name;
					$parsedown                       = new \Parsedown();
					$output['sections']['changelog'] = preg_replace( '/<h\d.*?>(.*?)<\/h\d>/ims', '<h4>$1</h4>', $parsedown->text( $latest_info->body ) );
				} else {
					$output['name'] = 'Failed to get the latest version information.';
					Debug::log( 'Failed to get the latest version information. Did you set the right token?', $latest );
				}

				if ( empty( $output['sections']['changelog'] ) ) {
					$output['sections']['changelog'] = '<p>This release contains version ' . $output['version'] . ' of the ' . $output['name'] . ' plugin</p>';
				}

				// @todo: Should be logo.
				$output['icons']['2x']     = wp_get_attachment_image_url( get_post_thumbnail_id( $post->ID ) );
				$output['icons']['1x']     = wp_get_attachment_image_url( get_post_thumbnail_id( $post->ID ) );
				$output['banners']['high'] = wp_get_attachment_image_url( get_post_thumbnail_id( $post->ID ), 'full' );
				$output['banners']['low']  = wp_get_attachment_image_url( get_post_thumbnail_id( $post->ID ), 'large' );

				if ( isset( $latest_info ) && property_exists( $latest_info, 'published_at' ) ) {
					$output['last_updated'] = $latest_info->published_at;
				}

				// Extra params: required_php, tested, requires, active_installs, api, slug, donate_link, rating, num_ratings, contributors.
				$output['author'] = '<a href="' . get_site_url() . '">' . get_bloginfo( 'name' ) . '</a>';

				$download_url    = add_query_arg( $license_info, get_rest_url() . 'premia/v1/download_update' );
				$do_not_validate = get_post_meta( $post->ID, '_updater_do_not_validate_licenses', true );

				if ( 'on' === $do_not_validate ) {
					$output['download_url'] = $download_url;
				} else {
					$validate = $this->validate( $license_info );
					if ( $validate ) {
						$output['download_url'] = $download_url;
					}
				}
			}
		}

		Debug::log( 'Check updates answer: ', $output );

		return apply_filters( 'premia_customize_update_info', $output, $license_info, $post_id );
	}

	/**
	 * Validate the license by checking if the url is saved as meta for this license key.
	 *
	 * @param array $license_info Array of license information.
	 *
	 * @return boolean true or false.
	 */
	public function validate( $license_info ) {
		return Licenses::validate_site( $license_info );
	}

	/**
	 * Rest callback for activation.
	 *
	 * @param array  $license_info Array of license information.
	 * @param string $action The intended action.
	 */
	public function manage_license( $license_info, $action ) {

		$defaults = array(
			'license_key' => '',
			'site_url'    => '',
			'action'      => '',
		);

		$license_info = wp_parse_args( $license_info, $defaults );

		switch ( $action ) {
			case 'deactivate':
				Debug::log( 'Deactivate license', $license_info );
				$result = Licenses::deactivate( $license_info );
				if ( ! $result ) {
					Debug::log( 'Failed to deactivate license', $license_info );
					return new \WP_REST_Response( array( 'error' => 'Failed to deactivate license' ), 400 );
				}
				break;

			case 'activate':
				Debug::log( 'Activate license', $license_info );
				$result = Licenses::activate( $license_info );
				if ( ! $result ) {
					Debug::log( 'Failed to activate license', $license_info );
					return new \WP_REST_Response( array( 'error' => 'Failed to activate license' ), 400 );
				}
				break;

			default:
				Debug::log( 'No action?', array( $action, $license_info ) );
				return new \WP_REST_Response( array( 'error' => 'No action provided.' ), 400 );
		}
	}

	/**
	 * Validate that this request comes from WordPress
	 *
	 * @param array $license_info An array with license information.
	 */
	public function validate_request( $license_info ) {

		$success         = true;
		$do_not_validate = false;

		if ( isset( $license_info['post_id'] ) ) {
			$do_not_validate = get_post_meta( $license_info['post_id'], '_updater_do_not_validate_licenses', true );
		}

		if ( ! is_user_logged_in() && 'on' !== $do_not_validate ) {

			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 'WordPress' ) === false ) {
				$success = false;
				Debug::log( 'Can\'t verify if request came from WordPress.', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
			}

			if ( isset( $license_info['site_url'] ) && ! empty( $license_info['site_url'] ) && ( isset( $_SERVER['HTTP_USER_AGENT'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), $license_info['site_url'] ) === false ) ) {
				$success = false;
				Debug::log( 'Can\'t verify if request came from website.', array( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), $license_info['site_url'] ) );
			}
		}

		return $success;
	}
}
