<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * سلامت آپلود: پیدا کردن علت خطاهایی مثل «پیوست‌ها نمی‌توانند در پایگاه داده درج شوند».
 *
 * این خطا را خود وردپرس می‌دهد وقتی درج یک ردیف در جدول posts شکست بخورد؛ برای همهٔ آپلودها (کتابخانهٔ رسانه،
 * تصویر از نشانی بیرونی، افزونه‌های درون‌ریزی، تصویر شاخص رخدادیار) یکسان است. شایع‌ترین علت‌ها:
 *  - ستون ID جدول posts بعد از انتقال سایت یا بازگردانی پشتیبان «AUTO_INCREMENT» یا کلید اصلی‌اش را از دست داده
 *    (اولین درج با ID=0 می‌نشیند و درج‌های بعدی تا وقتی آن ردیف هست شکست می‌خورند؛ برای همین «گاهی» کار می‌کند)
 *  - جدول خراب (crashed) یا پر بودن دیسک / سهمیهٔ هاست
 *  - نویسه‌هایی که با charset جدول جور نیستند (مثلاً ایموجی در جدول utf8 قدیمی)
 * فقط برای مدیر و فقط وقتی دکمه زده شود اجرا می‌شود؛ هیچ باری روی سایت ندارد.
 */
final class Health {

	/** جدول‌های اصلی وردپرس و ستون کلید هر کدام. */
	private static function tables(): array {
		global $wpdb;
		return array(
			$wpdb->posts        => 'ID',
			$wpdb->postmeta     => 'meta_id',
			$wpdb->options      => 'option_id',
			$wpdb->terms        => 'term_id',
			$wpdb->term_taxonomy => 'term_taxonomy_id',
			$wpdb->termmeta     => 'meta_id',
			$wpdb->comments     => 'comment_ID',
			$wpdb->commentmeta  => 'meta_id',
			$wpdb->users        => 'ID',
			$wpdb->usermeta     => 'umeta_id',
		);
	}

