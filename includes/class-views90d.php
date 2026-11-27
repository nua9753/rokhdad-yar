<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * خواندن snapshot بازدید ۹۰ روزه‌ی رخدادچکر و نمایش آن در فهرست نوشته‌ها.
 * این ابزار عمداً هیچ داده‌ای را در post meta ذخیره نمی‌کند.
 */
final class Views90d {

	const TOOL = 'views_90d';
	const CRON = 'rokhdadyar_views90d_sync';
	const COLUMN = 'rokhdadyar_views90d';
	const API_LIMIT = 2000;
	const REQUEST_TIMEOUT = 60;
	const DB_VERSION = '1';
	const DB_OPTION = 'rokhdadyar_views_db_version';
	const LOCK = 'rokhdadyar_views90d_sync_lock';

	/** @var bool|null */
	private static $table_exists = null;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'rokhdad_views';
	}

	public static function activate(): void {
		self::ensure_db();
		self::sync_schedule();
	}

	/** Create/upgrade the table for sites upgraded from an older plugin version. */
	public static function maybe_upgrade(): void {
		self::ensure_db();
	}

	private static function ensure_db(): void {
		$version = (string) get_option( self::DB_OPTION, '' );
		if ( self::DB_VERSION !== $version || ! self::table_exists() ) {
			self::create_table();
			update_option( self::DB_OPTION, self::DB_VERSION, false );
		}
	}

	public static function create_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			post_id bigint(20) unsigned NOT NULL,
			views int(10) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (post_id),
			KEY updated_at (updated_at)
		) {$charset};";
		dbDelta( $sql );
		self::$table_exists = null;
	}

	private static function table_exists(): bool {
		if ( null !== self::$table_exists ) {
			return self::$table_exists;
		}
		global $wpdb;
		$table = self::table();
		$like  = $wpdb->esc_like( $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::$table_exists = ( $table === $found );
		return self::$table_exists;
	}

	public static function config(): array {
		$c = Toolbox::config()[ self::TOOL ] ?? array();
		return array_merge(
			array(
				'on'         => 0,
				'roles'      => array( 'editor' ),
				'panel_url'  => 'https://amar.rokhdad.site',
				'token'      => '',
				'last_sync'  => '',
				'last_count' => 0,
				'last_error' => '',
			),
			(array) $c
		);
	}

	public static function enabled(): bool {
		return ! empty( self::config()['on'] );
	}

	public static function can(): bool {
		return Toolbox::can( self::TOOL );
	}

	public static function sanitize_url( $url ): string {
		$url = esc_url_raw( trim( (string) $url ) );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) ? untrailingslashit( $url ) : 'https://amar.rokhdad.site';
	}

	public static function set_state( array $state ): void {
		Settings::update_tool_state( self::TOOL, $state );
	}

	public static function sync_schedule(): void {
		if ( ! self::enabled() || ! self::config()['panel_url'] ) {
			wp_clear_scheduled_hook( self::CRON );
			return;
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( self::next_run(), 'daily', self::CRON );
		}
	}

	private static function next_run(): int {
		$tz = wp_timezone();
		$now = new \DateTimeImmutable( 'now', $tz );
		$next = $now->setTime( 3, 0, 0 );
		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}
		return $next->getTimestamp();
	}

	/** Run one complete sync. The daily event remains scheduled even if this run fails. */
	public static function sync(): array {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			@wp_raise_memory_limit( 'cron' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		self::ensure_db();
		if ( get_transient( self::LOCK ) ) {
			return array( 'ok' => false, 'locked' => true, 'message' => 'همگام‌سازی در حال اجراست.' );
		}
		set_transient( self::LOCK, 1, 30 * MINUTE_IN_SECONDS );
		try {
			if ( ! self::enabled() ) {
				return array( 'ok' => false, 'message' => 'ابزار بازدید ۹۰ روزه خاموش است.' );
			}
			return array( 'ok' => true, 'count' => self::run_sync() );
		} catch ( \Throwable $e ) {
			$message = self::safe_error( $e->getMessage(), self::config()['token'] );
			self::set_state( array( 'last_error' => $message ) );
			return array( 'ok' => false, 'message' => $message );
		} finally {
			delete_transient( self::LOCK );
		}
	}

	private static function run_sync(): int {
		$config = self::config();
		if ( ! $config['token'] ) {
			throw new \RuntimeException( 'توکن رخدادچکر تنظیم نشده است.' );
		}
		$rows = self::fetch_all( $config['panel_url'], $config['token'] );
		$remote = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row[0] ) ) {
				continue;
			}
			$path = self::normalize_path( $row[0] );
			$views = max( 0, (int) ( $row[1] ?? 0 ) );
			$remote[ $path ] = ( $remote[ $path ] ?? 0 ) + $views;
		}

		$posts = self::published_posts();
		$values = array();
		foreach ( $posts as $post_id => $path ) {
			$values[] = array( (int) $post_id, (int) ( $remote[ $path ] ?? 0 ) );
		}
		self::replace_rows( $values );
		self::set_state(
			array(
				'last_sync'  => current_time( 'mysql' ),
				'last_count' => count( $values ),
				'last_error' => '',
			)
		);
		return count( $values );
	}

	private static function fetch_all( string $panel_url, string $token, int $limit = self::API_LIMIT, bool $single = false ): array {
		$rows = array();
		$offset = 0;
		$limit = max( 1, min( self::API_LIMIT, $limit ) );
		do {
			$url = add_query_arg(
				array( 'offset' => $offset, 'limit' => $limit ),
				untrailingslashit( $panel_url ) . '/api/wp/page-views'
			);
			$data = self::fetch_page( $url, $token, $offset );
			if ( ! isset( $data['rows'] ) || ! is_array( $data['rows'] ) ) {
				throw new \RuntimeException( 'offset ' . $offset . ': پاسخ بازدید ۹۰ روزه معتبر نیست.' );
			}
			$batch = $data['rows'];
			$rows = array_merge( $rows, $batch );
			$offset += count( $batch );
			$total = isset( $data['total'] ) ? max( 0, (int) $data['total'] ) : null;
		} while ( ! $single && count( $batch ) === $limit && ( null === $total || $offset < $total ) );
		return $rows;
	}

	private static function fetch_page( string $url, string $token, int $offset ): array {
		$last_error = '';
		for ( $attempt = 1; $attempt <= 3; ++$attempt ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => self::REQUEST_TIMEOUT,
					'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ),
				)
			);
			$retry = false;
			if ( is_wp_error( $response ) ) {
				$code = (string) $response->get_error_code();
				$text = (string) $response->get_error_message();
				$last_error = $code ? $code . ': ' . $text : $text;
				$retry = true;
			} else {
				$status = (int) wp_remote_retrieve_response_code( $response );
				if ( $status >= 200 && $status < 300 ) {
					$data = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( is_array( $data ) ) {
						return $data;
					}
					throw new \RuntimeException( 'offset ' . $offset . ': پاسخ JSON پنل رخدادچکر معتبر نیست.' );
				}
				$reason = trim( (string) wp_remote_retrieve_response_message( $response ) );
				$last_error = 'HTTP ' . $status . ( $reason ? ' ' . $reason : '' );
				$retry = 429 === $status || $status >= 500;
			}
			if ( ! $retry || 3 === $attempt ) {
				throw new \RuntimeException( 'offset ' . $offset . ': ' . $last_error );
			}
			sleep( 1 === $attempt ? 2 : 5 );
		}
		throw new \RuntimeException( 'offset ' . $offset . ': درخواست پنل ناموفق بود.' );
	}

	private static function published_posts(): array {
		global $wpdb;
		$map = array();
		$last_id = 0;
		do {
			$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT 2000",
					$last_id
				)
			);
			$ids = array_map( 'intval', (array) $ids );
			if ( $ids && function_exists( '_prime_post_caches' ) ) {
				_prime_post_caches( $ids, true, true );
			}
			foreach ( $ids as $post_id ) {
				$permalink = get_permalink( (int) $post_id );
				if ( $permalink ) {
					$map[ (int) $post_id ] = self::normalize_path( wp_parse_url( $permalink, PHP_URL_PATH ) ?: '/' );
				}
			}
			if ( $ids ) {
				$last_id = (int) end( $ids );
			}
			if ( $ids && function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			} else {
				foreach ( $ids as $post_id ) {
					clean_post_cache( (int) $post_id );
				}
			}
		} while ( count( $ids ) === 2000 );
		return $map;
	}

	private static function replace_rows( array $values ): void {
		global $wpdb;
		$table = self::table();
		$now = current_time( 'mysql' );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		try {
			if ( false === $wpdb->query( "DELETE FROM {$table}" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				throw new \RuntimeException( 'حذف داده‌های قبلی بازدید انجام نشد.' );
			}
			foreach ( array_chunk( $values, 1000 ) as $chunk ) {
				$placeholders = array();
				$args = array();
				foreach ( $chunk as $value ) {
					$placeholders[] = '(%d, %d, %s)';
					$args[] = (int) $value[0];
					$args[] = (int) $value[1];
					$args[] = $now;
				}
				$sql = "INSERT INTO {$table} (post_id, views, updated_at) VALUES " . implode( ',', $placeholders )
					. ' ON DUPLICATE KEY UPDATE views = VALUES(views), updated_at = VALUES(updated_at)';
				if ( false === $wpdb->query( $wpdb->prepare( $sql, $args ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					throw new \RuntimeException( 'ذخیره‌ی بازدیدها انجام نشد.' );
				}
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw $e;
		}
	}

	public static function normalize_path( $raw ): string {
		$path = rawurldecode( (string) $raw );
		$cut = strcspn( $path, '?#' );
		$path = substr( $path, 0, $cut );
		if ( '' === $path ) {
			$path = '/';
		}
		return '/' === $path ? '/' : untrailingslashit( $path );
	}

	public static function register_rest(): void {
		register_rest_route(
			Rest::NS,
			'/views90d/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'test_connection' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
		register_rest_route(
			Rest::NS,
			'/views90d/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'manual_sync' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public static function manual_sync(): \WP_REST_Response {
		$result = self::sync();
		if ( ! empty( $result['ok'] ) ) {
			return new \WP_REST_Response( $result, 200 );
		}
		return new \WP_REST_Response( $result, ! empty( $result['locked'] ) ? 409 : 502 );
	}

	public static function test_connection( \WP_REST_Request $request ): \WP_REST_Response {
		$config = self::config();
		$panel_url = self::sanitize_url( $request->get_param( 'panel_url' ) ?: $config['panel_url'] );
		$token = trim( sanitize_text_field( (string) ( $request->get_param( 'token' ) ?: $config['token'] ) ) );
		try {
			if ( ! $token ) {
				throw new \RuntimeException( 'توکن رخدادچکر تنظیم نشده است.' );
			}
			$rows = self::fetch_all( $panel_url, $token, 1, true );
			return new \WP_REST_Response( array( 'ok' => true, 'count' => count( $rows ) ), 200 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => self::safe_error( $e->getMessage(), $token ) ), 502 );
		}
	}

	private static function safe_error( $message, string $token = '' ): string {
		$message = sanitize_text_field( (string) $message );
		if ( $token ) {
			$message = str_replace( $token, '[token]', $message );
		}
		return mb_substr( $message, 0, 240, 'UTF-8' );
	}

	/* ---------- ستون فهرست نوشته‌ها ---------- */

	public static function list_hooks(): void {
		// phpcs:ignore WordPress.Security.NonceVerification
		$type = sanitize_key( $_REQUEST['post_type'] ?? 'post' );
		if ( 'post' !== $type || ! self::can() ) {
			return;
		}
		add_filter( 'manage_post_posts_columns', array( __CLASS__, 'column_head' ) );
		add_action( 'manage_post_posts_custom_column', array( __CLASS__, 'column_cell' ), 10, 2 );
		add_filter( 'manage_edit-post_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_filter( 'posts_clauses', array( __CLASS__, 'clauses' ), 20, 2 );
	}

	public static function column_head( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out[ self::COLUMN ] = __( 'بازدید ۹۰ روز', 'rokhdadyar' );
			}
		}
		return $out;
	}

	public static function column_cell( string $column, int $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}
		$views = self::table_exists() ? self::row_value( $post_id ) : null;
		echo null === $views ? '&mdash;' : esc_html( number_format_i18n( (int) $views ) );
	}

	public static function sortable( array $columns ): array {
		$columns[ self::COLUMN ] = self::COLUMN;
		return $columns;
	}

	public static function clauses( array $clauses, \WP_Query $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() || 'post' !== ( $query->get( 'post_type' ) ?: 'post' ) || ! self::can() || ! self::table_exists() ) {
			return $clauses;
		}
		global $wpdb;
		$table = self::table();
		$alias = 'rokhdadyar_views90d';
		if ( false === strpos( (string) $clauses['join'], ' ' . $alias . ' ' ) ) {
			$clauses['join'] .= " LEFT JOIN {$table} AS {$alias} ON {$alias}.post_id = {$wpdb->posts}.ID";
			$clauses['fields'] .= ", {$alias}.views AS rokhdadyar_views90d_value";
		}
		if ( self::COLUMN === $query->get( 'orderby' ) ) {
			$direction = 'asc' === strtolower( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';
			$clauses['orderby'] = "CASE WHEN {$alias}.post_id IS NULL THEN 1 ELSE 0 END ASC, {$alias}.views {$direction}";
		}
		return $clauses;
	}

	private static function row_value( int $post_id ) {
		global $wp_query;
		foreach ( (array) ( $wp_query->posts ?? array() ) as $post ) {
			if ( (int) ( $post->ID ?? 0 ) === $post_id && property_exists( $post, 'rokhdadyar_views90d_value' ) ) {
				return null === $post->rokhdadyar_views90d_value ? null : (int) $post->rokhdadyar_views90d_value;
			}
		}
		return null;
	}
}
