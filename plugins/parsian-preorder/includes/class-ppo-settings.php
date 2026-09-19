<?php
/**
 * تنظیمات افزونهٔ پیش‌فروش.
 *
 * همهٔ گزینه‌ها در یک آرایه ذخیره می‌شوند تا خواندنشان یک پرس‌وجو بیشتر نباشد.
 * گزینه‌های افزونهٔ قدیمی (parsian_preorder_*) هنگام ارتقا به همین آرایه منتقل
 * می‌شوند.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * گزینه‌ها.
 */
class PPO_Settings {

	const OPTION = 'ppo_settings';

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PPO_Settings|null
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
	 * @return PPO_Settings
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
			/* --------------------------- عمومی --------------------------- */
			// پنجرهٔ جلوگیری از ثبت تکراری (دقیقه).
			'duplicate_window'   => 5,
			// روزهای بی‌پیگیری تا وقتی درخواست «معطل» شمرده شود.
			'stale_days'         => 2,
			// چند روز بعد از ثبت، پیگیری بعدی پیش‌فرض تنظیم شود.
			'followup_days'      => 1,
			// فیلدهای فرم درخواست.
			'ask_company'        => 1,
			'ask_city'           => 0,
			'ask_note'           => 1,
			// وضعیتی که درخواست تازه با آن ثبت می‌شود.
			'initial_status'     => PPO_Status::NEW_REQUEST,
			// کاربری که درخواست‌های تازه به او واگذار می‌شود (۰ = هیچ‌کس).
			'default_assignee'   => 0,

			/* --------------------------- نمایش --------------------------- */
			'badge_text'         => 'پیش‌فروش',
			'badge_color'        => '#d97706',
			'button_text'        => 'ثبت درخواست پیش‌فروش',
			'form_intro'         => 'این محصول پیش‌فروش است. درخواستتان را ثبت کنید تا همکاران ما برای تکمیل سفارش با شما تماس بگیرند.',
			'success_text'       => 'درخواست پیش‌فروش شما ثبت شد. همکاران ما به‌زودی با شما تماس می‌گیرند.',
			'show_countdown'     => 1,
			'show_capacity'      => 1,
			'myaccount_tab'      => 1,

			/* --------------------------- پیامک --------------------------- */
			'sms_enabled'        => 1,
			'sms_api_key'        => '',
			'customer_template'  => 'parsianpreorder',
			'admin_template'     => 'parsianpreorderadmin',
			'admin_phone'        => '',
			// قالب پیامک برای هر وضعیت (خالی = پیامکی فرستاده نشود).
			'status_templates'   => array(),

			/* --------------------------- ایمیل --------------------------- */
			'email_enabled'      => 1,
			'email_to'           => '',
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

		if ( ! array_key_exists( $key, $this->cache ) ) {
			return $default;
		}

