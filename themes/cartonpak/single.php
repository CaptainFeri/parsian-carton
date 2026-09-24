<?php
/**
 * تک نوشته — وبلاگ
 *
 * @package cartonpak
 */

get_header();
?>

<article <?php post_class( 'container page-wrap single-post' ); ?>>
	<div class="page-content">
		<div class="page-heading">
			<h1 class="page-title"><?php the_title(); ?></h1>
			<span class="blog-card-date">🗓 <?php echo esc_html( cartonpak_digits( get_the_date( 'd M Y' ) ) ); ?> — نویسنده: <?php the_author(); ?></span>
		</div>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="single-thumb"><?php the_post_thumbnail( 'large' ); ?></figure>
		<?php endif; ?>

		<div class="entry-content">
			<?php the_content(); ?>
		</div>

		<div class="single-nav">
			<?php previous_post_link( '%link', '❮ مقاله قبلی' ); ?>
			<?php next_post_link( '%link', 'مقاله بعدی ❯' ); ?>
		</div>
	</div>

	<aside class="sidebar">
		<?php if ( is_active_sidebar( 'blog-sidebar' ) ) : ?>
			<?php dynamic_sidebar( 'blog-sidebar' ); ?>
		<?php endif; ?>
	</aside>
</article>

<?php
get_footer();
