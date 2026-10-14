<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * تصویر شاخص با هوش مصنوعی — منطقی جدا از عنوان و چکیده:
 *
 *  ۱) مدل متنی از روی مطلب یک «شرح تصویر» انگلیسی و یک متن جایگزین (alt) می‌نویسد (اختیاری).
 *  ۲) API تصویر، عکس را می‌سازد.
 *  ۳) عکس کوچک و WebP می‌شود و به کتابخانهٔ رسانه می‌رود (هنوز به نوشته وصل نیست).
 *  ۴) کاربر پیش‌نمایش را می‌بیند؛ با تأیید، تصویر شاخص می‌شود و با رد، فایل پاک می‌شود.
 */
final class Image {

	const PENDING = '_rokhdadyar_img_pending';

	const FLAG     = '_rokhdadyar_generated';
	const TIMEOUT  = 120;
	const MAX_BODY = 25165824; // ۲۴ مگابایت؛ پاسخ تصویر به‌صورت base64 می‌آید.
	const FORMATS  = array( 'openai', 'gemini' );
	const COUNTER  = 'rokhdadyar_img';

	/**
	 * سطح‌های وضوح: [اندازه در قالب OpenAI، اندازه در Gemini]. هرچه بالاتر، گران‌تر.
	 * فایل نهایی به اندازهٔ تنظیم‌شده (پیش‌فرض ۱۲۰۰×۸۰۰) بریده می‌شود، پس بالاتر از «استاندارد» معمولاً فقط هزینه است.
	 */
	const TIERS = array(
		'eco' => array( '1024x1024', '1K' ),
		'std' => array( '1536x1024', '1K' ),
		'2k'  => array( '2048x1360', '2K' ),
		'4k'  => array( '3840x2160', '4K' ),
	);
	const QUALITIES = array( 'low', 'medium', 'high' );

	public static function defaults(): array {
		return array(
			'enabled'   => 0,
			'format'    => 'openai',
			'base_url'  => '',
			'api_key'   => '',
			'model'     => '',
			'tier'      => 'std',     // eco | std | 2k | 4k | custom
			'size'      => '1536x1024', // فقط در حالت custom
			'quality'   => 'medium',  // '' = پیش‌فرض مدل (معمولاً گران‌ترین) | low | medium | high
			'daily_cap' => 0,         // سقف تعداد تصویر در روز برای کل سایت؛ ۰ = بدون سقف
			'style'     => 'Clean modern editorial illustration, soft natural lighting, cohesive color palette, uncluttered composition',
			'writer'    => 1,
			'prompt'    => self::default_prompt(),
			'width'     => 1200, // اندازهٔ نهایی فایل؛ ۱۲۰۰ پیکسل عرض حداقلِ لازم برای Google Discover است.
			'height'    => 800,
			'webp'      => 1,
			'filename'  => '{slug}-{date}', // الگوی نام فایل؛ تاریخ و ساعت جلوی نام تکراری را می‌گیرد.
		);
	}

	public static function default_prompt(): string {
		return "بر اساس مطلب زیر، برای «تصویر شاخص» آن دقیقاً دو خط بنویس:\n"
			. "PROMPT: یک شرح انگلیسی ۴۰ تا ۷۰ کلمه‌ای برای مدل تصویرساز: سوژهٔ اصلی و مشخصِ همین مطلب، صحنه، زاویهٔ دید و نور؛ قاب افقی؛ بدون هیچ نوشته، حروف، عدد، لوگو یا واترمارک داخل تصویر. سبک: {style}\n"
			. "ALT: متن جایگزین به زبان مطلب؛ توصیف واقعی همان تصویر در حداکثر ۱۱۰ نویسه که کلیدواژهٔ کانونی را طبیعی در خود داشته باشد.\n\n"
			. "عنوان: {title}\n"
			. "کلیدواژهٔ کانونی: {keyword}\n\n"
			. "متن مطلب:\n\"\"\"\n{content}\n\"\"\"";
	}

