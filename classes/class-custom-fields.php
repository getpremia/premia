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
	}

	public function get_fields() {
		$doc_generate_url = 'https://docs.github.com/en/github/authenticating-to-github/keeping-your-account-and-data-secure/creating-a-personal-access-token';
		$doc_bot_url      = 'https://docs.github.com/en/github/getting-started-with-github/learning-about-github/types-of-github-accounts';
		return array(
			array(
				'name'  => '_updater_repo',
				'label' => __( 'Github API URL', 'premia' ),
				'desc'  => __( 'You can also paste the URL to your Github Repo', 'premia' ),
				'type'  => 'text',
			),
			array(
				'name'  => '_updater_api_token',
				'type'  => 'password',
				'label' => __( 'Github API Key', 'premia' ),
				'desc'  => sprintf( __( '%1$sCreating a personal access token%2$s - %3$sTypes of Github accounts%4$s.', 'premia' ), '<a href="' . $doc_generate_url . '">', '</a>', '<a href="' . $doc_bot_url . '">', '</a>' ),
			),
			array(
				'name'  => '_updater_do_not_validate_licenses',
				'type'  => 'checkbox',
				'label' => __( 'Do not validate licenses', 'premia' ),
				'desc'  => __( 'When enabling this option, license checks are disabled.', 'premia' ),
			),
		);
	}

	public function add_metabox() {
		add_meta_box( $this->metabox_id, __( 'Premia settings', 'premia' ), array( $this, 'render_fields' ), array( 'post', 'page' ) );
	}

	public function render_fields( $post ) {
		$fields = $this->get_fields();
		foreach ( $fields as $field ) {
			echo '<div>';
			switch ( $field['type'] ) {
				case 'checkbox':
					$checked = ( get_post_meta( $post->ID, $field['name'], true ) === 'on' ? ' checked="checked"' : '' );
					echo '<label for="' . $field['name'] . '">';
					echo '<input id="' . $field['name'] . '" type="' . $field['type'] . '" name="' . $field['name'] . '" ' . $checked . ' />';
					echo $field['label'];
					echo '</label>';
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

	public function update_fields( $post_id ) {

		$fields = $this->get_fields();

		foreach ( $fields as $field ) {
			$value = apply_filters( 'premia_update_field', sanitize_text_field( $_POST[ $field['name'] ] ), $post_id );
			update_post_meta( $post_id, $field['name'], $value );
		}
	}

	public function replace_github_url( $value ) {
		if ( strpos( $value, 'github.com' ) !== false && strpos( $value, 'api.github.com' ) === false ) {
			$value = rtrim( sanitize_text_field( $value ), '/' );
			$value = str_replace( 'github.com', 'api.github.com/repos', $value );
		}
		return $value;
	}
}
