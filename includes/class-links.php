<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * مطالب مهم + پیشنهاد لینک داخلی با هوش مصنوعی.
 *
 * فقط به «مطالب مهم» لینک داده می‌شود. فهرست آن‌ها (عنوان، نشانی، کلیدواژه) یک بار ساخته و نگه داشته می‌شود
 * و با هر تیک یا برداشتن تیک تازه می‌شود. برای هر مطلب فقط فهرست کوتاهی از مطالب مهمِ مرتبط به مدل فرستاده می‌شود.
 *
 * مدل برای هر لینک یک «عبارت انکر» می‌دهد که باید عیناً در متن باشد؛ سرور همین را در متن واقعی پیدا می‌کند
 * و فقط پیشنهادهایی را برمی‌گرداند که عبارتشان واقعاً در متن هست و هنوز به آن مطلب لینک نشده است.
 */
final class Links {

	const META  = '_rokhdadyar_important';
	const CACHE = 'chk_important';
	const SEND  = 60; // حداکثر مطلب مهمی که برای هر درخواست فرستاده می‌شود

	public static function mark( int $post_id, bool $on ): void {
		if ( $on ) {
			if ( ! get_post_meta( $post_id, self::META, true ) ) {
				update_post_meta( $post_id, self::META, time() );
			}
		} else {
			delete_post_meta( $post_id, self::META );
		}
		self::flush();
	}

	public static function flush(): void {
		delete_transient( self::CACHE );
	}

