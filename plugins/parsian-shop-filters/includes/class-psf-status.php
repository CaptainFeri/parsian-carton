<?php
/**
 * عیب‌یابی: چرا پنل فیلتر دیده نمی‌شود؟
 *
 * شایع‌ترین گزارش دربارهٔ این افزونه «پنل اصلاً نمی‌آید» است و علتش تقریباً
 * همیشه یکی از چند چیز مشخص است: صفحهٔ فروشگاه تنظیم نشده، صفحه‌ای که کاربر
 * «فروشگاه» صدایش می‌زند واقعاً بایگانی محصول نیست، قالب قلاب‌های ووکامرس را
 * برداشته، یا صفت‌ها محلی‌اند نه سراسری.
 *
 * این کلاس همهٔ این‌ها را یکجا بررسی می‌کند و پاسخ را در پیشخوان می‌گذارد، تا
 * به‌جای حدس زدن، یک نگاه کافی باشد.
 *
 * @package parsian-shop-filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * بررسی سلامت افزونه.
 */
class PSF_Status {

	/**
	 * اجرای همهٔ بررسی‌ها.
	 *
	 * @return array[] هر بررسی: label، state (ok|warn|fail)، text، fix (اختیاری).
	 */
	public static function checks() {
		$checks = array();

		$checks[] = self::check_woocommerce();
		$checks[] = self::check_coming_soon();
		$checks[] = self::check_shop_page();
		$checks[] = self::check_products();
		$checks[] = self::check_hook();
		$checks[] = self::check_attributes();
		$checks[] = self::check_price_bounds();

		return array_values( array_filter( $checks ) );
	}

	/**
	 * آیا مشکلی هست؟
	 *
	 * @return bool
	 */
	public static function has_problems() {
		foreach ( self::checks() as $check ) {
			if ( 'fail' === $check['state'] ) {
				return true;
			}
		}

		return false;
	}

	/* ------------------------------ بررسی‌ها ------------------------------ */

	/**
	 * ووکامرس فعال است؟
	 *
	 * @return array
	 */
	protected static function check_woocommerce() {
		$active = class_exists( 'WooCommerce' );

		return array(
			'label' => __( 'ووکامرس', 'parsian-shop-filters' ),
			'state' => $active ? 'ok' : 'fail',
			'text'  => $active
				? __( 'فعال است.', 'parsian-shop-filters' )
				: __( 'فعال نیست. بدون ووکامرس هیچ پنلی ساخته نمی‌شود.', 'parsian-shop-filters' ),
		);
	}

	/**
	 * حالت «به‌زودی» ووکامرس خاموش است؟
	 *
	 * این یکی موذی است: مدیر سایت که وارد شده فروشگاه را سالم می‌بیند، ولی
	 * بازدیدکنندهٔ معمولی — یعنی مشتری، و خود شما روی گوشیِ بدون ورود — صفحهٔ
	 * «به‌زودی» را می‌بیند. حلقهٔ محصولات اصلاً اجرا نمی‌شود، پس نه محصولی هست
	 * نه پنل فیلتری.
	 *
	 * @return array|null
	 */
	protected static function check_coming_soon() {
		$mode = get_option( 'woocommerce_coming_soon', 'no' );

		if ( 'yes' !== $mode ) {
			return null;
		}

		$store_only = 'yes' === get_option( 'woocommerce_store_pages_only', 'no' );

		return array(
			'label' => __( 'حالت به‌زودی', 'parsian-shop-filters' ),
			'state' => 'fail',
			'text'  => $store_only
				? __( 'حالت «به‌زودی» ووکامرس روشن است و صفحه‌های فروشگاه را برای بازدیدکننده با یک صفحهٔ جایگزین می‌پوشاند. شما چون وارد شده‌اید فروشگاه را می‌بینید، مشتری نه — و پنل فیلتر هم برای او وجود ندارد.', 'parsian-shop-filters' )
				: __( 'حالت «به‌زودی» ووکامرس روی کل سایت روشن است؛ بازدیدکننده به‌جای سایت، صفحهٔ جایگزین را می‌بیند.', 'parsian-shop-filters' ),
			'fix'   => admin_url( 'admin.php?page=wc-settings&tab=site-visibility' ),
		);
	}

