<?php
namespace Woocomerce_License_Updater;

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
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_downloads' ) );
		add_filter( 'woocommerce_customer_get_downloadable_products', array( $this, 'add_wc_downloads' ) );
		add_action( 'woocommerce_account_view-license-keys_endpoint', array( $this, 'manage_installs' ), 20 );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_wc_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'add_wc_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_wc_product_data_panel' ) );
	}

	/**
	 * Save Woocommerce product options
	 *
	 * @param int $post_id The Product ID.
	 */
	public function save_wc_product_data_panel( $post_id ) {
		$product = wc_get_product( $post_id );
		$product->update_meta_data( '_updater_repo', sanitize_text_field( $_POST['_updater_repo'] ) );
		$product->update_meta_data( '_updater_api_token', sanitize_text_field( $_POST['_updater_api_token'] ) );
		$product->save();
	}

	/**
	 * Add the panel and it's options
	 */
	public function add_wc_product_data_panel() {
		?><div id="updater_options_data" class="panel woocommerce_options_panel hidden">
		<?php

		woocommerce_wp_text_input(
			array(
				'id'            => '_updater_repo',
				'label'         => __( 'Github API URL', 'woocommerce-license-updater' ),
				'wrapper_class' => 'show_if_simple',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'            => '_updater_api_token',
				'label'         => __( 'Github API Key', 'woocommerce-license-updater' ),
				'wrapper_class' => 'show_if_simple',
				'type'          => 'password',
			)
		);

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
			'label'    => __( 'Update options', 'woocommerce-license-updater' ),
			'target'   => 'updater_options_data',
			'class'    => array( 'hide_if_external' ),
			'priority' => 25,
		);
		return $tabs;
	}

	/**
	 * This section is shown below the license manager output in Woocommerce -> My account
	 */
	public function manage_installs() {
		if ( isset( $_GET['site_url'] ) && isset( $_GET['action'] ) && isset( $_GET['license_key'] ) ) {
			if ( ! empty( $_GET['site_url'] ) && ! empty( $_GET['action'] ) && ! empty( $_GET['license_key'] ) ) {
				$action      = sanitize_text_field( $_GET['action'] );
				$site_url    = sanitize_text_field( $_GET['site_url'] );
				$license_key = sanitize_text_field( $_GET['license_key'] );

				$license = lmfwc_get_license( $license_key );

				if ( $action === 'deactivate' ) {
					Woocommerce_License_Updater::deactivate_license(
						array(
							'license_key' => $license,
							'site_url'    => $site_url,
						)
					);
				}
			}
		}

		echo '<h2>' . esc_html__( 'Manage installations', 'woocommerce-license-updater' ) . '</h2>';
		$user_licenses = apply_filters( 'lmfwc_get_all_customer_license_keys', get_current_user_id() );
		foreach ( $user_licenses as $data ) {
			foreach ( $data['licenses'] as $license ) {
				$installs = lmfwc_get_license_meta( $license->getId(), 'installations', false );
				echo '<table>';
				echo '<tr><th>' . esc_html__( 'Site', 'woocommerce-license-updater' ) . '</th><th>' . esc_html__( 'Action', 'woocommerce-license-updater' ) . '</th></tr>';
				foreach ( $installs as $site ) {
						echo '<tr>';
						echo '<td>' . $site . '</td>';
						$deactivate_link = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . 'view-license-keys';
						$deactivate_link = add_query_arg(
							array(
								'site_url'    => esc_url( $site ),
								'action'      => 'deactivate',
								'license_key' => $license->getDecryptedLicenseKey(),
							)
						);
						echo '<td><a href="' . esc_url( wp_nonce_url( $deactivate_link ) ) . '" class="button" data-site="' . $site . '">Deactivate</td>';
						echo '</tr>';
				}
				echo '</table>';
			}
		}
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
			;
			foreach ( $data['licenses'] as $license ) {

				$product = wc_get_product( $license->getproductId() );

				$license_info = array(
					'license_key' => $license->getDecryptedLicenseKey(),
					'site_url'    => '',
					'plugin'      => $product->get_permalink(),
					'_wpnonce'    => wp_create_nonce( 'wp_rest' ),
				);

				$file_name = $product->get_slug() . '.zip';

				$download_url = get_rest_url() . 'license-updater/v1/download_update';
				$download_url = add_query_arg( $license_info, $download_url );
				$downloads[]  = array(
					'download_url'        => $download_url,
					'download_id'         => false,
					'product_id'          => $license->getproductId(),
					'product_name'        => $data['name'],
					'product_url'         => get_permalink( $license->getproductId() ),
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
		echo '<header><h2>' . esc_html__( 'Downloads', 'woocommerce-license-updater' ) . '</h2></header>';
		echo '<table>';

		$user_licenses = apply_filters( 'lmfwc_get_customer_license_keys', $order );

		foreach ( $user_licenses as $data ) {
			if ( empty( $data['keys'] ) ) {
				echo '<p>' . esc_html__( 'Downloads will show here after your purchase is confirmed.', 'woocommerce-license-updater' ) . '';
			}
			foreach ( $data['keys'] as $license ) {
				$product = wc_get_product( $license->getproductId() );
				$license_info = array(
					'license_key' => $license->getDecryptedLicenseKey(),
					'site_url'    => '',
					'plugin'      => $product->get_permalink(),
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
