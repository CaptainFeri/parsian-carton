<?php
/**
 * پیشخوان: نوع نوشته «پیش‌فروش»، ستون‌ها، تغییر وضعیت، فیلتر، خروجی CSV، تنظیمات پیامک
 *
 * @package parsian_preorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- نوع نوشته ---------- */

add_action( 'init', 'parsian_preorder_register_post_type' );
function parsian_preorder_register_post_type() {
	register_post_type( 'preorder', array(
		'labels'        => array(
			'name'          => 'پیش‌فروش‌ها',
			'singular_name' => 'پیش‌فروش',
			'menu_name'     => 'پیش‌فروش‌ها',
			'edit_item'     => 'ویرایش پیش‌فروش',
			'search_items'  => 'جستجوی پیش‌فروش',
			'not_found'     => 'درخواست پیش‌فروشی یافت نشد.',
		),
		'public'        => false,
		'show_ui'       => true,
		'menu_icon'     => 'dashicons-cart',
		'menu_position' => 57,
		'supports'      => array( 'title' ),
		'capabilities'  => array(
			'create_posts' => 'do_not_allow',
		),
		'map_meta_cap'  => true,
	) );
}

/* ---------- ستون‌های فهرست ---------- */

add_filter( 'manage_preorder_posts_columns', 'parsian_preorder_columns' );
function parsian_preorder_columns( $columns ) {
	return array(
		'cb'     => $columns['cb'],
		'title'  => 'محصول',
		'qty'    => 'تعداد',
		'phone'  => 'تلفن',
		'name'   => 'نام',
		'lead'   => 'زمان ارسال',
		'cdate'  => 'تاریخ',
		'status' => 'وضعیت',
	);
}

add_action( 'manage_preorder_posts_custom_column', 'parsian_preorder_columns_content', 10, 2 );
function parsian_preorder_columns_content( $column, $post_id ) {
	switch ( $column ) {
		case 'qty':
			echo esc_html( parsian_preorder_fa_digits( (string) (int) get_post_meta( $post_id, '_preorder_qty', true ) ) );
			break;

		case 'phone':
			$phone = get_post_meta( $post_id, '_preorder_phone', true );
			if ( $phone ) {
				echo '<a href="tel:' . esc_attr( $phone ) . '" dir="ltr">' . esc_html( $phone ) . '</a>';
			} else {
				echo '—';
			}
			break;

		case 'name':
			$name = get_post_meta( $post_id, '_preorder_name', true );
			echo esc_html( $name ? $name : '—' );
			break;

		case 'lead':
			$days = (int) get_post_meta( $post_id, '_preorder_lead_days', true );
			echo $days > 0 ? esc_html( parsian_preorder_fa_digits( (string) $days ) . ' روز کاری' ) : '—';
			break;

		case 'cdate':
			echo esc_html( get_the_date( 'Y/m/d H:i', $post_id ) );
			break;

		case 'status':
			$status   = get_post_meta( $post_id, '_preorder_status', true );
			$status   = $status ? $status : 'new';
			$statuses = parsian_preorder_statuses();
			echo '<select class="preorder-status-select" data-preorder-id="' . esc_attr( $post_id ) . '">';
			foreach ( $statuses as $key => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $key ),
					selected( $status, $key, false ),
					esc_html( $label )
				);
			}
			echo '</select>';
			break;
	}
}

add_action( 'wp_ajax_parsian_preorder_status', 'parsian_preorder_ajax_status' );
function parsian_preorder_ajax_status() {
	check_ajax_referer( 'parsian-preorder', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ) );
	}
	$post_id = absint( $_POST['preorder_id'] ?? 0 );
	$status  = sanitize_key( $_POST['status'] ?? '' );
	if ( ! $post_id || ! array_key_exists( $status, parsian_preorder_statuses() ) ) {
		wp_send_json_error( array( 'message' => 'ورودی نامعتبر.' ) );
	}
	update_post_meta( $post_id, '_preorder_status', $status );
	wp_send_json_success();
}

/* ---------- فیلتر وضعیت + دکمه خروجی CSV ---------- */

add_action( 'restrict_manage_posts', 'parsian_preorder_status_filter' );
function parsian_preorder_status_filter( $post_type ) {
	if ( 'preorder' !== $post_type ) {
		return;
	}
	$current = isset( $_GET['preorder_status'] ) ? sanitize_key( $_GET['preorder_status'] ) : '';
	echo '<select name="preorder_status">';
	echo '<option value="">همه وضعیت‌ها</option>';
	foreach ( parsian_preorder_statuses() as $key => $label ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $key ),
			selected( $current, $key, false ),
			esc_html( $label )
		);
	}
	echo '</select>';

	$export_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=parsian_preorder_export&status=' . $current ),
		'parsian-preorder-export'
	);
	echo ' <a class="button" style="margin-inline-start:6px" href="' . esc_url( $export_url ) . '">خروجی CSV</a>';
}