	/**
	 * صفحهٔ فروشگاه تنظیم و منتشر شده است؟
	 *
	 * این شایع‌ترین علت «پنل نمی‌آید» است: نشانی‌ای که کاربر فروشگاه صدایش
	 * می‌زند، در واقع یک برگهٔ معمولی است و `is_shop()` رویش false برمی‌گردد.
	 *
	 * @return array
	 */
	protected static function check_shop_page() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return null;
		}

		$page_id = wc_get_page_id( 'shop' );
		$page    = $page_id > 0 ? get_post( $page_id ) : null;

		if ( ! $page ) {
			return array(
				'label' => __( 'صفحهٔ فروشگاه', 'parsian-shop-filters' ),
				'state' => 'fail',
				'text'  => __( 'در تنظیمات ووکامرس صفحه‌ای به‌عنوان «فروشگاه» انتخاب نشده است. تا وقتی این صفحه تعیین نشود، وردپرس هیچ نشانی‌ای را بایگانی محصول نمی‌داند و پنل فیلتر جایی برای نشستن ندارد.', 'parsian-shop-filters' ),
				'fix'   => admin_url( 'admin.php?page=wc-settings&tab=products' ),
			);
		}

		if ( 'publish' !== $page->post_status ) {
			return array(
				'label' => __( 'صفحهٔ فروشگاه', 'parsian-shop-filters' ),
				'state' => 'fail',
				/* translators: %s: وضعیت صفحه. */
				'text'  => sprintf( __( 'صفحهٔ فروشگاه منتشر نشده است (وضعیت فعلی: %s).', 'parsian-shop-filters' ), $page->post_status ),
				'fix'   => get_edit_post_link( $page_id, '' ),
			);
		}

		return array(
			'label' => __( 'صفحهٔ فروشگاه', 'parsian-shop-filters' ),
			'state' => 'ok',
			/* translators: 1: عنوان صفحه، 2: نشانی. */
			'text'  => sprintf(
				__( '«%1$s» — %2$s', 'parsian-shop-filters' ),
				$page->post_title,
				get_permalink( $page_id )
			),
		);
	}

	/**
	 * محصول منتشرشده‌ای هست؟
	 *
	 * @return array
	 */
	protected static function check_products() {
		$counts = wp_count_posts( 'product' );
		$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;

		return array(
			'label' => __( 'محصولات منتشرشده', 'parsian-shop-filters' ),
			'state' => $total > 0 ? 'ok' : 'fail',
			'text'  => $total > 0
				/* translators: %s: تعداد محصول. */
				? sprintf( __( '%s محصول.', 'parsian-shop-filters' ), psf_digits( $total ) )
				: __( 'هیچ محصول منتشرشده‌ای نیست؛ بایگانی خالی است و پنل هم نمایش داده نمی‌شود.', 'parsian-shop-filters' ),
		);
	}

	/**
	 * قالب قلاب `woocommerce_before_shop_loop` را برنداشته است؟
	 *
	 * بعضی قالب‌ها نوار ابزار خودشان را می‌سازند و با `remove_all_actions`
	 * همهٔ قلاب را خالی می‌کنند؛ آن‌وقت پنل هم با آن می‌رود.
	 *
	 * @return array
	 */
	protected static function check_hook() {
		$attached = has_action( 'woocommerce_before_shop_loop', array( PSF_Render::instance(), 'render' ) );

		if ( false !== $attached ) {
			return array(
				'label' => __( 'قلاب نمایش', 'parsian-shop-filters' ),
				'state' => 'ok',
				'text'  => __( 'پنل به قلاب استاندارد ووکامرس وصل است.', 'parsian-shop-filters' ),
			);
		}

		return array(
			'label' => __( 'قلاب نمایش', 'parsian-shop-filters' ),
			'state' => 'fail',
			'text'  => __( 'قلاب «woocommerce_before_shop_loop» برداشته شده است — معمولاً کار قالب یا افزونه‌ای دیگر. در این حالت از شورت‌کد [parsian_filters] داخل قالب یا برگه استفاده کنید.', 'parsian-shop-filters' ),
		);
	}

	/**
	 * صفت‌های سراسری برای فیلتر ویژگی‌ها هست؟
	 *
	 * @return array
	 */
	protected static function check_attributes() {
		$taxonomies = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();

		if ( ! $taxonomies ) {
			return array(
				'label' => __( 'ویژگی‌های سراسری', 'parsian-shop-filters' ),
				'state' => 'warn',
				'text'  => __( 'هیچ ویژگی سراسری‌ای تعریف نشده است، پس بخش «ویژگی‌ها» در پنل خالی می‌ماند. اگر ویژگی‌های محصولاتتان محلی‌اند، با ابزار «محصولات ← تبدیل صفت به سراسری» تبدیلشان کنید.', 'parsian-shop-filters' ),
				'fix'   => admin_url( 'edit.php?post_type=product&page=pcs-attributes' ),
			);
		}

		$usable = PSF_Render::filterable_attribute_taxonomies();

		return array(
			'label' => __( 'ویژگی‌های سراسری', 'parsian-shop-filters' ),
			'state' => $usable ? 'ok' : 'warn',
			'text'  => $usable
				/* translators: %s: فهرست ویژگی‌ها. */
				? sprintf( __( 'قابل فیلتر: %s', 'parsian-shop-filters' ), implode( '، ', array_map( 'wc_attribute_label', $usable ) ) )
				: __( 'ویژگی سراسری هست ولی هیچ‌کدام در تنظیمات پایین برای فیلتر انتخاب نشده‌اند.', 'parsian-shop-filters' ),
		);
	}

	/**
	 * بازهٔ قیمت معنادار است؟
	 *
	 * @return array
	 */
	protected static function check_price_bounds() {
		$bounds = PSF_Render::instance()->price_bounds();

		if ( empty( $bounds['max'] ) || $bounds['max'] <= $bounds['min'] ) {
			return array(
				'label' => __( 'بازهٔ قیمت', 'parsian-shop-filters' ),
				'state' => 'warn',
				'text'  => __( 'همهٔ محصولات یک قیمت دارند یا قیمتی ثبت نشده است؛ اسلایدر قیمت نمایش داده نمی‌شود.', 'parsian-shop-filters' ),
			);
		}

		return array(
			'label' => __( 'بازهٔ قیمت', 'parsian-shop-filters' ),
			'state' => 'ok',
			/* translators: 1: کمترین قیمت، 2: بیشترین قیمت. */
			'text'  => sprintf(
				__( 'از %1$s تا %2$s', 'parsian-shop-filters' ),
				psf_format_price( $bounds['min'] ),
				psf_format_price( $bounds['max'] )
			),
		);
	}

	/* ------------------------------- نمایش ------------------------------- */

	/**
	 * جدول وضعیت در صفحهٔ تنظیمات.
	 */
	public static function render() {
		$icons = array(
			'ok'   => '✅',
			'warn' => '⚠️',
			'fail' => '⛔',
		);
		?>
		<h2><?php esc_html_e( 'وضعیت — چرا پنل دیده می‌شود یا نمی‌شود؟', 'parsian-shop-filters' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'اگر پنل فیلتر در فروشگاه ظاهر نمی‌شود، جواب تقریباً همیشه در همین جدول است.', 'parsian-shop-filters' ); ?>
		</p>

		<table class="widefat striped" style="max-width:860px;margin-bottom:24px;">
			<tbody>
				<?php foreach ( self::checks() as $check ) : ?>
					<tr>
						<td style="width:30px;font-size:16px;">
							<?php echo esc_html( isset( $icons[ $check['state'] ] ) ? $icons[ $check['state'] ] : '' ); ?>
						</td>
						<th style="width:170px;"><?php echo esc_html( $check['label'] ); ?></th>
						<td>
							<?php echo esc_html( $check['text'] ); ?>
							<?php if ( ! empty( $check['fix'] ) ) : ?>
								— <a href="<?php echo esc_url( $check['fix'] ); ?>"><?php esc_html_e( 'رفتن به تنظیمات', 'parsian-shop-filters' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description">
			<?php
			printf(
				/* translators: %s: شورت‌کد. */
				esc_html__( 'برای گذاشتن پنل در جای دلخواه (مثلاً یک برگهٔ سفارشی)، از شورت‌کد %s استفاده کنید.', 'parsian-shop-filters' ),
				'<code>[parsian_filters]</code>'
			);
			?>
		</p>
		<?php
	}
}
