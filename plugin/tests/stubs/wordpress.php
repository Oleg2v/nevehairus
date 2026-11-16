<?php
/**
 * A fake WordPress API for the stub harness (SPEC §11.2): no database, no HTTP, no WordPress.
 * Everything a widget or the core may call, with WordPress's behaviour where it matters for the output
 * (escaping, kses, add_query_arg, nonces, hooks), in memory. Every function is guarded by
 * function_exists, so a fixture may add a missing WordPress core function the same way.
 *
 * State lives in $GLOBALS['nh_test'] and is reset by nh_test_reset() (bootstrap.php).
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'WEEK_IN_SECONDS', 604800 );
}

/** Thrown where WordPress would end the request (wp_send_json, NH_Forms::halt, wp_die). */
class NH_Test_Halt extends RuntimeException {
}

/**
 * The fake site: state of one test run.
 */
function nh_test_state_defaults(): array {
	return [
		'options'      => [],
		'transients'   => [],
		'meta'         => [],    // post id => key => value
		'posts'        => [],    // post id => [ 'type', 'status', 'url', 'parent', 'mime' ]
		'hooks'        => [],    // hook => priority => [ callbacks ]
		'actions_done' => [],
		'current'      => [],    // stack of running hooks
		'caps'         => [],    // capability => bool
		'queried'      => 10,
		'is_admin'     => false,
		'is_singular'  => true,
		'styles'       => [],
		'scripts'      => [],
		'enqueued'     => [ 'style' => [], 'script' => [] ],
		'inline'       => [],
		'mails'        => [],
		'mail_result'  => true,
		'redirects'    => [],
		'json'         => [],
		'deleted'      => [],
		'doing_wrong'  => [],
		'headers'      => [],
		'settings'     => [],
		'notices'      => [],
		'time_offset'  => 0,
	];
}

if ( ! isset( $GLOBALS['nh_test'] ) ) {
	$GLOBALS['nh_test'] = nh_test_state_defaults();
}

