<?php
/**
 * Plugin Name: Novalnet payment Add-On for Gravity Forms
 * Plugin URI:  https://www.novalnet.de/modul/gravityforms
 * Description: PCI compliant payment solution, covering a full scope of payment services and seamless integration for easy adaptability
 * Version:     3.0.0
 * Author:      Novalnet AG
 * Author URI:  https://www.novalnet.de
 * Text Domain: novalnet-gravity-forms
 * Domain Path: /languages
 * License URI: https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 *
 * @package     novalnet-gravity-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Novalnet bootstrap class to register addon.
 */
if ( ! function_exists( 'gf_novalnet_bootstrap' ) ) {
	/**
	 * Register Novalnet addon in gravity form addons.*/
	function gf_novalnet_bootstrap() {
		if ( method_exists( 'GFForms', 'include_payment_addon_framework' ) === false ) {
			return;
		}
		if ( ! class_exists( 'GF_Novalnet' ) ) :
			define( 'GF_NOVALNET_VERSION', '3.0.0' );
			define( 'GF_NOVALNET_FILE', __FILE__ );
			define( 'GF_NOVALNET_PATH', plugin_dir_path( __FILE__ ) );
			define( 'GF_NOVALNET_URL', plugin_dir_url( __FILE__ ) );

			load_plugin_textdomain( 'novalnet-gravity-forms', false, 'novalnet-gravity-forms/languages' );

			GFForms::include_payment_addon_framework();
			GFForms::include_addon_framework();
			require_once 'includes/class-gf-novalnet-configuration.php';
			require_once 'class-gf-novalnet.php';
			GFAddOn::register( 'GF_Novalnet' );
		endif;
	}
}

if ( ! function_exists( 'gf_novalnet' ) ) {
	/**
	 * Returns the primary instance of GF_Novalnet.
	 *
	 * @return GF_Novalnet
	 */
	function gf_novalnet() {
		return GF_Novalnet::get_instance();
	}
}

add_action( 'gform_loaded', 'gf_novalnet_bootstrap', 5 );
