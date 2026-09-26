<?php
/**
 * بایگانی نوشته‌ها — وبلاگ
 *
 * @package cartonpak
 */

get_header();
?>

<div class="container page-wrap">
	<div class="page-content blog-page">
		<div class="page-heading">
			<h1 class="page-title">
				<?php
				if ( is_home() && ! is_front_page() ) {
					single_post_title();
				} else {
					the_archive_title();
				}
				?>
			</h1>
			<p class="page-subtitle">مقالات آموزشی بسته‌بندی، چاپ و کارتن‌سازی</p>
		</div>

		<div class="blog-grid">
			<?php
			if ( have_posts() ) :
				while ( have_posts() ) :
					the_post();
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
				the_posts_pagination( array(
					'prev_text' => '❮ قبلی',
					'next_text' => 'بعدی ❯',
				) );
			else :
				echo '<p>نوشته‌ای یافت نشد.</p>';
			endif;
			?>
		</div>
	</div>

	<aside class="sidebar">
		<?php if ( is_active_sidebar( 'blog-sidebar' ) ) : ?>
			<?php dynamic_sidebar( 'blog-sidebar' ); ?>
		<?php else : ?>
			<section class="widget">
				<h3 class="widget-title">دسته‌های وبلاگ</h3>
				<ul class="widget-list">
					<?php wp_list_categories( array( 'title_li' => '', 'show_count' => true ) ); ?>
				</ul>
			</section>
		<?php endif; ?>
	</aside>
</div>

<?php
get_footer();
