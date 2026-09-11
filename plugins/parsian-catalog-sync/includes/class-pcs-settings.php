<?php
/**
 * گزینه‌های افزونه و تاریخچهٔ اجراها.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * تنظیمات همگام‌سازی.
 */
class PCS_Settings {

	const OPTION  = 'pcs_settings';
	const HISTORY = 'pcs_history';

	/**
	 * حداکثر تعداد اجرای ذخیره‌شده در تاریخچه.
	 */
	const HISTORY_LIMIT = 20;

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PCS_Settings|null
	 */
	protected static $instance = null;

	/**
	 * کش گزینه‌ها.
	 *
	 * @var array|null
	 */
	protected $cache = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PCS_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * سازنده.
	 */
	protected function __construct() {}

	/**
	 * مقادیر پیش‌فرض.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'price_unit'        => 'toman',
			'missing_action'    => 'draft',
			'create_attributes' => 1,
			'import_attributes' => 0,
			'source'            => '',
			'sheet'             => '',
			'schedule'          => 'off',
		);
	}

	/**
	 * خواندن یک گزینه.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $default مقدار جایگزین.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, array() );
			$this->cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}

		return array_key_exists( $key, $this->cache ) ? $this->cache[ $key ] : $default;
	}

	/**
	 * دور ریختن کش گزینه‌ها — پس از تغییر مستقیم گزینه‌ها در دیتابیس لازم است.
	 */
	public function flush() {
		$this->cache = null;
	}

	/**
	 * ذخیرهٔ گزینه‌ها.
	 *
	 * @param array $values مقادیر.
	 */
	public function save( $values ) {
		$current = array();

		foreach ( self::defaults() as $key => $default ) {
			$current[ $key ] = $this->get( $key, $default );
		}

		$clean = $current;

		if ( isset( $values['price_unit'] ) ) {
			$clean['price_unit'] = in_array( $values['price_unit'], array( 'toman', 'rial' ), true ) ? $values['price_unit'] : 'toman';
		}

		if ( isset( $values['missing_action'] ) ) {
			$allowed                 = array( 'none', 'draft', 'outofstock', 'trash' );
			$clean['missing_action'] = in_array( $values['missing_action'], $allowed, true ) ? $values['missing_action'] : 'draft';
		}

		$clean['create_attributes'] = empty( $values['create_attributes'] ) ? 0 : 1;
		$clean['import_attributes'] = empty( $values['import_attributes'] ) ? 0 : 1;

		if ( isset( $values['source'] ) ) {
			$clean['source'] = self::sanitize_source( $values['source'] );
		}

		if ( isset( $values['sheet'] ) ) {
			$clean['sheet'] = sanitize_text_field( $values['sheet'] );
		}

		if ( isset( $values['schedule'] ) ) {
			$allowed           = array( 'off', 'hourly', 'twicedaily', 'daily' );
			$clean['schedule'] = in_array( $values['schedule'], $allowed, true ) ? $values['schedule'] : 'off';
		}

		update_option( self::OPTION, $clean, false );
		$this->cache = null;

		PCS_Scheduler::reschedule( $clean['schedule'] );
	}

	/**
	 * پاک‌سازی مقدار «منبع» — یا یک نشانی اینترنتی یا مسیر فایل روی سرور.
	 *
	 * @param string $source مقدار خام.
	 * @return string
	 */
	public static function sanitize_source( $source ) {
		$source = trim( (string) $source );

		if ( '' === $source ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $source ) ) {
			return esc_url_raw( $source );
		}

		// مسیرهای محلی فقط داخل پوشهٔ uploads پذیرفته می‌شوند.
		$uploads = wp_get_upload_dir();
		$base    = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		$path    = wp_normalize_path( $source );

		if ( 0 !== strpos( $path, $base ) ) {
			$path = $base . ltrim( $path, '/' );
		}

		// پیمایش به بیرون از uploads مجاز نیست.
		if ( false !== strpos( $path, '../' ) ) {
			return '';
		}

		return $path;
	}

	/* ------------------------------ واحد قیمت ------------------------------ */

	/**
	 * واحدی که قیمت‌ها در دیتابیس با آن ذخیره شده‌اند.
	 *
	 * قالب cartonpak قیمت‌ها را ریالی نگه می‌دارد و هنگام نمایش بر ۱۰ تقسیم می‌کند.
	 *
	 * @return string toman | rial
	 */
	public function store_unit() {
		$unit = function_exists( 'cartonpak_rial_to_toman' ) ? 'rial' : 'toman';

		/**
		 * تغییر واحد قیمت دیتابیس.
		 *
		 * @param string $unit واحد تشخیص‌داده‌شده.
		 */
		return apply_filters( 'pcs_store_price_unit', $unit );
	}

	/**
	 * ضریب تبدیل قیمت فایل به قیمت دیتابیس.
	 *
	 * @return float
	 */
	public function price_multiplier() {
		$file  = $this->get( 'price_unit' );
		$store = $this->store_unit();

		if ( $file === $store ) {
			return 1.0;
		}

		return 'toman' === $file ? 10.0 : 0.1;
	}

	/**
	 * تبدیل یک قیمت از واحد فایل به واحد دیتابیس.
	 *
	 * @param float $amount مقدار خوانده‌شده از فایل.
	 * @return float
	 */
	public function to_store_price( $amount ) {
		return round( (float) $amount * $this->price_multiplier(), 2 );
	}

	/**
	 * تبدیل یک قیمت از واحد دیتابیس به واحد فایل — برای نمایش در پیش‌نمایش.
	 *
	 * @param float $amount مقدار دیتابیس.
	 * @return float
	 */
	public function to_file_price( $amount ) {
		$multiplier = $this->price_multiplier();

		return $multiplier ? round( (float) $amount / $multiplier, 2 ) : (float) $amount;
	}

	/* ------------------------------ تاریخچه ------------------------------ */

	/**
	 * ثبت یک اجرا در تاریخچه.
	 *
	 * @param array $report گزارش اجرا.
	 */
	public function record_run( $report ) {
		$history = get_option( self::HISTORY, array() );
		$history = is_array( $history ) ? $history : array();

		array_unshift(
			$history,
			array(
				'time'    => isset( $report['timestamp'] ) ? $report['timestamp'] : current_time( 'mysql' ),
				'user'    => get_current_user_id(),
				'created' => (int) $report['created'],
				'updated' => (int) $report['updated'],
				'skipped' => (int) $report['skipped'],
				'failed'  => (int) $report['failed'],
				'missing' => (int) $report['missing'],
				// فقط چند پیام نخست نگه داشته می‌شود تا جدول گزینه‌ها بزرگ نشود.
				'notes'   => array_slice( (array) $report['messages'], 0, 25 ),
			)
		);

		update_option( self::HISTORY, array_slice( $history, 0, self::HISTORY_LIMIT ), false );
	}

	/**
	 * خواندن تاریخچه.
	 *
	 * @return array[]
	 */
	public function history() {
		$history = get_option( self::HISTORY, array() );

		return is_array( $history ) ? $history : array();
	}
}
