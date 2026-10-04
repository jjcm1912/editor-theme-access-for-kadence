=== Editor Theme Access for Kadence ===
Contributors: jjcm1962
Tags: kadence, editor, capabilities, customizer, theme options
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gives the Editor role the same access to the Kadence Customizer that an Administrator has, without ever writing anything to the database.

== Description ==

The Kadence theme uses its own internal capability check to decide
whether to display its own Customizer panels (Header, Footer, Colors
& Fonts, General, Posts/Pages Layout, Homepage Settings, etc.). In
practice, that check is only satisfied by `manage_options` — the
standard `edit_theme_options` capability, which the Editor role
normally lacks, is not enough: an Editor with only
`edit_theme_options` still sees just the native WordPress panels
(Site Identity, Menus, Widgets, Additional CSS), not Kadence's own
panels.

This plugin solves that by granting `manage_options` to Editor users,
but in a tightly scoped way:

1. **Only when the active theme is actually Kadence** (or a Kadence
   child theme). The plugin can stay installed and active on any
   WordPress site — it does nothing on sites using a different theme.
   There is no need to deactivate it when switching themes, or to
   install a different variant for other themes.
2. **Only at runtime.** The capability is never written to the role
   or to any database option — it is returned by the `user_has_cap`
   filter only for the duration of the current HTTP request.
3. **Only during verified Customizer requests**, with a nonce check
   on every possible path: native Customizer preview, AJAX save
   requests, and REST requests to `/wp/v2/settings` or
   `/wp/v2/themes`. The mere presence of a URL parameter is never
   enough on its own — this prevents an Editor from obtaining
   `manage_options` on other wp-admin pages simply by tampering with
   the query string.

Outside of those conditions, the Editor has `manage_options` nowhere
else: they remain without access to Plugins, Users, or any other page
that depends on that capability.

**Nothing is written to the database**

* There is no `add_cap()` call on the `WP_Role` object, in any
  activation, deactivation, or `admin_init` hook.
* There is no `register_setting`, `update_option`, or
  `update_user_meta` anywhere in the plugin.
* Deactivating the plugin immediately restores native WordPress
  behavior for all Editors; uninstalling leaves no residue in the
  database.

**Extensibility**

By default, the elevation applies to all Editors (within the
conditions above). To restrict it to specific users, use the
`etak_grant_theme_access` filter in a must-use plugin or in the
theme's `functions.php` — without modifying this plugin:

`add_filter( 'etak_grant_theme_access', function ( $grant, $user ) {`
`    return in_array( $user->ID, array( 5, 12 ), true );`
`}, 10, 2 );`

You can also adjust the AJAX actions and REST routes recognized as
Customizer/Kadence context through the `etak_customizer_ajax_actions`
and `etak_customizer_rest_routes` filters — useful if your site has
additional Kadence-related integrations using different actions or
routes than the defaults.

If your Kadence installation (or a variant of it) uses a different
theme slug, adjust it with `etak_kadence_theme_slugs`:

`add_filter( 'etak_kadence_theme_slugs', function ( $slugs ) {`
`    return array_merge( $slugs, array( 'my-kadence-slug' ) );`
`} );`

== Installation ==

1. Upload the `editor-theme-access-for-kadence` folder to the
   `/wp-content/plugins/` directory, or install the plugin directly
   from the "Plugins" screen in WordPress.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. No further configuration is needed. Users with the Editor role can
   now open and save every Kadence Customizer panel.

== Frequently Asked Questions ==

= Why does the plugin grant manage_options, instead of just edit_theme_options? =

Because Kadence, for its own panels (Header, Footer, Colors & Fonts,
General, Posts/Pages Layout, Homepage Settings), internally checks a
capability that only `manage_options` satisfies. `edit_theme_options`
alone only reveals the native WordPress panels (Site Identity, Menus,
Widgets, Additional CSS).

= Isn't this dangerous — doesn't an Editor become almost an Administrator? =

The elevation only exists during verified (nonce-checked) Customizer
requests — never persistently or globally. An Editor does not gain
`manage_options` by visiting Plugins, Users, or any other wp-admin
page; only inside the Customizer screen itself and the requests it
generates. That said, it is still a real elevation: an Editor with
Customizer access can change any setting that lives there (including,
for example, "Additional CSS", which accepts arbitrary CSS, or panels
from other plugins that also depend on `manage_options` and appear in
the Customizer). Weigh this trade-off before activating the plugin on
a site with multiple Editors.

= Do I need to deactivate the plugin if I switch themes? =

No. The plugin checks, on every request, whether the active theme
(`get_template()`) is `kadence`; if it isn't, it does nothing at all.
You can leave it always active, even on sites that don't use Kadence,
or switch themes without worrying about deactivating it first.

= Is the permission recorded in the database? =

No, under any circumstance. It is recalculated on every request
through the `user_has_cap` filter.

= Can I restrict this to a specific Editor? =

Yes, via the `etak_grant_theme_access` filter (see the Description
section) in a must-use plugin or the theme's `functions.php` — no
need to modify this plugin.

= Is this plugin official, or affiliated with Kadence WP? =

No. This is an independent, third-party plugin with no affiliation
with Kadence WP.

== Screenshots ==

No screenshots — this plugin has no user interface.

== Changelog ==

= 2.1.1 =
* Renamed the main class to ETAK_Theme_Access to use a short, unique
  plugin prefix (WordPress.org coding standards).
* Removed the manual load_plugin_textdomain() call — translations for
  plugins hosted on WordPress.org are loaded automatically since
  WordPress 4.6.

= 2.1.0 =
* The elevation now only applies when the active theme is actually
  Kadence (or a child theme), checked via get_template(). The plugin
  can stay active on any site, with no effect on themes other than
  Kadence.

= 2.0.0 =
* Architecture change: the elevation now includes manage_options (in
  addition to edit_theme_options and customize), since Kadence
  requires manage_options to display its own Customizer panels. The
  grant is strictly limited to nonce-verified Customizer/Kadence
  requests, and is never persisted to the database.

= 1.6.0 =
* Renamed from "Kadence Editor Theme Access" to "Editor Theme Access
  for Kadence".

= 1.5.0 =
* Removed per-user ID filtering; edit_theme_options is granted to all
  Editors.

= 1.4.0 =
* Added environment variable and text file as configuration sources
  for the authorized editor list.

= 1.3.0 =
* Authorized editor list defined outside the database.

= 1.2.0 =
* (Reverted) Settings page under Users → Kadence Access.

= 1.1.0 =
* Simplified the edit_theme_options grant.

= 1.0.0 =
* First public release.

== Upgrade Notice ==

= 2.1.0 =
Behavior change: starting with this version, Editors receive
manage_options (not just edit_theme_options), but only during
verified Customizer/Kadence requests. Review the FAQ before updating
on a production site.
