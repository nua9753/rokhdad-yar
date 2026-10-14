<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * پیشنهاد دسته و برچسب از میان دسته‌ها و برچسب‌های موجودِ سایت.
 *
 * برای اینکه نه توکن زیادی مصرف شود و نه وردپرس زیر بار برود:
 *  ۱) فهرست کامل هر طبقه‌بندی فقط یک بار با یک کوئری سبک ساخته و نگه داشته می‌شود
 *     (پیش‌فرض: دسته‌ها ۶ ماه، برچسب‌ها ۲ ماه). تا آن موقع هیچ کوئری دیگری زده نمی‌شود.
 *  ۲) برای هر نوشته، روی همین سرور و بدون هوش مصنوعی، یک «فهرست کوتاه» از موارد مرتبط جدا می‌شود
 *     (مثلاً ۶۰ برچسب از ۲۰۰۰ تا). فقط همین فهرست کوتاه برای مدل فرستاده می‌شود.
 *  ۳) مدل فقط شماره‌ها را برمی‌گرداند.
 */
final class Terms {

	const CATS_MAX = 400; // تا این تعداد، همهٔ دسته‌ها برای مدل فرستاده می‌شوند.

	const TAXES     = array(
		'cats' => 'category',
		'tags' => 'post_tag',
	);
	const INDEX_MAX = 5000; // سقف تعداد موارد هر فهرست.
	const INDEX_VER = 2;    // نسخهٔ ساختار فهرست (۲: دستهٔ مادر هم نگه داشته می‌شود).

	/** طبقه‌بندی‌هایی که هم در تنظیمات روشن‌اند و هم این نوع نوشته دارد. */
	public static function enabled( string $post_type ): array {
		$out = array();
		foreach ( self::TAXES as $key => $tax ) {
			if ( Settings::get( 'terms_' . $key ) && is_object_in_taxonomy( $post_type, $tax ) ) {
				$out[ $key ] = $tax;
			}
		}
		return $out;
	}

	/* ---------- فهرست نگه‌داشته‌شده ---------- */

	private static function option( string $tax ): string {
		return 'rokhdadyar_terms_' . $tax;
	}

