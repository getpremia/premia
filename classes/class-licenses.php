<?php
/**
 * Licenses
 *
 * @package Premia
 *
 * @since 1.0
 */

namespace Premia;

/**
 * Licenses

 * @since 1.0
 */
class Licenses {

	/**
	 * Consturctor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initializer.
	 */
	public function init() {
		$this->start();
	}

	/**
	 * Is necessary?
	 *
	 * @return bool Only do this when License Manage for Woocommerce is not active.
	 */
	public function is_necessary() {
		if ( \class_exists( 'LicenseManagerForWooCommerce\Main' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Start the class.
	 */
	public function start() {
		if ( $this->is_necessary() ) {
			add_action( 'init', array( $this, 'register_post_types' ) );
			add_action( 'manage_prem_license_posts_columns', array( $this, 'manage_columns' ) );
			add_action( 'manage_prem_license_posts_custom_column', array( $this, 'columns_content' ), 10, 2 );
			add_action( 'wp_insert_post_data', array( $this, 'insert_license' ), 10, 2 );
		}
	}

	/**
	 * Manage columns
	 *
	 * @param array $columns Exisiting column.
	 * @return array The new columns.
	 */
	public function manage_columns( $columns ) {
		$columns['post']     = __( 'Linked post', 'premia' );
		$columns['expires']  = __( 'Expires on', 'premia' );
		$columns['customer'] = __( 'Customer', 'premia' );
		return $columns;
	}

	/**
	 * Add column content.
	 *
	 * @param string $column The column ID.
	 * @param int    $post_id The post ID.
	 */
	public function columns_content( $column, $post_id ) {
		switch ( $column ) {
			case 'post':
				$linked_id = get_post_meta( $post_id, '_premia_linked_post_id', true );
				echo '<a href="' . esc_url( get_edit_post_link( $linked_id ) ) . '">' . esc_html( get_the_title( $linked_id ) ) . '</a>';
				break;
			case 'customer':
				$customer  = get_post_field( 'post_author', $post_id );
				$user_data = get_userdata( $customer );
				echo '<a href="' . esc_url( get_edit_user_link( $customer ) ) . '">' . esc_html( $user_data->data->display_name ) . '</a>';
				break;
			case 'expires':
				$datetime = get_post_meta( $post_id, '_premia_expiry_date', true );
				if ( ! empty( $datetime ) ) {
					$date = new \Datetime();
					$date->setTimestamp( $datetime );
					echo esc_html( $date->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
				}
				break;
		}
	}

	/**
	 * Register the License post type.
	 */
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
				//phpcs:ignore
				'menu_icon'          => 'data:image/svg+xml;base64,' . base64_encode( '<svg width="34" height="36" viewBox="0 0 34 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.0914 16.4397L1.54909 17.2336L15.0458 2.94295L9.0914 16.4397Z"/><path d="M10.4205 16.4L16.981 1.52961L23.1041 16.4H10.4205Z"/><path d="M24.4178 16.4388L18.7884 2.76746L32.4597 17.243L24.4178 16.4388Z"/><path d="M9.10933 17.6444L15.2991 33.3252L1.26894 18.4697L9.10933 17.6444Z"/><path d="M23.1395 17.6L16.9807 34.3169L10.3819 17.6H23.1395Z"/><path d="M24.4024 17.6432L32.7256 18.4755L18.5763 33.4572L24.4024 17.6432Z"/></svg>' ),
			)
		);
	}

	/**
	 * Generate a License key.
	 *
	 * @param array $data Data for insert.
	 * @param array $post_data The Post data.
	 * @return mixed
	 */
	public function insert_license( $data, $post_data ) {
		if ( 'prem_license' === $data['post_type'] && 'auto-draft' !== $data['post_status'] ) {

			if ( ! is_admin() ) {
				require_once ABSPATH . 'wp-admin/includes/post.php';
			}

			$existing = \post_exists( $data['post_title'] );

			if ( $existing && $existing !== $post_data['ID'] || empty( $data['post_title'] ) ) {
				$data['post_title'] = $this->generate_license( $post_data['ID'] );
			}
		}
		return $data;
	}

	/**
	 * Verified if the key structure is correct.
	 *
	 * @param string $license_key The license key.
	 * @return bool The result.
	 */
	public function verify_license_key_structure( $license_key ) {
		$clean_key     = str_replace( '/[^a-zA-Z0-9]+/', '', $license_key );
		$remove_dashes = str_replace( '-', '', $clean_key );
		if ( strlen( $remove_dashes ) === 16 ) {
			return true;
		}
		return false;
	}

	/**
	 * Generate a license
	 *
	 * @param int $post_id The Post ID.
	 * @return string The license key.
	 */
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

	/**
	 * Create a license.
	 *
	 * @param int $product_id The Product ID.
	 * @param int $user_id The User ID.
	 * @return int The licence post ID.
	 */
	public static function create_license( $product_id, $user_id ) {
		Debug::log( "Create license (product #{$product_id}) for user #{$user_id}" );

		$license_args = array(
			'post_title'  => self::generate_license(),
			'post_status' => 'publish',
			'post_type'   => 'prem_license',
			'post_author' => $user_id,
			'meta_input'  => array(
				'_premia_linked_post_id' => $product_id,
			),
		);

		$license_days = intval( get_post_meta( $product_id, '_updater_license_validity', true ) );

		// Only add meta when license days is above 0.
		if ( 0 !== $license_days && $license_days > 0 ) {
			$today = new \Datetime();
			$today->setTimestamp( time() );
			$today->modify( "+{$license_days}days" );
			$license_args['meta_input']['_premia_expiry_date'] = $today->getTimestamp();
		}

		$license_id = wp_insert_post( $license_args );

		if ( ! is_wp_error( $license_id ) ) {
			Debug::log( "Succesfuly created license #{$license_id} " );
		} else {
			Debug::log( "An error occured while creating license #{$license_id}." );
		}

		return $license_id;
	}

	/**
	 * Check site
	 *
	 * @param string $license_key The license key.
	 * @param string $site_url The site URL.
	 */
	public static function check_site( $license_key, $site_url ) {
		Debug::log( 'Check site: ' . $site_url );
		$post = self::get_license_by_license_key( $license_key );
		if ( null !== $post ) {
			$sites = get_post_meta( $post->ID, 'installations', true );
			if ( is_array( $sites ) && in_array( $site_url, $sites, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add a site to a license.
	 *
	 * @param string $license_key The license key.
	 * @param string $site_url The site URL.
	 */
	public static function add_site( $license_key, $site_url ) {
		Debug::log( 'Add site: ' . $site_url );
		$post = self::get_license_by_license_key( $license_key );
		if ( null !== $post && is_a( $post, 'WP_Post' ) ) {
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

	/**
	 * Remove a site from license.
	 *
	 * @param string $license_key The license key.
	 * @param string $site_url The site URL.
	 */
	public static function remove_site( $license_key, $site_url ) {
		Debug::log( 'Remove site: ' . $site_url );
		$post = self::get_license_by_license_key( $license_key );
		if ( null !== $post && is_a( $post, 'WP_Post' ) ) {
			$sites = get_post_meta( $post->ID, 'installations', true );
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

	/**
	 * Retrieve a license post by license key.
	 *
	 * @param string $license_key The license key.
	 * @return object The post object.
	 */
	public static function get_license_by_license_key( $license_key ) {
		$post = get_page_by_title( $license_key, OBJECT, 'prem_license' );
		return apply_filters( 'premia_get_license_by_license_key', $post, $license_key );
	}

	/**
	 * Get linked post from license key.
	 *
	 * @param string $license_key The license key.
	 * @return int a post ID.
	 */
	public static function get_linked_post_by_license_key( $license_key ) {
		$post_id = false;
		$post    = self::get_license_by_license_key( $license_key );
		if ( ! is_wp_error( $post ) && is_a( $post, 'WP_Post' ) && 'publish' === $post->post_status ) {
			$post_id = get_post_meta( $post->ID, '_premia_linked_post_id', true );
		}
		return $post_id;
	}

	/**
	 * Activate a license
	 *
	 * @param array $license_info An array of license information.
	 * @return bool The result.
	 */
	public static function activate( $license_info ) {

		Debug::log( 'activate:', $license_info );

		// Check for status.
		if ( isset( $license_info['action'] ) && 'status' === $license_info['action'] ) {
			return self::check_site( $license_info['license_key'], $license_info['site_url'] );
		}

		$validate = self::validate_license_key( $license_info );
		if ( $validate ) {
			if ( ! empty( $license_info['site_url'] ) ) {
				// Check if license key belongs to post.
				self::add_site( $license_info['license_key'], esc_url_raw( $license_info['site_url'] ) );
			}
		}
		return apply_filters( 'premia_activate_license', $validate, $license_info );
	}

	/**
	 * Deactivate a license
	 *
	 * @param array $license_info An array of license information.
	 * @return bool The result.
	 */
	public static function deactivate( $license_info ) {
		$status = false;
		if ( isset( $license_info['license_key'] ) && isset( $license_info['site_url'] ) ) {
			if ( ! isset( $license_info['post_id'] ) && isset( $license_info['license_key'] ) ) {
				$linked_post = intval( self::get_linked_post_by_license_key( $license_info['license_key'] ) );
				if ( is_int( $linked_post ) ) {
					$license_info['post_id'] = $linked_post;
				}
			}

			if ( self::license_has_access( $license_info ) ) {
				$status = true;
				self::remove_site( $license_info['license_key'], esc_url_raw( $license_info['site_url'] ) );
			}
		}
		return apply_filters( 'premia_deactivate_license', $status, $license_info );
	}

	/**
	 * Get license
	 *
	 * @param array $license_info An array of license information.
	 * @return $object The license object.
	 */
	public static function get_license( $license_info ) {
		$license = self::get_linked_post_by_license_key( $license_info['license_key'] );
		return apply_filters( 'premia_get_license', $license, $license_info );
	}

	/**
	 * Validate a license key
	 *
	 * @param array $license_info An array of license information.
	 * @return bool $validate The result.
	 */
	public static function validate_license_key( $license_info ) {
		$validate = false;

		if ( ! isset( $license_info['post_id'] ) && isset( $license_info['license_key'] ) ) {
			$linked_post = intval( self::get_linked_post_by_license_key( $license_info['license_key'] ) );
			if ( is_int( $linked_post ) && 0 !== $linked_post ) {
				$validate = true;
			}
		}

		if ( ! self::license_has_access( $license_info ) ) {
			$validate = false;
		}

		Debug::log( 'Validation result: ', $validate );

		return apply_filters( 'premia_validate_license', $validate, $license_info );
	}

	/**
	 * Validate a site
	 *
	 * @param array $license_info An array of license information.
	 * @return bool The result.
	 */
	public static function validate_site( $license_info ) {
		$validate = false;

		if ( ! isset( $license_info['post_id'] ) && isset( $license_info['license_key'] ) ) {
			$linked_post = intval( self::get_linked_post_by_license_key( $license_info['license_key'] ) );
			Debug::log( 'Linked post:', $linked_post );
			if ( is_int( $linked_post ) ) {
				$license_info['post_id'] = $linked_post;
			}
		}

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

		if ( self::license_is_expired( $license_info ) ) {
			$output['name'] = 'License has expired.';
			return false;
		}

		// Check if license key belongs to post.
		if ( ! self::license_has_access( $license_info ) ) {
			return false;
		}

		$sites = self::get_installations( $license_info['license_key'] );

		if ( ! is_array( $sites ) ) {
			$sites = array();
		}

		Debug::log( 'Sites: ', $sites, 2 );

		if ( isset( $license_info['site_url'] ) ) {
			Debug::log( 'Site: ', $license_info['site_url'], 2 );
			if ( in_array( $license_info['site_url'], $sites, true ) || is_user_logged_in() ) {
				$validate = true;
			}
		}

		Debug::log( 'Validation result: ', $validate );

		return apply_filters( 'premia_validate_license', $validate, $license_info );
	}

	/**
	 * Does the license have access to the post?
	 *
	 * @param array $license_info An array of license information.
	 * @return bool The result.
	 */
	public static function license_has_access( $license_info ) {
		$linked_post = intval( self::get_linked_post_by_license_key( $license_info['license_key'] ) );
		if ( 0 !== $linked_post ) {
			$name = get_post_field( 'post_name', $linked_post );
			if ( ( isset( $license_info['post_id'] ) && $license_info['post_id'] !== $linked_post ) || $license_info['plugin'] !== $name ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Is the license active?
	 *
	 * @param string $license_key The license key.
	 * @return bool The result.
	 */
	public static function is_license_active( $license_key ) {
		$license = self::get_license_by_license_key( $license_key );

		if ( is_a( $license, 'WP_Post' ) ) {

			Debug::log( 'post status:', $license->post_status );

			if ( 'publish' !== $license->post_status ) {
				$validate = false;
			}
		}

		return apply_filters( 'premia_is_license_active', $validate, $license_key );

	}

	/**
	 * Get installations of license key
	 *
	 * @param string $license_key The license key.
	 * @return array Array of sites.
	 */
	public static function get_installations( $license_key ) {
		$sites   = array();
		$license = self::get_license_by_license_key( $license_key );
		if ( is_a( $license, 'WP_Post' ) ) {
			$sites = get_post_meta( $license->ID, 'installations', true );
		}
		return apply_filters( 'premia_get_installations', $sites, $license_key );
	}

	/**
	 * Check if license is expired.
	 *
	 * @param array $license_info An array of license information.
	 * @return bool The result.
	 */
	public static function license_is_expired( $license_info ) {
		$status = false;

		Debug::log( 'Check if license is expired.' );

		if ( isset( $license_info['license_key'] ) ) {
			$license = self::get_license_by_license_key( $license_info['license_key'] );

			if ( ! is_wp_error( $license ) && is_a( $license, 'WP_Post' ) ) {
				$expiry_timestamp = get_post_meta( $license->ID, '_premia_expiry_date', true );

				if ( ! empty( $expiry_timestamp ) ) {
					if ( $expiry_timestamp < time() ) {
						$status = true;
						Debug::log( 'License is expired.', $license_info );
					}
				}
			}
		}

		return $status;
	}
}
