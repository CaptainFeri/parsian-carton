<?php
/**
 * تنظیمات افزونه (پیشخوان ← محصولات ← فروش عمده).
 *
 * @package parsian-wholesale
 */

defined( 'ABSPATH' ) || exit;

/**
 * مدیریت گزینه‌های فروش عمده.
 */
class PW_Settings {

	const OPTION = 'pw_settings';

	/**
	 * تعداد ردیف‌های پله در فرم تنظیمات.
	 */
	const TIER_ROWS = 5;

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PW_Settings|null
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
	 * @return PW_Settings
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
	 * بازه‌ها طبق طرح ۱–۹۹، ۱۰۰–۴۹۹ و ۵۰۰ به بالا هستند ولی درصدها صفرند:
	 * تا فروشنده درصد واقعی را وارد نکند، جدول پلکانی در سایت نمایش داده نمی‌شود.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'tiers'           => array( array( 1, 0.0 ), array( 100, 0.0 ), array( 500, 0.0 ) ),
			'default_qty'     => 100,
			'qty_step'        => 10,
			'qty_presets'     => '100, 500, 1000',
			'logo_print'      => 1,
			'logo_print_note' => 'هزینهٔ چاپ پس از بررسی طرح، پیش از ارسال به شما اعلام می‌شود.',
			'quote_email'     => '',
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

