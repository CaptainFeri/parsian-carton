<?php
/**
 * آماده‌سازی متن توضیحات محصول.
 *
 * دو کار انجام می‌دهد:
 *
 * ۱. **ترمیم:** در توضیحات این فروشگاه، رشتهٔ تحت‌اللفظی `\n` (بک‌اسلش + n) کنار
 *    خط جدید واقعی ذخیره شده است — نتیجهٔ یک درون‌ریزی قدیمی که کاراکتر فرار را
 *    دوبار escape کرده بود. وردپرس این‌ها را به‌صورت متن نمایش می‌دهد و در صفحهٔ
 *    محصول «n»های سرگردان دیده می‌شود.
 *
 * ۲. **تبدیل متن ساده به HTML:** اگر کسی در اکسل متن ساده بنویسد (بدون تگ)، خط
 *    جدیدها در HTML نادیده گرفته می‌شوند و همه‌چیز به هم می‌چسبد. اینجا پاراگراف و
 *    فهرست ساخته می‌شود تا همان چیزی که در اکسل دیده می‌شود در سایت هم دیده شود.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * پردازش متن توضیحات.
 */
class PCS_Content {

	/**
	 * آماده‌سازی کامل یک توضیح برای ذخیره.
	 *
	 * @param string $text متن خام سلول.
	 * @return string
	 */
	public static function prepare( $text ) {
		$text = self::repair( $text );

		return self::to_html( $text );
	}

	/**
	 * حذف دنباله‌های `\n` تحت‌اللفظی و مرتب کردن فاصله‌ها.
	 *
	 * @param string $text متن خام.
	 * @return string
	 */
	public static function repair( $text ) {
		$text = (string) $text;

		if ( '' === trim( $text ) ) {
			return '';
		}

		// «\n» و «\r\n» تحت‌اللفظی — چه تنها، چه چسبیده به خط جدید واقعی.
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/\\\\r\\\\n|\\\\n|\\\\r/', "\n", $text );

		// «\t» تحت‌اللفظی هم از همان درون‌ریزی مانده است.
		$text = preg_replace( '/\\\\t/', "\t", $text );

		// فاصله و تب انتهای هر خط.
		$text = preg_replace( '/[ \t]+$/m', '', $text );

		// بیش از دو خط خالی پشت‌سرهم، به یک خط خالی تبدیل می‌شود.
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		return trim( $text );
	}

	/**
	 * تبدیل متن ساده به HTML، با دست نزدن به متنی که از قبل HTML است.
	 *
	 * @param string $text متن ترمیم‌شده.
	 * @return string
	 */
	public static function to_html( $text ) {
		if ( '' === trim( $text ) ) {
			return '';
		}

		// متنی که تگ بلوکی دارد از قبل HTML است و فقط خطوط خالی اضافه‌اش پاک می‌شود.
		if ( self::has_block_html( $text ) ) {
			return self::tidy_html( $text );
		}

		$blocks = preg_split( '/\n{2,}/', $text );
		$html   = array();

		foreach ( $blocks as $block ) {
			$block = trim( $block );

			if ( '' === $block ) {
				continue;
			}

			$list = self::build_list( $block );

			if ( null !== $list ) {
				$html[] = $list;
				continue;
			}

			// خط جدید تکی داخل یک پاراگراف، شکست خط است.
			$html[] = '<p>' . str_replace( "\n", "<br>\n", esc_html( $block ) ) . '</p>';
		}

		return implode( "\n\n", $html );
	}

	/**
	 * ساخت فهرست از بلوکی که همهٔ خطوطش با نشانهٔ فهرست شروع می‌شوند.
	 *
	 * @param string $block بلوک متن.
	 * @return string|null
	 */
	protected static function build_list( $block ) {
		$lines  = preg_split( '/\n/', $block );
		$items  = array();
		$marker = null;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			// «• » و «- » و «* » فهرست نامرتب؛ «۱. » و «1) » فهرست مرتب.
			if ( preg_match( '/^[-*•]\s+(.+)$/u', $line, $match ) ) {
				$kind = 'ul';
			} elseif ( preg_match( '/^[0-9۰-۹]+[.)]\s+(.+)$/u', $line, $match ) ) {
				$kind = 'ol';
			} else {
				return null;
			}

			if ( null === $marker ) {
				$marker = $kind;
			} elseif ( $marker !== $kind ) {
				return null;
			}

			$items[] = '<li>' . esc_html( trim( $match[1] ) ) . '</li>';
		}

		if ( ! $items || null === $marker ) {
			return null;
		}

		return '<' . $marker . ">\n\t" . implode( "\n\t", $items ) . "\n</" . $marker . '>';
	}

	/**
	 * آیا متن تگ بلوکی HTML دارد؟
	 *
	 * @param string $text متن.
	 * @return bool
	 */
	protected static function has_block_html( $text ) {
		return (bool) preg_match( '/<(p|ul|ol|li|div|h[1-6]|table|blockquote|br)\b/i', $text );
	}

	/**
	 * مرتب کردن HTML موجود بدون تغییر ساختارش.
	 *
	 * @param string $text متن HTML.
	 * @return string
	 */
	protected static function tidy_html( $text ) {
		// خط خالی *داخل* فهرست در ویرایشگر وردپرس پاراگراف خالی می‌سازد و باید برود.
		// ولی خط خالی *پیش از* آغاز فهرست، مرز پاراگراف قبلی است و باید بماند —
		// وگرنه آن پاراگراف به فهرست می‌چسبد و wpautop دیگر جدایشان نمی‌کند.
		$text = preg_replace( '/\n\s*\n(\s*<(?:li\b|\/li|\/ul|\/ol))/i', "\n$1", $text );

		return trim( $text );
	}
}
