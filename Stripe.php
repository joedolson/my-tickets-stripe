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
 * Version:     1.2.0
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
$mt_stripe_version = '1.2.0';
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
require_once( 'stripe-php/init.php' );

add_action( 'mt_receive_ipn', 'mt_stripe_ipn' );
/**
 * Process events sent from from Stripe
 *
 * The only event this currently handles is the charge.refunded event.
 * Uses do_action( 'mt_stripe_event', $charge ) if you want custom handling.
 *
 */
function mt_stripe_ipn() {
	if ( isset( $_REQUEST['mt_stripe_ipn'] ) && 'true' == $_REQUEST['mt_stripe_ipn'] ) {
		$options = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
		// these all need to be set from Stripe data.
		$stripe_options = $options['mt_gateways']['stripe'];
		if ( isset( $stripe_options['test_mode'] ) && 'true' == $stripe_options['test_mode'] ) {
			$secret_key = trim( $stripe_options['test_secret'] );
		} else {
			$secret_key = trim( $stripe_options['prod_secret'] );
		}

		Stripe::setApiKey( $secret_key );

		// retrieve the request's body and parse it as JSON.
		$body = @file_get_contents( 'php://input' );

		// grab the event information.
		$event_json = json_decode( $body );

		// this will be used to retrieve the event from Stripe.
		$event_id = $event_json->id;

		if ( isset( $event_json->id ) ) {

			try {
				// to verify this is a real event, we re-retrieve the event from Stripe.
				$event      = Stripe_Event::retrieve( $event_id );
				$charge     = $event->data->object;
				$email      = $charge->metadata->email;
				$payment_id = $charge->metadata->payment_id;

				// successful payment.
				if ( 'charge.refunded' == $event->type ) {
					$details = array(
						'id'    => $payment_id,
						'name'  => get_the_title( $payment_id ),
						'email' => get_post_meta( $payment_id, '_email', true ),
					);
					mt_send_notifications( 'Refunded', $details );
					update_post_meta( $payment_id, '_is_paid', 'Refunded' );
				}

				do_action( 'mt_stripe_event', $charge );

			} catch ( Exception $e ) {
				if ( function_exists( 'mt_log_error' ) ) {
					mt_log_error( $e );
				}
			}
		}
	}

	return;
}

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

add_filter( 'mt_setup_gateways', 'mt_setup_stripe', 10, 1 );
/**
 * Set up stripe in options.
 *
 * @param array $gateways Gateways installed.
 *
 * @return updated gateways
 */
