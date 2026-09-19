<?php
/**
 * جدول درخواست‌های پیش‌فروش.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * کارتابل درخواست‌ها.
 */
class PPO_List_Table extends WP_List_Table {

	/**
	 * تعداد سطر در هر صفحه.
	 */
	const PER_PAGE = 25;

	/**
	 * سازنده.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'preorder',
				'plural'   => 'preorders',
				'ajax'     => false,
			)
		);
	}

	/**
	 * ستون‌ها.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox">',
			'id'       => __( 'شماره', 'parsian-preorder' ),
			'product'  => __( 'محصول', 'parsian-preorder' ),
			'customer' => __( 'مشتری', 'parsian-preorder' ),
			'quantity' => __( 'تعداد', 'parsian-preorder' ),
			'value'    => __( 'ارزش تقریبی', 'parsian-preorder' ),
			'assignee' => __( 'مسئول پیگیری', 'parsian-preorder' ),
			'followup' => __( 'پیگیری بعدی', 'parsian-preorder' ),
			'status'   => __( 'وضعیت', 'parsian-preorder' ),
			'date'     => __( 'تاریخ ثبت', 'parsian-preorder' ),
		);
	}

	/**
	 * ستون‌های قابل مرتب‌سازی.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'id'   => array( 'ID', false ),
			'date' => array( 'date', true ),
		);
	}

	/**
	 * عملیات گروهی.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array();

		foreach ( PPO_Status::all() as $key => $status ) {
			/* translators: %s: نام وضعیت. */
			$actions[ 'status:' . $key ] = sprintf( __( 'تغییر وضعیت به «%s»', 'parsian-preorder' ), $status['label'] );
		}

		$actions['assign_me'] = __( 'واگذاری به خودم', 'parsian-preorder' );
		$actions['export']    = __( 'خروجی CSV از انتخاب‌شده‌ها', 'parsian-preorder' );
		$actions['delete']    = __( 'حذف', 'parsian-preorder' );

		return $actions;
	}

	/**
	 * ستون انتخاب.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="request[]" value="%d">', $item->get_id() );
	}

	/**
	 * پیام فهرست خالی.
	 */
	public function no_items() {
		esc_html_e( 'درخواستی با این فیلترها پیدا نشد.', 'parsian-preorder' );
	}

	/**
	 * ستون شماره + عملیات سطر.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_id( $item ) {
		$actions = array(
			'view' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $item->get_admin_url() ),
				esc_html__( 'جزئیات و پیگیری', 'parsian-preorder' )
			),
		);

		$order = $item->get_order();

		if ( $order ) {
			$actions['order'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $order->get_edit_order_url() ),
				esc_html__( 'سفارش', 'parsian-preorder' )
			);
		}

		$title = sprintf(
			'<a class="row-title" href="%s">#%s</a>',
			esc_url( $item->get_admin_url() ),
			esc_html( ppo_digits( $item->get_id() ) )
		);

		if ( $item->is_overdue() ) {
			$title .= ' <span class="ppo-flag" title="' . esc_attr__( 'پیگیری عقب افتاده است', 'parsian-preorder' ) . '">!</span>';
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * ستون محصول.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_product( $item ) {
		$product = $item->get_product();
		$name    = esc_html( $item->get_product_name() );

		if ( $product ) {
			$name = sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_edit_post_link( $product->get_id() ) ),
				$name
			);

			if ( $product->get_sku() ) {
				$name .= '<br><small class="ppo-muted">' . esc_html( $product->get_sku() ) . '</small>';
			}
		}

		return $name;
	}

	/**
	 * ستون مشتری.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_customer( $item ) {
		$phone = ppo_display_phone( $item->get( 'phone' ) );
		$name  = (string) $item->get( 'name' );
		$lines = array();

		if ( '' !== $name ) {
			$lines[] = esc_html( $name );
		}

		if ( '' !== $phone ) {
			$lines[] = sprintf( '<a href="tel:%1$s" dir="ltr">%2$s</a>', esc_attr( $item->get( 'phone' ) ), esc_html( ppo_digits( $phone ) ) );
		}

		$company = (string) $item->get( 'company' );

		if ( '' !== $company ) {
			$lines[] = '<small class="ppo-muted">' . esc_html( $company ) . '</small>';
		}

		return $lines ? implode( '<br>', $lines ) : '<span class="ppo-muted">—</span>';
	}

	/**
	 * ستون تعداد.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_quantity( $item ) {
		return esc_html( ppo_digits( $item->get( 'quantity', 1 ) ) );
	}

	/**
	 * ستون ارزش.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_value( $item ) {
		$value = $item->get_value();

		return $value ? esc_html( ppo_price( $value ) ) : '<span class="ppo-muted">—</span>';
	}

	/**
	 * ستون مسئول پیگیری.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_assignee( $item ) {
		$user = $item->get_assignee();

		return $user ? esc_html( $user->display_name ) : '<span class="ppo-muted">—</span>';
	}

	/**
	 * ستون پیگیری بعدی.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_followup( $item ) {
		$date = (string) $item->get( 'followup_date' );

		if ( '' === $date ) {
			return '<span class="ppo-muted">—</span>';
		}

		$text  = esc_html( ppo_jalali_date( $date ) );
		$days  = ppo_days_until( $date );
		$class = ( null !== $days && $days < 0 && PPO_Status::is_open( $item->get_status() ) ) ? 'ppo-late' : '';

		return sprintf(
			'<span class="%1$s">%2$s<br><small>%3$s</small></span>',
			esc_attr( $class ),
			$text,
			esc_html( ppo_relative_days( $date ) )
		);
	}

	/**
	 * ستون وضعیت.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_status( $item ) {
		return PPO_Status::badge( $item->get_status() );
	}

	/**
	 * ستون تاریخ.
	 *
	 * @param PPO_Request $item درخواست.
	 * @return string
	 */
	public function column_date( $item ) {
		return esc_html( ppo_jalali_date( $item->get_date(), true ) );
	}

	/**
	 * پیوندهای شمارشی بالای جدول.
	 *
	 * @return array
	 */
	protected function get_views() {
		$counts  = PPO_Metrics::counts_by_status();
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base    = admin_url( 'admin.php?page=ppo-requests' );
		$views   = array();

		$views['all'] = sprintf(
			'<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$s)</span></a>',
			esc_url( $base ),
			'' === $current ? 'current' : '',
			esc_html__( 'همه', 'parsian-preorder' ),
			esc_html( ppo_digits( array_sum( $counts ) ) )
		);

		foreach ( PPO_Status::all() as $key => $status ) {
			if ( empty( $counts[ $key ] ) ) {
				continue;
			}

			$views[ $key ] = sprintf(
				'<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$s)</span></a>',
				esc_url( add_query_arg( 'status', $key, $base ) ),
				$current === $key ? 'current' : '',
				esc_html( $status['label'] ),
				esc_html( ppo_digits( $counts[ $key ] ) )
			);
		}

		return $views;
	}

	/**
	 * فیلترهای بالای جدول.
	 *
	 * @param string $which بالا یا پایین.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش.
		$product  = isset( $_GET['product'] ) ? (int) $_GET['product'] : 0;
		$assignee = isset( $_GET['assignee'] ) ? (int) $_GET['assignee'] : 0;
		$overdue  = ! empty( $_GET['overdue'] );
		// phpcs:enable

		echo '<div class="alignleft actions">';

		// فهرست محصولات به همان‌هایی محدود است که پیش‌فروششان روشن است یا
		// درخواستی دارند — وگرنه با ۲۰۰ محصول، این کشو بی‌استفاده می‌شود.
		echo '<select name="product">';
		printf( '<option value="0">%s</option>', esc_html__( 'همهٔ محصولات', 'parsian-preorder' ) );

		foreach ( self::filterable_products() as $id => $label ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $id,
				selected( $product, $id, false ),
				esc_html( $label )
			);
		}

		echo '</select>';

		wp_dropdown_users(
			array(
				'name'             => 'assignee',
				'selected'         => $assignee,
				'show_option_none' => __( 'همهٔ کارشناسان', 'parsian-preorder' ),
				'option_none_value' => 0,
				'capability'       => array( 'manage_woocommerce' ),
			)
		);

		printf(
			'<label class="ppo-inline-check"><input type="checkbox" name="overdue" value="1" %s> %s</label>',
			checked( $overdue, true, false ),
			esc_html__( 'فقط عقب‌افتاده‌ها', 'parsian-preorder' )
		);

		submit_button( __( 'اعمال فیلتر', 'parsian-preorder' ), '', 'filter_action', false );

		echo '</div>';
	}

	/**
	 * محصولاتی که در کشوی فیلتر می‌آیند: محصولات پیش‌فروش، به‌اضافهٔ هر محصولی
	 * که دست‌کم یک درخواست دارد.
	 *
	 * @return array<int,string> شناسه => نام.
	 */
	public static function filterable_products() {
		global $wpdb;

		$ids = PPO_Product::preorder_product_ids();

		$with_requests = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT meta.meta_value
				 FROM {$wpdb->postmeta} AS meta
				 INNER JOIN {$wpdb->posts} AS posts ON posts.ID = meta.post_id AND posts.post_type = %s
				 WHERE meta.meta_key = '_preorder_product_id'",
				PPO_Post_Type::POST_TYPE
			)
		);

		$ids = array_unique( array_map( 'intval', array_merge( $ids, (array) $with_requests ) ) );

		$products = array();

		foreach ( $ids as $id ) {
			if ( ! $id ) {
				continue;
			}

			$product = wc_get_product( $id );

			if ( $product ) {
				$products[ $id ] = $product->get_name();
			}
		}

		asort( $products );

		return $products;
	}

	/**
	 * آماده‌سازی داده‌ها.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط خواندن فیلترها.
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$product  = isset( $_GET['product'] ) ? (int) $_GET['product'] : 0;
		$assignee = isset( $_GET['assignee'] ) ? (int) $_GET['assignee'] : 0;
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$overdue  = ! empty( $_GET['overdue'] );
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date';
		$order    = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC';
		// phpcs:enable

		$query = array(
			'post_type'      => PPO_Post_Type::POST_TYPE,
			'post_status'    => PPO_Status::exists( $status )
				? array( $status )
				: array_merge( PPO_Status::keys(), array( 'private' ) ),
			'posts_per_page' => $overdue ? -1 : self::PER_PAGE,
			'paged'          => $overdue ? 1 : $paged,
			'orderby'        => in_array( $orderby, array( 'ID', 'date' ), true ) ? $orderby : 'date',
			'order'          => $order,
		);

		$meta = array();

		if ( $product ) {
			$meta[] = array(
				'key'   => '_preorder_product_id',
				'value' => $product,
			);
		}

		if ( $assignee ) {
			$meta[] = array(
				'key'   => '_preorder_assignee',
				'value' => $assignee,
			);
		}

		if ( $meta ) {
			$meta['relation']    = 'AND';
			$query['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( '' !== $search ) {
			// جستجو هم روی عنوان (نام محصول) و هم روی شماره و نام مشتری کار می‌کند.
			$ids = self::search_ids( $search );

			if ( $ids ) {
				$query['post__in'] = $ids;
			} else {
				$query['s'] = $search;
			}
		}

		$wp_query = new WP_Query( $query );
		$items    = array();

		foreach ( $wp_query->posts as $post ) {
			$request = new PPO_Request( $post );

			if ( $overdue && ! $request->is_overdue() ) {
				continue;
			}

			$items[] = $request;
		}

		$total = $overdue ? count( $items ) : (int) $wp_query->found_posts;

		if ( $overdue ) {
			$items = array_slice( $items, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );
		}

		$this->items = $items;

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) ceil( $total / self::PER_PAGE ),
			)
		);
	}

	/**
	 * یافتن درخواست‌ها بر پایهٔ شماره، تلفن یا نام مشتری.
	 *
	 * @param string $search عبارت جستجو.
	 * @return int[]
	 */
	public static function search_ids( $search ) {
		global $wpdb;

		$search = trim( $search );
		$ids    = array();

		// «#12» یا «12» یعنی شمارهٔ درخواست.
		$numeric = ppo_latin_digits( ltrim( $search, '#' ) );

		if ( ctype_digit( $numeric ) && PPO_Request::find( (int) $numeric ) ) {
			$ids[] = (int) $numeric;
		}

		$phone = ppo_normalize_phone( $search );
		$like  = '%' . $wpdb->esc_like( $search ) . '%';

		$found = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT meta.post_id
				 FROM {$wpdb->postmeta} AS meta
				 INNER JOIN {$wpdb->posts} AS posts ON posts.ID = meta.post_id AND posts.post_type = %s
				 WHERE ( meta.meta_key = '_preorder_phone' AND meta.meta_value = %s )
				    OR ( meta.meta_key IN ( '_preorder_name', '_preorder_company', '_preorder_note', '_preorder_city' ) AND meta.meta_value LIKE %s )",
				PPO_Post_Type::POST_TYPE,
				$phone,
				$like
			)
		);

		return array_values( array_unique( array_merge( $ids, array_map( 'intval', (array) $found ) ) ) );
	}
}
