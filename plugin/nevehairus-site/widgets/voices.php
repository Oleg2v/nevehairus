<?php
/**
 * Widget `voices` — the families' voice on the night spread: a quote and a video facade.
 *
 * Prototype: prototype/src/index.html L261–295 (`section#voices`).
 * Spec: plugin/SPEC.md §4.9.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * The spread starts like every other (v3.2, research/07 §10.2 item 8): the ruling line and a visible H2,
 * then the quote. The plain-language twin of the quote replaces the whole blockquote (reported speech,
 * not a quotation).
 *
 * The video is a facade with a visible word on its button («צפו בסרטון»): nothing is requested from YouTube
 * before the visitor agrees to media cookies (assets/js/widgets/voices.js, through nh.consent).
 */
final class NH_Widget_Voices extends NH_Widget_Base {

	public const ID = 'voices';

	public const KEYWORDS = [ 'voices', 'quote', 'video', 'youtube', 'ציטוט', 'סרטון', 'משפחות' ];

	/**
	 * `sizes` of the 16:9 poster when its own `poster_sizes` is empty (research/07 §8.1: at most 37.5rem).
	 */
	private const POSTER_SIZES = '(min-width: 64em) 37.5rem, calc(100vw - 2.5rem)';

	/**
	 * A YouTube video id; anything else means "no video".
	 */
	private const VIDEO_ID = '/^[A-Za-z0-9_-]{11}$/';

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'קולות · ציטוט ווידאו', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-testimonial';
	}

	/**
	 * Controls: תוכן (title, quote, cite, video texts), תמונות (poster), then מקטע.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'nh_content',
			[
				'label' => __( 'תוכן', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		NH_Controls::heading(
			$this,
			[
				'default' => 'מה אומרות המשפחות.',
				'plain'   => '',
				'dot'     => 'citrus',
			]
		);

		NH_Controls::pair(
			$this,
			'quote',
			[
				'label'   => __( 'ציטוט', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 3,
				'default' => 'הבן שלי וחבריו מבלים מחוץ לכותלי המוסד מדי שבוע.',
				'plain'   => "אמא של דייר כתבה לנו מכתב.\nהיא כתבה שהבן שלה והחברים שלו יוצאים לבלות כל שבוע.",
			]
		);

		$this->add_control(
			'cite',
			[
				'label'       => __( 'מי אמר (מתחת לציטוט)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'ליליה, אמא של דייר בנווה האירוס',
				'description' => __( 'בשפה פשוטה הטקסט הפשוט של הציטוט מחליף את הציטוט כולו, כולל השורה הזאת.', 'nevehairus-site' ),
				'label_block' => true,
			]
		);

		$this->add_control(
			'video_heading',
			[
				'label'     => __( 'סרטון', 'nevehairus-site' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'video_id',
			[
				'label'       => __( 'מזהה הסרטון ב־YouTube', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'JiaXto-WmJo',
				'description' => __( '11 התווים שאחרי v= בכתובת הסרטון. ריק או לא תקין = בלי סרטון.', 'nevehairus-site' ),
				'label_block' => true,
			]
		);

		$this->add_control(
			'video_title',
			[
				'label'       => __( 'שם הסרטון', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'הורה של דייר מספר על נווה האירוס',
				'label_block' => true,
			]
		);

		NH_Controls::pair(
			$this,
			'play_label',
			[
				'label'   => __( 'כפתור ההפעלה: המילים על הכפתור', 'nevehairus-site' ),
				'default' => 'צפו בסרטון',
				'plain'   => '',
			]
		);

		$this->add_control(
			'play_prefix',
			[
				'label'       => __( 'כפתור ההפעלה: טקסט לקוראי מסך בין המילים על הכפתור לשם הסרטון', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => ':',
				'description' => __( 'קורא המסך מקריא: המילים על הכפתור, הטקסט הזה ושם הסרטון.', 'nevehairus-site' ),
				'label_block' => true,
			]
		);

		NH_Controls::pair(
			$this,
			'consent_text',
			[
				'label'   => __( 'שאלת ההסכמה לפני טעינת הסרטון', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 3,
				'default' => 'הסרטון מוטמע מ־YouTube, שעשוי לשמור עוגיות. לטעון אותו?',
				'plain'   => "הסרטון נמצא באתר YouTube.\nYouTube יכול לשמור מידע עליכם.\nלפתוח את הסרטון?",
			]
		);

		NH_Controls::pair(
			$this,
			'load_label',
			[
				'label'   => __( 'כפתור: לטעון את הסרטון', 'nevehairus-site' ),
				'default' => 'כן, לטעון את הסרטון',
				'plain'   => '',
			]
		);

		NH_Controls::pair(
			$this,
			'cancel_label',
			[
				'label'   => __( 'כפתור: לא לטעון', 'nevehairus-site' ),
				'default' => 'לא עכשיו',
				'plain'   => '',
			]
		);

		NH_Controls::pair(
			$this,
			'caption',
			[
				'label'   => __( 'כיתוב מתחת לסרטון', 'nevehairus-site' ),
				'default' => 'סרטון: הורה של דייר מספר על נווה האירוס. בתמונה: ינשופים, עבודת יד של דיירות ודיירי נווה האירוס.',
				'plain'   => '',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'nh_images',
			[
				'label' => __( 'תמונות', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		NH_Controls::image(
			$this,
			'poster',
			[
				'label'      => __( 'תמונת הפתיחה של הסרטון', 'nevehairus-site' ),
				'decorative' => true,
				'alt'        => '',
				'sizes'      => self::POSTER_SIZES,
			]
		);

		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => 'voices' ] );
	}

	/**
	 * Markup of index.html `section#voices`: the ruling line, the visible H2 with the quote, the video.
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		$title = $this->nh_h2( $s );

		$words  = '' !== $title ? $title . "\n" : '';
		$words .= $this->life_quote( $s );

		$html  = $this->nh_section_open(
			$s,
			[
				'class'      => 'v3-voices v3-spread',
				'tone'       => 'night',
				'labelledby' => '' !== $title ? 'title' : null,
			]
		);
		$html .= "\n" . '<div class="nh-container v3-voices__grid">' . "\n";
		$html .= $this->nh_rule() . "\n";
		if ( '' !== $words ) {
			$html .= '<div class="v3-voices__words">' . "\n" . $words . '</div>' . "\n";
		}
		$html .= $this->life_video( $s );
		$html .= '</div>' . "\n";
		$html .= $this->nh_section_close() . "\n";

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The blockquote and its plain twin, by the five states of §3.5 (the twin replaces the whole
	 * blockquote, cite included): the blockquote gets `v3-full` whenever a twin is set (or `-`).
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private function life_quote( array $s ): string {
		$quote = trim( self::life_text( $s, 'quote' ) );
		$plain = trim( self::life_text( $s, 'quote_plain' ) );
		$cite  = trim( self::life_text( $s, 'cite' ) );

		$html = '';
		if ( '' !== $quote ) {
			$html .= '<blockquote' . NH_Html::attrs( [ 'class' => '' === $plain ? 'v3-quote' : 'v3-quote v3-full' ] ) . '>';
			$html .= '<p>' . NH_Text::esc( $quote ) . '</p>';
			if ( '' !== $cite ) {
				$html .= '<footer>— <cite>' . NH_Text::esc( $cite ) . '</cite></footer>';
			}
			$html .= '</blockquote>' . "\n";
		}

		// State 4 of NH_Text::block() (no full text): `<div class="v3-plain"><p>…</p>…</div>`, or '' for '' and '-'.
		$twin = NH_Text::block(
			'',
			$plain,
			[
				'plain_tag'   => 'div',
				'plain_lines' => 'p',
			]
		);
		if ( '' !== $twin ) {
			$html .= $twin . "\n";
		}

		return $html;
	}

	/**
	 * `figure.v3-video-fig` with the facade, or '' when the video id is not a YouTube id.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private function life_video( array $s ): string {
		$id = trim( self::life_text( $s, 'video_id' ) );
		if ( 1 !== preg_match( self::VIDEO_ID, $id ) ) {
			return '';
		}

		$video_title = trim( self::life_text( $s, 'video_title' ) );
		// The visible word(s) on the button, then for screen readers the prefix and the video's title:
		// «צפו בסרטון» + «: הורה של דייר…» (research/07 §10.2 item 8).
		$word = NH_Text::pair( self::life_text( $s, 'play_label' ), self::life_text( $s, 'play_label_plain' ) );
		$sr   = trim( trim( self::life_text( $s, 'play_prefix' ) ) . ' ' . $video_title );
		if ( '' === $word && '' === $sr ) {
			$sr = __( 'סרטון', 'nevehairus-site' ); // The play button must never be nameless.
		}

		$consent = NH_Text::block(
			self::life_text( $s, 'consent_text' ),
			self::life_text( $s, 'consent_text_plain' ),
			[
				'full_tag'  => 'p',
				'plain_tag' => 'div',
			]
		);

		$buttons = '';
		$load    = NH_Text::pair( self::life_text( $s, 'load_label' ), self::life_text( $s, 'load_label_plain' ) );
		if ( '' !== $load ) {
			$buttons .= '<button class="nh-btn nh-btn--light" type="button" data-video-load>' . $load . '</button>';
		}
		$cancel = NH_Text::pair( self::life_text( $s, 'cancel_label' ), self::life_text( $s, 'cancel_label_plain' ) );
		if ( '' !== $cancel ) {
			$buttons .= '<button class="nh-btn nh-btn--ghost" type="button" data-video-cancel>' . $cancel . '</button>';
		}

		$caption = NH_Text::pair( self::life_text( $s, 'caption' ), self::life_text( $s, 'caption_plain' ) );

		$html   = '<figure class="v3-video-fig">' . "\n";
		$html  .= '<div' . NH_Html::attrs(
			[
				'class'      => 'nh-video v3-video',
				'data-video' => $id,
				'data-title' => '' !== $video_title ? $video_title : null,
			]
		) . '>' . "\n";
		$poster = $this->life_poster( $s );
		if ( '' !== $poster ) {
			$html .= $poster . "\n";
		}
		$html .= '<button class="nh-video__play" type="button">'
			. '<span class="nh-video__btn">'
			. '<span class="nh-video__icon" aria-hidden="true">' . NH_Html::icon( 'play', '', false ) . '</span>'
			. ( '' !== $word ? '<span>' . $word . '</span>' : '' )
			. '</span>'
			. ( '' !== $sr ? '<span class="nh-sr-only">' . esc_html( $sr ) . '</span>' : '' )
			. '</button>' . "\n";
		$html .= '<div class="nh-video__consent" hidden>' . "\n";
		if ( '' !== $consent ) {
			$html .= $consent . "\n";
		}
		if ( '' !== $buttons ) {
			// The prototype centred this row with an inline style; the rule now lives in voices.css (§4.9).
			$html .= '<div class="nh-btn-row">' . $buttons . '</div>' . "\n";
		}
		$html .= '</div>' . "\n";
		$html .= '</div>' . "\n";
		if ( '' !== $caption ) {
			$html .= '<figcaption class="v3-video__cap">' . $caption . '</figcaption>' . "\n";
		}
		$html .= '</figure>' . "\n";

		return $html;
	}

	/**
	 * The poster `<picture>` ('' without an image: the facade's dark background remains).
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	private function life_poster( array $s ): string {
		$img = NH_Controls::image_args( $s, 'poster' );
		$id  = isset( $img['photo'] ) ? (int) $img['photo'] : 0;
		if ( $id <= 0 ) {
			return '';
		}

		$sizes = isset( $img['sizes'] ) && is_string( $img['sizes'] ) ? trim( $img['sizes'] ) : '';
		$o     = [ 'sizes' => '' !== $sizes ? $sizes : self::POSTER_SIZES ];
		// Decorative by default: the play button names the video and the caption describes the image.
		if ( ! empty( $img['decorative'] ) ) {
			$o['decorative'] = true;
		} elseif ( isset( $img['alt'] ) && is_string( $img['alt'] ) && '' !== trim( $img['alt'] ) ) {
			$o['alt'] = trim( $img['alt'] );
		}

		return NH_Picture::render( $id, $o );
	}

	/**
	 * A setting as a string ('' when missing or not scalar).
	 *
	 * @param array  $s   Settings.
	 * @param string $key Control id.
	 * @return string
	 */
	private static function life_text( array $s, string $key ): string {
		return isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? (string) $s[ $key ] : '';
	}
}
