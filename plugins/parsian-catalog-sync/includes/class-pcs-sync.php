<?php
/**
 * موتور همگام‌سازی: ساخت پیش‌نمایش تغییرات و اعمال آن‌ها روی محصولات.
 *
 * کلید یکتای هر سطر «کد محصول» (SKU) است. همگام‌سازی ایدم‌پوتنت است: اجرای
 * دوبارهٔ همان فایل هیچ تغییری ایجاد نمی‌کند.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * همگام‌سازی محصولات با فایل.
 */
class PCS_Sync {

	/**
	 * فیلدهایی که مقدارشان قیمت است.
	 *
	 * @var string[]
	 */
	protected static $price_fields = array( 'regular_price', 'sale_price' );

	/**
	 * ساخت نقشهٔ تغییرات بدون نوشتن در دیتابیس.
	 *
	 * @param string $path  مسیر فایل.
	 * @param string $sheet نام برگه.
	 * @return array|WP_Error
	 */
	public static function plan( $path, $sheet = '' ) {
		$data = PCS_Spreadsheet::read( $path, $sheet );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$mapping = PCS_Mapper::build( $data['headers'] );

		if ( ! in_array( 'sku', $mapping['fields'], true ) ) {
			return new WP_Error(
				'pcs_no_sku',
				__( 'ستون «کد محصول» در فایل پیدا نشد. این ستون کلید یکتای هر محصول است و بدون آن همگام‌سازی ممکن نیست.', 'parsian-catalog-sync' )
			);
		}

		$rows      = array();
		$seen      = array();
		$file_skus = array();

		foreach ( $data['rows'] as $index => $record ) {
			// شمارهٔ سطر برای پیام‌های خطا، با فرض یک سطر سرستون.
			$row = self::plan_row( $record, $mapping, $index + 2 );

			if ( '' !== $row['sku'] ) {
				if ( isset( $seen[ $row['sku'] ] ) ) {
					$row['action'] = 'error';
					$row['errors'][] = sprintf(
						/* translators: 1: کد محصول، 2: شمارهٔ سطر قبلی. */
						__( 'کد محصول «%1$s» تکراری است (سطر %2$d هم همین کد را دارد).', 'parsian-catalog-sync' ),
						$row['sku'],
						$seen[ $row['sku'] ]
					);
				} else {
					$seen[ $row['sku'] ] = $row['row'];
					$file_skus[]         = $row['sku'];
				}
			}

			$rows[] = $row;
		}

		return array(
			'rows'       => $rows,
			'missing'    => self::find_missing( $file_skus ),
			'mapping'    => $mapping,
			'summary'    => self::summarize( $rows ),
			'sheet'      => $sheet,
			'price_unit' => PCS_Settings::instance()->get( 'price_unit' ),
		);
	}

