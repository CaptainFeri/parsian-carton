<?php
/**
 * سبد خرید — قالب اختصاصی کارتن‌پک
 *
 * @package cartonpak
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' ); ?>

<form class="woocommerce-cart-form cart-layout" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
	<?php do_action( 'woocommerce_before_cart_table' ); ?>

	<div class="cart-columns">

		<div class="cart-items-panel">
			<div class="cart-panel-head">
				<h2 class="cart-panel-title">سبد خرید شما</h2>
				<span class="cart-panel-count"><?php echo esc_html( cartonpak_digits( WC()->cart->get_cart_contents_count() ) ); ?> کالا</span>
			</div>

			<div class="cart-items">
				<?php
				foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
					$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
					$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
					$visible    = apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key );

					if ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) {
						$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
						$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
						?>
						<div class="cart-item woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

							<div class="cart-item-thumb">
								<?php
								$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );
								if ( ! $product_permalink ) {
									echo $thumbnail; // PHPCS: XSS ok.
								} else {
									printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // PHPCS: XSS ok.
								}
								?>
							</div>

							<div class="cart-item-info">
								<div class="cart-item-name">
									<?php
									if ( ! $product_permalink ) {
										echo wp_kses_post( $product_name . '&nbsp;' );
									} else {
										echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
									}
									do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
									echo wp_kses_post( wc_get_formatted_cart_item_data( $cart_item ) );
									if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
										echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
									}
									?>
								</div>
							</div>

							<div class="cart-item-qty">
								<span class="cart-item-label">تعداد</span>
								<div class="qty-stepper">
									<button type="button" class="qty-step qty-minus" aria-label="کاهش تعداد">−</button>
									<?php
									if ( $_product->is_sold_individually() ) {
										$min_quantity = 1;
										$max_quantity = 1;
									} else {
										$min_quantity = 0;
										$max_quantity = $_product->get_max_purchase_quantity();
									}
									$product_quantity = woocommerce_quantity_input(
										array(
											'input_name'   => "cart[{$cart_item_key}][qty]",
											'input_value'  => $cart_item['quantity'],
											'max_value'    => $max_quantity,
											'min_value'    => $min_quantity,
											'product_name' => $product_name,
										),
										$_product,
										false
									);
									echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // PHPCS: XSS ok.
									?>
									<button type="button" class="qty-step qty-plus" aria-label="افزایش تعداد">+</button>
								</div>
							</div>

							<div class="cart-item-total">
								<span class="cart-item-label">قیمت</span>
								<strong class="cart-item-price">
									<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // PHPCS: XSS ok. ?>
								</strong>
							</div>

							<div class="cart-item-remove">
								<?php
								echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									'woocommerce_cart_item_remove_link',
									sprintf(
										'<a role="button" href="%s" class="remove-cart-item" aria-label="%s" data-product_id="%s" data-product_sku="%s">✕</a>',
										esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
										esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
										esc_attr( $product_id ),
										esc_attr( $_product->get_sku() )
									),
									$cart_item_key
								);
								?>
							</div>
						</div>
						<?php
					}
				}
				do_action( 'woocommerce_cart_contents' );
				?>
			</div>

			<div class="cart-actions-row">
				<a class="btn btn-ghost btn-small" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">→ ادامه خرید</a>
				<button type="submit" class="btn btn-small btn-dark" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>">🔄 <?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>
				<?php do_action( 'woocommerce_cart_actions' ); ?>
				<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
			</div>

			<?php do_action( 'woocommerce_after_cart_contents' ); ?>
		</div>

		<aside class="cart-summary">
			<div class="cart-summary-inner">
				<h3 class="cart-summary-title">خلاصه سفارش</h3>

				<?php if ( wc_coupons_enabled() ) : ?>
					<div class="cart-coupon">
						<p class="cart-coupon-title">کد تخفیف دارید؟</p>
						<div class="cart-coupon-row">
							<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="کد تخفیف" />
							<button type="submit" class="btn btn-small" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>">اعمال</button>
						</div>
						<?php do_action( 'woocommerce_cart_coupon' ); ?>
					</div>
				<?php endif; ?>

				<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
					<div class="cart-coupon-applied">
						<span class="cart-coupon-applied-code">کد «<?php echo esc_html( $code ); ?>»</span>
						<span class="cart-coupon-applied-amount">−<?php echo wp_kses_post( wc_price( WC()->cart->get_coupon_discount_amount( $code, WC()->cart->display_cart_ex_tax ) ) ); ?></span>
						<a href="<?php echo esc_url( wc_get_cart_remove_url( $code ) ); ?>" class="cart-coupon-remove" aria-label="حذف کد تخفیف">✕</a>
					</div>
				<?php endforeach; ?>

				<div class="cart-totals">
					<div class="cart-totals-row">
						<span>جمع جزء</span>
						<strong><?php wc_cart_totals_subtotal_html(); ?></strong>
					</div>

					<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
						<div class="cart-totals-row">
							<span>حمل‌ونقل</span>
							<strong><?php wc_cart_totals_shipping_html(); ?></strong>
						</div>
					<?php endif; ?>

					<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
						<div class="cart-totals-row">
							<span><?php echo esc_html( $fee->name ); ?></span>
							<strong><?php wc_cart_totals_fee_html( $fee ); ?></strong>
						</div>
					<?php endforeach; ?>

					<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
						<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
							<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
								<div class="cart-totals-row">
									<span><?php echo esc_html( $tax->label ); ?></span>
									<strong><?php echo wp_kses_post( $tax->formatted_amount ); ?></strong>
								</div>
							<?php endforeach; ?>
						<?php else : ?>
							<div class="cart-totals-row">
								<span><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></span>
								<strong><?php wc_cart_totals_taxes_total_html(); ?></strong>
							</div>
						<?php endif; ?>
					<?php endif; ?>

					<div class="cart-totals-row cart-totals-total">
						<span>مبلغ قابل پرداخت</span>
						<strong class="cart-grand-total"><?php wc_cart_totals_order_total_html(); ?></strong>
					</div>
				</div>

				<a class="btn btn-primary btn-block cart-checkout-btn" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">ادامه و پرداخت ←</a>
				<p class="cart-checkout-note">پرداخت در محل و ارسال به سراسر کشور</p>
			</div>
		</aside>

	</div>

	<?php do_action( 'woocommerce_after_cart_table' ); ?>
</form>

<?php do_action( 'woocommerce_after_cart' ); ?>
