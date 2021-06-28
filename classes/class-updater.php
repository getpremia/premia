<?php
/**
 * Updater for Premia
 *
 * @link              https://sympose.net
 * @since             1.4.0
 * @package           Sympose Premium
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
	private $api_url = 'https://getpremia.com/wp-json/license-updater/v1/';

	/**
	 * Updater constructor
	 *
	 * @param string $name The plugin name.
	 */
	public function __construct( $name ) {
		$this->plugin_name = $name;
		add_filter( "puc_request_info_query_args-{$this->plugin_name}", array( $this, 'add_license_info' ) );

		$this->puc = \Puc_v4_Factory::buildUpdateChecker(
			$this->api_url . 'check_updates',
			__FILE__,
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

		return $args;

	}

}