/* ---------- Escaping and sanitising ---------------------------------------------------------------- */

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) {
		return addslashes( (string) $text );
	}
}
if ( ! function_exists( 'wp_allowed_protocols' ) ) {
	function wp_allowed_protocols() {
		return [ 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' ];
	}
}
if ( ! function_exists( 'nh_test_clean_url' ) ) {
	/** esc_url() without the display escaping: WordPress's rules for schemes and characters. */
	function nh_test_clean_url( string $url, ?array $protocols = null ): string {
		$url = trim( str_replace( ' ', '%20', $url ) );
		if ( '' === $url ) {
			return '';
		}
		$url = (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', $url );
		if ( '' === $url ) {
			return '';
		}
		// WordPress prefixes http:// here; the stub keeps relative paths ("../…", "./…") because the fake
		// media library points from plugin/tests/out/ to the prototype's photos (SPEC §11.2).
		if ( false === strpos( $url, ':' ) && ! in_array( $url[0], [ '/', '#', '?', '.' ], true ) && ! preg_match( '/^[a-z0-9-]+?\.php/i', $url ) ) {
			$url = 'http://' . $url;
		}
		if ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $url, $m ) && ! in_array( strtolower( $m[1] ), $protocols ?? wp_allowed_protocols(), true ) ) {
			return '';
		}
		return $url;
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url, $protocols = null, $_context = 'display' ) {
		$url = nh_test_clean_url( (string) $url, $protocols );
		if ( 'display' === $_context ) {
			$url = str_replace( [ '&amp;', '&' ], '&#038;', $url );
			$url = str_replace( "'", '&#039;', $url );
		}
		return $url;
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return nh_test_clean_url( (string) $url, $protocols );
	}
}
if ( ! function_exists( 'sanitize_url' ) ) {
	function sanitize_url( $url, $protocols = null ) {
		return esc_url_raw( $url, $protocols );
	}
}
if ( ! function_exists( 'wp_kses_allowed_html' ) ) {
	function wp_kses_allowed_html( $context = '' ) {
		$common = [
			'class' => true,
			'id'    => true,
			'lang'  => true,
			'dir'   => true,
			'title' => true,
			'style' => true,
		];
		$tags   = [ 'a', 'abbr', 'b', 'blockquote', 'br', 'cite', 'code', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 'q', 's', 'small', 'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'u', 'ul', 'figure', 'figcaption', 'del', 'ins', 'mark' ];
		$out    = [];
		foreach ( $tags as $t ) {
			$out[ $t ] = $common;
		}
		$out['a'] += [
			'href'   => true,
			'rel'    => true,
			'target' => true,
		];
		$out['img'] += [
			'src'    => true,
			'alt'    => true,
			'width'  => true,
			'height' => true,
		];
		return $out;
	}
}
if ( ! function_exists( 'wp_kses' ) ) {
	/**
	 * kses over DOMDocument: allowed tags keep their allowed attributes (href with an allowed protocol),
	 * other tags are removed and their text kept (as WordPress does); comments are dropped.
	 */
	function wp_kses( $content, $allowed_html, $allowed_protocols = [] ) {
		$content = (string) $content;
		if ( '' === $content ) {
			return '';
		}
		if ( is_string( $allowed_html ) ) {
			$allowed_html = wp_kses_allowed_html( $allowed_html );
		}
		$allowed_html = array_change_key_case( (array) $allowed_html, CASE_LOWER );
		$protocols    = $allowed_protocols ? $allowed_protocols : wp_allowed_protocols();
		if ( false === strpos( $content, '<' ) && false === strpos( $content, '&' ) && false === strpos( $content, '>' ) ) {
			return $content;
		}
		$doc  = new DOMDocument( '1.0', 'UTF-8' );
		$prev = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8"><div id="nh-kses-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$root = null;
		foreach ( $doc->getElementsByTagName( 'div' ) as $div ) {
			if ( 'nh-kses-root' === $div->getAttribute( 'id' ) ) {
				$root = $div;
				break;
			}
		}
		if ( null === $root ) {
			return esc_html( wp_strip_all_tags( $content ) );
		}
		return nh_test_kses_children( $root, $allowed_html, $protocols );
	}
}
if ( ! function_exists( 'nh_test_kses_children' ) ) {
	function nh_test_kses_children( DOMNode $node, array $allowed, array $protocols ): string {
		$void = [ 'br', 'hr', 'img', 'input', 'wbr', 'source' ];
		$out  = '';
		foreach ( $node->childNodes as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType || XML_CDATA_SECTION_NODE === $child->nodeType ) {
				$out .= htmlspecialchars( $child->nodeValue, ENT_NOQUOTES, 'UTF-8' );
				continue;
			}
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			$tag   = strtolower( $child->nodeName );
			$inner = nh_test_kses_children( $child, $allowed, $protocols );
			if ( ! isset( $allowed[ $tag ] ) ) {
				$out .= $inner;
				continue;
			}
			$attrs = '';
			$rules = is_array( $allowed[ $tag ] ) ? array_change_key_case( $allowed[ $tag ], CASE_LOWER ) : [];
			foreach ( $child->attributes as $attr ) {
				$name = strtolower( $attr->nodeName );
				if ( empty( $rules[ $name ] ) && ! ( 0 === strpos( $name, 'data-' ) && ! empty( $rules['data-*'] ) ) ) {
					continue;
				}
				$value = $attr->nodeValue;
				if ( in_array( $name, [ 'href', 'src' ], true ) ) {
					$value = nh_test_clean_url( $value, $protocols );
					if ( '' === $value ) {
						continue;
					}
				}
				$attrs .= ' ' . $name . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"';
			}
			$out .= in_array( $tag, $void, true ) ? '<' . $tag . $attrs . '>' : '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
		}
		return $out;
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return wp_kses( $data, 'post' );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}
if ( ! function_exists( 'wp_specialchars_decode' ) ) {
	function wp_specialchars_decode( $text, $quote_style = ENT_NOQUOTES ) {
		return htmlspecialchars_decode( (string) $text, ENT_QUOTES );
	}
}
if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
	function wp_check_invalid_utf8( $text ) {
		return mb_check_encoding( (string) $text, 'UTF-8' ) ? (string) $text : '';
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = wp_check_invalid_utf8( (string) $str );
		$str = wp_strip_all_tags( $str );
		$str = (string) preg_replace( '/[\r\n\t ]+/', ' ', $str );
		$str = (string) preg_replace( '/%[a-f0-9]{2}/i', '', $str );
		return trim( $str );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		$str   = wp_check_invalid_utf8( (string) $str );
		$str   = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $str );
		$str   = strip_tags( $str );
		$lines = array_map( 'rtrim', explode( "\n", str_replace( "\r\n", "\n", $str ) ) );
		return trim( implode( "\n", $lines ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $classname, $fallback = '' ) {
		$sanitized = (string) preg_replace( '|%[a-fA-F0-9][a-fA-F0-9]|', '', (string) $classname );
		$sanitized = (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $sanitized );
		return '' === $sanitized && $fallback ? sanitize_html_class( $fallback ) : $sanitized;
	}
}
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		$color = (string) $color;
		if ( '' === $color ) {
			return '';
		}
		return preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ? $color : null;
	}
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		// As WordPress: special characters removed, whitespace → dashes, letters of any script kept.
		$filename = str_replace( [ '?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', chr( 0 ) ], '', (string) $filename );
		$filename = (string) preg_replace( '/[\r\n\t -]+/', '-', $filename );
		return trim( $filename, '.-_' );
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = trim( (string) $email );
		return is_email( $email ) ? $email : (string) preg_replace( '/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@-]/', '', $email );
	}
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		$email = (string) $email;
		if ( strlen( $email ) < 6 || false === strpos( $email, '@', 1 ) ) {
			return false;
		}
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}
}
if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( $value ) {
		return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}
