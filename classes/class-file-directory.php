<?php
/**
 * File Directory
 *
 * @package Premia
 *
 * @since 1.0
 */

namespace Premia;

/**
 * Generator

 * @since 1.0
 */
class File_Directory {

	/**
	 * Prepare directories
	 *
	 * @param string $name Plugin name.
	 * @param string $version Plugin version.
	 */
	public static function prepare_directories( $name, $version ) {

		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$base_dir = trailingslashit( apply_filters( 'premia_plugin_assets_download_path', plugin_dir_path( dirname( __FILE__ ) ) ) );

		if ( ! is_dir( $base_dir ) ) {
			$wp_filesystem->mkdir( $base_dir );
		}

		if ( ! is_dir( $base_dir . 'releases' ) ) {
			$wp_filesystem->mkdir( $base_dir . 'releases/' );
		}

		if ( ! is_dir( $base_dir . 'releases/' . $name ) ) {
			$wp_filesystem->mkdir( $base_dir . 'releases/' . $name );
		}

		if ( ! is_dir( $base_dir . 'releases/' . $name . '/' . $version ) ) {
			$wp_filesystem->mkdir( $base_dir . 'releases/' . $name . '/' . $version );
		}

		if ( ! file_exists( $base_dir . 'releases/.htaccess' ) ) {
			self::create_htaccess( $base_dir . 'releases/.htaccess' );
		}

		if ( ! file_exists( $base_dir . 'releases/' . $name . '/.htaccess' ) ) {
			self::create_htaccess( $base_dir . 'releases/' . $name . '/.htaccess' );
		}

		if ( ! file_exists( $base_dir . 'releases/' . $name . '/' . $version . '/.htaccess' ) ) {
			self::create_htaccess( $base_dir . 'releases/' . $name . '/' . $version . '/.htaccess' );
		}

		return array(
			'base_dir'         => $base_dir,
			'releases'         => 'releases/',
			'current_releases' => 'releases/' . $name . '/',
			'current_release'  => 'releases/' . $name . '/' . $version . '/',
		);
	}

	/**
	 * Create htaccess
	 *
	 * @param string $file Path to file.
	 */
	public static function create_htaccess( $file ) {
		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$file_content  = 'Order Deny,Allow' . "\n";
		$file_content .= 'Deny from all' . "\n";
		$wp_filesystem->put_contents( $file, $file_content );
	}

	/**
	 * Checks if file is protected.
	 *
	 * @param string $file URL to file.
	 * @param bool   $is_premia_path Is it a premia or custom path?.
	 * @return bool The result.
	 */
	public static function is_protected_file( $file, $is_premia_path = true ) {
		if ( $is_premia_path ) {
			$url = plugin_dir_url( dirname( __FILE__ ) . '/' ) . $file;
		} else {
			$url = $file;
		}

		$result = wp_remote_get( $url );
		$status = wp_remote_retrieve_response_code( $result );
		Debug::log(
			'Check if file is protected:',
			array(
				$file,
				$url,
				$status,
			),
			2
		);

		if ( ! empty( $status ) && 200 !== $status ) {
			return true;
		}

		Debug::log( 'Permission issue detected.' );

		Admin_Notices::add_notice(
			__( 'Premia detected a permission issue. Make sure your directories are protected and re-check permissions.', 'premia' ),
			'permission-issue',
			time(),
			'error',
			array(
				'file' => $url,
			)
		);

		return false;
	}
}
