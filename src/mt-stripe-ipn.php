<?php
/**
 * My Tickets: Stripe - IPN
 *
 * @category Functionality
 * @package  My Tickets: Stripe
 * @author   Joe Dolson
 * @license  GPLv2 or later
 * @link     https://www.joedolson.com/my-tickets-stripe/
 */

add_action( 'mt_receive_ipn', 'mt_stripe_ipn' );
/**
 * Process events sent from from Stripe
 *
 * Handles the charge.refunded event & source.chargeable events.
 * Uses do_action( 'mt_stripe_event', $charge ) if you want custom handling.
 */
function mt_stripe_ipn() {
	if ( isset( $_REQUEST['mt_stripe_ipn'] ) && 'true' === $_REQUEST['mt_stripe_ipn'] ) {
		global $mt_stripe_version;

		$options = array_merge( mt_default_settings(), get_option( 'mt_settings' ) );
		// these all need to be set from Stripe data.
		$stripe_options = $options['mt_gateways']['stripe'];
		if ( isset( $stripe_options['test_mode'] ) && 'true' === $stripe_options['test_mode'] ) {
			$secret_key = trim( $stripe_options['test_secret'] );
		} else {
			$secret_key = trim( $stripe_options['prod_secret'] );
		}
		if ( ! $secret_key ) {
			return;
		}
		\Stripe\Stripe::setAppInfo(
			'WordPress MyTicketsStripe',
			$mt_stripe_version,
			'https://www.joedolson.com/contact/'
		);
		\Stripe\Stripe::setApiKey( $secret_key );
		\Stripe\Stripe::setApiVersion( '2019-08-14' );

		// retrieve the request's body and parse it as JSON.
		$body = @file_get_contents( 'php://input' );

		// grab the event information.
		$event_json = json_decode( $body );
		if ( ! is_object( $event_json ) ) {
			status_header( 418 );
			die;
		}
		// this will be used to retrieve the event from Stripe.
		$event_id = $event_json->id;

		if ( isset( $event_json->id ) ) {

			try {
				// to verify this is a real event, we re-retrieve the event from Stripe.
				$event  = \Stripe\Event::retrieve( $event_id );
				$object = $event->data->object->object;

				switch ( $object ) {
					case 'charge':
						$object     = $event->data->object;
						$payment_id = $object->metadata->payment_id;
						break;
					case 'payment_intent':
						$object     = $event->data->object;
						$payment_id = $object->metadata->payment_id;
						$email      = get_post_meta( $payment_id, '_email', true );
						break;
					default:
						// Need to return 200 on all other situations.
						status_header( 200 );
						die;
				}
				do_action( 'mt_stripe_event', $object );

			} catch ( Exception $e ) {
				if ( function_exists( 'mt_log_error' ) ) {
					mt_log_error( $e );
				}
				// Return an HTTP 202 (Accepted) to stop repeating this event.
				// An error is thrown if an event is sent to the site for a transaction in test mode after the site is switched to live mode or vice versa.
				status_header( 202 );
				die;
			}
			switch ( $event->type ) {
				case 'charge.refunded':
					$status = get_post_meta( $payment_id, '_is_paid', true );
					$partial = ( $object->refunded ) ? false : true;
					if ( $partial ) {
						$details = array(
							'id'     => $payment_id,
							'name'   => get_the_title( $payment_id ),
							'email'  => get_post_meta( $payment_id, '_email', true ),
							'amount' => ( mt_zerodecimal_currency() ) ? $object->amount_refunded : $object->amount_refunded / 100,
						);
						$template = apply_filters( 'mt_stripe_partial_refund_email', __( 'A partial refund on your purchase has been administered. The refund should appear on your credit card statement within 5-10 days. Refunded amount: {amount}.', 'my-tickets-stripe' ) );
						$body     = mt_draw_template( $details, $template );
						$sitename = get_bloginfo( 'name' );
						wp_mail( $details['email'], sprintf( __( 'Partial Refund from %s', 'my-tickets-stripe' ), $sitename ), $body );
						status_header( 200 );
					} else {
						if ( ! ( 'Refunded' === $status ) ) {
							update_post_meta( $payment_id, '_is_paid', 'Refunded' );
							$details = array(
								'id'    => $payment_id,
								'name'  => get_the_title( $payment_id ),
								'email' => get_post_meta( $payment_id, '_email', true ),
							);
							mt_send_notifications( 'Refunded', $details );
							status_header( 200 );
						} else {
							status_header( 202 );
						}
					}
					die;
					break;
				// Successful payment.
				case 'payment_intent.succeeded':
					$status = get_post_meta( $payment_id, '_is_paid', true );
					if ( ! ( 'Completed' === $status ) ) {
						$paid           = $object->amount_received;
						$transaction_id = $object->id;
						$receipt_id     = get_post_meta( $payment_id, '_receipt', true );
						$payment_status = 'Completed';
						$payer_name     = get_the_title( $payment_id );
						$names          = explode( ' ', $payer_name );
						$first_name     = array_shift( $names );
						$last_name      = implode( ' ', $names );
						$bill_address   = array(
							'street'  => $object->charges->data[0]->billing_details->address->line1,
							'street2' => $object->charges->data[0]->billing_details->address->line2,
							'city'    => $object->charges->data[0]->billing_details->address->city,
							'state'   => $object->charges->data[0]->billing_details->address->state,
							'country' => $object->charges->data[0]->billing_details->address->country,
							'code'    => $object->charges->data[0]->billing_details->address->postal_code,
						);
						// This is temporary; need to get it somehow.
						$shipping_address = get_post_meta( $payment_id, '_mts_shipping', true );
						if ( $shipping_address ) {
							$ship_address = array(
								'street'  => strip_tags( $shipping_address['street'] ),
								'street2' => strip_tags( $shipping_address['street2'] ),
								'city'    => strip_tags( $shipping_address['city'] ),
								'state'   => strip_tags( $shipping_address['state'] ),
								'country' => strip_tags( $shipping_address['country'] ),
								'code'    => strip_tags( $shipping_address['code'] ),
							);
						} else {
							$ship_address = array();
						}

						$price = ( mt_zerodecimal_currency() ) ? $paid : $paid / 100;
						$data  = array(
							'transaction_id' => $transaction_id,
							'price'          => $price,
							'currency'       => $options['mt_currency'],
							'email'          => $email,
							'first_name'     => $first_name, // get from charge.
							'last_name'      => $last_name, // get from charge.
							'status'         => $payment_status,
							'purchase_id'    => $payment_id,
							'shipping'       => $ship_address,
						);
						mt_handle_payment( 'VERIFIED', '200', $data, $_REQUEST );
					}
					status_header( 200 );
					die;
					break;
				default:
					status_header( 200 );
					die;
			}
		} else {
			status_header( 400 );
			die;
		}
	}

	return;
}

/**
 * Set cURL to use SSL version supporting TLS 1.2
 *
 * @param object $handle CURL object.
 */
function mts_http_api_curl( $handle ) {
	curl_setopt( $handle, CURLOPT_SSLVERSION, 6 );
}
add_action( 'http_api_curl', 'mts_http_api_curl' );
