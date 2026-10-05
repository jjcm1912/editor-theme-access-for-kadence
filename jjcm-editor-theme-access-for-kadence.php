<?php
/**
 * Plugin Name:       JJCM Editor Theme Access for Kadence
 * Plugin URI:        https://github.com/jjcm1912/editor-theme-access-for-kadence
 * Description:       Gives the "editor" role full access to the Kadence theme's Customizer (Header, Footer, Colors & Fonts, General, Posts/Pages Layout, etc.), which Kadence only fully displays to users with manage_options. The elevation only applies when Kadence is the active theme, only during verified (nonce-checked) Customizer requests, and is never written to the database.
 * Version:           2.1.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            José
 * Author URI:        https://github.com/jjcm1912
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jjcm-editor-theme-access-for-kadence
 * Domain Path:       /languages
 *
 * @package ETAK_Theme_Access
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Avoid redeclaration if this file is somehow included more than once.
if ( ! class_exists( 'ETAK_Theme_Access' ) ) {

	/**
	 * Main plugin class.
	 *
	 * The Kadence theme uses its own internal capability check to
	 * decide whether to display its own Customizer panels (Header,
	 * Footer, Colors & Fonts, General, Posts/Pages Layout, Homepage
	 * Settings). In practice, that check is only satisfied by
	 * manage_options — the edit_theme_options capability alone is not
	 * enough to see those panels (only the native WordPress ones keep
	 * showing: Site Identity, Menus, Widgets, Additional CSS).
	 *
	 * To work around this without granting manage_options broadly,
	 * this plugin only grants it:
	 *   1) when the active theme is actually Kadence (or a Kadence
	 *      child theme) — on any other theme, the plugin does nothing
	 *      at all;
	 *   2) to users with the "editor" role;
	 *   3) only at runtime, via the 'user_has_cap' filter — it is
	 *      never persisted on the role or in any database option;
	 *   4) only during requests verified as genuine Customizer
	 *      requests (preview, saving via AJAX or REST), with a nonce
	 *      check on every one of those paths — the mere presence of a
	 *      URL parameter is never enough on its own.
	 *
	 * Outside of those conditions, the Editor has manage_options
	 * nowhere else: they remain without access to Plugins, Users, or
	 * any other page that depends on that capability. The plugin can
	 * stay active permanently on any WordPress install — it only has
	 * a practical effect on sites where Kadence is actually being
	 * used.
	 */
	class ETAK_Theme_Access {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '2.1.2';

		/**
		 * Prevents recursion inside the user_has_cap filter (once the
		 * filter decides to grant the capability, it must not trigger
		 * itself again indirectly).
		 *
		 * @var bool
		 */
		private static $processing = false;

		/**
		 * Registers the plugin's hooks.
		 *
		 * Translations are not loaded manually: since WordPress 4.6,
		 * plugins hosted on WordPress.org have their translations
		 * loaded automatically, based solely on the Text Domain
		 * header above — a manual load_plugin_textdomain() call is
		 * redundant and discouraged.
		 *
		 * @return void
		 */
		public static function init() {
			add_filter( 'user_has_cap', array( __CLASS__, 'grant_runtime_caps' ), 10, 4 );
		}

		/**
		 * Checks whether the active theme is Kadence or a Kadence
		 * child theme. Uses get_template() (not get_stylesheet())
		 * because that always returns the parent theme, even when a
		 * child theme is active — this mirrors the check Kadence
		 * itself performs internally when registering its panels.
		 *
		 * Without this check, the elevation would apply to the
		 * Customizer of any theme, not just Kadence.
		 *
		 * @return bool
		 */
		private static function is_kadence_theme_active() {
			/**
			 * Filters the theme slug(s) considered "Kadence" for the
			 * purposes of this plugin. Defaults to the official
			 * 'kadence' slug (the free theme available on
			 * WordPress.org). Useful if you use a variant with a
			 * different slug.
			 *
			 * @param string[] $slugs List of accepted theme slugs.
			 */
			$kadence_slugs = apply_filters( 'etak_kadence_theme_slugs', array( 'kadence' ) );

			return in_array( get_template(), (array) $kadence_slugs, true );
		}

		/**
		 * Checks whether the user is an eligible Editor (and not also
		 * an Administrator, for whom this would already be moot).
		 *
		 * @param WP_User|null $user User being evaluated.
		 * @return bool
		 */
		private static function is_eligible_editor( $user ) {
			if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
				return false;
			}

			if ( ! in_array( 'editor', (array) $user->roles, true ) ) {
				return false;
			}

			if ( in_array( 'administrator', (array) $user->roles, true ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Detects whether the current HTTP request is genuinely a
		 * legitimate Customizer/Kadence request — with a nonce check
		 * on every path that allows one (AJAX, REST, saving
		 * settings). The mere presence of a URL parameter is never
		 * enough on its own.
		 *
		 * @return bool
		 */
		private static function is_verified_kadence_context() {

			// 1. Native Customizer preview.
			if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
				return true;
			}

			// 2. Loading the Customizer screen itself, with a nonce.
			if ( is_admin() && isset( $_REQUEST['wp_customize'] ) && 'on' === $_REQUEST['wp_customize'] ) {
				$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
				return ! empty( $nonce ) && wp_verify_nonce( $nonce, 'customize-preview' );
			}

			// 3. AJAX requests from the Customizer/Kadence, with a nonce.
			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
				$allowed_actions = apply_filters(
					'etak_customizer_ajax_actions',
					array( 'customize_save', 'customize_refresh_nonces', 'customize_preview_settings' )
				);

				$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
				if ( ! in_array( $action, (array) $allowed_actions, true ) ) {
					return false;
				}

				$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
				return ! empty( $nonce ) && wp_verify_nonce( $nonce, 'customize_save' );
			}

			// 4. REST requests related to settings/themes, with a nonce.
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				$route = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

				$allowed_routes = apply_filters( 'etak_customizer_rest_routes', array( '/wp/v2/settings', '/wp/v2/themes' ) );

				$matches_route = false;
				foreach ( (array) $allowed_routes as $route_fragment ) {
					if ( false !== strpos( $route, $route_fragment ) ) {
						$matches_route = true;
						break;
					}
				}
				if ( ! $matches_route ) {
					return false;
				}

				$header_nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
				if ( ! empty( $header_nonce ) && wp_verify_nonce( $header_nonce, 'wp_rest' ) ) {
					return true;
				}

				$param_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
				return ! empty( $param_nonce ) && wp_verify_nonce( $param_nonce, 'wp_rest' );
			}

			return false;
		}

		/**
		 * Filters the current user's capabilities, granting
		 * manage_options (in addition to edit_theme_options and
		 * customize) only when both conditions are met: the user is
		 * an eligible Editor, AND the current request is a verified
		 * Customizer/Kadence request.
		 *
		 * @param bool[]   $allcaps Capabilities already granted to the user.
		 * @param string[] $caps    Primitive capabilities required.
		 * @param array    $args    Additional arguments.
		 * @param WP_User  $user    User being evaluated.
		 * @return bool[] The (possibly altered) capabilities array.
		 */
		public static function grant_runtime_caps( $allcaps, $caps, $args, $user ) {

			if ( self::$processing ) {
				return $allcaps;
			}

			// Already an administrator (or already has manage_options some other way) — nothing to do.
			if ( ! empty( $allcaps['manage_options'] ) ) {
				return $allcaps;
			}

			// Only act if the active theme is actually Kadence (or a child theme).
			if ( ! self::is_kadence_theme_active() ) {
				return $allcaps;
			}

			if ( ! self::is_eligible_editor( $user ) ) {
				return $allcaps;
			}

			/**
			 * Filters whether this specific Editor should receive the
			 * elevation. Defaults to applying to all eligible
			 * Editors. A must-use plugin or the theme's functions.php
			 * can restrict this to specific IDs without modifying
			 * this plugin:
			 *
			 *   add_filter( 'etak_grant_theme_access', function ( $grant, $user ) {
			 *       return in_array( $user->ID, array( 5, 12 ), true );
			 *   }, 10, 2 );
			 *
			 * @param bool    $grant Whether the elevation should be granted to this user.
			 * @param WP_User $user  User being evaluated.
			 */
			if ( ! apply_filters( 'etak_grant_theme_access', true, $user ) ) {
				return $allcaps;
			}

			if ( ! self::is_verified_kadence_context() ) {
				return $allcaps;
			}

			self::$processing = true;
			$allcaps['edit_theme_options'] = true;
			$allcaps['customize']          = true;
			$allcaps['manage_options']     = true;
			self::$processing = false;

			return $allcaps;
		}
	}

	ETAK_Theme_Access::init();
}
