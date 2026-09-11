<?php
/**
 * نمایش پیش‌فروش روی محصولات (نشان، زمان ارسال، فرم درخواست) و ثبت درخواست (AJAX)
 *
 * @package parsian_preorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- صفحه محصول: نشان و زمان ارسال ---------- */

add_action( 'woocommerce_single_product_summary', 'parsian_preorder_single_notice', 6 );
function parsian_preorder_single_notice() {
	global $product;
	if ( ! parsian_preorder_is_product( $product ) ) {
		return;
	}
	$days = parsian_preorder_lead_days( $product );
	echo '<div class="preorder-notice">';
	echo '<span class="preorder-badge">پیش‌فروش</span>';
	if ( $days > 0 ) {
		echo '<span class="preorder-lead">ارسال تا ' . esc_html( parsian_preorder_fa_digits( (string) $days ) ) . ' روز کاری</span>';
	}
	echo '</div>';
}

/* ---------- صفحه محصول: جایگزینی «افزودن به سبد» با فرم پیش‌فروش ---------- */

add_action( 'template_redirect', 'parsian_preorder_maybe_swap_add_to_cart' );
function parsian_preorder_maybe_swap_add_to_cart() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product || ! parsian_preorder_is_product( $product ) ) {
		return;
	}
	remove_action( 'woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30 );
	remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
	add_action( 'woocommerce_simple_add_to_cart', 'parsian_preorder_render_form', 30 );
	add_action( 'woocommerce_variable_add_to_cart', 'parsian_preorder_render_form', 30 );
}

function parsian_preorder_render_form() {
	global $product;
	$days  = parsian_preorder_lead_days( $product );
	$phone = '';
	$name  = '';
	if ( is_user_logged_in() ) {
		$user  = wp_get_current_user();
		$phone = get_user_meta( $user->ID, 'billing_phone', true );
		$name  = trim( $user->first_name . ' ' . $user->last_name );
	}
	?>
	<div class="preorder-form-wrap">
		<form class="preorder-form" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
			<div class="preorder-form-row">
				<label for="parsian-preorder-qty">تعداد (بسته)</label>
				<input type="number" id="parsian-preorder-qty" name="quantity" value="1" min="1" step="1" inputmode="numeric" required />
			</div>
			<div class="preorder-form-row">
				<label for="parsian-preorder-phone">شماره موبایل</label>
				<input type="tel" id="parsian-preorder-phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" dir="ltr" inputmode="numeric" placeholder="09*********" required />
			</div>
			<div class="preorder-form-row">
				<label for="parsian-preorder-name">نام (اختیاری)</label>
				<input type="text" id="parsian-preorder-name" name="name" value="<?php echo esc_attr( $name ); ?>" />
			</div>
			<div class="preorder-form-row">
				<label for="parsian-preorder-note">توضیحات (اختیاری)</label>
				<textarea id="parsian-preorder-note" name="note" rows="2"></textarea>
			</div>
			<?php if ( $days > 0 ) : ?>
				<p class="preorder-form-hint">پس از ثبت درخواست، همکاران ما برای تکمیل سفارش با شما تماس می‌گیرند. ارسال تا <?php echo esc_html( parsian_preorder_fa_digits( (string) $days ) ); ?> روز کاری.</p>
			<?php else : ?>
				<p class="preorder-form-hint">پس از ثبت درخواست، همکاران ما برای تکمیل سفارش با شما تماس می‌گیرند.</p>
			<?php endif; ?>
			<button type="submit" class="preorder-submit">ثبت پیش‌فروش</button>
			<div class="preorder-form-msg" role="status"></div>
		</form>
	</div>
	<?php
}

/* ---------- کارت‌های محصول (بایگانی، صفحه اصلی، محصولات مرتبط) ---------- */

function parsian_preorder_render_card_badge( $product ) {
	if ( ! parsian_preorder_is_product( $product ) ) {
		return '';
	}
	return '<span class="product-card-sale preorder-card-badge">پیش‌فروش</span>';
}

function parsian_preorder_render_card_button( $product ) {
	if ( ! parsian_preorder_is_product( $product ) ) {
		return '';
	}
	$days = parsian_preorder_lead_days( $product );
	$text = $days > 0 ? 'پیش‌فروش (' . parsian_preorder_fa_digits( (string) $days ) . ' روز)' : 'پیش‌فروش';
	return '<a class="btn btn-small preorder-card-btn" href="' . esc_url( get_permalink( $product->get_id() ) ) . '">' . esc_html( $text ) . '</a>';
}