if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return (string) preg_replace( '|(?<=.)/+|', '/', str_replace( '\\', '/', (string) $path ) );
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}
if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $display = true ) {
		$out = (string) $checked === (string) $current ? " checked='checked'" : '';
		if ( $display ) {
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $out;
	}
}
if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		$out = (string) $selected === (string) $current ? " selected='selected'" : '';
		if ( $display ) {
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $out;
	}
}

/* ---------- i18n --------------------------------------------------------------------------------------- */

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return (string) $text;
	}
	function _e( $text, $domain = 'default' ) {
		echo (string) $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	function _x( $text, $context, $domain = 'default' ) {
		return (string) $text;
	}
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? (string) $single : (string) $plural;
	}
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
	function esc_attr__( $text, $domain = 'default' ) {
		return esc_attr( $text );
	}
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	function esc_html_x( $text, $context, $domain = 'default' ) {
		return esc_html( $text );
	}
	function esc_attr_x( $text, $context, $domain = 'default' ) {
		return esc_attr( $text );
	}
	function load_plugin_textdomain( $domain, $deprecated = false, $path = false ) {
		return true;
	}
}

/* ---------- Hooks ------------------------------------------------------------------------------------- */

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['nh_test']['hooks'][ $hook ][ (int) $priority ][] = [ $callback, (int) $accepted_args ];
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
	function nh_test_callbacks( string $hook ): array {
		$by_priority = $GLOBALS['nh_test']['hooks'][ $hook ] ?? [];
		ksort( $by_priority );
		$out = [];
		foreach ( $by_priority as $list ) {
			foreach ( $list as $entry ) {
				$out[] = $entry;
			}
		}
		return $out;
	}
	function apply_filters( $hook, $value, ...$args ) {
		$GLOBALS['nh_test']['current'][] = $hook;
		try {
			foreach ( nh_test_callbacks( $hook ) as [ $callback, $accepted ] ) {
				$value = call_user_func_array( $callback, array_slice( array_merge( [ $value ], $args ), 0, max( 1, $accepted ) ) );
			}
		} finally {
			array_pop( $GLOBALS['nh_test']['current'] );
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['nh_test']['actions_done'][ $hook ] = ( $GLOBALS['nh_test']['actions_done'][ $hook ] ?? 0 ) + 1;
		$GLOBALS['nh_test']['current'][]              = $hook;
		try {
			foreach ( nh_test_callbacks( $hook ) as [ $callback, $accepted ] ) {
				call_user_func_array( $callback, array_slice( $args, 0, max( $accepted, 0 ) ) );
			}
		} finally {
			array_pop( $GLOBALS['nh_test']['current'] );
		}
	}
	function did_action( $hook ) {
		return (int) ( $GLOBALS['nh_test']['actions_done'][ $hook ] ?? 0 );
	}
	function has_filter( $hook, $callback = false ) {
		$callbacks = nh_test_callbacks( $hook );
		if ( false === $callback ) {
			return (bool) $callbacks;
		}
		foreach ( $GLOBALS['nh_test']['hooks'][ $hook ] ?? [] as $priority => $list ) {
			foreach ( $list as $entry ) {
				if ( $entry[0] === $callback ) {
					return $priority;
				}
			}
		}
		return false;
	}
	function has_action( $hook, $callback = false ) {
		return has_filter( $hook, $callback );
	}
	function remove_filter( $hook, $callback, $priority = 10 ) {
		foreach ( $GLOBALS['nh_test']['hooks'][ $hook ][ (int) $priority ] ?? [] as $i => $entry ) {
			if ( $entry[0] === $callback ) {
				unset( $GLOBALS['nh_test']['hooks'][ $hook ][ (int) $priority ][ $i ] );
				return true;
			}
		}
		return false;
	}
	function remove_action( $hook, $callback, $priority = 10 ) {
		return remove_filter( $hook, $callback, $priority );
	}
	function current_filter() {
		$stack = $GLOBALS['nh_test']['current'];
		return $stack ? (string) end( $stack ) : '';
	}
	function current_action() {
		return current_filter();
	}
	function doing_action( $hook = null ) {
		return null === $hook ? (bool) $GLOBALS['nh_test']['current'] : in_array( $hook, $GLOBALS['nh_test']['current'], true );
	}
	function __return_true() {
		return true;
	}
	function __return_false() {
		return false;
	}
	function __return_empty_string() {
		return '';
	}
	function __return_empty_array() {
		return [];
	}
}

