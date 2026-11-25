<?php
/**
 * Automatic maintenance activity.
 *
 * @package WPCareReport
 *
 * @var array<string, string> $filters
 * @var int $total
 * @var int $pages
 * @var int $page
 * @var array<int, array<string, mixed>> $rows
 * @var string $notice
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<div class="carepilot">
		<header class="carepilot-header">
			<div>
				<h1><?php esc_html_e( 'Maintenance activity', 'carepilot' ); ?></h1>
				<p class="carepilot-lead"><?php esc_html_e( 'Updates and changes CarePilot detected. A row is added when the work has finished, not when it is only started.', 'carepilot' ); ?></p>
			</div>
		</header>

		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<form class="carepilot-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="carepilot-activity">
			<label>
				<span><?php esc_html_e( 'Type', 'carepilot' ); ?></span>
				<select name="type">
					<option value=""><?php esc_html_e( 'All types', 'carepilot' ); ?></option>
					<?php foreach ( \WPCareReport\Api::ACTIVITY_TYPES as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['type'], $type ); ?>><?php echo esc_html( \WPCareReport\Api::activity_label( $type ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Status', 'carepilot' ); ?></span>
				<select name="status">
					<option value=""><?php esc_html_e( 'Any status', 'carepilot' ); ?></option>
					<?php foreach ( \WPCareReport\Api::ACTIVITY_STATUSES as $status ) : ?>
						<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filters['status'], $status ); ?>><?php echo esc_html( \WPCareReport\Api::status_label( $status ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button class="button" type="submit"><?php esc_html_e( 'Filter', 'carepilot' ); ?></button>
		</form>

		<?php if ( array() === $rows ) : ?>
			<div class="carepilot-empty">
				<p><?php esc_html_e( 'No detected activity matches this view. New rows appear after the next finished update, activation, or theme change.', 'carepilot' ); ?></p>
			</div>
		<?php else : ?>
			<table class="carepilot-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Activity', 'carepilot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'carepilot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'carepilot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'carepilot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td>
								<?php echo esc_html( (string) $row['description'] ); ?>
								<span class="carepilot-meta">
									<?php
									echo esc_html( (string) $row['component_name'] );
									if ( '' !== (string) $row['previous_version'] || '' !== (string) $row['new_version'] ) {
										echo esc_html( ' · ' . (string) $row['previous_version'] . ' → ' . (string) $row['new_version'] );
									}
									?>
								</span>
							</td>
							<td><?php echo esc_html( \WPCareReport\Api::activity_label( (string) $row['activity_type'] ) ); ?></td>
							<td><?php echo esc_html( \WPCareReport\Dashboard::format_time( (string) $row['created_at'] ) ); ?></td>
							<td><span class="carepilot-badge is-<?php echo esc_attr( (string) $row['status'] ); ?>"><?php echo esc_html( \WPCareReport\Api::status_label( (string) $row['status'] ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $pages > 1 ) : ?>
				<nav class="carepilot-pager" aria-label="<?php esc_attr_e( 'Activity pages', 'carepilot' ); ?>">
					<?php
					echo wp_kses_post(
						(string) paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $page,
								'total'     => $pages,
								'add_args'  => false,
								'prev_text' => __( 'Previous', 'carepilot' ),
								'next_text' => __( 'Next', 'carepilot' ),
							)
						)
					);
					?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
