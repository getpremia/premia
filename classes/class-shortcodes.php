<?php
namespace Premia;

/**
 * Github API Class

 * @since 1.0
 */
class Shortcodes {

	public function __construct() {
		$this->init();
	}

	public function init() {
		add_shortcode( 'premia_download_button', array( $this, 'render_shortcode' ) );
	}

	public function render_shortcode() {
		$download_url = '';

		$post         = get_post( get_the_ID() );
		$license_info = array(
			'site_url' => '',
			'plugin'   => $post->post_name,
			'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			'post_id'  => get_the_ID(),
		);

		$download_url = get_rest_url() . 'license-updater/v1/download_update';
		$download_url = add_query_arg( $license_info, $download_url );
		return '<p><a class="button" href="' . esc_html( $download_url ) . '">Download ' . $post->post_title . '</a></p>';
	}
}
