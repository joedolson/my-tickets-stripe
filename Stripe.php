<?php
/**
 * My Tickets: Stripe payment gateway
 *
 * @package     My Tickets: Stripe
 * @author      Joe Dolson
 * @copyright   2016-2019 Joe Dolson
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: My Tickets: Stripe
 * Plugin URI: http://www.joedolson.com/my-tickets/add-ons/
 * Description: Add support for the Stripe payment gateway to My Tickets.
 * Author: Joseph C Dolson
 * Author URI: http://www.joedolson.com
 * Text Domain: my-tickets-stripe
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/license/gpl-2.0.txt
 * Domain Path: lang
 * Version:     1.2.5
 */

/*
	Copyright 2016-2019  Joe Dolson (email : joe@joedolson.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

global $mt_stripe_version;
$mt_stripe_version = '1.2.5';
load_plugin_textdomain( 'my-tickets-stripe', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

add_action( 'admin_notices', 'mt_stripe_mt_version' );
/**
 * Check that the current version of My Tickets is supported.
 */
function mt_stripe_mt_version() {
	if ( ! function_exists( 'mt_get_current_version' ) ) {
		// function mt_get_current_version added in My Tickets 1.4.0.
		$message = sprintf( __( 'My Tickets: Stripe requires at least <strong>My Tickets 1.4.0</strong>. Please update My Tickets!', 'my-tickets-stripe' ) );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		} else {
			echo "<div class='error'><p>$message</p></div>";
		}
	}
}

// The URL of the site with EDD installed.
define( 'EDD_MT_STRIPE_STORE_URL', 'https://www.joedolson.com' );
// The title of your product in EDD and should match the download title in EDD exactly.
define( 'EDD_MT_STRIPE_ITEM_NAME', 'My Tickets: Stripe' );

if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
	// load our custom updater if it doesn't already exist.
	include( dirname( __FILE__ ) . '/updates/EDD_SL_Plugin_Updater.php' );
}

// setup the updater.
if ( class_exists( 'EDD_SL_Plugin_Updater' ) ) { // prevent fatal error if doesn't exist for some reason.
	$edd_updater = new EDD_SL_Plugin_Updater( EDD_MT_STRIPE_STORE_URL, __FILE__, array(
		'version'   => $mt_stripe_version,								// current version number.
		'license'   => trim( get_option( 'mt_stripe_license_key' ) ),	// license key.
		'item_name' => EDD_MT_STRIPE_ITEM_NAME,							// name of this plugin.
		'author'    => 'Joe Dolson',									// author of this plugin.
		'url'       => home_url(),
	) );
}

/**
 * Import Stripe class.
 *
 * @package Stripe
 */
if ( ! class_exists( 'Stripe' ) ) {
	require_once( 'stripe-php/init.php' );
}
require_once( 'mt-stripe-ipn.php' );

/**
 * Return all currencies supported by Stripe.
 *
 * @return array of currency ids
 */
function mt_stripe_supported() {
	return array( 'AED', 'ALL', 'ANG', 'AUD', 'AWG', 'BBD', 'BDT', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BWP', 'BZD', 'CAD', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JMD', 'JPY', 'KES', 'KHR', 'KMF', 'KRW', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'MAD', 'MDL', 'MNT', 'MOP', 'MRO', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RUB', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'STD', 'SVC', 'SZL', 'THB', 'TOP', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VND', 'VUV', 'WST', 'XAF', 'XOF', 'YER', 'ZAR' );
}

add_filter( 'mt_currencies', 'mt_stripe_currencies', 10, 1 );
/**
 * If this gateway is active, limit currencies to supported currencies.
 *
 * @param $currencies Currencies supported.
 *
 * @return return full currency array.
 */
