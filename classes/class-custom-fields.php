<?php
namespace Premia;

/**
 * Github API Class

 * @since 1.0
 */
class Custom_Fields {

	private $metabox_id = 'premia-settings';

	public function __construct() {
		$this->init();
	}

	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post', array( $this, 'update_fields' ) );

		add_filter( 'premia_update_field', array( $this, 'replace_github_url' ) );
		add_filter( 'premia_update_field', array( $this, 'github_remove_last_slash' ) );
		add_filter( 'premia_update_field', array( $this, 'do_not_validate' ), 10, 3 );
	}

	public static function get_fields( $type = 'all' ) {
		$doc_generate_url = 'https://docs.github.com/en/github/authenticating-to-github/keeping-your-account-and-data-secure/creating-a-personal-access-token';
		$doc_bot_url      = 'https://docs.github.com/en/github/getting-started-with-github/learning-about-github/types-of-github-accounts';

		$fields = array();

		$license_fields = apply_filters(
			'premia_customize_license_fields',
			array(
				array(
					'name'    => '_premia_linked_post_id',
					'label'   => __( 'Linked Post or Page', 'premia' ),
					'desc'    => __( 'Select a post that this license is linked to.', 'premia' ),
					'type'    => 'select',
					'visible' => true,
				),
			)
		);

		$post_fields = apply_filters(
			'premia_customize_post_fields',
			array(
				array(
					'name'    => '_updater_repo',
					'label'   => __( 'Github API URL', 'premia' ),
					'desc'    => __( 'You can also paste the URL to your Github Repo', 'premia' ),
					'type'    => 'text',
					'visible' => true,
				),
				array(
					'name'    => '_updater_api_token',
					'type'    => 'password',
					'label'   => __( 'Github API Key', 'premia' ),
					'desc'    => sprintf( __( '%1$sCreating a personal access token%2$s - %3$sTypes of Github accounts%4$s.', 'premia' ), '<a href="' . $doc_generate_url . '">', '</a>', '<a href="' . $doc_bot_url . '">', '</a>' ),
					'visible' => true,
				),
				array(
					'name'    => '_updater_do_not_validate_licenses',
					'type'    => 'checkbox',
					'label'   => __( 'Do not validate licenses', 'premia' ),
					'desc'    => __( 'When enabling this option, license checks are disabled.', 'premia' ),
					'visible' => false,
				),
			)
		);

		switch ( $type ) {
			case 'license':
				$fields = $license_fields;
				break;
			case 'post':
				$fields = $post_fields;
				break;
			default:
				$fields = array_merge( $license_fields, $post_fields );
				break;
		}

		return $fields;
	}

	public function add_metabox() {
		add_meta_box( $this->metabox_id, __( 'Premia settings', 'premia' ), array( $this, 'render_post_fields' ), array( 'post', 'page' ) );
		add_meta_box( $this->metabox_id, __( 'License settings', 'premia' ), array( $this, 'render_license_fields' ), array( 'prem_license' ) );
	}

	public function render_fields( $fields, $post ) {
		foreach ( $fields as $field ) {
			if ( $field['visible'] === true ) {
				echo '<div>';
				switch ( $field['type'] ) {
					case 'checkbox':
						$checked = ( get_post_meta( $post->ID, $field['name'], true ) === 'on' ? ' checked="checked"' : '' );
						echo '<label for="' . $field['name'] . '">';
						echo '<input id="' . $field['name'] . '" type="' . $field['type'] . '" name="' . $field['name'] . '" ' . $checked . ' />';
						echo $field['label'];
						echo '</label>';
						break;
					case 'select':
						echo '<label for="' . $field['name'] . '">' . $field['label'] . '</label><br/>';
						$choices = get_posts(
							array(
								'post_type'    => array( 'post', 'page' ),
								'numbersposts' => -1,
							)
						);
						echo '<select id="' . $field['name'] . '" name="' . $field['name'] . '">';
						foreach ( $choices as $choice ) {
							$selected = ( intval( get_post_meta( $post->ID, $field['name'], true ) ) === $choice->ID ? ' selected="selected"' : '' );
							echo '<option name="' . $choice->ID . '" value="' . $choice->ID . '" ' . $selected . '>' . $choice->post_title . '</option>';
						}
						echo '</select>';
						echo '<div><i>' . $field['desc'] . '</i></div>';
						break;
						break;
					default:
						echo '<label for="' . $field['name'] . '">' . $field['label'] . '</label><br/>';
						echo '<input id="' . $field['name'] . '" type="' . $field['type'] . '" name="' . $field['name'] . '" value="' . get_post_meta( $post->ID, $field['name'], true ) . '" />';
						echo '<div><i>' . $field['desc'] . '</i></div>';
						break;
				}
				echo '</div><br/>';
			}
		}
	}

	public function render_post_fields( $post ) {
		$fields = $this->get_fields( 'post' );
		return $this->render_fields( $fields, $post );
	}

	public function render_license_fields( $post ) {
		$fields = $this->get_fields( 'license' );
		return $this->render_fields( $fields, $post );
	}

	public function update_fields( $post_id ) {

		$fields = $this->get_fields();

		foreach ( $fields as $field ) {
			$value = '';
			if ( isset( $_POST[ $field['name'] ] ) ) {
				$value = sanitize_text_field( $_POST[ $field['name'] ] );
			}
			$value = apply_filters( 'premia_update_field', $value, $field, $post_id );
			update_post_meta( $post_id, $field['name'], $value );
		}
	}

	public static function replace_github_url( $value ) {
		if ( strpos( $value, 'github.com' ) !== false && strpos( $value, 'api.github.com' ) === false ) {
			$value = rtrim( sanitize_text_field( $value ), '/' );
			$value = str_replace( 'github.com', 'api.github.com/repos', $value );
		}
		return $value;
	}

	public static function github_remove_last_slash( $value ) {
		if ( strpos( $value, 'github.com' ) !== false ) {
			$value = rtrim( sanitize_text_field( $value ), '/' );
		}
		return $value;
	}

	public function do_not_validate( $value, $field, $post_id ) {
		if ( get_post_type( $post_id ) !== 'product' ) {
			if ( $field['name'] === '_updater_do_not_validate_licenses' ) {
				$value = 'on';
			}
		}
		return $value;
	}
}
