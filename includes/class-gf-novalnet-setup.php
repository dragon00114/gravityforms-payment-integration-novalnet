<?php
/**
 * This file is used for installing and uninstalling processes
 *
 * @author   Novalnet AG
 * @package  novalnet-gravity-forms
 * @license  https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * GF_Novalnet_Setup
 */
class GF_Novalnet_Setup {

	/**
	 * Handle installation process.
	 */
	public static function install() {
		if ( GFForms::get_wp_option( 'gf_novalnet_version' ) !== GF_NOVALNET_VERSION ) {
			self::create_table();
			update_option( 'gf_novalnet_version', GF_NOVALNET_VERSION );
		}
	}

	/**
	 * Handle uninstallation process.
	 */
	public static function uninstall() {
		delete_option( 'gf_novalnet_version' );
		delete_option( 'gravityformsaddon_' . gf_novalnet()->get_slug() . '_settings' );
		return true;
	}

	/**
	 * Handle table creation process.
	 */
	private static function create_table() {
		global $wpdb;
		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		include_once ABSPATH . 'wp-admin/install-helper.php';
		$collate          = $wpdb->get_charset_collate();
		$existing_version = GFForms::get_wp_option( 'gf_novalnet_version' );
		if ( ! empty( $existing_version ) ) { // For updating plugin.
			if ( version_compare( $existing_version, '3.0.0', '<' ) ) {
				// Modify transaction details table.
				Gf_Novalnet_Helper::handle_query_error( $wpdb->query( "ALTER TABLE {$wpdb->prefix}novalnet_transaction_details MODIFY COLUMN status varchar(64);" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				// Modify transaction details table.
				$drop_columns = array(
					'vendor_details',
					'email',
					'date',
					'payment_method',
					'payment_type',
				);
				foreach ( $drop_columns as $column ) {
					maybe_drop_column(
						"{$wpdb->prefix}novalnet_transaction_details",
						"{$column}",
						"ALTER TABLE {$wpdb->prefix}novalnet_transaction_details DROP COLUMN {$column};"
					);
				}
			}
		} // New installation of plugin.
		// Creating transaction details table to maintain the transaction log.
		Gf_Novalnet_Helper::handle_query_error(
			dbDelta(
				"CREATE TABLE {$wpdb->prefix}novalnet_transaction_details (
				id int(11) unsigned AUTO_INCREMENT COMMENT 'Auto increment id',
				entry_id int(11) unsigned COMMENT 'Post id for the entry in shop',
				tid bigint(20) COMMENT 'Transaction id',
				subscription_tid bigint(20) unsigned COMMENT 'Novalnet transaction reference ID',
				subscription_length int(11) unsigned COMMENT 'Length of the subscription',
				trial_enabled tinyint(1) unsigned COMMENT 'Subscriptions trail payment',
				is_renewal tinyint(1) unsigned COMMENT 'Subscriptions renewal payment',
				next_payment_date datetime COMMENT 'Subscription next cycle date',
				subs_expiry_date datetime COMMENT 'Subscription expiry date',
				status varchar(64) COMMENT 'Transaction status',
				transaction_amount int(11) unsigned COMMENT 'Transaction amount in minimum unit of currency',
				recurring_amount int(11) unsigned COMMENT 'Amount in minimum unit of currency',
				refunded_amount int(11) unsigned COMMENT 'Refunded amount in minimum unit of currency',
				paid_amount int(11) unsigned COMMENT 'Paid amount in minimum unit of currency',
				additional_info text COMMENT 'Additional information used in gateways',
				PRIMARY KEY  (id)
				) $collate COMMENT='Novalnet Transaction History';"
			)
		);
	}
}
