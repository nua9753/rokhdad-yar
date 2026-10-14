<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * جعبه ابزار: ابزارهای جانبیِ نوشته که هر کدام جدا روشن/خاموش می‌شوند و دسترسیِ نقش‌ها را دارند.
 * در ویرایشگر یک جعبهٔ جدا به نام «جعبه ابزار» می‌آید (کلاسیک و بلوکی).
 * ابزار ۱: پین در دسته‌بندی (Pin).
 */
final class Toolbox {

	/** فهرست ابزارها: شناسه => نام. ابزارهای بعدی همین‌جا اضافه می‌شوند. */
	public static function tools(): array {
		return array(
			'pin'       => __( 'پین در دسته‌بندی', 'rokhdadyar' ),
			'important' => __( 'مطالب مهم', 'rokhdadyar' ),
		);
	}

	public static function defaults(): array {
		return array(
			'pin' => array(
				'on'    => 0,
				'roles' => array( 'editor' ),
				'label' => 'مطلب مهم',
				'color' => '#d63638',
				'max'   => 3,
			),
			'important' => array(
				'on'    => 0,
				'roles' => array( 'editor' ),
				'links' => 1, // پیشنهاد لینک داخلی با هوش مصنوعی فقط به مطالب مهم
				'max'   => 5, // حداکثر لینک پیشنهادی برای هر مطلب
			),
		);
	}

	public static function config(): array {
		$saved = Settings::get( 'tools' );
		$out   = self::defaults();
		foreach ( $out as $k => $d ) {
			$out[ $k ] = array_merge( $d, is_array( $saved[ $k ] ?? null ) ? $saved[ $k ] : array() );
		}
		return $out;
	}

	public static function sanitize( $raw ): array {
		$roles = array_keys( Access::roles() );
		$d     = self::defaults();
		$p     = (array) ( $raw['pin'] ?? array() );
		$color = sanitize_hex_color( (string) ( $p['color'] ?? '' ) );
		$label = sanitize_text_field( (string) ( $p['label'] ?? '' ) );
		return array(
			'pin' => array(
				'on'    => empty( $p['on'] ) ? 0 : 1,
				'roles' => array_values( array_intersect( $roles, array_map( 'sanitize_key', (array) ( $p['roles'] ?? array() ) ) ) ),
				'label' => '' !== $label ? mb_substr( $label, 0, 30, 'UTF-8' ) : $d['pin']['label'],
				'color' => $color ? $color : $d['pin']['color'],
				'max'   => max( 1, min( 10, (int) ( $p['max'] ?? 3 ) ) ),
			),
			'important' => array(
				'on'    => empty( $raw['important']['on'] ) ? 0 : 1,
				'roles' => array_values( array_intersect( $roles, array_map( 'sanitize_key', (array) ( $raw['important']['roles'] ?? array() ) ) ) ),
				'links' => empty( $raw['important']['links'] ) ? 0 : 1,
				'max'   => max( 1, min( 10, (int) ( $raw['important']['max'] ?? 5 ) ) ),
			),
		);
	}

	/** پیشنهاد لینک داخلی روشن است؟ (ابزار «مطالب مهم» + گزینهٔ لینک) */
	public static function links_on(): bool {
		$c = self::config()['important'];
		return ! empty( $c['on'] ) && ! empty( $c['links'] );
	}

