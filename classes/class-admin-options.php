<?php
namespace Premia;

/**
 * Admin Options

 * @since 1.0
 */
class Admin_Options {

	private $api_url = 'https://getpremia.com/wp-json/license-updater/v1/';

	private $plugin_name = 'premia';

	public function __construct() {
		$this->api_url = apply_filters( 'premia_api_url', $this->api_url );
		$this->init();
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	public function add_menu_page() {
		add_menu_page(
			'Premia',
			__( 'Premia settings', 'premia' ),
			'manage_options',
			$this->plugin_name . '-settings',
			array( $this, 'settings_page' ),
			'data:image/svg+xml;base64,' . base64_encode( '<svg width="34" height="36" viewBox="0 0 34 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.0914 16.4397L1.54909 17.2336L15.0458 2.94295L9.0914 16.4397Z"/><path d="M10.4205 16.4L16.981 1.52961L23.1041 16.4H10.4205Z"/><path d="M24.4178 16.4388L18.7884 2.76746L32.4597 17.243L24.4178 16.4388Z"/><path d="M9.10933 17.6444L15.2991 33.3252L1.26894 18.4697L9.10933 17.6444Z"/><path d="M23.1395 17.6L16.9807 34.3169L10.3819 17.6H23.1395Z"/><path d="M24.4024 17.6432L32.7256 18.4755L18.5763 33.4572L24.4024 17.6432Z"/></svg>' ),
		);
	}

	public function settings_page() {

		$license_verified = true;

		$option_name     = str_replace( '-', '_', $this->plugin_name ) . '_license_key';
		$tag_option_name = str_replace( '-', '_', $this->plugin_name ) . '_tag';

		if ( isset( $_GET['action'] ) && 'recheck-permissions' === $_GET['action'] ) {
			$notices = Admin_Notices::get_notices();
			foreach ( $notices['notices'] as $key => $notice ) {
				if ( $notice['type'] === 'permission-issue' ) {
					if ( File_Directory::is_protected_file( $notice['data']['file'], false ) ) {
						Admin_Notices::remove_notice( $key );
					}
				}
			}
		}

		if ( isset( $_POST[ $option_name ] ) ) {

			if ( wp_verify_nonce( $_POST['_wpnonce'] ) ) {

				$license = sanitize_text_field( $_POST[ $option_name ] );
				$tag     = sanitize_text_field( $_POST[ $tag_option_name ] );
				$action  = sanitize_text_field( $_POST['action'] );

				$url  = $this->api_url . 'activate';
				$args = array(
					'body' => array(
						'license_key' => $license,
						'site_url'    => get_site_url(),
						'action'      => $action,
					),
				);

				Debug::log( 'Executing request to: ' . $url, $args );

				$activate = wp_remote_post(
					$this->api_url . $action,
					$args
				);

				$status = wp_remote_retrieve_response_code( $activate );

				Debug::log( 'Result status: ' . $url, $status );
				Debug::log( 'Result body: ' . $url, wp_remote_retrieve_body( $activate ) );

				if ( $action === 'deactivate' ) {
					$license = '';
				}

				if ( $status !== 200 && $action === 'activate' ) {
					echo '<div class="notice notice-error"><p>Failed to activate license.</p></div>';
				} else {
					if ( $action === 'activate' ) {
						Updater::check_for_updates();
						$message = __( 'License activated!', 'premia-demo' );
					} else {
						$message = __( 'License deactivated!', 'premia-demo' );
					}

					echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
					update_option( $option_name, $license );
				}
			}
			update_option( $tag_option_name, $tag );
		}

		$current_license = get_option( $option_name );
		$current_tag     = get_option( $tag_option_name );

		if ( ! empty( $current_license ) ) {
			// Check if license is still active.
			$activate = wp_remote_post(
				$this->api_url . 'activate',
				array(
					'body' => array(
						'license_key' => $current_license,
						'site_url'    => get_site_url(),
						'action'      => 'status',
					),
				)
			);

			$status = wp_remote_retrieve_response_code( $activate );

			if ( $status !== 200 ) {
				$license_verified = false;
				echo '<div class="notice notice-error"><p>' . __( 'Please re-activate your license.', 'premia-demo' ) . '</p></div>';
			}
		}
		?>
		<div class="wrap">
		<h1>Premia <?php _e( 'Settings', 'premia' ); ?></h1>
		<table class="form-table" role="presentation">
		<form method="POST">
		<?php wp_nonce_field(); ?>
		<tbody>
			<tr>
				<th scope="row"><label for="<?php echo esc_html( $option_name ); ?>"><?php _e( 'License Key', 'premia' ); ?></label></th>
				<td>
					<?php
					echo '<input type="text" name="' . esc_html( $option_name ) . '" id="' . esc_html( $option_name ) . '" ' . ( ( ! empty( $current_license ) && $license_verified === true ) ? 'readonly="readonly"' : '' ) . ' value="' . esc_html( $current_license ) . '" placeholder="' . __( 'Enter License key', 'premia' ) . '" class="regular-text" />';
					echo '<input type="hidden" name="action" value="' . ( ( ! empty( $current_license ) && $license_verified === true ) ? 'deactivate' : 'activate' ) . '" />';
					echo '<input class="button-primary" type="submit" value="' . ( ( ! empty( $current_license ) && $license_verified === true ) ? 'Deactivate' : 'Activate' ) . '" />';
					?>
				</td>
			</tr>
			<?php if ( WP_DEBUG ) : ?>
			<tr>
				<th scope="row"><label for="<?php echo esc_html( $tag_option_name ); ?>"><?php _e( 'Tag', 'premia' ); ?></label></th>
				<td>
					<?php
					echo '<input type="text" name="' . esc_html( $tag_option_name ) . '" id="' . esc_html( $tag_option_name ) . '" value="' . esc_html( $current_tag ) . '" placeholder="' . __( 'Enter tag', 'premia' ) . '" class="regular-text" />';
					echo '<input class="button-primary" type="submit" value="' . __( 'Update', 'premia' ) . '" />';
					?>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
		</table>
		</form>
		<div class="wrap">
			<h1><?php _e( 'Status', 'premia' ); ?></h1>
			<p><a class="button button-secondary" href="<?php echo admin_url( 'admin.php?page=premia-settings&action=recheck-permissions' ); ?>"><?php _e( 'Re-check permissions', 'premia' ); ?></a></p>
		</div>
		</div>
		<?php
	}
}
