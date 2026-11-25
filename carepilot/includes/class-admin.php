<?php
/**
 * CarePilot admin screens.
 *
 * @package WPCareReport
 */

namespace WPCareReport;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, assets, and form handlers. Assets load only on CarePilot screens.
 */
class Admin {

	/**
	 * Page hooks returned by add_menu_page and add_submenu_page.
	 *
	 * @var array<int, string>
	 */
	private $hooks = array();

	/**
	 * Validation message for the log form, when a save did not redirect.
	 */
	private string $log_error = '';

	/**
	 * Field values to show again after a validation error.
	 *
	 * @var array<string, string>
	 */
	private array $log_input = array();

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * CarePilot menu. Pro screens are added by the Pro plugin.
	 */
	public function menu(): void {
		$this->hooks[] = add_menu_page(
			__( 'CarePilot', 'carepilot' ),
			__( 'CarePilot', 'carepilot' ),
			'manage_options',
			'carepilot',
			array( $this, 'render_dashboard' ),
			'dashicons-clipboard',
			58
		);
		$this->hooks[] = add_submenu_page(
			'carepilot',
			__( 'Dashboard', 'carepilot' ),
			__( 'Dashboard', 'carepilot' ),
			'manage_options',
			'carepilot',
			array( $this, 'render_dashboard' )
		);
		$activity = add_submenu_page(
			'carepilot',
			__( 'Maintenance Activity', 'carepilot' ),
			__( 'Maintenance Activity', 'carepilot' ),
			'manage_options',
			'carepilot-activity',
			array( $this, 'render_activity' )
		);
		$logs = add_submenu_page(
			'carepilot',
			__( 'Maintenance Logs', 'carepilot' ),
			__( 'Maintenance Logs', 'carepilot' ),
			'manage_options',
			'carepilot-logs',
			array( $this, 'render_logs' )
		);
		$settings = add_submenu_page(
			'carepilot',
			__( 'Settings', 'carepilot' ),
			__( 'Settings', 'carepilot' ),
			'manage_options',
			'carepilot-settings',
			array( $this, 'render_settings' )
		);
		$this->hooks[] = $activity;
		$this->hooks[] = $logs;
		if ( is_string( $settings ) && '' !== $settings ) {
			$this->hooks[] = $settings;
		}
		add_action( 'load-' . $logs, array( $this, 'handle_logs' ) );
		if ( is_string( $settings ) && '' !== $settings ) {
			add_action( 'load-' . $settings, array( $this, 'handle_settings' ) );
		}
	}

	/**
	 * Load CSS and JS on CarePilot screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( string $hook ): void {
		if ( ! in_array( $hook, $this->hooks, true ) ) {
			return;
		}

		$css = WPCARE_DIR . 'admin/css/admin.css';
		$js  = WPCARE_DIR . 'admin/js/admin.js';
		wp_enqueue_style( 'carepilot-admin', WPCARE_URL . 'admin/css/admin.css', array(), (string) filemtime( $css ) );
		wp_enqueue_script( 'carepilot-admin', WPCARE_URL . 'admin/js/admin.js', array(), (string) filemtime( $js ), true );
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view CarePilot.', 'carepilot' ) );
		}

		$dashboard = new Dashboard( new Database() );
		$stats     = $dashboard->stats();
		$site      = $dashboard->site();
		$chart     = $dashboard->chart();
		$recent    = $dashboard->recent();
		$notice    = $this->notice();
		$max       = 1;
		foreach ( $chart as $day ) {
			$max = max( $max, $day['automatic'] + $day['manual'] );
		}

		include WPCARE_DIR . 'admin/views/dashboard.php';
	}

	public function render_activity(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view CarePilot.', 'carepilot' ) );
		}

		$filters = $this->activity_filters();
		$db      = new Database();
		$total   = $db->count_activities( $filters );
		$pages   = max( 1, (int) ceil( $total / 20 ) );
		$page    = min( $pages, max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ) );
		$filters['limit']  = 20;
		$filters['offset'] = ( $page - 1 ) * 20;
		$rows              = $db->get_activities( $filters );
		$notice            = $this->notice();

		include WPCARE_DIR . 'admin/views/activity.php';
	}

	/**
	 * Save, delete, or export before the screen is rendered.
	 */
	public function handle_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		$action = isset( $_POST['wpcare_action'] ) ? sanitize_key( wp_unslash( $_POST['wpcare_action'] ) ) : '';
		$logs   = new Maintenance_Logs( new Database() );

		if ( 'export' === $action ) {
			check_admin_referer( 'wpcare_export_logs' );
			$logs->export( $this->log_filters() );
		}

		if ( 'delete' === $action ) {
			check_admin_referer( 'wpcare_delete_log' );
			$id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
			$logs->delete( $id );
			$this->redirect_logs( 'deleted' );
		}

