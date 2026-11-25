<?php
/**
 * Settings that change tracking, retention, and uninstall.
 *
 * @package WPCareReport
 *
 * @var array<string, int|string> $settings
 * @var string                    $notice
 * @var array<int, string>        $excluded
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<div class="carepilot">
		<header class="carepilot-header">
			<div>
				<h1><?php esc_html_e( 'Settings', 'carepilot' ); ?></h1>
				<p class="carepilot-lead"><?php esc_html_e( 'These choices apply to this site only. CarePilot does not send them anywhere.', 'carepilot' ); ?></p>
			</div>
		</header>

		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<form class="carepilot-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-settings' ) ); ?>">
			<?php wp_nonce_field( 'wpcare_save_settings' ); ?>

			<section class="carepilot-card">
				<h2><?php esc_html_e( 'Activity tracking', 'carepilot' ); ?></h2>
				<label class="carepilot-check">
					<input type="checkbox" name="wpcare_settings[tracking_enabled]" value="1" <?php checked( 1, (int) $settings['tracking_enabled'] ); ?>>
					<span><?php esc_html_e( 'Record finished updates, activations, and theme changes', 'carepilot' ); ?></span>
				</label>
				<p class="carepilot-meta"><?php esc_html_e( 'Turning this off stops new detected rows. Rows already stored stay in place.', 'carepilot' ); ?></p>

				<fieldset>
					<legend><?php esc_html_e( 'Do not record these', 'carepilot' ); ?></legend>
					<?php foreach ( \WPCareReport\Api::ACTIVITY_TYPES as $wpcare_type ) : ?>
						<label class="carepilot-check">
							<input type="checkbox" name="wpcare_settings[excluded_activities][]" value="<?php echo esc_attr( $wpcare_type ); ?>" <?php checked( in_array( $wpcare_type, $excluded, true ) ); ?>>
							<span><?php echo esc_html( \WPCareReport\Api::activity_label( $wpcare_type ) ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</section>

			<section class="carepilot-card">
				<h2><?php esc_html_e( 'How long to keep rows', 'carepilot' ); ?></h2>
				<label>
					<span><?php esc_html_e( 'Delete detected activity and manual logs older than', 'carepilot' ); ?></span>
					<select name="wpcare_settings[retention_days]">
						<option value="90" <?php selected( 90, (int) $settings['retention_days'] ); ?>><?php esc_html_e( '90 days', 'carepilot' ); ?></option>
						<option value="180" <?php selected( 180, (int) $settings['retention_days'] ); ?>><?php esc_html_e( '180 days', 'carepilot' ); ?></option>
						<option value="365" <?php selected( 365, (int) $settings['retention_days'] ); ?>><?php esc_html_e( '365 days', 'carepilot' ); ?></option>
						<option value="0" <?php selected( 0, (int) $settings['retention_days'] ); ?>><?php esc_html_e( 'Keep until I delete them', 'carepilot' ); ?></option>
					</select>
				</label>
				<p class="carepilot-meta"><?php esc_html_e( 'A daily cleanup removes rows older than this. Zero keeps every row.', 'carepilot' ); ?></p>
			</section>

			<section class="carepilot-card">
				<h2><?php esc_html_e( 'If this plugin is deleted', 'carepilot' ); ?></h2>
				<label class="carepilot-check">
					<input type="checkbox" name="wpcare_settings[delete_data]" value="1" <?php checked( 1, (int) $settings['delete_data'] ); ?>>
					<span><?php esc_html_e( 'Delete all CarePilot data if I delete this plugin', 'carepilot' ); ?></span>
				</label>
				<p class="carepilot-meta"><?php esc_html_e( 'Deactivating the plugin keeps the data. Leave this off unless you want the tables removed when the plugin is deleted.', 'carepilot' ); ?></p>
			</section>

			<p class="carepilot-form-actions">
				<button class="button button-primary" type="submit"><?php esc_html_e( 'Save settings', 'carepilot' ); ?></button>
			</p>
		</form>
	</div>
</div>
