<?php
/**
 *  Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for Plugin Licensing which restricts access to some BKAP Modules based on type of license.
 *
 * @author      Tyche Softwares
 * @package     BKAP/License
 * @category    Classes
 * @since       5.12.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_License' ) ) {

	/**
	 * BKAP License class.
	 *
	 * @since 5.12.0
	 */
	class BKAP_License {

		/**
		 * License Key.
		 *
		 * @var string
		 */
		public static $license_key = '';

		/**
		 * License Type.
		 *
		 * @var string
		 */
		public static $license_type = '';

		/**
		 * License Status.
		 *
		 * @var string
		 */
		public static $license_status = '';

		/**
		 * General License Error Message.
		 *
		 * @var string
		 */
		public static $license_error_message = 'You are on the %1$s License. This feature is available only on the %2$s License.';

		/**
		 * Plugin License Error Message.
		 *
		 * @var string
		 */
		public static $plugin_license_error_message = 'You have activated the %1$s Plugin. Your current license ( %2$s ) does not offer support for Vendor Plugins. Please upgrade to the %3$s License.';

		/**
		 * Initializes the BKAP_License() class. Checks for an existing instance and if it doesn't find one, it then creates it.
		 *
		 * @since 5.12.0
		 */
		public static function init() {

			static $instance = false;

			if ( ! $instance ) {
				$instance = new BKAP_License();
			}

			return $instance;
		}

		/**
		 * Default Constructor
		 *
		 * @since 5.12.0
		 */
		public function __construct() {
			self::load_license_data();
			add_action( 'admin_init', array( $this, 'save_license_key_and_maybe_reactivate' ) );
			add_action( 'admin_init', array( $this, 'deactivate_license' ) );
			add_action( 'admin_init', array( $this, 'activate_license' ) );
			add_action( 'admin_notices', array( &$this, 'vendor_plugin_license_error_notice' ), 15 );
			add_action( 'init', array( &$this, 'remove_wp_actions' ), 10 );
		}

		/**
		 * Load data from DB.
		 *
		 * @since 5.12.0
		 */
		public static function load_license_data() {
			self::$license_key    = get_option( 'edd_sample_license_key', '' );
			self::$license_type   = get_option( 'edd_sample_license_type', '' );
			self::$license_status = get_option( 'edd_sample_license_status', '' );
		}

		/**
		 * This function add the license page in the Booking menu.
		 *
		 * @since 1.7
		 */
		public static function display_license_page() {
			?>

		   <div class="wrap">
				<h2>
					<?php esc_html_e( 'Plugin License Options', 'woocommerce-booking' ); ?>
				</h2>

				<?php
				if ( isset( $_GET['bkap_license_notice'] ) && 'error' === $_GET['bkap_license_notice'] ) { // phpcs:ignore
					$notice = sprintf(
						/* translators: %s: License Key */
						__( 'An error has been encountered while trying to activate your license key: %s. Please check that you typed in the key correctly ( make sure to save ) and try again.', 'woocommerce-booking' ),
						self::$license_key
					);

					self::display_error_notice( $notice );
				}
				?>
							
				<form method="post" action="options.php">

				<?php settings_fields( 'bkap_edd_sample_license' ); ?>

					<table class="form-table">
						<tbody>
							<tr valign="top">	
								<th scope="row" valign="top">
									<?php esc_html_e( 'License Key', 'woocommerce-booking' ); ?>
								</th>

								<td>
									<input id="edd_sample_license_key" name="edd_sample_license_key" type="text" class="regular-text" value="<?php esc_attr_e( self::$license_key ); // phpcs:ignore ?>" />
									<label class="description" for="edd_sample_license_key"><?php esc_html_e( 'Enter your license key', 'woocommerce-booking' ); ?></label>
								</td>
							</tr>

							<?php if ( false !== self::$license_key ) { ?>
								<tr valign="top">	
									<th scope="row" valign="top">
										<?php esc_html_e( 'Activate License', 'woocommerce-booking' ); ?>
									</th>

									<td>
									<?php if ( 'valid' === self::$license_status ) { ?>
										<span style="color:green;"><?php esc_html_e( 'active', 'woocommerce-booking' ); ?></span>
										<?php wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
										<input type="submit" class="button-secondary" name="bkap_edd_license_deactivate" value="<?php esc_attr_e( 'Deactivate License', 'woocommerce-booking' ); ?>"/>
										<?php
									} else {
											wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' );
										?>
											<input type="submit" class="button-secondary" name="bkap_edd_license_activate" value="<?php esc_attr_e( 'Activate License', 'woocommerce-booking' ); ?>"/>
									<?php } ?>
									</td>
								</tr>
								<?php } ?>
						</tbody>
					</table>	
					<?php submit_button(); ?>
				</form>
			<?php
		}

		/**
		 * This function stores the license key once the plugin is installed and the license key saved.
		 *
		 * @since 5.12.0
		 */
		public static function save_license_key_and_maybe_reactivate() {
			register_setting( 'bkap_edd_sample_license', 'edd_sample_license_key', array( 'BKAP_License', 'reactivate_if_new_license' ) );
		}

		/**
		 * Places a call to fetch license details.
		 *
		 * @param string $action Action to send to remote server while fetching license.
		 *
		 * @since 5.12.0
		 */
		public static function fetch_license( $action = 'check_license' ) {

			$api_params = array(
				'edd_action' => $action,
				'license'    => self::$license_key,
				'item_name'  => rawurlencode( EDD_SL_ITEM_NAME_BOOK ),
			);

			// Call the Tyche API.
			$response = wp_remote_get(
				esc_url_raw( add_query_arg( $api_params, EDD_SL_STORE_URL_BOOK ) ),
				array(
					'timeout'   => 15,
					'sslverify' => false,
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			return json_decode( wp_remote_retrieve_body( $response ) );
		}

		/**
		 * This function will check the license entered using an API call to the store website and if its valid it will activate the license.
		 *
		 * @since 5.12.0
		 */
		public static function activate_license() {

			if ( isset( $_POST['bkap_edd_license_activate'] ) ) { // phpcs:ignore

				if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) ) {
					return; // get out if we didn't click the Activate button.
				}

				$license_data  = self::fetch_license( 'activate_license' );
				$http_referrer = $_POST['_wp_http_referer']; // phpcs:ignore
				$did_update    = false;

				if ( $license_data && isset( $license_data->license ) && '' !== $license_data->license && 'invalid' !== $license_data->license ) {

					$did_update           = true;
					self::$license_status = $license_data->license;
					self::$license_type   = self::get_license_type( strval( $license_data->price_id ), $license_data );

					update_option( 'edd_sample_license_status', self::$license_status );
					update_option( 'edd_sample_license_expires', $license_data->expires );
					update_option( 'edd_sample_license_type', self::$license_type );

					if ( false === strpos( $http_referrer, 'bkap_license_notice' ) ) {
						return;
					}
				}

				if ( $did_update ) {
					// Remove bkap_license_notice from URL.
					$http_referrer = remove_query_arg( 'bkap_license_notice', $http_referrer );
				} else {

					// If we get here, then an error has occurred.
					// Append error variable to http_referer.
					if ( false === strpos( $http_referrer, 'bkap_license_notice' ) ) {
						$http_referrer .= '&bkap_license_notice=error'; // phpcs:ignore
					}
				}

				$redirect = wp_validate_redirect( add_query_arg( 'settings-updated', 'true', $http_referrer ) );
				wp_redirect( $redirect ); // phpcs:ignore
				exit;
			}
		}

		/**
		 * This function will deactivate the license.
		 *
		 * @since 5.12.0
		 */
		public static function deactivate_license() {

			if ( isset( $_POST['bkap_edd_license_deactivate'] ) ) { //phpcs:ignore

				if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) ) {
					return;
				}

				$license_data = self::fetch_license( 'deactivate_license' );

				// $license_data->license will be either "deactivated" or "failed".
				if ( isset( $license_data->license ) && 'deactivated' === $license_data->license ) {
					delete_option( 'edd_sample_license_status' );
					delete_option( 'edd_sample_license_expires' );
					delete_option( 'edd_sample_license_type' );
				}
			}
		}

		/**
		 * This checks if a license key is valid.
		 *
		 * @since 5.12.0
		 */
		public static function check_license() {

			$license_data = self::fetch_license();

			$data = 'invalid';
			if ( isset( $license_data->license ) && 'valid' === $license_data->license ) {
				$data = 'valid';
			}

			self::$license_status = $license_data->license;
			self::$license_type   = self::get_license_type( strval( $license_data->price_id ), $license_data );

			update_option( 'edd_sample_license_status', self::$license_status );
			update_option( 'edd_sample_license_expires', $license_data->expires );
			update_option( 'edd_sample_license_type', self::$license_type );
			return $data;
		}

		/**
		 * This checks that the license type option is not empty. If it is, then we go a quick license key fetch.
		 *
		 * @since 5.12.0
		 */
		public static function check_license_type() {

			$license_type = get_option( 'edd_sample_license_type', '' );

			if ( '' !== $license_type ) {
				return;
			}

			$license_data = self::fetch_license();

			if ( $license_data && isset( $license_data->license ) && '' !== $license_data->license && 'invalid' !== $license_data->license ) {
				self::$license_type = self::get_license_type( strval( $license_data->price_id ), $license_data );
				update_option( 'edd_sample_license_type', self::$license_type );
			}
		}

		/**
		 * This function checks if a new license has been entered, if yes, then plugin must be reactivated.
		 *
		 * @param string $license License Key.
		 *
		 * @since Updated 5.12.0
		 */
		public static function reactivate_if_new_license( $license ) {
				$old_license = get_option( 'edd_sample_license_key' );

			if ( '' !== $license && isset( $old_license ) && '' !== $old_license && $old_license !== $license ) {
				delete_option( 'edd_sample_license_status' ); // A new license has been entered, so we must reactivate.
			}

			return $license;
		}

		/**
		 * This function gets the license type from the Price ID..
		 *
		 * @param string $price_id Price ID of the license.
		 *
		 * @since Updated 5.12.0
		 */
		private static function get_license_type( $price_id, $license_data ) {

			$license_type = '';

			if ( isset( $license_data->expires ) ) {
				if ( 'lifetime' === $license_data->expires || ( strtotime( $license_data->expires ) <= strtotime( '2023-03-31' ) ) ) {
					return 'enterprise';
				}
			}

			switch ( $price_id ) {

				case '1':
					$license_type = 'business';
					break;

				case '2':
					$license_type = 'enterprise';
					break;

				case '0':
				case '3':
				default:
					$license_type = 'starter';
					break;
			}

			return $license_type;
		}

		/**
		 * Plan Error Message.
		 *
		 * @param string $expected_plan Expected Plan that is valid for the restriced resouce.
		 *
		 * @since 5.12.0
		 */
		public static function license_error_message( $expected_plan ) {
			self::check_license_type();
			return sprintf(
				/* translators: %1$s: Current Plan, %2$s: Expected Plan */
				__( self::$license_error_message, 'woocommerce-booking' ), //phpcs:ignore
				ucwords( self::$license_type ),
				ucwords( $expected_plan )
			);
		}

		/**
		 * Checks if License is for Starter Plan.
		 *
		 * @since 5.12.0
		 */
		public static function starter_license() {
			self::check_license_type();
			return 'starter' === self::$license_type;
		}

		/**
		 * Starter Plan Error Message.
		 *
		 * @since 5.12.0
		 */
		public static function starter_license_error_message() {
			return self::license_error_message( 'starter' );
		}

		/**
		 * Checks if License is for Business Plan.
		 *
		 * @since 5.12.0
		 */
		public static function business_license() {
			self::check_license_type();
			return 'enterprise' === self::$license_type || 'business' === self::$license_type;
		}

		/**
		 * Business Plan Error Message.
		 *
		 * @since 5.12.0
		 */
		public static function business_license_error_message() {
			return self::license_error_message( 'business' );
		}

		/**
		 * Checks if License is for Enterprise Plan.
		 *
		 * @since 5.12.0
		 */
		public static function enterprise_license() {
			return 'enterprise' === self::$license_type;
		}

		/**
		 * Enterprise Plan Error Message.
		 *
		 * @since 5.12.0
		 */
		public static function enterprise_license_error_message() {
			return self::license_error_message( 'enterprise' );
		}

		/**
		 * Displays an error notice on the Admin page.
		 *
		 * @param string $notice Error notice to be displayed.
		 *
		 * @since 5.12.0
		 */
		public static function display_error_notice( $notice ) {
			printf( "<div class='notice notice-error'><p>%s</p></div>", $notice ); // phpcs:ignore
		}

		/**
		 * Displays an error notice if any of the Vendor Plugins are activated with an un-supporting license.
		 *
		 * @since 5.12.0
		 */
		public static function vendor_plugin_license_error_notice() {

			global $current_screen;

			if ( 'page' !== $current_screen->post_type && 'post' !== $current_screen->post_type && 'update' !== $current_screen->base && ! self::business_license() ) {

				if ( class_exists( 'WeDevs_Dokan' ) ) {
					$notice = sprintf(
						/* translators: %1$s: Vendor Plugin, %2$s: Current License, %3$s: Expected License */
						__( self::$plugin_license_error_message, 'woocommerce-booking' ), //phpcs:ignore
						'Dokan Multivendor',
						ucwords( self::$license_type ),
						'Business or Enterprise'
					);

					self::display_error_notice( $notice );
				}

				if ( function_exists( 'is_wcvendors_active' ) && is_wcvendors_active() ) {
					$notice = sprintf(
						/* translators: %1$s: Vendor Plugin, %2$s: Current License, %3$s: Expected License */
						__( self::$plugin_license_error_message, 'woocommerce-booking' ), //phpcs:ignore
						'WC Vendors',
						ucwords( self::$license_type ),
						'Business or Enterprise'
					);

					self::display_error_notice( $notice );
				}

				if ( function_exists( 'is_wcfm_page' ) ) {
					$notice = sprintf(
						/* translators: %1$s: Vendor Plugin, %2$s: Current License, %3$s: Expected License */
						__( self::$plugin_license_error_message, 'woocommerce-booking' ), //phpcs:ignore
						'WCFM Marketplace',
						ucwords( self::$license_type ),
						'Business or Enterprise'
					);

					self::display_error_notice( $notice );
				}
			}
		}

		/**
		 * Removes WP Action Hooks that have been set in Add-on Plugins if License is not supported.
		 *
		 * @since 5.12.0
		 */
		public static function remove_wp_actions() {

			// Outlook Calendar Addon.
			if ( class_exists( 'Bkap_Outlook_Calendar' ) ) {

				$did_remove = self::remove_wp_action( 'bkap_global_integration_settings', 'bkap_outlook_calendar_options', 10 );

				if ( $did_remove ) {
					add_action( 'bkap_global_integration_settings', array( 'BKAP_License', 'show_enterprise_license_error_message' ), 10, 1 );
				}
			}
		}

		/**
		 * Displays the Enterprise License Error Message.
		 *
		 * @param string $screen Current Screen.
		 *
		 * @since 5.12.0
		 */
		public static function show_enterprise_license_error_message( $screen ) {
			if ( 'outlook_calendar' === $screen ) {
				?>
					<div class="bkap-plugin-error-notice-admin"><?php echo BKAP_License::enterprise_license_error_message(); // phpcs:ignore; ?></div>
				<?php
			}
		}

		/**
		 * Removes WP Action Hook.
		 *
		 * @param string $action WP Action to be removed.
		 * @param string $function PHP function assigned to the hook.
		 * @param int    $priority Priority of WP Action.
		 *
		 * @since 5.12.0
		 */
		public static function remove_wp_action( $action, $function, $priority ) {

			global $wp_filter;

			$did_remove = false;

			$callbacks = $wp_filter[ $action ]->callbacks[ $priority ];

			foreach ( $callbacks as $callback_key => $callback ) {
				if ( false !== strpos( $callback_key, $function ) ) {
					unset( $wp_filter[ $action ]->callbacks[ $priority ][ $callback_key ] );
					$did_remove = true;
				}
			}

			return $did_remove;
		}
	}
}

/**
 * Class Initilaization Function.
 *
 * @since 5.12.0
 */
function bkap_license() {
	return BKAP_License::init();
}

bkap_license();
