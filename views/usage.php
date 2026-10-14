<?php
namespace Rokhdadyar;

/**
 * گزارش مصرف — هم در مرکز کنترل و هم در صفحهٔ جدای نقش‌های مجاز بار می‌شود.
 *
 * @var string $chk_base نشانی همین صفحه برای پیوند ماه‌ها.
 */
defined( 'ABSPATH' ) || exit;

$chk_hash = $chk_hash ?? '';
		$chk_months = Usage::months();
		// phpcs:ignore WordPress.Security.NonceVerification
		$chk_ym     = isset( $_GET['usage_month'] ) && in_array( $_GET['usage_month'], $chk_months, true ) ? sanitize_key( $_GET['usage_month'] ) : $chk_months[0];
		$chk_rep    = Usage::report( $chk_ym );
		$chk_num    = static function ( $n ) {
			return esc_html( number_format_i18n( (int) $n ) );
		};
		$chk_table  = static function ( string $head, array $rows ) use ( $chk_num ) {
			?>
			<div class="chk-card">
				<h2><?php echo esc_html( $head ); ?></h2>
				<?php if ( ! $rows ) : ?>
					<p class="chk-hint"><?php esc_html_e( 'در این ماه چیزی ثبت نشده است.', 'rokhdadyar' ); ?></p>
				<?php else : ?>
				<div class="chk-table-wrap">
					<table class="chk-table">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html( $head ); ?></th>
								<th scope="col"><?php esc_html_e( 'درخواست', 'rokhdadyar' ); ?></th>
								<th scope="col"><?php esc_html_e( 'توکن ورودی', 'rokhdadyar' ); ?></th>
								<th scope="col"><?php esc_html_e( 'از کش سرویس', 'rokhdadyar' ); ?></th>
								<th scope="col"><?php esc_html_e( 'توکن خروجی', 'rokhdadyar' ); ?></th>
								<th scope="col"><?php esc_html_e( 'تصویر', 'rokhdadyar' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $chk_label => $chk_r ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $chk_label ); ?></th>
									<td><?php echo $chk_num( $chk_r[0] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
									<td><?php echo $chk_num( $chk_r[1] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
									<td><?php echo $chk_num( $chk_r[3] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
									<td><?php echo $chk_num( $chk_r[2] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
									<td><?php echo $chk_num( $chk_r[4] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
			<?php
		};
		$chk_days  = array();
		foreach ( $chk_rep['days'] as $chk_d => $chk_r ) {
			$chk_days[ Usage::day_label( $chk_ym, (string) $chk_d ) ] = $chk_r;
		}
		$chk_users = array();
		foreach ( $chk_rep['users'] as $chk_u => $chk_r ) {
			$chk_ud = get_userdata( (int) $chk_u );
			$chk_users[ $chk_ud ? $chk_ud->display_name : '#' . $chk_u ] = $chk_r;
		}
		?>
			<div class="chk-card">
				<div class="chk-card__head">
					<div>
						<h2><?php echo esc_html( Usage::label( $chk_ym ) ); ?></h2>
						<p class="chk-hint"><?php esc_html_e( 'فقط درخواست‌هایی که واقعاً به سرویس رفته‌اند شمرده می‌شوند؛ پاسخ‌هایی که از حافظه آمده‌اند مصرفی ندارند. عددها همان چیزی است که خودِ سرویس گزارش کرده است.', 'rokhdadyar' ); ?></p>
					</div>
				</div>
				<div class="chk-months">
					<?php foreach ( $chk_months as $chk_m ) : ?>
						<a class="chk-chip<?php echo $chk_m === $chk_ym ? ' is-on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'usage_month', $chk_m, $chk_base ) . $chk_hash ); ?>"><?php echo esc_html( Usage::label( $chk_m ) ); ?></a>
					<?php endforeach; ?>
				</div>
				<dl class="chk-stats">
					<div><dt><?php esc_html_e( 'درخواست', 'rokhdadyar' ); ?></dt><dd><?php echo $chk_num( $chk_rep['total'][0] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></dd></div>
					<div><dt><?php esc_html_e( 'توکن ورودی', 'rokhdadyar' ); ?></dt><dd><?php echo $chk_num( $chk_rep['total'][1] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></dd></div>
					<div><dt><?php esc_html_e( 'از کش سرویس', 'rokhdadyar' ); ?></dt><dd><?php echo $chk_num( $chk_rep['total'][3] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></dd></div>
					<div><dt><?php esc_html_e( 'توکن خروجی', 'rokhdadyar' ); ?></dt><dd><?php echo $chk_num( $chk_rep['total'][2] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></dd></div>
					<div><dt><?php esc_html_e( 'تصویر', 'rokhdadyar' ); ?></dt><dd><?php echo $chk_num( $chk_rep['total'][4] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></dd></div>
				</dl>
			</div>
			<?php
			// نمودار ۱۵ روز اخیر: دو نمودار جدا، چون مقیاس درخواست و توکن یکی نیست. فقط HTML و CSS.
			$chk_recent = Usage::recent( 15 );
			$chk_short  = static function ( int $n ): string {
				if ( $n >= 1000000 ) {
					return number_format_i18n( $n / 1000000, 1 ) . 'M';
				}
				return $n >= 10000 ? number_format_i18n( $n / 1000 ) . 'k' : ( $n >= 1000 ? number_format_i18n( $n / 1000, 1 ) . 'k' : number_format_i18n( $n ) );
			};
			$chk_charts = array(
				'req'    => __( 'درخواست‌ها', 'rokhdadyar' ),
				'tokens' => __( 'توکن مصرفی (ورودی + خروجی)', 'rokhdadyar' ),
			);
			?>
			<div class="chk-card">
				<div class="chk-card__head">
					<div>
						<h2><?php esc_html_e( '۱۵ روز اخیر', 'rokhdadyar' ); ?></h2>
						<p class="chk-hint"><?php esc_html_e( 'هر ستون یک روز است؛ تازه‌ترین روز در انتهای نمودار. این نمودار به ماهِ انتخاب‌شده بستگی ندارد.', 'rokhdadyar' ); ?></p>
					</div>
				</div>
				<?php foreach ( $chk_charts as $chk_key => $chk_title ) : ?>
					<?php $chk_max = max( 1, max( array_column( $chk_recent, $chk_key ) ) ); ?>
					<h3 class="chk-chart__title"><?php echo esc_html( $chk_title ); ?></h3>
					<ol class="chk-chart" data-chart="<?php echo esc_attr( $chk_key ); ?>">
						<?php foreach ( $chk_recent as $chk_d ) : ?>
							<li title="<?php echo esc_attr( $chk_d['date'] . ': ' . number_format_i18n( $chk_d[ $chk_key ] ) ); ?>">
								<span class="chk-chart__val"><?php echo $chk_d[ $chk_key ] ? esc_html( $chk_short( $chk_d[ $chk_key ] ) ) : ''; ?></span>
								<span class="chk-chart__bar<?php echo $chk_d[ $chk_key ] ? '' : ' is-zero'; ?>" style="height: <?php echo (int) round( 112 * $chk_d[ $chk_key ] / $chk_max ); ?>px"></span>
								<span class="chk-chart__day"><?php echo esc_html( $chk_d['day'] ); ?><span class="screen-reader-text"> — <?php echo esc_html( number_format_i18n( $chk_d[ $chk_key ] ) ); ?></span></span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endforeach; ?>
			</div>
			<?php
			$chk_table( __( 'مدل', 'rokhdadyar' ), $chk_rep['models'] );
			$chk_table( __( 'کاربر', 'rokhdadyar' ), $chk_users );
			$chk_table( __( 'روز', 'rokhdadyar' ), $chk_days );
			?>