		return array_key_exists( $key, $this->cache ) ? $this->cache[ $key ] : $default;
	}

	/**
	 * پله‌های سراسری.
	 *
	 * @return array
	 */
	public function tiers() {
		return PW_Tiers::normalize( $this->get( 'tiers', array() ) );
	}

	/**
	 * عددهای میان‌بر تعداد.
	 *
	 * @return int[]
	 */
	public function presets() {
		$out = array();
		foreach ( preg_split( '/[,،\s]+/u', PW_Tiers::latin_digits( (string) $this->get( 'qty_presets', '' ) ) ) as $part ) {
			$n = (int) $part;
			if ( $n > 0 ) {
				$out[ $n ] = $n;
			}
		}

		return array_values( $out );
	}

	/**
	 * افزودن زیرمنو.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'فروش عمده', 'parsian-wholesale' ),
			__( 'فروش عمده', 'parsian-wholesale' ),
			'manage_woocommerce',
			'pw-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * ثبت تنظیمات.
	 */
	public function register_settings() {
		register_setting(
			'pw_settings_group',
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
		$input = is_array( $input ) ? $input : array();

		$rows = array();
		if ( isset( $input['tiers'] ) && is_array( $input['tiers'] ) ) {
			foreach ( $input['tiers'] as $row ) {
				if ( is_array( $row ) && isset( $row['min'] ) && '' !== trim( (string) $row['min'] ) ) {
					$rows[] = array( wp_unslash( $row['min'] ), isset( $row['discount'] ) ? wp_unslash( $row['discount'] ) : 0 );
				}
			}
		}

		$email = isset( $input['quote_email'] ) ? sanitize_email( wp_unslash( $input['quote_email'] ) ) : '';

		$output = array(
			'tiers'           => PW_Tiers::normalize( $rows ),
			'default_qty'     => max( 0, (int) PW_Tiers::to_number( $input['default_qty'] ?? 0 ) ),
			'qty_step'        => max( 1, (int) PW_Tiers::to_number( $input['qty_step'] ?? 1 ) ),
			'qty_presets'     => sanitize_text_field( wp_unslash( $input['qty_presets'] ?? '' ) ),
			'logo_print'      => empty( $input['logo_print'] ) ? 0 : 1,
			'logo_print_note' => sanitize_text_field( wp_unslash( $input['logo_print_note'] ?? '' ) ),
			'quote_email'     => is_email( $email ) ? $email : '',
		);

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

		$name  = self::OPTION;
		$tiers = $this->tiers();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'فروش عمده', 'parsian-wholesale' ); ?></h1>
			<p><?php esc_html_e( 'قیمت پلکانی، گزینهٔ چاپ لوگو و فرم استعلام قیمت. قیمت پلکانی بر پایهٔ مجموع تعداد هر محصول در سبد (همهٔ واریاسیون‌هایش با هم) حساب می‌شود.', 'parsian-wholesale' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'pw_settings_group' ); ?>

				<h2><?php esc_html_e( 'قیمت پلکانی', 'parsian-wholesale' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'هر ردیف: از این تعداد به بالا، این درصد از قیمت هر عدد کم می‌شود. ردیف‌های خالی نادیده گرفته می‌شوند. تا وقتی همهٔ درصدها صفرند، جدول پلکانی در سایت نمایش داده نمی‌شود. هر محصول می‌تواند در برگهٔ «فروش عمده» ویرایش محصول، پله‌های خودش را داشته باشد.', 'parsian-wholesale' ); ?>
				</p>
				<table class="widefat striped" style="max-width:420px;margin:12px 0 24px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'از تعداد', 'parsian-wholesale' ); ?></th>
							<th><?php esc_html_e( 'درصد تخفیف', 'parsian-wholesale' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php for ( $i = 0; $i < self::TIER_ROWS; $i++ ) : ?>
							<?php
							$min      = isset( $tiers[ $i ] ) ? $tiers[ $i ][0] : '';
							$discount = isset( $tiers[ $i ] ) ? PW_Tiers::format_number( $tiers[ $i ][1] ) : '';
							?>
							<tr>
								<td>
									<?php if ( 0 === $i ) : ?>
										<input type="hidden" name="<?php echo esc_attr( "{$name}[tiers][0][min]" ); ?>" value="1">
										<?php esc_html_e( '۱ (قیمت پایه)', 'parsian-wholesale' ); ?>
									<?php else : ?>
										<input type="text" inputmode="numeric" class="small-text" name="<?php echo esc_attr( "{$name}[tiers][{$i}][min]" ); ?>" value="<?php echo esc_attr( $min ); ?>">
									<?php endif; ?>
								</td>
								<td>
									<input type="text" inputmode="decimal" class="small-text" name="<?php echo esc_attr( "{$name}[tiers][{$i}][discount]" ); ?>" value="<?php echo esc_attr( $discount ); ?>"> ٪
								</td>
							</tr>
						<?php endfor; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'تعداد در صفحهٔ محصول', 'parsian-wholesale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pw-default-qty"><?php esc_html_e( 'تعداد پیش‌فرض', 'parsian-wholesale' ); ?></label></th>
						<td>
							<input id="pw-default-qty" type="text" inputmode="numeric" class="small-text" name="<?php echo esc_attr( "{$name}[default_qty]" ); ?>" value="<?php echo esc_attr( $this->get( 'default_qty' ) ); ?>">
							<p class="description"><?php esc_html_e( 'صفر یعنی پیش‌فرض ووکامرس (۱ عدد). اگر موجودی انبار کمتر باشد، همان موجودی پیش‌فرض می‌شود.', 'parsian-wholesale' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pw-qty-step"><?php esc_html_e( 'گام دکمه‌های + و −', 'parsian-wholesale' ); ?></label></th>
						<td><input id="pw-qty-step" type="text" inputmode="numeric" class="small-text" name="<?php echo esc_attr( "{$name}[qty_step]" ); ?>" value="<?php echo esc_attr( $this->get( 'qty_step' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pw-qty-presets"><?php esc_html_e( 'دکمه‌های میان‌بر تعداد', 'parsian-wholesale' ); ?></label></th>
						<td>
							<input id="pw-qty-presets" type="text" class="regular-text" name="<?php echo esc_attr( "{$name}[qty_presets]" ); ?>" value="<?php echo esc_attr( $this->get( 'qty_presets' ) ); ?>">
							<p class="description"><?php esc_html_e( 'با کاما جدا کنید؛ خالی یعنی بدون میان‌بر.', 'parsian-wholesale' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'چاپ لوگوی اختصاصی', 'parsian-wholesale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'نمایش گزینه', 'parsian-wholesale' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( "{$name}[logo_print]" ); ?>" value="1" <?php checked( (bool) $this->get( 'logo_print' ) ); ?>>
								<?php esc_html_e( 'گزینهٔ «چاپ لوگوی اختصاصی» در صفحهٔ محصول (قابل خاموش کردن برای هر محصول)', 'parsian-wholesale' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pw-logo-note"><?php esc_html_e( 'توضیح زیر گزینه', 'parsian-wholesale' ); ?></label></th>
						<td><input id="pw-logo-note" type="text" class="large-text" name="<?php echo esc_attr( "{$name}[logo_print_note]" ); ?>" value="<?php echo esc_attr( $this->get( 'logo_print_note' ) ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'استعلام قیمت', 'parsian-wholesale' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pw-quote-email"><?php esc_html_e( 'ایمیل دریافت استعلام‌ها', 'parsian-wholesale' ); ?></label></th>
						<td>
							<input id="pw-quote-email" type="email" class="regular-text" dir="ltr" name="<?php echo esc_attr( "{$name}[quote_email]" ); ?>" value="<?php echo esc_attr( $this->get( 'quote_email' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<p class="description"><?php esc_html_e( 'استعلام‌ها در «پیشخوان ← استعلام‌های قیمت» ذخیره می‌شوند و یک نسخه به این ایمیل هم فرستاده می‌شود. خالی یعنی ایمیل مدیر سایت.', 'parsian-wholesale' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
