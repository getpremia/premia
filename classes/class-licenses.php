<?php
namespace Premia;

/**
 * Licenses

 * @since 1.0
 */
class Licenses {

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );

		add_action( 'manage_prem_license_posts_columns', array( $this, 'manage_columns' ) );
		add_action( 'manage_prem_license_posts_custom_column', array( $this, 'columns_content' ), 10, 2 );

		add_action( 'wp_insert_post_data', array( $this, 'insert_license' ), 10, 2 );
	}

	public function manage_columns( $columns ) {
		unset( $columns['author'] );
		$columns['post'] = __( 'Linked post', 'premia' );
		return $columns;
	}

	public function columns_content( $column, $post_id ) {
		switch ( $column ) {
			case 'post':
				$linked_id = get_post_meta( $post_id, '_premia_linked_post_id', true );
				echo '<a href="' . get_edit_post_link( $linked_id ) . '">' . get_the_title( $linked_id ) . '</a>';
				break;
		}
	}

	public function register_post_types() {
		register_post_type(
			'prem_license',
			array(
				'public'             => true,
				'label'              => 'Licenses',
				'description'        => 'Recipe custom post type.',
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

			$existing = post_exists( $data['post_title'] );

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

	public function generate_license( $post_id ) {
		$license_key = implode( '-', str_split( substr( md5( random_bytes( 16 ) ), 0, 16 ), 4 ) );
		$existing    = post_exists( $license_key );
		if ( $existing && $existing !== $post_id ) {
			return $this->generate_license();
		}
		return strtoupper( $license_key );
	}

	public function create_license() {
		return wp_insert_post(array(
			'post_title' => $this->generate_license(),
			'post_status' => 'publish',
			'post_type' => 'prem_license'
		));
	}

	public static function activate( $license_info ) {
		return apply_filters( 'premia_activate_license', '__return_true', $license_info );
	}

	public static function deactivate( $license_info ) {
		return apply_filters( 'premia_activate_license', '__return_true', $license_info );
	}

	public static function get_license( $license_info ) {
		return apply_filters( 'premia_get_license', '__return_true', $license_info );
	}
}
