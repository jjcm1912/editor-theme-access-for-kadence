<?php
/**
 * Uninstall routine.
 *
 * This plugin never writes capabilities, options, or any other data
 * to the database — every grant (including the temporary elevation to
 * manage_options) is calculated dynamically through the 'user_has_cap'
 * filter, only during verified Customizer/Kadence requests. Because of
 * this, uninstalling the plugin requires no cleanup at all: once the
 * plugin is removed, the filter simply stops running and the "editor"
 * role immediately reverts to its native behavior.
 *
 * This file exists only to follow WordPress.org best practices
 * (it avoids the "no uninstall.php found" notice) and to make it
 * explicit, for anyone reviewing the plugin, that there is nothing to
 * clean up.
 *
 * @package ETAK_Theme_Access
 */

// Exit if WordPress did not invoke this file through the uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Nothing to do: this plugin creates no option, capability, or meta data.
