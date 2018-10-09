=== My Tickets: Stripe ===
Contributors: joedolson
Donate link: http://www.joedolson.com/my-tickets/add-ons/
Tags: my-tickets, stripe
Requires at least: 4.4
Tested up to: 4.9
Requires PHP: 5.3
Stable tag: trunk

Support for Stripe in My Tickets.

== Description ==

Support for Stripe payment gateway transactions using My Tickets.

New or updated translations are always appreciated. The translation files are included in the download. 

== Installation ==

1. Upload the `/my-tickets-stripe/` directory into your WordPress plugins directory.

2. Activate the plugin on your WordPress plugins page

3. Go to My Tickets > Payment Settings and configure the Stripe payment gateway.

== Changelog ==

= 1.1.2 =

* Fix script call: does not need to require a Page to load scripts, just any singular context.

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
