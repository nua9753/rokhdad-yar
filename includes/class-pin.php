<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * پین در دسته‌بندی.
 *
 * ذخیره: هر بار که نوشته‌ای ذخیره می‌شود، فهرست «دسته‌هایی که این نوشته در آن‌ها پین است» (فقط سطح اول و دوم درخت)
 * در یک گزینهٔ کوچکِ autoload حساب و نگه داشته می‌شود. پس در سایت هیچ کوئری اضافه‌ای برای پیدا کردن پین‌ها زده نمی‌شود.
 *
 * نمایش: فقط در صفحهٔ آرشیو دسته‌ها و فقط در صفحهٔ اول، نوشته‌های پین‌شده بالای فهرست می‌آیند (و از بقیهٔ صفحه‌ها حذف می‌شوند
 * تا تکراری نشوند) و کنار عنوانشان نشان «مطلب مهم» می‌نشیند. در خروجی سایت هیچ نامی از افزونه نیست.
 */
final class Pin {

	const META  = '_rokhdadyar_pin';
	const FRONT = 'rokhdadyar_front'; // گزینهٔ کوچکِ autoload برای سایت: تنظیمات نمایش + نقشهٔ پین‌ها

	/** @var int[]|null نوشته‌های پین‌شدهٔ دستهٔ فعلی (در همین درخواست). */
	private static $current = null;

	public static function front(): array {
		$f = get_option( self::FRONT );
		return is_array( $f ) ? $f : array( 'on' => 0, 'map' => array() );
	}

	/** وقتی تنظیمات جعبه ابزار ذخیره می‌شود. */
	public static function sync_settings(): void {
		$c          = Toolbox::config()['pin'];
		$f          = self::front();
		$f['on']    = (int) $c['on'];
		$f['label'] = $c['label'];
		$f['color'] = $c['color'];
		$f['max']   = (int) $c['max'];
		$f['map']   = $c['on'] ? self::rebuild() : array();
		update_option( self::FRONT, $f, true );
	}

	/** نقشهٔ کامل از روی متای نوشته‌ها (فقط هنگام ذخیرهٔ تنظیمات). */
	private static function rebuild(): array {
		$map = array();
		$ids = get_posts(
			array(
				'post_type'      => Toolbox::pin_types(),
				'post_status'    => 'publish',
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'         => 'ids',
				'posts_per_page' => 500,
				'no_found_rows'  => true,
			)
		);
		foreach ( $ids as $id ) {
			$t = (int) get_post_meta( $id, self::META, true );
			if ( $t ) {
				$map[ (int) $id ] = array(
					't' => $t,
					'c' => self::targets( (int) $id ),
				);
			}
		}
		return $map;
	}

	/** دسته‌های هدف: هر دستهٔ نوشته و اجدادش، به شرطی که در سطح اول یا دوم درخت باشند. */
	public static function targets( int $post_id ): array {
		$out = array();
		foreach ( wp_get_post_categories( $post_id ) as $cat ) {
			$chain = array_reverse( get_ancestors( (int) $cat, 'category', 'taxonomy' ) ); // از ریشه
			$chain[] = (int) $cat;
			foreach ( array_slice( $chain, 0, 2 ) as $t ) {
				$out[ (int) $t ] = true;
			}
		}
		unset( $out[ (int) get_option( 'default_category' ) ] );
		return array_keys( $out );
	}

	/** پس از ذخیرهٔ کامل نوشته (متا و دسته‌ها هم ذخیره شده‌اند): نقشهٔ پین‌ها به‌روز می‌شود. */
	public static function refresh( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$f   = self::front();
		$map = is_array( $f['map'] ?? null ) ? $f['map'] : array();
		$on  = (int) get_post_meta( $post_id, self::META, true );
		if ( $on && 'publish' === $post->post_status ) {
			$map[ $post_id ] = array(
				't' => $on,
				'c' => self::targets( $post_id ),
			);
		} else {
			unset( $map[ $post_id ] );
		}
		if ( ( $f['map'] ?? array() ) !== $map ) {
			$f['map'] = $map;
			update_option( self::FRONT, $f, true );
		}
	}

