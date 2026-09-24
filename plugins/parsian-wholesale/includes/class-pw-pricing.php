<?php
/**
 * قیمت پلکانی: جدول صفحهٔ محصول و اعمال قیمت در سبد خرید.
 *
 * @package parsian-wholesale
 */

defined( 'ABSPATH' ) || exit;

/**
 * قیمت پلکانی.
 */
class PW_Pricing {

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PW_Pricing|null
	 */
	protected static $instance = null;

	/**
	 * قیمت پایهٔ هر شیء محصول در سبد، پیش از اعمال تخفیف.
	 *
	 * محاسبهٔ سبد در یک درخواست چند بار اجرا می‌شود؛ بدون این حافظه، تخفیف
	 * روی قیمتِ از قبل تخفیف‌خورده دوباره اعمال می‌شد.
	 *
	 * @var array<int, float>
	 */
	protected $base_prices = array();

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PW_Pricing
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
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_cart_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_data' ), 20, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_table' ), 15 );
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'quantity_args' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * مجموع تعداد هر محصول (والد) در سبد.
	 *
	 * @param WC_Cart $cart سبد.
	 * @return array<int, float>
	 */
	protected function quantities( $cart ) {
		$totals = array();
		foreach ( $cart->get_cart() as $item ) {
			$id            = (int) $item['product_id'];
			$totals[ $id ] = ( isset( $totals[ $id ] ) ? $totals[ $id ] : 0 ) + (float) $item['quantity'];
		}

		return $totals;
	}

	/**
	 * اعمال قیمت پلکانی روی اقلام سبد.
	 *
	 * @param WC_Cart $cart سبد.
	 */
	public function apply_cart_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$quantities = $this->quantities( $cart );
		$divisor    = pw_price_divisor();

		foreach ( $cart->get_cart() as $item ) {
			$product = $item['data'];
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$key = spl_object_id( $product );
			if ( ! isset( $this->base_prices[ $key ] ) ) {
				$this->base_prices[ $key ] = (float) $product->get_price( 'edit' );
			}
			$base = $this->base_prices[ $key ];

			$tiers    = pw_get_tiers( (int) $item['product_id'] );
			$discount = $tiers ? PW_Tiers::discount_for( $tiers, $quantities[ (int) $item['product_id'] ] ) : 0.0;

			$product->set_price( PW_Tiers::apply( $base, $discount, $divisor ) );
		}
	}

	/**
	 * نمایش پلهٔ فعال زیر نام محصول در سبد.
	 *
	 * @param array $data ردیف‌های توضیح.
	 * @param array $item قلم سبد.
	 * @return array
	 */
	public function cart_item_data( $data, $item ) {
		if ( ! WC()->cart || empty( $item['product_id'] ) ) {
			return $data;
		}

		$tiers = pw_get_tiers( (int) $item['product_id'] );
		if ( ! $tiers ) {
			return $data;
		}

		$quantities = $this->quantities( WC()->cart );
		$discount   = PW_Tiers::discount_for( $tiers, $quantities[ (int) $item['product_id'] ] ?? 0 );
		if ( $discount > 0 ) {
			$data[] = array(
				'key'   => __( 'تخفیف پلکانی', 'parsian-wholesale' ),
				'value' => pw_digits( PW_Tiers::format_number( $discount ) ) . '٪',
			);
		}

		return $data;
	}

	/**
	 * قیمت نمایشی پایهٔ یک محصول (برای متغیرها: کمترین قیمت واریاسیون).
	 *
	 * @param WC_Product $product محصول.
	 * @return float
	 */
	public static function display_base_price( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			/* از قیمت نمایشی تک‌تک واریاسیون‌ها؛ get_variation_price() قیمت خام
			   دیتابیس را می‌دهد و از فیلترهای نمایش قیمت (مثل ریال ← تومان) رد نمی‌شود. */
			$min = null;
			foreach ( $product->get_visible_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child && '' !== $child->get_price() ) {
					$price = (float) wc_get_price_to_display( $child );
					$min   = null === $min ? $price : min( $min, $price );
				}
			}

			return (float) $min;
		}

		return (float) wc_get_price_to_display( $product );
	}

	/**
	 * تعداد پیش‌فرض صفحهٔ محصول، محدود به موجودی.
	 *
	 * @param WC_Product $product محصول.
	 * @return int
	 */
	public static function default_quantity( $product ) {
		$qty = (int) pw_quantity_settings()['default'];
		if ( $qty < 1 ) {
			return 1;
		}

		$max = $product->get_max_purchase_quantity();
		if ( $max > 0 && $qty > $max ) {
			$qty = $max;
		}

		return max( 1, $qty );
	}

	/**
	 * مقدار اولیهٔ فیلد تعداد در صفحهٔ محصول.
	 *
	 * @param array      $args    آرگومان‌های فیلد.
	 * @param WC_Product $product محصول.
	 * @return array
	 */
	public function quantity_args( $args, $product ) {
		if ( ! is_product() || ! $product || is_cart() || isset( $_REQUEST['quantity'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $args;
		}

		/* فقط فیلد اصلی فرم خرید؛ فیلدهای محصولات گروهی نام آرایه‌ای دارند. */
		if ( isset( $args['input_name'] ) && 'quantity' !== $args['input_name'] ) {
			return $args;
		}

		$args['input_value'] = self::default_quantity( $product );

		return $args;
	}

	/**
	 * جدول قیمت پلکانی صفحهٔ محصول.
	 *
	 * @param WC_Product|null $product محصول؛ پیش‌فرض محصول جاری.
	 */
	public function render_table( $product = null ) {
		if ( ! $product instanceof WC_Product ) {
			global $product;
		}
		if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
			return;
		}

		$tiers = pw_get_tiers( $product );
		if ( ! $tiers ) {
			return;
		}

		$base   = self::display_base_price( $product );
		$qty    = self::default_quantity( $product );
		$active = PW_Tiers::find( $tiers, $qty );
		$ranges = PW_Tiers::ranges( $tiers );
		?>
		<div class="pw-tiers" data-pw-tiers="<?php echo esc_attr( wp_json_encode( $tiers ) ); ?>" data-pw-base="<?php echo esc_attr( $base ); ?>">
			<span class="pw-tiers-title" id="pw-tiers-title"><?php esc_html_e( 'قیمت پلکانی', 'parsian-wholesale' ); ?></span>
			<ul class="pw-tiers-list" aria-labelledby="pw-tiers-title">
				<?php foreach ( $ranges as $i => $range ) : ?>
					<li class="pw-tier<?php echo $i === $active ? ' is-active' : ''; ?>" data-pw-discount="<?php echo esc_attr( $range['discount'] ); ?>"<?php echo $i === $active ? ' aria-current="true"' : ''; ?>>
						<span class="pw-tier-range"><?php echo esc_html( pw_range_label( $range ) ); ?></span>
						<span class="pw-tier-side">
							<span class="pw-tier-badge"<?php echo $i === $active ? '' : ' hidden'; ?>><?php esc_html_e( 'قیمت فعلی شما', 'parsian-wholesale' ); ?></span>
							<span class="pw-tier-price"><?php echo wp_kses_post( wc_price( PW_Tiers::apply( $base, $range['discount'] ) ) ); ?></span>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * اسکریپت جدول پلکانی.
	 */
	public function enqueue() {
		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_script( 'pw-wholesale', PW_URL . 'assets/pw.js', array( 'jquery' ), PW_VERSION, true );
		/* همان تنظیمی که wc_price() به کار می‌برد، با تغییرات قالب (مثلاً جداکنندهٔ ٬). */
		$format = apply_filters(
			'wc_price_args',
			array(
				'decimal_separator'  => wc_get_price_decimal_separator(),
				'thousand_separator' => wc_get_price_thousand_separator(),
				'decimals'           => wc_get_price_decimals(),
			)
		);

		wp_localize_script(
			'pw-wholesale',
			'pwData',
			array(
				'decimals'  => $format['decimals'],
				'thousand'  => $format['thousand_separator'],
				'decimal'   => $format['decimal_separator'],
				'format'    => html_entity_decode( get_woocommerce_price_format() ),
				'symbol'    => html_entity_decode( get_woocommerce_currency_symbol() ),
				'step'      => pw_quantity_settings()['step'],
			)
		);
	}
}
