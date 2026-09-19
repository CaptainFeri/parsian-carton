<?php
/**
 * مدل «درخواست پیش‌فروش».
 *
 * همهٔ خواندن و نوشتن یک درخواست از همین‌جا می‌گذرد تا نام کلیدهای متا در یک
 * جا بماند و هر تغییر وضعیت، خودبه‌خود در تاریخچه ثبت شود.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * درخواست پیش‌فروش.
 */
class PPO_Request {

	/**
	 * شناسهٔ نوشته.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * نوشتهٔ متناظر.
	 *
	 * @var WP_Post|null
	 */
	protected $post = null;

	/**
	 * نگاشت نام فیلد به کلید متا.
	 *
	 * کلیدها عمداً همان کلیدهای افزونهٔ قدیمی‌اند تا داده‌های ثبت‌شده بدون
	 * جابه‌جایی خوانده شوند.
	 *
	 * @var array<string,string>
	 */
	protected static $meta_map = array(
		'product_id'    => '_preorder_product_id',
		'variation_id'  => '_preorder_variation_id',
		'quantity'      => '_preorder_qty',
		'phone'         => '_preorder_phone',
		'name'          => '_preorder_name',
		'company'       => '_preorder_company',
		'city'          => '_preorder_city',
		'note'          => '_preorder_note',
		'lead_days'     => '_preorder_lead_days',
		'release_date'  => '_preorder_release_date',
		'user_id'       => '_preorder_user_id',
		'unit_price'    => '_preorder_unit_price',
		'assignee'      => '_preorder_assignee',
		'followup_date' => '_preorder_followup',
		'priority'      => '_preorder_priority',
		'order_id'      => '_preorder_order_id',
		'source'        => '_preorder_source',
		'sms_status'    => '_preorder_sms_status',
		'ip'            => '_preorder_ip',
	);

	/**
	 * ساخت شیء از روی شناسه.
	 *
	 * @param int|WP_Post $request شناسه یا نوشته.
	 */
	public function __construct( $request ) {
		if ( $request instanceof WP_Post ) {
			$this->post = $request;
			$this->id   = (int) $request->ID;
			return;
		}

		$this->id   = (int) $request;
		$this->post = get_post( $this->id );
	}

	/**
	 * خواندن یک درخواست — یا null اگر وجود نداشته باشد.
	 *
	 * @param int $id شناسه.
	 * @return PPO_Request|null
	 */
	public static function find( $id ) {
		$post = get_post( (int) $id );

		if ( ! $post || PPO_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return new self( $post );
	}

	/**
	 * آیا درخواست معتبری در دست است؟
	 *
	 * @return bool
	 */
	public function exists() {
		return $this->post && PPO_Post_Type::POST_TYPE === $this->post->post_type;
	}

	/**
	 * شناسه.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/* ------------------------------- ساخت ------------------------------- */

	/**
	 * ثبت یک درخواست تازه.
	 *
	 * @param array $data داده‌های درخواست.
	 * @return PPO_Request|WP_Error
	 */
	public static function create( $data ) {
		$settings   = PPO_Settings::instance();
		$product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product ) {
			return new WP_Error( 'ppo_product', __( 'محصول پیدا نشد.', 'parsian-preorder' ) );
		}

		$phone = ppo_normalize_phone( isset( $data['phone'] ) ? $data['phone'] : '' );

		if ( ! ppo_is_valid_phone( $phone ) ) {
			return new WP_Error( 'ppo_phone', __( 'شمارهٔ موبایل معتبر وارد کنید (مثل ۰۹۱۲۳۴۵۶۷۸۹).', 'parsian-preorder' ) );
		}

		$quantity = max( 1, (int) ppo_latin_digits( isset( $data['quantity'] ) ? $data['quantity'] : 1 ) );
		$status   = $settings->get( 'initial_status' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => PPO_Post_Type::POST_TYPE,
				'post_status' => PPO_Status::exists( $status ) ? $status : PPO_Status::NEW_REQUEST,
				'post_title'  => sprintf(
					/* translators: 1: نام محصول، 2: تعداد. */
					__( '%1$s — %2$s بسته', 'parsian-preorder' ),
					$product->get_name(),
					ppo_digits( $quantity )
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$request  = new self( $post_id );
		$followup = (int) $settings->get( 'followup_days' );

		$request->set_many(
			array(
				'product_id'    => $product_id,
				'variation_id'  => isset( $data['variation_id'] ) ? (int) $data['variation_id'] : 0,
				'quantity'      => $quantity,
				'phone'         => $phone,
				'name'          => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
				'company'       => isset( $data['company'] ) ? sanitize_text_field( $data['company'] ) : '',
				'city'          => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : '',
				'note'          => isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : '',
				'lead_days'     => PPO_Product::lead_days( $product ),
				'release_date'  => PPO_Product::release_date( $product ),
				'user_id'       => isset( $data['user_id'] ) ? (int) $data['user_id'] : get_current_user_id(),
				'unit_price'    => PPO_Product::effective_price( $product ),
				'assignee'      => (int) $settings->get( 'default_assignee' ),
				'followup_date' => $followup ? gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) + $followup * DAY_IN_SECONDS ) : '', // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
				'priority'      => isset( $data['priority'] ) ? sanitize_key( $data['priority'] ) : 'normal',
				'source'        => isset( $data['source'] ) ? sanitize_key( $data['source'] ) : 'site',
				'ip'            => isset( $data['ip'] ) ? sanitize_text_field( $data['ip'] ) : '',
			)
		);

		PPO_Log::add(
			$post_id,
			sprintf(
				/* translators: 1: تعداد، 2: نام محصول. */
				__( 'درخواست ثبت شد: %1$s بسته از «%2$s».', 'parsian-preorder' ),
				ppo_digits( $quantity ),
				$product->get_name()
			),
			'system',
			0
		);

		/**
		 * پس از ثبت یک درخواست تازه.
		 *
		 * @param PPO_Request $request درخواست.
		 */
		do_action( 'ppo_request_created', $request );

		return $request;
	}

