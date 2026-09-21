=== Hexa JPN Tools ===
Contributors: michaelperes
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later

JPN event management, host tools, and the authenticated Code.Hexa integration contract.

== Description ==

Hexa JPN Tools owns the JPN Miami WordPress event workflow and provides a
versioned REST contract for Code.Hexa using WordPress Application Passwords.

== Changelog ==

= 1.0.1 =
* Aligns the live REST fixture with the integration-capability access contract.

= 1.0.0 =
* Migrates JPN-specific behavior from jpn-structure into an isolated plugin.
* Adds the authenticated hexa-jpn/v1 health, manifest, settings, events, hosts, and operations contract.
* Reuses Hexa WordPress Plugin Core for runtime and update behavior.
