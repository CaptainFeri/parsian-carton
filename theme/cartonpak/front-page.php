<?php
/**
 * صفحه اصلی قالب کارتن‌پک
 *
 * @package cartonpak
 */

get_header();

$has_wc = class_exists( 'WooCommerce' );
$shop_url = $has_wc ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>

<?php /* ---------- اسلایدر ---------- */ ?>
<section class="hero">
	<div class="container hero-slider" id="heroSlider">
		<div class="hero-slide is-active">
			<div class="hero-content">
				<span class="hero-eyebrow">تولیدکننده مستقیم کارتن</span>
				<h1 class="hero-title">خرید کارتن و جعبه بسته‌بندی از کارخانه <em>سمنان</em></h1>
				<p class="hero-desc">انواع کارتن سه لایه و پنج لایه، جعبه‌های دایکاتی چاپی، سینی، لایی و جعبه‌های لمینتی — با کیفیت، سرعت و دقت از خط تولید پارسیان کارتن.</p>
				<div class="hero-cta">
					<a class="btn btn-primary" href="<?php echo esc_url( $shop_url ); ?>">مشاهده محصولات</a>
					<a class="btn btn-ghost" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">مشاوره رایگان</a>
				</div>
				<ul class="hero-features">
					<li>🚚 ارسال به سراسر کشور</li>
					<li>🎨 چاپ اختصاصی لوگوی برند شما</li>
					<li>📦 کارتن میوه، پسته، تخم‌مرغ و صادراتی</li>
				</ul>
			</div>
			<div class="hero-art" aria-hidden="true">
				<div class="box box-big"><span>کارتن</span></div>
				<div class="box box-medium"><span>پاکت</span></div>
				<div class="box box-small"><span>چسب</span></div>
			</div>
		</div>

		<div class="hero-slide">
			<div class="hero-content">
				<span class="hero-eyebrow">کارتن اسباب‌کشی و پستی</span>
				<h1 class="hero-title">با <em>کارتن‌های ۵ لایه</em> امن، اسباب‌کشی و ارسال کنید</h1>
				<p class="hero-desc">کارتن‌های مقاوم، جعبه‌های اداره پست، سینی و لایی — تحویل در سریع‌ترین زمان ممکن به سراسر کشور.</p>
				<div class="hero-cta">
					<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/product-category/moving-cartons/' ) ); ?>">لوازم اسباب‌کشی</a>
					<a class="btn btn-ghost" href="tel:<?php echo esc_attr( cartonpak_option( 'cartonpak_mobile' ) ); ?>">ثبت سفارش تلفنی</a>
				</div>
				<ul class="hero-features">
					<li>🛡️ مقاوم در برابر ضربه</li>
					<li>⏱️ تحویل به‌موقع سفارشات</li>
					<li>♻️ قابل بازیافت</li>
				</ul>
			</div>
			<div class="hero-art hero-art-alt" aria-hidden="true">
				<div class="moving-stack">
					<span class="ms-label">📦 ۵۰×۳۰×۳۵</span>
					<span class="ms-label">📦 ۶۰×۴۰×۵۰</span>
					<span class="ms-label">📦 ۷۰×۵۰×۴۰</span>
				</div>
			</div>
		</div>

		<div class="hero-slide">
			<div class="hero-content">
				<span class="hero-eyebrow">چاپ اختصاصی</span>
				<h1 class="hero-title">چاپ لوگوی برند شما روی <em>کارتن و پاکت</em></h1>
				<p class="hero-desc">چاپ اختصاصی با کیفیت بالا و تیراژ دلخواه؛ بسته‌بندی، لباس برند شماست.</p>
				<div class="hero-cta">
					<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">درخواست مشاوره</a>
					<a class="btn btn-ghost" href="<?php echo esc_url( $shop_url ); ?>">خرید آماده</a>
				</div>
				<ul class="hero-features">
					<li>🖨️ چاپ فلکسو و سیلک</li>
					<li>🎨 مشاوره رایگان طراحی</li>
					<li>⏳ تحویل تیراژ بالا در ۷ روز</li>
				</ul>
			</div>
			<div class="hero-art hero-art-alt2" aria-hidden="true">
				<div class="print-box">LOGO<br><small>برند شما</small></div>
			</div>
		</div>

		<button class="hero-arrow hero-arrow-next" id="heroNext" aria-label="اسلاید بعدی">❮</button>
		<button class="hero-arrow hero-arrow-prev" id="heroPrev" aria-label="اسلاید قبلی">❯</button>
		<div class="hero-dots" id="heroDots"></div>
	</div>
