<?php
/**
 * نقطهٔ پایانی آژاکس برای پیشنهاد زندهٔ محصولات.
 *
 * به‌روزرسانی شبکهٔ محصولات هنگام تغییر فیلتر در سمت کلاینت و با واکشی همان
 * آدرس بایگانی انجام می‌شود، بنابراین اینجا فقط جستجوی زنده پیاده‌سازی شده است.
 *
 * @package parsian-shop-filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * جستجوی زنده.
 */
class PSF_Ajax {

	/**
	 * حداکثر تعداد پیشنهاد.
	 */
	const LIMIT = 6;

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PSF_Ajax|null
	 */
	protected static $instance = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PSF_Ajax
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
		add_action( 'wp_ajax_psf_suggest', array( $this, 'suggest' ) );
		add_action( 'wp_ajax_nopriv_psf_suggest', array( $this, 'suggest' ) );
	}

	/**
	 * پاسخ پیشنهادهای جستجو.
	 */
	public function suggest() {
		check_ajax_referer( 'psf_nonce', 'nonce' );

		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$term = trim( psf_normalize_digits( $term ) );

		if ( mb_strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'items' => array(), 'more' => '' ) );
		}

		$category = isset( $_POST['category'] ) ? sanitize_title( wp_unslash( $_POST['category'] ) ) : '';

		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => self::LIMIT,
			's'                   => $term,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'exclude-from-search',
					'operator' => 'NOT IN',
				),
			),
		);

		if ( $category ) {
			$args['tax_query'][] = array(
				'taxonomy'         => 'product_cat',
				'field'            => 'slug',
				'terms'            => $category,
				'include_children' => true,
			);
		}

		$query = new WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post );

			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}

			$items[] = array(
				'title' => $product->get_name(),
				'url'   => $product->get_permalink(),
				'price' => wp_strip_all_tags( $product->get_price_html() ),
				'image' => $this->thumbnail_url( $product ),
				'stock' => $product->is_in_stock(),
			);
		}

		wp_reset_postdata();

		wp_send_json_success(
			array(
				'items' => $items,
				'more'  => add_query_arg( 'psf_q', rawurlencode( $term ), PSF_Render::instance()->base_url() ),
			)
		);
	}

	/**
	 * آدرس بندانگشتی محصول.
	 *
	 * @param WC_Product $product محصول.
	 * @return string
	 */
	protected function thumbnail_url( $product ) {
		$id = $product->get_image_id();

		if ( ! $id ) {
			return wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' );
		}

		$src = wp_get_attachment_image_src( $id, 'woocommerce_gallery_thumbnail' );

		return $src ? $src[0] : wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' );
	}
}
