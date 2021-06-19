<?php
namespace Premia;

/**
 * Generator

 * @since 1.0
 */
class Compressor {
	/**
	 * Generate a ZIP file
	 */
	public static function generate_zip( $base_dir, $version, $archive_path, $plugin_name, $file_path ) {

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new \WP_Filesystem_Direct( false );

		// This is where magic unpack and repacking happens.

		$extract_path = $base_dir . 'tmp/unpacked/' . $version;

		// Extract the zip file.
		$package = new \ZipArchive();
		$package->open( $file_path );
		$package->extractTo( $extract_path );

		// Scan the unpacked contents
		$unpacked_contents = scandir( $base_dir . 'tmp/unpacked/' . $version );

		// Get the name of the unpacked folder.
		foreach ( $unpacked_contents as $folder ) {
			if ( ! is_file( $folder ) ) {
				$unpacked_folder = $folder;
			}
		}

		// Full path to unpacked folder.
		$unpacked_path = $base_dir . 'tmp/unpacked/' . $version . '/' . $unpacked_folder;

		// Create a new zip file.
		$repack = new \ZipArchive();
		$repack->open( $archive_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

		$dir   = $unpacked_path;
		$it    = new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS );
		$files = new \RecursiveIteratorIterator(
			$it,
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		// Add the files to the repack.
		foreach ( $files as $name => $file ) {
			if ( ! $file->isDir() ) {
				// Get actual path.
				$file_to_add = $file->getRealPath();

				// Somehow generate the relative path.
				$relative_path = substr( $file_to_add, strlen( $unpacked_path ) + 1 );

				$zip_file_path = $plugin_name . '/' . $relative_path;

				// Add the files but slice in a subfolder.
				$repack->addFile( $file_to_add, $zip_file_path );
			}
		}

		// Close the ZIP.
		$repack->close();

		/**
		 * Clean up
		 */

		// Remove original package.
		$fs->delete( $file_path );

		// Remove subdir.
		$fs->rmdir( $dir, true );

		// Remove version dir.
		$fs->rmdir( $extract_path, true );

		return $archive_path;
	}

	/**
	 * Download a ZIP file
	 */
	public static function download_zip( $github_data, $path, $base_dir ) {
		$zip = Github::request( $github_data, $path );

		if ( is_wp_error( $zip ) ) {
			Debug::log( 'Zip is not valid?', $zip );
			return new \WP_REST_Response( array( 'error' => 'Zip is invalid.' ), 400 );
		}

		$parts    = explode( 'filename=', $zip['headers']['content-disposition'] );
		$zip_name = $parts[1];

		$file_path = $base_dir . 'tmp/zip/' . $zip_name;

		// Save the zip file.
		file_put_contents( $file_path, $zip['body'] );

		return $file_path;
	}

	public static function prepare_directories( $base_dir, $version ) {

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new \WP_Filesystem_Direct( false );

		if ( ! is_dir( $base_dir . 'tmp' ) ) {
			$fs->mkdir( $base_dir . 'tmp/' );
		}

		if ( ! is_dir( $base_dir . 'tmp/zip' ) ) {
			$fs->mkdir( $base_dir . 'tmp/zip' );
		}

		if ( ! is_dir( $base_dir . 'tmp/unpacked' ) ) {
			$fs->mkdir( $base_dir . 'tmp/unpacked' );
		}

		if ( ! is_dir( $base_dir . 'tmp/unpacked/' . $version ) ) {
			$fs->mkdir( $base_dir . 'tmp/unpacked/' . $version );
		}

		if ( ! is_dir( $base_dir . 'tmp/zip/' . $version ) ) {
			$fs->mkdir( $base_dir . 'tmp/zip/' . $version );
		}
	}

	public static function clean( $dir ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new \WP_Filesystem_Direct( false );

		Debug::log( 'Remove dir: ' . $dir );
		$fs->rmdir( $dir, true );
	}
}
