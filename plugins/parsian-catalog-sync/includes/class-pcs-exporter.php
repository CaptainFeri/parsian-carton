<?php
/**
 * ساخت فایل CSV از محصولات فروشگاه.
 *
 * قرارداد اصلی این کلاس: **خروجی باید دوباره قابل ورود باشد.** سرستون‌ها همان
 * نام‌هایی هستند که PCS_Mapper می‌شناسد و مقدارها همان‌طور نوشته می‌شوند که
 * PCS_Sync هنگام مقایسه می‌سازد؛ پس اگر فایل را بگیرید و بدون ویرایش دوباره
 * وارد کنید، پیش‌نمایش باید «بدون تغییر» نشان دهد. آزمون tests/test-exporter.php
 * دقیقاً همین را می‌سنجد.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * برون‌ریزی کاتالوگ.
 */
class PCS_Exporter {

	/**
	 * تعداد محصولی که در هر گام از دیتابیس خوانده می‌شود.
	 */
	const CHUNK = 100;

	/**
	 * سقف ستون‌های صفت — محافظ در برابر کاتالوگ‌های عجیب.
	 */
	const MAX_ATTRIBUTE_COLUMNS = 12;

	/**
	 * گزینه‌های پیش‌فرض برون‌ریزی.
	 *
	 * @return array
	 */
	public static function default_args() {
		return array(
			'status'       => 'any',   // any | publish | draft | private
			'category'     => 0,       // شناسهٔ دسته (۰ = همه)
			'variations'   => 1,       // واریاسیون‌ها هم بیایند؟
			'descriptions' => 1,       // ستون توضیحات و توضیح کوتاه
			'images'       => 1,       // ستون تصاویر
			'attributes'   => 1,       // ستون‌های صفت
			'extras'       => 0,       // برچسب، ویژه، ترتیب، وزن و ابعاد
			'custom'       => 1,       // ستون‌های افزونه‌های دیگر (مثل پیش‌فروش)
			'empty'        => 0,       // فقط سرستون‌ها (قالب خالی)
		);
	}

	/**
	 * یکدست‌سازی گزینه‌های ورودی.
	 *
	 * @param array $args گزینه‌ها.
	 * @return array
	 */
	public static function parse_args( $args ) {
		$args = wp_parse_args( (array) $args, self::default_args() );

		$args['status']   = in_array( $args['status'], array( 'any', 'publish', 'draft', 'private' ), true ) ? $args['status'] : 'any';
		$args['category'] = (int) $args['category'];

		foreach ( array( 'variations', 'descriptions', 'images', 'attributes', 'extras', 'custom', 'empty' ) as $flag ) {
			$args[ $flag ] = empty( $args[ $flag ] ) ? 0 : 1;
		}

		return $args;
	}

	/* ------------------------------- ستون‌ها ------------------------------- */

