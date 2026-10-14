<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * تاریخچهٔ عنوان، چکیده و نامک: هر بار که افزونه یکی از این‌ها را عوض می‌کند، مقدار قبلی نگه داشته می‌شود
 * تا با یک کلیک برگردد. در یک متای خودِ نوشته ذخیره می‌شود (حداکثر ۱۲ مورد برای هر نوشته).
 */
final class History {

	const META   = '_rokhdadyar_history';
	const FIELDS = array( 'title', 'excerpt', 'slug' );
	const MAX    = 12;

	/** مقدار فعلیِ ذخیره‌شدهٔ یک فیلد. */
	public static function current( \WP_Post $post, string $field ): string {
		if ( 'title' === $field ) {
			return (string) $post->post_title;
		}
		return 'excerpt' === $field ? (string) $post->post_excerpt : urldecode( (string) $post->post_name );
	}

	/** مقدارِ پیش از تغییر را نگه می‌دارد. خالی یا تکراری ثبت نمی‌شود. */
	public static function add( int $post_id, string $field, string $old ): void {
		$old = 'excerpt' === $field ? sanitize_textarea_field( $old ) : sanitize_text_field( $old );
		if ( '' === $old || ! in_array( $field, self::FIELDS, true ) ) {
			return;
		}
		$list = get_post_meta( $post_id, self::META, true );
		$list = is_array( $list ) ? $list : array();
		foreach ( $list as $item ) {
			if ( $item['f'] === $field ) {
				if ( $item['v'] === $old ) {
					return; // همین مقدار آخرین موردِ این فیلد است.
				}
				break;
			}
		}
		array_unshift(
			$list,
			array(
				'f' => $field,
				'v' => mb_substr( $old, 0, 1000, 'UTF-8' ),
				't' => time(),
				'u' => get_current_user_id(),
			)
		);
		update_post_meta( $post_id, self::META, wp_slash( array_slice( $list, 0, self::MAX ) ) );
	}

	public static function has( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, self::META, true );
	}

	/** برای نمایش: تازه‌ترین اول، با تاریخ و نام کاربر. */
	public static function get( \WP_Post $post ): array {
		$list = get_post_meta( $post->ID, self::META, true );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $item ) {
			if ( ! in_array( $item['f'] ?? '', self::FIELDS, true ) || ( 'excerpt' === $item['f'] && ! post_type_supports( $post->post_type, 'excerpt' ) ) ) {
				continue;
			}
			$user  = get_userdata( (int) ( $item['u'] ?? 0 ) );
			$out[] = array(
				'f'       => $item['f'],
				'v'       => (string) $item['v'],
				'date'    => wp_date( get_option( 'date_format' ) . ' H:i', (int) $item['t'] ),
				'user'    => $user ? $user->display_name : '',
				'current' => self::current( $post, $item['f'] ) === (string) $item['v'],
			);
		}
		return $out;
	}
}
