<?php
/**
 * Shortcodes
 *
 * @package Premia
 *
 * @since 1.0
 */

namespace Premia;

/**
 * Shortcodes
 *
 * @since 1.0
 */
class Shortcodes {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initalizer.
	 */
	public function init() {
		add_shortcode( 'premia_download_link', array( $this, 'render_link' ) );
		add_shortcode( 'premia_download_button', array( $this, 'render_button' ) );
	}

	/**
	 * Render a download button.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string A HTML button.
	 */
	public function render_button( $atts ) {

		if ( isset( $atts['id'] ) && ! empty( $atts['id'] ) ) {
			$id = intval( $atts['id'] );
		} else {
			$id = get_the_ID();
		}

		$download_data = $this->get_download_data( $id );
		return '<p><a class="button" href="' . esc_url( $download_data['link'] ) . '">Download ' . esc_html( $download_data['name'] ) . '</a></p>';
	}

	/**
	 * Render a link
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string A HTML link.
	 */
	public function render_link( $atts ) {

		if ( isset( $atts['id'] ) && ! empty( $atts['id'] ) ) {
			$id = intval( $atts['id'] );
		} else {
			$id = get_the_ID();
		}

		$data = $this->get_download_data( $id );

		return $data['link'];
	}

	/**
	 *
	 * Get download data
	 *
	 * @param int $id The license ID.
	 * @return array License information.
	 */
	public function get_download_data( $id ) {
		$data = array(
			'link' => '',
			'name' => '',
		);

		$post = get_post( $id );
		if ( ! is_wp_error( $post ) && null !== $post ) {
			$license_info = array(
				'site_url' => '',
				'plugin'   => $post->post_name,
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
				'post_id'  => $id,
			);

			$download_link = get_rest_url() . 'premia/v1/download_update';
			$download_link = add_query_arg( $license_info, $download_link );

			$data = array(
				'link' => $download_link,
				'name' => $post->post_title,
			);

		}

		return $data;
	}
}
