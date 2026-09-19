<?php
/**
 * آمار داشبورد پیش‌فروش.
 *
 * همهٔ شمارش‌ها با پرس‌وجوی مستقیم انجام می‌شوند (نه بارگذاری همهٔ درخواست‌ها)
 * تا داشبورد با چند هزار درخواست هم سریع بماند.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * سنجه‌ها.
 */
class PPO_Metrics {

	/**
	 * شمار درخواست‌ها به تفکیک وضعیت.
	 *
	 * @return array<string,int>
	 */
	public static function counts_by_status() {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT post_status, COUNT(*) AS total
				 FROM {$wpdb->posts}
				 WHERE post_type = %s
				 GROUP BY post_status",
				PPO_Post_Type::POST_TYPE
			),
			ARRAY_A
		);

		$counts = array_fill_keys( PPO_Status::keys(), 0 );

		foreach ( (array) $rows as $row ) {
			$status = $row['post_status'];

			// درخواست‌های افزونهٔ قدیمی (post_status = private) جدید شمرده می‌شوند.
			if ( ! isset( $counts[ $status ] ) ) {
				$status = PPO_Status::NEW_REQUEST;
			}

			$counts[ $status ] += (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * شمار درخواست‌های ثبت‌شده در بازهٔ اخیر.
	 *
	 * @param int $days تعداد روز.
	 * @return int
	 */
	public static function count_since( $days ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - (int) $days * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_date >= %s",
				PPO_Post_Type::POST_TYPE,
				$since
			)
		);
	}

	/**
	 * مجموع ارزش تقریبی درخواست‌ها.
	 *
	 * @param string[] $statuses وضعیت‌های موردنظر (خالی = همه).
	 * @param int      $days     فقط درخواست‌های این چند روز اخیر (۰ = همه).
	 * @return float
	 */
	public static function total_value( $statuses = array(), $days = 0 ) {
		global $wpdb;

		$where  = array( 'posts.post_type = %s' );
		$params = array( PPO_Post_Type::POST_TYPE );

		if ( $statuses ) {
			$where[]  = 'posts.post_status IN ( ' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )';
			$params   = array_merge( $params, $statuses );
		}

		if ( $days > 0 ) {
			$where[]  = 'posts.post_date >= %s';
			$params[] = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - (int) $days * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- جانگهدارها بالا ساخته شده‌اند.
		$sql = $wpdb->prepare(
			"SELECT SUM( CAST( qty.meta_value AS DECIMAL(20,4) ) * CAST( price.meta_value AS DECIMAL(20,4) ) )
			 FROM {$wpdb->posts} AS posts
			 INNER JOIN {$wpdb->postmeta} AS qty ON qty.post_id = posts.ID AND qty.meta_key = '_preorder_qty'
			 INNER JOIN {$wpdb->postmeta} AS price ON price.post_id = posts.ID AND price.meta_key = '_preorder_unit_price'
			 WHERE " . implode( ' AND ', $where ),
			$params
		);
		// phpcs:enable

		return (float) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * پرفروش‌ترین محصولات پیش‌فروش.
	 *
	 * @param int $limit تعداد.
	 * @return array[] هر سطر: product_id، requests، packages.
	 */
	public static function top_products( $limit = 5 ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT product.meta_value AS product_id,
				        COUNT(*) AS requests,
				        SUM( CAST( qty.meta_value AS UNSIGNED ) ) AS packages
				 FROM {$wpdb->posts} AS posts
				 INNER JOIN {$wpdb->postmeta} AS product ON product.post_id = posts.ID AND product.meta_key = '_preorder_product_id'
				 LEFT JOIN {$wpdb->postmeta} AS qty ON qty.post_id = posts.ID AND qty.meta_key = '_preorder_qty'
				 WHERE posts.post_type = %s
				 GROUP BY product.meta_value
				 ORDER BY requests DESC
				 LIMIT %d",
				PPO_Post_Type::POST_TYPE,
				(int) $limit
			),
			ARRAY_A
		);

		$result = array();

		foreach ( (array) $rows as $row ) {
			$result[] = array(
				'product_id' => (int) $row['product_id'],
				'requests'   => (int) $row['requests'],
				'packages'   => (int) $row['packages'],
			);
		}

		return $result;
	}

	/**
	 * درخواست‌های باز که پیگیری‌شان عقب افتاده است.
	 *
	 * @param int $limit سقف تعداد.
	 * @return PPO_Request[]
	 */
	public static function overdue( $limit = 10 ) {
		// معیار عقب‌افتادگی در خود PPO_Request::is_overdue() است؛ اینجا فقط
		// درخواست‌های باز، از قدیمی‌ترین، غربال می‌شوند.
		$posts = get_posts(
			array(
				'post_type'      => PPO_Post_Type::POST_TYPE,
				'post_status'    => PPO_Status::open_keys(),
				'posts_per_page' => (int) $limit * 4,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$overdue = array();

		foreach ( $posts as $post ) {
			$request = new PPO_Request( $post );

			if ( $request->is_overdue() ) {
				$overdue[] = $request;
			}

			if ( count( $overdue ) >= $limit ) {
				break;
			}
		}

		return $overdue;
	}

	/**
	 * تازه‌ترین درخواست‌ها.
	 *
	 * @param int $limit تعداد.
	 * @return PPO_Request[]
	 */
	public static function recent( $limit = 8 ) {
		$posts = get_posts(
			array(
				'post_type'      => PPO_Post_Type::POST_TYPE,
				'post_status'    => array_merge( PPO_Status::keys(), array( 'private' ) ),
				'posts_per_page' => (int) $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		return array_map(
			static function ( $post ) {
				return new PPO_Request( $post );
			},
			$posts
		);
	}

	/**
	 * نرخ تبدیل: چند درصد درخواست‌های بسته‌شده به سفارش رسیده‌اند.
	 *
	 * @return float عددی بین ۰ تا ۱۰۰.
	 */
	public static function conversion_rate() {
		$counts    = self::counts_by_status();
		$converted = isset( $counts[ PPO_Status::CONVERTED ] ) ? $counts[ PPO_Status::CONVERTED ] : 0;
		$cancelled = isset( $counts[ PPO_Status::CANCELLED ] ) ? $counts[ PPO_Status::CANCELLED ] : 0;
		$closed    = $converted + $cancelled;

		if ( ! $closed ) {
			return 0.0;
		}

		return round( $converted / $closed * 100, 1 );
	}

	/**
	 * شمار درخواست‌های هر روز در بازهٔ اخیر — برای نمودار ستونی داشبورد.
	 *
	 * @param int $days تعداد روز.
	 * @return array<string,int> تاریخ میلادی => تعداد.
	 */
	public static function daily_counts( $days = 14 ) {
		global $wpdb;

		$days  = max( 1, (int) $days );
		$since = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) - ( $days - 1 ) * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DATE(post_date) AS day, COUNT(*) AS total
				 FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_date >= %s
				 GROUP BY DATE(post_date)",
				PPO_Post_Type::POST_TYPE,
				$since . ' 00:00:00'
			),
			ARRAY_A
		);

		$series = array();

		for ( $index = $days - 1; $index >= 0; $index-- ) {
			$day            = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) - $index * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
			$series[ $day ] = 0;
		}

		foreach ( (array) $rows as $row ) {
			if ( isset( $series[ $row['day'] ] ) ) {
				$series[ $row['day'] ] = (int) $row['total'];
			}
		}

		return $series;
	}
}
