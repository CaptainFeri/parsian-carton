<?php
/**
 * نمای فهرست درخواست‌ها.
 *
 * فرم فهرست عمداً GET است — مثل فهرست نوشته‌های خود وردپرس — تا نشانی هر
 * فیلتر قابل ذخیره و هم‌رسانی باشد. عملیات گروهی هم از همین فرم می‌رود و با
 * nonce خود WP_List_Table بررسی می‌شود.
 *
 * @package parsian-preorder
 *
 * @var PPO_List_Table $table
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط بازتاب فیلترهای نمایش.
$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$product = isset( $_GET['product'] ) ? (int) $_GET['product'] : 0;
$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// phpcs:enable
?>
<div class="wrap ppo-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'درخواست‌های پیش‌فروش', 'parsian-preorder' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ppo-export-form">
		<?php wp_nonce_field( 'ppo_export' ); ?>
		<input type="hidden" name="action" value="ppo_export">
		<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
		<input type="hidden" name="product" value="<?php echo esc_attr( $product ); ?>">
		<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
		<button type="submit" class="page-title-action"><?php esc_html_e( 'خروجی CSV از همین فهرست', 'parsian-preorder' ); ?></button>
	</form>

	<hr class="wp-header-end">

	<?php $table->views(); ?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="ppo-requests">
		<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">

		<?php
		$table->search_box( __( 'جستجو', 'parsian-preorder' ), 'ppo-search' );
		$table->display();
		?>
	</form>
</div>
