<?php
/**
 * wrapper ووکامرس — تمام صفحات ووکامرس (فروشگاه، محصول، سبد، پرداخت)
 *
 * @package cartonpak
 */

get_header();
?>

<div class="container page-wrap shop-wrap<?php echo is_product() ? ' product-wrap' : ''; ?>">
	<?php cartonpak_breadcrumb(); ?>
	<?php woocommerce_content(); ?>
</div>

<?php
get_footer();
