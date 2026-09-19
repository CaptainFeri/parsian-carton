<?php
/**
 * نمای تنظیمات پیش‌فروش.
 *
 * @package parsian-preorder
 *
 * @var PPO_Settings $settings
 */

defined( 'ABSPATH' ) || exit;

$api_key   = $settings->sms_api_key();
$templates = (array) $settings->get( 'status_templates' );
?>
<div class="wrap ppo-wrap">
	<h1><?php esc_html_e( 'تنظیمات پیش‌فروش', 'parsian-preorder' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ppo_settings' ); ?>
		<input type="hidden" name="action" value="ppo_settings">
		<?php // به ذخیره‌کننده می‌گوید این یک ارسال کامل فرم است، پس تیک‌های نیامده خاموش‌اند. ?>
		<input type="hidden" name="ppo_form" value="1">

		<div class="ppo-card">
			<h2><?php esc_html_e( 'گردش کار', 'parsian-preorder' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ppo-initial-status"><?php esc_html_e( 'وضعیت درخواست تازه', 'parsian-preorder' ); ?></label></th>
					<td>
						<select id="ppo-initial-status" name="initial_status">
							<?php foreach ( PPO_Status::all() as $key => $status ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings->get( 'initial_status' ), $key ); ?>>
									<?php echo esc_html( $status['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-default-assignee"><?php esc_html_e( 'مسئول پیش‌فرض', 'parsian-preorder' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_users(
							array(
								'id'                => 'ppo-default-assignee',
								'name'              => 'default_assignee',
								'selected'          => (int) $settings->get( 'default_assignee' ),
								'show_option_none'  => __( 'بدون مسئول', 'parsian-preorder' ),
								'option_none_value' => 0,
								'capability'        => array( 'manage_woocommerce' ),
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'هر درخواست تازه خودکار به این کارشناس واگذار می‌شود.', 'parsian-preorder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-followup-days"><?php esc_html_e( 'پیگیری بعدی، چند روز بعد', 'parsian-preorder' ); ?></label></th>
					<td>
						<input type="number" id="ppo-followup-days" name="followup_days" min="0" step="1" class="small-text"
							value="<?php echo esc_attr( $settings->get( 'followup_days' ) ); ?>">
						<p class="description"><?php esc_html_e( 'تاریخ پیگیری هر درخواست تازه به‌صورت خودکار همین‌قدر جلوتر گذاشته می‌شود. صفر یعنی تاریخی گذاشته نشود.', 'parsian-preorder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-stale-days"><?php esc_html_e( 'مهلت پیش‌فرض پیگیری (روز)', 'parsian-preorder' ); ?></label></th>
					<td>
						<input type="number" id="ppo-stale-days" name="stale_days" min="0" step="1" class="small-text"
							value="<?php echo esc_attr( $settings->get( 'stale_days' ) ); ?>">
						<p class="description"><?php esc_html_e( 'درخواست بازی که تاریخ پیگیری ندارد و از این چند روز گذشته باشد، «عقب‌افتاده» علامت می‌خورد.', 'parsian-preorder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-duplicate"><?php esc_html_e( 'پنجرهٔ ضد تکرار (دقیقه)', 'parsian-preorder' ); ?></label></th>
					<td>
						<input type="number" id="ppo-duplicate" name="duplicate_window" min="0" step="1" class="small-text"
							value="<?php echo esc_attr( $settings->get( 'duplicate_window' ) ); ?>">
						<p class="description"><?php esc_html_e( 'ثبت دوبارهٔ همان محصول با همان شماره، در این بازه رد می‌شود.', 'parsian-preorder' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="ppo-card">
			<h2><?php esc_html_e( 'فرم درخواست', 'parsian-preorder' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'فیلدهای فرم', 'parsian-preorder' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:4px;">
							<input type="checkbox" name="ask_company" value="1" <?php checked( (bool) $settings->get( 'ask_company' ) ); ?>>
							<?php esc_html_e( 'پرسیدن نام شرکت', 'parsian-preorder' ); ?>
						</label>
						<label style="display:block;margin-bottom:4px;">
							<input type="checkbox" name="ask_city" value="1" <?php checked( (bool) $settings->get( 'ask_city' ) ); ?>>
							<?php esc_html_e( 'پرسیدن شهر', 'parsian-preorder' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" name="ask_note" value="1" <?php checked( (bool) $settings->get( 'ask_note' ) ); ?>>
							<?php esc_html_e( 'پرسیدن توضیحات', 'parsian-preorder' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-button-text"><?php esc_html_e( 'متن دکمهٔ ثبت', 'parsian-preorder' ); ?></label></th>
					<td><input type="text" id="ppo-button-text" name="button_text" class="regular-text" value="<?php echo esc_attr( $settings->get( 'button_text' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-form-intro"><?php esc_html_e( 'متن بالای فرم', 'parsian-preorder' ); ?></label></th>
					<td><textarea id="ppo-form-intro" name="form_intro" rows="2" class="large-text"><?php echo esc_textarea( $settings->get( 'form_intro' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-success-text"><?php esc_html_e( 'پیام پس از ثبت', 'parsian-preorder' ); ?></label></th>
					<td><textarea id="ppo-success-text" name="success_text" rows="2" class="large-text"><?php echo esc_textarea( $settings->get( 'success_text' ) ); ?></textarea></td>
				</tr>
			</table>
		</div>

		<div class="ppo-card">
			<h2><?php esc_html_e( 'نمایش در سایت', 'parsian-preorder' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ppo-badge-text"><?php esc_html_e( 'متن نشان', 'parsian-preorder' ); ?></label></th>
					<td>
						<input type="text" id="ppo-badge-text" name="badge_text" class="regular-text" value="<?php echo esc_attr( $settings->get( 'badge_text' ) ); ?>">
						<input type="color" name="badge_color" value="<?php echo esc_attr( $settings->get( 'badge_color' ) ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'نمایش‌ها', 'parsian-preorder' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:4px;">
							<input type="checkbox" name="show_countdown" value="1" <?php checked( (bool) $settings->get( 'show_countdown' ) ); ?>>
							<?php esc_html_e( 'نمایش «چند روز تا عرضه»', 'parsian-preorder' ); ?>
						</label>
						<label style="display:block;margin-bottom:4px;">
							<input type="checkbox" name="show_capacity" value="1" <?php checked( (bool) $settings->get( 'show_capacity' ) ); ?>>
							<?php esc_html_e( 'نمایش ظرفیت باقی‌مانده', 'parsian-preorder' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" name="myaccount_tab" value="1" <?php checked( (bool) $settings->get( 'myaccount_tab' ) ); ?>>
							<?php esc_html_e( 'زبانهٔ «پیش‌فروش‌های من» در حساب کاربری', 'parsian-preorder' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<div class="ppo-card">
			<h2><?php esc_html_e( 'پیامک', 'parsian-preorder' ); ?></h2>

			<?php if ( '' === $api_key ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'کلید API ثبت نشده است؛ درخواست‌ها ثبت می‌شوند ولی پیامکی فرستاده نمی‌شود.', 'parsian-preorder' ); ?></p>
				</div>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'ارسال پیامک', 'parsian-preorder' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sms_enabled" value="1" <?php checked( (bool) $settings->get( 'sms_enabled' ) ); ?>>
							<?php esc_html_e( 'پیامک‌ها فرستاده شوند', 'parsian-preorder' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-api-key"><?php esc_html_e( 'کلید API کاوه‌نگار', 'parsian-preorder' ); ?></label></th>
					<td>
						<input type="text" id="ppo-api-key" name="sms_api_key" class="large-text ltr" dir="ltr" value="<?php echo esc_attr( $settings->get( 'sms_api_key' ) ); ?>">
						<p class="description"><?php esc_html_e( 'خالی بگذارید تا کلید افزونهٔ «ورود با پیامک» استفاده شود.', 'parsian-preorder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-customer-template"><?php esc_html_e( 'قالب پیامک مشتری', 'parsian-preorder' ); ?></label></th>
					<td>
						<input type="text" id="ppo-customer-template" name="customer_template" class="regular-text ltr" dir="ltr" value="<?php echo esc_attr( $settings->get( 'customer_template' ) ); ?>">
						<p class="description"><?php esc_html_e( 'دو پارامتر: token = روزهای کاری، token2 = نام محصول.', 'parsian-preorder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-admin-template"><?php esc_html_e( 'قالب پیامک پشتیبانی', 'parsian-preorder' ); ?></label></th>
					<td>
						<input type="text" id="ppo-admin-template" name="admin_template" class="regular-text ltr" dir="ltr" value="<?php echo esc_attr( $settings->get( 'admin_template' ) ); ?>">
						<p class="description"><?php esc_html_e( 'دو پارامتر: token = شمارهٔ درخواست، token2 = محصول و تعداد. خالی یعنی فرستاده نشود.', 'parsian-preorder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-admin-phone"><?php esc_html_e( 'تلفن پشتیبانی', 'parsian-preorder' ); ?></label></th>
					<td>
						<input type="text" id="ppo-admin-phone" name="admin_phone" class="regular-text ltr" dir="ltr" value="<?php echo esc_attr( ppo_display_phone( $settings->get( 'admin_phone' ) ) ); ?>" placeholder="09*********">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'پیامک تغییر وضعیت', 'parsian-preorder' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:8px;">
							<?php esc_html_e( 'برای هر وضعیت می‌توانید یک قالب پیامک بگذارید تا با رسیدن درخواست به آن مرحله، خودکار به مشتری خبر داده شود. خالی یعنی پیامکی فرستاده نشود. پارامترها: token = نام وضعیت، token2 = نام محصول.', 'parsian-preorder' ); ?>
						</p>
						<?php foreach ( PPO_Status::all() as $key => $status ) : ?>
							<p>
								<label style="display:inline-block;min-width:170px;"><?php echo esc_html( $status['label'] ); ?></label>
								<input type="text" class="regular-text ltr" dir="ltr"
									name="status_templates[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( isset( $templates[ $key ] ) ? $templates[ $key ] : '' ); ?>">
							</p>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>
		</div>

		<div class="ppo-card">
			<h2><?php esc_html_e( 'ایمیل', 'parsian-preorder' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'ایمیل درخواست تازه', 'parsian-preorder' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="email_enabled" value="1" <?php checked( (bool) $settings->get( 'email_enabled' ) ); ?>>
							<?php esc_html_e( 'با هر درخواست تازه، ایمیل فرستاده شود', 'parsian-preorder' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ppo-email-to"><?php esc_html_e( 'گیرندگان', 'parsian-preorder' ); ?></label></th>
					<td>
						<input type="text" id="ppo-email-to" name="email_to" class="large-text ltr" dir="ltr" value="<?php echo esc_attr( $settings->get( 'email_to' ) ); ?>">
						<p class="description"><?php esc_html_e( 'چند نشانی را با کاما جدا کنید. خالی یعنی ایمیل مدیر سایت.', 'parsian-preorder' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'ذخیرهٔ تنظیمات', 'parsian-preorder' ) ); ?>
	</form>
</div>
