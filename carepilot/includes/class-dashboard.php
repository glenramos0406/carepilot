<?php
/**
 * Dashboard numbers from the database and from WordPress itself.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Collects the dashboard. Every count comes from stored rows.
 */
class Dashboard {

	public function __construct( private Database $db ) {
	}

	/**
	 * @return array<string, int>
	 */
	public function stats(): array {
		return array(
			'activities'     => $this->db->count_activities(),
			'core_updates'   => $this->db->count_activities(
				array(
					'type'   => 'core_update',
					'status' => 'completed',
				)
			),
			'plugin_updates' => $this->db->count_activities(
				array(
					'type'   => 'plugin_update',
					'status' => 'completed',
				)
			),
			'theme_updates'  => $this->db->count_activities(
				array(
					'type'   => 'theme_update',
					'status' => 'completed',
				)
			),
			'logs'           => $this->db->count_logs(),
			'pending'        => $this->db->count_logs( array( 'status' => 'pending' ) ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function site(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = get_plugins();
		$active    = count( (array) get_option( 'active_plugins', array() ) );
		if ( is_multisite() ) {
			$active += count( (array) get_site_option( 'active_sitewide_plugins', array() ) );
		}

		$theme = wp_get_theme();

		return array(
			'url'       => home_url( '/' ),
			'wp'        => wpcare_wp_version(),
			'php'       => PHP_VERSION,
			'theme'     => $theme->exists() ? (string) $theme->get( 'Name' ) : '',
			'installed' => count( $installed ),
			'active'    => $active,
			'https'     => wp_is_using_https(),
			'last'      => $this->db->latest_moment(),
		);
	}

	/**
	 * Thirty days ending today. Each day has automatic and manual counts.
	 *
	 * @return array<int, array{key: string, label: string, automatic: int, manual: int}>
	 */
	public function chart(): array {
		$end   = current_datetime();
		$start = $end->modify( '-29 days' )->setTime( 0, 0, 0 );
		$from  = $start->format( 'Y-m-d H:i:s' );
		$to    = $end->format( 'Y-m-d 23:59:59' );

		$automatic = $this->db->daily_counts( 'activities', $from, $to );
		$manual    = $this->db->daily_counts( 'logs', $from, $to );
		$days      = array();

		for ( $i = 0; $i < 30; $i++ ) {
			$day    = $start->modify( '+' . $i . ' days' );
			$key    = $day->format( 'Y-m-d' );
			$days[] = array(
				'key'       => $key,
				'label'     => wp_date( 'M j', $day->getTimestamp() ),
				'automatic' => $automatic[ $key ] ?? 0,
				'manual'    => $manual[ $key ] ?? 0,
			);
		}

		return $days;
	}

	/**
	 * Newest recorded work from both tables.
	 *
	 * @return array<int, array{source: string, label: string, meta: string, when: string, status: string}>
	 */
	public function recent(): array {
		$rows = array();
		foreach ( $this->db->get_activities( array( 'limit' => 8 ) ) as $row ) {
			$rows[] = array(
				'source' => 'automatic',
				'label'  => (string) $row['description'],
				'meta'   => Api::activity_label( (string) $row['activity_type'] ),
				'when'   => (string) $row['created_at'],
				'status' => (string) $row['status'],
				'sort'   => (string) $row['created_at'] . '-' . str_pad( (string) $row['id'], 10, '0', STR_PAD_LEFT ),
			);
		}
		foreach ( $this->db->get_logs( array( 'limit' => 8, 'order' => 'created' ) ) as $row ) {
			$rows[] = array(
				'source' => 'manual',
				'label'  => (string) $row['title'],
				'meta'   => Api::category_label( (string) $row['category'] ),
				'when'   => (string) $row['created_at'],
				'status' => (string) $row['status'],
				'sort'   => (string) $row['created_at'] . '-' . str_pad( (string) $row['id'], 10, '0', STR_PAD_LEFT ),
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return strcmp( $b['sort'], $a['sort'] );
			}
		);

		$rows = array_slice( $rows, 0, 8 );
		foreach ( $rows as &$row ) {
			unset( $row['sort'] );
		}

		return $rows;
	}

	/**
	 * Format a site-timezone MySQL datetime for the admin.
	 */
	public static function format_time( string $mysql ): string {
		$mysql = trim( $mysql );
		if ( '' === $mysql || str_starts_with( $mysql, '0000-00-00' ) ) {
			return '';
		}
		$dt = date_create_immutable_from_format( 'Y-m-d H:i:s', $mysql, wp_timezone() );
		if ( ! $dt ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $dt->getTimestamp() );
	}

	/**
	 * Format a completed date without the midnight time.
	 */
	public static function format_date( string $mysql ): string {
		$mysql = trim( $mysql );
		if ( '' === $mysql || str_starts_with( $mysql, '0000-00-00' ) ) {
			return '';
		}
		$dt = date_create_immutable_from_format( 'Y-m-d H:i:s', $mysql, wp_timezone() );
		if ( ! $dt ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ), $dt->getTimestamp() );
	}
}
