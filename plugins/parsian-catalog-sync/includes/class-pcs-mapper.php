<?php
/**
 * نگاشت سرستون‌های فایل اکسل به فیلدهای محصول ووکامرس و یکدست‌سازی مقادیر.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * نگاشت ستون‌ها.
 */
class PCS_Mapper {

	/**
	 * پیشوند ستون‌های ویژگی.
	 */
	const ATTRIBUTE_PREFIX = 'ویژگی:';

	/**
	 * نام‌های پذیرفته‌شده برای هر فیلد.
	 *
	 * کلیدها فیلد ووکامرس و مقادیر، نگارش‌های مجاز سرستون هستند. همهٔ مقایسه‌ها
	 * پس از عبور از PCS_Spreadsheet::normalize_header انجام می‌شود.
	 *
	 * @return array<string,string[]>
	 */
	public static function aliases() {
		$map = array(
			'sku'               => array( 'کد محصول', 'کد کالا', 'شناسه محصول', 'کد', 'sku' ),
			'name'              => array( 'نام محصول', 'نام', 'عنوان', 'عنوان محصول', 'name', 'title' ),
			'description'       => array( 'توضیحات', 'توضیح', 'شرح', 'توضیحات کامل', 'description' ),
			'short_description' => array( 'توضیح کوتاه', 'توضیحات کوتاه', 'خلاصه', 'short description' ),
			'regular_price'     => array( 'قیمت', 'قیمت اصلی', 'قیمت عادی', 'قیمت فروش', 'price', 'regular price' ),
			'sale_price'        => array( 'قیمت حراج', 'قیمت تخفیف', 'قیمت ویژه', 'sale price' ),
			'stock_quantity'    => array( 'موجودی', 'تعداد', 'انبار', 'تعداد موجودی', 'stock', 'quantity' ),
			'stock_status'      => array( 'وضعیت موجودی', 'وضعیت انبار', 'stock status' ),
			'categories'        => array( 'دسته بندی', 'دسته', 'دسته ها', 'گروه', 'category', 'categories' ),
			'tags'              => array( 'برچسب', 'برچسب ها', 'تگ', 'tags' ),
			'image'             => array( 'تصویر', 'عکس', 'تصویر شاخص', 'عکس اصلی', 'image' ),
			'gallery'           => array( 'گالری', 'تصاویر', 'عکس های بیشتر', 'gallery' ),
			'weight'            => array( 'وزن', 'weight' ),
			'length'            => array( 'طول', 'length' ),
			'width'             => array( 'عرض', 'width' ),
			'height'            => array( 'ارتفاع', 'height' ),
			'status'            => array( 'وضعیت', 'وضعیت انتشار', 'status' ),
			'featured'          => array( 'ویژه', 'محصول ویژه', 'featured' ),
			'menu_order'        => array( 'ترتیب', 'اولویت', 'order' ),
		);

		/**
		 * تغییر نام‌های پذیرفته‌شدهٔ ستون‌ها.
		 *
		 * @param array<string,string[]> $map نگاشت پیش‌فرض.
		 */
		return (array) apply_filters( 'pcs_column_aliases', $map );
	}

	/**
	 * ساخت نگاشت «سرستون فایل → فیلد» برای سرستون‌های یک فایل.
	 *
	 * @param string[] $headers سرستون‌های فایل.
	 * @return array{fields:array<string,string>,attributes:array<string,string>,unknown:string[]}
	 */
	public static function build( $headers ) {
		$lookup = array();

		foreach ( self::aliases() as $field => $names ) {
			foreach ( $names as $name ) {
				$lookup[ self::key( $name ) ] = $field;
			}
		}

		$fields     = array();
		$attributes = array();
		$unknown    = array();

		foreach ( $headers as $header ) {
			$normalized = PCS_Spreadsheet::normalize_header( $header );

			if ( 0 === mb_strpos( $normalized, self::ATTRIBUTE_PREFIX ) ) {
				$label = trim( mb_substr( $normalized, mb_strlen( self::ATTRIBUTE_PREFIX ) ) );
				if ( '' !== $label ) {
					$attributes[ $header ] = $label;
				}
				continue;
			}

			$key = self::key( $normalized );

			if ( isset( $lookup[ $key ] ) && ! in_array( $lookup[ $key ], $fields, true ) ) {
				$fields[ $header ] = $lookup[ $key ];
				continue;
			}

			$unknown[] = $header;
		}

		return array(
			'fields'     => $fields,
			'attributes' => $attributes,
			'unknown'    => $unknown,
		);
	}

