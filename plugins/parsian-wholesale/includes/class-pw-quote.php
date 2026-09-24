<?php
/**
 * فرم «کارتن با ابعاد دلخواه» / استعلام قیمت عمده.
 *
 * درخواست‌ها به‌صورت نوشتهٔ خصوصی (pw_quote) ذخیره می‌شوند و یک نسخه ایمیل
 * می‌شود. فرم با [parsian_quote_form] یا pw_render_quote_form() نمایش داده می‌شود.
 *
 * @package parsian-wholesale
 */

defined( 'ABSPATH' ) || exit;

/**
 * استعلام قیمت.
 */
class PW_Quote {

	const POST_TYPE = 'pw_quote';

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PW_Quote|null
	 */
	protected static $instance = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PW_Quote
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * ثبت قلاب‌ها.
	 */
	protected function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'admin_post_pw_quote', array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_pw_quote', array( $this, 'handle' ) );
		add_shortcode( 'parsian_quote_form', array( $this, 'shortcode' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'meta_box' ) );
	}

	/**
	 * انواع کارتن قابل انتخاب در فرم.
	 *
	 * @return string[]
	 */
	public static function types() {
		return (array) apply_filters( 'pw_quote_types', array( 'سه‌لایه', 'پنج‌لایه', 'دایکات' ) );
	}

	/**
	 * ثبت نوع نوشته.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'          => __( 'استعلام‌های قیمت', 'parsian-wholesale' ),
					'singular_name' => __( 'استعلام قیمت', 'parsian-wholesale' ),
					'menu_name'     => __( 'استعلام‌های قیمت', 'parsian-wholesale' ),
					'edit_item'     => __( 'جزئیات استعلام', 'parsian-wholesale' ),
				),
				'public'        => false,
				'show_ui'       => true,
				'menu_position' => 56,
				'menu_icon'     => 'dashicons-clipboard',
				'supports'      => array( 'title' ),
				'capabilities'  => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'  => true,
			)
		);
	}

	/**
	 * خواندن و پاک‌سازی ورودی فرم.
	 *
	 * @param array $raw ورودی خام ($_POST).
	 * @return array{data: array, errors: string[]}
	 */
	public static function validate( $raw ) {
		$text = static function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) ? sanitize_text_field( wp_unslash( $raw[ $key ] ) ) : '';
		};
		$number = static function ( $key ) use ( $text ) {
			$value = PW_Tiers::to_number( $text( $key ) );

			return null !== $value && $value > 0 ? $value : null;
		};

		$product_id = absint( $raw['pw_product'] ?? 0 );
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$type       = $text( 'pw_type' );
		$phone      = preg_replace( '/[^\d+]/', '', PW_Tiers::latin_digits( $text( 'pw_phone' ) ) );

		$data = array(
			'length'  => $number( 'pw_length' ),
			'width'   => $number( 'pw_width' ),
			'height'  => $number( 'pw_height' ),
			'qty'     => $number( 'pw_qty' ),
			'type'    => in_array( $type, self::types(), true ) ? $type : '',
			'name'    => $text( 'pw_name' ),
			'phone'   => $phone,
			'product' => $product ? $product->get_id() : 0,
		);

		$errors = array();
		if ( ! $data['qty'] ) {
			$errors[] = 'qty';
		}
		if ( ! $data['product'] && ! ( $data['length'] && $data['width'] && $data['height'] ) ) {
			$errors[] = 'size';
		}
		if ( strlen( ltrim( $data['phone'], '+' ) ) < 8 ) {
			$errors[] = 'phone';
		}

		return array(
			'data'   => $data,
			'errors' => $errors,
		);
	}

	/**
	 * عنوان خلاصهٔ یک استعلام.
	 *
	 * @param array $data دادهٔ پاک‌شده.
	 * @return string
	 */
	public static function summary( $data ) {
		$parts = array();
		if ( $data['product'] ) {
			$parts[] = get_the_title( $data['product'] );
		}
		if ( $data['length'] && $data['width'] && $data['height'] ) {
			$parts[] = PW_Tiers::format_number( $data['length'] ) . '×' . PW_Tiers::format_number( $data['width'] ) . '×' . PW_Tiers::format_number( $data['height'] ) . ' سانتی‌متر';
		}
		if ( $data['type'] ) {
			$parts[] = $data['type'];
		}
		$parts[] = PW_Tiers::format_number( $data['qty'] ) . ' عدد';

		return implode( ' — ', $parts );
	}

	/**
	 * پردازش ارسال فرم.
	 */
	public function handle() {
		$redirect = wp_get_referer();
		$redirect = $redirect ? remove_query_arg( array( 'pw_quote', 'pw_error' ), $redirect ) : home_url( '/' );
		$redirect = strtok( $redirect, '#' );

		if ( ! isset( $_POST['pw_quote_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['pw_quote_nonce'] ) ), 'pw_quote' ) ) {
			$this->back( $redirect, 'expired' );
		}

		/* تله برای ربات‌ها: فیلدی که کاربر نمی‌بیند باید خالی بماند. */
		if ( ! empty( $_POST['pw_website'] ) ) {
			$this->back( $redirect, '', 'sent' );
		}

		$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$limit_key = 'pw_quote_' . md5( $ip );
		$count     = (int) get_transient( $limit_key );
		if ( $count >= 5 ) {
			$this->back( $redirect, 'limit' );
		}

		$result = self::validate( $_POST );
		if ( $result['errors'] ) {
			$this->back( $redirect, implode( ',', $result['errors'] ) );
		}

		$data    = $result['data'];
		$summary = self::summary( $data );
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => ( $data['name'] ? $data['name'] . ' — ' : '' ) . $summary,
			)
		);

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			$this->back( $redirect, 'save' );
		}

		update_post_meta( $post_id, '_pw_quote', $data );
		set_transient( $limit_key, $count + 1, 10 * MINUTE_IN_SECONDS );

		$to = (string) PW_Settings::instance()->get( 'quote_email', '' );
		$to = $to ? $to : get_option( 'admin_email' );
		wp_mail(
			$to,
			/* translators: %s: خلاصهٔ استعلام */
			sprintf( __( 'استعلام قیمت جدید: %s', 'parsian-wholesale' ), $summary ),
			implode( "\n", self::detail_lines( $data ) ) . "\n\n" . admin_url( 'post.php?post=' . $post_id . '&action=edit' )
		);

		do_action( 'pw_quote_created', $post_id, $data );

		$this->back( $redirect, '', 'sent' );
	}

	/**
	 * بازگشت به صفحهٔ فرم.
	 *
	 * @param string $url    نشانی.
	 * @param string $error  کد خطا.
	 * @param string $status وضعیت.
	 */
	protected function back( $url, $error, $status = 'error' ) {
		$args = array( 'pw_quote' => $status );
		if ( $error ) {
			$args['pw_error'] = $error;
		}
		wp_safe_redirect( add_query_arg( $args, $url ) . '#quote' );
		exit;
	}

	/**
	 * سطرهای جزئیات برای ایمیل و پیشخوان.
	 *
	 * @param array $data دادهٔ پاک‌شده.
	 * @return string[]
	 */
	public static function detail_lines( $data ) {
		$lines = array(
			__( 'نام', 'parsian-wholesale' ) . ': ' . ( $data['name'] ? $data['name'] : '—' ),
			__( 'شماره تماس', 'parsian-wholesale' ) . ': ' . $data['phone'],
		);
		if ( $data['product'] ) {
			$lines[] = __( 'محصول', 'parsian-wholesale' ) . ': ' . get_the_title( $data['product'] ) . ' (' . get_permalink( $data['product'] ) . ')';
		}
		if ( $data['length'] ) {
			$lines[] = __( 'ابعاد (طول × عرض × ارتفاع)', 'parsian-wholesale' ) . ': ' . PW_Tiers::format_number( $data['length'] ) . ' × ' . PW_Tiers::format_number( (float) $data['width'] ) . ' × ' . PW_Tiers::format_number( (float) $data['height'] ) . ' سانتی‌متر';
		}
		if ( $data['type'] ) {
			$lines[] = __( 'نوع کارتن', 'parsian-wholesale' ) . ': ' . $data['type'];
		}
		$lines[] = __( 'تعداد', 'parsian-wholesale' ) . ': ' . PW_Tiers::format_number( $data['qty'] );

		return $lines;
	}

	/**
	 * پیام نتیجهٔ ارسال قبلی.
	 *
	 * @return array{type: string, text: string}|null
	 */
	public static function notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط پیام نمایشی است.
		$status = isset( $_GET['pw_quote'] ) ? sanitize_key( wp_unslash( $_GET['pw_quote'] ) ) : '';
		$codes  = isset( $_GET['pw_error'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_GET['pw_error'] ) ) ) : array();
		// phpcs:enable

		if ( 'sent' === $status ) {
			return array(
				'type' => 'success',
				'text' => __( 'درخواست شما ثبت شد. پیش‌فاکتور به‌زودی از طریق شماره‌ای که وارد کردید برایتان فرستاده می‌شود.', 'parsian-wholesale' ),
			);
		}
		if ( 'error' !== $status ) {
			return null;
		}

		$messages = array(
			'qty'     => __( 'تعداد را وارد کنید.', 'parsian-wholesale' ),
			'size'    => __( 'طول، عرض و ارتفاع را وارد کنید.', 'parsian-wholesale' ),
			'phone'   => __( 'شماره تماس معتبر وارد کنید.', 'parsian-wholesale' ),
			'limit'   => __( 'تعداد درخواست‌ها زیاد بود؛ چند دقیقهٔ دیگر دوباره امتحان کنید یا تماس بگیرید.', 'parsian-wholesale' ),
			'expired' => __( 'صفحه منقضی شده بود؛ لطفاً دوباره ارسال کنید.', 'parsian-wholesale' ),
			'save'    => __( 'ثبت درخواست ممکن نشد؛ لطفاً دوباره امتحان کنید.', 'parsian-wholesale' ),
		);
		$text = array();
		foreach ( $codes as $code ) {
			if ( isset( $messages[ $code ] ) ) {
				$text[] = $messages[ $code ];
			}
		}

		return array(
			'type' => 'error',
			'text' => $text ? implode( ' ', $text ) : $messages['save'],
		);
	}

	/**
	 * نمایش فرم.
	 *
	 * @param array $args product: شناسهٔ محصول (پیش‌فرض از ?pw_product).
	 * @return string
	 */
	public static function render( $args = array() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product_id = isset( $args['product'] ) ? absint( $args['product'] ) : ( isset( $_GET['pw_product'] ) ? absint( $_GET['pw_product'] ) : 0 );
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$product    = $product && 'publish' === $product->get_status() ? $product : null;
		$notice     = self::notice();
		$need_size  = ! $product;
		$uid        = wp_unique_id( 'pwq-' );

		ob_start();
		?>
		<form class="pw-quote-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pw_quote">
			<?php wp_nonce_field( 'pw_quote', 'pw_quote_nonce' ); ?>

			<?php if ( $notice ) : ?>
				<p class="pw-quote-notice is-<?php echo esc_attr( $notice['type'] ); ?>" role="<?php echo 'error' === $notice['type'] ? 'alert' : 'status'; ?>"><?php echo esc_html( $notice['text'] ); ?></p>
			<?php endif; ?>

			<?php if ( $product ) : ?>
				<input type="hidden" name="pw_product" value="<?php echo esc_attr( $product->get_id() ); ?>">
				<p class="pw-quote-product">
					<?php esc_html_e( 'استعلام برای:', 'parsian-wholesale' ); ?>
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
				</p>
			<?php endif; ?>

			<div class="pw-quote-grid">
				<?php
				$dims = array(
					'pw_length' => array( __( 'طول (سانتی‌متر)', 'parsian-wholesale' ), '۳۰' ),
					'pw_width'  => array( __( 'عرض (سانتی‌متر)', 'parsian-wholesale' ), '۲۰' ),
					'pw_height' => array( __( 'ارتفاع (سانتی‌متر)', 'parsian-wholesale' ), '۲۰' ),
				);
				foreach ( $dims as $name => $field ) :
					?>
					<div class="pw-field">
						<label for="<?php echo esc_attr( "{$uid}-{$name}" ); ?>"><?php echo esc_html( $field[0] ); ?></label>
						<input id="<?php echo esc_attr( "{$uid}-{$name}" ); ?>" name="<?php echo esc_attr( $name ); ?>" type="text" inputmode="decimal" autocomplete="off" placeholder="<?php echo esc_attr( sprintf( /* translators: %s: مثال */ __( 'مثلاً %s', 'parsian-wholesale' ), $field[1] ) ); ?>"<?php echo $need_size ? ' required' : ''; ?>>
					</div>
				<?php endforeach; ?>

				<div class="pw-field">
					<label for="<?php echo esc_attr( "{$uid}-qty" ); ?>"><?php esc_html_e( 'تعداد', 'parsian-wholesale' ); ?></label>
					<input id="<?php echo esc_attr( "{$uid}-qty" ); ?>" name="pw_qty" type="text" inputmode="numeric" autocomplete="off" placeholder="<?php esc_attr_e( 'مثلاً ۵۰۰', 'parsian-wholesale' ); ?>" required>
				</div>

				<div class="pw-field">
					<label for="<?php echo esc_attr( "{$uid}-type" ); ?>"><?php esc_html_e( 'نوع کارتن', 'parsian-wholesale' ); ?></label>
					<select id="<?php echo esc_attr( "{$uid}-type" ); ?>" name="pw_type">
						<?php foreach ( self::types() as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="pw-field">
					<label for="<?php echo esc_attr( "{$uid}-name" ); ?>"><?php esc_html_e( 'نام شما', 'parsian-wholesale' ); ?></label>
					<input id="<?php echo esc_attr( "{$uid}-name" ); ?>" name="pw_name" type="text" autocomplete="name">
				</div>

				<div class="pw-field">
					<label for="<?php echo esc_attr( "{$uid}-phone" ); ?>"><?php esc_html_e( 'شماره تماس', 'parsian-wholesale' ); ?></label>
					<input id="<?php echo esc_attr( "{$uid}-phone" ); ?>" name="pw_phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="09xxxxxxxxx" required>
				</div>

				<button type="submit" class="pw-quote-submit"><?php esc_html_e( 'دریافت پیش‌فاکتور', 'parsian-wholesale' ); ?></button>
			</div>

			<div class="pw-hp" aria-hidden="true">
				<label for="<?php echo esc_attr( "{$uid}-website" ); ?>">Website</label>
				<input id="<?php echo esc_attr( "{$uid}-website" ); ?>" name="pw_website" type="text" tabindex="-1" autocomplete="off">
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * کد کوتاه [parsian_quote_form product="123"].
	 *
	 * @param array $atts ویژگی‌ها.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'product' => null ), $atts, 'parsian_quote_form' );
		if ( null === $atts['product'] ) {
			unset( $atts['product'] );
		}

		return self::render( $atts );
	}

	/**
	 * ستون‌های فهرست استعلام‌ها.
	 *
	 * @param array $columns ستون‌ها.
	 * @return array
	 */
	public function columns( $columns ) {
		return array(
			'cb'       => $columns['cb'],
			'title'    => __( 'استعلام', 'parsian-wholesale' ),
			'pw_phone' => __( 'شماره تماس', 'parsian-wholesale' ),
			'pw_qty'   => __( 'تعداد', 'parsian-wholesale' ),
			'date'     => $columns['date'],
		);
	}

	/**
	 * محتوای ستون‌ها.
	 *
	 * @param string $column  ستون.
	 * @param int    $post_id شناسه.
	 */
	public function column( $column, $post_id ) {
		$data = get_post_meta( $post_id, '_pw_quote', true );
		$data = is_array( $data ) ? $data : array();

		if ( 'pw_phone' === $column && ! empty( $data['phone'] ) ) {
			echo '<a href="' . esc_url( 'tel:' . $data['phone'] ) . '" dir="ltr">' . esc_html( $data['phone'] ) . '</a>';
		} elseif ( 'pw_qty' === $column && ! empty( $data['qty'] ) ) {
			echo esc_html( PW_Tiers::format_number( $data['qty'] ) );
		}
	}

	/**
	 * جعبهٔ جزئیات در صفحهٔ ویرایش.
	 */
	public function meta_box() {
		add_meta_box(
			'pw_quote_details',
			__( 'جزئیات استعلام', 'parsian-wholesale' ),
			function ( $post ) {
				$data = get_post_meta( $post->ID, '_pw_quote', true );
				if ( ! is_array( $data ) ) {
					return;
				}
				echo '<ul>';
				foreach ( self::detail_lines( wp_parse_args( $data, array( 'length' => null, 'width' => null, 'height' => null, 'type' => '', 'name' => '', 'phone' => '', 'product' => 0, 'qty' => 0 ) ) ) as $line ) {
					echo '<li>' . esc_html( $line ) . '</li>';
				}
				echo '</ul>';
			},
			self::POST_TYPE,
			'normal',
			'high'
		);
	}
}

/**
 * نمایش فرم استعلام قیمت.
 *
 * @param array $args product: شناسهٔ محصول.
 */
function pw_render_quote_form( $args = array() ) {
	echo PW_Quote::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی درون render پاک‌سازی شده است.
}
