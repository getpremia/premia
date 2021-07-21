<?php
namespace Premia;

/**
 * Licenses

 * @since 1.0
 */
class Licenses {

	public function __construct() {
		$this->init();
	}

	public function init() {
		$this->start();
	}

	public function is_necessary() {
		if ( \class_exists( 'LicenseManagerForWooCommerce\Main' ) ) {
			return false;
		}
		return true;
	}

	public function start() {
		if ( $this->is_necessary() ) {
			add_action( 'init', array( $this, 'register_post_types' ) );
			add_action( 'manage_prem_license_posts_columns', array( $this, 'manage_columns' ) );
			add_action( 'manage_prem_license_posts_custom_column', array( $this, 'columns_content' ), 10, 2 );
			add_action( 'wp_insert_post_data', array( $this, 'insert_license' ), 10, 2 );
		}
	}

	public function manage_columns( $columns ) {
		$columns['post']     = __( 'Linked post', 'premia' );
		$columns['customer'] = __( 'Customer', 'premia' );
		return $columns;
	}

	public function columns_content( $column, $post_id ) {
		switch ( $column ) {
			case 'post':
				$linked_id = get_post_meta( $post_id, '_premia_linked_post_id', true );
				echo '<a href="' . get_edit_post_link( $linked_id ) . '">' . get_the_title( $linked_id ) . '</a>';
				break;
			case 'customer':
				$customer  = get_post_field( 'post_author', $post_id );
				$user_data = get_userdata( $customer );
				echo '<a href="' . get_edit_user_link( $customer ) . '">' . $user_data->data->display_name . '</a>';
		}
	}

	public function register_post_types() {
		register_post_type(
			'prem_license',
			array(
				'public'             => true,
				'label'              => 'Licenses',
				'description'        => 'Custom Post Type for Premia Licenses',
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'query_var'          => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 10,
				'supports'           => array( 'title' ),
				'taxonomies'         => array(),
				'show_in_rest'       => false,
				'menu_icon'          => 'data:image/svg+xml;base64,' . base64_encode( '<svg width="34" height="36" viewBox="0 0 34 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.0914 16.4397L1.54909 17.2336L15.0458 2.94295L9.0914 16.4397Z"/><path d="M10.4205 16.4L16.981 1.52961L23.1041 16.4H10.4205Z"/><path d="M24.4178 16.4388L18.7884 2.76746L32.4597 17.243L24.4178 16.4388Z"/><path d="M9.10933 17.6444L15.2991 33.3252L1.26894 18.4697L9.10933 17.6444Z"/><path d="M23.1395 17.6L16.9807 34.3169L10.3819 17.6H23.1395Z"/><path d="M24.4024 17.6432L32.7256 18.4755L18.5763 33.4572L24.4024 17.6432Z"/></svg>' ),
			)
		);
	}

	public function insert_license( $data, $post_data ) {
		if ( $data['post_type'] === 'prem_license' && $data['post_status'] !== 'auto-draft' ) {

			if ( ! is_admin() ) {
				require_once ABSPATH . 'wp-admin/includes/post.php';
			}

			$existing = \post_exists( $data['post_title'] );

			if ( $existing && $existing !== $post_data['ID'] ) {
				$data['post_title'] = $this->generate_license( $post_data['ID'] );
			}
		}
		return $data;
	}

	public function verify_license_key_structure( $license_key ) {
		$clean_key     = str_replace( '/[^a-zA-Z0-9]+/', '', $license_key );
		$remove_dashes = str_replace( '-', '', $clean_key );
		if ( strlen( $remove_dashes ) === 16 ) {
			return true;
		}
		return false;
	}

	public static function generate_license( $post_id = false ) {
		if ( ! is_admin() ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}
		$license_key = implode( '-', str_split( substr( md5( random_bytes( 16 ) ), 0, 16 ), 4 ) );
		$existing    = \post_exists( $license_key );
		if ( $existing && $existing !== $post_id ) {
			return $this->generate_license();
		}
		return strtoupper( $license_key );
	}

