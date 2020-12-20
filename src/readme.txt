=== My Tickets: Stripe ===
Contributors: joedolson
Donate link: http://www.joedolson.com/my-tickets/add-ons/
Tags: my-tickets, stripe
Requires at least: 4.4
Tested up to: 5.6
Requires PHP: 5.6
Stable tag: 1.2.9

Support for Stripe in My Tickets.

== Description ==

Support for Stripe payment gateway transactions using My Tickets.

New or updated translations are always appreciated. The translation files are included in the download. 

== Installation ==

1. Upload the `/my-tickets-stripe/` directory into your WordPress plugins directory.

2. Activate the plugin on your WordPress plugins page

3. Go to My Tickets > Payment Settings and configure the Stripe payment gateway.

== Changelog ==

= 1.2.10 =

* Bug fix: Event-specific references used a function accessing data that doesn't exist yet at payment creation.
* Implement PHP tests.
* Change main plug-in file name.

= 1.2.9 =

* Change: pass event-specific references in purchase description.
* Change: Add option to refresh Stripe webhooks.
* Changes to webhook terminology for consistency.

= 1.2.8 =

* Bug fix: Incorrect Stripe class_exists check.
* Add translation: French.

= 1.2.7 =

* Bug fix: Disabled redirect from purchase screen.
* Improvement: Store intent ID to avoid creating un-completable payment requests.

= 1.2.6 =

* Bug fix: Stray character.

= 1.2.5 =

* Change: Add processing animation to show that card transaction still in progress.
* Change: Add delay on JS redirect to give Stripe more time to communicate with server.
* Bug fix: Prevent PHP notice: only save Stripe settings on payment settings page.

= 1.2.4 =

* Bug fix: Really stupid invalid comparison error.

= 1.2.3 =

* Bug fix: Better communication abouot webhook endpoints.
* Bug fix: Better handling of event errors and unhandled events.

= 1.2.2 =

* Bug fix: Don't duplicate processing of events if status already updated.
* Bug fix: Return 200 as default event response; only return 400 on errors.
* Bug fix: Catch errors if webhook ID did not exist.

= 1.2.1 =

* Bug fix: If a site does not have a blog name defined, use their URL to generate the statement descriptor.
* Bug fix: Payment may not be finalized immediately. Provide better description of status if not.
* Bug fix: If Stripe is already loaded by another plug-in, don't load it again.
* Bug fix: Don't assume Stripe settings already exist when configuring.
* Bug fix: Don't attempt payment if Stripe API keys are not configured.
* Bug fix: 'Successful Payment' text not localized.
* Bug fix: Billing address passed as Shipping address.

= 1.2.0 =

* Major Update: Change Stripe API usage to conform to Strong Customer Authentication rules
* Fix issue with missing shipping fields.
* Option to offer card payment without requesting billing address.
* Automatic configuration of webhook endpoint. 
* Re-order API key fields to match Stripe ordering.
* Improve customer information in Stripe admin.
* Update plugin updater class.

= 1.1.5 =

* Option to define custom label for gateway selector.
* Update .pot

= 1.1.4 =

* With correct function, must not pass argument.

= 1.1.3 =

* Previous script fix was incorrect; use is_singular, not is_single.

= 1.1.2 =

* Fix script call: does not need to require a Page to load scripts, just any singular context.
* Add .pot file for translators.

= 1.1.1 =

* Fix syntax error in 1.1.0

= 1.1.0 =

* Bug fix: Need to test for 0, not falsey value in stripos
* Bug fix: Payment ID parameter in URL should be 'payment_id', not 'payment'
* Bug fix: Add statement descriptor
* Change: Add purchase description
* Code sniffs & style changes.

= 1.0.3 =

* Bug fix: don't declare class if Stripe class already declared.
* Constrain admin notices to My Tickets settings.
* Add admin notice to require SSL.
* Update code style.

= 1.0.2 =

* Bug fix: Incorrect call to Stripe secret value in some contexts
* Bug fix: Correctly call transaction ID & payer name when passing data to save from Stripe.

= 1.0.1 =

* Bug fix: Incorrect variable comparison caused notice about license validation to always show
* Bug fix: Incorrect call to Stripe public key in some contexts
* Bug fix: Improved value comparison in switching between live and test mode for Stripe

= 1.0.0 =

* Initial release.

== Frequently Asked Questions ==

= Hey! Why don't you have any Frequently Asked Questions here! =

Brand new plug-in - nothing asked yet!

== Screenshots ==

== Upgrade Notice ==

* Following this update, you will need to re-activate the plug-in, due to a filename change.