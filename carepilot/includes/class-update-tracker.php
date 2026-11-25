<?php
/**
 * Turns package upgrader results into activity rows.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * A row is written only after install_package() returns.
 * An update that never reaches that point is not recorded, because its outcome is not known.
 */
class Update_Tracker {

	/**
	 * Versions read before each package was unpacked.
	 *
	 * @var array<string, array<string, array{name: string, version: string}>>
	 */
	private $before = array(
		'plugin' => array(),
		'theme'  => array(),
	);

	/**
	 * Package results waiting for the single completion hook.
	 *
	 * @var array<int, array{result: mixed, extra: array<string, mixed>, before: array{name: string, version: string}|null}>
	 */
	private $captured = array();

	public function __construct( private Activity_Tracker $activities ) {
	}

	/**
	 * Listen for the upgrader. Bulk updates fire the completion hook once, after every package.
	 */
	public function register(): void {
		add_filter( 'upgrader_pre_install', array( $this, 'snapshot' ), 10, 2 );
		add_filter( 'upgrader_install_package_result', array( $this, 'capture' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'flush' ), 20, 2 );
	}

	/**
	 * Read the installed version before files are replaced.
	 *
	 * @param mixed                $response   Install response. Returned unchanged.
	 * @param array<string, mixed> $hook_extra Package context.
	 * @return mixed
	 */
	public function snapshot( $response, $hook_extra ) {
		if ( is_wp_error( $response ) || ! is_array( $hook_extra ) ) {
			return $response;
		}

		$plugin = isset( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ? $this->activities->plugin_file( $hook_extra['plugin'] ) : '';
		if ( '' !== $plugin ) {
			$this->before['plugin'][ $plugin ] = $this->activities->plugin_header( $plugin );
		}

		$theme = isset( $hook_extra['theme'] ) && is_string( $hook_extra['theme'] ) ? $this->activities->theme_slug( $hook_extra['theme'] ) : '';
		if ( '' !== $theme ) {
			$this->before['theme'][ $theme ] = $this->theme_header( $theme );
		}

		return $response;
	}

	/**
	 * Hold the package result until the upgrader says the batch is finished.
	 *
	 * @param mixed                $result     Result from install_package().
	 * @param array<string, mixed> $hook_extra Package context.
	 * @return mixed
	 */
	public function capture( $result, $hook_extra ) {
		$extra  = is_array( $hook_extra ) ? $hook_extra : array();
		$before = null;
		$plugin = isset( $extra['plugin'] ) && is_string( $extra['plugin'] ) ? $this->activities->plugin_file( $extra['plugin'] ) : '';
		$theme  = isset( $extra['theme'] ) && is_string( $extra['theme'] ) ? $this->activities->theme_slug( $extra['theme'] ) : '';
		if ( '' !== $plugin && isset( $this->before['plugin'][ $plugin ] ) ) {
			$before = $this->before['plugin'][ $plugin ];
		} elseif ( '' !== $theme && isset( $this->before['theme'][ $theme ] ) ) {
			$before = $this->before['theme'][ $theme ];
		}

		$this->captured[] = array(
			'result' => $result,
			'extra'  => $extra,
			'before' => $before,
		);

		return $result;
	}

	/**
	 * Write one row per captured package. Translation packs are ignored.
	 *
	 * @param \WP_Upgrader|mixed   $upgrader   Upgrader instance.
	 * @param array<string, mixed> $hook_extra Batch context.
	 */
	public function flush( $upgrader, $hook_extra ): void {
		$type = is_array( $hook_extra ) && isset( $hook_extra['type'] ) ? (string) $hook_extra['type'] : '';
		if ( 'core' === $type || 'translation' === $type ) {
			$this->captured = array();
			return;
		}

		foreach ( $this->captured as $item ) {
			$this->record_item( $upgrader, $item['result'], $item['extra'], $item['before'] );
		}
		$this->captured = array();
	}

	/**
	 * @param \WP_Upgrader|mixed                                          $upgrader Upgrader instance.
	 * @param mixed                                                       $result   Package result.
	 * @param array<string, mixed>                                        $extra    Package context.
	 * @param array{name: string, version: string}|null                   $before   Version read beforehand.
	 */
	private function record_item( $upgrader, $result, array $extra, ?array $before ): void {
		$kind = $this->kind( $extra );
		if ( '' === $kind ) {
			return;
		}

		$failed = is_wp_error( $result ) || false === $result || null === $result;
		$after  = $this->after_header( $upgrader, $extra, $kind );
		$name   = '';
		$old    = '';
		$new    = '';

		if ( is_array( $before ) && '' !== $before['name'] ) {
			$name = $before['name'];
			$old  = $before['version'];
		}
		if ( '' !== $after['name'] ) {
			$name = $after['name'];
			$new  = $after['version'];
		}
		if ( '' === $name ) {
			$name = $this->fallback_name( $upgrader, $kind );
		}
		if ( '' === $name ) {
			return;
		}
		if ( ! $failed && '' === $new ) {
			$new = $old;
		}

		$status = $failed ? 'failed' : 'completed';
		if ( 'failed' === $status ) {
			$description = '' !== $old
				? sprintf(
					/* translators: 1: component name, 2: version still installed. */
					__( '%1$s is still on %2$s. The update did not finish.', 'carepilot' ),
					$name,
					$old
				)
				: sprintf(
					/* translators: %s: component name. */
					__( '%s did not finish installing.', 'carepilot' ),
					$name
				);
		} elseif ( str_ends_with( $kind, '_install' ) ) {
			$description = '' !== $new
				? sprintf(
					/* translators: 1: component name, 2: version. */
					__( 'Installed %1$s %2$s.', 'carepilot' ),
					$name,
					$new
				)
				: sprintf(
					/* translators: %s: component name. */
					__( 'Installed %s.', 'carepilot' ),
					$name
				);
		} elseif ( '' !== $old && '' !== $new && $old !== $new ) {
			$description = sprintf(
				/* translators: 1: component name, 2: previous version, 3: new version. */
				__( 'Updated %1$s from %2$s to %3$s.', 'carepilot' ),
				$name,
				$old,
				$new
			);
		} elseif ( '' !== $new ) {
			$description = sprintf(
				/* translators: 1: component name, 2: version. */
				__( 'Updated %1$s to %2$s.', 'carepilot' ),
				$name,
				$new
			);
		} else {
			$description = sprintf(
				/* translators: %s: component name. */
				__( 'Updated %s.', 'carepilot' ),
				$name
			);
		}

		$identity = $this->identity( $extra, $kind );
		$this->activities->record(
			array(
				'activity_type'    => $kind,
				'component_name'   => $name,
				'description'      => $description,
				'previous_version' => $old,
				'new_version'      => $new,
				'status'           => $status,
				'event_key'        => $this->activities->event_key( $kind, $identity, $old, $new, $status, false ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $extra Package context.
	 */
	private function kind( array $extra ): string {
		$action = isset( $extra['action'] ) ? sanitize_key( (string) $extra['action'] ) : 'update';
		$type   = isset( $extra['type'] ) ? sanitize_key( (string) $extra['type'] ) : '';
		if ( ! empty( $extra['plugin'] ) || 'plugin' === $type ) {
			return 'install' === $action ? 'plugin_install' : 'plugin_update';
		}
		if ( ! empty( $extra['theme'] ) || 'theme' === $type ) {
			return 'install' === $action ? 'theme_install' : 'theme_update';
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $extra Package context.
	 */
	private function identity( array $extra, string $kind ): string {
		if ( str_starts_with( $kind, 'plugin' ) && ! empty( $extra['plugin'] ) && is_string( $extra['plugin'] ) ) {
			$file = $this->activities->plugin_file( $extra['plugin'] );
			if ( '' !== $file ) {
				return $file;
			}
		}
		if ( str_starts_with( $kind, 'theme' ) && ! empty( $extra['theme'] ) && is_string( $extra['theme'] ) ) {
			$slug = $this->activities->theme_slug( $extra['theme'] );
			if ( '' !== $slug ) {
				return $slug;
			}
		}

		return $kind;
	}

	/**
	 * @param \WP_Upgrader|mixed   $upgrader Upgrader instance.
	 * @param array<string, mixed> $extra    Package context.
	 * @return array{name: string, version: string}
	 */
	private function after_header( $upgrader, array $extra, string $kind ): array {
		if ( str_starts_with( $kind, 'plugin' ) ) {
			$file = '';
			if ( ! empty( $extra['plugin'] ) && is_string( $extra['plugin'] ) ) {
				$file = $this->activities->plugin_file( $extra['plugin'] );
			} elseif ( is_object( $upgrader ) && method_exists( $upgrader, 'plugin_info' ) ) {
				$info = $upgrader->plugin_info();
				$file = is_string( $info ) ? $this->activities->plugin_file( $info ) : '';
			}
			if ( '' !== $file ) {
				return $this->activities->plugin_header( $file );
			}
		}

		if ( str_starts_with( $kind, 'theme' ) ) {
			$slug = '';
			if ( ! empty( $extra['theme'] ) && is_string( $extra['theme'] ) ) {
				$slug = $this->activities->theme_slug( $extra['theme'] );
			} elseif ( is_object( $upgrader ) && method_exists( $upgrader, 'theme_info' ) ) {
				$info = $upgrader->theme_info();
				$slug = is_string( $info ) ? $this->activities->theme_slug( $info ) : '';
			}
			if ( '' !== $slug ) {
				return $this->theme_header( $slug );
			}
		}

		return array(
			'name'    => '',
			'version' => '',
		);
	}

	/**
	 * @param \WP_Upgrader|mixed $upgrader Upgrader instance.
	 */
	private function fallback_name( $upgrader, string $kind ): string {
		if ( is_object( $upgrader ) && isset( $upgrader->new_plugin_data ) && is_array( $upgrader->new_plugin_data ) ) {
			$name = $this->activities->label( (string) ( $upgrader->new_plugin_data['Name'] ?? '' ) );
			if ( '' !== $name ) {
				return $name;
			}
		}
		if ( str_starts_with( $kind, 'theme' ) ) {
			return __( 'Theme', 'carepilot' );
		}
		if ( str_starts_with( $kind, 'plugin' ) ) {
			return __( 'Plugin', 'carepilot' );
		}

		return '';
	}

	/**
	 * @return array{name: string, version: string}
	 */
	private function theme_header( string $slug ): array {
		$theme = wp_get_theme( $slug );
		$theme->cache_delete();
		$theme = wp_get_theme( $slug );
		if ( ! $theme->exists() ) {
			return array(
				'name'    => '',
				'version' => '',
			);
		}

		return array(
			'name'    => $this->activities->label( (string) $theme->get( 'Name' ) ),
			'version' => $this->activities->version( (string) $theme->get( 'Version' ) ),
		);
	}
}
