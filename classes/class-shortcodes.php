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
		add_shortcode( 'premia_download_link', array( $this, 'render_link' ) );
		add_shortcode( 'premia_download_button', array( $this, 'render_button' ) );
	}

	public function render_button( $atts ) {

		if ( isset( $atts['id'] ) && ! empty( $atts['id'] ) ) {
			$id = intval( $atts['id'] );
		} else {
			$id = get_the_ID();
		}

		$download_data = $this->get_download_data( $id );
		return '<p><a class="button" href="' . esc_html( $download_data['link'] ) . '">Download ' . $download_data['name'] . '</a></p>';
	}

	public function render_link( $atts ) {

		if ( isset( $atts['id'] ) && ! empty( $atts['id'] ) ) {
			$id = intval( $atts['id'] );
		} else {
			$id = get_the_ID();
		}

		$data = $this->get_download_data( $id );

		return $data['link'];
	}

	public function get_download_data( $id ) {
		$data = array(
			'link' => '',
			'name' => '',
		);

		$post = get_post( $id );
		if ( ! is_wp_error( $post ) && $post !== null ) {
			$license_info = array(
				'site_url' => '',
				'plugin'   => $post->post_name,
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
				'post_id'  => $id,
			);

			$download_link = get_rest_url() . 'license-updater/v1/download_update';
			$download_link = add_query_arg( $license_info, $download_link );

			$data = array(
				'link' => $download_link,
				'name' => $post->post_title,
			);

		}

		return $data;
	}
}
