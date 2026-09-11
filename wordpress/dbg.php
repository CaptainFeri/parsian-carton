<?php
global $wpdb;
$file = $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value LIKE '%cat-01%' ORDER BY post_id ASC LIMIT 1" );
if ( $file ) {
	$id = (int) $file;
	echo "ID=$id\n";
	echo "file=" . get_attached_file( $id ) . "\n";
	$md = wp_get_attachment_metadata( $id );
	echo "meta width=" . var_export( isset($md['width']) ? $md['width'] : 'n/a', true ) . " height=" . var_export( isset($md['height']) ? $md['height'] : 'n/a', true ) . "\n";
	$svg = @simplexml_load_file( get_attached_file( $id ) );
	if ( $svg ) {
		echo "svg width=" . var_export( (string) $svg['width'], true ) . " height=" . var_export( (string) $svg['height'], true ) . " viewBox=" . var_export( (string) $svg['viewBox'], true ) . "\n";
	} else {
		echo "simplexml FAILED\n";
	}
	echo "filter exists: " . ( function_exists( 'cartonpak_svg_dimensions' ) ? 'yes' : 'no' ) . "\n";
	$img = wp_get_attachment_image( $id, 'woocommerce_thumbnail' );
	echo "img tag: " . substr( $img, 0, 300 ) . "\n";
} else {
	echo "no attachment found\n";
}