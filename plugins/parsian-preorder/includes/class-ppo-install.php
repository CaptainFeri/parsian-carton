<?php
/**
 * نصب، ارتقا و انتقال داده‌های افزونهٔ قدیمی.
 *
 * نسخهٔ ۱ وضعیت را در متای `_preorder_status` نگه می‌داشت و همهٔ درخواست‌ها
 * وضعیت نوشتهٔ `private` داشتند. اینجا آن‌ها به وضعیت‌های واقعی خط لوله منتقل
 * می‌شوند و گزینه‌های پراکندهٔ قدیمی در یک آرایه جمع می‌شوند. انتقال ایدم‌پوتنت
 * است: اجرای دوباره‌اش چیزی را خراب نمی‌کند.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * نصب و ارتقا.
 */
class PPO_Install {

	/**
	 * گزینه‌ای که نسخهٔ نصب‌شده را نگه می‌دارد.
	 */
	const VERSION_OPTION = 'ppo_version';

	/**
	 * پرچم پایان انتقال داده‌های نسخهٔ قدیمی.
	 */
	const MIGRATED_OPTION = 'ppo_legacy_migrated';

	/**
	 * پرچم «قوانین بازنویسی نشانی باید تازه شوند».
	 */
	const FLUSH_OPTION = 'ppo_flush_rules';

	/**
	 * تعداد درخواستی که در هر بار انتقال پردازش می‌شود.
	 */
	const BATCH = 500;

	/**
	 * نگاشت وضعیت‌های نسخهٔ قدیمی به خط لولهٔ تازه.
	 *
	 * @var array<string,string>
	 */
	protected static $legacy_statuses = array(
		'new'       => 'ppo-new',
		'contacted' => 'ppo-contacted',
		'converted' => 'ppo-converted',
		'cancelled' => 'ppo-cancelled',
	);

	/**
	 * هنگام فعال‌سازی افزونه.
	 */
	public static function activate() {
		self::add_capabilities();

		// نوع نوشته و وضعیت‌ها هنوز ثبت نشده‌اند؛ برای انتقال لازم‌اند.
		PPO_Post_Type::register();

		self::migrate();

		update_option( self::VERSION_OPTION, PPO_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * هنگام غیرفعال‌سازی.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * ارتقای خودکار وقتی فایل‌های افزونه بدون فعال‌سازی دوباره به‌روز شده‌اند.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) !== PPO_VERSION ) {
			self::add_capabilities();
			self::migrate_options();

			// وقتی فایل‌های افزونه روی نسخهٔ فعالِ قبلی اکسترکت می‌شوند،
			// وردپرس قلاب فعال‌سازی را دوباره اجرا نمی‌کند؛ پس نقطهٔ پایانی
			// «پیش‌فروش‌های من» در حساب کاربری قانون بازنویسی نمی‌گیرد و ۴۰۴
			// می‌دهد. اینجا فقط پرچم می‌گذاریم — خودِ تازه‌سازی باید بعد از
			// ثبت نقطهٔ پایانی (روی init) انجام شود.
			update_option( self::FLUSH_OPTION, 1 );
			update_option( self::VERSION_OPTION, PPO_VERSION );
		}

		add_action( 'wp_loaded', array( __CLASS__, 'maybe_flush_rules' ) );

		// انتقال وضعیت‌ها دسته‌ای است؛ تا وقتی تمام نشده، هر بار یک دسته جلو می‌رود.
		if ( ! get_option( self::MIGRATED_OPTION ) ) {
			self::migrate_statuses();
		}
	}

	/**
	 * تازه‌سازی قوانین بازنویسی، یک بار پس از هر ارتقا.
	 */
	public static function maybe_flush_rules() {
		if ( ! get_option( self::FLUSH_OPTION ) ) {
			return;
		}

		delete_option( self::FLUSH_OPTION );
		flush_rewrite_rules( false );
	}

	/**
	 * دادن دسترسی کارتابل به مدیر و مدیر فروشگاه.
	 */
	public static function add_capabilities() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role ) {
				$role->add_cap( 'manage_preorders' );
			}
		}
	}

	/* ------------------------------- انتقال ------------------------------- */

	/**
	 * انتقال داده‌های نسخهٔ قدیمی.
	 */
	public static function migrate() {
		self::migrate_options();

		// تا وقتی همهٔ درخواست‌ها منتقل نشده‌اند، دسته‌به‌دسته ادامه می‌دهیم.
		$guard = 0;

		while ( ! self::migrate_statuses() && ++$guard < 50 ) {
			continue;
		}
	}

	/**
	 * جمع کردن گزینه‌های پراکندهٔ قدیمی در آرایهٔ تنظیمات.
	 */
	protected static function migrate_options() {
		$map = array(
			'parsian_preorder_customer_template' => 'customer_template',
			'parsian_preorder_admin_template'    => 'admin_template',
			'parsian_preorder_admin_phone'       => 'admin_phone',
			'parsian_preorder_api_key'           => 'sms_api_key',
		);

		$values = array();

		foreach ( $map as $legacy => $key ) {
			$value = get_option( $legacy, null );

			if ( null !== $value && '' !== $value ) {
				$values[ $key ] = $value;
			}
		}

		if ( ! $values ) {
			return;
		}

		$settings = PPO_Settings::instance();

		// گزینه‌هایی که کاربر در نسخهٔ تازه دست‌کاری کرده، بازنویسی نمی‌شوند.
		$stored = get_option( PPO_Settings::OPTION, array() );

		foreach ( $values as $key => $value ) {
			if ( isset( $stored[ $key ] ) && '' !== $stored[ $key ] ) {
				unset( $values[ $key ] );
			}
		}

		if ( $values ) {
			$settings->save( array_merge( is_array( $stored ) ? $stored : array(), $values ) );
		}
	}

	/**
	 * تبدیل وضعیت‌های متایی به وضعیت نوشته.
	 *
	 * @return bool آیا انتقال تمام شد؟
	 */
	protected static function migrate_statuses() {
		$posts = get_posts(
			array(
				'post_type'      => PPO_Post_Type::POST_TYPE,
				'post_status'    => array( 'private', 'publish', 'draft', 'pending' ),
				'posts_per_page' => self::BATCH,
				'no_found_rows'  => true,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$legacy = (string) get_post_meta( $post->ID, '_preorder_status', true );
			$status = isset( self::$legacy_statuses[ $legacy ] ) ? self::$legacy_statuses[ $legacy ] : PPO_Status::NEW_REQUEST;

			wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => $status,
				)
			);

			// قیمت واحد برای درخواست‌های قدیمی ثبت نشده بود؛ از خود محصول برداشته
			// می‌شود تا آمار ارزش، این درخواست‌ها را هم بشمارد.
			if ( '' === (string) get_post_meta( $post->ID, '_preorder_unit_price', true ) && function_exists( 'wc_get_product' ) ) {
				$product_id = (int) get_post_meta( $post->ID, '_preorder_product_id', true );
				$product    = $product_id ? wc_get_product( $product_id ) : null;

				update_post_meta( $post->ID, '_preorder_unit_price', $product ? (float) $product->get_price( 'edit' ) : 0 );
			}
		}

		$finished = count( $posts ) < self::BATCH;

		if ( $finished ) {
			update_option( self::MIGRATED_OPTION, 1 );
		}

		return $finished;
	}
}
