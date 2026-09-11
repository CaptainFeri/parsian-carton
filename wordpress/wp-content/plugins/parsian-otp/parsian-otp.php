<?php
/**
 * Plugin Name: پارسیان کارتن — ورود و ثبت‌نام با پیامک (کاوه‌نگار)
 * Plugin URI:  https://parsian-carton.local
 * Description: ورود و ثبت‌نام بدون رمز عبور با کد تأیید یک‌بارمصرف (OTP) ارسال‌شده از طریق سرویس پیامک کاوه‌نگار (Kavenegar).
 * Version:     1.0.0
 * Author:      Parsian Carton
 * Text Domain: parsian-otp
 *
 * @package parsian_otp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARSIAN_OTP_VER', '1.0.0' );

/* ----------------------------------------------------------------
   Options
---------------------------------------------------------------- */

function parsian_otp_defaults() {
	return array(
		'api_key'       => '',
		'template'      => 'parsiancarton',
		'code_length'   => 5,
		'send_interval' => 60,
		'code_lifetime' => 300,
		'max_attempts'  => 3,
	);
}

function parsian_otp_get_option( $key ) {
	$defaults = parsian_otp_defaults();
	if ( array_key_exists( $key, $defaults ) ) {
		$stored = get_option( 'parsian_otp_' . $key, null );
		return null !== $stored ? $stored : $defaults[ $key ];
	}
	return get_option( 'parsian_otp_' . $key, '' );
}

/* ----------------------------------------------------------------
 * تنظیمات در پیشخوان
---------------------------------------------------------------- */

add_action( 'admin_menu', 'parsian_otp_admin_menu' );
function parsian_otp_admin_menu() {
	add_options_page(
		'ورود با پیامک (کاوه‌نگار)',
		'ورود با پیامک',
		'manage_options',
		'parsian-otp',
		'parsian_otp_settings_page'
	);
}

function parsian_otp_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'parsian_otp_settings' ) ) {
		update_option( 'parsian_otp_api_key', sanitize_text_field( wp_unslash( $_POST['parsian_otp_api_key'] ?? '' ) ) );
		update_option( 'parsian_otp_template', sanitize_text_field( wp_unslash( $_POST['parsian_otp_template'] ?? '' ) ) );
		update_option( 'parsian_otp_code_length', max( 4, min( 6, (int) ( $_POST['parsian_otp_code_length'] ?? 5 ) ) ) );
		echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>';
	}
	$api_key     = parsian_otp_get_option( 'api_key' );
	$template    = parsian_otp_get_template();
	$code_length = (int) parsian_otp_get_option( 'code_length' );
	?>
	<div class="wrap">
		<h1>ورود و ثبت‌نام با پیامک — کاوه‌نگار</h1>
		<p>فرم ورود و ثبت‌نام بدون رمز عبور است؛ کاربر تنها شماره موبایل و کد پیامک‌شده را وارد می‌کند. اگر حساب وجود داشته باشد وارد می‌شود، در غیر این صورت حساب جدید ساخته می‌شود.</p>
		<form method="post">
			<?php wp_nonce_field( 'parsian_otp_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="parsian_otp_api_key">کلید API کاوه‌نگار</label></th>
					<td>
						<input type="text" class="regular-text" id="parsian_otp_api_key" name="parsian_otp_api_key" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off" />
						<p class="description">از پنل کاوه‌نگار (<code>panel.kavenegar.com</code>) بخش «کلید وب‌سرویس» دریافت کنید. ارسال از طریق سرویس <code>verify/lookup</code> انجام می‌شود.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="parsian_otp_template">نام قالب پیامک</label></th>
					<td>
						<input type="text" class="regular-text" id="parsian_otp_template" name="parsian_otp_template" value="<?php echo esc_attr( $template ); ?>" />
						<p class="description">نام قالب تأییدشده در پنل کاوه‌نگار؛ قالب باید یک پارامتر (کد) داشته باشد. مثال: <code>parsiancarton</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="parsian_otp_code_length">تعداد رقم کد</label></th>
					<td><input type="number" min="4" max="6" id="parsian_otp_code_length" name="parsian_otp_code_length" value="<?php echo esc_attr( $code_length ); ?>" /></td>
				</tr>
			</table>
			<?php if ( '' === $api_key ) : ?>
				<div class="notice notice-warning inline">
					<p><strong>حالت آزمایشی:</strong> کلید API ثبت نشده است؛ پیامک واقعی ارسال نمی‌شود و به‌جای آن، کد تأیید داخل فرم نمایش داده می‌شود تا جریان ورود قابل تست باشد.</p>
				</div>
			<?php endif; ?>
			<?php submit_button( 'ذخیره تنظیمات' ); ?>
		</form>
	</div>
	<?php
}

/* ----------------------------------------------------------------
 * شماره موبایل
----------------------------------------------------------------*/

