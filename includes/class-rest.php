<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

final class Rest {

	const NS = 'rokhdadyar/v1';

	public static function register(): void {
		$post_args = array(
			'post_id' => array(
				'type'     => 'integer',
				'required' => true,
			),
			'task'    => array(
				'type'     => 'string',
				'required' => true,
				'enum'     => array( 'title', 'excerpt', 'slug' ),
			),
		);

		register_rest_route(
			self::NS,
			'/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'generate' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $post_args + array(
					'content' => array( 'type' => 'string' ),
					'title'   => array( 'type' => 'string' ),
					'fresh'   => array( 'type' => 'boolean' ),
					'keyword' => array( 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'apply' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $post_args + array(
					'value' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		// تصویر شاخص: مسیرهای جدا.
		$img = array(
			'post_id'       => array(
				'type'     => 'integer',
				'required' => true,
			),
			'attachment_id' => array( 'type' => 'integer' ),
		);
		foreach ( array( 'image' => 'image', 'image/apply' => 'image_apply', 'image/discard' => 'image_discard', 'image/pending' => 'image_pending' ) as $path => $cb ) {
			register_rest_route(
				self::NS,
				'/' . $path,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, $cb ),
					'permission_callback' => static function ( \WP_REST_Request $req ) {
						return Access::can( 'image' ) && self::owns( $req ) && current_user_can( 'upload_files' );
					},
					'args'                => $img,
				)
			);
		}

		// تاریخچه.
		foreach ( array( 'history' => 'history', 'history/add' => 'history_add' ) as $path => $cb ) {
			register_rest_route(
				self::NS,
				'/' . $path,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, $cb ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
					'args'                => array(
						'post_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				)
			);
		}

		// دسته و برچسب.
		foreach ( array( 'terms' => 'terms', 'terms/apply' => 'terms_apply' ) as $path => $cb ) {
			register_rest_route(
				self::NS,
				'/' . $path,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, $cb ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
					'args'                => array(
						'post_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				)
			);
		}
		register_rest_route(
			self::NS,
			'/terms/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'terms_refresh' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/models',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'models' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'test' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public static function can_edit( \WP_REST_Request $req ): bool {
		return Access::can( 'text' ) && self::owns( $req );
	}

	/** مجوز خودِ وردپرس برای همین نوشته، جدا از دسترسیِ نقش. */
	private static function owns( \WP_REST_Request $req ): bool {
		$id   = (int) $req['post_id'];
		$post = $id ? get_post( $id ) : null;
		return $post
			&& in_array( $post->post_type, (array) Settings::get( 'post_types' ), true )
			&& current_user_can( 'edit_post', $id );
	}

	public static function generate( \WP_REST_Request $req ) {
		$post = get_post( (int) $req['post_id'] );
		if ( $req->has_param( 'keyword' ) ) {
			Extractor::save_keywords( $post->ID, (string) $req['keyword'] );
		}
		$res = Generator::run(
			$post,
			(string) $req['task'],
			$req->has_param( 'content' ) ? (string) $req['content'] : null,
			$req->has_param( 'title' ) ? (string) $req['title'] : null,
			(bool) $req['fresh'],
			sanitize_textarea_field( (string) $req['prompt'] )
		);
		return rest_ensure_response( Made::pass( $post->ID, (string) $req['task'], $res ) );
	}

	/** ذخیرهٔ مستقیم (برای فهرست نوشته‌ها). نامک نوشته دست‌نخورده می‌ماند. */
	public static function apply( \WP_REST_Request $req ) {
		$id    = (int) $req['post_id'];
		$task  = (string) $req['task'];
		$post  = get_post( $id );
		$value = 'title' === $task ? sanitize_text_field( (string) $req['value'] ) : sanitize_textarea_field( (string) $req['value'] );

		if ( '' === $value ) {
			return new \WP_Error( 'chk_empty', __( 'مقدار خالی است.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}
		// مقدار قبلی برای «تاریخچه و برگشت» نگه داشته می‌شود.
		$old = History::current( $post, $task );
		if ( $old !== ( 'slug' === $task ? urldecode( sanitize_title( $value ) ) : $value ) ) {
			History::add( $id, $task, $old );
		}
		if ( 'slug' === $task && Guard::covers( $post ) && ! Guard::can_change() ) {
			return new \WP_Error( 'chk_slug_locked', Guard::config()['locked'], array( 'status' => 403 ) );
		}
		if ( 'slug' === $task ) {
			// وردپرس خودش نامک را پاک‌سازی و یکتا می‌کند (در صورت تکراری‌بودن، ‎-2‎ می‌گیرد).
			$res = wp_update_post(
				wp_slash(
					array(
						'ID'        => $id,
						'post_name' => sanitize_title( $value ),
					)
				),
				true
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			clean_post_cache( $id );
			return rest_ensure_response(
				array(
					'task'  => $task,
					'value' => urldecode( get_post( $id )->post_name ),
					'link'  => get_permalink( $id ),
				)
			);
		}
		if ( 'excerpt' === $task && ! post_type_supports( $post->post_type, 'excerpt' ) ) {
			return new \WP_Error( 'chk_unsupported', __( 'این نوع نوشته چکیده ندارد.', 'rokhdadyar' ), array( 'status' => 400 ) );
		}

		$res = wp_update_post(
			wp_slash(
				array(
					'ID'        => $id,
					'post_name' => $post->post_name,
					( 'title' === $task ? 'post_title' : 'post_excerpt' ) => $value,
				)
			),
			true
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return rest_ensure_response(
			array(
				'task'  => $task,
				'value' => $value,
			)
		);
	}

	/** آزمایش اتصال یک پروفایل (ذخیره‌شده یا هنوز ذخیره‌نشده). */
	public static function test( \WP_REST_Request $req ) {
		$p = self::profile_from( $req );

		$t0   = microtime( true );
		$text = Client::complete( $p, 'You are a connection test. Reply with the single word: OK', 'ping' );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return rest_ensure_response(
			array(
				'ok'    => true,
				'ms'    => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
				'reply' => mb_substr( $text, 0, 80 ),
			)
		);
	}

	public static function models( \WP_REST_Request $req ) {
		$ids = Client::models( self::profile_from( $req ) );
		return is_wp_error( $ids ) ? $ids : rest_ensure_response( array( 'models' => $ids ) );
	}

	/** پروفایل از بدنهٔ درخواست؛ اگر کلید تازه‌ای نیامده باشد، کلید ذخیره‌شدهٔ همان پروفایل. */
	private static function profile_from( \WP_REST_Request $req ): array {
		$p   = Settings::sanitize_profile( (array) $req->get_json_params() );
		$key = trim( (string) $req['api_key'] );
		if ( '' !== $key ) {
			$p['api_key'] = $key;
		} else {
			$saved        = Settings::profile( $p['id'] );
			$p['api_key'] = $saved ? $saved['api_key'] : '';
		}
		return $p;
	}

	public static function image( \WP_REST_Request $req ) {
		if ( (int) $req['discard'] ) {
			Image::discard( (int) $req['discard'] ); // «دوباره بساز»: پیش‌نمایش قبلی پاک می‌شود.
		}
		$res = Image::generate(
			get_post( (int) $req['post_id'] ),
			$req->has_param( 'content' ) ? (string) $req['content'] : null,
			$req->has_param( 'title' ) ? (string) $req['title'] : null,
			sanitize_textarea_field( (string) $req['prompt'] )
		);
		return rest_ensure_response( Made::pass( (int) $req['post_id'], 'image', $res ) );
	}

	public static function image_apply( \WP_REST_Request $req ) {
		return rest_ensure_response(
			Image::apply(
				get_post( (int) $req['post_id'] ),
				(int) $req['attachment_id'],
				$req->has_param( 'alt' ) ? (string) $req['alt'] : null,
				! $req->has_param( 'set' ) || (bool) $req['set']
			)
		);
	}

	public static function image_pending( \WP_REST_Request $req ) {
		$p = Image::pending( get_post( (int) $req['post_id'] ) );
		return rest_ensure_response( $p ? $p : array( 'id' => 0 ) );
	}

	public static function image_discard( \WP_REST_Request $req ) {
		return rest_ensure_response( array( 'deleted' => Image::discard( (int) $req['attachment_id'] ) ) );
	}

	private static function ids( $v ): ?array {
		return is_array( $v ) ? array_map( 'intval', $v ) : null;
	}

	public static function terms( \WP_REST_Request $req ) {
		$current = array();
		foreach ( array( 'cats', 'tags' ) as $k ) {
			$ids = self::ids( $req[ 'current_' . $k ] );
			if ( null !== $ids ) {
				$current[ $k ] = $ids; // وضعیت ذخیره‌نشدهٔ ویرایشگر.
			}
		}
		$res = Terms::suggest(
			get_post( (int) $req['post_id'] ),
			$req->has_param( 'content' ) ? (string) $req['content'] : null,
			$req->has_param( 'title' ) ? (string) $req['title'] : null,
			$current,
			(bool) $req['fresh']
		);
		return rest_ensure_response( Made::pass( (int) $req['post_id'], 'terms', $res ) );
	}

	public static function terms_apply( \WP_REST_Request $req ) {
		return rest_ensure_response(
			Terms::apply(
				get_post( (int) $req['post_id'] ),
				self::ids( $req['cats'] ),
				self::ids( $req['tags'] ),
				is_array( $req['new'] ) ? array_map( 'strval', $req['new'] ) : array(),
				! $req->has_param( 'save' ) || (bool) $req['save']
			)
		);
	}

	/** بازسازی دستی فهرست نگه‌داشته‌شدهٔ دسته‌ها و برچسب‌ها. */
	public static function terms_refresh() {
		foreach ( Terms::TAXES as $tax ) {
			Terms::index( $tax, true );
		}
		return rest_ensure_response( Terms::status() );
	}

	public static function history( \WP_REST_Request $req ) {
		return rest_ensure_response( array( 'items' => History::get( get_post( (int) $req['post_id'] ) ) ) );
	}

	/** ویرایشگرها مقدار را خودشان عوض می‌کنند؛ مقدار قبلی را از این راه برای تاریخچه می‌فرستند. */
	public static function history_add( \WP_REST_Request $req ) {
		History::add( (int) $req['post_id'], sanitize_key( (string) $req['field'] ), (string) $req['old'] );
		return rest_ensure_response( array( 'ok' => true ) );
	}
}
