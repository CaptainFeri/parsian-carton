<?php
/**
 * اطلاع‌رسانی: پیامک (کاوه‌نگار) و ایمیل.
 *
 * هر ارسال — موفق یا ناموفق — در تاریخچهٔ درخواست ثبت می‌شود؛ وقتی مشتری
 * می‌گوید «پیامکی نیامد»، پاسخ در همان صفحه هست.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * پیامک و ایمیل.
 */
class PPO_Notifier {

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_action( 'ppo_request_created', array( __CLASS__, 'on_created' ) );
		add_action( 'ppo_request_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 3 );
	}

	/* ------------------------------ رویدادها ------------------------------ */

	/**
	 * پس از ثبت درخواست تازه: پیامک به مشتری، پیامک و ایمیل به پشتیبانی.
	 *
	 * @param PPO_Request $request درخواست.
	 */
	public static function on_created( $request ) {
		$settings = PPO_Settings::instance();

		$result = self::send_sms(
			$request->get( 'phone' ),
			(string) $request->get( 'lead_days', 0 ),
			$request->get_product_name(),
			$settings->get( 'customer_template' )
		);

		$request->set( 'sms_status', is_wp_error( $result ) ? 'error' : $result );
		self::log_sms( $request, __( 'پیامک تأیید برای مشتری', 'parsian-preorder' ), $result );

		$admin_phone = $settings->admin_phone();
		$template    = (string) $settings->get( 'admin_template' );

		if ( $admin_phone && '' !== $template ) {
			$summary = sprintf(
				/* translators: 1: نام محصول، 2: تعداد. */
				__( '%1$s — %2$s بسته', 'parsian-preorder' ),
				$request->get_product_name(),
				ppo_digits( $request->get( 'quantity', 1 ) )
			);

			$result = self::send_sms( $admin_phone, (string) $request->get_id(), $summary, $template );
			self::log_sms( $request, __( 'پیامک اطلاع‌رسانی به پشتیبانی', 'parsian-preorder' ), $result );
		}

		self::send_admin_email( $request );
	}

	/**
	 * پس از تغییر وضعیت: اگر برای وضعیت تازه قالب پیامکی تعریف شده باشد، فرستاده می‌شود.
	 *
	 * @param PPO_Request $request  درخواست.
	 * @param string      $status   وضعیت تازه.
	 * @param string      $previous وضعیت قبلی.
	 */
	public static function on_status_changed( $request, $status, $previous ) {
		$templates = (array) PPO_Settings::instance()->get( 'status_templates' );

		if ( empty( $templates[ $status ] ) ) {
			return;
		}

		$result = self::send_sms(
			$request->get( 'phone' ),
			PPO_Status::label( $status ),
			$request->get_product_name(),
			$templates[ $status ]
		);

		self::log_sms(
			$request,
			sprintf(
				/* translators: %s: نام وضعیت. */
				__( 'پیامک وضعیت «%s»', 'parsian-preorder' ),
				PPO_Status::label( $status )
			),
			$result
		);
	}

	/* ------------------------------- پیامک ------------------------------- */

	/**
	 * ارسال پیامک با سرویس verify/lookup کاوه‌نگار.
	 *
	 * @param string $phone    گیرنده.
	 * @param string $token    پارامتر اول قالب.
	 * @param string $token2   پارامتر دوم قالب.
	 * @param string $template نام قالب تأییدشده.
	 * @return string|WP_Error sent | skipped | disabled | WP_Error
	 */
	public static function send_sms( $phone, $token, $token2 = '', $template = '' ) {
		$settings = PPO_Settings::instance();

		if ( ! $settings->get( 'sms_enabled' ) ) {
			return 'disabled';
		}

		$phone = ppo_normalize_phone( $phone );

		if ( ! ppo_is_valid_phone( $phone ) || '' === $template ) {
			return 'skipped';
		}

		$api_key = $settings->sms_api_key();

		if ( '' === $api_key ) {
			// بدون کلید، درخواست ثبت می‌شود ولی پیامکی نمی‌رود.
			return 'skipped';
		}

		$body = array(
			'receptor' => $phone,
			'token'    => self::token( $token ),
			'template' => $template,
		);

		if ( '' !== $token2 ) {
			$body['token2'] = self::token( $token2 );
		}

		$response = wp_remote_post(
			sprintf( 'https://api.kavenegar.com/v1/%s/verify/lookup.json', rawurlencode( $api_key ) ),
			array(
				'timeout' => 15,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $json['return']['status'] ) && 200 === (int) $json['return']['status'] ) {
			return 'sent';
		}

		$message = isset( $json['return']['message'] )
			? $json['return']['message']
			: __( 'پاسخ نامشخص از سرویس پیامک.', 'parsian-preorder' );

		return new WP_Error( 'ppo_sms', $message );
	}

