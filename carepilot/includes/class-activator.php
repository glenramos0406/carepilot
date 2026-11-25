<?php
/**
 * Plugin activation.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Creates tables and default settings. Rows that already exist are kept.
 */
class Activator {

	/**
	 * Run for the current site.
	 */
	public static function activate(): void {
		$db = new Database();
		$db->install();
		Settings::ensure_defaults();
		Cleanup::schedule();
	}
}
