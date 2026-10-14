<?php
/**
 * Plugin Name:       رخدادیار — عنوان، چکیده، نامک و تصویر شاخص با هوش مصنوعی
 * Description:       ساخت عنوان، چکیده، نامک، دسته و برچسب و تصویر شاخص نوشته با هوش مصنوعی؛ با مرکز کنترل برای مدل‌ها، Base URL، کلید API و پرامپت‌ها.
 * Version:           2.17.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            رخدادیار
 * Plugin URI:        https://github.com/nua9753/rokhdad-yar
 * License:           GPL-2.0-or-later
 * Text Domain:       rokhdadyar
 * Update URI:        https://github.com/nua9753/rokhdad-yar
 */

defined( 'ABSPATH' ) || exit;

define( 'ROKHDADYAR_VER', '2.17.0' );
define( 'ROKHDADYAR_FILE', __FILE__ );
define( 'ROKHDADYAR_DIR', __DIR__ . '/' );

/*
 * بارگذاری تنبل: هیچ کلاسی تا وقتی واقعاً لازم نشود خوانده نمی‌شود.
 * در سمت کاربر (فرانت) این افزونه فقط همین فایل را بار می‌کند و هیچ کوئری یا فایلی اضافه نمی‌کند.
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'Rokhdadyar\\' ) ) {
			return;
		}
		$file = ROKHDADYAR_DIR . 'includes/class-' . strtolower( substr( $class, 11 ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

if ( is_admin() ) {
	add_action( 'plugins_loaded', array( 'Rokhdadyar\\Admin', 'init' ) );
}
// به‌روزرسانی از گیت‌هاب: در پیشخوان و در کرون وردپرس (برای به‌روزرسانی خودکار).
if ( is_admin() || wp_doing_cron() ) {
	add_action( 'plugins_loaded', array( 'Rokhdadyar\\Updater', 'init' ) );
}

add_action( 'rest_api_init', array( 'Rokhdadyar\\Rest', 'register' ) );
// محافظ نامک: فقط هنگام ذخیرهٔ نوشته صدا زده می‌شود (فرم، REST، ویرایش سریع)؛ بار دیگری ندارد.
add_filter( 'wp_insert_post_data', array( 'Rokhdadyar\\Guard', 'filter' ), 99, 2 );
add_action( 'post_updated', array( 'Rokhdadyar\\Guard', 'track' ), 10, 3 );
// جعبه ابزار ← پین در دسته‌بندی. در سایت فقط وقتی ابزار روشن است و فقط در آرشیو دسته‌ها کاری انجام می‌شود؛
// تنظیماتش در یک گزینهٔ autoload است، پس کوئری اضافه‌ای ندارد.
add_action( 'wp_after_insert_post', array( 'Rokhdadyar\\Pin', 'refresh' ) );
add_action( 'deleted_post', array( 'Rokhdadyar\\Pin', 'forget' ) );
add_action( 'rest_api_init', array( 'Rokhdadyar\\Toolbox', 'register_meta' ), 5 ); // متای جعبه ابزار برای ویرایشگر بلوکی
// فهرست کش‌شدهٔ «مطالب مهم» با هر تیک/برداشتن تیک تازه می‌شود (کلاس فقط برای همان کلید بار می‌شود).
$rokhdadyar_imp = static function ( $mid, $pid, $key ) {
	if ( '_rokhdadyar_important' === $key ) {
		Rokhdadyar\Links::flush();
	}
};
add_action( 'updated_post_meta', $rokhdadyar_imp, 10, 3 );
add_action( 'added_post_meta', $rokhdadyar_imp, 10, 3 );
add_action( 'deleted_post_meta', $rokhdadyar_imp, 10, 3 );
add_action( 'transition_post_status', static function ( $new, $old ) {
	if ( 'publish' === $new || 'publish' === $old ) {
		delete_transient( 'chk_important' ); // عنوان/نشانیِ مطالب مهم ممکن است عوض شده باشد.
	}
}, 10, 2 );
unset( $rokhdadyar_imp );
$rokhdadyar_front = get_option( 'rokhdadyar_front' );
if ( ! empty( $rokhdadyar_front['on'] ) ) {
	if ( ! is_admin() && ! empty( $rokhdadyar_front['map'] ) ) {
		add_filter( 'posts_clauses', array( 'Rokhdadyar\\Pin', 'clauses' ), 20, 2 );
		add_filter( 'the_title', array( 'Rokhdadyar\\Pin', 'the_title' ), 10, 2 );
		add_filter( 'post_class', array( 'Rokhdadyar\\Pin', 'post_class' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( 'Rokhdadyar\\Pin', 'styles' ) );
		add_action( 'wp_footer', array( 'Rokhdadyar\\Pin', 'footer_script' ), 99 );
	}
}
unset( $rokhdadyar_front );
add_action( 'rokhdadyar_cleanup', array( 'Rokhdadyar\\Image', 'cleanup' ) ); // پاک‌سازی روزانهٔ پیش‌نمایش‌های ثبت‌نشده.
