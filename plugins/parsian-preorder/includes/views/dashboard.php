<?php
/**
 * نمای داشبورد پیش‌فروش.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

$counts     = PPO_Metrics::counts_by_status();
$open_keys  = PPO_Status::open_keys();
$open_total = 0;

foreach ( $open_keys as $key ) {
	$open_total += isset( $counts[ $key ] ) ? $counts[ $key ] : 0;
}

$today      = PPO_Metrics::count_since( 1 );
$week       = PPO_Metrics::count_since( 7 );
$open_value = PPO_Metrics::total_value( $open_keys );
$won_value  = PPO_Metrics::total_value( array( PPO_Status::CONVERTED ) );
$conversion = PPO_Metrics::conversion_rate();
$overdue    = PPO_Metrics::overdue( 8 );
$recent     = PPO_Metrics::recent( 8 );
$top        = PPO_Metrics::top_products( 5 );
$daily      = PPO_Metrics::daily_counts( 14 );
$peak       = max( 1, max( $daily ) );
$requests   = admin_url( 'admin.php?page=ppo-requests' );
?>
<div class="wrap ppo-wrap">
	<h1><?php esc_html_e( 'داشبورد پیش‌فروش', 'parsian-preorder' ); ?></h1>

	<div class="ppo-kpis">
		<a class="ppo-kpi" href="<?php echo esc_url( $requests ); ?>">
			<span class="ppo-kpi-number"><?php echo esc_html( ppo_digits( $open_total ) ); ?></span>
			<span class="ppo-kpi-label"><?php esc_html_e( 'درخواست باز', 'parsian-preorder' ); ?></span>
		</a>
		<div class="ppo-kpi">
			<span class="ppo-kpi-number"><?php echo esc_html( ppo_digits( $today ) ); ?></span>
			<span class="ppo-kpi-label"><?php esc_html_e( 'امروز', 'parsian-preorder' ); ?></span>
		</div>
		<div class="ppo-kpi">
			<span class="ppo-kpi-number"><?php echo esc_html( ppo_digits( $week ) ); ?></span>
			<span class="ppo-kpi-label"><?php esc_html_e( 'هفتهٔ گذشته', 'parsian-preorder' ); ?></span>
		</div>
		<a class="ppo-kpi <?php echo $overdue ? 'ppo-kpi-warn' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'overdue', 1, $requests ) ); ?>">
			<span class="ppo-kpi-number"><?php echo esc_html( ppo_digits( count( $overdue ) ) ); ?></span>
			<span class="ppo-kpi-label"><?php esc_html_e( 'پیگیری عقب‌افتاده', 'parsian-preorder' ); ?></span>
		</a>
		<div class="ppo-kpi">
			<span class="ppo-kpi-number"><?php echo esc_html( ppo_price( $open_value ) ); ?></span>
			<span class="ppo-kpi-label"><?php esc_html_e( 'ارزش درخواست‌های باز', 'parsian-preorder' ); ?></span>
		</div>
		<div class="ppo-kpi">
			<span class="ppo-kpi-number"><?php echo esc_html( ppo_price( $won_value ) ); ?></span>
			<span class="ppo-kpi-label"><?php esc_html_e( 'ارزش سفارش‌شده‌ها', 'parsian-preorder' ); ?></span>
		</div>
		<div class="ppo-kpi">
			<span class="ppo-kpi-number"><?php echo esc_html( ppo_digits( $conversion ) ); ?>٪</span>
			<span class="ppo-kpi-label"><?php esc_html_e( 'نرخ تبدیل به سفارش', 'parsian-preorder' ); ?></span>
		</div>
	</div>

	<div class="ppo-grid">
		<div class="ppo-card">
			<h2><?php esc_html_e( 'خط لولهٔ پیگیری', 'parsian-preorder' ); ?></h2>
			<p class="ppo-muted"><?php esc_html_e( 'هر درخواست از «جدید» شروع می‌شود و تا «تبدیل به سفارش» یا «لغو» پیش می‌رود. روی هر مرحله بزنید تا فقط همان‌ها را ببینید.', 'parsian-preorder' ); ?></p>

			<ul class="ppo-pipeline">
				<?php foreach ( PPO_Status::all() as $key => $status ) : ?>
					<?php $count = isset( $counts[ $key ] ) ? $counts[ $key ] : 0; ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'status', $key, $requests ) ); ?>" title="<?php echo esc_attr( $status['description'] ); ?>">
							<span class="ppo-pipeline-dot" style="background:<?php echo esc_attr( $status['color'] ); ?>"></span>
							<span class="ppo-pipeline-label"><?php echo esc_html( $status['label'] ); ?></span>
							<span class="ppo-pipeline-count"><?php echo esc_html( ppo_digits( $count ) ); ?></span>
						</a>
						<div class="ppo-pipeline-bar">
							<span style="width:<?php echo esc_attr( min( 100, round( $count / max( 1, array_sum( $counts ) ) * 100 ) ) ); ?>%;background:<?php echo esc_attr( $status['color'] ); ?>"></span>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="ppo-card">
			<h2><?php esc_html_e( 'درخواست‌های دو هفتهٔ اخیر', 'parsian-preorder' ); ?></h2>

			<div class="ppo-chart">
				<?php foreach ( $daily as $day => $count ) : ?>
					<?php list( , $month, $mday ) = ppo_gregorian_to_jalali( (int) substr( $day, 0, 4 ), (int) substr( $day, 5, 2 ), (int) substr( $day, 8, 2 ) ); ?>
					<div class="ppo-chart-col" title="<?php echo esc_attr( sprintf( '%s %s: %s', ppo_digits( $mday ), ppo_jalali_month_name( $month ), ppo_digits( $count ) ) ); ?>">
						<span class="ppo-chart-bar" style="height:<?php echo esc_attr( max( 3, round( $count / $peak * 100 ) ) ); ?>%"></span>
						<small><?php echo esc_html( ppo_digits( $mday ) ); ?></small>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<div class="ppo-grid">
		<div class="ppo-card">
			<h2><?php esc_html_e( 'پیگیری‌های عقب‌افتاده', 'parsian-preorder' ); ?></h2>

			<?php if ( ! $overdue ) : ?>
				<p class="ppo-empty"><?php esc_html_e( 'هیچ درخواستی معطل نمانده است.', 'parsian-preorder' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<tbody>
						<?php foreach ( $overdue as $request ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( $request->get_admin_url() ); ?>">#<?php echo esc_html( ppo_digits( $request->get_id() ) ); ?></a>
								</td>
								<td><?php echo esc_html( $request->get_product_name() ); ?></td>
								<td dir="ltr"><?php echo esc_html( ppo_digits( ppo_display_phone( $request->get( 'phone' ) ) ) ); ?></td>
								<td><?php echo wp_kses_post( PPO_Status::badge( $request->get_status() ) ); ?></td>
								<td class="ppo-late"><?php echo esc_html( ppo_relative_days( $request->get( 'followup_date', substr( $request->get_date(), 0, 10 ) ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="ppo-card">
			<h2><?php esc_html_e( 'پرتقاضاترین محصولات', 'parsian-preorder' ); ?></h2>

			<?php if ( ! $top ) : ?>
				<p class="ppo-empty"><?php esc_html_e( 'هنوز درخواستی ثبت نشده است.', 'parsian-preorder' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'محصول', 'parsian-preorder' ); ?></th>
							<th><?php esc_html_e( 'درخواست', 'parsian-preorder' ); ?></th>
							<th><?php esc_html_e( 'بسته', 'parsian-preorder' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top as $row ) : ?>
							<?php $product = wc_get_product( $row['product_id'] ); ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'product', $row['product_id'], $requests ) ); ?>">
										<?php echo esc_html( $product ? $product->get_name() : __( '(محصول حذف‌شده)', 'parsian-preorder' ) ); ?>
									</a>
								</td>
								<td><?php echo esc_html( ppo_digits( $row['requests'] ) ); ?></td>
								<td><?php echo esc_html( ppo_digits( $row['packages'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<div class="ppo-card">
		<h2><?php esc_html_e( 'تازه‌ترین درخواست‌ها', 'parsian-preorder' ); ?></h2>

		<?php if ( ! $recent ) : ?>
			<p class="ppo-empty"><?php esc_html_e( 'هنوز درخواستی ثبت نشده است.', 'parsian-preorder' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'شماره', 'parsian-preorder' ); ?></th>
						<th><?php esc_html_e( 'محصول', 'parsian-preorder' ); ?></th>
						<th><?php esc_html_e( 'مشتری', 'parsian-preorder' ); ?></th>
						<th><?php esc_html_e( 'تعداد', 'parsian-preorder' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'parsian-preorder' ); ?></th>
						<th><?php esc_html_e( 'تاریخ', 'parsian-preorder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent as $request ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $request->get_admin_url() ); ?>">#<?php echo esc_html( ppo_digits( $request->get_id() ) ); ?></a></td>
							<td><?php echo esc_html( $request->get_product_name() ); ?></td>
							<td>
								<?php echo esc_html( $request->get( 'name', '—' ) ); ?>
								<br><small dir="ltr"><?php echo esc_html( ppo_digits( ppo_display_phone( $request->get( 'phone' ) ) ) ); ?></small>
							</td>
							<td><?php echo esc_html( ppo_digits( $request->get( 'quantity', 1 ) ) ); ?></td>
							<td><?php echo wp_kses_post( PPO_Status::badge( $request->get_status() ) ); ?></td>
							<td><?php echo esc_html( ppo_jalali_date( $request->get_date(), true ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
