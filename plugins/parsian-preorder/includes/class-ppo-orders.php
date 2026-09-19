<?php
/**
 * پل میان درخواست پیش‌فروش و سفارش ووکامرس.
 *
 * کار کارتابل وقتی تمام می‌شود که درخواست به یک سفارش واقعی تبدیل شود. این
 * کلاس همان یک کلیک را انجام می‌دهد: ساخت سفارش با محصول، تعداد و قیمت
 * توافق‌شده، وصل کردن مشتری، و پیوند دوطرفه میان درخواست و سفارش.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * تبدیل درخواست به سفارش.
 */
class PPO_Orders {

	/**
	 * کلید متای سفارش که به درخواست اشاره می‌کند.
	 */
	const ORDER_META = '_ppo_request_id';

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_order_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_order_column' ), 20, 2 );

		// جدول سفارش‌های HPOS ووکامرس.
		add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'add_order_column' ), 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'render_order_column' ), 20, 2 );
	}

	/**
	 * ساخت سفارش از روی یک درخواست.
	 *
	 * @param PPO_Request $request    درخواست.
	 * @param array       $args       گزینه‌ها: quantity، unit_price، status.
	 * @return WC_Order|WP_Error
	 */
	public static function create_order( $request, $args = array() ) {
		if ( ! function_exists( 'wc_create_order' ) ) {
			return new WP_Error( 'ppo_no_wc', __( 'ووکامرس در دسترس نیست.', 'parsian-preorder' ) );
		}

		if ( $request->get( 'order_id' ) ) {
			return new WP_Error(
				'ppo_already',
				__( 'برای این درخواست از قبل سفارش ساخته شده است.', 'parsian-preorder' )
			);
		}

		$product = $request->get_product();

		if ( ! $product ) {
			return new WP_Error( 'ppo_no_product', __( 'محصول این درخواست دیگر در فروشگاه نیست.', 'parsian-preorder' ) );
		}

		$args = wp_parse_args(
			$args,
			array(
				'quantity'   => (int) $request->get( 'quantity', 1 ),
				'unit_price' => (float) $request->get( 'unit_price', 0 ),
				'status'     => 'wc-pending',
			)
		);

		$quantity = max( 1, (int) $args['quantity'] );
		$price    = (float) $args['unit_price'];

		if ( $price <= 0 ) {
			$price = (float) $product->get_price( 'edit' );
		}

		$order = wc_create_order(
			array(
				'customer_id' => (int) $request->get( 'user_id' ),
				'status'      => $args['status'],
			)
		);

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$order->add_product( $product, $quantity, array( 'subtotal' => $price * $quantity, 'total' => $price * $quantity ) );

		$name  = (string) $request->get( 'name' );
		$parts = preg_split( '/\s+/u', trim( $name ), 2 );

		$order->set_billing_first_name( isset( $parts[0] ) ? $parts[0] : '' );
		$order->set_billing_last_name( isset( $parts[1] ) ? $parts[1] : '' );
		$order->set_billing_phone( ppo_display_phone( $request->get( 'phone' ) ) );

		if ( $request->get( 'company' ) ) {
			$order->set_billing_company( $request->get( 'company' ) );
		}

		if ( $request->get( 'city' ) ) {
			$order->set_billing_city( $request->get( 'city' ) );
		}

		$order->update_meta_data( self::ORDER_META, $request->get_id() );
		$order->calculate_totals( false );

		$notes = array(
			sprintf(
				/* translators: %s: شمارهٔ درخواست. */
				__( 'از درخواست پیش‌فروش #%s ساخته شد.', 'parsian-preorder' ),
				ppo_digits( $request->get_id() )
			),
		);

		$release = (string) $request->get( 'release_date' );

		if ( '' !== $release ) {
			/* translators: %s: تاریخ عرضه. */
			$notes[] = sprintf( __( 'تاریخ عرضه: %s', 'parsian-preorder' ), ppo_jalali_date( $release ) );
		}

		if ( $request->get( 'lead_days' ) ) {
			/* translators: %s: تعداد روز. */
			$notes[] = sprintf( __( 'زمان آماده‌سازی: %s روز کاری', 'parsian-preorder' ), ppo_digits( $request->get( 'lead_days' ) ) );
		}

		if ( $request->get( 'note' ) ) {
			/* translators: %s: توضیحات مشتری. */
			$notes[] = sprintf( __( 'توضیحات مشتری: %s', 'parsian-preorder' ), $request->get( 'note' ) );
		}

		$order->add_order_note( implode( "\n", $notes ) );
		$order->save();

		$request->set( 'order_id', $order->get_id() );

		PPO_Log::add(
			$request->get_id(),
			sprintf(
				/* translators: 1: شمارهٔ سفارش، 2: تعداد، 3: مبلغ. */
				__( 'سفارش #%1$s ساخته شد — %2$s بسته، مبلغ %3$s.', 'parsian-preorder' ),
				ppo_digits( $order->get_order_number() ),
				ppo_digits( $quantity ),
				ppo_price( $order->get_total() )
			),
			'order'
		);

		$request->set_status( PPO_Status::CONVERTED );

		/**
		 * پس از ساخت سفارش از یک درخواست.
		 *
		 * @param WC_Order    $order   سفارش.
		 * @param PPO_Request $request درخواست.
		 */
		do_action( 'ppo_order_created', $order, $request );

		return $order;
	}

	/**
	 * درخواستِ پشت یک سفارش.
	 *
	 * @param WC_Order|int $order سفارش.
	 * @return PPO_Request|null
	 */
	public static function request_for_order( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( (int) $order );
		}

		if ( ! $order ) {
			return null;
		}

		$request_id = (int) $order->get_meta( self::ORDER_META, true );

		return $request_id ? PPO_Request::find( $request_id ) : null;
	}

	/* --------------------------- فهرست سفارش‌ها --------------------------- */

	/**
	 * افزودن ستون «پیش‌فروش» به فهرست سفارش‌ها.
	 *
	 * @param array $columns ستون‌ها.
	 * @return array
	 */
	public static function add_order_column( $columns ) {
		$columns['ppo_request'] = __( 'پیش‌فروش', 'parsian-preorder' );

		return $columns;
	}

	/**
	 * محتوای ستون.
	 *
	 * @param string       $column ستون.
	 * @param int|WC_Order $order  سفارش.
	 */
	public static function render_order_column( $column, $order ) {
		if ( 'ppo_request' !== $column ) {
			return;
		}

		$request = self::request_for_order( $order );

		if ( ! $request ) {
			echo '<span class="ppo-muted">—</span>';
			return;
		}

		printf(
			'<a href="%1$s">#%2$s</a>',
			esc_url( $request->get_admin_url() ),
			esc_html( ppo_digits( $request->get_id() ) )
		);
	}
}
