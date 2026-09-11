<?php
/**
 * صفحهٔ پیشخوان برای تبدیل صفت محلی به سراسری.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * رابط کاربری انتقال صفت.
 */
class PCS_Attribute_Admin {

	const PAGE       = 'pcs-attributes';
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * نمونهٔ یکتا.
	 *
	 * @var PCS_Attribute_Admin|null
	 */
	protected static $instance = null;

	/**
	 * دریافت نمونهٔ یکتا.
	 *
	 * @return PCS_Attribute_Admin
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
		add_action( 'admin_post_pcs_attr_preview', array( $this, 'handle_preview' ) );
		add_action( 'admin_post_pcs_attr_apply', array( $this, 'handle_apply' ) );
	}

	/**
	 * افزودن زیرمنو.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'تبدیل صفت به سراسری', 'parsian-catalog-sync' ),
			__( 'تبدیل صفت به سراسری', 'parsian-catalog-sync' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * ساخت پیش‌نمایش انتقال.
	 */
	public function handle_preview() {
		$this->guard( 'pcs_attr_preview' );

		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$slug  = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

		$plan = PCS_Attribute_Migrator::plan( $label, $slug );

		if ( is_wp_error( $plan ) ) {
			$this->bail( $plan->get_error_message() );
		}

		$token = wp_generate_password( 12, false, false );
		set_transient( 'pcs_attr_plan_' . $token, $plan, HOUR_IN_SECONDS );

		$this->go( array( 'plan' => $token ) );
	}