		return $this->cache[ $key ];
	}

	/**
	 * دور ریختن کش.
	 */
	public function flush() {
		$this->cache = null;
	}

	/**
	 * ذخیرهٔ گزینه‌ها — فقط کلیدهای شناخته‌شده و پس از پاک‌سازی.
	 *
	 * @param array $values مقادیر خام.
	 */
	public function save( $values ) {
		$clean = array();

		foreach ( self::defaults() as $key => $default ) {
			$clean[ $key ] = $this->get( $key, $default );
		}

		$integers = array( 'duplicate_window', 'stale_days', 'followup_days', 'default_assignee' );

		foreach ( $integers as $key ) {
			if ( isset( $values[ $key ] ) ) {
				$clean[ $key ] = max( 0, (int) ppo_latin_digits( $values[ $key ] ) );
			}
		}

		// تیک خاموش اصلاً در POST نمی‌آید، پس در ارسال فرم «نبودِ کلید» یعنی خاموش.
		// ولی در ذخیرهٔ برنامه‌ای (مثل انتقال داده‌های نسخهٔ قدیمی) نبودِ کلید باید
		// یعنی «دست نزن»، وگرنه یک ذخیرهٔ جزئی همهٔ تیک‌ها را خاموش می‌کند. فیلد
		// پنهان ppo_form این دو حالت را از هم جدا می‌کند.
		$from_form = ! empty( $values['ppo_form'] );
		$flags     = array( 'ask_company', 'ask_city', 'ask_note', 'show_countdown', 'show_capacity', 'myaccount_tab', 'sms_enabled', 'email_enabled' );

		foreach ( $flags as $key ) {
			if ( $from_form || array_key_exists( $key, $values ) ) {
				$clean[ $key ] = empty( $values[ $key ] ) ? 0 : 1;
			}
		}

		$texts = array( 'badge_text', 'button_text', 'customer_template', 'admin_template', 'sms_api_key' );

		foreach ( $texts as $key ) {
			if ( isset( $values[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( $values[ $key ] );
			}
		}

		$paragraphs = array( 'form_intro', 'success_text' );

		foreach ( $paragraphs as $key ) {
			if ( isset( $values[ $key ] ) ) {
				$clean[ $key ] = sanitize_textarea_field( $values[ $key ] );
			}
		}

		if ( isset( $values['badge_color'] ) ) {
			$color                = sanitize_hex_color( $values['badge_color'] );
			$clean['badge_color'] = $color ? $color : self::defaults()['badge_color'];
		}

		if ( isset( $values['admin_phone'] ) ) {
			$clean['admin_phone'] = ppo_normalize_phone( $values['admin_phone'] );
		}

		if ( isset( $values['email_to'] ) ) {
			$emails = array();

			foreach ( preg_split( '/[,،\s]+/u', (string) $values['email_to'] ) as $email ) {
				$email = sanitize_email( trim( $email ) );

				if ( $email && is_email( $email ) ) {
					$emails[] = $email;
				}
			}

			$clean['email_to'] = implode( ', ', $emails );
		}

		if ( isset( $values['initial_status'] ) && PPO_Status::exists( $values['initial_status'] ) ) {
			$clean['initial_status'] = $values['initial_status'];
		}

		if ( isset( $values['status_templates'] ) && is_array( $values['status_templates'] ) ) {
			$templates = array();

			foreach ( $values['status_templates'] as $status => $template ) {
				if ( PPO_Status::exists( $status ) ) {
					$templates[ $status ] = sanitize_text_field( $template );
				}
			}

			$clean['status_templates'] = array_filter( $templates );
		}

		update_option( self::OPTION, $clean, true );
		$this->cache = null;
	}

	/* ------------------------------ کمک‌کننده‌ها ------------------------------ */

	/**
	 * کلید API کاوه‌نگار — اگر اینجا خالی باشد از افزونهٔ «ورود با پیامک» خوانده می‌شود.
	 *
	 * @return string
	 */
	public function sms_api_key() {
		$key = (string) $this->get( 'sms_api_key' );

		if ( '' !== $key ) {
			return $key;
		}

		return (string) get_option( 'parsian_otp_api_key', '' );
	}

	/**
	 * شمارهٔ پشتیبانی برای اطلاع‌رسانی درخواست تازه.
	 *
	 * @return string
	 */
	public function admin_phone() {
		$phone = (string) $this->get( 'admin_phone' );

		if ( '' === $phone ) {
			$phone = (string) get_theme_mod( 'cartonpak_mobile', '' );
		}

		if ( '' === $phone ) {
			$phone = (string) get_theme_mod( 'cartonpak_support', '' );
		}

		return ppo_normalize_phone( $phone );
	}

	/**
	 * گیرندگان ایمیل اطلاع‌رسانی.
	 *
	 * @return string[]
	 */
	public function email_recipients() {
		$raw = (string) $this->get( 'email_to' );

		if ( '' === $raw ) {
			return array( get_option( 'admin_email' ) );
		}

		return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
	}
}
