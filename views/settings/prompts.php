<?php
/**
 * مرکز کنترل — تب «پرامپت‌ها». داخل views/settings.php بار می‌شود و متغیرهای آن را می‌بیند.
 */

namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;
?>
		<!-- پرامپت‌ها -->
		<section class="chk-panel" data-panel="prompts">
			<div class="chk-card chk-vars">
				<h2><?php esc_html_e( 'متغیرها', 'rokhdadyar' ); ?></h2>
				<p class="chk-hint"><?php esc_html_e( 'روی هر متغیر بزنید تا در پرامپتی که آخرین بار در آن بودید درج شود.', 'rokhdadyar' ); ?></p>
				<div class="chk-chips">
					<?php foreach ( $chk_vars as $chk_v ) : ?>
						<button type="button" class="chk-chip" data-var="<?php echo esc_attr( $chk_v ); ?>"><?php echo esc_html( $chk_v ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>

			<?php
			$chk_prompt( 'system', __( 'پرامپت سیستم', 'rokhdadyar' ), __( 'نقش و قواعد کلی؛ برای هر دو کار مشترک است.', 'rokhdadyar' ), 6 );
			$chk_prompt( 'title', __( 'پرامپت عنوان', 'rokhdadyar' ), __( 'قواعد تیترنویسی و سئو اینجاست. {styles} و {examples} از دو بخش پایین می‌آیند.', 'rokhdadyar' ), 26 );
			?>
			<div class="chk-card">
				<div class="chk-card__head">
					<div>
						<h2><label for="chk-styles"><?php esc_html_e( 'سبک‌های عنوان', 'rokhdadyar' ); ?></label></h2>
						<p class="chk-hint"><?php esc_html_e( 'هر خط یک عنوان می‌سازد (حداکثر ۸). قالب هر خط: «برچسب: توضیح سبک». برچسب روی پیشنهادها نمایش داده می‌شود.', 'rokhdadyar' ); ?></p>
					</div>
					<button type="button" class="button-link" data-reset="styles"><?php esc_html_e( 'برگرداندن به پیش‌فرض', 'rokhdadyar' ); ?></button>
				</div>
				<textarea id="chk-styles" class="chk-prompt" name="chk[title_styles]" rows="7" data-prompt="styles" dir="auto"><?php echo esc_textarea( $s['title_styles'] ); ?></textarea>
			</div>
			<div class="chk-card">
				<h2><label for="chk-examples"><?php esc_html_e( 'نمونه عنوان‌هایی که می‌پسندید', 'rokhdadyar' ); ?></label></h2>
				<p class="chk-hint"><?php esc_html_e( 'قوی‌ترین راه برای اینکه عنوان‌ها به سلیقهٔ شما نزدیک شوند: ۵ تا ۱۰ عنوان واقعی که دوست دارید (از سایت خودتان یا رقبا)، هر کدام در یک خط. مدل لحن و ساختارشان را الگو می‌گیرد، نه موضوعشان را.', 'rokhdadyar' ); ?></p>
				<textarea id="chk-examples" class="chk-prompt" name="chk[title_examples]" rows="7" dir="auto" style="margin-block-start:10px"><?php echo esc_textarea( $s['title_examples'] ); ?></textarea>
			</div>
			<?php
			$chk_prompt( 'excerpt', __( 'پرامپت چکیده', 'rokhdadyar' ), __( 'چکیده مثل توضیحات متا نوشته می‌شود. هر چکیده باید در یک خط جدا بیاید.', 'rokhdadyar' ), 18 );
			?>

			<div class="chk-card">
				<h2><?php esc_html_e( 'نامک (Slug)', 'rokhdadyar' ); ?></h2>
				<label class="chk-check" style="margin-block:10px">
					<input type="checkbox" name="chk[slug_enabled]" value="1" <?php checked( $s['slug_enabled'] ); ?>>
					<span><?php esc_html_e( 'دکمهٔ ساخت نامک نمایش داده شود', 'rokhdadyar' ); ?></span>
				</label>
				<div class="chk-options" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
					<label class="chk-option">
						<input type="radio" name="chk[slug_lang]" value="en" <?php checked( $s['slug_lang'], 'en' ); ?>>
						<span>
							<strong><?php esc_html_e( 'انگلیسی', 'rokhdadyar' ); ?></strong>
							<span class="chk-ltr" style="display:block">ai-assistant-guide</span>
							<?php esc_html_e( 'نشانی کوتاه و تمیز که همه‌جا درست کپی می‌شود.', 'rokhdadyar' ); ?>
						</span>
					</label>
					<label class="chk-option">
						<input type="radio" name="chk[slug_lang]" value="fa" <?php checked( $s['slug_lang'], 'fa' ); ?>>
						<span>
							<strong><?php esc_html_e( 'فارسی', 'rokhdadyar' ); ?></strong>
							<span style="display:block">راهنمای-دستیار-هوش-مصنوعی</span>
							<?php esc_html_e( 'کلیدواژهٔ فارسی در نشانی دیده می‌شود؛ هنگام کپی‌کردن، نشانی بلند و کدشده می‌شود.', 'rokhdadyar' ); ?>
						</span>
					</label>
				</div>
				<div class="chk-grid chk-grid--4">
					<label class="chk-field">
						<span><?php esc_html_e( 'تعداد نامک پیشنهادی', 'rokhdadyar' ); ?></span>
						<input type="number" min="1" max="5" name="chk[slug_count]" value="<?php echo (int) $s['slug_count']; ?>">
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'حداکثر تعداد کلمه', 'rokhdadyar' ); ?></span>
						<input type="number" min="2" max="10" name="chk[slug_words]" value="<?php echo (int) $s['slug_words']; ?>">
					</label>
				</div>
			</div>
			<?php $chk_prompt( 'slug', __( 'پرامپت نامک', 'rokhdadyar' ), __( 'قاعدهٔ زبان از انتخاب بالا می‌آید و جای {slug_rules} می‌نشیند.', 'rokhdadyar' ), 11 ); ?>

			<div class="chk-card">
				<h2><?php esc_html_e( 'اندازه‌ها', 'rokhdadyar' ); ?></h2>
				<p class="chk-hint"><?php esc_html_e( 'پیش‌فرض‌ها برای نتایج گوگل تنظیم شده‌اند: عنوان تا ۶۰ و چکیده (توضیحات متا) تا ۱۶۰ نویسه.', 'rokhdadyar' ); ?></p>
				<div class="chk-grid chk-grid--4">
					<label class="chk-field">
						<span><?php esc_html_e( 'سقف نویسهٔ عنوان', 'rokhdadyar' ); ?></span>
						<input type="number" min="20" max="200" name="chk[title_max]" value="<?php echo (int) $s['title_max']; ?>">
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'تعداد چکیدهٔ پیشنهادی', 'rokhdadyar' ); ?></span>
						<input type="number" min="1" max="5" name="chk[excerpt_count]" value="<?php echo (int) $s['excerpt_count']; ?>">
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'سقف نویسهٔ چکیده', 'rokhdadyar' ); ?></span>
						<input type="number" min="60" max="1000" name="chk[excerpt_max]" value="<?php echo (int) $s['excerpt_max']; ?>">
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'زبان خروجی', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'خالی = زبان خودِ متن', 'rokhdadyar' ); ?></em></span>
						<input type="text" name="chk[language]" value="<?php echo esc_attr( $s['language'] ); ?>" placeholder="<?php esc_attr_e( 'مثلاً: فارسی', 'rokhdadyar' ); ?>">
					</label>
				</div>
			</div>
		</section>
