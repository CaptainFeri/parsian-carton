<?php
/**
 * تهیهٔ تصاویر محصول از روی مقدار سلول اکسل.
 *
 * مقدار سلول می‌تواند یکی از این‌ها باشد:
 *  - آدرس کامل تصویر (https://…) — یک بار دانلود و در کتابخانهٔ رسانه ذخیره می‌شود
 *  - نام فایل (postal-1.svg) — در کتابخانهٔ رسانه و پوشهٔ uploads جستجو می‌شود
 *  - شناسهٔ عددی پیوست (۱۲۳)
 *
 * برای جلوگیری از دانلود تکراری، نشانی مبدأ در متای پیوست ذخیره می‌شود.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * مدیریت رسانه.
 */
class PCS_Media {

	/**
	 * کلید متای نشانی مبدأ.
	 */
	const SOURCE_META = '_pcs_source_url';

	/**
	 * یافتن یا ساخت پیوست برای یک مقدار سلول.
	 *
	 * @param string $value   مقدار سلول.
	 * @param string $context نام محصول برای متن جایگزین.
	 * @return int|WP_Error شناسهٔ پیوست.
	 */
	public static function resolve( $value, $context = '' ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return new WP_Error( 'pcs_media_empty', __( 'مقدار تصویر خالی است.', 'parsian-catalog-sync' ) );
		}

		if ( ctype_digit( $value ) ) {
			$id = (int) $value;

			if ( 'attachment' === get_post_type( $id ) ) {
				return $id;
			}

			return new WP_Error(
				'pcs_media_id',
				sprintf(
					/* translators: %d: شناسهٔ پیوست. */
					__( 'پیوستی با شناسهٔ %d در کتابخانهٔ رسانه نیست.', 'parsian-catalog-sync' ),
					$id
				)
			);
		}

		if ( preg_match( '#^https?://#i', $value ) ) {
			return self::from_url( $value, $context );
		}