	/**
	 * اجرای انتقال.
	 */
	public function handle_apply() {
		$this->guard( 'pcs_attr_apply' );

		if ( empty( $_POST['confirm'] ) ) {
			$this->bail( __( 'برای ادامه باید تأیید کنید که از دیتابیس بکاپ گرفته‌اید.', 'parsian-catalog-sync' ) );
		}

		$token = isset( $_POST['plan'] ) ? sanitize_text_field( wp_unslash( $_POST['plan'] ) ) : '';
		$plan  = $token ? get_transient( 'pcs_attr_plan_' . $token ) : false;

		if ( ! is_array( $plan ) ) {
			$this->bail( __( 'پیش‌نمایش منقضی شده است؛ دوباره بسازید.', 'parsian-catalog-sync' ) );
		}

		$report = PCS_Attribute_Migrator::apply( $plan );

		delete_transient( 'pcs_attr_plan_' . $token );

		if ( is_wp_error( $report ) ) {
			$this->bail( $report->get_error_message() );
		}

		$this->go(
			array(
				'done'       => 1,
				'products'   => (int) $report['products'],
				'variations' => (int) $report['variations'],
				'terms'      => (int) $report['terms'],
				'failed'     => count( $report['failed'] ),
			)
		);
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
	 * بازگشت با پیام خطا.
	 *
	 * @param string $message متن.
	 */
	protected function bail( $message ) {
		set_transient( 'pcs_attr_error_' . get_current_user_id(), $message, 5 * MINUTE_IN_SECONDS );
		$this->go( array( 'failed' => 1 ) );
	}

	/**
	 * بازگشت به صفحهٔ افزونه.
	 *
	 * @param array $args پارامترها.
	 */
	protected function go( $args ) {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array(
						'post_type' => 'product',
						'page'      => self::PAGE,
					),
					$args
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/* -------------------------------- نمایش -------------------------------- */

	/**
	 * نمایش صفحه.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط نمایش.
		$token = isset( $_GET['plan'] ) ? sanitize_text_field( wp_unslash( $_GET['plan'] ) ) : '';
		$plan  = $token ? get_transient( 'pcs_attr_plan_' . $token ) : false;
		// phpcs:enable

		echo '<div class="wrap pcs-wrap">';
		echo '<h1>' . esc_html__( 'تبدیل صفت محلی به صفت سراسری', 'parsian-catalog-sync' ) . '</h1>';

		$this->render_notices();

		if ( is_array( $plan ) ) {
			$this->render_preview( $plan, $token );
		} else {
			$this->render_intro();
			$this->render_scan();
		}

		echo '</div>';
	}

	/**
	 * پیام‌ها.
	 */
	protected function render_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['failed'] ) && empty( $_GET['done'] ) ) {
			$message = get_transient( 'pcs_attr_error_' . get_current_user_id() );
			delete_transient( 'pcs_attr_error_' . get_current_user_id() );

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $message ? $message : __( 'انجام نشد.', 'parsian-catalog-sync' ) )
			);
		}

		if ( ! empty( $_GET['done'] ) ) {
			printf(
				'<div class="notice notice-success"><p>%s</p><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: محصولات، 2: واریاسیون‌ها، 3: مقادیر. */
						__( 'انتقال انجام شد — %1$s محصول، %2$s واریاسیون، %3$s مقدار.', 'parsian-catalog-sync' ),
						pcs_digits( (int) $_GET['products'] ),
						pcs_digits( (int) $_GET['variations'] ),
						pcs_digits( (int) $_GET['terms'] )
					)
				),
				esc_html__( 'حالا به ووکامرس ← وضعیت ← ابزارها بروید و «بازسازی جدول شاخص صفت‌های محصول» را اجرا کنید تا شمارندهٔ فیلترها درست شود.', 'parsian-catalog-sync' )
			);
		}
		// phpcs:enable
	}

	/**
	 * توضیح کار.
	 */
	protected function render_intro() {
		?>
		<div class="pcs-card">
			<h2><?php esc_html_e( 'این ابزار چه می‌کند؟', 'parsian-catalog-sync' ); ?></h2>
			<p>
				<?php esc_html_e( 'صفت‌های «محلی» داخل متای خود محصول ذخیره می‌شوند و تاکسونومی ندارند، بنابراین پنل فیلتر فروشگاه نمی‌تواند رویشان فیلتر بگذارد. این ابزار یک صفت محلی را به صفت سراسری تبدیل می‌کند و همهٔ محصولات و واریاسیون‌هایی را که از آن استفاده می‌کنند به‌روز می‌کند.', 'parsian-catalog-sync' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'چرا باید قبلش بکاپ بگیرید:', 'parsian-catalog-sync' ); ?></strong>
				<?php esc_html_e( 'صفت‌های یک محصول متغیر تعیین می‌کنند چه واریاسیون‌هایی وجود دارد. این ابزار کلید و مقدار صفت را روی هر واریاسیون بازنویسی می‌کند؛ اگر چیزی نیمه‌کاره بماند، واریاسیون‌ها از والدشان جدا می‌افتند و محصول قابل خرید نمی‌ماند. پیش از اجرا، پیش‌نمایش را کامل بخوانید.', 'parsian-catalog-sync' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * فهرست صفت‌های محلی موجود.
	 */
	protected function render_scan() {
		$found = PCS_Attribute_Migrator::scan();
		?>
		<div class="pcs-card">
			<h2><?php esc_html_e( 'صفت‌های محلی موجود', 'parsian-catalog-sync' ); ?></h2>

			<?php if ( ! $found ) : ?>
				<p><?php esc_html_e( 'هیچ صفت محلی‌ای پیدا نشد — همهٔ صفت‌ها سراسری‌اند و فیلتر فروشگاه می‌تواند از آن‌ها استفاده کند.', 'parsian-catalog-sync' ); ?></p>
			<?php else : ?>
				<table class="widefat striped pcs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'نام صفت', 'parsian-catalog-sync' ); ?></th>
							<th><?php esc_html_e( 'محصولات', 'parsian-catalog-sync' ); ?></th>
							<th><?php esc_html_e( 'از این تعداد، متغیر', 'parsian-catalog-sync' ); ?></th>
							<th><?php esc_html_e( 'مقادیر', 'parsian-catalog-sync' ); ?></th>
							<th><?php esc_html_e( 'تبدیل', 'parsian-catalog-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $found as $item ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $item['label'] ); ?></strong></td>
								<td><?php echo esc_html( pcs_digits( $item['products'] ) ); ?></td>
								<td><?php echo esc_html( pcs_digits( $item['variable'] ) ); ?></td>
								<td><?php echo esc_html( implode( '، ', $item['options'] ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'pcs_attr_preview' ); ?>
										<input type="hidden" name="action" value="pcs_attr_preview">
										<input type="hidden" name="label" value="<?php echo esc_attr( $item['label'] ); ?>">
										<input type="text" name="slug" dir="ltr" size="10"
											value="<?php echo esc_attr( $this->suggest_slug( $item['label'] ) ); ?>"
											placeholder="type" required>
										<button type="submit" class="button"><?php esc_html_e( 'پیش‌نمایش', 'parsian-catalog-sync' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">
					<?php esc_html_e( 'اسلاگ با حروف لاتین نوشته می‌شود چون نام تاکسونومی وردپرس است و در آدرس صفحات دیده می‌شود؛ نام فارسی صفت به‌عنوان برچسب نمایشی حفظ می‌شود.', 'parsian-catalog-sync' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * پیشنهاد اسلاگ لاتین برای یک برچسب فارسی.
	 *
	 * @param string $label برچسب.
	 * @return string
	 */
	protected function suggest_slug( $label ) {
		$known = array(
			'نوع'        => 'type',
			'سایز'       => 'size',
			'اندازه'     => 'size',
			'رنگ'        => 'color',
			'جنس'        => 'material',
			'تعداد لایه' => 'layers',
			'نوع چاپ'    => 'print',
			'ابعاد'      => 'dimensions',
		);

		$key = PCS_Spreadsheet::normalize_header( $label );

		if ( isset( $known[ $key ] ) ) {
			return $known[ $key ];
		}

		$slug = PCS_Attribute_Migrator::clean_slug( $label );

		return $slug ? $slug : 'attribute';
	}

	/**
	 * پیش‌نمایش انتقال.
	 *
	 * @param array  $plan  نقشه.
	 * @param string $token کلید.
	 */
	protected function render_preview( $plan, $token ) {
		?>
		<div class="pcs-card">
			<h2><?php esc_html_e( 'پیش‌نمایش انتقال', 'parsian-catalog-sync' ); ?></h2>

			<div class="pcs-summary">
				<div class="pcs-stat pcs-stat-update">
					<span class="pcs-stat-number"><?php echo esc_html( pcs_digits( count( $plan['products'] ) ) ); ?></span>
					<span class="pcs-stat-label"><?php esc_html_e( 'محصول', 'parsian-catalog-sync' ); ?></span>
				</div>
				<div class="pcs-stat pcs-stat-update">
					<span class="pcs-stat-number"><?php echo esc_html( pcs_digits( $plan['variations'] ) ); ?></span>
					<span class="pcs-stat-label"><?php esc_html_e( 'واریاسیون', 'parsian-catalog-sync' ); ?></span>
				</div>
				<div class="pcs-stat pcs-stat-create">
					<span class="pcs-stat-number"><?php echo esc_html( pcs_digits( count( $plan['terms'] ) ) ); ?></span>
					<span class="pcs-stat-label"><?php esc_html_e( 'مقدار', 'parsian-catalog-sync' ); ?></span>
				</div>
				<div class="pcs-stat <?php echo $plan['conflicts'] ? 'pcs-stat-error' : 'pcs-stat-unchanged'; ?>">
					<span class="pcs-stat-number"><?php echo esc_html( pcs_digits( count( $plan['conflicts'] ) ) ); ?></span>
					<span class="pcs-stat-label"><?php esc_html_e( 'ناسازگاری', 'parsian-catalog-sync' ); ?></span>
				</div>
			</div>

			<p>
				<?php
				printf(
					/* translators: 1: برچسب صفت، 2: نام تاکسونومی. */
					esc_html__( 'صفت «%1$s» به تاکسونومی %2$s تبدیل می‌شود.', 'parsian-catalog-sync' ),
					'<strong>' . esc_html( $plan['label'] ) . '</strong>',
					'<code>' . esc_html( $plan['taxonomy'] ) . '</code>'
				);
				echo ' ';
				echo $plan['creates_tax']
					? esc_html__( 'این صفت سراسری هنوز وجود ندارد و ساخته می‌شود.', 'parsian-catalog-sync' )
					: esc_html__( 'این صفت سراسری از قبل وجود دارد و از همان استفاده می‌شود.', 'parsian-catalog-sync' );
				?>
			</p>

			<p>
				<?php esc_html_e( 'مقادیری که به ترم تبدیل می‌شوند:', 'parsian-catalog-sync' ); ?>
				<?php foreach ( $plan['terms'] as $term ) : ?>
					<code style="margin-inline-end:6px;"><?php echo esc_html( $term ); ?></code>
				<?php endforeach; ?>
			</p>

			<?php if ( $plan['conflicts'] ) : ?>
				<div class="notice notice-error inline">
					<p><strong><?php esc_html_e( 'تا این موارد اصلاح نشوند انتقال انجام نمی‌شود:', 'parsian-catalog-sync' ); ?></strong></p>
					<p><?php esc_html_e( 'این واریاسیون‌ها مقداری دارند که در فهرست مقادیر صفتِ محصولِ والد نیست، پس نمی‌توان معادل درستش را تعیین کرد. در پیشخوان، مقدار صفت این واریاسیون‌ها را دوباره انتخاب و ذخیره کنید.', 'parsian-catalog-sync' ); ?></p>
					<ul class="pcs-issues pcs-issues-error">
						<?php foreach ( $plan['conflicts'] as $conflict ) : ?>
							<li>
								<?php echo esc_html( $conflict['product'] ); ?>
								← <?php esc_html_e( 'واریاسیون', 'parsian-catalog-sync' ); ?>
								<a href="<?php echo esc_url( get_edit_post_link( $conflict['variation'] ) ); ?>">#<?php echo esc_html( $conflict['variation'] ); ?></a>
								: <code><?php echo esc_html( $conflict['value'] ); ?></code>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<h3><?php esc_html_e( 'محصولاتی که تغییر می‌کنند', 'parsian-catalog-sync' ); ?></h3>
			<table class="widefat striped pcs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'محصول', 'parsian-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'نوع', 'parsian-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'مقادیر', 'parsian-catalog-sync' ); ?></th>
						<th><?php esc_html_e( 'واریاسیون', 'parsian-catalog-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plan['products'] as $entry ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $entry['id'] ) ); ?>">
									<?php echo esc_html( $entry['name'] ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $entry['type'] ); ?></td>
							<td><?php echo esc_html( implode( '، ', $entry['options'] ) ); ?></td>
							<td><?php echo esc_html( pcs_digits( count( $entry['variations'] ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pcs-apply-form">
				<?php wp_nonce_field( 'pcs_attr_apply' ); ?>
				<input type="hidden" name="action" value="pcs_attr_apply">
				<input type="hidden" name="plan" value="<?php echo esc_attr( $token ); ?>">

				<?php if ( $plan['conflicts'] ) : ?>
					<p>
						<a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'product', 'page' => self::PAGE ), admin_url( 'edit.php' ) ) ); ?>">
							<?php esc_html_e( 'بازگشت', 'parsian-catalog-sync' ); ?>
						</a>
					</p>
				<?php else : ?>
					<p>
						<label>
							<input type="checkbox" name="confirm" value="1" required>
							<?php esc_html_e( 'از دیتابیس بکاپ گرفته‌ام و پیش‌نمایش را خوانده‌ام.', 'parsian-catalog-sync' ); ?>
						</label>
					</p>
					<p>
						<button type="submit" class="button button-primary button-hero">
							<?php esc_html_e( 'انجام انتقال', 'parsian-catalog-sync' ); ?>
						</button>
						<a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'product', 'page' => self::PAGE ), admin_url( 'edit.php' ) ) ); ?>">
							<?php esc_html_e( 'انصراف', 'parsian-catalog-sync' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}
