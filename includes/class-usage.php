<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * گزارش مصرف: شمارنده‌های تجمیعی، بدون جدول جدا و بدون ثبت تک‌تک درخواست‌ها.
 *
 * هر ماه یک گزینهٔ کوچک (بدون autoload): روز ← کاربر ← مدل ← [درخواست، توکن ورودی، توکن خروجی، ورودیِ کش‌شده، تصویر].
 * فقط وقتی واقعاً درخواستی به سرویس رفته یک بار نوشته می‌شود؛ پاسخ‌هایی که از حافظه می‌آیند چیزی ثبت نمی‌کنند.
 */
final class Usage {

	const PREFIX = 'rokhdadyar_usage_';
	const KEEP   = 12; // ماه

	/**
	 * «الان» به وقت سایت، با تقویم میلادی.
	 * عمداً wp_date نیست: افزونه‌های تاریخ شمسی (مثل پارسی‌دیت) خروجی wp_date را شمسی می‌کنند و کلیدهای ذخیره به‌هم می‌ریختند.
	 * wp_date فقط برای نمایش به کار می‌رود.
	 */
	private static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', wp_timezone() );
	}

	public static function add( string $model, ?array $usage, int $images = 0 ): void {
		$now  = self::now();
		$key  = self::PREFIX . $now->format( 'Ym' );
		$data = get_option( $key );
		if ( ! is_array( $data ) ) {
			$data = array();
			// اولین نوشتنِ هر ماه: ماهِ خارج از بازهٔ نگه‌داری پاک می‌شود.
			delete_option( self::PREFIX . $now->modify( '-' . ( self::KEEP + 1 ) . ' months' )->format( 'Ym' ) );
		}
		$day   = $now->format( 'd' );
		$user  = get_current_user_id();
		$model = '' !== $model ? mb_substr( $model, 0, 60, 'UTF-8' ) : '—';
		$row   = $data[ $day ][ $user ][ $model ] ?? array( 0, 0, 0, 0, 0 );

		if ( $images ) {
			$row[4] += $images;
		} else {
			++$row[0];
			$row[1] += (int) ( $usage['in'] ?? 0 );
			$row[2] += (int) ( $usage['out'] ?? 0 );
			$row[3] += (int) ( $usage['cached'] ?? 0 );
		}
		$data[ $day ][ $user ][ $model ] = $row;
		update_option( $key, $data, false );
	}

	/** ماه‌هایی که داده دارند، تازه‌ترین اول (فقط در صفحهٔ گزارش صدا زده می‌شود). */
	public static function months(): array {
		global $wpdb;
		self::fix_jalali();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name DESC", $wpdb->esc_like( self::PREFIX ) . '%' ) );
		$out   = array();
		foreach ( (array) $names as $n ) {
			$ym = substr( $n, strlen( self::PREFIX ) );
			if ( preg_match( '/^\d{6}$/', $ym ) ) {
				$out[] = $ym;
			}
		}
		$now = self::now()->format( 'Ym' );
		if ( ! in_array( $now, $out, true ) ) {
			array_unshift( $out, $now );
		}
		return $out;
	}

	/**
	 * @return array { total[5], days{dd=>[5]}, users{id=>[5]}, models{name=>[5]} }
	 */
	public static function report( string $ym ): array {
		$data = preg_match( '/^\d{6}$/', $ym ) ? get_option( self::PREFIX . $ym ) : array();
		$out  = array(
			'total'  => array( 0, 0, 0, 0, 0 ),
			'days'   => array(),
			'users'  => array(),
			'models' => array(),
		);
		$sum  = static function ( ?array $a, array $b ): array {
			$a = $a ? $a : array( 0, 0, 0, 0, 0 );
			foreach ( $b as $i => $v ) {
				$a[ $i ] += (int) $v;
			}
			return $a;
		};
		foreach ( (array) $data as $day => $users ) {
			foreach ( (array) $users as $uid => $models ) {
				foreach ( (array) $models as $model => $row ) {
					$row                      = array_pad( array_map( 'intval', (array) $row ), 5, 0 );
					$out['total']             = $sum( $out['total'], $row );
					$out['days'][ $day ]      = $sum( $out['days'][ $day ] ?? null, $row );
					$out['users'][ $uid ]     = $sum( $out['users'][ $uid ] ?? null, $row );
					$out['models'][ $model ]  = $sum( $out['models'][ $model ] ?? null, $row );
				}
			}
		}
		krsort( $out['days'] );
		$by_tokens = static function ( $a, $b ) {
			return ( $b[1] + $b[2] + $b[4] * 1000 ) <=> ( $a[1] + $a[2] + $a[4] * 1000 );
		};
		uasort( $out['users'], $by_tokens );
		uasort( $out['models'], $by_tokens );
		return $out;
	}

	/**
	 * مصرفِ n روز اخیر (قدیمی‌ترین اول) برای نمودار؛ حداکثر دو گزینهٔ ماهانه خوانده می‌شود.
	 *
	 * @return array[] هر مورد: { day, date, req, tokens }
	 */
	public static function recent( int $n = 15 ): array {
		$out   = array();
		$cache = array();
		$now   = self::now()->setTime( 12, 0 );
		for ( $i = $n - 1; $i >= 0; $i-- ) {
			$day = $now->modify( '-' . $i . ' days' );
			$ts  = $day->getTimestamp();
			$ym  = $day->format( 'Ym' );
			if ( ! isset( $cache[ $ym ] ) ) {
				$cache[ $ym ] = (array) get_option( self::PREFIX . $ym, array() );
			}
			$req    = 0;
			$tokens = 0;
			foreach ( (array) ( $cache[ $ym ][ $day->format( 'd' ) ] ?? array() ) as $models ) {
				foreach ( (array) $models as $row ) {
					$req    += (int) ( $row[0] ?? 0 );
					$tokens += (int) ( $row[1] ?? 0 ) + (int) ( $row[2] ?? 0 );
				}
			}
			$out[] = array(
				'day'    => wp_date( 'j', $ts ),
				'date'   => wp_date( get_option( 'date_format' ), $ts ),
				'req'    => $req,
				'tokens' => $tokens,
			);
		}
		return $out;
	}

	/** «۲۰۲۶۱۰» ← «اکتبر ۲۰۲۶» به قالب تاریخ سایت. */
	public static function label( string $ym ): string {
		$first = \DateTimeImmutable::createFromFormat( '!Ymd H:i', $ym . '01 12:00', wp_timezone() );
		if ( ! $first ) {
			return $ym;
		}
		$last = $first->modify( 'last day of this month' );
		// بازهٔ دقیق روزها؛ با تقویم شمسی هم درست خوانده می‌شود (مثلاً «۱۰ مهر تا ۹ آبان ۱۴۰۵»).
		return wp_date( 'j F', $first->getTimestamp() ) . ' – ' . wp_date( 'j F Y', $last->getTimestamp() );
	}

	/** تاریخِ یک روز از ماهِ ذخیره‌شده، برای نمایش. */
	public static function day_label( string $ym, string $d ): string {
		$dt = \DateTimeImmutable::createFromFormat( '!Ymd H:i', $ym . str_pad( $d, 2, '0', STR_PAD_LEFT ) . ' 12:00', wp_timezone() );
		return $dt ? wp_date( get_option( 'date_format' ), $dt->getTimestamp() ) : $d;
	}

	/**
	 * نسخه‌های پیش از ۲.۱۱ روی سایت‌هایی که تاریخ شمسی دارند، کلید ماه و روز را شمسی ذخیره کرده بودند (مثلاً ۱۴۰۵۰۷).
	 * یک بار به میلادی برگردانده و با داده‌های درست ادغام می‌شوند.
	 */
	private static function fix_jalali(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::PREFIX . '1' ) . '%' ) );
		foreach ( (array) $names as $name ) {
			$ym = substr( $name, strlen( self::PREFIX ) );
			if ( ! preg_match( '/^(1[34]\d\d)(\d\d)$/', $ym, $m ) ) {
				continue;
			}
			foreach ( (array) get_option( $name, array() ) as $jd => $users ) {
				list( $gy, $gm, $gd ) = self::to_gregorian( (int) $m[1], (int) $m[2], (int) $jd );
				$key  = self::PREFIX . sprintf( '%04d%02d', $gy, $gm );
				$data = (array) get_option( $key, array() );
				$day  = sprintf( '%02d', $gd );
				foreach ( (array) $users as $uid => $models ) {
					foreach ( (array) $models as $model => $row ) {
						$cur = $data[ $day ][ $uid ][ $model ] ?? array( 0, 0, 0, 0, 0 );
						foreach ( array_pad( (array) $row, 5, 0 ) as $i => $v ) {
							$cur[ $i ] = (int) ( $cur[ $i ] ?? 0 ) + (int) $v;
						}
						$data[ $day ][ $uid ][ $model ] = $cur;
					}
				}
				update_option( $key, $data, false );
			}
			delete_option( $name );
		}
	}

	/** تبدیل تاریخ شمسی به میلادی (الگوریتم استاندارد). */
	private static function to_gregorian( int $jy, int $jm, int $jd ): array {
		$jy  += 1595;
		$days = -355668 + ( 365 * $jy ) + ( intdiv( $jy, 33 ) * 8 ) + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd + ( $jm < 7 ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
		$gy   = 400 * intdiv( $days, 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			$gy  += 100 * intdiv( --$days, 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				$days++;
			}
		}
		$gy  += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$gy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}
		$gd   = $days + 1;
		$leap = ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400;
		$mdays = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		for ( $gm = 1; $gm <= 12 && $gd > $mdays[ $gm ]; $gm++ ) {
			$gd -= $mdays[ $gm ];
		}
		return array( $gy, $gm, $gd );
	}
}
