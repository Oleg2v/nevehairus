<?php
defined( 'ABSPATH' ) || exit;

/**
 * Small HTML helpers: attributes, icons, URLs and link tokens, LTR spans, phone links, aria-current
 * (current_state()), the display toggles, the editor-only note (SPEC §3.6).
 *
 * @package nevehairus-site
 */
final class NH_Html {

	/** Symbols of the sprite printed by the site module (SPEC §5.3). */
	public const ICONS = [ 'phone', 'menu', 'close', 'arrow-fwd', 'arrow-back', 'play', 'expand', 'speech', 'sliders' ];

	/** Link tokens that resolve to a page from the settings (SPEC §3.5, §7). */
	public const TOKENS = [ '#privacy', '#accessibility', '#sitemap', '#entrances', '#jobs-page' ];

	/** Elements without a closing tag. */
	private const VOID = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr' ];

	/**
	 * ` k="v"` for each attribute: null/false skipped, true → bare attribute, arrays joined by spaces,
	 * values esc_attr()'d.
	 *
	 * @param array $a Attributes.
	 */
	public static function attrs( array $a ): string {
		$out = '';
		foreach ( $a as $k => $v ) {
			if ( null === $v || false === $v ) {
				continue;
			}
			$k = preg_replace( '/[^a-zA-Z0-9_:.-]/', '', (string) $k );
			if ( '' === $k ) {
				continue;
			}
			if ( true === $v ) {
				$out .= ' ' . $k;
				continue;
			}
			if ( is_array( $v ) ) {
				$v = implode(
					' ',
					array_filter(
						array_map( 'strval', $v ),
						static function ( string $x ): bool {
							return '' !== $x;
						}
					)
				);
			}
			$out .= ' ' . $k . '="' . esc_attr( (string) $v ) . '"';
		}
		return $out;
	}

	/**
	 * `<tag attrs>inner</tag>` (void elements without closing tag). `$inner` is trusted HTML.
	 */
	public static function tag( string $tag, array $a, string $inner ): string {
		$tag  = preg_match( '/^[a-z][a-z0-9-]*$/', $tag ) ? $tag : 'span';
		$open = '<' . $tag . self::attrs( $a ) . '>';
		return in_array( $tag, self::VOID, true ) ? $open : $open . $inner . '</' . $tag . '>';
	}

	/**
	 * An icon of the sprite: `<svg class="…" aria-hidden="true" focusable="false"><use href="#i-{name}"/></svg>`.
	 * No class attribute when `$class` is ''. With `$aria_hidden = false` only focusable="false" is written
	 * (for icons inside an element that is already aria-hidden).
	 */
	public static function icon( string $name, string $class = '', bool $aria_hidden = true ): string {
		$name = preg_replace( '/[^a-z0-9-]/', '', strtolower( $name ) );
		return '<svg' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' )
			. ( $aria_hidden ? ' aria-hidden="true"' : '' )
			. ' focusable="false"><use href="#i-' . $name . '"/></svg>';
	}

	/**
	 * A URL control value (array with 'url') or a string → esc_url()'d URL, link tokens resolved;
	 * '' when empty.
	 *
	 * @param array|string|null $link URL control value or URL.
	 */
	public static function url( $link ): string {
		$url = is_array( $link ) ? ( $link['url'] ?? '' ) : $link;
		$url = is_scalar( $url ) ? trim( (string) $url ) : '';
		if ( '' === $url ) {
			return '';
		}
		return esc_url( self::resolve_token( $url ) );
	}

	/**
	 * A link token → the URL from the settings; anything else (and an unresolved token) unchanged.
	 */
	public static function resolve_token( string $href ): string {
		if ( ! in_array( $href, self::TOKENS, true ) ) {
			return $href;
		}
		$url = '#jobs-page' === $href ? NH_Settings::jobs_url() : NH_Settings::page_url( substr( $href, 1 ) );
		return '' !== $url ? $url : $href;
	}

	/**
	 * True when the URL has a host and it is not this site's host.
	 */
	public static function is_external( string $url ): bool {
		$host = wp_parse_url( trim( $url ), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}
		$own = wp_parse_url( home_url(), PHP_URL_HOST );
		return strtolower( $host ) !== strtolower( (string) $own );
	}

	/**
	 * `<span class="nh-ltr">{text}</span>` (phones, e-mails, "100+" inside Hebrew text).
	 */
	public static function ltr( string $text ): string {
		return '<span class="nh-ltr">' . esc_html( $text ) . '</span>';
	}

	/**
	 * `tel:` + the digits and "+" of a phone number.
	 */
	public static function tel( string $tel ): string {
		return 'tel:' . preg_replace( '/[^+0-9]/', '', $tel );
	}

