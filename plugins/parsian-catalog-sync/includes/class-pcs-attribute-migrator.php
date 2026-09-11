<?php
/**
 * تبدیل صفت محلی محصول به صفت سراسری (تاکسونومی `pa_*`).
 *
 * چرا لازم است: فیلتر فروشگاه فقط روی صفت سراسری کار می‌کند، چون صفت محلی داخل
 * متای خود محصول ذخیره می‌شود و تاکسونومی ندارد؛ فیلتر کردن رویش نیازمند
 * پرس‌وجوی LIKE روی متاست که هم کند است هم شکننده.
 *
 * چرا حساس است: صفت یک محصول متغیر تعیین می‌کند چه واریاسیون‌هایی وجود دارد.
 * هر واریاسیون با متای `attribute_<کلید>` به مقدار صفت وصل است و برای صفت
 * سراسری، مقدارِ ذخیره‌شده **اسلاگ ترم** است نه برچسب. اگر کلید یا مقدار
 * جابه‌جا بماند، واریاسیون از والدش جدا می‌افتد و محصول قابل خرید نمی‌ماند.
 *
 * به همین دلیل همه‌چیز از راه CRUD ووکامرس انجام می‌شود (نه دست‌کاری مستقیم
 * متا): ووکامرس خودش `_product_attributes` را می‌نویسد، ترم‌ها را به محصول وصل
 * می‌کند و متاهای قدیمی واریاسیون را پاک می‌کند.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * انتقال صفت محلی به سراسری.
 */
class PCS_Attribute_Migrator {

	/**
	 * بیشینهٔ طول اسلاگ صفت — نام تاکسونومی ووکامرس ۳۲ نویسه است (`pa_` + ۲۸).
	 */
	const MAX_SLUG = 28;

	/**
	 * یافتن همهٔ صفت‌های محلیِ به‌کاررفته در محصولات.
	 *
	 * @return array<string,array{label:string,products:int,variable:int,options:string[]}>
	 */
	public static function scan() {
		$found = array();

		foreach ( self::product_ids() as $product_id ) {
			$product = wc_get_product( $product_id );

			// صفت روی والد تعریف می‌شود؛ واریاسیون فقط مقدارش را نگه می‌دارد.
			if ( ! $product || $product->is_type( 'variation' ) ) {
				continue;
			}

			foreach ( $product->get_attributes() as $attribute ) {
				// واریاسیون به‌جای شیء صفت، رشتهٔ مقدار برمی‌گرداند.
				if ( ! $attribute instanceof WC_Product_Attribute || $attribute->is_taxonomy() ) {
					continue;
				}

				$label = $attribute->get_name();
				$key   = self::normalize( $label );

				if ( ! isset( $found[ $key ] ) ) {
					$found[ $key ] = array(
						'label'    => $label,
						'products' => 0,
						'variable' => 0,
						'options'  => array(),
					);
				}

				$found[ $key ]['products']++;

				if ( $product->is_type( 'variable' ) ) {
					$found[ $key ]['variable']++;
				}

				foreach ( $attribute->get_options() as $option ) {
					$option = trim( (string) $option );

					if ( '' !== $option && ! in_array( $option, $found[ $key ]['options'], true ) ) {
						$found[ $key ]['options'][] = $option;
					}
				}
			}
		}

		return $found;
	}

