<?php
/**
 * فیلدهای ویرایش یک بنر.
 *
 * @package parsian-banners
 */

defined( 'ABSPATH' ) || exit;

/**
 * جعبهٔ تنظیمات بنر.
 */
class PBN_Meta {

	const NONCE = 'pbn_save_banner';

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'save_post_' . PBN_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * افزودن جعبه.
	 */
	public static function add_box() {
		add_meta_box(
			'pbn_banner_fields',
			__( 'محتوای بنر', 'parsian-banners' ),
			array( __CLASS__, 'render_content' ),
			PBN_Post_Type::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'pbn_banner_display',
			__( 'نمایش', 'parsian-banners' ),
			array( __CLASS__, 'render_display' ),
			PBN_Post_Type::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * فیلدهای محتوا.
	 *
	 * @param WP_Post $post نوشته.
	 */
	public static function render_content( $post ) {
		$banner = new PBN_Banner( $post );

		wp_nonce_field( self::NONCE, 'pbn_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pbn-eyebrow"><?php esc_html_e( 'برچسب بالای عنوان', 'parsian-banners' ); ?></label></th>
				<td>
					<input type="text" id="pbn-eyebrow" name="pbn_eyebrow" class="regular-text"
						value="<?php echo esc_attr( $banner->get( 'eyebrow' ) ); ?>"
						placeholder="<?php esc_attr_e( 'تولیدکننده مستقیم کارتن', 'parsian-banners' ); ?>">
					<p class="description"><?php esc_html_e( 'متن کوتاهی که بالای عنوان اصلی، با رنگ تأکید دیده می‌شود.', 'parsian-banners' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pbn-description"><?php esc_html_e( 'توضیح', 'parsian-banners' ); ?></label></th>
				<td>
					<textarea id="pbn-description" name="pbn_description" rows="3" class="large-text"><?php echo esc_textarea( $banner->get( 'description' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'یکی دو جمله زیر عنوان. برای تأکید روی بخشی از عنوان، آن را داخل <em>…</em> بگذارید.', 'parsian-banners' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pbn-features"><?php esc_html_e( 'فهرست ویژگی‌ها', 'parsian-banners' ); ?></label></th>
				<td>
					<textarea id="pbn-features" name="pbn_features" rows="3" class="large-text"
						placeholder="🚚 ارسال به سراسر کشور&#10;🎨 چاپ اختصاصی"><?php echo esc_textarea( $banner->get( 'features' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'هر خط، یک مورد. خالی بگذارید تا نمایش داده نشود.', 'parsian-banners' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'دکمهٔ اصلی', 'parsian-banners' ); ?></th>
				<td>
					<input type="text" name="pbn_button_text" class="regular-text"
						value="<?php echo esc_attr( $banner->get( 'button_text' ) ); ?>"
						placeholder="<?php esc_attr_e( 'مشاهده محصولات', 'parsian-banners' ); ?>">
					<input type="url" name="pbn_button_url" class="regular-text ltr" dir="ltr"
						value="<?php echo esc_attr( $banner->get( 'button_url' ) ); ?>"
						placeholder="https://parsiancarton.com/shop/">
					<p class="description"><?php esc_html_e( 'هر دو خالی یعنی دکمه‌ای نمایش داده نشود.', 'parsian-banners' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'دکمهٔ دوم', 'parsian-banners' ); ?></th>
				<td>
					<input type="text" name="pbn_button2_text" class="regular-text"
						value="<?php echo esc_attr( $banner->get( 'button2_text' ) ); ?>"
						placeholder="<?php esc_attr_e( 'مشاوره رایگان', 'parsian-banners' ); ?>">
					<input type="text" name="pbn_button2_url" class="regular-text ltr" dir="ltr"
						value="<?php echo esc_attr( $banner->get( 'button2_url' ) ); ?>"
						placeholder="tel:02133445566">
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * فیلدهای نمایش.
	 *
	 * @param WP_Post $post نوشته.
	 */
	public static function render_display( $post ) {
		$banner   = new PBN_Banner( $post );
		$mobile   = (int) $banner->get( 'mobile_image', 0 );
		$devices  = array(
			'all'     => __( 'موبایل و دسکتاپ', 'parsian-banners' ),
			'desktop' => __( 'فقط دسکتاپ', 'parsian-banners' ),
			'mobile'  => __( 'فقط موبایل', 'parsian-banners' ),
		);
		$schemes  = array(
			'light' => __( 'متن روشن (تصویر تیره)', 'parsian-banners' ),
			'dark'  => __( 'متن تیره (تصویر روشن)', 'parsian-banners' ),
		);
		$location = $banner->get_location();
		?>
		<p>
			<label for="pbn-location"><strong><?php esc_html_e( 'جایگاه', 'parsian-banners' ); ?></strong></label><br>
			<select id="pbn-location" name="pbn_location" style="width:100%">
				<?php foreach ( PBN_Render::locations() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $location, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="pbn-device"><strong><?php esc_html_e( 'روی چه دستگاهی', 'parsian-banners' ); ?></strong></label><br>
			<select id="pbn-device" name="pbn_device" style="width:100%">
				<?php foreach ( $devices as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $banner->get_device(), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="pbn-scheme"><strong><?php esc_html_e( 'رنگ متن', 'parsian-banners' ); ?></strong></label><br>
			<select id="pbn-scheme" name="pbn_scheme" style="width:100%">
				<?php foreach ( $schemes as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $banner->get_scheme(), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="pbn-overlay"><strong><?php esc_html_e( 'تیرگی روی تصویر', 'parsian-banners' ); ?></strong></label><br>
			<?php
			// بنر تازه هنوز تصویری ندارد، پس get_overlay() صفر می‌دهد؛ ولی وقتی
			// کاربر تصویر را انتخاب کند، صفر یعنی متن روی تصویر ناخواناست.
			$pbn_overlay = '' === $banner->get( 'overlay', '' ) ? 45 : $banner->get_overlay();
			?>
			<input type="number" id="pbn-overlay" name="pbn_overlay" min="0" max="100" step="5" style="width:100%"
				value="<?php echo esc_attr( $pbn_overlay ); ?>">
			<span class="description"><?php esc_html_e( 'درصد. اگر متن روی تصویر خوانا نیست، بالاترش ببرید.', 'parsian-banners' ); ?></span>
		</p>

		<p>
			<label for="pbn-start"><strong><?php esc_html_e( 'نمایش از تاریخ', 'parsian-banners' ); ?></strong></label><br>
			<input type="text" id="pbn-start" name="pbn_start" style="width:100%" dir="ltr"
				value="<?php echo esc_attr( $banner->get( 'start_date' ) ? pbn_jalali_date( $banner->get( 'start_date' ) ) : '' ); ?>"
				placeholder="<?php esc_attr_e( '۱۴۰۵/۰۷/۰۱', 'parsian-banners' ); ?>">
		</p>

		<p>
			<label for="pbn-end"><strong><?php esc_html_e( 'تا تاریخ', 'parsian-banners' ); ?></strong></label><br>
			<input type="text" id="pbn-end" name="pbn_end" style="width:100%" dir="ltr"
				value="<?php echo esc_attr( $banner->get( 'end_date' ) ? pbn_jalali_date( $banner->get( 'end_date' ) ) : '' ); ?>"
				placeholder="<?php esc_attr_e( '۱۴۰۵/۰۸/۳۰', 'parsian-banners' ); ?>">
			<span class="description"><?php esc_html_e( 'هر دو خالی یعنی همیشه نمایش داده شود.', 'parsian-banners' ); ?></span>
		</p>

		<div class="pbn-mobile-image">
			<strong><?php esc_html_e( 'تصویر ویژهٔ موبایل', 'parsian-banners' ); ?></strong>
			<p class="description"><?php esc_html_e( 'اختیاری. اگر تصویر اصلی روی گوشی بد بریده می‌شود، یک تصویر عمودی اینجا بگذارید.', 'parsian-banners' ); ?></p>

			<input type="hidden" id="pbn-mobile-image" name="pbn_mobile_image" value="<?php echo esc_attr( $mobile ); ?>">

			<div class="pbn-mobile-preview">
				<?php if ( $mobile ) : ?>
					<img src="<?php echo esc_url( (string) wp_get_attachment_image_url( $mobile, 'medium' ) ); ?>" alt="">
				<?php endif; ?>
			</div>

			<button type="button" class="button pbn-pick-image"><?php esc_html_e( 'انتخاب تصویر', 'parsian-banners' ); ?></button>
			<button type="button" class="button-link pbn-clear-image"><?php esc_html_e( 'برداشتن', 'parsian-banners' ); ?></button>
		</div>
		<?php
	}

	/**
	 * ذخیرهٔ فیلدها.
	 *
	 * @param int     $post_id شناسه.
	 * @param WP_Post $post    نوشته.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['pbn_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['pbn_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$banner = new PBN_Banner( $post_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- بالا بررسی شد.
		$texts = array(
			'eyebrow'      => 'pbn_eyebrow',
			'button_text'  => 'pbn_button_text',
			'button2_text' => 'pbn_button2_text',
		);

		foreach ( $texts as $field => $key ) {
			$banner->set( $field, isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '' );
		}

		$banner->set( 'description', isset( $_POST['pbn_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pbn_description'] ) ) : '' );
		$banner->set( 'features', isset( $_POST['pbn_features'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pbn_features'] ) ) : '' );

		// دکمهٔ اول همیشه نشانی است؛ دکمهٔ دوم می‌تواند tel: یا mailto: باشد.
		$banner->set( 'button_url', isset( $_POST['pbn_button_url'] ) ? esc_url_raw( wp_unslash( $_POST['pbn_button_url'] ) ) : '' );
		$banner->set(
			'button2_url',
			isset( $_POST['pbn_button2_url'] )
				? esc_url_raw( wp_unslash( $_POST['pbn_button2_url'] ), array( 'http', 'https', 'tel', 'mailto' ) )
				: ''
		);

		$banner->set( 'mobile_image', isset( $_POST['pbn_mobile_image'] ) ? (int) $_POST['pbn_mobile_image'] : 0 );
		$banner->set( 'scheme', ( isset( $_POST['pbn_scheme'] ) && 'dark' === $_POST['pbn_scheme'] ) ? 'dark' : 'light' );
		$banner->set( 'overlay', isset( $_POST['pbn_overlay'] ) ? min( 100, max( 0, (int) $_POST['pbn_overlay'] ) ) : 45 );

		$device = isset( $_POST['pbn_device'] ) ? sanitize_key( wp_unslash( $_POST['pbn_device'] ) ) : 'all';
		$banner->set( 'device', in_array( $device, array( 'all', 'desktop', 'mobile' ), true ) ? $device : 'all' );

		$location = isset( $_POST['pbn_location'] ) ? sanitize_key( wp_unslash( $_POST['pbn_location'] ) ) : 'home';
		$banner->set( 'location', array_key_exists( $location, PBN_Render::locations() ) ? $location : 'home' );

		$banner->set( 'start_date', isset( $_POST['pbn_start'] ) ? pbn_parse_date( wp_unslash( $_POST['pbn_start'] ) ) : '' );
		$banner->set( 'end_date', isset( $_POST['pbn_end'] ) ? pbn_parse_date( wp_unslash( $_POST['pbn_end'] ) ) : '' );
		// phpcs:enable

		unset( $post );
	}
}
