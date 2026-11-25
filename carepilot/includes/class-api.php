<?php
/**
 * Public contract for CarePilot Pro and other first-party code.
 *
 * Pro must call this class. It must not query the free plugin tables itself.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Stable methods and identifiers. CONTRACT changes only when a call would break.
 */
class Api {

	/**
	 * Contract understood by CarePilot Pro 1.0.
	 */
	public const CONTRACT = 1;

	/**
	 * Automatic activity slugs. Labels are translated at display time.
	 *
	 * @var array<int, string>
	 */
	public const ACTIVITY_TYPES = array(
		'core_update',
		'plugin_update',
		'plugin_install',
		'plugin_activate',
		'plugin_deactivate',
		'theme_update',
		'theme_install',
		'theme_switch',
	);

	/**
	 * Status values stored for an automatic activity.
	 *
	 * @var array<int, string>
	 */
	public const ACTIVITY_STATUSES = array(
		'completed',
		'failed',
	);

	/**
	 * Manual log categories.
	 *
	 * @var array<int, string>
	 */
	public const LOG_CATEGORIES = array(
		'website_maintenance',
		'performance',
		'security',
		'content',
		'bug_fix',
		'seo',
		'other',
	);

	/**
	 * Status values stored for a manual log.
	 *
	 * @var array<int, string>
	 */
	public const LOG_STATUSES = array(
		'completed',
		'pending',
	);

	/**
	 * Contract version. Pro compares this with its own expected contract.
	 */
	public static function contract(): int {
		return self::CONTRACT;
	}

	/**
	 * Installed free plugin version.
	 */
	public static function version(): string {
		return WPCARE_VERSION;
	}

	public static function database(): Database {
		return new Database();
	}

	/**
	 * Current free settings. The API key for performance checks is not in this array.
	 *
	 * @return array<string, int|string>
	 */
	public static function settings(): array {
		return Settings::get();
	}

	/**
	 * @param array<string, mixed> $args See Database::count_activities().
	 */
	public static function count_activities( array $args = array() ): int {
		return self::database()->count_activities( $args );
	}

	/**
	 * @param array<string, mixed> $args See Database::get_activities().
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_activities( array $args = array() ): array {
		return self::database()->get_activities( $args );
	}

	/**
	 * @param array<string, mixed> $args See Database::count_logs().
	 */
	public static function count_logs( array $args = array() ): int {
		return self::database()->count_logs( $args );
	}

	/**
	 * @param array<string, mixed> $args See Database::get_logs().
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_logs( array $args = array() ): array {
		return self::database()->get_logs( $args );
	}

	/**
	 * Plain-language label for an activity slug.
	 */
	public static function activity_label( string $slug ): string {
		$labels = array(
			'core_update'       => __( 'WordPress update', 'carepilot' ),
			'plugin_update'     => __( 'Plugin update', 'carepilot' ),
			'plugin_install'    => __( 'Plugin installation', 'carepilot' ),
			'plugin_activate'   => __( 'Plugin activation', 'carepilot' ),
			'plugin_deactivate' => __( 'Plugin deactivation', 'carepilot' ),
			'theme_update'      => __( 'Theme update', 'carepilot' ),
			'theme_install'     => __( 'Theme installation', 'carepilot' ),
			'theme_switch'      => __( 'Theme change', 'carepilot' ),
		);

		return $labels[ $slug ] ?? $slug;
	}

	/**
	 * Plain-language label for a manual log category.
	 */
	public static function category_label( string $slug ): string {
		$labels = array(
			'website_maintenance' => __( 'Website maintenance', 'carepilot' ),
			'performance'         => __( 'Performance optimization', 'carepilot' ),
			'security'            => __( 'Security', 'carepilot' ),
			'content'             => __( 'Content updates', 'carepilot' ),
			'bug_fix'             => __( 'Bug fixes', 'carepilot' ),
			'seo'                 => __( 'SEO', 'carepilot' ),
			'other'               => __( 'Other', 'carepilot' ),
		);

		return $labels[ $slug ] ?? $slug;
	}

	/**
	 * Plain-language label for a stored status.
	 */
	public static function status_label( string $slug ): string {
		$labels = array(
			'completed' => __( 'Completed', 'carepilot' ),
			'failed'    => __( 'Failed', 'carepilot' ),
			'pending'   => __( 'Pending', 'carepilot' ),
		);

		return $labels[ $slug ] ?? $slug;
	}
}
