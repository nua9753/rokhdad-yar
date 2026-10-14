<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * تبدیل محتوای نوشته به متنی کوتاه و پرمعنا برای مدل.
 *
 * سه حالت:
 *  - smart : اگر متن در بودجه جا شود کامل می‌رود؛ وگرنه «خلاصهٔ ساختاری» ساخته می‌شود:
 *            مقدمه + همهٔ تیترها + جملهٔ اول بخش‌های میانی + جمع‌بندی.
 *  - intro : فقط ابتدای متن تا سقف بودجه.
 *  - full  : کل متن (با سقف ایمنی).
 *
 * عمداً فیلتر the_content اجرا نمی‌شود: هم کند است، هم خروجی افزونه‌های دیگر را وارد متن می‌کند.
 */
final class Extractor {

	const HARD_LIMIT = 60000;
	const RAW_LIMIT  = 400000; // سقف بایتِ ورودی پیش از هر پردازش، تا نوشته‌های غول‌پیکر هم سبک بمانند.

	public static function build( \WP_Post $post, ?string $content = null, ?string $title = null ): array {
		$mode   = (string) Settings::get( 'source_mode' );
		$budget = (int) Settings::get( 'budget' );
		$text   = self::clean( null !== $content ? $content : $post->post_content );
		$full   = self::len( $text );

		if ( 'full' === $mode ) {
			$out = self::cut( $text, self::HARD_LIMIT );
		} elseif ( 'intro' === $mode ) {
			$out = self::cut( $text, $budget );
		} else {
			$out = $full <= $budget ? $text : self::digest( $text, $budget );
		}

		$ctx = array(
			'content'    => $out,
			'title'      => trim( wp_strip_all_tags( null !== $title ? $title : $post->post_title ) ),
			'excerpt'    => trim( wp_strip_all_tags( $post->post_excerpt ) ),
			'categories' => '',
			'tags'       => '',
			'keyword'    => '',
			'secondary'  => '',
			'keywords'   => '',
			'chars_full' => $full,
			'chars_sent' => self::len( $out ),
			'digested'   => 'smart' === $mode && $full > $budget,
		);

		if ( Settings::get( 'use_meta' ) ) {
			$ctx['categories'] = self::terms( $post->ID, 'category', 5 );
			$ctx['tags']       = self::terms( $post->ID, 'post_tag', 8 );
		}

		// اولین کلیدواژه «اصلی» است و بقیه «فرعی».
		$list = self::split( self::keywords( $post->ID ) );
		if ( $list ) {
			$ctx['keywords']  = implode( '، ', $list );
			$ctx['keyword']   = array_shift( $list );
			$ctx['secondary'] = implode( '، ', $list );
		}

		return $ctx;
	}

	/** HTML/بلوک ← متن ساده؛ تیترها با «## » علامت می‌خورند. */
	public static function clean( string $html ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}
		if ( strlen( $html ) > self::RAW_LIMIT ) {
			$html = mb_strcut( $html, 0, self::RAW_LIMIT, 'UTF-8' );
		}
		// حذف چیزهایی که متن نیستند، همراه با محتوایشان.
		$html = preg_replace( '#<(script|style|pre|code|svg|iframe|form|noscript|figcaption)\b[^>]*>.*?</\1>#is', ' ', $html );
		$html = strip_shortcodes( (string) $html );
		$html = preg_replace( '#\[/?[a-zA-Z][\w-]*(?:\s[^\]]*)?\]#', ' ', $html );

		// تیترها.
		$html = preg_replace_callback(
			'#<h[1-6]\b[^>]*>(.*?)</h[1-6]>#is',
			static function ( $m ) {
				$t = trim( wp_strip_all_tags( $m[1] ) );
				return '' === $t ? "\n" : "\n## " . $t . "\n";
			},
			(string) $html
		);
		// مرز بلوک‌ها ← خط جدید.
		$html = preg_replace( '#<(?:br\s*/?|/p|/div|/li|/tr|/blockquote|/h[1-6])>#i', "\n", (string) $html );

		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[ \t\x{00A0}]+/u', ' ', $text );
		$text = preg_replace( '/ *\n\s*/u', "\n", (string) $text );

