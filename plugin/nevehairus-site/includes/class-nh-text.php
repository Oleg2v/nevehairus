<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plain-language twins, lines, the heading tessera, the inline allowlist and text tokens (SPEC §3.5).
 *
 * The five states of a text F and its twin P (both trimmed):
 *   1  F ≠ '', P = ''         → F once, no mode class (both modes)
 *   2  F ≠ '', P ≠ '', P ≠ -  → F with class v3-full, then P with class v3-plain
 *   3  F ≠ '', P = -          → F with class v3-full only (hidden in plain mode)
 *   4  F = '', P ≠ '', P ≠ -  → P with class v3-plain only
 *   5  otherwise              → '' (the caller omits the surrounding element)
 *
 * @package nevehairus-site
 */
final class NH_Text {

	/** A twin with this value hides the text in plain mode. */
	public const HIDE = '-';

	/** Tessera colours (the iris one is ''). */
	private const DOTS = [ '', 'clay', 'ink', 'citrus' ];

	/**
	 * The state (1–5) of a text and its twin.
	 */
	public static function state( string $full, string $plain = '' ): int {
		$f = trim( $full );
		$p = trim( $plain );
		if ( '' !== $f ) {
			if ( '' === $p ) {
				return 1;
			}
			return self::HIDE === $p ? 3 : 2;
		}
		return ( '' !== $p && self::HIDE !== $p ) ? 4 : 5;
	}

	/**
	 * Inline pair. Options: tag ('span'), class (extra classes on every element), dot (null = none;
	 * '' iris; 'clay'|'ink'|'citrus'; 'none'), plain_lines (each line of P in its own span, joined by a
	 * space), bare (true: state 1 without class = text only), esc ('inline'|'text').
	 *
	 * @param string $full  Text.
	 * @param string $plain Plain-language twin.
	 * @param array  $o     Options.
	 */
	public static function pair( string $full, string $plain = '', array $o = [] ): string {
		$o     = array_merge(
			[
				'tag'         => 'span',
				'class'       => '',
				'dot'         => null,
				'plain_lines' => false,
				'bare'        => true,
				'esc'         => 'inline',
			],
			$o
		);
		$state = self::state( $full, $plain );
		if ( 5 === $state ) {
			return '';
		}
		$f   = trim( $full );
		$p   = trim( $plain );
		$tag = self::tag_name( (string) $o['tag'], 'span' );
		$cls = self::classes( (string) $o['class'] );
		$esc = (string) $o['esc'];

		$fh = 4 === $state ? '' : self::dot( self::esc( $f, $esc ), $o['dot'] );
		$ph = '';
		if ( 2 === $state || 4 === $state ) {
			if ( $o['plain_lines'] ) {
				$parts = [];
				foreach ( self::lines( $p ) as $line ) {
					$parts[] = '<span>' . self::esc( $line, $esc ) . '</span>';
				}
				$ph = implode( ' ', $parts );
			} else {
				$ph = self::esc( $p, $esc );
			}
		}

		switch ( $state ) {
			case 1:
				return ( $o['bare'] && '' === $cls ) ? $fh : self::wrap( $tag, $cls, [], $fh );
			case 2:
				return self::wrap( $tag, self::join( $cls, 'v3-full' ), [], $fh ) . self::wrap( $tag, self::join( $cls, 'v3-plain' ), [], $ph );
			case 3:
				return self::wrap( $tag, self::join( $cls, 'v3-full' ), [], $fh );
			default:
				return self::wrap( $tag, self::join( $cls, 'v3-plain' ), [], $ph );
		}
	}

