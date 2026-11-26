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
	const API_LIMIT = 20000;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'rokhdad_views';
	}

	public static function activate(): void {
		self::create_table();
		self::sync_schedule();
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
		wp_clear_scheduled_hook( self::CRON );
		if ( self::enabled() && self::config()['token'] && self::config()['panel_url'] ) {
			wp_schedule_single_event( self::next_run(), self::CRON );
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

	/** Run one complete sync. The next single event is always scheduled again. */
	public static function sync(): void {
		try {
			if ( self::enabled() ) {
				self::run_sync();
			}
		} catch ( \Throwable $e ) {
			self::set_state( array( 'last_error' => self::safe_error( $e->getMessage(), self::config()['token'] ) ) );
		} finally {
			self::sync_schedule();
		}
	}

	private static function run_sync(): void {
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
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 30,
					'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ),
				)
			);
			if ( is_wp_error( $response ) ) {
				throw new \RuntimeException( 'اتصال به پنل رخدادچکر برقرار نشد.' );
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( $status < 200 || $status >= 300 ) {
				throw new \RuntimeException( 'پنل رخدادچکر پاسخ معتبر نداد (HTTP ' . $status . ').' );
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || ! isset( $data['rows'] ) || ! is_array( $data['rows'] ) ) {
				throw new \RuntimeException( 'پاسخ بازدید ۹۰ روزه معتبر نیست.' );
			}
			$batch = $data['rows'];
			$rows = array_merge( $rows, $batch );
			$offset += count( $batch );
			$total = isset( $data['total'] ) ? max( 0, (int) $data['total'] ) : null;
		} while ( ! $single && count( $batch ) === $limit && ( null === $total || $offset < $total ) );
		return $rows;
	}

	private static function published_posts(): array {
		$map = array();
		$page = 1;
		do {
			$ids = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 2000,
					'paged'          => $page,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ( $ids as $post_id ) {
				$permalink = get_permalink( (int) $post_id );
				if ( $permalink ) {
					$map[ (int) $post_id ] = self::normalize_path( wp_parse_url( $permalink, PHP_URL_PATH ) ?: '/' );
				}
			}
			++$page;
		} while ( count( $ids ) === 2000 );
		return $map;
	}

	private static function replace_rows( array $values ): void {
		global $wpdb;
		$table = self::table();
		$now = current_time( 'mysql' );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		try {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
		$views = self::row_value( $post_id );
		echo null === $views ? '&mdash;' : esc_html( number_format_i18n( (int) $views ) );
	}

	public static function sortable( array $columns ): array {
		$columns[ self::COLUMN ] = self::COLUMN;
		return $columns;
	}

	public static function clauses( array $clauses, \WP_Query $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() || 'post' !== ( $query->get( 'post_type' ) ?: 'post' ) || ! self::can() ) {
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
