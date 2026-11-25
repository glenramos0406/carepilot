<?php
/**
 * Writes verified maintenance events.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Records activations, theme switches, and finished core updates.
 * Plugin and theme package results are handled by Update_Tracker.
 */
class Activity_Tracker {

	public const CORE_OPTION = 'wpcare_core_version';

	/**
	 * Hook listeners. Callbacks do no work until WordPress fires the event.
	 */
	public function register(): void {
		$this->remember_core_version();
		( new Update_Tracker( $this ) )->register();
		add_action( 'activated_plugin', array( $this, 'on_activated' ), 10, 1 );
		add_action( 'deactivated_plugin', array( $this, 'on_deactivated' ), 10, 1 );
		add_action( 'switch_theme', array( $this, 'on_switch_theme' ), 10, 3 );
		add_action( '_core_updated_successfully', array( $this, 'on_core_updated' ), 10, 1 );
		add_action( 'upgrader_process_complete', array( $this, 'on_core_failed' ), 20, 2 );
	}

	/**
	 * Keep the current WordPress version so a later successful update has a "from" version.
	 * The first save is not an update.
	 */
	public function remember_core_version(): void {
		if ( false === get_option( self::CORE_OPTION, false ) ) {
			add_option( self::CORE_OPTION, wpcare_wp_version(), '', true );
		}
	}

	/**
	 * Store one event when tracking is on and this type is not excluded.
	 *
	 * @param array<string, string> $event Activity fields, including event_key.
	 */
	public function record( array $event ): int {
		if ( function_exists( 'wp_installing' ) && wp_installing() ) {
			return 0;
		}

		$settings = Settings::get();
		if ( empty( $settings['tracking_enabled'] ) ) {
			return 0;
		}

		$type     = isset( $event['activity_type'] ) ? sanitize_key( $event['activity_type'] ) : '';
		$excluded = array_filter( explode( "\n", (string) $settings['excluded_activities'] ) );
		if ( in_array( $type, $excluded, true ) ) {
			return 0;
		}

		return ( new Database() )->insert_activity( $event );
	}

	/**
	 * A plugin was activated. Silent reactivation during an update does not reach this hook.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_activated( $plugin ): void {
		$this->record_plugin_state( 'plugin_activate', is_string( $plugin ) ? $plugin : '' );
	}

	/**
	 * A plugin was deactivated. Silent deactivation during an update does not reach this hook.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_deactivated( $plugin ): void {
		$this->record_plugin_state( 'plugin_deactivate', is_string( $plugin ) ? $plugin : '' );
	}

	/**
	 * The active theme changed.
	 *
	 * @param string        $new_name  New theme name.
	 * @param \WP_Theme|mixed $new_theme New theme.
	 * @param \WP_Theme|mixed $old_theme Previous theme.
	 */
	public function on_switch_theme( $new_name, $new_theme, $old_theme ): void {
		if ( ! $new_theme instanceof \WP_Theme || ! $old_theme instanceof \WP_Theme ) {
			return;
		}
		if ( $new_theme->get_stylesheet() === $old_theme->get_stylesheet() ) {
			return;
		}

		$new_label = $this->label( is_string( $new_name ) ? $new_name : $new_theme->get( 'Name' ) );
		$old_label = $this->label( $old_theme->get( 'Name' ) );
		if ( '' === $new_label ) {
			return;
		}

		$old_version = $this->version( (string) $old_theme->get( 'Version' ) );
		$new_version = $this->version( (string) $new_theme->get( 'Version' ) );

		$this->record(
			array(
				'activity_type'    => 'theme_switch',
				'component_name'   => $new_label,
				'description'      => sprintf(
					/* translators: 1: previous theme name, 2: new theme name. */
					__( 'Switched the active theme from %1$s to %2$s.', 'carepilot' ),
					$old_label,
					$new_label
				),
				'previous_version' => $old_version,
				'new_version'      => $new_version,
				'status'           => 'completed',
				'event_key'        => $this->event_key( 'theme_switch', $new_theme->get_stylesheet(), $old_theme->get_stylesheet(), $new_version, 'completed', true ),
			)
		);
	}

	/**
	 * WordPress core finished updating. This hook runs only after a successful update.
	 *
	 * @param string $new_version Installed WordPress version.
	 */
	public function on_core_updated( $new_version ): void {
		$new      = $this->version( is_string( $new_version ) ? $new_version : '' );
		$previous = $this->version( (string) get_option( self::CORE_OPTION, '' ) );
		if ( '' === $new || $new === $previous ) {
			return;
		}

		$description = '' !== $previous
			? sprintf(
				/* translators: 1: previous WordPress version, 2: new WordPress version. */
				__( 'Updated WordPress from %1$s to %2$s.', 'carepilot' ),
				$previous,
				$new
			)
			: sprintf(
				/* translators: %s: WordPress version. */
				__( 'Updated WordPress to %s.', 'carepilot' ),
				$new
			);

		$this->record(
			array(
				'activity_type'    => 'core_update',
				'component_name'   => 'WordPress',
				'description'      => $description,
				'previous_version' => $previous,
				'new_version'      => $new,
				'status'           => 'completed',
				'event_key'        => $this->event_key( 'core_update', 'wordpress', $previous, $new, 'completed', false ),
			)
		);

		update_option( self::CORE_OPTION, $new, true );
	}

