<?php
/**
 * Plugin deactivation.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Stops scheduled cleanup. Tables and settings stay in the database.
 */
class Deactivator {

	/**
	 * Hook cleared on deactivation and on uninstall.
	 * uninstall.php repeats this string because WordPress loads that file alone.
	 */
	public const CLEANUP_HOOK = 'wpcare_cleanup';

	/**
	 * Deactivation must not delete activities or maintenance logs.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}
}
