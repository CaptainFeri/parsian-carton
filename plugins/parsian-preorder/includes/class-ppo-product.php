<?php
/**
 * تنظیمات پیش‌فروش روی هر محصول.
 *
 * زبانهٔ «پیش‌فروش» در ویرایشگر محصول، ستون پیش‌فروش در فهرست محصولات، و
 * توابع خواندن این تنظیمات که بقیهٔ افزونه به آن‌ها تکیه می‌کند.
 *
 * کلیدهای متا عمداً همان کلیدهای افزونهٔ قدیمی‌اند (`_cartonpak_preorder` و
 * `_cartonpak_lead_days`) تا محصولاتی که از قبل پیش‌فروش بوده‌اند، بعد از
 * به‌روزرسانی همان‌طور بمانند.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * پیش‌فروش در سطح محصول.
 */
class PPO_Product {

	const META_ENABLED   = '_cartonpak_preorder';
	const META_LEAD_DAYS = '_cartonpak_lead_days';
	const META_RELEASE   = '_ppo_release_date';
	const META_CAPACITY  = '_ppo_capacity';
	const META_MIN_QTY   = '_ppo_min_qty';
	const META_DEPOSIT   = '_ppo_deposit';
	const META_PRICE     = '_ppo_price';
	const META_NOTE      = '_ppo_note';
	const META_AUTO_END  = '_ppo_auto_end';

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ) );

		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_column' ), 20, 2 );
	}

	/* ------------------------------ خواندن ------------------------------ */

	/**
	 * تبدیل ورودی به شیء محصول.
	 *
	 * @param WC_Product|int $product محصول یا شناسه.
	 * @return WC_Product|null
	 */
	protected static function resolve( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( (int) $product );
		}

		return ( $product && is_a( $product, 'WC_Product' ) ) ? $product : null;
	}

	/**
	 * آیا این محصول پیش‌فروش است؟
	 *
	 * محصولی که تاریخ عرضه‌اش گذشته و «پایان خودکار» دارد، دیگر پیش‌فروش نیست.
	 *
	 * @param WC_Product|int $product محصول.
	 * @return bool
	 */
	public static function is_preorder( $product ) {
		$product = self::resolve( $product );

		if ( ! $product ) {
			return false;
		}

		$enabled = 'yes' === $product->get_meta( self::META_ENABLED, true );

		// واریاسیون، تنظیم والدش را به ارث می‌برد.
		if ( ! $enabled && $product->is_type( 'variation' ) ) {
			$parent  = self::resolve( $product->get_parent_id() );
			$enabled = $parent ? 'yes' === $parent->get_meta( self::META_ENABLED, true ) : false;
			$product = $parent ? $parent : $product;
		}

		if ( ! $enabled ) {
			return false;
		}

		if ( 'yes' === $product->get_meta( self::META_AUTO_END, true ) ) {
			$release = self::release_date( $product );
			$days    = '' !== $release ? ppo_days_until( $release ) : null;

			if ( null !== $days && $days < 0 ) {
				return false;
			}
		}

		/**
		 * تغییر تشخیص پیش‌فروش بودن یک محصول.
		 *
		 * @param bool       $enabled نتیجه.
		 * @param WC_Product $product محصول.
		 */
		return (bool) apply_filters( 'ppo_is_preorder', true, $product );
	}

	/**
	 * زمان آماده‌سازی و ارسال (روز کاری).
	 *
	 * @param WC_Product|int $product محصول.
	 * @return int
	 */
	public static function lead_days( $product ) {
		$product = self::resolve( $product );

		return $product ? max( 0, (int) $product->get_meta( self::META_LEAD_DAYS, true ) ) : 0;
	}

	/**
	 * تاریخ عرضه (میلادی Y-m-d).
	 *
	 * @param WC_Product|int $product محصول.
	 * @return string
	 */
	public static function release_date( $product ) {
		$product = self::resolve( $product );

		if ( ! $product ) {
			return '';
		}

		$date = (string) $product->get_meta( self::META_RELEASE, true );

		if ( '' === $date && $product->is_type( 'variation' ) ) {
			$parent = self::resolve( $product->get_parent_id() );
			$date   = $parent ? (string) $parent->get_meta( self::META_RELEASE, true ) : '';
		}

		return $date;
	}

	/**
	 * ظرفیت پیش‌فروش (۰ = بی‌نهایت).
	 *
	 * @param WC_Product|int $product محصول.
	 * @return int
	 */
	public static function capacity( $product ) {
		$product = self::resolve( $product );

		return $product ? max( 0, (int) $product->get_meta( self::META_CAPACITY, true ) ) : 0;
	}

	/**
	 * حداقل تیراژ سفارش.
	 *
	 * @param WC_Product|int $product محصول.
	 * @return int
	 */
	public static function min_quantity( $product ) {
		$product = self::resolve( $product );
		$minimum = $product ? (int) $product->get_meta( self::META_MIN_QTY, true ) : 0;

		return max( 1, $minimum );
	}

	/**
	 * درصد پیش‌پرداخت (۰ = بدون پیش‌پرداخت).
	 *
	 * @param WC_Product|int $product محصول.
	 * @return int
	 */
	public static function deposit_percent( $product ) {
		$product = self::resolve( $product );
		$percent = $product ? (int) $product->get_meta( self::META_DEPOSIT, true ) : 0;

		return min( 100, max( 0, $percent ) );
	}

	/**
	 * یادداشت پیش‌فروش که زیر محصول نمایش داده می‌شود.
	 *
	 * @param WC_Product|int $product محصول.
	 * @return string
	 */
	public static function note( $product ) {
		$product = self::resolve( $product );

		return $product ? (string) $product->get_meta( self::META_NOTE, true ) : '';
	}

	/**
	 * قیمت مؤثر پیش‌فروش — قیمت ویژهٔ پیش‌فروش، وگرنه قیمت خود محصول.
	 *
	 * @param WC_Product|int $product محصول.
	 * @return float
	 */
	public static function effective_price( $product ) {
		$product = self::resolve( $product );

		if ( ! $product ) {
			return 0.0;
		}

		$special = $product->get_meta( self::META_PRICE, true );

		if ( '' !== $special && null !== $special ) {
			return (float) $special;
		}

		return (float) $product->get_price( 'edit' );
	}

	/**
	 * تعداد بسته‌های رزروشدهٔ یک محصول در درخواست‌های باز.
	 *
	 * @param int $product_id شناسهٔ محصول.
	 * @return int
	 */
	public static function reserved( $product_id ) {
		global $wpdb;

		$product_id = (int) $product_id;

		if ( ! $product_id ) {
			return 0;
		}

		$statuses     = PPO_Status::open_keys();
		$statuses[]   = PPO_Status::CONVERTED;
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- جانگهدارها بالا ساخته شده‌اند.
		$sql = $wpdb->prepare(
			"SELECT SUM( CAST( qty.meta_value AS UNSIGNED ) )
			 FROM {$wpdb->posts} AS posts
			 INNER JOIN {$wpdb->postmeta} AS product ON product.post_id = posts.ID AND product.meta_key = '_preorder_product_id'
			 INNER JOIN {$wpdb->postmeta} AS qty ON qty.post_id = posts.ID AND qty.meta_key = '_preorder_qty'
			 WHERE posts.post_type = %s
			   AND posts.post_status IN ( {$placeholders} )
			   AND product.meta_value = %d",
			array_merge( array( PPO_Post_Type::POST_TYPE ), $statuses, array( $product_id ) )
		);
		// phpcs:enable

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * ظرفیت باقی‌مانده — یا null وقتی ظرفیتی تعیین نشده باشد.
	 *
	 * @param WC_Product|int $product محصول.
	 * @return int|null
	 */
	public static function remaining( $product ) {
		$product = self::resolve( $product );

		if ( ! $product ) {
			return null;
		}

		$capacity = self::capacity( $product );

		if ( ! $capacity ) {
			return null;
		}

		return max( 0, $capacity - self::reserved( $product->get_id() ) );
	}

	/**
	 * آیا ظرفیت پیش‌فروش این محصول پر شده است؟
	 *
	 * @param WC_Product|int $product محصول.
	 * @return bool
	 */
	public static function is_full( $product ) {
		$remaining = self::remaining( $product );

		return null !== $remaining && $remaining <= 0;
	}

	/**
	 * شناسهٔ همهٔ محصولات پیش‌فروش.
	 *
	 * @return int[]
	 */
	public static function preorder_product_ids() {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_ENABLED,
						'value' => 'yes',
					),
				),
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/* ------------------------------ ویرایشگر ------------------------------ */

	/**
	 * افزودن زبانهٔ پیش‌فروش.
	 *
	 * @param array $tabs زبانه‌ها.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs['ppo_preorder'] = array(
			'label'    => __( 'پیش‌فروش', 'parsian-preorder' ),
			'target'   => 'ppo_product_data',
			'class'    => array(),
			'priority' => 25,
		);

		return $tabs;
	}

	/**
	 * محتوای زبانه.
	 */
	public static function render_panel() {
		global $post;

		$product = wc_get_product( $post->ID );

		if ( ! $product ) {
			return;
		}

		$release   = self::release_date( $product );
		$remaining = self::remaining( $product );
		?>
		<div id="ppo_product_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => self::META_ENABLED,
						'label'       => __( 'پیش‌فروش فعال است', 'parsian-preorder' ),
						'description' => __( 'دکمهٔ «افزودن به سبد» با فرم ثبت درخواست پیش‌فروش جایگزین می‌شود.', 'parsian-preorder' ),
						'value'       => 'yes' === $product->get_meta( self::META_ENABLED, true ) ? 'yes' : 'no',
					)
				);
				?>
			</div>

			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'          => self::META_LEAD_DAYS,
						'label'       => __( 'زمان آماده‌سازی (روز کاری)', 'parsian-preorder' ),
						'description' => __( 'به مشتری نمایش داده می‌شود: «ارسال تا … روز کاری».', 'parsian-preorder' ),
						'desc_tip'    => true,
						'type'        => 'number',
						'custom_attributes' => array(
							'min'  => '0',
							'step' => '1',
						),
						'value'       => self::lead_days( $product ) ? self::lead_days( $product ) : '',
					)
				);
				?>

				<p class="form-field ppo_release_field">
					<label for="<?php echo esc_attr( self::META_RELEASE ); ?>"><?php esc_html_e( 'تاریخ عرضه', 'parsian-preorder' ); ?></label>
					<input type="text" class="short" name="<?php echo esc_attr( self::META_RELEASE ); ?>"
						id="<?php echo esc_attr( self::META_RELEASE ); ?>"
						value="<?php echo esc_attr( $release ? ppo_jalali_date( $release ) : '' ); ?>"
						placeholder="<?php esc_attr_e( '۱۴۰۴/۰۸/۰۱', 'parsian-preorder' ); ?>">
					<span class="description">
						<?php esc_html_e( 'شمسی یا میلادی. خالی یعنی تاریخ مشخصی اعلام نشده است.', 'parsian-preorder' ); ?>
						<?php if ( $release ) : ?>
							<strong><?php echo esc_html( ppo_relative_days( $release ) ); ?></strong>
						<?php endif; ?>
					</span>
				</p>

				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => self::META_AUTO_END,
						'label'       => __( 'پایان خودکار', 'parsian-preorder' ),
						'description' => __( 'با رسیدن تاریخ عرضه، پیش‌فروش خودبه‌خود خاموش می‌شود و محصول عادی فروخته می‌شود.', 'parsian-preorder' ),
						'value'       => 'yes' === $product->get_meta( self::META_AUTO_END, true ) ? 'yes' : 'no',
					)
				);
				?>
			</div>

			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'                => self::META_MIN_QTY,
						'label'             => __( 'حداقل تیراژ (بسته)', 'parsian-preorder' ),
						'description'       => __( 'کمتر از این تعداد، درخواست ثبت نمی‌شود.', 'parsian-preorder' ),
						'desc_tip'          => true,
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => '1',
							'step' => '1',
						),
						'value'             => self::min_quantity( $product ) > 1 ? self::min_quantity( $product ) : '',
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => self::META_CAPACITY,
						'label'             => __( 'ظرفیت پیش‌فروش (بسته)', 'parsian-preorder' ),
						'description'       => __( 'مجموع بسته‌هایی که برای این دوره می‌پذیرید. خالی یا صفر یعنی بدون سقف.', 'parsian-preorder' ),
						'desc_tip'          => true,
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => '0',
							'step' => '1',
						),
						'value'             => self::capacity( $product ) ? self::capacity( $product ) : '',
					)
				);
				?>

				<?php if ( null !== $remaining ) : ?>
					<p class="form-field">
						<label><?php esc_html_e( 'وضعیت ظرفیت', 'parsian-preorder' ); ?></label>
						<span class="description">
							<?php
							printf(
								/* translators: 1: رزروشده، 2: ظرفیت، 3: باقی‌مانده. */
								esc_html__( '%1$s بسته از %2$s بسته رزرو شده — %3$s بسته باقی مانده.', 'parsian-preorder' ),
								esc_html( ppo_digits( self::reserved( $product->get_id() ) ) ),
								esc_html( ppo_digits( self::capacity( $product ) ) ),
								esc_html( ppo_digits( $remaining ) )
							);
							?>
						</span>
					</p>
				<?php endif; ?>
			</div>

			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'                => self::META_PRICE,
						'label'             => __( 'قیمت ویژهٔ پیش‌فروش', 'parsian-preorder' ),
						'description'       => __( 'خالی یعنی همان قیمت عادی محصول. فقط برای برآورد ارزش درخواست و نمایش در فرم استفاده می‌شود.', 'parsian-preorder' ),
						'desc_tip'          => true,
						'data_type'         => 'price',
						'value'             => $product->get_meta( self::META_PRICE, true ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => self::META_DEPOSIT,
						'label'             => __( 'پیش‌پرداخت (درصد)', 'parsian-preorder' ),
						'description'       => __( 'درصدی از مبلغ که هنگام ثبت سفارش دریافت می‌شود. در فرم به مشتری اعلام می‌شود.', 'parsian-preorder' ),
						'desc_tip'          => true,
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => '0',
							'max'  => '100',
							'step' => '1',
						),
						'value'             => self::deposit_percent( $product ) ? self::deposit_percent( $product ) : '',
					)
				);

				woocommerce_wp_textarea_input(
					array(
						'id'          => self::META_NOTE,
						'label'       => __( 'یادداشت پیش‌فروش', 'parsian-preorder' ),
						'description' => __( 'زیر عنوان محصول و بالای فرم نمایش داده می‌شود؛ مثلاً شرایط چاپ یا حداقل سفارش.', 'parsian-preorder' ),
						'desc_tip'    => true,
						'value'       => self::note( $product ),
					)
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * ذخیرهٔ تنظیمات محصول.
	 *
	 * @param int $post_id شناسهٔ محصول.
	 */
	public static function save( $post_id ) {
		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- ووکامرس پیش از این قلاب nonce را بررسی کرده است.
		$enabled = isset( $_POST[ self::META_ENABLED ] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST[ self::META_ENABLED ] ) );

		$product->update_meta_data( self::META_ENABLED, $enabled ? 'yes' : 'no' );
		$product->update_meta_data( self::META_AUTO_END, isset( $_POST[ self::META_AUTO_END ] ) ? 'yes' : 'no' );

		$integers = array(
			self::META_LEAD_DAYS => 0,
			self::META_MIN_QTY   => 0,
			self::META_CAPACITY  => 0,
			self::META_DEPOSIT   => 0,
		);

		foreach ( $integers as $key => $minimum ) {
			$value = isset( $_POST[ $key ] ) ? (int) ppo_latin_digits( wp_unslash( $_POST[ $key ] ) ) : 0;
			$product->update_meta_data( $key, max( $minimum, $value ) );
		}

		$release = isset( $_POST[ self::META_RELEASE ] ) ? ppo_parse_date( wp_unslash( $_POST[ self::META_RELEASE ] ) ) : '';
		$product->update_meta_data( self::META_RELEASE, $release );

		$price = isset( $_POST[ self::META_PRICE ] ) ? wc_format_decimal( wp_unslash( $_POST[ self::META_PRICE ] ) ) : '';
		$product->update_meta_data( self::META_PRICE, $price );

		$note = isset( $_POST[ self::META_NOTE ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ self::META_NOTE ] ) ) : '';
		$product->update_meta_data( self::META_NOTE, $note );
		// phpcs:enable

		$product->save();
	}

	/* ---------------------------- فهرست محصولات ---------------------------- */

	/**
	 * افزودن ستون پیش‌فروش به فهرست محصولات.
	 *
	 * @param array $columns ستون‌ها.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'is_in_stock' === $key ) {
				$new['ppo_preorder'] = __( 'پیش‌فروش', 'parsian-preorder' );
			}
		}

		if ( ! isset( $new['ppo_preorder'] ) ) {
			$new['ppo_preorder'] = __( 'پیش‌فروش', 'parsian-preorder' );
		}

		return $new;
	}

	/**
	 * محتوای ستون پیش‌فروش.
	 *
	 * @param string $column  نام ستون.
	 * @param int    $post_id شناسهٔ محصول.
	 */
	public static function render_column( $column, $post_id ) {
		if ( 'ppo_preorder' !== $column ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product || 'yes' !== $product->get_meta( self::META_ENABLED, true ) ) {
			echo '<span class="ppo-muted">—</span>';
			return;
		}

		$release   = self::release_date( $product );
		$remaining = self::remaining( $product );

		echo '<span class="ppo-badge" style="--ppo-badge-color:' . esc_attr( PPO_Settings::instance()->get( 'badge_color' ) ) . '">';
		echo esc_html__( 'فعال', 'parsian-preorder' );
		echo '</span>';

		if ( $release ) {
			echo '<br><small>' . esc_html( ppo_jalali_date( $release ) ) . '</small>';
		}

		if ( null !== $remaining ) {
			echo '<br><small>';
			printf(
				/* translators: %s: تعداد بسته. */
				esc_html__( '%s بسته باقی', 'parsian-preorder' ),
				esc_html( ppo_digits( $remaining ) )
			);
			echo '</small>';
		}
	}
}
