<?php
/**
 * خروجی CSV از درخواست‌های پیش‌فروش.
 *
 * همان قواعد فایل کاتالوگ: BOM برای اکسل، پایان خط \r\n و خنثی‌سازی سلول‌هایی
 * که اکسل آن‌ها را فرمول می‌بیند.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * برون‌ریزی درخواست‌ها.
 */
class PPO_Export {

	/**
	 * سرستون‌های فایل.
	 *
	 * @return string[]
	 */
	public static function headers() {
		return array(
			__( 'شماره', 'parsian-preorder' ),
			__( 'تاریخ ثبت', 'parsian-preorder' ),
			__( 'وضعیت', 'parsian-preorder' ),
			__( 'محصول', 'parsian-preorder' ),
			__( 'کد محصول', 'parsian-preorder' ),
			__( 'تعداد (بسته)', 'parsian-preorder' ),
			__( 'قیمت واحد', 'parsian-preorder' ),
			__( 'ارزش تقریبی', 'parsian-preorder' ),
			__( 'نام مشتری', 'parsian-preorder' ),
			__( 'شرکت', 'parsian-preorder' ),
			__( 'تلفن', 'parsian-preorder' ),
			__( 'شهر', 'parsian-preorder' ),
			__( 'توضیحات', 'parsian-preorder' ),
			__( 'تاریخ عرضه', 'parsian-preorder' ),
			__( 'زمان آماده‌سازی (روز)', 'parsian-preorder' ),
			__( 'مسئول پیگیری', 'parsian-preorder' ),
			__( 'پیگیری بعدی', 'parsian-preorder' ),
			__( 'شمارهٔ سفارش', 'parsian-preorder' ),
		);
	}

	/**
	 * یک سطر از روی درخواست.
	 *
	 * @param PPO_Request $request درخواست.
	 * @return string[]
	 */
	public static function row( $request ) {
		$product  = $request->get_product();
		$assignee = $request->get_assignee();
		$order    = $request->get_order();
		$followup = (string) $request->get( 'followup_date' );
		$release  = (string) $request->get( 'release_date' );

		return array(
			(string) $request->get_id(),
			ppo_jalali_date( $request->get_date(), true ),
			PPO_Status::label( $request->get_status() ),
			$request->get_product_name(),
			$product ? (string) $product->get_sku() : '',
			(string) $request->get( 'quantity', 1 ),
			(string) $request->get( 'unit_price', 0 ),
			(string) $request->get_value(),
			(string) $request->get( 'name' ),
			(string) $request->get( 'company' ),
			ppo_display_phone( $request->get( 'phone' ) ),
			(string) $request->get( 'city' ),
			(string) $request->get( 'note' ),
			'' !== $release ? ppo_jalali_date( $release ) : '',
			(string) $request->get( 'lead_days', 0 ),
			$assignee ? $assignee->display_name : '',
			'' !== $followup ? ppo_jalali_date( $followup ) : '',
			$order ? (string) $order->get_order_number() : '',
		);
	}

	/**
	 * فرستادن فایل به مرورگر.
	 *
	 * @param array $args فیلترها: status، product، search، ids.
	 */
	public static function stream( $args = array() ) {
		$requests = self::query( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="preorders-' . gmdate( 'Y-m-d-Hi' ) . '.csv"' );

		$handle = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// BOM — بدون آن اکسل ویندوز متن فارسی را درهم نشان می‌دهد.
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $handle, self::line( self::headers() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		foreach ( $requests as $request ) {
			fwrite( $handle, self::line( self::row( $request ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * ساخت متن CSV در حافظه — برای آزمون.
	 *
	 * @param array $args فیلترها.
	 * @return string
	 */
	public static function to_csv( $args = array() ) {
		$csv = self::line( self::headers() );

		foreach ( self::query( $args ) as $request ) {
			$csv .= self::line( self::row( $request ) );
		}

		return $csv;
	}

	/**
	 * خواندن درخواست‌ها بر پایهٔ فیلترها.
	 *
	 * @param array $args فیلترها.
	 * @return PPO_Request[]
	 */
	protected static function query( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'status'  => '',
				'product' => 0,
				'search'  => '',
				'ids'     => array(),
			)
		);

		if ( $args['ids'] ) {
			$requests = array();

			foreach ( (array) $args['ids'] as $id ) {
				$request = PPO_Request::find( $id );

				if ( $request ) {
					$requests[] = $request;
				}
			}

			return $requests;
		}

		$query = array(
			'post_type'      => PPO_Post_Type::POST_TYPE,
			'post_status'    => PPO_Status::exists( $args['status'] )
				? array( $args['status'] )
				: array_merge( PPO_Status::keys(), array( 'private' ) ),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		if ( $args['product'] ) {
			$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_preorder_product_id',
					'value' => (int) $args['product'],
				),
			);
		}

		if ( '' !== $args['search'] ) {
			$query['s'] = $args['search'];
		}

		return array_map(
			static function ( $post ) {
				return new PPO_Request( $post );
			},
			get_posts( $query )
		);
	}

	/* ------------------------------ نوشتن ------------------------------ */

	/**
	 * ساخت یک سطر CSV بر پایهٔ RFC 4180 با پایان خط ویندوزی.
	 *
	 * @param array $row مقادیر.
	 * @return string
	 */
	public static function line( $row ) {
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
	 * @param mixed $value مقدار.
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

		if ( '-' === $first && ! is_numeric( $value ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
