<?php
namespace Premia;

/**
 * Github API Class

 * @since 1.0
 */
class Github {
	/**
	 * Send a request to Github.
	 *
	 * @param string $api_url The endpoint to request to.
	 * @param string $api_token The token to use.
	 * @param string $url The path to rewuest to.
	 * @param array  $args a collection of arguments for wp_remote_get.
	 * @param string $download_type ZIP or something else?
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

		if ( $download_type === 'zip' && strpos( $url, '/assets/' ) !== false ) {
			$args['headers']['accept'] = 'application/octet-stream';
		}

		$url = $data['api_url'] . $url;

		Debug::log( 'Executing request to: ' . $url, $args );

		$request = wp_remote_get( $url, $args );

		Debug::log( 'Answer: ', wp_remote_retrieve_body( $request ), 2 );

		return $request;
	}

	public static function get_meta_data( $post_id ) {

		$data = array();
		// Get Github information from post.
		$data['api_url']   = get_post_meta( $post_id, '_updater_repo', true );
		$data['api_token'] = get_post_meta( $post_id, '_updater_api_token', true );

		return $data;
	}

	/**
	 * Download a ZIP file
	 */
	public static function download_asset( $github_data, $path, $base_dir, $plugin_file ) {
		$zip = self::request( $github_data, $path, array(), 'zip' );

		if ( is_wp_error( $zip ) ) {
			Debug::log( 'Zip is not valid?', $zip );
			return new \WP_REST_Response( array( 'error' => 'Zip is invalid.' ), 400 );
		}

		$parts = explode( 'filename=', $zip['headers']['content-disposition'] );
		if ( is_array( $parts ) && isset( $parts[1] ) ) {
			$zip_name = $parts[1];
		} else {
			$zip_name = $plugin_file;
		}

		$file_path = $base_dir . 'tmp/zip/' . $zip_name;

		// Save the zip file.
		file_put_contents( $file_path, $zip['body'] );

		return $file_path;
	}
}