function mt_stripe_currencies( $currencies ) {
	$options     = ( ! is_array( get_option( 'mt_settings' ) ) ) ? array() : get_option( 'mt_settings' );
	$defaults    = mt_default_settings();
	$options     = array_merge( $defaults, $options );
	$mt_gateways = $options['mt_gateway'];

	if ( is_array( $mt_gateways ) && in_array( 'authorizenet', $mt_gateways ) ) {
		$stripe = mt_stripe_supported();
		$return = array();
		foreach ( $stripe as $currency ) {
			$keys = array_keys( $currencies );
			if ( in_array( $currency, $keys ) ) {
				$return[ $currency ] = $currencies[ $currency ];
			}
		}

		return $return;
	}

	return $currencies;
}

add_filter( 'mt_settings', 'mt_stripe_settings', 10, 2 );
/**
 * When settings are saved, check for Stripe keys. If added or changed, create endpoint.
 *
 * @param array $settings New settings.
 * @param array $post POST data
 * 
 * @return settings
 */
function mt_stripe_settings( $settings, $post ) {]
	if ( isset( $_GET['page'] ) && 'mt-payment' == $_GET['page'] ) {
		$new_options = array_merge( mt_default_settings(), $settings );
		$old_options = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
		// these all need to be set from Stripe data.
		$nstripe_options = $new_options['mt_gateways']['stripe'];
		$ostripe_options = isset( $old_options['mt_gateways']['stripe'] ) ? $old_options['mt_gateways']['stripe'] : array();

		$test_secret_key  = trim( $nstripe_options['test_secret'] );
		$test_osecret_key = isset( $ostripe_options['test_secret'] ) ? trim( $ostripe_options['test_secret'] ) : '';
		$live_secret_key  = trim( $nstripe_options['prod_secret'] );
		$live_osecret_key = isset( $ostripe_options['prod_secret'] ) ? trim( $ostripe_options['prod_secret'] ) : '';

		$test_secret_key = ( $test_secret_key != $test_osecret_key && '' != $test_secret_key ) ? $test_secret_key : false;
		$live_secret_key = ( $live_secret_key != $live_osecret_key && '' != $live_secret_key ) ? $live_secret_key : false;

		if ( $test_secret_key ) {
			\Stripe\Stripe::setApiKey( $test_secret_key );

			$endpoint = \Stripe\WebhookEndpoint::create([
				'url' => add_query_arg( 'mt_stripe_ipn', 'true', home_url() ),
				'enabled_events' => ["*"]
			]);

			update_option( 'mt_stripe_test_webhook', $endpoint->id );
		}

		if ( $live_secret_key ) {
			\Stripe\Stripe::setApiKey( $live_secret_key );

			$endpoint = \Stripe\WebhookEndpoint::create([
				'url' => add_query_arg( 'mt_stripe_ipn', 'true', home_url() ),
				'enabled_events' => ["*"]
			]);

			update_option( 'mt_stripe_live_webhook', $endpoint->id );
		}
	}

	return $settings;
}


add_filter( 'mt_setup_gateways', 'mt_setup_stripe', 10, 1 );
/**
 * Set up stripe in options.
 *
 * @param array $gateways Gateways installed.
 *
 * @return updated gateways
 */
