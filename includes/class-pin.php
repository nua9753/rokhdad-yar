<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * پین در دسته‌بندی.
 *
 * ذخیره: هر بار که نوشته‌ای ذخیره می‌شود، فهرست «دسته‌هایی که این نوشته در آن‌ها پین است» (فقط سطح اول و دوم درخت)
 * در یک گزینهٔ کوچکِ autoload حساب و نگه داشته می‌شود. پس در سایت هیچ کوئری اضافه‌ای برای پیدا کردن پین‌ها زده نمی‌شود.
 *
 * نمایش: هر کوئری‌ای که نوشته‌های یک دسته را فهرست می‌کند (آرشیو اصلی، المنتور، حلقه‌های خودِ پوسته) در SQL طوری مرتب می‌شود
 * که پین‌ها اول بیایند؛ نشان «مطلب مهم» کنار عنوانشان می‌نشیند. در خروجی سایت هیچ نامی از افزونه نیست.
 */
final class Pin {

	const META  = '_rokhdadyar_pin';
	const FRONT = 'rokhdadyar_front'; // گزینهٔ کوچکِ autoload برای سایت: تنظیمات نمایش + نقشهٔ پین‌ها

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
		$old = $f['map'][ $post_id ]['c'] ?? array();
		if ( ( $f['map'] ?? array() ) !== $map ) {
			$f['map'] = $map;
			update_option( self::FRONT, $f, true );
			self::purge( array_merge( (array) $old, (array) ( $map[ $post_id ]['c'] ?? array() ) ) );
		}
	}

	public static function forget( int $post_id ): void {
		$f = self::front();
		if ( isset( $f['map'][ $post_id ] ) ) {
			$old = (array) ( $f['map'][ $post_id ]['c'] ?? array() );
			unset( $f['map'][ $post_id ] );
			update_option( self::FRONT, $f, true );
			self::purge( $old );
		}
	}

	/** برداشتن پین از مرکز کنترل. */
	public static function unpin( int $post_id ): void {
		delete_post_meta( $post_id, self::META );
		self::forget( $post_id );
	}

	/* ---------- سایت ---------- */

	/** @var int[] نوشته‌هایی که در همین صفحه بالای یک فهرست دسته آمده‌اند (برای نشان). */
	private static $shown = array();

	/** شناسهٔ نوشته‌های پین‌شدهٔ یک دسته، تازه‌ترین پین اول. */
	public static function for_term( int $term_id ): array {
		$f    = self::front();
		$hits = array();
		foreach ( (array) ( $f['map'] ?? array() ) as $pid => $row ) {
			if ( in_array( $term_id, array_map( 'intval', (array) ( $row['c'] ?? array() ) ), true ) ) {
				$hits[ (int) $pid ] = (int) ( $row['t'] ?? 0 );
			}
		}
		arsort( $hits );
		return array_slice( array_keys( $hits ), 0, max( 1, (int) ( $f['max'] ?? 3 ) ) );
	}

	/**
	 * دسته‌ای که یک کوئری فهرستش را می‌خواهد (فقط وقتی دقیقاً یک دسته است).
	 * هم کوئری اصلی آرشیو، هم کوئری‌های جدای پوسته و صفحه‌سازها (المنتور و …) که «نوشته‌های همین دسته» را می‌آورند.
	 */
	private static function term_of( \WP_Query $q ): int {
		if ( is_admin() || $q->is_singular() || $q->is_feed() || $q->is_search() ) {
			return 0;
		}
		$order = $q->get( 'orderby' );
		if ( in_array( $order, array( 'rand', 'post__in', 'none' ), true ) || ( is_string( $order ) && 0 === strpos( $order, 'RAND' ) ) ) {
			return 0;
		}
		if ( ! empty( $q->tax_query->queried_terms['category'] ) ) {
			$qt    = $q->tax_query->queried_terms['category'];
			$terms = (array) ( $qt['terms'] ?? array() );
			if ( 1 !== count( $terms ) ) {
				return 0;
			}
			$t = 'term_id' === ( $qt['field'] ?? '' ) ? get_term( (int) $terms[0], 'category' ) : get_term_by( (string) ( $qt['field'] ?? 'slug' ), (string) $terms[0], 'category' );
			return $t && ! is_wp_error( $t ) ? (int) $t->term_id : 0;
		}
		return 0;
	}

	/**
	 * مرتب‌سازی در خودِ SQL: نوشته‌های پین‌شده اول می‌آیند و بقیه به ترتیب همیشگی.
	 * چون چیزی از نتیجه حذف یا به آن اضافه نمی‌شود، صفحه‌بندی و تعداد درست می‌ماند و نوشته تکراری نمی‌شود.
	 */
	public static function clauses( array $c, \WP_Query $q ): array {
		$term = self::term_of( $q );
		$ids  = $term ? self::for_term( $term ) : array();
		if ( ! $ids ) {
			return $c;
		}
		global $wpdb;
		$list = implode( ',', array_map( 'intval', array_reverse( $ids ) ) ); // FIELD … DESC: آخرینِ فهرست اول می‌آید.
		$c['orderby'] = "FIELD({$wpdb->posts}.ID, {$list}) DESC" . ( '' !== trim( (string) $c['orderby'] ) ? ', ' . $c['orderby'] : '' );
		$q->set( 'pinned_ids', $ids );
		self::$shown = array_values( array_unique( array_merge( self::$shown, $ids ) ) );
		return $c;
	}

	/** نشان کنار عنوان در حلقهٔ اصلی پوسته؛ برای حلقه‌های دیگر، اسکریپت پایین صفحه نشان را می‌گذارد. */
	public static function the_title( string $title, $post_id = 0 ): string {
		if ( ! self::$shown || ! in_the_loop() || ! in_array( (int) $post_id, self::$shown, true ) || false !== strpos( $title, 'pin-badge' ) ) {
			return $title;
		}
		$f = self::front();
		return '<span class="pin-badge">' . esc_html( (string) ( $f['label'] ?? '' ) ) . '</span> ' . $title;
	}

	public static function post_class( array $classes, $class, $post_id ): array {
		if ( self::$shown && in_array( (int) $post_id, self::$shown, true ) ) {
			$classes[] = 'is-pinned';
			$classes[] = 'sticky'; // بیشتر پوسته‌ها برای «sticky» ظاهر ویژه دارند.
		}
		return $classes;
	}

	/** استایل نشان: در head همهٔ صفحه‌های فهرستی (سبک: چند خط CSS). */
	public static function styles(): void {
		if ( is_admin() || is_singular() ) {
			return;
		}
		$f     = self::front();
		$color = sanitize_hex_color( (string) ( $f['color'] ?? '' ) );
		$color = $color ? $color : '#d63638';
		wp_register_style( 'pin-badge', false, array(), '2' );
		wp_enqueue_style( 'pin-badge' );
		wp_add_inline_style(
			'pin-badge',
			'.pin-badge{display:inline-flex;align-items:center;gap:.3em;margin-inline-end:.45em;padding:.2em .7em;border-radius:999px;background:' . $color . ';color:#fff!important;font-size:.62em;font-weight:700;line-height:1.6;vertical-align:middle;letter-spacing:0;text-decoration:none!important;box-shadow:0 1px 4px rgba(0,0,0,.15);white-space:nowrap}'
			. '.pin-badge::before{content:"";width:.5em;height:.5em;border-radius:50%;background:#fff;opacity:.9}'
		);
	}

	/**
	 * برای حلقه‌هایی که عنوان را از راه دیگری چاپ می‌کنند (صفحه‌سازها، پوسته‌های خاص):
	 * نشان کنار اولین عنوانِ کارتِ همان نوشته گذاشته می‌شود. فقط وقتی در این صفحه پینی نشان داده شده باشد.
	 */
	public static function footer_script(): void {
		if ( ! self::$shown ) {
			return;
		}
		$f    = self::front();
		$data = array(
			'ids'   => self::$shown,
			'label' => (string) ( $f['label'] ?? '' ),
			'urls'  => array_values( array_filter( array_map( 'get_permalink', self::$shown ) ) ),
		);
		echo '<script>(function(d){var c=' . wp_json_encode( $data ) . ';'
			. 'function badge(){var s=d.createElement("span");s.className="pin-badge";s.textContent=c.label;return s;}'
			. 'function mark(h){if(!h||h.querySelector(".pin-badge")||(h.parentNode&&h.parentNode.querySelector&&h.parentNode.querySelector(":scope > .pin-badge")))return;var a=h.querySelector("a")||h;a.insertBefore(d.createTextNode(" "),a.firstChild);a.insertBefore(badge(),a.firstChild);}'
			. 'var seen=[];'
			// ۱) کارت‌هایی که کلاس post-ID دارند (تقریباً همهٔ پوسته‌ها و المنتور).
			. 'c.ids.forEach(function(id){d.querySelectorAll(".post-"+id+",#post-"+id+",[data-id=\"post-"+id+"\"]").forEach(function(el){var h=el.querySelector("h1,h2,h3,h4,.entry-title,.post-title,.elementor-post__title,.wp-block-post-title");if(h){mark(h);seen.push(h);}});});'
			// ۲) هر عنوانی که پیوندش به نشانی همان نوشته است.
			. 'c.urls.forEach(function(u){d.querySelectorAll("h1 a[href],h2 a[href],h3 a[href],h4 a[href]").forEach(function(a){if(a.href.replace(/\/$/,"")===u.replace(/\/$/,"")){var h=a.closest("h1,h2,h3,h4");if(seen.indexOf(h)<0)mark(h);}});});'
			. '})(document);</script>' . "\n";
	}

	/* ---------- کش صفحه ---------- */

	/** وقتی پینی عوض می‌شود، صفحهٔ دسته‌های مربوط از کش افزونه‌های رایج پاک می‌شود تا ترتیب تازه فوراً دیده شود. */
	public static function purge( array $terms ): void {
		$urls = array();
		foreach ( array_unique( array_map( 'intval', $terms ) ) as $t ) {
			$link = get_term_link( $t, 'category' );
			if ( ! is_wp_error( $link ) ) {
				$urls[] = $link;
			}
			clean_term_cache( $t, 'category' );
		}
		if ( ! $urls ) {
			return;
		}
		foreach ( $urls as $u ) {
			do_action( 'litespeed_purge_url', $u );               // LiteSpeed Cache
			if ( function_exists( 'w3tc_flush_url' ) ) {
				w3tc_flush_url( $u );                              // W3 Total Cache
			}
			if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
				sg_cachepress_purge_cache( $u );                   // SiteGround
			}
			if ( function_exists( 'wpsc_delete_url_cache' ) ) {
				wpsc_delete_url_cache( $u );                       // WP Super Cache
			}
		}
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $urls );                           // WP Rocket
		}
		if ( class_exists( 'WpFastestCache' ) && isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache();           // WP Fastest Cache (همهٔ کش؛ راه دقیق‌تری ندارد)
		}
		do_action( 'rokhdadyar_pins_changed', $urls );             // برای کش‌های سفارشی
	}
}
