<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for Viewing and Cancelling Bookings on the Customer Account Page.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Cancel-Booking
 * @category    Classes
 * @since       5.9.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Bkap_Cancel_Booking' ) ) {

	/**
	 * Cancel Bookings from the Customer Account Page
	 *
	 * @since 5.9.1
	 */
	class Bkap_Cancel_Booking {

		/**
		 * Label for Booking endpoint.
		 *
		 * @var string
		 */
		protected static $endpoint = 'bookings';

		/**
		 * Construct
		 *
		 * @since 5.9.1
		 */
		public function __construct() {
			add_action( 'init', array( &$this, 'bkap_register_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( &$this, 'bkap_enqueue_scripts' ) );
			add_action( 'bkap_cancel_booking_actions', array( &$this, 'bkap_cancel_booking_action_cancel' ) ); // Action when the Cancel Button is clicked.
			add_action( 'init', array( &$this, 'bkap_add_booking_endpoint' ) ); // Register booking endpoint for Booking Page.
			add_filter( 'query_vars', array( &$this, 'bkap_add_booking_query_var' ), 0, 1 ); // Add booking to query_var.
			add_filter( 'woocommerce_account_menu_items', array( &$this, 'bkap_save_booking_endpoint' ), 10, 1 ); // Save booking endpoint.
			add_action( 'woocommerce_account_' . self::$endpoint . '_endpoint', array( &$this, 'bkap_endpoint_content' ) ); // Load endpoint template and render view.
			add_filter( 'the_title', array( &$this, 'bkap_endpoint_title' ) ); // Set endpoint title in the header.
		}

		/**
		 * Register Scripts.
		 *
		 * @since 5.9.1
		 *
		 * @hook init
		 */
		public function bkap_register_scripts() {
			wp_register_style( 'bkap-cancel-booking', bkap_load_scripts_class::bkap_asset_url( '/assets/css/bkap-cancel-booking.css', BKAP_FILE ), null, BKAP_VERSION );

			wp_register_script( 'bkap-cancel-booking-datatable', bkap_load_scripts_class::bkap_asset_url( '/assets/js/jquery.dataTables.min.js', BKAP_FILE ), null, BKAP_VERSION );

			wp_register_script( 'bkap-cancel-booking', bkap_load_scripts_class::bkap_asset_url( '/assets/js/bkap-cancel-booking.js', BKAP_FILE ), null, BKAP_VERSION );

			wp_localize_script(
				'bkap-cancel-booking',
				'bkap_cancel_booking_param',
				array(
					'confirm_msg'   => __( 'Are you sure you want to cancel this booking?', 'woocommerce-booking' ),
					'search_msg'    => __( 'Search in Bookings', 'woocommerce-booking' ),
					'next_text'     => __( 'Next', 'woocommerce-booking' ),
					'previous_text' => __( 'Previous', 'woocommerce-booking' ),
				)
			);
		}

		/**
		 * Enqueue Scripts.
		 *
		 * @since 5.9.1
		 *
		 * @hook wp_enqueue_scripts
		 */
		public function bkap_enqueue_scripts() {

			global $wp;

			// Enqueue scripts only on account page.
			if ( is_user_logged_in() && is_account_page() && ! is_wc_endpoint_url() ) {
				wp_enqueue_style( 'bkap-cancel-booking' );
				wp_enqueue_style( 'dashicons' ); // Use Dashicons (the default set used in the WP backend) in Frontned.
			}

			// Load jquery.dataTable scripts only on the Boookings Page contained in the My Account Page.
			$url_from_request = home_url( add_query_arg( array(), $wp->request ) );
			$endpoint_url     = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . self::$endpoint;

			if ( is_user_logged_in() && is_account_page() && ( $url_from_request === $endpoint_url ) ) {
				wp_enqueue_style( 'bkap-cancel-booking-datatable' );
				wp_enqueue_script( 'bkap-cancel-booking-datatable', array( 'jquery' ) );
				wp_enqueue_script( 'bkap-cancel-booking' );
			}
		}

		/**
		 * Add endpoint.
		 *
		 * @since 5.9.1
		 */
		public function bkap_add_booking_endpoint() {
			add_rewrite_endpoint( self::$endpoint, EP_ROOT | EP_PAGES );
			flush_rewrite_rules(); // Flush the rewrite rules so that the new endpoint can take effect.
		}

		/**
		 * Add endpoint to query_var.
		 *
		 * @param array $vars Query vars.
		 * @return array
		 * @since       5.9.1
		 */
		public function bkap_add_booking_query_var( $vars ) {
			$vars[] = self::$endpoint;
			return $vars;
		}

		/**
		 * Save endpoint.
		 *
		 * @param array $menu_links WooCommerce Account Menu Links.
		 * @return array
		 * @since       5.9.1
		 */
		public function bkap_save_booking_endpoint( $menu_links ) {
			// Add menu link for new endpoint after the second item: in this case, the Order menu link.
			$new_menu_link                    = array();
			$new_menu_link[ self::$endpoint ] = __( 'Bookings', 'woocommerce-booking' );

			$menu_links = array_slice( $menu_links, 0, 2, true ) + $new_menu_link + array_slice( $menu_links, 2, null, true );
			return $menu_links;
		}

		/**
		 * Set endpoint title.
		 *
		 * @param string $title WooCommerce Account Page Title.
		 * @return string
		 * @since        5.9.1
		 */
		public function bkap_endpoint_title( $title ) {

			global $wp;

			if ( ! is_admin() && is_main_query() && in_the_loop() && is_page() ) {

				if ( isset( $wp->query_vars[ self::$endpoint ] ) ) {
					$title = ucwords( self::$endpoint );
					remove_filter( 'the_title', array( &$this, 'bkap_endpoint_title' ) );
				}
			}

			return $title;
		}

		/**
		 * Get Bookings.
		 *
		 * This function fetches all bookings for the customer.
		 *
		 * @since 5.9.1
		 */
		public function fetch_bookings() {

			$bookings          = array();
			$return_data       = array();
			$customer_id       = get_current_user_id();
			$upcoming_bookings = array();
			$past_bookings     = array();

			$args = apply_filters(
				'bkap_cancel_booking_fetch_bookings_args',
				array(
					'author'         => $customer_id,
					'post_type'      => 'bkap_booking',
					'posts_per_page' => -1,
					'return'         => 'objects',
				),
				$customer_id
			);

			$query = new WP_Query( $args );

			// Proceed only when records are available.

			if ( $query->post_count > 0 ) {

				foreach ( $query->posts as $post ) {
					$booking  = new BKAP_Booking( $post );
					$order_id = $booking->get_order_id();

					// Check if the $_order object is valid and present in the system for the Booking ID. IF it is not, then do not show this Booking as there is no order to link it to or track.
					if ( ! wc_get_order( $order_id ) ) {
						continue;
					}

					$bookings[] = $booking;
				}

				// Sort Bookings into Upcoming category based on Booking Time.

				$current_date = date( 'YmdHis', current_time( 'timestamp' ) );

				$upcoming_bookings = array_filter(
					$bookings,
					function( $booking ) use ( $current_date ) {
						return $booking->get_start() >= $current_date;
					}
				);

				// Remove all Upcoming Bookings so that we are left with Past Bookings in the Bookings array.
				$past_bookings = array_diff(
					array_map(
						'serialize',
						$bookings
					),
					array_map(
						'serialize',
						$upcoming_bookings
					)
				);

				$past_bookings = array_map(
					'unserialize',
					$past_bookings
				);

				$return_data = array(
					'Upcoming Bookings' => $upcoming_bookings,
					'Past Bookings'     => $past_bookings,
				);
			}

			return apply_filters(
				'bkap_cancel_booking_fetch_bookings',
				$return_data,
				$bookings,
				$upcoming_bookings,
				$past_bookings
			);
		}

		/**
		 * Table Columns.
		 *
		 * This function returns the columns needed for the table header in the Bookings View.
		 *
		 * @since 5.9.1
		 */
		public static function bkap_get_account_endpoint_columns() {

			$columns = array(
				'id'             => __( 'ID', 'woocommerce-booking' ),
				'booked-product' => __( 'Booked Product', 'woocommerce-booking' ),
				'order-id'       => __( 'Order ID', 'woocommerce-booking' ),
				'start-date'     => __( 'Start Date', 'woocommerce-booking' ),
				'end-date'       => __( 'End Date', 'woocommerce-booking' ),
				'booking-status' => __( 'Booking Status', 'woocommerce-booking' ),
				'zoom-meeting'   => __( 'Zoom Meeting', 'woocommerce-booking' ),
				'booking-action' => __( 'Action', 'woocommerce-booking' ),
			);

			return apply_filters( 'bkap_account_endpoint_columns', $columns );
		}

		/**
		 * Endpoint template.
		 *
		 * This function renders the template needed for the new bookings endpoint.
		 *
		 * @since 5.9.1
		 */
		public function bkap_endpoint_content() {

			$bookings = $this->fetch_bookings();

			wc_get_template(
				'bookings/bkap-cancel-booking.php',
				array(
					'bookings'     => $bookings,
					'has_bookings' => 0 < count( $bookings ),
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}

		/**
		 * Cancel Booking URL.
		 *
		 * This function renders the url for Cancel Booking.
		 *
		 * @param int $booking_id Booking ID.
		 * @since 5.9.1
		 */
		public static function bkap_cancel_booking_url( $booking_id ) {
			return add_query_arg(
				array(
					'cancel_booking' => 'true',
					'booking_id'     => $booking_id,
				),
				wc_get_endpoint_url( self::$endpoint )
			);
		}

		/**
		 * Zoom Meeting Link for Booking.
		 *
		 * This function returns the Zoom Meeting Link url if one is available for the Booking.
		 *
		 * @param int $booking_id Booking ID.
		 * @since 5.9.1
		 */
		public static function bkap_get_zoom_meeting_link( $booking_id ) {

			$booking           = new BKAP_Booking( $booking_id );
			$zoom_meeting_link = $booking->get_zoom_meeting_link();

			if ( '' !== $zoom_meeting_link ) {
				$zoom_meeting_link = sprintf( '<a href="%s" target="_blank"><span class="dashicons dashicons-video-alt2"></span></a>', $zoom_meeting_link );
			}

			return $zoom_meeting_link;
		}

		/**
		 * Cancel Booking Actions.
		 *
		 * This function returns the buttons for Cancel Booking and determines if the Cancel Booking button should be shown for the Booking.
		 *
		 * @param int $booking_id Booking ID.
		 * @since 5.9.1
		 */
		public static function bkap_cancel_booking_action( $booking_id ) {

			$is_cancel_enabled_for_booking = false;

			$booking_cancel_url        = self::bkap_cancel_booking_url( $booking_id );
			$booking_cancel_url_button = '<a href="' . esc_url( $booking_cancel_url ) . '" class="woocommerce-button button bkap-cancel-booking-cancel-button">' . __( 'Cancel', 'woocommerce-booking' ) . '</a>';

			$booking = new BKAP_Booking( $booking_id );

			$current_date   = date( 'YmdHis', current_time( 'timestamp' ) );
			$is_booking_new = ( $booking->get_start() >= $current_date );

			$is_booking_paid_for = ( 'paid' === $booking->get_status() );

			// Check if booking is old or not paid for. They do not need a Cancel button.
			if ( $is_booking_new && $is_booking_paid_for ) {

				$product_id                                     = $booking->get_product_id();
				$booking_settings                               = bkap_get_post_meta( $product_id );
				$is_cancel_enabled_for_product_at_product_level = ( isset( $booking_settings['booking_can_be_cancelled'] ) && isset( $booking_settings['booking_can_be_cancelled']['status'] ) && 'on' === $booking_settings['booking_can_be_cancelled']['status'] );
				$is_cancel_enabled_for_product                  = $is_cancel_enabled_for_product_at_product_level;

				// Check if Cancel Booking has been set at global level only if product level check is false - since product level takes precedence over global level.

				$global_settings                   = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
				$is_cancel_enabled_at_global_level = ( isset( $global_settings->bkap_booking_minimum_hours_cancel ) && '' !== $global_settings->bkap_booking_minimum_hours_cancel );

				if ( ! $is_cancel_enabled_for_product ) {
					$is_cancel_enabled_for_product = $is_cancel_enabled_at_global_level;
				}

				// Filter to change precedence of Cancel Booking check and make global level have precedence over product.
				if ( apply_filters( 'bkap_cancel_booking_set_global_level_precedence', false, $booking_id, $booking ) ) {
					$is_cancel_enabled_for_product = $is_cancel_enabled_at_global_level;
				}

				// Filter to exclude products at global level from being able to have their bookings cancelled.
				$is_cancel_enabled_for_product = apply_filters( 'bkap_cancel_booking_exclude_product', $is_cancel_enabled_for_product, $product_id, $booking_id, $booking );

				$bkap_cancel_booking_time = 0;

				if ( $is_cancel_enabled_for_product ) {

					// Check if time and duration have been set at product level.
					$is_time_and_duration_set = ( isset( $booking_settings['booking_can_be_cancelled']['period'] ) && '' !== $booking_settings['booking_can_be_cancelled']['period'] && isset( $booking_settings['booking_can_be_cancelled']['duration'] ) && '' !== $booking_settings['booking_can_be_cancelled']['duration'] );

					if ( $is_time_and_duration_set ) {
						// Convert period and duration to strtotime format.
						$period   = $booking_settings['booking_can_be_cancelled']['period'];
						$duration = (int) $booking_settings['booking_can_be_cancelled']['duration'];

						if ( $duration > 0 ) {
							if ( 'day' === $period ) {
								$bkap_cancel_booking_time = $duration * 24 * 60 * 60;
							} elseif ( 'hour' === $period ) {
								$bkap_cancel_booking_time = $duration * 60 * 60;
							} elseif ( 'minute' === $period ) {
								$bkap_cancel_booking_time = $duration * 60;
							} else {
								$bkap_cancel_booking_time = 0;
							}
						}
					}

					// Calculate cancel booking time at global level.
					if ( $is_cancel_enabled_at_global_level ) {
						$bkap_cancel_booking_time_global = ( (int) $global_settings->bkap_booking_minimum_hours_cancel ) * 60 * 60;
					}

					if ( ! $is_time_and_duration_set || apply_filters( 'bkap_cancel_booking_set_global_level_precedence', false, $booking_id, $booking ) ) {

						// If time and duration have not been set at product level, then check at global level or use global level if filter to change precedence to global level has been activated.
						if ( $is_cancel_enabled_at_global_level ) {
							$is_time_and_duration_set = true;
							$bkap_cancel_booking_time = $bkap_cancel_booking_time_global;
						}
					}

					if ( ! $is_time_and_duration_set ) {

						// If $is_time_and_duration_set is not set at this point, then we assume that Cancelling Booking has been set to 'on' on the Product Page and no time/duration has been set.
						if ( $is_cancel_enabled_for_product_at_product_level ) {

							// Set a time/duration to 60 seconds before the booking date. We choose 60 seconds so that the time/duration is not so close to the Booking Time.
							$is_time_and_duration_set = true;
							$bkap_cancel_booking_time = 60;
						}
					}

					if ( $is_time_and_duration_set ) {

						$item_id = $booking->get_item_id();

						$booking_date = wc_get_order_item_meta( $item_id, '_wapbk_booking_date', true );
						$booking_date = explode( '-', $booking_date );
						$booking_date = $booking_date[2] . '-' . $booking_date[1] . '-' . $booking_date[0];

						$booking_time = wc_get_order_item_meta( $item_id, '_wapbk_time_slot', true );
						if ( '' !== $booking_time ) {
							$booking_time_explode = explode( ' - ', $booking_time );
							$booking_date        .= ' ' . $booking_time_explode[0];
						}

						$diff_from_booked_date = (int) ( (int) strtotime( $booking_date ) - current_time( 'timestamp' ) );
						if ( $diff_from_booked_date >= $bkap_cancel_booking_time ) {
							$is_cancel_enabled_for_booking = true;
						}
					}
				}
			}

			return $is_cancel_enabled_for_booking ? $booking_cancel_url_button : '';
		}

		/**
		 * Cancel Booking.
		 *
		 * This function will initiate the cancel booking process when the Cancel button has been clicked.
		 *
		 * @hook bkap_cancel_booking_actions
		 *
		 * @since 5.9.1
		 */
		public static function bkap_cancel_booking_action_cancel() {

			$cancel_booking_view_url = wc_get_endpoint_url( self::$endpoint );

			if ( isset( $_GET['booking_id'] ) && isset( $_GET['cancel_booking'] ) && 'true' === $_GET['cancel_booking'] ) { // phpcs:ignore

				$booking_id = sanitize_text_field( wp_unslash( $_GET['booking_id'] ) ); // phpcs:ignore
				$booking    = new BKAP_Booking( $booking_id );
				$item_id    = $booking->get_item_id();

				if ( isset( $booking_id ) && ( $booking_id > 0 ) ) {

					// Ensure that Booking ID for Booking is valid for Cancel Booking as user may manually fix in Booking ID in address bar.
					$is_cancel_valid_for_booking = ( '' !== self::bkap_cancel_booking_action( $booking_id ) );

					if ( $is_cancel_valid_for_booking ) {
						bkap_booking_confirmation::bkap_save_booking_status( $item_id, 'cancelled' );

						// Add note about Booking cancellation in the order.
						$order_id = $booking->get_order_id();
						$_order   = wc_get_order( $order_id );

						$current_user  = wp_get_current_user();
						$customer_name = $current_user->display_name;

						/* translators: %s: note order */
						$note = sprintf( esc_html__( '%1$s has cancelled Booking #%2$d', 'woocommerce-booking' ), esc_html( $customer_name ), esc_html( $booking_id ) );

						$_order->add_order_note( $note );

						/* Refund the Order Item upon Booking Cancellation */
						if ( apply_filters( 'bkap_process_refund_for_booking', false, $booking_id, /* $product_id, */$order_id ) ) {
							bkap_process_refund_for_booking( $order_id, $item_id );
						}

						wc_add_notice( __( 'Booking has been successsfully cancelled.', 'woocommerce-booking' ), 'success' );
					} else {
						wc_add_notice( __( 'This Booking cannot be cancelled.', 'woocommerce-booking' ), 'error' );
					}
					print( '<script type="text/javascript">location.href="' . esc_url( $cancel_booking_view_url ) . '";</script>' );
				}
			}
		}
	}
	$bkap_cancel_booking = new Bkap_Cancel_Booking();
}
