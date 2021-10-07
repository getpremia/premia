<?php
namespace Premia;

/**
 * Generator

 * @since 1.0
 */
class File_Directory {

	public static function prepare_directories( $base_dir, $name, $version ) {

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new \WP_Filesystem_Direct( false );

		if ( ! is_dir( $base_dir . 'tmp' ) ) {
			$fs->mkdir( $base_dir . 'tmp/' );
		}

		if ( ! is_dir( $base_dir . 'tmp/zip' ) ) {
			$fs->mkdir( $base_dir . 'tmp/zip/' );
		}

		if ( ! is_dir( $base_dir . 'tmp/unpacked' ) ) {
			$fs->mkdir( $base_dir . 'tmp/unpacked/' );
		}

		if ( ! is_dir( $base_dir . 'tmp/unpacked/' . $version ) ) {
			$fs->mkdir( $base_dir . 'tmp/unpacked/' . $version );
		}

		if ( ! is_dir( $base_dir . 'tmp/zip/' . $version ) ) {
			$fs->mkdir( $base_dir . 'tmp/zip/' . $version );
		}

		if ( ! is_dir( $base_dir . 'releases' ) ) {
			$fs->mkdir( $base_dir . 'releases/' );
		}

		if ( ! is_dir( $base_dir . 'releases/' . $name ) ) {
			$fs->mkdir( $base_dir . 'releases/' . $name );
		}

		if ( ! is_dir( $base_dir . 'releases/' . $name . '/' . $version ) ) {
			$fs->mkdir( $base_dir . 'releases/' . $name . '/' . $version );
		}

		if ( ! file_exists( $base_dir . 'tmp/.htaccess' ) ) {
			// @todo Create .htaccess
			self::create_htaccess( $base_dir . 'tmp/.htaccess' );
		}

		if ( ! file_exists( $base_dir . 'releases/.htaccess' ) ) {
			// @todo Create .htaccess
			self::create_htaccess( $base_dir . 'releases/.htaccess' );
		}

		return array(
			'tmp'              => 'tmp/',
			'releases'         => 'releases/',
			'current_releases' => 'releases/' . $name . '/',
			'current_release'  => 'releases/' . $name . '/' . $version . '/',
		);
	}

	public static function create_htaccess( $file ) {
		$file_content  = 'Options - Indexes' . "\n";
		$file_content .= 'deny from all' . "\n";
		$file_content .= '<FilesMatch \'\.(jpg|jpeg|png|gif|mp3|ogg)$\'>' . "\n";
		$file_content .= 'Order Allow,Deny' . "\n";
		$file_content .= 'Allow from all' . "\n";
		$file_content .= '</FilesMatch>' . "\n";
		file_put_contents( $file, $file_content );
	}

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
			)
		);
		if ( 200 !== $status ) {
			return true;
		}

		Debug::log( 'Permission issue detected.' );

		Admin_Notices::add_notice(
			__( 'Premia has detected a permission issue. Make sure your directories are protected.' ),
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