	/* ------------------------------ فیلدها ------------------------------ */

	/**
	 * خواندن یک فیلد.
	 *
	 * @param string $field   نام فیلد.
	 * @param mixed  $default مقدار جایگزین.
	 * @return mixed
	 */
	public function get( $field, $default = '' ) {
		if ( ! isset( self::$meta_map[ $field ] ) ) {
			return $default;
		}

		$value = get_post_meta( $this->id, self::$meta_map[ $field ], true );

		if ( '' === $value || null === $value ) {
			return $default;
		}

		$integers = array( 'product_id', 'variation_id', 'quantity', 'lead_days', 'user_id', 'assignee', 'order_id' );

		return in_array( $field, $integers, true ) ? (int) $value : $value;
	}

	/**
	 * نوشتن یک فیلد.
	 *
	 * @param string $field نام فیلد.
	 * @param mixed  $value مقدار.
	 */
	public function set( $field, $value ) {
		if ( ! isset( self::$meta_map[ $field ] ) ) {
			return;
		}

		update_post_meta( $this->id, self::$meta_map[ $field ], $value );
	}

	/**
	 * نوشتن چند فیلد.
	 *
	 * @param array $values نگاشت فیلد => مقدار.
	 */
	public function set_many( $values ) {
		foreach ( $values as $field => $value ) {
			$this->set( $field, $value );
		}
	}

	/* ----------------------------- خصوصیات ----------------------------- */

	/**
	 * وضعیت جاری.
	 *
	 * @return string
	 */
	public function get_status() {
		$status = $this->post ? $this->post->post_status : '';

		// درخواست‌های افزونهٔ قدیمی وضعیت را در متا داشتند و نوشته‌شان private بود.
		if ( ! PPO_Status::exists( $status ) ) {
			return PPO_Status::NEW_REQUEST;
		}

		return $status;
	}

	/**
	 * تغییر وضعیت — با ثبت در تاریخچه و اطلاع‌رسانی.
	 *
	 * @param string $status وضعیت تازه.
	 * @param string $note   یادداشت اختیاری.
	 * @return bool آیا وضعیت عوض شد؟
	 */
	public function set_status( $status, $note = '' ) {
		if ( ! PPO_Status::exists( $status ) ) {
			return false;
		}

		$previous = $this->get_status();

		if ( $previous === $status ) {
			if ( '' !== $note ) {
				PPO_Log::add( $this->id, $note, 'note' );
			}

			return false;
		}

		wp_update_post(
			array(
				'ID'          => $this->id,
				'post_status' => $status,
			)
		);

		$this->post = get_post( $this->id );

		PPO_Log::add(
			$this->id,
			sprintf(
				/* translators: 1: وضعیت قبلی، 2: وضعیت تازه. */
				__( 'وضعیت از «%1$s» به «%2$s» تغییر کرد.', 'parsian-preorder' ),
				PPO_Status::label( $previous ),
				PPO_Status::label( $status )
			),
			'status'
		);

		if ( '' !== $note ) {
			PPO_Log::add( $this->id, $note, 'note' );
		}

		/**
		 * پس از تغییر وضعیت یک درخواست.
		 *
		 * @param PPO_Request $request  درخواست.
		 * @param string      $status   وضعیت تازه.
		 * @param string      $previous وضعیت قبلی.
		 */
		do_action( 'ppo_request_status_changed', $this, $status, $previous );

		return true;
	}

