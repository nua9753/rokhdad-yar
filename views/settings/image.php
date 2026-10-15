<?php
/**
 * مرکز کنترل — تب «تصویر شاخص». داخل views/settings.php بار می‌شود و متغیرهای آن را می‌بیند.
 */

namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;
?>
		<!-- تصویر شاخص: سرویس و منطق جدا -->
		<?php $chk_img = Image::config(); ?>
		<section class="chk-panel" data-panel="image">
			<div class="chk-card">
				<label class="chk-check">
					<input type="checkbox" name="chk[image][enabled]" value="1" <?php checked( $chk_img['enabled'] ); ?>>
					<span>
						<strong><?php esc_html_e( 'ساخت تصویر شاخص با هوش مصنوعی فعال باشد', 'rokhdadyar' ); ?></strong>
						<em><?php esc_html_e( 'یک دکمهٔ سوم کنار عنوان و چکیده اضافه می‌شود. تصویر اول پیش‌نمایش داده می‌شود و فقط با تأیید شما تصویر شاخص می‌شود.', 'rokhdadyar' ); ?></em>
					</span>
				</label>
			</div>

			<article class="chk-card" data-profile>
				<input type="hidden" value="image" data-f="id">
				<h2><?php esc_html_e( 'سرویس تصویر', 'rokhdadyar' ); ?></h2>
				<p class="chk-hint"><?php esc_html_e( 'کلید و نشانی این بخش از مدل‌های متنی جداست.', 'rokhdadyar' ); ?></p>
				<div class="chk-grid" style="margin-block-start:12px">
					<label class="chk-field">
						<span><?php esc_html_e( 'قالب API', 'rokhdadyar' ); ?></span>
						<select name="chk[image][format]" data-f="format">
							<option value="openai" <?php selected( $chk_img['format'], 'openai' ); ?>><?php esc_html_e( 'سازگار با OpenAI Images (‎/images/generations‎)', 'rokhdadyar' ); ?></option>
							<option value="gemini" <?php selected( $chk_img['format'], 'gemini' ); ?>><?php esc_html_e( 'Google Gemini (مدل‌های تصویرساز)', 'rokhdadyar' ); ?></option>
						</select>
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'نام مدل تصویر', 'rokhdadyar' ); ?></span>
						<span class="chk-combo">
							<input type="text" class="chk-ltr" name="chk[image][model]" value="<?php echo esc_attr( $chk_img['model'] ); ?>" placeholder="gpt-image-1" data-f="model" spellcheck="false" autocomplete="off">
							<button type="button" class="button" data-models><?php esc_html_e( 'فهرست مدل‌ها', 'rokhdadyar' ); ?></button>
						</span>
						<datalist data-model-list></datalist>
						<small class="chk-models-out" data-models-out aria-live="polite"></small>
					</label>
					<label class="chk-field chk-field--wide">
						<span><?php esc_html_e( 'Base URL', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'خالی = نشانی رسمی همان قالب', 'rokhdadyar' ); ?></em></span>
						<input type="url" class="chk-ltr" name="chk[image][base_url]" value="<?php echo esc_attr( $chk_img['base_url'] ); ?>" placeholder="https://api.openai.com/v1" data-f="base_url" spellcheck="false">
					</label>
					<label class="chk-field chk-field--wide">
						<span><?php esc_html_e( 'کلید API', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'رمزشده ذخیره می‌شود و دوباره نمایش داده نمی‌شود', 'rokhdadyar' ); ?></em></span>
						<input type="password" class="chk-ltr" name="chk[image][api_key]" value="" placeholder="<?php echo esc_attr( Settings::mask( $chk_img['api_key'] ) ? Settings::mask( $chk_img['api_key'] ) : 'sk-…' ); ?>" data-f="api_key" autocomplete="new-password" spellcheck="false">
						<?php if ( Settings::key_lost( (string) $chk_img['api_key'] ) ) : ?>
							<span class="chk-keylost"><?php esc_html_e( 'کلید ذخیره‌شده دیگر خوانده نمی‌شود؛ دوباره واردش کنید.', 'rokhdadyar' ); ?></span>
						<?php endif; ?>
					</label>
					<div class="chk-field">
						<span><?php esc_html_e( 'اندازهٔ نهایی فایل (پیکسل)', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'عرض × ارتفاع', 'rokhdadyar' ); ?></em></span>
						<span class="chk-combo">
							<input type="number" class="chk-ltr" min="600" max="2400" step="1" name="chk[image][width]" value="<?php echo (int) $chk_img['width']; ?>" aria-label="<?php esc_attr_e( 'عرض', 'rokhdadyar' ); ?>">
							<input type="number" class="chk-ltr" min="315" max="2400" step="1" name="chk[image][height]" value="<?php echo (int) $chk_img['height']; ?>" aria-label="<?php esc_attr_e( 'ارتفاع', 'rokhdadyar' ); ?>">
						</span>
					</div>
					<p class="chk-hint chk-field--wide"><?php esc_html_e( 'تصویر همیشه دقیقاً به همین اندازه ذخیره می‌شود (برش از مرکز). برای Google Discover عرض دست‌کم ۱۲۰۰ پیکسل لازم است.', 'rokhdadyar' ); ?></p>
					<label class="chk-check chk-field--wide">
						<input type="checkbox" name="chk[image][webp]" value="1" <?php checked( $chk_img['webp'] ); ?>>
						<span><?php esc_html_e( 'به WebP تبدیل شود', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'حجم تصویر چند برابر کمتر می‌شود؛ برای سرعت صفحه و سئو بهتر است.', 'rokhdadyar' ); ?></em></span>
					</label>
					<label class="chk-field chk-field--wide">
						<span><?php esc_html_e( 'نام پیش‌فرض فایل تصویر', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'متغیرها: {slug} نامک نوشته، {id} شناسهٔ نوشته، {site} نام دامنهٔ سایت، {date} تاریخ و ساعت ساخت (جلوی نام تکراری را می‌گیرد). بقیه را هر چه بنویسید عیناً می‌آید.', 'rokhdadyar' ); ?></em></span>
						<input type="text" class="chk-ltr" name="chk[image][filename]" value="<?php echo esc_attr( $chk_img['filename'] ); ?>" placeholder="{slug}-{date}" spellcheck="false">
						<em class="chk-hint">
							<?php
							/* translators: %s: example file name */
							echo esc_html( sprintf( __( 'نمونه: %s', 'rokhdadyar' ), Image::file_name( new \WP_Post( (object) array( 'ID' => 123, 'post_name' => 'nail-polish-trends' ) ), (string) $chk_img['filename'] ) . '.webp' ) );
							?>
						</em>
					</label>
				</div>
			</article>

			<div class="chk-card">
				<h2><?php esc_html_e( 'کنترل هزینه', 'rokhdadyar' ); ?></h2>
				<p class="chk-hint"><?php esc_html_e( 'هزینهٔ هر تصویر را وضوح و کیفیت تعیین می‌کند. فایل نهایی به اندازهٔ بالا (پیش‌فرض ۱۲۰۰×۸۰۰) بریده می‌شود، پس وضوح بالاتر از «استاندارد» در سایت دیده نمی‌شود و فقط هزینه دارد.', 'rokhdadyar' ); ?></p>
				<div class="chk-grid" style="margin-block-start:12px">
					<label class="chk-field">
						<span><?php esc_html_e( 'وضوحِ درخواستی از مدل', 'rokhdadyar' ); ?></span>
						<select name="chk[image][tier]" data-tier>
							<option value="eco" <?php selected( $chk_img['tier'], 'eco' ); ?>><?php esc_html_e( 'اقتصادی — 1024×1024 (ارزان‌ترین؛ بالا و پایین بریده می‌شود)', 'rokhdadyar' ); ?></option>
							<option value="std" <?php selected( $chk_img['tier'], 'std' ); ?>><?php esc_html_e( 'استاندارد — 1536×1024 (پیشنهادی؛ بدون برش)', 'rokhdadyar' ); ?></option>
							<option value="2k" <?php selected( $chk_img['tier'], '2k' ); ?>><?php esc_html_e( '2K — 2048×1360 (گران)', 'rokhdadyar' ); ?></option>
							<option value="4k" <?php selected( $chk_img['tier'], '4k' ); ?>><?php esc_html_e( '4K — 3840×2160 (گران‌ترین)', 'rokhdadyar' ); ?></option>
							<option value="custom" <?php selected( $chk_img['tier'], 'custom' ); ?>><?php esc_html_e( 'سفارشی', 'rokhdadyar' ); ?></option>
						</select>
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'کیفیت', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'بیشترین اثر را روی قیمت دارد', 'rokhdadyar' ); ?></em></span>
						<select name="chk[image][quality]">
							<option value="low" <?php selected( $chk_img['quality'], 'low' ); ?>><?php esc_html_e( 'پایین — ارزان‌ترین و سریع‌ترین', 'rokhdadyar' ); ?></option>
							<option value="medium" <?php selected( $chk_img['quality'], 'medium' ); ?>><?php esc_html_e( 'متوسط — پیشنهادی', 'rokhdadyar' ); ?></option>
							<option value="high" <?php selected( $chk_img['quality'], 'high' ); ?>><?php esc_html_e( 'بالا — گران', 'rokhdadyar' ); ?></option>
							<option value="" <?php selected( $chk_img['quality'], '' ); ?>><?php esc_html_e( 'پیش‌فرض مدل (معمولاً گران‌ترین)', 'rokhdadyar' ); ?></option>
						</select>
					</label>
					<label class="chk-field" data-custom-size>
						<span><?php esc_html_e( 'اندازهٔ سفارشی', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'فقط وقتی وضوح «سفارشی» باشد؛ خالی = پیش‌فرض مدل', 'rokhdadyar' ); ?></em></span>
						<input type="text" class="chk-ltr" name="chk[image][size]" value="<?php echo esc_attr( $chk_img['size'] ); ?>" placeholder="1536x1024">
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'سقف تعداد تصویر در روز', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'برای کل سایت؛ ۰ = بدون سقف', 'rokhdadyar' ); ?></em></span>
						<input type="number" min="0" max="100000" name="chk[image][daily_cap]" value="<?php echo (int) $chk_img['daily_cap']; ?>">
					</label>
				</div>
				<p class="chk-hint">
					<?php
					/* translators: %d: images generated today */
					echo esc_html( sprintf( __( 'امروز تا این لحظه %d تصویر از سرویس گرفته شده است («دوباره بساز» هم شمرده می‌شود).', 'rokhdadyar' ), Image::used_today() ) );
					?>
				</p>
				<p class="chk-hint"><?php esc_html_e( 'کیفیت فقط در قالب «سازگار با OpenAI Images» فرستاده می‌شود. اگر مدلی وضوح یا کیفیتِ انتخابی را نپذیرد، افزونه همان گزینه را برمی‌دارد و با پیش‌فرض مدل می‌سازد.', 'rokhdadyar' ); ?></p>
			</div>

			<div class="chk-card">
				<h2><?php esc_html_e( 'ظاهر و محتوای تصویر', 'rokhdadyar' ); ?></h2>
				<label class="chk-field" style="margin-block:12px">
					<span><?php esc_html_e( 'سبک ثابت تصویرها', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'برای اینکه همهٔ تصویرهای سایت یک‌دست باشند؛ انگلیسی بنویسید', 'rokhdadyar' ); ?></em></span>
					<input type="text" class="chk-ltr" name="chk[image][style]" value="<?php echo esc_attr( $chk_img['style'] ); ?>">
				</label>
				<label class="chk-check">
					<input type="checkbox" name="chk[image][writer]" value="1" <?php checked( $chk_img['writer'] ); ?>>
					<span>
						<?php esc_html_e( 'شرح تصویر و متن جایگزین را «مدلِ عنوان» از روی متن نوشته بنویسد', 'rokhdadyar' ); ?>
						<em><?php esc_html_e( 'تصویر به موضوع واقعی مطلب نزدیک‌تر می‌شود و alt سئویی می‌گیرد. اگر خاموش باشد، تصویر فقط از روی عنوان ساخته می‌شود.', 'rokhdadyar' ); ?></em>
					</span>
				</label>
				<div class="chk-card__head" style="margin-block-start:16px">
					<label for="chk-img-prompt"><strong><?php esc_html_e( 'پرامپتِ نوشتنِ شرح تصویر', 'rokhdadyar' ); ?></strong></label>
					<button type="button" class="button-link" data-reset="image"><?php esc_html_e( 'برگرداندن به پیش‌فرض', 'rokhdadyar' ); ?></button>
				</div>
				<textarea id="chk-img-prompt" class="chk-prompt" name="chk[image][prompt]" rows="9" data-prompt="image" dir="auto"><?php echo esc_textarea( $chk_img['prompt'] ); ?></textarea>
				<p class="chk-hint"><?php esc_html_e( 'متغیرها: {style} {title} {keyword} {content}. خروجی باید دو خط PROMPT: و ALT: باشد.', 'rokhdadyar' ); ?></p>
			</div>

			<div class="chk-card chk-health" data-health>
				<div class="chk-card__head">
					<div>
						<h2><?php esc_html_e( 'سلامت آپلود', 'rokhdadyar' ); ?></h2>
						<p class="chk-hint"><?php esc_html_e( 'اگر آپلود تصویر (در کتابخانهٔ رسانه، از نشانی بیرونی یا همین افزونه) گاهی شکست می‌خورد یا خطای «پیوست‌ها نمی‌توانند در پایگاه داده درج شوند» می‌آید، این بررسی علت را پیدا می‌کند. فقط می‌خواند و یک ردیف آزمایشی را درج و فوراً حذف می‌کند.', 'rokhdadyar' ); ?></p>
					</div>
					<button type="button" class="button" data-health-run><?php esc_html_e( 'بررسی کن', 'rokhdadyar' ); ?></button>
				</div>
				<ul class="chk-health__list" data-health-out aria-live="polite"></ul>
				<div class="chk-health__fix" data-health-fix hidden>
					<p class="chk-hint"><?php esc_html_e( 'تعمیر: ردیف‌های با شناسهٔ ۰ به شناسهٔ تازه منتقل می‌شوند (چیزی حذف نمی‌شود) و کلید اصلی و AUTO_INCREMENT جدول‌ها برمی‌گردد. پیش از تعمیر از پایگاه‌داده پشتیبان بگیرید.', 'rokhdadyar' ); ?></p>
					<button type="button" class="button button-primary" data-health-repair><?php esc_html_e( 'تعمیر پایگاه‌داده', 'rokhdadyar' ); ?></button>
				</div>
			</div>
		</section>
