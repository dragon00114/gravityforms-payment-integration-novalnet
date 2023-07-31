<?php
/**
 * This file is used to process payment and handle the response
 * from the payment process and its asynchronous process
 *
 * @author   Novalnet AG
 * @package  novalnet-gravity-forms
 * @license  https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * GF_Novalnet
 */
class GF_Novalnet extends GFPaymentAddOn {

	/**
	 * Version number of the Add-On
	 *
	 * @var string
	 */
	protected $_version = GF_NOVALNET_VERSION;

	/**
	 * Gravity Forms minimum version requirement
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '2.3';

	/**
	 * URL-friendly identifier used for form settings, add-on settings, text domain localization...
	 *
	 * @var string
	 */
	protected $_slug = 'novalnet-gravity-forms';

	/**
	 * Relative path to the plugin from the plugins folder.
	 *
	 * @var string
	 */
	protected $_path = 'novalnet-gravity-forms/novalnet-gravity-forms.php';

	/**
	 * Full path of the plugin.
	 *
	 * @var string
	 */
	public $_full_path = __FILE__;

	/**
	 * URL to the Novalnet website.
	 *
	 * @var string
	 */
	protected $_url = 'https://www.novalnet.de';

	/**
	 * Title of the plugin to be used on the settings page, form settings and plugins page.
	 *
	 * @var string
	 */
	protected $_title = 'Gravity Forms Novalnet Add-on';

	/**
	 * Whether Add-on framework has settings renderer support or not, settings renderer was introduced in Gravity Forms 2.5
	 *
	 * @var bool
	 */
	protected $has_settings_renderer;

	/**
	 * Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
	 *
	 * @var string
	 */
	protected $_short_title = 'Novalnet';

	/**
	 * Settings inputs prefix string.
	 *
	 * @var string
	 */
	protected $input_field_prefix = '';

	/**
	 * Settings inputs container prefix string.
	 *
	 * @var string
	 */
	protected $field_container_prefix = '';

	/**
	 * The add-on supports callbacks.
	 *
	 * @var bool
	 */
	public $_supports_callbacks = true;

	/**
	 * The single instance of the class.
	 *
	 * @var GF_Novalnet
	 */
	private static $_instance = null;

	/**
	 * The request data.
	 *
	 * @var array
	 */
	public $request = array();

	/**
	 * Get an instance of this class.
	 *
	 * @return GF_Novalnet
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Runs before the payment add-on is initialized.
	 *
	 * @return void
	 */
	public function pre_init() {
		parent::pre_init();
		add_action( 'wp', array( $this, 'maybe_thankyou_page' ), 5 );
		add_action( 'parse_request', array( $this, 'maybe_process_callback' ) ); // Webhook and Return handler hook.
	}

	/**
	 * Admin initial actions.
	 */
	public function init_admin() {
		include_once 'includes/class-gf-novalnet-helper.php';
		parent::init_admin();
	}

	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();

		$this->request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification

		include_once 'includes/class-gf-novalnet-setup.php';
		include_once 'includes/class-gf-novalnet-helper.php';

		$this->load_text_domain();

		add_action( 'admin_init', array( 'GF_Novalnet_Setup', 'install' ) );

		register_deactivation_hook( GF_NOVALNET_FILE, array( 'GF_Novalnet_Setup', 'uninstall' ) );

		add_filter( 'gform_form_tag', array( $this, 'show_error_message' ), 10 );

		add_filter( 'wp_ajax_get_novalnet_vendor_details', array( 'GF_novalnet_Configuration', 'send_auto_config_call' ) );

		add_filter( 'wp_ajax_config_novalnet_hook_url', array( 'GF_novalnet_Configuration', 'send_webhook_url_config_call' ) );

		add_filter( 'gform_replace_merge_tags', array( $this, 'add_transaction_info' ), 10, 7 );

		add_filter( 'gform_merge_tag_data', array( $this, 'check_novalnet_transaction_note' ), 10, 4 );

		add_filter( 'gform_disable_notification', array( $this, 'delay_instant_notification' ), 10, 5 );

		add_filter( 'gform_custom_merge_tags', array( $this, 'add_transaction_notes_short_code' ) );

		add_filter( 'novalnet_store_instalment_data', array( $this, 'store_instalment_data' ), 10, 2 );

		add_filter( 'novalnet_store_instalment_data_webhook', array( $this, 'store_instalment_data_webhook' ), 10, 1 );

