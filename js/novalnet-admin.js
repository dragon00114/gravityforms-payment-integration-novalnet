/**
 * This script is used for configuring the merchant credentials
 * in the configuration fields
 *
 * @author   Novalnet AG
 * @package  novalnet-gravity-forms
 * @license  https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

(function ( $ ) {
	novalnet_admin = {
		init : function () {
			$( '#gaddon-setting-row-novalnet_tariff' ).hide();
			$( '.novalnet-subs-config' ).hide();
			if ($( '#novalnet_public_key' ).length && $( '#novalnet_payment_access_key' ).length) {
				if ( '' !== $.trim( $( '#novalnet_public_key' ).val() ) && '' !== $.trim( $( '#novalnet_payment_access_key' ).val() ) ) {
					novalnet_admin.fill_merchant_details();
				}
				$( '#novalnet_public_key, #novalnet_payment_access_key' ).on(
					'input change',
					function(e) {
						$( this ).next( "input[type=text]" ).focus();
						if ( '' !== $.trim( $( '#novalnet_public_key' ).val() ) && '' !== $.trim( $( '#novalnet_payment_access_key' ).val() ) ) {
							if ( 'input' === e.type ) {
								if (e.originalEvent.inputType != undefined && 'insertFromPaste' === e.originalEvent.inputType ) {
									novalnet_admin.fill_merchant_details();
								}
							} else {
								novalnet_admin.fill_merchant_details();
							}

						}
						if ( '' == $.trim( $( '#novalnet_public_key' ).val() ) && '' == $.trim( $( '#novalnet_payment_access_key' ).val() ) ) {
							novalnet_admin.null_basic_params();
						}
					}
				);
				$( '#novalnet_public_key' ).closest( 'form' ).on(
					'submit',
					function( event ) {
						if ( undefined === novalnet_admin.ajax_complete ) {
							event.preventDefault();
							$( document ).ajaxComplete(
								function( event, xhr, settings ) {
									$( '#novalnet_public_key' ).closest( 'form' ).submit();
								}
							);
						}
					}
				);
			}
		},
		/* Vendor hash process */
		handle_merchant_details_response : function ( data ) {
			data = data.data;
			if (undefined !== data.error && '' !== data.error ) {
				alert( data.error );
				novalnet_admin.null_basic_params();
				return false;
			}
			$( '.novalnet-configurations' ).show();
			$( '#gform_setting_novalnet_tariff' ).show();
			var saved_tariff_id      = $( '#novalnet_tariff' ).val();
			var saved_subs_tariff_id = $( '#novalnet_subs_tariff' ).val();

			if ($( '#novalnet_tariff' ).prop( 'type' ) == 'text') {
				$( '#novalnet_tariff' ).replaceWith( '<select id="novalnet_tariff" class="small gaddon-setting gaddon-select" name= "_gform_setting_novalnet_tariff" ></select>' );
			}
			if ($( '#novalnet_subs_tariff' ).prop( 'type' ) == 'text') {
				$( '#novalnet_subs_tariff' ).replaceWith( '<select id="novalnet_subs_tariff" class="small gaddon-setting gaddon-select" name= "_gform_setting_novalnet_subs_tariff" ></select>' );
			}
			$( '#novalnet_tariff' ).empty().append();
			$( '#novalnet_subs_tariff' ).empty().append();
			for ( var tariff_id in data.tariff ) {
				var tariff_type  = data.tariff[ tariff_id ].type;
				var tariff_value = data.tariff[ tariff_id ].name;
				$( '#novalnet_tariff' ).append(
					$(
						'<option>',
						{
							value: $.trim( tariff_id ),
							text : $.trim( tariff_value )
						}
					)
				);
				if ( tariff_type == 4 ) {
					if ( $( '.novalnet-subs-config' ).css('display') == 'none' ) {
						$( '.novalnet-subs-config' ).show();
					}
					$( '#novalnet_subs_tariff' ).append(
						$(
							'<option>',
							{
								value: $.trim( tariff_id ),
								text : $.trim( tariff_value )
							}
						)
					);
				}
				// Assign tariff id.
				if (saved_tariff_id === $.trim( tariff_id ) ) {
					$( '#novalnet_tariff' ).val( $.trim( tariff_id ) );
				}
				// Assign subscription tariff id.
				if (saved_subs_tariff_id === $.trim( tariff_id ) ) {
					$( '#novalnet_subs_tariff' ).val( $.trim( tariff_id ) );
				}
			}
			// Assign vendor details.
			$( '#novalnet_vendor' ).val( data.vendor );
			$( '#novalnet_auth_code' ).val( data.auth_code );
			$( '#novalnet_product' ).val( data.project );
			novalnet_admin.ajax_complete = 'true';
			return true;
		},
		/* Process to fill the merchant details */
		fill_merchant_details : function () {
			var data = {
				'novalnet_api_key': $.trim( $( '#novalnet_public_key' ).val() ),
				'novalnet_access_key': $.trim( $( '#novalnet_payment_access_key' ).val() ),
				'action': 'get_novalnet_vendor_details',
				'nonce' : gf_novalnet_admin_strings.merchant_details_nonce,
			};
			novalnet_admin.ajax_call( data );
		},
		/* Process to config notification url */
		config_notification_url : function () {
			var data = {
				'novalnet_api_key': $.trim( $( '#novalnet_public_key' ).val() ),
				'novalnet_access_key': $.trim( $( '#novalnet_payment_access_key' ).val() ),
				'novalnet_hook_url': $.trim( $( '#novalnet_webhook_url' ).val() ),
				'action': 'config_novalnet_hook_url',
				'nonce' : gf_novalnet_admin_strings.notification_url_nonce,
			};
			novalnet_admin.ajax_call( data , 'webhook_config' );
		},
		/* Empty config values */
		null_basic_params : function () {
			novalnet_admin.ajax_complete = 'true';
			$( '#gform_setting_novalnet_tariff' ).hide();
			$( '#novalnet_payment_access_key, #novalnet_public_key' ).val( '' );
			$( '#novalnet_tariff' ).find( 'option' ).remove();
			$( '#novalnet_tariff' ).append(
				$(
					'<option>',
					{
						value: '',
						text : novalnet_admin.select_text,
					}
				)
			);
			$( '.novalnet-configurations' ).hide();
		},
		/* Initiate ajax call to server */
		ajax_call : function ( data , type = 'merchant_details' ) {
			$.ajax(
				{
					type:     'post',
					url:      ajaxurl,
					data:     data,
					success:  function( response ) {
						if ( type == 'merchant_details' ) {
							return novalnet_admin.handle_merchant_details_response( response );
						} else if ( type == 'webhook_config' ) {
							data = response.data;
							if ( undefined !== data.error && '' !== data.error ) {
								alert( data.error );
								return false;
							} else if ( undefined !== data.result.status_text && '' !== data.result.status_text ) {
								alert( data.result.status_text );
								return true;
							}
						}
					},
				}
			);
		}
	};
	$( document ).ready(
		function () {
			$( '.novalnet-configurations' ).hide();
			novalnet_admin.init();
		}
	);
})( jQuery );