	/**
	 * پاک‌سازی پارامتر قالب پیامک.
	 *
	 * کاوه‌نگار در پارامترهای قالب، فاصله و چند نویسه را نمی‌پذیرد.
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	protected static function token( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = str_replace( array( "\r", "\n", "\t" ), ' ', $value );
		$value = preg_replace( '/[\/\\\\?&%#;]+/', '-', $value );
		$value = preg_replace( '/\s+/u', '‌', trim( $value ) );

		return mb_substr( $value, 0, 60 );
	}

	/**
	 * ثبت نتیجهٔ ارسال در تاریخچه.
	 *
	 * @param PPO_Request     $request درخواست.
	 * @param string          $label   عنوان.
	 * @param string|WP_Error $result  نتیجه.
	 */
	protected static function log_sms( $request, $label, $result ) {
		if ( is_wp_error( $result ) ) {
			/* translators: 1: عنوان پیامک، 2: متن خطا. */
			$text = sprintf( __( '%1$s فرستاده نشد: %2$s', 'parsian-preorder' ), $label, $result->get_error_message() );
		} elseif ( 'sent' === $result ) {
			/* translators: %s: عنوان پیامک. */
			$text = sprintf( __( '%s فرستاده شد.', 'parsian-preorder' ), $label );
		} elseif ( 'disabled' === $result ) {
			/* translators: %s: عنوان پیامک. */
			$text = sprintf( __( '%s فرستاده نشد (پیامک در تنظیمات خاموش است).', 'parsian-preorder' ), $label );
		} else {
			/* translators: %s: عنوان پیامک. */
			$text = sprintf( __( '%s فرستاده نشد (کلید API یا قالب تنظیم نشده است).', 'parsian-preorder' ), $label );
		}

		PPO_Log::add( $request->get_id(), $text, 'sms', 0 );
	}

	/* ------------------------------- ایمیل ------------------------------- */

	/**
	 * ایمیل اطلاع‌رسانی درخواست تازه به پشتیبانی.
	 *
	 * @param PPO_Request $request درخواست.
	 */
	public static function send_admin_email( $request ) {
		$settings = PPO_Settings::instance();

		if ( ! $settings->get( 'email_enabled' ) ) {
			return;
		}

		$recipients = $settings->email_recipients();

		if ( ! $recipients ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: نام سایت، 2: نام محصول. */
			__( '[%1$s] درخواست پیش‌فروش تازه — %2$s', 'parsian-preorder' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$request->get_product_name()
		);

		$lines = array(
			sprintf( '%s: %s', __( 'محصول', 'parsian-preorder' ), $request->get_product_name() ),
			sprintf( '%s: %s', __( 'تعداد', 'parsian-preorder' ), ppo_digits( $request->get( 'quantity', 1 ) ) ),
			sprintf( '%s: %s', __( 'تلفن', 'parsian-preorder' ), ppo_display_phone( $request->get( 'phone' ) ) ),
			sprintf( '%s: %s', __( 'نام', 'parsian-preorder' ), $request->get( 'name', '—' ) ),
			sprintf( '%s: %s', __( 'شرکت', 'parsian-preorder' ), $request->get( 'company', '—' ) ),
			sprintf( '%s: %s', __( 'توضیحات', 'parsian-preorder' ), $request->get( 'note', '—' ) ),
			sprintf( '%s: %s', __( 'ارزش تقریبی', 'parsian-preorder' ), ppo_price( $request->get_value() ) ),
			'',
			$request->get_admin_url(),
		);

		wp_mail( $recipients, $subject, implode( "\n", $lines ) );
	}
}
