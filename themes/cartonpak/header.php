<?php
/**
 * هدر قالب: نوار بالا، لوگو، منو، جستجو، حساب کاربری و سبد خرید.
 *
 * @package cartonpak
 */

$has_wc  = class_exists( 'WooCommerce' );
$support = cartonpak_option( 'cartonpak_support' );
$topbar  = cartonpak_option( 'cartonpak_topbar_text' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main">رفتن به محتوای اصلی</a>

<div class="site">

	<div class="topbar">
		<div class="container topbar-inner">
			<?php if ( $topbar ) : ?>
				<span class="topbar-note"><?php echo esc_html( $topbar ); ?></span>
			<?php endif; ?>
			<?php if ( $support ) : ?>
				<a class="topbar-phone" href="<?php echo esc_url( cartonpak_tel( $support ) ); ?>">پشتیبانی: <span dir="ltr"><?php echo esc_html( $support ); ?></span></a>
			<?php endif; ?>
		</div>
	</div>

	<header class="site-header">
		<div class="container header-inner">
			<button type="button" class="icon-btn nav-toggle" aria-controls="siteNav" aria-expanded="false" aria-label="باز کردن منو">
				<?php echo cartonpak_icon( 'menu', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>

			<div class="brand">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a class="brand-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<?php echo cartonpak_icon( 'logo', 34, 'brand-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="brand-name"><?php bloginfo( 'name' ); ?></span>
					</a>
				<?php endif; ?>
			</div>

			<nav class="site-nav" id="siteNav" aria-label="منوی اصلی">
				<div class="site-nav-head">
					<span class="brand-name"><?php bloginfo( 'name' ); ?></span>
					<button type="button" class="icon-btn nav-close" aria-label="بستن منو">
						<?php echo cartonpak_icon( 'close', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
				</div>
				<?php
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'main-menu',
					'depth'          => 2,
					'fallback_cb'    => 'cartonpak_default_menu',
				) );
				?>
			</nav>

			<form role="search" method="get" class="header-search" action="<?php echo esc_url( $has_wc ? cartonpak_shop_url() : home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="headerSearch">جستجوی محصول</label>
				<?php echo cartonpak_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input id="headerSearch" type="search" name="s" placeholder="جستجو: ابعاد یا نوع کارتن" value="<?php echo esc_attr( get_search_query() ); ?>">
				<?php if ( $has_wc ) : ?>
					<input type="hidden" name="post_type" value="product">
				<?php endif; ?>
			</form>

			<?php if ( $has_wc ) : ?>
				<a class="icon-btn header-account" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" aria-label="<?php echo esc_attr( is_user_logged_in() ? 'حساب کاربری' : 'ورود / ثبت‌نام' ); ?>">
					<?php echo cartonpak_icon( 'user', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
				<a class="header-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
					<?php echo cartonpak_icon( 'cart', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="header-cart-label">سبد خرید</span>
					<?php echo cartonpak_cart_count_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endif; ?>
		</div>
		<div class="nav-overlay" hidden></div>
	</header>

	<main id="main" class="site-main" tabindex="-1">
