<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * دروازهٔ درخواست‌ها: هر درخواست به سرویس هوش مصنوعی یک پردازهٔ PHP را تا رسیدن پاسخ نگه می‌دارد.
 * برای اینکه چند کاربرِ هم‌زمان پردازه‌های سایت را پر نکنند:
 *  - هر کاربر در هر لحظه فقط یک درخواست باز دارد؛
 *  - کل سایت حداکثر «max_parallel» درخواستِ هم‌زمان دارد و بقیه پیام «سرِ کار هستیم» می‌گیرند.
 *
 * جای خالی‌ها ردیف‌هایی در جدول options است که با INSERT IGNORE گرفته می‌شوند؛ کلید یکتای option_name
 * گرفتن را اتمی می‌کند و چیزی از کش عبور نمی‌کند. جای خالیِ پردازه‌ای که وسط کار مرده، با گذشتن زمانش آزاد می‌شود.
 */
final class Gate {

	const SLOT = 'rokhdadyar_slot_';

	/** @var string[] جای‌هایی که همین پردازه گرفته است. */
	private static $held = array();

	/**
	 * @param int $ttl بیشترین زمانی که این درخواست ممکن است طول بکشد (ثانیه).
	 * @return true|\WP_Error
	 */
	public static function enter( int $ttl ) {
		$lock = 'chk_lock_' . get_current_user_id();
		if ( get_transient( $lock ) ) {
			return new \WP_Error( 'chk_busy', __( 'درخواست قبلی شما هنوز در حال انجام است؛ چند لحظه صبر کنید.', 'rokhdadyar' ), array( 'status' => 429 ) );
		}
		$max = (int) Settings::get( 'max_parallel' );
		if ( $max > 0 ) {
			$slot = self::take( $max, $ttl );
			if ( ! $slot ) {
				return new \WP_Error(
					'chk_site_busy',
					/* translators: %d: number of parallel requests */
					sprintf( __( 'الان %d درخواست دیگر در حال انجام است و برای اینکه سایت کند نشود، این یکی منتظر می‌ماند. چند ثانیهٔ دیگر دوباره بزنید.', 'rokhdadyar' ), $max ),
					array( 'status' => 429 )
				);
			}
			self::$held[] = $slot;
		}
		set_transient( $lock, 1, $ttl );
		// اگر مهلت اجرای PHP سرور کوتاه‌تر از انتظار پاسخ مدل باشد، پردازه وسط کار کشته می‌شود و قفل‌ها می‌مانند.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( $ttl + 30 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		static $hooked = false;
		if ( ! $hooked ) {
			$hooked = true;
			register_shutdown_function( array( __CLASS__, 'leave' ) ); // حتی بعد از خطای مرگبار.
		}
		return true;
	}

	public static function leave(): void {
		delete_transient( 'chk_lock_' . get_current_user_id() );
		global $wpdb;
		foreach ( self::$held as $slot ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->options, array( 'option_name' => $slot ) );
		}
		self::$held = array();
	}

	/** یک جای خالی می‌گیرد یا null. */
	private static function take( int $max, int $ttl ): ?string {
		global $wpdb;
		$now = time();
		for ( $i = 1; $i <= $max; $i++ ) {
			$name = self::SLOT . $i;
			// جای پردازه‌ای که مرده یا از زمانش گذشته آزاد می‌شود.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $name, $now ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) ( $now + $ttl ) ) );
			if ( 1 === (int) $ok ) {
				return $name;
			}
		}
		return null;
	}

	/** چند جا الان گرفته شده (برای نمایش در تنظیمات). */
	public static function busy(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) >= %d", $wpdb->esc_like( self::SLOT ) . '%', time() ) );
	}
}
