<?php
/**
 * Remove plugin data only when the site owner asked for that.
 *
 * Deactivation never reaches this file. Tables stay unless
 * "Delete all CarePilot data if I delete this plugin" is checked.
 *
 * @package WPCareReport
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Drop this site's tables and options when its own setting allows it.
 * The cleanup event is cleared either way, because the plugin is gone.
 *
 * The hook name matches WPCareReport\Deactivator::CLEANUP_HOOK.
 */
function wpcare_uninstall_current_site() {
	$settings = get_option( 'wpcare_settings', array() );
	$delete   = is_array( $settings ) && ! empty( $settings['delete_data'] );

	wp_clear_scheduled_hook( 'wpcare_cleanup' );

	if ( ! $delete ) {
		return;
	}

	global $wpdb;

	foreach ( array( 'wpcare_activities', 'wpcare_maintenance_logs' ) as $suffix ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $suffix ) );
	}

	delete_option( 'wpcare_settings' );
	delete_option( 'wpcare_db_version' );
	delete_option( 'wpcare_core_version' );
	wpcare_uninstall_transients();
}

/**
 * Remove transients this plugin created. Options that belong to Pro are left alone.
 */
function wpcare_uninstall_transients() {
	global $wpdb;

	$like_timeout = $wpdb->esc_like( '_transient_timeout_wpcare_' ) . '%';
	$like_value   = $wpdb->esc_like( '_transient_wpcare_' ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$like_timeout,
			$like_value
		)
	);
}

if ( is_multisite() ) {
	$wpcare_offset = 0;
	do {
		$wpcare_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $wpcare_offset,
			)
		);
		if ( ! is_array( $wpcare_ids ) || array() === $wpcare_ids ) {
			break;
		}
		foreach ( $wpcare_ids as $wpcare_blog_id ) {
			switch_to_blog( (int) $wpcare_blog_id );
			wpcare_uninstall_current_site();
			restore_current_blog();
		}
		$wpcare_offset += 100;
	} while ( 100 === count( $wpcare_ids ) );
} else {
	wpcare_uninstall_current_site();
}