function parsian_otp_normalize_phone( $phone ) {
	$fa    = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$en    = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$phone = str_replace( $fa, $en, (string) $phone );
	$phone = preg_replace( '/[^0-9]/', '', $phone );
	if ( 0 === strpos( $phone, '0' ) ) {
		$phone = '+98' . substr( $phone, 1 );
	} elseif ( 0 === strpos( $phone, '98' ) && 11 === strlen( $phone ) ) {
		$phone = '+' . $phone;
	} elseif ( 0 === strpos( $phone, '9' ) && 10 === strlen( $phone ) ) {
		$phone = '+98' . $phone;
	}
	return $phone;
}

function parsian_otp_is_valid_phone( $phone ) {
	return (bool) preg_match( '/^\+98\d{10}$/', $phone );
}

function parsian_otp_phone_key( $phone ) {
	return 'parsian_otp_' . substr( wp_hash( $phone ), 0, 24 );
}

/* ----------------------------------------------------------------
 * ارسال کد (AJAX)
---------------------------------------------------------------- */

add_action( 'wp_ajax_nopriv_parsian_otp_send', 'parsian_otp_ajax_send' );
add_action( 'wp_ajax_parsian_otp_send', 'parsian_otp_ajax_send' );

function parsian_otp_ajax_send() {
	check_ajax_referer( 'parsian-otp', 'nonce' );

	$phone = parsian_otp_normalize_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );

	if ( ! parsian_otp_is_valid_phone( $phone ) ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل معتبر وارد کنید (مثال: 09123456789).' ) );
	}

	$transient_key = parsian_otp_phone_key( $phone );
	$stored        = get_transient( $transient_key );
	$interval      = max( 30, (int) parsian_otp_get_option( 'send_interval' ) );

	if ( $stored && isset( $stored['sent_at'] ) && ( time() - (int) $stored['sent_at'] ) < $interval ) {
		$wait = $interval - ( time() - (int) $stored['sent_at'] );
		wp_send_json_error( array( 'message' => 'کد قبلاً ارسال شده است. لطفاً ' . $wait . ' ثانیه دیگر تلاش کنید.' ) );
	}

	$length = max( 4, min( 6, (int) parsian_otp_get_option( 'code_length' ) ) );
	$code   = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$code .= wp_rand( 0, 9 );
	}

	$result = parsian_otp_send_via_kavenegar( $phone, $code );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => 'ارسال پیامک با خطا مواجه شد: ' . $result->get_error_message() ) );
	}

	set_transient(
		$transient_key,
		array(
			'code'     => wp_hash( $code ),
			'attempts' => (int) parsian_otp_get_option( 'max_attempts' ),
			'sent_at'  => time(),
			'phone'    => $phone,
		),
		(int) parsian_otp_get_option( 'code_lifetime' )
	);

	wp_send_json_success(
		array(
			'message'  => 'کد تأیید برای شماره شما ارسال شد.',
			'dev_code' => true === $result ? '' : $code,
		)
	);
}

/**
 * ارسال کد از طریق کاوه‌نگار.
 *
 * @return true|WP_Error|string (true = ارسال واقعی، string = کد در حالت تست)
 */
function parsian_otp_send_via_kavenegar( $phone, $code ) {
	$api_key = parsian_otp_get_option( 'api_key' );

	if ( '' === $api_key ) {
		return $code; // حالت آزمایشی — بجای پیامک، کد را برمی‌گردانیم.
	}

	$endpoint = sprintf(
		'https://api.kavenegar.com/v1/%s/%s/lookup.json',
		rawurlencode( $api_key ),
		'verify'
	);

	$response = wp_remote_post(
		$endpoint,
		array(
			'timeout' => 15,
			'body'    => array(
				'receptor' => $phone,
				'token'    => $code,
				'template' => parsian_otp_get_template(),
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'kavenegar', $response->get_error_message() );
	}

	$json = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( isset( $json['return']['status'] ) && 200 === (int) $json['return']['status'] ) {
		return true;
	}

	$msg = isset( $json['return']['message'] ) ? $json['return']['message'] : 'خطای ناشناخته سرویس کاوه‌نگار';
	return new WP_Error( 'kavenegar', $msg );
}

/* ----------------------------------------------------------------
 * تأیید کد و ورود / ساخت حساب (AJAX)
 * ---------------------------------------------------------------- */

add_action( 'wp_ajax_nopriv_parsian_otp_verify', 'parsian_otp_ajax_verify' );
add_action( 'wp_ajax_parsian_otp_verify', 'parsian_otp_ajax_verify' );

function parsian_otp_ajax_verify() {
	check_ajax_referer( 'parsian-otp', 'nonce' );

	$phone = parsian_otp_normalize_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
	$code  = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );

	$key    = parsian_otp_phone_key( $phone );
	$stored = get_transient( $key );

	if ( ! $stored ) {
		wp_send_json_error( array( 'message' => 'کد تأیید منقضی شده است؛ دوباره کد دریافت کنید.' ) );
	}

	if ( ! hash_equals( $stored['code'], wp_hash( $code ) ) ) {
		if ( isset( $stored['attempts'] ) && $stored['attempts'] > 0 ) {
			$stored['attempts']--;
			set_transient( $key, $stored, (int) parsian_otp_get_option( 'code_lifetime' ) );
		}
		if ( $stored['attempts'] <= 0 ) {
			delete_transient( $key );
			wp_send_json_error( array( 'message' => 'تعداد تلاش‌های مجاز به پایان رسید؛ دوباره کد دریافت کنید.' ) );
		}
		wp_send_json_error( array( 'message' => 'کد واردشده صحیح نیست. یک‌بار دیگر امتحان کنید.' ) );
	}

	$user = parsian_otp_find_or_create_user( $stored['phone'] );

	if ( is_wp_error( $user ) ) {
		wp_send_json_error( array( 'message' => $user->get_error_message() ) );
	}

	delete_transient( $key );

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );
	do_action( 'wp_login', $user->user_login, $user );

	$redirect = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
	$redirect = apply_filters( 'parsian_otp_redirect', $redirect, $user );

	wp_send_json_success(
		array(
			'message'  => 'ورود با موفقیت انجام شد.',
			'redirect' => $redirect,
		)
	);
}

