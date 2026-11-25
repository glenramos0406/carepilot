<?php
/**
 * Manual maintenance log writes and CSV export.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Validates log forms. The list itself is read through Database.
 */
class Maintenance_Logs {

	public function __construct( private Database $db ) {
	}

	/**
	 * Create or update a log from an already-unslashed request array.
	 *
	 * @param array<string, mixed> $request Posted fields.
	 * @return int|\WP_Error Row id, or an error the screen can show.
	 */
	public function save( int $id, array $request ) {
		$title = isset( $request['title'] ) ? sanitize_text_field( (string) $request['title'] ) : '';
		if ( '' === $title ) {
			return new \WP_Error( 'wpcare_title', __( 'Add a title for this maintenance task.', 'carepilot' ) );
		}

		$category = isset( $request['category'] ) ? sanitize_key( (string) $request['category'] ) : '';
		$status   = isset( $request['status'] ) ? sanitize_key( (string) $request['status'] ) : '';
		if ( ! in_array( $category, Api::LOG_CATEGORIES, true ) ) {
			return new \WP_Error( 'wpcare_category', __( 'Choose a category.', 'carepilot' ) );
		}
		if ( ! in_array( $status, Api::LOG_STATUSES, true ) ) {
			return new \WP_Error( 'wpcare_status', __( 'Choose a status.', 'carepilot' ) );
		}

		$date = isset( $request['completed_on'] ) ? sanitize_text_field( (string) $request['completed_on'] ) : '';
		$completed = null;
		if ( '' !== $date ) {
			if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				return new \WP_Error( 'wpcare_date', __( 'Enter the completed date as YYYY-MM-DD.', 'carepilot' ) );
			}
			$parts = array_map( 'intval', explode( '-', $date ) );
			if ( ! checkdate( $parts[1], $parts[2], $parts[0] ) ) {
				return new \WP_Error( 'wpcare_date', __( 'That completed date is not a real calendar day.', 'carepilot' ) );
			}
			$completed = $date . ' 00:00:00';
		}

		$row = array(
			'title'          => $title,
			'description'    => isset( $request['description'] ) ? (string) $request['description'] : '',
			'internal_notes' => isset( $request['internal_notes'] ) ? (string) $request['internal_notes'] : '',
			'category'       => $category,
			'status'         => $status,
			'completed_at'   => $completed,
		);

		if ( $id > 0 ) {
			if ( ! $this->db->update_log( $id, $row ) ) {
				return new \WP_Error( 'wpcare_save', __( 'That maintenance log could not be saved.', 'carepilot' ) );
			}
			return $id;
		}

		$new_id = $this->db->insert_log( $row );
		if ( $new_id < 1 ) {
			return new \WP_Error( 'wpcare_save', __( 'That maintenance log could not be saved.', 'carepilot' ) );
		}

		return $new_id;
	}

	/**
	 * Delete one log owned by this site.
	 */
	public function delete( int $id ): bool {
		return $this->db->delete_log( $id );
	}

	/**
	 * Send the current filter as a CSV download. Stops the request.
	 *
	 * @param array<string, mixed> $filters List filters.
	 */
	public function export( array $filters ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="carepilot-maintenance-logs.csv"' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download. csv_line() quotes every cell.
		echo "\xEF\xBB\xBF";
		echo $this->csv_line(
			array(
				__( 'Title', 'carepilot' ),
				__( 'Description', 'carepilot' ),
				__( 'Category', 'carepilot' ),
				__( 'Status', 'carepilot' ),
				__( 'Completed', 'carepilot' ),
				__( 'Recorded', 'carepilot' ),
				__( 'Internal notes', 'carepilot' ),
			)
		);

		$offset = 0;
		do {
			$filters['limit']  = 500;
			$filters['offset'] = $offset;
			$rows              = $this->db->get_logs( $filters );
			foreach ( $rows as $row ) {
				echo $this->csv_line(
					array(
						(string) $row['title'],
						(string) $row['description'],
						Api::category_label( (string) $row['category'] ),
						Api::status_label( (string) $row['status'] ),
						(string) $row['completed_at'],
						(string) $row['created_at'],
						(string) $row['internal_notes'],
					)
				);
			}
			$offset += 500;
		} while ( count( $rows ) === 500 && $offset < 5000 );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}

	/**
	 * One CSV row. Cells that start with a formula character are prefixed.
	 *
	 * @param array<int, string> $cells Cell values.
	 */
	private function csv_line( array $cells ): string {
		$out = array();
		foreach ( $cells as $cell ) {
			$cell = str_replace( array( "\r\n", "\r" ), "\n", $cell );
			if ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@' ), true ) ) {
				$cell = "'" . $cell;
			}
			$out[] = '"' . str_replace( '"', '""', $cell ) . '"';
		}

		return implode( ',', $out ) . "\r\n";
	}
}