	/**
	 * ساخت نقشهٔ تغییرات یک سطر.
	 *
	 * @param array $record  رکورد سطر.
	 * @param array $mapping نگاشت ستون‌ها.
	 * @param int   $number  شمارهٔ سطر در فایل.
	 * @return array
	 */
	protected static function plan_row( $record, $mapping, $number ) {
		$row = array(
			'row'        => $number,
			'sku'        => '',
			'name'       => '',
			'type'       => '',
			'parent'     => '',
			'action'     => 'unchanged',
			'product_id' => 0,
			'values'     => array(),
			'attributes' => array(),
			'changes'    => array(),
			'errors'     => array(),
			'warnings'   => array(),
		);

		$values = array();

		foreach ( $mapping['fields'] as $header => $field ) {
			$values[ $field ] = isset( $record[ $header ] ) ? $record[ $header ] : '';
		}

		foreach ( $mapping['attributes'] as $header => $label ) {
			$raw = isset( $record[ $header ] ) ? $record[ $header ] : '';

			if ( '' !== trim( $raw ) ) {
				$row['attributes'][ $label ] = PCS_Mapper::split( $raw );
			}
		}

		// ستون‌های جفتیِ صفت در خروجی ووکامرس («نام ۱ صفت» + «مقدار(های) ۱ صفت»).
		if ( ! empty( $mapping['wc_attributes'] ) ) {
			foreach ( $mapping['wc_attributes'] as $group ) {
				$label  = isset( $record[ $group['name'] ] ) ? trim( $record[ $group['name'] ] ) : '';
				$values_raw = isset( $record[ $group['values'] ] ) ? trim( $record[ $group['values'] ] ) : '';

				if ( '' !== $label && '' !== $values_raw ) {
					$row['attributes'][ $label ] = PCS_Mapper::split( $values_raw );
				}
			}
		}

		$row['sku']    = trim( (string) ( isset( $values['sku'] ) ? $values['sku'] : '' ) );
		$row['name']   = trim( (string) ( isset( $values['name'] ) ? $values['name'] : '' ) );
		$row['type']   = strtolower( trim( (string) ( isset( $values['type'] ) ? $values['type'] : '' ) ) );
		$row['parent'] = trim( (string) ( isset( $values['parent'] ) ? $values['parent'] : '' ) );

		if ( '' === $row['sku'] ) {
			$row['action']   = 'error';
			$row['errors'][] = __( 'ستون «کد محصول» خالی است؛ این سطر نادیده گرفته می‌شود.', 'parsian-catalog-sync' );
			return $row;
		}

		$product_id = wc_get_product_id_by_sku( $row['sku'] );
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( $product_id && ! $product ) {
			$row['action']   = 'error';
			$row['errors'][] = __( 'محصولی با این کد در دیتابیس هست ولی خوانده نشد.', 'parsian-catalog-sync' );
			return $row;
		}

		$row['product_id'] = (int) $product_id;

		if ( ! $product && '' === $row['name'] ) {
			$row['action']   = 'error';
			$row['errors'][] = __( 'محصول تازه است و ستون «نام محصول» خالی است.', 'parsian-catalog-sync' );
			return $row;
		}

		// ساخت محصول متغیر یا واریاسیون از روی فایل انجام نمی‌شود: واریاسیون به
		// والد و نگاشت صفت‌ها وابسته است و ساختن نادرستش محصول را خراب می‌کند.
		// به‌روزرسانی واریاسیون‌های موجود (قیمت، موجودی و…) کاملاً پشتیبانی می‌شود.
		if ( ! $product && in_array( $row['type'], array( 'variable', 'variation' ), true ) ) {
			$row['action']   = 'error';
			$row['errors'][] = 'variation' === $row['type']
				? __( 'این سطر یک واریاسیون تازه است. واریاسیون باید ابتدا در پیشخوان ذیل محصول متغیرش ساخته شود؛ بعد از آن قیمت و موجودی‌اش از فایل به‌روز می‌شود.', 'parsian-catalog-sync' )
				: __( 'این سطر یک محصول متغیر تازه است. محصول متغیر و صفت‌هایش باید ابتدا در پیشخوان ساخته شود؛ بعد از آن از فایل به‌روز می‌شود.', 'parsian-catalog-sync' );
			return $row;
		}

		// صفت‌ها فقط برای محصول ساده اعمال می‌شوند و به‌صورت پیش‌فرض خاموش‌اند.
		if ( $row['attributes'] && ( ! PCS_Settings::instance()->get( 'import_attributes' ) || ! self::type_accepts_attributes( $row['type'] ) ) ) {
			$row['attributes'] = array();
		}

		$prepared      = self::prepare_values( $values, $row );
		$row['values'] = $prepared;

		$row['changes'] = $product
			? self::diff_existing( $product, $prepared, $row )
			: self::describe_new( $prepared, $row );

		if ( $row['errors'] ) {
			$row['action'] = 'error';
		} elseif ( ! $product ) {
			$row['action'] = 'create';
		} elseif ( $row['changes'] ) {
			$row['action'] = 'update';
		} else {
			$row['action'] = 'unchanged';
		}

		return $row;
	}

