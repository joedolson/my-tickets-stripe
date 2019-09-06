// Create a Stripe client.
(function ($) {

	var stripe = Stripe( mt_stripe.publishable_key );
	var elements = stripe.elements();
	var submitButton = document.getElementById( 'mt-stripe-submit' );
	var cardExists = document.getElementById( 'mt-card-element' );
	var payment_id = document.getElementById( 'mt-payment-id' ).value;

	if ( cardExists !== null ) {

		var style = {
			base: {
				// Add your base input styles here. For example:
				fontSize: '16px',
				color: "#32325d",
			}
		};

		// Create an instance of the card Element.
		var card = elements.create('card', {style: style});

		// Add an instance of the card Element into the `card-element` <div>.
		card.mount('#mt-card-element');
		
		card.addEventListener('change', function(event) {
			var displayError = document.getElementById('mt-card-errors');
			if (event.error) {
				displayError.textContent = event.error.message;
			} else {
				displayError.textContent = '';
			}
		});
		
		// Create a token or display an error when the form is submitted.
		var form = document.getElementById('mt-payment-form');

		form.addEventListener('submit', function(event) {
			event.preventDefault();
			var ownerInfo = {
				payment_method_data: {
					billing_details: {
						name: document.getElementById( 'mt_name' ).value,
						address: {
							line1: document.getElementById( 'address1' ).value,
							line2: document.getElementById( 'address2' ).value,
							city: document.getElementById( 'card_city' ).value,
							postal_code: document.getElementById( 'card_zip' ).value,
							country: document.getElementById( 'card_country' ).value,
						},
						email: document.getElementById( 'mt_email' ).value
					},
				},
			};
			submitButton.disabled = true;
			submitButton.value = mt_stripe.processing;
			var clientSecret = document.getElementById( 'mt_client_secret' ).value;
			console.log( clientSecret );

			stripe.handleCardPayment( clientSecret,card,ownerInfo).then(function(result) {
				if (result.error) {
					// Inform the customer that there was an error.
					var errorElement = document.getElementById('mt-card-errors');
					errorElement.textContent = result.error.message;
					errorElement.classList.add('visible');
					submitButton.disabled = false;
					submitButton.value = mt_stripe.pay;
				} else {
					var errorElement = document.getElementById('mt-card-errors');
					errorElement.textContent = 'Successful Payment';
					window.location = mt_stripe.return_url.replace( '%d', payment_id );
				}
			})
		});
	}

}(jQuery));