<?php
/**
 * هدر قالب کارتن‌پک
 *
 * @package cartonpak
 */
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

<div class="site">

	<?php /* نوار بالای سایت */ ?>
	<div class="topbar">
		<div class="container topbar-inner">
			<div class="topbar-items">
				<span class="topbar-item"><span class="topbar-ic">🏭</span> تولیدکننده انواع کارتن و جعبه بسته‌بندی در سمنان</span>
				<span class="topbar-item"><span class="topbar-ic">🚚</span> ارسال به سراسر کشور</span>
				<span class="topbar-item"><span class="topbar-ic">🎨</span> چاپ اختصاصی و دایکات</span>
				<span class="topbar-item"><span class="topbar-ic">🛡️</span> ضمانت کیفیت کالا</span>
			</div>
			<div class="topbar-social">
				<a href="<?php echo esc_url( cartonpak_option( 'cartonpak_whatsapp' ) ); ?>" target="_blank" rel="noopener" aria-label="پیام‌رسان‌ها" class="topbar-social-link">روبیکا | بله | ایتا</a>
				<a href="<?php echo esc_url( cartonpak_option( 'cartonpak_instagram' ) ); ?>" target="_blank" rel="noopener" aria-label="اینستاگرام" class="topbar-social-link">اینستاگرام</a>
			</div>
		</div>
	</div>

	<?php /* هدر اصلی */ ?>
	<header class="site-header">
		<div class="container header-main">
			<button class="nav-toggle" id="navToggle" aria-label="باز کردن منو" aria-expanded="false">
				<span></span><span></span><span></span>
			</button>

			<div class="site-brand">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a class="brand-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<span class="brand-logo-icon">📦</span>
						<span class="brand-logo-text">پارسیان <span class="brand-logo-accent">کارتن</span></span>
					</a>
				<?php endif; ?>
			</div>

			<div class="header-search">
				<form role="search" method="get" class="search-form" action="<?php echo esc_url( class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">
					<svg class="search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.5" y2="16.5"></line></svg>
					<input type="search" name="<?php echo esc_attr( class_exists( 'WooCommerce' ) ? 's' : 's' ); ?>" placeholder="جستجوی کارتن، جعبه، ملزومات بسته‌بندی..." value="<?php echo esc_attr( get_search_query() ); ?>">
					<button type="submit" class="search-submit">جستجو</button>
				</form>
			</div>

			<div class="header-actions">
				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<?php if ( is_user_logged_in() ) : ?>
						<?php
						$current_user      = wp_get_current_user();
						$profile_firstname = trim( (string) get_user_meta( $current_user->ID, 'first_name', true ) );
						$profile_label     = $profile_firstname ? $profile_firstname : 'حساب کاربری';
						?>
						<div class="header-account" title="<?php echo esc_attr( $profile_label ); ?>">
							<a class="header-action header-account-trigger" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
								<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
								<span class="header-action-label"><?php echo esc_html( $profile_label ); ?></span>
							</a>
							<div class="account-menu">
								<span class="account-menu-title"><?php echo esc_html( $profile_label ); ?></span>
								<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">پیشخوان</a>
								<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">سفارش‌ها</a>
								<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>">نشانی‌ها</a>
								<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">جزئیات حساب</a>
								<span class="account-menu-sep"></span>
								<a class="account-menu-logout" href="<?php echo esc_url( wc_logout_url() ); ?>">خروج از حساب</a>
							</div>
						</div>
					<?php else : ?>
						<a class="header-action header-account" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" title="ورود / ثبت‌نام">
							<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
							<span class="header-action-label">ورود / ثبت‌نام</span>
						</a>
					<?php endif; ?>
					<a class="header-action header-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" title="سبد خرید">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
						<span class="cart-count">۰</span>
						<span class="header-action-label">سبد خرید</span>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php /* منوی اصلی + دسته‌بندی‌ها */ ?>
		<nav class="main-nav" id="mainNav">
			<div class="container main-nav-inner">
				<div class="nav-cats" id="navCats">
					<button class="nav-cats-toggle" id="navCatsToggle">
						<span class="nav-cats-burger"><span></span><span></span><span></span></span>
						دسته‌بندی محصولات
					</button>
					<div class="nav-cats-dropdown" id="navCatsDropdown">
						<?php
						if ( class_exists( 'WooCommerce' ) ) {
							$cats = get_terms( array(
								'taxonomy'   => 'product_cat',
								'hide_empty' => false,
								'parent'     => 0,
								'number'     => 12,
							) );
							if ( ! is_wp_error( $cats ) && $cats ) {
								echo '<a class="nav-cat-all" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">همه محصولات</a>';
								foreach ( $cats as $cat ) {
									echo '<a class="nav-cat-item" href="' . esc_url( get_term_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
								}
							}
						}
						?>
					</div>
				</div>

				<?php
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'main-menu',
					'fallback_cb'    => 'cartonpak_default_menu',
				) );
				?>

				<div class="nav-hotline">
					<span class="nav-hotline-icon">☎</span>
					<div class="nav-hotline-text">
						<small>ثبت سفارش تلفنی</small>
						<strong dir="ltr"><?php echo esc_html( cartonpak_option( 'cartonpak_mobile' ) ); ?></strong>
					</div>
				</div>
			</div>
		</nav>

		<div class="nav-overlay" id="navOverlay"></div>
	</header>

	<main class="site-main">
