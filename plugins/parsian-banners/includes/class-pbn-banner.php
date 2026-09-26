<?php
/**
 * مدل یک بنر.
 *
 * همهٔ خواندن و نوشتن متای بنر از همین‌جا می‌گذرد تا نام کلیدها یک جا بماند.
 *
 * @package parsian-banners
 */

defined( 'ABSPATH' ) || exit;

/**
 * بنر.
 */
class PBN_Banner {

	/**
	 * شناسهٔ نوشته.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * نگاشت نام فیلد به کلید متا.
	 *
	 * @var array<string,string>
	 */
	protected static $meta_map = array(
		'eyebrow'      => '_pbn_eyebrow',
		'description'  => '_pbn_description',
		'features'     => '_pbn_features',
		'button_text'  => '_pbn_button_text',
		'button_url'   => '_pbn_button_url',
		'button2_text' => '_pbn_button2_text',
		'button2_url'  => '_pbn_button2_url',
		'mobile_image' => '_pbn_mobile_image',
		'scheme'       => '_pbn_scheme',
		'overlay'      => '_pbn_overlay',
		'start_date'   => '_pbn_start',
		'end_date'     => '_pbn_end',
		'device'       => '_pbn_device',
		'location'     => '_pbn_location',
	);

	/**
	 * سازنده.
	 *
	 * @param int|WP_Post $banner شناسه یا نوشته.
	 */
	public function __construct( $banner ) {
		$this->id = ( $banner instanceof WP_Post ) ? (int) $banner->ID : (int) $banner;
	}

	/**
	 * فهرست کلیدهای متا.
	 *
	 * @return array<string,string>
	 */
	public static function meta_map() {
		return self::$meta_map;
	}

	/**
	 * شناسه.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * خواندن یک فیلد.
	 *
	 * @param string $field   نام فیلد.
	 * @param mixed  $default مقدار جایگزین.
	 * @return mixed
	 */
	public function get( $field, $default = '' ) {
		if ( ! isset( self::$meta_map[ $field ] ) ) {
			return $default;
		}

		$value = get_post_meta( $this->id, self::$meta_map[ $field ], true );

		return ( '' === $value || null === $value ) ? $default : $value;
	}

	/**
	 * نوشتن یک فیلد.
	 *
	 * @param string $field نام فیلد.
	 * @param mixed  $value مقدار.
	 */
	public function set( $field, $value ) {
		if ( isset( self::$meta_map[ $field ] ) ) {
			update_post_meta( $this->id, self::$meta_map[ $field ], $value );
		}
	}

	/**
	 * عنوان بنر.
	 *
	 * @return string
	 */
	public function get_title() {
		return (string) get_the_title( $this->id );
	}

	/**
	 * ترتیب نمایش.
	 *
	 * @return int
	 */
	public function get_order() {
		$post = get_post( $this->id );

		return $post ? (int) $post->menu_order : 0;
	}

	/**
	 * نشانی تصویر بنر — تصویر موبایل وقتی تعریف شده باشد ترجیح دارد.
	 *
	 * @param bool $mobile تصویر موبایل خواسته می‌شود؟
	 * @return string
	 */
	public function get_image_url( $mobile = false ) {
		if ( $mobile ) {
			$id = (int) $this->get( 'mobile_image', 0 );

			if ( $id ) {
				$url = wp_get_attachment_image_url( $id, 'large' );

				if ( $url ) {
					return $url;
				}
			}
		}

		$id = (int) get_post_thumbnail_id( $this->id );

		return $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';
	}

	/**
	 * سطرهای فهرست ویژگی‌ها.
	 *
	 * @return string[]
	 */
	public function get_features() {
		$raw = (string) $this->get( 'features' );

		if ( '' === trim( $raw ) ) {
			return array();
		}

		$lines = preg_split( '/[\r\n]+/', $raw );

		return array_values(
			array_filter(
				array_map( 'trim', (array) $lines ),
				static function ( $line ) {
					return '' !== $line;
				}
			)
		);
	}

	/**
	 * آیا بنر در بازهٔ زمانی‌اش است؟
	 *
	 * @return bool
	 */
	public function is_in_window() {
		$today = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
		$start = (string) $this->get( 'start_date' );
		$end   = (string) $this->get( 'end_date' );

		if ( '' !== $start && $today < $start ) {
			return false;
		}

		if ( '' !== $end && $today > $end ) {
			return false;
		}

		return true;
	}

	/**
	 * دستگاهی که بنر برایش نمایش داده می‌شود.
	 *
	 * @return string all | desktop | mobile
	 */
	public function get_device() {
		$device = (string) $this->get( 'device', 'all' );

		return in_array( $device, array( 'all', 'desktop', 'mobile' ), true ) ? $device : 'all';
	}

	/**
	 * جایگاه بنر.
	 *
	 * @return string
	 */
	public function get_location() {
		$location = (string) $this->get( 'location', 'home' );

		return '' === $location ? 'home' : $location;
	}

	/**
	 * طرح رنگی متن.
	 *
	 * @return string light | dark
	 */
	public function get_scheme() {
		return 'dark' === $this->get( 'scheme' ) ? 'dark' : 'light';
	}

	/**
	 * شدت لایهٔ تیرهٔ روی تصویر (۰ تا ۱۰۰).
	 *
	 * @return int
	 */
	public function get_overlay() {
		$overlay = $this->get( 'overlay', '' );

		if ( '' === $overlay ) {
			// بدون تصویر، لایهٔ تیره بی‌معناست.
			return $this->get_image_url() ? 45 : 0;
		}

		return min( 100, max( 0, (int) $overlay ) );
	}

	/* ------------------------------ پرس‌وجو ------------------------------ */

	/**
	 * بنرهای فعال یک جایگاه، به ترتیب.
	 *
	 * @param string $location جایگاه.
	 * @return PBN_Banner[]
	 */
	public static function active( $location = 'home' ) {
		$posts = get_posts(
			array(
				'post_type'      => PBN_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'no_found_rows'  => true,
			)
		);

		$banners = array();

		foreach ( $posts as $post ) {
			$banner = new self( $post );

			if ( $banner->get_location() !== $location || ! $banner->is_in_window() ) {
				continue;
			}

			$banners[] = $banner;
		}

		/**
		 * تغییر فهرست بنرهای نمایش‌داده‌شده.
		 *
		 * @param PBN_Banner[] $banners  بنرها.
		 * @param string       $location جایگاه.
		 */
		return (array) apply_filters( 'pbn_active_banners', $banners, $location );
	}

	/**
	 * همهٔ بنرها (حتی پیش‌نویس‌ها) — برای پیشخوان.
	 *
	 * @return PBN_Banner[]
	 */
	public static function all() {
		$posts = get_posts(
			array(
				'post_type'      => PBN_Post_Type::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'no_found_rows'  => true,
			)
		);

		return array_map(
			static function ( $post ) {
				return new self( $post );
			},
			$posts
		);
	}
}