	/**
	 * The post id of a same-host URL without a fragment (url_to_postid()), else 0.
	 */
	public static function post_id( string $url ): int {
		$url = trim( $url );
		if ( '' === $url || false !== strpos( $url, '#' ) || self::is_external( $url ) ) {
			return 0;
		}
		return (int) url_to_postid( $url );
	}

	/**
	 * Where a navigation link stands relative to the requested page (research/07 §5.3, §12.3):
	 * - `'page'`: `$url` (same host, no fragment) is the requested post itself;
	 * - `'true'`: the requested post or one of its ancestors is in `$ids_csv` (comma-separated post ids), or
	 *   `$url` points to an ancestor of the requested post — the link is the current section;
	 * - `''`: anything else, and always outside `is_singular()` (on archives, search and 404 the queried
	 *   object is a term, a user or nothing, never a post: its id must not be read as one).
	 *
	 * @param string $url     The link (plain, not HTML-escaped).
	 * @param string $ids_csv Post ids that make the link current (e.g. the pages of the section).
	 */
	public static function current_state( string $url, string $ids_csv = '' ): string {
		if ( ! is_singular() ) {
			return '';
		}
		$current = (int) get_queried_object_id();
		if ( $current <= 0 ) {
			return '';
		}
		$id = self::post_id( $url );
		if ( $id > 0 && $id === $current ) {
			return 'page';
		}
		$ancestors = array_map( 'intval', (array) get_post_ancestors( $current ) );
		$ids       = array_filter( array_map( 'absint', explode( ',', $ids_csv ) ) );
		if ( array_intersect( array_merge( [ $current ], $ancestors ), $ids ) ) {
			return 'true';
		}
		return ( $id > 0 && in_array( $id, $ancestors, true ) ) ? 'true' : '';
	}

	/**
	 * Current-page test for navigation: `current_state()` is not ''.
	 */
	public static function is_current( string $url, string $ids_csv = '' ): bool {
		return '' !== self::current_state( $url, $ids_csv );
	}

	/**
	 * The display switches, plain language and calm mode (research/07 §7.3), as in the menu and the footer:
	 * `<button class="v3-toggle" data-{plain|calm}-toggle aria-pressed="false">` with the label, the silent
	 * state word (`data-on` / `data-off`, shown by CSS) and the track. A switch is printed only when its key is
	 * in `$labels` (always in this order: plain, calm): the menu passes both, the footer's display widget may
	 * pass only `calm`. Each label is HTML (e.g. NH_Text::pair() output; filtered with the inline allowlist) or
	 * a [ full, plain ] pair. The reduced-motion switch of v3.1 is gone: a `motion` label is ignored.
	 *
	 * @param array $labels [ 'plain' => …, 'calm' => … ] (either key may be left out).
	 * @param array $states [ 'on' => 'פועל', 'off' => 'כבוי' ] (plain text; the defaults when missing).
	 */
	public static function display_toggles( array $labels, array $states = [] ): string {
		// the widgets pass their own controls (state_on / state_off); these are only the fallback
		$on  = isset( $states['on'] ) && is_scalar( $states['on'] ) ? trim( (string) $states['on'] ) : __( 'פועל', 'nevehairus-site' );
		$off = isset( $states['off'] ) && is_scalar( $states['off'] ) ? trim( (string) $states['off'] ) : __( 'כבוי', 'nevehairus-site' );
		$out = '';
		foreach ( [ 'plain', 'calm' ] as $key ) {
			if ( ! array_key_exists( $key, $labels ) ) {
				continue;
			}
			$label = $labels[ $key ] ?? '';
			$label = is_array( $label )
				? NH_Text::pair( (string) ( $label[0] ?? '' ), (string) ( $label[1] ?? '' ) )
				: wp_kses( (string) $label, NH_Text::kses_inline() );
			$state = ( '' !== $on || '' !== $off )
				? '<span class="v3-toggle__state" aria-hidden="true" data-on="' . esc_attr( $on ) . '" data-off="' . esc_attr( $off ) . '"></span>'
				: '';
			$out  .= '<button type="button" class="v3-toggle" data-' . $key . '-toggle aria-pressed="false"><span class="v3-toggle__label">' . $label . '</span>' . $state . '<i aria-hidden="true"></i></button>';
		}
		return $out;
	}

	/**
	 * A note only editors see (users who can edit_posts): missing settings, unresolved links. '' for visitors.
	 */
	public static function admin_note( string $text ): string {
		if ( '' === trim( $text ) || ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="nh-admin-note" role="note">' . esc_html( $text ) . '</p>';
	}
}