add_action( 'pre_get_posts', 'parsian_preorder_filter_query' );
function parsian_preorder_filter_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'preorder' !== $query->get( 'post_type' ) ) {
		return;
	}
	$status = isset( $_GET['preorder_status'] ) ? sanitize_key( $_GET['preorder_status'] ) : '';
	if ( '' !== $status && array_key_exists( $status, parsian_preorder_statuses() ) ) {
		$query->set( 'meta_query', array(
			array(
				'key'   => '_preorder_status',
				'value' => $status,
			),
		) );
	}
}

/* ---------- خروجی CSV ---------- */

add_action( 'admin_post_parsian_preorder_export', 'parsian_preorder_export_csv' );
function parsian_preorder_export_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی غیرمجاز.' );
	}
	check_admin_referer( 'parsian-preorder-export' );

	$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
	$args   = array(
		'post_type'      => 'preorder',
		'post_status'    => 'private',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( '' !== $status && array_key_exists( $status, parsian_preorder_statuses() ) ) {
		$args['meta_query'] = array(
			array(
				'key'   => '_preorder_status',
				'value' => $status,
			),
		);
	}
	$posts = get_posts( $args );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="preorders-' . gmdate( 'Y-m-d' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	// BOM — برای باز شدن صحیح متن فارسی در اکسل.
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'شماره', 'تاریخ', 'محصول', 'تعداد', 'تلفن', 'نام', 'توضیحات', 'زمان ارسال (روز کاری)', 'وضعیت' ) );

	foreach ( $posts as $post ) {
		$product_id = (int) get_post_meta( $post->ID, '_preorder_product_id', true );
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$status_key = get_post_meta( $post->ID, '_preorder_status', true );
		fputcsv( $out, array(
			'#' . $post->ID,
			get_the_date( 'Y/m/d H:i', $post->ID ),
			$product ? $product->get_name() : (string) get_the_title( $post->ID ),
			(int) get_post_meta( $post->ID, '_preorder_qty', true ),
			(string) get_post_meta( $post->ID, '_preorder_phone', true ),
			(string) get_post_meta( $post->ID, '_preorder_name', true ),
			(string) get_post_meta( $post->ID, '_preorder_note', true ),
			(int) get_post_meta( $post->ID, '_preorder_lead_days', true ),
			parsian_preorder_status_label( $status_key ),
		) );
	}

	fclose( $out );
	exit;
}

/* ---------- باکس جزئیات در ویرایشگر ---------- */

add_action( 'add_meta_boxes', 'parsian_preorder_meta_box' );
function parsian_preorder_meta_box() {
	add_meta_box( 'parsian_preorder_details', 'جزئیات پیش‌فروش', 'parsian_preorder_meta_box_html', 'preorder', 'normal', 'high' );
}