/* ---------- Options, transients, settings ------------------------------------------------------------- */

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default_value = false ) {
		if ( array_key_exists( $name, $GLOBALS['nh_test']['options'] ) ) {
			return $GLOBALS['nh_test']['options'][ $name ];
		}
		$defaults = [
			'blogname'       => 'מעון נווה האירוס',
			'home'           => 'https://nh.test',
			'siteurl'        => 'https://nh.test',
			'admin_email'    => 'admin@example.test',
			'show_on_front'  => 'page',
			'page_on_front'  => 10,
			'blog_charset'   => 'UTF-8',
			'date_format'    => 'j.n.Y',
			'timezone_string' => 'Asia/Jerusalem',
		];
		if ( array_key_exists( $name, $defaults ) ) {
			return $defaults[ $name ];
		}
		// as WordPress (register_setting()'s filter_default_option()): the registered default only when the
		// caller passed none of its own
		if ( func_num_args() < 2 && isset( $GLOBALS['nh_test']['settings'][ $name ]['default'] ) ) {
			return $GLOBALS['nh_test']['settings'][ $name ]['default'];
		}
		return $default_value;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['nh_test']['options'][ $name ] = $value;
		return true;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) {
		if ( array_key_exists( $name, $GLOBALS['nh_test']['options'] ) ) {
			return false;
		}
		$GLOBALS['nh_test']['options'][ $name ] = $value;
		return true;
	}
	function delete_option( $name ) {
		unset( $GLOBALS['nh_test']['options'][ $name ] );
		return true;
	}
	function get_transient( $key ) {
		$t = $GLOBALS['nh_test']['transients'][ $key ] ?? null;
		if ( null === $t || ( $t['expires'] > 0 && $t['expires'] < time() ) ) {
			return false;
		}
		return $t['value'];
	}
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['nh_test']['transients'][ $key ] = [
			'value'   => $value,
			'expires' => $expiration ? time() + (int) $expiration : 0,
			'ttl'     => (int) $expiration,
		];
		return true;
	}
	function delete_transient( $key ) {
		unset( $GLOBALS['nh_test']['transients'][ $key ] );
		return true;
	}
	function register_setting( $group, $name, $args = [] ) {
		$GLOBALS['nh_test']['settings'][ $name ] = (array) $args + [ 'group' => $group ];
	}
	function add_settings_error( $setting, $code, $message, $type = 'error' ) {
		$GLOBALS['nh_test']['notices'][] = [ $setting, $code, $message, $type ];
	}
	function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {
	}
	function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
		$GLOBALS['nh_test']['pages'][ $menu_slug ] = $callback;
		return 'settings_page_' . $menu_slug;
	}
	function add_settings_section( $id, $title, $callback, $page, $args = [] ) {
		$GLOBALS['nh_test']['sections'][ $page ][ $id ] = [ $title, [] ];
	}
	function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = [] ) {
		$GLOBALS['nh_test']['sections'][ $page ][ $section ][1][ $id ] = [ $title, $callback, $args ];
	}
	function settings_fields( $group ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	function do_settings_sections( $page ) {
		foreach ( $GLOBALS['nh_test']['sections'][ $page ] ?? [] as $section ) {
			echo '<h2>' . esc_html( $section[0] ) . '</h2><table class="form-table">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			foreach ( $section[1] as $field ) {
				echo '<tr><th>' . esc_html( $field[0] ) . '</th><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				call_user_func( $field[1], $field[2] );
				echo '</td></tr>';
			}
			echo '</table>';
		}
	}
	function submit_button( $text = null ) {
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html( $text ?? 'Save' ) . '</button></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$out = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
		if ( $display ) {
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $out;
	}
	function wp_dropdown_pages( $args = [] ) {
		$out = '<select name="' . esc_attr( $args['name'] ?? 'page_id' ) . '" id="' . esc_attr( $args['id'] ?? '' ) . '"><option value="0">' . esc_html( $args['show_option_none'] ?? '' ) . '</option>';
		foreach ( $GLOBALS['nh_test']['posts'] as $id => $p ) {
			if ( 'page' === $p['type'] ) {
				$out .= '<option value="' . (int) $id . '"' . selected( (int) ( $args['selected'] ?? 0 ), (int) $id, false ) . '>' . esc_html( $p['title'] ?? (string) $id ) . '</option>';
			}
		}
		$out .= '</select>';
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $out;
	}
	function get_current_screen() {
		return isset( $GLOBALS['nh_test']['screen'] ) ? (object) [ 'id' => $GLOBALS['nh_test']['screen'] ] : null;
	}
}

