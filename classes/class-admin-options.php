<?php
/**
 * Admin Options
 *
 * @package Premia
 * @since 1.0
 */

namespace Premia;

/**
 * Admin Options
 *
 * @since 1.0
 */
class Admin_Options {

	/**
	 * The API url.
	 *
	 * @var string The API url.
	 */
	private $api_url = 'https://getpremia.com/wp-json/premia/v1/';

	/**
	 * The plugin name.
	 *
	 * @var string The plugin name.
	 */
	private $plugin_name = 'premia';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_url = apply_filters( 'premia_api_url', $this->api_url );
		$this->init();
	}

	/**
	 * Initializer.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'check_permissions' ) );
	}

	/**
	 * Add a menu page.
	 */
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

	/**
	 * Check Permissions and possibly remove notice.
	 */
	public function check_permissions() {
		if ( isset( $_GET['action'] ) && 'recheck-permissions' === $_GET['action'] ) {
			$notices = Admin_Notices::get_notices();
			foreach ( $notices['notices'] as $key => $notice ) {
				if ( 'permission-issue' === $notice['type'] ) {
					if ( File_Directory::is_protected_file( $notice['data']['file'], false ) ) {
						Admin_Notices::remove_notice( $key );
					}
				}
			}
		}
	}

	/**
	 * The settings page.
	 */
	public function settings_page() {

		$license_verified = true;

		$option_name     = str_replace( '-', '_', $this->plugin_name ) . '_license_key';
		$tag_option_name = str_replace( '-', '_', $this->plugin_name ) . '_tag';

		if ( isset( $_POST[ $option_name ] ) ) {

			if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ) ) ) {

				$tag    = '';
				$action = 'activate';

				$license = sanitize_text_field( wp_unslash( $_POST[ $option_name ] ) );
				if ( isset( $_POST['action'] ) ) {
					$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
				}

				if ( isset( $_POST[ $tag_option_name ] ) ) {
					$tag = sanitize_text_field( wp_unslash( $_POST[ $tag_option_name ] ) );
				}

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

				if ( 'deactivate' === $action ) {
					$license = '';
				}

				if ( 200 !== $status && 'activate' === $action ) {
					echo '<div class="notice notice-error"><p>Failed to activate license.</p></div>';
				} else {
					if ( 'activate' === $action ) {
						Updater::check_for_updates();
						$message = __( 'License activated!', 'premia' );
					} else {
						$message = __( 'License deactivated!', 'premia' );
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

			if ( 200 !== $status ) {
				$license_verified = false;
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Please re-activate your license.', 'premia' ) . '</p></div>';
			}
		}
		?>
		<div class="wrap">
		<h1>Premia <?php esc_html_e( 'Settings', 'premia' ); ?></h1>
		<table class="form-table" role="presentation">
		<form method="POST">
		<?php wp_nonce_field(); ?>
		<tbody>
			<tr>
				<th scope="row"><label for="<?php echo esc_html( $option_name ); ?>"><?php esc_html_e( 'License Key', 'premia' ); ?></label></th>
				<td>
					<?php
					echo '<input type="text" name="' . esc_html( $option_name ) . '" id="' . esc_html( $option_name ) . '" ' . ( ( ! empty( $current_license ) && true === $license_verified ) ? 'readonly="readonly"' : '' ) . ' value="' . esc_html( $current_license ) . '" placeholder="' . esc_html__( 'Enter License key', 'premia' ) . '" class="regular-text" />';
					echo '<input type="hidden" name="action" value="' . ( ( ! empty( $current_license ) && true === $license_verified ) ? 'deactivate' : 'activate' ) . '" />';
					echo '<input class="button-primary" type="submit" value="' . ( ( ! empty( $current_license ) && true === $license_verified ) ? 'Deactivate' : 'Activate' ) . '" />';
					?>
				</td>
			</tr>
			<?php if ( WP_DEBUG ) : ?>
			<tr>
				<th scope="row"><label for="<?php echo esc_html( $tag_option_name ); ?>"><?php esc_html_e( 'Tag', 'premia' ); ?></label></th>
				<td>
					<?php
					echo '<input type="text" name="' . esc_html( $tag_option_name ) . '" id="' . esc_html( $tag_option_name ) . '" value="' . esc_html( $current_tag ) . '" placeholder="' . esc_html__( 'Enter tag', 'premia' ) . '" class="regular-text" />';
					echo '<input class="button-primary" type="submit" value="' . esc_html__( 'Update', 'premia' ) . '" />';
					?>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
		</table>
		</form>
		<div class="wrap">
			<h1><?php esc_html_e( 'Status', 'premia' ); ?></h1>
			<p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=premia-settings&action=recheck-permissions' ) ); ?>"><?php esc_html_e( 'Re-check permissions', 'premia' ); ?></a></p>
		</div>
		</div>
		<?php
	}
}
