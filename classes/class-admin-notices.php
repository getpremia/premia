<?php
/**
 * Admin Notices
 *
 * @package Premia
 * @since 1.0
 */

namespace Premia;

/**
 * Admin Notices

 * @since 1.0
 */
class Admin_Notices {

	/**
	 * The option name.
	 *
	 * @var string $option_name
	 */
	public static $option_name = '_premia_notices';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initializer.
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'check_notices' ) );
	}

	/**
	 * Get notices
	 *
	 * @return array Array of notices.
	 */
	public static function get_notices() {
		$notices = get_option( self::$option_name );
		if ( ! is_array( $notices ) || ! isset( $notices['notices'] ) || ! is_array( $notices['notices'] ) ) {
			$notices = array(
				'notices'        => array(),
				'existing_types' => array(),
			);
		}
		return $notices;
	}

	/**
	 * Update a notice
	 *
	 * @param array $notices Update notices with a new value.
	 * @return mixed update_option result.
	 */
	public static function update_notices( $notices ) {
		return update_option( self::$option_name, $notices );
	}

	/**
	 * Check notices and display notices.
	 */
	public function check_notices() {
		$notices = self::get_notices();
		if ( is_array( $notices['notices'] ) && ! empty( $notices['notices'] ) ) {
			foreach ( $notices['notices'] as $key => $notice ) {
				$extra = '';
				if ( 'permission-issue' === $notice['type'] ) {
					$extra = '<a href="' . esc_url( admin_url( 'admin.php?page=premia-settings' ) ) . '">' . __( 'Go to settings', 'premia' ) . '.</a>';
				}
				echo '<div data-type="premia-notice" data-id="' . esc_attr( $key ) . '" class="notice notice-' . esc_html( $notice['notice_type'] ) . '">';
				echo '<p>' . esc_html( $notice['message'] ) . ( ! empty( $extra ) ? ' ' . wp_kses_post( $extra ) : '' ) . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Add a notice.
	 *
	 * @param string $message The notice message.
	 * @param string $type The type of notice (error etc.).
	 * @param string $time A timestamp.
	 * @param string $notice_type The premia notice type.
	 * @param array  $args  Extra arguments.
	 */
	public static function add_notice( $message, $type, $time, $notice_type, $args ) {
		$notices = self::get_notices();
		if ( ! in_array( $type, $notices['existing_types'], true ) ) {
			$notices['notices'][]        = array(
				'message'     => $message,
				'type'        => $type,
				'time'        => $time,
				'notice_type' => $notice_type,
				'data'        => $args,
			);
			$notices['existing_types'][] = $type;
		}

		self::update_notices( $notices );
	}

	/**
	 * Removes a notice.
	 *
	 * @param int $id The notice ID.
	 */
	public static function remove_notice( $id ) {
		$notices = self::get_notices();
		$notice  = $notices['notices'][ $id ];
		unset( $notices['notices'][ $id ] );
		foreach ( $notices['existing_types'] as $key => $type ) {
			if ( $type === $notice['type'] ) {
				unset( $notices['existing_types'][ $key ] );
			}
		}
		self::update_notices( $notices );
	}
}