	/**
	 * تبدیل مقادیر خام سلول به مقادیر آمادهٔ ووکامرس.
	 *
	 * @param array $values مقادیر خام.
	 * @param array $row    سطر (برای ثبت خطا/هشدار).
	 * @return array
	 */
	protected static function prepare_values( $values, &$row ) {
		$settings = PCS_Settings::instance();
		$prepared = array();
		$skip     = self::fields_to_skip( $row['type'] );

		foreach ( $values as $field => $raw ) {
			$raw = is_string( $raw ) ? trim( $raw ) : $raw;

			// سلول خالی یعنی «تغییری نده»، نه «خالی کن».
			if ( '' === $raw ) {
				continue;
			}

			// فیلدهایی که برای این نوع محصول بی‌معنا یا خطرناک‌اند نادیده گرفته می‌شوند.
			if ( in_array( $field, $skip, true ) ) {
				continue;
			}

			switch ( $field ) {
				case 'sku':
				case 'type':
				case 'parent':
					break;

				case 'images':
					// ووکامرس همه را در یک ستون می‌دهد: اولی تصویر شاخص، بقیه گالری.
					$list = PCS_Mapper::split( $raw );

					if ( $list ) {
						$prepared['image'] = array_shift( $list );

						if ( $list ) {
							$prepared['gallery'] = $list;
						}
					}
					break;

				case 'regular_price':
				case 'sale_price':
					$number = PCS_Mapper::number( $raw );

					if ( null === $number || $number < 0 ) {
						$row['errors'][] = sprintf(
							/* translators: 1: نام ستون، 2: مقدار سلول. */
							__( 'مقدار ستون «%1$s» عدد معتبری نیست: %2$s', 'parsian-catalog-sync' ),
							self::field_label( $field ),
							$raw
						);
						break;
					}

					$prepared[ $field ] = (string) $settings->to_store_price( $number );
					break;

				case 'stock_quantity':
					$number = PCS_Mapper::number( $raw );

					if ( null === $number ) {
						$row['errors'][] = sprintf(
							/* translators: %s: مقدار سلول. */
							__( 'مقدار ستون «موجودی» عدد معتبری نیست: %s', 'parsian-catalog-sync' ),
							$raw
						);
						break;
					}

					$prepared['stock_quantity'] = (int) $number;
					break;

				case 'stock_status':
					$status = PCS_Mapper::stock_status( $raw );

					if ( null === $status ) {
						$row['warnings'][] = sprintf(
							/* translators: %s: مقدار سلول. */
							__( 'وضعیت موجودی «%s» شناخته نشد و نادیده گرفته شد.', 'parsian-catalog-sync' ),
							$raw
						);
						break;
					}

					$prepared['stock_status'] = $status;
					break;

				case 'status':
					$status = PCS_Mapper::post_status( $raw );

					if ( null === $status ) {
						$row['warnings'][] = sprintf(
							/* translators: %s: مقدار سلول. */
							__( 'وضعیت انتشار «%s» شناخته نشد و نادیده گرفته شد.', 'parsian-catalog-sync' ),
							$raw
						);
						break;
					}

					$prepared['status'] = $status;
					break;

				case 'featured':
					$flag = PCS_Mapper::boolean( $raw );

					if ( null !== $flag ) {
						$prepared['featured'] = $flag;
					}
					break;

				case 'categories':
				case 'tags':
				case 'gallery':
					$prepared[ $field ] = PCS_Mapper::split( $raw );
					break;

				case 'weight':
				case 'length':
				case 'width':
				case 'height':
					$number = PCS_Mapper::number( $raw );

					if ( null !== $number ) {
						$prepared[ $field ] = (string) $number;
					}
					break;

				case 'menu_order':
					$number = PCS_Mapper::number( $raw );

					if ( null !== $number ) {
						$prepared['menu_order'] = (int) $number;
					}
					break;

				default:
					$prepared[ $field ] = $raw;
			}
		}

		if ( isset( $prepared['regular_price'], $prepared['sale_price'] )
			&& (float) $prepared['sale_price'] > (float) $prepared['regular_price'] ) {
			$row['warnings'][] = __( 'قیمت حراج از قیمت اصلی بیشتر است؛ ووکامرس آن را اعمال نمی‌کند.', 'parsian-catalog-sync' );
		}

		return $prepared;
	}


	/**
	 * فیلدهایی که برای یک نوع محصول نباید از فایل اعمال شوند.
	 *
	 * - **متغیر (variable):** قیمت و موجودیِ خودش معنا ندارد؛ این‌ها از واریاسیون‌ها
	 *   می‌آیند. نوشتن قیمت روی والد، در فروشگاه دیده نمی‌شود ولی داده را گمراه‌کننده
	 *   می‌کند.
	 * - **واریاسیون (variation):** دسته‌بندی و برچسب ندارد (از والد می‌گیرد) و نامش
	 *   خودکار از روی صفت‌ها ساخته می‌شود؛ نوشتن این‌ها بی‌اثر یا مخرب است.
	 *
	 * @param string $type نوع محصول در فایل.
	 * @return string[]
	 */
	protected static function fields_to_skip( $type ) {
		if ( 'variable' === $type ) {
			return array( 'regular_price', 'sale_price', 'stock_quantity' );
		}

		if ( 'variation' === $type ) {
			return array( 'categories', 'tags', 'featured', 'name' );
		}

		return array();
	}

	/**
	 * آیا این نوع محصول اجازهٔ اعمال صفت‌ها از فایل را دارد؟
	 *
	 * صفت‌های محصول متغیر، ساختار واریاسیون‌هایش را تعیین می‌کنند؛ بازنویسی‌شان از
	 * روی فایل می‌تواند پیوند واریاسیون‌ها را بشکند، پس فقط محصول ساده مجاز است.
	 *
	 * @param string $type نوع محصول.
	 * @return bool
	 */
	protected static function type_accepts_attributes( $type ) {
		return '' === $type || 'simple' === $type;
	}