</section>

<?php /* ---------- مزایای فوری ---------- */ ?>
<section class="quick-benefits">
	<div class="container benefits-grid">
		<div class="benefit">
			<span class="benefit-icon">🚚</span>
			<div><strong>ارسال به سراسر کشور</strong><small>از طریق باربری، اتوبوس‌رانی و پست</small></div>
		</div>
		<div class="benefit">
			<span class="benefit-icon">📄</span>
			<div><strong>صدور فاکتور رسمی</strong><small>مناسب برای کسب‌وکارها</small></div>
		</div>
		<div class="benefit">
			<span class="benefit-icon">⚡</span>
			<div><strong>شعار ما: کیفیت، سرعت و دقت</strong><small>تحویل سریع و به‌موقع سفارشات</small></div>
		</div>
		<div class="benefit">
			<span class="benefit-icon">📦</span>
			<div><strong>تنوع کامل محصولات</strong><small>کارتن میوه، پسته، تخم‌مرغ و صادراتی</small></div>
		</div>
		<div class="benefit">
			<span class="benefit-icon">🛡️</span>
			<div><strong>ضمانت کیفیت کالا</strong><small>بهترین کیفیت و بهترین چاپ</small></div>
		</div>
		<div class="benefit">
			<span class="benefit-icon">🎧</span>
			<div><strong>پشتیبانی و پیگیری سفارش</strong><small>پاسخگویی همه‌روزه</small></div>
		</div>
	</div>
</section>