function mt_setup_stripe( $gateways ) {
	$note           = '';
	$options        = ( ! is_array( get_option( 'mt_settings' ) ) ) ? array() : get_option( 'mt_settings' );
	$stripe_options = isset( $options['mt_gateways']['stripe'] ) ? $options['mt_gateways']['stripe'] : array();
	if ( ! empty( $stripe_options ) ) {
		$test_secret_key      = trim( $stripe_options['test_secret'] );
		$test_webhook_id = get_option( 'mt_stripe_test_webhook', false );
		$live_secret_key      = trim( $stripe_options['prod_secret'] );
		$live_webhook_id = get_option( 'mt_stripe_live_webhook', false );
		
		$setup = ( $test_secret_key && $live_secret_key ) ? true : false;
	} else {
		$setup = false;
	}

	if ( $setup && ( $test_webhook_id || $live_webhook_id ) ) {
		if ( $test_webhook_id ) {
			\Stripe\Stripe::setApiKey( $test_secret_key );
			try {
				$test_endpoint = \Stripe\WebhookEndpoint::retrieve( $test_webhook_id );
			} catch ( Exception $e ) {
				if ( function_exists( 'mt_log_error' ) ) {
					mt_log_error( $e );
				}
				$test_endpoint = (object) array( 'status' => 'missing' );
			}
		} else {
			$test_endpoint = (object) array( 'status' => 'not created' );
		}
		if ( $live_webhook_id ) {
			\Stripe\Stripe::setApiKey( $live_secret_key );
			try {
				$live_endpoint = \Stripe\WebhookEndpoint::retrieve( $live_webhook_id );
			} catch ( Exception $e ) {
				if ( function_exists( 'mt_log_error' ) ) {
					mt_log_error( $e );
				}
				$live_endpoint = (object) array( 'status' => 'missing' );
			}
		} else {
			$live_endpoint = (object) array( 'status' => 'not created', 'url' => add_query_arg( 'mt_stripe_ipn', 'true', home_url() ) );
		}
		$note     = sprintf( __( 'Your Stripe webhook endpoints have been automatically created. The endpoints point to <code>%1$s</code>. Your live endpoint is currently <strong>%2$s</strong>, and your test endpoint is <strong>%3$s</strong>.', 'my-tickets-stripe' ), $live_endpoint->url, $live_endpoint->status, $test_endpoint->status );

	} else if ( $setup ) {
		\Stripe\Stripe::setApiKey( $live_secret_key );
		$endpoints = \Stripe\WebhookEndpoint::all( ['limit' => 10] );
		foreach( $endpoints as $endpoint ) {
			if ( $endpoint->url == add_query_arg( 'mt_stripe_ipn', 'true', home_url() ) ) {
				$note = sprintf( __( 'You have an existing live Stripe endpoint at <code>%s</code>.', 'my-tickets-stripe' ), $endpoint->url );
			}
		}
		\Stripe\Stripe::setApiKey( $test_secret_key );
		$endpoints = \Stripe\WebhookEndpoint::all( ['limit' => 10] );
		foreach( $endpoints as $endpoint ) {
			if ( $endpoint->url == add_query_arg( 'mt_stripe_ipn', 'true', home_url() ) ) {
				$note .= ' ' . sprintf( __( 'You have an existing test Stripe endpoint at <code>%s</code>.', 'my-tickets-stripe' ), $endpoint->url );
			}
		}
		if ( '' === $note ) {
			$note = sprintf( __( 'You need to add <code>%s</code> as a Webhook URL in your Stripe account at Stripe > Dashboard > Settings > Webhooks. My Tickets: Stripe will attempt to configure your webhook automatically when you save your Stripe API keys.', 'my-tickets-stripe' ), add_query_arg( 'mt_stripe_ipn', 'true', home_url() ) );
		}
	} else {
		$note = sprintf( __( 'You need to add <code>%s</code> as a Webhook URL in your Stripe account at Stripe > Dashboard > Settings > Webhooks. My Tickets: Stripe will attempt to configure your webhook automatically when you save your Stripe API keys.', 'my-tickets-stripe' ), add_query_arg( 'mt_stripe_ipn', 'true', home_url() ) );
	}

	// this key is how the gateway will be referenced in all contexts.
	$gateways['stripe'] = array(
		'label'  => __( 'Stripe', 'my-tickets-stripe' ),
		'fields' => array(
			'prod_public'     => __( 'API Publishable Key (Production)', 'my-tickets-stripe' ),
			'prod_secret'     => __( 'API Secret Key (Production)', 'my-tickets-stripe' ),
			'test_public'     => __( 'API Publishable Key (Test)', 'my-tickets-stripe' ),
			'test_secret'     => __( 'API Secret Key (Test)', 'my-tickets-stripe' ),
			'test_mode' => array(
				'label' => __( 'Test Mode Enabled', 'my-tickets-stripe' ),
				'type'  => 'checkbox',
				'value' => 'true',
			),
			'selector'        => __( 'Gateway selector label', 'my-tickets' ),
			'disable_address' => array(
				'label' => __( 'Disable Billing Address Fields', 'my-tickets' ),
				'type'  => 'checkbox',
				'value' => 'off',
			),
		),
		// Translators: Stripe webhook URL for this site.
		'note' => $note,
	);

/**
 * Needs further work; take live when completed. But I can add multiple gateways within one plug-in. Woot.
	$gateways['iban'] = array(
		'label'    => __( 'IBAN', 'my-tickets-stripe' ),
		'selector' => __( 'Gateway selector label', 'my-tickets-stripe' ),
		'note'     => __( 'There are no extra settings required to use International Bank Account Numbers with Stripe.', 'my-tickets-stripe' ),
	);

	$gateways['ideal'] = array(
		'label'    => __( 'iDEAL', 'my-tickets-stripe' ),
		'selector' => __( 'Gateway selector label', 'my-tickets-stripe' ),
		'note'     => __( 'There are no extra settings required to use iDEAL Bank with Stripe.', 'my-tickets-stripe' ),
	);
*/
	return $gateways;
}

