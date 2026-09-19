<?php
/**
 * نمای جزئیات و پیگیری یک درخواست.
 *
 * چیدمان دو ستونی: سمت راست اطلاعات و تاریخچه، سمت چپ کارهایی که کارشناس
 * انجام می‌دهد (تغییر وضعیت، واگذاری، پیگیری بعدی، ساخت سفارش).
 *
 * @package parsian-preorder
 *
 * @var PPO_Request $request
 */

defined( 'ABSPATH' ) || exit;

$product   = $request->get_product();
$order     = $request->get_order();
$assignee  = $request->get_assignee();
$history   = PPO_Log::get( $request->get_id() );
$status    = $request->get_status();
$followup  = (string) $request->get( 'followup_date' );
$release   = (string) $request->get( 'release_date' );
$customer  = (int) $request->get( 'user_id' );
$back      = admin_url( 'admin.php?page=ppo-requests' );
$priority  = (string) $request->get( 'priority', 'normal' );
$kind_icon = array(
	'note'   => '✎',
	'status' => '⇄',
	'sms'    => '✉',
	'order'  => '🧾',
	'system' => '•',
);
?>
<div class="wrap ppo-wrap ppo-single">
	<h1 class="wp-heading-inline">
		<?php
		printf(
			/* translators: %s: شمارهٔ درخواست. */
			esc_html__( 'درخواست پیش‌فروش #%s', 'parsian-preorder' ),
			esc_html( ppo_digits( $request->get_id() ) )
		);
		?>
	</h1>
	<a href="<?php echo esc_url( $back ); ?>" class="page-title-action"><?php esc_html_e( 'بازگشت به فهرست', 'parsian-preorder' ); ?></a>

	<hr class="wp-header-end">

	<div class="ppo-single-grid">
		<div class="ppo-single-main">
			<div class="ppo-card">
				<h2><?php esc_html_e( 'اطلاعات درخواست', 'parsian-preorder' ); ?></h2>

				<table class="widefat striped ppo-facts">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'محصول', 'parsian-preorder' ); ?></th>
							<td>
								<?php if ( $product ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
									<?php if ( $product->get_sku() ) : ?>
										<code><?php echo esc_html( $product->get_sku() ); ?></code>
									<?php endif; ?>
								<?php else : ?>
									<?php echo esc_html( $request->get_product_name() ); ?>
									<em>(<?php esc_html_e( 'محصول دیگر در فروشگاه نیست', 'parsian-preorder' ); ?>)</em>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'تعداد و مبلغ', 'parsian-preorder' ); ?></th>
							<td>
								<?php
								printf(
									/* translators: 1: تعداد، 2: قیمت واحد، 3: مبلغ کل. */
									esc_html__( '%1$s بسته × %2$s = %3$s', 'parsian-preorder' ),
									esc_html( ppo_digits( $request->get( 'quantity', 1 ) ) ),
									esc_html( ppo_price( $request->get( 'unit_price', 0 ) ) ),
									'<strong>' . esc_html( ppo_price( $request->get_value() ) ) . '</strong>'
								);
								?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'مشتری', 'parsian-preorder' ); ?></th>
							<td>
								<?php echo esc_html( $request->get( 'name', '—' ) ); ?>
								<?php if ( $customer ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $customer ) ); ?>">(<?php esc_html_e( 'کاربر سایت', 'parsian-preorder' ); ?>)</a>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'تلفن', 'parsian-preorder' ); ?></th>
							<td>
								<a href="tel:<?php echo esc_attr( $request->get( 'phone' ) ); ?>" dir="ltr">
									<?php echo esc_html( ppo_digits( ppo_display_phone( $request->get( 'phone' ) ) ) ); ?>
								</a>
							</td>
						</tr>
						<?php if ( $request->get( 'company' ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'شرکت', 'parsian-preorder' ); ?></th>
								<td><?php echo esc_html( $request->get( 'company' ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $request->get( 'city' ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'شهر', 'parsian-preorder' ); ?></th>
								<td><?php echo esc_html( $request->get( 'city' ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $request->get( 'note' ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'توضیحات مشتری', 'parsian-preorder' ); ?></th>
								<td><?php echo nl2br( esc_html( $request->get( 'note' ) ) ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<th><?php esc_html_e( 'زمان آماده‌سازی', 'parsian-preorder' ); ?></th>
							<td>
								<?php
								echo $request->get( 'lead_days' )
									? esc_html( sprintf( /* translators: %s: تعداد روز. */ __( '%s روز کاری', 'parsian-preorder' ), ppo_digits( $request->get( 'lead_days' ) ) ) )
									: '—';
								?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'تاریخ عرضه', 'parsian-preorder' ); ?></th>
							<td>
								<?php if ( '' !== $release ) : ?>
									<?php echo esc_html( ppo_jalali_date( $release ) ); ?>
									<small>(<?php echo esc_html( ppo_relative_days( $release ) ); ?>)</small>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'تاریخ ثبت', 'parsian-preorder' ); ?></th>
							<td><?php echo esc_html( ppo_jalali_date( $request->get_date(), true ) ); ?></td>
						</tr>
						<?php if ( $order ) : ?>
							<tr>
								<th><?php esc_html_e( 'سفارش', 'parsian-preorder' ); ?></th>
								<td>
									<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
										#<?php echo esc_html( ppo_digits( $order->get_order_number() ) ); ?>
									</a>
									— <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="ppo-card">
				<h2><?php esc_html_e( 'تاریخچه و یادداشت‌ها', 'parsian-preorder' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ppo-note-form">
					<?php wp_nonce_field( 'ppo_add_note' ); ?>
					<input type="hidden" name="action" value="ppo_add_note">
					<input type="hidden" name="request" value="<?php echo esc_attr( $request->get_id() ); ?>">

					<textarea name="note" rows="2" placeholder="<?php esc_attr_e( 'نتیجهٔ تماس، توافق قیمت، شرایط چاپ…', 'parsian-preorder' ); ?>" required></textarea>
					<button type="submit" class="button"><?php esc_html_e( 'ثبت یادداشت', 'parsian-preorder' ); ?></button>
				</form>

				<?php if ( ! $history ) : ?>
					<p class="ppo-empty"><?php esc_html_e( 'هنوز رویدادی ثبت نشده است.', 'parsian-preorder' ); ?></p>
				<?php else : ?>
					<ul class="ppo-timeline">
						<?php foreach ( $history as $entry ) : ?>
							<li class="ppo-timeline-<?php echo esc_attr( $entry['kind'] ); ?>">
								<span class="ppo-timeline-icon">
									<?php echo esc_html( isset( $kind_icon[ $entry['kind'] ] ) ? $kind_icon[ $entry['kind'] ] : '•' ); ?>
								</span>
								<div class="ppo-timeline-body">
									<p><?php echo nl2br( esc_html( $entry['text'] ) ); ?></p>
									<small>
										<?php echo esc_html( ppo_jalali_date( $entry['date'], true ) ); ?>
										— <?php echo esc_html( $entry['author'] ); ?>
									</small>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>

		<div class="ppo-single-side">
			<div class="ppo-card">
				<h2><?php esc_html_e( 'پیگیری', 'parsian-preorder' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ppo_save_request' ); ?>
					<input type="hidden" name="action" value="ppo_save_request">
					<input type="hidden" name="request" value="<?php echo esc_attr( $request->get_id() ); ?>">

					<p>
						<label for="ppo-status"><?php esc_html_e( 'وضعیت', 'parsian-preorder' ); ?></label>
						<select id="ppo-status" name="status">
							<?php foreach ( PPO_Status::all() as $key => $item ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
									<?php echo esc_html( $item['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label for="ppo-assignee"><?php esc_html_e( 'مسئول پیگیری', 'parsian-preorder' ); ?></label>
						<?php
						wp_dropdown_users(
							array(
								'id'                => 'ppo-assignee',
								'name'              => 'assignee',
								'selected'          => $assignee ? $assignee->ID : 0,
								'show_option_none'  => __( 'بدون مسئول', 'parsian-preorder' ),
								'option_none_value' => 0,
								'capability'        => array( 'manage_woocommerce' ),
							)
						);
						?>
					</p>

					<p>
						<label for="ppo-followup"><?php esc_html_e( 'پیگیری بعدی', 'parsian-preorder' ); ?></label>
						<input type="text" id="ppo-followup" name="followup_date" class="ltr"
							value="<?php echo esc_attr( '' !== $followup ? ppo_jalali_date( $followup ) : '' ); ?>"
							placeholder="<?php esc_attr_e( '۱۴۰۴/۰۷/۰۵', 'parsian-preorder' ); ?>">
						<?php if ( '' !== $followup ) : ?>
							<small class="<?php echo esc_attr( $request->is_overdue() ? 'ppo-late' : '' ); ?>">
								<?php echo esc_html( ppo_relative_days( $followup ) ); ?>
							</small>
						<?php endif; ?>
					</p>

					<p>
						<label for="ppo-priority"><?php esc_html_e( 'اولویت', 'parsian-preorder' ); ?></label>
						<select id="ppo-priority" name="priority">
							<option value="normal" <?php selected( $priority, 'normal' ); ?>><?php esc_html_e( 'عادی', 'parsian-preorder' ); ?></option>
							<option value="high" <?php selected( $priority, 'high' ); ?>><?php esc_html_e( 'فوری', 'parsian-preorder' ); ?></option>
							<option value="low" <?php selected( $priority, 'low' ); ?>><?php esc_html_e( 'کم', 'parsian-preorder' ); ?></option>
						</select>
					</p>

					<hr>

					<p>
						<label for="ppo-quantity"><?php esc_html_e( 'تعداد توافق‌شده (بسته)', 'parsian-preorder' ); ?></label>
						<input type="number" id="ppo-quantity" name="quantity" min="1" step="1"
							value="<?php echo esc_attr( $request->get( 'quantity', 1 ) ); ?>">
					</p>

					<p>
						<label for="ppo-price"><?php esc_html_e( 'قیمت واحد توافق‌شده', 'parsian-preorder' ); ?></label>
						<input type="text" id="ppo-price" name="unit_price" class="ltr"
							value="<?php echo esc_attr( $request->get( 'unit_price', 0 ) ); ?>">
						<small><?php esc_html_e( 'همین مبلغ در سفارش ووکامرس نوشته می‌شود.', 'parsian-preorder' ); ?></small>
					</p>

					<p>
						<label for="ppo-status-note"><?php esc_html_e( 'یادداشت این تغییر (اختیاری)', 'parsian-preorder' ); ?></label>
						<textarea id="ppo-status-note" name="status_note" rows="2"></textarea>
					</p>

					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'ذخیره', 'parsian-preorder' ); ?>
					</button>
				</form>
			</div>

			<div class="ppo-card">
				<h2><?php esc_html_e( 'تبدیل به سفارش', 'parsian-preorder' ); ?></h2>

				<?php if ( $order ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: شمارهٔ سفارش. */
							esc_html__( 'سفارش #%s برای این درخواست ساخته شده است.', 'parsian-preorder' ),
							esc_html( ppo_digits( $order->get_order_number() ) )
						);
						?>
					</p>
					<a class="button" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
						<?php esc_html_e( 'باز کردن سفارش', 'parsian-preorder' ); ?>
					</a>
				<?php elseif ( ! $product ) : ?>
					<p class="ppo-empty"><?php esc_html_e( 'محصول این درخواست دیگر در فروشگاه نیست، پس سفارشی ساخته نمی‌شود.', 'parsian-preorder' ); ?></p>
				<?php else : ?>
					<p class="ppo-muted">
						<?php esc_html_e( 'یک سفارش «در انتظار پرداخت» با همین محصول، تعداد و قیمت توافق‌شده ساخته می‌شود و مشخصات مشتری در آن پر می‌شود. پیش از این کار، تعداد و قیمت بالا را نهایی کنید.', 'parsian-preorder' ); ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'ppo_create_order' ); ?>
						<input type="hidden" name="action" value="ppo_create_order">
						<input type="hidden" name="request" value="<?php echo esc_attr( $request->get_id() ); ?>">

						<button type="submit" class="button button-primary ppo-confirm-order">
							<?php esc_html_e( 'ساخت سفارش ووکامرس', 'parsian-preorder' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
