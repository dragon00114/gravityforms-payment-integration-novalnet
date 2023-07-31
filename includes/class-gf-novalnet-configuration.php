<?php
/**
 * This file is used for displaying configuration fields and
 * auto-configuration of merchant details and webhook URL
 *
 * @author   Novalnet AG
 * @package  novalnet-gravity-forms
 * @license  https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * GF_Novalnet_Configuration
 */
class GF_Novalnet_Configuration {

	/**
	 * Handle auto config call
	 */
	public static function send_auto_config_call() {
		$error = '';
		check_ajax_referer( 'nn_get_novalnet_vendor_details', 'nonce' );
		$post                = sanitize_post( $_POST );
		$novalnet_access_key = ! empty( wp_unslash( $post['novalnet_access_key'] ) ) ? wp_unslash( $post['novalnet_access_key'] ) : '';
		$novalnet_api_key    = ! empty( wp_unslash( $post['novalnet_api_key'] ) ) ? wp_unslash( $post['novalnet_api_key'] ) : '';
		if ( ! empty( $novalnet_api_key ) && ! empty( $novalnet_access_key ) ) {
			$request  = array(
				'merchant' => array(
					'signature' => $novalnet_api_key,
				),
				'custom'   => array(
					'lang' => GF_Novalnet_Helper::get_language(),
				),
			);
			$response = GF_Novalnet_Helper::perform_http_request( $request, 'merchant/details', $novalnet_access_key );
			if ( ! empty( $response['result']['status'] ) && 'SUCCESS' === $response['result']['status'] ) {
				wp_send_json_success( $response['merchant'] );
			} else {
				$error = $response['result']['status_text'];
			}
		} else {
			$error = __( 'Please enter the required fields under Novalnet API Configuration', 'novalnet-gravity-forms' );
		}
		wp_send_json_error(
			array(
				'error' => $error,
			)
		);
	}

	/**
	 * Handle webhook URL configuration call
	 */
	public static function send_webhook_url_config_call() {
		$error = '';
		check_ajax_referer( 'nn_config_novalnet_hook_url', 'nonce' );
		$post = sanitize_post( $_POST );
		if ( ! empty( $post ['novalnet_hook_url'] ) && ! empty( $post ['novalnet_api_key'] ) && ! empty( wp_unslash( $post['novalnet_access_key'] ) ) ) {
			$request  = array(
				'merchant' => array(
					'signature' => $post ['novalnet_api_key'],
				),
				'webhook'  => array(
					'url' => $post ['novalnet_hook_url'],
				),
				'custom'   => array(
					'lang' => GF_Novalnet_Helper::get_language(),
				),
			);
			$response = GF_Novalnet_Helper::perform_http_request( $request, 'webhook/configure', $post ['novalnet_access_key'] );
			if ( ! empty( $response['result']['status'] ) && 'SUCCESS' === $response['result']['status'] ) {
					$response['result']['status_text'] = __( 'Notification / Webhook URL is configured successfully in Novalnet Admin Portal', 'novalnet-gravity-forms' );
					wp_send_json_success( $response );
			} else {
				$error = $response['result']['status_text'];
			}
		} else {
			$error = __( 'Please enter the required fields under Novalnet API Configuration', 'novalnet-gravity-forms' );
		}
		wp_send_json_error(
			array(
				'error' => $error,
			)
		);
	}