	/**
	 * مقایسهٔ مقادیر فایل با محصول موجود.
	 *
	 * @param WC_Product $product محصول.
	 * @param array      $values  مقادیر آماده.
	 * @param array      $row     سطر.
	 * @return array<string,array{from:string,to:string}>
	 */
	protected static function diff_existing( $product, $values, &$row ) {
		$changes = array();

		foreach ( $values as $field => $value ) {
			$current = self::current_value( $product, $field );
			$next    = self::comparable( $field, $value );

			if ( $current !== $next ) {
				$changes[ $field ] = array(
					'from' => $current,
					'to'   => $next,
				);
			}
		}

		foreach ( $row['attributes'] as $label => $terms ) {
			$current = self::current_attribute( $product, $label );
			$next    = implode( '، ', $terms );

			if ( $current !== $next ) {
				$changes[ 'attribute:' . $label ] = array(
					'from' => $current,
					'to'   => $next,
				);
			}
		}

		return $changes;
	}

	/**
	 * توصیف مقادیر یک محصول تازه.
	 *
	 * @param array $values مقادیر آماده.
	 * @param array $row    سطر.
	 * @return array
	 */
	protected static function describe_new( $values, &$row ) {
		$changes = array();

		foreach ( $values as $field => $value ) {
			$changes[ $field ] = array(
				'from' => '',
				'to'   => self::comparable( $field, $value ),
			);
		}

		foreach ( $row['attributes'] as $label => $terms ) {
			$changes[ 'attribute:' . $label ] = array(
				'from' => '',
				'to'   => implode( '، ', $terms ),
			);
		}

		return $changes;
	}

	/**
	 * مقدار فعلی یک فیلد روی محصول، به شکل رشتهٔ قابل مقایسه.
	 *
	 * @param WC_Product $product محصول.
	 * @param string     $field   نام فیلد.
	 * @return string
	 */
	protected static function current_value( $product, $field ) {
		switch ( $field ) {
			case 'categories':
				return self::term_names( $product->get_id(), 'product_cat' );

			case 'tags':
				return self::term_names( $product->get_id(), 'product_tag' );

			case 'image':
				return (string) $product->get_image_id();

			case 'gallery':
				return implode( '،', $product->get_gallery_image_ids() );

			case 'status':
				return (string) $product->get_status();

			case 'featured':
				return $product->get_featured() ? '1' : '0';

			case 'stock_quantity':
				return null === $product->get_stock_quantity() ? '' : (string) (int) $product->get_stock_quantity();

			case 'description':
				return (string) $product->get_description();

			case 'short_description':
				return (string) $product->get_short_description();

			case 'name':
				return (string) $product->get_name();

			case 'menu_order':
				return (string) $product->get_menu_order();

			case 'regular_price':
			case 'sale_price':
				$value = 'regular_price' === $field ? $product->get_regular_price( 'edit' ) : $product->get_sale_price( 'edit' );
				return '' === $value || null === $value ? '' : (string) (float) $value;

			case 'weight':
			case 'length':
			case 'width':
			case 'height':
				$getter = 'weight' === $field ? 'get_weight' : 'get_' . $field;
				$value  = $product->$getter( 'edit' );
				return '' === $value || null === $value ? '' : (string) (float) $value;

			case 'stock_status':
				return (string) $product->get_stock_status();
		}

		return '';
	}

	/**
	 * مقدار جدید به شکل رشتهٔ قابل مقایسه.
	 *
	 * @param string $field فیلد.
	 * @param mixed  $value مقدار.
	 * @return string
	 */
	protected static function comparable( $field, $value ) {
		if ( 'image' === $field ) {
			// مقدار فایل به شناسهٔ پیوست ترجمه می‌شود تا با مقدار فعلی محصول قابل
			// مقایسه باشد؛ وگرنه نشانی با شناسه مقایسه می‌شد و هر بار برای همهٔ
			// محصولات «تغییر تصویر» گزارش می‌شد.
			$id = PCS_Media::peek( $value );

			// پیدا نشد یعنی هنگام اعمال ساخته می‌شود؛ خود مقدار نمایش داده می‌شود.
			return $id ? (string) $id : (string) $value;
		}

		if ( 'gallery' === $field ) {
			$ids = array();

			foreach ( (array) $value as $item ) {
				$id    = PCS_Media::peek( $item );
				$ids[] = $id ? (string) $id : (string) $item;
			}

			return implode( '،', $ids );
		}

		if ( is_array( $value ) ) {
			return implode( '، ', $value );
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( in_array( $field, self::$price_fields, true ) || in_array( $field, array( 'weight', 'length', 'width', 'height' ), true ) ) {
			return '' === $value ? '' : (string) (float) $value;
		}

		return (string) $value;
	}

	/**
	 * نام ترم‌های یک تاکسونومی برای مقایسه.
	 *
	 * @param int    $post_id  شناسهٔ محصول.
	 * @param string $taxonomy تاکسونومی.
	 * @return string
	 */
	protected static function term_names( $post_id, $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );

		if ( is_wp_error( $terms ) || ! $terms ) {
			return '';
		}

		sort( $terms );

		return implode( '، ', $terms );
	}