function parsian_otp_find_or_create_user( $phone ) {
	$user = get_user_by( 'login', $phone );
	if ( $user ) {
		return $user;
	}

	$by_meta = get_users(
		array(
			'meta_key'   => 'billing_phone',
			'meta_value' => $phone,
			'number'     => 1,
			'fields'     => 'ID',
		)
	);
	if ( $by_meta ) {
		return get_user_by( 'id', $by_meta[0] );
	}

	$username = ltrim( $phone, '+98' ); // 9xxxxxxxxx
	$password = wp_generate_password( 16, false );

	$user_id = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_pass'    => $password,
			'user_email'   => $username . '@parsian-carton.local',
			'display_name' => 'کاربر ' . $username,
			'role'         => 'customer',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	update_user_meta( $user_id, 'billing_phone', $phone );
	update_user_meta( $user_id, 'parsian_otp_phone', $phone );

	return get_user_by( 'id', $user_id );
}

/* ----------------------------------------------------------------
 * فرم ورود در صفحه «حساب کاربری» و «پرداخت»
 * ---------------------------------------------------------------- */

add_shortcode( 'parsian_otp', 'parsian_otp_shortcode' );
function parsian_otp_shortcode() {
	if ( is_user_logged_in() ) {
		return '<p>شما وارد شده‌اید.</p>';
	}
	return parsian_otp_render_form();
}

function parsian_otp_render_form() {
	$length = (int) parsian_otp_get_option( 'code_length' );
	ob_start();
	?>
	<div class="parsian-otp-wrap">
		<div class="parsian-otp-box">
			<h2 class="parsian-otp-title">ورود / ثبت‌نام با پیامک</h2>
			<p class="parsian-otp-intro">شماره موبایل خود را وارد کنید تا کد تأیید برایتان پیامک شود. اگر حساب کاربری نداشته باشید، به‌صورت خودکار ساخته می‌شود.</p>

			<div class="parsian-otp-msg" role="alert"></div>

			<form id="parsianOtpForm" autocomplete="off" novalidate>
				<input type="hidden" name="action" value="parsian_otp_send" />

				<div class="parsian-otp-field">
					<label for="parsian-otp-phone">شماره موبایل</label>
					<input type="tel" id="parsian-otp-phone" name="phone" inputmode="numeric" dir="ltr" placeholder="09*********" required />
				</div>

				<button type="button" class="parsian-otp-btn" data-step="send">ارسال کد تأیید</button>

				<div class="parsian-otp-code-step" style="display:none;">
					<div class="parsian-otp-field">
						<label for="parsian-otp-code">کد تأیید</label>
						<input type="text" id="parsian-otp-code" name="code" inputmode="numeric" dir="ltr" maxlength="<?php echo esc_attr( $length ); ?>" />
					</div>
					<small class="parsian-otp-hint">کد تا حدود <?php echo esc_html( (int) ( parsian_otp_get_option( 'code_lifetime' ) / 60 ) ); ?> دقیقه معتبر است.</small>
					<button type="submit" class="parsian-otp-btn parsian-otp-btn-verify" data-step="verify">ورود / ساخت حساب</button>
					<a href="#" id="parsianOtpResend" class="parsian-otp-resend" style="display:none;">ارسال دوباره کد</a>
				</div>
			</form>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* ----------------------------------------------------------------
 * اسکریپت و استایل
 * ---------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'parsian_otp_assets', 20 );
function parsian_otp_assets() {
	if ( is_user_logged_in() ) {
		return;
	}
	$relevant = ( function_exists( 'is_account_page' ) && is_account_page() ) || ( function_exists( 'is_checkout' ) && is_checkout() );
	if ( ! $relevant ) {
		return;
	}
	wp_enqueue_script( 'parsian-otp', plugins_url( 'assets/parsian-otp.js', __FILE__ ), array( 'jquery' ), PARSIAN_OTP_VER, true );
	wp_localize_script(
		'parsian-otp',
		'parsianOtp',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'parsian-otp' ),
		)
	);
	wp_enqueue_style( 'parsian-otp', plugin_dir_url( __FILE__ ) . 'assets/parsian-otp.css', array(), PARSIAN_OTP_VER );
}