<?php /* ---------- دسته‌بندی محصولات ---------- */ ?>
<section class="section">
	<div class="container">
		<div class="section-head">
			<h2 class="section-title">دسته‌بندی محصولات</h2>
			<a class="section-link" href="<?php echo esc_url( $shop_url ); ?>">همه محصولات ←</a>
		</div>

		<div class="cat-grid">
			<?php
			/* دسته‌بندی‌ها از پیشخوان (product_cat) خوانده می‌شوند — فقط دسته‌هایی که محصول دارند نمایش داده می‌شوند */
			$cat_meta = array(
				'postal-cartons'       => array( 'کارتن پستی', 'سایز ۱ تا ۹ استاندارد پست', '📦' ),
				'moving-cartons'       => array( 'کارتن اسباب‌کشی', 'کارتن ۳ و ۵ لایه مقاوم', '🚚' ),
				'catering-restaurant'  => array( 'کترینگ و رستورانی', 'سینی و جعبه‌های کترینگ', '🍱' ),
				'fast-food'            => array( 'فست فودی', 'جعبه پیتزا، برگر و سوخاری', '🍕' ),
				'juice-restaurant'     => array( 'آبمیوه و رستورانی', 'جا لیوانی و سینی', '🥤' ),
				'bubble-mailers'       => array( 'پاکت حبابدار', 'پاکت پستی ضدضربه', '✉️' ),
				'packaging-supplies'   => array( 'ملزومات بسته‌بندی', 'چسب، سلفون، نایلون حبابدار', '🧰' ),
				'agri-export'          => array( 'کشاورزی و صادراتی', 'کارتن میوه، پسته، تخم‌مرغ و صادراتی', '🍇' ),
				'goni'                 => array( 'گونی', 'گونی پستی و صنعتی', '👜' ),
				'printing'             => array( 'چاپ اختصاصی', 'چاپ لوگو روی محصولات', '🖨️' ),
			);
			$cat_slugs = array();
			if ( $has_wc ) {
				$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
				if ( $terms && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$cat_slugs[] = $term->slug;
					}
				}
			}
			if ( empty( $cat_slugs ) ) {
				$cat_slugs = array_keys( $cat_meta );
			}
			foreach ( $cat_slugs as $slug ) :
				$term  = $has_wc ? get_term_by( 'slug', $slug, 'product_cat' ) : false;
				$url   = $term ? get_term_link( $term ) : $shop_url;
				$title = $term ? $term->name : ( $cat_meta[ $slug ][0] ?? $slug );
				$desc  = $cat_meta[ $slug ][1] ?? ( $term ? sprintf( '%d محصول', $term->count ) : '' );
				$icon  = $cat_meta[ $slug ][2] ?? '📦';
				?>
				<a class="cat-card" href="<?php echo esc_url( $url ); ?>">
					<span class="cat-card-icon"><?php echo esc_html( $icon ); ?></span>
					<span class="cat-card-title"><?php echo esc_html( $title ); ?></span>
					<span class="cat-card-desc"><?php echo esc_html( $desc ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php
/* ---------- بخش‌های محصولات ---------- */
function cartonpak_render_product_section( $slug, $title, $subtitle, $count = 8 ) {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term ) {
		return;
	}
	$query = new WP_Query( array(
		'post_type'      => 'product',
		'posts_per_page' => $count,
		'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug ) ),
	) );
	if ( ! $query->have_posts() ) {
		return;
	}
	?>
	<section class="section section-alt">
		<div class="container">
			<div class="section-head">
				<div>
					<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
					<?php if ( $subtitle ) : ?><p class="section-subtitle"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
				</div>
				<a class="section-link" href="<?php echo esc_url( get_term_link( $term ) ); ?>">مشاهده همه ←</a>
			</div>
			<div class="products-carousel">
				<button class="carousel-btn carousel-prev" aria-label="قبلی">❯</button>
				<div class="carousel-track">
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						$product = wc_get_product( get_the_ID() );
						if ( ! $product ) {
							continue;
						}
						?>
						<div class="product-card">
							<a class="product-card-media" href="<?php the_permalink(); ?>">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?>
								<?php else : ?>
									<span class="product-card-ph">📦</span>
								<?php endif; ?>
								<?php if ( $product->is_on_sale() ) : ?>
									<span class="product-card-sale">حراج</span>
								<?php endif; ?>
							</a>
							<div class="product-card-body">
								<a class="product-card-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								<div class="product-card-price">
									<?php if ( $product->is_on_sale() ) : ?>
										<del class="price-old"><?php echo esc_html( cartonpak_price( $product->get_regular_price() ) ); ?></del>
									<?php endif; ?>
									<span class="price-current"><?php echo esc_html( cartonpak_price( $product->get_price() ) ); ?></span>
								</div>
								<div class="product-card-actions">
									<a class="btn btn-small" href="<?php the_permalink(); ?>">جزئیات و خرید</a>
									<?php
									if ( $product->is_type( 'simple' ) && $product->is_purchasable() ) {
										echo '<a href="?add-to-cart=' . esc_attr( $product->get_id() ) . '" data-quantity="1" data-product_id="' . esc_attr( $product->get_id() ) . '" class="btn btn-icon ajax_add_to_cart add_to_cart_button" rel="nofollow" aria-label="افزودن به سبد">🛒</a>';
									}
									?>
								</div>
							</div>
						</div>
						<?php
					endwhile;
					wp_reset_postdata();
					?>
				</div>
				<button class="carousel-btn carousel-next" aria-label="بعدی">❮</button>
			</div>
		</div>
	</section>
	<?php
}