add_filter( 'mt_shipping_fields', 'mt_stripe_shipping_fields', 10, 2 );
/**
 * Parse shipping fields to Stripe's format
 *
 * @param string $form Form data.
 * @param string $gateway Gateway in use.
 *
 * @return string $form updated.
 */
function mt_stripe_shipping_fields( $form, $gateway ) {
	if ( 'stripe' == $gateway ) {
		$search  = array(
			'mt_shipping_street',
			'mt_shipping_street2',
			'mt_shipping_city',
			'mt_shipping_state',
			'mt_shipping_country',
			'mt_shipping_code',
		);
		$replace = array(
			'x_ship_to_address',
			'x_shipping_street2',
			'x_ship_to_city',
			'x_ship_to_state',
			'x_ship_to_country',
			'x_ship_to_zip',
		);

		return str_replace( $search, $replace, $form );
	}

	return $form;
}

add_filter( 'mt_response_messages', 'mt_stripe_messages', 10, 2 );
/*
 * Feeds custom response messages to return page (cart)
 *
 * @param string $message Current messages.
 * @param string $code Returned code.
 *
 * @return string New message.
 */
function mt_stripe_messages( $message, $code ) {
	if ( isset( $_GET['gateway'] ) && 'stripe' == $_GET['gateway'] || 'ideal' == $_GET['gateway'] || 'iban' == $_GET['gateway'] ) {
		$options = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
		if ( 1 == $code || 'thanks' == $code ) {
			$payment_id     = absint( $_GET['payment_id'] );
			$receipt_id     = get_post_meta( $payment_id, '_receipt', true );
			$receipt        = esc_url( add_query_arg( array( 'receipt_id' => $receipt_id ), get_permalink( $options['mt_receipt_page'] ) ) );
			// If payment status has not yet transitioned to completed, notify purchaser.
			if ( 'Completed' !== get_post_meta( $payment_id, '_is_paid', true ) ) {
				// Translators: URL to view receipt.
				return sprintf( __( 'Thank you for your purchase! Your payment is still processing. Your receipt will be updated and tickets generated after the credit card payment is complete. <a href="%s">View your receipt</a>', 'my-tickets-stripe' ), $receipt );
			} else {
				// Translators: Transaction ID from Stripe, URL to view receipt.
				return sprintf( __( 'Thank you for your purchase! <a href="%s">View your receipt</a>', 'my-tickets-stripe' ), $receipt );
			}
		} else {
			$reason = isset( $_GET['reason'] ) ? stripslashes( urldecode( $_GET['reason'] ) ) : __( 'Unknown failure.', 'my-tickets-stripe' );
			// Translators: Error message from Stripe.
			return sprintf( __( 'Sorry, an error occurred: %s', 'my-tickets-stripe' ), "<strong>" . sanitize_text_field( $reason ) . "</strong>" );
		}
	}

	return $message;
}

