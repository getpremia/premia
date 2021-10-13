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

		$data = array();
		// Get Github information from post.
		$data['api_url']   = get_post_meta( $post_id, '_updater_repo', true );
		$data['api_token'] = get_post_meta( $post_id, '_updater_api_token', true );

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
		Debug::log( 'Downloading asset from: ', $path, 2 );
		$zip = self::request( $github_data, $path, array(), 'zip' );

		if ( is_wp_error( $zip ) ) {
			Debug::log( 'Error: invalid response.', $zip );
			return new \WP_REST_Response( array( 'error' => 'Zip is invalid.' ), 400 );
		}

		$parts    = explode( 'filename=', $zip['headers']['content-disposition'] );
		$zip_name = $parts[1];

		$file_path = $target . $zip_name;

		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require ABSPATH . '/wp-admin/includes/class-wp-filesystem-base.php';
			require ABSPATH . '/wp-admin/includes/class-wp-filesystem-direct.php';
		}
		$fs = new \WP_Filesystem_Direct( null );

		// Save the zip file.
		$fs->put_contents( $file_path, $zip['body'] );

		return $file_path;
	}
}
