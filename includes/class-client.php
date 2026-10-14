<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * کلاینت سبک برای سه قالب API:
 *  - openai    : هر سرویس سازگار با /chat/completions (OpenAI، OpenRouter، درگاه‌های داخلی و …)
 *  - anthropic : /v1/messages
 *  - gemini    : /models/{model}:generateContent
 */
final class Client {

	const TIMEOUT  = 40;
	const MAX_BODY = 262144; // پاسخ‌های بزرگ‌تر از این خوانده نمی‌شوند.

	/** @var array|null مصرف توکنِ آخرین فراخوانی: in، out و cached (بخشی از ورودی که سرویس از کش خودش خوانده). */
	public static $usage = null;

	/**
	 * $prefix بخشِ ثابتِ پیام است (متن مطلب). همیشه اولِ پیام می‌آید تا «کش پیشوند» سرویس‌ها
	 * (OpenAI، DeepSeek، Gemini، Qwen خودکار؛ Anthropic با cache_control) در درخواست‌های بعدیِ همان نوشته تخفیف بدهد.
	 *
	 * @return string|\WP_Error
	 */
	public static function complete( array $profile, string $system, string $user, string $prefix = '' ) {
		self::$usage = null;
		if ( Settings::key_lost( (string) ( $profile['api_key'] ?? '' ) ) ) {
			return Settings::key_lost_error( (string) ( $profile['label'] ?? '' ) );
		}
		$key = Settings::decrypt( (string) ( $profile['api_key'] ?? '' ) );
		if ( '' === $key ) {
			return new \WP_Error( 'chk_no_key', __( 'کلید API برای این مدل تنظیم نشده است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		if ( '' === (string) ( $profile['model'] ?? '' ) ) {
			return new \WP_Error( 'chk_no_model', __( 'نام مدل تنظیم نشده است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}

		$method = 'call_' . $profile['format'];
		if ( '' !== $prefix && 'anthropic' !== $profile['format'] ) {
			$user   = $prefix . "\n\n" . $user;
			$prefix = '';
		}
		self::$prefix = $prefix;
		$started      = microtime( true );
		$text         = self::$method( $profile, $key, $system, $user, true );

		// بعضی مدل‌های Qwen در حالت غیر استریم فقط با خاموش‌بودن «فکر کردن» جواب می‌دهند.
		if ( is_wp_error( $text ) && 'openai' === $profile['format'] && false !== stripos( $text->get_error_message(), 'enable_thinking' ) ) {
			$text = self::call_openai( $profile, $key, $system, $user, true, array( 'enable_thinking' => false ) );
		}

		// بعضی مدل‌ها temperature یا max_tokens را نمی‌پذیرند؛ یک بار بدون آن‌ها تلاش می‌کنیم.
		if ( is_wp_error( $text ) && 400 === (int) ( $text->get_error_data()['upstream'] ?? 0 )
			&& preg_match( '/temperature|max_tokens|max_completion_tokens|unsupported|not support/i', $text->get_error_message() ) ) {
			$text = self::$method( $profile, $key, $system, $user, false );
		}
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		// حذف بلوک «فکر کردن» مدل‌های استدلالی.
		$text = preg_replace( '#<think(?:ing)?>.*?</think(?:ing)?>#is', '', $text );
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return new \WP_Error( 'chk_empty', __( 'مدل پاسخ خالی برگرداند.', 'rokhdadyar' ), array( 'status' => 502 ) );
		}
		self::$usage       = is_array( self::$usage ) ? self::$usage : array( 'in' => 0, 'out' => 0, 'cached' => 0 );
		self::$usage['ms'] = (int) round( 1000 * ( microtime( true ) - $started ) );
		Usage::add( (string) ( ! empty( $profile['label'] ) ? $profile['label'] : $profile['model'] ), self::$usage );
		return $text;
	}

	/** @var array|null درخواست‌های جمع‌شده در حالت موازی. */
	private static $collect = null;

	/** @var array|\WP_Error|null پاسخ آماده برای پردازش در حالت موازی. */
	private static $replay = null;

	/**
	 * چند پیام به یک مدل، هم‌زمان (نه پشت سر هم): زمان انتظار برابر کندترین پاسخ است، نه جمع همه.
	 * اگر یکی خطا داد، همان یکی با complete() معمولی (با تلاش‌های جایگزینش) دوباره فرستاده می‌شود.
	 *
	 * @return array نتیجهٔ هر پیام (متن یا WP_Error) به همان ترتیب
	 */
	public static function complete_many( array $profile, string $system, array $users ): array {
		$key = Settings::decrypt( (string) ( $profile['api_key'] ?? '' ) );
		if ( '' === $key || '' === (string) ( $profile['model'] ?? '' ) || count( $users ) < 2 || Settings::key_lost( (string) ( $profile['api_key'] ?? '' ) ) ) {
			return array_map(
				static function ( $u ) use ( $profile, $system ) {
					return self::complete( $profile, $system, $u );
				},
				$users
			);
		}
		$method       = 'call_' . $profile['format'];
		self::$prefix = '';
		self::$collect = array();
		foreach ( $users as $u ) {
			self::$method( $profile, $key, $system, $u, true );
		}
		$reqs          = self::$collect;
		self::$collect = null;

		$started = microtime( true );
		$batch   = array();
		foreach ( $reqs as $i => $r ) {
			$opt = array(
				'timeout'         => (float) $r[1]['timeout'],
				'connect_timeout' => 10,
				'verify'          => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
				'redirects'       => 2,
			);
			if ( defined( 'WP_PROXY_HOST' ) && defined( 'WP_PROXY_PORT' ) ) {
				$opt['proxy'] = WP_PROXY_HOST . ':' . WP_PROXY_PORT;
			}
			$batch[ $i ] = array(
				'url'     => $r[0],
				'headers' => $r[1]['headers'],
				'data'    => $r[1]['body'] ?? null,
				'type'    => 'POST',
				'options' => $opt,
			);
		}
		$class = class_exists( '\WpOrg\Requests\Requests' ) ? '\WpOrg\Requests\Requests' : ( class_exists( '\Requests' ) ? '\Requests' : '' );
		$resps = array();
		if ( $class ) {
			try {
				$resps = $class::request_multiple( $batch );
			} catch ( \Throwable $e ) {
				$resps = array();
			}
		}
		$ms  = (int) round( 1000 * ( microtime( true ) - $started ) );
		$out = array();
		$sum = array(
			'in'     => 0,
			'out'    => 0,
			'cached' => 0,
			'ms'     => $ms,
		);
		foreach ( $users as $i => $u ) {
			$r = $resps[ $i ] ?? null;
			if ( $r && is_object( $r ) && isset( $r->status_code ) && ! ( $r instanceof \Exception ) ) {
				self::$replay = array(
					'headers'  => array(),
					'body'     => (string) $r->body,
					'response' => array(
						'code'    => (int) $r->status_code,
						'message' => '',
					),
				);
				self::$usage  = null;
				$text         = self::$method( $profile, $key, $system, $u, true );
				self::$replay = null;
			} else {
				$text = new \WP_Error( 'chk_http', 'parallel' );
			}
			if ( is_wp_error( $text ) ) {
				$text = self::complete( $profile, $system, $u ); // دوباره، تکی و با همهٔ تلاش‌های جایگزین.
			} else {
				$text = trim( (string) preg_replace( '#<think(?:ing)?>.*?</think(?:ing)?>#is', '', $text ) );
				Usage::add( (string) ( ! empty( $profile['label'] ) ? $profile['label'] : $profile['model'] ), self::$usage );
			}
			foreach ( array( 'in', 'out', 'cached' ) as $k ) {
				$sum[ $k ] += (int) ( self::$usage[ $k ] ?? 0 );
			}
			$out[ $i ] = $text;
		}
		self::$usage = $sum;
		return $out;
	}

	/** @var string فقط برای Anthropic: بلوک قابل‌کش. */
	private static $prefix = '';

	private static function note_usage( int $in, int $out, int $cached ): void {
		self::$usage = array(
			'in'     => $in,
			'out'    => $out,
			'cached' => $cached,
		);
	}

	private static function call_openai( array $p, string $key, string $system, string $user, bool $tuned, array $extra = array() ) {
		$base = $p['base_url'] ? $p['base_url'] : 'https://api.openai.com/v1';
		$url  = preg_match( '#/chat/completions$#', $base ) ? $base : $base . '/chat/completions';
		$body = array(
			'model'    => $p['model'],
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
		);
		if ( $tuned ) {
			if ( '' !== (string) $p['temperature'] ) {
				$body['temperature'] = (float) $p['temperature'];
			}
			if ( (int) $p['max_tokens'] > 0 ) {
				$body['max_tokens'] = (int) $p['max_tokens'];
			}
		}
		$json = self::post( $url, array( 'Authorization' => 'Bearer ' . $key ), $body + $extra );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$u = (array) ( $json['usage'] ?? array() );
		self::note_usage(
			(int) ( $u['prompt_tokens'] ?? 0 ),
			(int) ( $u['completion_tokens'] ?? 0 ),
			(int) ( $u['prompt_tokens_details']['cached_tokens'] ?? ( $u['prompt_cache_hit_tokens'] ?? 0 ) )
		);
		$c = $json['choices'][0]['message']['content'] ?? '';
		return is_array( $c ) ? self::join_parts( $c ) : (string) $c;
	}

	private static function call_anthropic( array $p, string $key, string $system, string $user, bool $tuned ) {
		$base = $p['base_url'] ? $p['base_url'] : 'https://api.anthropic.com';
		if ( preg_match( '#/messages$#', $base ) ) {
			$url = $base;
		} elseif ( preg_match( '#/v1$#', $base ) ) {
			$url = $base . '/messages';
		} else {
			$url = $base . '/v1/messages';
		}
		$body = array(
			'model'      => $p['model'],
			'max_tokens' => (int) $p['max_tokens'] > 0 ? (int) $p['max_tokens'] : 1024,
			'system'     => $system,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => '' === self::$prefix ? $user : array(
						array(
							'type'          => 'text',
							'text'          => self::$prefix,
							'cache_control' => array( 'type' => 'ephemeral' ),
						),
						array(
							'type' => 'text',
							'text' => $user,
						),
					),
				),
			),
		);
		if ( $tuned && '' !== (string) $p['temperature'] ) {
			$body['temperature'] = min( 1, (float) $p['temperature'] );
		}
		$json = self::post(
			$url,
			array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
			),
			$body
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$u    = (array) ( $json['usage'] ?? array() );
		$read = (int) ( $u['cache_read_input_tokens'] ?? 0 );
		self::note_usage( (int) ( $u['input_tokens'] ?? 0 ) + $read + (int) ( $u['cache_creation_input_tokens'] ?? 0 ), (int) ( $u['output_tokens'] ?? 0 ), $read );
		return self::join_parts( (array) ( $json['content'] ?? array() ) );
	}

	private static function call_gemini( array $p, string $key, string $system, string $user, bool $tuned ) {
		$base = $p['base_url'] ? $p['base_url'] : 'https://generativelanguage.googleapis.com/v1beta';
		$url  = $base . '/models/' . rawurlencode( $p['model'] ) . ':generateContent';
		$body = array(
			'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
			'contents'          => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $user ) ),
				),
			),
		);
		if ( $tuned ) {
			$cfg = array();
			if ( '' !== (string) $p['temperature'] ) {
				$cfg['temperature'] = (float) $p['temperature'];
			}
			if ( (int) $p['max_tokens'] > 0 ) {
				$cfg['maxOutputTokens'] = (int) $p['max_tokens'];
			}
			if ( $cfg ) {
				$body['generationConfig'] = $cfg;
			}
		}
		$json = self::post( $url, array( 'x-goog-api-key' => $key ), $body );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$u = (array) ( $json['usageMetadata'] ?? array() );
		self::note_usage( (int) ( $u['promptTokenCount'] ?? 0 ), (int) ( $u['candidatesTokenCount'] ?? 0 ), (int) ( $u['cachedContentTokenCount'] ?? 0 ) );
		return self::join_parts( (array) ( $json['candidates'][0]['content']['parts'] ?? array() ) );
	}

	/** بخش‌های متنی پاسخ را به هم می‌چسباند (و بخش‌های غیرمتنی مثل thinking را رد می‌کند). */
	private static function join_parts( array $parts ): string {
		$out = '';
		foreach ( $parts as $part ) {
			if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] )
				&& in_array( $part['type'] ?? 'text', array( 'text', 'output_text' ), true ) && empty( $part['thought'] ) ) {
				$out .= $part['text'];
			}
		}
		return $out;
	}

	/**
	 * فهرست مدل‌های یک سرویس، برای پرکردن خودکار فیلد «نام مدل».
	 *
	 * @return string[]|\WP_Error
	 */
	public static function models( array $p ) {
		if ( Settings::key_lost( (string) ( $p['api_key'] ?? '' ) ) ) {
			return Settings::key_lost_error( (string) ( $p['label'] ?? '' ) );
		}
		$key = Settings::decrypt( (string) ( $p['api_key'] ?? '' ) );
		if ( '' === $key ) {
			return new \WP_Error( 'chk_no_key', __( 'اول کلید API را وارد کنید.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		if ( 'anthropic' === $p['format'] ) {
			$base = preg_replace( '#/v1(?:/messages)?$#', '', $p['base_url'] ? $p['base_url'] : 'https://api.anthropic.com' );
			$json = self::post(
				$base . '/v1/models?limit=100',
				array(
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
				),
				null
			);
		} elseif ( 'gemini' === $p['format'] ) {
			$base = $p['base_url'] ? $p['base_url'] : 'https://generativelanguage.googleapis.com/v1beta';
			$json = self::post( $base . '/models?pageSize=200', array( 'x-goog-api-key' => $key ), null );
		} else {
			$base = preg_replace( '#/chat/completions$#', '', $p['base_url'] ? $p['base_url'] : 'https://api.openai.com/v1' );
			$json = self::post( $base . '/models', array( 'Authorization' => 'Bearer ' . $key ), null );
		}
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$ids = array();
		foreach ( (array) ( $json['data'] ?? ( $json['models'] ?? array() ) ) as $m ) {
			$id = is_array( $m ) ? (string) ( $m['id'] ?? ( $m['name'] ?? '' ) ) : '';
			$id = preg_replace( '#^models/#', '', $id );
			if ( '' !== $id ) {
				$ids[] = sanitize_text_field( $id );
			}
		}
		sort( $ids );
		return array_slice( $ids, 0, 300 );
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function post( string $url, array $headers, ?array $body, ?int $timeout = null, ?int $max_body = null ) {
		$args = array(
			'method'              => null === $body ? 'GET' : 'POST',
			'timeout'             => $timeout ? $timeout : ( null === $body ? 15 : self::TIMEOUT ),
			'redirection'         => 2,
			'limit_response_size' => $max_body ? $max_body : self::MAX_BODY,
			'headers'             => $headers + array( 'Content-Type' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['body']        = wp_json_encode( $body );
			$args['data_format'] = 'body';
		}
		// حالت موازی (complete_many): مرحلهٔ اول فقط درخواست جمع می‌شود، مرحلهٔ دوم پاسخِ آماده پردازش می‌شود.
		if ( is_array( self::$collect ) ) {
			self::$collect[] = array( $url, $args );
			return new \WP_Error( 'chk_collect', 'collect' );
		}
		if ( null !== self::$replay ) {
			$res          = self::$replay;
			self::$replay = null;
		} else {
			$res = wp_remote_request( $url, $args );
		}
		if ( is_wp_error( $res ) ) {
			return new \WP_Error(
				'chk_http',
				/* translators: %s: error message */
				sprintf( __( 'ارتباط با سرور مدل برقرار نشد: %s', 'rokhdadyar' ), $res->get_error_message() ),
				array( 'status' => 502 )
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $raw, true );

		if ( $code >= 400 || ! is_array( $json ) ) {
			$msg = '';
			if ( is_array( $json ) ) {
				$err = $json['error'] ?? ( $json['message'] ?? '' );
				$msg = is_array( $err ) ? (string) ( $err['message'] ?? '' ) : (string) $err;
			}
			if ( '' === $msg ) {
				$msg = mb_substr( trim( wp_strip_all_tags( $raw ) ), 0, 200 );
			}
			return new \WP_Error(
				'chk_api',
				/* translators: 1: HTTP status, 2: message */
				sprintf( __( 'خطای سرور مدل (%1$d): %2$s', 'rokhdadyar' ), $code, $msg ? $msg : '—' ),
				array(
					'status'   => 502,
					'upstream' => $code,
				)
			);
		}
		return $json;
	}
}
