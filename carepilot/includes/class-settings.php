<?php
/**
 * Free plugin settings.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitizes the autoloaded settings array.
 */
class Settings {

	public const OPTION = 'wpcare_settings';

	/**
	 * Default settings used on a new install.
	 *
	 * @return array<string, int|string>
	 */
	public static function defaults(): array {
		return array(
			'tracking_enabled'    => 1,
			'retention_days'      => 365,
			'agency_name'         => '',
			'report_period'       => 'month',
			'date_format'         => '',
			'timezone'            => '',
			'excluded_activities' => '',
			'delete_data'         => 0,
		);
	}

	/**
	 * Saved settings merged over the defaults.
	 *
	 * @return array<string, int|string>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$clean = self::defaults();
		foreach ( $clean as $key => $default ) {
			if ( array_key_exists( $key, $stored ) ) {
				$clean[ $key ] = $stored[ $key ];
			}
		}

		$clean['tracking_enabled']    = empty( $clean['tracking_enabled'] ) ? 0 : 1;
		$clean['delete_data']         = empty( $clean['delete_data'] ) ? 0 : 1;
		$clean['retention_days']      = self::retention( $clean['retention_days'] );
		$clean['agency_name']         = self::agency_name( $clean['agency_name'] );
		$clean['report_period']       = self::report_period( $clean['report_period'] );
		$clean['date_format']         = self::date_format( $clean['date_format'] );
		$clean['timezone']            = self::timezone( $clean['timezone'] );
		$clean['excluded_activities'] = self::excluded( (string) $clean['excluded_activities'] );

		return $clean;
	}

	/**
	 * Settings API callback. Missing checkboxes are stored as off.
	 *
	 * @param mixed $input Posted values.
	 * @return array<string, int|string>
	 */
	public static function sanitize( $input ): array {
		$clean = self::get();
		if ( ! is_array( $input ) ) {
			return $clean;
		}

		$clean['tracking_enabled']    = empty( $input['tracking_enabled'] ) ? 0 : 1;
		$clean['delete_data']         = empty( $input['delete_data'] ) ? 0 : 1;
		$clean['retention_days']      = self::retention( $input['retention_days'] ?? 365 );
		$clean['agency_name']         = self::agency_name( $input['agency_name'] ?? '' );
		$clean['report_period']       = self::report_period( $input['report_period'] ?? 'month' );
		$clean['date_format']         = self::date_format( $input['date_format'] ?? '' );
		$clean['timezone']            = self::timezone( $input['timezone'] ?? '' );
		$clean['excluded_activities'] = self::excluded( isset( $input['excluded_activities'] ) ? (string) $input['excluded_activities'] : '' );

		return $clean;
	}

	/**
	 * Create the option on first activation without overwriting later edits.
	 */
	public static function ensure_defaults(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', true );
		}
	}

	/**
	 * Keep a known retention value. Zero means keep rows until someone deletes them.
	 *
	 * @param mixed $value Posted or stored value.
	 */
	private static function retention( $value ): int {
		$days = (int) $value;

		return in_array( $days, array( 0, 90, 180, 365 ), true ) ? $days : 365;
	}

	/**
	 * @param mixed $value Posted or stored value.
	 */
	private static function agency_name( $value ): string {
		$name = sanitize_text_field( (string) $value );
		if ( strlen( $name ) > 120 ) {
			$name = substr( $name, 0, 120 );
		}

		return $name;
	}

	/**
	 * @param mixed $value Posted or stored value.
	 */
	private static function report_period( $value ): string {
		$period = sanitize_key( (string) $value );

		return in_array( $period, array( 'month', 'quarter' ), true ) ? $period : 'month';
	}

	/**
	 * Empty means the site's own date format.
	 *
	 * @param mixed $value Posted or stored value.
	 */
	private static function date_format( $value ): string {
		$format = sanitize_text_field( (string) $value );
		if ( strlen( $format ) > 40 ) {
			$format = substr( $format, 0, 40 );
		}

		return $format;
	}

	/**
	 * Empty means the timezone in Settings → General.
	 *
	 * @param mixed $value Posted or stored value.
	 */
	private static function timezone( $value ): string {
		$zone = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $zone ) {
			return '';
		}

		return in_array( $zone, timezone_identifiers_list(), true ) ? $zone : '';
	}

	/**
	 * One activity slug per line. Unknown slugs are dropped.
	 */
	private static function excluded( string $raw ): string {
		$raw   = str_replace( array( "\r\n", "\r" ), "\n", $raw );
		$lines = explode( "\n", $raw );
		$kept  = array();

		foreach ( $lines as $line ) {
			$line = sanitize_key( $line );
			if ( '' === $line || ! in_array( $line, Api::ACTIVITY_TYPES, true ) ) {
				continue;
			}
			$kept[ $line ] = $line;
		}

		return implode( "\n", array_values( $kept ) );
	}
}