		add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'add_instalment_metabox' ), 10, 3 );

		add_action( 'gform_payment_details', array( $this, 'show_payment_details' ), 10, 2 );

		add_action( 'gform_payment_statuses', array( $this, 'add_nn_custom_payment_status' ), 10, 1 );

		add_filter( 'gform_register_init_scripts', array( $this, 'enqueue_novalnet_checkout_script' ), 10, 3 );

		// Set UI prefixes depending on settings renderer availability.
		$this->has_settings_renderer  = $this->is_gravityforms_supported( '2.5-beta' );
		$this->input_field_prefix     = $this->has_settings_renderer ? '_gform_setting' : '_gaddon_setting';
		$this->field_container_prefix = $this->has_settings_renderer ? 'gform_setting_' : 'gaddon-setting-row-';

	}


	/**
	 * Called when the user chooses to uninstall the Add-On after
	 * permissions have been checked and before removing all
	 * Add-On settings and Form settings.
	 */
	public function uninstall() {
		parent::uninstall();
		return GF_Novalnet_Setup::uninstall();
	}

	/**
	 * Return the scripts which should be enqueued in admin.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'gf_novalnet_admin',
				'src'     => plugins_url( null, gf_novalnet()->_full_path ) . '/js/novalnet-admin.js',
				'version' => GF_NOVALNET_VERSION,
				'deps'    => array( 'jquery' ),
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
					),
				),
				'strings' => array(
					'merchant_details_nonce' => wp_create_nonce( 'nn_get_novalnet_vendor_details' ),
					'notification_url_nonce' => wp_create_nonce( 'nn_config_novalnet_hook_url' ),
				),
			),
		);
		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Process to handle Novalnet webhook and payment response.
	 */
	public function maybe_process_callback() {
		if ( ( 'gf_novalnet_response' === (string) rgget( 'nn_action' ) ) && ! empty( rgget( 'gf_novalnet_return' ) ) ) { // Real time response.
			$response_action = $this->authenticate_response();
			if ( true === $response_action['is_valid_nncall'] ) { // Valid response.
				$settings = $this->get_plugin_settings();
				$response = GF_Novalnet_Helper::get_transaction_result( $this->request, $settings['novalnet_payment_access_key'] );
				if ( GF_Novalnet_Helper::is_success_status( $response ) ) { // Transaction success.
					$payment_name       = GF_Novalnet_Helper::get_payment_name( $response['transaction']['payment_type'] );
					$this->_short_title = $payment_name['payment_name'];
					if ( in_array( $response['transaction']['payment_type'], array( 'INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA' ), true ) && rgar( $response['instalment'], 'cycle_amount' ) ) { // For instalment payments.
						$response_action['amount'] = GF_Novalnet_Helper::get_formatted_amount( rgar( $response['instalment'], 'cycle_amount' ) );
					} else {
						$response_action['amount'] = GF_Novalnet_Helper::get_formatted_amount( rgar( $response['transaction'], 'amount' ) );
					}
					$response_action['transaction_id'] = rgar( $response['transaction'], 'tid' );
					$response_action['payment_method'] = $payment_name['payment_name'];
					$this->update_callback_action_data( $response, $response_action );
					$this->transaction_post_process( $response_action['entry_id'], $response );
					$transaction_details = GF_Novalnet_Helper::form_transaction_details( $response );
					gform_update_meta( $response_action['entry_id'], '_novalnet_transaction_comments', $transaction_details );
				} else { // Transaction failure.
					$response_action['status_text'] = GF_Novalnet_Helper::get_status_description( $response );
					$response_action['note']        = GF_Novalnet_Helper::form_transaction_data( $response, true );
					$response_action['type']        = 'fail_payment';
					$response_action['callback']    = 'fail_payment';
				}
			}
			$this->process_response_action( $response_action );
		} elseif ( 'gf_novalnet_webhook' === rgget( 'nn_action' ) ) { // Asynchronous response.
			include_once 'includes/class-gf-novalnet-webhook.php';
			$webhook = new GF_Novalnet_Webhook();
			$webhook->process();
		}
	}

	/**
	 * Update the entry base response action data
	 *
	 * @param array $action The action to perform.
	 */
	public function process_response_action( $action ) {
		$action = wp_parse_args(
			$action,
			array(
				'type'             => false,
				'amount'           => false,
				'amount_formatted' => false,
				'transaction_type' => false,
				'transaction_id'   => false,
				'subscription_id'  => false,
				'entry_id'         => false,
				'payment_status'   => false,
				'note'             => false,
			)
		);
		$result = false;
		$entry  = GFAPI::get_entry( $action['entry_id'] );
		if ( ! $entry || is_wp_error( $entry ) ) {
			$this->novalnet_redirect( 'Entry not found' );
		}

		$is_duplicate_response = gform_get_meta( $entry['id'], 'is_nn_response_processed', true );
		if ( ! empty( $is_duplicate_response ) && 'yes' === $is_duplicate_response ) {
			return true;
		}
		gform_update_meta( $entry['id'], 'is_nn_response_processed', 'yes' );

		$action = $this->maybe_add_action_amount_formatted( $action, $entry['currency'] );
		if ( ! in_array( rgar( $action, 'callback' ), array( 'complete_payment', 'complete_authorization' ), true ) && is_callable( array( $this, rgar( $action, 'callback' ) ) ) ) {
			$result = call_user_func_array( array( $this, $action['callback'] ), array( $entry, $action ) );
		} elseif ( 'complete_payment' === rgar( $action, 'callback' ) ) {
			$this->complete_payment( $entry, $action );
		} elseif ( 'complete_authorization' === rgar( $action, 'callback' ) ) {
			$this->complete_authorization( $entry, $action );
		}

		if ( ! empty( $action['form_id'] ) ) {
			$form = GFAPI::get_form( $action['form_id'] );
			$this->gf_novalnet_send_notifications( $form, $entry );
		}

		if ( 'fail_payment' === (string) $action['type'] ) {
			$note = ( isset( $action['is_txn_failure'] ) && true === $action['is_txn_failure'] ) ? $action['note'] : $action['status_text'];
			$this->novalnet_redirect( $note );
		}
	}

	/**
	 * Authenticate the callback response.
	 */
	public function authenticate_response() {
		$return_data = base64_decode( rgget( 'gf_novalnet_return' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		parse_str( $return_data, $data );
		list( $form_id, $entry_id ) = explode( '|', $data['entry_id'] );
		// Process the request if the return entry id and form are correct.
		if ( wp_hash( 'entry_id=' . $data['entry_id'] ) === $data['hash'] ) {
			$response_action = array(
				'entry_id'        => $entry_id,
				'form_id'         => $form_id,
				'is_valid_nncall' => false,
			);
			// Checksum validation.
			if ( ! empty( rgget( 'checksum' ) ) && ! empty( rgget( 'tid' ) ) && ! empty( rgget( 'txn_secret' ) )
			&& ! empty( rgget( 'status' ) ) ) {
				$settings           = $this->get_plugin_settings();
				$token_string       = rgget( 'tid' ) . rgget( 'txn_secret' ) . rgget( 'status' ) . strrev( $settings['novalnet_payment_access_key'] );
				$generated_checksum = hash( 'sha256', $token_string );
				if ( rgget( 'checksum' ) !== $generated_checksum ) { // Checksum failure.
					$response_action['is_txn_failure'] = true;
					$response_action['note']           = __( 'While redirecting some data has been changed. The hash check failed', 'novalnet-gravity-forms' );
					$response_action['type']           = 'fail_payment';
					$response_action['callback']       = 'fail_payment';
					$response_action['transaction_id'] = rgget( 'tid' );
				} else { // Checksum success.
					$response_action['is_valid_nncall'] = true;
					if ( 'FAILURE' === rgget( 'status' ) ) {
						$response_action['is_valid_nncall'] = false;
						$response_action['is_txn_failure']  = true;
						$response_action['note']            = GF_Novalnet_Helper::get_status_description( $this->request );
						$response_action['type']            = 'fail_payment';
						$response_action['callback']        = 'fail_payment';
					}
				}
			} else { // Invalid response.
				$response_action['is_txn_failure'] = true;
				$response_action['note']           = GF_Novalnet_Helper::get_status_description( $this->request );
				$response_action['type']           = 'fail_payment';
				$response_action['callback']       = 'fail_payment';
			}
			return $response_action;
		} else {
			return new WP_Error( 'invalid_request', sprintf( __( 'The hash check failed', 'novalnet-gravity-forms' ) ) );
		}
	}


	/**
	 * Handles the after callback process.
	 *
	 * @param array $callback_action The performed action.
	 * @param array $callback_result The result of the action.
	 *
	 * @return bool
	 */
	public function post_callback( $callback_action, $callback_result ) {
		if ( is_wp_error( $callback_action ) || ! $callback_action ) {
			return false;
		}
		return true;
	}

	/**
	 * Display the thank you page.
	 */
	public function maybe_thankyou_page() {
		if ( rgget( 'gf_novalnet_return' ) && 'SUCCESS' === rgget( 'status' ) ) {

			$str = base64_decode( rgget( 'gf_novalnet_return' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			parse_str( $str, $query );
			if ( wp_hash( 'entry_id=' . $query['entry_id'] ) === $query['hash'] ) {
				list( $form_id, $entry_id ) = explode( '|', $query['entry_id'] );
				$form                       = GFAPI::get_form( $form_id );
				$lead                       = GFAPI::get_entry( $entry_id );

				if ( ! class_exists( 'GFFormDisplay' ) ) {
					require_once GFCommon::get_base_path() . '/form_display.php';
				}

				$confirmation = GFFormDisplay::handle_confirmation( $form, $lead, false );

				if ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
					header( "Location: {$confirmation['redirect']}" );
					exit;
				}

				GFFormDisplay::$submission[ $form_id ] = array(
					'is_confirmation'      => true,
					'confirmation_message' => $confirmation,
					'form'                 => $form,
					'lead'                 => $lead,
				);
			}
		}
	}

	/**
	 * Update the response details in callback action data.
	 *
	 * @param array $response        The received transaction response.
	 * @param array $response_action The current response_action data.
	 * @param bool  $is_subscription Flag to check the transaction is subscription.
	 * @param bool  $is_renewal      Flag to check the transaction is renewal.
	 * */
	public function update_callback_action_data( $response, &$response_action, $is_subscription = false, $is_renewal = false ) {
		if ( ! empty( $response['subscription'] ) && ! $is_renewal ) { // If subscription transaction.
			$entry           = GFAPI::get_entry( $response_action['entry_id'] );
			$is_subscription = true;
			// Start new subscription.
			$subscription = array(
				'subscription_id' => $response['subscription']['tid'],
				'amount'          => GF_Novalnet_Helper::get_formatted_amount( $response['subscription']['amount'] ),
			);
			$this->start_subscription( $entry, $subscription );
			$response_action['subscription_id'] = $response['subscription']['tid']; // Parent transaction id.
		}
		if ( 'CONFIRMED' === $response['transaction']['status'] ) { // For confirmed transaction.
			$response_action['type']     = ( $is_subscription ) ? 'add_subscription_payment' : 'complete_payment';
			$response_action['callback'] = ( $is_subscription ) ? 'add_subscription_payment' : 'complete_payment';
		} elseif ( 'PENDING' === $response['transaction']['status'] ) { // For pending transaction.
			if ( 'INVOICE' === (string) $response['transaction']['payment_type'] ) { // For Invoice payment only.
				$response_action['type']     = ( $is_subscription ) ? 'add_subscription_payment' : 'complete_payment';
				$response_action['callback'] = ( $is_subscription ) ? 'add_subscription_payment' : 'complete_payment';
			} else { // For other Invoice payment which has a PENDING status.
				$response_action['type']     = 'add_pending_payment';
				$response_action['callback'] = 'add_pending_payment';
			}
		} elseif ( 'ON_HOLD' === $response['transaction']['status'] ) { // For On-hold transaction.
			if ( $is_subscription ) { // For subscription.
				$response_action['type']     = 'add_pending_payment';
				$response_action['callback'] = 'add_pending_payment';
			} else { // For one-time.
				$response_action['type']     = 'complete_authorization';
				$response_action['callback'] = 'complete_authorization';
			}
		} else { // For failure transaction.
			$response_action['type']     = ( $is_subscription ) ? 'fail_subscription_payment' : 'fail_payment';
			$response_action['callback'] = ( $is_subscription ) ? 'fail_subscription_payment' : 'fail_payment';
		}
	}

	/**
	 * Handles the redirect inside the shop
	 *
	 * @param string $note Entry note.
	 */
	public function novalnet_redirect( $note ) {
		$redirect_url = explode( '?', GF_Novalnet_Helper::return_url( true ) );
		$redirect_url = $redirect_url['0'];
		$redirect_url = add_query_arg( 'gf_novalnet_error', rawurlencode( $note ), $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit();
	}

	/**
	 * Handles the transaction post process.
	 *
	 * @param array   $entry_id        Current entry_id.
	 * @param array   $server_response Payment response.
	 * @param boolean $is_renewal      Flag to identify the renewal transaction.
	 */
	public function transaction_post_process( $entry_id, $server_response, $is_renewal = false ) {
		$payment_feed = $this->get_payment_feed( array( 'id' => $entry_id ) );
		GF_Novalnet_Helper::insert_transaction_details( $entry_id, $server_response, $payment_feed, $is_renewal );
	}

	/**
	 * Send disabled notification
	 *
	 * @param array $form Form array that contains notification details.
	 * @param array $entry Entry array that contains customer details.
	 */
	public function gf_novalnet_send_notifications( $form, $entry ) {
		$notifications_to_send = array();
		if ( ! empty( $form ['notifications'] ) ) {
			foreach ( $form ['notifications'] as $notification ) {
				if ( rgar( $notification, 'event' ) === 'form_submission' ) {
					$notifications_to_send [] = $notification['id'];
				}
			}
			if ( ! empty( $notifications_to_send ) ) {
				GFCommon::send_notifications( $notifications_to_send, $form, $entry, true, 'form_submission' );
			}
		}
	}

	/**
	 * Disable the instant notification since the payment not success yet.
	 *
	 * @param bool  $is_disabled  Active payment feed containing all the configuration data.
	 * @param array $notification The current notification object.
	 * @param array $form         The Form Object that triggered the notification event.
	 * @param array $entry        The Entry Object that triggered the notification event.
	 * @param array $data         Array of data which can be used in the notifications via the generic {object:property} merge tag. Defaults to empty array.
	 */
	public function delay_instant_notification( $is_disabled, $notification, $form, $entry, $data ) {
		if ( rgar( $notification, 'event' ) === 'form_submission' && gform_get_meta( $entry['id'], 'payment_gateway' ) == $this->_slug ) { // phpcs:ignore WordPress.PHP.StrictComparisons
			return true;
		}
		return $is_disabled;
	}

	/**
	 * Handles the formation of payment parameters and the URL.
	 *
	 * @param array $feed            Active payment feed containing all the configuration data.
	 * @param array $submission_data Contains form field data submitted by the user as well as payment information (i.e. payment amount, setup fee, line items, etc...).
	 * @param array $form            Current form array containing all form settings.
	 * @param array $entry           Current entry array containing entry information (i.e data submitted by users).
	 *
	 * @return string
	 */
	public function redirect_url( $feed, $submission_data, $form, $entry ) {
		$parameters    = array();
		$settings      = $this->get_plugin_settings();
		$customer_data = GF_Novalnet_Helper::get_customer_data( $feed, $entry, $form );
		$parameters    = array();
		GF_Novalnet_Helper::form_merchant_parameters( $settings, $parameters );
		GF_Novalnet_Helper::form_cust_parameters( $customer_data, $entry, $parameters );
		GF_Novalnet_Helper::form_transaction_parameters( $entry, $form, $submission_data, $parameters, $settings );
		if ( ! empty( $settings['novalnet_instalment_cycles_plan'] ) ) {
			GF_Novalnet_Helper::form_instalment_parameters( $settings['novalnet_instalment_cycles_plan'], $parameters );
		}
		GF_Novalnet_Helper::form_custom_parameters( $parameters );
		if ( 'subscription' === $feed['meta']['transactionType'] ) { // Create subscription parameters after hosted page parameters.
			GF_Novalnet_Helper::form_subscription_parameters( $feed, $settings, $submission_data, $parameters );
		}

		GF_Novalnet_Helper::form_hosted_page_parameters( $parameters, $feed, $settings );

		$endpoint_action = ( rgar( $settings, 'novalnet_transaction_type' ) === 'authorize' && rgar( $parameters['transaction'], 'amount' ) > rgar( $settings, 'on_hold_limit' ) ) ? 'seamless/authorize' : 'seamless/payment';

		// updating lead's payment_status to Processing.
		GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Processing' );

		$response = GF_Novalnet_Helper::perform_http_request( $parameters, $endpoint_action, $settings['novalnet_payment_access_key'] );

		if ( ! empty( $response['result']['status'] ) && 'SUCCESS' === $response['result']['status'] && ! empty( $response['transaction']['txn_secret'] ) ) { // Checking payment URL call is success or not.
			$redirect_url = GF_Novalnet_Helper::internal_redirect_url( $entry['form_id'], $entry['id'], $response['transaction']['txn_secret'] );
			gform_update_meta( $entry['id'], '_render_novalnet_overlay', $response['transaction']['txn_secret'] );
			return $redirect_url;
		} else {
			$action = array(
				'note' => GF_Novalnet_Helper::get_status_description( $response ),
			);
			$this->fail_payment( $entry, $action );
			$this->novalnet_redirect( $action['note'] );
		}
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		return file_get_contents( $this->get_base_path() . '/images/menu-icon.svg' ); // phpcs:ignore
	}

	/**
	 * Add subscription transaction details in entry subscription.
	 *
	 * @param array $entry Current entry of customer.
	 * @param array $response Payment response.
	 */
	public function add_subscription_data( $entry, $response ) {
		$subscription = array(
			'amount'          => GF_Novalnet_Helper::get_formatted_amount( $response['transaction']['amount'] ),
			'subscription_id' => $response['subscription']['tid'],
		);
		return $this->start_subscription( $entry, $subscription );
	}

	/**
	 * Cancle subscription using backend.
	 *
	 * @param array $entry The entry to cancel subscription.
	 * @param array $feed  Feed of the entry.
	 */
	public function cancel( $entry, $feed ) {
		if ( rgar( $entry, 'transaction_id' ) ) {
			$transaction_details = GF_Novalnet_Helper::get_transaction_details( rgar( $entry, 'transaction_id' ) );
			if ( ! empty( $transaction_details['tid'] ) ) {
				// Form common parameter tid and lang.
				$parameters['subscription']['tid']    = $transaction_details['tid'];
				$parameters['custom']['lang']         = strtoupper( GF_Novalnet_Helper::get_language() );
				$parameters['custom']['shop_invoked'] = 1;
				$settings                             = $this->get_plugin_settings();

				$response = GF_Novalnet_Helper::perform_http_request( $parameters, 'subscription/cancel', $settings['novalnet_payment_access_key'] );

				if ( GF_Novalnet_Helper::is_success_status( $response ) ) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
	}

	/**
	 * Build Plugins settings field.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return GF_Novalnet_Configuration::plugin_settings_fields();
	}

	/**
	 * Define the markup for the trial_period type field.
	 *
	 * @access public
	 *
	 * @uses GFAddOn::settings_text()
	 * @uses GFAddOn::field_failed_validation()
	 * @uses GFAddOn::get_error_icon()
	 *
	 * @param array     $field The field properties.
	 * @param bool|true $echo  Should the setting markup be echoed. Defaults to true.
	 *
	 * @return string|void The HTML markup if $echo is set to false. Void otherwise.
	 */
	public function settings_trial_period( $field, $echo = true ) {
		// Get text input markup.
		$html = $this->settings_text( $field, false );

		// Prepare validation placeholder name.
		$validation_placeholder = array( 'name' => 'trialValidationPlaceholder' );

		// Add validation indicator.
		if ( $this->field_failed_validation( $validation_placeholder ) ) {
			$html .= '&nbsp;' . $this->get_error_icon( $validation_placeholder );
		}

		// If trial is not enabled and setup fee is enabled, hide field.
		$html .= '
			<script type="text/javascript">
			if( ! jQuery( "#trial_enabled" ).is( ":checked" ) ) {
				jQuery( "#trial_enabled" ).prop( "checked", false );
				jQuery( "#' . $this->field_container_prefix . 'trial_period" ).hide();
			}
			</script>';

		// Echo setting markup, if enabled.
		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		return $html;

	}



	/**
	 * Define the markup for the trial type field.
	 *
	 * @param array     $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed. Defaults to true.
	 *
	 * @return string|void The HTML markup if $echo is set to false. Void otherwise.
	 */
	public function settings_trial( $field, $echo = true ) {
		// Prepare enabled field settings.
		$enabled_field = array(
			'name'       => $field['name'] . '_checkbox',
			'type'       => 'checkbox',
			'horizontal' => true,
			'choices'    => array(
				array(
					'label'    => esc_html__( 'Enabled', 'novalnet-gravity-forms' ),
					'name'     => $field['name'] . '_enabled',
					'value'    => '1',
					'onchange' => "if(jQuery(this).prop('checked')){
						jQuery('#{$this->field_container_prefix}trial_period').show();
					} else {
						jQuery('#{$this->field_container_prefix}trial_period').hide();
						jQuery('#trial_period').val( '' );
					}",
				),
			),
		);

		// Get checkbox markup.
		$html = $this->settings_checkbox( $enabled_field, false );

		// Echo setting markup, if enabled.
		if ( $echo ) {
			echo $html; // phpcs:ignore
		}

		return $html;
	}

	/**
	 * Define the choices available in the billing cycle dropdowns.
	 *
	 * @return array billing_cycles intervals that are supported.
	 */
	public function supported_billing_intervals() {
		$billing_cycles = array(
			'day'   => array(
				'label' => esc_html__( 'day(s)', 'gravityforms' ),
				'min'   => 1,
				'max'   => 365,
			),
			'week'  => array(
				'label' => esc_html__( 'week(s)', 'gravityforms' ),
				'min'   => 1,
				'max'   => 52,
			),
			'month' => array(
				'label' => esc_html__( 'month(s)', 'gravityforms' ),
				'min'   => 1,
				'max'   => 12,
			),
			'year'  => array(
				'label' => esc_html__( 'year(s)', 'gravityforms' ),
				'min'   => 1,
				'max'   => 5,
			),
		);
		return $billing_cycles;
	}


	/**
	 * Configures the settings which should be rendered on the Form.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		$default_settings = parent::feed_settings_fields();
		// --get billing info section and add customer first/last name.
		$billing_info   = parent::get_field( 'billingInformation', $default_settings );
		$billing_fields = $billing_info['field_map'];
		$add_first_name = true;
		$add_last_name  = true;
		foreach ( $billing_fields as $mapping ) {
			// add first/last name if it does not already exist in billing fields.
			if ( 'firstName' === $mapping['name'] ) {
				$add_first_name = false;
			} elseif ( 'lastName' === $mapping['name'] ) {
				$add_last_name = false;
			}
		}

		if ( $add_last_name ) {
			// add last name.
			array_unshift(
				$billing_info['field_map'],
				array(
					'name'     => 'lastName',
					'label'    => esc_html__( 'Last Name', 'novalnet-gravity-forms' ),
					'required' => false,
				)
			);
		}
		if ( $add_first_name ) {
			array_unshift(
				$billing_info['field_map'],
				array(
					'name'     => 'firstName',
					'label'    => esc_html__( 'First Name', 'novalnet-gravity-forms' ),
					'required' => false,
				)
			);
		}

		array_unshift(
			$billing_info['field_map'],
			array(
				'name'     => 'birth_date',
				'label'    => esc_html__( 'Birth Date', 'novalnet-gravity-forms' ),
				'required' => false,
			)
		);

		array_unshift(
			$billing_info['field_map'],
			array(
				'name'     => 'company',
				'label'    => esc_html__( 'Company Name', 'novalnet-gravity-forms' ),
				'required' => false,
			)
		);

		$default_settings = parent::replace_field( 'billingInformation', $billing_info, $default_settings );

		// Set trial field to be visibile by default, setup fee and trial period can coexist in stripe.
		$trial_field      = array(
			'name'    => 'trial',
			'label'   => esc_html__( 'Trial', 'novalnet-gravity-forms' ),
			'type'    => 'trial',
			'hidden'  => false,
			'tooltip' => '<h6>' . esc_html__( 'Trial Period', 'gravityforms' ) . '</h6>' . esc_html__( 'Enable a trial period.  The user\'s recurring payment will not begin until after this trial period.', 'novalnet-gravity-forms' ),
		);
		$default_settings = $this->replace_field( 'trial', $trial_field, $default_settings );

		// Prepare trial period field.
		$trial_period_field = array(
			'name'                => 'trial_period',
			'label'               => esc_html__( 'Trial Period', 'novalnet-gravity-forms' ),
			'type'                => 'trial_period',
			'validation_callback' => array( $this, 'validate_trial_period' ),
		);

		if ( $this->has_settings_renderer ) {
			$trial_period_field['append'] = esc_html__( 'days', 'novalnet-gravity-forms' );
		} else {
			$trial_period_field['style']       = 'width:40px;text-align:center;';
			$trial_period_field['after_input'] = '&nbsp;' . esc_html__( 'days', 'novalnet-gravity-forms' );
		}
		// Add trial period field.
		$default_settings = $this->add_field_after( 'trial', $trial_period_field, $default_settings );

		return apply_filters( 'novalnet_feed_settings_fields', $default_settings, $this->get_current_form() );
	}

	/**
	 * Validate the trial_period type field.
	 *
	 * @access public
	 *
	 * @uses GFAddOn::get_posted_settings()
	 * @uses GFAddOn::set_field_error()
	 *
	 * @param array $field The field properties. Not used.
	 *
	 * @return void
	 */
	public function validate_trial_period( $field ) {

		// Get posted settings.
		$settings = $this->get_posted_settings();

		// If trial period is not numeric, set field error.
		if ( $settings['trial_enabled'] && ( empty( $settings['trial_period'] ) || ! ctype_digit( $settings['trial_period'] ) || ( 365 < $settings['trial_period'] ) ) ) {
			$this->set_field_error( $field, esc_html__( 'Please enter a valid number of days.', 'novalnet-gravity-forms' ) );
		}

	}


	/**
	 * Add Novalnet transaction info in mail.
	 *
	 * @param array $tags The existing tags.
	 *
	 * @return array
	 */
	public function add_transaction_notes_short_code( $tags ) {
		$tags [] = array(
			'tag'   => '{payment_action:novalnet_transaction_note}',
			'label' => esc_html__( 'Novalnet Payment Details', 'novalnet-gravity-forms' ),
		);
		return $tags;
	}

	/**
	 * Replace transaction note merge tage
	 *
	 * @param array  $data  Array of key/value pairs, where key is used as merge tag and value is an array of data available to the merge tag.
	 * @param string $text  String of text which will be searched for merge tags.
	 * @param array  $form  Current form object.
	 * @param array  $lead  The current Entry Object.
	 */
	public function check_novalnet_transaction_note( $data, $text, $form, $lead ) {
		if ( (string) gform_get_meta( $data['entry']['id'], 'payment_gateway' ) === $this->_slug && ! empty( $data['payment_action'] ) ) {
			$transaction_details = gform_get_meta( $data['entry']['id'], '_novalnet_transaction_comments', true );
			if ( empty( $data['payment_action']['novalnet_transaction_note'] ) ) {
				$data['payment_action']['novalnet_transaction_note'] = GFCommon::format_variable_value( $transaction_details, false, true, 'html' );
			}
		}
		return $data;
	}

	/**
	 * Add Novalnet transaction info in mail.
	 *
	 * @param string $text       The existing mail content.
	 * @param array  $form       Current form array containing all form settings.
	 * @param array  $entry      Current entry array containing entry information (i.e data submitted by users).
	 * @param string $url_encode Need to process url encoding.
	 * @param string $esc_html   Need to escape the html.
	 * @param string $nl2br      Need to convert new line to break.
	 * @param string $format     The mail format.
	 *
	 * @return string
	 */
	public function add_transaction_info( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		if ( empty( $form ) || empty( $entry ) ) {
			return $text;
		}
		$transaction_details = gform_get_meta( $entry['id'], '_novalnet_transaction_comments', true );
		if ( $transaction_details ) {
			if ( 'html' === $format && ! $esc_html ) {
				$transaction_details = GFCommon::format_variable_value( $transaction_details, $url_encode, $esc_html, $format );
			}
			$text = str_replace( '{payment_action:novalnet_transaction_note}', $transaction_details, $text );
		} else {
			$text = str_replace( '{payment_action:novalnet_transaction_note}', '', $text );
		}
		return $text;
	}

	/**
	 * Add custom payment statuses.
	 *
	 * @param array $payment_statuses An array of entry payment statuses with the entry value as the key (15 char max) to the text for display.
	 */
	public function add_nn_custom_payment_status( $payment_statuses ) {
		$custom_status = array(
			'Paused' => esc_html__( 'Paused', 'gravityforms' ),
		);
		return array_merge( $payment_statuses, $custom_status );
	}

	/**
	 * Shows the payment details in Admin meta.
	 *
	 * @param int   $form_id The form ID value.
	 * @param array $entry   Current entry array containing entry information (i.e data submitted by users).
	 */
	public function show_payment_details( $form_id, $entry ) {
		$allowedhtml_cycles  = array(
			'b'  => true,
			'br' => true,
		);
		$transaction_details = gform_get_meta( $entry['id'], '_novalnet_transaction_comments', true );
		if ( ! rgblank( $entry ['payment_method'] ) ) {
			?>
			<div id="gf_novalnet_payment_method" class="gf_payment_detail">
				<?php echo esc_html__( 'Payment Method:', 'novalnet-gravity-forms' ); ?><br/>
				<span id='gf_novalnet_payment_method_value'><strong><?php echo esc_html( str_replace( PHP_EOL, '<br/>', $entry ['payment_method'] ) ); ?></strong></span>
			</div>
			<?php
		}

		if ( 2 === (int) $entry['transaction_type'] && 'Active' === $entry['payment_status'] ) {
			$subscription_details = GF_Novalnet_Helper::get_subscription_details( $entry['id'] );
			$subs_expiry_date     = gform_get_meta( $entry['id'], '_nn_subs_expiry_data', true );
			if ( ! empty( $subscription_details['next_payment_date'] ) ) {
				$next_payment_date = GFCommon::format_date( $subscription_details['next_payment_date'], false, 'Y/m/d', false );
				?>
				<div id="gf_novalnet_subs_expiry" class="gf_payment_detail">
					<br/>
					<?php echo esc_html__( 'Next Payment', 'novalnet-gravity-forms' ); ?>:
					<span id='gform_payment_date'><?php echo esc_html( $next_payment_date ); ?></span>
				</div>
				<?php
			}
			if ( ! empty( $subs_expiry_date ) ) {
				$subs_expiry_date = GFCommon::format_date( $subs_expiry_date, false, 'Y/m/d', false );
				?>
				<div id="gf_novalnet_subs_expiry" class="gf_payment_detail">
					<?php echo esc_html__( 'Expiry Date', 'novalnet-gravity-forms' ); ?>:
					<span id='gform_payment_date'><?php echo esc_html( $subs_expiry_date ); ?></span>
				</div>
				<?php
			}
		}
		if ( ! rgblank( $transaction_details ) ) {
			?>
			<div id="gf_novalnet_transaction_details" class="gf_payment_detail">
				<?php esc_html_e( 'Novalnet Transaction Details:', 'novalnet-gravity-forms' ); ?><br/>
				<span id='gf_novalnet_transaction_details_value'><?php echo wp_kses( nl2br( $transaction_details ), $allowedhtml_cycles ); ?></span>
			</div>
			<?php
		}
	}

	/**
	 * Shows the error message inside the form.
	 *
	 * @param string $content The content of the page.
	 *
	 * @return string
	 */
	public function show_error_message( $content ) {
		if ( isset( $this->request['gf_novalnet_error'] ) ) {
			$content .= sprintf(
				'<div class="validation_error">%s</div>',
				$this->request['gf_novalnet_error']
			);
		}
		return $content;
	}

	/**
	 * Used to enqueue additional inline scripts
	 *
	 * @param array  $form       The Form object.
	 * @param string $field_vale The current value of the selected field.
	 * @param bool   $is_ajax    Returns true if using AJAX.  Otherwise, false.
	 */
	public function enqueue_novalnet_checkout_script( $form, $field_vale, $is_ajax ) {
		wp_enqueue_script( 'novalnet-checkout', 'https://paygate.novalnet.de/v2/checkout.js', array(), GF_NOVALNET_VERSION, true );
		$txn_secret = '';
		if ( rgget( 'gf_nnentry_id' ) ) {
			$txn_secret = gform_get_meta( rgget( 'gf_nnentry_id' ), '_render_novalnet_overlay', true );
			gform_delete_meta( rgget( 'gf_nnentry_id' ), '_render_novalnet_overlay' );
		}
		if ( rgget( 'gf_novalnet_txn_secret' ) && ! empty( $txn_secret ) ) {
			$gf_form_data = array(
				'form_id'    => $form['id'],
				'txn_secret' => $txn_secret,
			);
			wp_enqueue_script( 'novalnet-front', GF_NOVALNET_URL . 'js/novalnet.js', array( 'jquery' ), GF_NOVALNET_VERSION, true );
			wp_localize_script( 'novalnet-front', 'gf_novalnet_form_data', $gf_form_data );
		}
	}

	/**
	 * Add supported notification events.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array|false The supported notification events. False if feed cannot be found within $form.
	 */
	public function supported_notification_events( $form ) {
		// If this form does not have a feed, return false.
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}
		// Return notification events.
		return array(
			'complete_payment'          => esc_html__( 'Payment Completed', 'novalnet-gravity-forms' ),
			'refund_payment'            => esc_html__( 'Payment Refunded', 'novalnet-gravity-forms' ),
			'fail_payment'              => esc_html__( 'Payment Failed', 'novalnet-gravity-forms' ),
			'create_subscription'       => esc_html__( 'Subscription Created', 'novalnet-gravity-forms' ),
			'cancel_subscription'       => esc_html__( 'Subscription Canceled', 'novalnet-gravity-forms' ),
			'add_subscription_payment'  => esc_html__( 'Subscription Payment Added', 'novalnet-gravity-forms' ),
			'fail_subscription_payment' => esc_html__( 'Subscription Payment Failed', 'novalnet-gravity-forms' ),
		);

	}

	/**
	 * Store instalment data.
	 *
	 * @param array $data The instalment data.
	 * @param int   $order_total The total transaction amount.
	 *
	 * @return array
	 */
	public function store_instalment_data( $data, $order_total ) {
		$instalment_details = array();
		if ( ! empty( $data['instalment'] ) && isset( $data['instalment']['cycles_executed'] ) ) {
			$transaction_amount = ( ! empty( $order_total ) && $order_total > $data['transaction']['amount'] ) ? $order_total : $data['transaction']['amount'];
			$cycle_amount       = $data['instalment']['cycle_amount'];
			$cycles             = $data['instalment']['cycle_dates'];
			$total_cycles       = count( $cycles );
			$last_cycle_amount  = $transaction_amount - ( $cycle_amount * ( $total_cycles - 1 ) );
			$instalment_details = array(
				'currency'        => $data['instalment']['currency'],
				'cycle_amount'    => $cycle_amount,
				'cycles_executed' => $data['instalment']['cycles_executed'],
				'pending_cycles'  => $data['instalment']['pending_cycles'],
				'cycle_details'   => array(),
			);

			foreach ( $cycles as $cycle => $cycle_date ) {
				$instalment_details['cycle_details'][ $cycle ]['amount'] = ( $cycle == $total_cycles ) ? $last_cycle_amount : $data['instalment']['cycle_amount']; // phpcs:ignore WordPress.PHP.StrictComparisons
				if ( $instalment_details['cycles_executed'] === $cycle ) {
					$instalment_details['cycle_details'][ $cycle ]['tid']             = ( ! empty( $data['instalment']['tid'] ) ) ? $data['instalment']['tid'] : $data['transaction']['tid'];
					$instalment_details['cycle_details'][ $cycle ]['next_cycle_date'] = $data['instalment']['next_cycle_date'];
				}
				$instalment_details['cycle_details'][ $cycle ]['date'] = $cycle_date;
			}
			$instalment_details = array( 'instalment_data' => $instalment_details );
		}
		if ( ! empty( $instalment_details ) ) {
			return GF_Novalnet_Helper::serialize_novalnet_data( $instalment_details );
		}
	}

	/**
	 * Get Instalment date
	 *
	 * @param array $data  The data.
	 *
	 * @return string
	 */
	public function store_instalment_data_webhook( $data ) {
		$instalment          = array();
		$transaction_details = GF_Novalnet_Helper::get_transaction_details( $data['event']['parent_tid'] );
		$additional_info     = GF_Novalnet_Helper::unserialize_novalnet_data( $transaction_details['additional_info'] );
		$instalment_details  = $additional_info['instalment_data'];
		$cycles_executed     = $data['instalment']['cycles_executed'];
		if ( ! empty( $instalment_details ) && ! empty( $cycles_executed ) ) {
			$total_cycles                          = count( $instalment_details['cycle_details'] );
			$instalment_details['cycles_executed'] = $cycles_executed;
			$instalment_details['pending_cycles']  = ( ! empty( $data['instalment']['pending_cycles'] ) ) ? $data['instalment']['pending_cycles'] : $total_cycles - $cycles_executed;

			$instalment ['tid']    = $data['transaction']['tid'];
			$instalment ['amount'] = $data['instalment']['cycle_amount'];
			$instalment ['date']   = gmdate( 'Y-m-d H:i:s' );
			if ( ! empty( $data['instalment']['next_cycle_date'] ) ) {
				$instalment ['next_cycle_date'] = $data['instalment']['next_cycle_date'];
			}
			$instalment_details['cycle_details'][ $cycles_executed ] = $instalment;
			$additional_info['instalment_data']                      = $instalment_details;
		}
		return GF_Novalnet_Helper::serialize_novalnet_data( $additional_info );
	}

	/**
	 * Get Store instalment data.
	 *
	 * @param string $transaction_id The transaction id.
	 *
	 * @return array
	 */
	public static function get_stored_instalment_data( $transaction_id ) {
		$transaction_details = GF_Novalnet_Helper::get_transaction_details( $transaction_id );
		$additional_info     = GF_Novalnet_Helper::unserialize_novalnet_data( rgar( $transaction_details, 'additional_info' ) );
		$instalment_details  = ( ! empty( $additional_info['instalment_data'] ) ) ? $additional_info['instalment_data'] : array();
		return $instalment_details;
	}

	/**
	 * Register instalment metabox callback function.
	 *
	 * @param array $meta_boxes The meta box object.
	 * @param array $entry The current entry details.
	 * @param array $form The form details.
	 *
	 * @return array
	 */
	public function add_instalment_metabox( $meta_boxes, $entry, $form ) {
		if ( rgar( $entry, 'transaction_id' ) ) {
			$transaction_details = GF_Novalnet_Helper::get_transaction_details( rgar( $entry, 'transaction_id' ) );
			$instalment          = self::get_stored_instalment_data( rgar( $entry, 'transaction_id' ) );
			if ( ! empty( $transaction_details ) && ! empty( $instalment['cycle_details'] ) ) {
				$meta_boxes['novalnet_installment'] = array(
					'title'    => esc_html__( 'Novalnet Instalment', 'novalnet-gravity-forms' ),
					'callback' => array( 'GF_Novalnet', 'meta_box_novalnet_instalment' ),
					'context'  => 'normal',
				);
			}
		}
		return $meta_boxes;
	}

	/**
	 * Display novalnet instalment details.
	 *
	 * @param array $args Details of form and entry.
	 *
	 * @return void
	 */
	public static function meta_box_novalnet_instalment( $args ) {
		$entry               = $args['entry'];
		$form                = $args['form'];
		$instalments         = self::get_stored_instalment_data( rgar( $entry, 'transaction_id' ) );
		$transaction_details = GF_Novalnet_Helper::get_transaction_details( rgar( $entry, 'transaction_id' ) );
		include_once 'templates/render-instalment-details.php';
	}
}