		if ( 'save' === $action ) {
			check_admin_referer( 'wpcare_save_log' );
			$id     = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
			$result = $logs->save( $id, wp_unslash( $_POST ) );
			if ( is_wp_error( $result ) ) {
				$this->log_error = $result->get_error_message();
				$this->log_input = $this->posted_log();
				return;
			}
			$this->redirect_logs( 'saved' );
		}
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view CarePilot.', 'carepilot' ) );
		}

		$settings = Settings::get();
		$notice   = $this->settings_notice();
		$excluded = array_filter( explode( "\n", (string) $settings['excluded_activities'] ) );

		include WPCARE_DIR . 'admin/views/settings.php';
	}

	/**
	 * Save the settings that affect this plugin today.
	 * Report fields that are not on this form keep their stored values.
	 */
	public function handle_settings(): void {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view CarePilot.', 'carepilot' ) );
		}

		check_admin_referer( 'wpcare_save_settings' );

		$current = Settings::get();
		$posted  = array();
		if ( isset( $_POST['wpcare_settings'] ) ) {
			$raw    = wp_unslash( $_POST['wpcare_settings'] );
			$posted = is_array( $raw ) ? $raw : array();
		}

		$chosen = array();
		if ( isset( $posted['excluded_activities'] ) && is_array( $posted['excluded_activities'] ) ) {
			foreach ( $posted['excluded_activities'] as $slug ) {
				$chosen[] = sanitize_key( (string) $slug );
			}
		}

		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					'tracking_enabled'    => $posted['tracking_enabled'] ?? 0,
					'delete_data'         => $posted['delete_data'] ?? 0,
					'retention_days'      => $posted['retention_days'] ?? 365,
					'excluded_activities' => implode( "\n", $chosen ),
					'agency_name'         => $current['agency_name'],
					'report_period'       => $current['report_period'],
					'date_format'         => $current['date_format'],
					'timezone'            => $current['timezone'],
				)
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                  => 'carepilot-settings',
					'wpcare_settings_notice' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view CarePilot.', 'carepilot' ) );
		}

		$action  = isset( $_GET['log_action'] ) ? sanitize_key( wp_unslash( $_GET['log_action'] ) ) : 'list';
		$db      = new Database();
		$editing = null;
		if ( 'edit' === $action ) {
			$editing = $db->get_log( isset( $_GET['log'] ) ? absint( $_GET['log'] ) : 0 );
			if ( null === $editing && '' === $this->log_error ) {
				$action = 'list';
			}
		}

		$filters = $this->log_filters();
		$total   = $db->count_logs( $filters );
		$pages   = max( 1, (int) ceil( $total / 20 ) );
		$page    = min( $pages, max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ) );
		$filters['limit']  = 20;
		$filters['offset'] = ( $page - 1 ) * 20;
		$rows              = $db->get_logs( $filters );
		$notice            = $this->notice();
		$error             = $this->log_error;
		$input             = $this->log_input;

		include WPCARE_DIR . 'admin/views/maintenance-logs.php';
	}

	/**
	 * @return array<string, string>
	 */
	private function activity_filters(): array {
		$type   = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		return array(
			'type'   => in_array( $type, Api::ACTIVITY_TYPES, true ) ? $type : '',
			'status' => in_array( $status, Api::ACTIVITY_STATUSES, true ) ? $status : '',
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function log_filters(): array {
		$category = isset( $_REQUEST['category'] ) ? sanitize_key( wp_unslash( $_REQUEST['category'] ) ) : '';
		$status   = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		return array(
			'category' => in_array( $category, Api::LOG_CATEGORIES, true ) ? $category : '',
			'status'   => in_array( $status, Api::LOG_STATUSES, true ) ? $status : '',
			'search'   => $search,
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function posted_log(): array {
		check_admin_referer( 'wpcare_save_log' );

		$date = isset( $_POST['completed_on'] ) ? sanitize_text_field( wp_unslash( $_POST['completed_on'] ) ) : '';

		return array(
			'title'          => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'internal_notes' => isset( $_POST['internal_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_notes'] ) ) : '',
			'category'       => isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '',
			'status'         => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '',
			'completed_on'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '',
		);
	}

	private function redirect_logs( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'carepilot-logs',
					'wpcare_notice'  => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function settings_notice(): string {
		$code = isset( $_GET['wpcare_settings_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpcare_settings_notice'] ) ) : '';

		return 'saved' === $code ? __( 'Settings saved.', 'carepilot' ) : '';
	}

	private function notice(): string {
		$code = isset( $_GET['wpcare_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpcare_notice'] ) ) : '';
		$messages = array(
			'saved'   => __( 'Maintenance log saved.', 'carepilot' ),
			'deleted' => __( 'Maintenance log deleted.', 'carepilot' ),
		);

		return $messages[ $code ] ?? '';
	}
}