	/**
	 * Block pair. Options: full_tag ('p'), full_class, full_attrs, full_lines (null = one element, newlines
	 * become <br>; 'li'|'p'|'span' = one child per line), plain_tag ('div'), plain_class, plain_attrs,
	 * plain_lines ('p'; null = one element), esc, dot (tessera on the full text).
	 *
	 * @param string $full  Text.
	 * @param string $plain Plain-language twin.
	 * @param array  $o     Options.
	 */
	public static function block( string $full, string $plain = '', array $o = [] ): string {
		$o     = array_merge(
			[
				'full_tag'    => 'p',
				'full_class'  => '',
				'full_attrs'  => [],
				'full_lines'  => null,
				'plain_tag'   => 'div',
				'plain_class' => '',
				'plain_attrs' => [],
				'plain_lines' => 'p',
				'esc'         => 'inline',
				'dot'         => null,
			],
			$o
		);
		$state = self::state( $full, $plain );
		if ( 5 === $state ) {
			return '';
		}
		$esc = (string) $o['esc'];
		$out = '';
		if ( 4 !== $state ) {
			$cls  = self::classes( (string) $o['full_class'] );
			$out .= self::wrap(
				self::tag_name( (string) $o['full_tag'], 'p' ),
				1 === $state ? $cls : self::join( $cls, 'v3-full' ),
				(array) $o['full_attrs'],
				self::inner( trim( $full ), $o['full_lines'], $esc, $o['dot'] )
			);
		}
		if ( 2 === $state || 4 === $state ) {
			$out .= self::wrap(
				self::tag_name( (string) $o['plain_tag'], 'div' ),
				self::join( self::classes( (string) $o['plain_class'] ), 'v3-plain' ),
				(array) $o['plain_attrs'],
				self::inner( trim( $plain ), $o['plain_lines'], $esc, null )
			);
		}
		return $out;
	}