cartonpak_render_product_section( 'postal-cartons', 'کارتن‌های پستی', 'استاندارد و اقتصادی، مطابق سایزبندی اداره پست', 8 );
cartonpak_render_product_section( 'agri-export', 'کارتن‌های کشاورزی و صادراتی', 'کارتن میوه، پسته، تخم‌مرغ و کارتن‌های صادراتی', 6 );
cartonpak_render_product_section( 'moving-cartons', 'کارتن اسباب‌کشی', 'کارتن‌های سه لایه و پنج لایه برای جابجایی', 6 );
cartonpak_render_product_section( 'catering-restaurant', 'کترینگ و رستورانی', 'سینی و جعبه‌های یک‌بار مصرف کترینگ', 8 );
cartonpak_render_product_section( 'fast-food', 'فست فودی صادراتی', 'جعبه پیتزا، همبرگر، ساندویچ و سوخاری', 8 );
cartonpak_render_product_section( 'juice-restaurant', 'آبمیوه و رستورانی', 'جا لیوانی، سینی و جعبه‌های رستورانی', 8 );
?>

<?php /* ---------- چرا ما ---------- */ ?>
<section class="section">
	<div class="container why-grid">
		<div class="why-visual" aria-hidden="true">
			<div class="why-box-stack">
				<div class="wb wb1">📦</div>
				<div class="wb wb2">✉️</div>
				<div class="wb wb3">🧷</div>
			</div>
		</div>
		<div class="why-content">
			<span class="why-eyebrow">چرا پارسیان کارتن؟</span>
			<h2 class="section-title">تولیدکننده انواع کارتن و جعبه بسته‌بندی، بدون واسطه و با قیمت کارخانه</h2>
			<ul class="why-list">
				<li><strong>تولیدکننده مستقیم:</strong> انواع کارتن سه لایه و پنج لایه، جعبه‌های دایکاتی چاپی، سینی، لایی و جعبه‌های لمینتی.</li>
				<li><strong>صنایع پوشش‌داده‌شده:</strong> خودروسازی، دارویی، لوازم خانگی، مواد غذایی، کشاورزی، دامداری و کارتن‌های صادراتی.</li>
				<li><strong>کیفیت، سرعت و دقت:</strong> بهره‌گیری از به‌روزترین دستگاه‌های تولید کارتن و جعبه برای بهترین چاپ و کیفیت.</li>
				<li><strong>ارسال به سراسر کشور:</strong> سفارش شما به تمام نقاط ایران ارسال می‌شود.</li>
			</ul>
			<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>">درباره ما بیشتر بدانید</a>
		</div>
	</div>
</section>

<?php /* ---------- نظرات مشتریان ---------- */ ?>
<section class="section section-alt">
	<div class="container">
		<div class="section-head">
			<h2 class="section-title">نظرات مشتریان</h2>
			<div class="rating-summary">★ ۴٫۹ از ۵ — بیش از ۲,۰۰۰ نظر ثبت‌شده</div>
		</div>
		<div class="testimonials-grid">
			<article class="testimonial">
				<div class="testimonial-stars">★★★★★</div>
				<p class="testimonial-text">سرعت تحویل فوق‌العاده بود؛ از زمان سفارش تا تحویل کمتر از یک ساعت! کارتن‌ها تمیز و محکم رسیدن. ثبت سفارش هم خیلی راحت بود، فقط انتخاب کردم و آدرس دادم.</p>
				<footer class="testimonial-author">
					<span class="t-avatar">م</span>
					<div><strong>مسعود</strong><small>خریدار واقعی — تهران</small></div>
					<span class="t-badge">✓ خرید تایید شده</span>
				</footer>
			</article>
			<article class="testimonial">
				<div class="testimonial-stars">★★★★★</div>
				<p class="testimonial-text">فروشگاه اینترنتی دارم و ماهانه چندصد کارتن پستی سفارش می‌دهم. کیفیت کارتن‌ها عالی است و چون مستقیم از کارخانه خرید می‌کنم، هزینه بسته‌بندی‌ام به‌شدت کم شده.</p>
				<footer class="testimonial-author">
					<span class="t-avatar">س</span>
					<div><strong>سارا</strong><small>خریدار عمده — کرج</small></div>
					<span class="t-badge">✓ خرید تایید شده</span>
				</footer>
			</article>
			<article class="testimonial">
				<div class="testimonial-stars">★★★★★</div>
				<p class="testimonial-text">برای اسباب‌کشی از کارتن‌های ۵ لایه کارتن‌پک استفاده کردم؛ هیچ‌کدام پاره نشد و وسایلم سالم موند. پشتیبانی هم واقعاً پاسخگو بود. حتماً به دوستانم پیشنهاد می‌دم.</p>
				<footer class="testimonial-author">
					<span class="t-avatar">ر</span>
					<div><strong>رضا</strong><small>خریدار واقعی — تهران</small></div>
					<span class="t-badge">✓ خرید تایید شده</span>
				</footer>
			</article>
		</div>
	</div>