	/**
	 * مقدار فعلی یک ویژگی روی محصول.
	 *
	 * @param WC_Product $product محصول.
	 * @param string     $label   برچسب ویژگی.
	 * @return string
	 */
	protected static function current_attribute( $product, $label ) {
		foreach ( $product->get_attributes() as $attribute ) {
			$name = $attribute->get_taxonomy() ? wc_attribute_label( $attribute->get_taxonomy() ) : $attribute->get_name();

			if ( PCS_Spreadsheet::normalize_header( $name ) !== PCS_Spreadsheet::normalize_header( $label ) ) {
				continue;
			}

			$options = $attribute->get_taxonomy()
				? wc_get_product_terms( $product->get_id(), $attribute->get_taxonomy(), array( 'fields' => 'names' ) )
				: $attribute->get_options();

			return implode( '، ', (array) $options );
		}

		return '';
	}

	/**
	 * محصولاتی که در فروشگاه هستند ولی در فایل نیستند.
	 *
	 * @param string[] $file_skus کدهای موجود در فایل.
	 * @return array[]
	 */
	protected static function find_missing( $file_skus ) {
		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$missing = array();
		$lookup  = array_flip( $file_skus );

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );

			if ( ! $product ) {
				continue;
			}

			$sku = $product->get_sku();

			// محصول بدون کد را نمی‌توان با فایل تطبیق داد، پس دست‌نخورده می‌ماند.
			if ( '' === $sku || isset( $lookup[ $sku ] ) ) {
				continue;
			}

