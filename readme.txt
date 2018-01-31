=== Paid Memberships Pro - Invite Only Add On ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, membership, invite
Requires at least: 3.5
Tested up to: 4.9.2
Stable tag: .3.4

Specify membership level(s) as "invite only" and provide members with invite codes to share after checkout.

== Description ==

The Invite Only Add On allows you to restrict membership signups for specific membership levels and require an invite code.

After completing their membership checkout, your member will receive a unique invite code that they can share with others. You can specify the number of uses on the invite code, make it unlimited, or give the member multiple single-use codes.

A list of used/unused invite code(s) is displayed on the Membership Account page, allowing a member to see who has used their code to register and manage the unused codes tied to their account.

The admin can increase the number of invites available for a user on the "Edit User" page.

Note: this plugin requires Paid Memberships Pro. 

== Installation ==

1. Upload the `pmpro-invite-only` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Set the $pmproio_invite_required_levels array to specify the levels which should require invite codes and generate them and (optionally) set the $pmproio_invite_given_levels if only specific levels should be given invite codes to share.
2. Other optional settings: PMPROIO_CODES and PMPROIO_CODES_USES constants can be set to define the number of codes to generate and number of uses code allowed per code.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-invite-only/issues

== Changelog ==
= .4 =
ENHANCEMENT: Per level Invite Only settings. Move settings to the PMPro Membership Levels page (GUI)

= .3.4 =
* BUG FIX: Fixed display of invite codes in emails.
* BUG FIX: When a user was assigned multiple invite codes, they were not displayed in the email body (array output as string). (Thanks, Matt Sims)
* ENHANCEMENT: Added the display name of the original recipient of a used invite code on the user profile screen. (Thanks, Matt Sims)

= .3.3 =
* BUG: Fixed bug where used codes were counted incorrectly.

= .3.2 =
* BUG: Fixed bug where "codes used" section would show up even if no codes were used.
* ENHANCEMENT: Updates to the display of used and unused codes in the membership confirmation page and user profile.

= .3.1 =
* BUG: Fixed issues where old ->pmpro_invite_codes method was used to check for a user's invite codes.
* BUG: Fixed grammar when showing one invite code in a list.

= .3 =
* Admins can now create additional invites for users.

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