	/**
	 * کلید مقایسهٔ سرستون — بی‌تفاوت به بزرگی/کوچکی حروف و فاصله.
	 *
	 * @param string $label برچسب.
	 * @return string
	 */
	protected static function key( $label ) {
		$label = PCS_Spreadsheet::normalize_header( $label );
		$label = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label, 'UTF-8' ) : strtolower( $label );

		return str_replace( array( ' ', '_', '-' ), '', $label );
	}

	/* ----------------------------- یکدست‌سازی مقادیر ----------------------------- */

	/**
	 * تبدیل ارقام فارسی/عربی به لاتین.
	 *
	 * @param string $text ورودی.
	 * @return string
	 */
	public static function latin_digits( $text ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( array_merge( $fa, $ar ), array_merge( $en, $en ), (string) $text );
	}

	/**
	 * خواندن یک عدد از سلول (با پشتیبانی از جداکنندهٔ هزارگان فارسی و لاتین).
	 *
	 * @param string $value مقدار سلول.
	 * @return float|null مقدار عددی یا null اگر سلول خالی/نامعتبر باشد.
	 */
	public static function number( $value ) {
		$value = trim( self::latin_digits( $value ) );

		if ( '' === $value ) {
			return null;
		}

		// جداکننده‌های هزارگان (٬ ، , فاصله) حذف و ممیز فارسی به نقطه تبدیل می‌شود.
		$value = str_replace( array( '٬', '،', ',', ' ', "\xC2\xA0", 'ریال', 'تومان' ), '', $value );
		$value = str_replace( '/', '.', $value );
		$value = preg_replace( '/[^0-9.\-]/u', '', $value );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		return (float) $value;
	}

	/**
	 * خواندن یک مقدار بله/خیر.
	 *
	 * @param string $value مقدار سلول.
	 * @return bool|null
	 */
	public static function boolean( $value ) {
		$value = mb_strtolower( trim( (string) $value ), 'UTF-8' );

		if ( '' === $value ) {
			return null;
		}

		if ( in_array( $value, array( 'بله', 'آری', 'دارد', 'فعال', 'yes', 'true', '1', 'y' ), true ) ) {
			return true;
		}

		if ( in_array( $value, array( 'خیر', 'نه', 'ندارد', 'غیرفعال', 'no', 'false', '0', 'n' ), true ) ) {
			return false;
		}

		return null;
	}

	/**
	 * وضعیت موجودی ووکامرس.
	 *
	 * @param string $value مقدار سلول.
	 * @return string|null instock | outofstock | onbackorder
	 */
	public static function stock_status( $value ) {
		$value = mb_strtolower( trim( (string) $value ), 'UTF-8' );

		if ( '' === $value ) {
			return null;
		}

		if ( in_array( $value, array( 'موجود', 'هست', 'در انبار', 'instock', 'in stock' ), true ) ) {
			return 'instock';
		}

		if ( in_array( $value, array( 'ناموجود', 'نیست', 'تمام شد', 'outofstock', 'out of stock' ), true ) ) {
			return 'outofstock';
		}

		if ( in_array( $value, array( 'پیش سفارش', 'پیش‌سفارش', 'onbackorder', 'backorder' ), true ) ) {
			return 'onbackorder';
		}

		return null;
	}

	/**
	 * وضعیت انتشار نوشته.
	 *
	 * @param string $value مقدار سلول.
	 * @return string|null publish | draft | private
	 */
	public static function post_status( $value ) {
		$value = mb_strtolower( trim( (string) $value ), 'UTF-8' );

		if ( '' === $value ) {
			return null;
		}

		if ( in_array( $value, array( 'منتشر', 'منتشر شده', 'انتشار', 'فعال', 'publish', 'published', 'active' ), true ) ) {
			return 'publish';
		}

		if ( in_array( $value, array( 'پیش نویس', 'پیش‌نویس', 'غیرفعال', 'draft', 'inactive' ), true ) ) {
			return 'draft';
		}

		if ( in_array( $value, array( 'خصوصی', 'private' ), true ) ) {
			return 'private';
		}

		return null;
	}

	/**
	 * شکستن یک سلول چندمقداری به آرایه.
	 *
	 * جداکننده‌های مجاز: کامای فارسی و لاتین، خط عمودی و خط جدید.
	 *
	 * @param string $value مقدار سلول.
	 * @return string[]
	 */
	public static function split( $value ) {
		$parts = preg_split( '/[,،|\r\n]+/u', (string) $value );

		if ( ! is_array( $parts ) ) {
			return array();
		}

		$parts = array_map( 'trim', $parts );

		return array_values( array_filter( $parts, static function ( $item ) {
			return '' !== $item;
		} ) );
	}
}
