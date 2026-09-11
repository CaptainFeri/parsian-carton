<?php
/**
 * اجازه آپلود فایل‌های SVG — برای تصاویر محصولات
 *
 * @package cartonpak
 */

add_filter(
	'upload_mimes',
	function ( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		return $mimes;
	}
);

add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename, $mimes ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'svg', 'svgz' ), true ) ) {
			$data['ext']             = $ext;
			$data['type']            = 'image/svg+xml';
			$data['proper_filename'] = $filename;
		}
		return $data;
	},
	10,
	4
);