	/** ابزار روشن است و کاربر فعلی اجازه‌اش را دارد؟ مدیر سایت همیشه دارد. */
	public static function can( string $tool ): bool {
		$c = self::config()[ $tool ] ?? null;
		if ( ! $c || empty( $c['on'] ) ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$user = wp_get_current_user();
		return $user && $user->exists() && (bool) array_intersect( (array) $user->roles, (array) $c['roles'] );
	}

	public static function any(): bool {
		foreach ( array_keys( self::tools() ) as $t ) {
			if ( self::can( $t ) ) {
				return true;
			}
		}
		return false;
	}

	/** نوع نوشته‌ای که دسته دارد و در «کجا فعال باشد» انتخاب شده. */
	public static function pin_types(): array {
		return array_values( array_filter( (array) Settings::get( 'post_types' ), static function ( $t ) {
			return is_object_in_taxonomy( $t, 'category' );
		} ) );
	}

	/* ---------- ویرایشگر کلاسیک: جعبهٔ جدا ---------- */

	public static function classic_box( string $post_type ): void {
		$pin = self::can( 'pin' ) && in_array( $post_type, self::pin_types(), true );
		$imp = self::can( 'important' ) && in_array( $post_type, (array) Settings::get( 'post_types' ), true );
		if ( ! $pin && ! $imp ) {
			return;
		}
		add_meta_box(
			'rokhdadyar-toolbox',
			__( 'جعبه ابزار', 'rokhdadyar' ),
			static function ( \WP_Post $post ) use ( $pin, $imp ) {
				wp_nonce_field( 'rokhdadyar_toolbox', 'rokhdadyar_toolbox_nonce' );
				if ( $pin ) {
					echo '<label class="chk-check"><input type="checkbox" name="rokhdadyar_pin" value="1" ' . checked( (bool) get_post_meta( $post->ID, Pin::META, true ), true, false ) . '><span><strong>' . esc_html__( 'پین در دسته‌بندی', 'rokhdadyar' ) . '</strong><em>' . esc_html__( 'این مطلب در بالای دسته‌های سطح اول و دومِ خودش می‌ماند و نشان «مطلب مهم» می‌گیرد.', 'rokhdadyar' ) . '</em></span></label>';
				}
				if ( $imp ) {
					echo '<label class="chk-check"><input type="checkbox" name="rokhdadyar_important" value="1" ' . checked( (bool) get_post_meta( $post->ID, Links::META, true ), true, false ) . '><span><strong>' . esc_html__( 'مطلب مهم', 'rokhdadyar' ) . '</strong><em>' . esc_html__( 'لینک‌های داخلیِ پیشنهادی هوش مصنوعی فقط به مطالب مهم داده می‌شوند.', 'rokhdadyar' ) . '</em></span></label>';
				}
			},
			$post_type,
			'side',
			'high',
			array( '__back_compat_meta_box' => true )
		);
	}

	public static function classic_save( int $post_id ): void {
		if ( ! isset( $_POST['rokhdadyar_toolbox_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['rokhdadyar_toolbox_nonce'] ), 'rokhdadyar_toolbox' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( self::can( 'pin' ) ) {
			if ( ! empty( $_POST['rokhdadyar_pin'] ) ) {
				if ( ! get_post_meta( $post_id, Pin::META, true ) ) {
					update_post_meta( $post_id, Pin::META, time() );
				}
			} else {
				delete_post_meta( $post_id, Pin::META );
			}
		}
		if ( self::can( 'important' ) ) {
			Links::mark( $post_id, ! empty( $_POST['rokhdadyar_important'] ) );
		}
	}

	/* ---------- ویرایشگر بلوکی: متای قابل‌ویرایش از REST ---------- */

	/** فقط در REST (ویرایشگر بلوکی) لازم است؛ در سایت هزینه‌ای ندارد. */
	public static function register_meta(): void {
		$c = self::config();
		foreach ( array( 'pin' => Pin::META, 'important' => Links::META ) as $tool => $key ) {
			if ( empty( $c[ $tool ]['on'] ) ) {
				continue;
			}
			foreach ( 'pin' === $tool ? self::pin_types() : (array) Settings::get( 'post_types' ) as $type ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'          => 'integer',
						'single'        => true,
						'default'       => 0,
						'show_in_rest'  => true,
						'auth_callback' => static function ( $allowed, $meta_key, $post_id ) use ( $tool ) {
							return current_user_can( 'edit_post', $post_id ) && self::can( $tool );
						},
					)
				);
			}
		}
	}
}
