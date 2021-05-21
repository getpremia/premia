<?php
namespace Premia;

/**
 * Github API Class

 * @since 1.0
 */
class Licenses {

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	public function register_post_types() {
		register_post_type('prem_license', array(
			'public' => true,
			'label' => 'Licenses',
			'description'        => 'Recipe custom post type.',
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => false,
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 10,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail' ),
			'taxonomies'         => array(  ),
			'show_in_rest'       => false,
			'menu_icon' => 'data:image/svg+xml;base64,' . base64_encode( '<svg width="34" height="36" viewBox="0 0 34 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.0914 16.4397L1.54909 17.2336L15.0458 2.94295L9.0914 16.4397Z"/><path d="M10.4205 16.4L16.981 1.52961L23.1041 16.4H10.4205Z"/><path d="M24.4178 16.4388L18.7884 2.76746L32.4597 17.243L24.4178 16.4388Z"/><path d="M9.10933 17.6444L15.2991 33.3252L1.26894 18.4697L9.10933 17.6444Z"/><path d="M23.1395 17.6L16.9807 34.3169L10.3819 17.6H23.1395Z"/><path d="M24.4024 17.6432L32.7256 18.4755L18.5763 33.4572L24.4024 17.6432Z"/></svg>' )
		));

		add_action( 'edit_form_top', function($post) {
			if( 'prem_license' == $post->post_type )
				echo "<a href='#' id='my-custom-header-link'>$post->post_type</a>";
		} );

	}
}
