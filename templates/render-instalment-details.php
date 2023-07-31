<?php
/**
 * Display Instalment related transactions
 *
 * @author  Novalnet AG
 * @package  novalnet-gravity-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! empty( $instalments['cycle_details'] ) && 1 < count( $instalments['cycle_details'] ) ) {
	?>
<div>
<table class="wp-list-table widefat striped">
<thead>
	<tr>
		<th><?php esc_attr_e( 'S.no', 'novalnet-gravity-forms' ); ?></th>
		<th><?php esc_attr_e( 'Date', 'novalnet-gravity-forms' ); ?></th>
		<th><?php esc_attr_e( 'Novalnet transaction ID', 'novalnet-gravity-forms' ); ?></th>
		<th><?php esc_attr_e( 'Amount', 'novalnet-gravity-forms' ); ?></th>
		<th><?php esc_attr_e( 'Status', 'novalnet-gravity-forms' ); ?></th>
	</tr>
</thead>
<tbody>
	<?php

	foreach ( $instalments['cycle_details'] as $cycle => $instalment ) {
		$instalment_status = ( ! empty( $instalment['tid'] ) ) ? __( 'Paid', 'novalnet-gravity-forms' ) : __( 'Pending', 'novalnet-gravity-forms' );

		$amount_text = GF_Novalnet_Helper::get_formatted_amount( $instalment['amount'], $instalments['currency'] );

		if ( isset( $instalments['is_instalment_cancelled'] ) && 1 === $instalments['is_instalment_cancelled'] ) {
			if ( isset( $instalments['is_full_cancelled'] ) && 1 === $instalments['is_full_cancelled'] ) {
				$instalment_status = ( ! empty( $instalment['tid'] ) ) ? __( 'Refunded', 'novalnet-gravity-forms' ) : __( 'Cancelled', 'novalnet-gravity-forms' );
			} else {
				$instalment_status = ( ! empty( $instalment['tid'] ) ) ? __( 'Paid', 'novalnet-gravity-forms' ) : __( 'Cancelled', 'novalnet-gravity-forms' );
			}
		}

		if ( ! empty( $instalment['refund_amount'] ) && ! empty( $instalment['tid'] ) ) {
			if ( $instalment['refund_amount'] >= $instalment['amount'] ) {
				$instalment_status    = __( 'Refunded', 'novalnet-gravity-forms' );
				$instalment['amount'] = $instalment['refund_amount'];
			} else {
				$instalment['amount'] -= $instalment['refund_amount'];
				$amount_text           = GF_Novalnet_Helper::get_formatted_amount( $instalment['amount'], $instalments['currency'] );
			}
		}
		?>
		<tr class>
			<td>
				<?php echo esc_attr( $cycle ); ?>
			</td>
			<td>
				<?php echo esc_attr( $instalment['date'] ); ?>
			</td>
			<td>
				<?php echo esc_attr( ! empty( $instalment['tid'] ) ? $instalment['tid'] : '-' ); ?>
			</td>
			<td>
				<?php echo esc_html( GF_Novalnet_Helper::get_formatted_amount( $instalment['amount'], $instalments['currency'] ) ); ?>
			</td>
			<td>
				<?php echo esc_html( $instalment_status ); ?>
			</td>
		</tr>
			<?php
	}
}
?>
		</tbody>
	</table>
</div>