	public static function forget( int $post_id ): void {
		$f = self::front();
		if ( isset( $f['map'][ $post_id ] ) ) {
			unset( $f['map'][ $post_id ] );
			update_option( self::FRONT, $f, true );
		}
	}

	/* ---------- سایت ---------- */

	/** شناسهٔ نوشته‌های پین‌شدهٔ یک دسته، تازه‌ترین پین اول. */
	public static function for_term( int $term_id ): array {
		$f    = self::front();
		$hits = array();
		foreach ( (array) ( $f['map'] ?? array() ) as $pid => $row ) {
			if ( in_array( $term_id, (array) ( $row['c'] ?? array() ), true ) ) {
				$hits[ (int) $pid ] = (int) ( $row['t'] ?? 0 );
			}
		}
		arsort( $hits );
		return array_slice( array_keys( $hits ), 0, max( 1, (int) ( $f['max'] ?? 3 ) ) );
	}

	private static function is_target( \WP_Query $q ): bool {
		return $q->is_main_query() && $q->is_category() && ! is_admin() && ! $q->is_feed();
	}

	public static function pre_get_posts( \WP_Query $q ): void {
		if ( ! self::is_target( $q ) ) {
			return;
		}
		$term = $q->get_queried_object();
		if ( ! $term || empty( $term->term_id ) ) {
			return;
		}
		self::$current = self::for_term( (int) $term->term_id );
		if ( self::$current ) {
			// در صفحه‌های آرشیو تکراری نشوند؛ بالای صفحهٔ اول جداگانه می‌آیند.
			$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), self::$current ) );
		}
	}

	public static function the_posts( array $posts, \WP_Query $q ): array {
		if ( ! self::$current || ! self::is_target( $q ) || (int) $q->get( 'paged' ) > 1 ) {
			return $posts;
		}
		$pinned = get_posts(
			array(
				'post__in'            => self::$current,
				'orderby'             => 'post__in',
				'post_type'           => 'any',
				'post_status'         => 'publish',
				'posts_per_page'      => count( self::$current ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			)
		);
		return array_merge( $pinned, $posts );
	}

	public static function is_pinned_here( int $post_id ): bool {
		return self::$current && in_array( $post_id, self::$current, true );
	}

	/** نشان کنار عنوان، فقط داخل حلقهٔ آرشیو دسته. */
	public static function the_title( string $title, $post_id = 0 ): string {
		if ( ! in_the_loop() || ! is_main_query() || ! self::is_pinned_here( (int) $post_id ) ) {
			return $title;
		}
		$f = self::front();
		return '<span class="pin-badge">' . esc_html( (string) ( $f['label'] ?? '' ) ) . '</span> ' . $title;
	}

	public static function post_class( array $classes, $class, $post_id ): array {
		if ( self::is_pinned_here( (int) $post_id ) ) {
			$classes[] = 'is-pinned';
			$classes[] = 'sticky'; // بیشتر پوسته‌ها برای «sticky» ظاهر ویژه دارند.
		}
		return $classes;
	}

	public static function styles(): void {
		if ( ! is_category() || ! self::$current ) {
			return;
		}
		$f     = self::front();
		$color = sanitize_hex_color( (string) ( $f['color'] ?? '' ) );
		$color = $color ? $color : '#d63638';
		wp_register_style( 'pin-badge', false, array(), '1' );
		wp_enqueue_style( 'pin-badge' );
		wp_add_inline_style(
			'pin-badge',
			'.pin-badge{display:inline-flex;align-items:center;gap:.3em;margin-inline-end:.45em;padding:.2em .7em;border-radius:999px;background:' . $color . ';color:#fff;font-size:.62em;font-weight:700;line-height:1.6;vertical-align:middle;letter-spacing:0;box-shadow:0 1px 4px rgba(0,0,0,.15);white-space:nowrap}'
			. '.pin-badge::before{content:"";width:.5em;height:.5em;border-radius:50%;background:#fff;opacity:.9}'
		);
	}
}
