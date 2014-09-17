=== PMPro Invite Only ===
Contributors: strangerstudios
Tags: pmpro, membership, invite
Requires at least: 3.5
Tested up to: 3.9.1
Stable tag: .2

Make certain levels invite only. After checkout, members will receive invite codes to share with others.

== Description ==

This plugin currently requires Paid Memberships Pro. 

Users must have an invite code to sign up for certain levels. Users are given an invite code to share.

== Installation ==

1. Upload the `pmpro-invite-only` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Set the $pmpro_invite_only_levels array and (optional) PMPROIO_INVITE_CODES constant to set which levels should require and generate codes.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-invite-only/issues

== Changelog ==
= .2 =
* Added invite code to account page and confirmation emails.
* Handling the PayPal Express review page better now.
* Forced invite codes to be upper case.
* Now showing used codes and their users along with unused codes on the Account/Profile pages.
* Confirmation, Account, and Profile pages updated to use new code system.
* Added a bunch of new functions for getting, saving, checking, parsing, and displaying invite codes.
* Invite codes now store their parent's user ID in the code, to make checking valid codes faster.
* Added support for multiple codes generated at checkout. Admins can define this with the PMPROIO_INVITE_CODES constant.

= .1 =
* This is the initial version of the plugin.