	/**
	 * Lines of a text: split on newlines (\r\n normalised), trimmed, empty lines dropped.
	 *
	 * @return string[]
	 */
	public static function lines( string $text ): array {
		$out = [];
		foreach ( explode( "\n", str_replace( [ "\r\n", "\r" ], "\n", $text ) ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * Escapes a text. 'inline': wp_kses() with the inline allowlist, link tokens in href resolved,
	 * newlines → <br>. 'text': esc_html().
	 */
	public static function esc( string $text, string $mode = 'inline' ): string {
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );
		if ( 'text' === $mode ) {
			return esc_html( $text );
		}
		$html = self::link_tokens( wp_kses( $text, self::kses_inline() ) );
		return implode( '<br>', self::lines( $html ) );
	}

	/**
	 * The final "." of a heading becomes a tessera: `<span class="v3-dot[ v3-dot--{variant}]">.</span>`.
	 * Variant null or 'none' = unchanged. Only the last dot is replaced.
	 *
	 * @param string      $html    Escaped HTML.
	 * @param string|null $variant '' (iris), 'clay', 'ink', 'citrus', 'none' or null.
	 */
	public static function dot( string $html, ?string $variant ): string {
		if ( null === $variant || 'none' === $variant ) {
			return $html;
		}
		$t = rtrim( $html );
		if ( '' === $t || '.' !== substr( $t, -1 ) ) {
			return $html;
		}
		$variant = in_array( $variant, self::DOTS, true ) ? $variant : '';
		$class   = '' === $variant ? 'v3-dot' : 'v3-dot v3-dot--' . $variant;
		return substr( $t, 0, -1 ) . '<span class="' . $class . '">.</span>';
	}

	/**
	 * The allowlist of texts with inline markup (wp_kses format).
	 */
	public static function kses_inline(): array {
		return [
			'a'      => [
				'href' => true,
				'rel'  => true,
				'lang' => true,
				'dir'  => true,
			],
			'b'      => [],
			'strong' => [],
			'small'  => [],
			'br'     => [],
			'span'   => [
				'class'       => true,
				'lang'        => true,
				'dir'         => true,
				'aria-hidden' => true,
			],
		];
	}

	/**
	 * Settings-text tokens: {phone} (tel link), {email} (": " + mailto link, or '' without a public e-mail),
	 * {contact} ({email} when set, else ": " + the phone link), {year}.
	 *
	 * @param string $html Escaped HTML.
	 */
	public static function tokens( string $html ): string {
		if ( false === strpos( $html, '{' ) ) {
			return $html;
		}
		$phone = NH_Settings::phone();
		$tel   = '' !== $phone['display']
			? '<a href="' . esc_url( NH_Html::tel( $phone['tel'] ) ) . '">' . NH_Html::ltr( $phone['display'] ) . '</a>'
			: '';
		$email = NH_Settings::email_public();
		$mail  = '' !== $email
			? ': <a href="' . esc_url( 'mailto:' . $email ) . '">' . NH_Html::ltr( $email ) . '</a>'
			: '';
		return strtr(
			$html,
			[
				'{phone}'   => $tel,
				'{email}'   => $mail,
				'{contact}' => '' !== $mail ? $mail : ( '' !== $tel ? ': ' . $tel : '' ),
				'{year}'    => esc_html( (string) wp_date( 'Y' ) ),
			]
		);
	}

	/**
	 * Resolves link tokens (#privacy, #accessibility, #sitemap, #entrances, #jobs-page) in href attributes
	 * of escaped HTML. Unresolved tokens stay as in-page anchors.
	 */
	public static function link_tokens( string $html ): string {
		if ( false === strpos( $html, 'href="#' ) ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'/\bhref="(#[a-z][a-z-]*)"/',
			static function ( array $m ): string {
				$url = NH_Html::resolve_token( $m[1] );
				return $url === $m[1] ? $m[0] : 'href="' . esc_url( $url ) . '"';
			},
			$html
		);
	}

	/**
	 * Link tokens in the HTML that are not resolved (for an editor note), e.g. [ '#accessibility' ].
	 *
	 * @return string[]
	 */
	public static function unresolved_tokens( string $html ): array {
		preg_match_all( '/\bhref="(#(?:privacy|accessibility|sitemap|entrances|jobs-page))"/', self::link_tokens( $html ), $m );
		return array_values( array_unique( $m[1] ) );
	}

	/**
	 * Inner HTML of a block element: one element per line, or the whole text.
	 *
	 * @param string      $text  Trimmed text.
	 * @param string|null $lines Tag per line, or null.
	 * @param string      $esc   Escape mode.
	 * @param string|null $dot   Tessera of the last line.
	 */
	private static function inner( string $text, $lines, string $esc, $dot ): string {
		if ( null === $lines || '' === $lines ) {
			return self::dot( self::esc( $text, $esc ), null === $dot ? null : (string) $dot );
		}
		$tag   = self::tag_name( (string) $lines, 'p' );
		$items = self::lines( $text );
		$last  = count( $items ) - 1;
		$out   = '';
		foreach ( $items as $i => $item ) {
			$h    = self::esc( $item, $esc );
			$h    = ( $i === $last && null !== $dot ) ? self::dot( $h, (string) $dot ) : $h;
			$out .= '<' . $tag . '>' . $h . '</' . $tag . '>';
		}
		return $out;
	}

	/**
	 * `<tag attrs class="…">inner</tag>` (attributes first, as the prototype's `<ul role="list" class="v3-full">`).
	 */
	private static function wrap( string $tag, string $class, array $attrs, string $inner ): string {
		$attrs['class'] = '' !== $class ? $class : null;
		return '<' . $tag . NH_Html::attrs( $attrs ) . '>' . $inner . '</' . $tag . '>';
	}

	/**
	 * Sanitised class list.
	 */
	private static function classes( string $classes ): string {
		$out = [];
		foreach ( preg_split( '/\s+/', trim( $classes ) ) as $c ) {
			$c = sanitize_html_class( $c );
			if ( '' !== $c ) {
				$out[] = $c;
			}
		}
		return implode( ' ', $out );
	}

	/**
	 * "a b" + mode class.
	 */
	private static function join( string $classes, string $mode ): string {
		return '' === $classes ? $mode : $classes . ' ' . $mode;
	}

	/**
	 * A safe tag name or the default.
	 */
	private static function tag_name( string $tag, string $fallback ): string {
		return preg_match( '/^[a-z][a-z0-9]*$/', $tag ) ? $tag : $fallback;
	}
}
