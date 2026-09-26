<?php
/**
 * wrapper ووکامرس — تمام صفحات ووکامرس (فروشگاه، محصول، سبد، پرداخت)
 *
 * @package cartonpak
 */

get_header();
?>

<div class="container page-wrap shop-wrap">
	<?php woocommerce_content(); ?>
</div>

<?php
get_footer();