		return trim( (string) $text );
	}

	/** خلاصهٔ ساختاری برای متن‌های بلندتر از بودجه. */
	private static function digest( string $text, int $budget ): string {
		$paras = array_values( array_filter( explode( "\n", $text ), 'strlen' ) );
		$n     = count( $paras );
		if ( $n < 4 ) {
			// متن بدون پاراگراف‌بندی: سر و ته.
			$head = (int) ( $budget * 0.7 );
			return self::cut( $text, $head ) . "\n[…]\n" . self::tail( $text, $budget - $head );
		}

		$head_budget = (int) ( $budget * 0.40 );
		$tail_budget = (int) ( $budget * 0.15 );

		// مقدمه.
		$head = array();
		$used = 0;
		$i    = 0;
		while ( $i < $n && $used < $head_budget ) {
			$head[] = $paras[ $i ];
			$used  += self::len( $paras[ $i ] );
			++$i;
		}
		// جمع‌بندی.
		$tail  = array();
		$tused = 0;
		$j     = $n - 1;
		while ( $j >= $i && $tused < $tail_budget ) {
			array_unshift( $tail, $paras[ $j ] );
			$tused += self::len( $paras[ $j ] );
			--$j;
		}

		// میانه: همهٔ تیترها + جملهٔ اول پاراگراف‌ها با نمونه‌گیری یکنواخت.
		$middle = array_slice( $paras, $i, max( 0, $j - $i + 1 ) );
		$left   = max( 0, $budget - $used - $tused );
		$mid    = array();
		if ( $middle && $left > 0 ) {
			$body = 0;
			foreach ( $middle as $p ) {
				if ( 0 !== strpos( $p, '## ' ) ) {
					++$body;
				}
			}
			$per  = 140;
			$step = max( 1, (int) ceil( ( $body * $per ) / $left ) );
			$k    = 0;
			foreach ( $middle as $p ) {
				if ( 0 === strpos( $p, '## ' ) ) {
					$mid[] = self::cut( $p, 120 );
					continue;
				}
				if ( 0 === $k % $step ) {
					$mid[] = self::first_sentence( $p, $per );
				}
				++$k;
			}
		}

		$out = implode( "\n", $head );
		if ( $mid ) {
			$out .= "\n[…]\n" . implode( "\n", $mid );
		}
		if ( $tail ) {
			$out .= "\n[…]\n" . implode( "\n", $tail );
		}
		// سقف نهایی (پاراگراف‌های خیلی بلند ممکن است از بودجه بیرون بزنند).
		return self::cut( $out, (int) ( $budget * 1.15 ) );
	}

	private static function first_sentence( string $p, int $max ): string {
		$parts = preg_split( '/(?<=[.!?؟…。])\s+/u', $p, 2 );
		return self::cut( $parts[0] ?? $p, $max );
	}

	/** برش تا سقف نویسه، ترجیحاً روی مرز کلمه. */
	private static function cut( string $s, int $max ): string {
		if ( $max <= 0 ) {
			return '';
		}
		if ( self::len( $s ) <= $max ) {
			return $s;
		}
		$s   = mb_substr( $s, 0, $max, 'UTF-8' );
		$pos = mb_strrpos( $s, ' ', 0, 'UTF-8' );
		if ( false !== $pos && $pos > $max * 0.8 ) {
			$s = mb_substr( $s, 0, $pos, 'UTF-8' );
		}
		return rtrim( $s ) . '…';
	}

	private static function tail( string $s, int $max ): string {
		return $max <= 0 ? '' : '…' . ltrim( mb_substr( $s, -$max, null, 'UTF-8' ) );
	}

	private static function len( string $s ): int {
		return mb_strlen( $s, 'UTF-8' );
	}

	private static function terms( int $post_id, string $tax, int $limit ): string {
		if ( ! is_object_in_taxonomy( get_post_type( $post_id ), $tax ) ) {
			return '';
		}
		$terms = get_the_terms( $post_id, $tax );
		if ( ! is_array( $terms ) ) {
			return '';
		}
		return implode( '، ', array_slice( wp_list_pluck( $terms, 'name' ), 0, $limit ) );
	}

	const META = '_rokhdadyar_keyword';

	/** کلیدواژه‌های نوشته: اول آنچه در خودِ افزونه وارد شده، وگرنه Yoast یا Rank Math. */
	public static function keywords( int $post_id ): string {
		$own = get_post_meta( $post_id, self::META, true );
		if ( is_string( $own ) && '' !== trim( $own ) ) {
			return implode( '، ', self::split( $own ) );
		}
		return implode( '، ', self::split( self::focus_keyword( $post_id ) ) );
	}

	/**
	 * ذخیرهٔ کلیدواژه‌ای که کاربر تایپ کرده. اگر با کلیدواژهٔ افزونهٔ سئو یکی باشد چیزی ذخیره نمی‌شود،
	 * تا تغییرهای بعدی در Yoast / Rank Math خودبه‌خود دیده شوند.
	 */
	public static function save_keywords( int $post_id, string $raw ): void {
		$new = implode( '، ', self::split( $raw ) );
		$seo = implode( '، ', self::split( self::focus_keyword( $post_id ) ) );
		if ( '' === $new || $new === $seo ) {
			delete_post_meta( $post_id, self::META );
		} else {
			update_post_meta( $post_id, self::META, wp_slash( $new ) );
		}
	}

	/** «الف، ب , ج» ← آرایهٔ یکتا، حداکثر ۵ مورد. */
	private static function split( string $raw ): array {
		$out = array();
		foreach ( (array) preg_split( '/[,،;؛\n]+/u', $raw ) as $k ) {
			$k = mb_substr( trim( sanitize_text_field( (string) $k ) ), 0, 80, 'UTF-8' );
			if ( '' !== $k ) {
				$out[ $k ] = 1;
			}
		}
		return array_slice( array_map( 'strval', array_keys( $out ) ), 0, 5 );
	}

	/** کلیدواژهٔ کانونی از Yoast یا Rank Math، اگر نصب باشند. */
	private static function focus_keyword( int $post_id ): string {
		foreach ( array( '_yoast_wpseo_focuskw', 'rank_math_focus_keyword' ) as $key ) {
			$v = get_post_meta( $post_id, $key, true );
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				return sanitize_text_field( $v );
			}
		}
		return '';
	}
}
