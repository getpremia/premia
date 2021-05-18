<?php
namespace Premia;

/**
 * Github API Class

 * @since 1.0
 */
class Github_API {
	/**
	 * Send a request to Github.
	 *
	 * @param string $api_url The endpoint to request to.
	 * @param string $api_token The token to use.
	 * @param string $url The path to rewuest to.
	 * @param array  $args a collection of arguments for wp_remote_get.
	 */
	public static function request( $data, $url, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $data['api_token'],
				),
			)
		);

		return wp_remote_get( $data['api_url'] . $url, $args );
	}
}
