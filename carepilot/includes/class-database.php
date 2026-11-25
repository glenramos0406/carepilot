<?php
/**
 * Free plugin tables and the read queries Pro is allowed to use through Api.
 *
 * WHERE fragments are fixed strings. Their values are bound by prepare().
 * Table names use the %i identifier placeholder.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Owns wpcare_activities and wpcare_maintenance_logs.
 *
 * Other plugins, including CarePilot Pro, should call WPCareReport\Api
 * instead of this class.
 */
class Database {

	/**
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * @param \wpdb|null $wpdb Optional database handle, used by tests.
	 */
	public function __construct( $wpdb = null ) {
		if ( null !== $wpdb ) {
			$this->wpdb = $wpdb;
			return;
		}
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Create or update tables. Safe to run more than once. Does not insert rows.
	 */
	public function install(): void {
		$wpdb = $this->wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$activities = $this->activities_table();
		$logs       = $this->logs_table();

		$sql = "CREATE TABLE {$activities} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			activity_type varchar(40) NOT NULL DEFAULT '',
			component_name varchar(191) NOT NULL DEFAULT '',
			description text NOT NULL,
			previous_version varchar(50) NOT NULL DEFAULT '',
			new_version varchar(50) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT '',
			event_key varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_key (event_key),
			KEY activity_type (activity_type),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		$sql = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(191) NOT NULL DEFAULT '',
			description text NOT NULL,
			internal_notes text NOT NULL,
			category varchar(40) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT '',
			completed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY category (category),
			KEY status (status),
			KEY completed_at (completed_at),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );

		update_option( 'wpcare_db_version', WPCARE_DB_VERSION, true );
	}

	/**
	 * Run dbDelta after an update when the stored schema version differs.
	 */
	public function maybe_upgrade(): void {
		$version = get_option( 'wpcare_db_version', '' );
		if ( WPCARE_DB_VERSION !== $version ) {
			$this->install();
		}
	}

	public function activities_table(): string {
		$wpdb = $this->wpdb;

		return $wpdb->prefix . 'wpcare_activities';
	}

	public function logs_table(): string {
		$wpdb = $this->wpdb;

		return $wpdb->prefix . 'wpcare_maintenance_logs';
	}