function mt_setup_stripe( $gateways ) {
	// this key is how the gateway will be referenced in all contexts.
	$gateways['stripe'] = array(
		'label'  => __( 'Stripe', 'my-tickets-stripe' ),
		'fields' => array(
			'prod_secret' => __( 'API Secret Key (Production)', 'my-tickets-stripe' ),
			'prod_public' => __( 'API Publishable Key (Production)', 'my-tickets-stripe' ),
			'test_secret' => __( 'API Secret Key (Test)', 'my-tickets-stripe' ),
			'test_public' => __( 'API Publishable Key (Test)', 'my-tickets-stripe' ),
			'test_mode' => array(
				'label' => __( 'Test Mode Enabled', 'my-tickets-stripe' ),
				'type'  => 'checkbox',
				'value' => 'true',
			),
			'selector' => __( 'Gateway selector label', 'my-tickets' ),
		),
		// Translators: Stripe webhook URL for this site.
		'note' => sprintf( __( 'To enable automatic refund processing, add <code>%s</code> as a Webhook URL in your Stripe account at Stripe > Dashboard > Settings > Webhooks.', 'my-tickets-stripe' ), add_query_arg( 'mt_stripe_ipn', 'true', home_url() ) ),
	);

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

add_filter( 'mt_format_transaction', 'mt_stripe_transaction', 10, 2 );
/**
 * Alter return value of transactions if using stripe.
 *
 * @param array  $transaction Transaction data.
 * @param string $gateway Current gateway.
 *
 * @return array $transaction.
 */
function mt_stripe_transaction( $transaction, $gateway ) {
	if ( 'stripe' == $gateway ) {
		// alter return value if desired.
	}

	if ( 'iban' == $gateway ) {
		// iban gateway
	}

	if ( 'ideal' == $gateway ) {
		// ideal gateway
	}

	return $transaction;
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
			$receipt_id     = sanitize_text_field( $_GET['receipt_id'] );
			$transaction_id = sanitize_text_field( $_GET['transaction_id'] );
			$receipt        = esc_url( add_query_arg( array( 'receipt_id' => $receipt_id ), get_permalink( $options['mt_receipt_page'] ) ) );

			// Translators: Transaction ID from Stripe, URL to view receipt.
			return sprintf( __( 'Thank you for your purchase! Your Stripe transaction id is <code>#%1$s</code>. <a href="%2$s">View your receipt</a>', 'my-tickets-stripe' ), $transaction_id, $receipt );
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
	$name    = $_POST['mt_fname'] . ' ' . $_POST['mt_lname'];
	$email   = $_POST['mt_email'];
	
	$form  = '<form id="mt-payment-form" action="/charge" method="post"><div class="stripe">';
	// Hidden form fields
	$form .= "<input type='hidden' name='payment_id' value='" . esc_attr( $payment_id ) . "' />
		<input type='hidden' name='amount' value='$total' />";
	$form .= apply_filters( 'mt_stripe_form', '', 'stripe', $args );
	if ( 'stripe' == $method ) {
		$form .= "<div id='mt-card'>
				<div id='mt-card-errors' role='alert'></div>
				<div class='form-row'>
					<label for='mt-card-element'>" . __( 'Credit or debit card', 'my-tickets-stripe' ) . "</label>
					<div id='mt-card-element'></div>
				</div>
			</div>";
	}
	if ( 'iban' == $method ) {
		$form .= '
			<div id="mt-iban">
				<div id="mt-iban-errors" role="alert"></div>
				<div class="form-row inline">
					<div class="col">
					  <label for="name">' . __( 'Name', 'my-tickets-stripe' ) . '</label><input id="name" name="name" value="' . esc_attr( $name ) . '" required>
					</div>
					<div class="col">
					  <label for="email">' . __( 'Email Address', 'my-tickets-stripe' ) . '</label><input id="email" name="email" type="email" value="' . esc_attr( $email ) . '"  required>
					</div>
				</div>
				<div class="form-row">
					<label for="mt-iban-element">' . __( 'IBAN', 'my-tickets-stripe' ) . '</label>
					<div id="mt-iban-element">
					  <!-- A Stripe Element will be inserted here. -->
					</div>
				</div>
				<div id="bank-name"></div>
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
				<div id="mt-iban-errors" role="alert"></div>
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
	$form .= "<input type='submit' name='stripe_submit' id='mt-stripe-submit' class='button button-primary' value='" . esc_attr( apply_filters( 'mt_gateway_button_text', __( 'Pay Now', 'my-tickets' ), 'stripe' ) ) . "' />";
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
					'payment_id'    => $payment_id,
				),
				get_permalink( $options['mt_purchase_page'] )
			);
			wp_localize_script(
				'mt.stripe',
				'mt_stripe', 
				array( 
					'publishable_key'     => $publishable,
					'currency'            => $options['mt_currency'],
					'purchase_descriptor' => __( 'Ticket Order', 'my-tickets-stripe' );
					'return_url'          => $return_url,
				)
			);
		}
	}
}

add_action( 'init', 'my_tickets_stripe_process_payment' );
/**
 * Handle processing of payment.
 */