	/** @return array[] { id, title, url, kw } تازه‌ترین تیک اول */
	public static function index(): array {
		$hit = get_transient( self::CACHE );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$ids = get_posts(
			array(
				'post_type'      => (array) Settings::get( 'post_types' ),
				'post_status'    => 'publish',
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'posts_per_page' => 500,
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			if ( ! (int) get_post_meta( $id, self::META, true ) ) {
				continue;
			}
			$kw    = trim( (string) strtok( Extractor::keywords( (int) $id ), ',،' ) );
			$out[] = array(
				'id'    => (int) $id,
				'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'url'   => get_permalink( $id ),
				'kw'    => $kw,
			);
		}
		set_transient( self::CACHE, $out, DAY_IN_SECONDS );
		return $out;
	}

	private static function norm( string $s ): string {
		$s = mb_strtolower( $s, 'UTF-8' );
		$s = str_replace( array( "\u{200c}", 'ي', 'ك', 'ة' ), array( ' ', 'ی', 'ک', 'ه' ), $s );
		return ' ' . trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s ) ) . ' ';
	}

	/** مطالب مهمِ مرتبط با این متن (به‌جز خودش و آن‌هایی که از قبل لینک شده‌اند). */
	public static function candidates( \WP_Post $post, string $html, string $text ): array {
		$list = array();
		foreach ( self::index() as $it ) {
			if ( $it['id'] === $post->ID || ( $it['url'] && false !== strpos( $html, untrailingslashit( $it['url'] ) ) ) ) {
				continue;
			}
			$list[] = $it;
		}
		if ( count( $list ) <= self::SEND ) {
			return $list;
		}
		// فهرست بلند است: بر اساس هم‌پوشانی کلمه‌های عنوان و کلیدواژه با متن، مرتبط‌ترین‌ها می‌روند.
		$hay   = self::norm( $text );
		$score = array();
		foreach ( $list as $i => $it ) {
			$s = 0;
			if ( '' !== $it['kw'] && false !== strpos( $hay, self::norm( $it['kw'] ) ) ) {
				$s += 10;
			}
			foreach ( array_filter( explode( ' ', trim( self::norm( $it['title'] ) ) ), static function ( $w ) {
				return mb_strlen( $w, 'UTF-8' ) > 2;
			} ) as $w ) {
				if ( false !== strpos( $hay, ' ' . $w . ' ' ) ) {
					++$s;
				}
			}
			$score[ $i ] = $s * 1000 - $i; // برابرها: تازه‌ترین تیک اول
		}
		arsort( $score );
		$out = array();
		foreach ( array_slice( array_keys( $score ), 0, self::SEND ) as $i ) {
			$out[] = $list[ $i ];
		}
		return $out;
	}

	public static function prompt( array $cands, int $max ): string {
		$p  = "از فهرست «مطالب مهم» زیر، آن‌هایی را انتخاب کن که برای خوانندهٔ مطلب بالا واقعاً مرتبط و مفیدند تا به آن‌ها لینک داخلی داده شود.\n\nقواعد:\n";
		$p .= '- حداکثر ' . $max . " لینک؛ اگر هیچ‌کدام واقعاً مرتبط نیست، چیزی ننویس\n";
		$p .= "- برای هر لینک یک «عبارت انکر» ۲ تا ۶ کلمه‌ای بده که **عیناً و با همان املا** در متن مطلب آمده باشد (از تیترها نه)\n";
		$p .= "- عبارت انکر باید موضوع مطلبِ مقصد را برساند؛ عبارت‌های کلی مثل «اینجا» یا «این مطلب» ممنوع\n";
		$p .= "- برای هر مطلب مقصد فقط یک لینک، و هر عبارت انکر فقط یک بار\n";
		$p .= "\nخروجی: هر لینک در یک خط به این شکل، بدون هیچ توضیحی:\nشماره | عبارت انکر\n\nمطالب مهم:\n";
		foreach ( $cands as $i => $it ) {
			$p .= ( $i + 1 ) . '. ' . $it['title'] . ( '' !== $it['kw'] ? ' (کلیدواژه: ' . $it['kw'] . ')' : '' ) . "\n";
		}
		return trim( $p );
	}

	/** عبارت انکر را در متن واقعی پیدا می‌کند (نیم‌فاصله/فاصله و ی/ک عربی یکی حساب می‌شوند) و همان املای متن را برمی‌گرداند. */
	public static function locate( string $text, string $anchor ): string {
		$anchor = trim( (string) preg_replace( '/^[\s«"\'*]+|[\s»"\'*.،,:]+$/u', '', $anchor ) );
		if ( mb_strlen( $anchor, 'UTF-8' ) < 3 ) {
			return '';
		}
		$parts = preg_split( '/[\s\x{200c}]+/u', $anchor );
		$re    = array();
		foreach ( $parts as $w ) {
			$re[] = strtr( preg_quote( $w, '/' ), array( 'ی' => '[یي]', 'ي' => '[یي]', 'ک' => '[کك]', 'ك' => '[کك]' ) );
		}
		if ( preg_match( '/' . implode( '[\s\x{200c}]+', $re ) . '/iu', $text, $m ) ) {
			return $m[0];
		}
		return '';
	}

	public static function parse( string $out, array $cands, string $text, int $max ): array {
		$res  = array();
		$used = array();
		$out  = strtr( $out, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) );
		foreach ( preg_split( '/\R+/u', $out ) as $line ) {
			if ( ! preg_match( '/^\s*[-*•]?\s*(\d+)\s*[.)]?\s*[|｜:\-–—]\s*(.+)$/u', (string) $line, $m ) ) {
				continue;
			}
			$it = $cands[ (int) $m[1] - 1 ] ?? null;
			if ( ! $it || isset( $used[ $it['id'] ] ) ) {
				continue;
			}
			$anchor = self::locate( $text, (string) $m[2] );
			if ( '' === $anchor || isset( $used[ 'a' . mb_strtolower( $anchor, 'UTF-8' ) ] ) ) {
				continue;
			}
			$used[ $it['id'] ]                               = true;
			$used[ 'a' . mb_strtolower( $anchor, 'UTF-8' ) ] = true;
			$res[] = array(
				'id'     => $it['id'],
				'title'  => $it['title'],
				'url'    => $it['url'],
				'anchor' => $anchor,
			);
			if ( count( $res ) >= $max ) {
				break;
			}
		}
		return $res;
	}

	/**
	 * @return array|\WP_Error { items[], cached, source{} }
	 */
	public static function suggest( \WP_Post $post, ?string $content, ?string $title, bool $fresh ) {
		$profile = Settings::profile_for( 'title' );
		if ( ! $profile ) {
			return new \WP_Error( 'chk_no_profile', __( 'هنوز هیچ مدلی در مرکز کنترل تعریف نشده است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		$html = null !== $content ? $content : $post->post_content;
		$ctx  = Extractor::build( $post, $html, $title );
		if ( $ctx['chars_sent'] < Generator::MIN_CHARS ) {
			return new \WP_Error( 'chk_short', __( 'متن نوشته هنوز برای این کار کافی نیست؛ اول چند پاراگراف بنویسید.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		$text  = Extractor::clean( $html );
		$cands = self::candidates( $post, $html, $text );
		if ( ! $cands ) {
			return new \WP_Error( 'chk_no_important', __( 'هنوز مطلب مهمی (به‌جز خودِ این مطلب یا آن‌هایی که از قبل لینک شده‌اند) نیست. اول در جعبه ابزارِ چند مطلب، تیک «مطلب مهم» را بزنید.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		$max    = (int) Toolbox::config()['important']['max'];
		$prompt = self::prompt( $cands, $max );
		$key    = 'chk_l' . md5( wp_json_encode( array( $profile['id'] ?? '', $profile['model'] ?? '', $ctx['content'], $prompt ) ) );
		$cached = false;
		$raw    = $fresh ? false : get_transient( $key );
		if ( is_string( $raw ) ) {
			$cached = true;
		} else {
			$gate = Gate::enter( Client::TIMEOUT + 5 );
			if ( is_wp_error( $gate ) ) {
				return $gate;
			}
			try {
				$raw = Client::complete( $profile, Generator::system( $ctx ), $prompt, Generator::block( $ctx ) );
			} finally {
				Gate::leave();
			}
			if ( is_wp_error( $raw ) ) {
				return $raw;
			}
			set_transient( $key, $raw, (int) Settings::get( 'cache_hours' ) * HOUR_IN_SECONDS );
		}
		return array(
			'items'  => self::parse( (string) $raw, $cands, $text, $max ),
			'cached' => $cached,
			'source' => array(
				'model' => $profile['label'] ? $profile['label'] : $profile['model'],
				'usage' => $cached ? null : Client::$usage,
				'sent'  => count( $cands ),
				'total' => count( self::index() ),
			),
		);
	}

	/**
	 * لینک را روی اولین رخدادِ عبارت در متن می‌گذارد؛ نه داخل لینک دیگر، نه در تیترها، کد یا توضیح بلوک‌ها.
	 * همان الگوریتمِ نسخهٔ جاوااسکریپت (ویرایشگرها)؛ ساختار بلوک‌ها دست نمی‌خورد.
	 */
	public static function insert( string $html, string $anchor, string $url ): array {
		$parts = preg_split( '/(<!--.*?-->|<[^>]+>)/su', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$skip  = 0;
		$done  = false;
		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( preg_match( '#^<(a|h[1-6]|code|pre|figcaption|button|script|style)[\s>]#i', $part ) ) {
					++$skip;
				} elseif ( preg_match( '#^</(a|h[1-6]|code|pre|figcaption|button|script|style)>#i', $part ) ) {
					$skip = max( 0, $skip - 1 );
				}
				continue;
			}
			if ( $done || $skip ) {
				continue;
			}
			$pos = mb_strpos( $part, $anchor, 0, 'UTF-8' );
			if ( false !== $pos ) {
				$parts[ $i ] = mb_substr( $part, 0, $pos, 'UTF-8' ) . '<a href="' . esc_url( $url ) . '">' . $anchor . '</a>' . mb_substr( $part, $pos + mb_strlen( $anchor, 'UTF-8' ), null, 'UTF-8' );
				$done        = true;
			}
		}
		return array( implode( '', $parts ), $done );
	}

	/** ثبت مستقیم (فهرست نوشته‌ها). */
	public static function apply( \WP_Post $post, array $items ) {
		$html  = $post->post_content;
		$added = 0;
		foreach ( $items as $it ) {
			$url    = esc_url_raw( (string) ( $it['url'] ?? '' ) );
			$anchor = sanitize_text_field( (string) ( $it['anchor'] ?? '' ) );
			if ( ! $url || '' === $anchor || 0 !== strpos( $url, home_url() ) ) {
				continue;
			}
			list( $html, $ok ) = self::insert( $html, $anchor, $url );
			$added            += $ok ? 1 : 0;
		}
		if ( $added ) {
			$res = wp_update_post(
				wp_slash(
					array(
						'ID'           => $post->ID,
						'post_content' => $html,
					)
				),
				true
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}
		return array( 'added' => $added );
	}
}