	/**
	 * ساخت نقشهٔ انتقال، بدون نوشتن در دیتابیس.
	 *
	 * @param string $label برچسب صفت محلی (مثلاً «نوع»).
	 * @param string $slug  اسلاگ لاتین تاکسونومی مقصد (مثلاً type).
	 * @return array|WP_Error
	 */
	public static function plan( $label, $slug ) {
		$label = trim( (string) $label );
		$slug  = self::clean_slug( $slug );

		if ( '' === $label ) {
			return new WP_Error( 'pcs_attr_label', __( 'نام صفت مشخص نشده است.', 'parsian-catalog-sync' ) );
		}

		if ( '' === $slug ) {
			return new WP_Error( 'pcs_attr_slug', __( 'اسلاگ صفت باید با حروف لاتین، عدد یا خط تیره نوشته شود (مثلاً type).', 'parsian-catalog-sync' ) );
		}

		$taxonomy = wc_attribute_taxonomy_name( $slug );
		$existing = wc_attribute_taxonomy_id_by_name( $slug );

		$plan = array(
			'label'       => $label,
			'slug'        => $slug,
			'taxonomy'    => $taxonomy,
			'creates_tax' => ! $existing,
			'terms'       => array(),
			'products'    => array(),
			'conflicts'   => array(),
			'variations'  => 0,
		);

		$needle = self::normalize( $label );

		foreach ( self::product_ids() as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || $product->is_type( 'variation' ) ) {
				continue;
			}

			$match = null;

			foreach ( $product->get_attributes() as $key => $attribute ) {
				if ( $attribute instanceof WC_Product_Attribute
					&& ! $attribute->is_taxonomy()
					&& self::normalize( $attribute->get_name() ) === $needle ) {
					$match = array(
						'key'       => $key,
						'attribute' => $attribute,
					);
					break;
				}
			}

			if ( ! $match ) {
				continue;
			}

			$options = array();

			foreach ( $match['attribute']->get_options() as $option ) {
				$option = trim( (string) $option );

				if ( '' === $option ) {
					continue;
				}

				$options[] = $option;

				if ( ! in_array( $option, $plan['terms'], true ) ) {
					$plan['terms'][] = $option;
				}
			}

			$entry = array(
				'id'         => $product_id,
				'name'       => $product->get_name(),
				'type'       => $product->get_type(),
				'key'        => $match['key'],
				'options'    => $options,
				'variations' => array(),
			);

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );

					if ( ! $variation ) {
						continue;
					}

					$value = self::variation_value( $variation, $match['key'] );

					// مقدار خالی یعنی «هر ...»؛ همان‌طور خالی می‌ماند.
					if ( '' !== $value && ! self::option_exists( $value, $options ) ) {
						$plan['conflicts'][] = array(
							'product'   => $product->get_name(),
							'variation' => $variation_id,
							'value'     => $value,
						);
						continue;
					}

