<?php
/**
 * متادیتای پیش‌فروش روی محصولات (زبانه «پیش‌فروش» در ویرایشگر محصول)
 *
 * @package parsian_preorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function parsian_preorder_is_product( $product ) {
	if ( is_numeric( $product ) ) {
		$product = wc_get_product( (int) $product );
	}
	if ( ! is_a( $product, 'WC_Product' ) ) {
		return false;
	}
	return 'yes' === $product->get_meta( '_cartonpak_preorder', true );
}

function parsian_preorder_lead_days( $product ) {
	if ( is_numeric( $product ) ) {
		$product = wc_get_product( (int) $product );
	}
	if ( ! is_a( $product, 'WC_Product' ) ) {
		return 0;
	}
	return max( 0, (int) $product->get_meta( '_cartonpak_lead_days', true ) );
}

add_filter( 'woocommerce_product_data_tabs', 'parsian_preorder_product_tab' );
function parsian_preorder_product_tab( $tabs ) {
	$tabs['preorder'] = array(
		'label'    => 'پیش‌فروش',
		'target'   => 'parsian_preorder_product_data',
		'priority' => 25,
	);
	return $tabs;
}

add_action( 'woocommerce_product_data_panels', 'parsian_preorder_product_panel' );
function parsian_preorder_product_panel() {
	global $post;
	$product = wc_get_product( $post->ID );
	$enabled = parsian_preorder_is_product( $product );
	$days    = parsian_preorder_lead_days( $product );
	?>
	<div id="parsian_preorder_product_data" class="panel woocommerce_options_panel">
		<div class="options_group">
			<?php
			woocommerce_wp_checkbox( array(
				'id'          => '_cartonpak_preorder',
				'label'       => 'فعال‌سازی پیش‌فروش',
				'description' => 'با فعال‌شدن، دکمه «افزودن به سبد خرید» با فرم ثبت پیش‌فروش جایگزین می‌شود و مشتری درخواست خود را ثبت می‌کند.',
				'value'       => $enabled ? 'yes' : 'no',
			) );
			woocommerce_wp_text_input( array(
				'id'                => '_cartonpak_lead_days',
				'label'             => 'زمان ارسال (روز کاری)',
				'description'       => 'تعداد روزهای کاری تا آماده‌سازی و ارسال؛ برای مشتری نمایش داده می‌شود.',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
				'value'             => $days,
			) );
			?>
		</div>
	</div>
	<?php
}

add_action( 'woocommerce_process_product_meta', 'parsian_preorder_save_product_meta' );
function parsian_preorder_save_product_meta( $post_id ) {
	$product = wc_get_product( $post_id );
	if ( ! $product ) {
		return;
	}
	$enabled = isset( $_POST['_cartonpak_preorder'] ) ? sanitize_text_field( wp_unslash( $_POST['_cartonpak_preorder'] ) ) : 'no';
	$enabled = 'yes' === $enabled;
	$product->update_meta_data( '_cartonpak_preorder', $enabled ? 'yes' : 'no' );
	$days = isset( $_POST['_cartonpak_lead_days'] ) ? (int) $_POST['_cartonpak_lead_days'] : 0;
	$product->update_meta_data( '_cartonpak_lead_days', $enabled ? max( 1, $days ) : 0 );
	$product->save();
}