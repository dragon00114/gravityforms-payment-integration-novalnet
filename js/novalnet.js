/**
 * This script is used for rendering the Novalnet seamless payment form
 *
 * @author   Novalnet AG
 * @package  novalnet-gravity-forms
 * @license  https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

(function ( $ ) {
	novalnet = {
		init : function () {
				Novalnet.setParam( "nn_it", "overlay" );
				Novalnet.setParam( "txn_secret", gf_novalnet_form_data.txn_secret );
				Novalnet.render();
				return false;
		},
	};
	$( document ).ready(
		function () {
			novalnet.init();
		}
	);
})( jQuery );