	/** وضعیت ستون کلید یک جدول. */
	private static function key_state( string $table, string $col ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$c = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $col ), ARRAY_A );
		if ( ! $c ) {
			return array( 'exists' => false );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$zero = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` = 0" );
		return array(
			'exists' => true,
			'type'   => (string) $c['Type'],
			'ai'     => false !== stripos( (string) $c['Extra'], 'auto_increment' ),
			'pk'     => 'PRI' === strtoupper( (string) $c['Key'] ),
			'zero'   => $zero,
		);
	}

	private static function bytes( $v ): int {
		return (int) wp_convert_hr_to_bytes( (string) $v );
	}

	private static function item( string $status, string $label, string $detail ): array {
		return array(
			'status' => $status, // ok | warn | bad
			'label'  => $label,
			'detail' => $detail,
		);
	}

	/**
	 * همهٔ بررسی‌ها.
	 *
	 * @return array{items: array, fixable: bool}
	 */
	public static function run(): array {
		global $wpdb;
		$items   = array();
		$fixable = false;

		// ۱) کلید و AUTO_INCREMENT جدول‌ها.
		$broken = array();
		foreach ( self::tables() as $table => $col ) {
			$s = self::key_state( $table, $col );
			if ( ! $s['exists'] ) {
				continue;
			}
			if ( ! $s['ai'] || ! $s['pk'] || $s['zero'] ) {
				$why = array();
				if ( ! $s['pk'] ) {
					$why[] = __( 'کلید اصلی ندارد', 'rokhdadyar' );
				}
				if ( ! $s['ai'] ) {
					$why[] = __( 'AUTO_INCREMENT ندارد', 'rokhdadyar' );
				}
				if ( $s['zero'] ) {
					/* translators: %d: rows */
					$why[] = sprintf( __( '%d ردیف با شناسهٔ ۰', 'rokhdadyar' ), $s['zero'] );
				}
				$broken[] = $table . ' (' . implode( '، ', $why ) . ')';
			}
		}
		if ( $broken ) {
			$fixable = true;
			$items[] = self::item(
				'bad',
				__( 'ساختار جدول‌های پایگاه‌داده', 'rokhdadyar' ),
				__( 'علت اصلی خطای «پیوست‌ها نمی‌توانند در پایگاه داده درج شوند» همین است: ', 'rokhdadyar' ) . implode( ' · ', $broken )
				. __( '. معمولاً بعد از انتقال سایت یا بازگردانی پشتیبان پیش می‌آید. دکمهٔ «تعمیر» پایین همین بخش درستش می‌کند.', 'rokhdadyar' )
			);
		} else {
			$items[] = self::item( 'ok', __( 'ساختار جدول‌های پایگاه‌داده', 'rokhdadyar' ), __( 'کلید و AUTO_INCREMENT همهٔ جدول‌های اصلی سالم است.', 'rokhdadyar' ) );
		}

		// ۲) جدول خراب.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$st = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->posts ), ARRAY_A );
		if ( $st ) {
			$crashed = '' === (string) $st['Engine'] || false !== stripos( (string) $st['Comment'], 'crash' ) || false !== stripos( (string) $st['Comment'], 'marked' );
			if ( $crashed ) {
				$fixable = true;
				$items[] = self::item( 'bad', __( 'جدول نوشته‌ها', 'rokhdadyar' ), __( 'جدول posts خراب علامت خورده است: ', 'rokhdadyar' ) . (string) $st['Comment'] );
			} else {
				$coll = (string) $st['Collation'];
				$utf  = 0 === stripos( $coll, 'utf8mb4' );
				$items[] = self::item(
					$utf ? 'ok' : 'warn',
					__( 'جدول نوشته‌ها', 'rokhdadyar' ),
					$st['Engine'] . ' · ' . $coll . ( $utf ? '' : __( ' — ایموجی و بعضی نویسه‌ها در عنوان فایل می‌توانند درج را خراب کنند؛ تبدیل به utf8mb4 توصیه می‌شود.', 'rokhdadyar' ) )
				);
			}
		}

		// ۳) آزمایش واقعی درج یک ردیف پیوست (و برگرداندن فوری آن).
		$test    = self::insert_test();
		$items[] = $test;

		// ۴) پوشهٔ آپلود و فضای دیسک.
		$up = wp_upload_dir( null, false );
		if ( ! empty( $up['error'] ) ) {
			$items[] = self::item( 'bad', __( 'پوشهٔ آپلود', 'rokhdadyar' ), (string) $up['error'] );
		} else {
			$dir = (string) $up['basedir'];
			$w   = wp_is_writable( $dir );
			$items[] = self::item( $w ? 'ok' : 'bad', __( 'پوشهٔ آپلود', 'rokhdadyar' ), $w ? __( 'قابل نوشتن است.', 'rokhdadyar' ) : __( 'قابل نوشتن نیست؛ دسترسی پوشهٔ wp-content/uploads را در هاست درست کنید (معمولاً 755).', 'rokhdadyar' ) );
			$free = function_exists( 'disk_free_space' ) ? @disk_free_space( $dir ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false !== $free ) {
				$mb      = (int) floor( $free / MB_IN_BYTES );
				$items[] = self::item( $mb < 50 ? 'bad' : ( $mb < 500 ? 'warn' : 'ok' ), __( 'فضای خالی دیسک', 'rokhdadyar' ), size_format( $free ) . ( $mb < 500 ? __( ' — کم است؛ با پر شدن دیسک یا سهمیهٔ هاست، هم فایل و هم پایگاه‌داده دیگر چیزی نمی‌پذیرند.', 'rokhdadyar' ) : '' ) );
			}
		}
		$tmp = get_temp_dir();
		$items[] = self::item( wp_is_writable( $tmp ) ? 'ok' : 'bad', __( 'پوشهٔ موقت', 'rokhdadyar' ), $tmp . ( wp_is_writable( $tmp ) ? '' : __( ' قابل نوشتن نیست؛ تصویرهای بیرونی اول اینجا دانلود می‌شوند. WP_TEMP_DIR را در wp-config.php تعریف کنید.', 'rokhdadyar' ) ) );

		// ۵) محدودیت‌های PHP.
		$upl = min( self::bytes( ini_get( 'upload_max_filesize' ) ), self::bytes( ini_get( 'post_max_size' ) ) );
		$items[] = self::item( $upl && $upl < 8 * MB_IN_BYTES ? 'warn' : 'ok', __( 'حداکثر حجم آپلود', 'rokhdadyar' ), size_format( $upl ) . ' (upload_max_filesize / post_max_size)' );
		$mem = self::bytes( ini_get( 'memory_limit' ) );
		$items[] = self::item( $mem > 0 && $mem < 128 * MB_IN_BYTES ? 'warn' : 'ok', __( 'حافظهٔ PHP', 'rokhdadyar' ), ( $mem > 0 ? size_format( $mem ) : __( 'بی‌سقف', 'rokhdadyar' ) ) . ( $mem > 0 && $mem < 128 * MB_IN_BYTES ? __( ' — برای پردازش تصویرهای بزرگ کم است؛ حداقل 256M.', 'rokhdadyar' ) : '' ) );

		// ۶) پردازش تصویر و دانلود از بیرون.
		$editor  = _wp_image_editor_choose( array( 'mime_type' => 'image/jpeg' ) );
		$webp    = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
		$items[] = self::item( $editor ? ( $webp ? 'ok' : 'warn' ) : 'bad', __( 'پردازشگر تصویر', 'rokhdadyar' ), $editor ? ( str_replace( 'WP_Image_Editor_', '', $editor ) . ( $webp ? ' · WebP' : __( ' · بدون پشتیبانی WebP', 'rokhdadyar' ) ) ) : __( 'نه GD نه Imagick فعال است؛ وردپرس نمی‌تواند تصویر را برش یا اندازه‌های کوچک بسازد.', 'rokhdadyar' ) );
		$curl    = function_exists( 'curl_init' );
		$items[] = self::item( $curl ? 'ok' : 'warn', __( 'دانلود تصویر از نشانی بیرونی', 'rokhdadyar' ), $curl ? 'cURL' : __( 'افزونهٔ cURL در PHP فعال نیست؛ دریافت تصویر از سایت‌های دیگر ممکن است شکست بخورد.', 'rokhdadyar' ) );

		return array(
			'items'   => $items,
			'fixable' => $fixable,
		);
	}

	/** یک ردیف آزمایشیِ پیوست درج و بلافاصله حذف می‌شود تا خطای واقعی پایگاه‌داده دیده شود. */
	private static function insert_test(): array {
		global $wpdb;
		$label = __( 'آزمایش درج پیوست در پایگاه‌داده', 'rokhdadyar' );
		$now   = current_time( 'mysql' );
		$gmt   = current_time( 'mysql', true );
		$row   = array(
			'post_author'           => get_current_user_id(),
			'post_date'             => $now,
			'post_date_gmt'         => $gmt,
			'post_modified'         => $now,
			'post_modified_gmt'     => $gmt,
			'post_title'            => 'rokhdadyar-health-test',
			'post_name'             => 'rokhdadyar-health-test-' . wp_generate_password( 6, false, false ),
			'post_content'          => '',
			'post_excerpt'          => '',
			'to_ping'               => '',
			'pinged'                => '',
			'post_content_filtered' => '',
			'post_status'           => 'inherit',
			'post_type'             => 'attachment',
			'post_mime_type'        => 'image/webp',
			'guid'                  => '',
		);
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok  = $wpdb->insert( $wpdb->posts, $row );
		$err = (string) $wpdb->last_error;
		$id  = (int) $wpdb->insert_id;
		if ( false !== $ok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->posts, array( 'post_name' => $row['post_name'] ) );
		}
		$wpdb->suppress_errors( $suppress );
		if ( false === $ok ) {
			return self::item( 'bad', $label, __( 'درج شکست خورد. پیام پایگاه‌داده: ', 'rokhdadyar' ) . ( '' !== $err ? $err : '—' ) );
		}
		if ( $id < 1 ) {
			return self::item( 'bad', $label, __( 'درج با شناسهٔ ۰ انجام شد؛ یعنی AUTO_INCREMENT جدول posts کار نمی‌کند و درج بعدی شکست می‌خورد.', 'rokhdadyar' ) );
		}
		return self::item( 'ok', $label, __( 'موفق بود (ردیف آزمایشی بلافاصله حذف شد).', 'rokhdadyar' ) );
	}

	/** خلاصهٔ یک‌خطی برای کنار پیام خطای آپلود. */
	public static function hint(): string {
		foreach ( array( $GLOBALS['wpdb']->posts => 'ID' ) as $table => $col ) {
			$s = self::key_state( $table, $col );
			if ( ! empty( $s['exists'] ) && ( ! $s['ai'] || ! $s['pk'] || $s['zero'] ) ) {
				return __( 'علت: ستون ID جدول posts سایت شما خراب است (AUTO_INCREMENT / کلید اصلی). در «تنظیمات رخدادیار ← تصویر شاخص ← سلامت آپلود» بررسی و تعمیر کنید.', 'rokhdadyar' );
			}
		}
		return __( 'برای پیدا کردن علت، «تنظیمات رخدادیار ← تصویر شاخص ← سلامت آپلود» را بزنید.', 'rokhdadyar' );
	}

	/**
	 * تعمیر: ردیف‌های با شناسهٔ ۰ به شناسهٔ تازه منتقل می‌شوند (داده حذف نمی‌شود)، سپس کلید اصلی و AUTO_INCREMENT
	 * با همان نوع ستون فعلی برگردانده می‌شوند؛ جدول خراب هم REPAIR می‌شود.
	 *
	 * @return array{done: string[], failed: string[]}
	 */
	public static function fix(): array {
		global $wpdb;
		$done   = array();
		$failed = array();
		$wpdb->suppress_errors( true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$st = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->posts ), ARRAY_A );
		if ( $st && ( '' === (string) $st['Engine'] || false !== stripos( (string) $st['Comment'], 'crash' ) ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "REPAIR TABLE `{$wpdb->posts}`" );
			$done[] = 'REPAIR ' . $wpdb->posts;
		}

		foreach ( self::tables() as $table => $col ) {
			$s = self::key_state( $table, $col );
			if ( empty( $s['exists'] ) || ( $s['ai'] && $s['pk'] && ! $s['zero'] ) ) {
				continue;
			}
			// ردیف‌های شناسهٔ ۰ ← شناسهٔ تازه، یکی‌یکی.
			for ( $i = 0; $i < (int) $s['zero']; $i++ ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$next = (int) $wpdb->get_var( "SELECT COALESCE(MAX(`{$col}`), 0) + 1 FROM `{$table}`" );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET `{$col}` = %d WHERE `{$col}` = 0 LIMIT 1", $next ) );
			}
			if ( ! $s['pk'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$dups = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT `{$col}` FROM `{$table}` GROUP BY `{$col}` HAVING COUNT(*) > 1) d" );
				if ( $dups ) {
					/* translators: 1: table, 2: count */
					$failed[] = sprintf( __( '%1$s: %2$d شناسهٔ تکراری دارد؛ تعمیر خودکار امن نیست، به پشتیبانی هاست بسپارید.', 'rokhdadyar' ), $table, $dups );
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$table}` ADD PRIMARY KEY (`{$col}`)" );
			}
			$type = preg_replace( '/[^a-z0-9() ]/i', '', $s['type'] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$table}` MODIFY `{$col}` {$type} NOT NULL AUTO_INCREMENT" );
			$after = self::key_state( $table, $col );
			if ( $after['ai'] && $after['pk'] && ! $after['zero'] ) {
				$done[] = $table;
			} else {
				$failed[] = $table . ': ' . ( '' !== $wpdb->last_error ? $wpdb->last_error : __( 'تعمیر نشد', 'rokhdadyar' ) );
			}
		}
		$wpdb->suppress_errors( false );
		return array(
			'done'   => $done,
			'failed' => $failed,
		);
	}
}