</section>

<?php /* ---------- وبلاگ ---------- */ ?>
<section class="section">
	<div class="container">
		<div class="section-head">
			<h2 class="section-title">از وبلاگ پارسیان کارتن بخوانید</h2>
			<a class="section-link" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">همه مقالات ←</a>
		</div>
		<div class="blog-grid">
			<?php
			$blog_query = new WP_Query( array( 'posts_per_page' => 3, 'post_type' => 'post' ) );
			if ( $blog_query->have_posts() ) :
				while ( $blog_query->have_posts() ) :
					$blog_query->the_post();
					?>
					<article class="blog-card">
						<a class="blog-card-media" href="<?php the_permalink(); ?>">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
							<?php else : ?>
								<span class="blog-card-ph">📰</span>
							<?php endif; ?>
						</a>
						<div class="blog-card-body">
							<span class="blog-card-date">🗓 <?php echo esc_html( cartonpak_digits( get_the_date( 'd M Y' ) ) ); ?></span>
							<h3 class="blog-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<p class="blog-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
							<a class="blog-card-more" href="<?php the_permalink(); ?>">ادامه مطلب ←</a>
						</div>
					</article>
					<?php
				endwhile;
				wp_reset_postdata();
			else :
				echo '<p>هنوز مقاله‌ای منتشر نشده است. از پیشخوان وردپرس، نوشته‌های آموزشی خود را اضافه کنید.</p>';
			endif;
			?>
		</div>
	</div>
</section>

<?php /* ---------- لوگوی همکاران ---------- */ ?>
<section class="section partners">
	<div class="container">
		<div class="section-head">
			<h2 class="section-title">افتخار همکاری با صنایع مختلف</h2>
		</div>
		<div class="partners-strip">
			<span>خودروسازی</span><span>صنایع دارویی</span><span>لوازم خانگی</span><span>مواد غذایی</span><span>کشاورزی و دامداری</span><span>صادرات به کشورهای همجوار</span>
		</div>
	</div>
</section>

<?php /* ---------- CTA ---------- */ ?>
<section class="cta-banner">
	<div class="container cta-inner">
		<h2>همین حالا سفارش بده و با خیال راحت بسته‌بندی کن!</h2>
		<p>ثبت سفارش بدون نیاز به ثبت‌نام؛ پرداخت در محل در زمان تحویل.</p>
		<div class="cta-actions">
			<a class="btn btn-light" href="<?php echo esc_url( $shop_url ); ?>">سفارش آنلاین (پرداخت در محل)</a>
			<a class="btn btn-outline-light" href="tel:<?php echo esc_attr( cartonpak_option( 'cartonpak_mobile' ) ); ?>">☎ ثبت سفارش تلفنی</a>
		</div>
	</div>
</section>

<?php
get_footer();