	/**
	 * The function to specify the configuration fields to be rendered on the plugin settings page
	 *
	 * @return array
	 */
	public static function plugin_settings_fields() {
		$subscription_payments = array(
			'CREDITCARD',
			'PAYPAL',
			'INVOICE',
			'GUARANTEED_INVOICE',
			'DIRECT_DEBIT_SEPA',
			'GUARANTEED_DIRECT_DEBIT_SEPA',
			'PREPAYMENT',
			'GOOGLEPAY',
			'APPLEPAY',
		);

		$subs_payment_choices = array();
		foreach ( $subscription_payments as $payment_type ) {
			$payment_name           = GF_Novalnet_Helper::get_payment_name( $payment_type );
			$subs_payment_choices[] = array(
				'label'         => ( isset( $payment_name['admin_payment_name'] ) ) ? $payment_name['admin_payment_name'] : $payment_name['payment_name'],
				'value'         => 1,
				'default_value' => 1,
				'name'          => 'nn_subs_pay_' . strtolower( $payment_type ),
			);
		}

		return array(
			array(
				'title'       => esc_html__( 'Novalnet API Configuration', 'novalnet-gravity-forms' ),
				/* translators: %1$s: admin_portal_link, %2$s: admin_portal_link_close */
				'description' => sprintf( __( 'Please read the Installation Guide before you start and login to the %1$s Novalnet Admin Portal%2$s using your merchant account. To get a merchant account, mail to sales@novalnet.de or call +49 (089) 923068320.', 'novalnet-gravity-forms' ), '<a href="https://admin.novalnet.de" target="_new">', '</a>' ),

				'fields'      => array(
					array(
						'name'    => 'novalnet_test_mode',
						'tooltip' => esc_html__( 'The payment will be processed in the test mode therefore amount for this transaction will not be charged.', 'novalnet-gravity-forms' ),
						'label'   => esc_html__( 'Enable test mode', 'novalnet-gravity-forms' ),
						'type'    => 'toggle',
						'class'   => 'small',
						'choices' => array(
							array(
								'label' => '',
								'name'  => 'novalnet_test_mode',
							),
						),
					),
					array(
						'name'              => 'novalnet_public_key',
						'description'       => sprintf( __( '<small>Get your Product activation key from the <a href="https://admin.novalnet.de" target="_blank">Novalnet Admin Portal</a> Projects > Choose your project > API credentials > API Signature (Product activation key) </small>.', 'novalnet-gravity-forms' ) ),
						'tooltip'           => esc_html__( 'Your product activation key is a unique token for merchant authentication and payment processing.', 'novalnet-gravity-forms' ),
						'label'             => esc_html__( 'Product activation key', 'novalnet-gravity-forms' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'autocomplete'      => 'off',
						'feedback_callback' => array( 'GF_Novalnet_Helper', 'is_valid_string' ),
					),
					array(
						'name'              => 'novalnet_payment_access_key',
						'description'       => sprintf( __( '<small>Get your Payment access key from the Novalnet Admin Portal: Projects > Choose your project > API credentials > Payment access key</small>.', 'novalnet-gravity-forms' ) ),
						'tooltip'           => esc_html__( 'Your secret key used to encrypt the data to avoid user manipulation and fraud.', 'novalnet-gravity-forms' ),
						'label'             => esc_html__( 'Payment access key', 'novalnet-gravity-forms' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'autocomplete'      => 'off',
						'feedback_callback' => array( 'GF_Novalnet_Helper', 'is_valid_string' ),
					),
					array(
						'name'              => 'novalnet_tariff',
						'tooltip'           => esc_html__( 'Select a Tariff ID to match the preferred tariff plan you created at the Novalnet Admin Portal for this project', 'novalnet-gravity-forms' ),
						'label'             => esc_html__( 'Select tariff ID', 'novalnet-gravity-forms' ),
						'type'              => 'text',
						'required'          => true,
						'feedback_callback' => array( 'GF_Novalnet_Helper', 'is_valid_digit' ),
					),
					array(
						'name'          => 'novalnet_transaction_type',
						'tooltip'       => esc_html__( 'Choose whether or not the payment should be charged immediately. Capture completes the transaction by transferring the funds from buyer account to merchant account. Authorize verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order.', 'novalnet-gravity-forms' ),
						'label'         => esc_html__( 'Payment action', 'novalnet-gravity-forms' ),
						'type'          => 'select',
						'class'         => 'small',
						'default_value' => 'capture',
						'onchange'      => "jQuery(this).parents('form').submit();",
						'choices'       => array(
							array(
								'label' => esc_html__( 'Capture', 'novalnet-gravity-forms' ),
								'value' => 'capture',
							),
							array(
								'label' => esc_html__( 'Authorize', 'novalnet-gravity-forms' ),
								'value' => 'authorize',
							),
						),
					),
					array(
						'name'        => 'on_hold_limit',
						'description' => esc_html__( '(in minimum unit of currency. E.g. enter 100 which is equal to 1.00)', 'novalnet-gravity-forms' ),
						'tooltip'     => esc_html__( 'In case the order amount exceeds the mentioned limit, the transaction will be set on-hold till your confirmation of the transaction. You can leave the field empty if you wish to process all the transactions as on-hold.', 'novalnet-gravity-forms' ),
						'label'       => esc_html__( 'Minimum transaction amount for authorization', 'novalnet-gravity-forms' ),
						'type'        => 'text',
						'class'       => 'small',
						'dependency'  => array(
							'field'  => 'novalnet_transaction_type',
							'values' => array( 'authorize' ),
						),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Credit/Debit Card', 'novalnet-gravity-forms' ),
				'class'  => 'novalnet-configurations',
				'fields' => array(
					array(
						'name'    => 'novalnet_cc_3d',
						'tooltip' => esc_html__( 'By enabling this option, all payments from cards issued outside the EU will be authenticated via 3DS 2.0 SCA.', 'novalnet-gravity-forms' ),
						'label'   => esc_html__( 'Enforce 3D secure payment outside EU', 'novalnet-gravity-forms' ),
						'type'    => 'toggle',
						'class'   => 'small',
						'choices' => array(
							array(
								'label' => '',
								'name'  => 'novalnet_cc_3d',
							),
						),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Subscription Management', 'novalnet-gravity-forms' ),
				'class'  => 'novalnet-configurations novalnet-subs-config',
				'fields' => array(
					array(
						'name'              => 'novalnet_subs_tariff',
						'tooltip'           => esc_html__( 'Select the preferred Novalnet subscription tariff ID available for your project. For more information, please refer the Installation Guide', 'novalnet-gravity-forms' ),
						'label'             => esc_html__( 'Select subscription tariff ID', 'novalnet-gravity-forms' ),
						'type'              => 'text',
						'class'             => 'small',
						'required'          => false,
						'feedback_callback' => array( 'GF_Novalnet_Helper', 'is_valid_digit' ),
					),
					array(
						'name'    => 'novalnet_payments',
						'label'   => esc_html__( 'Subscription payments', 'novalnet-gravity-forms' ),
						'type'    => 'checkbox',
						'class'   => 'small',
						'choices' => $subs_payment_choices,
					),
				),
			),
			array(
				'title'  => esc_html__( 'Direct Debit SEPA', 'novalnet-gravity-forms' ),
				'class'  => 'novalnet-configurations',
				'fields' => array(
					array(
						'name'       => 'novalnet_sepa_due_date',
						'tooltip'    => esc_html__( ' Number of days after which the payment is debited (must be between 2 and 14 days).', 'novalnet-gravity-forms' ),
						'label'      => esc_html__( 'Payment due date (in days)', 'novalnet-gravity-forms' ),
						'type'       => 'text',
						'class'      => 'small',
						'input_type' => 'number',
						'min'        => 2,
					),
				),
			),
			array(
				'title'  => esc_html__( 'Invoice / Prepayment', 'novalnet-gravity-forms' ),
				'class'  => 'novalnet-configurations',
				'fields' => array(
					array(
						'name'       => 'novalnet_due_date',
						'tooltip'    => esc_html__( 'Number of days given to the buyer to transfer the amount to Novalnet (must be greater than 7 days). If this field is left blank, 14 days will be set as due date by default.', 'novalnet-gravity-forms' ),
						'label'      => esc_html__( 'Payment due date (in days)', 'novalnet-gravity-forms' ),
						'type'       => 'text',
						'class'      => 'small',
						'min'        => 7,
						'input_type' => 'number',
					),
				),
			),
			array(
				'title'  => esc_html__( 'Barzahlen/viacash', 'novalnet-gravity-forms' ),
				'class'  => 'novalnet-configurations',
				'fields' => array(
					array(
						'name'       => 'novalnet_slip_expiry_date',
						'tooltip'    => esc_html__( 'Number of days given to the buyer to pay at a store. If this field is left blank, 14 days will be set as slip expiry date by default.', 'novalnet-gravity-forms' ),
						'label'      => esc_html__( 'Slip expiry date (in days)', 'novalnet-gravity-forms' ),
						'type'       => 'text',
						'class'      => 'small',
						'input_type' => 'number',
						'min'        => 1,
					),
				),
			),
			array(
				'title'  => esc_html__( 'Instalment', 'novalnet-gravity-forms' ),
				'class'  => 'novalnet-configurations',
				'fields' => array(
					array(
						'name'        => 'novalnet_instalment_cycles_plan',
						'tooltip'     => esc_html__( 'Select the instalment cycles that can be availed in the instalment plan', 'novalnet-gravity-forms' ),
						'label'       => esc_html__( 'Instalment Cycles', 'novalnet-gravity-forms' ),
						'description' => sprintf( __( '<small>Enter instalment cycles <strong>Example: 2,4,6,10,12</strong></small>.', 'novalnet-gravity-forms' ) ),
						'type'        => 'text',
						'class'       => 'small',
					),
				),
			),
			array(
				'title'  => esc_html__( 'Notification / Webhook URL Setup', 'novalnet-gravity-forms' ),
				'class'  => 'novalnet-configurations',
				'fields' => array(
					array(
						'name'    => 'novalnet_webhook_test_mode',
						'tooltip' => 'This option will allow performing a manual execution. Please disable this option before setting your shop to LIVE mode, to avoid unauthorized calls from external parties (excl. Novalnet).',
						'label'   => esc_html__( 'Deactivate IP address control (for test purpose only)', 'novalnet-gravity-forms' ),
						'type'    => 'toggle',
						'choices' => array(
							array(
								'label' => '',
								'name'  => 'novalnet_webhook_test_mode',
							),
						),
					),
					array(
						'name'          => 'novalnet_webhook_email_to',
						'tooltip'       => esc_html__( 'E-mail address of the recipient', 'novalnet-gravity-forms' ),
						'default_value' => get_bloginfo( 'admin_email' ),
						'label'         => esc_html__( 'Send e-mail to', 'novalnet-gravity-forms' ),
						'type'          => 'text',
						'class'         => 'medium',
					),
					array(
						'name'    => 'novalnet_webhook_url',
						'tooltip' => esc_html__( 'The notification URL is used to keep your database/system actual and synchronizes with the Novalnet transaction status.', 'novalnet-gravity-forms' ),
						'label'   => esc_html__( 'Notification / Webhook URL', 'novalnet-gravity-forms' ),
						'type'    => 'text',
						'class'   => 'medium',
						'value'   => esc_url( add_query_arg( 'nn_action', 'gf_novalnet_webhook', get_bloginfo( 'url' ) . '/' ) ),
					),
					array(
						'name' => 'webhook_config',
						'type' => 'html',
						'html' => "<button type='button' class='secondary button' onclick='novalnet_admin.config_notification_url()'>Configure</button>",
					),
				),
			),
		);
	}
}
