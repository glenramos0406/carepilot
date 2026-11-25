<?php
/**
 * Manual maintenance logs.
 *
 * @package WPCareReport
 *
 * @var string $action
 * @var array<string, mixed>|null $editing
 * @var array<string, string> $filters
 * @var int $total
 * @var int $pages
 * @var int $page
 * @var array<int, array<string, mixed>> $rows
 * @var string $notice
 * @var string $error
 * @var array<string, string> $input
 */

defined( 'ABSPATH' ) || exit;

$showing_form = 'new' === $action || 'edit' === $action || '' !== $error;
$values       = array(
	'title'          => '',
	'description'    => '',
	'internal_notes' => '',
	'category'       => 'website_maintenance',
	'status'         => 'completed',
	'completed_on'   => '',
);
if ( is_array( $editing ) ) {
	$completed = (string) $editing['completed_at'];
	$values    = array(
		'title'          => (string) $editing['title'],
		'description'    => (string) $editing['description'],
		'internal_notes' => (string) $editing['internal_notes'],
		'category'       => (string) $editing['category'],
		'status'         => (string) $editing['status'],
		'completed_on'   => '' !== $completed ? substr( $completed, 0, 10 ) : '',
	);
}
if ( array() !== $input ) {
	$values = array_merge( $values, $input );
}
$log_id = is_array( $editing ) ? (int) $editing['id'] : 0;
?>
<div class="wrap">
	<div class="carepilot">
		<header class="carepilot-header">
			<div>
				<h1><?php esc_html_e( 'Maintenance logs', 'carepilot' ); ?></h1>
				<p class="carepilot-lead"><?php esc_html_e( 'Work you record yourself. These entries stay separate from updates CarePilot detects.', 'carepilot' ); ?></p>
			</div>
			<?php if ( ! $showing_form ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-logs&log_action=new' ) ); ?>"><?php esc_html_e( 'Add maintenance log', 'carepilot' ); ?></a>
			<?php endif; ?>
		</header>

		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php if ( $showing_form ) : ?>
			<form class="carepilot-card carepilot-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-logs' . ( $log_id ? '&log_action=edit&log=' . $log_id : '&log_action=new' ) ) ); ?>">
				<?php wp_nonce_field( 'wpcare_save_log' ); ?>
				<input type="hidden" name="wpcare_action" value="save">
				<input type="hidden" name="log_id" value="<?php echo esc_attr( (string) $log_id ); ?>">
				<label>
					<span><?php esc_html_e( 'Title', 'carepilot' ); ?></span>
					<input type="text" name="title" required maxlength="191" value="<?php echo esc_attr( $values['title'] ); ?>">
				</label>
				<div class="carepilot-form-row">
					<label>
						<span><?php esc_html_e( 'Category', 'carepilot' ); ?></span>
						<select name="category">
							<?php foreach ( \WPCareReport\Api::LOG_CATEGORIES as $category ) : ?>
								<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $values['category'], $category ); ?>><?php echo esc_html( \WPCareReport\Api::category_label( $category ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Status', 'carepilot' ); ?></span>
						<select name="status">
							<?php foreach ( \WPCareReport\Api::LOG_STATUSES as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $values['status'], $status ); ?>><?php echo esc_html( \WPCareReport\Api::status_label( $status ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Date completed', 'carepilot' ); ?></span>
						<input type="date" name="completed_on" value="<?php echo esc_attr( $values['completed_on'] ); ?>">
					</label>
				</div>
				<label>
					<span><?php esc_html_e( 'Description', 'carepilot' ); ?></span>
					<textarea name="description" rows="5"><?php echo esc_textarea( $values['description'] ); ?></textarea>
				</label>
				<label>
					<span><?php esc_html_e( 'Internal notes', 'carepilot' ); ?></span>
					<textarea name="internal_notes" rows="3"><?php echo esc_textarea( $values['internal_notes'] ); ?></textarea>
					<small><?php esc_html_e( 'Visible here only. Client reports do not include this field.', 'carepilot' ); ?></small>
				</label>
				<div class="carepilot-form-actions">
					<button class="button button-primary" type="submit"><?php echo $log_id ? esc_html__( 'Save changes', 'carepilot' ) : esc_html__( 'Save log', 'carepilot' ); ?></button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-logs' ) ); ?>"><?php esc_html_e( 'Cancel', 'carepilot' ); ?></a>
				</div>
			</form>
		<?php else : ?>
			<form class="carepilot-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="carepilot-logs">
				<label>
					<span class="carepilot-sr"><?php esc_html_e( 'Search titles', 'carepilot' ); ?></span>
					<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search titles', 'carepilot' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Category', 'carepilot' ); ?></span>
					<select name="category">
						<option value=""><?php esc_html_e( 'All categories', 'carepilot' ); ?></option>
						<?php foreach ( \WPCareReport\Api::LOG_CATEGORIES as $category ) : ?>
							<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $filters['category'], $category ); ?>><?php echo esc_html( \WPCareReport\Api::category_label( $category ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Status', 'carepilot' ); ?></span>
					<select name="status">
						<option value=""><?php esc_html_e( 'Any status', 'carepilot' ); ?></option>
						<?php foreach ( \WPCareReport\Api::LOG_STATUSES as $status ) : ?>
							<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filters['status'], $status ); ?>><?php echo esc_html( \WPCareReport\Api::status_label( $status ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button class="button" type="submit"><?php esc_html_e( 'Filter', 'carepilot' ); ?></button>
			</form>

			<form class="carepilot-export" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-logs' ) ); ?>">
				<?php wp_nonce_field( 'wpcare_export_logs' ); ?>
				<input type="hidden" name="wpcare_action" value="export">
				<input type="hidden" name="category" value="<?php echo esc_attr( $filters['category'] ); ?>">
				<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>">
				<input type="hidden" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>">
				<button class="button" type="submit"><?php esc_html_e( 'Export CSV', 'carepilot' ); ?></button>
			</form>

			<?php if ( array() === $rows ) : ?>
				<div class="carepilot-empty">
					<p><?php esc_html_e( 'No manual logs match this view.', 'carepilot' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-logs&log_action=new' ) ); ?>"><?php esc_html_e( 'Add maintenance log', 'carepilot' ); ?></a>
				</div>
			<?php else : ?>
				<table class="carepilot-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Task', 'carepilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Category', 'carepilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Completed', 'carepilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'carepilot' ); ?></th>
							<th scope="col"><span class="carepilot-sr"><?php esc_html_e( 'Actions', 'carepilot' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td>
									<?php echo esc_html( (string) $row['title'] ); ?>
									<?php if ( '' !== (string) $row['description'] ) : ?>
										<span class="carepilot-meta"><?php echo esc_html( (string) $row['description'] ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== (string) $row['internal_notes'] ) : ?>
										<details>
											<summary><?php esc_html_e( 'Internal notes', 'carepilot' ); ?></summary>
											<p><?php echo esc_html( (string) $row['internal_notes'] ); ?></p>
										</details>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( \WPCareReport\Api::category_label( (string) $row['category'] ) ); ?></td>
								<td><?php echo esc_html( \WPCareReport\Dashboard::format_date( (string) $row['completed_at'] ) ); ?></td>
								<td><span class="carepilot-badge is-<?php echo esc_attr( (string) $row['status'] ); ?>"><?php echo esc_html( \WPCareReport\Api::status_label( (string) $row['status'] ) ); ?></span></td>
								<td class="carepilot-row-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-logs&log_action=edit&log=' . (int) $row['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'carepilot' ); ?></a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-logs' ) ); ?>" data-wpcare-confirm="<?php esc_attr_e( 'Delete this maintenance log?', 'carepilot' ); ?>">
										<?php wp_nonce_field( 'wpcare_delete_log' ); ?>
										<input type="hidden" name="wpcare_action" value="delete">
										<input type="hidden" name="log_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>">
										<button type="submit"><?php esc_html_e( 'Delete', 'carepilot' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $pages > 1 ) : ?>
					<nav class="carepilot-pager" aria-label="<?php esc_attr_e( 'Log pages', 'carepilot' ); ?>">
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
		<?php endif; ?>
	</div>
</div>
