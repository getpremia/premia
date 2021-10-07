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

			switch ( $priority ) {
				case 2:
					$show = false;
					if ( defined( 'PREMIA_DEBUG' ) && boolval( PREMIA_DEBUG ) === true ) {
						$show = true;
					}
					break;
				case 3:
					$show = false;
					if ( defined( 'PREMIA_LOG_LEVEL' ) && intval( PREMIA_LOG_LEVEL ) >= 3 ) {
						$show = true;
					}
					break;
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
