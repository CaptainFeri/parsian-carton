<?php
/**
 * صفحه ساده
 *
 * @package cartonpak
 */

get_header();
?>

<div class="container page-wrap">
	<article <?php post_class( 'page-content' ); ?>>
		<div class="page-heading">
			<h1 class="page-title"><?php the_title(); ?></h1>
		</div>
		<div class="entry-content">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>
		</div>
	</article>
</div>

<?php
get_footer();
