<?php
/**
 * نمایش پیش‌فروش در سایت: نشان روی کارت محصول، نوار اطلاعات، فرم درخواست،
 * ثبت درخواست (AJAX) و زبانهٔ «پیش‌فروش‌های من» در حساب کاربری.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * بخش کاربری.
 */
class PPO_Frontend {

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_notice' ), 6 );
		add_action( 'template_redirect', array( __CLASS__, 'swap_add_to_cart' ) );

		// نشان روی کارت محصول در بایگانی — برای قالب‌هایی که کارت را override نکرده‌اند.
		add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'render_loop_badge' ), 15 );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'loop_button' ), 10, 2 );
		add_filter( 'woocommerce_post_class', array( __CLASS__, 'product_class' ), 10, 2 );

		// محصول پیش‌فروش نباید از راه نشانی مستقیم وارد سبد شود.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'block_add_to_cart' ), 10, 3 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 25 );

		add_action( 'wp_ajax_ppo_submit', array( __CLASS__, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_ppo_submit', array( __CLASS__, 'ajax_submit' ) );

		if ( PPO_Settings::instance()->get( 'myaccount_tab' ) ) {
			add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ) );
			add_action( 'init', array( __CLASS__, 'account_endpoint' ) );
			add_action( 'woocommerce_account_preorders_endpoint', array( __CLASS__, 'account_content' ) );
		}
	}

	/* ----------------------------- صفحهٔ محصول ----------------------------- */

	/**
	 * نوار اطلاعات پیش‌فروش بالای صفحهٔ محصول.
	 */
	public static function render_notice() {
		global $product;

		if ( ! PPO_Product::is_preorder( $product ) ) {
			return;
		}

		$settings  = PPO_Settings::instance();
		$release   = PPO_Product::release_date( $product );
		$days      = PPO_Product::lead_days( $product );
		$remaining = PPO_Product::remaining( $product );
		$note      = PPO_Product::note( $product );

		echo '<div class="ppo-notice" style="--ppo-color:' . esc_attr( $settings->get( 'badge_color' ) ) . '">';
		echo '<span class="ppo-badge">' . esc_html( $settings->get( 'badge_text' ) ) . '</span>';

		if ( $release ) {
			echo '<span class="ppo-fact">';
			printf(
				/* translators: %s: تاریخ عرضه. */
				esc_html__( 'تاریخ عرضه: %s', 'parsian-preorder' ),
				esc_html( ppo_jalali_date( $release ) )
			);

			if ( $settings->get( 'show_countdown' ) ) {
				$relative = ppo_relative_days( $release );

				if ( '' !== $relative ) {
					echo ' <small>(' . esc_html( $relative ) . ')</small>';
				}
			}

			echo '</span>';
		}

		if ( $days > 0 ) {
			echo '<span class="ppo-fact">';
			printf(
				/* translators: %s: تعداد روز کاری. */
				esc_html__( 'آماده‌سازی و ارسال تا %s روز کاری', 'parsian-preorder' ),
				esc_html( ppo_digits( $days ) )
			);
			echo '</span>';
		}

		if ( $settings->get( 'show_capacity' ) && null !== $remaining ) {
			echo '<span class="ppo-fact">';
			printf(
				/* translators: %s: تعداد بسته. */
				esc_html__( 'ظرفیت باقی‌مانده: %s بسته', 'parsian-preorder' ),
				esc_html( ppo_digits( $remaining ) )
			);
			echo '</span>';
		}

		echo '</div>';

		if ( '' !== $note ) {
			echo '<p class="ppo-product-note">' . esc_html( $note ) . '</p>';
		}
	}

	/**
	 * جایگزینی دکمهٔ «افزودن به سبد» با فرم درخواست.
	 */
	public static function swap_add_to_cart() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		if ( ! $product || ! PPO_Product::is_preorder( $product ) ) {
			return;
		}

		remove_action( 'woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30 );
		remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
		remove_action( 'woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', 30 );

		add_action( 'woocommerce_simple_add_to_cart', array( __CLASS__, 'render_form' ), 30 );
		add_action( 'woocommerce_variable_add_to_cart', array( __CLASS__, 'render_form' ), 30 );
		add_action( 'woocommerce_grouped_add_to_cart', array( __CLASS__, 'render_form' ), 30 );
	}

	/**
	 * فرم ثبت درخواست.
	 */
	public static function render_form() {
		global $product;

		$settings = PPO_Settings::instance();
		$minimum  = PPO_Product::min_quantity( $product );
		$deposit  = PPO_Product::deposit_percent( $product );
		$price    = PPO_Product::effective_price( $product );
		$phone    = '';
		$name     = '';

		if ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$phone = ppo_display_phone( get_user_meta( $user->ID, 'billing_phone', true ) );
			$name  = trim( $user->first_name . ' ' . $user->last_name );
		}

		if ( PPO_Product::is_full( $product ) ) {
			echo '<div class="ppo-form-wrap ppo-full"><p>';
			esc_html_e( 'ظرفیت پیش‌فروش این محصول تکمیل شده است. برای دورهٔ بعد با ما تماس بگیرید.', 'parsian-preorder' );
			echo '</p></div>';
			return;
		}
		?>
		<div class="ppo-form-wrap" style="--ppo-color:<?php echo esc_attr( $settings->get( 'badge_color' ) ); ?>">
			<?php if ( $settings->get( 'form_intro' ) ) : ?>
				<p class="ppo-form-intro"><?php echo esc_html( $settings->get( 'form_intro' ) ); ?></p>
			<?php endif; ?>

			<form class="ppo-form" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-unit-price="<?php echo esc_attr( $price ); ?>">
				<div class="ppo-row">
					<label for="ppo-qty"><?php esc_html_e( 'تعداد (بسته)', 'parsian-preorder' ); ?></label>
					<input type="number" id="ppo-qty" name="quantity" value="<?php echo esc_attr( $minimum ); ?>"
						min="<?php echo esc_attr( $minimum ); ?>" step="1" inputmode="numeric" required>
					<?php if ( $minimum > 1 ) : ?>
						<small class="ppo-hint">
							<?php
							printf(
								/* translators: %s: حداقل تعداد. */
								esc_html__( 'حداقل سفارش: %s بسته', 'parsian-preorder' ),
								esc_html( ppo_digits( $minimum ) )
							);
							?>
						</small>
					<?php endif; ?>
				</div>

				<div class="ppo-row">
					<label for="ppo-phone"><?php esc_html_e( 'شمارهٔ موبایل', 'parsian-preorder' ); ?></label>
					<input type="tel" id="ppo-phone" name="phone" value="<?php echo esc_attr( $phone ); ?>"
						dir="ltr" inputmode="numeric" placeholder="09123456789" required>
				</div>

				<div class="ppo-row">
					<label for="ppo-name"><?php esc_html_e( 'نام و نام خانوادگی', 'parsian-preorder' ); ?></label>
					<input type="text" id="ppo-name" name="name" value="<?php echo esc_attr( $name ); ?>">
				</div>

				<?php if ( $settings->get( 'ask_company' ) ) : ?>
					<div class="ppo-row">
						<label for="ppo-company"><?php esc_html_e( 'نام شرکت (اختیاری)', 'parsian-preorder' ); ?></label>
						<input type="text" id="ppo-company" name="company">
					</div>
				<?php endif; ?>

				<?php if ( $settings->get( 'ask_city' ) ) : ?>
					<div class="ppo-row">
						<label for="ppo-city"><?php esc_html_e( 'شهر', 'parsian-preorder' ); ?></label>
						<input type="text" id="ppo-city" name="city">
					</div>
				<?php endif; ?>

				<?php if ( $settings->get( 'ask_note' ) ) : ?>
					<div class="ppo-row">
						<label for="ppo-note"><?php esc_html_e( 'توضیحات (اختیاری)', 'parsian-preorder' ); ?></label>
						<textarea id="ppo-note" name="note" rows="2"></textarea>
					</div>
				<?php endif; ?>

				<?php if ( $price > 0 ) : ?>
					<p class="ppo-estimate" data-template="<?php echo esc_attr__( 'برآورد مبلغ: %s', 'parsian-preorder' ); ?>">
						<?php
						printf(
							/* translators: %s: مبلغ. */
							esc_html__( 'برآورد مبلغ: %s', 'parsian-preorder' ),
							esc_html( ppo_price( $price * $minimum ) )
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( $deposit > 0 ) : ?>
					<p class="ppo-deposit">
						<?php
						printf(
							/* translators: %s: درصد پیش‌پرداخت. */
							esc_html__( 'برای شروع تولید، %s درصد مبلغ به‌عنوان پیش‌پرداخت دریافت می‌شود.', 'parsian-preorder' ),
							esc_html( ppo_digits( $deposit ) )
						);
						?>
					</p>
				<?php endif; ?>

				<button type="submit" class="ppo-submit button">
					<?php echo esc_html( $settings->get( 'button_text' ) ); ?>
				</button>

				<div class="ppo-message" role="status" aria-live="polite"></div>
			</form>
		</div>
		<?php
	}

	/* ------------------------------ بایگانی ------------------------------ */

	/**
	 * نشان «پیش‌فروش» روی کارت محصول.
	 */
	public static function render_loop_badge() {
		global $product;

		if ( ! PPO_Product::is_preorder( $product ) ) {
			return;
		}

		printf(
			'<span class="ppo-card-badge" style="--ppo-color:%1$s">%2$s</span>',
			esc_attr( PPO_Settings::instance()->get( 'badge_color' ) ),
			esc_html( PPO_Settings::instance()->get( 'badge_text' ) )
		);
	}

	/**
	 * جایگزینی دکمهٔ «افزودن به سبد» کارت با پیوند به صفحهٔ محصول.
	 *
	 * @param string     $html    دکمهٔ پیش‌فرض.
	 * @param WC_Product $product محصول.
	 * @return string
	 */
	public static function loop_button( $html, $product ) {
		if ( ! PPO_Product::is_preorder( $product ) ) {
			return $html;
		}

		return sprintf(
			'<a href="%1$s" class="button ppo-card-button">%2$s</a>',
			esc_url( get_permalink( $product->get_id() ) ),
			esc_html( PPO_Settings::instance()->get( 'badge_text' ) )
		);
	}

	/**
	 * افزودن کلاس به کارت محصول پیش‌فروش تا قالب بتواند استایلش بدهد.
	 *
	 * @param string[]   $classes کلاس‌ها.
	 * @param WC_Product $product محصول.
	 * @return string[]
	 */
	public static function product_class( $classes, $product ) {
		if ( PPO_Product::is_preorder( $product ) ) {
			$classes[] = 'ppo-product';
		}

		return $classes;
	}

	/**
	 * نشان کارت محصول — برای قالبی که کارت را خودش می‌سازد.
	 *
	 * @param WC_Product $product محصول.
	 * @return string HTML.
	 */
	public static function card_badge( $product ) {
		if ( ! PPO_Product::is_preorder( $product ) ) {
			return '';
		}

		return sprintf(
			'<span class="ppo-card-badge" style="--ppo-color:%1$s">%2$s</span>',
			esc_attr( PPO_Settings::instance()->get( 'badge_color' ) ),
			esc_html( PPO_Settings::instance()->get( 'badge_text' ) )
		);
	}

	/**
	 * جلوگیری از افزودن محصول پیش‌فروش به سبد خرید.
	 *
	 * @param bool $passed     نتیجهٔ بررسی‌های قبلی.
	 * @param int  $product_id شناسهٔ محصول.
	 * @param int  $quantity   تعداد.
	 * @return bool
	 */
	public static function block_add_to_cart( $passed, $product_id, $quantity ) {
		unset( $quantity );

		if ( ! PPO_Product::is_preorder( $product_id ) ) {
			return $passed;
		}

		wc_add_notice(
			__( 'این محصول پیش‌فروش است و از راه فرم درخواست پیش‌فروش سفارش داده می‌شود.', 'parsian-preorder' ),
			'error'
		);

		return false;
	}

	/* -------------------------------- AJAX -------------------------------- */

	/**
	 * ثبت درخواست از فرم سایت.
	 */
	public static function ajax_submit() {
		check_ajax_referer( 'ppo-submit', 'nonce' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- بالا بررسی شد.
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product || ! PPO_Product::is_preorder( $product ) ) {
			wp_send_json_error( array( 'message' => __( 'این محصول برای پیش‌فروش فعال نیست.', 'parsian-preorder' ) ) );
		}

		$quantity = isset( $_POST['quantity'] ) ? (int) ppo_latin_digits( wp_unslash( $_POST['quantity'] ) ) : 0;
		$minimum  = PPO_Product::min_quantity( $product );

		if ( $quantity < $minimum ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: حداقل تعداد. */
						__( 'حداقل سفارش این محصول %s بسته است.', 'parsian-preorder' ),
						ppo_digits( $minimum )
					),
				)
			);
		}

		$remaining = PPO_Product::remaining( $product );

		if ( null !== $remaining && $quantity > $remaining ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: ظرفیت باقی‌مانده. */
						__( 'ظرفیت باقی‌ماندهٔ پیش‌فروش %s بسته است.', 'parsian-preorder' ),
						ppo_digits( $remaining )
					),
				)
			);
		}

		$phone = ppo_normalize_phone( isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '' );

		if ( ! ppo_is_valid_phone( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'شمارهٔ موبایل معتبر وارد کنید (مثل ۰۹۱۲۳۴۵۶۷۸۹).', 'parsian-preorder' ) ) );
		}

		if ( self::is_duplicate( $product_id, $phone ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'درخواست شما برای این محصول همین حالا ثبت شده است؛ همکاران ما به‌زودی تماس می‌گیرند.', 'parsian-preorder' ),
				)
			);
		}

		$request = PPO_Request::create(
			array(
				'product_id' => $product_id,
				'quantity'   => $quantity,
				'phone'      => $phone,
				'name'       => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'company'    => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '',
				'city'       => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
				'note'       => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
				'source'     => 'site',
				'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			)
		);
		// phpcs:enable

		if ( is_wp_error( $request ) ) {
			wp_send_json_error( array( 'message' => $request->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => PPO_Settings::instance()->get( 'success_text' ),
				'id'      => $request->get_id(),
			)
		);
	}

	/**
	 * آیا همین شماره، همین محصول را همین چند دقیقهٔ پیش ثبت کرده است؟
	 *
	 * @param int    $product_id شناسهٔ محصول.
	 * @param string $phone      شماره.
	 * @return bool
	 */
	protected static function is_duplicate( $product_id, $phone ) {
		$minutes = (int) PPO_Settings::instance()->get( 'duplicate_window' );

		if ( $minutes <= 0 ) {
			return false;
		}

		$found = get_posts(
			array(
				'post_type'      => PPO_Post_Type::POST_TYPE,
				'post_status'    => array_merge( PPO_Status::keys(), array( 'private' ) ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'after' => $minutes . ' minutes ago',
					),
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_preorder_phone',
						'value' => $phone,
					),
					array(
						'key'   => '_preorder_product_id',
						'value' => (int) $product_id,
					),
				),
			)
		);

		return ! empty( $found );
	}

	/* ------------------------------ حساب کاربری ------------------------------ */

	/**
	 * ثبت نقطهٔ پایانی «پیش‌فروش‌های من».
	 */
	public static function account_endpoint() {
		add_rewrite_endpoint( 'preorders', EP_ROOT | EP_PAGES );
	}

	/**
	 * افزودن زبانه به منوی حساب کاربری.
	 *
	 * @param array $items زبانه‌ها.
	 * @return array
	 */
	public static function account_menu( $items ) {
		$new = array();

		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'orders' === $key ) {
				$new['preorders'] = __( 'پیش‌فروش‌های من', 'parsian-preorder' );
			}
		}

		if ( ! isset( $new['preorders'] ) ) {
			$new['preorders'] = __( 'پیش‌فروش‌های من', 'parsian-preorder' );
		}

		return $new;
	}

	/**
	 * محتوای زبانهٔ حساب کاربری.
	 */
	public static function account_content() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'      => PPO_Post_Type::POST_TYPE,
				'post_status'    => array_merge( PPO_Status::keys(), array( 'private' ) ),
				'posts_per_page' => 25,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_preorder_user_id',
						'value' => $user_id,
					),
				),
			)
		);

		if ( ! $posts ) {
			echo '<p>' . esc_html__( 'هنوز درخواست پیش‌فروشی ثبت نکرده‌اید.', 'parsian-preorder' ) . '</p>';
			return;
		}

		echo '<table class="woocommerce-orders-table ppo-account-table"><thead><tr>';
		echo '<th>' . esc_html__( 'شماره', 'parsian-preorder' ) . '</th>';
		echo '<th>' . esc_html__( 'تاریخ', 'parsian-preorder' ) . '</th>';
		echo '<th>' . esc_html__( 'محصول', 'parsian-preorder' ) . '</th>';
		echo '<th>' . esc_html__( 'تعداد', 'parsian-preorder' ) . '</th>';
		echo '<th>' . esc_html__( 'وضعیت', 'parsian-preorder' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $posts as $post ) {
			$request = new PPO_Request( $post );

			echo '<tr>';
			echo '<td>#' . esc_html( ppo_digits( $request->get_id() ) ) . '</td>';
			echo '<td>' . esc_html( ppo_jalali_date( $request->get_date() ) ) . '</td>';
			echo '<td>' . esc_html( $request->get_product_name() ) . '</td>';
			echo '<td>' . esc_html( ppo_digits( $request->get( 'quantity', 1 ) ) ) . '</td>';
			echo '<td>' . wp_kses_post( PPO_Status::badge( $request->get_status() ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/* ------------------------------ اسکریپت ------------------------------ */

	/**
	 * بارگذاری استایل و اسکریپت — فقط جایی که لازم است.
	 */
	public static function enqueue() {
		if ( ! function_exists( 'is_woocommerce' ) || ( ! is_woocommerce() && ! is_cart() && ! is_account_page() ) ) {
			return;
		}

		wp_enqueue_style( 'ppo-front', PPO_URL . 'assets/ppo-front.css', array(), PPO_VERSION );

		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_script( 'ppo-front', PPO_URL . 'assets/ppo-front.js', array(), PPO_VERSION, true );
		wp_localize_script(
			'ppo-front',
			'ppoFront',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ppo-submit' ),
				'sending'  => __( 'در حال ثبت…', 'parsian-preorder' ),
				'error'    => __( 'ثبت درخواست ناموفق بود؛ دوباره تلاش کنید.', 'parsian-preorder' ),
				'currency' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
			)
		);
	}
}
