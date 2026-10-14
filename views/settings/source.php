<?php
/**
 * مرکز کنترل — تب «منبع محتوا». داخل views/settings.php بار می‌شود و متغیرهای آن را می‌بیند.
 */

namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;
?>
		<!-- منبع محتوا -->
		<section class="chk-panel" data-panel="source">
			<div class="chk-card">
				<h2><?php esc_html_e( 'چه بخشی از نوشته برای مدل فرستاده شود؟', 'rokhdadyar' ); ?></h2>
				<div class="chk-options">
					<label class="chk-option">
						<input type="radio" name="chk[source_mode]" value="smart" <?php checked( $s['source_mode'], 'smart' ); ?>>
						<span>
							<strong><?php esc_html_e( 'هوشمند', 'rokhdadyar' ); ?> <i><?php esc_html_e( 'پیشنهادی', 'rokhdadyar' ); ?></i></strong>
							<?php esc_html_e( 'متن کوتاه کامل می‌رود. متن بلند به «خلاصهٔ ساختاری» تبدیل می‌شود: مقدمه، همهٔ تیترها، جملهٔ اول بخش‌های میانی و جمع‌بندی.', 'rokhdadyar' ); ?>
						</span>
					</label>
					<label class="chk-option">
						<input type="radio" name="chk[source_mode]" value="intro" <?php checked( $s['source_mode'], 'intro' ); ?>>
						<span>
							<strong><?php esc_html_e( 'فقط ابتدای متن', 'rokhdadyar' ); ?></strong>
							<?php esc_html_e( 'ارزان‌ترین حالت. برای خبر کوتاه خوب است؛ در مقالهٔ بلند، نتیجه‌گیری و بخش‌های میانی دیده نمی‌شود.', 'rokhdadyar' ); ?>
						</span>
					</label>
					<label class="chk-option">
						<input type="radio" name="chk[source_mode]" value="full" <?php checked( $s['source_mode'], 'full' ); ?>>
						<span>
							<strong><?php esc_html_e( 'کل متن', 'rokhdadyar' ); ?></strong>
							<?php esc_html_e( 'دقیق‌ترین و گران‌ترین حالت؛ هزینه با طول نوشته بالا می‌رود.', 'rokhdadyar' ); ?>
						</span>
					</label>
				</div>
				<div class="chk-grid">
					<label class="chk-field">
						<span><?php esc_html_e( 'بودجهٔ متن (نویسه)', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'در حالت هوشمند و ابتدای متن', 'rokhdadyar' ); ?></em></span>
						<input type="number" min="800" max="60000" step="100" name="chk[budget]" value="<?php echo (int) $s['budget']; ?>">
					</label>
					<label class="chk-check">
						<input type="checkbox" name="chk[use_meta]" value="1" <?php checked( $s['use_meta'] ); ?>>
						<span><?php esc_html_e( 'دسته‌ها و برچسب‌های نوشته هم فرستاده شود', 'rokhdadyar' ); ?></span>
					</label>
				</div>
				<p class="chk-hint"><?php esc_html_e( 'کد، شورت‌کد، اسکریپت و زیرنویس تصویر همیشه پیش از ارسال حذف می‌شوند. کلیدواژه همیشه فرستاده می‌شود: اول آنچه هنگام ساخت وارد می‌کنید، وگرنه کلیدواژهٔ کانونی Yoast یا Rank Math.', 'rokhdadyar' ); ?></p>
			</div>
		</section>
