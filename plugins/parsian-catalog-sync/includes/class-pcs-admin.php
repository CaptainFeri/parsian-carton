<?php
/**
 * صفحهٔ پیشخوان: بارگذاری فایل، پیش‌نمایش تغییرات، اعمال و تاریخچه.
 *
 * جریان کار عمداً دو مرحله‌ای است: هیچ تغییری بدون دیدن پیش‌نمایش اعمال نمی‌شود.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * رابط کاربری پیشخوان.
 */
class PCS_Admin {

	const PAGE       = 'pcs-sync';
	const TRANSIENT  = 'pcs_plan_';
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PCS_Admin|null
	 */
	protected static $instance = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PCS_Admin
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
		add_action( 'admin_post_pcs_preview', array( $this, 'handle_preview' ) );
		add_action( 'admin_post_pcs_apply', array( $this, 'handle_apply' ) );
		add_action( 'admin_post_pcs_settings', array( $this, 'handle_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * افزودن زیرمنو.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'همگام‌سازی با اکسل', 'parsian-catalog-sync' ),
			__( 'همگام‌سازی با اکسل', 'parsian-catalog-sync' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * بارگذاری استایل صفحه.
	 *
	 * @param string $hook شناسهٔ صفحهٔ جاری.
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( $hook, self::PAGE ) && false === strpos( $hook, PCS_Attribute_Admin::PAGE ) ) {
			return;
		}

		wp_enqueue_style( 'pcs-admin', PCS_URL . 'assets/pcs-admin.css', array(), PCS_VERSION );
	}

	/* ------------------------------ پردازش فرم‌ها ------------------------------ */

	/**
	 * ساخت پیش‌نمایش از فایل بارگذاری‌شده یا منبع ثابت.
	 */
	public function handle_preview() {
		$this->guard( 'pcs_preview' );

		$sheet  = isset( $_POST['sheet'] ) ? sanitize_text_field( wp_unslash( $_POST['sheet'] ) ) : '';
		$source = isset( $_POST['source_type'] ) ? sanitize_key( wp_unslash( $_POST['source_type'] ) ) : 'upload';

		if ( 'stored' === $source ) {
			$path = PCS_Scheduler::localize( PCS_Settings::instance()->get( 'source' ) );

			if ( is_wp_error( $path ) ) {
				$this->redirect_with_error( $path->get_error_message() );
			}
		} else {
			$path = $this->accept_upload();
		}

		$plan = PCS_Sync::plan( $path, $sheet );

		if ( is_wp_error( $plan ) ) {
			$this->redirect_with_error( $plan->get_error_message() );
		}

		$plan['file']  = wp_basename( $path );
		$plan['path']  = $path;
		$plan['sheets'] = PCS_Spreadsheet::sheet_names( $path );

		$token = wp_generate_password( 12, false, false );
		set_transient( self::TRANSIENT . $token, $plan, HOUR_IN_SECONDS );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'product',
					'page'      => self::PAGE,
					'plan'      => $token,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * اعمال پیش‌نمایش ذخیره‌شده.
	 */
	public function handle_apply() {
		$this->guard( 'pcs_apply' );

		$token = isset( $_POST['plan'] ) ? sanitize_text_field( wp_unslash( $_POST['plan'] ) ) : '';
		$plan  = $token ? get_transient( self::TRANSIENT . $token ) : false;

		if ( ! is_array( $plan ) ) {
			$this->redirect_with_error( __( 'پیش‌نمایش منقضی شده است؛ فایل را دوباره بارگذاری کنید.', 'parsian-catalog-sync' ) );
		}

		$report = PCS_Sync::apply( $plan );

		delete_transient( self::TRANSIENT . $token );

		if ( isset( $plan['path'] ) && file_exists( $plan['path'] ) ) {
			wp_delete_file( $plan['path'] );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'product',
					'page'      => self::PAGE,
					'done'      => 1,
					'created'   => (int) $report['created'],
					'updated'   => (int) $report['updated'],
					'skipped'   => (int) $report['skipped'],
					'failed_rows' => (int) $report['failed'],
					'missing'   => (int) $report['missing'],
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * ذخیرهٔ تنظیمات.
	 */
	public function handle_settings() {
		$this->guard( 'pcs_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- در guard() بررسی شد.
		$values = wp_unslash( $_POST );

		PCS_Settings::instance()->save(
			array(
				'price_unit'        => isset( $values['price_unit'] ) ? sanitize_key( $values['price_unit'] ) : 'toman',
				'missing_action'    => isset( $values['missing_action'] ) ? sanitize_key( $values['missing_action'] ) : 'draft',
				'create_attributes' => isset( $values['create_attributes'] ) ? 1 : 0,
				'import_attributes' => isset( $values['import_attributes'] ) ? 1 : 0,
				'source'            => isset( $values['source'] ) ? $values['source'] : '',
				'sheet'             => isset( $values['sheet'] ) ? $values['sheet'] : '',
				'schedule'          => isset( $values['schedule'] ) ? sanitize_key( $values['schedule'] ) : 'off',
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'product',
					'page'      => self::PAGE,
					'saved'     => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * بررسی دسترسی و nonce.
	 *
	 * @param string $action نام عملیات.
	 */
	protected function guard( $action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'اجازهٔ انجام این کار را ندارید.', 'parsian-catalog-sync' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * پذیرش فایل بارگذاری‌شده و بازگرداندن مسیر موقت.
	 *
	 * @return string
	 */
	protected function accept_upload() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- در guard() بررسی شد.
		if ( empty( $_FILES['catalog']['name'] ) ) {
			$this->redirect_with_error( __( 'فایلی انتخاب نشده است.', 'parsian-catalog-sync' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$file = array(
			'name'     => sanitize_file_name( wp_unslash( $_FILES['catalog']['name'] ) ), // phpcs:ignore
			'type'     => isset( $_FILES['catalog']['type'] ) ? sanitize_text_field( wp_unslash( $_FILES['catalog']['type'] ) ) : '', // phpcs:ignore
			'tmp_name' => isset( $_FILES['catalog']['tmp_name'] ) ? $_FILES['catalog']['tmp_name'] : '', // phpcs:ignore
			'error'    => isset( $_FILES['catalog']['error'] ) ? (int) $_FILES['catalog']['error'] : 0, // phpcs:ignore
			'size'     => isset( $_FILES['catalog']['size'] ) ? (int) $_FILES['catalog']['size'] : 0, // phpcs:ignore
		);

		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, array( 'xlsx', 'xlsm', 'csv', 'txt' ), true ) ) {
			$this->redirect_with_error( __( 'فقط فایل‌های xlsx و csv پذیرفته می‌شوند.', 'parsian-catalog-sync' ) );
		}

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
					'csv'  => 'text/csv',
					'txt'  => 'text/plain',
				),
			)
		);

		if ( isset( $upload['error'] ) ) {
			$this->redirect_with_error( $upload['error'] );
		}

		return $upload['file'];
	}

	/**
	 * بازگشت به صفحه با پیام خطا.
	 *
	 * @param string $message متن خطا.
	 */
	protected function redirect_with_error( $message ) {
		set_transient( 'pcs_error_' . get_current_user_id(), $message, MINUTE_IN_SECONDS * 5 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'product',
					'page'      => self::PAGE,
					'failed'    => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/* -------------------------------- نمایش -------------------------------- */

	/**
	 * نمایش صفحهٔ افزونه.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط نمایش، بدون تغییر داده.
		$token = isset( $_GET['plan'] ) ? sanitize_text_field( wp_unslash( $_GET['plan'] ) ) : '';
		$plan  = $token ? get_transient( self::TRANSIENT . $token ) : false;
		// phpcs:enable

		echo '<div class="wrap pcs-wrap">';
		echo '<h1>' . esc_html__( 'همگام‌سازی محصولات با فایل اکسل', 'parsian-catalog-sync' ) . '</h1>';

		$this->render_notices();

		if ( is_array( $plan ) ) {
			$this->render_preview( $plan, $token );
		} else {
			$this->render_upload_form();
			$this->render_settings_form();
			$this->render_history();
		}

		echo '</div>';
	}

	/**
	 * پیام‌های بالای صفحه.
	 */
	protected function render_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['failed'] ) ) {
			$message = get_transient( 'pcs_error_' . get_current_user_id() );
			delete_transient( 'pcs_error_' . get_current_user_id() );

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $message ? $message : __( 'انجام نشد.', 'parsian-catalog-sync' ) )
			);
		}

		if ( ! empty( $_GET['saved'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'تنظیمات ذخیره شد.', 'parsian-catalog-sync' )
			);
		}

		if ( ! empty( $_GET['done'] ) ) {
			$created = isset( $_GET['created'] ) ? (int) $_GET['created'] : 0;
			$updated = isset( $_GET['updated'] ) ? (int) $_GET['updated'] : 0;
			$skipped = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0;
			$failed  = isset( $_GET['failed_rows'] ) ? (int) $_GET['failed_rows'] : 0;
			$missing = isset( $_GET['missing'] ) ? (int) $_GET['missing'] : 0;

			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: ساخته‌شده، 2: به‌روزشده، 3: بدون تغییر، 4: ناموفق، 5: غایب. */
						__( 'همگام‌سازی انجام شد — %1$s محصول جدید، %2$s به‌روزرسانی، %3$s بدون تغییر، %4$s ناموفق، %5$s محصول غایب از فایل.', 'parsian-catalog-sync' ),
						pcs_digits( $created ),
						pcs_digits( $updated ),
						pcs_digits( $skipped ),
						pcs_digits( $failed ),
						pcs_digits( $missing )
					)
				)
			);
		}
		// phpcs:enable
	}

