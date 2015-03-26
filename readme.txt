=== Plugin Name ===
Contributors: BorisColombier
Donate link: http://wba.fr/
Tags: WooCommerce, Payment, Gateway, Credit Cards, Shopping Cart, PayPlug, Extension
Requires at least: 3.0.1
Tested up to: 4.1.1
Stable tag: 1.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept all credit cards with PayPlug on your WooCommerce site via this secure WooCommerce gateway.

== Description ==

[PayPlug](https://www.payplug.fr/ "PayPlug") is a very low cost payment solution for businesses based in Europe.
Woocommerce PayPlug is the payment gateway for Woocommerce validated by PayPlug.


== Installation ==

1. Create a [free PayPlug account](http://url.wba.fr/payplug/ "free PayPlug account")
2. Install the plugin via FTP or the Wordpress manager
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to the Woocommerce settings and to the Payment Gateways tab
5. Click on PayPlug and set your PayPlug login and password


== Screenshots ==

1. Configuration screen
2. Public view

== Changelog ==

= 1.4.3 =
* Fix error if CURL_SSLVERSION_TLSv1 is an undefined constant on the server and server display PHP notices

= 1.4.2 =
* Add TEST (Sandbox) Mode
* delete settings on uninstall

= 1.4.1 =
* Security fix for ssl v3 vulnerability

= 1.4 =
* Plugin now compatible with all woocommerce version
* Fix deprecated function when using logs
* Add possibility to set Payplug parameters without CURL library installed on the server
* Add possibility to set orders as 'completed' after payment

= 1.3.2 =
* Fix for websites with http on public side and https on backoffice

= 1.3.1 =
* Fix for configuration page access

= 1.3 =
* Fix for woocommerce 2.1

= 1.2 =
* Fix authentication problem

= 1.1 =
* Fix jQuery problem

= 1.0 =
* First version