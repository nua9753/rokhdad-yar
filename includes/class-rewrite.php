<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * بازنویسی یک پاراگراف در دلِ ویرایشگر: کوتاه‌تر، بلندتر، یا با دستور خودِ کاربر.
 * فقط همان پاراگراف (و عنوان نوشته برای فهم موضوع) فرستاده می‌شود، نه کل متن؛ مدل دو نسخه برمی‌گرداند.
 * لینک‌ها و قالب‌بندیِ درون‌خطی (پررنگ، کج) حفظ می‌شوند و خروجی فقط با همین برچسب‌های امن پذیرفته می‌شود.
 */
final class Rewrite {

	const MAX_IN = 6000;

	private static function allowed(): array {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'br'     => array(),
			'mark'   => array(),
			'code'   => array(),
			'sup'    => array(),
			'sub'    => array(),
		);
	}

	/**
	 * @param int $variant ۰ = هر دو نسخه در یک پاسخ؛ ۱ = فقط نسخهٔ نزدیک به متن؛ ۲ = فقط نسخهٔ با بیان تازه‌تر.
	 */
	public static function prompt( string $mode, string $html, string $title, string $own, int $variant = 0 ): string {
		$len = mb_strlen( wp_strip_all_tags( $html ), 'UTF-8' );
		switch ( $mode ) {
			case 'short':
				$task = sprintf( 'این پاراگراف را کوتاه‌تر و فشرده‌تر بازنویسی کن؛ حدود %d تا %d نویسه. نکته‌های اصلی، عددها، نام‌ها و کلیدواژه‌ها بمانند؛ فقط حشو و تکرار حذف شود.', (int) round( $len * 0.45 ), (int) round( $len * 0.65 ) );
				break;
			case 'long':
				$task = sprintf( 'این پاراگراف را بلندتر و کامل‌تر بازنویسی کن؛ حدود %d تا %d نویسه. همان نکته‌ها را روشن‌تر و با توضیح، مثال یا پیوند منطقیِ بیشتر بنویس. هیچ واقعیت، عدد، نام یا ادعای تازه‌ای که در خودِ پاراگراف نیست از خودت اضافه نکن.', (int) round( $len * 1.5 ), (int) round( $len * 2 ) );
				break;
			default:
				$task = "این پاراگراف را طبق این دستورِ نویسنده بازنویسی کن:\n" . mb_substr( $own, 0, 1000, 'UTF-8' );
		}
		return $task . "\n\nقواعد:\n"
			. "- به همان زبان و لحن پاراگراف؛ روان، طبیعی و سئوپسند\n"
			. "- لینک‌ها (<a href=\"…\">) را با همان نشانی نگه دار و روی متنِ معادل بگذار؛ پررنگ و کج‌ها هم بمانند\n"
			. "- هیچ برچسب HTML دیگری اضافه نکن؛ تیتر، فهرست یا پاراگراف جدید نساز\n"
			. self::shape( $variant )
			. ( '' !== $title ? "\nعنوان نوشته (فقط برای فهم موضوع): " . $title . "\n" : '' )
			. "\nپاراگراف:\n\"\"\"\n" . $html . "\n\"\"\"";
	}

	private static function shape( int $variant ): string {
		if ( 1 === $variant ) {
			return "- فقط یک نسخه بنویس، نزدیک به ساختار و واژه‌های خودِ پاراگراف؛ بدون عنوان یا توضیح\n";
		}
		if ( 2 === $variant ) {
			return "- فقط یک نسخه بنویس، با جمله‌بندی و ترتیبِ تازه‌تر و گیراتر از متن اصلی؛ بدون عنوان یا توضیح\n";
		}
		return "- دو نسخهٔ متفاوت بنویس و بینشان فقط یک خط با === بگذار؛ بدون شماره، عنوان یا توضیح\n";
	}

	/** خروجی مدل ← دو نسخهٔ تمیز. */
	public static function parse( string $out ): array {
		$out   = (string) preg_replace( '#<think(?:ing)?>.*?</think(?:ing)?>#is', '', $out );
		$parts = preg_split( '/^\s*(?:={3,}|-{3,}|\*{3,})\s*$/mu', trim( $out ) );
		if ( count( $parts ) < 2 ) {
			// بعضی مدل‌ها به‌جای جداکننده شماره می‌گذارند.
			$parts = preg_split( '/^\s*(?:نسخه|گزینه|version|option)?\s*[0-9۱۲]+\s*[.):：\-]\s*/miu', trim( $out ), -1, PREG_SPLIT_NO_EMPTY );
		}
		$items = array();
		foreach ( (array) $parts as $p ) {
			$p = trim( (string) $p );
			$p = (string) preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', $p );
			// برچسبِ «نسخه ۱:» فقط وقتی حذف می‌شود که شماره و دونقطه داشته باشد؛ جمله‌ای که با «نسخهٔ جدید…» شروع شود سالم می‌ماند.
			$p = (string) preg_replace( '/^(?:\*\*)?\s*(?:نسخهٔ?|گزینهٔ?|version|option)\s*(?:[0-9۰-۹]+|اول|دوم)\s*(?:\*\*)?\s*[:：]\s*(?:\*\*)?\s*/iu', '', $p );
			$p = (string) preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $p );
			$p = str_replace( '**', '', $p );
			$p = (string) preg_replace( '/^"{3}|"{3}$/u', '', $p );
			$p = (string) preg_replace( '#^<p[^>]*>|</p>$#i', '', trim( $p ) );
			$p = trim( (string) preg_replace( '/\s*\R\s*|[ \t]{2,}/u', ' ', $p ) );
			$p = trim( wp_kses( $p, self::allowed() ) );
			if ( mb_strlen( wp_strip_all_tags( $p ), 'UTF-8' ) >= 10 && ! in_array( $p, $items, true ) ) {
				$items[] = $p;
			}
			if ( count( $items ) >= 2 ) {
				break;
			}
		}
		return $items;
	}

	/**
	 * @return array|\WP_Error { items[], cached, source{} }
	 */
	public static function run( \WP_Post $post, string $html, string $mode, string $own, string $title, bool $fresh ) {
		$mode = in_array( $mode, array( 'short', 'long', 'own' ), true ) ? $mode : 'short';
		$html = trim( wp_kses( $html, self::allowed() ) );
		if ( mb_strlen( wp_strip_all_tags( $html ), 'UTF-8' ) < 20 ) {
			return new \WP_Error( 'chk_para_short', __( 'این پاراگراف برای بازنویسی خیلی کوتاه است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $html, 'UTF-8' ) > self::MAX_IN ) {
			return new \WP_Error( 'chk_para_long', __( 'این پاراگراف خیلی بلند است؛ آن را به چند پاراگراف کوتاه‌تر بشکنید.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		if ( 'own' === $mode && '' === trim( $own ) ) {
			return new \WP_Error( 'chk_para_own', __( 'دستور بازنویسی را بنویسید.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		$profile = Settings::profile_for( 'excerpt' );
		if ( ! $profile ) {
			return new \WP_Error( 'chk_no_profile', __( 'هنوز هیچ مدلی در مرکز کنترل تعریف نشده است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		$title  = '' !== $title ? $title : (string) $post->post_title;
		$system = 'تو ویراستار حرفه‌ای یک رسانهٔ آنلاین هستی. فقط خروجی نهایی را بنویس.';
		$key    = 'chk_r' . md5( ( $profile['id'] ?? '' ) . '|' . ( $profile['model'] ?? '' ) . '|2|' . self::prompt( $mode, $html, $title, $own ) );
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
				// دو نسخه هم‌زمان و هر کدام در یک درخواستِ کوتاه‌تر: انتظار برابر یک پاسخِ تک‌نسخه‌ای است، نه دوتایی.
				$outs = Client::complete_many(
					$profile,
					$system,
					array(
						self::prompt( $mode, $html, $title, $own, 1 ),
						self::prompt( $mode, $html, $title, $own, 2 ),
					)
				);
			} finally {
				Gate::leave();
			}
			$got = array();
			$err = null;
			foreach ( $outs as $o ) {
				if ( is_wp_error( $o ) ) {
					$err = $o;
					continue;
				}
				$one = self::parse( (string) $o );
				if ( $one ) {
					$got[] = $one[0];
				}
			}
			if ( ! $got ) {
				return $err ? $err : new \WP_Error( 'chk_empty', __( 'مدل پاسخ قابل‌استفاده‌ای برنگرداند؛ دوباره امتحان کنید.', 'rokhdadyar' ), array( 'status' => 502 ) );
			}
			$raw = implode( "\n===\n", array_unique( $got ) );
		}
		$items = self::parse( (string) $raw );
		if ( ! $items ) {
			return new \WP_Error( 'chk_empty', __( 'مدل پاسخ قابل‌استفاده‌ای برنگرداند؛ دوباره امتحان کنید.', 'rokhdadyar' ), array( 'status' => 502 ) );
		}
		if ( ! $cached ) {
			set_transient( $key, $raw, (int) Settings::get( 'cache_hours' ) * HOUR_IN_SECONDS );
		}
		return array(
			'items'  => $items,
			'cached' => $cached,
			'source' => array(
				'model' => $profile['label'] ? $profile['label'] : $profile['model'],
				'usage' => $cached ? null : Client::$usage,
			),
		);
	}
}
