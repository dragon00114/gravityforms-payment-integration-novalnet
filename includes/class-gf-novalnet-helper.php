<?php
/**
 * Novalnet Helper Class
 *
 * @author   Novalnet AG
 * @category Class
 * @package  novalnet-gravity-forms
 * @version  2.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * GF_Novalnet_Helper
 */
class GF_Novalnet_Helper {

	/**
	 * Duplicate query argumnets
	 *
	 * @var array
	 */
	public static $duplicate_query_arg = array( 'gf_novalnet_return', 'checksum', 'tid', 'txn_secret', 'status', 'nn_action', 'gf_novalnet_error', 'payment_type', 'status_text', 'status_code', 'status', 'gf_nnentry_id' );

	/**
	 * Handles the error while exception occurs.
	 *
	 * @param string $query The processed query.
	 *
	 * @throws Exception The WordPress error as exception.
	 *
	 * @return boolean
	 */
	public static function handle_query_error( $query ) {
		global $wpdb;
		try {
			// Checking for query error.
			if ( $wpdb->last_error ) {
				throw new Exception( $wpdb->last_error );
			}
		} catch ( Exception $e ) {
			GFCommon::log_error( 'SQL error occured: ' . __CLASS__ . '::' . __FUNCTION__ . $e->getMessage() );
		}
		return $query;
	}

	/**
	 * Validation for digits.
	 *
	 * @param string $input The input value.
	 *
	 * @return boolean
	 */
	public static function is_valid_digit( $input ) {
		if ( ! empty( $input ) ) {
			return ( preg_match( '/^[0-9]+$/', $input ) ) ? $input : false;
		}
		return false;
	}

	/**
	 * Validation for string.
	 *
	 * @param string $input The input value.
	 *
	 * @return boolean
	 */
	public static function is_valid_string( $input ) {
		if ( ! empty( $input ) ) {
			return ( preg_match( '/^[a-z0-9|]+$/i', $input ) ) ? $input : false;
		}
		return false;
	}

	/**
	 * Build customer fields value based on gravity forms.
	 *
	 * @return boolean
	 */
	public static function get_customer_fields() {
		return array(
			array(
				'name'      => 'first_name',
				'meta_name' => 'billingInformation_firstName',
			),
			array(
				'name'      => 'last_name',
				'meta_name' => 'billingInformation_lastName',
			),
			array(
				'name'      => 'email',
				'meta_name' => 'billingInformation_email',
			),
			array(
				'name'      => 'address1',
				'meta_name' => 'billingInformation_address',
			),
			array(
				'name'      => 'address2',
				'meta_name' => 'billingInformation_address2',
			),
			array(
				'name'      => 'city',
				'meta_name' => 'billingInformation_city',
			),
			array(
				'name'      => 'state',
				'meta_name' => 'billingInformation_state',
			),
			array(
				'name'      => 'zip',
				'meta_name' => 'billingInformation_zip',
			),
			array(
				'name'      => 'country',
				'meta_name' => 'billingInformation_country',
			),
			array(
				'name'      => 'birth_date',
				'meta_name' => 'billingInformation_birth_date',
			),
			array(
				'name'      => 'company',
				'meta_name' => 'billingInformation_company',
			),
			array(
				'name'      => 'phone',
				'meta_name' => 'billingInformation_phone',
			),
			array(
				'name'      => 'mobile',
				'meta_name' => 'billingInformation_mobile',
			),
		);
	}

	/**
	 * Converting the amount into cents
	 *
	 * @param float $amount The amount.
	 *
	 * @return int
	 */
	public static function formatted_amount( $amount ) {

		return str_replace( ',', '', sprintf( '%0.2f', $amount ) ) * 100;
	}

	/**
	 * Converting the amount into euro
	 *
	 * @param int    $amount The amount.
	 * @param string $currency The currency.
	 *
	 * @return float
	 */
	public static function get_formatted_amount( $amount, $currency = '' ) {
		if ( '' === $currency ) {
			return wp_strip_all_tags( sprintf( '%0.2f', ( $amount / 100 ) ) );
		}
		return wp_strip_all_tags( GFCommon::to_money( sprintf( '%0.2f', ( $amount / 100 ), $currency ) ) );
	}

