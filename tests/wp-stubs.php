<?php
/**
 * شبیه‌سازی حداقلی وردپرس/ووکامرس برای آزمون افزونهٔ همگام‌سازی.
 * فقط رفتارهایی که کد افزونه واقعاً به آن‌ها تکیه می‌کند پیاده شده است.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

class WP_Error {
	protected $code;
	protected $message;
	protected $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data( $code = '' ) { return $this->data; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function __( $text, $domain = '' ) { return $text; }
function apply_filters( $tag, $value ) { return $value; }
function do_action( $tag ) {}
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) ); }
function sanitize_title( $v ) { return trim( preg_replace( '/[^\p{L}\p{N}]+/u', '-', (string) $v ), '-' ); }
function esc_url_raw( $v ) { return (string) $v; }
function current_time( $type ) { return '2026-09-11 12:00:00'; }
function get_current_user_id() { return 1; }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', (string) $p ); }
function wp_basename( $p ) { return basename( (string) $p ); }
function trailingslashit( $p ) { return rtrim( (string) $p, '/' ) . '/'; }
function wp_get_upload_dir() { return array( 'basedir' => '/var/www/uploads' ); }

$GLOBALS['pcs_options'] = array();
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['pcs_options'] ) ? $GLOBALS['pcs_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) { $GLOBALS['pcs_options'][ $key ] = $value; return true; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }

/* --------------------------- محصولات شبیه‌سازی‌شده --------------------------- */

class Fake_Product {
	public $id;
	public $data = array();
	public $terms = array( 'product_cat' => array(), 'product_tag' => array() );
	public $attributes = array();
	public $saved = 0;

	public function __construct( $id = 0, $data = array() ) {
		$this->id   = $id;
		$this->data = array_merge(
			array(
				'name' => '', 'sku' => '', 'description' => '', 'short_description' => '',
				'regular_price' => '', 'sale_price' => '', 'stock_quantity' => null,
				'stock_status' => 'instock', 'status' => 'publish', 'featured' => false,
				'image_id' => 0, 'gallery' => array(), 'weight' => '', 'length' => '',
				'width' => '', 'height' => '', 'menu_order' => 0, 'manage_stock' => false,
			),
			$data
		);
	}

	public function get_id() { return $this->id; }
	public function get_name() { return $this->data['name']; }
	public function get_sku() { return $this->data['sku']; }
	public function get_description() { return $this->data['description']; }
	public function get_short_description() { return $this->data['short_description']; }
	public function get_regular_price( $ctx = 'view' ) { return $this->data['regular_price']; }
	public function get_sale_price( $ctx = 'view' ) { return $this->data['sale_price']; }
	public function get_stock_quantity() { return $this->data['stock_quantity']; }
	public function get_stock_status() { return $this->data['stock_status']; }
	public function get_status() { return $this->data['status']; }
	public function get_featured() { return $this->data['featured']; }
	public function get_image_id() { return $this->data['image_id']; }
	public function get_gallery_image_ids() { return $this->data['gallery']; }
	public function get_weight( $ctx = 'view' ) { return $this->data['weight']; }
	public function get_length( $ctx = 'view' ) { return $this->data['length']; }
	public function get_width( $ctx = 'view' ) { return $this->data['width']; }
	public function get_height( $ctx = 'view' ) { return $this->data['height']; }
	public function get_menu_order() { return $this->data['menu_order']; }
	public function get_attributes() { return $this->attributes; }

	public function __call( $name, $args ) {
		if ( 0 === strpos( $name, 'set_' ) ) {
			$this->data[ substr( $name, 4 ) ] = $args[0];
			return null;
		}
		throw new Exception( "متد ناشناخته: {$name}" );
	}

	public function set_image_id( $v ) { $this->data['image_id'] = $v; }
	public function set_gallery_image_ids( $v ) { $this->data['gallery'] = $v; }
	public function set_category_ids( $v ) { $this->data['category_ids'] = $v; }
	public function set_tag_ids( $v ) { $this->data['tag_ids'] = $v; }
	public function set_attributes( $v ) { $this->attributes = $v; }

	public function save() {
		$this->saved++;
		if ( ! $this->id ) {
			$this->id = ++$GLOBALS['pcs_next_id'];
			$GLOBALS['pcs_products'][ $this->id ] = $this;
		}
		return $this->id;
	}
}

class WC_Product_Simple extends Fake_Product {}

$GLOBALS['pcs_products'] = array();
$GLOBALS['pcs_next_id']  = 100;

function pcs_test_add_product( $id, $data, $terms = array() ) {
	$product = new Fake_Product( $id, $data );
	foreach ( $terms as $taxonomy => $names ) {
		$product->terms[ $taxonomy ] = $names;
	}
	$GLOBALS['pcs_products'][ $id ] = $product;
	return $product;
}

function wc_get_product( $id ) {
	if ( $id instanceof Fake_Product ) { return $id; }
	return isset( $GLOBALS['pcs_products'][ (int) $id ] ) ? $GLOBALS['pcs_products'][ (int) $id ] : false;
}

function wc_get_product_id_by_sku( $sku ) {
	foreach ( $GLOBALS['pcs_products'] as $id => $product ) {
		if ( $product->get_sku() === $sku ) { return $id; }
	}
	return 0;
}

function get_posts( $args ) { return array_keys( $GLOBALS['pcs_products'] ); }

function wp_get_object_terms( $post_id, $taxonomy, $args = array() ) {
	$product = wc_get_product( $post_id );
	return $product && isset( $product->terms[ $taxonomy ] ) ? $product->terms[ $taxonomy ] : array();
}

function wc_get_attribute_taxonomies() { return $GLOBALS['pcs_attribute_taxonomies'] ?? array(); }
function wc_attribute_taxonomy_name( $name ) { return 'pa_' . $name; }
function wc_attribute_label( $taxonomy ) {
	foreach ( wc_get_attribute_taxonomies() as $tax ) {
		if ( 'pa_' . $tax->attribute_name === $taxonomy ) { return $tax->attribute_label; }
	}
	return $taxonomy;
}
$GLOBALS['pcs_attribute_taxonomies'] = array();

/* --------------------------- رسانه و دیتابیس --------------------------- */

class Fake_WPDB {
	public $postmeta = 'wp_postmeta';
	public $posts    = 'wp_posts';
	public function prepare( $query, ...$args ) { return $query; }
	public function esc_like( $text ) { return $text; }
	// کتابخانهٔ رسانهٔ شبیه‌سازی‌شده: نگاشت «نام فایل یا نشانی» به شناسهٔ پیوست.
	public function get_var( $query ) {
		return isset( $GLOBALS['pcs_lookup_result'] ) ? $GLOBALS['pcs_lookup_result'] : 0;
	}
}

$GLOBALS['wpdb'] = new Fake_WPDB();
$GLOBALS['pcs_attachments'] = array();
$GLOBALS['pcs_lookup_result'] = 0;

function get_post_type( $id ) {
	return isset( $GLOBALS['pcs_attachments'][ (int) $id ] ) ? 'attachment' : false;
}

function attachment_url_to_postid( $url ) {
	$map = array_flip( $GLOBALS['pcs_attachments'] );
	return isset( $map[ $url ] ) ? (int) $map[ $url ] : 0;
}

/**
 * ثبت یک پیوست در کتابخانهٔ شبیه‌سازی‌شده.
 *
 * @param int    $id  شناسه.
 * @param string $url نشانی.
 */
function pcs_test_add_attachment( $id, $url ) {
	$GLOBALS['pcs_attachments'][ (int) $id ] = $url;
}
