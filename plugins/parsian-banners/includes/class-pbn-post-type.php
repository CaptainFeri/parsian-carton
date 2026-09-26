<?php
/**
 * نوع نوشتهٔ «بنر».
 *
 * از رابط استاندارد نوشته‌های وردپرس استفاده می‌کند — همان صفحه‌ای که مدیر سایت
 * با آن آشناست: فهرست، کشیدن و رها کردن برای ترتیب، پیش‌نویس/انتشار برای
 * روشن و خاموش کردن، و تصویر شاخص برای تصویر بنر.
 *
 * @package parsian-banners
 */

defined( 'ABSPATH' ) || exit;

/**
 * ثبت نوع نوشته.
 */
class PBN_Post_Type {

	const POST_TYPE = 'parsian_banner';

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );

		// جعبهٔ «تصویر شاخص» فقط وقتی دیده می‌شود که قالب هم post-thumbnails را
		// پشتیبانی کند. قالب فعلی می‌کند، ولی افزونه نباید به آن بند باشد —
		// با عوض شدن قالب، بنرها تصویرشان را از دست می‌دادند.
		add_action( 'after_setup_theme', array( __CLASS__, 'ensure_thumbnail_support' ), 20 );

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );

		// ترتیب فهرست پیشخوان باید همان ترتیب نمایش در سایت باشد.
		add_action( 'pre_get_posts', array( __CLASS__, 'order_admin_list' ) );
	}

	/**
	 * ثبت نوع نوشته.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'بنرها', 'parsian-banners' ),
					'singular_name'      => __( 'بنر', 'parsian-banners' ),
					'menu_name'          => __( 'بنرها', 'parsian-banners' ),
					'add_new'            => __( 'بنر تازه', 'parsian-banners' ),
					'add_new_item'       => __( 'افزودن بنر تازه', 'parsian-banners' ),
					'edit_item'          => __( 'ویرایش بنر', 'parsian-banners' ),
					'new_item'           => __( 'بنر تازه', 'parsian-banners' ),
					'view_item'          => __( 'دیدن بنر', 'parsian-banners' ),
					'search_items'       => __( 'جستجوی بنر', 'parsian-banners' ),
					'not_found'          => __( 'هنوز بنری ساخته نشده است.', 'parsian-banners' ),
					'not_found_in_trash' => __( 'بنری در زباله‌دان نیست.', 'parsian-banners' ),
					'featured_image'     => __( 'تصویر بنر', 'parsian-banners' ),
					'set_featured_image' => __( 'انتخاب تصویر بنر', 'parsian-banners' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-images-alt2',
				'menu_position'   => 25,
				'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
				'capability_type' => 'page',
				'map_meta_cap'    => true,
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => false,
			)
		);
	}

	/**
	 * اطمینان از اینکه نوع نوشتهٔ بنر تصویر شاخص می‌پذیرد.
	 */
	public static function ensure_thumbnail_support() {
		$support = get_theme_support( 'post-thumbnails' );

		// true یعنی قالب برای همهٔ انواع فعال کرده؛ کاری لازم نیست.
		if ( true === $support ) {
			return;
		}

		$types = ( is_array( $support ) && isset( $support[0] ) && is_array( $support[0] ) ) ? $support[0] : array();

		if ( in_array( self::POST_TYPE, $types, true ) ) {
			return;
		}

		$types[] = self::POST_TYPE;

		add_theme_support( 'post-thumbnails', $types );
	}

	/**
	 * ستون‌های فهرست بنرها.
	 *
	 * @param array $columns ستون‌ها.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'        => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'pbn_thumb' => __( 'تصویر', 'parsian-banners' ),
			'title'     => __( 'عنوان', 'parsian-banners' ),
			'pbn_order' => __( 'ترتیب', 'parsian-banners' ),
			'pbn_where' => __( 'نمایش در', 'parsian-banners' ),
			'pbn_when'  => __( 'بازهٔ نمایش', 'parsian-banners' ),
			'pbn_state' => __( 'وضعیت', 'parsian-banners' ),
			'date'      => __( 'تاریخ', 'parsian-banners' ),
		);
	}

	/**
	 * محتوای ستون‌ها.
	 *
	 * @param string $column  نام ستون.
	 * @param int    $post_id شناسهٔ بنر.
	 */
	public static function column( $column, $post_id ) {
		$banner = new PBN_Banner( $post_id );

		switch ( $column ) {
			case 'pbn_thumb':
				$url = $banner->get_image_url();

				if ( $url ) {
					printf(
						'<img src="%s" alt="" style="width:88px;height:52px;object-fit:cover;border-radius:6px;">',
						esc_url( $url )
					);
				} else {
					echo '<span class="pbn-muted">—</span>';
				}
				break;

			case 'pbn_order':
				echo esc_html( pbn_digits( $banner->get_order() ) );
				break;

			case 'pbn_where':
				$devices = array(
					'all'     => __( 'موبایل و دسکتاپ', 'parsian-banners' ),
					'desktop' => __( 'فقط دسکتاپ', 'parsian-banners' ),
					'mobile'  => __( 'فقط موبایل', 'parsian-banners' ),
				);
				$device  = $banner->get_device();

				echo esc_html( isset( $devices[ $device ] ) ? $devices[ $device ] : $device );
				break;

			case 'pbn_when':
				$start = (string) $banner->get( 'start_date' );
				$end   = (string) $banner->get( 'end_date' );

				if ( '' === $start && '' === $end ) {
					echo '<span class="pbn-muted">' . esc_html__( 'همیشه', 'parsian-banners' ) . '</span>';
					break;
				}

				echo esc_html(
					sprintf(
						'%s — %s',
						'' !== $start ? pbn_jalali_date( $start ) : '…',
						'' !== $end ? pbn_jalali_date( $end ) : '…'
					)
				);
				break;

			case 'pbn_state':
				$post = get_post( $post_id );

				if ( ! $post || 'publish' !== $post->post_status ) {
					echo '<span class="pbn-badge pbn-badge-off">' . esc_html__( 'خاموش', 'parsian-banners' ) . '</span>';
					break;
				}

				if ( ! $banner->is_in_window() ) {
					echo '<span class="pbn-badge pbn-badge-wait">' . esc_html__( 'خارج از بازه', 'parsian-banners' ) . '</span>';
					break;
				}

				echo '<span class="pbn-badge pbn-badge-on">' . esc_html__( 'در حال نمایش', 'parsian-banners' ) . '</span>';
				break;
		}
	}

	/**
	 * مرتب‌سازی فهرست پیشخوان بر پایهٔ ترتیب نمایش.
	 *
	 * @param WP_Query $query پرس‌وجو.
	 */
	public static function order_admin_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	}
}