	public static function config(): array {
		$saved = Settings::get( 'image' );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function ready(): bool {
		$c = self::config();
		return $c['enabled'] && '' !== $c['api_key'] && '' !== $c['model'];
	}

	public static function sanitize( array $raw, array $old ): array {
		$d      = self::defaults();
		$format = sanitize_key( $raw['format'] ?? '' );
		$key    = trim( (string) ( $raw['api_key'] ?? '' ) );
		$size   = preg_replace( '/[^0-9x:a-z]/', '', strtolower( (string) ( $raw['size'] ?? '' ) ) );
		$prompt = trim( str_replace( "\r\n", "\n", sanitize_textarea_field( (string) ( $raw['prompt'] ?? '' ) ) ) );
		$style  = sanitize_text_field( (string) ( $raw['style'] ?? '' ) );

		return array(
			'enabled'   => empty( $raw['enabled'] ) ? 0 : 1,
			'format'    => in_array( $format, self::FORMATS, true ) ? $format : 'openai',
			'base_url'  => untrailingslashit( esc_url_raw( trim( (string) ( $raw['base_url'] ?? '' ) ), array( 'http', 'https' ) ) ),
			'api_key'   => '' !== $key ? Settings::encrypt( $key ) : (string) ( $old['api_key'] ?? '' ),
			'model'     => sanitize_text_field( (string) ( $raw['model'] ?? '' ) ),
			'tier'      => isset( self::TIERS[ $raw['tier'] ?? '' ] ) || 'custom' === ( $raw['tier'] ?? '' ) ? (string) $raw['tier'] : 'std',
			'size'      => $size,
			'quality'   => in_array( $raw['quality'] ?? '', self::QUALITIES, true ) ? (string) $raw['quality'] : '',
			'daily_cap' => max( 0, min( 100000, (int) ( $raw['daily_cap'] ?? 0 ) ) ),
			'style'     => '' !== $style ? $style : $d['style'],
			'writer'    => empty( $raw['writer'] ) ? 0 : 1,
			'prompt'    => '' !== $prompt ? $prompt : $d['prompt'],
			'width'     => max( 600, min( 2400, (int) ( $raw['width'] ?? $d['width'] ) ) ),
			'height'    => max( 315, min( 2400, (int) ( $raw['height'] ?? $d['height'] ) ) ),
			'webp'      => empty( $raw['webp'] ) ? 0 : 1,
			'filename'  => '' !== trim( (string) ( $raw['filename'] ?? '' ) ) ? mb_substr( sanitize_text_field( (string) $raw['filename'] ), 0, 120, 'UTF-8' ) : $d['filename'],
		);
	}

	/**
	 * @return array|\WP_Error { id, url, alt, prompt }
	 */
	public static function generate( \WP_Post $post, ?string $content = null, ?string $title = null, string $custom = '' ) {
		$cfg = self::config();
		if ( ! self::ready() ) {
			return new \WP_Error( 'chk_img_off', __( 'ساخت تصویر شاخص در مرکز کنترل فعال یا کامل تنظیم نشده است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		$ctx = Extractor::build( $post, $content, $title );
		$custom = mb_substr( trim( $custom ), 0, 2000, 'UTF-8' );
		if ( '' === $custom && '' === $ctx['title'] && $ctx['chars_sent'] < Generator::MIN_CHARS ) {
			return new \WP_Error( 'chk_short', __( 'برای ساخت تصویر دست‌کم عنوان یا چند پاراگراف متن لازم است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}

		$used = self::used_today();
		if ( $cfg['daily_cap'] && $used >= $cfg['daily_cap'] ) {
			return new \WP_Error(
				'chk_img_cap',
				/* translators: %d: daily cap */
				sprintf( __( 'سقف امروزِ ساخت تصویر پر شده است (%d تصویر). فردا دوباره می‌شود ساخت، یا سقف را در مرکز کنترل بالا ببرید.', 'rokhdadyar' ), $cfg['daily_cap'] ),
				array( 'status' => 429 )
			);
		}

		if ( Settings::key_lost( (string) $cfg['api_key'] ) ) {
			return Settings::key_lost_error( __( 'تصویر', 'rokhdadyar' ) );
		}
		$gate = Gate::enter( self::TIMEOUT + Client::TIMEOUT + 10 );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		try {
		// دو حالت: پرامپتِ دستیِ کاربر عیناً فرستاده می‌شود (بدون مدل متنی و بدون افزودن سبک)؛ وگرنه شرح تصویر خودکار از روی متن.
		$brief = '' !== $custom
			? array(
				'prompt' => $custom,
				'alt'    => '' !== $ctx['title'] ? $ctx['title'] : $ctx['keyword'],
			)
			: self::brief( $ctx, $cfg );
		$key   = Settings::decrypt( $cfg['api_key'] );
		$bytes = 'gemini' === $cfg['format'] ? self::call_gemini( $cfg, $key, $brief['prompt'] ) : self::call_openai( $cfg, $key, $brief['prompt'] );
		if ( ! is_wp_error( $bytes ) ) {
			// سرویس تصویر را ساخته و هزینه‌اش حساب شده؛ همین‌جا شمرده می‌شود، حتی اگر ذخیره شکست بخورد.
			update_option(
				self::COUNTER,
				array(
					'd' => ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' ),
					'n' => ++$used,
				),
				false
			);
			Usage::add( (string) $cfg['model'], null, 1 );
		}
		$id    = is_wp_error( $bytes ) ? $bytes : self::store( $post, $bytes, $brief['alt'], $cfg );
		} finally {
			Gate::leave();
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		// تا ثبت یا دور انداخته شود، روی نوشته می‌ماند؛ بستن پنجره یا رفرش صفحه آن را از دست نمی‌دهد.
		update_post_meta(
			$post->ID,
			self::PENDING,
			array(
				'id'     => (int) $id,
				'alt'    => $brief['alt'],
				'prompt' => $brief['prompt'],
				'custom' => '' !== $custom,
				't'      => time(),
			)
		);
		if ( ! wp_next_scheduled( 'rokhdadyar_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'rokhdadyar_cleanup' );
		}
		$src = wp_get_attachment_image_url( $id, 'medium_large' );
		return array(
			'id'     => $id,
			'url'    => $src ? $src : wp_get_attachment_url( $id ),
			'alt'    => $brief['alt'],
			'prompt' => $brief['prompt'],
			'custom' => '' !== $custom,
			'quota'  => $cfg['daily_cap']
				/* translators: 1: used today, 2: daily cap */
				? sprintf( __( 'امروز %1$d از %2$d', 'rokhdadyar' ), $used, $cfg['daily_cap'] )
				/* translators: %d: used today */
				: sprintf( __( 'امروز %d تصویر', 'rokhdadyar' ), $used ),
		);
	}

	/** تعداد تصویرهایی که امروز (به وقت سایت) از سرویس گرفته شده. */
	public static function used_today(): int {
		$c = get_option( self::COUNTER );
		return is_array( $c ) && ( $c['d'] ?? '' ) === ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' ) ? (int) $c['n'] : 0;
	}

	/** نزدیک‌ترین نسبتِ پشتیبانی‌شده به اندازهٔ نهایی (برای Gemini). */
	private static function aspect( array $c ): string {
		$r    = $c['width'] / max( 1, $c['height'] );
		$best = '3:2';
		$diff = 9;
		foreach ( array( '1:1' => 1, '4:3' => 4 / 3, '3:2' => 1.5, '16:9' => 16 / 9, '21:9' => 21 / 9 ) as $name => $v ) {
			if ( abs( $r - $v ) < $diff ) {
				$diff = abs( $r - $v );
				$best = $name;
			}
		}
		return $best;
	}

	/** شرح تصویر + متن جایگزین. اگر مدل متنی در کار نباشد یا خطا بدهد، از عنوان ساخته می‌شود. */
	private static function brief( array $ctx, array $cfg ): array {
		$subject  = '' !== $ctx['title'] ? $ctx['title'] : mb_substr( $ctx['content'], 0, 200, 'UTF-8' );
		$fallback = array(
			'prompt' => 'A wide landscape featured image for an article titled "' . $subject . '"'
				. ( '' !== $ctx['keyword'] ? ' (topic: ' . $ctx['keyword'] . ')' : '' )
				. '. ' . $cfg['style'] . '. No text, letters, numbers, logos or watermarks anywhere in the image.',
			'alt'    => $subject,
		);
		$profile = $cfg['writer'] ? Settings::profile_for( 'title' ) : null;
		if ( ! $profile || $ctx['chars_sent'] < Generator::MIN_CHARS ) {
			return $fallback;
		}

		// اگر همراه عنوان و چکیده «یک‌جا» ساخته شده باشد، هیچ توکنی مصرف نمی‌شود.
		$text = Generator::bundled( $ctx, $profile, 'image' );
		if ( null === $text ) {
			// همان پیشوندِ عنوان و چکیده (سیستم + متن مطلب) تا کش سرویس به کار بیاید.
			$text = Client::complete( $profile, Generator::system( $ctx ), self::task_prompt( $ctx, $cfg ), Generator::block( $ctx ) );
		}
		return is_wp_error( $text ) ? $fallback : self::parse_brief( (string) $text, $fallback );
	}

	/** دستور نوشتن شرح تصویر، بدون خودِ متن مطلب (متن جدا و اولِ پیام فرستاده می‌شود). */
	public static function task_prompt( array $ctx, array $cfg ): string {
		return Generator::strip_content(
			strtr(
				$cfg['prompt'],
				array(
					'{style}'   => $cfg['style'],
					'{title}'   => '' !== $ctx['title'] ? $ctx['title'] : '—',
					'{keyword}' => '' !== $ctx['keyword'] ? $ctx['keyword'] : '—',
				)
			)
		);
	}

	public static function parse_brief( string $text, array $fallback ): array {
		$out = $fallback;
		if ( preg_match( '/PROMPT\s*[:：]\s*(.+?)(?=\R\s*ALT\s*[:：]|\z)/isu', $text, $m ) ) {
			$out['prompt'] = trim( preg_replace( '/\s+/u', ' ', $m[1] ) ) . ' No text, letters, logos or watermarks in the image.';
		}
		if ( preg_match( '/ALT\s*[:：]\s*(.+)/iu', $text, $m ) ) {
			$alt = sanitize_text_field( (string) preg_replace( '/^[\s"\'«»]+|[\s"\'«»]+$/u', '', $m[1] ) ); // trim() بایت‌محور است و حرف آخر فارسی را خراب می‌کند.
			if ( '' !== $alt ) {
				$out['alt'] = mb_substr( $alt, 0, 160, 'UTF-8' );
			}
		}
		return $out;
	}

	/**
	 * هر سرویس سازگار با ‎/images/generations‎ (OpenAI، xAI و درگاه‌ها).
	 *
	 * @return string|\WP_Error بایت‌های تصویر.
	 */
	private static function call_openai( array $c, string $key, string $prompt ) {
		$base = $c['base_url'] ? $c['base_url'] : 'https://api.openai.com/v1';
		$url  = preg_match( '#/images/generations$#', $base ) ? $base : $base . '/images/generations';
		$body = array(
			'model'  => $c['model'],
			'prompt' => $prompt,
			'n'      => 1,
		);
		$size = 'custom' === $c['tier'] ? $c['size'] : self::TIERS[ $c['tier'] ][0];
		if ( '' !== $size ) {
			$body['size'] = $size;
		}
		if ( '' !== $c['quality'] ) {
			$body['quality'] = $c['quality'];
		}
		$head = array( 'Authorization' => 'Bearer ' . $key );
		$json = Client::post( $url, $head, $body, self::TIMEOUT, self::MAX_BODY );

		// اگر مدل این اندازه یا کیفیت را نپذیرفت، همان گزینه برداشته و دوباره تلاش می‌شود (پیش‌فرض خودِ مدل).
		foreach ( array( 'quality', 'size' ) as $opt ) {
			if ( is_wp_error( $json ) && isset( $body[ $opt ] ) && 400 === (int) ( $json->get_error_data()['upstream'] ?? 0 ) && false !== stripos( $json->get_error_message(), $opt ) ) {
				unset( $body[ $opt ] );
				$json = Client::post( $url, $head, $body, self::TIMEOUT, self::MAX_BODY );
			}
		}
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$item = $json['data'][0] ?? array();
		if ( ! empty( $item['b64_json'] ) ) {
			return self::decode( (string) $item['b64_json'] );
		}
		if ( ! empty( $item['url'] ) ) {
			return self::download( (string) $item['url'] );
		}
		return new \WP_Error( 'chk_img_empty', __( 'سرویس تصویر پاسخی بدون تصویر برگرداند.', 'rokhdadyar' ), array( 'status' => 502 ) );
	}

	/** مدل‌های تصویرساز Gemini از راه generateContent. */
	private static function call_gemini( array $c, string $key, string $prompt ) {
		$base = $c['base_url'] ? $c['base_url'] : 'https://generativelanguage.googleapis.com/v1beta';
		$url  = $base . '/models/' . rawurlencode( $c['model'] ) . ':generateContent';
		$head = array( 'x-goog-api-key' => $key );
		$body = array(
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
			'generationConfig' => array(
				'responseModalities' => array( 'TEXT', 'IMAGE' ),
				'imageConfig'        => array( 'aspectRatio' => self::aspect( $c ) ),
			),
		);
		if ( 'custom' !== $c['tier'] ) {
			$body['generationConfig']['imageConfig']['imageSize'] = self::TIERS[ $c['tier'] ][1];
		}
		$json = Client::post( $url, $head, $body, self::TIMEOUT, self::MAX_BODY );
		// مدل‌هایی که تنظیم اندازه را نمی‌شناسند: اول فقط نسبت تصویر، بعد بدون هیچ تنظیمی.
		foreach ( array( 'imageSize', 'imageConfig' ) as $opt ) {
			if ( is_wp_error( $json ) && 400 === (int) ( $json->get_error_data()['upstream'] ?? 0 ) ) {
				if ( 'imageSize' === $opt ) {
					unset( $body['generationConfig']['imageConfig']['imageSize'] );
				} else {
					unset( $body['generationConfig']['imageConfig'] );
				}
				$json = Client::post( $url, $head, $body, self::TIMEOUT, self::MAX_BODY );
			}
		}
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		foreach ( (array) ( $json['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			$data = $part['inlineData']['data'] ?? ( $part['inline_data']['data'] ?? '' );
			if ( '' !== $data ) {
				return self::decode( (string) $data );
			}
		}
		return new \WP_Error( 'chk_img_empty', __( 'سرویس تصویر پاسخی بدون تصویر برگرداند.', 'rokhdadyar' ), array( 'status' => 502 ) );
	}

	private static function decode( string $b64 ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$bin = base64_decode( $b64, true );
		return false === $bin || '' === $bin
			? new \WP_Error( 'chk_img_bad', __( 'دادهٔ تصویر خراب است.', 'rokhdadyar' ), array( 'status' => 502 ) )
			: $bin;
	}

	/** نشانی تصویر از پاسخ یک سرویس بیرونی می‌آید؛ با نسخهٔ «ایمن» دانلود می‌شود تا به شبکهٔ داخلی سرور راه نبرد. */
	private static function download( string $url ) {
		$res = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 60,
				'limit_response_size' => self::MAX_BODY,
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new \WP_Error( 'chk_img_dl', __( 'دانلود تصویر ساخته‌شده ناموفق بود.', 'rokhdadyar' ), array( 'status' => 502 ) );
		}
		return (string) wp_remote_retrieve_body( $res );
	}

	/**
	 * کوچک‌سازی، تبدیل به WebP و ثبت در کتابخانهٔ رسانه.
	 *
	 * @return int|\WP_Error شناسهٔ پیوست.
	 */
	/**
	 * نام فایل از روی الگوی مرکز کنترل.
	 * {slug} نامک لاتین نوشته (اگر لاتین نباشد post-شناسه)، {id} شناسهٔ نوشته، {site} نام دامنهٔ سایت، {date} تاریخ و ساعت (۲۰۲۶-۱۰-۱۰-۱۸۵۸).
	 * اگر باز هم هم‌نام باشد، وردپرس خودش -1، -2 … اضافه می‌کند.
	 */
	public static function file_name( \WP_Post $post, string $tpl ): string {
		$tpl  = '' !== trim( $tpl ) ? $tpl : '{slug}-{date}';
		$now  = new \DateTimeImmutable( 'now', wp_timezone() ); // میلادی و لاتین، حتی اگر سایت تاریخ شمسی داشته باشد.
		$slug = preg_match( '/^[a-z0-9-]{3,80}$/', (string) $post->post_name ) ? $post->post_name : 'post-' . $post->ID;
		$name = strtr(
			$tpl,
			array(
				'{slug}' => $slug,
				'{id}'   => (string) $post->ID,
				'{site}' => strtok( (string) preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), '.' ) ?: 'site', // دامنهٔ لاتین سایت، مثلاً setareh.
				'{date}' => $now->format( 'Y-m-d-Hi' ),
			)
		);
		$name = sanitize_file_name( remove_accents( $name ) );
		$name = trim( (string) preg_replace( '/-{2,}/', '-', $name ), '-.' );
		return '' !== $name ? mb_substr( $name, 0, 100, 'UTF-8' ) : $slug . '-' . $now->format( 'Y-m-d-Hi' );
	}

	private static function store( \WP_Post $post, string $bytes, string $alt, array $cfg ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( 'rokhdadyar' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( ! $tmp || false === file_put_contents( $tmp, $bytes ) ) {
			return new \WP_Error( 'chk_img_tmp', __( 'نوشتن فایل موقت ممکن نشد.', 'rokhdadyar' ), array( 'status' => 500 ) );
		}
		unset( $bytes );

		$mime = wp_get_image_mime( $tmp );
		$exts = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		);
		if ( ! $mime || ! isset( $exts[ $mime ] ) ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'chk_img_bad', __( 'فایل برگشتی یک تصویر معتبر نیست.', 'rokhdadyar' ), array( 'status' => 502 ) );
		}
		$ext = $exts[ $mime ];

		// یک بار پردازش: برش از مرکز و رساندن به اندازهٔ دقیقِ تنظیم‌شده + خروجی WebP.
		$editor = wp_get_image_editor( $tmp );
		if ( ! is_wp_error( $editor ) ) {
			$size = $editor->get_size();
			$w    = (int) $cfg['width'];
			$h    = (int) $cfg['height'];
			if ( $size['width'] !== $w || $size['height'] !== $h ) {
				// بزرگ‌ترین قابِ هم‌نسبت با اندازهٔ هدف از وسط تصویر بریده و به همان اندازه رسانده می‌شود
				// (اگر مدل تصویر کوچک‌تری داده باشد بزرگ می‌شود، تا خروجی هیچ‌وقت از اندازهٔ هدف کمتر نباشد).
				$src_w = min( $size['width'], (int) round( $size['height'] * $w / $h ) );
				$src_h = min( $size['height'], (int) round( $size['width'] * $h / $w ) );
				$editor->crop( (int) ( ( $size['width'] - $src_w ) / 2 ), (int) ( ( $size['height'] - $src_h ) / 2 ), $src_w, $src_h, $w, $h );
			}
			$to_webp = $cfg['webp'] && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
			$editor->set_quality( 82 );
			$saved = $editor->save( $tmp . '-out', $to_webp ? 'image/webp' : $mime );
			if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
				wp_delete_file( $tmp );
				$tmp = $saved['path'];
				$ext = $to_webp ? 'webp' : $ext;
			}
		}

		$file = array(
			'name'     => self::file_name( $post, (string) ( $cfg['filename'] ?? '' ) ) . '.' . $ext,
			'tmp_name' => $tmp,
		);
		$id   = media_handle_sideload( $file, $post->ID, '' !== $alt ? $alt : $post->post_title );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
		update_post_meta( $id, self::FLAG, get_current_user_id() );
		return (int) $id;
	}

	/** فقط پیوست‌هایی که همین افزونه ساخته قابل ثبت یا دورریختن‌اند. */
	public static function owns( int $attachment_id ): bool {
		return $attachment_id && 'attachment' === get_post_type( $attachment_id ) && get_post_meta( $attachment_id, self::FLAG, true );
	}

	public static function apply( \WP_Post $post, int $attachment_id, ?string $alt, bool $set ) {
		if ( ! self::owns( $attachment_id ) ) {
			return new \WP_Error( 'chk_img_owner', __( 'این تصویر را این افزونه نساخته است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		if ( null !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $alt ) ) );
		}
		if ( $set ) {
			set_post_thumbnail( $post->ID, $attachment_id );
		}
		delete_post_meta( $post->ID, self::PENDING );
		return array(
			'id'  => $attachment_id,
			'url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
		);
	}

	/** پیش‌نمایشِ ردشده پاک می‌شود تا فایل بی‌صاحب در رسانه نماند. تصویری که شاخص شده دست نمی‌خورد. */
	/** تصویرِ ساخته‌شده و ثبت‌نشدهٔ یک نوشته، اگر هنوز هست. */
	public static function pending( \WP_Post $post ): ?array {
		$p  = get_post_meta( $post->ID, self::PENDING, true );
		$id = is_array( $p ) ? (int) ( $p['id'] ?? 0 ) : 0;
		if ( ! $id || ! self::owns( $id ) || (int) get_post_thumbnail_id( $post->ID ) === $id ) {
			if ( $p ) {
				delete_post_meta( $post->ID, self::PENDING );
			}
			return null;
		}
		$src = wp_get_attachment_image_url( $id, 'medium_large' );
		return array(
			'id'       => $id,
			'url'      => $src ? $src : wp_get_attachment_url( $id ),
			'alt'      => (string) ( $p['alt'] ?? '' ),
			'prompt'   => (string) ( $p['prompt'] ?? '' ),
			'custom'   => ! empty( $p['custom'] ),
			'quota'    => '',
			'restored' => true,
		);
	}

	/** روزانه: پیش‌نمایش‌هایی که بیش از دو روز ثبت نشده‌اند از رسانه پاک می‌شوند. */
	public static function cleanup(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::PENDING ) );
		foreach ( (array) $rows as $row ) {
			$p = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $p ) || (int) ( $p['t'] ?? 0 ) < time() - 2 * DAY_IN_SECONDS ) {
				if ( is_array( $p ) && ! empty( $p['id'] ) ) {
					self::discard( (int) $p['id'] );
				}
				delete_post_meta( (int) $row->post_id, self::PENDING );
			}
		}
	}

	public static function discard( int $attachment_id ): bool {
		if ( ! self::owns( $attachment_id ) ) {
			return false;
		}
		$parent = (int) wp_get_post_parent_id( $attachment_id );
		if ( $parent && (int) get_post_thumbnail_id( $parent ) === $attachment_id ) {
			return false;
		}
		if ( $parent ) {
			$p = get_post_meta( $parent, self::PENDING, true );
			if ( is_array( $p ) && (int) ( $p['id'] ?? 0 ) === $attachment_id ) {
				delete_post_meta( $parent, self::PENDING );
			}
		}
		return (bool) wp_delete_attachment( $attachment_id, true );
	}
}