add_filter( 'mt_gateway', 'mt_gateway_stripe', 10, 3 );
/*
 * Generates purchase form to be displayed under shopping cart confirmation.
 *
 * @param string $form Existing form.
 * @param string $gateway name of gateway
 * @param array  $args data for current cart
 *
 * @return string
 */
function mt_gateway_stripe( $form, $gateway, $args ) {
	if ( 'stripe' == $gateway || 'ideal' == $gateway || 'iban' == $gateway ) {
		$options    = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
		$payment_id = $args['payment'];
		$amount     = (float) $args['total'];
		$handling   = ( isset( $options['mt_handling'] ) ) ? (float) $options['mt_handling'] : 0;
		$shipping   = ( 'postal' == $args['method'] ) ? (float) $options['mt_shipping'] : 0;
		$total      = ( mt_zerodecimal_currency() ) ? ( $amount + $handling + $shipping ) : ( $amount + $handling + $shipping ) * 100;
		$purchaser  = get_the_title( $payment_id );

		$url  = mt_replace_http( add_query_arg( 'mt_stripe_ipn', 'true', trailingslashit( home_url() ) ) );
		if ( 'stripe' == $gateway ) {
			$form = mt_stripe_form( $url, $payment_id, $total, $args );
		}
		if ( 'ideal' == $gateway ) {
			$form = mt_stripe_form( $url, $payment_id, $total, $args, 'ideal' );
		}
		if ( 'iban' == $gateway ) {
			$form = mt_stripe_form( $url, $payment_id, $total, $args, 'iban' );
		}
	}

	return $form;
}

/**
 * Set up form for making a Stripe payment.
 *
 * @param string  $url $url to send query to. (Unused)
 * @param integer $payment_id ID for this payment.
 * @param float   $total Total amount of payment.
 * @param array   $args Payment arguments.
 * @param string  $method Method of payment selected.
 *
 * @return string.
 */
