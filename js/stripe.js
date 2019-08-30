// Create a Stripe client.
(function ($) {

	var stripe = Stripe( mt_stripe.publishable_key );
	var elements = stripe.elements();
	var amount = parseFloat( $( '.mt_cart_total .mt_total_number .price' ).text() ) * 100;

	var cardExists = document.getElementById( 'mt-card-element' );
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
			var displayError = document.getElementById('card-errors');
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

			stripe.createToken(card).then(function(result) {
				if (result.error) {
					// Inform the customer that there was an error.
					var errorElement = document.getElementById('mt-card-errors');
					errorElement.textContent = result.error.message;
				} else {
					// Send the token to your server.
					stripeTokenHandler(result.token);
				}
			});
		});

		function stripeTokenHandler(token) {
			// Insert the token ID into the form so it gets submitted to the server
			var form = document.getElementById('mt-payment-form');
			var hiddenInput = document.createElement('input');
			hiddenInput.setAttribute('type', 'hidden');
			hiddenInput.setAttribute('name', 'stripeToken');
			hiddenInput.setAttribute('value', token.id);
			form.appendChild(hiddenInput);

			// Submit the form
			form.submit();
		}
	}

	var idealExists = document.getElementById( 'mt-ideal-bank-element' );
	if ( idealExists !== null ) {

		var style = {
			base: {
				// Add your base input styles here. For example:
				fontSize: '16px',
				color: "#32325d",
			}
		};

		// Create an instance of the idealBank Element.
		var idealBank = elements.create('idealBank', {style: style});

		// Add an instance of the idealBank Element into the `ideal-bank-element` <div>.
		idealBank.mount('#mt-ideal-bank-element');

		var errorMessage = document.getElementById('mt-error-message');

		// Create a source or display an error when the form is submitted.
		var form = document.getElementById('mt-payment-form');

		form.addEventListener('submit', function(event) {
			event.preventDefault();

			var sourceData = {
				type: 'ideal',
				amount: amount,
				currency: mt_stripe.currency,
				statement_descriptor: mt_stripe.purchase_descriptor + ' #' + purchase_id,
				owner: {
					name: document.querySelector('input[name="name"]').value,
				},
				// Specify the URL to which the customer should be redirected after paying.
				redirect: {
					return_url: mt_stripe.return_url,
				},
			};

			// Call `stripe.createSource` with the idealBank Element and
			// additional options.
			stripe.createSource(idealBank, sourceData).then(function(result) {
				if (result.error) {
					// Inform the customer that there was an error.
					var errorElement = document.getElementById('error-message');
					errorElement.textContent = error.message;
				} else {
					// Redirect the customer to the authorization URL.
					stripeSourceHandler(result.source);
				}
			});
		});

		function stripeSourceHandler(source) {
			// Redirect the customer to the authorization URL.
			document.location.href = source.redirect.url;
		}
	}

	var ibanExists = document.getElementById( 'mt-iban-element' );
	if ( ibanExists !== null ) {

		var style = {
			base: {
				// Add your base input styles here. For example:
				fontSize: '16px',
				color: "#32325d",
			}
		};

		// Create an instance of the iban Element.
		var iban = elements.create('iban', {
			style: style,
			supportedCountries: ['SEPA'],
		});

		// Add an instance of the iban Element into the `iban-element` <div>.
		iban.mount('#mt-iban-element');

		var errorMessage = document.getElementById('mt-error-message');
		var bankName = document.getElementById('bank-name');

		iban.on('change', function(event) {
			// Handle real-time validation errors from the iban Element.
			if (event.error) {
				errorMessage.textContent = event.error.message;
				errorMessage.classList.add('visible');
			} else {
				errorMessage.classList.remove('visible');
			}

			// Display bank name corresponding to IBAN, if available.
			if (event.bankName) {
				bankName.textContent = event.bankName;
				bankName.classList.add('visible');
			} else {
				bankName.classList.remove('visible');
			}
		});

		var form = document.getElementById('mt-payment-form');
		form.addEventListener('submit', function(event) {
			event.preventDefault();

			var sourceData = {
				type: 'sepa_debit',
				currency: 'eur',
				owner: {
					name: document.querySelector('input[name="name"]').value,
					email: document.querySelector('input[name="email"]').value,
				},
				mandate: {
					// Automatically send a mandate notification email to your customer
					// once the source is charged.
					notification_method: 'email',
				},
			};

			// Call `stripe.createSource` with the IBAN Element and additional options.
			stripe.createSource(iban, sourceData).then(function(result) {
				if (result.error) {
					// Inform the customer that there was an error.
					var errorElement = document.getElementById('mt-error-message');
					errorElement.textContent = result.error.message;
				} else {
					// Send the Source to your server.
					stripeSourceHandler(result.source);
				}
			});
		});

		function stripeSourceHandler(source) {
			// Insert the Source ID into the form so it gets submitted to the server.
			var form = document.getElementById('mt-payment-form');
			var hiddenInput = document.createElement('input');
			hiddenInput.setAttribute('type', 'hidden');
			hiddenInput.setAttribute('name', 'stripeSource');
			hiddenInput.setAttribute('value', source.id);
			form.appendChild(hiddenInput);

			// Submit the form.
			form.submit();
		}
	}

}(jQuery));