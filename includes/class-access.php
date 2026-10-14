<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * دسترسی بر اساس نقش: چه نقشی ابزارهای متنی را ببیند، چه نقشی تصویر بسازد و چه نقشی گزارش مصرف را ببیند.
 * مدیر سایت همیشه همه‌چیز را دارد. این‌ها روی مجوزهای خودِ وردپرس سوار می‌شوند و چیزی را دور نمی‌زنند:
 * کسی که اجازهٔ ویرایش نوشته‌ای را ندارد، با این تنظیم هم نخواهد داشت.
 */
final class Access {

	const AREAS = array( 'text', 'image', 'usage' );

	/** نقش‌هایی که اصلاً به نوشته‌ها دست می‌زنند (به‌جز مدیر): slug => نام. */
	public static function roles(): array {
		$out = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			if ( ! empty( $role['capabilities']['manage_options'] ) || empty( $role['capabilities']['edit_posts'] ) ) {
				continue;
			}
			$out[ $slug ] = translate_user_role( $role['name'] );
		}
		return $out;
	}

	/** پیش‌فرض همان رفتار قبلی است: متن برای همه، تصویر برای کسانی که اجازهٔ بارگذاری دارند، گزارش فقط مدیر. */
	public static function defaults(): array {
		$out = array(
			'text'  => array(),
			'image' => array(),
			'usage' => array(),
		);
		foreach ( wp_roles()->roles as $slug => $role ) {
			if ( ! empty( $role['capabilities']['manage_options'] ) || empty( $role['capabilities']['edit_posts'] ) ) {
				continue;
			}
			$out['text'][] = $slug;
			if ( ! empty( $role['capabilities']['upload_files'] ) ) {
				$out['image'][] = $slug;
			}
		}
		return $out;
	}

	public static function lists(): array {
		$saved = Settings::get( 'access' );
		return is_array( $saved ) && $saved ? array_merge( self::defaults(), $saved ) : self::defaults();
	}

	public static function sanitize( $raw ): array {
		$roles = array_keys( self::roles() );
		$out   = array();
		foreach ( self::AREAS as $area ) {
			$out[ $area ] = array_values( array_intersect( $roles, array_map( 'sanitize_key', (array) ( $raw[ $area ] ?? array() ) ) ) );
		}
		return $out;
	}

	public static function can( string $area ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		$allowed = self::lists()[ $area ] ?? array();
		return (bool) array_intersect( (array) $user->roles, $allowed );
	}

	/** هیچ‌کدام از ابزارهای داخل نوشته را ندارد؟ */
	public static function none(): bool {
		return ! self::can( 'text' ) && ! self::can( 'image' );
	}
}
