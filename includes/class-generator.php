<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * ساخت پرامپت نهایی، فراخوانی مدل، پاک‌سازی خروجی و کش.
 *
 * سه لایه برای کم‌کردن مصرف توکن:
 *  ۱) حافظهٔ نتیجه: همان ورودی ← همان نتیجه، بدون هیچ درخواستی.
 *  ۲) ساخت یک‌جا: در اولین درخواستِ یک نوشته، عنوان‌ها و چکیده‌ها (و شرح تصویر) با هم ساخته می‌شوند؛
 *     متن فقط یک بار فرستاده می‌شود و دکمه‌های بعدی از حافظه جواب می‌گیرند.
 *  ۳) کش پیشوند سرویس: متن مطلب همیشه اولِ پیام است، پس درخواست‌های بعدیِ همان نوشته ارزان‌تر حساب می‌شوند.
 */
final class Generator {

	const MIN_CHARS = 80;

	/**
	 * @return array|\WP_Error  { task, items[], labels[], cached, source{} }
	 */
	public static function run( \WP_Post $post, string $task, ?string $content = null, ?string $title = null, bool $fresh = false, string $own = '' ) {
		$profile = Settings::profile_for( $task );
		if ( ! $profile ) {
			return new \WP_Error( 'chk_no_profile', __( 'هنوز هیچ مدلی در مرکز کنترل تعریف نشده است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		if ( '' !== trim( $own ) ) {
			return self::run_own( $task, $profile, trim( $own ), $fresh );
		}

		$ctx = Extractor::build( $post, $content, $title );
		if ( $ctx['chars_sent'] < self::MIN_CHARS ) {
			return new \WP_Error( 'chk_short', __( 'متن نوشته هنوز برای این کار کافی نیست؛ اول چند پاراگراف بنویسید.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}

		$system = self::system( $ctx );
		$block  = self::block( $ctx );
		$spec   = self::spec( $task, $ctx );

		$out = array(
			'task'   => $task,
			'items'  => array(),
			'labels' => array(),
			'cached' => false,
			'source' => array(
				'chars_full' => $ctx['chars_full'],
				'chars_sent' => $ctx['chars_sent'],
				'digested'   => $ctx['digested'],
				'keyword'    => $ctx['keyword'],
				'keywords'   => $ctx['keywords'],
				'model'      => $profile['label'] ? $profile['label'] : $profile['model'],
				'usage'      => null,
			),
		);
		$finish = static function ( array $items, bool $cached ) use ( &$out ) {
			$out['items']           = array_column( $items, 0 );
			$out['labels']          = array_column( $items, 1 );
			$out['cached']          = $cached;
			$out['source']['usage'] = $cached ? null : Client::$usage;
			return $out;
		};

		$ttl  = (int) Settings::get( 'cache_hours' ) * HOUR_IN_SECONDS;
		$key  = 'chk_' . md5( $task . '|' . self::profile_sig( $profile ) . '|' . $system . '|' . $block . '|' . $spec['prompt'] );
		$bkey = self::bundle_key( $ctx, $profile );

		// لایهٔ ۱: حافظه.
		if ( $ttl && ! $fresh ) {
			$hit = get_transient( $key );
			if ( is_array( $hit ) && $hit ) {
				return $finish( $hit, true );
			}
			$bundle = get_transient( $bkey );
			if ( is_array( $bundle ) && ! empty( $bundle[ $task ] ) ) {
				return $finish( $bundle[ $task ], true );
			}
		}

		// یک درخواست باز برای هر کاربر و سقف هم‌زمانی برای کل سایت (Gate).
		$gate = Gate::enter( 2 * Client::TIMEOUT + 5 );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		try {
			// لایهٔ ۲: ساخت یک‌جا (در «دوباره بساز» فقط همان یک کار ساخته می‌شود).
			$partners = ( $ttl && ! $fresh ) ? self::partners( $post, $task, $profile, $ctx, $content ) : array();
			if ( $partners ) {
				$specs = array( $task => $spec ) + $partners;
				$text  = Client::complete( $profile, $system, self::combined_prompt( $specs ), $block );
				if ( is_wp_error( $text ) ) {
					return $text; // خطای شبکه یا مهلت: درخواست دوم فقط انتظار را دو برابر می‌کرد.
				}
				$bundle = self::split_sections( $text, $specs );
				if ( ! empty( $bundle[ $task ] ) ) {
					set_transient( $bkey, $bundle, $ttl );
					return $finish( $bundle[ $task ], false );
				}
				// خروجیِ یک‌جا قابل‌استفاده نبود؛ همان یک کار جدا ساخته می‌شود.
			}

			$text = Client::complete( $profile, $system, $spec['prompt'], $block );
			if ( is_wp_error( $text ) ) {
				return $text;
			}
			$items = self::parse( $text, $task, $spec['count'], $spec['max'] );
			if ( ! $items ) {
				return new \WP_Error( 'chk_empty', __( 'مدل پاسخ قابل‌استفاده‌ای برنگرداند.', 'rokhdadyar' ), array( 'status' => 502 ) );
			}
			if ( $ttl ) {
				set_transient( $key, $items, $ttl );
				// همین نتیجه در بستهٔ این نوشته هم می‌نشیند (کلیدش به عنوانِ فعلی وابسته نیست)،
				// تا پس از انتخاب یک عنوان، چکیده دوباره درخواست نشود.
				$bundle          = get_transient( $bkey );
				$bundle          = is_array( $bundle ) ? $bundle : array();
				$bundle[ $task ] = $items;
				set_transient( $bkey, $bundle, $ttl );
			}
			return $finish( $items, false );
		} finally {
			Gate::leave();
		}
	}

	/* ---------- اجزای پیام ---------- */

	private static function vars( array $ctx ): array {
		$lang = trim( (string) Settings::get( 'language' ) );
		return array(
			'{title}'      => self::or_dash( $ctx['title'] ),
			'{excerpt}'    => self::or_dash( $ctx['excerpt'] ),
			'{categories}' => self::or_dash( $ctx['categories'] ),
			'{tags}'       => self::or_dash( $ctx['tags'] ),
			'{keyword}'    => self::or_dash( $ctx['keyword'] ),
			'{secondary}'  => self::or_dash( $ctx['secondary'] ),
			'{language}'   => '' !== $lang ? $lang : __( 'همان زبان متن مطلب', 'rokhdadyar' ),
			'{site_name}'  => get_bloginfo( 'name' ),
		);
	}

	/** پیام سیستم: برای همهٔ کارهای یک نوشته یکسان است (فقط متغیرهای ثابت در آن جایگزین می‌شوند). */
	public static function system( array $ctx ): string {
		$v = self::vars( $ctx );
		return strtr(
			(string) Settings::get( 'system_prompt' ),
			array(
				'{language}'  => $v['{language}'],
				'{site_name}' => $v['{site_name}'],
			)
		);
	}

	/** بلوک متن مطلب: بخشِ ثابت و بزرگِ پیام که اول می‌آید. */
	public static function block( array $ctx ): string {
		return "متن مطلب:\n\"\"\"\n" . $ctx['content'] . "\n\"\"\"";
	}

	/** {content} از دستور کار برداشته می‌شود، چون متن جدا و اولِ پیام فرستاده می‌شود. */
	public static function strip_content( string $prompt ): string {
		$prompt = preg_replace( '/\s*متن مطلب\s*[:：]\s*"""\s*\{content\}\s*"""\s*$/u', '', $prompt );
		return trim( str_replace( '{content}', __( '(متن مطلب در ابتدای همین پیام آمده است)', 'rokhdadyar' ), (string) $prompt ) );
	}

	/** دستور یک کار (title|excerpt) + تعداد و سقف نویسه. */
	/**
	 * «پرامپت خودم»: فقط همان چند خطی که کاربر نوشته فرستاده می‌شود؛ نه متن نوشته، نه کلیدواژه، نه پرامپت‌های مرکز کنترل.
	 * تنها چیزی که اضافه می‌شود قالب خروجی است (تعداد، سقف طول، هر مورد در یک خط) تا پیشنهادها قابل انتخاب باشند.
	 *
	 * @return array|\WP_Error
	 */
	private static function run_own( string $task, array $profile, string $own, bool $fresh ) {
		$count = max( 1, (int) Settings::get( $task . '_count' ) );
		$max   = 'slug' === $task ? 60 : (int) Settings::get( $task . '_max' );
		$what  = array(
			'title'   => 'عنوان',
			'excerpt' => 'چکیده',
			'slug'    => 'نامک (slug)',
		);
		$system = "فقط خروجی نهایی را بنویس: هر مورد در یک خط جدا، بدون شماره‌گذاری، توضیح، گیومه یا مارک‌داون.";
		$format = sprintf( '%d %s مختلف بنویس، هر کدام حداکثر %d نویسه.', $count, $what[ $task ] ?? $task, $max );
		if ( 'slug' === $task ) {
			$format .= ' ' . ( 'fa' === Settings::get( 'slug_lang' ) ? 'به فارسی و کلمه‌ها با خط تیره.' : 'به انگلیسی، حروف کوچک لاتین و کلمه‌ها با خط تیره.' );
		}
		$user = mb_substr( $own, 0, 4000, 'UTF-8' ) . "\n\n" . $format;

		$out = array(
			'task'   => $task,
			'items'  => array(),
			'labels' => array(),
			'cached' => false,
			'source' => array(
				'own'      => true,
				'model'    => $profile['label'] ? $profile['label'] : $profile['model'],
				'usage'    => null,
				'keyword'  => '',
				'keywords' => '',
			),
		);
		$ttl = (int) Settings::get( 'cache_hours' ) * HOUR_IN_SECONDS;
		$key = 'chk_o' . md5( $task . '|' . self::profile_sig( $profile ) . '|' . $user );
		if ( $ttl && ! $fresh ) {
			$hit = get_transient( $key );
			if ( is_array( $hit ) && $hit ) {
				$out['items']  = array_column( $hit, 0 );
				$out['cached'] = true;
				return $out;
			}
		}
		$gate = Gate::enter( Client::TIMEOUT + 5 );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		try {
			$text = Client::complete( $profile, $system, $user );
		} finally {
			Gate::leave();
		}
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$items = self::parse( $text, $task, $count, $max );
		if ( ! $items ) {
			return new \WP_Error( 'chk_empty', __( 'مدل پاسخ قابل‌استفاده‌ای برنگرداند.', 'rokhdadyar' ), array( 'status' => 502 ) );
		}
		if ( $ttl ) {
			set_transient( $key, $items, $ttl );
		}
		$out['items']           = array_column( $items, 0 );
		$out['source']['usage'] = Client::$usage;
		return $out;
	}

	private static function spec( string $task, array $ctx ): array {
		$count  = (int) Settings::get( $task . '_count' );
		$styles = array();
		if ( 'title' === $task ) {
			foreach ( array_filter( array_map( 'trim', explode( "\n", (string) Settings::get( 'title_styles' ) ) ), 'strlen' ) as $line ) {
				$bits     = preg_split( '/\s*[:：]\s*/u', $line, 2 );
				$styles[] = ( count( $styles ) + 1 ) . '. [' . $bits[0] . ']' . ( isset( $bits[1] ) ? ' — ' . $bits[1] : '' );
			}
			if ( $styles ) {
				$count = count( $styles );
			}
		}
		$max  = 'slug' === $task ? 60 : (int) Settings::get( $task . '_max' );
		$tpl  = (string) Settings::get( $task . '_prompt' );
		$vars = self::vars( $ctx ) + array(
			'{count}'      => (string) $count,
			'{max_chars}'  => (string) $max,
			'{styles}'     => $styles ? implode( "\n", $styles ) : '—',
			'{examples}'   => self::examples(),
			'{max_words}'  => (string) (int) Settings::get( 'slug_words' ),
			'{slug_rules}' => 'fa' === Settings::get( 'slug_lang' )
				? __( 'به فارسی؛ کلمه‌ها با خط تیره (-) از هم جدا شوند؛ بدون نیم‌فاصله، اعراب و علامت', 'rokhdadyar' )
				: __( 'به انگلیسی و با حروف کوچک لاتین؛ کلمه‌ها با خط تیره (-) از هم جدا شوند؛ معادلِ معناییِ انگلیسی را بنویس، نه فینگلیش (مگر برای نام‌های خاص و برند)', 'rokhdadyar' ),
		);
		$prompt = self::strip_content( strtr( $tpl, $vars ) );
		if ( $styles && false === strpos( $tpl, '{styles}' ) ) {
			// پرامپت سفارشیِ قدیمی که متغیر سبک‌ها را ندارد: سبک‌ها خودکار ضمیمه می‌شوند.
			$prompt .= "\n\nهر عنوان را با یکی از این سبک‌ها و به همین ترتیب بنویس، هر خط به شکل «[برچسب سبک] متن عنوان»:\n" . implode( "\n", $styles );
		}
		return array(
			'prompt' => $prompt,
			'count'  => $count,
			'max'    => $max,
		);
	}

	/** نمونه عنوان‌هایی که سردبیر سایت می‌پسندد، به‌صورت بخشی از پرامپت؛ اگر چیزی وارد نشده باشد خالی. */
	private static function examples(): string {
		$lines = array_filter( array_map( 'trim', explode( "\n", (string) Settings::get( 'title_examples' ) ) ), 'strlen' );
		if ( ! $lines ) {
			return '';
		}
		return "نمونه عنوان‌هایی که سردبیر همین سایت می‌پسندد. لحن، ریتم و ساختارشان را الگو بگیر؛ موضوع و کلمه‌هایشان را کپی نکن:\n- "
			. implode( "\n- ", $lines ) . "\n\n";
	}

	private static function profile_sig( array $p ): string {
		return $p['id'] . '|' . $p['model'] . '|' . $p['base_url'];
	}

	/* ---------- ساخت یک‌جا ---------- */

	/** کلید بسته به عنوانِ فعلی وابسته نیست، تا پس از انتخاب یک عنوان هم چکیده‌ها از حافظه بیایند. */
	private static function bundle_key( array $ctx, array $profile ): string {
		$img = Image::config();
		return 'chk_b' . md5(
			self::profile_sig( $profile ) . '|' . self::system( $ctx ) . '|' . self::block( $ctx ) . '|' . $ctx['keywords'] . '|'
			. Settings::get( 'title_prompt' ) . '|' . Settings::get( 'excerpt_prompt' ) . '|' . Settings::get( 'title_styles' ) . '|'
			. Settings::get( 'excerpt_count' ) . '|' . Settings::get( 'title_max' ) . '|' . Settings::get( 'excerpt_max' ) . '|'
			. Settings::get( 'title_examples' ) . '|' . Settings::get( 'terms_cats' ) . Settings::get( 'terms_tags' ) . Settings::get( 'terms_new' ) . '|' . Settings::get( 'terms_cats_max' ) . '|' . Settings::get( 'terms_tags_max' ) . '|' . Settings::get( 'terms_shortlist' ) . '|' . Settings::get( 'slug_prompt' ) . '|' . Settings::get( 'slug_lang' ) . '|' . Settings::get( 'slug_count' ) . '|' . Settings::get( 'slug_words' ) . '|'
			. $img['prompt'] . '|' . $img['style']
		);
	}

	/** کارهای دیگری که می‌شود همراه این کار با همان مدل ساخت. */
	private static function partners( \WP_Post $post, string $task, array $profile, array $ctx, ?string $content = null ): array {
		if ( ! Settings::get( 'bundle' ) ) {
			return array();
		}
		$out  = array();
		$same = static function ( string $t ) use ( $profile ) {
			$p = Settings::profile_for( $t );
			return $p && $p['id'] === $profile['id'];
		};
		foreach ( array( 'title', 'excerpt', 'slug' ) as $other ) {
			if ( $other === $task || ! $same( $other )
				|| ( 'excerpt' === $other && ! post_type_supports( $post->post_type, 'excerpt' ) )
				|| ( 'slug' === $other && ! Settings::get( 'slug_enabled' ) ) ) {
				continue;
			}
			$out[ $other ] = self::spec( $other, $ctx );
		}
		if ( $same( 'title' ) ) {
			$plan = Terms::plan( $post, $ctx, $content, array() );
			if ( $plan ) {
				$out['terms'] = array(
					'prompt' => $plan['prompt'],
					'refs'   => $plan['refs'],
				);
			}
		}
		$img = Image::config();
		if ( Image::ready() && $img['writer'] && $same( 'title' ) && post_type_supports( $post->post_type, 'thumbnail' ) ) {
			$out['image'] = array( 'prompt' => Image::task_prompt( $ctx, $img ) );
		}
		return $out;
	}

	private static function combined_prompt( array $specs ): string {
		$tags = array();
		$body = '';
		foreach ( $specs as $name => $spec ) {
			$tag    = '===' . strtoupper( $name ) . '===';
			$tags[] = $tag;
			$body  .= "\n\n" . $tag . "\n" . $spec['prompt'];
		}
		return 'روی متن بالا ' . count( $specs ) . " کار جدا انجام بده. قواعد هر کار فقط برای همان کار است.\n"
			. 'خروجی هر کار را زیر سرتیتر خودش بنویس؛ سرتیترها را عیناً، هر کدام در یک خط جدا و به همین ترتیب بیاور: ' . implode( ' ', $tags )
			. $body;
	}

	/** خروجی یک‌جا ← نتیجهٔ هر کار. */
	private static function split_sections( string $text, array $specs ): array {
		$out   = array();
		$parts = preg_split( '/^\W*={2,}\s*(TITLE|EXCERPT|SLUG|TERMS|IMAGE)\s*={2,}\W*$/miu', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		for ( $i = 1; $i + 1 < count( $parts ); $i += 2 ) {
			$name = strtolower( $parts[ $i ] );
			$body = trim( $parts[ $i + 1 ] );
			if ( ! isset( $specs[ $name ] ) || '' === $body ) {
				continue;
			}
			if ( 'image' === $name || 'terms' === $name ) {
				$out[ $name ] = $body; // متن خام؛ خودِ آن بخش بعداً می‌خواندش.
			} else {
				$items = self::parse( $body, $name, $specs[ $name ]['count'], $specs[ $name ]['max'] );
				if ( $items ) {
					$out[ $name ] = $items;
				}
			}
		}
		return $out;
	}

	/**
	 * نتیجهٔ یک کار از بستهٔ یک‌جا، اگر هنوز در حافظه باشد (برای شرح تصویر).
	 *
	 * @return mixed|null
	 */
	/** یک کار را از بستهٔ یک نوشته پاک می‌کند (مثلاً وقتی پاسخش قابل‌استفاده نبود). */
	public static function bundle_forget( array $ctx, array $profile, string $task ): void {
		$key    = self::bundle_key( $ctx, $profile );
		$bundle = get_transient( $key );
		if ( is_array( $bundle ) && isset( $bundle[ $task ] ) ) {
			unset( $bundle[ $task ] );
			set_transient( $key, $bundle, (int) Settings::get( 'cache_hours' ) * HOUR_IN_SECONDS );
		}
	}

	public static function bundled( array $ctx, array $profile, string $task ) {
		$bundle = get_transient( self::bundle_key( $ctx, $profile ) );
		return is_array( $bundle ) && ! empty( $bundle[ $task ] ) ? $bundle[ $task ] : null;
	}

	/** گذاشتن نتیجهٔ یک کار در بستهٔ همین نوشته، تا دفعهٔ بعد از حافظه بیاید. */
	public static function bundle_put( array $ctx, array $profile, string $task, $value ): void {
		$ttl = (int) Settings::get( 'cache_hours' ) * HOUR_IN_SECONDS;
		if ( ! $ttl ) {
			return;
		}
		$key             = self::bundle_key( $ctx, $profile );
		$bundle          = get_transient( $key );
		$bundle          = is_array( $bundle ) ? $bundle : array();
		$bundle[ $task ] = $value;
		set_transient( $key, $bundle, $ttl );
	}

	/* ---------- پاک‌سازی خروجی ---------- */

	private static function or_dash( string $v ): string {
		return '' !== $v ? $v : '—';
	}

	/**
	 * هر خط یک پیشنهاد: [متن، برچسب سبک]. عنوان‌های سبک‌دار به ترتیب سبک‌ها می‌مانند؛
	 * در بقیه، پیشنهادهایی که در سقف نویسه جا می‌شوند جلوتر می‌آیند.
	 *
	 * @return array[]
	 */
	private static function parse( string $text, string $task, int $count, int $max ): array {
		$min     = 'excerpt' === $task ? 40 : ( 'slug' === $task ? 3 : 8 ); // خط‌های کوتاه‌تر از این برچسب یا تعارف مدل‌اند، نه پیشنهاد.
		$fit     = array();
		$big     = array();
		$labeled = false;

		$lines = preg_split( '/\R+/u', $text );
		if ( 'excerpt' === $task && 1 === $count ) {
			$lines = array( preg_replace( '/\s+/u', ' ', $text ) );
		}
		foreach ( $lines as $line ) {
			$label = '';
			$line  = preg_replace( '/^\s*(?:[-*•–]|[0-9۰-۹]+[.)\-:])\s+/u', '', (string) $line );
			if ( 'title' === $task && preg_match( '/^\**\[([^\]]{1,24})\]\**\s*[:：\-–—]?\s*(.+)$/u', (string) $line, $m ) ) {
				$label   = sanitize_text_field( $m[1] );
				$line    = $m[2];
				$labeled = true;
			}
			$line = 'slug' === $task ? self::slugify( (string) $line, (string) Settings::get( 'slug_lang' ) ) : self::strip_wrapping( (string) $line );
			$len  = mb_strlen( $line, 'UTF-8' );
			if ( $len < $min || isset( $fit[ $line ] ) || isset( $big[ $line ] ) ) {
				continue;
			}
			if ( $labeled || $len <= $max ) {
				$fit[ $line ] = $label;
			} else {
				$big[ $line ] = $label;
			}
		}
		$out = array();
		foreach ( $fit + $big as $line => $label ) {
			$out[] = array( (string) $line, $label );
		}
		return array_slice( $out, 0, $count );
	}

	/**
	 * هر چیزی که مدل برگردانده ← نامکِ تمیز. در حالت انگلیسی فقط a-z، رقم و خط تیره می‌ماند
	 * (پس خروجی فارسی یا فینگلیشِ ناقص خودبه‌خود کنار گذاشته می‌شود)؛ در حالت فارسی حروف و رقم‌های هر زبانی.
	 */
	public static function slugify( string $s, string $lang ): string {
		$s = trim( wp_strip_all_tags( $s ) );
		$s = preg_replace( '#^\S*://\S*/(?=[^/\s]+/?$)#u', '', $s ); // اگر نشانی کامل داده، فقط بخش آخر.
		$s = mb_strtolower( (string) preg_replace( '#^[\s/`"\'«»*]+|[\s/`"\'«»*]+$#u', '', (string) $s ), 'UTF-8' );
		$s = str_replace( array( "\u{200c}", "\u{200d}", '_', ' ', 'ي', 'ك' ), array( '-', '-', '-', '-', 'ی', 'ک' ), $s );
		$s = 'fa' === $lang ? preg_replace( '/[^\p{L}\p{N}-]+/u', '', $s ) : preg_replace( '/[^a-z0-9-]+/', '', $s );
		return trim( (string) preg_replace( '/-{2,}/', '-', (string) $s ), '-' );
	}

	/** حذف شماره، گلوله، برچسب («گزینه ۱:»)، مارک‌داون و گیومهٔ دور متن. */
	private static function strip_wrapping( string $s ): string {
		$s = trim( $s );
		$s = preg_replace( '/^(?:[-*•–]|[0-9۰-۹]+[.)\-:])\s+/u', '', $s );
		$s = preg_replace( '/^#{1,6}\s+/u', '', (string) $s );
		$s = preg_replace( '/^(?:\*\*)?(?:عنوان|چکیده|گزینه|پیشنهاد|title|excerpt|option)\s*[0-9۰-۹]*\s*(?:\*\*)?\s*[:：]\s*/iu', '', (string) $s );
		$s = preg_replace( '/^(\*\*|__)(.+)\1$/us', '$2', (string) $s );
		$s = preg_replace( '/^["\'«“”‘’`](.+)["\'»“”‘’`]$/us', '$1', trim( (string) $s ) );
		return sanitize_text_field( (string) $s );
	}
}
