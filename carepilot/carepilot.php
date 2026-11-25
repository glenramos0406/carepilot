<?php
/**
 * Plugin Name:       CarePilot
 * Description:       Records finished WordPress, plugin, and theme updates, and keeps a log of maintenance work you add by hand.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            Glenn Lawrence Ramos
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       carepilot
 * Domain Path:       /languages
 *
 * @package WPCareReport
 */

defined( 'ABSPATH' ) || exit;

define( 'WPCARE_VERSION', '1.0.0' );
define( 'WPCARE_DB_VERSION', '1.0.0' );
define( 'WPCARE_FILE', __FILE__ );
define( 'WPCARE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCARE_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCARE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * WordPress version. wp_get_wp_version() exists from WordPress 6.7.
 */
function wpcare_wp_version(): string {
	if ( function_exists( 'wp_get_wp_version' ) ) {
		return wp_get_wp_version();
	}

	global $wp_version;

	return is_string( $wp_version ) ? $wp_version : '';
}

require_once WPCARE_DIR . 'includes/class-settings.php';
require_once WPCARE_DIR . 'includes/class-database.php';
require_once WPCARE_DIR . 'includes/class-api.php';
require_once WPCARE_DIR . 'includes/class-activator.php';
require_once WPCARE_DIR . 'includes/class-deactivator.php';
require_once WPCARE_DIR . 'includes/class-activity-tracker.php';
require_once WPCARE_DIR . 'includes/class-update-tracker.php';
require_once WPCARE_DIR . 'includes/class-maintenance-logs.php';
require_once WPCARE_DIR . 'includes/class-cleanup.php';
require_once WPCARE_DIR . 'includes/class-dashboard.php';
require_once WPCARE_DIR . 'includes/class-admin.php';
require_once WPCARE_DIR . 'includes/class-loader.php';

/**
 * Run a callback for every site when the plugin is network-activated.
 *
 * @param callable $callback Callback with no arguments.
 */
function wpcare_each_site( $callback ) {
	if ( ! is_multisite() ) {
		$callback();
		return;
	}

	$offset = 0;
	do {
		$ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $offset,
			)
		);
		if ( ! is_array( $ids ) || array() === $ids ) {
			break;
		}
		foreach ( $ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			$callback();
			restore_current_blog();
		}
		$offset += 100;
	} while ( 100 === count( $ids ) );
}

/**
 * Create tables and default settings. Existing rows are kept.
 *
 * @param bool $network_wide Whether this is a network activation.
 */
function wpcare_activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		wpcare_each_site( array( \WPCareReport\Activator::class, 'activate' ) );
		return;
	}
	\WPCareReport\Activator::activate();
}

/**
 * Stop scheduled cleanup. Does not delete activities or logs.
 *
 * @param bool $network_wide Whether this is a network deactivation.
 */
function wpcare_deactivate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		wpcare_each_site( array( \WPCareReport\Deactivator::class, 'deactivate' ) );
		return;
	}
	\WPCareReport\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'wpcare_activate' );
register_deactivation_hook( __FILE__, 'wpcare_deactivate' );

\WPCareReport\Loader::register();

add_action(
	'wp_initialize_site',
	static function ( $site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( WPCARE_BASENAME ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		\WPCareReport\Activator::activate();
		restore_current_blog();
	}
);
