<?php
namespace Premia;

/**
 * Admin Notices

 * @since 1.0
 */
class Admin_Notices {

	public static $option_name = '_premia_notices';

	public function __construct() {
		$this->init();
	}

	public function init() {
		add_action( 'admin_notices', array( $this, 'check_notices' ) );
	}

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

	public static function update_notices( $notices ) {
		return update_option( self::$option_name, $notices );
	}

	public function check_notices() {
		$notices = self::get_notices();
		if ( is_array( $notices['notices'] ) && ! empty( $notices['notices'] ) ) {
			foreach ( $notices['notices'] as $key => $notice ) {
				$extra = '';
				if ( $notice['type'] === 'permission-issue' ) {
					$extra = '<a href="">' . __( 'Go to settings', 'premia' ) . '</a>';
				}
				echo '<div data-type="premia-notice" data-id="' . $key . '" class="notice notice-' . esc_html( $notice['notice_type'] ) . '">';
				echo '<p>' . esc_html( $notice['message'] ) . ( ! empty( $extra ) ? ' ' . $extra : '' ) . '</p>';
				echo '</div>';
			}
		}
	}

	public static function add_notice( $message, $type, $time, $notice_type, $args ) {
		$notices = self::get_notices();
		if ( ! in_array( $type, $notices['existing_types'] ) ) {
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

	public static function remove_notice( $id ) {
		$notices = self::get_notices();
		$notice  = $notices['notices'][ $id ];
		unset( $notices['notices'][ $id ] );
		foreach ( $notice['existing_types'] as $key => $type ) {
			if ( $type === $notice['type'] ) {
				unset( $notice['existing_types'][ $key ] );
			}
		}
		self::update_notices( $notices );
	}
}
