<?php
/**
 * WooCommerce Helper
 *
 * @package Premia
 *
 * @since 1.0
 */

namespace Premia;

/**
 * Woocommerce class
 *
 * @since 1.0
 */
class Woocommerce_Helper {

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
		add_action( 'plugins_loaded', array( $this, 'start' ), 10 );
	}

	/**
	 * Checks if Woocommerce is active.
	 *
	 * @return bool activate state.
	 */
	public function is_woocommerce_active() {
		return \class_exists( 'WooCommerce' );
	}

	/**
	 * Starts when Woocommece is active.
	 *
	 * @return void
	 */
	public function start() {
		if ( $this->is_woocommerce_active() ) {
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_downloads' ) );
			add_filter( 'woocommerce_customer_get_downloadable_products', array( $this, 'add_wc_downloads' ) );
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_wc_product_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'add_wc_product_data_panel' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_wc_product_data_panel' ) );
			add_filter( 'premia_customize_license_fields', array( $this, 'add_linked_order_field' ) );
			add_filter( 'premia_customize_post_fields', array( $this, 'add_wc_fields' ) );
			add_filter( 'premia_supported_post_types', array( $this, 'add_product_support' ) );
		}

		if ( ! Woocommerce_License_Manager_Helper::is_license_manager_active() ) {
			add_action( 'woocommerce_payment_complete', array( $this, 'maybe_create_licences' ) );
			add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_create_licences' ) );
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'maybe_deactivate_licences' ) );
			add_action( 'woocommerce_order_refunded', array( $this, 'maybe_deactivate_licences' ) );
			add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_license_meta' ), 10 );
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_licenses' ) );
		}
	}

	/**
	 * Filter the formatted meta data.
	 *
	 * @param array $formatted_meta The unformatted metadata.
	 * @return array The formatted metadata.
	 */
	public function format_license_meta( $formatted_meta ) {
		foreach ( $formatted_meta as &$meta ) {
			$meta->display_key   = __( 'License', 'premia' );
			$meta->display_value = '<a href="' . get_edit_post_link( $meta->value ) . '">' . get_the_title( $meta->value ) . '</a>';
		}
		return $formatted_meta;
	}

	/**
	 * Add product support for Premia.
	 *
	 * @param array $post_types Supported Post Types.
	 * @return array customized supported post types.
	 */
	public function add_product_support( $post_types ) {
		$post_types[] = 'product';
		return $post_types;
	}

	/**
	 * Add Woocommerce fields.
	 *
	 * @param array $fields The exisiting fields.
	 * @return array The new fields.
	 */
	public function add_wc_fields( $fields ) {
		if ( 'product' === get_post_type() ) {
			$fields = array_merge(
				array(
					array(
						'name'    => '_updater_enable_license',
						'type'    => 'checkbox',
						'label'   => __( 'Enable licensing', 'premia' ),
						'desc'    => __( 'Enabling this option will generate licenses for each purchase of this item.', 'premia' ),
						'visible' => true,
					),
				),
				$fields
			);
		}
		return $fields;
	}

	/**
	 * Save Woocommerce product options
	 *
	 * @param int $post_id The Product ID.
	 */
	public function save_wc_product_data_panel( $post_id ) {

		if ( ! ( isset( $_POST['woocommerce_meta_nonce'], $_POST['acme_text_id'] ) || wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) ) {
			return false;
		}

		$product = wc_get_product( $post_id );

		$fields = Custom_Fields::get_fields();

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field['name'] ] ) ) {
				$value = apply_filters( 'premia_update_field', sanitize_text_field( wp_unslash( $_POST[ $field['name'] ] ) ), $field, $post_id );
				$product->update_meta_data( $field['name'], $value );
			}
		}

		$product->save();
	}

	/**
	 * Add the panel and it's options
	 */
	public function add_wc_product_data_panel() {
		?><div id="premia_options_data" class="panel woocommerce_options_panel hidden">
		<?php

		$fields = Custom_Fields::get_fields( 'post' );

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
		$tabs['premia_settings'] = array(
			'label'    => __( 'Premia settings', 'premia' ),
			'target'   => 'premia_options_data',
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

		$downloads = array();

		$posts = get_posts(
			array(
				'post_author' => get_current_user_id(),
				'numberposts' => -1,
				'post_type'   => 'prem_license',
				'post_status' => 'publish',
			)
		);

		foreach ( $posts as $post ) {

			$linked_post_id  = get_post_meta( $post->ID, '_premia_linked_post_id', true );
			$linked_order_id = get_post_meta( $post->ID, '_premia_linked_order_id', true );
			$linked_post     = get_post( $linked_post_id );

			$license_info = array(
				'license_key' => $post->post_title,
				'site_url'    => '',
				'plugin'      => $linked_post->post_name,
				'_wpnonce'    => wp_create_nonce( 'wp_rest' ),
			);

			$file_name = $linked_post->post_name . '.zip';

			$download_url = get_rest_url() . 'premia/v1/download_update';
			$download_url = add_query_arg( $license_info, $download_url );
			$downloads[]  = array(
				'download_url'        => $download_url,
				'download_id'         => false,
				'product_id'          => $linked_post->ID,
				'product_name'        => $linked_post->post_title,
				'product_url'         => get_permalink( $linked_post->ID ),
				'download_name'       => $file_name,
				'order_id'            => $linked_order_id,
				'downloads_remaining' => '',
				'access_expires'      => 'yes',
				'file'                => array(
					'name' => $file_name,
					'file' => $download_url,
				),
			);
		}

		$downloads = apply_filters( 'premia_woocommerce_downloads', $downloads );

		return $downloads;
	}

	/**
	 * Add the downloads on the thank you page.
	 *
	 * @param object $order a WC_Order object.
	 */
	public function add_downloads( $order ) {
		echo '<header><h2>' . esc_html__( 'Downloads', 'premia' ) . '</h2></header>';

		$downloads = array();

		foreach ( $order->get_items()  as $item ) {
			$license_id = $item->get_meta( '_premia_linked_license' );
			if ( ! empty( $license_id ) ) {
				$license = get_post( $license_id );
				if ( 'publish' === $license->post_status ) {
					if ( is_a( $license, 'WP_Post' ) && 'publish' === $license->post_status ) {
						$post_id      = get_post_meta( $license_id, '_premia_linked_post_id', true );
						$post         = get_post( $post_id );
						$license_info = array(
							'license_key' => $license->post_title,
							'site_url'    => '',
							'plugin'      => $post->post_name,
							'_wpnonce'    => wp_create_nonce( 'wp_rest' ),
							'post_id'     => $post->ID,
						);
						$download_url = get_rest_url() . 'premia/v1/download_update';
						$download_url = add_query_arg( $license_info, $download_url );
						$downloads[]  = array(
							'link' => $download_url,
							'name' => $post->post_title,
						);
					}
				}
			}
		}

		$downloads = apply_filters( 'premia_order_downloads', $downloads, $order );

		if ( is_user_logged_in() ) {

			if ( ! empty( $downloads ) ) {
				echo '<p>' . esc_html__( 'Get started by downloading your files below!', 'premia' ) . '</p>';
				foreach ( $downloads as $download ) {
					echo '<p><a class="button" href="' . esc_html( $download['link'] ) . '">Download ' . esc_html( $download['name'] ) . '</a></p>';
				}
			} else {
				echo '<p>' . esc_html__( 'Downloads will show here after your purchase is confirmed.', 'premia' ) . '</p>';
			}
		} else {
			echo '<p>' . esc_html__( 'Login to access your downloads.', 'premia' ) . '</p>';
		}
	}

	/**
	 * Add the licenses on the thank you page.
	 *
	 * @param object $order a WC_Order object.
	 */
	public function add_licenses( $order ) {
		echo '<header><h2>' . esc_html__( 'Licenses', 'premia' ) . '</h2></header>';

		$licenses = array();

		foreach ( $order->get_items()  as $item ) {
			$license_id = $item->get_meta( '_premia_linked_license' );
			if ( ! empty( $license_id ) ) {
				$license = get_post( $license_id );
				if ( 'publish' === $license->post_status ) {
					if ( is_a( $license, 'WP_Post' ) && 'publish' === $license->post_status ) {
						$post_id    = get_post_meta( $license_id, '_premia_linked_post_id', true );
						$post       = get_post( $post_id );
						$licenses[] = array(
							'license_key' => $license->post_title,
							'name'        => $post->post_title,
							'post_id'     => $post->ID,
						);
					}
				}
			}
		}

		$licenses = apply_filters( 'premia_order_licenses', $licenses, $order );

		if ( ! empty( $licenses ) ) {
			echo '<table>';
			echo '<tr><th>' . esc_html__( 'Name', 'premia' ) . '</th><th>' . esc_html__( 'License', 'premia' ) . '</th></tr>';
			foreach ( $licenses as $license ) {
				echo '<tr><td><strong>' . esc_html( $license['name'] ) . '</strong></td><td><code>' . esc_html( $license['license_key'] ) . '</code></td></tr>';
			}
			echo '</table>';
		} else {
			echo '<p>' . esc_html__( 'Licenses will show here after your purchase is confirmed.', 'premia' ) . '</p>';
		}
	}

	/**
	 * Add linker order field.
	 *
	 * @param array $fields Existing fields.
	 * @return array New fields.
	 */
	public function add_linked_order_field( $fields ) {
		$fields[] = array(
			'name'    => '_premia_linked_order_id',
			'label'   => __( 'Linked order ID', 'premia' ),
			'type'    => 'post_link',
			'visible' => true,
		);
		return $fields;
	}

	/**
	 * Maybe create a license.
	 *
	 * @param int $order_id The Order ID.
	 * @return void
	 */
	public function maybe_create_licences( $order_id ) {
		Debug::log( 'Maybe create license: ', $order_id );
		$order           = wc_get_order( $order_id );
		$license_created = $order->get_meta( '_license_created' );
		Debug::log( 'License created?: ', $license_created );
		if ( empty( $license_created ) || false === $license_created ) {
			Debug::log( 'Create license: ', $order_id );
			foreach ( $order->get_items() as $item_id => $item ) {
				$product_id      = $item->get_product_id();
				$license_enabled = get_post_meta( $product_id, '_updater_enable_license', true );
				if ( 'yes' === $license_enabled ) {
					$license_id = Licenses::create_license( $product_id, $order->get_user_id() );
					update_post_meta( $license_id, '_premia_linked_order_id', $order_id );
					wc_update_order_item_meta( $item_id, '_premia_linked_license', $license_id );
				}
			}
			$order->update_meta_data( '_license_created', true );
			$order->save();
		}
	}

	/**
	 * Maybe deactivate license.
	 *
	 * @param int $order_id The Order ID.
	 * @return void
	 */
	public function maybe_deactivate_licences( $order_id ) {
		Debug::log( 'Maybe deactivate license: ', $order_id );
		$order = wc_get_order( $order_id );
		foreach ( $order->get_items() as $item ) {
			$license_id = $item->get_meta( '_premia_linked_license' );
			if ( ! empty( $license_id ) ) {
				Debug::log( 'Trash license: ', $license_id );
				wp_trash_post( $license_id );
			}
		}
	}
}
