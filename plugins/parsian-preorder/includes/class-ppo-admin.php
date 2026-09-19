<?php
/**
 * کارتابل پیش‌فروش در پیشخوان.
 *
 * سه صفحه: داشبورد (آمار و کارهای عقب‌افتاده)، درخواست‌ها (فهرست و جزئیات) و
 * تنظیمات. همهٔ نوشتن‌ها از admin-post.php می‌گذرند تا nonce و دسترسی در یک
 * نقطه بررسی شود.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

require_once PPO_PATH . 'includes/class-ppo-list-table.php';

/**
 * رابط پیشخوان.
 */
class PPO_Admin {

	const MENU = 'ppo-dashboard';

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PPO_Admin|null
	 */
	protected static $instance = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PPO_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * ثبت قلاب‌ها.
	 */
	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'admin_post_ppo_save_request', array( $this, 'handle_save_request' ) );
		add_action( 'admin_post_ppo_add_note', array( $this, 'handle_add_note' ) );
		add_action( 'admin_post_ppo_create_order', array( $this, 'handle_create_order' ) );
		add_action( 'admin_post_ppo_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_ppo_settings', array( $this, 'handle_settings' ) );
	}

	/* -------------------------------- منو -------------------------------- */

	/**
	 * ساخت منوی پیش‌فروش.
	 */
	public function add_menu() {
		// منو با دسترسی اختصاصی افزونه ساخته می‌شود، مگر کاربری که فقط مدیر
		// فروشگاه است — آن‌وقت با دسترسی ووکامرس، وگرنه منو را اصلاً نمی‌بیند.
		$capability = current_user_can( ppo_capability() ) ? ppo_capability() : 'manage_woocommerce';
		$counts     = PPO_Metrics::counts_by_status();
		$open       = 0;

		foreach ( PPO_Status::open_keys() as $key ) {
			$open += isset( $counts[ $key ] ) ? $counts[ $key ] : 0;
		}

		$title = __( 'پیش‌فروش', 'parsian-preorder' );

		if ( $open ) {
			$title .= sprintf( ' <span class="awaiting-mod"><span class="pending-count">%s</span></span>', esc_html( ppo_digits( $open ) ) );
		}

		add_menu_page(
			__( 'پیش‌فروش', 'parsian-preorder' ),
			$title,
			$capability,
			self::MENU,
			array( $this, 'render_dashboard' ),
			'dashicons-calendar-alt',
			56
		);

		add_submenu_page(
			self::MENU,
			__( 'داشبورد پیش‌فروش', 'parsian-preorder' ),
			__( 'داشبورد', 'parsian-preorder' ),
			$capability,
			self::MENU,
			array( $this, 'render_dashboard' )
		);

		$requests_hook = add_submenu_page(
			self::MENU,
			__( 'درخواست‌های پیش‌فروش', 'parsian-preorder' ),
			__( 'درخواست‌ها', 'parsian-preorder' ),
			$capability,
			'ppo-requests',
			array( $this, 'render_requests' )
		);

		// عملیات گروهی باید پیش از چاپ هر خروجی انجام شود تا بتوان ریدایرکت کرد.
		add_action( 'load-' . $requests_hook, array( $this, 'maybe_process_bulk' ) );

		add_submenu_page(
			self::MENU,
			__( 'محصولات پیش‌فروش', 'parsian-preorder' ),
			__( 'محصولات', 'parsian-preorder' ),
			$capability,
			'ppo-products',
			array( $this, 'render_products' )
		);

		add_submenu_page(
			self::MENU,
			__( 'تنظیمات پیش‌فروش', 'parsian-preorder' ),
			__( 'تنظیمات', 'parsian-preorder' ),
			'manage_options',
			'ppo-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * بارگذاری استایل و اسکریپت صفحه‌های افزونه.
	 *
	 * @param string $hook شناسهٔ صفحه.
	 */
	public function enqueue( $hook ) {
		$ours = false !== strpos( $hook, 'ppo-' ) || false !== strpos( $hook, self::MENU );

		// ستون پیش‌فروش در فهرست محصولات و سفارش‌ها هم به استایل نیاز دارد.
		if ( ! $ours && ! in_array( $hook, array( 'edit.php', 'post.php', 'post-new.php', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'ppo-admin', PPO_URL . 'assets/ppo-admin.css', array(), PPO_VERSION );

		if ( $ours ) {
			wp_enqueue_script( 'ppo-admin', PPO_URL . 'assets/ppo-admin.js', array(), PPO_VERSION, true );
			wp_localize_script(
				'ppo-admin',
				'ppoAdmin',
				array(
					'confirmDelete' => __( 'این درخواست‌ها برای همیشه حذف شوند؟', 'parsian-preorder' ),
					'confirmOrder'  => __( 'سفارش ووکامرس از روی این درخواست ساخته شود؟', 'parsian-preorder' ),
				)
			);
		}
	}

	/* ------------------------------ نگهبان‌ها ------------------------------ */

	/**
	 * بررسی دسترسی و nonce.
	 *
	 * @param string $action نام عملیات.
	 */
	protected function guard( $action ) {
		if ( ! ppo_user_can() ) {
			wp_die( esc_html__( 'اجازهٔ انجام این کار را ندارید.', 'parsian-preorder' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * بازگشت به یک صفحه با پیام.
	 *
	 * @param array $args پارامترهای نشانی.
	 */
	protected function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * ثبت پیام برای نمایش در صفحهٔ بعد.
	 *
	 * @param string $message متن.
	 * @param string $type    success | error.
	 */
	protected function notice( $message, $type = 'success' ) {
		set_transient(
			'ppo_notice_' . get_current_user_id(),
			array(
				'text' => $message,
				'type' => $type,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * نمایش پیام ذخیره‌شده.
	 */
	protected function render_notice() {
		$notice = get_transient( 'ppo_notice_' . get_current_user_id() );

		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( 'ppo_notice_' . get_current_user_id() );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'error' === $notice['type'] ? 'error' : 'success',
			esc_html( $notice['text'] )
		);
	}

	/* ------------------------------ عملیات ------------------------------ */

	/**
	 * ذخیرهٔ تغییرات یک درخواست (وضعیت، مسئول، پیگیری، تعداد، قیمت).
	 */
	public function handle_save_request() {
		$this->guard( 'ppo_save_request' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- در guard() بررسی شد.
		$request = PPO_Request::find( isset( $_POST['request'] ) ? (int) $_POST['request'] : 0 );

		if ( ! $request ) {
			$this->notice( __( 'درخواست پیدا نشد.', 'parsian-preorder' ), 'error' );
			$this->redirect( array( 'page' => 'ppo-requests' ) );
		}

		$note = isset( $_POST['status_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['status_note'] ) ) : '';

		if ( isset( $_POST['status'] ) ) {
			$request->set_status( sanitize_key( wp_unslash( $_POST['status'] ) ), $note );
		}

		if ( isset( $_POST['assignee'] ) ) {
			$request->assign( (int) $_POST['assignee'] );
		}

		if ( isset( $_POST['followup_date'] ) ) {
			$request->set( 'followup_date', ppo_parse_date( wp_unslash( $_POST['followup_date'] ) ) );
		}

		if ( isset( $_POST['quantity'] ) ) {
			$request->set( 'quantity', max( 1, (int) ppo_latin_digits( wp_unslash( $_POST['quantity'] ) ) ) );
		}

		if ( isset( $_POST['unit_price'] ) ) {
			$request->set( 'unit_price', (float) ppo_latin_digits( wp_unslash( $_POST['unit_price'] ) ) );
		}

		if ( isset( $_POST['priority'] ) ) {
			$request->set( 'priority', sanitize_key( wp_unslash( $_POST['priority'] ) ) );
		}
		// phpcs:enable

		$this->notice( __( 'درخواست به‌روزرسانی شد.', 'parsian-preorder' ) );
		$this->redirect(
			array(
				'page' => 'ppo-requests',
				'view' => $request->get_id(),
			)
		);
	}

	/**
	 * افزودن یادداشت دستی.
	 */
	public function handle_add_note() {
		$this->guard( 'ppo_add_note' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- در guard() بررسی شد.
		$request = PPO_Request::find( isset( $_POST['request'] ) ? (int) $_POST['request'] : 0 );
		$text    = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		// phpcs:enable

		if ( $request && '' !== $text ) {
			PPO_Log::add( $request->get_id(), $text, 'note' );
			$this->notice( __( 'یادداشت ثبت شد.', 'parsian-preorder' ) );
		}

		$this->redirect(
			array(
				'page' => 'ppo-requests',
				'view' => $request ? $request->get_id() : 0,
			)
		);
	}

	/**
	 * ساخت سفارش ووکامرس از روی درخواست.
	 */
	public function handle_create_order() {
		$this->guard( 'ppo_create_order' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- در guard() بررسی شد.
		$request = PPO_Request::find( isset( $_POST['request'] ) ? (int) $_POST['request'] : 0 );
		// phpcs:enable

		if ( ! $request ) {
			$this->notice( __( 'درخواست پیدا نشد.', 'parsian-preorder' ), 'error' );
			$this->redirect( array( 'page' => 'ppo-requests' ) );
		}

		$order = PPO_Orders::create_order( $request );

		if ( is_wp_error( $order ) ) {
			$this->notice( $order->get_error_message(), 'error' );
		} else {
			$this->notice(
				sprintf(
					/* translators: %s: شمارهٔ سفارش. */
					__( 'سفارش #%s ساخته شد.', 'parsian-preorder' ),
					ppo_digits( $order->get_order_number() )
				)
			);
		}

		$this->redirect(
			array(
				'page' => 'ppo-requests',
				'view' => $request->get_id(),
			)
		);
	}

	/**
	 * اجرای عملیات گروهی پیش از ساخته شدن صفحه.
	 */
	public function maybe_process_bulk() {
		$table = new PPO_List_Table();

		$this->process_bulk( $table );
	}

	/**
	 * عملیات گروهی روی درخواست‌های انتخاب‌شده.
	 *
	 * از همان فرم GET فهرست می‌آید (مثل فهرست نوشته‌های وردپرس) و با nonceای که
	 * WP_List_Table خودش گذاشته بررسی می‌شود.
	 *
	 * @param PPO_List_Table $table جدول.
	 */
	protected function process_bulk( $table ) {
		$action = $table->current_action();

		if ( ! $action ) {
			return;
		}

		check_admin_referer( 'bulk-preorders' );

		if ( ! ppo_user_can() ) {
			wp_die( esc_html__( 'اجازهٔ انجام این کار را ندارید.', 'parsian-preorder' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- بالا بررسی شد.
		$ids = isset( $_REQUEST['request'] ) ? array_filter( array_map( 'intval', (array) wp_unslash( $_REQUEST['request'] ) ) ) : array();

		if ( ! $ids ) {
			$this->notice( __( 'هیچ درخواستی انتخاب نشده بود.', 'parsian-preorder' ), 'error' );
			$this->redirect( array( 'page' => 'ppo-requests' ) );
		}

		if ( 'export' === $action ) {
			$this->send_export( array( 'ids' => $ids ) );
		}

		$done = 0;

		foreach ( $ids as $id ) {
			$request = PPO_Request::find( $id );

			if ( ! $request ) {
				continue;
			}

			if ( 0 === strpos( $action, 'status:' ) ) {
				$request->set_status( substr( $action, strlen( 'status:' ) ) );
				$done++;
				continue;
			}

			if ( 'assign_me' === $action ) {
				$request->assign( get_current_user_id() );
				$done++;
				continue;
			}

			if ( 'delete' === $action ) {
				$request->delete();
				$done++;
			}
		}

		$this->notice(
			sprintf(
				/* translators: %s: تعداد درخواست. */
				__( '%s درخواست پردازش شد.', 'parsian-preorder' ),
				ppo_digits( $done )
			)
		);

		$this->redirect( array( 'page' => 'ppo-requests' ) );
	}

	/**
	 * خروجی CSV با همان فیلترهای فهرست.
	 */
	public function handle_export() {
		$this->guard( 'ppo_export' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- در guard() بررسی شد.
		$args = array(
			'status'  => isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '',
			'product' => isset( $_REQUEST['product'] ) ? (int) $_REQUEST['product'] : 0,
			'search'  => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
		);
		// phpcs:enable

		$this->send_export( $args );
	}

	/**
	 * فرستادن فایل خروجی و پایان درخواست.
	 *
	 * @param array $args فیلترها.
	 */
	protected function send_export( $args ) {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		PPO_Export::stream( $args );
		exit;
	}

	/**
	 * ذخیرهٔ تنظیمات.
	 */
	public function handle_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'اجازهٔ انجام این کار را ندارید.', 'parsian-preorder' ) );
		}

		check_admin_referer( 'ppo_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- بالا بررسی شد.
		PPO_Settings::instance()->save( wp_unslash( $_POST ) );

		$this->notice( __( 'تنظیمات ذخیره شد.', 'parsian-preorder' ) );
		$this->redirect( array( 'page' => 'ppo-settings' ) );
	}

	/* ------------------------------- نمایش ------------------------------- */

	/**
	 * داشبورد آمار.
	 */
	public function render_dashboard() {
		if ( ! ppo_user_can() ) {
			return;
		}

		$this->render_notice();

		include PPO_PATH . 'includes/views/dashboard.php';
	}

	/**
	 * فهرست درخواست‌ها یا صفحهٔ جزئیات یک درخواست.
	 */
	public function render_requests() {
		if ( ! ppo_user_can() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما.
		$view = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;

		$this->render_notice();

		if ( $view ) {
			$request = PPO_Request::find( $view );

			if ( ! $request ) {
				echo '<div class="wrap"><h1>' . esc_html__( 'درخواست پیدا نشد', 'parsian-preorder' ) . '</h1></div>';
				return;
			}

			include PPO_PATH . 'includes/views/request.php';
			return;
		}

		$table = new PPO_List_Table();
		$table->prepare_items();

		include PPO_PATH . 'includes/views/requests.php';
	}

	/**
	 * فهرست محصولات پیش‌فروش و وضعیت ظرفیتشان.
	 */
	public function render_products() {
		if ( ! ppo_user_can() ) {
			return;
		}

		$this->render_notice();

		include PPO_PATH . 'includes/views/products.php';
	}

	/**
	 * صفحهٔ تنظیمات.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->render_notice();

		$settings = PPO_Settings::instance();

		include PPO_PATH . 'includes/views/settings.php';
	}
}
