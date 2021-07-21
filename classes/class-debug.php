<?php
namespace Premia;

/**
 * Github API Class

 * @since 1.0
 */
class Debug {
	/**
	 * Send a request to Github.
	 *
	 * @param string $api_url The endpoint to request to.
	 * @param string $api_token The token to use.
	 * @param string $url The path to rewuest to.
	 * @param array  $args a collection of arguments for wp_remote_get.
	 */
	public static function log( $name, $info = '', $priority = 1 ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$show = true;

			if ( $priority > 1 ) {
				$show = false;
				if ( defined( 'PREMIA_DEBUG' ) && boolval( PREMIA_DEBUG ) === true ) {
					$show = true;
				}
			}
			if ( $show === true ) {
				error_log( '--[Premia Log] | ' . $name );
				if ( ! empty( $info ) ) {
					error_log( print_r( $info, true ) );
				}
			}
		}
	}
}
