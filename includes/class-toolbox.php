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
			'pin' => __( 'پین در دسته‌بندی', 'rokhdadyar' ),
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
		);
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
		if ( ! self::can( 'pin' ) || ! in_array( $post_type, self::pin_types(), true ) ) {
			return;
		}
		add_meta_box(
			'rokhdadyar-toolbox',
			__( 'جعبه ابزار', 'rokhdadyar' ),
			static function ( \WP_Post $post ) {
				wp_nonce_field( 'rokhdadyar_toolbox', 'rokhdadyar_toolbox_nonce' );
				echo '<label class="chk-check"><input type="checkbox" name="rokhdadyar_pin" value="1" ' . checked( (bool) get_post_meta( $post->ID, Pin::META, true ), true, false ) . '><span><strong>' . esc_html__( 'پین در دسته‌بندی', 'rokhdadyar' ) . '</strong><em>' . esc_html__( 'این مطلب در بالای دسته‌های سطح اول و دومِ خودش می‌ماند و نشان «مطلب مهم» می‌گیرد.', 'rokhdadyar' ) . '</em></span></label>';
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
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! self::can( 'pin' ) ) {
			return;
		}
		if ( ! empty( $_POST['rokhdadyar_pin'] ) ) {
			update_post_meta( $post_id, Pin::META, time() );
		} else {
			delete_post_meta( $post_id, Pin::META );
		}
	}

	/* ---------- ویرایشگر بلوکی: متای قابل‌ویرایش از REST ---------- */

	public static function register_meta(): void {
		foreach ( self::pin_types() as $type ) {
			register_post_meta(
				$type,
				Pin::META,
				array(
					'type'          => 'integer',
					'single'        => true,
					'default'       => 0,
					'show_in_rest'  => true,
					'auth_callback' => static function ( $allowed, $key, $post_id ) {
						return current_user_can( 'edit_post', $post_id ) && self::can( 'pin' );
					},
				)
			);
		}
	}
}
