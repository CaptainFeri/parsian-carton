<?php
/**
 * گزینهٔ «چاپ لوگوی اختصاصی» در صفحهٔ محصول.
 *
 * انتخاب مشتری همراه قلم سبد ذخیره و در سفارش ثبت می‌شود؛ قیمت را تغییر
 * نمی‌دهد چون هزینهٔ چاپ به طرح بستگی دارد و بعداً اعلام می‌شود.
 *
 * @package parsian-wholesale
 */

defined( 'ABSPATH' ) || exit;

/**
 * چاپ لوگو.
 */
class PW_Logo_Print {

	const FIELD = 'pw_logo_print';

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PW_Logo_Print|null
	 */
	protected static $instance = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PW_Logo_Print
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
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_field' ), 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'order_item_meta' ), 10, 3 );
	}

	/**
	 * آیا گزینه برای این محصول فعال است؟
	 *
	 * @param WC_Product|int $product محصول.
	 * @return bool
	 */
	public static function enabled_for( $product ) {
		if ( ! PW_Settings::instance()->get( 'logo_print' ) ) {
			return false;
		}

		$parent_id = pw_parent_id( $product );

		return (bool) apply_filters( 'pw_logo_print_enabled', 'no' !== get_post_meta( $parent_id, '_pw_logo_print', true ), $parent_id );
	}

	/**
	 * نمایش گزینه در فرم خرید.
	 */
	public function render_field() {
		global $product;
		if ( ! $product instanceof WC_Product || ! self::enabled_for( $product ) ) {
			return;
		}

		$note = (string) PW_Settings::instance()->get( 'logo_print_note', '' );
		?>
		<div class="pw-logo-print">
			<label class="pw-check">
				<input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>" value="1"<?php echo $note ? ' aria-describedby="pw-logo-note"' : ''; ?>>
				<span><?php esc_html_e( 'چاپ لوگوی اختصاصی روی کارتن (سفارش عمده)', 'parsian-wholesale' ); ?></span>
			</label>
			<?php if ( $note ) : ?>
				<p class="pw-note" id="pw-logo-note"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * ذخیرهٔ انتخاب همراه قلم سبد.
	 *
	 * @param array $data       دادهٔ قلم.
	 * @param int   $product_id شناسهٔ محصول.
	 * @return array
	 */
	public function add_cart_item_data( $data, $product_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- فرم افزودن به سبد ووکامرس نانس ندارد.
		if ( ! empty( $_POST[ self::FIELD ] ) && self::enabled_for( $product_id ) ) {
			$data[ self::FIELD ] = 1;
		}

		return $data;
	}

	/**
	 * نمایش در سبد و صفحهٔ پرداخت.
	 *
	 * @param array $data ردیف‌های توضیح.
	 * @param array $item قلم سبد.
	 * @return array
	 */
	public function cart_item_data( $data, $item ) {
		if ( ! empty( $item[ self::FIELD ] ) ) {
			$data[] = array(
				'key'   => __( 'چاپ لوگوی اختصاصی', 'parsian-wholesale' ),
				'value' => __( 'بله', 'parsian-wholesale' ),
			);
		}

		return $data;
	}

	/**
	 * ثبت در اقلام سفارش.
	 *
	 * @param WC_Order_Item_Product $order_item قلم سفارش.
	 * @param string                $key        کلید قلم سبد.
	 * @param array                 $values     دادهٔ قلم سبد.
	 */
	public function order_item_meta( $order_item, $key, $values ) {
		if ( ! empty( $values[ self::FIELD ] ) ) {
			$order_item->add_meta_data( __( 'چاپ لوگوی اختصاصی', 'parsian-wholesale' ), __( 'بله', 'parsian-wholesale' ), true );
		}
	}
}
