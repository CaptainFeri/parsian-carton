<?php
/**
 * خوانندهٔ فایل‌های اکسل (.xlsx) و CSV — بدون هیچ وابستگی بیرونی.
 *
 * فایل xlsx در واقع یک آرشیو زیپ از چند فایل XML است؛ اینجا فقط بخش‌هایی که
 * برای یک فهرست محصولات لازم است خوانده می‌شود: نام برگه‌ها، رشته‌های مشترک و
 * سلول‌های برگه. قالب‌بندی، فرمول و تاریخ نیازی نیست و نادیده گرفته می‌شود
 * (مقدار محاسبه‌شدهٔ فرمول خوانده می‌شود، نه خود فرمول).
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * خواندن صفحه‌گسترده به آرایه.
 */
class PCS_Spreadsheet {

	/**
	 * حداکثر تعداد سطر قابل پردازش — محافظ در برابر فایل‌های غول‌آسا.
	 */
	const MAX_ROWS = 20000;

	/**
	 * خواندن یک فایل و بازگرداندن سطرها به‌صورت آرایهٔ انجمنی بر پایهٔ سرستون‌ها.
	 *
	 * @param string $path مسیر فایل.
	 * @param string $sheet نام برگه (اختیاری — پیش‌فرض نخستین برگه).
	 * @return array{headers:string[],rows:array[]}|WP_Error
	 */
	public static function read( $path, $sheet = '' ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'pcs_unreadable', __( 'فایل خوانده نشد؛ مسیر یا دسترسی فایل را بررسی کنید.', 'parsian-catalog-sync' ) );
		}

		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( in_array( $extension, array( 'csv', 'txt' ), true ) ) {
			$matrix = self::read_csv( $path );
		} elseif ( 'xlsx' === $extension || 'xlsm' === $extension ) {
			$matrix = self::read_xlsx( $path, $sheet );
		} else {
			return new WP_Error(
				'pcs_format',
				sprintf(
					/* translators: %s: پسوند فایل. */
					__( 'قالب فایل «%s» پشتیبانی نمی‌شود. از xlsx یا csv استفاده کنید.', 'parsian-catalog-sync' ),
					$extension
				)
			);
		}

		if ( is_wp_error( $matrix ) ) {
			return $matrix;
		}

		return self::to_records( $matrix );
	}

	/**
	 * فهرست نام برگه‌های یک فایل xlsx.
	 *
	 * @param string $path مسیر فایل.
	 * @return string[]
	 */
	public static function sheet_names( $path ) {
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, array( 'xlsx', 'xlsm' ), true ) || ! class_exists( 'ZipArchive' ) ) {
			return array();
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $path ) ) {
			return array();
		}

		$names    = array();
		$workbook = self::load_xml( $zip, 'xl/workbook.xml' );

		if ( $workbook && isset( $workbook->sheets->sheet ) ) {
			foreach ( $workbook->sheets->sheet as $node ) {
				$names[] = (string) $node['name'];
			}
		}

		$zip->close();

		return $names;
	}

	/* ------------------------------- xlsx ------------------------------- */

	/**
	 * خواندن xlsx به ماتریس سطر/ستون.
	 *
	 * @param string $path  مسیر فایل.
	 * @param string $sheet نام برگه.
	 * @return array[]|WP_Error
	 */
	protected static function read_xlsx( $path, $sheet = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'pcs_zip', __( 'افزونهٔ ZipArchive در PHP فعال نیست؛ فایل xlsx خوانده نمی‌شود. می‌توانید فایل را با فرمت CSV ذخیره کنید.', 'parsian-catalog-sync' ) );
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'pcs_zip_open', __( 'فایل اکسل باز نشد؛ ممکن است ناقص آپلود شده باشد.', 'parsian-catalog-sync' ) );
		}

		$strings = self::read_shared_strings( $zip );
		$target  = self::resolve_sheet_path( $zip, $sheet );

		if ( ! $target ) {
			$zip->close();
			return new WP_Error( 'pcs_sheet', __( 'برگهٔ موردنظر در فایل اکسل پیدا نشد.', 'parsian-catalog-sync' ) );
		}

		$xml = self::load_xml( $zip, $target );
		$zip->close();

		if ( ! $xml ) {
			return new WP_Error( 'pcs_sheet_xml', __( 'محتوای برگهٔ اکسل خوانده نشد.', 'parsian-catalog-sync' ) );
		}

		$matrix = array();
		$count  = 0;

		foreach ( $xml->sheetData->row as $row ) {
			if ( ++$count > self::MAX_ROWS ) {
				break;
			}

			$line = array();

			foreach ( $row->c as $cell ) {
				$index = self::column_index( (string) $cell['r'] );
				$line[ $index ] = self::cell_value( $cell, $strings );
			}

			if ( ! $line ) {
				$matrix[] = array();
				continue;
			}

			// سلول‌های خالیِ میانی در XML وجود ندارند و باید بازسازی شوند.
			$width = max( array_keys( $line ) ) + 1;
			$full  = array_fill( 0, $width, '' );

			foreach ( $line as $index => $value ) {
				$full[ $index ] = $value;
			}

			$matrix[] = $full;
		}

		return $matrix;
	}

	/**
	 * خواندن جدول رشته‌های مشترک.
	 *
	 * @param ZipArchive $zip آرشیو.
	 * @return string[]
	 */
	protected static function read_shared_strings( $zip ) {
		$xml = self::load_xml( $zip, 'xl/sharedStrings.xml' );

		if ( ! $xml ) {
			return array();
		}

		$strings = array();

		foreach ( $xml->si as $item ) {
			if ( isset( $item->t ) ) {
				$strings[] = (string) $item->t;
				continue;
			}

			// رشته‌های دارای قالب‌بندی به چند قطعهٔ <r><t> شکسته می‌شوند.
			$buffer = '';
			foreach ( $item->r as $run ) {
				$buffer .= (string) $run->t;
			}
			$strings[] = $buffer;
		}

		return $strings;
	}

	/**
	 * یافتن مسیر XML برگهٔ موردنظر داخل آرشیو.
	 *
	 * @param ZipArchive $zip   آرشیو.
	 * @param string     $sheet نام برگه.
	 * @return string
	 */
	protected static function resolve_sheet_path( $zip, $sheet ) {
		$workbook = self::load_xml( $zip, 'xl/workbook.xml' );
		$rels     = self::load_xml( $zip, 'xl/_rels/workbook.xml.rels' );

		if ( ! $workbook || ! isset( $workbook->sheets->sheet ) ) {
			// بازگشت به حالت متداول در نبود فهرست برگه‌ها.
			return false !== $zip->locateName( 'xl/worksheets/sheet1.xml' ) ? 'xl/worksheets/sheet1.xml' : '';
		}

		$map = array();

		if ( $rels ) {
			foreach ( $rels->Relationship as $relation ) {
				$map[ (string) $relation['Id'] ] = ltrim( (string) $relation['Target'], '/' );
			}
		}

		$chosen = null;

		foreach ( $workbook->sheets->sheet as $node ) {
			if ( ! $chosen ) {
				$chosen = $node;
			}

			if ( '' !== $sheet && (string) $node['name'] === $sheet ) {
				$chosen = $node;
				break;
			}
		}

		if ( ! $chosen ) {
			return '';
		}

		$id     = (string) $chosen->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
		$target = isset( $map[ $id ] ) ? $map[ $id ] : 'worksheets/sheet1.xml';

		// مسیرها در rels نسبت به پوشهٔ xl/ هستند.
		$target = 0 === strpos( $target, 'xl/' ) ? $target : 'xl/' . $target;

		return false !== $zip->locateName( $target ) ? $target : '';
	}

	/**
	 * تبدیل مقدار یک سلول به رشته.
	 *
	 * @param SimpleXMLElement $cell    سلول.
	 * @param string[]         $strings رشته‌های مشترک.
	 * @return string
	 */
	protected static function cell_value( $cell, $strings ) {
		$type = isset( $cell['t'] ) ? (string) $cell['t'] : '';

		if ( 's' === $type ) {
			$index = (int) $cell->v;
			return isset( $strings[ $index ] ) ? $strings[ $index ] : '';
		}

		if ( 'inlineStr' === $type ) {
			if ( isset( $cell->is->t ) ) {
				return (string) $cell->is->t;
			}

			$buffer = '';
			if ( isset( $cell->is->r ) ) {
				foreach ( $cell->is->r as $run ) {
					$buffer .= (string) $run->t;
				}
			}
			return $buffer;
		}

		if ( 'b' === $type ) {
			return '1' === (string) $cell->v ? '1' : '0';
		}

		if ( 'e' === $type ) {
			// سلول خطادار (#N/A و مانند آن) مثل خالی رفتار می‌کند.
			return '';
		}

		return isset( $cell->v ) ? (string) $cell->v : '';
	}

	/**
	 * تبدیل مرجع سلول (مثل «BC12») به شمارهٔ ستون صفرپایه.
	 *
	 * @param string $reference مرجع سلول.
	 * @return int
	 */
	protected static function column_index( $reference ) {
		$letters = strtoupper( preg_replace( '/[^A-Za-z]/', '', $reference ) );
		$index   = 0;

		for ( $i = 0, $len = strlen( $letters ); $i < $len; $i++ ) {
			$index = $index * 26 + ( ord( $letters[ $i ] ) - 64 );
		}

		return max( 0, $index - 1 );
	}

	/**
	 * بارگذاری یک فایل XML از آرشیو.
	 *
	 * @param ZipArchive $zip  آرشیو.
	 * @param string     $name نام فایل.
	 * @return SimpleXMLElement|false
	 */
	protected static function load_xml( $zip, $name ) {
		if ( false === $zip->locateName( $name ) ) {
			return false;
		}

		$content = $zip->getFromName( $name );

		if ( ! $content ) {
			return false;
		}

		$previous = libxml_use_internal_errors( true );
		// بارگذاری بدون موجودیت‌های بیرونی — فایل اکسل ورودی کاربر است.
		$xml = simplexml_load_string( $content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $xml;
	}

	/* -------------------------------- CSV -------------------------------- */

	/**
	 * خواندن CSV به ماتریس.
	 *
	 * @param string $path مسیر فایل.
	 * @return array[]|WP_Error
	 */
	protected static function read_csv( $path ) {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $handle ) {
			return new WP_Error( 'pcs_csv', __( 'فایل CSV باز نشد.', 'parsian-catalog-sync' ) );
		}

		$matrix = array();
		$first  = true;

		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) { // phpcs:ignore
			if ( $first ) {
				// حذف BOM که اکسل ابتدای فایل‌های UTF-8 می‌گذارد.
				if ( isset( $row[0] ) ) {
					$row[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $row[0] );
				}
				$first = false;
			}

			$matrix[] = array_map( static function ( $value ) {
				return null === $value ? '' : (string) $value;
			}, $row );

			if ( count( $matrix ) > self::MAX_ROWS ) {
				break;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $matrix;
	}

	/* ------------------------------ تبدیل ------------------------------ */

	/**
	 * تبدیل ماتریس به رکوردهای انجمنی با کلید سرستون.
	 *
	 * @param array[] $matrix ماتریس سطر/ستون.
	 * @return array{headers:string[],rows:array[]}|WP_Error
	 */
	protected static function to_records( $matrix ) {
		// سطرهای خالی ابتدای فایل (عنوان، فاصله) رد می‌شوند تا به سرستون‌ها برسیم.
		$header_index = null;

		foreach ( $matrix as $index => $row ) {
			if ( count( array_filter( $row, static function ( $cell ) {
				return '' !== trim( (string) $cell );
			} ) ) >= 2 ) {
				$header_index = $index;
				break;
			}
		}

		if ( null === $header_index ) {
			return new WP_Error( 'pcs_empty', __( 'فایل خالی است یا سطر سرستون پیدا نشد.', 'parsian-catalog-sync' ) );
		}

		$headers = array();

		foreach ( $matrix[ $header_index ] as $position => $label ) {
			$label = self::normalize_header( $label );
			// سرستون‌های تکراری کنار گذاشته می‌شوند تا داده‌ها جابه‌جا نشوند.
			$headers[ $position ] = ( '' !== $label && ! in_array( $label, $headers, true ) ) ? $label : '';
		}

		$rows = array();

		foreach ( array_slice( $matrix, $header_index + 1 ) as $row ) {
			$record = array();
			$filled = false;

			foreach ( $headers as $position => $label ) {
				if ( '' === $label ) {
					continue;
				}

				$value = isset( $row[ $position ] ) ? trim( (string) $row[ $position ] ) : '';
				$record[ $label ] = $value;

				if ( '' !== $value ) {
					$filled = true;
				}
			}

			if ( $filled ) {
				$rows[] = $record;
			}
		}

		return array(
			'headers' => array_values( array_filter( $headers ) ),
			'rows'    => $rows,
		);
	}

	/**
	 * یکدست‌سازی نام سرستون (حذف فاصله‌های اضافه و نویسه‌های نامرئی).
	 *
	 * @param string $label برچسب خام.
	 * @return string
	 */
	public static function normalize_header( $label ) {
		$label = (string) $label;
		// نویسهٔ نیم‌فاصله، فاصلهٔ باریک و فاصلهٔ بدون‌شکست به فاصلهٔ ساده تبدیل می‌شوند.
		$label = str_replace( array( "\xE2\x80\x8C", "\xE2\x80\x8F", "\xE2\x80\x8E", "\xC2\xA0" ), array( ' ', '', '', ' ' ), $label );
		$label = preg_replace( '/\s+/u', ' ', $label );
		// «ي» و «ك» عربی به معادل فارسی تبدیل می‌شوند تا تطبیق سرستون‌ها مطمئن باشد.
		$label = str_replace( array( 'ي', 'ك' ), array( 'ی', 'ک' ), $label );

		return trim( $label );
	}
}
