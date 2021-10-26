<?php
/**
 * Debug
 *
 * @package Premia
 *
 * @since 0.1
 */

namespace Premia;

/**
 * Debug
 *
 * @since 0.1
 */
class Debug {
	/**
	 * Log
	 *
	 * @param string $name A prefix.
	 * @param mixed  $info Anything that needs be logged.
	 * @param int    $priority When should it show?.
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
			if ( true === $show ) {
				//phpcs:ignore
				error_log( '--[Premia Log] | ' . $name );
				if ( ! empty( $info ) ) {
					//phpcs:ignore
					error_log( print_r( $info, true ) );
				}
			}
		}
	}
}
