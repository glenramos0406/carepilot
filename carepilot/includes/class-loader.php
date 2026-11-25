<?php
/**
 * Boots the free plugin after WordPress has loaded.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Registers lifecycle hooks and tells Pro that the free plugin is ready.
 */
class Loader {

	/**
	 * Called from the main file while plugins are still being included.
	 * The ready action fires later, on plugins_loaded, so a plugin that
	 * loaded first can still subscribe.
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'boot' ), 10 );
	}

	/**
	 * Upgrade the schema if needed, then let Pro attach.
	 */
	public static function boot(): void {
		( new Database() )->maybe_upgrade();
		( new Activity_Tracker() )->register();
		( new Cleanup() )->register();
		if ( is_admin() ) {
			( new Admin() )->register();
		}
		/**
		 * Fires after CarePilot has loaded and its schema is current.
		 *
		 * Pro listens for this at include time. Do not assume folder order:
		 * wp-care-report-pro sorts before carepilot.
		 */
		do_action( 'wpcare_report_loaded' );
	}
}
