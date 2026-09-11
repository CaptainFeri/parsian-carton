<?php
/**
 * نمایش پنل فیلتر و ابزار جستجو در بایگانی محصولات.
 *
 * @package parsian-shop-filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * لایهٔ نمایش.
 */
class PSF_Render {

	const BOUNDS_VERSION_OPTION = 'psf_bounds_version';

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PSF_Render|null
	 */
	protected static $instance = null;

	/**
	 * آیا پنل در این درخواست نمایش داده شده است؟
	 *
	 * @var bool
	 */
	protected $rendered = false;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PSF_Render
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'render' ), 15 );

		// قالب ووکامرس `woocommerce_before_shop_loop` را فقط وقتی صدا می‌زند که
		// محصولی پیدا شده باشد. بدون این قلاب، فیلتری که نتیجه‌ای ندارد پنل را هم
		// محو می‌کرد و کاربر راهی برای برداشتن فیلتر نداشت.
		add_action( 'woocommerce_no_products_found', array( $this, 'render' ), 1 );
		add_action( 'woocommerce_no_products_found', array( $this, 'render_empty_state' ), 5 );

		// کش بازهٔ قیمت با هر تغییر محصول باطل می‌شود.
		add_action( 'woocommerce_update_product', array( __CLASS__, 'flush_bounds_cache' ) );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'flush_bounds_cache' ) );
		add_action( 'psf_flush_caches', array( __CLASS__, 'flush_bounds_cache' ) );
	}

	/**
	 * بارگذاری استایل و اسکریپت در بایگانی‌های فیلترپذیر.
	 */
	public function enqueue() {
		if ( ! psf_is_filterable_archive() ) {
			return;
		}

		wp_enqueue_style( 'psf-filters', PSF_URL . 'assets/psf.css', array(), PSF_VERSION );
		wp_enqueue_script( 'psf-filters', PSF_URL . 'assets/psf.js', array(), PSF_VERSION, true );

		$settings = PSF_Settings::instance();

		wp_localize_script(
			'psf-filters',
			'psfData',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'psf_nonce' ),
				'useAjax'     => $settings->enabled( 'ajax' ) ? 1 : 0,
				'liveSearch'  => $settings->enabled( 'live_search' ) ? 1 : 0,
				'openDefault' => $settings->get( 'open_by_default' ) ? 1 : 0,
				'i18n'        => array(
					'loading'    => __( 'در حال به‌روزرسانی…', 'parsian-shop-filters' ),
					'noSuggest'  => __( 'محصولی با این عبارت پیدا نشد.', 'parsian-shop-filters' ),
					'searching'  => __( 'در حال جستجو…', 'parsian-shop-filters' ),
					'allResults' => __( 'دیدن همهٔ نتایج', 'parsian-shop-filters' ),
				),
			)
		);
	}

	/* --------------------------------- داده‌ها --------------------------------- */

	/**
	 * تاکسونومی‌های ویژگی که باید فیلتر شوند.
	 *
	 * @return string[]
	 */
	public static function filterable_attribute_taxonomies() {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$cache = array();

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return $cache;
		}

		$chosen = array();
		if ( class_exists( 'PSF_Settings' ) ) {
			$chosen = (array) PSF_Settings::instance()->get( 'attributes', array() );
		}

		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			if ( $chosen && ! in_array( $taxonomy, $chosen, true ) ) {
				continue;
			}

			$cache[] = $taxonomy;
		}

		/**
		 * تغییر فهرست ویژگی‌های قابل فیلتر.
		 *
		 * @param string[] $cache فهرست تاکسونومی‌ها.
		 */
		$cache = (array) apply_filters( 'psf_filterable_attributes', $cache );

		return $cache;
	}

	/**
	 * باطل کردن کش بازهٔ قیمت.
	 */
	public static function flush_bounds_cache() {
		update_option( self::BOUNDS_VERSION_OPTION, time(), false );
	}

	/**
	 * کمینه و بیشینهٔ قیمت محصولات در زمینهٔ جاری (به واحد نمایشی).
	 *
	 * @return array{min:float,max:float}
	 */
	public function price_bounds() {
		global $wpdb;

		$object  = get_queried_object();
		$term_id = ( $object instanceof WP_Term ) ? (int) $object->term_id : 0;
		$version = (int) get_option( self::BOUNDS_VERSION_OPTION, 0 );
		$key     = 'psf_bounds_' . $version . '_' . $term_id;
		$cached  = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
		$join   = '';
		$where  = '';

		if ( $term_id && $object instanceof WP_Term ) {
			$tax_query = new WP_Tax_Query(
				array(
					array(
						'taxonomy'         => $object->taxonomy,
						'field'            => 'term_id',
						'terms'            => $term_id,
						'include_children' => true,
					),
				)
			);
			$sql   = $tax_query->get_sql( $wpdb->posts, 'ID' );
			$join  = $sql['join'];
			$where = $sql['where'];
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			"SELECT MIN(min_price) AS min_price, MAX(max_price) AS max_price
			 FROM {$lookup}
			 WHERE product_id IN (
				SELECT {$wpdb->posts}.ID FROM {$wpdb->posts} {$join}
				WHERE {$wpdb->posts}.post_type = 'product'
				AND {$wpdb->posts}.post_status = 'publish' {$where}
			 )"
		);
		// phpcs:enable

		$bounds = array(
			'min' => $row && null !== $row->min_price ? floor( psf_to_display_price( $row->min_price ) ) : 0.0,
			'max' => $row && null !== $row->max_price ? ceil( psf_to_display_price( $row->max_price ) ) : 0.0,
		);

		set_transient( $key, $bounds, DAY_IN_SECONDS );

		return $bounds;
	}

	/**
	 * شمارش محصولات هر ترم با در نظر گرفتن فیلترهای فعال دیگر.
	 *
	 * @param string $taxonomy تاکسونومی.
	 * @param int[]  $term_ids شناسهٔ ترم‌ها.
	 * @return array<int,int>
	 */
	protected function term_counts( $taxonomy, $term_ids ) {
		$filterer_class = '\Automattic\WooCommerce\Internal\ProductAttributesLookup\Filterer';

		if ( $term_ids && function_exists( 'wc_get_container' ) && class_exists( $filterer_class ) ) {
			try {
				$counts = wc_get_container()->get( $filterer_class )->get_filtered_term_product_counts( $term_ids, $taxonomy, 'or' );
				if ( is_array( $counts ) ) {
					return array_map( 'intval', $counts );
				}
			} catch ( Throwable $e ) {
				// نسخهٔ ووکامرس این API داخلی را ندارد — به شمارش سراسری ترم برمی‌گردیم.
				unset( $e );
			}
		}

		return array();
	}

	/* --------------------------------- آدرس‌ها --------------------------------- */

	/**
	 * آدرس پایهٔ بایگانی جاری (بدون پارامترهای پرس‌وجو).
	 *
	 * @return string
	 */
	public function base_url() {
		if ( is_product_taxonomy() ) {
			$object = get_queried_object();
			$link   = ( $object instanceof WP_Term ) ? get_term_link( $object ) : '';
			if ( $link && ! is_wp_error( $link ) ) {
				return $link;
			}
		}

		$shop = wc_get_page_permalink( 'shop' );

		return $shop ? $shop : home_url( '/' );
	}

	/**
	 * پارامترهای پرس‌وجوی جاری، منهای کلیدهای حذف‌شده.
	 *
	 * @param string[] $remove کلیدهایی که باید حذف شوند.
	 * @return array
	 */
	public function current_query_args( $remove = array() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args = wp_unslash( $_GET );
		$args = is_array( $args ) ? $args : array();

		// صفحه‌بندی همیشه با تغییر فیلتر به اول برمی‌گردد.
		$remove = array_merge( $remove, array( 'paged', 'page', 'psf_ajax' ) );

		foreach ( $remove as $key ) {
			unset( $args[ $key ] );
		}

		return $args;
	}

	/**
	 * ساخت آدرس بایگانی با مجموعه‌ای از پارامترها.
	 *
	 * @param array $args پارامترها.
	 * @return string
	 */
	public function build_url( $args ) {
		$base  = $this->base_url();
		$parts = wp_parse_url( $base );

		if ( ! empty( $parts['query'] ) ) {
			$existing = array();
			wp_parse_str( $parts['query'], $existing );
			$args = array_merge( $existing, $args );
			$base = strtok( $base, '?' );
		}

		$args = array_filter(
			$args,
			static function ( $value ) {
				return '' !== $value && null !== $value && array() !== $value;
			}
		);

		return $args ? add_query_arg( $args, $base ) : $base;
	}

	/**
	 * آدرسی که یک فیلتر مشخص را حذف می‌کند.
	 *
	 * @param string      $key   کلید پارامتر.
	 * @param string|null $value مقدار مشخص (برای فیلترهای چندمقداری).
	 * @return string
	 */
	public function url_without( $key, $value = null ) {
		$args = $this->current_query_args();

		if ( null === $value || ! isset( $args[ $key ] ) ) {
			unset( $args[ $key ] );
			return $this->build_url( $args );
		}

		$current = is_array( $args[ $key ] ) ? $args[ $key ] : explode( ',', (string) $args[ $key ] );
		$current = array_values(
			array_filter(
				array_map( 'sanitize_title', $current ),
				static function ( $item ) use ( $value ) {
					return $item !== $value;
				}
			)
		);

		if ( $current ) {
			$args[ $key ] = $current;
		} else {
			unset( $args[ $key ] );
		}

		return $this->build_url( $args );
	}

	/**
	 * فیلدهای مخفی برای حفظ زمینهٔ صفحه هنگام ارسال فرم.
	 */
	public function hidden_context_fields() {
		$base  = $this->base_url();
		$parts = wp_parse_url( $base );
		$keep  = array();

		if ( ! empty( $parts['query'] ) ) {
			wp_parse_str( $parts['query'], $keep );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['orderby'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$keep['orderby'] = sanitize_title( wp_unslash( $_GET['orderby'] ) );
		}

		foreach ( $keep as $name => $value ) {
			printf(
				'<input type="hidden" name="%s" value="%s">',
				esc_attr( $name ),
				esc_attr( $value )
			);
		}
	}

	/* --------------------------------- نمایش --------------------------------- */

	/**
	 * شمارش فیلترهای فعال (برای نشانگر روی دکمهٔ «فیلترها»).
	 *
	 * @return int
	 */
	public function count_active() {
		$filters = PSF_Query::active_filters();
		$count   = 0;

		$count += count( $filters['categories'] );
		$count += $filters['stock'] ? 1 : 0;
		$count += $filters['sale'] ? 1 : 0;
		$count += ( null !== $filters['min_price'] || null !== $filters['max_price'] ) ? 1 : 0;

		foreach ( $filters['attributes'] as $terms ) {
			$count += count( $terms );
		}

		return $count;
	}

	/**
	 * نمایش کل پنل فیلتر.
	 */
	public function render() {
		if ( ! psf_is_filterable_archive() || $this->rendered ) {
			return;
		}

		$this->rendered = true;

		$settings = PSF_Settings::instance();
		$active   = $this->count_active();
		$open     = $settings->get( 'open_by_default' ) ? ' is-open' : '';
		$object   = get_queried_object();
		$category = ( is_product_category() && $object instanceof WP_Term ) ? $object->slug : '';
		?>
		<div class="psf" id="psf-panel-wrap" data-psf-root data-category="<?php echo esc_attr( $category ); ?>">
			<form class="psf-form" method="get" action="<?php echo esc_url( $this->base_url() ); ?>" data-psf-form>
				<?php $this->hidden_context_fields(); ?>

				<div class="psf-bar">
					<?php if ( $settings->enabled( 'search' ) ) : ?>
						<?php $this->render_search_field(); ?>
					<?php endif; ?>

					<button type="button" class="psf-toggle" data-psf-toggle aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="psf-panel">
						<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
							<line x1="3" y1="6" x2="21" y2="6"></line>
							<line x1="7" y1="12" x2="17" y2="12"></line>
							<line x1="10" y1="18" x2="14" y2="18"></line>
						</svg>
						<span><?php esc_html_e( 'فیلترها', 'parsian-shop-filters' ); ?></span>
						<?php if ( $active ) : ?>
							<span class="psf-badge"><?php echo esc_html( psf_digits( $active ) ); ?></span>
						<?php endif; ?>
					</button>
				</div>

				<?php $this->render_chips(); ?>

				<div class="psf-panel<?php echo esc_attr( $open ); ?>" id="psf-panel" data-psf-panel>
					<div class="psf-panel-head">
						<h2 class="psf-panel-title"><?php esc_html_e( 'فیلتر محصولات', 'parsian-shop-filters' ); ?></h2>
						<button type="button" class="psf-close" data-psf-close aria-label="<?php esc_attr_e( 'بستن فیلترها', 'parsian-shop-filters' ); ?>">✕</button>
					</div>

					<div class="psf-facets">
						<?php
						if ( $settings->enabled( 'categories' ) ) {
							$this->render_categories_facet();
						}

						if ( $settings->enabled( 'price' ) ) {
							$this->render_price_facet();
						}

						if ( $settings->enabled( 'attributes' ) ) {
							$this->render_attribute_facets();
						}

						$this->render_toggles();
						?>
					</div>

					<div class="psf-actions">
						<button type="submit" class="psf-apply"><?php esc_html_e( 'اعمال فیلتر', 'parsian-shop-filters' ); ?></button>
						<?php if ( PSF_Query::has_active_filters() ) : ?>
							<a class="psf-reset" href="<?php echo esc_url( $this->build_url( array() ) ); ?>"><?php esc_html_e( 'حذف همهٔ فیلترها', 'parsian-shop-filters' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</form>

			<div class="psf-overlay" data-psf-overlay hidden></div>
		</div>
		<?php
	}

	/**
	 * کادر جستجوی درون‌بایگانی به‌همراه پیشنهاد زنده.
	 */
	protected function render_search_field() {
		$filters     = PSF_Query::active_filters();
		$placeholder = is_product_taxonomy() && get_queried_object() instanceof WP_Term
			/* translators: %s: نام دسته‌بندی جاری. */
			? sprintf( __( 'جستجو در %s…', 'parsian-shop-filters' ), get_queried_object()->name )
			: __( 'جستجوی کارتن، جعبه، ملزومات بسته‌بندی…', 'parsian-shop-filters' );
		?>
		<div class="psf-search" data-psf-search>
			<svg class="psf-search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
				<circle cx="11" cy="11" r="7"></circle>
				<line x1="21" y1="21" x2="16.5" y2="16.5"></line>
			</svg>
			<input type="search"
				name="psf_q"
				class="psf-search-input"
				value="<?php echo esc_attr( $filters['keyword'] ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				autocomplete="off"
				aria-label="<?php esc_attr_e( 'جستجوی محصول', 'parsian-shop-filters' ); ?>"
				data-psf-search-input>
			<?php if ( '' !== $filters['keyword'] ) : ?>
				<a class="psf-search-clear" href="<?php echo esc_url( $this->url_without( 'psf_q' ) ); ?>" aria-label="<?php esc_attr_e( 'پاک کردن جستجو', 'parsian-shop-filters' ); ?>">✕</a>
			<?php endif; ?>
			<button type="submit" class="psf-search-submit"><?php esc_html_e( 'جستجو', 'parsian-shop-filters' ); ?></button>
			<div class="psf-suggest" data-psf-suggest hidden></div>
		</div>
		<?php
	}

	/**
	 * نمایش تراشه‌های فیلترهای فعال.
	 */
	protected function render_chips() {
		if ( ! PSF_Query::has_active_filters() ) {
			return;
		}

		$filters = PSF_Query::active_filters();
		$chips   = array();

		if ( '' !== $filters['keyword'] ) {
			$chips[] = array(
				/* translators: %s: عبارت جستجوشده. */
				'label' => sprintf( __( 'جستجو: %s', 'parsian-shop-filters' ), $filters['keyword'] ),
				'url'   => $this->url_without( 'psf_q' ),
			);
		}

		foreach ( $filters['categories'] as $slug ) {
			$term    = get_term_by( 'slug', $slug, 'product_cat' );
			$chips[] = array(
				'label' => $term ? $term->name : $slug,
				'url'   => $this->url_without( 'psf_cat', $slug ),
			);
		}

		foreach ( $filters['attributes'] as $taxonomy => $slugs ) {
			foreach ( $slugs as $slug ) {
				$term    = get_term_by( 'slug', $slug, $taxonomy );
				$chips[] = array(
					'label' => $term ? $term->name : $slug,
					'url'   => $this->url_without( psf_attribute_param( $taxonomy ), $slug ),
				);
			}
		}

		if ( null !== $filters['min_price'] || null !== $filters['max_price'] ) {
			$bounds = $this->price_bounds();
			$min    = null !== $filters['min_price'] ? $filters['min_price'] : $bounds['min'];
			$max    = null !== $filters['max_price'] ? $filters['max_price'] : $bounds['max'];

			$chips[] = array(
				'label' => sprintf(
					/* translators: 1: کمینهٔ قیمت، 2: بیشینهٔ قیمت. */
					__( 'قیمت: %1$s تا %2$s تومان', 'parsian-shop-filters' ),
					psf_format_price( $min ),
					psf_format_price( $max )
				),
				'url'   => $this->build_url( $this->current_query_args( array( 'psf_min', 'psf_max' ) ) ),
			);
		}

		if ( $filters['stock'] ) {
			$chips[] = array(
				'label' => __( 'فقط کالاهای موجود', 'parsian-shop-filters' ),
				'url'   => $this->url_without( 'psf_stock' ),
			);
		}

		if ( $filters['sale'] ) {
			$chips[] = array(
				'label' => __( 'فقط حراج', 'parsian-shop-filters' ),
				'url'   => $this->url_without( 'psf_sale' ),
			);
		}

		if ( ! $chips ) {
			return;
		}
		?>
		<div class="psf-chips" data-psf-chips>
			<span class="psf-chips-label"><?php esc_html_e( 'فیلترهای فعال:', 'parsian-shop-filters' ); ?></span>
			<?php foreach ( $chips as $chip ) : ?>
				<a class="psf-chip" href="<?php echo esc_url( $chip['url'] ); ?>">
					<span><?php echo esc_html( $chip['label'] ); ?></span>
					<span class="psf-chip-x" aria-hidden="true">✕</span>
				</a>
			<?php endforeach; ?>
			<a class="psf-chip psf-chip-clear" href="<?php echo esc_url( $this->build_url( array() ) ); ?>">
				<?php esc_html_e( 'حذف همه', 'parsian-shop-filters' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * فیلتر دسته‌بندی — در فروشگاه دسته‌های سطح اول، در یک دسته زیردسته‌ها.
	 */
	protected function render_categories_facet() {
		$object = get_queried_object();
		$parent = ( is_product_category() && $object instanceof WP_Term ) ? (int) $object->term_id : 0;

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => $parent,
			)
		);

		// دسته‌ای بدون زیردسته: دسته‌های هم‌سطح نمایش داده می‌شوند تا جابه‌جایی ممکن باشد.
		$siblings = false;
		if ( ( ! $terms || is_wp_error( $terms ) ) && $parent && $object instanceof WP_Term ) {
			$siblings = true;
			$terms    = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'parent'     => (int) $object->parent,
					'exclude'    => array( $parent ),
				)
			);
		}

		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}

		$chosen = PSF_Query::active_filters()['categories'];
		$title  = $siblings ? __( 'دسته‌بندی‌های دیگر', 'parsian-shop-filters' ) : __( 'دسته‌بندی', 'parsian-shop-filters' );

		if ( $siblings ) {
			// در حالت هم‌سطح، انتخاب دسته باید کاربر را به آن بایگانی ببرد نه فیلتر کردن دستهٔ جاری.
			$this->open_facet( $title, 'psf-facet-cat' );
			echo '<ul class="psf-options psf-options-links">';
			foreach ( $terms as $term ) {
				printf(
					'<li><a class="psf-option-link" href="%1$s">%2$s<span class="psf-count">%3$s</span></a></li>',
					esc_url( get_term_link( $term ) ),
					esc_html( $term->name ),
					esc_html( psf_digits( $term->count ) )
				);
			}
			echo '</ul>';
			$this->close_facet();
			return;
		}

		$this->open_facet( $title, 'psf-facet-cat' );
		$this->render_checkbox_list( 'psf_cat[]', $terms, $chosen, 'product_cat' );
		$this->close_facet();
	}

	/**
	 * فیلتر بازهٔ قیمت.
	 */
	protected function render_price_facet() {
		$bounds = $this->price_bounds();

		if ( $bounds['max'] <= 0 || $bounds['max'] <= $bounds['min'] ) {
			return;
		}

		$filters = PSF_Query::active_filters();
		$min     = null !== $filters['min_price'] ? $filters['min_price'] : $bounds['min'];
		$max     = null !== $filters['max_price'] ? $filters['max_price'] : $bounds['max'];

		$this->open_facet( __( 'بازهٔ قیمت (تومان)', 'parsian-shop-filters' ), 'psf-facet-price' );
		?>
		<div class="psf-price" data-psf-price
			data-min="<?php echo esc_attr( $bounds['min'] ); ?>"
			data-max="<?php echo esc_attr( $bounds['max'] ); ?>">

			<div class="psf-price-inputs">
				<label class="psf-price-field">
					<span><?php esc_html_e( 'از', 'parsian-shop-filters' ); ?></span>
					<input type="text" inputmode="numeric" name="psf_min"
						value="<?php echo esc_attr( null !== $filters['min_price'] ? (int) $filters['min_price'] : '' ); ?>"
						placeholder="<?php echo esc_attr( number_format( $bounds['min'], 0, '.', ',' ) ); ?>"
						data-psf-price-min>
				</label>
				<span class="psf-price-sep">—</span>
				<label class="psf-price-field">
					<span><?php esc_html_e( 'تا', 'parsian-shop-filters' ); ?></span>
					<input type="text" inputmode="numeric" name="psf_max"
						value="<?php echo esc_attr( null !== $filters['max_price'] ? (int) $filters['max_price'] : '' ); ?>"
						placeholder="<?php echo esc_attr( number_format( $bounds['max'], 0, '.', ',' ) ); ?>"
						data-psf-price-max>
				</label>
			</div>

			<div class="psf-slider" data-psf-slider aria-hidden="true">
				<div class="psf-slider-track"></div>
				<div class="psf-slider-range" data-psf-slider-range></div>
				<input type="range" class="psf-slider-handle" data-psf-slider-lo
					min="<?php echo esc_attr( $bounds['min'] ); ?>"
					max="<?php echo esc_attr( $bounds['max'] ); ?>"
					value="<?php echo esc_attr( $min ); ?>"
					step="1000" tabindex="-1"
					aria-label="<?php esc_attr_e( 'کمینهٔ قیمت', 'parsian-shop-filters' ); ?>">
				<input type="range" class="psf-slider-handle" data-psf-slider-hi
					min="<?php echo esc_attr( $bounds['min'] ); ?>"
					max="<?php echo esc_attr( $bounds['max'] ); ?>"
					value="<?php echo esc_attr( $max ); ?>"
					step="1000" tabindex="-1"
					aria-label="<?php esc_attr_e( 'بیشینهٔ قیمت', 'parsian-shop-filters' ); ?>">
			</div>

			<p class="psf-price-hint">
				<?php
				printf(
					/* translators: 1: کمترین قیمت، 2: بیشترین قیمت. */
					esc_html__( 'قیمت محصولات این بخش از %1$s تا %2$s تومان است.', 'parsian-shop-filters' ),
					esc_html( psf_format_price( $bounds['min'] ) ),
					esc_html( psf_format_price( $bounds['max'] ) )
				);
				?>
			</p>
		</div>
		<?php
		$this->close_facet();
	}

	/**
	 * فیلتر ویژگی‌های محصول.
	 */
	protected function render_attribute_facets() {
		$filters = PSF_Query::active_filters();

		foreach ( self::filterable_attribute_taxonomies() as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);

			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}

			$labels = wc_attribute_label( $taxonomy );
			$chosen = isset( $filters['attributes'][ $taxonomy ] ) ? $filters['attributes'][ $taxonomy ] : array();

			$this->open_facet( $labels, 'psf-facet-attr psf-facet-' . esc_attr( $taxonomy ) );
			$this->render_checkbox_list( psf_attribute_param( $taxonomy ) . '[]', $terms, $chosen, $taxonomy );
			$this->close_facet();
		}
	}

	/**
	 * کلیدهای موجودی و حراج.
	 */
	protected function render_toggles() {
		$settings = PSF_Settings::instance();

		if ( ! $settings->enabled( 'stock' ) && ! $settings->enabled( 'sale' ) ) {
			return;
		}

		$filters = PSF_Query::active_filters();

		$this->open_facet( __( 'وضعیت', 'parsian-shop-filters' ), 'psf-facet-status' );
		echo '<ul class="psf-options">';

		if ( $settings->enabled( 'stock' ) ) {
			printf(
				'<li><label class="psf-option"><input type="checkbox" name="psf_stock" value="1" %1$s><span class="psf-option-label">%2$s</span></label></li>',
				checked( $filters['stock'], true, false ),
				esc_html__( 'فقط کالاهای موجود', 'parsian-shop-filters' )
			);
		}

		if ( $settings->enabled( 'sale' ) ) {
			printf(
				'<li><label class="psf-option"><input type="checkbox" name="psf_sale" value="1" %1$s><span class="psf-option-label">%2$s</span></label></li>',
				checked( $filters['sale'], true, false ),
				esc_html__( 'فقط محصولات حراج', 'parsian-shop-filters' )
			);
		}

		echo '</ul>';
		$this->close_facet();
	}

	/**
	 * فهرست چک‌باکسی ترم‌ها.
	 *
	 * @param string    $name     نام فیلد.
	 * @param WP_Term[] $terms    ترم‌ها.
	 * @param string[]  $chosen   اسلاگ‌های انتخاب‌شده.
	 * @param string    $taxonomy تاکسونومی (برای شمارش).
	 */
	protected function render_checkbox_list( $name, $terms, $chosen, $taxonomy ) {
		$term_ids = wp_list_pluck( $terms, 'term_id' );
		$counts   = $this->term_counts( $taxonomy, $term_ids );
		$many     = count( $terms ) > 8;

		printf( '<ul class="psf-options%s" data-psf-options>', $many ? ' psf-options-scroll' : '' );

		foreach ( $terms as $term ) {
			$count    = isset( $counts[ $term->term_id ] ) ? (int) $counts[ $term->term_id ] : (int) $term->count;
			$selected = in_array( $term->slug, $chosen, true );

			// ترم‌های بدون نتیجه پنهان نمی‌شوند، فقط غیرفعال می‌شوند تا فهرست پرش نکند.
			printf(
				'<li><label class="psf-option%1$s"><input type="checkbox" name="%2$s" value="%3$s" %4$s %5$s><span class="psf-option-label">%6$s</span><span class="psf-count">%7$s</span></label></li>',
				( 0 === $count && ! $selected ) ? ' is-empty' : '',
				esc_attr( $name ),
				esc_attr( $term->slug ),
				checked( $selected, true, false ),
				( 0 === $count && ! $selected ) ? 'disabled' : '',
				esc_html( $term->name ),
				esc_html( psf_digits( $count ) )
			);
		}

		echo '</ul>';
	}

	/**
	 * شروع یک بخش فیلتر.
	 *
	 * @param string $title عنوان.
	 * @param string $class کلاس اضافی.
	 */
	protected function open_facet( $title, $class = '' ) {
		printf(
			'<section class="psf-facet %1$s"><h3 class="psf-facet-title">%2$s</h3><div class="psf-facet-body">',
			esc_attr( $class ),
			esc_html( $title )
		);
	}

	/**
	 * پایان یک بخش فیلتر.
	 */
	protected function close_facet() {
		echo '</div></section>';
	}

	/**
	 * پیام جایگزین وقتی هیچ محصولی با فیلترهای فعلی پیدا نشد.
	 */
	public function render_empty_state() {
		if ( ! psf_is_filterable_archive() || ! PSF_Query::has_active_filters() ) {
			return;
		}

		// پیام پیش‌فرض ووکامرس جای خود را به این کادر می‌دهد تا دو پیام هم‌زمان نباشد.
		remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
		?>
		<div class="psf-empty">
			<span class="psf-empty-icon" aria-hidden="true">🔍</span>
			<p class="psf-empty-title"><?php esc_html_e( 'با این فیلترها محصولی پیدا نشد.', 'parsian-shop-filters' ); ?></p>
			<p class="psf-empty-text"><?php esc_html_e( 'می‌توانید بازهٔ قیمت را بازتر کنید یا چند فیلتر را بردارید.', 'parsian-shop-filters' ); ?></p>
			<a class="psf-empty-reset" href="<?php echo esc_url( $this->build_url( array() ) ); ?>">
				<?php esc_html_e( 'حذف همهٔ فیلترها', 'parsian-shop-filters' ); ?>
			</a>
		</div>
		<?php
	}
}