function parsian_preorder_meta_box_html( $post ) {
	$product_id = (int) get_post_meta( $post->ID, '_preorder_product_id', true );
	$product    = $product_id ? wc_get_product( $product_id ) : null;
	$status     = get_post_meta( $post->ID, '_preorder_status', true );
	$status     = $status ? $status : 'new';
	$sms_status = get_post_meta( $post->ID, '_preorder_sms_status', true );
	$user_id    = (int) get_post_meta( $post->ID, '_preorder_user_id', true );
	$sms_labels = array(
		'sent'  => 'ارسال شد',
		'test'  => 'ارسال نشد (بدون کلید API)',
		'error' => 'خطا در ارسال',
	);

	$rows = array(
		'محصول'   => $product ? '<a href="' . esc_url( get_edit_post_link( $product_id ) ) . '" target="_blank">' . esc_html( $product->get_name() ) . '</a>' : '—',
		'تعداد'   => esc_html( parsian_preorder_fa_digits( (string) (int) get_post_meta( $post->ID, '_preorder_qty', true ) ) . ' بسته' ),
		'تلفن'    => '<a href="tel:' . esc_attr( (string) get_post_meta( $post->ID, '_preorder_phone', true ) ) . '" dir="ltr">' . esc_html( (string) get_post_meta( $post->ID, '_preorder_phone', true ) ) . '</a>',
		'نام'     => esc_html( (string) get_post_meta( $post->ID, '_preorder_name', true ) ?: '—' ),
		'توضیحات' => esc_html( (string) get_post_meta( $post->ID, '_preorder_note', true ) ?: '—' ),
		'زمان ارسال' => esc_html( (int) get_post_meta( $post->ID, '_preorder_lead_days', true ) > 0 ? parsian_preorder_fa_digits( (string) (int) get_post_meta( $post->ID, '_preorder_lead_days', true ) ) . ' روز کاری' : '—' ),
		'پیامک مشتری' => isset( $sms_labels[ $sms_status ] ) ? esc_html( $sms_labels[ $sms_status ] ) : '—',
		'کاربر'   => $user_id ? '<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '" target="_blank">' . esc_html( get_userdata( $user_id )->user_login ) . '</a>' : '—',
	);
	?>
	<table class="widefat striped" style="max-width:720px">
		<tbody>
			<?php foreach ( $rows as $label => $html ) : ?>
				<tr>
					<th style="width:140px"><?php echo esc_html( $label ); ?></th>
					<td><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th>وضعیت</th>
				<td>
					<select class="preorder-status-select" data-preorder-id="<?php echo esc_attr( $post->ID ); ?>">
						<?php foreach ( parsian_preorder_statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}

/* ---------- تنظیمات پیامک ---------- */

add_action( 'admin_menu', 'parsian_preorder_submenu' );
function parsian_preorder_submenu() {
	add_submenu_page(
		'edit.php?post_type=preorder',
		'تنظیمات پیش‌فروش',
		'تنظیمات',
		'manage_options',
		'parsian-preorder-settings',
		'parsian_preorder_settings_page'
	);
}

function parsian_preorder_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'parsian_preorder_settings' ) ) {
		update_option( 'parsian_preorder_customer_template', sanitize_text_field( wp_unslash( $_POST['customer_template'] ?? '' ) ) );
		update_option( 'parsian_preorder_admin_template', sanitize_text_field( wp_unslash( $_POST['admin_template'] ?? '' ) ) );
		update_option( 'parsian_preorder_admin_phone', sanitize_text_field( wp_unslash( $_POST['admin_phone'] ?? '' ) ) );
		echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>';
	}

	$api_key    = parsian_preorder_api_key();
	$admin_phone = parsian_preorder_admin_phone();
	?>
	<div class="wrap">
		<h1>تنظیمات پیش‌فروش</h1>
		<p>درخواست‌های پیش‌فروش از طریق سرویس پیامک کاوه‌نگار (Kavenegar) اطلاع‌رسانی می‌شوند. کلید API از تنظیمات افزونه «ورود با پیامک» (پیشخوان ← تنظیمات ← ورود با پیامک) برداشته می‌شود.</p>
		<?php if ( '' === $api_key ) : ?>
			<div class="notice notice-warning inline">
				<p><strong>کلید API ثبت نشده است؛</strong> پیامکی ارسال نمی‌شود و درخواست‌ها فقط در پیشخوان ثبت می‌شوند. برای فعال‌سازی پیامک، کلید API را در «تنظیمات ← ورود با پیامک» وارد کنید.</p>
			</div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'parsian_preorder_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="customer_template">قالب پیامک مشتری</label></th>
					<td>
						<input type="text" class="regular-text" id="customer_template" name="customer_template" value="<?php echo esc_attr( parsian_preorder_get_option( 'customer_template' ) ); ?>" />
						<p class="description">قالب تأییدشده در پنل کاوه‌نگار با دو پارامتر: <code>token</code> = روزهای کاری، <code>token2</code> = نام محصول. مثال: <code>parsianpreorder</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="admin_template">قالب پیامک پشتیبانی</label></th>
					<td>
						<input type="text" class="regular-text" id="admin_template" name="admin_template" value="<?php echo esc_attr( parsian_preorder_get_option( 'admin_template' ) ); ?>" />
						<p class="description">قالب اطلاع‌رسانی درخواست جدید به پشتیبانی با دو پارامتر: <code>token</code> = شماره درخواست، <code>token2</code> = نام محصول و تعداد. مثال: <code>parsianpreorderadmin</code> — برای غیرفعال‌سازی، خالی بگذارید.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="admin_phone">تلفن پشتیبانی</label></th>
					<td>
						<input type="text" class="regular-text" id="admin_phone" name="admin_phone" value="<?php echo esc_attr( parsian_preorder_get_option( 'admin_phone' ) ); ?>" dir="ltr" placeholder="09*********" />
						<p class="description">درخواست جدید به این شماره پیامک می‌شود. اگر خالی باشد، از «موبایل / ثبت سفارش» در سفارشی‌سازی قالب استفاده می‌شود (فعلاً: <?php echo esc_html( $admin_phone ); ?>).</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'ذخیره تنظیمات' ); ?>
		</form>
	</div>
	<?php
}

/* ---------- اسکریپت و استایل پیشخوان ---------- */

add_action( 'admin_enqueue_scripts', 'parsian_preorder_admin_assets' );
function parsian_preorder_admin_assets( $hook ) {
	$is_list    = 'edit.php' === $hook && 'preorder' === ( $_GET['post_type'] ?? '' );
	$is_editor  = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'preorder' === ( $_GET['post_type'] ?? get_post_type( (int) ( $_GET['post'] ?? 0 ) ) );
	if ( ! $is_list && ! $is_editor ) {
		return;
	}
	wp_enqueue_script( 'parsian-preorder-admin', PARSIAN_PREORDER_URL . 'assets/parsian-preorder-admin.js', array( 'jquery' ), PARSIAN_PREORDER_VER, true );
	wp_localize_script(
		'parsian-preorder-admin',
		'parsianPreorder',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'parsian-preorder' ),
		)
	);
	wp_enqueue_style( 'parsian-preorder-admin', PARSIAN_PREORDER_URL . 'assets/parsian-preorder-admin.css', array(), PARSIAN_PREORDER_VER );
}