	/**
	 * سرستون‌های فایل به ترتیب.
	 *
	 * ترتیب بر پایهٔ کاری چیده شده که واقعاً انجام می‌شود: ویرایش قیمت و موجودی.
	 * پس این دو کنار نام محصول‌اند و متن‌های بلند ته جدول رفته‌اند.
	 *
	 * @param array $args       گزینه‌ها.
	 * @param int   $attributes تعداد ستون‌های صفت.
	 * @return array<int,array{key:string,label:string}>
	 */
	public static function columns( $args, $attributes = 0 ) {
		$args    = self::parse_args( $args );
		$columns = array(
			array( 'key' => 'id',     'label' => __( 'شناسه', 'parsian-catalog-sync' ) ),
			array( 'key' => 'type',   'label' => __( 'نوع', 'parsian-catalog-sync' ) ),
			array( 'key' => 'sku',    'label' => __( 'کد محصول', 'parsian-catalog-sync' ) ),
			array( 'key' => 'name',   'label' => __( 'نام محصول', 'parsian-catalog-sync' ) ),
			array( 'key' => 'regular_price', 'label' => __( 'قیمت', 'parsian-catalog-sync' ) ),
			array( 'key' => 'sale_price',    'label' => __( 'قیمت حراج', 'parsian-catalog-sync' ) ),
			array( 'key' => 'stock_quantity', 'label' => __( 'موجودی', 'parsian-catalog-sync' ) ),
			array( 'key' => 'stock_status',   'label' => __( 'وضعیت موجودی', 'parsian-catalog-sync' ) ),
			array( 'key' => 'categories',     'label' => __( 'دسته‌بندی', 'parsian-catalog-sync' ) ),
			array( 'key' => 'status',         'label' => __( 'وضعیت انتشار', 'parsian-catalog-sync' ) ),
		);

		if ( $args['extras'] ) {
			$columns[] = array( 'key' => 'tags',       'label' => __( 'برچسب‌ها', 'parsian-catalog-sync' ) );
			$columns[] = array( 'key' => 'featured',   'label' => __( 'ویژه', 'parsian-catalog-sync' ) );
			$columns[] = array( 'key' => 'menu_order', 'label' => __( 'ترتیب', 'parsian-catalog-sync' ) );
			$columns[] = array( 'key' => 'weight',     'label' => __( 'وزن', 'parsian-catalog-sync' ) );
			$columns[] = array( 'key' => 'length',     'label' => __( 'طول', 'parsian-catalog-sync' ) );
			$columns[] = array( 'key' => 'width',      'label' => __( 'عرض', 'parsian-catalog-sync' ) );
			$columns[] = array( 'key' => 'height',     'label' => __( 'ارتفاع', 'parsian-catalog-sync' ) );
		}

		if ( $args['custom'] ) {
			foreach ( PCS_Fields::all() as $key => $field ) {
				$columns[] = array(
					'key'   => PCS_Fields::field( $key ),
					'label' => $field['label'],
				);
			}
		}

		if ( $args['descriptions'] ) {
			$columns[] = array( 'key' => 'short_description', 'label' => __( 'توضیح کوتاه', 'parsian-catalog-sync' ) );
			$columns[] = array( 'key' => 'description',       'label' => __( 'توضیحات', 'parsian-catalog-sync' ) );
		}

		if ( $args['images'] ) {
			$columns[] = array( 'key' => 'images', 'label' => __( 'تصاویر', 'parsian-catalog-sync' ) );
		}

		$columns[] = array( 'key' => 'parent', 'label' => __( 'مادر', 'parsian-catalog-sync' ) );

		if ( $args['attributes'] ) {
			for ( $index = 1; $index <= $attributes; $index++ ) {
				$columns[] = array(
					'key'   => 'attribute_name_' . $index,
					/* translators: %s: شمارهٔ صفت. */
					'label' => sprintf( __( 'نام %s صفت', 'parsian-catalog-sync' ), pcs_digits( $index ) ),
				);
				$columns[] = array(
					'key'   => 'attribute_values_' . $index,
					/* translators: %s: شمارهٔ صفت. */
					'label' => sprintf( __( 'مقدار(های) %s صفت', 'parsian-catalog-sync' ), pcs_digits( $index ) ),
				);
			}
		}

		/**
		 * تغییر ستون‌های فایل خروجی.
		 *
		 * @param array $columns ستون‌ها.
		 * @param array $args    گزینه‌های برون‌ریزی.
		 */
		return (array) apply_filters( 'pcs_export_columns', $columns, $args );
	}

	/* -------------------------------- داده -------------------------------- */

	/**
	 * شناسهٔ محصولاتی که باید برون‌ریزی شوند — والدها به ترتیب، هر واریاسیون
	 * بلافاصله زیر والدش.
	 *
	 * @param array $args گزینه‌ها.
	 * @return int[]
	 */
	public static function collect_ids( $args ) {
		$args = self::parse_args( $args );

		$query = array(
			'post_type'      => 'product',
			'post_status'    => 'any' === $args['status'] ? array( 'publish', 'draft', 'pending', 'private' ) : array( $args['status'] ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		if ( $args['category'] ) {
			$query['tax_query'] = array(
				array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => $args['category'],
					'include_children' => true,
				),
			);
		}

		$parents = get_posts( $query );
		$ids     = array();

		foreach ( $parents as $parent_id ) {
			$ids[] = (int) $parent_id;

			if ( ! $args['variations'] ) {
				continue;
			}

			$product = wc_get_product( $parent_id );

			if ( $product && $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $child_id ) {
					$ids[] = (int) $child_id;
				}
			}
		}

		return $ids;
	}