	/**
	 * Return server / address.
	 *
	 * @return float
	 */
	public static function get_server_address() {
		if ( isset( $_SERVER ['SERVER_ADDR'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER ['SERVER_ADDR'] ) );
		} else {
			$ip_address = isset( $_SERVER['HTTP_HOST'] ) ? gethostbyname( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		}
		return $ip_address;
	}

	/**
	 * Handle server post process.
	 *
	 * @param array  $request_data The array values to be sent.
	 * @param string $action       The payment endpoint action.
	 * @param string $access_key   The authentication arguments.
	 *
	 * @return array
	 */
	public static function perform_http_request( $request_data, $action, $access_key = '' ) {

		$url = 'https://payport.novalnet.de/v2/' . $action;
		// Perform server call and format the response.
		if ( ! empty( $access_key ) ) {
			// Form headers.
			$headers = array(
				'Content-Type'    => 'application/json',
				'charset'         => 'utf-8',
				'Accept'          => 'application/json',
				'X-NN-Access-Key' => base64_encode( $access_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			);

			$order_no     = ( ! empty( $request_data['transaction']['order_no'] ) ) ? '[ ' . $request_data['transaction']['order_no'] . ' ]' : '';
			$json_request = self::serialize_novalnet_data( $request_data );
			// Post the values to the paygate URL.
			$response = wp_remote_post(
				$url,
				array(
					'method'  => 'POST',
					'headers' => $headers,
					'timeout' => 240,
					'body'    => $json_request,
				)
			);
			// Return error.
			if ( is_wp_error( $response ) ) {
				return array(
					'result' => array(
						'status'      => 'FAILURE',
						'status_code' => '106',
						'status_text' => $response->get_error_message(),
					),
				);
			} elseif ( ! empty( $response['body'] ) ) {
				return self::unserialize_novalnet_data( $response['body'] );
			}
		}
		return array(
			'result' => array(
				'status_code' => '106',
				'status'      => 'FAILURE',
				'status_text' => __( 'Please enter the required fields under Novalnet API Configuration', 'novalnet-gravity-forms' ),
			),
		);
	}

	/**
	 * Get novalnet transaction details.
	 *
	 * @param array  $data Transaction data.
	 * @param string $access_key Payment access key.
	 *
	 * @return array
	 */
	public static function get_transaction_result( $data, $access_key ) {
		$parameters = array(
			'transaction' => array(
				'tid' => $data['tid'],
			),
			'custom'      => array(
				'lang' => strtoupper( self::get_language() ),
			),
		);
		$response   = self::perform_http_request( $parameters, 'transaction/details', $access_key );
		return $response;
	}

	/**
	 * Returns WordPress language.
	 *
	 * @return string
	 */
	public static function get_language() {
		return substr( get_bloginfo( 'language' ), 0, 2 );
	}

	/**
	 * Get the customer details.
	 *
	 * @param array $feed  Active payment feed containing all the configuration data.
	 * @param array $entry Current entry array containing entry information (i.e data submitted by users).
	 * @param array $form  Current form array containing all form settings.
	 *
	 * @return array
	 */
	public static function get_customer_data( $feed, $entry, $form ) {
		$data = array();
		foreach ( self::get_customer_fields() as $field ) {
			if ( isset( $feed['meta'][ $field['meta_name'] ] ) ) {
				$field_id = $feed['meta'][ $field['meta_name'] ];

				$value = rgar( $entry, $field_id );

				$data[ $field['name'] ] = $value;
			}
		}

		if ( rgar( $data, 'country' ) ) {
			$data['country_code'] = GFCommon::get_country_code( $data['country'] );
		}
		return $data;
	}

	/**
	 * Forms the system parameters.
	 *
	 * @param array $parameters The formed parameters.
	 */
	public static function form_custom_parameters( &$parameters ) {

		$parameters['custom'] = array(
			'lang' => strtoupper( self::get_language() ),
		);

	}

	/**
	 * Forms the system parameters.
	 *
	 * @param array $parameters The formed parameters.
	 * @param array $feed       The current payment feed.
	 * @param array $settings   The payment config settings.
	 */
	public static function form_hosted_page_parameters( &$parameters, $feed, $settings ) {
		$parameters['hosted_page'] = array(
			'hide_blocks' => array(
				0 => 'ADDRESS_FORM',
				1 => 'SHOP_INFO',
				2 => 'LANGUAGE_MENU',
				3 => 'TARIFF',
			),
			'skip_pages'  => array(
				0 => 'CONFIRMATION_PAGE',
				1 => 'SUCCESS_PAGE',
				2 => 'PAYMENT_PAGE',
			),
		);

		if ( 'subscription' === $feed['meta']['transactionType'] && ! empty( $parameters ['subscription'] ) ) {
			$display_payments = array();
			foreach ( $settings as $field => $value ) {
				if ( strpos( $field, 'nn_subs_pay_' ) !== false && 1 === (int) $value ) {
					$payment_type = strtoupper( str_replace( 'nn_subs_pay_', '', $field ) );
					if ( ( isset( $parameters['transaction']['amount'] ) && $parameters['transaction']['amount'] < 999 ) || isset( $parameters['subscription']['trial_amount'] ) && $parameters['subscription']['trial_amount'] < 999 ) {
						if ( in_array( $payment_type, array( 'GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA' ), true ) ) {
							continue;
						}
					}
					$display_payments[] = strtoupper( str_replace( 'nn_subs_pay_', '', $payment_type ) );
				}
			}
			if ( ! empty( $display_payments ) ) {
				$parameters['hosted_page']['display_payments'] = $display_payments;
			}
		}
	}

	/**
	 * Forms the transaction parameters.
	 *
	 * @param array $entry The entry to process payment.
	 * @param array $form The form to process payment.
	 * @param array $submission_data The current form submission data.
	 * @param array $parameters The request data.
	 * @param array $settings The addon settings.
	 */
	public static function form_transaction_parameters( $entry, $form, $submission_data, &$parameters, $settings ) {
		$due_dates = array();

		if ( rgar( $settings, 'novalnet_due_date' ) >= 7 ) {
			$due_dates['INVOICE']    = gmdate( 'Y-m-d', strtotime( '+ ' . rgar( $settings, 'novalnet_due_date' ) . ' day' ) );
			$due_dates['PREPAYMENT'] = gmdate( 'Y-m-d', strtotime( '+ ' . rgar( $settings, 'novalnet_due_date' ) . ' day' ) );
		}
		if ( rgar( $settings, 'novalnet_sepa_due_date' ) >= 2 && rgar( $settings, 'novalnet_sepa_due_date' ) <= 14 ) {
			$due_dates['DIRECT_DEBIT_SEPA'] = gmdate( 'Y-m-d', strtotime( '+ ' . rgar( $settings, 'novalnet_sepa_due_date' ) . ' day' ) );
		}
		if ( rgar( $settings, 'novalnet_slip_expiry_date' ) >= 1 ) {
			$due_dates['CASHPAYMENT'] = gmdate( 'Y-m-d', strtotime( '+ ' . rgar( $settings, 'novalnet_slip_expiry_date' ) . ' day' ) );
		}
		$payment_amount            = ( ! empty( $submission_data['payment_amount'] ) ) ? $submission_data['payment_amount'] : GFCommon::get_order_total( $form, $entry );
		$parameters['transaction'] = array(
			'amount'           => self::formatted_amount( $payment_amount ),
			'currency'         => rgar( $entry, 'currency' ),
			'test_mode'        => ( isset( $settings['novalnet_test_mode'] ) && '1' === $settings['novalnet_test_mode'] ) ? 1 : 0,
			'order_no'         => rgar( $entry, 'id' ),
			'return_url'       => self::return_url( $entry['form_id'], $entry['id'] ),
			'error_return_url' => self::return_url( $entry['form_id'], $entry['id'], $entry['source_url'] ),
			'hook_url'         => $settings['novalnet_webhook_url'],
			'system_name'      => 'Gravity Forms',
			'system_version'   => GFForms::$version . '-NN-' . GF_NOVALNET_VERSION,
			'system_ip'        => self::get_server_address(),
		);

		if ( ! empty( $settings['novalnet_cc_3d'] ) ) {
			$parameters['transaction']['enforce_3d'] = $settings['novalnet_cc_3d'];
		}

		if ( ! empty( $due_dates ) ) {
			$parameters['transaction']['due_dates'] = $due_dates;
		}
	}

	/**
	 * Forms the instalment parameters.
	 *
	 * @param string $cycles   The selected cycles.
	 * @param array  $parameters The formed parameters.
	 */
	public static function form_instalment_parameters( $cycles, &$parameters ) {
		$parameters['instalment'] = array(
			'preselected_cycle' => 4,
			'cycles_list'       => explode( ',', $cycles ),
		);
	}

	/**
	 * Forms the customer parameters.
	 *
	 * @param array $customer End customer billing details.
	 * @param array $entry The entry.
	 * @param array $parameters Server request data.
	 */
	public static function form_cust_parameters( $customer, $entry, &$parameters ) {
		$parameters['customer'] = array(
			'first_name'  => rgar( $customer, 'first_name' ),
			'last_name'   => rgar( $customer, 'last_name' ),
			'email'       => rgar( $customer, 'email' ),
			'customer_ip' => rgar( $entry, 'ip' ),
			'customer_no' => rgar( $entry, 'id' ),
			'billing'     =>
				array(
					'street'       => trim( rgar( $customer, 'address1' ) . ' ' . rgar( $customer, 'address2' ) ),
					'city'         => rgar( $customer, 'city' ),
					'zip'          => rgar( $customer, 'zip' ),
					'country_code' => rgar( $customer, 'country_code' ),
				),
		);

		if ( rgar( $customer, 'birth_date' ) ) {
			$parameters['customer']['birth_date'] = rgar( $customer, 'birth_date' );
		}
		if ( rgar( $customer, 'company' ) ) {
			$parameters['customer']['billing']['company'] = rgar( $customer, 'company' );
		}
		if ( rgar( $customer, 'state' ) ) {
			$parameters['customer']['billing']['state'] = rgar( $customer, 'state' );
		}
	}

	/**
	 * Forms the merchant parameters.
	 *
	 * @param array $settings   The plugin settings.
	 * @param array $parameters The formed parameters.
	 */
	public static function form_merchant_parameters( $settings, &$parameters ) {
		$parameters['merchant'] = array(
			'signature' => $settings['novalnet_public_key'],
			'tariff'    => $settings['novalnet_tariff'],
		);
	}

	/**
	 * Forms the subscription data
	 *
	 * @param array $feed            Active payment feed containing all the configuration data.
	 * @param array $settings        The currently saved plugin settings.
	 * @param array $submission_data Contains form field data submitted by the user as well as payment information.
	 * @param array $parameters      The formed parameters.
	 */
	public static function form_subscription_parameters( $feed, $settings, $submission_data, &$parameters ) {
		if ( 'week' === $feed['meta']['billingCycle_unit'] ) {
			$interval = ( $feed['meta']['billingCycle_length'] * 7 ) . 'd';
		} else {
			$interval = $feed['meta']['billingCycle_length'] . $feed['meta']['billingCycle_unit'][0];
		}
		$parameters['subscription']['interval'] = $interval;
		$parameters['merchant']['tariff']       = $settings['novalnet_subs_tariff'];
		$trial_amount                           = 0;
		if ( ! empty( $feed['meta']['setupFee_enabled'] ) || ! empty( $feed['meta']['trial_enabled'] ) ) {
			if ( ! empty( $feed['meta']['setupFee_enabled'] ) && ! empty( $submission_data['setup_fee'] ) ) {
				$trial_amount += self::formatted_amount( $submission_data['setup_fee'] );
			}

			if ( ! empty( $feed['meta']['trial_enabled'] ) && (int) $feed['meta']['trial_period'] > 0 ) {
				if ( ! empty( $submission_data['trial'] ) ) {
					$trial_amount += self::formatted_amount( $submission_data['trial'] );
				}
				$parameters['subscription']['trial_interval'] = $feed['meta']['trial_period'] . 'd';
			} else {
				$trial_amount = ( $trial_amount > 0 ) ? $parameters['transaction']['amount'] + $trial_amount : $parameters['transaction']['amount'];
			}

			$parameters['subscription']['trial_amount'] = $trial_amount;

			if ( ( isset( $feed['meta']['trial_enabled'] ) && 0 == $feed['meta']['trial_enabled'] ) && 0 === $trial_amount ) { // phpcs:ignore WordPress.PHP.StrictComparisons
				unset( $parameters['subscription']['trial_amount'] );
				unset( $parameters['subscription']['trial_interval'] );
			}
		}

		if ( isset( $feed['meta']['recurringTimes'] ) && $feed['meta']['recurringTimes'] > 0 ) {

			$expiry_interval = ( $feed['meta']['billingCycle_length'] * $feed['meta']['recurringTimes'] );

			$current_timestamp = strtotime( gmdate( 'Y-m-d H:i:s' ) );

			$expiry_timestamp = strtotime( '+' . $expiry_interval . ' ' . $feed['meta']['billingCycle_unit'], $current_timestamp );

			if ( ! empty( $parameters['subscription']['trial_interval'] ) ) {
				$expiry_timestamp = strtotime( '+' . str_replace( 'd', ' Days', $parameters['subscription']['trial_interval'] ), $expiry_timestamp );
			}

			if ( ! empty( $expiry_timestamp ) && $expiry_timestamp > $current_timestamp ) {
				$parameters['subscription']['expiry_date'] = gmdate( 'Y-m-d', $expiry_timestamp );
				gform_update_meta( $parameters['transaction']['order_no'], '_nn_subs_expiry_data', $parameters['subscription']['expiry_date'] );
			}
		}
	}

	/**
	 * Build base return url.
	 */
	public static function get_form_request_url() {
		$url = GFCommon::is_ssl() ? 'https://' : 'http://';

		$server_name = ( isset( $_SERVER['SERVER_NAME'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : '';
		$server_port = ( isset( $_SERVER['SERVER_PORT'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_PORT'] ) ) : '';
		$request_uri = ( isset( $_SERVER['REQUEST_URI'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		$server_port = apply_filters( 'gform_novalnet_return_url_port', $server_port );

		if ( '80' !== $server_port ) {
			$url .= $server_name . ':' . $server_port . $request_uri;
		} else {
			$url .= $server_name . $request_uri;
		}
		$url = remove_query_arg( self::$duplicate_query_arg, $url );
		return $url;
	}

	/**
	 * Build the valid return URL .
	 *
	 * @param int    $form_id The form ID value.
	 * @param int    $lead_id The lead ID value.
	 * @param string $source_url The request form url.
	 */
	public static function return_url( $form_id = '', $lead_id = '', $source_url = '' ) {
		$url = self::get_form_request_url();
		if ( '' === $form_id && '' === $lead_id ) {
			return $url;
		}
		$ids_query  = "entry_id={$form_id}|{$lead_id}";
		$ids_query .= '&hash=' . wp_hash( $ids_query );
		return add_query_arg(
			array(
				'nn_action'          => 'gf_novalnet_response',
				'gf_novalnet_return' => base64_encode( $ids_query ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			),
			$url
		);
	}

	/**
	 * Internal redirect url.
	 *
	 * @param string $form_id The current form id.
	 * @param string $lead_id The current lead id.
	 * @param string $txn_secret The current transactions txn secret.
	 */
	public static function internal_redirect_url( $form_id = '', $lead_id = '', $txn_secret = '' ) {
		$url = self::get_form_request_url();
		if ( '' === $form_id && '' === $lead_id ) {
			return $url;
		}

		return add_query_arg(
			array(
				'gf_novalnet_txn_secret' => $txn_secret,
				'gf_nnentry_id'          => $lead_id,
			),
			$url
		);
	}

	/**
	 * Form transaction details, bank details, nearest store details and instalment details.
	 *
	 * @param array $data The data used to form the comments.
	 *
	 * @return string
	 */
	public static function form_transaction_details( $data ) {
		// Forming basic comments.
		$transaction_details = self::form_transaction_data( $data );
		if ( 'PENDING' === $data['transaction']['status'] && in_array( $data['transaction']['payment_type'], array( 'GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA', 'INSTALMENT_INVOICE_WITH_RATE', 'INSTALMENT_DIRECT_DEBIT_SEPA_WITH_RATE' ), true ) ) {
			$transaction_details .= PHP_EOL . PHP_EOL . __( 'Your order is under verification and we will soon update you with the order status. Please note that this may take upto 24 hours.', 'novalnet-gravity-forms' );
		} elseif ( ! empty( $data ['transaction']['bank_details'] ) && ! empty( $data ['transaction']['amount'] ) && empty( $data ['instalment']['prepaid'] ) ) {
			$transaction_details .= self::form_bank_details( $data );
		} elseif ( ! empty( $data['transaction']['nearest_stores'] ) ) {
			$transaction_details .= self::form_nearest_store_details( $data );

		} elseif ( ! empty( $data['transaction']['partner_payment_reference'] ) ) {
			/* translators: %s: amount */
			$transaction_details .= PHP_EOL . sprintf( __( 'Please use the following payment reference details to pay the amount of %s at a Multibanco ATM or through your internet banking.', 'novalnet-gravity-forms' ), self::get_formatted_amount( $data['transaction']['amount'] ) );
			/* translators: %s: partner_payment_reference */
			$transaction_details .= PHP_EOL . sprintf( __( 'Payment Reference : %s', 'novalnet-gravity-forms' ), $data['transaction']['partner_payment_reference'] ) . PHP_EOL;
		}
		if ( ! empty( $data['instalment'] ) ) { // For instalment payments only.
			$tid         = ( isset( $data['event']['parent_tid'] ) ) ? rgar( $data['event'], 'parent_tid' ) : rgar( $data['transaction'], 'tid' );
			$instalments = GF_Novalnet::get_stored_instalment_data( $tid );
			if ( ! empty( $instalments['cycle_details'] ) && 1 < count( $instalments['cycle_details'] ) ) {
				$transaction_details .= PHP_EOL . PHP_EOL . __( 'Novalnet Instalment details', 'novalnet-gravity-forms' );
				foreach ( $instalments['cycle_details'] as $cycle => $instalment ) {
					/* translators: %1$s: cycle */
					$transaction_details .= PHP_EOL . sprintf( __( 'Instalment %1$s', 'novalnet-gravity-forms' ), $cycle );
					/* translators: %s: Instalment Date */
					$transaction_details .= PHP_EOL . sprintf( __( 'Date: %s', 'novalnet-gravity-forms' ), $instalment['date'] );
					if ( rgar( $instalment, 'tid' ) ) {
						/* translators: %s: Instalment TID */
						$transaction_details .= PHP_EOL . sprintf( __( 'TID: %s', 'novalnet-gravity-forms' ), $instalment['tid'] );
					}
					/* translators: %s: Instalment Amount */
					$transaction_details .= PHP_EOL . sprintf( __( 'Amount: %1$s', 'novalnet-gravity-forms' ), self::get_formatted_amount( $instalment['amount'], $instalments['currency'] ) ) . PHP_EOL;
				}
			}
		}
		return $transaction_details;
	}

	/**
	 * Form transaction details.
	 *
	 * @param array   $data The response data.
	 * @param boolean $is_error The error.
	 *
	 * @return string
	 */
	public static function form_transaction_data( $data, $is_error = false ) {
		$comments = '';
		if ( ! empty( $data ['transaction']['tid'] ) ) {
			/* translators: %s: TID */
			$comments = sprintf( __( 'Novalnet transaction ID: %s', 'novalnet-gravity-forms' ), $data ['transaction']['tid'] );
			if ( ! empty( $data ['transaction'] ['test_mode'] ) ) {
				$comments .= PHP_EOL . __( 'Test order', 'novalnet-gravity-forms' );
			}
		}
		if ( $is_error ) {
			$comments .= PHP_EOL . self::get_status_description( $data );
		}
		return $comments;
	}

	/**
	 * Form bank details command.
	 *
	 * @param array   $data The response data.
	 * @param boolean $reference Flag the transaction reference.
	 *
	 * @return string
	 */
	public static function form_bank_details( $data, $reference = true ) {
		$order_amount = $data ['transaction']['amount'];
		if ( ! empty( $data['instalment']['cycle_amount'] ) ) {
			$order_amount = $data ['instalment']['cycle_amount'];
		}
		$order_amount = self::get_formatted_amount( $order_amount, $data ['transaction']['currency'] );
		if ( in_array( $data['transaction']['status'], array( 'CONFIRMED', 'PENDING' ), true ) && ! empty( $data ['transaction']['due_date'] ) ) { // For Invoice payments with due date.
			/* translators: %1$s: amount, %2$s: due date */
			$transaction_details = PHP_EOL . PHP_EOL . sprintf( __( 'Please transfer the amount of %1$s to the following account on or before %2$s', 'novalnet-gravity-forms' ), $order_amount, $data ['transaction']['due_date'] ) . PHP_EOL . PHP_EOL;
			if ( ! empty( $data['instalment']['cycle_amount'] ) ) { // For instalment payment.
				/* translators: %1$s: instalment_amount, %2$s: due_date */
				$transaction_details = PHP_EOL . PHP_EOL . sprintf( __( 'Please transfer the instalment cycle amount of %1$s to the following account on or before %2$s', 'novalnet-gravity-forms' ), $order_amount, $data ['transaction']['due_date'] ) . PHP_EOL . PHP_EOL;
			}
		} else {
			/* translators: %s: amount*/
			$transaction_details = PHP_EOL . PHP_EOL . sprintf( __( 'Please transfer the amount of %1$s to the following account.', 'novalnet-gravity-forms' ), $order_amount ) . PHP_EOL . PHP_EOL;
			if ( ! empty( $data['instalment']['cycle_amount'] ) ) { // For instalment payment.
				/* translators: %1$s: instalment_amount */
				$transaction_details = PHP_EOL . PHP_EOL . sprintf( __( 'Please transfer the instalment cycle amount of %1$s to the following account.', 'novalnet-gravity-forms' ), $order_amount ) . PHP_EOL . PHP_EOL;
			}
		}

		foreach ( array(
			/* translators: %s: account_holder */
			'account_holder' => __( 'Account holder: %s', 'novalnet-gravity-forms' ),
			/* translators: %s: bank_name */
			'bank_name'      => __( 'Bank: %s', 'novalnet-gravity-forms' ),
			/* translators: %s: bank_place */
			'bank_place'     => __( 'Place: %s', 'novalnet-gravity-forms' ),
			/* translators: %s: iban */
			'iban'           => __( 'IBAN: %s', 'novalnet-gravity-forms' ),
			/* translators: %s: bic */
			'bic'            => __( 'BIC: %s', 'novalnet-gravity-forms' ),
		) as $key => $text ) {
			if ( ! empty( $data ['transaction']['bank_details'][ $key ] ) ) {
				$transaction_details .= sprintf( $text, $data ['transaction']['bank_details'][ $key ] ) . PHP_EOL;
			}
		}
		// Form payment references.
		if ( $reference ) {
			$transaction_details .= PHP_EOL . __( 'Please use any of the following payment references when transferring the amount. This is necessary to match it with your corresponding order', 'novalnet-gravity-forms' );
			/* translators: %s:  TID */
			$transaction_details .= PHP_EOL . sprintf( __( 'Payment Reference 1: TID %s', 'novalnet-gravity-forms' ), $data ['transaction']['tid'] );

			if ( ! empty( $data ['transaction']['invoice_ref'] ) ) {
				/* translators: %s: invoice_ref */
				$transaction_details .= PHP_EOL . sprintf( __( 'Payment Reference 2: %s', 'novalnet-gravity-forms' ), $data ['transaction']['invoice_ref'] );
			}
		}
		return $transaction_details;
	}

	/**
	 * Form nearest store details.
	 *
	 * @param array $data The response data.
	 *
	 * @return string
	 */
	public static function form_nearest_store_details( $data ) {
		$nearest_stores        = $data['transaction']['nearest_stores'];
		$nearest_store_details = '';
		if ( ! empty( $data['transaction']['due_date'] ) ) {
			/* translators: %s: due_date */
			$nearest_store_details .= PHP_EOL . sprintf( __( 'Slip expiry date : %s', 'novalnet-gravity-forms' ), $data['transaction']['due_date'] );
		}
		$nearest_store_details .= PHP_EOL . PHP_EOL . __( 'Store(s) near to you: ', 'novalnet-gravity-forms' ) . PHP_EOL . PHP_EOL;
		$countries              = array_flip( ( new GF_Field_Address() )->get_country_codes() );
		foreach ( $nearest_stores as $nearest_store ) {
			$nearest_store_details .= $nearest_store['store_name'] . '</br>';
			$nearest_store_details .= $nearest_store['street'] . '</br>';
			$nearest_store_details .= $nearest_store['city'] . '</br>';
			$nearest_store_details .= $nearest_store['zip'] . '</br>';
			$nearest_store_details .= ucwords( strtolower( $countries[ $nearest_store['country_code'] ] ) ) . '</br></br>';
			$nearest_store_details .= PHP_EOL;
		}
		return $nearest_store_details;
	}

	/**
	 * Insert transaction details into the database.
	 *
	 * @param array   $entry_id        Current entry_id.
	 * @param array   $server_response Payment response.
	 * @param array   $payment_feed    Current form payment feed.
	 * @param boolean $is_renewal      Flag to identify the renewal transaction.
	 */
	public static function insert_transaction_details( $entry_id, $server_response, $payment_feed, $is_renewal ) {
		$entry       = GFFormsModel::get_lead( $entry_id );
		$form        = GFAPI::get_form( $entry['form_id'] );
		$order_total = self::formatted_amount( GFCommon::get_order_total( $form, $entry ) );
		$insert_data = array(
			'entry_id'           => $entry_id,
			'status'             => $server_response['transaction']['status'],
			'transaction_amount' => $server_response['transaction']['amount'],
			'refunded_amount'    => 0,
			'tid'                => $server_response['transaction']['tid'],
		);
		gform_update_meta( $entry_id, '_nn_payment', strtolower( $server_response['transaction']['payment_type'] ) );
		if ( ! empty( $server_response['subscription'] ) && ! $is_renewal ) {
			$insert_data['recurring_amount']    = $server_response['subscription']['amount'];
			$insert_data['subscription_tid']    = isset( $server_response ['subscription']['tid'] ) ? $server_response ['subscription']['tid'] : '';
			$insert_data['subscription_length'] = $payment_feed['meta']['recurringTimes'];
			$insert_data['next_payment_date']   = isset( $server_response['subscription']['next_cycle_date'] ) ? $server_response['subscription']['next_cycle_date'] : '';
			$insert_data['trial_enabled']       = ( 1 == $payment_feed['meta']['trial_enabled'] && $payment_feed['meta']['trial_period'] > 0 ) ? 1 : 0; // phpcs:ignore WordPress.PHP.StrictComparisons
			$insert_data['is_renewal']          = 0;

			$subs_expiry_date = gform_get_meta( $entry_id, '_nn_subs_expiry_data', true );
			if ( ! empty( $subs_expiry_date ) ) {
				$insert_data['subs_expiry_date'] = $subs_expiry_date;
			}
		} elseif ( $is_renewal ) {
			$insert_data['is_renewal'] = 1;
		}
		if ( in_array( $server_response['transaction']['payment_type'], array( 'INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA' ), true ) ) { // For instalment payments.
			if ( $order_total > $server_response['transaction']['amount'] ) {
				$insert_data['transaction_amount'] = $order_total;
				$insert_data ['additional_info']   = apply_filters( 'novalnet_store_instalment_data', $server_response, $order_total );
			} else {
				$insert_data ['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $server_response, $server_response['transaction']['amount'] );
			}
		}
		if ( in_array( $server_response['transaction']['payment_type'], array( 'PREPAYMENT', 'INVOICE', 'CASHPAYMENT', 'GUARANTEED_INVOICE', 'INSTALMENT_INVOICE' ), true ) ) { // For Invoice payments.
			if ( ! empty( $insert_data ['additional_info'] ) ) {
				$insert_data ['additional_info'] = self::serialize_novalnet_data( self::unserialize_novalnet_data( $insert_data ['additional_info'] ) + array( 'bank_details' => $server_response['transaction']['bank_details'] ) );
			} elseif ( ! empty( $server_response['transaction']['bank_details'] ) ) {
				$insert_data ['additional_info'] = self::serialize_novalnet_data( array( 'bank_details' => $server_response['transaction']['bank_details'] ) );
			} elseif ( ! empty( $server_response['transaction']['nearest_stores'] ) ) {
				$insert_data ['additional_info'] = self::serialize_novalnet_data( array( 'nearest_stores' => $server_response['transaction']['nearest_stores'] ) );
			}
		}
		$insert_data['paid_amount'] = ( rgar( $insert_data, 'status' ) === 'CONFIRMED' ) ? $insert_data ['transaction_amount'] : 0;
		self::insert_db( $insert_data );
	}

	/**
	 * Handling db insert operation.
	 *
	 * @param array  $insert_value The values to be insert in the given table.
	 * @param string $table_name   The table name.
	 */
	public static function insert_db( $insert_value, $table_name = 'novalnet_transaction_details' ) {
		global $wpdb;
		// Perform query action.
		self::handle_query_error( $wpdb->insert( "{$wpdb->prefix}$table_name", $insert_value ) ); // db call ok.
	}

	/**
	 * Handling db update operation.
	 *
	 * @param array  $update_value The update values.
	 * @param array  $where_array  The where condition query.
	 * @param string $table_name   The table name.
	 */
	public static function db_update( $update_value, $where_array, $table_name = 'novalnet_transaction_details' ) {
		global $wpdb;
		// Perform query action.
		self::handle_query_error( $wpdb->update( "{$wpdb->prefix}$table_name", $update_value, $where_array ) ); // WPCS: cache ok, DB call ok.
	}

	/**
	 * Retrieves messages from server response.
	 *
	 * @param array $data The response data.
	 *
	 * @return string
	 */
	public static function get_status_description( $data ) {
		if ( isset( $data ['status_text'] ) ) {
			return $data ['status_text'];
		} elseif ( isset( $data ['status_desc'] ) ) {
			return $data ['status_desc'];
		} elseif ( isset( $data ['status_message'] ) ) {
			return $data ['status_message'];
		} elseif ( isset( $data ['subscription_pause'] ['status_message'] ) ) {
			return $data ['subscription_pause'] ['status_message'];
		} elseif ( isset( $data ['subscription_update'] ['status_message'] ) ) {
			return $data ['subscription_update'] ['status_message'];
		} elseif ( isset( $data ['result']['status_text'] ) ) {
			return $data ['result']['status_text'];
		}
		return __( 'Payment was not successful. An error occurred', 'novalnet-gravity-forms' );
	}

	/**
	 * Format the text.
	 *
	 * @param string $text The test value.
	 *
	 * @return int|boolean
	 */
	public static function format_text( $text ) {
		return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Formating the date as per the
	 * shop structure.
	 *
	 * @param date $date The date value.
	 *
	 * @return string
	 */
	public static function formatted_date( $date = '' ) {
		return date_i18n( get_option( 'date_format' ), strtotime( '' === $date ? gmdate( 'Y-m-d H:i:s' ) : $date ) );
	}

	/**
	 * Returns original post_id based on TID.
	 *
	 * @param int $tid The tid value.
	 *
	 * @return array
	 */
	public static function get_original_post_id( $tid ) {

		global $wpdb;

		// Get post id based on TID.
		$post_id = self::handle_query_error( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} where post_excerpt LIKE %s", "%$tid%" ), ARRAY_A ) ); // db call ok; no-cache ok.
		return $post_id;

	}

	/**
	 * Returns the transaction details from the database.
	 *
	 * @param string $tid     The TID value.
	 *
	 * @return array
	 */
	public static function get_transaction_details( $tid ) {
		global $wpdb;
		return self::handle_query_error( $wpdb->get_row( $wpdb->prepare( "SELECT entry_id, transaction_amount, refunded_amount, paid_amount, tid, additional_info, status FROM {$wpdb->prefix}novalnet_transaction_details WHERE tid=%s", $tid ), ARRAY_A ) );// db call ok; no-cache ok.
	}

	/**
	 * Returns the details of subscription.
	 *
	 * @param int $entry_id  Entry ID.
	 *
	 * @return array
	 */
	public static function get_subscription_details( $entry_id ) {
		global $wpdb;
		return self::handle_query_error( $wpdb->get_row( $wpdb->prepare( "SELECT subscription_tid, recurring_amount, subscription_length, trial_enabled, is_renewal, next_payment_date FROM {$wpdb->prefix}novalnet_transaction_details WHERE entry_id=%s AND is_renewal=0", $entry_id ), ARRAY_A ) );// db call ok; no-cache ok.
	}

	/**
	 * Get transaction count
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return int
	 */
	public static function get_transaction_count( $entry_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT count(id) FROM {$wpdb->prefix}gf_addon_payment_transaction WHERE lead_id=%d", $entry_id ) );// db call ok; no-cache ok.
	}

	/**
	 * Get the payment name based on the payment type.
	 *
	 * @param string $payment_type Transaction payment type.
	 *
	 * @return array
	 */
	public static function get_payment_name( $payment_type ) {
		$payments = array(
			'CREDITCARD'                             => array(
				'payment_name'   => __( 'Credit/Debit Card', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_cc',
			),
			'ONLINE_TRANSFER'                        => array(
				'payment_name'   => __( 'Sofort', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_instantbank',
			),
			'ONLINE_BANK_TRANSFER'                   => array(
				'payment_name'   => __( 'Online bank transfer', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_online_bank_transfer',
			),
			'PAYPAL'                                 => array(
				'payment_name'   => __( 'PayPal', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_paypal',
			),
			'IDEAL'                                  => array(
				'payment_name'   => __( 'iDEAL', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_ideal',
			),
			'ALIPAY'                                 => array(
				'payment_name'   => __( 'Alipay', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_alipay',
			),
			'WECHATPAY'                              => array(
				'payment_name'   => __( 'WeChat Pay', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_wechatpay',
			),
			'TRUSTLY'                                => array(
				'payment_name'   => __( 'Trustly', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_trustly',
			),
			'GIROPAY'                                => array(
				'payment_name'   => __( 'giropay', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_giropay',
			),
			'EPS'                                    => array(
				'payment_name'   => __( 'eps', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_eps',
			),
			'PRZELEWY24'                             => array(
				'payment_name'   => __( 'Przelewy24', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_przelewy24',
			),
			'CASHPAYMENT'                            => array(
				'payment_name'   => __( 'Barzahlen/viacash', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_barzahlen',
			),
			'INVOICE'                                => array(
				'payment_name'   => __( 'Invoice', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_invoice',
			),
			'GUARANTEED_INVOICE'                     => array(
				'admin_payment_name' => __( 'Invoice with payment guarantee', 'novalnet-gravity-forms' ),
				'payment_name'       => __( 'Invoice', 'novalnet-gravity-forms' ),
				'payment_method'     => 'novalnet_guaranteed_invoice',
			),
			'DIRECT_DEBIT_SEPA'                      => array(
				'payment_name'   => __( 'Direct Debit SEPA', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_direct_debit_sepa',
			),
			'GUARANTEED_DIRECT_DEBIT_SEPA'           => array(
				'admin_payment_name' => __( 'Direct debit SEPA with payment guarantee', 'novalnet-gravity-forms' ),
				'payment_name'       => __( 'Direct Debit SEPA', 'novalnet-gravity-forms' ),
				'payment_method'     => 'novalnet_guaranteed_sepa',
			),
			'PREPAYMENT'                             => array(
				'payment_name'   => __( 'Prepayment', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_prepayment',
			),
			'BANCONTACT'                             => array(
				'payment_name'   => __( 'Bancontact', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_bancontact',
			),
			'MULTIBANCO'                             => array(
				'payment_name'   => __( 'Multibanco', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_multibanco',
			),
			'POSTFINANCE_CARD'                       => array(
				'payment_name'   => __( 'PostFinance Card', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_postfinance_card',
			),
			'POSTFINANCE'                            => array(
				'payment_name'   => __( 'PostFinance E-Finance', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_postfinance',
			),
			'INSTALMENT_INVOICE'                     => array(
				'payment_name'   => __( 'Instalment by Invoice', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_instalment_invoice',
			),
			'INSTALMENT_DIRECT_DEBIT_SEPA'           => array(
				'payment_name'   => __( 'Instalment by Direct Debit SEPA', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_instalment_sepa',
			),
			'INSTALMENT_INVOICE_WITH_RATE'           => array(
				'payment_name'   => __( 'Instalment by Invoice Rate', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_instalment_invoice_rate',
			),
			'INSTALMENT_DIRECT_DEBIT_SEPA_WITH_RATE' => array(
				'payment_name'   => __( 'Instalment by Direct Debit SEPA Rate', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_instalment_sepa_rate',
			),
			'GOOGLEPAY'                              => array(
				'payment_name'   => __( 'Google Pay', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_googlepay',
			),
			'APPLEPAY'                               => array(
				'payment_name'   => __( 'Apple Pay', 'novalnet-gravity-forms' ),
				'payment_method' => 'novalnet_applepay',
			),
		);
		return $payments[ $payment_type ];
	}

	/**
	 * Checks for the given string in given text.
	 *
	 * @param string $string The string value.
	 * @param string $data   The data value.
	 *
	 * @return boolean
	 */
	public static function check_string( $string, $data = 'novalnet' ) {
		return ( false !== strpos( $string, $data ) );
	}


	/**
	 * Perform the serialization of the data.
	 *
	 * @param array $data The to be serialized.
	 */
	public static function serialize_novalnet_data( $data ) {
		$result = '';
		if ( ! empty( $data ) ) {
			$result = wp_json_encode( $data );
		}
		return $result;
	}

	/**
	 * Perform unserialize data.
	 *
	 * @param array $data The to be unserialized.
	 */
	public static function unserialize_novalnet_data( $data ) {
		$result = array();
		if ( empty( $data ) ) {
			return $result;
		}

		if ( is_serialized( $data ) ) {
			return maybe_unserialize( $data );
		}
		$result = json_decode( $data, true, 512, JSON_BIGINT_AS_STRING );
		if ( json_last_error() === 0 ) {
			return $result;
		}
	}

	/**
	 * Check for server status
	 *
	 * @param array $data  The response array.
	 *
	 * @return boolean
	 */
	public static function is_success_status( $data ) {
		return ( ( ! empty( $data['result']['status'] ) && 'SUCCESS' === $data['result']['status'] )
		|| ( ! empty( $data['status'] ) && ( 'SUCCESS' === $data['status'] || 100 === (int) $data['status'] ) ) );
	}
}
