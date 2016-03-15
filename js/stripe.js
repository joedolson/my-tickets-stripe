Stripe.setPublishableKey(mt_stripe.publishable_key);
function stripeResponseHandler(status, response) {
	
    if ( response.error ) {
		// show errors returned by Stripe
        jQuery(".payment-errors").html( response.error.message ).addClass( 'active' ).focus();
		// re-enable the submit button
		jQuery('#mt-stripe-submit').attr("disabled", false);
    } else {
        var payment_form = jQuery("#my-tickets-stripe-payment-form");
        // token contains id, last4, and card type
        var token = response['id'];
        // insert the token into the form so it gets submitted to the server
        payment_form.append("<input type='hidden' class='stripeToken' name='stripeToken' value='" + token + "'/>");
        // and submit
		payment_form.get(0).submit();
    }
}
jQuery(document).ready(function($) {
	$("#my-tickets-stripe-payment-form").submit( function(event) {

	// disable the submit button to prevent repeated clicks
		$('#mt-stripe-submit').attr( "disabled", "disabled" );

		// send the card details to Stripe
		Stripe.createToken( {
			number: $('.card-number').val(),
			cvc: $('.card-cvc').val(),
			exp_month: $('.card-expiry-month').val(),
			exp_year: $('.card-expiry-year').val(),
			address_city: $( '.card-city' ).val(),
			address_country: $( '.card-country' ).val(),
			address_line1: $( '.card-address' ).val(),
			address_line2: $( '.card-address-2' ).val(),
			address_state: $( '.card-state' ).val(),
			address_zip: $( '.card-zip' ).val(),
			name: $( '.card-name' ).val()
		}, stripeResponseHandler );

		// prevent the form from submitting with the default action
		return false;
	});
});