		return self::from_filename( $value );
	}


	/**
	 * یافتن شناسهٔ پیوستِ یک مقدار، بدون ساخت یا دانلود چیزی.
	 *
	 * در مرحلهٔ پیش‌نمایش لازم است بدانیم تصویر فعلی محصول همان تصویر فایل هست یا
	 * نه. بدون این، مقدار فایل (نشانی) با مقدار محصول (شناسهٔ پیوست) مقایسه می‌شد و
	 * هر بار برای همهٔ محصولات «تغییر تصویر» گزارش می‌شد.
	 *
	 * @param string $value مقدار سلول.
	 * @return int شناسهٔ پیوست، یا ۰ اگر هنوز در سایت نباشد.
	 */
	public static function peek( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return 0;
		}

		if ( ctype_digit( $value ) ) {
			return 'attachment' === get_post_type( (int) $value ) ? (int) $value : 0;
		}

		if ( preg_match( '#^https?://#i', $value ) ) {
			$existing = self::find_by_source( $value );

			if ( $existing ) {
				return $existing;
			}

			// تصویری که روی همین سایت آپلود شده، از روی نشانی‌اش پیدا می‌شود.
			return (int) attachment_url_to_postid( $value );
		}

		return (int) self::lookup_filename( $value );
	}

	/**
	 * جستجوی پیوست بر پایهٔ نام فایل — فقط خواندن.
	 *
	 * @param string $filename نام فایل.
	 * @return int
	 */
	protected static function lookup_filename( $filename ) {
		global $wpdb;

		$filename = ltrim( wp_normalize_path( $filename ), '/' );
		$basename = wp_basename( $filename );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wp_attached_file'
				 AND ( meta_value = %s OR meta_value LIKE %s )
				 ORDER BY post_id DESC LIMIT 1",
				$filename,
				'%/' . $wpdb->esc_like( $basename )
			)
		);
		// phpcs:enable
	}

	/**
	 * یافتن پیوست بر پایهٔ نام فایل.
	 *
	 * @param string $filename نام فایل.
	 * @return int|WP_Error
	 */
	protected static function from_filename( $filename ) {

		$filename = ltrim( wp_normalize_path( $filename ), '/' );
		$basename = wp_basename( $filename );
		$id       = self::lookup_filename( $filename );

		if ( $id ) {
			return $id;
		}

		// اگر فایل روی دیسک هست ولی در کتابخانه ثبت نشده، ثبتش می‌کنیم.
		$uploads = wp_get_upload_dir();
		$path    = trailingslashit( $uploads['basedir'] ) . $filename;

		if ( is_file( $path ) ) {
			return self::attach_existing_file( $path, $filename );
		}

		return new WP_Error(
			'pcs_media_missing',
			sprintf(
				/* translators: %s: نام فایل. */
				__( 'تصویر «%s» نه در کتابخانهٔ رسانه پیدا شد نه در پوشهٔ uploads.', 'parsian-catalog-sync' ),
				$basename
			)
		);
	}

	/**
	 * ثبت فایلی که از قبل در uploads هست به‌عنوان پیوست.
	 *
	 * @param string $path     مسیر کامل فایل.
	 * @param string $relative مسیر نسبت به uploads.
	 * @return int|WP_Error
	 */
	protected static function attach_existing_file( $path, $relative ) {
		$filetype = wp_check_filetype( $path );

		if ( empty( $filetype['type'] ) ) {
			return new WP_Error( 'pcs_media_type', __( 'نوع فایل تصویر شناسایی نشد.', 'parsian-catalog-sync' ) );
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $filetype['type'],
				'post_title'     => sanitize_file_name( pathinfo( $path, PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$path
		);

		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'pcs_media_attach', __( 'ثبت تصویر در کتابخانهٔ رسانه ناموفق بود.', 'parsian-catalog-sync' ) );
		}

		update_post_meta( $id, '_wp_attached_file', $relative );
		self::generate_metadata( $id, $path );

		return (int) $id;
	}

	/**
	 * دانلود تصویر از یک نشانی و افزودن به کتابخانهٔ رسانه.
	 *
	 * @param string $url     نشانی.
	 * @param string $context نام محصول.
	 * @return int|WP_Error
	 */
	protected static function from_url( $url, $context = '' ) {
		$existing = self::find_by_source( $url );

		if ( $existing ) {
			return $existing;
		}

		// تصویری که قبلاً روی همین سایت آپلود شده، دوباره دانلود نمی‌شود.
		$local = attachment_url_to_postid( $url );

		if ( $local ) {
			return (int) $local;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$temp = download_url( $url, 30 );

		if ( is_wp_error( $temp ) ) {
			return new WP_Error(
				'pcs_media_download',
				sprintf(
					/* translators: 1: نشانی تصویر، 2: پیام خطا. */
					__( 'دانلود تصویر «%1$s» ناموفق بود: %2$s', 'parsian-catalog-sync' ),
					$url,
					$temp->get_error_message()
				)
			);
		}

		$name = wp_basename( wp_parse_url( $url, PHP_URL_PATH ) );
		$name = $name ? sanitize_file_name( $name ) : 'product-image';

		$file = array(
			'name'     => $name,
			'tmp_name' => $temp,
		);

		$id = media_handle_sideload( $file, 0, $context );

		if ( is_wp_error( $id ) ) {
			// فایل موقت در هر حالتی پاک می‌شود تا دیسک پر نشود.
			if ( file_exists( $temp ) ) {
				wp_delete_file( $temp );
			}
			return $id;
		}

		update_post_meta( $id, self::SOURCE_META, esc_url_raw( $url ) );

		return (int) $id;
	}

	/**
	 * یافتن پیوستی که پیش‌تر از همین نشانی دانلود شده است.
	 *
	 * @param string $url نشانی.
	 * @return int
	 */
	protected static function find_by_source( $url ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s ORDER BY post_id DESC LIMIT 1",
				self::SOURCE_META,
				esc_url_raw( $url )
			)
		);
		// phpcs:enable
	}

	/**
	 * ساخت متادیتای پیوست (اندازه‌های تصویر).
	 *
	 * @param int    $id   شناسهٔ پیوست.
	 * @param string $path مسیر فایل.
	 */
	protected static function generate_metadata( $id, $path ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$metadata = wp_generate_attachment_metadata( $id, $path );

		if ( $metadata ) {
			wp_update_attachment_metadata( $id, $metadata );
		}
	}
}
