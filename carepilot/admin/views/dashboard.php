<?php
/**
 * CarePilot dashboard.
 *
 * @package WPCareReport
 *
 * @var array<string, int>  $stats
 * @var array<string, mixed> $site
 * @var array<int, array{key: string, label: string, automatic: int, manual: int}> $chart
 * @var array<int, array{source: string, label: string, meta: string, when: string, status: string}> $recent
 * @var string $notice
 * @var int $max
 */

defined( 'ABSPATH' ) || exit;

$empty = 0 === $stats['activities'] && 0 === $stats['logs'];
?>
<div class="wrap">
	<div class="carepilot">
		<header class="carepilot-header">
			<div>
				<h1><?php esc_html_e( 'CarePilot', 'carepilot' ); ?></h1>
				<p class="carepilot-lead"><?php esc_html_e( 'A record of the maintenance finished on this site.', 'carepilot' ); ?></p>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-logs&log_action=new' ) ); ?>"><?php esc_html_e( 'Add maintenance log', 'carepilot' ); ?></a>
		</header>

		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<section class="carepilot-stats" aria-label="<?php esc_attr_e( 'Maintenance totals', 'carepilot' ); ?>">
			<?php
			$cards = array(
				array( $stats['activities'], __( 'Maintenance activities', 'carepilot' ) ),
				array( $stats['core_updates'], __( 'WordPress updates', 'carepilot' ) ),
				array( $stats['plugin_updates'], __( 'Plugin updates', 'carepilot' ) ),
				array( $stats['theme_updates'], __( 'Theme updates', 'carepilot' ) ),
				array( $stats['logs'], __( 'Manual tasks', 'carepilot' ) ),
				array( $stats['pending'], __( 'Pending tasks', 'carepilot' ) ),
			);
			foreach ( $cards as $card ) :
				?>
				<article class="carepilot-stat">
					<strong><?php echo esc_html( (string) $card[0] ); ?></strong>
					<span><?php echo esc_html( $card[1] ); ?></span>
				</article>
			<?php endforeach; ?>
		</section>

		<div class="carepilot-grid">
			<section class="carepilot-card">
				<h2><?php esc_html_e( 'Website', 'carepilot' ); ?></h2>
				<dl class="carepilot-facts">
					<div>
						<dt><?php esc_html_e( 'Address', 'carepilot' ); ?></dt>
						<dd><a href="<?php echo esc_url( (string) $site['url'] ); ?>"><?php echo esc_html( (string) $site['url'] ); ?></a></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'WordPress', 'carepilot' ); ?></dt>
						<dd><?php echo esc_html( (string) $site['wp'] ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'PHP', 'carepilot' ); ?></dt>
						<dd><?php echo esc_html( (string) $site['php'] ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Active theme', 'carepilot' ); ?></dt>
						<dd><?php echo esc_html( (string) $site['theme'] ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Plugins', 'carepilot' ); ?></dt>
						<dd>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: active plugin count, 2: installed plugin count. */
									__( '%1$d active, %2$d installed', 'carepilot' ),
									(int) $site['active'],
									(int) $site['installed']
								)
							);
							?>
						</dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'HTTPS', 'carepilot' ); ?></dt>
						<dd><?php echo ! empty( $site['https'] ) ? esc_html__( 'On', 'carepilot' ) : esc_html__( 'Off', 'carepilot' ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Last maintenance', 'carepilot' ); ?></dt>
						<dd>
							<?php
							$last = \WPCareReport\Dashboard::format_time( (string) $site['last'] );
							echo '' !== $last ? esc_html( $last ) : esc_html__( 'Nothing recorded yet', 'carepilot' );
							?>
						</dd>
					</div>
				</dl>
			</section>

			<section class="carepilot-card">
				<h2><?php esc_html_e( 'Last 30 days', 'carepilot' ); ?></h2>
				<ul class="carepilot-chart">
					<?php foreach ( $chart as $day ) : ?>
						<?php
						$total      = $day['automatic'] + $day['manual'];
						$share      = $total > 0 ? max( 8, (int) round( ( $total / $max ) * 100 ) ) : 0;
						$auto_share = $total > 0 ? (int) round( ( $day['automatic'] / $total ) * 100 ) : 0;
						$day_label  = sprintf(
							/* translators: 1: date, 2: automatic count, 3: manual count. */
							__( '%1$s, %2$d automatic, %3$d manual', 'carepilot' ),
							$day['label'],
							$day['automatic'],
							$day['manual']
						);
						?>
						<li>
							<span class="carepilot-stack" style="<?php echo esc_attr( 'height:' . $share . '%' ); ?>" title="<?php echo esc_attr( $day_label ); ?>">
								<span class="carepilot-sr"><?php echo esc_html( $day_label ); ?></span>
								<?php if ( $day['automatic'] > 0 ) : ?>
									<span class="is-auto" style="<?php echo esc_attr( 'height:' . $auto_share . '%' ); ?>"></span>
								<?php endif; ?>
								<?php if ( $day['manual'] > 0 ) : ?>
									<span class="is-manual" style="<?php echo esc_attr( 'height:' . ( 100 - $auto_share ) . '%' ); ?>"></span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<div class="carepilot-chart-meta">
					<span><?php echo esc_html( $chart[0]['label'] ); ?></span>
					<span class="carepilot-legend"><i class="is-auto"></i> <?php esc_html_e( 'Automatic', 'carepilot' ); ?></span>
					<span class="carepilot-legend"><i class="is-manual"></i> <?php esc_html_e( 'Manual', 'carepilot' ); ?></span>
					<span><?php echo esc_html( $chart[ count( $chart ) - 1 ]['label'] ); ?></span>
				</div>
			</section>
		</div>

		<section class="carepilot-card">
			<h2><?php esc_html_e( 'Recent activity', 'carepilot' ); ?></h2>
			<?php if ( $empty ) : ?>
				<div class="carepilot-empty">
					<p><?php esc_html_e( 'Nothing has been recorded yet. CarePilot adds a line when a WordPress, plugin, or theme update finishes. Work WordPress cannot see, such as a content change or a layout fix, can be added as a manual log.', 'carepilot' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=carepilot-logs&log_action=new' ) ); ?>"><?php esc_html_e( 'Add maintenance log', 'carepilot' ); ?></a>
				</div>
			<?php else : ?>
				<table class="carepilot-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Activity', 'carepilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Source', 'carepilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date', 'carepilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'carepilot' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent as $row ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $row['label'] ); ?>
									<span class="carepilot-meta"><?php echo esc_html( $row['meta'] ); ?></span>
								</td>
								<td><?php echo 'manual' === $row['source'] ? esc_html__( 'Manual task', 'carepilot' ) : esc_html__( 'Detected', 'carepilot' ); ?></td>
								<td><?php echo esc_html( \WPCareReport\Dashboard::format_time( $row['when'] ) ); ?></td>
								<td><span class="carepilot-badge is-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( \WPCareReport\Api::status_label( $row['status'] ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
	</div>
</div>
