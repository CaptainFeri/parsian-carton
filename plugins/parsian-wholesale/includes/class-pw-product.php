<?php
/**
 * برگهٔ «فروش عمده» در ویرایش محصول.
 *
 * @package parsian-wholesale
 */

defined( 'ABSPATH' ) || exit;

/**
 * تنظیمات فروش عمدهٔ هر محصول.
 */
class PW_Product {

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PW_Product|null
	 */
	protected static $instance = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PW_Product
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
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
	}

	/**
	 * افزودن برگه.
	 *
	 * @param array $tabs برگه‌ها.
	 * @return array
	 */
	public function add_tab( $tabs ) {
		$tabs['pw_wholesale'] = array(
			'label'    => __( 'فروش عمده', 'parsian-wholesale' ),
			'target'   => 'pw_wholesale_data',
			'class'    => array( 'hide_if_grouped', 'hide_if_external' ),
			'priority' => 25,
		);

		return $tabs;
	}

	/**
	 * محتوای برگه.
	 */
	public function render_panel() {
		global $post;

		$mode   = (string) get_post_meta( $post->ID, '_pw_tiers_mode', true );
		$custom = (string) get_post_meta( $post->ID, '_pw_tiers', true );
		$global = PW_Tiers::to_text( PW_Settings::instance()->tiers() );
		?>
		<div id="pw_wholesale_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_select(
					array(
						'id'      => '_pw_tiers_mode',
						'label'   => __( 'قیمت پلکانی', 'parsian-wholesale' ),
						'value'   => $mode,
						'options' => array(
							''       => __( 'پله‌های سراسری', 'parsian-wholesale' ),
							'custom' => __( 'پله‌های اختصاصی این محصول', 'parsian-wholesale' ),
							'off'    => __( 'بدون قیمت پلکانی', 'parsian-wholesale' ),
						),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_pw_tiers',
						'label'       => __( 'پله‌های اختصاصی', 'parsian-wholesale' ),
						'value'       => $custom,
						'placeholder' => '100:5, 500:10',
						'desc_tip'    => true,
						'description' => __( 'به شکل «تعداد:درصد تخفیف» و جدا با کاما. مثال: 100:5, 500:10 یعنی از ۱۰۰ عدد ۵٪ و از ۵۰۰ عدد ۱۰٪ ارزان‌تر.', 'parsian-wholesale' ),
					)
				);
				?>
				<p class="form-field">
					<?php
					/* translators: %s: پله‌های سراسری */
					echo esc_html( sprintf( __( 'پله‌های سراسری فعلی: %s', 'parsian-wholesale' ), $global ? $global : __( 'تعریف نشده', 'parsian-wholesale' ) ) );
					?>
				</p>
			</div>
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => '_pw_logo_print_off',
						'label'       => __( 'چاپ لوگو', 'parsian-wholesale' ),
						'value'       => 'no' === get_post_meta( $post->ID, '_pw_logo_print', true ) ? 'yes' : 'no',
						'description' => __( 'گزینهٔ «چاپ لوگوی اختصاصی» برای این محصول نمایش داده نشود', 'parsian-wholesale' ),
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * ذخیره.
	 *
	 * @param WC_Product $product محصول.
	 */
	public function save( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- ووکامرس نانس فرم محصول را پیش از این قلاب بررسی کرده است.
		$mode = isset( $_POST['_pw_tiers_mode'] ) ? sanitize_key( wp_unslash( $_POST['_pw_tiers_mode'] ) ) : '';
		$mode = in_array( $mode, array( 'custom', 'off' ), true ) ? $mode : '';

		$tiers = isset( $_POST['_pw_tiers'] ) ? PW_Tiers::to_text( PW_Tiers::parse( sanitize_text_field( wp_unslash( $_POST['_pw_tiers'] ) ) ) ) : '';

		$product->update_meta_data( '_pw_tiers_mode', $mode );
		$product->update_meta_data( '_pw_tiers', $tiers );
		$product->update_meta_data( '_pw_logo_print', empty( $_POST['_pw_logo_print_off'] ) ? '' : 'no' );
		// phpcs:enable
	}
}