	/**
	 * A core update attempt finished without the success hook.
	 *
	 * @param \WP_Upgrader|mixed    $upgrader   Upgrader instance.
	 * @param array<string, mixed>  $hook_extra Extra update arguments.
	 */
	public function on_core_failed( $upgrader, $hook_extra ): void {
		unset( $upgrader );
		if ( ! is_array( $hook_extra ) || empty( $hook_extra['type'] ) || 'core' !== $hook_extra['type'] ) {
			return;
		}
		if ( did_action( '_core_updated_successfully' ) ) {
			return;
		}

		$previous = $this->version( (string) get_option( self::CORE_OPTION, '' ) );
		$offered  = $this->offered_core_version();
		$name     = 'WordPress';
		$description = '' !== $previous
			? sprintf(
				/* translators: %s: WordPress version still installed. */
				__( 'WordPress is still on %s. The update did not finish.', 'carepilot' ),
				$previous
			)
			: __( 'The WordPress update did not finish.', 'carepilot' );

		$this->record(
			array(
				'activity_type'    => 'core_update',
				'component_name'   => $name,
				'description'      => $description,
				'previous_version' => $previous,
				'new_version'      => $offered,
				'status'           => 'failed',
				'event_key'        => $this->event_key( 'core_update', 'wordpress', $previous, $offered, 'failed', true ),
			)
		);
	}

	/**
	 * @param string $type   plugin_activate or plugin_deactivate.
	 * @param string $plugin Plugin basename.
	 */
	private function record_plugin_state( string $type, string $plugin ): void {
		$plugin = $this->plugin_file( $plugin );
		if ( '' === $plugin ) {
			return;
		}

		$data = $this->plugin_header( $plugin );
		if ( '' === $data['name'] ) {
			return;
		}

		if ( '' !== $data['version'] ) {
			$description = sprintf(
				'plugin_activate' === $type
					/* translators: 1: plugin name, 2: plugin version. */
					? __( 'Activated %1$s %2$s.', 'carepilot' )
					/* translators: 1: plugin name, 2: plugin version. */
					: __( 'Deactivated %1$s %2$s.', 'carepilot' ),
				$data['name'],
				$data['version']
			);
		} else {
			$description = sprintf(
				'plugin_activate' === $type
					/* translators: %s: plugin name. */
					? __( 'Activated %s.', 'carepilot' )
					/* translators: %s: plugin name. */
					: __( 'Deactivated %s.', 'carepilot' ),
				$data['name']
			);
		}

		$this->record(
			array(
				'activity_type'    => $type,
				'component_name'   => $data['name'],
				'description'      => $description,
				'previous_version' => '',
				'new_version'      => $data['version'],
				'status'           => 'completed',
				'event_key'        => $this->event_key( $type, $plugin, '', $data['version'], 'completed', true ),
			)
		);
	}

	/**
	 * Version offered by the core update transient, when it is still present.
	 */
	private function offered_core_version(): string {
		$transient = get_site_transient( 'update_core' );
		if ( ! is_object( $transient ) || empty( $transient->updates ) || ! is_array( $transient->updates ) ) {
			return '';
		}
		$offer = $transient->updates[0];
		if ( is_object( $offer ) && ! empty( $offer->version ) ) {
			return $this->version( (string) $offer->version );
		}
		if ( is_object( $offer ) && ! empty( $offer->current ) ) {
			return $this->version( (string) $offer->current );
		}

		return '';
	}

	/**
	 * @return array{name: string, version: string}
	 */
	public function plugin_header( string $plugin ): array {
		$path = WP_PLUGIN_DIR . '/' . $plugin;
		if ( ! is_file( $path ) ) {
			return array(
				'name'    => '',
				'version' => '',
			);
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data( $path, false, false );

		return array(
			'name'    => $this->label( isset( $data['Name'] ) ? (string) $data['Name'] : '' ),
			'version' => $this->version( isset( $data['Version'] ) ? (string) $data['Version'] : '' ),
		);
	}

	/**
	 * Accept a normal plugin basename and reject paths that leave the plugins directory.
	 */
	public function plugin_file( string $plugin ): string {
		$plugin = str_replace( '\\', '/', trim( $plugin ) );
		if ( '' === $plugin || str_contains( $plugin, '..' ) || str_starts_with( $plugin, '/' ) ) {
			return '';
		}
		if ( 1 === preg_match( '/^[A-Za-z0-9._-]+\.php$/', $plugin ) ) {
			return $plugin;
		}
		if ( 1 === preg_match( '/^[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+\.php$/', $plugin ) ) {
			return $plugin;
		}

		return '';
	}

	/**
	 * Accept a theme directory name.
	 */
	public function theme_slug( string $slug ): string {
		$slug = trim( $slug );
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $slug ) ) {
			return '';
		}

		return $slug;
	}

	public function label( string $value ): string {
		$value = sanitize_text_field( $value );
		if ( strlen( $value ) > 191 ) {
			$value = substr( $value, 0, 191 );
		}

		return $value;
	}

	public function version( string $value ): string {
		$value = preg_replace( '/[^0-9A-Za-z._+-]/', '', trim( wp_strip_all_tags( $value ) ) );
		if ( ! is_string( $value ) ) {
			return '';
		}
		if ( strlen( $value ) > 50 ) {
			$value = substr( $value, 0, 50 );
		}

		return $value;
	}

	/**
	 * Same inputs produce the same key, so a hook that fires twice stores one row.
	 * Timed events include the current minute's timestamp so a later activation is kept.
	 */
	public function event_key( string $type, string $identity, string $old, string $new, string $status, bool $with_time ): string {
		$parts = array( $type, $identity, $old, $new, $status );
		if ( $with_time ) {
			$parts[] = current_time( 'mysql' );
		}

		return hash( 'sha256', implode( '|', $parts ) );
	}
}