	/**
	 * فرم بارگذاری فایل.
	 */
	protected function render_upload_form() {
		$settings = PCS_Settings::instance();
		$stored   = $settings->get( 'source' );
		?>
		<div class="pcs-card">
			<h2><?php esc_html_e( '۱) فایل را انتخاب کنید', 'parsian-catalog-sync' ); ?></h2>
			<p class="pcs-muted">
				<?php esc_html_e( 'ابتدا پیش‌نمایش تغییرات را می‌بینید؛ تا وقتی دکمهٔ «اعمال» را نزنید هیچ‌چیز در فروشگاه تغییر نمی‌کند.', 'parsian-catalog-sync' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'pcs_preview' ); ?>
				<input type="hidden" name="action" value="pcs_preview">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pcs-file"><?php esc_html_e( 'فایل اکسل', 'parsian-catalog-sync' ); ?></label></th>
						<td>
							<input type="file" id="pcs-file" name="catalog" accept=".xlsx,.xlsm,.csv,.txt">
							<p class="description"><?php esc_html_e( 'قالب‌های پشتیبانی‌شده: xlsx و csv.', 'parsian-catalog-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pcs-sheet"><?php esc_html_e( 'نام برگه', 'parsian-catalog-sync' ); ?></label></th>
						<td>
							<input type="text" id="pcs-sheet" name="sheet" class="regular-text" value="<?php echo esc_attr( $settings->get( 'sheet' ) ); ?>" placeholder="<?php esc_attr_e( 'خالی = نخستین برگه', 'parsian-catalog-sync' ); ?>">
						</td>
					</tr>
					<?php if ( $stored ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'یا منبع ثابت', 'parsian-catalog-sync' ); ?></th>
							<td>
								<label>
									<input type="radio" name="source_type" value="stored">
									<?php echo esc_html( $stored ); ?>
								</label>
								<label style="margin-inline-start:16px;">
									<input type="radio" name="source_type" value="upload" checked>
									<?php esc_html_e( 'فایل بارگذاری‌شدهٔ بالا', 'parsian-catalog-sync' ); ?>
								</label>
							</td>
						</tr>
					<?php endif; ?>
				</table>

				<?php submit_button( __( 'نمایش پیش‌نمایش تغییرات', 'parsian-catalog-sync' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * پیش‌نمایش تغییرات.
	 *
	 * @param array  $plan  نقشهٔ تغییرات.
	 * @param string $token کلید پیش‌نمایش.
	 */
	protected function render_preview( $plan, $token ) {
		$summary  = $plan['summary'];
		$settings = PCS_Settings::instance();
		$labels   = array(
			'create'    => __( 'محصول جدید', 'parsian-catalog-sync' ),
			'update'    => __( 'به‌روزرسانی', 'parsian-catalog-sync' ),
			'unchanged' => __( 'بدون تغییر', 'parsian-catalog-sync' ),
			'error'     => __( 'دارای خطا', 'parsian-catalog-sync' ),
		);
		?>
		<div class="pcs-card">
			<h2><?php esc_html_e( '۲) پیش‌نمایش تغییرات', 'parsian-catalog-sync' ); ?></h2>

			<p class="pcs-muted">
				<?php
				printf(
					/* translators: 1: نام فایل، 2: واحد قیمت. */
					esc_html__( 'فایل: %1$s — قیمت‌های فایل به %2$s خوانده شده‌اند.', 'parsian-catalog-sync' ),
					'<code>' . esc_html( $plan['file'] ) . '</code>',
					esc_html( 'rial' === $plan['price_unit'] ? __( 'ریال', 'parsian-catalog-sync' ) : __( 'تومان', 'parsian-catalog-sync' ) )
				);
				?>
			</p>

			<div class="pcs-summary">
				<?php foreach ( $labels as $key => $label ) : ?>
					<div class="pcs-stat pcs-stat-<?php echo esc_attr( $key ); ?>">
						<span class="pcs-stat-number"><?php echo esc_html( pcs_digits( $summary[ $key ] ) ); ?></span>
						<span class="pcs-stat-label"><?php echo esc_html( $label ); ?></span>
					</div>
				<?php endforeach; ?>
				<div class="pcs-stat pcs-stat-missing">
					<span class="pcs-stat-number"><?php echo esc_html( pcs_digits( count( $plan['missing'] ) ) ); ?></span>
					<span class="pcs-stat-label"><?php esc_html_e( 'در فایل نیست', 'parsian-catalog-sync' ); ?></span>
				</div>
			</div>

			<?php if ( $plan['mapping']['unknown'] ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							/* translators: %s: فهرست ستون‌ها. */
							esc_html__( 'این ستون‌ها شناخته نشدند و نادیده گرفته می‌شوند: %s', 'parsian-catalog-sync' ),
							'<code>' . esc_html( implode( '</code>، <code>', $plan['mapping']['unknown'] ) ) . '</code>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php $this->render_rows_table( $plan['rows'] ); ?>

			<?php if ( $plan['missing'] ) : ?>
				<h3><?php esc_html_e( 'محصولاتی که در فایل نیستند', 'parsian-catalog-sync' ); ?></h3>
				<p class="pcs-muted">
					<?php
					$actions = array(
						'none'       => __( 'دست‌نخورده می‌مانند.', 'parsian-catalog-sync' ),
						'draft'      => __( 'به پیش‌نویس تبدیل می‌شوند (از فروشگاه پنهان، ولی قابل بازگرداندن).', 'parsian-catalog-sync' ),
						'outofstock' => __( 'ناموجود می‌شوند.', 'parsian-catalog-sync' ),
						'trash'      => __( 'به زباله‌دان منتقل می‌شوند.', 'parsian-catalog-sync' ),
					);
					$action = $settings->get( 'missing_action' );
					echo esc_html( isset( $actions[ $action ] ) ? $actions[ $action ] : '' );
					?>
				</p>
				<table class="widefat striped pcs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'کد محصول', 'parsian-catalog-sync' ); ?></th>
							<th><?php esc_html_e( 'نام', 'parsian-catalog-sync' ); ?></th>
							<th><?php esc_html_e( 'وضعیت فعلی', 'parsian-catalog-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $plan['missing'] as $item ) : ?>
							<tr>
								<td><code><?php echo esc_html( $item['sku'] ); ?></code></td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $item['id'] ) ); ?>">
										<?php echo esc_html( $item['name'] ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $item['status'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pcs-apply-form">
				<?php wp_nonce_field( 'pcs_apply' ); ?>
				<input type="hidden" name="action" value="pcs_apply">
				<input type="hidden" name="plan" value="<?php echo esc_attr( $token ); ?>">

				<p>
					<button type="submit" class="button button-primary button-hero">
						<?php esc_html_e( 'اعمال تغییرات روی فروشگاه', 'parsian-catalog-sync' ); ?>
					</button>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'product', 'page' => self::PAGE ), admin_url( 'edit.php' ) ) ); ?>">
						<?php esc_html_e( 'انصراف', 'parsian-catalog-sync' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * جدول سطرهای فایل.
	 *
	 * @param array[] $rows سطرها.
	 */
	protected function render_rows_table( $rows ) {
		$badges = array(
			'create'    => __( 'جدید', 'parsian-catalog-sync' ),
			'update'    => __( 'تغییر', 'parsian-catalog-sync' ),
			'unchanged' => __( 'بدون تغییر', 'parsian-catalog-sync' ),
			'error'     => __( 'خطا', 'parsian-catalog-sync' ),
		);
		?>
		<table class="widefat striped pcs-table">
			<thead>
				<tr>
					<th style="width:60px;"><?php esc_html_e( 'سطر', 'parsian-catalog-sync' ); ?></th>
					<th style="width:110px;"><?php esc_html_e( 'وضعیت', 'parsian-catalog-sync' ); ?></th>
					<th style="width:130px;"><?php esc_html_e( 'کد محصول', 'parsian-catalog-sync' ); ?></th>
					<th><?php esc_html_e( 'نام', 'parsian-catalog-sync' ); ?></th>
					<th><?php esc_html_e( 'تغییرات', 'parsian-catalog-sync' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr class="pcs-row pcs-row-<?php echo esc_attr( $row['action'] ); ?>">
						<td><?php echo esc_html( pcs_digits( $row['row'] ) ); ?></td>
						<td>
							<span class="pcs-badge pcs-badge-<?php echo esc_attr( $row['action'] ); ?>">
								<?php echo esc_html( isset( $badges[ $row['action'] ] ) ? $badges[ $row['action'] ] : $row['action'] ); ?>
							</span>
						</td>
						<td><code><?php echo esc_html( $row['sku'] ); ?></code></td>
						<td>
							<?php if ( $row['product_id'] ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $row['product_id'] ) ); ?>">
									<?php echo esc_html( $row['name'] ? $row['name'] : get_the_title( $row['product_id'] ) ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $row['name'] ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $row['errors'] ) : ?>
								<ul class="pcs-issues pcs-issues-error">
									<?php foreach ( $row['errors'] as $error ) : ?>
										<li><?php echo esc_html( $error ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( $row['warnings'] ) : ?>
								<ul class="pcs-issues pcs-issues-warning">
									<?php foreach ( $row['warnings'] as $warning ) : ?>
										<li><?php echo esc_html( $warning ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( $row['changes'] ) : ?>
								<ul class="pcs-diff">
									<?php foreach ( $row['changes'] as $field => $change ) : ?>
										<li>
											<span class="pcs-diff-field"><?php echo esc_html( PCS_Sync::field_label( $field ) ); ?></span>
											<?php if ( '' !== $change['from'] ) : ?>
												<del><?php echo esc_html( $this->shorten( $change['from'] ) ); ?></del>
												<span aria-hidden="true">←</span>
											<?php endif; ?>
											<ins><?php echo esc_html( $this->shorten( $change['to'] ) ); ?></ins>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php elseif ( ! $row['errors'] ) : ?>
								<span class="pcs-muted">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * کوتاه کردن مقدارهای بلند برای نمایش در جدول.
	 *
	 * @param string $value مقدار.
	 * @return string
	 */
	protected function shorten( $value ) {
		$value = (string) $value;

		return mb_strlen( $value ) > 70 ? mb_substr( $value, 0, 70 ) . '…' : $value;
	}

	/**
	 * فرم تنظیمات.
	 */
	protected function render_settings_form() {
		$settings = PCS_Settings::instance();
		$store    = $settings->store_unit();
		?>
		<div class="pcs-card">
			<h2><?php esc_html_e( 'تنظیمات', 'parsian-catalog-sync' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'pcs_settings' ); ?>
				<input type="hidden" name="action" value="pcs_settings">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'واحد قیمت در فایل', 'parsian-catalog-sync' ); ?></th>
						<td>
							<label style="margin-inline-end:16px;">
								<input type="radio" name="price_unit" value="toman" <?php checked( 'toman', $settings->get( 'price_unit' ) ); ?>>
								<?php esc_html_e( 'تومان', 'parsian-catalog-sync' ); ?>
							</label>
							<label>
								<input type="radio" name="price_unit" value="rial" <?php checked( 'rial', $settings->get( 'price_unit' ) ); ?>>
								<?php esc_html_e( 'ریال', 'parsian-catalog-sync' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: 1: واحد دیتابیس، 2: ضریب تبدیل. */
									esc_html__( 'قیمت‌ها در دیتابیس این سایت به %1$s ذخیره می‌شوند، پس هر قیمت فایل در %2$s ضرب می‌شود.', 'parsian-catalog-sync' ),
									esc_html( 'rial' === $store ? __( 'ریال', 'parsian-catalog-sync' ) : __( 'تومان', 'parsian-catalog-sync' ) ),
									esc_html( pcs_digits( rtrim( rtrim( number_format( $settings->price_multiplier(), 2, '.', '' ), '0' ), '.' ) ) )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'محصولاتی که در فایل نیستند', 'parsian-catalog-sync' ); ?></th>
						<td>
							<?php
							$choices = array(
								'draft'      => __( 'به پیش‌نویس تبدیل شوند (پیشنهادی)', 'parsian-catalog-sync' ),
								'outofstock' => __( 'ناموجود شوند', 'parsian-catalog-sync' ),
								'none'       => __( 'دست‌نخورده بمانند', 'parsian-catalog-sync' ),
								'trash'      => __( 'به زباله‌دان بروند', 'parsian-catalog-sync' ),
							);
							?>
							<select name="missing_action">
								<?php foreach ( $choices as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $settings->get( 'missing_action' ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'محصولات بدون «کد محصول» هرگز تغییر نمی‌کنند، چون با فایل قابل تطبیق نیستند.', 'parsian-catalog-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'ویژگی‌ها', 'parsian-catalog-sync' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="import_attributes" value="1" <?php checked( (bool) $settings->get( 'import_attributes' ) ); ?>>
								<?php esc_html_e( 'ویژگی‌ها از فایل خوانده و روی محصولات اعمال شوند', 'parsian-catalog-sync' ); ?>
							</label>
							<p class="description" style="margin-bottom:10px;">
								<?php esc_html_e( 'پیش‌فرض خاموش است. ویژگی‌های یک محصول متغیر، ساختار واریاسیون‌هایش را تعیین می‌کنند و بازنویسی‌شان از روی فایل می‌تواند پیوند واریاسیون‌ها را بشکند؛ به همین دلیل حتی با روشن بودن این گزینه، ویژگی‌ها فقط روی محصولات ساده اعمال می‌شوند.', 'parsian-catalog-sync' ); ?>
							</p>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="create_attributes" value="1" <?php checked( (bool) $settings->get( 'create_attributes' ) ); ?>>
								<?php esc_html_e( 'ویژگی‌های تازهٔ فایل به‌صورت ویژگی سراسری ساخته شوند', 'parsian-catalog-sync' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'فقط ویژگی‌های سراسری در پنل فیلتر فروشگاه قابل استفاده‌اند.', 'parsian-catalog-sync' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pcs-source"><?php esc_html_e( 'منبع ثابت', 'parsian-catalog-sync' ); ?></label></th>
						<td>
							<input type="text" id="pcs-source" name="source" class="large-text ltr" dir="ltr" value="<?php echo esc_attr( $settings->get( 'source' ) ); ?>" placeholder="https://docs.google.com/…/pub?output=csv">
							<p class="description">
								<?php esc_html_e( 'نشانی یک فایل اینترنتی (مثلاً خروجی CSV گوگل‌شیت) یا نام فایلی داخل پوشهٔ uploads. برای همگام‌سازی خودکار لازم است.', 'parsian-catalog-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'همگام‌سازی خودکار', 'parsian-catalog-sync' ); ?></th>
						<td>
							<?php
							$schedules = array(
								'off'        => __( 'خاموش', 'parsian-catalog-sync' ),
								'hourly'     => __( 'هر ساعت', 'parsian-catalog-sync' ),
								'twicedaily' => __( 'روزی دو بار', 'parsian-catalog-sync' ),
								'daily'      => __( 'روزی یک بار', 'parsian-catalog-sync' ),
							);
							?>
							<select name="schedule">
								<?php foreach ( $schedules as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $settings->get( 'schedule' ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'در حالت خودکار پیش‌نمایشی نمایش داده نمی‌شود؛ تغییرات مستقیم اعمال می‌شوند. نتیجهٔ هر اجرا در تاریخچهٔ پایین همین صفحه ثبت می‌شود.', 'parsian-catalog-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pcs-default-sheet"><?php esc_html_e( 'برگهٔ پیش‌فرض', 'parsian-catalog-sync' ); ?></label></th>
						<td>
							<input type="text" id="pcs-default-sheet" name="sheet" class="regular-text" value="<?php echo esc_attr( $settings->get( 'sheet' ) ); ?>">
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'ذخیرهٔ تنظیمات', 'parsian-catalog-sync' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * تاریخچهٔ اجراها.
	 */
	protected function render_history() {
		$history = PCS_Settings::instance()->history();

		if ( ! $history ) {
			return;
		}
		?>
		<div class="pcs-card">
			<h2><?php esc_html_e( 'تاریخچهٔ همگام‌سازی', 'parsian-catalog-sync' ); ?></h2>

			<table class="widefat striped pcs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'زمان', 'parsian-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'جدید', 'parsian-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'به‌روزرسانی', 'parsian-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'بدون تغییر', 'parsian-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'ناموفق', 'parsian-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'پیام‌ها', 'parsian-catalog-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $run ) : ?>
						<tr>
							<td><?php echo esc_html( pcs_digits( $run['time'] ) ); ?></td>
							<td><?php echo esc_html( pcs_digits( $run['created'] ) ); ?></td>
							<td><?php echo esc_html( pcs_digits( $run['updated'] ) ); ?></td>
							<td><?php echo esc_html( pcs_digits( $run['skipped'] ) ); ?></td>
							<td><?php echo esc_html( pcs_digits( $run['failed'] ) ); ?></td>
							<td>
								<?php if ( empty( $run['notes'] ) ) : ?>
									<span class="pcs-muted">—</span>
								<?php else : ?>
									<ul class="pcs-issues">
										<?php foreach ( $run['notes'] as $note ) : ?>
											<li>
												<?php if ( ! empty( $note['sku'] ) ) : ?>
													<code><?php echo esc_html( $note['sku'] ); ?></code>
												<?php endif; ?>
												<?php echo esc_html( $note['text'] ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
