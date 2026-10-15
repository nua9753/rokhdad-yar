<?php
namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

/**
 * همهٔ چیزهای پیشخوان. هر بخش فقط روی صفحهٔ خودش فعال می‌شود.
 */
final class Admin {

	const PAGE = 'rokhdadyar';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_rokhdadyar_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_rokhdadyar_unpin', array( __CLASS__, 'unpin' ) );
		add_action( 'admin_post_rokhdadyar_unimportant', array( __CLASS__, 'unimportant' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ROKHDADYAR_FILE ), array( __CLASS__, 'action_links' ) );

		// ستون فهرست نوشته‌ها: فقط روی edit.php و هنگام «ویرایش سریع».
		add_action( 'load-edit.php', array( __CLASS__, 'list_hooks' ) );
		add_action( 'wp_ajax_inline-save', array( __CLASS__, 'list_hooks' ), 0 );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'guard_assets' ) );
		add_action( 'admin_notices', array( 'Rokhdadyar\\Guard', 'notice' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'classic_box' ) );
		add_filter( 'mce_external_plugins', array( __CLASS__, 'mce_plugin' ) );
		add_filter( 'mce_buttons', array( __CLASS__, 'mce_button' ) );
		add_action( 'add_meta_boxes', array( 'Rokhdadyar\\Toolbox', 'classic_box' ) );
		add_action( 'save_post', array( 'Rokhdadyar\\Toolbox', 'classic_save' ) );
		add_action( 'edit_form_before_permalink', array( __CLASS__, 'classic_title_button' ) );
	}

	/* ---------- مرکز کنترل ---------- */

	public static function menu(): void {
		add_options_page(
			__( 'رخدادیار — مرکز کنترل', 'rokhdadyar' ),
			__( 'رخدادیار', 'rokhdadyar' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
		// نقش‌هایی که به مرکز کنترل راه ندارند ولی اجازهٔ دیدن گزارش را گرفته‌اند، صفحهٔ جدای خودشان را دارند.
		if ( ! current_user_can( 'manage_options' ) && Access::can( 'usage' ) ) {
			add_submenu_page(
				'index.php',
				__( 'رخدادیار — گزارش مصرف', 'rokhdadyar' ),
				__( 'مصرف رخدادیار', 'rokhdadyar' ),
				'edit_posts',
				self::PAGE . '-usage',
				array( __CLASS__, 'render_usage' )
			);
		}
	}

	public static function render_usage(): void {
		if ( ! Access::can( 'usage' ) ) {
			return;
		}
		$chk_base = admin_url( 'index.php?page=' . self::PAGE . '-usage' );
		echo '<div class="wrap chk-wrap"><header class="chk-head"><div><h1><img class="chk-logo" src="' . esc_url( plugins_url( 'assets/logo.svg', ROKHDADYAR_FILE ) ) . '" height="44" alt="' . esc_attr__( 'رخدادیار', 'rokhdadyar' ) . '"></h1><p>' . esc_html__( 'گزارش مصرف', 'rokhdadyar' ) . '</p></div></header>';
		require ROKHDADYAR_DIR . 'views/usage.php';
		echo '</div>';
	}

	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'مرکز کنترل', 'rokhdadyar' ) . '</a>' );
		return $links;
	}

	public static function url(): string {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = Settings::get();
		require ROKHDADYAR_DIR . 'views/settings.php';
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'rokhdadyar' ), 403 );
		}
		check_admin_referer( 'rokhdadyar_save' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- در Settings::update پاک‌سازی می‌شود.
		Settings::update( isset( $_POST['chk'] ) && is_array( $_POST['chk'] ) ? wp_unslash( $_POST['chk'] ) : array() );
		wp_safe_redirect( add_query_arg( 'updated', '1', self::url() ) );
		exit;
	}

	/** «برداشتن پین» از فهرست مطلب‌های پین‌شده در تب جعبه ابزار. */
	public static function unpin(): void {
		$id = (int) ( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( 'rokhdadyar_unpin_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'rokhdadyar' ), 403 );
		}
		Pin::unpin( $id );
		wp_safe_redirect( self::url() . '#toolbox' );
		exit;
	}

	public static function unimportant(): void {
		$id = (int) ( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( 'rokhdadyar_unimportant_' . $id );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'rokhdadyar' ), 403 );
		}
		Links::mark( $id, false );
		wp_safe_redirect( self::url() . '#toolbox' );
		exit;
	}

	/* ---------- فهرست نوشته‌ها ---------- */

	public static function list_hooks(): void {
		// phpcs:ignore WordPress.Security.NonceVerification
		$type = sanitize_key( $_REQUEST['post_type'] ?? 'post' );
		if ( ! in_array( $type, (array) Settings::get( 'post_types' ), true ) || Access::none() ) {
			return;
		}
		add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'column_head' ) );
		add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'column_cell' ), 10, 2 );
	}

	/** ستون باریک درست بعد از عنوان. */
	public static function column_head( array $cols ): array {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['rokhdadyar'] = '<span class="chk-col-head" title="' . esc_attr__( 'عنوان و چکیده با هوش مصنوعی', 'rokhdadyar' ) . '">' . self::logo( 16 ) . '</span>';
			}
		}
		return $out;
	}

	public static function column_cell( string $col, int $post_id ): void {
		if ( 'rokhdadyar' !== $col || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		$text = Access::can( 'text' );
		echo '<div class="chk-actions">';
		if ( $text ) {
			self::button( 'title', $post_id, __( 'ساخت عنوان با هوش مصنوعی', 'rokhdadyar' ), 'T' );
		}
		if ( $text && post_type_supports( $post->post_type, 'excerpt' ) ) {
			$has = '' !== trim( $post->post_excerpt );
			self::button(
				'excerpt',
				$post_id,
				$has ? __( 'بازنویسی چکیده با هوش مصنوعی', 'rokhdadyar' ) : __( 'این نوشته چکیده ندارد — ساخت با هوش مصنوعی', 'rokhdadyar' ),
				'¶',
				$has ? '' : ' chk-btn--missing'
			);
		}
		if ( $text && Settings::get( 'slug_enabled' ) ) {
			self::button( 'slug', $post_id, __( 'ساخت نامک (slug) با هوش مصنوعی', 'rokhdadyar' ), '/' );
		}
		if ( $text && Terms::enabled( $post->post_type ) ) {
			self::button( 'terms', $post_id, __( 'پیشنهاد دسته و برچسب با هوش مصنوعی', 'rokhdadyar' ), '#' );
		}
		if ( $text && Toolbox::links_on() ) {
			self::button( 'links', $post_id, __( 'پیشنهاد لینک داخلی به مطالب مهم', 'rokhdadyar' ), '' );
		}
		self::image_button( $post );
		// دکمهٔ تاریخچه همیشه هست، ولی تا وقتی چیزی برای برگرداندن نباشد پنهان می‌ماند.
		if ( $text ) {
			self::button( 'history', $post_id, __( 'تاریخچه: برگرداندن عنوان، چکیده یا نامک قبلی', 'rokhdadyar' ), '↺', History::has( $post_id ) ? ' chk-btn--plain' : ' chk-btn--plain chk-btn--hidden' );
		}
		echo '</div>';
	}

	/** دکمهٔ تصویر شاخص؛ فقط وقتی در مرکز کنترل روشن و کامل باشد. */
	private static function image_button( \WP_Post $post, string $extra = '', string $text = '' ): void {
		if ( ! Image::ready() || ! Access::can( 'image' ) || ! post_type_supports( $post->post_type, 'thumbnail' ) || ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$has = (bool) get_post_meta( $post->ID, '_thumbnail_id', true );
		self::button(
			'image',
			$post->ID,
			$has ? __( 'ساخت تصویر شاخص تازه با هوش مصنوعی', 'rokhdadyar' ) : __( 'این نوشته تصویر شاخص ندارد — ساخت با هوش مصنوعی', 'rokhdadyar' ),
			'',
			$extra . ( $has ? '' : ' chk-btn--missing' ),
			$text
		);
	}

	private static function button( string $task, int $post_id, string $label, string $glyph, string $extra = '', string $text = '' ): void {
		if ( Made::has( $post_id, $task ) ) {
			// قبلاً یک بار با افزونه ساخته شده: دکمه رنگی می‌شود.
			$extra .= ' is-made';
			$label .= ' — ' . __( 'قبلاً ساخته شده', 'rokhdadyar' );
		}
		printf(
			'<button type="button" class="chk-btn%5$s" data-chk-task="%1$s" data-chk-post="%2$d" data-chk-kw="%8$s" data-chk-pub="%9$d" aria-label="%3$s" title="%3$s">%6$s<span class="chk-btn__glyph" aria-hidden="true">%4$s</span>%7$s</button>',
			esc_attr( $task ),
			(int) $post_id,
			esc_attr( $label ),
			'links' === $task ? '<svg viewBox="0 0 16 16" width="14" height="14" focusable="false"><path d="M6.6 9.4a2.6 2.6 0 0 0 3.7 0l2.2-2.2a2.6 2.6 0 0 0-3.7-3.7l-.9.9M9.4 6.6a2.6 2.6 0 0 0-3.7 0L3.5 8.8a2.6 2.6 0 0 0 3.7 3.7l.9-.9" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>' : ( 'image' === $task ? '<svg viewBox="0 0 16 16" width="14" height="14" focusable="false"><rect x="1.5" y="2.5" width="13" height="11" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.4"/><circle cx="5.2" cy="6" r="1.2" fill="currentColor"/><path d="M2 12l3.5-3.5 2.5 2.5 2.5-3L14 11.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>' : esc_html( $glyph ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_attr( $extra ),
			self::icon(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت.
			'' !== $text ? '<span class="chk-btn__text">' . esc_html( $text ) . '</span>' : '',
			esc_attr( Extractor::keywords( $post_id ) ), // متای نوشته‌های فهرست از قبل در حافظه است؛ کوئری تازه‌ای نمی‌زند.
			'publish' === get_post_status( $post_id ) ? 1 : 0
		);
	}

	public static function icon(): string {
		return '<svg class="chk-spark" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 0l1.7 4.9a2.2 2.2 0 0 0 1.4 1.4L16 8l-4.9 1.7a2.2 2.2 0 0 0-1.4 1.4L8 16l-1.7-4.9a2.2 2.2 0 0 0-1.4-1.4L0 8l4.9-1.7a2.2 2.2 0 0 0 1.4-1.4z"/></svg>';
	}

	/* ---------- فایل‌ها ---------- */

	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$url = plugin_dir_url( ROKHDADYAR_FILE ) . 'assets/';

		if ( 'dashboard_page_' . self::PAGE . '-usage' === $hook ) {
			wp_enqueue_style( 'rokhdadyar', $url . 'admin.css', array(), ROKHDADYAR_VER );
			return;
		}
		if ( 'settings_page_' . self::PAGE === $hook ) {
			wp_enqueue_style( 'rokhdadyar', $url . 'admin.css', array(), ROKHDADYAR_VER );
			wp_enqueue_script( 'rokhdadyar-settings', $url . 'settings.js', array(), ROKHDADYAR_VER, true );
			wp_localize_script( 'rokhdadyar-settings', 'RokhdadyarCfg', self::config() );
			return;
		}

		$is_list = 'edit.php' === $hook;
		$is_edit = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		if ( ( ! $is_list && ! $is_edit ) || ! in_array( $screen->post_type, (array) Settings::get( 'post_types' ), true ) || Access::none() ) {
			return;
		}
		wp_enqueue_style( 'rokhdadyar', $url . 'admin.css', array(), ROKHDADYAR_VER );

		// ویرایشگر بلوکی فایل خودش را دارد (block_assets).
		if ( $is_edit && $screen->is_block_editor() ) {
			return;
		}
		wp_enqueue_script( 'rokhdadyar-admin', $url . 'admin.js', array(), ROKHDADYAR_VER, true );
		wp_localize_script( 'rokhdadyar-admin', 'RokhdadyarCfg', self::config() );
		// ماژول‌ها کوچک‌اند و فقط روی همین دو صفحه بار می‌شوند؛ تصویر و دسته فقط وقتی آن بخش برای این کاربر روشن است.
		wp_enqueue_script( 'rokhdadyar-admin-text', $url . 'admin-text.js', array( 'rokhdadyar-admin' ), ROKHDADYAR_VER, true );
		if ( Image::ready() && Access::can( 'image' ) ) {
			wp_enqueue_script( 'rokhdadyar-admin-image', $url . 'admin-image.js', array( 'rokhdadyar-admin' ), ROKHDADYAR_VER, true );
		}
		if ( Access::can( 'text' ) && Terms::enabled( $screen->post_type ) ) {
			wp_enqueue_script( 'rokhdadyar-admin-terms', $url . 'admin-terms.js', array( 'rokhdadyar-admin' ), ROKHDADYAR_VER, true );
		}
		if ( $is_edit && self::inline_on() ) {
			wp_enqueue_script( 'rokhdadyar-classic-rewrite', $url . 'classic-rewrite.js', array( 'rokhdadyar-admin', 'quicktags' ), ROKHDADYAR_VER, true );
		}
		if ( Access::can( 'text' ) && Toolbox::links_on() ) {
			wp_enqueue_script( 'rokhdadyar-admin-links', $url . 'admin-links.js', array( 'rokhdadyar-admin' ), ROKHDADYAR_VER, true );
		}
	}

	/** بازنویسی پاراگراف در دل متن: برای این کاربر و این صفحه روشن است؟ */
	private static function inline_on(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'post' === $screen->base && Settings::get( 'inline' ) && Settings::get( 'profiles' )
			&& Access::can( 'text' ) && in_array( $screen->post_type, (array) Settings::get( 'post_types' ), true );
	}

	public static function mce_plugin( $plugins ) {
		if ( self::inline_on() ) {
			$plugins['rokhdadyar'] = plugin_dir_url( ROKHDADYAR_FILE ) . 'assets/mce-rewrite.js?ver=' . ROKHDADYAR_VER;
		}
		return $plugins;
	}

	public static function mce_button( $buttons ) {
		if ( self::inline_on() ) {
			$buttons[] = 'rokhdadyar';
		}
		return $buttons;
	}

	/** محافظ نامک: مستقل از دسترسی به ابزارهای افزونه، برای هر کسی که نوشته را ویرایش می‌کند. */
	public static function guard_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || 'off' === Guard::mode() || ! in_array( $hook, array( 'edit.php', 'post.php' ), true )
			|| ! in_array( $screen->post_type, (array) Settings::get( 'post_types' ), true ) || ( 'post.php' === $hook && $screen->is_block_editor() ) ) {
			return;
		}
		$url = plugin_dir_url( ROKHDADYAR_FILE ) . 'assets/';
		wp_enqueue_style( 'rokhdadyar', $url . 'admin.css', array(), ROKHDADYAR_VER );
		wp_enqueue_script( 'rokhdadyar-guard', $url . 'guard.js', array(), ROKHDADYAR_VER, true );
		wp_localize_script( 'rokhdadyar-guard', 'RokhdadyarGuard', Guard::config() );
	}

	public static function block_assets(): void {
		$screen = get_current_screen();
		if ( self::inline_on() ) {
			wp_enqueue_script( 'rokhdadyar-inline', plugin_dir_url( ROKHDADYAR_FILE ) . 'assets/inline-editor.js', array( 'wp-hooks', 'wp-compose', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-api-fetch' ), ROKHDADYAR_VER, true );
			$i18n = self::config()['i18n'];
			wp_localize_script(
				'rokhdadyar-inline',
				'RokhdadyarInline',
				array(
					'i18n' => array(
						'label'    => $i18n['rwLabel'],
						'short'    => $i18n['rwShort'],
						'long'     => $i18n['rwLong'],
						'own'      => $i18n['rwOwn'],
						'ownLabel' => $i18n['rwOwnLabel'],
						'ownPh'    => $i18n['rwOwnPh'],
						'current'  => $i18n['rwCurrent'],
						'option'   => $i18n['rwOption'],
						'chars'    => $i18n['rwChars'],
						'go'       => $i18n['rwGo'],
						'cancel'   => $i18n['rwCancel'],
						'retry'    => $i18n['retry'],
						'working'  => $i18n['working'],
						'cached'   => $i18n['cached'],
						'secs'     => $i18n['secs'],
						'error'    => $i18n['error'],
						'near'     => $i18n['rwNear'],
						'fresh'    => $i18n['rwFresh'],
						'use'      => $i18n['rwUse'],
						'shorter'  => $i18n['rwShorter'],
						'longer'   => $i18n['rwLonger'],
						'hint'     => $i18n['rwHint'],
					),
				)
			);
		}
		$tb_pin = $screen && Toolbox::can( 'pin' ) && in_array( $screen->post_type, Toolbox::pin_types(), true );
		$tb_imp = $screen && Toolbox::can( 'important' ) && in_array( $screen->post_type, (array) Settings::get( 'post_types' ), true );
		if ( $tb_pin || $tb_imp ) {
			wp_enqueue_script( 'rokhdadyar-toolbox', plugin_dir_url( ROKHDADYAR_FILE ) . 'assets/toolbox-editor.js', array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-editor' ), ROKHDADYAR_VER, true );
			wp_localize_script(
				'rokhdadyar-toolbox',
				'RokhdadyarToolbox',
				array(
					'tools' => array_values(
						array_filter(
							array(
								$tb_pin ? array(
									'meta'  => Pin::META,
									'label' => __( 'پین در دسته‌بندی', 'rokhdadyar' ),
									'help'  => __( 'این مطلب در بالای دسته‌های سطح اول و دومِ خودش می‌ماند و نشان «مطلب مهم» می‌گیرد. فقط برای مطلب منتشرشده.', 'rokhdadyar' ),
								) : null,
								$tb_imp ? array(
									'meta'  => Links::META,
									'label' => __( 'مطلب مهم', 'rokhdadyar' ),
									'help'  => __( 'لینک‌های داخلیِ پیشنهادی هوش مصنوعی فقط به مطالب مهم داده می‌شوند.', 'rokhdadyar' ),
								) : null,
							)
						)
					),
					'title' => __( 'جعبه ابزار', 'rokhdadyar' ),
				)
			);
		}
		if ( $screen && 'off' !== Guard::mode() && in_array( $screen->post_type, (array) Settings::get( 'post_types' ), true ) ) {
			wp_enqueue_script( 'rokhdadyar-guard-editor', plugin_dir_url( ROKHDADYAR_FILE ) . 'assets/guard-editor.js', array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-editor' ), ROKHDADYAR_VER, true );
			wp_localize_script( 'rokhdadyar-guard-editor', 'RokhdadyarGuard', Guard::config() );
		}
		if ( ! $screen || ! in_array( $screen->post_type, (array) Settings::get( 'post_types' ), true ) || Access::none() ) {
			return;
		}
		wp_enqueue_script(
			'rokhdadyar-editor',
			plugin_dir_url( ROKHDADYAR_FILE ) . 'assets/editor.js',
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-editor', 'wp-edit-post', 'wp-blocks' ),
			ROKHDADYAR_VER,
			true
		);
		$cfg                   = self::config();
		$cfg['supportsExcerpt'] = post_type_supports( $screen->post_type, 'excerpt' );
		$cfg['terms']           = array_keys( Terms::enabled( $screen->post_type ) );
		$cfg['links']           = Toolbox::links_on();
		$cfg['image']           = Image::ready() && Access::can( 'image' ) && post_type_supports( $screen->post_type, 'thumbnail' ) && current_user_can( 'upload_files' );
		if ( ! Access::can( 'text' ) ) {
			// این نقش فقط تصویر می‌سازد: ابزارهای متنی و تاریخچه در پنل نمی‌آیند.
			$cfg['ready']   = false;
			$cfg['terms']   = array();
			$cfg['links']   = false;
			$cfg['history'] = false;
		}
		$cfg['keyword']         = get_post() ? Extractor::keywords( get_post()->ID ) : '';
		$cfg['made']            = get_post() ? Made::get( get_post()->ID ) : array();
		wp_localize_script( 'rokhdadyar-editor', 'RokhdadyarCfg', $cfg );
	}

	/** لوگوی رسمی به‌جای نوشتهٔ «رخدادیار» در عنوان‌ها؛ نام برای صفحه‌خوان و «تنظیمات صفحه» می‌ماند. */
	public static function logo( int $height ): string {
		return sprintf(
			'<img class="chk-wordmark" src="%s" height="%d" alt=""><span class="screen-reader-text">%s</span>',
			esc_url( plugins_url( 'assets/logo.svg', ROKHDADYAR_FILE ) ),
			$height,
			esc_html__( 'رخدادیار', 'rokhdadyar' )
		);
	}

	private static function config(): array {
		return array(
			'logo'        => esc_url_raw( plugins_url( 'assets/logo.svg', ROKHDADYAR_FILE ) ),
			'mark'        => esc_url_raw( plugins_url( 'assets/spark.svg', ROKHDADYAR_FILE ) ),
			'root'        => esc_url_raw( rest_url( Rest::NS . '/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'direct'      => (bool) Settings::get( 'direct_apply' ),
			'askKeyword'  => (bool) Settings::get( 'ask_keyword' ),
			'ready'       => (bool) Settings::get( 'profiles' ),
			'settingsUrl' => current_user_can( 'manage_options' ) ? self::url() : '',
			'titleMax'    => (int) Settings::get( 'title_max' ),
			'excerptMax'  => (int) Settings::get( 'excerpt_max' ),
			'titleMin'    => 30,
			'slugMin'     => 8,
			'slugMax'     => 60,
			'slug'        => (bool) Settings::get( 'slug_enabled' ),
			'slugLang'    => (string) Settings::get( 'slug_lang' ),
			'excerptMin'  => 120,
			'presets'     => Settings::presets(),
			'defaults'    => array(
				'system'  => Settings::default_prompt( 'system' ),
				'title'   => Settings::default_prompt( 'title' ),
				'excerpt' => Settings::default_prompt( 'excerpt' ),
				'styles'  => Settings::default_prompt( 'styles' ),
				'slug'    => Settings::default_prompt( 'slug' ),
				'image'   => Image::default_prompt(),
			),
			'i18n'        => array(
				'title'        => __( 'عنوان پیشنهادی', 'rokhdadyar' ),
				'excerpt'      => __( 'چکیدهٔ پیشنهادی', 'rokhdadyar' ),
				'makeTitle'    => __( 'ساخت عنوان', 'rokhdadyar' ),
				'makeExcerpt'  => __( 'ساخت چکیده', 'rokhdadyar' ),
				'working'      => __( 'در حال نوشتن…', 'rokhdadyar' ),
				'pick'         => __( 'یکی را انتخاب کنید', 'rokhdadyar' ),
				'apply'        => __( 'ثبت', 'rokhdadyar' ),
				'retry'        => __( 'دوباره بساز', 'rokhdadyar' ),
				'close'        => __( 'بستن', 'rokhdadyar' ),
				'saved'        => __( 'ثبت شد', 'rokhdadyar' ),
				'cached'       => __( 'از حافظه · ۰ توکن', 'rokhdadyar' ),
				'made'         => __( 'قبلاً ساخته شده', 'rokhdadyar' ),
				/* translators: %s: token count */
				'tokens'       => __( '%s توکن', 'rokhdadyar' ),
				/* translators: %s: token count */
				'tokCached'    => __( '%s از کش سرویس', 'rokhdadyar' ),
				/* translators: %s: seconds */
				'secs'         => __( '%s ثانیه', 'rokhdadyar' ),
				'tooSlow'      => __( 'پاسخ مدل بیش از حد طول کشید و درخواست رها شد. اگر مدل «استدلالی/thinking» انتخاب کرده‌اید، یک مدل سریع‌تر بگذارید؛ یا مهلت سرور (PHP یا nginx) را بالاتر ببرید.', 'rokhdadyar' ),
				'chars'        => __( 'نویسه', 'rokhdadyar' ),
				'digest'       => __( 'خلاصهٔ ساختاری', 'rokhdadyar' ),
				'fullText'     => __( 'متن کامل', 'rokhdadyar' ),
				'error'        => __( 'خطا', 'rokhdadyar' ),
				'notReady'     => __( 'هنوز مدلی تعریف نشده است.', 'rokhdadyar' ),
				'openSettings' => __( 'رفتن به مرکز کنترل', 'rokhdadyar' ),
				'panel'        => __( 'رخدادیار', 'rokhdadyar' ),
				'testing'      => __( 'در حال آزمایش…', 'rokhdadyar' ),
				'testOk'       => __( 'وصل شد', 'rokhdadyar' ),
				'ms'           => __( 'میلی‌ثانیه', 'rokhdadyar' ),
				'confirmDel'   => __( 'این مدل حذف شود؟', 'rokhdadyar' ),
				'newModel'     => __( 'مدل جدید', 'rokhdadyar' ),
				'keyword'      => __( 'کلیدواژه', 'rokhdadyar' ),
				'image'        => __( 'تصویر شاخص پیشنهادی', 'rokhdadyar' ),
				'makeImage'    => __( 'ساخت تصویر شاخص', 'rokhdadyar' ),
				'imgWorking'   => __( 'در حال ساخت تصویر… ممکن است تا یک دقیقه طول بکشد.', 'rokhdadyar' ),
				'imgApply'     => __( 'ثبت به‌عنوان تصویر شاخص', 'rokhdadyar' ),
				'imgAlt'       => __( 'متن جایگزین (alt)', 'rokhdadyar' ),
				'ownToggle'    => __( 'با پرامپت خودم بساز', 'rokhdadyar' ),
				'ownPh'        => __( 'مثلاً: عنوان برای خبر کاهش قیمت طلا امروز، لحن فوری و کوتاه', 'rokhdadyar' ),
				'ownHint'      => __( 'فقط همین متن فرستاده می‌شود؛ نه متن نوشته، نه کلیدواژه و نه پرامپت‌های مرکز کنترل. Ctrl+Enter = ساخت', 'rokhdadyar' ),
				'ownGo'        => __( 'بساز با همین', 'rokhdadyar' ),
				'ownMeta'      => __( 'پرامپت شما', 'rokhdadyar' ),
				'imgPending'   => __( 'ساخته‌شده و هنوز ثبت‌نشده', 'rokhdadyar' ),
				'imgPrompt'    => __( 'پرامپت تصویر', 'rokhdadyar' ),
				'imgHint'      => __( 'خالی بگذارید تا از روی متن نوشته ساخته شود. اگر چیزی بنویسید، عیناً همان برای مدل تصویر فرستاده می‌شود.', 'rokhdadyar' ),
				'imgUsed'      => __( 'این پرامپت فرستاده شد. می‌توانید تغییرش دهید و دوباره بسازید؛ اگر خالی‌اش کنید، خودکار از روی متن ساخته می‌شود.', 'rokhdadyar' ),
				'imgPh'        => __( 'مثلاً: عکس از بالا از چند لاک ژله‌ای قرمز روی سنگ مرمر، نور ملایم روز', 'rokhdadyar' ),
				'imgAuto'      => __( 'خودکار از روی متن', 'rokhdadyar' ),
				'imgCustom'    => __( 'پرامپت شما', 'rokhdadyar' ),
				'imgDiscard'   => __( 'نمی‌خواهم', 'rokhdadyar' ),
				'slug'         => __( 'نامک پیشنهادی', 'rokhdadyar' ),
				'makeSlug'     => __( 'ساخت نامک', 'rokhdadyar' ),
				'terms'        => __( 'دسته و برچسب پیشنهادی', 'rokhdadyar' ),
				'links'        => __( 'لینک داخلی به مطالب مهم', 'rokhdadyar' ),
				'lPick'        => __( 'این عبارت‌ها در متن به مطالب مهم لینک می‌شوند؛ هر کدام را نمی‌خواهید بردارید.', 'rokhdadyar' ),
				'lNone'        => __( 'هیچ‌کدام از مطالب مهم به این متن مربوط نبود.', 'rokhdadyar' ),
				'lApply'       => __( 'ثبت لینک‌ها', 'rokhdadyar' ),
				/* translators: 1: sent, 2: total important posts */
				'lSent'        => __( '%1$s از %2$s مطلب مهم بررسی شد', 'rokhdadyar' ),
				'makeLinks'    => __( 'پیشنهاد لینک داخلی', 'rokhdadyar' ),
				'rwLabel'      => __( 'رخدادیار', 'rokhdadyar' ),
				'rwShort'      => __( 'کوتاه‌تر', 'rokhdadyar' ),
				'rwLong'       => __( 'بلندتر', 'rokhdadyar' ),
				'rwOwn'        => __( 'پرامپت خودم…', 'rokhdadyar' ),
				'rwOwnLabel'   => __( 'این پاراگراف چطور بازنویسی شود؟', 'rokhdadyar' ),
				'rwOwnPh'      => __( 'مثلاً: رسمی‌تر و با لحن خبری، کلیدواژهٔ «لاک ژله‌ای» هم بیاید', 'rokhdadyar' ),
				'rwCurrent'    => __( 'پاراگراف فعلی', 'rokhdadyar' ),
				/* translators: %s: option number */
				'rwOption'     => __( 'نسخهٔ %s — برای جایگزینی بزنید', 'rokhdadyar' ),
				/* translators: %s: number of characters */
				'rwChars'      => __( '%s نویسه', 'rokhdadyar' ),
				'rwGo'         => __( 'بازنویسی کن', 'rokhdadyar' ),
				'rwCancel'     => __( 'انصراف', 'rokhdadyar' ),
				'rwEdit'       => __( 'تغییر دستور', 'rokhdadyar' ),
				'healthRunning' => __( 'در حال بررسی…', 'rokhdadyar' ),
				'healthConfirm' => __( 'ساختار جدول‌های پایگاه‌داده تعمیر شود؟ پیش از ادامه مطمئن شوید از پایگاه‌داده پشتیبان دارید.', 'rokhdadyar' ),
				'healthFixed'   => __( 'تعمیر شد:', 'rokhdadyar' ),
				'healthNothing' => __( 'چیزی برای تعمیر نبود.', 'rokhdadyar' ),
				'rwNear'       => __( 'نزدیک به متن', 'rokhdadyar' ),
				'rwFresh'      => __( 'بیان تازه', 'rokhdadyar' ),
				'rwUse'        => __( 'جایگزین کن', 'rokhdadyar' ),
				/* translators: %s: percent */
				'rwShorter'    => __( '%s٪ کوتاه‌تر', 'rokhdadyar' ),
				/* translators: %s: percent */
				'rwLonger'     => __( '%s٪ بلندتر', 'rokhdadyar' ),
				'rwHint'       => __( 'روی نسخهٔ دلخواه بزنید یا کلید ۱ و ۲ را فشار دهید.', 'rokhdadyar' ),
				'rwNoPara'     => __( 'اول نشانگر را داخل یک پاراگراف بگذارید (یا آن را انتخاب کنید).', 'rokhdadyar' ),
				'rwSelect'     => __( 'اول متنِ پاراگراف را انتخاب کنید.', 'rokhdadyar' ),
				'rwPickMode'   => __( 'متنِ انتخاب‌شده چطور بازنویسی شود؟', 'rokhdadyar' ),
				'close'        => __( 'بستن', 'rokhdadyar' ),
				'makeTerms'    => __( 'پیشنهاد دسته و برچسب', 'rokhdadyar' ),
				'cats'         => __( 'دسته‌ها', 'rokhdadyar' ),
				'tags'         => __( 'برچسب‌ها', 'rokhdadyar' ),
				'newTags'      => __( 'برچسب تازه (ساخته می‌شود)', 'rokhdadyar' ),
				'tAll'         => __( 'همه', 'rokhdadyar' ),
				'tClear'       => __( 'هیچ‌کدام', 'rokhdadyar' ),
				'tCurrent'     => __( 'فعلی', 'rokhdadyar' ),
				'tNone'        => __( 'مورد مناسبی در فهرست سایت پیدا نشد.', 'rokhdadyar' ),
				/* translators: 1: sent, 2: total */
				'tSent'        => __( '%1$s از %2$s برچسب فرستاده شد', 'rokhdadyar' ),
				'refreshing'   => __( 'در حال بازسازی…', 'rokhdadyar' ),
				'refreshed'    => __( 'بازسازی شد', 'rokhdadyar' ),
				'history'      => __( 'تاریخچه', 'rokhdadyar' ),
				'showHistory'  => __( 'تاریخچه و برگشت', 'rokhdadyar' ),
				'hEmpty'       => __( 'هنوز چیزی با رخدادیار عوض نشده که بشود برگرداند.', 'rokhdadyar' ),
				'hRestore'     => __( 'برگردان', 'rokhdadyar' ),
				'hNow'         => __( 'همین الان فعال است', 'rokhdadyar' ),
				'fTitle'       => __( 'عنوان', 'rokhdadyar' ),
				'fExcerpt'     => __( 'چکیده', 'rokhdadyar' ),
				'fSlug'        => __( 'نامک', 'rokhdadyar' ),
				'slugWarn'     => __( 'این نوشته منتشر شده است. با تغییر نامک، نشانی صفحه عوض می‌شود. وردپرس نشانی قبلی را به نشانی تازه هدایت می‌کند، ولی لینک‌های داخلی را بررسی کنید.', 'rokhdadyar' ),
				'kwLabel'      => __( 'کلیدواژهٔ کانونی', 'rokhdadyar' ),
				'kwHint'       => __( 'چند کلیدواژه را با ویرگول جدا کنید؛ اولی کلیدواژهٔ اصلی است و در ابتدای عنوان می‌آید.', 'rokhdadyar' ),
				'kwPlaceholder' => __( 'مثلاً: خرید لاک ژله‌ای', 'rokhdadyar' ),
				'kwAsk'        => __( 'این نوشته هنوز کلیدواژه ندارد.', 'rokhdadyar' ),
				'go'           => __( 'بساز', 'rokhdadyar' ),
				'skipKw'       => __( 'بدون کلیدواژه', 'rokhdadyar' ),
				'noKeyword'    => __( 'بدون کلیدواژه', 'rokhdadyar' ),
				'edit'         => __( 'پیش از ثبت می‌توانید ویرایش کنید', 'rokhdadyar' ),
				'loadingList'  => __( 'در حال دریافت…', 'rokhdadyar' ),
				/* translators: %d: number of models */
				'modelsFound'  => __( '%d مدل پیدا شد؛ از فهرست انتخاب کنید', 'rokhdadyar' ),
			),
		);
	}

	/* ---------- ویرایشگر کلاسیک ---------- */

	/** دکمهٔ کوچک درست کنار فیلد عنوان. */
	public static function classic_title_button( \WP_Post $post ): void {
		if ( ! in_array( $post->post_type, (array) Settings::get( 'post_types' ), true ) || ! current_user_can( 'edit_post', $post->ID ) || ! Access::can( 'text' ) ) {
			return;
		}
		echo '<div class="chk-inline chk-inline--title">';
		self::button( 'title', $post->ID, __( 'ساخت عنوان با هوش مصنوعی', 'rokhdadyar' ), 'T' );
		echo '</div>';
	}

	public static function classic_box( string $post_type ): void {
		if ( ! in_array( $post_type, (array) Settings::get( 'post_types' ), true ) || Access::none() ) {
			return;
		}
		$text = Access::can( 'text' );
		add_meta_box(
			'rokhdadyar-box',
			self::logo( 20 ),
			static function ( \WP_Post $post ) use ( $text ) {
				echo '<div class="chk-actions chk-actions--box">';
				if ( $text ) {
					self::button( 'title', $post->ID, __( 'ساخت عنوان با هوش مصنوعی', 'rokhdadyar' ), 'T', ' chk-btn--wide', __( 'ساخت عنوان', 'rokhdadyar' ) );
				}
				if ( $text && post_type_supports( $post->post_type, 'excerpt' ) ) {
					self::button( 'excerpt', $post->ID, __( 'ساخت چکیده با هوش مصنوعی', 'rokhdadyar' ), '¶', ' chk-btn--wide', __( 'ساخت چکیده', 'rokhdadyar' ) );
				}
				if ( $text && Settings::get( 'slug_enabled' ) ) {
					self::button( 'slug', $post->ID, __( 'ساخت نامک (slug) با هوش مصنوعی', 'rokhdadyar' ), '/', ' chk-btn--wide', __( 'ساخت نامک', 'rokhdadyar' ) );
				}
				if ( $text && Terms::enabled( $post->post_type ) ) {
					self::button( 'terms', $post->ID, __( 'پیشنهاد دسته و برچسب با هوش مصنوعی', 'rokhdadyar' ), '#', ' chk-btn--wide', __( 'دسته و برچسب', 'rokhdadyar' ) );
				}
				if ( $text && Toolbox::links_on() ) {
					self::button( 'links', $post->ID, __( 'پیشنهاد لینک داخلی به مطالب مهم', 'rokhdadyar' ), '', ' chk-btn--wide', __( 'لینک داخلی', 'rokhdadyar' ) );
				}
				self::image_button( $post, ' chk-btn--wide', __( 'ساخت تصویر شاخص', 'rokhdadyar' ) );
				if ( $text ) {
					self::button( 'history', $post->ID, __( 'تاریخچه: برگرداندن عنوان، چکیده یا نامک قبلی', 'rokhdadyar' ), '↺', ' chk-btn--wide chk-btn--plain', __( 'تاریخچه و برگشت', 'rokhdadyar' ) );
				}
				echo '</div>';
			},
			$post_type,
			'side',
			'high',
			array( '__back_compat_meta_box' => true ) // در ویرایشگر بلوکی پنهان می‌ماند.
		);
	}
}