function my_tickets_stripe_process_payment() {
	if ( isset( $_POST['_mt_action']) && 'stripe' == $_POST['_mt_action'] && wp_verify_nonce( $_POST['_wp_stripe_nonce'], 'my-tickets-stripe' ) ) {
		// load the stripe libraries
		if ( ! class_exists( 'Stripe' ) ) {
			require_once( 'lib/Stripe.php' );
		}

 		$options        = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
		$stripe_options = $options['mt_gateways']['stripe'];
		$purchase_page  = get_permalink( $options['mt_purchase_page'] );

		// check if we are using test mode
		if ( isset( $stripe_options['test_mode'] ) && 'true' == $stripe_options['test_mode'] ) {
			$secret_key = trim( $stripe_options['test_secret'] );
		} else {
			$secret_key = trim( $stripe_options['prod_secret'] );
		}

		$payment_id  = absint( $_POST['payment_id'] );
		$payer_email = get_post_meta( $payment_id, '_email', true );
		$paid        = get_post_meta( $payment_id, '_total_paid', true );
		$payer_name  = get_the_title( $payment_id );
		$names       = explode( ' ', $payer_name );
		$first_name  = array_shift( $names );
		$last_name   = implode( ' ', $names );
		$amount      = ( mt_zerodecimal_currency() ) ? $paid : $paid * 100;
		$passed      = $_POST['amount'];
		// retrieve the token generated by stripe.js.
		$token       = $_POST['stripeToken'];
		$address     = array();
		// compare amounts from payment and from passage.
		if ( $amount != $passed ) {
			// probably fraudulent: user attempted to change the amount paid. Raise fraud flag?
		}
		// Create a statement descriptor.
		$remove               = array( '<', '>', '"', '\'' );
		$statement_descriptor = strtoupper( substr( sanitize_text_field( str_replace( $remove, '', get_bloginfo( 'name' ) ) ), 0, 22 ) );
		// attempt to charge the customer's card.
		try {
			Stripe::setApiKey( $secret_key );
			$charge = Stripe_Charge::create( array(
					'amount'               => $amount,
					'currency'             => $options['mt_currency'],
					'card'                 => $token,
					'description'          => sprintf( __( 'Tickets Purchased from %s', 'my-tickets-stripe' ), get_bloginfo( 'name' ) ),
					'statement_descriptor' => $statement_descriptor,
					'metadata'             => array(
						'email'      => $payer_email,
						'payment_id' => $payment_id,
					),
				)
			);

			$paid           = $charge->paid; // true if charge succeeded.
			$transaction_id = $charge->id;
			$receipt_id     = get_post_meta( $payment_id, '_receipt', true ); // where does that come from.

			//get charge object to look at.
			if ( isset( $_POST['mt_shipping_street'] ) ) {
				$address = array(
					'street'  => isset( $_POST['mt_shipping_street'] ) ? $_POST['mt_shipping_street'] : '',
					'street2' => isset( $_POST['mt_shipping_street2'] ) ? $_POST['mt_shipping_street2'] : '',
					'city'    => isset( $_POST['mt_shipping_city'] ) ? $_POST['mt_shipping_city'] : '',
					'state'   => isset( $_POST['mt_shipping_state'] ) ? $_POST['mt_shipping_state'] : '',
					'country' => isset( $_POST['mt_shipping_code'] ) ? $_POST['mt_shipping_code'] : '',
					'code'    => isset( $_POST['mt_shipping_country'] ) ? $_POST['mt_shipping_country'] : '',
				);
			}

			$payment_status = 'Completed';
			$redirect       = mt_replace_http( esc_url_raw( add_query_arg( array(
					'response_code'  => 'thanks',
					'gateway'        => 'stripe',
 					'transaction_id' => $transaction_id,
					'receipt_id'     => $receipt_id,
					'payment_id'     => $payment_id,
				), $purchase_page ) ) );

		} catch ( Exception $e ) {

			$body           = $e->jsonBody;
			$message        = $body['error']['message'];
			$payment_status = 'Failed';
			// redirect on failed payment.
			$redirect = mt_replace_http( esc_url_raw( add_query_arg( array(
					'response_code' => 'failed',
					'gateway'       => 'stripe',
					'payment_id'    => $payment_id,
					'reason'        => urlencode( $message ),
				), $purchase_page ) ) );
		}

		$price = ( mt_zerodecimal_currency() ) ? $amount : $amount / 100;
		$data  = array(
			'transaction_id' => $transaction_id,
			'price'          => $amount / 100,
			'currency'       => $options['mt_currency'],
			'email'          => $payer_email,
			'first_name'     => $first_name, // get from charge.
			'last_name'      => $last_name, // get from charge.
			'status'         => $payment_status,
			'purchase_id'    => $payment_id,
			'shipping'       => $address,
		);

		mt_handle_payment( 'VERIFIED', '200', $data, $_REQUEST );

		// redirect back to our previous page with the added query variable
		wp_safe_redirect( $redirect );
		exit;
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