	/**
	 * فهرست [شناسه، نام، تعداد استفاده] مرتب بر اساس پراستفاده‌ترین.
	 * از حافظه خوانده می‌شود؛ فقط وقتی مهلتش گذشته باشد دوباره ساخته می‌شود.
	 */
	public static function index( string $tax, bool $rebuild = false ): array {
		$days  = (int) Settings::get( 'category' === $tax ? 'terms_cats_days' : 'terms_tags_days' );
		$saved = $rebuild ? null : get_option( self::option( $tax ) );
		if ( is_array( $saved ) && isset( $saved['t'], $saved['items'] ) && self::INDEX_VER === (int) ( $saved['v'] ?? 1 ) && ( time() - (int) $saved['t'] ) < $days * DAY_IN_SECONDS ) {
			return $saved['items'];
		}

		global $wpdb;
		// یک کوئری سبک و مستقیم؛ بدون ساختن شیء برای هر برچسب.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, tt.count, tt.parent FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s ORDER BY tt.count DESC, t.term_id ASC LIMIT %d",
				$tax,
				self::INDEX_MAX
			),
			ARRAY_N
		);
		$items = array();
		foreach ( (array) $rows as $r ) {
			$name = trim( wp_strip_all_tags( html_entity_decode( (string) $r[1], ENT_QUOTES, 'UTF-8' ) ) );
			if ( '' !== $name ) {
				$items[] = array( (int) $r[0], $name, (int) $r[2], (int) $r[3] );
			}
		}
		update_option(
			self::option( $tax ),
			array(
				'v'     => self::INDEX_VER,
				't'     => time(),
				'items' => $items,
			),
			false
		);
		return $items;
	}

	/** وضعیت فهرست‌ها برای نمایش در مرکز کنترل (بدون ساختن دوباره). */
	public static function status(): array {
		$out = array();
		foreach ( self::TAXES as $key => $tax ) {
			$saved       = get_option( self::option( $tax ) );
			$out[ $key ] = is_array( $saved ) && isset( $saved['t'] )
				? array(
					'count' => count( $saved['items'] ),
					'date'  => wp_date( get_option( 'date_format' ), (int) $saved['t'] ),
				)
				: null;
		}
		return $out;
	}

	private static function add_to_index( string $tax, int $id, string $name ): void {
		$saved = get_option( self::option( $tax ) );
		if ( is_array( $saved ) && isset( $saved['items'] ) ) {
			$saved['items'][] = array( $id, $name, 1, 0 );
			update_option( self::option( $tax ), $saved, false );
		}
	}

	/* ---------- سلسله‌مراتب دسته‌ها ---------- */

	/** @return array [شناسه => [نام، شناسهٔ مادر]] از همان فهرست نگه‌داشته‌شده؛ بدون کوئری. */
	private static function cat_map(): array {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			foreach ( self::index( 'category' ) as $it ) {
				$map[ $it[0] ] = array( $it[1], (int) ( $it[3] ?? 0 ) );
			}
		}
		return $map;
	}

	/** زنجیرهٔ مادرها، از نزدیک‌ترین به دورترین. */
	private static function ancestors( int $id ): array {
		$map = self::cat_map();
		$out = array();
		$cur = $map[ $id ][1] ?? 0;
		while ( $cur && isset( $map[ $cur ] ) && ! in_array( $cur, $out, true ) && count( $out ) < 10 ) {
			$out[] = $cur;
			$cur   = $map[ $cur ][1];
		}
		return $out;
	}

	/** مسیر کامل: «فناوری › هوش مصنوعی». */
	private static function path( int $id ): string {
		$map   = self::cat_map();
		$names = array( $map[ $id ][0] ?? '' );
		foreach ( self::ancestors( $id ) as $a ) {
			array_unshift( $names, $map[ $a ][0] );
		}
		return implode( ' › ', array_filter( $names, 'strlen' ) );
	}

	/** شناسهٔ دسته‌هایی که زیردسته دارند. */
	private static function parents_set(): array {
		$out = array();
		foreach ( self::cat_map() as $row ) {
			if ( $row[1] ) {
				$out[ $row[1] ] = true;
			}
		}
		return $out;
	}

	/* ---------- فهرست کوتاه (روی سرور، بدون هوش مصنوعی) ---------- */

	/** نامی که مدل نوشته را با فهرست تطبیق می‌دهد: اول مسیر کامل، بعد نام خودِ دسته. */
	private static function match_name( array $list, string $name ): int {
		$want = self::norm( $name );
		if ( '  ' === $want ) {
			return 0;
		}
		foreach ( $list as $id => $label ) {
			if ( self::norm( $label ) === $want ) {
				return (int) $id;
			}
		}
		foreach ( $list as $id => $label ) {
			if ( self::norm( (string) preg_replace( '/^.*›\s*/u', '', $label ) ) === $want ) {
				return (int) $id;
			}
		}
		return 0;
	}

	private static function norm( string $s ): string {
		$s = mb_strtolower( $s, 'UTF-8' );
		$s = str_replace( array( "\u{200c}", "\u{200d}", 'ي', 'ك', 'ة', '-', '_' ), array( ' ', ' ', 'ی', 'ک', 'ه', ' ', ' ' ), $s );
		// علامت‌ها هم فاصله می‌شوند تا «هوش مصنوعی.» و «هوش مصنوعی» یکی حساب شوند.
		return ' ' . trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s ) ) . ' ';
	}

	/**
	 * مرتبط‌ترین موارد یک طبقه‌بندی با این نوشته.
	 * امتیاز: تکرار نام در متن + حضور در عنوان + هم‌خوانی با کلیدواژه. موارد فعلیِ نوشته همیشه می‌مانند.
	 *
	 * @return array[] [شناسه => نام]
	 */
	private static function shortlist( string $tax, string $text, string $title, string $keywords, array $current, int $limit ): array {
		$items = self::index( $tax );
		$names = array();
		foreach ( $items as $it ) {
			$names[ $it[0] ] = $it[1];
		}
		if ( count( $items ) <= $limit ) {
			return $names; // فهرست کوچک است (معمولاً دسته‌ها)؛ همه‌اش می‌رود.
		}

		$text  = self::norm( $text );
		$title = self::norm( $title );
		$kw    = self::norm( $keywords );
		$score = array();
		foreach ( $items as $it ) {
			$n = self::norm( $it[1] );
			if ( mb_strlen( $n, 'UTF-8' ) < 4 ) { // با دو فاصلهٔ کناری؛ یعنی نام یک‌حرفی کنار می‌رود.
				continue;
			}
			$s = min( 5, substr_count( $text, $n ) );
			if ( false !== strpos( $title, $n ) ) {
				$s += 3;
			}
			if ( '  ' !== $kw && ( false !== strpos( $kw, $n ) || false !== strpos( $n, $kw ) ) ) {
				$s += 4;
			}
			if ( $s > 0 ) {
				$score[ $it[0] ] = $s;
			}
		}
		arsort( $score );

		$out = array();
		foreach ( $current as $id ) {
			if ( isset( $names[ $id ] ) ) {
				$out[ $id ] = $names[ $id ];
			}
		}
		foreach ( array_keys( $score ) as $id ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$out[ $id ] = $names[ $id ];
		}
		// اگر متن با نام هیچ‌کدام جور نشد، چند مورد پراستفاده هم می‌رود تا مدل دست‌خالی نماند.
		foreach ( $items as $it ) {
			if ( count( $out ) >= min( 15, $limit ) ) {
				break;
			}
			$out[ $it[0] ] = $it[1];
		}
		return $out;
	}

	/* ---------- پیشنهاد ---------- */

	/** فهرست‌های کوتاه + دستور کار. در «ساخت یک‌جا» هم همین استفاده می‌شود. */
	public static function plan( \WP_Post $post, array $ctx, ?string $content, array $current ): ?array {
		$taxes = self::enabled( $post->post_type );
		if ( ! $taxes ) {
			return null;
		}
		$text  = mb_substr( Extractor::clean( null !== $content ? $content : $post->post_content ), 0, 40000, 'UTF-8' );
		$lists = array();
		foreach ( $taxes as $key => $tax ) {
			// دسته‌ها: همهٔ فهرستِ کش‌شده می‌رود (تا ۴۰۰ مورد)، نه فقط آن‌هایی که نامشان در متن آمده.
			// دسته و برچسبِ فعلی نوشته عمداً به مدل گفته نمی‌شود تا همان را برنگرداند.
			$lists[ $key ] = self::shortlist( $tax, $text, $ctx['title'], $ctx['keywords'], array(), 'cats' === $key ? self::CATS_MAX : (int) Settings::get( 'terms_shortlist' ) );
		}
		if ( isset( $lists['cats'] ) ) {
			$default = (int) get_option( 'default_category' );
			if ( count( $lists['cats'] ) > 1 ) {
				unset( $lists['cats'][ $default ] ); // «دسته‌بندی نشده» هیچ‌وقت پیشنهاد نمی‌شود.
			}
			// دسته‌ها با مسیر کامل نشان داده می‌شوند تا مدل جای هر کدام را در درخت ببیند.
			foreach ( $lists['cats'] as $id => $name ) {
				$lists['cats'][ $id ] = self::path( (int) $id );
			}
			asort( $lists['cats'] );
		}
		// شمارهٔ کوتاه (۱، ۲، ۳…) به‌جای شناسهٔ واقعی: مدل‌ها شمارهٔ کوچک را کمتر اشتباه می‌کنند.
		$refs = array();
		foreach ( $lists as $key => $list ) {
			$n = 0;
			foreach ( $list as $id => $name ) {
				$refs[ $key ][ ++$n ] = (int) $id;
			}
		}
		$has_kids = isset( $lists['cats'] ) ? self::parents_set() : array();

		$max_c = (int) Settings::get( 'terms_cats_max' );
		$max_t = (int) Settings::get( 'terms_tags_max' );
		$new   = isset( $taxes['tags'] ) && Settings::get( 'terms_new' );
		$p     = "از فهرست‌های زیر، مناسب‌ترین موارد را برای مطلب بالا انتخاب کن.\n\nقواعد:\n- فقط از همین فهرست‌ها انتخاب کن و فقط شماره‌ها را بنویس (با رقم انگلیسی)\n- فقط بر اساس موضوعِ واقعیِ متن تصمیم بگیر، نه کلمه‌هایی که گذرا آمده‌اند\n";
		$fmt   = '';
		if ( isset( $lists['cats'] ) ) {
			$p   .= '- حداکثر ' . $max_c . " دسته (دست‌کم یکی): دسته‌ای که موضوع اصلیِ مطلب در آن می‌گنجد\n";
			if ( $has_kids ) {
				$p .= "- دسته‌ها درختی‌اند («مادر › فرزند»). همیشه دقیق‌ترین زیردسته را انتخاب کن: دسته‌ای که علامت «+» دارد زیردسته دارد و نباید خودش به‌تنهایی انتخاب شود، مگر اینکه هیچ‌کدام از زیردسته‌هایش به مطلب نخورد. دستهٔ مادر را جدا ننویس؛ خودکار اضافه می‌شود\n";
			}
			$fmt .= "CATS: شماره‌ها با ویرگول\n";
		}
		if ( isset( $lists['tags'] ) ) {
			$p   .= '- حداکثر ' . $max_t . " برچسب: فقط آن‌هایی که مطلب واقعاً درباره‌شان است، نه هر چیزی که یک بار نامش آمده؛ اگر هیچ‌کدام مناسب نیست خالی بگذار\n";
			$fmt .= "TAGS: شماره‌ها با ویرگول\n";
		}
		if ( $new ) {
			$p   .= "- اگر موضوع مهمی از مطلب در فهرست برچسب‌ها نیست، حداکثر ۳ برچسب تازهٔ کوتاه (یک تا سه کلمه) پیشنهاد بده؛ وگرنه خالی بگذار\n";
			$fmt .= "NEW: برچسب‌های تازه با ویرگول\n";
		}
		$p .= "\nخروجی دقیقاً به این شکل، بدون هیچ توضیحی:\n" . $fmt;
		foreach ( $lists as $key => $list ) {
			$p .= "\n" . ( 'cats' === $key ? 'دسته‌ها' : 'برچسب‌ها' ) . ":\n";
			foreach ( $refs[ $key ] as $n => $id ) {
				$p .= $n . '=' . $list[ $id ] . ( 'cats' === $key && isset( $has_kids[ $id ] ) ? ' +' : '' ) . "\n";
			}
		}
		return array(
			'prompt' => trim( $p ),
			'lists'  => $lists,
			'refs'   => $refs,
		);
	}

	/** خروجی مدل ← شناسه‌های معتبر (فقط از همان فهرست کوتاه) + برچسب‌های تازه. */
	public static function parse( string $text, array $lists, array $refs = array() ): array {
		$out  = array(
			'cats' => array(),
			'tags' => array(),
			'new'  => array(),
		);
		// پاسخ واقعی مدل‌ها همیشه تمیز نیست: **CATS**، رقم فارسی/عربی، شماره در خط‌های بعدی، یا نامِ دسته به‌جای شماره.
		$text = strtr( str_replace( array( '*', '`', '#' ), '', $text ), array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9' ) );
		$heads = array(
			'cats' => 'CATS|CATEGORIES|دسته‌ها|دسته ها|دسته',
			'tags' => 'TAGS|برچسب‌ها|برچسب ها|برچسب',
		);
		foreach ( array( 'cats' => (int) Settings::get( 'terms_cats_max' ), 'tags' => (int) Settings::get( 'terms_tags_max' ) ) as $key => $max ) {
			if ( ! isset( $lists[ $key ] ) ) {
				continue;
			}
			// متنِ بعد از سرتیتر تا سرتیتر بعدی (ممکن است چند خط باشد).
			if ( ! preg_match( '/(?:^|\n)\s*(?:' . $heads[ $key ] . ')\s*[:：\-–]\s*(.*?)(?=\n\s*(?:CATS|CATEGORIES|TAGS|NEW|دسته|برچسب)\b|\z)/isu', $text, $m ) ) {
				continue;
			}
			$found = array();
			// هر مورد جدا بررسی می‌شود: «3»، «3=آموزش»، «آموزش» یا «فناوری › هوش مصنوعی».
			foreach ( (array) preg_split( '/[,،;؛\n]+/u', $m[1] ) as $item ) {
				$item = trim( (string) preg_replace( '/^[\s\-–•.]+/u', '', (string) $item ) );
				if ( '' === $item ) {
					continue;
				}
				$id = 0;
				if ( preg_match( '/\p{L}/u', $item ) ) {
					$id = self::match_name( $lists[ $key ], (string) preg_replace( '/^\d+\s*[=:.)\-]\s*/u', '', $item ) );
				}
				if ( ! $id && preg_match( '/^(\d+)/', $item, $num ) ) {
					$n  = (int) $num[1];
					$id = $refs[ $key ][ $n ] ?? ( isset( $lists[ $key ][ $n ] ) ? $n : 0 ); // شمارهٔ کوتاه، یا شناسهٔ واقعی.
				}
				if ( $id ) {
					$found[] = (int) $id;
				}
			}
			foreach ( array_unique( $found ) as $id ) {
				if ( count( $out[ $key ] ) < $max ) {
					$out[ $key ][] = (int) $id;
				}
			}
		}
		if ( isset( $lists['tags'] ) && Settings::get( 'terms_new' ) && preg_match( '/NEW\s*[:：]\s*([^\r\n]*)/iu', $text, $m ) ) {
			$known = array_map( array( __CLASS__, 'norm' ), wp_list_pluck( self::index( 'post_tag' ), 1 ) );
			foreach ( (array) preg_split( '/[,،;؛]+/u', $m[1] ) as $name ) {
				$name = mb_substr( (string) preg_replace( '/^[\s\-—]+|[\s\-—.]+$/u', '', sanitize_text_field( (string) $name ) ), 0, 40, 'UTF-8' );
				if ( mb_strlen( $name, 'UTF-8' ) > 1 && ! in_array( self::norm( $name ), $known, true ) && count( $out['new'] ) < 3 ) {
					$out['new'][] = $name;
				}
			}
		}
		return $out;
	}

	private static function current( int $post_id, string $tax ): array {
		$ids = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function suggest( \WP_Post $post, ?string $content, ?string $title, array $current, bool $fresh ) {
		$profile = Settings::profile_for( 'title' );
		if ( ! $profile ) {
			return new \WP_Error( 'chk_no_profile', __( 'هنوز هیچ مدلی در مرکز کنترل تعریف نشده است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		$ctx = Extractor::build( $post, $content, $title );
		if ( $ctx['chars_sent'] < Generator::MIN_CHARS ) {
			return new \WP_Error( 'chk_short', __( 'متن نوشته هنوز برای این کار کافی نیست؛ اول چند پاراگراف بنویسید.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		$plan = self::plan( $post, $ctx, $content, $current );
		if ( ! $plan ) {
			return new \WP_Error( 'chk_terms_off', __( 'پیشنهاد دسته و برچسب برای این نوع نوشته خاموش است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}

		$cached = true;
		$text   = $fresh ? null : Generator::bundled( $ctx, $profile, 'terms' );
		if ( null !== $text ) {
			$try = self::parse( (string) $text, $plan['lists'], $plan['refs'] );
			if ( ! $try['cats'] && ! $try['tags'] && ! $try['new'] ) {
				// بخش دسته‌ها در «ساخت یک‌جا» قابل‌استفاده نبود؛ این بار جدا ساخته می‌شود.
				Generator::bundle_forget( $ctx, $profile, 'terms' );
				$text = null;
			}
		}
		if ( null === $text ) {
			$cached = false;
			$gate   = Gate::enter( Client::TIMEOUT + 5 );
			if ( is_wp_error( $gate ) ) {
				return $gate;
			}
			try {
				$text = Client::complete( $profile, Generator::system( $ctx ), $plan['prompt'], Generator::block( $ctx ) );
			} finally {
				Gate::leave();
			}
			if ( is_wp_error( $text ) ) {
				return $text;
			}
			Generator::bundle_put( $ctx, $profile, 'terms', $text );
		}

		$picked = self::parse( (string) $text, $plan['lists'], $plan['refs'] );
		if ( ! $picked['cats'] && ! $picked['tags'] && ! $picked['new'] ) {
			// پیش‌تر در این حالت فقط دسته‌های فعلی دوباره نشان داده می‌شد و به نظر می‌رسید مدل همان را پیشنهاد داده است.
			return new \WP_Error( 'chk_terms_empty', __( 'مدل از فهرست دسته‌ها و برچسب‌ها چیزی انتخاب نکرد. «دوباره بساز» را بزنید؛ اگر تکرار شد، مدل دیگری را برای عنوان انتخاب کنید.', 'rokhdadyar' ), array( 'status' => 502 ) );
		}
		$out    = array(
			'cached' => $cached,
			'new'    => $picked['new'],
			'source' => array(
				'model' => $profile['label'] ? $profile['label'] : $profile['model'],
				'usage' => $cached ? null : Client::$usage,
			),
		);
		foreach ( self::TAXES as $key => $tax ) {
			if ( ! isset( $plan['lists'][ $key ] ) ) {
				continue;
			}
			$cur         = array_map( 'intval', $current[ $key ] ?? self::current( $post->ID, $tax ) );
			$names       = $plan['lists'][ $key ];
			$out[ $key ] = array();
			if ( 'cats' === $key && Settings::get( 'terms_parents' ) ) {
				// سلسله‌مراتب: با هر زیردستهٔ انتخاب‌شده، همهٔ دسته‌های مادرش هم تیک می‌خورند.
				foreach ( $picked['cats'] as $id ) {
					$picked['cats'] = array_merge( $picked['cats'], self::ancestors( (int) $id ) );
				}
				$picked['cats'] = array_values( array_unique( $picked['cats'] ) );
			}
			$all = array_unique( array_merge( $cur, $picked[ $key ] ) );
			if ( 'cats' === $key ) {
				usort(
					$all,
					static function ( $a, $b ) {
						return strcmp( self::path( (int) $a ), self::path( (int) $b ) ); // مادر پیش از فرزند.
					}
				);
			}
			foreach ( $all as $id ) {
				$name = 'cats' === $key ? self::path( (int) $id ) : ( $names[ $id ] ?? '' );
				if ( '' === $name ) {
					$term = get_term( $id, $tax );
					$name = $term && ! is_wp_error( $term ) ? $term->name : '';
				}
				if ( '' !== $name ) {
					$out[ $key ][] = array(
						'id'        => $id,
						'name'      => $name,
						'current'   => in_array( $id, $cur, true ),
						'suggested' => in_array( $id, $picked[ $key ], true ),
						// دستهٔ پیش‌فرض وردپرس («دسته‌بندی نشده») وقتی دستهٔ واقعی پیشنهاد شده، بدون تیک نشان داده می‌شود.
						'checked'   => ! ( 'cats' === $key && $picked['cats'] && (int) get_option( 'default_category' ) === (int) $id && ! in_array( $id, $picked['cats'], true ) ),
					);
				}
			}
			$out['source'][ $key . '_sent' ]  = count( $names );
			$out['source'][ $key . '_total' ] = count( self::index( $tax ) );
		}
		return $out;
	}

	/**
	 * ثبت انتخاب کاربر. فهرستِ داده‌شده جایگزین فهرست فعلی می‌شود (موارد فعلی در پنجره تیک‌خورده نشان داده شده‌اند).
	 *
	 * @return array|\WP_Error
	 */
	public static function apply( \WP_Post $post, ?array $cats, ?array $tags, array $new, bool $save ) {
		$taxes   = self::enabled( $post->post_type );
		$created = array();

		if ( $new && isset( $taxes['tags'] ) && Settings::get( 'terms_new' ) ) {
			$tax = get_taxonomy( 'post_tag' );
			if ( ! $tax || ! current_user_can( $tax->cap->edit_terms ) ) {
				return new \WP_Error( 'chk_terms_cap', __( 'اجازهٔ ساخت برچسب تازه را ندارید.', 'rokhdadyar' ), array( 'status' => 403 ) );
			}
			foreach ( array_slice( $new, 0, 3 ) as $name ) {
				$name = mb_substr( sanitize_text_field( (string) $name ), 0, 40, 'UTF-8' );
				if ( '' === $name ) {
					continue;
				}
				$exists = term_exists( $name, 'post_tag' );
				$term   = $exists ? $exists : wp_insert_term( $name, 'post_tag' );
				if ( ! is_wp_error( $term ) ) {
					$created[] = array(
						'id'   => (int) $term['term_id'],
						'name' => $name,
					);
					if ( ! $exists ) {
						self::add_to_index( 'post_tag', (int) $term['term_id'], $name );
					}
				}
			}
		}

		$sets = array(
			'cats' => $cats,
			'tags' => null === $tags ? null : array_merge( $tags, wp_list_pluck( $created, 'id' ) ),
		);
		if ( $save ) {
			foreach ( $taxes as $key => $tax ) {
				if ( null === $sets[ $key ] ) {
					continue;
				}
				$obj = get_taxonomy( $tax );
				if ( ! $obj || ! current_user_can( $obj->cap->assign_terms ) ) {
					return new \WP_Error( 'chk_terms_cap', __( 'اجازهٔ تغییر دسته یا برچسب این نوشته را ندارید.', 'rokhdadyar' ), array( 'status' => 403 ) );
				}
				$ids = array_values( array_unique( array_filter( array_map( 'intval', $sets[ $key ] ) ) ) );
				if ( 'cats' === $key && ! $ids ) {
					continue; // نوشته بدون دسته نمی‌ماند.
				}
				wp_set_object_terms( $post->ID, $ids, $tax, false );
			}
		}
		return array(
			'created' => $created,
			'cats'    => $sets['cats'],
			'tags'    => $sets['tags'],
		);
	}
}
