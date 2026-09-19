<?php
/**
 * خط لولهٔ وضعیت درخواست‌های پیش‌فروش.
 *
 * وضعیت هر درخواست، **وضعیت خود نوشته** است (نه یک متا). این انتخاب عمدی است:
 * شمارش هر وضعیت را دیتابیس با یک پرس‌وجو می‌دهد، فیلتر کردن ارزان است و
 * درخواست‌ها هیچ‌وقت بدون وضعیت نمی‌مانند.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * وضعیت‌های پیش‌فروش.
 */
class PPO_Status {

	/**
	 * وضعیت درخواست تازه.
	 */
	const NEW_REQUEST = 'ppo-new';

	/**
	 * وضعیت درخواستی که به سفارش تبدیل شده.
	 */
	const CONVERTED = 'ppo-converted';

	/**
	 * وضعیت درخواست لغوشده.
	 */
	const CANCELLED = 'ppo-cancelled';

	/**
	 * تعریف همهٔ وضعیت‌ها به ترتیب خط لوله.
	 *
	 * open = هنوز روی میز کارشناس است و در شمارش «باز» می‌آید.
	 *
	 * @return array<string,array{label:string,short:string,color:string,open:bool,description:string}>
	 */
	public static function all() {
		$statuses = array(
			self::NEW_REQUEST => array(
				'label'       => __( 'جدید', 'parsian-preorder' ),
				'short'       => __( 'جدید', 'parsian-preorder' ),
				'color'       => '#2563eb',
				'open'        => true,
				'description' => __( 'تازه از سایت ثبت شده و هنوز کسی سراغش نرفته است.', 'parsian-preorder' ),
			),
			'ppo-contacted'   => array(
				'label'       => __( 'در تماس', 'parsian-preorder' ),
				'short'       => __( 'در تماس', 'parsian-preorder' ),
				'color'       => '#7c3aed',
				'open'        => true,
				'description' => __( 'با مشتری تماس گرفته شده و در حال بررسی جزئیات است.', 'parsian-preorder' ),
			),
			'ppo-quoted'      => array(
				'label'       => __( 'پیش‌فاکتور صادر شد', 'parsian-preorder' ),
				'short'       => __( 'پیش‌فاکتور', 'parsian-preorder' ),
				'color'       => '#0891b2',
				'open'        => true,
				'description' => __( 'قیمت نهایی اعلام و پیش‌فاکتور برای مشتری فرستاده شده است.', 'parsian-preorder' ),
			),
			'ppo-approved'    => array(
				'label'       => __( 'تأیید مشتری', 'parsian-preorder' ),
				'short'       => __( 'تأییدشده', 'parsian-preorder' ),
				'color'       => '#059669',
				'open'        => true,
				'description' => __( 'مشتری پیش‌فاکتور را تأیید کرده و منتظر تولید است.', 'parsian-preorder' ),
			),
			'ppo-production'  => array(
				'label'       => __( 'در حال تولید', 'parsian-preorder' ),
				'short'       => __( 'تولید', 'parsian-preorder' ),
				'color'       => '#d97706',
				'open'        => true,
				'description' => __( 'سفارش وارد خط تولید شده است.', 'parsian-preorder' ),
			),
			'ppo-ready'       => array(
				'label'       => __( 'آمادهٔ ارسال', 'parsian-preorder' ),
				'short'       => __( 'آماده', 'parsian-preorder' ),
				'color'       => '#16a34a',
				'open'        => true,
				'description' => __( 'کالا آماده است و منتظر ارسال یا تحویل.', 'parsian-preorder' ),
			),
			self::CONVERTED   => array(
				'label'       => __( 'تبدیل به سفارش', 'parsian-preorder' ),
				'short'       => __( 'سفارش شد', 'parsian-preorder' ),
				'color'       => '#15803d',
				'open'        => false,
				'description' => __( 'برای این درخواست سفارش ووکامرس ساخته شده و کار کارتابل تمام است.', 'parsian-preorder' ),
			),
			self::CANCELLED   => array(
				'label'       => __( 'لغو شده', 'parsian-preorder' ),
				'short'       => __( 'لغو', 'parsian-preorder' ),
				'color'       => '#dc2626',
				'open'        => false,
				'description' => __( 'مشتری منصرف شده یا درخواست قابل انجام نبوده است.', 'parsian-preorder' ),
			),
		);

		/**
		 * تغییر وضعیت‌های خط لولهٔ پیش‌فروش.
		 *
		 * @param array $statuses وضعیت‌ها.
		 */
		return (array) apply_filters( 'ppo_statuses', $statuses );
	}

	/**
	 * فهرست کلید وضعیت‌ها.
	 *
	 * @return string[]
	 */
	public static function keys() {
		return array_keys( self::all() );
	}

	/**
	 * آیا این کلید یک وضعیت معتبر است؟
	 *
	 * @param string $status کلید.
	 * @return bool
	 */
	public static function exists( $status ) {
		return array_key_exists( $status, self::all() );
	}

	/**
	 * برچسب یک وضعیت.
	 *
	 * @param string $status کلید.
	 * @return string
	 */
	public static function label( $status ) {
		$all = self::all();

		return isset( $all[ $status ] ) ? $all[ $status ]['label'] : (string) $status;
	}

	/**
	 * رنگ یک وضعیت.
	 *
	 * @param string $status کلید.
	 * @return string
	 */
	public static function color( $status ) {
		$all = self::all();

		return isset( $all[ $status ] ) ? $all[ $status ]['color'] : '#64748b';
	}

	/**
	 * وضعیت‌هایی که هنوز باز شمرده می‌شوند.
	 *
	 * @return string[]
	 */
	public static function open_keys() {
		$keys = array();

		foreach ( self::all() as $key => $status ) {
			if ( ! empty( $status['open'] ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * آیا وضعیت، باز است؟
	 *
	 * @param string $status کلید.
	 * @return bool
	 */
	public static function is_open( $status ) {
		return in_array( $status, self::open_keys(), true );
	}

	/**
	 * ثبت وضعیت‌ها به‌عنوان وضعیت نوشته.
	 */
	public static function register() {
		foreach ( self::all() as $key => $status ) {
			register_post_status(
				$key,
				array(
					'label'                     => $status['label'],
					'public'                    => false,
					'internal'                  => false,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: تعداد درخواست. */
					'label_count'               => _n_noop( $status['label'] . ' (%s)', $status['label'] . ' (%s)', 'parsian-preorder' ),
				)
			);
		}
	}

	/**
	 * نشان رنگی یک وضعیت برای نمایش در پیشخوان.
	 *
	 * @param string $status کلید.
	 * @return string HTML.
	 */
	public static function badge( $status ) {
		return sprintf(
			'<span class="ppo-badge" style="--ppo-badge-color:%1$s">%2$s</span>',
			esc_attr( self::color( $status ) ),
			esc_html( self::label( $status ) )
		);
	}
}
