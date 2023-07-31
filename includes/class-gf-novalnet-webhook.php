<?php
/**
 * Novalnet API callback.
 *
 * @class    NN_Callback_Api
 * @version  2.0.1
 * @package  novalnet-gravity-forms
 * @category Class
 * @author   Novalnet AG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Novalnet Callback Api Class.
 *
 * @class   Novalnet
 * @version 2.0.0
 */
class GF_Novalnet_Webhook {

	/**
	 * Allowed host from Novalnet.
	 *
	 * @var string
	 */
	protected $novalnet_host_name = 'pay-nn.de';

	/**
	 * Mandatory Parameters.
	 *
	 * @var array
	 */
	protected $mandatory = array(
		'event'       => array(
			'type',
			'checksum',
			'tid',
		),
		'merchant'    => array(
			'vendor',
			'project',
		),
		'result'      => array(
			'status',
		),
		'transaction' => array(
			'tid',
			'payment_type',
			'status',
		),
	);

	/**
	 * The Return response to Novalnet.
	 *
	 * @var array
	 */
	protected $response;

	/**
	 * Request parameters.
	 *
	 * @var array
	 */
	protected $event_data = array();

	/**
	 * Recived Event type.
	 *
	 * @var string
	 */
	protected $event_type;

	/**
	 * Recived Event TID.
	 *
	 * @var int
	 */
	protected $event_tid;

	/**
	 * Recived Event parent TID.
	 *
	 * @var int
	 */
	protected $parent_tid;

	/**
	 * Your payment access key value
	 *
	 * @var string
	 */
	protected $payment_access_key;

	/**
	 * The details need to be update in Novalnet table.
	 *
	 * @var array
	 */
	protected $update_data = array();

	/**
	 * Order reference values.
	 *
	 * @var array
	 */
	protected $order_reference = array();

	/**
	 * Novalnet subscription details.
	 *
	 * @var array
	 */
	protected $subscription_reference = array();

	/**
	 * Test mode value.
	 *
	 * @var boolean
	 */
	protected $test_mode;

	/**
	 * Gravity Novalnet addon object
	 *
	 * @var object
	 */
	private $gf_novalnet;

	/**
	 * Callback api process.
	 */
	public function process() {
		$this->gf_novalnet = gf_novalnet();

		$this->settings = $this->gf_novalnet->get_plugin_settings();

		// Authenticate request host.
		$this->authenticate_event_data();
		// Set Event data.
		$this->event_type      = $this->event_data ['event'] ['type'];
		$this->event_tid       = $this->event_data ['event'] ['tid'];
		$this->parent_tid      = ( ! empty( $this->event_data ['event'] ['parent_tid'] ) ) ? $this->event_data ['event'] ['parent_tid'] : $this->event_tid;
		$this->order_reference = $this->get_order_reference();

		if ( empty( $this->order_reference ['entry_id'] ) ) {
			$this->display_message( array( 'message' => 'Order reference not found in the shop' ) );
		}

		$this->gf_entry = GFFormsModel::get_lead( $this->order_reference ['entry_id'] );
		$this->gf_form  = GFAPI::get_form( $this->gf_entry['form_id'] );

		if ( $this->gf_novalnet->has_subscription( $this->gf_entry ) ) {
			$this->subscription_reference = GF_Novalnet_Helper::get_subscription_details( $this->gf_entry['id'] );
		}

		if ( 'RENEWAL' === $this->event_type && ! GF_Novalnet_Helper::is_success_status( $this->event_data ) && ! empty( $this->event_data['result']['status_text'] ) && ! empty( $this->gf_entry['id'] ) ) {
			gform_update_meta( $this->gf_entry['id'], '_nn_subs_cancelled_reason', $this->event_data['result']['status_text'] );
		}

		if ( GF_Novalnet_Helper::is_success_status( $this->event_data ) ) {
			switch ( $this->event_type ) {
				case 'PAYMENT':
					$this->display_message( array( 'message' => 'Novalnet callback received' ) );
					break;
				case 'TRANSACTION_CAPTURE':
					$this->handle_transaction_capture();
					break;
				case 'TRANSACTION_CANCEL':
					$this->handle_transaction_cancel();
					break;
				case 'TRANSACTION_REFUND':
					$this->handle_transaction_refund();
					break;
				case 'TRANSACTION_UPDATE':
					$this->handle_transaction_update();
					break;
				case 'CREDIT':
					$this->handle_credit();
					break;
				case 'CHARGEBACK':
					$this->handle_chargeback();
					break;
				case 'INSTALMENT':
					$this->handle_instalment();
					break;
				case 'INSTALMENT_CANCEL':
					$this->handle_instalment_cancel();
					break;
				case 'RENEWAL':
					$this->handle_renewal();
					break;
				case 'SUBSCRIPTION_UPDATE':
					$this->handle_subscription_update();
					break;
				case 'SUBSCRIPTION_CANCEL':
					$this->handle_subscription_cancel();
					break;
				case 'SUBSCRIPTION_SUSPEND':
					$this->handle_subscription_suspend();
					break;
				case 'SUBSCRIPTION_REACTIVATE':
					$this->handle_subscription_reactivate();
					break;
				case 'PAYMENT_REMINDER_1':
				case 'PAYMENT_REMINDER_2':
					$this->handle_payment_reminder();
					break;
				case 'SUBMISSION_TO_COLLECTION_AGENCY':
					$this->handle_collection_submission();
					break;
				default:
					$this->display_message( array( 'message' => "The webhook notification has been received for the unhandled EVENT type($this->event_type)" ) );
			}
		} else {
			$this->display_message( array( 'message' => 'Novalnet callback received' ) );
		}

		if ( isset( $this->response['message'] ) ) {
			$this->send_notification_mail( $this->response['message'] );
		}
		$this->display_message( $this->response );
	}