/* ---------- Site, URLs, posts --------------------------------------------------------------------------- */

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '', $scheme = null ) {
		$path = (string) $path;
		return 'https://nh.test' . ( '' !== $path ? '/' . ltrim( $path, '/' ) : '' );
	}
	function site_url( $path = '', $scheme = null ) {
		return home_url( $path );
	}
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'https://nh.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
	function get_bloginfo( $show = '', $filter = 'raw' ) {
		switch ( $show ) {
			case 'name':
				return (string) get_option( 'blogname' );
			case 'charset':
				return 'UTF-8';
			case 'language':
				return 'he-IL';
			case 'version':
				return '7.1.4';
		}
		return '';
	}
	function is_admin() {
		return (bool) $GLOBALS['nh_test']['is_admin'];
	}
	function wp_doing_ajax() {
		return false;
	}
	function is_singular( $post_types = '' ) {
		return (bool) $GLOBALS['nh_test']['is_singular'];
	}
	function get_queried_object_id() {
		return (int) $GLOBALS['nh_test']['queried'];
	}
	function get_the_ID() {
		return (int) $GLOBALS['nh_test']['queried'];
	}
	function get_post( $post = null ) {
		$id = is_object( $post ) ? (int) $post->ID : (int) ( $post ?? get_the_ID() );
		$p  = $GLOBALS['nh_test']['posts'][ $id ] ?? null;
		if ( null === $p ) {
			return null;
		}
		return (object) [
			'ID'             => $id,
			'post_type'      => $p['type'],
			'post_status'    => $p['status'],
			'post_parent'    => (int) ( $p['parent'] ?? 0 ),
			'post_mime_type' => (string) ( $p['mime'] ?? '' ),
			'post_title'     => (string) ( $p['title'] ?? '' ),
		];
	}
	function get_post_status( $post = null ) {
		$p = get_post( $post );
		return $p ? $p->post_status : false;
	}
	function get_post_type( $post = null ) {
		$p = get_post( $post );
		return $p ? $p->post_type : false;
	}
	function get_post_mime_type( $post = null ) {
		$p = get_post( $post );
		return $p ? $p->post_mime_type : false;
	}
	function get_permalink( $post = 0, $leavename = false ) {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		$id = $id > 0 ? $id : get_the_ID();
		return $GLOBALS['nh_test']['posts'][ $id ]['url'] ?? false;
	}
	function get_post_ancestors( $post ) {
		$id  = is_object( $post ) ? (int) $post->ID : (int) $post;
		$out = [];
		while ( ! empty( $GLOBALS['nh_test']['posts'][ $id ]['parent'] ) && count( $out ) < 20 ) {
			$id    = (int) $GLOBALS['nh_test']['posts'][ $id ]['parent'];
			$out[] = $id;
		}
		return $out;
	}
	function url_to_postid( $url ) {
		$url = strtok( (string) $url, '?#' );
		if ( 0 === strpos( (string) $url, '/' ) ) {
			$url = home_url( (string) $url );
		}
		foreach ( $GLOBALS['nh_test']['posts'] as $id => $p ) {
			if ( isset( $p['url'] ) && 'attachment' !== $p['type'] && untrailingslashit( $p['url'] ) === untrailingslashit( (string) $url ) ) {
				return (int) $id;
			}
		}
		return 0;
	}
	function get_privacy_policy_url() {
		return (string) ( $GLOBALS['nh_test']['privacy_url'] ?? '' );
	}
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$meta = $GLOBALS['nh_test']['meta'][ (int) $post_id ] ?? [];
		if ( '' === $key ) {
			return $meta;
		}
		if ( ! array_key_exists( $key, $meta ) ) {
			return $single ? '' : [];
		}
		return $single ? $meta[ $key ] : [ $meta[ $key ] ];
	}
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['nh_test']['meta'][ (int) $post_id ][ $key ] = $value;
		return true;
	}
	function get_template() {
		return 'hello-elementor';
	}
	function get_stylesheet() {
		return 'hello-theme-child';
	}
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( (string) $file ) );
	}
	function plugin_dir_url( $file ) {
		// Relative to plugin/tests/out/, so the rendered pages load the plugin's real CSS, JS and fonts.
		return '../../' . basename( dirname( (string) $file ) ) . '/';
	}
	function plugin_basename( $file ) {
		return basename( dirname( (string) $file ) ) . '/' . basename( (string) $file );
	}
	function register_activation_hook( $file, $callback ) {
		add_action( 'activate_' . plugin_basename( $file ), $callback );
	}
	function register_deactivation_hook( $file, $callback ) {
		add_action( 'deactivate_' . plugin_basename( $file ), $callback );
	}
	/** As WordPress: the `_wp_page_template` meta, '' for the default template, false for no post. */
	function get_page_template_slug( $post = null ) {
		$id = is_object( $post ) ? (int) $post->ID : (int) ( $post ?? get_the_ID() );
		if ( ! isset( $GLOBALS['nh_test']['posts'][ $id ] ) ) {
			return false;
		}
		$slug = (string) get_post_meta( $id, '_wp_page_template', true );
		return ( '' === $slug || 'default' === $slug ) ? '' : $slug;
	}
}

