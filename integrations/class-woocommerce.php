<?php
namespace Premia;

/**
 * Woocommerce class
 *
 * @since 1.0
 */
class Woocommerce {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initiator.
	 */
	public function init() {
		if ( class_exists( 'woocommerce' ) ) {
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_downloads' ) );
			add_filter( 'woocommerce_customer_get_downloadable_products', array( $this, 'add_wc_downloads' ) );
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_wc_product_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'add_wc_product_data_panel' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_wc_product_data_panel' ) );
		}
	}

	/**
	 * Save Woocommerce product options
	 *
	 * @param int $post_id The Product ID.
	 */
	public function save_wc_product_data_panel( $post_id ) {
		$product = wc_get_product( $post_id );

		$fields = Custom_Fields::get_fields();

		foreach ( $fields as $field ) {
			$value = apply_filters( 'premia_update_field', sanitize_text_field( $_POST[ $field['name'] ] ), $field, $post_id );
			$product->update_meta_data( $field['name'], $value );
		}

		$product->save();
	}

	/**
	 * Add the panel and it's options
	 */
	public function add_wc_product_data_panel() {
		?><div id="updater_options_data" class="panel woocommerce_options_panel hidden">
		<?php

		$fields = Custom_Fields::get_fields();

		foreach ( $fields as $field ) {
			switch ( $field['type'] ) {
				case 'checkbox':
					woocommerce_wp_checkbox(
						array(
							'id'          => $field['name'],
							'label'       => $field['label'],
							'description' => $field['desc'],
							'value'       => get_post_meta( get_the_ID(), $field['name'], true ),
						)
					);
					break;
				default:
					woocommerce_wp_text_input(
						array(
							'id'          => $field['name'],
							'label'       => $field['label'],
							'description' => $field['desc'],
							'type'        => $field['type'],
						)
					);
					break;
			}
		}

		?>
		</div>
		<?php
	}

	/**
	 * Add Tab to Woocommerce product data options
	 *
	 * @param array $tabs a collection of registered tabs.
	 *
	 * @return array $tabs a modified collection of tabs.
	 */
	public function add_wc_product_tab( $tabs ) {
		$tabs['updater_options'] = array(
			'label'    => __( 'Update options', 'premia' ),
			'target'   => 'updater_options_data',
			'class'    => array( 'hide_if_external' ),
			'priority' => 25,
		);
		return $tabs;
	}

	/**
	 * Hook into Woocommerce downloads and add the available downlaods for the licenses of the user.
	 *
	 * @param array $downloads an array of available downloads.
	 * @return array $downloads a modified array of downloads.
	 */
	public function add_wc_downloads( $downloads ) {

		$user_licenses = apply_filters( 'lmfwc_get_all_customer_license_keys', get_current_user_id() );

		foreach ( $user_licenses as $data ) {
			foreach ( $data['licenses'] as $license ) {

				$post = get_post( $license->getproductId() );

				$license_info = array(
					'license_key' => $license->getDecryptedLicenseKey(),
					'site_url'    => '',
					'plugin'      => $post->post_name,
					'_wpnonce'    => wp_create_nonce( 'wp_rest' ),
				);

				$file_name = $post->post_name . '.zip';

				$download_url = get_rest_url() . 'license-updater/v1/download_update';
				$download_url = add_query_arg( $license_info, $download_url );
				$downloads[]  = array(
					'download_url'        => $download_url,
					'download_id'         => false,
					'product_id'          => $post->ID,
					'product_name'        => $data['name'],
					'product_url'         => get_permalink( $post->ID ),
					'download_name'       => $file_name,
					'order_id'            => $license->getOrderId(),
					'downloads_remaining' => '',
					'access_expires'      => 'yes',
					'file'                => array(
						'name' => $file_name,
						'file' => $download_url,
					),
				);
			}
		}

		return $downloads;
	}

	/**
	 * Add the downloads on the thank you page.
	 *
	 * @param object $order a WC_Order object.
	 */
	public function add_downloads( $order ) {
		echo '<header><h2>' . esc_html__( 'Downloads', 'premia' ) . '</h2></header>';
		echo '<table>';

		$user_licenses = apply_filters( 'lmfwc_get_customer_license_keys', $order );

		foreach ( $user_licenses as $data ) {
			if ( empty( $data['keys'] ) ) {
				echo '<p>' . esc_html__( 'Downloads will show here after your purchase is confirmed.', 'premia' ) . '';
			}
			foreach ( $data['keys'] as $license ) {
				$post         = get_post( $license->getproductId() );
				$license_info = array(
					'license_key' => $license->getDecryptedLicenseKey(),
					'site_url'    => '',
					'plugin'      => $post->post_name,
					'_wpnonce'    => wp_create_nonce( 'wp_rest' ),
				);
				$download_url = get_rest_url() . 'license-updater/v1/download_update';
				$download_url = add_query_arg( $license_info, $download_url );
				echo '<a class="button" href="' . esc_html( $download_url ) . '">Download ' . esc_html( $data['name'] ) . '</a>';
			}
		}
		echo '</table>';
	}
}
