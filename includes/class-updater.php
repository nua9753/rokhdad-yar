<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * به‌روزرسانی وردپرسی از گیت‌هاب: مثل افزونه‌های مخزن وردپرس، در «افزونه‌ها» و «به‌روزرسانی‌ها» اعلان می‌آید،
 * «جزئیات» دارد، با یک کلیک (یا خودکار) نصب می‌شود.
 *
 * منبع: آخرین Release مخزن. فایل نصب، دارایی rokhdadyar.zip همان Release است (گیت‌هاب اکشن می‌سازدش).
 * با سربرگ «Update URI» وردپرس خودش این افزونه را از مخزن رسمی جدا می‌کند و فیلتر update_plugins_github.com را صدا می‌زند.
 * پاسخ گیت‌هاب ۶ ساعت نگه داشته می‌شود (محدودیت ۶۰ درخواست در ساعت برای هر IP).
 */
final class Updater {

	const REPO  = 'nua9753/rokhdad-yar';
	const ASSET = 'rokhdadyar.zip';
	const CACHE = 'rokhdadyar_gh_release';

	public static function init(): void {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 0 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_action( 'admin_post_rokhdadyar_check_update', array( __CLASS__, 'force_check' ) );
	}

	/** آخرین نسخهٔ منتشرشده در گیت‌هاب (یا null). */
	public static function release( bool $fresh = false ): ?array {
		$cached = get_site_transient( self::CACHE );
		if ( ! $fresh && is_array( $cached ) ) {
			return $cached ? $cached : null;
		}
		$res = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
				),
			)
		);
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = 200 === $code ? json_decode( (string) wp_remote_retrieve_body( $res ), true ) : null;
		if ( ! is_array( $json ) || empty( $json['tag_name'] ) ) {
			// خطا یا هنوز Releaseی نیست: یک ساعت دوباره نپرس.
			set_site_transient( self::CACHE, array(), HOUR_IN_SECONDS );
			return null;
		}
		$package = '';
		foreach ( (array) ( $json['assets'] ?? array() ) as $a ) {
			if ( self::ASSET === ( $a['name'] ?? '' ) ) {
				$package = (string) $a['browser_download_url'];
			}
		}
		$out = array(
			'version' => ltrim( (string) $json['tag_name'], 'vV' ),
			'package' => $package ? $package : (string) ( $json['zipball_url'] ?? '' ),
			'url'     => (string) ( $json['html_url'] ?? 'https://github.com/' . self::REPO ),
			'notes'   => (string) ( $json['body'] ?? '' ),
			'date'    => (string) ( $json['published_at'] ?? '' ),
		);
		set_site_transient( self::CACHE, $out, 6 * HOUR_IN_SECONDS );
		return $out;
	}

	/** وردپرس هنگام بررسی به‌روزرسانی‌ها برای افزونه‌هایی با Update URI روی github.com این را صدا می‌زند. */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( ROKHDADYAR_FILE ) !== $plugin_file ) {
			return $update;
		}
		$r = self::release();
		if ( ! $r || ! $r['package'] ) {
			return $update;
		}
		return array(
			'id'           => 'github.com/' . self::REPO,
			'slug'         => dirname( $plugin_file ),
			'plugin'       => $plugin_file,
			'version'      => $r['version'],
			'new_version'  => $r['version'],
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $r['package'],
			'tested'       => get_bloginfo( 'version' ),
			'requires_php' => '7.4',
			'icons'        => array( 'svg' => plugins_url( 'assets/logo.svg', ROKHDADYAR_FILE ) ),
		);
	}

	/** پنجرهٔ «نمایش جزئیات» با یادداشت انتشار. */
	public static function info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( plugin_basename( ROKHDADYAR_FILE ) ) !== $args->slug ) {
			return $res;
		}
		$r = self::release();
		if ( ! $r ) {
			return $res;
		}
		return (object) array(
			'name'          => 'رخدادیار',
			'slug'          => $args->slug,
			'version'       => $r['version'],
			'author'        => '<a href="https://github.com/' . self::REPO . '">رخدادیار</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'download_link' => $r['package'],
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'tested'        => get_bloginfo( 'version' ),
			'last_updated'  => $r['date'],
			'sections'      => array(
				'changelog'   => '' !== $r['notes'] ? wpautop( esc_html( $r['notes'] ) ) : esc_html__( 'یادداشتی برای این نسخه نوشته نشده است.', 'rokhdadyar' ),
				'description' => esc_html__( 'ساخت عنوان، چکیده، نامک، دسته و برچسب و تصویر شاخص نوشته با هوش مصنوعی.', 'rokhdadyar' ),
			),
		);
	}

	/**
	 * اگر بسته از zipball گیت‌هاب آمده باشد، پوشه‌اش «nua9753-rokhdad-yar-abc123» است؛
	 * به نام پوشهٔ فعلی افزونه برگردانده می‌شود تا وردپرس آن را همان افزونه بداند و غیرفعال نشود.
	 */
	public static function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || plugin_basename( ROKHDADYAR_FILE ) !== $hook_extra['plugin'] ) {
			return $source;
		}
		$want = trailingslashit( $remote_source ) . dirname( $hook_extra['plugin'] ) . '/';
		if ( trailingslashit( $source ) === $want ) {
			return $source;
		}
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->move( $source, $want, true ) ) {
			return $want;
		}
		return $source;
	}

	public static function flush(): void {
		delete_site_transient( self::CACHE );
	}

	/** پیوند «بررسی به‌روزرسانی» در ردیف افزونه. */
	public static function row_meta( array $links, string $file ): array {
		if ( plugin_basename( ROKHDADYAR_FILE ) === $file && current_user_can( 'update_plugins' ) ) {
			$links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rokhdadyar_check_update' ), 'rokhdadyar_check_update' ) ) . '">' . esc_html__( 'بررسی به‌روزرسانی', 'rokhdadyar' ) . '</a>';
		}
		return $links;
	}

	public static function force_check(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'rokhdadyar' ), 403 );
		}
		check_admin_referer( 'rokhdadyar_check_update' );
		self::flush();
		self::release( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}
}