	/**
	 * Handle transaction capture
	 */
	public function handle_transaction_capture() {
		$entry_id                    = $this->gf_entry['id'];
		$this->update_data['status'] = $this->event_data['transaction']['status'];
		/* translators: %1$s: Callback Date */
		$this->response['message'] = GF_Novalnet_Helper::format_text( sprintf( __( 'The transaction has been confirmed on %s.', 'novalnet-gravity-forms' ), GF_Novalnet_Helper::formatted_date() ) );
		if ( in_array( $this->event_data ['transaction']['payment_type'], array( 'INSTALMENT_DIRECT_DEBIT_SEPA', 'INSTALMENT_INVOICE' ), true ) ) {
			if ( ! empty( $this->order_reference ['additional_info'] ) ) {
				$this->order_reference ['additional_info'] = GF_Novalnet_Helper::serialize_novalnet_data( array_merge( GF_Novalnet_Helper::unserialize_novalnet_data( $this->order_reference ['additional_info'] ), GF_Novalnet_Helper::unserialize_novalnet_data( apply_filters( 'novalnet_store_instalment_data', $this->event_data, $this->order_reference ['transaction_amount'] ) ) ) );
			} else {
				$this->order_reference ['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $this->event_data, $this->order_reference ['transaction_amount'] );
			}

			// update additional info before payment comments.
			GF_Novalnet_Helper::db_update(
				array(
					'additional_info' => $this->order_reference['additional_info'],
				),
				array(
					'entry_id' => $entry_id,
					'tid'      => $this->parent_tid,
				)
			);
		}
		if ( in_array( $this->event_data ['transaction']['payment_type'], array( 'INVOICE', 'INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA', 'GUARANTEED_INVOICE' ), true ) ) {
			$additional_info = ( ! empty( $this->order_reference ['additional_info'] ) ) ? GF_Novalnet_Helper::unserialize_novalnet_data( $this->order_reference ['additional_info'] ) : array();
			if ( 'INSTALMENT_DIRECT_DEBIT_SEPA' !== (string) $this->event_data ['transaction']['payment_type'] && empty( $this->event_data ['transaction']['bank_details'] ) && isset( $additional_info['bank_details'] ) ) {
				$this->event_data ['transaction']['bank_details'] = $additional_info['bank_details'];
			}
			$transaction_comments = GF_Novalnet_Helper::form_transaction_details( $this->event_data );

			gform_update_meta( $entry_id, '_novalnet_transaction_comments', $transaction_comments );
		}
		if ( 'INVOICE' !== (string) $this->event_data ['transaction']['payment_type'] ) {
			$this->update_data ['paid_amount'] = $this->event_data ['transaction']['amount'];
		}

		if ( $this->gf_novalnet->has_subscription( $this->gf_entry ) ) {
			$action = array(
				'subscription_id' => $this->parent_tid,
				'amount'          => GF_Novalnet_Helper::get_formatted_amount( $this->event_data ['transaction']['amount'] ),
				'transaction_id'  => $this->event_data['transaction']['tid'],
			);
			$this->gf_novalnet->add_subscription_payment( $this->gf_entry, $action );
		} else {
			// Required action data.
			$action = array(
				'type'           => 'complete_payment',
				'payment_method' => GF_Novalnet_Helper::get_payment_name( $this->event_data ['transaction']['payment_type'] ),
				'amount'         => GF_Novalnet_Helper::get_formatted_amount( $this->event_data ['transaction']['amount'] ),
				'transaction_id' => $this->event_data['transaction']['tid'],
			);
			$this->gf_novalnet->complete_payment( $this->gf_entry, $action );
		}

		// Update gateway status.
		GF_Novalnet_Helper::db_update(
			$this->update_data,
			array(
				'entry_id' => $entry_id,
				'tid'      => $this->parent_tid,
			)
		);
	}

	/**
	 * Handle transaction capture
	 */
	public function handle_transaction_cancel() {
		/* translators: %1$s: date, %2$s: time */
		$this->response['message'] = GF_Novalnet_Helper::format_text( sprintf( __( 'The transaction has been canceled on %1$s %2$s.', 'novalnet-gravity-forms' ), GF_Novalnet_Helper::formatted_date(), gmdate( 'H:i:s' ) ) );
		$action['note']            = $this->response['message'];
		if ( $this->gf_novalnet->has_subscription( $this->gf_entry ) ) {
			$this->gf_novalnet->fail_subscription_payment( $this->gf_entry, $action );
		} else {
			$this->gf_novalnet->void_authorization( $this->gf_entry, $action );
		}
	}

	/**
	 * Handle transaction refund
	 */
	public function handle_transaction_refund() {
		if ( ! empty( $this->event_data ['transaction'] ['refund'] ['amount'] ) ) {
			$action = array(
				'amount'           => GF_Novalnet_Helper::get_formatted_amount( $this->event_data ['transaction'] ['refund'] ['amount'] ),
				'amount_formatted' => GF_Novalnet_Helper::get_formatted_amount( $this->event_data ['transaction'] ['refund'] ['amount'], $this->event_data['transaction']['currency'] ),
				'transaction_id'   => $this->parent_tid,
			);
			/* translators: %1$s: tid, %2$s: amount */
			$this->response['message'] = sprintf( __( 'Refund has been initiated for the TID:%1$s with the amount %2$s', 'novalnet-gravity-forms' ), $this->parent_tid, $action['amount_formatted'] ) . PHP_EOL;

			if ( ! empty( $this->event_data['transaction']['refund']['tid'] ) ) {
				/* translators: %s: response tid */
				$this->response['message'] .= sprintf( __( ' New TID:%s for the refunded amount', 'novalnet-gravity-forms' ), $this->event_data ['transaction']['refund']['tid'] );
				$action['transaction_id']   = $this->event_data ['transaction']['refund']['tid'];
			}
			$action['note'] = $this->response['message'];

			$this->update_data = array(
				'status'          => $this->event_data ['transaction']['status'],
				'refunded_amount' => $this->order_reference ['refunded_amount'] + $this->event_data ['transaction'] ['refund'] ['amount'],
			);
			if ( in_array( $this->event_data ['transaction'] ['payment_type'], array( 'INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA' ), true ) ) {
				$additional_info = ( ! empty( $this->order_reference ['additional_info'] ) ) ? GF_Novalnet_Helper::unserialize_novalnet_data( $this->order_reference ['additional_info'] ) : array();
				if ( isset( $additional_info['instalment_data'] ) ) {
					$instalment_data = $additional_info['instalment_data'];
					foreach ( $instalment_data['cycle_details'] as $cycle => $instalment ) {
						if ( ! empty( $instalment ['tid'] ) && (int) $instalment ['tid'] === (int) $this->event_data ['transaction']['tid'] ) {
							if ( strpos( $instalments [ $key ] ['amount'], '.' ) ) {
								$instalments [ $key ] ['amount'] *= 100;
							}
							$instalment_data['cycle_details'][ $cycle ]['refund_amount'] = ( ! empty( $instalment_data['cycle_details'][ $cycle ]['refund_amount'] ) ) ? $instalment_data['cycle_details'][ $cycle ]['refund_amount'] + $this->event_data ['transaction'] ['refund'] ['amount'] : $this->event_data ['transaction'] ['refund'] ['amount'];
						}
					}
					$additional_info['instalment_data']   = $instalment_data;
					$this->update_data['additional_info'] = GF_Novalnet_Helper::serialize_novalnet_data( $additional_info );
				}
			}
			GF_Novalnet_Helper::db_update(
				$this->update_data,
				array(
					'entry_id' => $this->gf_entry['id'],
					'tid'      => $this->parent_tid,
				)
			);
			if ( $this->update_data['refunded_amount'] < $this->order_reference ['transaction_amount'] ) {
				$action['payment_status'] = $this->gf_entry['payment_status'];
			}
			$this->gf_novalnet->refund_payment( $this->gf_entry, $action );
		}
	}

	/**
	 * Handle chargeback
	 */
	public function handle_chargeback() {
		if ( ( rgar( $this->order_reference, 'status', 'CONFIRMED' ) || rgar( $this->order_reference, 'status', '100' ) ) && ! empty( $this->event_data ['transaction'] ['amount'] ) ) {
			/* translators: %1$s: parent_tid, %2$s: amount, %3$s: date, %4$s: tid  */
			$this->response['message'] = sprintf( __( 'Chargeback executed successfully for the TID: %1$s amount: %2$s on %3$s. The subsequent TID: %4$s.', 'novalnet-gravity-forms' ), $this->parent_tid, GF_Novalnet_Helper::get_formatted_amount( $this->event_data ['transaction'] ['amount'], $this->event_data ['transaction'] ['currency'] ), GF_Novalnet_Helper::formatted_date(), $this->event_tid );
			$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'] );
		}
	}

	/**
	 * Handle credit
	 */
	public function handle_credit() {
		$amount_formatted = '';
		if ( ! empty( $this->event_data ['transaction'] ['amount'] ) ) {
			$amount_formatted = GF_Novalnet_Helper::get_formatted_amount( $this->event_data ['transaction'] ['amount'], $this->event_data['transaction']['currency'] );
		}
		if ( 'ONLINE_TRANSFER_CREDIT' === $this->event_data['transaction']['payment_type'] ) {
			/* translators: %1$s: tid, %2$s: amount, %3$s: date, %4$s: parent_tid */
			$this->response['message'] = GF_Novalnet_Helper::format_text( sprintf( __( 'Credit has been successfully received for the TID: %1$s with amount %2$s on %3$s. Please refer PAID order details in our Novalnet Admin Portal for the TID: %4$s', 'novalnet-gravity-forms' ), $this->parent_tid, $amount_formatted, GF_Novalnet_Helper::formatted_date(), $this->event_data['transaction']['tid'] ) );
		} else {
			/* translators: %s: post type */
			$this->response['message'] = sprintf( __( 'Credit has been successfully received for the TID: %1$s with amount %2$s on %3$s. Please refer PAID order details in our Novalnet Admin Portal for the TID: %4$s', 'novalnet-gravity-forms' ), $this->parent_tid, $amount_formatted, GF_Novalnet_Helper::formatted_date(), $this->event_data['transaction']['tid'] );
			if ( in_array( $this->event_data['transaction']['payment_type'], array( 'INVOICE_CREDIT', 'CASHPAYMENT_CREDIT', 'MULTIBANCO_CREDIT' ), true ) ) {
				if ( (int) $this->order_reference ['paid_amount'] < (int) $this->order_reference ['transaction_amount'] ) {
					// Calculate total amount.
					$paid_amount = $this->order_reference ['paid_amount'] + $this->event_data['transaction']['amount'];
					// Calculate including refunded amount.
					$amount_to_be_paid = $this->order_reference['transaction_amount'] - $this->order_reference ['refunded_amount'];
					// Update transaction details.
					GF_Novalnet_Helper::db_update(
						array(
							'paid_amount' => $paid_amount,
							'status'      => $this->event_data['transaction']['status'],
						),
						array(
							'entry_id' => $this->gf_entry['id'],
							'tid'      => $this->parent_tid,
						)
					);
					if ( ( (int) $paid_amount >= (int) $amount_to_be_paid ) ) {
						if ( 'Paid' !== (string) $this->gf_entry['payment_status'] && ! $this->gf_novalnet->has_subscription( $this->gf_entry ) ) {
							$payment_type = strtoupper( gform_get_meta( $this->gf_entry['id'], '_nn_payment', true ) );
							$payment_type = ( ! empty( $payment_type ) ) ? $payment_type : $this->event_data['transaction']['payment_type'];
							$payment_name = GF_Novalnet_Helper::get_payment_name( $payment_type );
							$action       = array(
								'type'           => 'complete_payment',
								'transaction_id' => $this->parent_tid,
								'amount'         => GF_Novalnet_Helper::get_formatted_amount( $paid_amount ),
								'payment_method' => isset( $payment_name['payment_name'] ) ? $payment_name['payment_name'] : '',
							);
							$this->gf_novalnet->complete_payment( $this->gf_entry, $action );
						} elseif ( 'Active' !== (string) $this->gf_entry['payment_status'] && $this->gf_novalnet->has_subscription( $this->gf_entry ) ) {
							$action = array(
								'type'            => 'add_subscription_payment',
								'transaction_id'  => $this->parent_tid,
								'amount'          => GF_Novalnet_Helper::get_formatted_amount( $paid_amount ),
								'subscription_id' => $this->gf_entry['transaction_id'],
							);
							$this->gf_novalnet->add_subscription_payment( $this->gf_entry, $action );
						}
					}
				}
			}
		}
		$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'], 'success' );
	}

	/**
	 * Handle transaction update
	 */
	public function handle_transaction_update() {
		$this->update_data ['status'] = $this->event_data ['transaction']['status'];
		if ( in_array( $this->event_data['transaction']['status'], array( 'PENDING', 'ON_HOLD', 'CONFIRMED', 'DEACTIVATED' ), true ) ) {
			$action = array();
			if ( 'DEACTIVATED' === $this->event_data['transaction']['status'] ) {
				/* translators: %s: Date */
				$this->response['message'] = sprintf( __( 'The transaction has been canceled on %1$s %2$s.', 'novalnet-gravity-forms' ), GF_Novalnet_Helper::formatted_date(), gmdate( 'H:i:s' ) );
				$transaction_comments      = GF_Novalnet_Helper::form_transaction_details( $this->event_data );
				gform_update_meta( $this->gf_entry['id'], '_novalnet_transaction_comments', $transaction_comments );
				$action['note'] = $this->response['message'];
				$this->gf_novalnet->fail_payment( $this->gf_entry, $action );
			} else {
				if ( in_array( $this->order_reference['status'], array( 'PENDING', 'ON_HOLD' ), true ) ) {
					$amount = $this->event_data['transaction']['amount'];
					if ( ! empty( $this->event_data['instalment']['cycle_amount'] ) ) {
						$amount = $this->event_data['instalment']['cycle_amount'];
					}
					$action['transaction_id'] = $this->parent_tid;
					$action['amount']         = GF_Novalnet_Helper::get_formatted_amount( $amount );

					$this->update_data ['status'] = $this->event_data ['transaction']['status'];

					if ( 'ON_HOLD' === $this->event_data['transaction']['status'] ) {
						$additional_info = ( ! empty( $this->order_reference ['additional_info'] ) ) ? GF_Novalnet_Helper::unserialize_novalnet_data( $this->order_reference ['additional_info'] ) : array();
						if ( empty( $this->event_data ['transaction']['bank_details'] ) && isset( $additional_info['bank_details'] ) ) {
							$this->event_data ['transaction']['bank_details'] = $additional_info['bank_details'];
						}
						if ( $this->gf_novalnet->has_subscription( $this->gf_entry ) ) {
							$this->gf_novalnet->add_pending_payment( $this->gf_entry, $action );
						} else {
							$this->gf_novalnet->complete_authorization( $this->gf_entry, $action );
						}
					} elseif ( 'CONFIRMED' === $this->event_data['transaction']['status'] ) {
						$additional_info = ( ! empty( $this->order_reference ['additional_info'] ) ) ? GF_Novalnet_Helper::unserialize_novalnet_data( $this->order_reference ['additional_info'] ) : array();
						if ( empty( $this->event_data ['transaction']['bank_details'] ) && isset( $additional_info['bank_details'] ) ) {
							$this->event_data ['transaction']['bank_details'] = $additional_info['bank_details'];
						}
						if ( in_array( $this->event_data ['transaction']['payment_type'], array( 'INSTALMENT_INVOICE', 'INSTALMENT_DIRECT_DEBIT_SEPA' ), true ) ) {
							if ( ! empty( $this->order_reference ['additional_info'] ) ) {
								$this->order_reference ['additional_info'] = GF_Novalnet_Helper::serialize_novalnet_data( array_merge( GF_Novalnet_Helper::unserialize_novalnet_data( $this->order_reference ['additional_info'] ), GF_Novalnet_Helper::unserialize_novalnet_data( apply_filters( 'novalnet_store_instalment_data', $this->event_data, $this->order_reference ['transaction_amount'] ) ) ) );
							} else {
								$this->order_reference ['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $this->event_data, $this->order_reference ['transaction_amount'] );
							}
							GF_Novalnet_Helper::db_update(
								array(
									'additional_info' => $this->order_reference['additional_info'],
								),
								array(
									'entry_id' => $this->gf_entry['id'],
								)
							);
						}
						if ( $this->gf_novalnet->has_subscription( $this->gf_entry ) ) {
							$action = array(
								'subscription_id' => $this->gf_entry['transaction_id'],
								'amount'          => GF_Novalnet_Helper::get_formatted_amount( $this->event_data ['transaction']['amount'] ),
								'transaction_id'  => $this->event_data['transaction']['tid'],
							);
							$this->gf_novalnet->add_subscription_payment( $this->gf_entry, $action );
						} else {
							$this->gf_novalnet->complete_payment( $this->gf_entry, $action );
						}

						$this->update_data['paid_amount'] = (int) $this->order_reference['paid_amount'] + (int) $amount;
					}

					// Reform the transaction comments.
					$additional_info = ( ! empty( $this->order_reference ['additional_info'] ) ) ? GF_Novalnet_Helper::unserialize_novalnet_data( $this->order_reference ['additional_info'] ) : array();

					if ( empty( $this->event_data ['transaction']['bank_details'] ) && in_array( $this->event_data ['transaction']['payment_type'], array( 'INVOICE', 'PREPAYMENT', 'GUARANTEED_INVOICE', 'INSTALMENT_INVOICE' ), true ) ) {
						if ( isset( $additional_info['bank_details'] ) ) {
							$this->event_data ['transaction']['bank_details'] = $additional_info['bank_details'];
						}
					}
					if ( empty( $this->event_data ['transaction']['nearest_stores'] ) && 'CASHPAYMENT' === $this->event_data ['transaction']['payment_type'] && $additional_info['nearest_stores'] ) {
						$this->event_data ['transaction']['nearest_stores'] = $additional_info['nearest_stores'];
					}

					$transaction_comments = GF_Novalnet_Helper::form_transaction_details( $this->event_data );

					if ( ! empty( $this->event_data['transaction']['due_date'] ) ) {
						/* translators: %1$s: tid, %2$s: amount, %3$s: due date */
						$this->response['message'] = GF_Novalnet_Helper::format_text( sprintf( __( 'Transaction updated successfully for the TID: %1$s with amount %2$s and due date %3$s.', 'novalnet-gravity-forms' ), $this->event_tid, GF_Novalnet_Helper::get_formatted_amount( $amount, $this->event_data['transaction']['currency'] ), GF_Novalnet_Helper::formatted_date( $this->event_data['transaction']['due_date'] ) ) );
					} else {
						/* translators: %1$s: tid, %2$s: amount*/
						$this->response['message'] = GF_Novalnet_Helper::format_text( sprintf( __( 'Transaction updated successfully for the TID: %1$s with amount %2$s.', 'novalnet-gravity-forms' ), $this->event_tid, GF_Novalnet_Helper::get_formatted_amount( $amount, $this->event_data['transaction']['currency'] ) ) );
					}

					if ( ! empty( $this->response['message'] ) ) {
						$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'] );
					}
				}
			}
			if ( ! empty( $transaction_comments ) ) {
				// Update order comments.
				gform_update_meta( $this->gf_entry['id'], '_novalnet_transaction_comments', $transaction_comments );
			}
		}
		GF_Novalnet_Helper::db_update(
			$this->update_data,
			array(
				'entry_id' => $this->gf_entry['id'],
				'tid'      => $this->parent_tid,
			)
		);
	}

	/**
	 * Handle instalment
	 */
	public function handle_instalment() {
		if ( 'CONFIRMED' === $this->event_data['transaction']['status'] && ! empty( $this->event_data['instalment']['cycles_executed'] ) ) {
			/* translators: %1$s: parent_tid, %2$s: amount, %3$s: date, %4$s: tid */
			$this->response ['message'] = sprintf( __( 'A new instalment has been received for the Transaction ID:%1$s with amount %2$s. The new instalment transaction ID is: %3$s', 'novalnet-gravity-forms' ), $this->parent_tid, GF_Novalnet_Helper::get_formatted_amount( $this->event_data['instalment']['cycle_amount'], $this->event_data['transaction']['currency'] ), $this->event_tid );

			// Update Instalment Data.
			$this->order_reference ['additional_info'] = apply_filters( 'novalnet_store_instalment_data_webhook', $this->event_data );
			$this->update_data ['additional_info']     = $this->order_reference ['additional_info'];
			if ( 'INSTALMENT_INVOICE' === $this->event_data['transaction']['payment_type'] && empty( $this->event_data ['transaction']['bank_details'] ) ) {
				$additional_info = ( ! empty( $this->order_reference ['additional_info'] ) ) ? GF_Novalnet_Helper::unserialize_novalnet_data( $this->order_reference ['additional_info'] ) : array();
				if ( isset( $additional_info['bank_details'] ) ) {
					$this->event_data ['transaction']['bank_details'] = $additional_info['bank_details'];
				}
			}
			// Update additional info before form comments.
			GF_Novalnet_Helper::db_update(
				$this->update_data,
				array(
					'entry_id' => $this->gf_entry['id'],
				)
			);
			// Build & update renewal comments.
			$transaction_comments = PHP_EOL . GF_Novalnet_Helper::form_transaction_details( $this->event_data );

			$email_to = '';
			if ( ! empty( $this->gf_form ['notifications'] ) ) {
				foreach ( $this->gf_form ['notifications'] as $notification ) {
					if ( rgar( $notification, 'event' ) === 'complete_payment' ) {
						$to_field = '';
						if ( rgar( $notification, 'toType' ) == 'field' ) { // phpcs:ignore WordPress.PHP.StrictComparisons
							$to_field = rgar( $notification, 'toField' );
							if ( rgempty( 'toField', $notification ) ) {
								$to_field = rgar( $notification, 'to' );
							}

							if ( ! empty( $to_field ) ) {
								$source_field = RGFormsModel::get_field( $this->gf_form, $to_field );
								$email_to     = RGFormsModel::get_lead_field_value( $this->gf_entry, $source_field );
							}
						}
						break;
					}
				}
			}

			if ( empty( $email_to ) && $this->event_data['customer']['email'] ) {
				$email_to = $this->event_data['customer']['email'];
			}

			if ( ! empty( $email_to ) ) {
				$message = nl2br( $this->response ['message'] . PHP_EOL . $transaction_comments );
				/* translators: %1$s: blogname */
				$subject = sprintf( __( '%1$s  New Instalment for the order.', 'novalnet-gravity-forms' ), get_option( 'blogname' ) );
				$body    = "<html><body><div><p>{$message}</p></div></body></html>";
				GFCommon::send_email( get_bloginfo( 'admin_email' ), $email_to, '', '', $subject, $body, get_option( 'blogname' ), 'html', '' );
			}

			gform_update_meta( $this->gf_entry['id'], '_novalnet_transaction_comments', $transaction_comments );
			$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'] );
		}
	}

	/**
	 * Handle instalment cancel
	 */
	public function handle_instalment_cancel() {
		if ( 'CONFIRMED' === $this->event_data['transaction']['status'] ) {
			$this->update_data ['status'] = 'DEACTIVATED';
			/* translators: %1$s: parent_tid, %2$s: date */
			$this->response ['message'] = sprintf( __( 'Instalment has been cancelled for the TID %1$s on %2$s', 'novalnet-gravity-forms' ), $this->parent_tid, GF_Novalnet_Helper::formatted_date() );
			$additional_info            = ( ! empty( $this->order_reference ['additional_info'] ) ) ? GF_Novalnet_Helper::unserialize_novalnet_data( $this->order_reference ['additional_info'] ) : array();
			if ( isset( $additional_info['instalment_data'] ) ) {
				$additional_info['instalment_data']['is_instalment_cancelled'] = 1;
				$additional_info['instalment_data']['is_full_cancelled']       = 1;
				if ( ! empty( $this->event_data['instalment']['cancel_type'] ) ) {
					$additional_info['instalment_data']['is_full_cancelled'] = ( 'ALL_CYCLES' === (string) $this->event_data['instalment']['cancel_type'] ) ? 1 : 0;
				}
				$this->update_data['additional_info'] = GF_Novalnet_Helper::serialize_novalnet_data( $additional_info );
			}

			GF_Novalnet_Helper::db_update(
				$this->update_data,
				array(
					'entry_id' => $this->gf_entry['id'],
					'tid'      => $this->parent_tid,
				)
			);

			if ( isset( $additional_info['instalment_data']['is_full_cancelled'] ) && 1 === (int) $additional_info['instalment_data']['is_full_cancelled'] ) {
				$action = array(
					'note'           => $this->response['message'],
					'payment_status' => 'Cancelled',
				);
				$this->gf_novalnet->fail_payment( $this->gf_entry, $action );
			} else {
				$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'] );
			}
		}
	}

	/**
	 * Handle subscription cancel
	 */
	public function handle_renewal() {
		if ( in_array( $this->event_data['transaction']['status'], array( 'CONFIRMED', 'PENDING' ), true ) ) {
			$amount_formatted = GF_Novalnet_Helper::get_formatted_amount( $this->event_data['transaction']['amount'], $this->event_data['transaction']['currency'] );
			/* translators: %1$s: parent_tid, %2$s: amount, %3$s: date, %4$s: subscription_tid */
			$this->response['message'] = sprintf( __( 'Subscription has been successfully renewed for the TID: %1$s with the amount %2$s on %3$s. The renewal TID is:%4$s', 'novalnet-gravity-forms' ), $this->parent_tid, $amount_formatted, GF_Novalnet_Helper::formatted_date(), $this->event_tid );

			$this->response['order_no'] = $this->gf_entry['id'];
			$this->gf_novalnet->transaction_post_process( $this->gf_entry['id'], $this->event_data, true );
			$transaction_comments = GF_Novalnet_Helper::form_transaction_details( $this->event_data );
			$action               = array(
				'subscription_id'           => $this->parent_tid,
				'transaction_id'            => $this->event_tid,
				'amount'                    => GF_Novalnet_Helper::get_formatted_amount( $this->event_data['transaction']['amount'] ),
				'amount_formatted'          => $amount_formatted,
				'note'                      => $this->response['message'],
				'novalnet_transaction_note' => GFCommon::format_variable_value( $transaction_comments, false, true, 'html' ),
			);
			$this->gf_novalnet->update_callback_action_data( $this->event_data, $action, true, true );

			if ( ! in_array( rgar( $action, 'type' ), array( 'complete_payment', 'complete_authorization' ), true ) && is_callable( array( $this->gf_novalnet, rgar( $action, 'type' ) ) ) ) {
				$result = call_user_func_array( array( $this->gf_novalnet, $action['type'] ), array( $this->gf_entry, $action ) );
			} elseif ( 'complete_payment' === rgar( $action, 'type' ) ) {
				$this->gf_novalnet->complete_payment( $this->gf_entry, $action );
			} elseif ( 'complete_authorization' === rgar( $action, 'type' ) ) {
				$this->gf_novalnet->complete_authorization( $this->gf_entry, $action );
			}

			if ( ! empty( $this->event_data ['subscription']['next_cycle_date'] ) ) {
				GF_Novalnet_Helper::db_update(
					array(
						'next_payment_date' => $this->event_data ['subscription']['next_cycle_date'],
					),
					array(
						'entry_id'         => $this->gf_entry['id'],
						'subscription_tid' => $this->parent_tid,
					),
				);
			}
			$this->gf_novalnet->add_note( $this->gf_entry['id'], $transaction_comments );
		}
	}

	/**
	 * Handle subscription update
	 */
	public function handle_subscription_update() {
		// Handle change payment method.
		$recurring_amount = $this->subscription_reference['recurring_amount'];
		$recurring_date   = gmdate( 'Y-m-d', strtotime( $this->subscription_reference['next_payment_date'] ) );
		if ( ( ! empty( $this->event_data ['subscription']['amount'] ) && (int) $recurring_amount !== (int) $this->event_data ['subscription']['amount'] ) || ( $recurring_date !== $this->event_data ['subscription']['next_cycle_date'] ) ) {
			/* translators: %1$s: amount, %2$s: next_cycle_date */
			$this->response['message']             = sprintf( __( 'Subscription updated successfully. You will be charged %1$s on %2$s.', 'novalnet-gravity-forms' ), ( GF_Novalnet_Helper::get_formatted_amount( $this->event_data ['subscription'] ['amount'] ) ), $this->event_data ['subscription']['next_cycle_date'] );
			$this->update_data['recurring_amount'] = $this->event_data ['subscription']['amount'];
		}
		if ( ( ! empty( $this->event_data ['transaction'] ['payment_type'] ) && $this->event_data ['subscription'] ['payment_type'] !== $this->event_data ['transaction'] ['payment_type'] ) ) {
			/* translators: %s: next_cycle_date */
			$this->response['message'] = sprintf( __( 'Successfully changed the payment method for next subscription on %s', 'novalnet-gravity-forms' ), $this->event_data ['subscription']['next_cycle_date'] );
			gform_update_meta( $this->gf_entry['id'], '_nn_payment', strtolower( $this->event_data ['transaction'] ['payment_type'] ) );
		}
		GF_Novalnet_Helper::db_update(
			$this->update_data,
			array(
				'entry_id'         => $this->gf_entry['id'],
				'subscription_tid' => $this->parent_tid,
			),
			'novalnet_transaction_details'
		);
		$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'] );
	}


	/**
	 * Handle subscription cancel
	 */
	public function handle_subscription_cancel() {
		$subs_cancel_reason = gform_get_meta( $this->gf_entry['id'], '_nn_subs_cancelled_reason', true );
		if ( ! empty( $subs_cancel_reason ) ) {
			$this->event_data ['subscription']['reason'] = ( empty( $this->event_data ['subscription']['reason'] ) ) ? $subs_cancel_reason : $this->event_data ['subscription']['reason'];
			gform_delete_meta( $this->gf_entry['id'], '_nn_subs_cancelled_reason' );
		}

		/* translators: %1$s: parent_tid, %2$s: amount, %3$s: next_cycle_date*/
		$this->response['message'] = sprintf( __( 'Subscription has been cancelled due to: %s. ', 'novalnet-gravity-forms' ), $this->event_data ['subscription']['reason'] );
		$feed                      = $this->gf_novalnet->get_payment_feed( $this->gf_entry, $this->gf_form );
		$this->gf_novalnet->cancel_subscription( $this->gf_entry, $feed );
	}

	/**
	 * Handle subscription suspend
	 */
	public function handle_subscription_suspend() {
		/* translators: %1$s: parent_tid, %3$s: date*/
		$this->response['message'] = sprintf( __( 'This subscription transaction has been suspended on %s', 'novalnet-gravity-forms' ), gmdate( 'Y-m-d H:i:s' ) );
		GFAPI::update_entry_property( $this->gf_entry['id'], 'payment_status', 'Paused' );
		$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'] );
	}

	/**
	 * Handle subscription reactivate
	 */
	public function handle_subscription_reactivate() {
		/* translators: %1$s: tid, %2$s: date, %3$s: time, %4$s: next_cycle_date*/
		$this->response['message'] = sprintf( __( 'Subscription has been reactivated for the TID:%1$s on %2$s %3$s. Next charging date :%4$s', 'novalnet-gravity-forms' ), $this->parent_tid, gmdate( 'Y-m-d' ), gmdate( 'H:i:s' ), $this->event_data ['subscription']['next_cycle_date'] );
		GFAPI::update_entry_property( $this->gf_entry['id'], 'payment_status', 'Active' );
		$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'] );
	}

	/**
	 * Handle payment reminder
	 */
	public function handle_payment_reminder() {
		/* translators: %1$s: payment_reminder_count */
		$this->response['message'] = sprintf( __( 'Payment Reminder %1$s has been sent to the customer.', 'novalnet-gravity-forms' ), explode( '_', $this->event_type )[2] );
		$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'] );
	}

	/**
	 * Handle payment reminder
	 */
	public function handle_collection_submission() {
		/* translators: %1$s: collection_agency_reference */
		$this->response['message'] = sprintf( __( 'The transaction has been submitted to the collection agency. Collection Reference: %1$s', 'novalnet-gravity-forms' ), $this->event_data['collection']['reference'] );
		$this->gf_novalnet->add_note( $this->gf_entry['id'], $this->response['message'] );
	}

	/**
	 * Cancel subscription when recurring time reached subscription length.
	 */
	public function check_subscription_recurring() {
		if ( ! empty( $this->subscription_reference ) && ! empty( $this->gf_entry ) ) {
			$transaction_count = GF_Novalnet_Helper::get_transaction_count( $this->gf_entry['id'] );
			if ( ! empty( $this->subscription_reference['subscription_length'] ) ) {

				$transaction_count = ( ! empty( $this->subscription_reference['trial_enabled'] ) ) ? $transaction_count - $this->subscription_reference['trial_enabled'] : $transaction_count;

				if ( $transaction_count >= $this->subscription_reference['subscription_length'] ) {
					$parameters['subscription']['tid']    = $this->subscription_reference['subscription_tid'];
					$parameters['subscription']['reason'] = '';
					$parameters['custom']['lang']         = strtoupper( GF_Novalnet_Helper::get_language() );
					$parameters['custom']['shop_invoked'] = 1;
					GF_Novalnet_Helper::perform_http_request( $parameters, 'subscription/cancel', $this->payment_access_key );
				}
			}
		}
	}

	/**
	 * Get order reference.
	 *
	 * @return array
	 */
	public function get_order_reference() {
		$gf_entry_id = '';
		if ( ! empty( $this->event_data['transaction']['order_no'] ) ) {
			$gf_entry_id = $this->event_data['transaction']['order_no'];
		}
		$transaction_details = array();
		$novalnet_tid        = ( isset( $this->event_data['event']['parent_tid'] ) && ! empty( $this->event_data['event']['parent_tid'] ) ) ? $this->event_data['event']['parent_tid'] : $this->event_data['event']['tid'];
		$transaction_details = GF_Novalnet_Helper::get_transaction_details( $novalnet_tid );
		if ( empty( $transaction_details ) && ! empty( $gf_entry_id ) ) {
			$entry = GFFormsModel::get_lead( $gf_entry_id );
			if ( ! empty( $entry['transaction_id'] ) ) {
				$transaction_details = GF_Novalnet_Helper::get_transaction_details( $entry['transaction_id'] );
			}
		}

		if ( empty( $transaction_details ) ) {
			if ( 'ONLINE_TRANSFER_CREDIT' === $this->event_data ['transaction'] ['payment_type'] ) {
				$this->event_data ['transaction'] ['tid'] = $this->parent_tid;
				$this->update_initial_payment( $gf_entry_id, false );
				$transaction_details = GF_Novalnet_Helper::get_transaction_details( $novalnet_tid );
			} elseif ( 'PAYMENT' === $this->event_data ['event'] ['type'] ) {
				$this->update_initial_payment( $gf_entry_id, true );
				$transaction_details ['entry_id'] = $gf_entry_id;
			} else {
				$this->display_message( array( 'message' => 'Order reference not found in the shop' ) );
			}
		}
		return $transaction_details;
	}

	/**
	 * Update / initialize the payment.
	 *
	 * @param int   $entry_id              The order id of the processing order.
	 * @param array $communication_failure Check for communication failure process.
	 */
	public function update_initial_payment( $entry_id, $communication_failure ) {
		$entry = GFFormsModel::get_lead( $entry_id );
		if ( empty( $entry ) ) {
			$this->display_message( array( 'message' => 'Order not found in the shop' ) );
		}
		$form = GFAPI::get_form( $entry['form_id'] );
		if ( 'ONLINE_TRANSFER_CREDIT' === rgar( $this->event_data['transaction'], 'payment_type' ) ) {
			$transaction_details = GF_Novalnet_Helper::get_transaction_result( array( 'tid' => $this->parent_tid ), $this->settings['novalnet_payment_access_key'] );
			$payment_name        = GF_Novalnet_Helper::get_payment_name( rgar( $transaction_details['transaction'], 'payment_type' ) );
		} else {
			$payment_name = GF_Novalnet_Helper::get_payment_name( rgar( $this->event_data['transaction'], 'payment_type' ) );
		}

		$action = array(
			'entry_id'       => $entry_id,
			'form_id'        => $entry['form_id'],
			'amount'         => GF_Novalnet_Helper::get_formatted_amount( rgar( $this->event_data['transaction'], 'amount' ) ),
			'transaction_id' => rgar( $this->event_data['transaction'], 'tid' ),
			'payment_method' => $payment_name['payment_name'],
		);
		$this->gf_novalnet->update_callback_action_data( $this->event_data, $action );

		if ( ! in_array( rgar( $action, 'type' ), array( 'complete_payment', 'complete_authorization' ), true ) && is_callable( array( $this->gf_novalnet, rgar( $action, 'type' ) ) ) ) {
			$result = call_user_func_array( array( $this->gf_novalnet, $action['type'] ), array( $entry, $action ) );
		} elseif ( 'complete_payment' === rgar( $action, 'type' ) ) {
			$this->gf_novalnet->complete_payment( $entry, $action );
		} elseif ( 'complete_authorization' === rgar( $action, 'type' ) ) {
			$this->gf_novalnet->complete_authorization( $entry, $action );
		}
		$transaction_comments = GF_Novalnet_Helper::form_transaction_details( $this->event_data );
		gform_update_meta( $entry['id'], '_novalnet_transaction_comments', $transaction_comments );
		$this->gf_novalnet->gf_novalnet_send_notifications( $form, $entry );
		if ( GF_Novalnet_Helper::is_success_status( $this->event_data ) ) {
			$this->gf_novalnet->transaction_post_process( $entry_id, $this->event_data );
		}
	}

	/**
	 * Display the callback messages.
	 *
	 * @param array $data Message for the executed process.
	 */
	public function display_message( $data ) {
		wp_send_json( $data, 200 );
	}

	/**
	 * Send notification mail.
	 *
	 * @param string $message The message to send in mail.
	 */
	public function send_notification_mail( $message ) {
		if ( ! empty( $this->settings['novalnet_webhook_email_to'] ) ) {
			/* translators: %1$s: blogname */
			$subject = sprintf( __( '%1$s  Novalnet Callback Script Access Report - GravityForms', 'novalnet-gravity-forms' ), get_option( 'blogname' ) );
			return GFCommon::send_email( '', $this->settings['novalnet_webhook_email_to'], '', '', $subject, $message, $from_name = '', 'html', '' );
		}
	}

	/**
	 * Validate event_data
	 */
	public function validate_event_data() {
		try {
			$json_input       = WP_REST_Server::get_raw_data();
			$this->event_data = GF_Novalnet_Helper::unserialize_novalnet_data( $json_input );
		} catch ( Exception $e ) {
			$this->display_message( array( 'message' => "Received data is not in the JSON format $e" ) );
		}
		if ( ! empty( $this->event_data ['custom'] ['shop_invoked'] ) ) {
			$this->display_message( array( 'message' => 'Process already handled in the shop.' ) );
		}
		// Your payment access key value.
		$this->payment_access_key = $this->settings['novalnet_payment_access_key'];
		// Validate request parameters.
		foreach ( $this->mandatory as $category => $parameters ) {
			if ( empty( $this->event_data [ $category ] ) ) {
				// Could be a possible manipulation in the notification data.
				$this->display_message( array( 'message' => "Required parameter category($category) not received" ) );
			} elseif ( ! empty( $parameters ) ) {
				foreach ( $parameters as $parameter ) {
					if ( empty( $this->event_data [ $category ] [ $parameter ] ) ) {

						// Could be a possible manipulation in the notification data.
						$this->display_message( array( 'message' => "Required parameter($parameter) in the category($category) not received" ) );
					} elseif ( in_array( $parameter, array( 'tid', 'parent_tid' ), true ) && ! preg_match( '/^\d{17}$/', $this->event_data [ $category ] [ $parameter ] ) ) {
						$this->display_message( array( 'message' => "Invalid TID received in the category($category) not received $parameter" ) );
					}
				}
			}
		}
	}

	/**
	 * Validate checksum
	 */
	public function validate_checksum() {
		$token_string = $this->event_data ['event'] ['tid'] . $this->event_data ['event'] ['type'] . $this->event_data ['result'] ['status'];
		if ( isset( $this->event_data ['transaction'] ['amount'] ) ) {
			$token_string .= $this->event_data ['transaction'] ['amount'];
		}
		if ( isset( $this->event_data ['transaction'] ['currency'] ) ) {
			$token_string .= $this->event_data ['transaction'] ['currency'];
		}
		if ( ! empty( $this->payment_access_key ) ) {
			$token_string .= strrev( $this->payment_access_key );
		}
		$generated_checksum = hash( 'sha256', $token_string );
		if ( $generated_checksum !== $this->event_data ['event'] ['checksum'] ) {
			$this->display_message( array( 'message' => 'While notifying some data has been changed. The hash check failed' ) );
		}
	}

	/**
	 * Authenticate server request
	 */
	public function authenticate_event_data() {
		$this->test_mode = (int) ( $this->settings['novalnet_webhook_test_mode'] );
		// Host based validation.
		if ( ! empty( $this->novalnet_host_name ) ) {
			$novalnet_host_ip = gethostbyname( $this->novalnet_host_name );
			// Authenticating the server request based on IP.
			$request_received_ip = GFFormsModel::get_ip();
			if ( ! empty( $novalnet_host_ip ) && ! empty( $request_received_ip ) ) {
				if ( $novalnet_host_ip !== $request_received_ip && empty( $this->test_mode ) ) {
					$this->display_message( array( 'message' => "Unauthorised access from the IP $request_received_ip" ) );
				}
			} else {
				$this->display_message( array( 'message' => 'Unauthorised access from the IP. Host/recieved IP is empty' ) );
			}
		} else {
			$this->display_message( array( 'message' => 'Unauthorised access from the IP. Novalnet Host name is empty' ) );
		}
		$this->validate_event_data();
		$this->validate_checksum();
	}
}