			$missing[] = array(
				'id'     => $id,
				'sku'    => $sku,
				'name'   => $product->get_name(),
				'status' => $product->get_status(),
			);
		}

		return $missing;
	}

	/**
	 * خلاصهٔ شمارشی از نقشهٔ تغییرات.
	 *
	 * @param array[] $rows سطرها.
	 * @return array<string,int>
	 */
	protected static function summarize( $rows ) {
		$summary = array(
			'create'    => 0,
			'update'    => 0,
			'unchanged' => 0,
			'error'     => 0,
		);

		foreach ( $rows as $row ) {
			if ( isset( $summary[ $row['action'] ] ) ) {
				$summary[ $row['action'] ]++;
			}
		}

		return $summary;
	}

	/**
	 * برچسب فارسی یک فیلد.
	 *
	 * @param string $field نام فیلد.
	 * @return string
	 */
	public static function field_label( $field ) {
		if ( 0 === strpos( $field, 'attribute:' ) ) {
			return sprintf(
				/* translators: %s: نام ویژگی. */
				__( 'ویژگی %s', 'parsian-catalog-sync' ),
				substr( $field, strlen( 'attribute:' ) )
			);
		}

		$labels = array(
			'sku'               => __( 'کد محصول', 'parsian-catalog-sync' ),
			'name'              => __( 'نام محصول', 'parsian-catalog-sync' ),
			'description'       => __( 'توضیحات', 'parsian-catalog-sync' ),
			'short_description' => __( 'توضیح کوتاه', 'parsian-catalog-sync' ),
			'regular_price'     => __( 'قیمت', 'parsian-catalog-sync' ),
			'sale_price'        => __( 'قیمت حراج', 'parsian-catalog-sync' ),
			'stock_quantity'    => __( 'موجودی', 'parsian-catalog-sync' ),
			'stock_status'      => __( 'وضعیت موجودی', 'parsian-catalog-sync' ),
			'categories'        => __( 'دسته‌بندی', 'parsian-catalog-sync' ),
			'tags'              => __( 'برچسب‌ها', 'parsian-catalog-sync' ),
			'image'             => __( 'تصویر شاخص', 'parsian-catalog-sync' ),
			'gallery'           => __( 'گالری', 'parsian-catalog-sync' ),
			'weight'            => __( 'وزن', 'parsian-catalog-sync' ),
			'length'            => __( 'طول', 'parsian-catalog-sync' ),
			'width'             => __( 'عرض', 'parsian-catalog-sync' ),
			'height'            => __( 'ارتفاع', 'parsian-catalog-sync' ),
			'status'            => __( 'وضعیت انتشار', 'parsian-catalog-sync' ),
			'featured'          => __( 'محصول ویژه', 'parsian-catalog-sync' ),
			'menu_order'        => __( 'ترتیب', 'parsian-catalog-sync' ),
		);

		return isset( $labels[ $field ] ) ? $labels[ $field ] : $field;
	}

	/* ------------------------------- اعمال ------------------------------- */

	/**
	 * اعمال نقشهٔ تغییرات روی محصولات.
	 *
	 * @param array $plan نقشهٔ برگشتی از plan().
	 * @return array گزارش اجرا.
	 */
	public static function apply( $plan ) {
		$settings = PCS_Settings::instance();

		$report = array(
			'created'   => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'missing'   => 0,
			'messages'  => array(),
			'timestamp' => current_time( 'mysql' ),
		);

		// در حین درون‌ریزی، بازشماری ترم‌ها و ایندکس‌گذاری به تعویق می‌افتد.
		wc_set_time_limit( 0 );
		wp_defer_term_counting( true );
		wp_suspend_cache_invalidation( true );

		foreach ( $plan['rows'] as $row ) {
			if ( in_array( $row['action'], array( 'error', 'unchanged' ), true ) ) {
				if ( 'error' === $row['action'] ) {
					$report['failed']++;
					foreach ( $row['errors'] as $message ) {
						$report['messages'][] = self::message( 'error', $row['row'], $row['sku'], $message );
					}
				} else {
					$report['skipped']++;
				}
				continue;
			}

			$result = self::write_row( $row );

			if ( is_wp_error( $result ) ) {
				$report['failed']++;
				$report['messages'][] = self::message( 'error', $row['row'], $row['sku'], $result->get_error_message() );
				continue;
			}

			if ( 'create' === $row['action'] ) {
				$report['created']++;
			} else {
				$report['updated']++;
			}

			foreach ( $row['warnings'] as $message ) {
				$report['messages'][] = self::message( 'warning', $row['row'], $row['sku'], $message );
			}
		}

		$report['missing'] = self::handle_missing( $plan['missing'], $settings->get( 'missing_action' ), $report );

		wp_suspend_cache_invalidation( false );
		wp_defer_term_counting( false );

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}

		// بازهٔ قیمت پنل فیلتر پس از تغییر قیمت‌ها باید دوباره محاسبه شود.
		do_action( 'psf_flush_caches' );

		PCS_Settings::instance()->record_run( $report );

		return $report;
	}

	/**
	 * نوشتن یک سطر روی محصول.
	 *
	 * @param array $row سطر نقشه.
	 * @return int|WP_Error شناسهٔ محصول.
	 */
	protected static function write_row( $row ) {
		try {
			return self::write_row_unguarded( $row );
		} catch ( Exception $e ) {
			// ووکامرس برای مقادیر نامعتبر (مثلاً کد محصول تکراری) استثنا پرتاب می‌کند؛
			// یک سطر خراب نباید کل درون‌ریزی را متوقف کند.
			return new WP_Error( 'pcs_write', $e->getMessage() );
		}
	}

	/**
	 * نوشتن یک سطر بدون مدیریت استثنا.
	 *
	 * @param array $row سطر نقشه.
	 * @return int|WP_Error شناسهٔ محصول.
	 * @throws Exception وقتی ووکامرس مقدار را نپذیرد.
	 */
	protected static function write_row_unguarded( $row ) {
		$product = $row['product_id'] ? wc_get_product( $row['product_id'] ) : new WC_Product_Simple();

		if ( ! $product ) {
			return new WP_Error( 'pcs_product', __( 'محصول خوانده نشد.', 'parsian-catalog-sync' ) );
		}

		$values = $row['values'];

		$product->set_sku( $row['sku'] );

		if ( isset( $values['name'] ) ) {
			$product->set_name( $values['name'] );
		}

		if ( isset( $values['description'] ) ) {
			$product->set_description( $values['description'] );
		}

		if ( isset( $values['short_description'] ) ) {
			$product->set_short_description( $values['short_description'] );
		}

		if ( isset( $values['regular_price'] ) ) {
			$product->set_regular_price( $values['regular_price'] );
		}

		if ( array_key_exists( 'sale_price', $values ) ) {
			$product->set_sale_price( $values['sale_price'] );
		}

		if ( isset( $values['stock_quantity'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $values['stock_quantity'] );

			// وقتی ستون «وضعیت موجودی» در فایل نیست، وضعیت از روی تعداد تعیین می‌شود.
			if ( ! isset( $values['stock_status'] ) ) {
				$product->set_stock_status( $values['stock_quantity'] > 0 ? 'instock' : 'outofstock' );
			}
		}

		if ( isset( $values['stock_status'] ) ) {
			$product->set_stock_status( $values['stock_status'] );
		}

		if ( isset( $values['status'] ) ) {
			$product->set_status( $values['status'] );
		} elseif ( ! $row['product_id'] ) {
			$product->set_status( 'publish' );
		}

		if ( isset( $values['featured'] ) ) {
			$product->set_featured( (bool) $values['featured'] );
		}

		foreach ( array( 'weight', 'length', 'width', 'height' ) as $field ) {
			if ( isset( $values[ $field ] ) ) {
				$setter = 'weight' === $field ? 'set_weight' : 'set_' . $field;
				$product->$setter( $values[ $field ] );
			}
		}

		if ( isset( $values['menu_order'] ) ) {
			$product->set_menu_order( $values['menu_order'] );
		}

		if ( isset( $values['categories'] ) ) {
			$product->set_category_ids( self::term_ids( $values['categories'], 'product_cat' ) );
		}

		if ( isset( $values['tags'] ) ) {
			$product->set_tag_ids( self::term_ids( $values['tags'], 'product_tag' ) );
		}

		if ( $row['attributes'] ) {
			$attributes = self::build_attributes( $row['attributes'], $product );

			if ( $attributes ) {
				$product->set_attributes( $attributes );
			}
		}

		$warnings = array();

		if ( isset( $values['image'] ) ) {
			$image = PCS_Media::resolve( $values['image'], $row['name'] );

			if ( is_wp_error( $image ) ) {
				$warnings[] = $image->get_error_message();
			} else {
				$product->set_image_id( $image );
			}
		}

		if ( isset( $values['gallery'] ) ) {
			$gallery = array();

			foreach ( $values['gallery'] as $item ) {
				$id = PCS_Media::resolve( $item, $row['name'] );

				if ( is_wp_error( $id ) ) {
					$warnings[] = $id->get_error_message();
					continue;
				}

				$gallery[] = $id;
			}

			if ( $gallery ) {
				$product->set_gallery_image_ids( $gallery );
			}
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			return new WP_Error( 'pcs_save', __( 'ذخیرهٔ محصول ناموفق بود.', 'parsian-catalog-sync' ) );
		}

		// هشدارهای رسانه پس از ذخیره گزارش می‌شوند تا بقیهٔ فیلدها از دست نروند.
		foreach ( $warnings as $warning ) {
			$row['warnings'][] = $warning;
		}

		update_post_meta( $product_id, '_pcs_synced_at', current_time( 'mysql' ) );

		return $product_id;
	}

	/**
	 * تبدیل نام دسته/برچسب به شناسهٔ ترم — با ساخت ترم‌های نبوده.
	 *
	 * نام‌های سلسله‌مراتبی با «>» جدا می‌شوند: «کارتن اسباب‌کشی>۵ لایه».
	 *
	 * @param string[] $names    نام‌ها.
	 * @param string   $taxonomy تاکسونومی.
	 * @return int[]
	 */
	protected static function term_ids( $names, $taxonomy ) {
		$ids = array();

		foreach ( $names as $name ) {
			$parent = 0;
			$levels = array_map( 'trim', explode( '>', $name ) );

			foreach ( $levels as $level ) {
				if ( '' === $level ) {
					continue;
				}

				$term = get_term_by( 'name', $level, $taxonomy );

				if ( ! $term ) {
					$term = get_term_by( 'slug', sanitize_title( $level ), $taxonomy );
				}

				if ( $term && ( 0 === $parent || (int) $term->parent === $parent ) ) {
					$parent = (int) $term->term_id;
					continue;
				}

				$created = wp_insert_term( $level, $taxonomy, array( 'parent' => $parent ) );

				if ( is_wp_error( $created ) ) {
					// ترم موجود با والد دیگر — همان را برمی‌داریم.
					$existing = $created->get_error_data( 'term_exists' );
					$parent   = $existing ? (int) $existing : $parent;
					continue;
				}

				$parent = (int) $created['term_id'];
			}

			if ( $parent ) {
				$ids[] = $parent;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * ساخت ویژگی‌های محصول از ستون‌های «ویژگی: …».
	 *
	 * ویژگی‌های سراسری (pa_*) ترجیح داده می‌شوند چون فقط آن‌ها در پنل فیلتر
	 * فروشگاه قابل استفاده‌اند؛ در نبودشان ویژگی سراسری ساخته می‌شود.
	 *
	 * @param array      $attributes نگاشت برچسب → مقادیر.
	 * @param WC_Product $product    محصول.
	 * @return WC_Product_Attribute[]
	 */
	protected static function build_attributes( $attributes, $product ) {
		$objects  = array();
		$position = 0;

		foreach ( $attributes as $label => $options ) {
			$taxonomy = self::attribute_taxonomy( $label );
			$object   = new WC_Product_Attribute();

			$object->set_name( $taxonomy ? $taxonomy : $label );
			$object->set_position( $position++ );
			$object->set_visible( true );
			$object->set_variation( false );

			if ( $taxonomy ) {
				$term_ids = array();

				foreach ( $options as $option ) {
					$term = get_term_by( 'name', $option, $taxonomy );

					if ( ! $term ) {
						$created = wp_insert_term( $option, $taxonomy );
						if ( is_wp_error( $created ) ) {
							continue;
						}
						$term_ids[] = (int) $created['term_id'];
						continue;
					}

					$term_ids[] = (int) $term->term_id;
				}

				if ( ! $term_ids ) {
					continue;
				}

				$object->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
				$object->set_options( $term_ids );
			} else {
				$object->set_id( 0 );
				$object->set_options( $options );
			}

			$objects[] = $object;
		}

		// ویژگی‌هایی که در فایل نیستند دست‌نخورده می‌مانند.
		foreach ( $product->get_attributes() as $key => $existing ) {
			$name = $existing->get_taxonomy() ? wc_attribute_label( $existing->get_taxonomy() ) : $existing->get_name();
			$seen = false;

			foreach ( array_keys( $attributes ) as $label ) {
				if ( PCS_Spreadsheet::normalize_header( $name ) === PCS_Spreadsheet::normalize_header( $label ) ) {
					$seen = true;
					break;
				}
			}

			if ( ! $seen ) {
				$objects[] = $existing;
			}
		}

		return $objects;
	}

	/**
	 * یافتن (یا ساخت) تاکسونومی ویژگی برای یک برچسب.
	 *
	 * @param string $label برچسب ویژگی.
	 * @return string نام تاکسونومی یا رشتهٔ خالی.
	 */
	protected static function attribute_taxonomy( $label ) {
		$normalized = PCS_Spreadsheet::normalize_header( $label );

		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			if ( PCS_Spreadsheet::normalize_header( $attribute->attribute_label ) === $normalized ) {
				return wc_attribute_taxonomy_name( $attribute->attribute_name );
			}
		}

		if ( ! PCS_Settings::instance()->get( 'create_attributes' ) ) {
			return '';
		}

		$slug = sanitize_title( $label );

		// نام تاکسونومی ووکامرس حداکثر ۲۸ نویسه دارد (pa_ + ۲۸ = ۳۲).
		$slug = substr( $slug, 0, 28 );

		if ( '' === $slug ) {
			return '';
		}

		$id = wc_create_attribute(
			array(
				'name'         => $label,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		if ( is_wp_error( $id ) ) {
			return '';
		}

		$taxonomy = wc_attribute_taxonomy_name( $slug );

		// تاکسونومی تازه در همین درخواست ثبت نشده است؛ بدون ثبت، افزودن ترم شکست می‌خورد.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				'product',
				array(
					'hierarchical' => false,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			);
		}

		return $taxonomy;
	}

	/**
	 * رسیدگی به محصولاتی که در فایل نیستند.
	 *
	 * @param array[] $missing محصولات غایب.
	 * @param string  $action  رفتار انتخابی.
	 * @param array   $report  گزارش (با ارجاع).
	 * @return int تعداد محصولات تغییریافته.
	 */
	protected static function handle_missing( $missing, $action, &$report ) {
		if ( 'none' === $action || ! $missing ) {
			return 0;
		}

		$count = 0;

		foreach ( $missing as $item ) {
			$product = wc_get_product( $item['id'] );

			if ( ! $product ) {
				continue;
			}

			if ( 'draft' === $action ) {
				if ( 'draft' === $product->get_status() ) {
					continue;
				}
				$product->set_status( 'draft' );
			} elseif ( 'outofstock' === $action ) {
				if ( 'outofstock' === $product->get_stock_status() ) {
					continue;
				}
				$product->set_stock_status( 'outofstock' );
			} elseif ( 'trash' === $action ) {
				wp_trash_post( $item['id'] );
				$count++;
				$report['messages'][] = self::message(
					'warning',
					0,
					$item['sku'],
					__( 'محصول در فایل نبود و به زباله‌دان منتقل شد.', 'parsian-catalog-sync' )
				);
				continue;
			} else {
				continue;
			}

			$product->save();
			$count++;
		}

		return $count;
	}

	/**
	 * ساخت یک پیام گزارش.
	 *
	 * @param string $type    نوع پیام.
	 * @param int    $row     شمارهٔ سطر.
	 * @param string $sku     کد محصول.
	 * @param string $message متن.
	 * @return array
	 */
	protected static function message( $type, $row, $sku, $message ) {
		return array(
			'type' => $type,
			'row'  => (int) $row,
			'sku'  => (string) $sku,
			'text' => (string) $message,
		);
	}
}