/* ---------- ثبت درخواست (AJAX) ---------- */

add_action( 'wp_ajax_nopriv_parsian_preorder_submit', 'parsian_preorder_ajax_submit' );
add_action( 'wp_ajax_parsian_preorder_submit', 'parsian_preorder_ajax_submit' );

function parsian_preorder_ajax_submit() {
	check_ajax_referer( 'parsian-preorder', 'nonce' );

	$product_id = absint( $_POST['product_id'] ?? 0 );
	$product    = wc_get_product( $product_id );
	if ( ! $product || ! parsian_preorder_is_product( $product ) ) {
		wp_send_json_error( array( 'message' => 'این محصول برای پیش‌فروش فعال نیست.' ) );
	}

	$quantity = max( 1, (int) ( $_POST['quantity'] ?? 1 ) );
	$phone    = parsian_preorder_normalize_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
	if ( ! parsian_preorder_is_valid_phone( $phone ) ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل معتبر وارد کنید (مثال: 09123456789).' ) );
	}
	$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

	/* جلوگیری از ثبت درخواست تکراری از یک شماره برای یک محصول */
	$recent = get_posts( array(
		'post_type'      => 'preorder',
		'post_status'    => 'private',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'   => '_preorder_phone',
				'value' => $phone,
			),
			array(
				'key'   => '_preorder_product_id',
				'value' => $product_id,
			),
		),
		'date_query'     => array(
			array(
				'after' => '5 minutes ago',
			),
		),
	) );
	if ( $recent ) {
		wp_send_json_error( array( 'message' => 'درخواست شما برای این محصول قبلاً ثبت شده است؛ همکاران ما به‌زودی با شما تماس می‌گیرند.' ) );
	}

	$post_id = wp_insert_post( array(
		'post_type'   => 'preorder',
		'post_status' => 'private',
		'post_title'  => 'پیش‌فروش — ' . $product->get_name(),
	) );
	if ( ! $post_id || is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'ثبت درخواست ناموفق بود؛ دوباره تلاش کنید.' ) );
	}

	update_post_meta( $post_id, '_preorder_product_id', $product_id );
	update_post_meta( $post_id, '_preorder_qty', $quantity );
	update_post_meta( $post_id, '_preorder_phone', $phone );
	update_post_meta( $post_id, '_preorder_name', $name );
	update_post_meta( $post_id, '_preorder_note', $note );
	update_post_meta( $post_id, '_preorder_status', 'new' );
	update_post_meta( $post_id, '_preorder_lead_days', parsian_preorder_lead_days( $product ) );
	update_post_meta( $post_id, '_preorder_user_id', get_current_user_id() );

	/* پیامک تأیید به مشتری */
	$days = parsian_preorder_lead_days( $product );
	$sms  = parsian_preorder_send_sms( $phone, (string) $days, $product->get_name(), parsian_preorder_get_option( 'customer_template' ) );
	update_post_meta( $post_id, '_preorder_sms_status', is_wp_error( $sms ) ? 'error' : ( true === $sms ? 'sent' : 'test' ) );

	/* اطلاع‌رسانی به پشتیبانی */
	$admin_phone = parsian_preorder_admin_phone();
	if ( $admin_phone && '' !== parsian_preorder_get_option( 'admin_template' ) ) {
		parsian_preorder_send_sms( $admin_phone, (string) $post_id, $product->get_name() . ' — ' . parsian_preorder_fa_digits( (string) $quantity ) . ' بسته', parsian_preorder_get_option( 'admin_template' ) );
	}

	wp_send_json_success( array(
		'message' => 'درخواست پیش‌فروش شما ثبت شد. همکاران ما برای تکمیل سفارش با شما تماس می‌گیرند.',
	) );
}

/* ---------- اسکریپت و استایل ---------- */

add_action( 'wp_enqueue_scripts', 'parsian_preorder_assets', 25 );
function parsian_preorder_assets() {
	wp_enqueue_style( 'parsian-preorder', PARSIAN_PREORDER_URL . 'assets/parsian-preorder.css', array(), PARSIAN_PREORDER_VER );
	wp_enqueue_script( 'parsian-preorder', PARSIAN_PREORDER_URL . 'assets/parsian-preorder.js', array( 'jquery' ), PARSIAN_PREORDER_VER, true );
	wp_localize_script(
		'parsian-preorder',
		'parsianPreorder',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'parsian-preorder' ),
		)
	);
}