<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * نشانِ «قبلاً ساخته شده»: برای هر نوشته یادش می‌ماند کدام کارها (عنوان، چکیده، نامک، دسته و برچسب، تصویر)
 * دست‌کم یک بار با افزونه ساخته شده‌اند تا دکمهٔ همان کار رنگی شود.
 * یک متای کوچک روی خودِ نوشته؛ فقط بارِ اولِ هر کار نوشته می‌شود و در فهرست نوشته‌ها کوئری تازه‌ای نمی‌زند.
 */
final class Made {

	const META  = '_rokhdadyar_made';
	const TASKS = array( 'title', 'excerpt', 'slug', 'terms', 'image', 'links' );

	/** @return string[] */
	public static function get( int $post_id ): array {
		$v    = get_post_meta( $post_id, self::META, true );
		$list = is_string( $v ) && '' !== $v ? explode( ',', $v ) : array();
		// نوشته‌هایی که پیش از این نسخه با افزونه عوض شده‌اند از روی تاریخچه‌شان شناخته می‌شوند.
		$hist = get_post_meta( $post_id, History::META, true );
		foreach ( is_array( $hist ) ? $hist : array() as $item ) {
			$list[] = (string) ( $item['f'] ?? '' );
		}
		return array_values( array_intersect( self::TASKS, $list ) );
	}

	public static function has( int $post_id, string $task ): bool {
		return in_array( $task, self::get( $post_id ), true );
	}

	public static function mark( int $post_id, string $task ): void {
		$list = self::get( $post_id );
		if ( ! in_array( $task, self::TASKS, true ) || in_array( $task, $list, true ) ) {
			return;
		}
		$list[] = $task;
		update_post_meta( $post_id, self::META, implode( ',', $list ) );
	}

	/** نتیجهٔ یک ساخت را عبور می‌دهد و اگر موفق بود نشان را می‌گذارد. */
	public static function pass( int $post_id, string $task, $res ) {
		if ( ! is_wp_error( $res ) ) {
			self::mark( $post_id, $task );
		}
		return $res;
	}
}