	/**
	 * محصول درخواست.
	 *
	 * @return WC_Product|null
	 */
	public function get_product() {
		$id = $this->get( 'variation_id' ) ? $this->get( 'variation_id' ) : $this->get( 'product_id' );

		if ( ! $id ) {
			return null;
		}

		$product = wc_get_product( $id );

		return $product ? $product : null;
	}

	/**
	 * نام محصول (یا عنوان درخواست، اگر محصول حذف شده باشد).
	 *
	 * @return string
	 */
	public function get_product_name() {
		$product = $this->get_product();

		if ( $product ) {
			return $product->get_name();
		}

		return $this->post ? $this->post->post_title : '';
	}

	/**
	 * ارزش تقریبی درخواست — تعداد × قیمت واحد ثبت‌شده.
	 *
	 * قیمت هنگام ثبت ذخیره می‌شود تا تغییر بعدی قیمت محصول، آمار گذشته را
	 * جابه‌جا نکند.
	 *
	 * @return float
	 */
	public function get_value() {
		$price = (float) $this->get( 'unit_price', 0 );

		if ( ! $price ) {
			$product = $this->get_product();
			$price   = $product ? (float) $product->get_price( 'edit' ) : 0;
		}

		return $price * max( 1, (int) $this->get( 'quantity', 1 ) );
	}

	/**
	 * تاریخ ثبت.
	 *
	 * @return string
	 */
	public function get_date() {
		return $this->post ? $this->post->post_date : '';
	}

	/**
	 * مسئول پیگیری.
	 *
	 * @return WP_User|null
	 */
	public function get_assignee() {
		$user_id = (int) $this->get( 'assignee' );

		if ( ! $user_id ) {
			return null;
		}

		$user = get_userdata( $user_id );

		return $user ? $user : null;
	}

	/**
	 * واگذاری به یک کارشناس.
	 *
	 * @param int $user_id شناسهٔ کاربر (۰ = برداشتن مسئول).
	 */
	public function assign( $user_id ) {
		$user_id  = (int) $user_id;
		$previous = (int) $this->get( 'assignee' );

		if ( $previous === $user_id ) {
			return;
		}

		$this->set( 'assignee', $user_id );

		$user = $user_id ? get_userdata( $user_id ) : false;

		PPO_Log::add(
			$this->id,
			$user
				/* translators: %s: نام کارشناس. */
				? sprintf( __( 'پیگیری به «%s» واگذار شد.', 'parsian-preorder' ), $user->display_name )
				: __( 'مسئول پیگیری برداشته شد.', 'parsian-preorder' ),
			'system'
		);
	}

	/**
	 * آیا پیگیری این درخواست عقب افتاده است؟
	 *
	 * @return bool
	 */
	public function is_overdue() {
		if ( ! PPO_Status::is_open( $this->get_status() ) ) {
			return false;
		}

		$followup = (string) $this->get( 'followup_date' );

		if ( '' !== $followup ) {
			$days = ppo_days_until( $followup );

			return null !== $days && $days < 0;
		}

		// بدون تاریخ پیگیری، معیار، سنِ درخواست است.
		$stale = (int) PPO_Settings::instance()->get( 'stale_days' );

		if ( ! $stale ) {
			return false;
		}

		$age = ( (int) current_time( 'timestamp' ) - (int) strtotime( $this->get_date() ) ) / DAY_IN_SECONDS; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		return $age > $stale;
	}

	/**
	 * سفارش ووکامرس ساخته‌شده از این درخواست.
	 *
	 * @return WC_Order|null
	 */
	public function get_order() {
		$order_id = (int) $this->get( 'order_id' );

		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return $order ? $order : null;
	}

	/**
	 * نشانی صفحهٔ جزئیات در پیشخوان.
	 *
	 * @return string
	 */
	public function get_admin_url() {
		return add_query_arg(
			array(
				'page' => 'ppo-requests',
				'view' => $this->id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * حذف کامل درخواست.
	 *
	 * @return bool
	 */
	public function delete() {
		return (bool) wp_delete_post( $this->id, true );
	}
}
