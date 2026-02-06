<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIDashboard
	 *
	 * Displays meaningful statistics and an overview of the double opt-in system.
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class UIDashboard extends UIPage {

		/**
		 * Constructor.
		 *
		 * @param LoggerInterface $logger          The logger instance.
		 * @param TemplateHandler $templateHandler The template handler.
		 * @param string          $domain          The text domain.
		 */
		public function __construct( LoggerInterface $logger, TemplateHandler $templateHandler, string $domain ) {
			parent::__construct(
				$logger,
				$templateHandler,
				$domain,
				'f12-cf7-doubleoptin',
				__( 'Dashboard', 'double-opt-in' ),
				0
			);

			// Register admin styles
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
		}

		/**
		 * Enqueue admin assets for the dashboard page.
		 *
		 * @param string $hook The current admin page hook.
		 *
		 * @return void
		 */
		public function enqueueAssets( string $hook ): void {
			// Only load on our dashboard page
			if ( strpos( $hook, 'f12-cf7-doubleoptin' ) === false || strpos( $hook, '_page_' ) !== false ) {
				// The main dashboard is 'toplevel_page_f12-cf7-doubleoptin', subpages have '_page_'
				if ( strpos( $hook, 'toplevel_page_f12-cf7-doubleoptin' ) === false ) {
					return;
				}
			}

			wp_enqueue_style(
				'doi-dashboard',
				plugins_url( 'assets/css/dashboard.css', dirname( __FILE__ ) ),
				[],
				FORGE12_OPTIN_VERSION
			);
		}

		/** @inheritDoc */
		public function getSettings( $settings ) {
			return $settings;
		}

		/** @inheritDoc */
		protected function onSave( $settings ) {
			return $settings;
		}

		/** @inheritDoc */
		public function theContent( $slug, $page, $settings ) {
			global $wpdb;
			$table = $wpdb->prefix . 'f12_cf7_doubleoptin';

			$activity     = $this->getActivity( $wpdb, $table );
			$topForms     = $this->getTopForms( $wpdb, $table );
			$recentOptIns = $this->getRecentOptIns( $wpdb, $table );
			$totals       = $this->getTotals( $wpdb, $table );

			// Calculate conversion rate
			$conversionRate = $totals['total'] > 0
				? round( ( $totals['confirmed'] / $totals['total'] ) * 100, 1 )
				: 0;

			?>
			<div class="doi-dashboard-header">
				<h1><?php _e( 'Dashboard', 'double-opt-in' ); ?></h1>
				<p><?php _e( 'Overview of your Double Opt-In activity', 'double-opt-in' ); ?></p>
			</div>

			<!-- Stats Cards -->
			<div class="doi-stats-grid">
				<div class="doi-stat-card doi-stat-primary">
					<div class="doi-stat-icon">
						<span class="dashicons dashicons-email-alt"></span>
					</div>
					<div class="doi-stat-value"><?php echo esc_html( number_format_i18n( $totals['total'] ) ); ?></div>
					<div class="doi-stat-label"><?php _e( 'Total Opt-Ins', 'double-opt-in' ); ?></div>
				</div>
				<div class="doi-stat-card doi-stat-success">
					<div class="doi-stat-icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<div class="doi-stat-value"><?php echo esc_html( number_format_i18n( $totals['confirmed'] ) ); ?></div>
					<div class="doi-stat-label"><?php _e( 'Confirmed', 'double-opt-in' ); ?></div>
				</div>
				<div class="doi-stat-card doi-stat-warning">
					<div class="doi-stat-icon">
						<span class="dashicons dashicons-clock"></span>
					</div>
					<div class="doi-stat-value"><?php echo esc_html( number_format_i18n( $totals['total'] - $totals['confirmed'] ) ); ?></div>
					<div class="doi-stat-label"><?php _e( 'Pending', 'double-opt-in' ); ?></div>
				</div>
				<div class="doi-stat-card doi-stat-info">
					<div class="doi-stat-icon">
						<span class="dashicons dashicons-chart-bar"></span>
					</div>
					<div class="doi-stat-value"><?php echo esc_html( $conversionRate ); ?>%</div>
					<div class="doi-stat-label"><?php _e( 'Conversion Rate', 'double-opt-in' ); ?></div>
				</div>
			</div>

			<div class="doi-grid-2">
				<!-- Activity Section -->
				<div class="doi-section">
					<div class="doi-section-header">
						<h2><span class="dashicons dashicons-chart-line"></span> <?php _e( 'Activity', 'double-opt-in' ); ?></h2>
					</div>
					<table class="doi-table">
						<thead>
							<tr>
								<th><?php _e( 'Period', 'double-opt-in' ); ?></th>
								<th><?php _e( 'New Opt-Ins', 'double-opt-in' ); ?></th>
								<th><?php _e( 'Confirmed', 'double-opt-in' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php _e( 'Last 24 Hours', 'double-opt-in' ); ?></td>
								<td class="doi-number"><?php echo esc_html( number_format_i18n( $activity['24h']['total'] ) ); ?></td>
								<td class="doi-number"><?php echo esc_html( number_format_i18n( $activity['24h']['confirmed'] ) ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Last 7 Days', 'double-opt-in' ); ?></td>
								<td class="doi-number"><?php echo esc_html( number_format_i18n( $activity['7d']['total'] ) ); ?></td>
								<td class="doi-number"><?php echo esc_html( number_format_i18n( $activity['7d']['confirmed'] ) ); ?></td>
							</tr>
							<tr>
								<td><?php _e( 'Last 30 Days', 'double-opt-in' ); ?></td>
								<td class="doi-number"><?php echo esc_html( number_format_i18n( $activity['30d']['total'] ) ); ?></td>
								<td class="doi-number"><?php echo esc_html( number_format_i18n( $activity['30d']['confirmed'] ) ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Top Forms Section -->
				<div class="doi-section">
					<div class="doi-section-header">
						<h2><span class="dashicons dashicons-forms"></span> <?php _e( 'Top Forms', 'double-opt-in' ); ?></h2>
					</div>
					<?php if ( ! empty( $topForms ) ) : ?>
					<table class="doi-table">
						<thead>
							<tr>
								<th><?php _e( 'Form', 'double-opt-in' ); ?></th>
								<th><?php _e( 'Opt-Ins', 'double-opt-in' ); ?></th>
								<th><?php _e( 'Rate', 'double-opt-in' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $topForms as $form ) :
								$post = get_post( (int) $form->cf_form_id );
								$name = $post ? $post->post_title : sprintf( __( 'Form #%d', 'double-opt-in' ), $form->cf_form_id );
								$link = get_edit_post_link( (int) $form->cf_form_id, 'raw' );
								$rate = $form->total > 0 ? round( $form->confirmed / $form->total * 100 ) : 0;
							?>
							<tr>
								<td>
									<?php if ( $link ) : ?>
										<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $name ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $name ); ?>
									<?php endif; ?>
								</td>
								<td class="doi-number"><?php echo esc_html( number_format_i18n( $form->total ) ); ?></td>
								<td>
									<div class="doi-rate-bar">
										<div class="doi-rate-bar-bg">
											<div class="doi-rate-bar-fill" style="width: <?php echo esc_attr( $rate ); ?>%;"></div>
										</div>
										<span class="doi-rate-value"><?php echo esc_html( $rate ); ?>%</span>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php else : ?>
					<div class="doi-empty-state">
						<span class="dashicons dashicons-forms"></span>
						<p><?php _e( 'No form data available yet.', 'double-opt-in' ); ?></p>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Recent Opt-Ins -->
			<div class="doi-section">
				<div class="doi-section-header">
					<h2><span class="dashicons dashicons-list-view"></span> <?php _e( 'Recent Opt-Ins', 'double-opt-in' ); ?></h2>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_optins' ) ); ?>" class="doi-view-all">
						<?php _e( 'View All', 'double-opt-in' ); ?> &rarr;
					</a>
				</div>
				<?php if ( ! empty( $recentOptIns ) ) : ?>
				<table class="doi-table">
					<thead>
						<tr>
							<th><?php _e( 'E-Mail', 'double-opt-in' ); ?></th>
							<th><?php _e( 'Form', 'double-opt-in' ); ?></th>
							<th><?php _e( 'Status', 'double-opt-in' ); ?></th>
							<th><?php _e( 'Date', 'double-opt-in' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recentOptIns as $optIn ) : ?>
						<tr>
							<td><?php echo esc_html( $optIn->get_email() ); ?></td>
							<td><?php echo esc_html( $optIn->get_form_name() ); ?></td>
							<td>
								<?php if ( $optIn->is_confirmed() ) : ?>
									<span class="doi-badge doi-badge-confirmed">
										<span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px;"></span>
										<?php _e( 'Confirmed', 'double-opt-in' ); ?>
									</span>
								<?php else : ?>
									<span class="doi-badge doi-badge-pending">
										<span class="dashicons dashicons-clock" style="font-size:14px;width:14px;height:14px;"></span>
										<?php _e( 'Pending', 'double-opt-in' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $optIn->get_createtime( 'formatted' ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
				<div class="doi-empty-state">
					<span class="dashicons dashicons-email-alt"></span>
					<p><?php _e( 'No opt-ins recorded yet.', 'double-opt-in' ); ?></p>
				</div>
				<?php endif; ?>
			</div>
			<?php
		}

		/** @inheritDoc */
		public function theSidebar( $slug, $page ) {
			$settings = CF7DoubleOptIn::getInstance()->getSettings();
			?>
			<div class="box">
				<h2><?php _e( 'Quick Info', 'double-opt-in' ); ?></h2>
				<p>
					<strong><?php _e( 'Version:', 'double-opt-in' ); ?></strong>
					<?php echo esc_html( FORGE12_OPTIN_VERSION ); ?>
				</p>
				<p>
					<strong><?php _e( 'Token Expiry:', 'double-opt-in' ); ?></strong>
					<?php
					$expiry = (int) ( $settings['token_expiry_hours'] ?? 48 );
					echo $expiry > 0
						? sprintf( _n( '%d hour', '%d hours', $expiry, 'double-opt-in' ), $expiry )
						: __( 'Disabled', 'double-opt-in' );
					?>
				</p>
				<p>
					<strong><?php _e( 'Retention:', 'double-opt-in' ); ?></strong>
					<?php
					$deleteVal    = (int) ( $settings['delete'] ?? 12 );
					$deletePeriod = $settings['delete_period'] ?? 'months';
					echo $deleteVal > 0
						? esc_html( $deleteVal . ' ' . $deletePeriod )
						: __( 'Disabled', 'double-opt-in' );
					?>
				</p>
				<p>
					<strong><?php _e( 'Rate Limit:', 'double-opt-in' ); ?></strong>
					<?php
					$rateIp = (int) ( $settings['rate_limit_ip'] ?? 5 );
					echo $rateIp > 0
						? sprintf( __( '%d per IP / %d min', 'double-opt-in' ), $rateIp, (int) ( $settings['rate_limit_window'] ?? 60 ) )
						: __( 'Disabled', 'double-opt-in' );
					?>
				</p>
			</div>
			<div class="box">
				<h2><?php _e( 'Quick Links', 'double-opt-in' ); ?></h2>
				<ul class="doi-quick-links">
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_optins' ) ); ?>"><?php _e( 'All Opt-Ins', 'double-opt-in' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_forms' ) ); ?>"><?php _e( 'Form Settings', 'double-opt-in' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_settings' ) ); ?>"><?php _e( 'Global Settings', 'double-opt-in' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=f12-cf7-doubleoptin_database' ) ); ?>"><?php _e( 'Database Management', 'double-opt-in' ); ?></a></li>
				</ul>
			</div>
			<?php
		}

		/**
		 * Get total counts for all opt-ins.
		 *
		 * @param \wpdb  $wpdb  The WordPress database instance.
		 * @param string $table The table name.
		 *
		 * @return array
		 */
		private function getTotals( $wpdb, string $table ): array {
			return [
				'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
				'confirmed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE doubleoptin = 1" ),
			];
		}

		/**
		 * Get activity data for different time periods.
		 *
		 * @param \wpdb  $wpdb  The WordPress database instance.
		 * @param string $table The table name.
		 *
		 * @return array
		 */
		private function getActivity( $wpdb, string $table ): array {
			$periods = [
				'24h' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				'7d'  => gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ),
				'30d' => gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ),
			];

			$result = [];
			foreach ( $periods as $key => $since ) {
				$result[ $key ] = [
					'total'     => (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE createtime >= %s",
						$since
					) ),
					'confirmed' => (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE updatetime >= %s AND doubleoptin = 1",
						$since
					) ),
				];
			}

			return $result;
		}

		/**
		 * Get top forms by opt-in count.
		 *
		 * @param \wpdb  $wpdb  The WordPress database instance.
		 * @param string $table The table name.
		 *
		 * @return array
		 */
		private function getTopForms( $wpdb, string $table ): array {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT cf_form_id,
				        COUNT(*) AS total,
				        SUM(doubleoptin) AS confirmed
				 FROM {$table}
				 GROUP BY cf_form_id
				 ORDER BY total DESC
				 LIMIT %d",
				5
			) );
		}

		/**
		 * Get the 5 most recent opt-ins.
		 *
		 * @param \wpdb  $wpdb  The WordPress database instance.
		 * @param string $table The table name.
		 *
		 * @return array<OptIn>
		 */
		private function getRecentOptIns( $wpdb, string $table ): array {
			return OptIn::get_list( [
				'perPage' => 5,
				'page'    => 1,
				'order'   => 'DESC',
				'keyword' => '',
			] );
		}
	}
}
