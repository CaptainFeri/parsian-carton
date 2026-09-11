<?php
/**
 * تنظیمات افزونه (پیشخوان ← محصولات ← فیلترهای فروشگاه).
 *
 * @package parsian-shop-filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * مدیریت گزینه‌های افزونه.
 */
class PSF_Settings {

	const OPTION = 'psf_settings';

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PSF_Settings|null
	 */
	protected static $instance = null;

	/**
	 * گزینه‌های خوانده‌شده (کش درون‌درخواستی).
	 *
	 * @var array|null
	 */
	protected $cache = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PSF_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * ثبت قلاب‌های پیشخوان.
	 */
	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * مقادیر پیش‌فرض.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enable_search'     => 1,
			'enable_categories' => 1,
			'enable_price'      => 1,
			'enable_attributes' => 1,
			'enable_stock'      => 1,
			'enable_sale'       => 1,
			'enable_ajax'       => 1,
			'enable_live_search' => 1,
			'attributes'        => array(),
			'open_by_default'   => 1,
		);
	}

	/**
	 * خواندن یک گزینه.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $default مقدار جایگزین.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION, array() );
			$this->cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}

		if ( ! array_key_exists( $key, $this->cache ) ) {
			return $default;
		}

		return $this->cache[ $key ];
	}

	/**
	 * آیا یک بخش فیلتر فعال است؟
	 *
	 * @param string $key کلید بدون پیشوند enable_.
	 * @return bool
	 */
	public function enabled( $key ) {
		return (bool) $this->get( 'enable_' . $key, false );
	}

	/**
	 * افزودن زیرمنو.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'فیلترهای فروشگاه', 'parsian-shop-filters' ),
			__( 'فیلترهای فروشگاه', 'parsian-shop-filters' ),
			'manage_woocommerce',
			'psf-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * ثبت تنظیمات.
	 */
	public function register_settings() {
		register_setting(
			'psf_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * پاک‌سازی ورودی فرم تنظیمات.
	 *
	 * @param mixed $input ورودی خام.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$output = array();

		foreach ( self::defaults() as $key => $default ) {
			if ( 'attributes' === $key ) {
				$attributes = isset( $input['attributes'] ) && is_array( $input['attributes'] ) ? $input['attributes'] : array();
				$output['attributes'] = array_values( array_filter( array_map( 'sanitize_key', $attributes ) ) );
				continue;
			}

			$output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$this->cache = null;

		return $output;
	}

	/**
	 * نمایش صفحهٔ تنظیمات.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$attribute_taxonomies = wc_get_attribute_taxonomies();
		$chosen               = (array) $this->get( 'attributes', array() );
		$toggles              = array(
			'search'      => __( 'کادر جستجو در بایگانی', 'parsian-shop-filters' ),
			'categories'  => __( 'فیلتر دسته‌بندی', 'parsian-shop-filters' ),
			'price'       => __( 'فیلتر بازهٔ قیمت', 'parsian-shop-filters' ),
			'attributes'  => __( 'فیلتر ویژگی‌ها', 'parsian-shop-filters' ),
			'stock'       => __( 'فیلتر «فقط کالاهای موجود»', 'parsian-shop-filters' ),
			'sale'        => __( 'فیلتر «فقط حراج»', 'parsian-shop-filters' ),
			'ajax'        => __( 'به‌روزرسانی آژاکسی شبکهٔ محصولات (بدون بارگذاری مجدد صفحه)', 'parsian-shop-filters' ),
			'live_search' => __( 'پیشنهاد زندهٔ محصولات هنگام تایپ', 'parsian-shop-filters' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'فیلترهای فروشگاه', 'parsian-shop-filters' ); ?></h1>
			<p><?php esc_html_e( 'این تنظیمات روی صفحهٔ فروشگاه و بایگانی دسته‌بندی/برچسب محصولات اعمال می‌شود.', 'parsian-shop-filters' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'psf_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'بخش‌های فعال', 'parsian-shop-filters' ); ?></th>
						<td>
							<?php foreach ( $toggles as $key => $label ) : ?>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox"
										name="<?php echo esc_attr( self::OPTION . '[enable_' . $key . ']' ); ?>"
										value="1" <?php checked( $this->enabled( $key ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'وضعیت اولیهٔ پنل', 'parsian-shop-filters' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPTION . '[open_by_default]' ); ?>"
									value="1" <?php checked( (bool) $this->get( 'open_by_default' ) ); ?>>
								<?php esc_html_e( 'پنل فیلتر در رایانه به‌صورت باز نمایش داده شود', 'parsian-shop-filters' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'ویژگی‌های قابل فیلتر', 'parsian-shop-filters' ); ?></th>
						<td>
							<?php if ( empty( $attribute_taxonomies ) ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: لینک صفحهٔ ویژگی‌های ووکامرس. */
										esc_html__( 'هنوز ویژگی سراسری تعریف نشده است. از %s ویژگی‌هایی مثل «سایز»، «تعداد لایه» و «نوع چاپ» بسازید.', 'parsian-shop-filters' ),
										'<a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=product_attributes' ) ) . '">' . esc_html__( 'محصولات ← ویژگی‌ها', 'parsian-shop-filters' ) . '</a>'
									);
									?>
								</p>
							<?php else : ?>
								<?php foreach ( $attribute_taxonomies as $tax ) : ?>
									<?php $name = wc_attribute_taxonomy_name( $tax->attribute_name ); ?>
									<label style="display:block;margin-bottom:6px;">
										<input type="checkbox"
											name="<?php echo esc_attr( self::OPTION . '[attributes][]' ); ?>"
											value="<?php echo esc_attr( $name ); ?>"
											<?php checked( in_array( $name, $chosen, true ) ); ?>>
										<?php echo esc_html( $tax->attribute_label ); ?>
										<code><?php echo esc_html( $name ); ?></code>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'اگر هیچ ویژگی‌ای انتخاب نشود، همهٔ ویژگی‌های سراسری که روی محصولات مقدار دارند نمایش داده می‌شوند.', 'parsian-shop-filters' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