	/**
	 * Count automatic activities.
	 *
	 * Supported args: type, status, after, before. after and before are
	 * MySQL datetimes in the site timezone, inclusive.
	 *
	 * @param array<string, mixed> $args Filters.
	 */
	public function count_activities( array $args = array() ): int {
		$wpdb   = $this->wpdb;
		$filter = $this->activity_bounds( $args );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE (%d = 1 OR activity_type = %s) AND (%d = 1 OR status = %s) AND created_at >= %s AND created_at <= %s',
				$this->activities_table(),
				$filter['type_off'],
				$filter['type'],
				$filter['status_off'],
				$filter['status'],
				$filter['after'],
				$filter['before']
			)
		);
	}

	/**
	 * Read automatic activities. Newest first. Limit is capped at 500.
	 *
	 * @param array<string, mixed> $args Filters plus limit and offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_activities( array $args = array() ): array {
		$wpdb   = $this->wpdb;
		$filter = $this->activity_bounds( $args );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, activity_type, component_name, description, previous_version, new_version, status, created_at FROM %i WHERE (%d = 1 OR activity_type = %s) AND (%d = 1 OR status = %s) AND created_at >= %s AND created_at <= %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
				$this->activities_table(),
				$filter['type_off'],
				$filter['type'],
				$filter['status_off'],
				$filter['status'],
				$filter['after'],
				$filter['before'],
				$this->limit( $args ),
				$this->offset( $args )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count manual maintenance logs.
	 *
	 * Supported args: category, status, search, after, before.
	 *
	 * @param array<string, mixed> $args Filters.
	 */
	public function count_logs( array $args = array() ): int {
		$wpdb   = $this->wpdb;
		$filter = $this->log_bounds( $args );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE (%d = 1 OR category = %s) AND (%d = 1 OR status = %s) AND (%d = 1 OR title LIKE %s) AND created_at >= %s AND created_at <= %s',
				$this->logs_table(),
				$filter['category_off'],
				$filter['category'],
				$filter['status_off'],
				$filter['status'],
				$filter['search_off'],
				$filter['search'],
				$filter['after'],
				$filter['before']
			)
		);
	}

	/**
	 * Read manual maintenance logs. Newest completed date first.
	 *
	 * @param array<string, mixed> $args Filters plus limit and offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_logs( array $args = array() ): array {
		$wpdb   = $this->wpdb;
		$filter = $this->log_bounds( $args );
		$limit  = $this->limit( $args );
		$offset = $this->offset( $args );
		if ( isset( $args['order'] ) && 'created' === $args['order'] ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, title, description, internal_notes, category, status, completed_at, created_at, updated_at FROM %i WHERE (%d = 1 OR category = %s) AND (%d = 1 OR status = %s) AND (%d = 1 OR title LIKE %s) AND created_at >= %s AND created_at <= %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
					$this->logs_table(),
					$filter['category_off'],
					$filter['category'],
					$filter['status_off'],
					$filter['status'],
					$filter['search_off'],
					$filter['search'],
					$filter['after'],
					$filter['before'],
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, title, description, internal_notes, category, status, completed_at, created_at, updated_at FROM %i WHERE (%d = 1 OR category = %s) AND (%d = 1 OR status = %s) AND (%d = 1 OR title LIKE %s) AND created_at >= %s AND created_at <= %s ORDER BY completed_at DESC, id DESC LIMIT %d OFFSET %d',
					$this->logs_table(),
					$filter['category_off'],
					$filter['category'],
					$filter['status_off'],
					$filter['status'],
					$filter['search_off'],
					$filter['search'],
					$filter['after'],
					$filter['before'],
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Bound values for a fixed activity query. An "off" flag of 1 skips that filter.
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{type_off: int, type: string, status_off: int, status: string, after: string, before: string}
	 */
	private function activity_bounds( array $args ): array {
		$type = isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : '';
		if ( ! in_array( $type, Api::ACTIVITY_TYPES, true ) ) {
			$type = '';
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( ! in_array( $status, Api::ACTIVITY_STATUSES, true ) ) {
			$status = '';
		}

		$after  = $this->mysql_datetime( isset( $args['after'] ) ? (string) $args['after'] : '' );
		$before = $this->mysql_datetime( isset( $args['before'] ) ? (string) $args['before'] : '' );

		return array(
			'type_off'   => '' === $type ? 1 : 0,
			'type'       => $type,
			'status_off' => '' === $status ? 1 : 0,
			'status'     => $status,
			'after'      => '' === $after ? '1000-01-01 00:00:00' : $after,
			'before'     => '' === $before ? '9999-12-31 23:59:59' : $before,
		);
	}

	/**
	 * Bound values for a fixed log query. An "off" flag of 1 skips that filter.
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{category_off: int, category: string, status_off: int, status: string, search_off: int, search: string, after: string, before: string}
	 */
	private function log_bounds( array $args ): array {
		$wpdb     = $this->wpdb;
		$category = isset( $args['category'] ) ? sanitize_key( (string) $args['category'] ) : '';
		if ( ! in_array( $category, Api::LOG_CATEGORIES, true ) ) {
			$category = '';
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( ! in_array( $status, Api::LOG_STATUSES, true ) ) {
			$status = '';
		}

		$search = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
		$after  = $this->mysql_datetime( isset( $args['after'] ) ? (string) $args['after'] : '' );
		$before = $this->mysql_datetime( isset( $args['before'] ) ? (string) $args['before'] : '' );

		return array(
			'category_off' => '' === $category ? 1 : 0,
			'category'     => $category,
			'status_off'   => '' === $status ? 1 : 0,
			'status'       => $status,
			'search_off'   => '' === $search ? 1 : 0,
			'search'       => '' === $search ? '' : '%' . $wpdb->esc_like( $search ) . '%',
			'after'        => '' === $after ? '1000-01-01 00:00:00' : $after,
			'before'       => '' === $before ? '9999-12-31 23:59:59' : $before,
		);
	}

	/**
	 * Accept only a MySQL datetime. Anything else is ignored.
	 */
	private function mysql_datetime( string $value ): string {
		$value = trim( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $args Requested page size.
	 */
	private function limit( array $args ): int {
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 20;
		if ( $limit < 1 ) {
			return 1;
		}
		if ( $limit > 500 ) {
			return 500;
		}

		return $limit;
	}

	/**
	 * @param array<string, mixed> $args Requested offset.
	 */
	private function offset( array $args ): int {
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		if ( $offset < 0 ) {
			return 0;
		}
		if ( $offset > 100000 ) {
			return 100000;
		}

		return $offset;
	}

	/**
	 * Store one verified automatic event. A repeated event_key is ignored.
	 *
	 * @param array<string, string> $row Activity fields.
	 * @return int New row id, or 0 when the row was rejected or already stored.
	 */
	public function insert_activity( array $row ): int {
		$wpdb   = $this->wpdb;
		$type   = isset( $row['activity_type'] ) ? sanitize_key( $row['activity_type'] ) : '';
		$status = isset( $row['status'] ) ? sanitize_key( $row['status'] ) : '';
		$key    = isset( $row['event_key'] ) ? strtolower( (string) $row['event_key'] ) : '';
		if ( ! in_array( $type, Api::ACTIVITY_TYPES, true ) || ! in_array( $status, Api::ACTIVITY_STATUSES, true ) ) {
			return 0;
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $key ) ) {
			return 0;
		}

		$inserted = $wpdb->insert(
			$this->activities_table(),
			array(
				'activity_type'    => $type,
				'component_name'   => $this->clip( sanitize_text_field( $row['component_name'] ?? '' ), 191 ),
				'description'      => $this->clip( sanitize_textarea_field( $row['description'] ?? '' ), 1000 ),
				'previous_version' => $this->clip( sanitize_text_field( $row['previous_version'] ?? '' ), 50 ),
				'new_version'      => $this->clip( sanitize_text_field( $row['new_version'] ?? '' ), 50 ),
				'status'           => $status,
				'event_key'        => $key,
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Read one manual log.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_log( int $id ): ?array {
		$wpdb = $this->wpdb;
		if ( $id < 1 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, title, description, internal_notes, category, status, completed_at, created_at, updated_at FROM %i WHERE id = %d',
				$this->logs_table(),
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Store a manual log. completed_at may be null while the task is still pending.
	 *
	 * @param array<string, string|null> $row Log fields.
	 */
	public function insert_log( array $row ): int {
		$wpdb  = $this->wpdb;
		$clean = $this->clean_log( $row );
		if ( null === $clean ) {
			return 0;
		}

		$now = current_time( 'mysql' );
		if ( null === $clean['completed_at'] ) {
			$inserted = $wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (title, description, internal_notes, category, status, completed_at, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, NULL, %s, %s)',
					$this->logs_table(),
					$clean['title'],
					$clean['description'],
					$clean['internal_notes'],
					$clean['category'],
					$clean['status'],
					$now,
					$now
				)
			);
		} else {
			$inserted = $wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (title, description, internal_notes, category, status, completed_at, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)',
					$this->logs_table(),
					$clean['title'],
					$clean['description'],
					$clean['internal_notes'],
					$clean['category'],
					$clean['status'],
					$clean['completed_at'],
					$now,
					$now
				)
			);
		}
		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Replace a manual log. Returns false when the id or the fields are not valid.
	 *
	 * @param array<string, string|null> $row Log fields.
	 */
	public function update_log( int $id, array $row ): bool {
		$wpdb = $this->wpdb;
		if ( $id < 1 || null === $this->get_log( $id ) ) {
			return false;
		}
		$clean = $this->clean_log( $row );
		if ( null === $clean ) {
			return false;
		}

		if ( null === $clean['completed_at'] ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET title = %s, description = %s, internal_notes = %s, category = %s, status = %s, completed_at = NULL, updated_at = %s WHERE id = %d',
					$this->logs_table(),
					$clean['title'],
					$clean['description'],
					$clean['internal_notes'],
					$clean['category'],
					$clean['status'],
					current_time( 'mysql' ),
					$id
				)
			);
		} else {
			$result = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET title = %s, description = %s, internal_notes = %s, category = %s, status = %s, completed_at = %s, updated_at = %s WHERE id = %d',
					$this->logs_table(),
					$clean['title'],
					$clean['description'],
					$clean['internal_notes'],
					$clean['category'],
					$clean['status'],
					$clean['completed_at'],
					current_time( 'mysql' ),
					$id
				)
			);
		}

		return false !== $result;
	}

	/**
	 * Delete one manual log.
	 */
	public function delete_log( int $id ): bool {
		$wpdb = $this->wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$result = $wpdb->delete( $this->logs_table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $result && $result > 0;
	}

	/**
	 * Delete one automatic activity. Used to remove a row the tracker just wrote during a test.
	 */
	public function delete_activity( int $id ): bool {
		$wpdb = $this->wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$result = $wpdb->delete( $this->activities_table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $result && $result > 0;
	}

	/**
	 * Counts per calendar day for the chart. Keys are Y-m-d.
	 *
	 * @return array<string, int>
	 */
	public function daily_counts( string $which, string $after, string $before ): array {
		$wpdb   = $this->wpdb;
		$after  = $this->mysql_datetime( $after );
		$before = $this->mysql_datetime( $before );
		if ( '' === $after || '' === $before ) {
			return array();
		}

		$table = 'logs' === $which ? $this->logs_table() : $this->activities_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(created_at) AS day_key, COUNT(*) AS total FROM %i WHERE created_at >= %s AND created_at <= %s GROUP BY DATE(created_at)',
				$table,
				$after,
				$before
			),
			ARRAY_A
		);
		$out   = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			if ( isset( $row['day_key'] ) ) {
				$out[ (string) $row['day_key'] ] = (int) $row['total'];
			}
		}

		return $out;
	}

	/**
	 * Latest moment something was recorded, or an empty string.
	 */
	public function latest_moment(): string {
		$wpdb     = $this->wpdb;
		$activity = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT created_at FROM %i ORDER BY created_at DESC, id DESC LIMIT %d',
				$this->activities_table(),
				1
			)
		);
		$log = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT created_at FROM %i ORDER BY created_at DESC, id DESC LIMIT %d',
				$this->logs_table(),
				1
			)
		);
		$activity = is_string( $activity ) ? $activity : '';
		$log      = is_string( $log ) ? $log : '';
		if ( '' === $activity ) {
			return $log;
		}
		if ( '' === $log ) {
			return $activity;
		}

		return $activity >= $log ? $activity : $log;
	}

	/**
	 * Delete rows older than a site-timezone datetime. Zero retention means keep everything.
	 */
	public function delete_older_than( string $before ): void {
		$wpdb   = $this->wpdb;
		$before = $this->mysql_datetime( $before );
		if ( '' === $before ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$this->activities_table(),
				$before
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$this->logs_table(),
				$before
			)
		);
	}

	/**
	 * @param array<string, string|null> $row Raw log fields.
	 * @return array{title: string, description: string, internal_notes: string, category: string, status: string, completed_at: string|null}|null
	 */
	private function clean_log( array $row ): ?array {
		$title = $this->clip( sanitize_text_field( (string) ( $row['title'] ?? '' ) ), 191 );
		if ( '' === $title ) {
			return null;
		}

		$category = sanitize_key( (string) ( $row['category'] ?? '' ) );
		$status   = sanitize_key( (string) ( $row['status'] ?? '' ) );
		if ( ! in_array( $category, Api::LOG_CATEGORIES, true ) || ! in_array( $status, Api::LOG_STATUSES, true ) ) {
			return null;
		}

		$completed = $row['completed_at'] ?? null;
		if ( null !== $completed && '' !== $completed ) {
			$completed = $this->mysql_datetime( (string) $completed );
			if ( '' === $completed ) {
				return null;
			}
		} else {
			$completed = null;
		}

		return array(
			'title'           => $title,
			'description'     => $this->clip( sanitize_textarea_field( (string) ( $row['description'] ?? '' ) ), 5000 ),
			'internal_notes'  => $this->clip( sanitize_textarea_field( (string) ( $row['internal_notes'] ?? '' ) ), 5000 ),
			'category'        => $category,
			'status'          => $status,
			'completed_at'    => $completed,
		);
	}

	private function clip( string $value, int $max ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}

		return substr( $value, 0, $max );
	}
}