function mt_stripe_form( $url, $payment_id, $total, $args, $method = 'stripe' ) {
	$options = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
	// The form only displays after a POST request, and these fields are required.
	$name  = $_POST['mt_fname'] . ' ' . $_POST['mt_lname'];
	$email = $_POST['mt_email'];
	$nonce = wp_create_nonce( 'my-tickets-stripe' );

	$stripe_options = $options['mt_gateways']['stripe'];
	$purchase_page  = get_permalink( $options['mt_purchase_page'] );

	// check if we are using test mode
	if ( isset( $stripe_options['test_mode'] ) && 'true' == $stripe_options['test_mode'] ) {
		$secret_key = trim( $stripe_options['test_secret'] );
	} else {
		$secret_key = trim( $stripe_options['prod_secret'] );
	}
	if ( ! $secret_key ) {
		wp_die( __( 'Your Stripe API keys have not been set. Generate your API keys at Stripe.com', 'my-tickets-stripe' ) );
	}
	// If blog name not provided, use URL.
	$remove   = array( '<', '>', '"', '\'' );
	$host     = parse_url( home_url() );
	$blogname = ( '' === trim( get_bloginfo( 'name' ) ) ) ? $host['host'] : get_bloginfo( 'name' );

	\Stripe\Stripe::setApiKey( $secret_key );
	$intent = \Stripe\PaymentIntent::create([
		'amount'               => $total,
		'currency'             => $options['mt_currency'],
		'payment_method_types' => ['card'],
		'statement_descriptor' => strtoupper( substr( sanitize_text_field( str_replace( $remove, '', $blogname ) ), 0, 22 ) ),
		'metadata'             => array( 'payment_id' => $payment_id ),
		'description'          => sprintf( __( 'Tickets Purchased from %s', 'my-tickets-stripe' ), get_bloginfo( 'name' ) ),
	]);

	$form  = '<form id="mt-payment-form" action="/charge" method="post">
	<div class="mt-stripe-hidden-fields">
		<input type="hidden" name="_wp_stripe_nonce" value="' . esc_attr( $nonce ) . '" />
		<input type="hidden" name="_mt_action" value="' . esc_attr( $method ) . '" />
		<input type="hidden" name="_mt_secret" id="mt_client_secret" value="' . $intent->client_secret . '" />
		<input type="hidden" name="payment_id" id="mt-payment-id" value="' . esc_attr( $payment_id ) . '" />
		<input type="hidden" name="amount" value="' . esc_attr( $total ) . '" />
	</div>
	<div class="stripe">';
	// Hidden form fields
	$form .= apply_filters( 'mt_stripe_form', '', 'stripe', $args );
	if ( 'stripe' === $method ) {
		$form .= "<div id='mt-card'>
				<p class='form-row'>
				  <label for='mt_name'>" . __( 'Name', 'my-tickets-stripe' ) . "</label><input id='mt_name' name='name' value='". esc_attr( $name ) . "' required>
				</p>
				<p class='form-row'>
				  <label for='mt_email'>" . __( 'Email Address', 'my-tickets-stripe' ) . "</label><input id='mt_email' name='email' type='email' value='" . esc_attr( $email ) . "'  required>
				</p>
				<div class='form-row'>
					<label for='mt-card-element'>" . __( 'Credit or debit card', 'my-tickets-stripe' ) . "</label>
					<div id='mt-card-element'></div>
				</div>
			</div>";
	}
	// iban and ideal are not currently available.
	if ( 'iban' == $method ) {
		$form .= '
			<div id="mt-iban">
				<div class="form-row inline">
					<div class="col">
					  <label for="mt_name">' . __( 'Name', 'my-tickets-stripe' ) . '</label><input id="mt_name" name="name" value="' . esc_attr( $name ) . '" required>
					</div>
					<div class="col">
					  <label for="mt_email">' . __( 'Email Address', 'my-tickets-stripe' ) . '</label><input id="mt_email" name="email" type="email" value="' . esc_attr( $email ) . '"  required>
					</div>
				</div>
				<div class="form-row">
					<label for="mt-iban-element">' . __( 'IBAN', 'my-tickets-stripe' ) . '</label>
					<div id="mt-iban-element">
					  <!-- A Stripe Element will be inserted here. -->
					</div>
				</div>
				<div id="bank-name" role="alert"></div>
				<div id="mandate-acceptance">
					<p>' . __( 'By providing your IBAN and confirming this payment, you are
					authorizing Rocketship Inc. and Stripe, our payment service
					provider, to send instructions to your bank to debit your account and
					your bank to debit your account in accordance with those instructions.
					You are entitled to a refund from your bank under the terms and
					conditions of your agreement with your bank. A refund must be claimed
					within 8 weeks starting from the date on which your account was debited.', 'my-tickets-stripe' ) . '</p>
				</div>
			</div>';
	}
	if ( 'ideal' == $method ) {
		$form .= '
			<div id="mt-ideal">
				<div class="form-row">
					<label for="name">' . __( 'Name', 'my-tickets-stripe' ) . '</label>
					<input id="name" name="name" value="' . esc_attr( $name ) . '" required>
				</div>

				<div class="form-row">
					<label for="mt-ideal-bank-element">' . __( 'iDEAL Bank', 'my-tickets-stripe' ) . '</label>
					<div id="mt-ideal-bank-element">
					  <!-- A Stripe Element will be inserted here. -->
					</div>
				</div>
			</div>';
	}
	// Ability to disable billing address.
	if ( ! isset( $stripe_options['disable_address'] ) || 'off' !== $stripe_options['disable_address'] ) {
	$form .= '<div class="address section">
		<fieldset>
		<legend>' . __( 'Billing Address', 'my-tickets-stripe' ) . '</legend>
			<p class="form-row">
				<label for="address1">' . __( 'Street', 'my-tickets-stripe' ) . '</label>
				<input type="text" id="address1" name="card_address" class="card-address" />
			</p>
			<p class="form-row">
				<label for="address2">' . __( 'Street (2)', 'my-tickets-stripe' ) . '</label>
				<input type="text" id="address2" name="card_address_2" class="card-address-2" />
			</p>
			<p class="form-row">
				<label for="card_city">' . __( 'City', 'my-tickets-stripe' ) . '</label>
				<input type="text" id="card_city" name="card_city" class="card-city" />
			</p>
			<p class="form-row">
				<label for="card_state">' . __( 'State/Province', 'my-tickets-stripe' ) . '</label>
				<input type="text" id="card_state" name="card_state" class="card-state" />
			</p>
			<p class="form-row">
				<label for="card_zip">' . __( 'Postal Code', 'my-tickets-stripe' ) . '</label>
				<input type="text" id="card_zip" name="card_zip" class="card-zip" />
			</p>
			<p class="form-row">
				<label for="card_country">' . __( 'Country', 'my-tickets-stripe' ) . '</label>
					<select name="card_country" id="card_country" class="mt_country">
					<option value="">Select Country</option>
					' . mt_shipping_country() . '
					</select>
			</p>
		</fieldset>
		</div>';
	}
	$form .= mt_render_field( 'address', 'stripe' );
	$form .= "<div id='mt-card-errors' class='mt-stripe-errors' role='alert'></div>";
	$form .= "<input type='submit' name='stripe_submit' id='mt-stripe-submit' class='button button-primary' value='" . esc_attr( apply_filters( 'mt_gateway_button_text', __( 'Pay Now', 'my-tickets' ), 'stripe' ) ) . "' />";
	$form .= apply_filters( 'mt_stripe_form', '', 'stripe', $args );
	$form .= '</div></form>';

	return $form;
}

