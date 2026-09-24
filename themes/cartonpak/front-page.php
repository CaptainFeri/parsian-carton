<?php
/**
 * صفحهٔ اصلی: معرفی، دسته‌بندی‌ها، قیمت پلکانی، پرفروش‌ترین‌ها و فرم ابعاد دلخواه.
 *
 * @package cartonpak
 */

get_header();

$has_wc   = class_exists( 'WooCommerce' );
$shop_url = cartonpak_shop_url();
$tiers    = cartonpak_tiers();
$hero_img = (int) get_theme_mod( 'cartonpak_hero_image', 0 );
?>

<section class="container hero" aria-labelledby="heroTitle">
	<div class="hero-text">
		<span class="eyebrow">تولید و فروش مستقیم کارتن بسته‌بندی</span>
		<h1 id="heroTitle">کارتن بسته‌بندی،<br>درست به اندازهٔ محصول شما</h1>
		<p class="hero-lead">
			کارتن سه‌لایه و پنج‌لایه در ابعاد استاندارد یا سفارشی، با امکان چاپ لوگو.
			<?php if ( $tiers ) : ?>
				خرید تکی یا عمده با قیمت پلکانی که خودکار در سبد خرید اعمال می‌شود.
			<?php else : ?>
				خرید تکی یا عمده، مستقیم از کارخانه.
			<?php endif; ?>
		</p>
		<div class="hero-actions">
			<a class="btn btn-primary btn-lg" href="<?php echo esc_url( $shop_url ); ?>">مشاهدهٔ محصولات</a>
			<a class="btn btn-secondary btn-lg" href="#quote">استعلام قیمت عمده</a>
		</div>
		<ul class="hero-features">
			<li><?php echo cartonpak_icon( 'truck', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>ارسال به سراسر ایران</span></li>
			<?php if ( $tiers ) : ?>
				<li><?php echo cartonpak_icon( 'tag', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>تخفیف پلکانی خرید عمده</span></li>
			<?php else : ?>
				<li><?php echo cartonpak_icon( 'print', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>چاپ اختصاصی لوگو</span></li>
			<?php endif; ?>
			<li><?php echo cartonpak_icon( 'chat', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>مشاورهٔ انتخاب کارتن</span></li>
		</ul>
	</div>

	<div class="hero-visual<?php echo $hero_img ? ' has-photo' : ''; ?>">
		<?php if ( $hero_img ) : ?>
			<?php echo wp_get_attachment_image( $hero_img, 'large', false, array( 'class' => 'hero-photo', 'fetchpriority' => 'high', 'sizes' => '(max-width: 1024px) 100vw, 620px' ) ); ?>
		<?php else : ?>
			<div class="hero-cartons" aria-hidden="true">
				<?php
				echo cartonpak_carton_art( 150 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo cartonpak_carton_art( 300 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo cartonpak_carton_art( 200 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		<?php endif; ?>
		<span class="float-card float-card-top"><?php echo cartonpak_icon( 'print', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>چاپ لوگوی اختصاصی روی کارتن</span>
		<span class="float-card float-card-bottom"><?php echo cartonpak_icon( 'resize', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>ابعاد استاندارد و سفارشی</span>
	</div>
</section>

<?php
$categories = $has_wc ? cartonpak_home_categories( 4 ) : array();
if ( $categories ) :
	?>
	<section class="container section section-cats" aria-labelledby="catsTitle">
		<div class="section-head">
			<h2 id="catsTitle">دسته‌بندی محصولات</h2>
			<a class="section-link" href="<?php echo esc_url( $shop_url ); ?>">همهٔ محصولات<?php echo cartonpak_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</div>
		<div class="cat-grid">
			<?php foreach ( $categories as $term ) : ?>
				<?php $meta = cartonpak_category_meta( $term ); ?>
				<a class="cat-card" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
					<span class="cat-card-icon"><?php echo cartonpak_icon( $meta['icon'], 34 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="cat-card-title"><?php echo esc_html( $term->name ); ?></span>
					<span class="cat-card-desc"><?php echo esc_html( $meta['desc'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
if ( $tiers ) :
	$ranges = PW_Tiers::ranges( $tiers );
	$best   = count( $ranges ) - 1;
	?>
	<section class="container" aria-labelledby="tiersTitle">
		<div class="tier-band">
			<div class="tier-band-text">
				<span class="tier-band-label">قیمت پلکانی</span>
				<h2 id="tiersTitle">هرچه بیشتر بخرید، ارزان‌تر</h2>
				<p>قیمت هر کارتن با بیشتر شدن تعداد سفارش، خودکار در سبد خرید کم می‌شود؛ بدون نیاز به تماس و پیگیری.</p>
			</div>
			<ul class="tier-cards" style="--tier-count: <?php echo esc_attr( count( $ranges ) ); ?>">
				<?php foreach ( $ranges as $i => $range ) : ?>
					<li class="tier-card<?php echo $i === $best ? ' is-best' : ''; ?>">
						<span class="tier-card-range"><?php echo esc_html( pw_range_label( $range ) ); ?></span>
						<span class="tier-card-value"><?php echo esc_html( pw_discount_label( $range['discount'] ) ); ?></span>
						<?php if ( $i === $best ) : ?>
							<span class="tier-card-badge">بیشترین صرفه</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<?php
if ( $has_wc ) :
	$best_sellers = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'no_found_rows'  => true,
			'meta_key'       => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => array(
				'meta_value_num' => 'DESC',
				'date'           => 'DESC',
			),
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => array( 'exclude-from-catalog' ),
					'operator' => 'NOT IN',
				),
			),
		)
	);

	if ( $best_sellers->have_posts() ) :
		$badges = array();
		foreach ( array_slice( $best_sellers->posts, 0, 2 ) as $post_item ) {
			if ( (int) get_post_meta( $post_item->ID, 'total_sales', true ) > 0 ) {
				$badges[ $post_item->ID ] = 'پرفروش';
			}
		}
		?>
		<section class="container section section-products" aria-labelledby="bestTitle">
			<div class="section-head">
				<h2 id="bestTitle">پرفروش‌ترین کارتن‌ها</h2>
				<a class="section-link" href="<?php echo esc_url( add_query_arg( 'orderby', 'popularity', $shop_url ) ); ?>">مشاهدهٔ همه<?php echo cartonpak_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
			<?php
			wc_setup_loop( array( 'columns' => 4, 'name' => 'best_sellers' ) );
			wc_set_loop_prop( 'cartonpak_badges', $badges );
			woocommerce_product_loop_start();
			while ( $best_sellers->have_posts() ) {
				$best_sellers->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			woocommerce_product_loop_end();
			wc_reset_loop();
			wp_reset_postdata();
			?>
		</section>
		<?php
	endif;
endif;
?>

<section class="container quote" id="quote" aria-labelledby="quoteTitle">
	<div class="quote-panel">
		<div class="quote-intro">
			<h2 id="quoteTitle">کارتن با ابعاد دلخواه</h2>
			<p>ابعاد و تعداد مورد نیازتان را وارد کنید تا پیش‌فاکتور برایتان ارسال شود.</p>
		</div>
		<div class="quote-form-wrap">
			<?php if ( function_exists( 'pw_render_quote_form' ) ) : ?>
				<?php pw_render_quote_form(); ?>
			<?php else : ?>
				<p class="quote-fallback">
					برای سفارش ابعاد دلخواه تماس بگیرید:
					<a href="<?php echo esc_url( cartonpak_tel( cartonpak_option( 'cartonpak_mobile' ) ) ); ?>" dir="ltr"><?php echo esc_html( cartonpak_option( 'cartonpak_mobile' ) ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
get_footer();