					$entry['variations'][] = array(
						'id'    => $variation_id,
						'value' => $value,
					);
					$plan['variations']++;
				}
			}

			$plan['products'][] = $entry;
		}

		return $plan;
	}

	/**
	 * اجرای انتقال.
	 *
	 * @param array $plan نقشهٔ برگشتی از plan().
	 * @return array|WP_Error گزارش.
	 */
	public static function apply( $plan ) {
		if ( $plan['conflicts'] ) {
			return new WP_Error(
				'pcs_attr_conflicts',
				__( 'تا وقتی واریاسیون‌های ناسازگار اصلاح نشده‌اند، انتقال انجام نمی‌شود.', 'parsian-catalog-sync' )
			);
		}

		$taxonomy = self::ensure_taxonomy( $plan['label'], $plan['slug'] );

		if ( is_wp_error( $taxonomy ) ) {
			return $taxonomy;
		}

		$terms = self::ensure_terms( $taxonomy, $plan['terms'] );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$report = array(
			'taxonomy'   => $taxonomy,
			'terms'      => count( $terms ),
			'products'   => 0,
			'variations' => 0,
			'failed'     => array(),
		);

		foreach ( $plan['products'] as $entry ) {
			$result = self::convert_product( $entry, $taxonomy, $terms );

			if ( is_wp_error( $result ) ) {
				$report['failed'][] = array(
					'name'    => $entry['name'],
					'message' => $result->get_error_message(),
				);
				continue;
			}

			$report['products']++;
			$report['variations'] += $result;
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}

		do_action( 'psf_flush_caches' );

		return $report;
	}

	/* ------------------------------- جزئیات ------------------------------- */

	/**
	 * تبدیل یک محصول و واریاسیون‌هایش.
	 *
	 * @param array  $entry    اطلاعات محصول از نقشه.
	 * @param string $taxonomy تاکسونومی مقصد.
	 * @param array  $terms    نگاشت «مقدار نرمال‌شده» به ترم.
	 * @return int|WP_Error تعداد واریاسیون‌های به‌روزشده.
	 */
	protected static function convert_product( $entry, $taxonomy, $terms ) {
		$product = wc_get_product( $entry['id'] );

		if ( ! $product ) {
			return new WP_Error( 'pcs_attr_product', __( 'محصول خوانده نشد.', 'parsian-catalog-sync' ) );
		}

		$attributes = $product->get_attributes();

		if ( ! isset( $attributes[ $entry['key'] ] ) ) {
			return new WP_Error( 'pcs_attr_gone', __( 'صفت محلی روی این محصول پیدا نشد؛ شاید پیش‌تر تغییر کرده است.', 'parsian-catalog-sync' ) );
		}

		$old      = $attributes[ $entry['key'] ];
		$term_ids = array();

		foreach ( $entry['options'] as $option ) {
			$key = self::normalize( $option );

			if ( isset( $terms[ $key ] ) ) {
				$term_ids[] = (int) $terms[ $key ]->term_id;
			}
		}

		if ( ! $term_ids ) {
			return new WP_Error( 'pcs_attr_terms', __( 'هیچ مقداری برای این صفت روی محصول نبود.', 'parsian-catalog-sync' ) );
		}

		// صفت تازه جای صفت محلی می‌نشیند و موقعیت/نمایان بودن/واریاسیونی بودن حفظ می‌شود.
		$replacement = new WC_Product_Attribute();
		$replacement->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$replacement->set_name( $taxonomy );
		$replacement->set_options( $term_ids );
		$replacement->set_position( $old->get_position() );
		$replacement->set_visible( $old->get_visible() );
		$replacement->set_variation( $old->get_variation() );

		$rebuilt = array();

		foreach ( $attributes as $key => $attribute ) {
			if ( $key === $entry['key'] ) {
				$rebuilt[ $taxonomy ] = $replacement;
				continue;
			}

			$rebuilt[ $key ] = $attribute;
		}

		$product->set_attributes( $rebuilt );
		$product->save();

		// واریاسیون‌ها باید پس از والد به‌روز شوند تا ترم‌ها از قبل وصل شده باشند.
		$updated = 0;

		foreach ( $entry['variations'] as $item ) {
			if ( self::convert_variation( $item, $entry['key'], $taxonomy, $terms ) ) {
				$updated++;
			}
		}

		return $updated;
	}

	/**
	 * تبدیل یک واریاسیون: کلید صفت و مقدارش.
	 *
	 * @param array  $item     اطلاعات واریاسیون.
	 * @param string $old_key  کلید صفت محلی.
	 * @param string $taxonomy تاکسونومی مقصد.
	 * @param array  $terms    نگاشت مقدار به ترم.
	 * @return bool
	 */
	protected static function convert_variation( $item, $old_key, $taxonomy, $terms ) {
		$variation = wc_get_product( $item['id'] );

		if ( ! $variation ) {
			return false;
		}

		$key = self::normalize( $item['value'] );

		// برای صفت سراسری، مقدار ذخیره‌شدهٔ واریاسیون باید اسلاگ ترم باشد نه برچسب.
		$value = ( '' !== $item['value'] && isset( $terms[ $key ] ) ) ? $terms[ $key ]->slug : '';

		$rebuilt = array();

		foreach ( $variation->get_attributes() as $name => $current ) {
			if ( self::same_key( $name, $old_key ) ) {
				$rebuilt[ $taxonomy ] = $value;
				continue;
			}

			$rebuilt[ $name ] = $current;
		}

		// اگر واریاسیون اصلاً این صفت را نداشت، همچنان باید مقدارش تعیین شود.
		if ( ! isset( $rebuilt[ $taxonomy ] ) ) {
			$rebuilt[ $taxonomy ] = $value;
		}

		$variation->set_attributes( $rebuilt );
		$variation->save();

		return true;
	}

	/**
	 * ساخت تاکسونومی صفت در صورت نبودن، و ثبت آن در همین درخواست.
	 *
	 * @param string $label برچسب نمایشی.
	 * @param string $slug  اسلاگ.
	 * @return string|WP_Error نام تاکسونومی.
	 */
	protected static function ensure_taxonomy( $label, $slug ) {
		$taxonomy = wc_attribute_taxonomy_name( $slug );

		if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
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
				return $id;
			}
		}

		// تاکسونومی تازه در این درخواست ثبت نشده است؛ بدون ثبت، افزودن ترم شکست می‌خورد.
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
	 * ساخت ترم‌ها و بازگرداندن نگاشت «مقدار نرمال‌شده» به ترم.
	 *
	 * @param string   $taxonomy تاکسونومی.
	 * @param string[] $options  مقادیر.
	 * @return array<string,WP_Term>|WP_Error
	 */
	protected static function ensure_terms( $taxonomy, $options ) {
		$map = array();

		foreach ( $options as $option ) {
			$term = get_term_by( 'name', $option, $taxonomy );

			if ( ! $term ) {
				$created = wp_insert_term( $option, $taxonomy );

				if ( is_wp_error( $created ) ) {
					$existing = $created->get_error_data( 'term_exists' );

					if ( ! $existing ) {
						return $created;
					}

					$term = get_term( (int) $existing, $taxonomy );
				} else {
					$term = get_term( (int) $created['term_id'], $taxonomy );
				}
			}

			if ( $term && ! is_wp_error( $term ) ) {
				$map[ self::normalize( $option ) ] = $term;
			}
		}

		return $map;
	}

	/**
	 * مقدار یک صفت روی واریاسیون.
	 *
	 * @param WC_Product_Variation $variation واریاسیون.
	 * @param string               $key       کلید صفت والد.
	 * @return string
	 */
	protected static function variation_value( $variation, $key ) {
		foreach ( $variation->get_attributes() as $name => $value ) {
			if ( self::same_key( $name, $key ) ) {
				return trim( (string) $value );
			}
		}

		return '';
	}

	/**
	 * مقایسهٔ کلید صفت والد با کلید ذخیره‌شده روی واریاسیون.
	 *
	 * ووکامرس کلید واریاسیون را با sanitize_title می‌سازد، پس دو نگارش ممکن است
	 * ظاهراً متفاوت ولی یکی باشند.
	 *
	 * @param string $a کلید نخست.
	 * @param string $b کلید دوم.
	 * @return bool
	 */
	protected static function same_key( $a, $b ) {
		return $a === $b || sanitize_title( $a ) === sanitize_title( $b ) || urldecode( $a ) === urldecode( $b );
	}

	/**
	 * آیا مقدار واریاسیون در فهرست مقادیر صفت هست؟
	 *
	 * @param string   $value   مقدار.
	 * @param string[] $options مقادیر مجاز.
	 * @return bool
	 */
	protected static function option_exists( $value, $options ) {
		$needle = self::normalize( $value );

		foreach ( $options as $option ) {
			if ( self::normalize( $option ) === $needle || sanitize_title( $option ) === $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * یکدست‌سازی برای مقایسه.
	 *
	 * @param string $text ورودی.
	 * @return string
	 */
	protected static function normalize( $text ) {
		$text = PCS_Spreadsheet::normalize_header( $text );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * پاک‌سازی اسلاگ پیشنهادی.
	 *
	 * @param string $slug اسلاگ خام.
	 * @return string
	 */
	public static function clean_slug( $slug ) {
		$slug = strtolower( trim( (string) $slug ) );
		$slug = preg_replace( '/[^a-z0-9_-]+/', '-', $slug );
		$slug = trim( (string) $slug, '-' );

		return substr( (string) $slug, 0, self::MAX_SLUG );
	}

	/**
	 * شناسهٔ همهٔ محصولات.
	 *
	 * @return int[]
	 */
	protected static function product_ids() {
		return get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
	}
}