add_action( 'wp_enqueue_scripts', 'mt_stripe_enqueue_scripts' );
/**
 * Enqueue Stripe payment gateway scripts.
 */
function mt_stripe_enqueue_scripts() {
	$options = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
	$page    = $options['mt_purchase_page'];
	global $mt_stripe_version;
	if ( is_singular() ) {
		$stripe_options = isset( $options['mt_gateways']['stripe'] ) ? $options['mt_gateways']['stripe'] : array();
		if ( ! empty( $stripe_options ) ) {
			// check if we are using test mode.
			if ( isset( $stripe_options['test_mode'] ) && 'true' == $stripe_options['test_mode'] ) {
				$publishable = trim( $stripe_options['test_public'] );
			} else {
				$publishable = trim( $stripe_options['prod_public'] );
			}
			wp_enqueue_style( 'mt.stripe.css', plugins_url( 'css/stripe.css', __FILE__ ) );
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'stripe', 'https://js.stripe.com/v3/' );
			wp_enqueue_script( 'mt.stripe', plugins_url( 'js/stripe.js', __FILE__ ), array( 'jquery' ), $mt_stripe_version, true );
			$return_url = add_query_arg(
				array(
					'response_code' => 'thanks',
					'gateway'       => 'stripe',
					'payment_id'    => '%d',
				),
				get_permalink( $options['mt_purchase_page'] )
			);
			$security = wp_create_nonce( 'mts_ajax_stripe' );
			wp_localize_script(
				'mt.stripe',
				'mt_stripe', 
				array( 
					'publishable_key'     => $publishable,
					'currency'            => $options['mt_currency'],
					'purchase_descriptor' => __( 'Ticket Order', 'my-tickets-stripe' ),
					'return_url'          => $return_url,
					'selected'            => __( 'Selected', 'my-tickets-stripe' ),
					'processing'          => __( 'Processing...', 'my-tickets-stripe' ),
					'pay'                 => __( 'Pay Now', 'my-tickets-stripe' ),
					'success'             => __( 'Successful Payment', 'my-tickets-stripe' ),
					'mts_ajax_action'     => 'mts_ajax_stripe',
					'ajaxurl'             => admin_url( 'admin-ajax.php' ),
					'security'            => $security,
				)
			);
		}
	}
}


