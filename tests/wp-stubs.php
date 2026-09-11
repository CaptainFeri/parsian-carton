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
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
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
	public function get_type() { return isset( $this->data['type'] ) ? $this->data['type'] : 'simple'; }
	public function is_type( $type ) { return $this->get_type() === $type; }
	public function get_children() { return isset( $this->data['children'] ) ? $this->data['children'] : array(); }

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

		// WC_Product_Data_Store_CPT::update_attributes ترم‌های صفت سراسری را به
		// محصول وصل می‌کند؛ برای اینکه آزمون واقعاً چیزی را بسنجد، اینجا هم همان
		// رفتار بازسازی می‌شود.
		foreach ( $this->attributes as $attribute ) {
			if ( $attribute instanceof WC_Product_Attribute && $attribute->is_taxonomy() ) {
				wp_set_object_terms( $this->id, $attribute->get_options(), $attribute->get_name() );
			}
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

function get_posts( $args ) {
	$types = isset( $args['post_type'] ) ? (array) $args['post_type'] : array( 'product' );
	$ids   = array();

	foreach ( $GLOBALS['pcs_products'] as $id => $product ) {
		// در وردپرس واقعی واریاسیون نوع post جداگانه‌ای دارد و در پرس‌وجوی
		// post_type=product نمی‌آید.
		$post_type = $product->is_type( 'variation' ) ? 'product_variation' : 'product';

		if ( in_array( $post_type, $types, true ) ) {
			$ids[] = $id;
		}
	}

	return $ids;
}

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

/* ------------------------ صفت‌ها و واریاسیون‌ها ------------------------ */

class WC_Product_Attribute {
	protected $id = 0, $name = '', $options = array(), $position = 0;
	protected $visible = true, $variation = false;
	public function set_id( $v ) { $this->id = (int) $v; }
	public function set_name( $v ) { $this->name = $v; }
	public function set_options( $v ) { $this->options = (array) $v; }
	public function set_position( $v ) { $this->position = (int) $v; }
	public function set_visible( $v ) { $this->visible = (bool) $v; }
	public function set_variation( $v ) { $this->variation = (bool) $v; }
	public function get_id() { return $this->id; }
	public function get_name() { return $this->name; }
	public function get_options() { return $this->options; }
	public function get_position() { return $this->position; }
	public function get_visible() { return $this->visible; }
	public function get_variation() { return $this->variation; }
	public function is_taxonomy() { return 0 === strpos( $this->name, 'pa_' ); }
	public function get_terms() {
		$terms = array();
		foreach ( $this->options as $id ) {
			$term = get_term( (int) $id, $this->name );
			if ( $term ) { $terms[] = $term; }
		}
		return $terms;
	}
}

/** ترم شبیه‌سازی‌شده. */
class Fake_Term {
	public $term_id, $name, $slug, $taxonomy;
	public function __construct( $id, $name, $slug, $taxonomy ) {
		$this->term_id = $id; $this->name = $name; $this->slug = $slug; $this->taxonomy = $taxonomy;
	}
}

$GLOBALS['pcs_terms'] = array();
$GLOBALS['pcs_next_term'] = 500;
$GLOBALS['pcs_taxonomies'] = array();
$GLOBALS['pcs_object_terms'] = array();

function wc_attribute_taxonomy_id_by_name( $slug ) {
	$slug = str_replace( 'pa_', '', $slug );
	return isset( $GLOBALS['pcs_taxonomies'][ $slug ] ) ? $GLOBALS['pcs_taxonomies'][ $slug ] : 0;
}

function wc_create_attribute( $args ) {
	$slug = $args['slug'];
	$GLOBALS['pcs_taxonomies'][ $slug ] = count( $GLOBALS['pcs_taxonomies'] ) + 1;
	$GLOBALS['pcs_attribute_taxonomies'][] = (object) array(
		'attribute_name'  => $slug,
		'attribute_label' => $args['name'],
	);
	return $GLOBALS['pcs_taxonomies'][ $slug ];
}

function taxonomy_exists( $taxonomy ) {
	return (bool) wc_attribute_taxonomy_id_by_name( $taxonomy );
}

function register_taxonomy( $taxonomy, $type, $args = array() ) { return true; }

function get_term_by( $field, $value, $taxonomy ) {
	foreach ( $GLOBALS['pcs_terms'] as $term ) {
		if ( $term->taxonomy !== $taxonomy ) { continue; }
		if ( 'name' === $field && $term->name === $value ) { return $term; }
		if ( 'slug' === $field && $term->slug === $value ) { return $term; }
	}
	return false;
}

function get_term( $id, $taxonomy = '' ) {
	return isset( $GLOBALS['pcs_terms'][ (int) $id ] ) ? $GLOBALS['pcs_terms'][ (int) $id ] : null;
}

function wp_insert_term( $name, $taxonomy, $args = array() ) {
	$existing = get_term_by( 'name', $name, $taxonomy );
	if ( $existing ) {
		return new WP_Error( 'term_exists', 'exists', $existing->term_id );
	}
	$id = ++$GLOBALS['pcs_next_term'];
	$GLOBALS['pcs_terms'][ $id ] = new Fake_Term( $id, $name, sanitize_title( $name ), $taxonomy );
	return array( 'term_id' => $id );
}

function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
	$GLOBALS['pcs_object_terms'][ $object_id ][ $taxonomy ] = (array) $terms;
	return (array) $terms;
}

function wc_delete_product_transients() {}
function wc_get_product_terms( $id, $taxonomy, $args = array() ) { return array(); }
