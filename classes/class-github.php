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

		Debug::log( 'Answer: ', wp_remote_retrieve_body( $request ) );

		return $request;
	}

	public static function get_meta_data( $post_id ) {

		$data = array();
		// Get Github information from post.
		$data['api_url']   = get_post_meta( $post_id, '_updater_repo', true );
		$data['api_token'] = get_post_meta( $post_id, '_updater_api_token', true );

		return $data;
	}
}