add_action( 'wp_ajax_mts_ajax_stripe', 'mts_ajax_stripe' );
add_action( 'wp_ajax_nopriv_mts_ajax_stripe', 'mts_ajax_stripe' );
/**
 * AJAX query sending request to update Shipping Address.
 */
function mts_ajax_stripe() {
	if ( isset( $_REQUEST['action'] ) && 'mts_ajax_stripe' === $_REQUEST['action'] ) {
		if ( ! wp_verify_nonce( $_REQUEST['security'], 'mts_ajax_stripe' ) ) {
			die( __( 'Security verification failed', 'my-tickets-stripe' ) );
		}
		$payment_id = absint( $_REQUEST['payment_id'] );
		$address    = $_REQUEST['address'];
		$meta       = update_post_meta( $payment_id, '_mts_shipping', $address );
		if ( false === $meta ) {
			wp_send_json(
				array(
					'response' => 0,
				)
			);
		} else {
			wp_send_json(
				array(
					'response' => 1,
				)
			);
		}
	}
}

add_action( 'mt_license_fields', 'mt_stripe_license_field' );
/**
 * Insert license key field onto license keys page.
 *
 * @param $fields string Existing fields.
 *
 * @return string
 */
function mt_stripe_license_field( $fields ) {
	$field  = 'mt_stripe_license_key';
	$active = ( 'valid' == get_option( 'mt_stripe_license_key_valid' ) ) ? ' <span class="license-activated">(active)</span>' : '';
	$name   =  __( 'My Tickets: Stripe', 'my-tickets-stripe' );
	return $fields . "
	<p class='license'>
		<label for='$field'>$name$active</label><br/>
		<input type='text' name='$field' id='$field' size='60' value='" . esc_attr( trim( get_option( $field ) ) ) . "' />
	</p>";
}

add_action( 'mt_save_license', 'mt_stripe_save_license', 10, 2 );
/**
 * Save license key.
 *
 * @param string $response Existing responses.
 * @param array  $post POST data.
 *
 * @return string New response.
 */
function mt_stripe_save_license( $response, $post ) {
	$field  = 'mt_stripe_license_key';
	$name   =  __( 'My Tickets: Stripe', 'my-tickets-stripe' );
	$verify = mt_verify_key( $field, EDD_MT_STRIPE_ITEM_NAME, EDD_MT_STRIPE_STORE_URL );
	$verify = "<li>$verify</li>";

	return $response . $verify;
}

// these are existence checkers. Exist if licensed.
if ( 'valid' == get_option( 'mt_stripe_license_key_valid' ) ) {
	/**
	 * I don't believe this is being used.
	 *
	 * @return boolean
	 */
	function mt_stripe_valid() {
		return true;
	}
} else {
	add_action( 'admin_notices', 'mt_stripe_licensed' );
}

/**
 * Display admin notice if license not provided.
 */
function mt_stripe_licensed() {
	global $current_screen;
	if ( stripos( $current_screen->id, 'my-tickets' ) ) {
		// Translators: Settings page URL.
		$message = sprintf( __( "Please <a href='%s'>enter your My Tickets: Stripe license key</a> to be eligible for support.", 'my-tickets-stripe' ), admin_url( 'admin.php?page=my-tickets#mt_stripe_license_key' ) );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		} else {
			echo "<div class='error'><p>$message</p></div>";
		}
	}
}

add_action( 'admin_notices', 'mt_stripe_requires_ssl' );
/**
 * Stripe only functions under SSL. Notify user that this is required.
 */
function mt_stripe_requires_ssl() {
	global $current_screen;
	if ( stripos( $current_screen->id, 'my-tickets' ) ) {
		if ( 0 === stripos( home_url(), 'https' ) ) {
			return;
		} else {
			echo "<div class='error'><p>" . __( 'Stripe requires an SSL Certificate. Please switch your site to HTTPS. <a href="https://websitesetup.org/http-to-https-wordpress/">How to switch WordPress to HTTPS</a>', 'my-tickets-stripe' ) . '</p></div>';
		}
	}
}
