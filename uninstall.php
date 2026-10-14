<?php
// پاک‌سازی کامل هنگام حذف افزونه.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'rokhdadyar' );
delete_option( 'rokhdadyar_img' );
delete_option( 'rokhdadyar_terms_category' );
delete_option( 'rokhdadyar_terms_post_tag' );
delete_post_meta_by_key( '_rokhdadyar_keyword' );
delete_post_meta_by_key( '_rokhdadyar_history' );
delete_post_meta_by_key( '_rokhdadyar_made' );
delete_post_meta_by_key( '_rokhdadyar_img_pending' );
wp_clear_scheduled_hook( 'rokhdadyar_cleanup' );
delete_option( 'rokhdadyar_front' );
delete_site_transient( 'rokhdadyar_gh_release' );
delete_post_meta_by_key( '_rokhdadyar_pin' );
delete_post_meta_by_key( '_rokhdadyar_important' );
delete_transient( 'chk_important' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_chk\_%' OR option_name LIKE '\_transient\_timeout\_chk\_%' OR option_name LIKE 'rokhdadyar\_usage\_%'" );