	/**
	 * بیشترین تعداد صفت در میان محصولات — تعداد ستون‌های صفت از این می‌آید.
	 *
	 * @param int[] $ids شناسه‌ها.
	 * @return int
	 */
	public static function max_attributes( $ids ) {
		$max = 0;

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );

			if ( ! $product ) {
				continue;
			}

			$max = max( $max, count( self::product_attributes( $product ) ) );

			if ( $max >= self::MAX_ATTRIBUTE_COLUMNS ) {
				return self::MAX_ATTRIBUTE_COLUMNS;
			}
		}

		return $max;
	}

	/**
	 * صفت‌های یک محصول به شکل «برچسب => مقدارها».
	 *
	 * محصول ساده و متغیر شیء WC_Product_Attribute دارند و واریاسیون یک آرایهٔ
	 * ساده از «تاکسونومی => اسلاگ» — هر دو اینجا یکدست می‌شوند.
	 *
	 * @param WC_Product $product محصول.
	 * @return array<string,string> برچسب => مقدارها.
	 */
	public static function product_attributes( $product ) {
		$result = array();

		foreach ( (array) $product->get_attributes() as $key => $attribute ) {
			if ( is_object( $attribute ) && method_exists( $attribute, 'get_options' ) ) {
				$taxonomy = $attribute->get_taxonomy();
				$label    = $taxonomy ? wc_attribute_label( $taxonomy ) : $attribute->get_name();

				$options = $taxonomy
					? wc_get_product_terms( $product->get_id(), $taxonomy, array( 'fields' => 'names' ) )
					: $attribute->get_options();

				$result[ $label ] = implode( '، ', array_map( 'strval', (array) $options ) );
				continue;
			}

			// واریاسیون: کلید نام صفت است و مقدار، یک گزینهٔ تکی.
			$name  = str_replace( 'attribute_', '', (string) $key );
			$label = 0 === strpos( $name, 'pa_' ) ? wc_attribute_label( $name ) : $name;
			$value = (string) $attribute;

			if ( 0 === strpos( $name, 'pa_' ) && '' !== $value ) {
				$term = get_term_by( 'slug', $value, $name );

				if ( $term && ! is_wp_error( $term ) ) {
					$value = $term->name;
				}
			}

			$result[ $label ] = $value;
		}

		return $result;
	}

	/**
	 * ساخت یک سطر از روی محصول.
	 *
	 * @param WC_Product $product    محصول.
	 * @param array      $args       گزینه‌ها.
	 * @param int        $attributes تعداد ستون‌های صفت.
	 * @return array<string,string> کلید ستون => مقدار.
	 */
	public static function row( $product, $args, $attributes = 0 ) {
		$settings = PCS_Settings::instance();
		$type     = $product->get_type();

		$row = array(
			'id'             => (string) $product->get_id(),
			'type'           => self::type_label( $type ),
			'sku'            => (string) $product->get_sku(),
			'name'           => (string) $product->get_name(),
			'regular_price'  => '',
			'sale_price'     => '',
			'stock_quantity' => '',
			'stock_status'   => self::stock_label( $product->get_stock_status() ),
			'categories'     => '',
			'status'         => self::status_label( $product->get_status() ),
			'parent'         => '',
		);

		// محصول متغیر قیمت و موجودی خودش را ندارد؛ این‌ها در واریاسیون‌هایش هستند.
		if ( 'variable' !== $type ) {
			$row['regular_price'] = self::price( $product->get_regular_price( 'edit' ), $settings );
			$row['sale_price']    = self::price( $product->get_sale_price( 'edit' ), $settings );

			$quantity = $product->get_stock_quantity();

			if ( null !== $quantity && '' !== $quantity ) {
				$row['stock_quantity'] = (string) (int) $quantity;
			}
		}

		if ( 'variation' === $type ) {
			$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : null;

			if ( $parent ) {
				$row['parent'] = $parent->get_sku() ? $parent->get_sku() : 'id:' . $parent->get_id();
			}
		} else {
			$row['categories'] = self::term_paths( $product->get_id(), 'product_cat' );
		}

		if ( $args['extras'] ) {
			$row['tags']       = self::term_paths( $product->get_id(), 'product_tag' );
			$row['featured']   = $product->get_featured() ? __( 'بله', 'parsian-catalog-sync' ) : __( 'خیر', 'parsian-catalog-sync' );
			$row['menu_order'] = (string) $product->get_menu_order();

			foreach ( array( 'weight', 'length', 'width', 'height' ) as $field ) {
				$getter        = 'get_' . $field;
				$value         = $product->$getter( 'edit' );
				$row[ $field ] = ( '' === $value || null === $value ) ? '' : self::number( (float) $value );
			}
		}

		if ( $args['custom'] ) {
			foreach ( PCS_Fields::all() as $key => $field ) {
				$row[ PCS_Fields::field( $key ) ] = PCS_Fields::applies_to( $field, $type )
					? PCS_Fields::to_file( $field, PCS_Fields::read( $product, $field ) )
					: '';
			}
		}

		if ( $args['descriptions'] ) {
			$row['short_description'] = (string) $product->get_short_description();
			$row['description']       = (string) $product->get_description();
		}

		if ( $args['images'] ) {
			$row['images'] = self::images( $product );
		}

		if ( $args['attributes'] && $attributes ) {
			$index = 1;

			foreach ( self::product_attributes( $product ) as $label => $values ) {
				if ( $index > $attributes ) {
					break;
				}

				$row[ 'attribute_name_' . $index ]   = (string) $label;
				$row[ 'attribute_values_' . $index ] = (string) $values;
				$index++;
			}
		}

		/**
		 * تغییر یک سطر خروجی.
		 *
		 * @param array      $row     مقادیر سطر.
		 * @param WC_Product $product محصول.
		 * @param array      $args    گزینه‌ها.
		 */
		return (array) apply_filters( 'pcs_export_row', $row, $product, $args );
	}

	/**
	 * ساخت کامل خروجی در حافظه — برای آزمون و پیش‌نمایش.
	 *
	 * @param array $args گزینه‌ها.
	 * @return array{headers:string[],rows:array[]}
	 */
	public static function build( $args = array() ) {
		$args = self::parse_args( $args );
		$ids  = $args['empty'] ? array() : self::collect_ids( $args );
		$max  = ( $args['attributes'] && $ids ) ? self::max_attributes( $ids ) : 0;

		$columns = self::columns( $args, $max );
		$headers = wp_list_pluck( $columns, 'label' );
		$rows    = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );

			if ( ! $product ) {
				continue;
			}

			$values = self::row( $product, $args, $max );
			$line   = array();

			foreach ( $columns as $column ) {
				$line[] = isset( $values[ $column['key'] ] ) ? (string) $values[ $column['key'] ] : '';
			}

			$rows[] = $line;
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * ساخت متن CSV در حافظه.
	 *
	 * @param array $args گزینه‌ها.
	 * @return string
	 */
	public static function to_csv( $args = array() ) {
		$data   = self::build( $args );
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		self::put_row( $handle, $data['headers'] );

		foreach ( $data['rows'] as $row ) {
			self::put_row( $handle, $row );
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $csv;
	}

	/**
	 * فرستادن فایل به مرورگر — بدون ساختن کل خروجی در حافظه.
	 *
	 * @param array $args گزینه‌ها.
	 */
	public static function stream( $args = array() ) {
		$args = self::parse_args( $args );
		$ids  = $args['empty'] ? array() : self::collect_ids( $args );
		$max  = ( $args['attributes'] && $ids ) ? self::max_attributes( $ids ) : 0;

		$columns = self::columns( $args, $max );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . self::filename( $args ) . '"' );

		$handle = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// BOM — بدون آن، اکسل ویندوز فایل UTF-8 را با حروف درهم باز می‌کند.
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		self::put_row( $handle, wp_list_pluck( $columns, 'label' ) );

		if ( $args['empty'] ) {
			self::put_row( $handle, self::sample_row( $columns ) );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return;
		}

		foreach ( array_chunk( $ids, self::CHUNK ) as $chunk ) {
			foreach ( $chunk as $id ) {
				$product = wc_get_product( $id );

				if ( ! $product ) {
					continue;
				}

				$values = self::row( $product, $args, $max );
				$line   = array();

				foreach ( $columns as $column ) {
					$line[] = isset( $values[ $column['key'] ] ) ? (string) $values[ $column['key'] ] : '';
				}

				self::put_row( $handle, $line );
			}

			// کش محصولات ووکامرس در برون‌ریزی بزرگ، حافظه را پر می‌کند.
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'products' );
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * یک سطر نمونه برای قالب خالی.
	 *
	 * @param array $columns ستون‌ها.
	 * @return string[]
	 */
	protected static function sample_row( $columns ) {
		$sample = array(
			'id'               => '',
			'type'             => __( 'ساده', 'parsian-catalog-sync' ),
			'sku'              => 'CRT-001',
			'name'             => __( 'کارتن پستی کد ۱', 'parsian-catalog-sync' ),
			'regular_price'    => '25000',
			'sale_price'       => '',
			'stock_quantity'   => '500',
			'stock_status'     => __( 'موجود', 'parsian-catalog-sync' ),
			'categories'       => __( 'کارتن پستی', 'parsian-catalog-sync' ),
			'status'           => __( 'منتشر', 'parsian-catalog-sync' ),
			'short_description' => __( 'یکی دو جمله زیر عنوان محصول.', 'parsian-catalog-sync' ),
		);

		$row = array();

		foreach ( $columns as $column ) {
			$row[] = isset( $sample[ $column['key'] ] ) ? $sample[ $column['key'] ] : '';
		}

		return $row;
	}

	/**
	 * نام فایل خروجی.
	 *
	 * @param array $args گزینه‌ها.
	 * @return string
	 */
	public static function filename( $args ) {
		if ( ! empty( $args['empty'] ) ) {
			return 'parsian-catalog-template.csv';
		}

		return 'parsian-catalog-' . gmdate( 'Y-m-d-Hi' ) . '.csv';
	}

	/* ------------------------------ ابزار نوشتن ------------------------------ */

	/**
	 * نوشتن یک سطر با پایان خط ویندوزی.
	 *
	 * @param resource $handle مقصد.
	 * @param array    $row    مقادیر.
	 */
	public static function put_row( $handle, $row ) {
		fwrite( $handle, self::csv_line( $row ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	}

	/**
	 * ساخت یک سطر CSV بر پایهٔ RFC 4180.
	 *
	 * به‌جای fputcsv دستی نوشته شده تا پایان خط \r\n باشد؛ اکسل ویندوز با \n
	 * تنها، همهٔ سطرها را به هم می‌چسباند.
	 *
	 * @param array $row مقادیر.
	 * @return string
	 */
	public static function csv_line( $row ) {
		$cells = array();

		foreach ( (array) $row as $value ) {
			$value = self::escape_cell( $value );

			if ( preg_match( '/["\r\n,]/', $value ) ) {
				$value = '"' . str_replace( '"', '""', $value ) . '"';
			}

			$cells[] = $value;
		}

		return implode( ',', $cells ) . "\r\n";
	}

	/**
	 * خنثی‌سازی سلول‌هایی که اکسل آن‌ها را فرمول می‌بیند.
	 *
	 * سلولی که با «=»، «+» یا «@» شروع شود در اکسل اجرا می‌شود؛ این راه شناخته‌شدهٔ
	 * تزریق فرمول به فایل خروجی است. یک آپاستروف ابتدای سلول، اکسل را وادار می‌کند
	 * آن را متن ببیند.
	 *
	 * @param mixed $value مقدار سلول.
	 * @return string
	 */
	public static function escape_cell( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return '';
		}

		$first = $value[0];

		if ( in_array( $first, array( '=', '+', '@' ), true ) || "\t" === $first || "\r" === $first ) {
			return "'" . $value;
		}

		// «-» فقط وقتی خطرناک است که عدد نباشد (مثل «-۱۰» که قیمت منفی است).
		if ( '-' === $first && ! is_numeric( $value ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/* ------------------------------ قالب‌بندی ------------------------------ */

	/**
	 * قیمت دیتابیس به قیمت فایل.
	 *
	 * @param string|float $amount   مقدار خام.
	 * @param PCS_Settings $settings تنظیمات.
	 * @return string
	 */
	protected static function price( $amount, $settings ) {
		if ( '' === $amount || null === $amount ) {
			return '';
		}

		return self::number( $settings->to_file_price( (float) $amount ) );
	}

	/**
	 * عدد بدون صفرهای اضافی اعشار.
	 *
	 * @param float $number عدد.
	 * @return string
	 */
	public static function number( $number ) {
		$number = (float) $number;

		if ( abs( $number - round( $number ) ) < 0.000001 ) {
			return (string) (int) round( $number );
		}

		return rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * نام فارسی نوع محصول.
	 *
	 * @param string $type نوع.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'simple'    => __( 'ساده', 'parsian-catalog-sync' ),
			'variable'  => __( 'متغیر', 'parsian-catalog-sync' ),
			'variation' => __( 'واریاسیون', 'parsian-catalog-sync' ),
			'grouped'   => __( 'گروهی', 'parsian-catalog-sync' ),
			'external'  => __( 'خارجی', 'parsian-catalog-sync' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : (string) $type;
	}

	/**
	 * نام فارسی وضعیت موجودی.
	 *
	 * @param string $status وضعیت.
	 * @return string
	 */
	public static function stock_label( $status ) {
		$labels = array(
			'instock'     => __( 'موجود', 'parsian-catalog-sync' ),
			'outofstock'  => __( 'ناموجود', 'parsian-catalog-sync' ),
			'onbackorder' => __( 'پیش‌سفارش', 'parsian-catalog-sync' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/**
	 * نام فارسی وضعیت انتشار.
	 *
	 * @param string $status وضعیت.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'publish' => __( 'منتشر', 'parsian-catalog-sync' ),
			'draft'   => __( 'پیش‌نویس', 'parsian-catalog-sync' ),
			'pending' => __( 'پیش‌نویس', 'parsian-catalog-sync' ),
			'private' => __( 'خصوصی', 'parsian-catalog-sync' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/**
	 * نام ترم‌های یک محصول — زیردسته‌ها با «>» و چند دسته با «،».
	 *
	 * ترتیب الفبایی است تا خروجی دو اجرای پشت‌سرهم یکی باشد.
	 *
	 * @param int    $post_id  شناسهٔ محصول.
	 * @param string $taxonomy تاکسونومی.
	 * @return string
	 */
	protected static function term_paths( $post_id, $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) || ! $terms ) {
			return '';
		}

		$names = array();

		foreach ( $terms as $term ) {
			$path   = array( $term->name );
			$parent = isset( $term->parent ) ? (int) $term->parent : 0;
			$guard  = 0;

			while ( $parent && $guard++ < 10 ) {
				$ancestor = get_term( $parent, $taxonomy );

				if ( ! $ancestor || is_wp_error( $ancestor ) ) {
					break;
				}

				array_unshift( $path, $ancestor->name );
				$parent = (int) $ancestor->parent;
			}

			$names[] = implode( '>', $path );
		}

		sort( $names );

		return implode( '، ', $names );
	}

	/**
	 * نشانی تصویر شاخص و گالری در یک سلول.
	 *
	 * @param WC_Product $product محصول.
	 * @return string
	 */
	protected static function images( $product ) {
		$ids = array();

		if ( $product->get_image_id() ) {
			$ids[] = (int) $product->get_image_id();
		}

		foreach ( (array) $product->get_gallery_image_ids() as $id ) {
			$ids[] = (int) $id;
		}

		$urls = array();

		foreach ( array_unique( $ids ) as $id ) {
			$url = wp_get_attachment_url( $id );

			if ( $url ) {
				$urls[] = $url;
			}
		}

		return implode( '، ', $urls );
	}
}
