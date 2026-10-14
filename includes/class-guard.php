<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * محافظ نامک: تغییر نامکِ نوشتهٔ منتشرشده یعنی تغییر آدرس صفحه؛ رتبهٔ گوگل و لینک‌های بیرونی و داخلی را به خطر می‌اندازد.
 *  - «confirm»: پیش از هر تغییر (ویرایشگر کلاسیک، بلوکی، ویرایش سریع، دکمهٔ نامکِ افزونه) هشدار جدی و تأیید صریح.
 *  - «lock»: فقط مدیر سایت می‌تواند عوضش کند؛ برای بقیه، حتی اگر از راه دیگری فرستاده شود، سمت سرور برگردانده می‌شود.
 * در هر حالت، نامک قبلی در «تاریخچه و برگشت» ثبت می‌شود تا با یک کلیک برگردد.
 */
final class Guard {

	public static function mode(): string {
		$m = (string) Settings::get( 'slug_guard' );
		return in_array( $m, array( 'off', 'confirm', 'lock' ), true ) ? $m : 'confirm';
	}

	/** آیا کاربر فعلی اجازهٔ عوض کردن نامکِ نوشتهٔ منتشرشده را دارد؟ */
	public static function can_change(): bool {
		return 'lock' !== self::mode() || current_user_can( 'manage_options' );
	}

	public static function covers( \WP_Post $post ): bool {
		return 'publish' === $post->post_status && in_array( $post->post_type, (array) Settings::get( 'post_types' ), true );
	}

	/** قفل سمت سرور: هر راهی که نامک را عوض کند (فرم، REST، ویرایش سریع، افزونهٔ دیگر). */
	public static function filter( array $data, array $postarr ): array {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! $id || 'off' === self::mode() || self::can_change() ) {
			return $data;
		}
		$old = get_post( $id );
		if ( $old && self::covers( $old ) && '' !== $old->post_name && $data['post_name'] !== $old->post_name ) {
			$data['post_name'] = $old->post_name;
			set_transient( 'chk_slug_locked_' . get_current_user_id(), 1, 120 );
		}
		return $data;
	}

	/** هر تغییر نامکِ نوشتهٔ منتشرشده، از هر جا، در تاریخچه ثبت می‌شود. */
	public static function track( int $id, \WP_Post $after, \WP_Post $before ): void {
		if ( self::covers( $before ) && '' !== $before->post_name && $before->post_name !== $after->post_name ) {
			History::add( $id, 'slug', urldecode( $before->post_name ) );
		}
	}

	public static function notice(): void {
		$key = 'chk_slug_locked_' . get_current_user_id();
		if ( get_transient( $key ) ) {
			delete_transient( $key );
			echo '<div class="notice notice-error"><p>' . esc_html__( 'نامکِ نوشتهٔ منتشرشده قفل است و فقط مدیر سایت می‌تواند آن را عوض کند؛ نامک قبلی حفظ شد.', 'rokhdadyar' ) . '</p></div>';
		}
	}

	/** متن‌های هشدار؛ مشترک بین ویرایشگر کلاسیک، فهرست و ویرایشگر بلوکی. */
	public static function config(): array {
		return array(
			'mode'    => self::mode(),
			'canEdit' => self::can_change(),
			'title'   => __( 'هشدار جدی: تغییر آدرس یک صفحهٔ منتشرشده', 'rokhdadyar' ),
			'lead'    => __( 'این نوشته منتشر شده و احتمالاً در گوگل ایندکس شده است. تغییر نامک یعنی آدرس صفحه عوض می‌شود و ممکن است:', 'rokhdadyar' ),
			'risks'   => array(
				__( 'رتبه و بازدید گوگل این صفحه از دست برود یا صفحه از نتایج گوگل و دیسکاور حذف شود.', 'rokhdadyar' ),
				__( 'لینک‌هایی که در شبکه‌های اجتماعی، سایت‌های دیگر و خودِ سایت داده شده به صفحهٔ خطای ۴۰۴ برسند.', 'rokhdadyar' ),
				__( 'اعتبار و آمار صفحه از صفر شروع شود و مدتی طول بکشد تا گوگل آدرس تازه را بپذیرد.', 'rokhdadyar' ),
			),
			'note'    => __( 'وردپرس معمولاً آدرس قدیمی را به آدرس تازه هدایت می‌کند، ولی افزونه‌های سئو، کش یا تنظیمات سرور می‌توانند جلوی آن را بگیرند. اگر مطمئن نیستید، تغییر ندهید.', 'rokhdadyar' ),
			'ack'     => __( 'می‌دانم ممکن است این صفحه رتبه‌اش را از دست بدهد و از گوگل حذف شود.', 'rokhdadyar' ),
			'yes'     => __( 'با این حال نامک را عوض کن', 'rokhdadyar' ),
			'no'      => __( 'نه، نامک فعلی بماند', 'rokhdadyar' ),
			'locked'  => __( 'نامکِ نوشته‌های منتشرشده قفل است و فقط مدیر سایت می‌تواند آن را عوض کند.', 'rokhdadyar' ),
		);
	}
}
