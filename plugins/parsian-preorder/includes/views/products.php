<?php
/**
 * نمای «محصولات پیش‌فروش» — یک نگاه به ظرفیت و تاریخ عرضهٔ همهٔ دوره‌های باز.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

$ids      = PPO_Product::preorder_product_ids();
$requests = admin_url( 'admin.php?page=ppo-requests' );
?>
<div class="wrap ppo-wrap">
	<h1><?php esc_html_e( 'محصولات پیش‌فروش', 'parsian-preorder' ); ?></h1>
	<p class="ppo-muted">
		<?php esc_html_e( 'محصولاتی که پیش‌فروششان روشن است. تنظیمات هر کدام در زبانهٔ «پیش‌فروش» صفحهٔ ویرایش همان محصول است؛ برای تغییر گروهی می‌توانید از فایل اکسل کاتالوگ استفاده کنید.', 'parsian-preorder' ); ?>
	</p>

	<?php if ( ! $ids ) : ?>
		<div class="ppo-card">
			<p class="ppo-empty">
				<?php esc_html_e( 'هنوز هیچ محصولی پیش‌فروش نشده است. در صفحهٔ ویرایش محصول، زبانهٔ «پیش‌فروش» را باز کنید و گزینهٔ «پیش‌فروش فعال است» را بزنید.', 'parsian-preorder' ); ?>
			</p>
		</div>
	<?php else : ?>
		<table class="widefat striped ppo-products">
			<thead>
				<tr>
					<th><?php esc_html_e( 'محصول', 'parsian-preorder' ); ?></th>
					<th><?php esc_html_e( 'تاریخ عرضه', 'parsian-preorder' ); ?></th>
					<th><?php esc_html_e( 'آماده‌سازی', 'parsian-preorder' ); ?></th>
					<th><?php esc_html_e( 'حداقل تیراژ', 'parsian-preorder' ); ?></th>
					<th><?php esc_html_e( 'ظرفیت', 'parsian-preorder' ); ?></th>
					<th><?php esc_html_e( 'رزروشده', 'parsian-preorder' ); ?></th>
					<th><?php esc_html_e( 'پیش‌پرداخت', 'parsian-preorder' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'parsian-preorder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $ids as $id ) : ?>
					<?php
					$product = wc_get_product( $id );

					if ( ! $product ) {
						continue;
					}

					$release   = PPO_Product::release_date( $product );
					$capacity  = PPO_Product::capacity( $product );
					$reserved  = PPO_Product::reserved( $id );
					$deposit   = PPO_Product::deposit_percent( $product );
					$active    = PPO_Product::is_preorder( $product );
					$percent   = $capacity ? min( 100, round( $reserved / $capacity * 100 ) ) : 0;
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
							<?php if ( $product->get_sku() ) : ?>
								<br><small class="ppo-muted"><?php echo esc_html( $product->get_sku() ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $release ) : ?>
								<?php echo esc_html( ppo_jalali_date( $release ) ); ?>
								<br><small><?php echo esc_html( ppo_relative_days( $release ) ); ?></small>
							<?php else : ?>
								<span class="ppo-muted">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							echo PPO_Product::lead_days( $product )
								? esc_html( ppo_digits( PPO_Product::lead_days( $product ) ) . ' ' . __( 'روز', 'parsian-preorder' ) )
								: '<span class="ppo-muted">—</span>';
							?>
						</td>
						<td><?php echo esc_html( ppo_digits( PPO_Product::min_quantity( $product ) ) ); ?></td>
						<td>
							<?php
							echo $capacity
								? esc_html( ppo_digits( $capacity ) )
								: '<span class="ppo-muted">' . esc_html__( 'بدون سقف', 'parsian-preorder' ) . '</span>';
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( 'product', $id, $requests ) ); ?>">
								<?php echo esc_html( ppo_digits( $reserved ) ); ?>
							</a>
							<?php if ( $capacity ) : ?>
								<div class="ppo-capacity"><span style="width:<?php echo esc_attr( $percent ); ?>%"></span></div>
							<?php endif; ?>
						</td>
						<td>
							<?php
							echo $deposit
								? esc_html( ppo_digits( $deposit ) . '٪' )
								: '<span class="ppo-muted">—</span>';
							?>
						</td>
						<td>
							<?php if ( ! $active ) : ?>
								<span class="ppo-badge" style="--ppo-badge-color:#64748b"><?php esc_html_e( 'پایان‌یافته', 'parsian-preorder' ); ?></span>
							<?php elseif ( PPO_Product::is_full( $product ) ) : ?>
								<span class="ppo-badge" style="--ppo-badge-color:#dc2626"><?php esc_html_e( 'ظرفیت تکمیل', 'parsian-preorder' ); ?></span>
							<?php else : ?>
								<span class="ppo-badge" style="--ppo-badge-color:#16a34a"><?php esc_html_e( 'باز', 'parsian-preorder' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
