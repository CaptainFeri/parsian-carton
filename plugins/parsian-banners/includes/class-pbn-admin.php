<?php
/**
 * پیشخوان بنرها: صفحهٔ تنظیمات و بارگذاری اسکریپت انتخاب تصویر.
 *
 * @package parsian-banners
 */

defined( 'ABSPATH' ) || exit;

/**
 * رابط پیشخوان.
 */
class PBN_Admin {

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_pbn_settings', array( __CLASS__, 'handle_settings' ) );
	}

	/**
	 * زیرمنوی تنظیمات.
	 */
	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . PBN_Post_Type::POST_TYPE,
			__( 'تنظیمات بنرها', 'parsian-banners' ),
			__( 'تنظیمات', 'parsian-banners' ),
			'manage_options',
			'pbn-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * استایل و اسکریپت پیشخوان.
	 *
	 * @param string $hook شناسهٔ صفحه.
	 */
	public static function enqueue( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$ours   = ( $screen && PBN_Post_Type::POST_TYPE === $screen->post_type ) || false !== strpos( $hook, 'pbn-settings' );

		if ( ! $ours ) {
			return;
		}

		wp_enqueue_style( 'pbn-admin', PBN_URL . 'assets/pbn-admin.css', array(), PBN_VERSION );

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			// کتابخانهٔ رسانهٔ وردپرس برای انتخاب تصویر ویژهٔ موبایل.
			wp_enqueue_media();
			wp_enqueue_script( 'pbn-admin', PBN_URL . 'assets/pbn-admin.js', array( 'jquery' ), PBN_VERSION, true );
			wp_localize_script(
				'pbn-admin',
				'pbnAdmin',
				array(
					'title'  => __( 'انتخاب تصویر بنر برای موبایل', 'parsian-banners' ),
					'button' => __( 'انتخاب این تصویر', 'parsian-banners' ),
				)
			);
		}
	}

	/**
	 * ذخیرهٔ تنظیمات.
	 */
	public static function handle_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'اجازهٔ انجام این کار را ندارید.', 'parsian-banners' ) );
		}

		check_admin_referer( 'pbn_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- بالا بررسی شد.
		PBN_Settings::save( wp_unslash( $_POST ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => PBN_Post_Type::POST_TYPE,
					'page'      => 'pbn-settings',
					'saved'     => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * صفحهٔ تنظیمات.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$banners = PBN_Banner::all();
		$active  = PBN_Banner::active( 'home' );
		?>
		<div class="wrap pbn-wrap">
			<h1><?php esc_html_e( 'تنظیمات بنرها', 'parsian-banners' ); ?></h1>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( ! empty( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تنظیمات ذخیره شد.', 'parsian-banners' ); ?></p></div>
			<?php endif; ?>

			<div class="pbn-card">
				<h2><?php esc_html_e( 'وضعیت', 'parsian-banners' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: 1: تعداد کل، 2: تعداد در حال نمایش. */
						esc_html__( '%1$s بنر ساخته‌اید و %2$s تای آن‌ها هم‌اکنون روی صفحهٔ اصلی نمایش داده می‌شود.', 'parsian-banners' ),
						'<strong>' . esc_html( pbn_digits( count( $banners ) ) ) . '</strong>',
						'<strong>' . esc_html( pbn_digits( count( $active ) ) ) . '</strong>'
					);
					?>
				</p>

				<?php if ( ! $banners ) : ?>
					<p class="description">
						<?php esc_html_e( 'تا وقتی بنری نساخته‌اید، صفحهٔ اصلی همان اسلایدر ثابت قالب را نشان می‌دهد. با ساختن نخستین بنر، جای آن را می‌گیرد.', 'parsian-banners' ); ?>
					</p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PBN_Post_Type::POST_TYPE ) ); ?>">
							<?php esc_html_e( 'ساخت نخستین بنر', 'parsian-banners' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pbn_settings' ); ?>
				<input type="hidden" name="action" value="pbn_settings">

				<div class="pbn-card">
					<h2><?php esc_html_e( 'نمایش', 'parsian-banners' ); ?></h2>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="pbn-interval"><?php esc_html_e( 'چرخش خودکار (ثانیه)', 'parsian-banners' ); ?></label></th>
							<td>
								<input type="number" id="pbn-interval" name="interval" min="0" max="60" step="1" class="small-text"
									value="<?php echo esc_attr( PBN_Settings::get( 'interval' ) ); ?>">
								<p class="description"><?php esc_html_e( 'صفر یعنی بنرها خودکار عوض نشوند و فقط با دکمه‌ها جابه‌جا شوند.', 'parsian-banners' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pbn-height"><?php esc_html_e( 'کمینهٔ ارتفاع روی دسکتاپ', 'parsian-banners' ); ?></label></th>
							<td>
								<input type="number" id="pbn-height" name="height" min="180" max="900" step="10" class="small-text"
									value="<?php echo esc_attr( PBN_Settings::get( 'height' ) ); ?>"> <?php esc_html_e( 'پیکسل', 'parsian-banners' ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pbn-height-sm"><?php esc_html_e( 'کمینهٔ ارتفاع روی موبایل', 'parsian-banners' ); ?></label></th>
							<td>
								<input type="number" id="pbn-height-sm" name="height_sm" min="140" max="700" step="10" class="small-text"
									value="<?php echo esc_attr( PBN_Settings::get( 'height_sm' ) ); ?>"> <?php esc_html_e( 'پیکسل', 'parsian-banners' ); ?>
								<p class="description"><?php esc_html_e( 'کف ارتفاع است، نه سقفش: اگر متن بنر بلند باشد، بنر از این بلندتر می‌شود. متن کوتاه‌تر یعنی بنر کوتاه‌تر و محصولات زودتر دیده می‌شوند.', 'parsian-banners' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'وقتی بنری نیست', 'parsian-banners' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="fallback" value="1" <?php checked( (bool) PBN_Settings::get( 'fallback' ) ); ?>>
									<?php esc_html_e( 'اسلایدر ثابت قالب نمایش داده شود', 'parsian-banners' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'اگر این را خاموش کنید و بنری هم نداشته باشید، بالای صفحهٔ اصلی خالی می‌ماند.', 'parsian-banners' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'ذخیرهٔ تنظیمات', 'parsian-banners' ) ); ?>
			</form>

			<div class="pbn-card">
				<h2><?php esc_html_e( 'گذاشتن بنر در جای دیگر', 'parsian-banners' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: 1: شورت‌کد ساده، 2: شورت‌کد با جایگاه. */
						esc_html__( 'برای نمایش بنرها داخل یک برگه یا نوشته، %1$s را بگذارید. برای یک جایگاه مشخص: %2$s', 'parsian-banners' ),
						'<code>[parsian_banners]</code>',
						'<code>[parsian_banners location="shop"]</code>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
