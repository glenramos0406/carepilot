<?php
/**
 * Removes activity older than the retention setting.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Daily cleanup. A retention of zero keeps every row.
 */
class Cleanup {

	/**
	 * Register the worker and make sure the daily event exists.
	 */
	public function register(): void {
		add_action( Deactivator::CLEANUP_HOOK, array( $this, 'run' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
	}

	/**
	 * Schedule the daily event once. Safe to call on every request.
	 */
	public static function schedule(): void {
		if ( wp_next_scheduled( Deactivator::CLEANUP_HOOK ) ) {
			return;
		}
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Deactivator::CLEANUP_HOOK );
	}

	/**
	 * Delete rows older than the configured number of days.
	 */
	public function run(): void {
		$days = (int) Settings::get()['retention_days'];
		if ( $days < 1 ) {
			return;
		}

		$cutoff = current_datetime()->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' );
		( new Database() )->delete_older_than( $cutoff );
	}
}
