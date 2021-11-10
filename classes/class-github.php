<?php
/**
 * Github
 *
 * @package Premia
 *
 * @since 1.0
 */

namespace Premia;

/**
 * Github API Class

 * @since 1.0
 */
class Github {
	/**
	 * Send a request to Github.
	 *
	 * @param string $data API information.
	 * @param string $url The path to rewuest to.
	 * @param array  $args a collection of arguments for wp_remote_get.
	 * @param string $download_type ZIP or something else?.
	 */
	public static function request( $data, $url, $args = array(), $download_type = false ) {

		$args = wp_parse_args(
			$args,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $data['api_token'],
				),
			)
		);

		if ( 'zip' === $download_type && false !== strpos( $url, '/assets/' ) ) {
			$args['headers']['accept'] = 'application/octet-stream';
		}

		$url = $data['api_url'] . $url;

		Debug::log( 'Executing request to: ' . $url, $args );

		$request = wp_remote_get( $url, $args );

		Debug::log( 'Answer: ', wp_remote_retrieve_body( $request ), 3 );

		return $request;
	}

	/**
	 * Get meta data
	 *
	 * @param int $post_id The post ID.
	 * @return array of api information.
	 */
	public static function get_meta_data( $post_id ) {

		$data = array(
			'api_url'   => '',
			'api_token' => '',
		);

		// Get Github information from post.
		$data['api_url']   = get_post_meta( $post_id, '_updater_repo', true );
		$data['api_token'] = get_post_meta( $post_id, '_updater_api_token', true );

		return $data;
	}

	/**
	 * Get release data
	 *
	 * @param array  $github_data Array with Github information.
	 * @param string $version The version requested.
	 * @return array Array of release information.
	 */
	public static function get_release_data( $github_data, $version ) {

		$data = array(
			'version'      => '',
			'changelog'    => '',
			'published_at' => '',
			'id'           => '',
		);

		$request     = self::request( $github_data, ( 'latest' === $version ) ? '/releases/latest' : '/releases/tags/' . $version );
		$latest_info = json_decode( wp_remote_retrieve_body( $request ) );
		if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) === 200 ) {
			$parsedown = new \Parsedown();

			$data['version']      = $latest_info->tag_name;
			$data['changelog']    = preg_replace( '/<h\d.*?>(.*?)<\/h\d>/ims', '<h4>$1</h4>', $parsedown->text( $latest_info->body ) );
			$data['published_at'] = $latest_info->published_at;
			$data['id']           = $latest_info->id;
			if ( empty( $data['changelog'] ) ) {
				$data['changelog'] = '<p>This release contains version ' . $data['version'] . '.</p>';
			}
		} else {
			Debug::log( 'Failed to get the latest version information. Did you set the right token?', $latest_info );
		}
		return $data;
	}

	/**
	 * Download a ZIP file
	 *
	 * @param array  $github_data The api information.
	 * @param string $path The remote URL.
	 * @param string $target The local path.
	 */
	public static function download_asset( $github_data, $path, $target ) {
		Debug::log( 'Downloading asset from: ' . $path, 2 );
		$zip = self::request( $github_data, $path, array(), 'zip' );

		if ( is_wp_error( $zip ) ) {
			Debug::log( 'Error: invalid response.', $zip );
			return new \WP_REST_Response( array( 'error' => 'Zip is invalid.' ), 400 );
		}

		$parts    = explode( 'filename=', $zip['headers']['content-disposition'] );
		$zip_name = $parts[1];

		$file_path = $target . $zip_name;

		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		Debug::log( 'Saving file to: ' . $file_path, null, 2 );
		// Save the zip file.
		$wp_filesystem->put_contents( $file_path, $zip['body'] );

		return $file_path;
	}
}