	public static function create_license( $product_id, $user_id ) {
		$license_id = wp_insert_post(
			array(
				'post_title'  => self::generate_license(),
				'post_status' => 'publish',
				'post_type'   => 'prem_license',
				'post_author' => $user_id,
			)
		);

		if ( ! is_wp_error( $license_id ) ) {
			update_post_meta( $license_id, '_premia_linked_post_id', $product_id );
		}

		return $license_id;
	}

	public static function add_site( $license_key, $site_url ) {
		$post = self::get_license_by_license_key( $license_key );
		if ( $post !== null ) {
			$sites = get_post_meta( $post->ID, 'installations', true );
			if ( ! is_array( $sites ) ) {
				$sites = array();
			}
			if ( ! in_array( $site_url, $sites, true ) ) {
				$sites[] = $site_url;
			}
			update_post_meta( $post->ID, 'installations', $sites );
		}
	}

	public static function remove_site( $license_key, $site_url ) {
		Debug::log( 'Remove site: ' . $site_url );
		$post = self::get_license_by_license_key( $license_key );
		if ( $post !== null ) {
			$sites = get_post_meta( $post->ID, 'installations', true );

			Debug::log( 'Remove site: ' . $site_url );

			if ( is_array( $sites ) && in_array( $site_url, $sites, true ) ) {
				$sites = array_filter(
					$sites,
					function( $e ) use ( $site_url ) {
						return ( $e !== $site_url );
					}
				);
			}
			update_post_meta( $post->ID, 'installations', $sites );
		}
	}

	public static function get_license_by_license_key( $license_key ) {
		return get_page_by_title( $license_key, OBJECT, 'prem_license' );
	}

	public static function get_linked_post_by_license_key( $license_key ) {
		$post = get_license_by_license_key( $license_key );
		return get_post_meta( $post->ID, '_premia_linked_post_id', true );
	}

	public static function activate( $license_info ) {
		self::add_site( $license_info['license_key'], $license_info['site_url'] );
		return apply_filters( 'premia_activate_license', true, $license_info );
	}

	public static function deactivate( $license_info ) {
		self::remove_site( $license_info['license_key'], $license_info['site_url'] );
		return apply_filters( 'premia_deactivate_license', true, $license_info );
	}

	public static function get_license( $license_info ) {
		$license = self::get_linked_post_by_license_key( $license_info['license_key'] );
		return apply_filters( 'premia_get_license', $license, $license_info );
	}

	public static function validate_license( $license_info ) {
		$validate = false;

		// Bail early as there's no validation to do.
		if ( isset( $license_info['post_id'] ) && ! empty( $license_info['post_id'] ) ) {
			$do_not_validate = get_post_meta( intval( $license_info['post_id'] ), '_updater_do_not_validate_licenses', true );
			if ( $do_not_validate ) {
				return true;
			}
		}

		if ( ! isset( $license_info['license_key'] ) || empty( $license_info['license_key'] ) ) {
			Debug::log( 'Cannot validate', $license_info );
			return false;
		}

		$license = self::get_license_by_license_key( $license_info['license_key'] );

		$sites = get_post_meta( $license->ID, 'installations', true );

		if ( ! is_array( $sites ) ) {
			$sites = array();
		}

		Debug::log( 'Sites: ', $sites, 2 );
		Debug::log( 'Site: ', $license_info['site_url'], 2 );

		if ( in_array( $license_info['site_url'], $sites, true ) || is_user_logged_in() ) {
			$validate = true;
		}

		Debug::log( 'post status:', $license->post_status );

		if ( $license->post_status !== 'publish' ) {
			$validate = false;
		}

		Debug::log( 'Validation result: ', $validate );

		return apply_filters( 'premia_validate_license', $validate, $license_info );
	}
}
