<?php
/**
 * Updater for Premia
 *
 * @link              https://sympose.net
 * @since             1.0
 * @package           Premia
 */

namespace Premia;

/**
 * Activator class
 */
class Updater {
	/**
	 * Set the API url
	 *
	 * @var string $api_url The API url.
	 */
	private $api_url = 'https://getpremia.com/wp-json/premia/v1/';

	/**
	 * The Updater library instance.
	 *
	 * @var object $puc The PUC object.
	 */
	private static $puc;

	/**
	 * Updater constructor
	 *
	 * @param string $name The plugin name.
	 * @param string $file The plugin file.
	 */
	public function __construct( $name, $file ) {
		$this->plugin_name = $name;
		add_filter( "puc_request_info_query_args-{$this->plugin_name}", array( $this, 'add_license_info' ) );

		$this->api_url = apply_filters( 'premia_api_url', $this->api_url );

		self::$puc = \Puc_v4_Factory::buildUpdateChecker(
			$this->api_url . 'check_updates',
			$file,
			$this->plugin_name
		);
	}

	/**
	 * Add License information to query args
	 *
	 * @param array $args An array of parameters.
	 */
	public function add_license_info( $args ) {
		$option_name         = str_replace( '-', '_', $this->plugin_name ) . '_license_key';
		$args['license_key'] = get_option( $option_name );
		$args['site_url']    = esc_url( get_site_url() );
		$args['plugin']      = $this->plugin_name;

		Debug::log( 'puc_request_info_query_args: ', $args );

		return $args;
	}

	/**
	 * Check for updates
	 */
	public static function check_for_updates() {
		return self::$puc->checkForUpdates();
	}

}