/* ---------- Users, nonces, requests ------------------------------------------------------------------- */

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return ! empty( $GLOBALS['nh_test']['caps'][ $capability ] );
	}
	function wp_create_nonce( $action = -1 ) {
		return 'nonce-' . $action;
	}
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return ( 'nonce-' . $action ) === (string) $nonce ? 1 : false;
	}
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		if ( ! wp_verify_nonce( $_REQUEST[ $query_arg ] ?? '', $action ) ) { // phpcs:ignore
			throw new NH_Test_Halt( 'check_admin_referer failed' );
		}
		return 1;
	}
	function wp_salt( $scheme = 'auth' ) {
		return 'nh-test-salt-' . $scheme;
	}
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		return gmdate( (string) $format, null === $timestamp ? time() : (int) $timestamp );
	}
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			$params = $args[0];
			$uri    = $args[1] ?? '';
		} else {
			$params = [ $args[0] => $args[1] ];
			$uri    = $args[2] ?? '';
		}
		$frag = '';
		if ( false !== ( $p = strpos( (string) $uri, '#' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			$frag = substr( $uri, $p );
			$uri  = substr( $uri, 0, $p );
		}
		$base  = $uri;
		$query = [];
		if ( false !== ( $q = strpos( $uri, '?' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			$base = substr( $uri, 0, $q );
			parse_str( substr( $uri, $q + 1 ), $query );
		}
		foreach ( $params as $k => $v ) {
			if ( false === $v ) {
				unset( $query[ $k ] );
			} else {
				$query[ $k ] = $v;
			}
		}
		$pairs = [];
		foreach ( $query as $k => $v ) {
			$pairs[] = $k . '=' . $v;
		}
		return $base . ( $pairs ? '?' . implode( '&', $pairs ) : '' ) . $frag;
	}
	function remove_query_arg( $key, $query = false ) {
		$keys = (array) $key;
		return add_query_arg( array_fill_keys( $keys, false ), (string) $query );
	}
	function wp_validate_redirect( $location, $fallback_url = '' ) {
		$location = trim( (string) $location );
		if ( '' === $location ) {
			return $fallback_url;
		}
		$host = parse_url( $location, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( $host && 'nh.test' !== $host ) {
			return $fallback_url;
		}
		return $location;
	}
	function wp_safe_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {
		$GLOBALS['nh_test']['redirects'][] = [ wp_validate_redirect( $location, admin_url() ), (int) $status ];
		return true;
	}
	function wp_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {
		$GLOBALS['nh_test']['redirects'][] = [ (string) $location, (int) $status ];
		return true;
	}
	function wp_send_json( $response, $status_code = null, $flags = 0 ) {
		$GLOBALS['nh_test']['json'][] = [ json_decode( (string) json_encode( $response ), true ), $status_code ];
		throw new NH_Test_Halt( 'wp_send_json' );
	}
	function wp_die( $message = '', $title = '', $args = [] ) {
		throw new NH_Test_Halt( 'wp_die: ' . ( is_string( $message ) ? $message : '' ) );
	}
	function nocache_headers() {
		$GLOBALS['nh_test']['headers']['nocache'] = true;
	}
	function _doing_it_wrong( $function_name, $message, $version ) {
		$GLOBALS['nh_test']['doing_wrong'][] = $function_name . ': ' . wp_strip_all_tags( (string) $message );
	}
}

/* ---------- Mail and files ------------------------------------------------------------------------------- */

if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
		$GLOBALS['nh_test']['mails'][] = [
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
			'from_name'   => apply_filters( 'wp_mail_from_name', 'WordPress' ),
			'files'       => array_map(
				static function ( $path ) {
					return is_string( $path ) && is_file( $path ) ? (string) file_get_contents( $path ) : null; // phpcs:ignore
				},
				(array) $attachments
			),
		];
		return (bool) $GLOBALS['nh_test']['mail_result'];
	}
	function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
		$ext   = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		$mimes = is_array( $mimes ) ? $mimes : [];
		$none  = [
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		];
		if ( '' === $ext || ! isset( $mimes[ $ext ] ) || ! is_file( (string) $file ) ) {
			return $none;
		}
		// The "real type" check of WordPress, reduced to the cases the tests need: a PDF starts with %PDF.
		$head = (string) file_get_contents( (string) $file, false, null, 0, 8 ); // phpcs:ignore
		if ( 'pdf' === $ext && 0 !== strpos( $head, '%PDF' ) ) {
			return $none;
		}
		return [
			'ext'             => $ext,
			'type'            => $mimes[ $ext ],
			'proper_filename' => false,
		];
	}
	function wp_delete_file( $file ) {
		$GLOBALS['nh_test']['deleted'][] = (string) $file;
		if ( is_file( (string) $file ) ) {
			unlink( (string) $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}
	function wp_handle_upload( ...$args ) {
		throw new LogicException( 'wp_handle_upload() must never be called (SPEC §6.2: a CV never enters the media library)' );
	}
	function media_handle_upload( ...$args ) {
		throw new LogicException( 'media_handle_upload() must never be called (SPEC §6.2)' );
	}
	function wp_insert_attachment( ...$args ) {
		throw new LogicException( 'wp_insert_attachment() must never be called (SPEC §6.2)' );
	}
	function wp_insert_post( ...$args ) {
		throw new LogicException( 'wp_insert_post() must never be called: form data is never stored' );
	}
}

/* ---------- Media (backed by fixtures/media.php) ------------------------------------------------------- */

if ( ! function_exists( 'wp_attachment_is_image' ) ) {
	function wp_attachment_is_image( $post = null ) {
		$p = get_post( $post );
		return $p && 'attachment' === $p->post_type && 0 === strpos( $p->post_mime_type, 'image/' );
	}
	function wp_get_attachment_metadata( $attachment_id = 0, $unfiltered = false ) {
		$m = $GLOBALS['nh_test']['meta'][ (int) $attachment_id ]['_wp_attachment_metadata'] ?? false;
		return is_array( $m ) ? $m : false;
	}
	function wp_get_attachment_url( $attachment_id = 0 ) {
		$p = $GLOBALS['nh_test']['posts'][ (int) $attachment_id ] ?? null;
		return ( $p && 'attachment' === $p['type'] ) ? (string) $p['url'] : false;
	}
	function wp_get_attachment_image_srcset( $attachment_id, $size = 'medium', $image_meta = null ) {
		$m = wp_get_attachment_metadata( $attachment_id );
		if ( ! $m || empty( $m['sizes'] ) ) {
			return false;
		}
		$url  = (string) wp_get_attachment_url( $attachment_id );
		$base = substr( $url, 0, (int) strrpos( $url, '/' ) + 1 );
		$set  = [];
		foreach ( $m['sizes'] as $s ) {
			// Fixture simplification: the full size of the fake library has no file of its own, so the
			// srcset lists the sub-sizes (as the prototype does).
			if ( abs( $m['height'] * $s['width'] / $m['width'] - $s['height'] ) <= 1 ) {
				$set[ (int) $s['width'] ] = $base . $s['file'] . ' ' . (int) $s['width'] . 'w';
			}
		}
		ksort( $set );
		return $set ? implode( ', ', $set ) : false;
	}
	function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail', $icon = false ) {
		$m = wp_get_attachment_metadata( $attachment_id );
		return $m ? [ wp_get_attachment_url( $attachment_id ), (int) $m['width'], (int) $m['height'], false ] : false;
	}
}

/* ---------- Styles and scripts ----------------------------------------------------------------------------- */

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src, $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['nh_test']['styles'][ $handle ] = [
			'src'  => $src,
			'deps' => (array) $deps,
			'ver'  => $ver,
		];
		return true;
	}
	function wp_register_script( $handle, $src, $deps = [], $ver = false, $args = [] ) {
		$GLOBALS['nh_test']['scripts'][ $handle ] = [
			'src'  => $src,
			'deps' => (array) $deps,
			'ver'  => $ver,
			'args' => is_array( $args ) ? $args : [ 'in_footer' => (bool) $args ],
		];
		return true;
	}
	function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {
		if ( '' !== (string) $src && ! isset( $GLOBALS['nh_test']['styles'][ $handle ] ) ) {
			wp_register_style( $handle, $src, $deps, $ver, $media ); // as WordPress: registers on the fly
		}
		$GLOBALS['nh_test']['enqueued']['style'][ $handle ] = true;
	}
	function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $args = [] ) {
		$GLOBALS['nh_test']['enqueued']['script'][ $handle ] = true;
	}
	function wp_style_is( $handle, $status = 'enqueued' ) {
		return 'registered' === $status ? isset( $GLOBALS['nh_test']['styles'][ $handle ] ) : isset( $GLOBALS['nh_test']['enqueued']['style'][ $handle ] );
	}
	function wp_script_is( $handle, $status = 'enqueued' ) {
		return 'registered' === $status ? isset( $GLOBALS['nh_test']['scripts'][ $handle ] ) : isset( $GLOBALS['nh_test']['enqueued']['script'][ $handle ] );
	}
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		$GLOBALS['nh_test']['inline'][ $handle ][ $position ][] = (string) $data;
		return true;
	}
	function wp_get_inline_script_tag( $data, $attributes = [] ) {
		$attrs = '';
		foreach ( (array) $attributes as $k => $v ) {
			$attrs .= true === $v ? ' ' . $k : ' ' . $k . '="' . esc_attr( (string) $v ) . '"';
		}
		return '<script' . $attrs . '>' . (string) $data . "</script>\n";
	}
	function wp_print_inline_script_tag( $data, $attributes = [] ) {
		echo wp_get_inline_script_tag( $data, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
