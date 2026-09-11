<?php
/**
 * اعمال فیلترها روی پرس‌وجوی محصولات.
 *
 * همهٔ فیلترها (جستجوی درون‌بایگانی، دسته‌بندی، ویژگی‌ها، بازهٔ قیمت، موجودی و
 * حراج) در همین کلاس اعمال می‌شوند. عمداً از پارامترهای بومی ووکامرس
 * (`filter_pa_*`) استفاده نشده است: آن‌ها فقط رشتهٔ جداشده با کاما را می‌پذیرند و
 * بدون جاوااسکریپت با چک‌باکس ساخته نمی‌شوند.
 *
 * بازهٔ قیمت عمداً از پیاده‌سازی بومی ووکامرس (`min_price`/`max_price`) استفاده
 * نمی‌کند: قالب قیمت‌ها را به ریال ذخیره و به تومان نمایش می‌دهد، و آدرس فیلتر
 * باید همان عددی را نشان دهد که کاربر روی کارت محصول می‌بیند. پارامترهای
 * `psf_min` و `psf_max` به تومان‌اند و هنگام ساخت پرس‌وجو به ریال تبدیل می‌شوند.
 *
 * @package parsian-shop-filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * لایهٔ پرس‌وجو.
 */
class PSF_Query {

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PSF_Query|null
	 */
	protected static $instance = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PSF_Query
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
		add_action( 'woocommerce_product_query', array( $this, 'apply_filters_to_query' ), 20 );
		add_filter( 'posts_clauses', array( $this, 'apply_price_clauses' ), 20, 2 );
		add_filter( 'woocommerce_catalog_orderby', array( $this, 'add_orderby_options' ) );
		add_filter( 'woocommerce_default_catalog_orderby', array( $this, 'keep_relevance_default' ) );
		add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'relevance_ordering_args' ) );
	}

	/**
	 * پارامترهای فیلتر فعال در درخواست جاری.
	 *
	 * @return array
	 */
	public static function active_filters() {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- پارامترهای عمومی و فقط-خواندنی.
		$keyword = isset( $_GET['psf_q'] ) ? sanitize_text_field( wp_unslash( $_GET['psf_q'] ) ) : '';

		$attributes = array();
		foreach ( PSF_Render::filterable_attribute_taxonomies() as $taxonomy ) {
			$chosen = psf_get_array_param( psf_attribute_param( $taxonomy ) );
			if ( $chosen ) {
				$attributes[ $taxonomy ] = $chosen;
			}
		}

		$cache = array(
			'keyword'    => $keyword,
			'categories' => psf_get_array_param( 'psf_cat' ),
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'stock'      => ! empty( $_GET['psf_stock'] ),
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'sale'       => ! empty( $_GET['psf_sale'] ),
			'min_price'  => psf_get_number_param( 'psf_min' ),
			'max_price'  => psf_get_number_param( 'psf_max' ),
			'attributes' => $attributes,
		);

		return $cache;
	}

	/**
	 * آیا دست‌کم یک فیلتر فعال است؟
	 *
	 * @return bool
	 */
	public static function has_active_filters() {
		$filters = self::active_filters();

		return '' !== $filters['keyword']
			|| ! empty( $filters['categories'] )
			|| $filters['stock']
			|| $filters['sale']
			|| null !== $filters['min_price']
			|| null !== $filters['max_price']
			|| ! empty( $filters['attributes'] );
	}

	/**
	 * اعمال فیلترهای سفارشی روی پرس‌وجوی اصلی محصولات.
	 *
	 * @param WP_Query $query پرس‌وجوی محصولات.
	 */
	public function apply_filters_to_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$filters = self::active_filters();

		if ( '' !== $filters['keyword'] ) {
			$query->set( 's', $filters['keyword'] );
		}

		if ( ! empty( $filters['categories'] ) ) {
			$tax_query   = (array) $query->get( 'tax_query', array() );
			$tax_query[] = array(
				'taxonomy'         => 'product_cat',
				'field'            => 'slug',
				'terms'            => $filters['categories'],
				'operator'         => 'IN',
				'include_children' => true,
			);
			$query->set( 'tax_query', $tax_query );
		}

		if ( ! empty( $filters['attributes'] ) ) {
			$tax_query = (array) $query->get( 'tax_query', array() );

			foreach ( $filters['attributes'] as $taxonomy => $terms ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $terms,
					'operator' => 'IN',
				);
			}

			$query->set( 'tax_query', $tax_query );
		}

		if ( $filters['stock'] ) {
			$meta_query   = (array) $query->get( 'meta_query', array() );
			$meta_query[] = array(
				'key'     => '_stock_status',
				'value'   => 'instock',
				'compare' => '=',
			);
			$query->set( 'meta_query', $meta_query );
		}

		if ( $filters['sale'] ) {
			$on_sale  = wc_get_product_ids_on_sale();
			$post__in = (array) $query->get( 'post__in', array() );
			$on_sale  = $on_sale ? $on_sale : array( 0 );

			// آرایهٔ خالی در post__in برای وردپرس بی‌معناست، پس همیشه حداقل یک شناسه می‌ماند.
			$merged = $post__in ? array_intersect( $post__in, $on_sale ) : $on_sale;
			$query->set( 'post__in', $merged ? array_values( $merged ) : array( 0 ) );
		}
	}

	/**
	 * محدود کردن نتایج به بازهٔ قیمت انتخابی.
	 *
	 * از جدول `wc_product_meta_lookup` استفاده می‌شود تا محصولات متغیر هم درست
	 * فیلتر شوند (همان روشی که خود ووکامرس برای فیلتر قیمت به‌کار می‌برد).
	 *
	 * @param array    $clauses  بخش‌های پرس‌وجو.
	 * @param WP_Query $wp_query پرس‌وجوی جاری.
	 * @return array
	 */
	public function apply_price_clauses( $clauses, $wp_query ) {
		global $wpdb;

		if ( is_admin() || ! $wp_query->is_main_query() || 'product_query' !== $wp_query->get( 'wc_query' ) ) {
			return $clauses;
		}

		$filters = self::active_filters();

		if ( null === $filters['min_price'] && null === $filters['max_price'] ) {
			return $clauses;
		}

		$min = null === $filters['min_price'] ? 0 : psf_to_raw_price( max( 0, $filters['min_price'] ) );
		$max = null === $filters['max_price'] ? PHP_INT_MAX : psf_to_raw_price( max( 0, $filters['max_price'] ) );

		if ( $max < $min ) {
			list( $min, $max ) = array( $max, $min );
		}

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		if ( false === strpos( $clauses['join'], $lookup ) ) {
			$clauses['join'] .= " INNER JOIN {$lookup} ON {$wpdb->posts}.ID = {$lookup}.product_id ";
		}

		// بازهٔ قیمت محصول باید با بازهٔ انتخابی هم‌پوشانی داشته باشد.
		$clauses['where'] .= $wpdb->prepare(
			" AND NOT ( %f < {$lookup}.min_price OR %f > {$lookup}.max_price ) ", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$max,
			$min
		);

		return $clauses;
	}

	/**
	 * افزودن گزینهٔ «مرتبط‌ترین» به منوی مرتب‌سازی هنگام جستجو.
	 *
	 * @param array $options گزینه‌های موجود.
	 * @return array
	 */
	public function add_orderby_options( $options ) {
		$filters = self::active_filters();

		if ( '' === $filters['keyword'] ) {
			return $options;
		}

		return array_merge(
			array( 'relevance' => __( 'مرتبط‌ترین با جستجو', 'parsian-shop-filters' ) ),
			$options
		);
	}

	/**
	 * هنگام جستجو، ترتیب پیش‌فرض «مرتبط‌ترین» است.
	 *
	 * @param string $default مقدار پیش‌فرض فروشگاه.
	 * @return string
	 */
	public function keep_relevance_default( $default ) {
		$filters = self::active_filters();

		return '' !== $filters['keyword'] ? 'relevance' : $default;
	}

	/**
	 * ترتیب «مرتبط‌ترین» را به ترتیب پیش‌فرض جستجوی وردپرس واگذار می‌کند.
	 *
	 * @param array $args آرگومان‌های ترتیب.
	 * @return array
	 */
	public function relevance_ordering_args( $args ) {
		$filters = self::active_filters();

		if ( '' === $filters['keyword'] ) {
			return $args;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_title( wp_unslash( $_GET['orderby'] ) ) : '';

		// فقط وقتی کاربر ترتیب دیگری انتخاب نکرده باشد.
		if ( '' === $orderby || 'relevance' === $orderby ) {
			$args['orderby']  = 'relevance';
			$args['order']    = 'DESC';
			$args['meta_key'] = ''; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}

		return $args;
	}
}
