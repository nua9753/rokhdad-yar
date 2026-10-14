<?php
/**
 * مرکز کنترل — تب «رفتار، دسترسی و بار سرور». داخل views/settings.php بار می‌شود و متغیرهای آن را می‌بیند.
 */

namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;
?>
		<!-- رفتار -->
		<section class="chk-panel" data-panel="behavior">
			<div class="chk-card">
				<h2><?php esc_html_e( 'کجا فعال باشد؟', 'rokhdadyar' ); ?></h2>
				<div class="chk-checks">
					<?php foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $chk_pt ) : ?>
						<?php
						if ( 'attachment' === $chk_pt->name || ! post_type_supports( $chk_pt->name, 'title' ) ) {
							continue;
						}
						?>
						<label class="chk-check">
							<input type="checkbox" name="chk[post_types][]" value="<?php echo esc_attr( $chk_pt->name ); ?>" <?php checked( in_array( $chk_pt->name, $s['post_types'], true ) ); ?>>
							<span><?php echo esc_html( $chk_pt->labels->name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
			$chk_roles  = Access::roles();
			$chk_access = Access::lists();
			$chk_areas  = array(
				'text'  => __( 'عنوان، چکیده، نامک، دسته و برچسب', 'rokhdadyar' ),
				'image' => __( 'ساخت تصویر شاخص', 'rokhdadyar' ),
				'usage' => __( 'دیدن گزارش مصرف', 'rokhdadyar' ),
			);
			?>
			<div class="chk-card">
				<h2><?php esc_html_e( 'دسترسی نقش‌ها', 'rokhdadyar' ); ?></h2>
				<p class="chk-hint"><?php esc_html_e( 'تعیین کنید هر نقش کدام بخش را داشته باشد. مدیر سایت همیشه همه را دارد. ساخت تصویر به اجازهٔ بارگذاری پرونده در وردپرس هم نیاز دارد.', 'rokhdadyar' ); ?></p>
				<?php if ( $chk_roles ) : ?>
				<div class="chk-table-wrap">
					<table class="chk-table chk-access">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'نقش', 'rokhdadyar' ); ?></th>
								<?php foreach ( $chk_areas as $chk_al ) : ?>
									<th scope="col"><?php echo esc_html( $chk_al ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $chk_roles as $chk_rs => $chk_rn ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $chk_rn ); ?></th>
									<?php foreach ( $chk_areas as $chk_ak => $chk_al ) : ?>
										<td><input type="checkbox" name="chk[access][<?php echo esc_attr( $chk_ak ); ?>][]" value="<?php echo esc_attr( $chk_rs ); ?>" aria-label="<?php echo esc_attr( $chk_rn . ' — ' . $chk_al ); ?>" <?php checked( in_array( $chk_rs, $chk_access[ $chk_ak ], true ) ); ?>></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php else : ?>
					<p class="chk-hint"><?php esc_html_e( 'به‌جز مدیر، نقشی که به نوشته‌ها دسترسی داشته باشد در این سایت نیست.', 'rokhdadyar' ); ?></p>
				<?php endif; ?>
			</div>
			<?php $chk_ts = Terms::status(); ?>
			<div class="chk-card">
				<h2><?php esc_html_e( 'دسته و برچسب', 'rokhdadyar' ); ?></h2>
				<p class="chk-hint"><?php esc_html_e( 'از میان دسته‌ها و برچسب‌های موجودِ سایت پیشنهاد می‌دهد. فهرست کامل فقط یک بار ساخته و نگه داشته می‌شود و برای هر نوشته فقط فهرست کوتاهی از موارد مرتبط به مدل فرستاده می‌شود.', 'rokhdadyar' ); ?></p>
				<div class="chk-checks" style="margin-block:12px">
					<label class="chk-check"><input type="checkbox" name="chk[terms_cats]" value="1" <?php checked( $s['terms_cats'] ); ?>><span><?php esc_html_e( 'پیشنهاد دسته', 'rokhdadyar' ); ?></span></label>
					<label class="chk-check"><input type="checkbox" name="chk[terms_parents]" value="1" <?php checked( $s['terms_parents'] ); ?>><span><?php esc_html_e( 'سلسله‌مراتب دسته‌ها رعایت شود', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'دقیق‌ترین زیردسته انتخاب می‌شود و دسته‌های مادرش هم تیک می‌خورند. دسته‌ای که زیردسته ندارد خودش انتخاب می‌شود.', 'rokhdadyar' ); ?></em></span></label>
					<label class="chk-check"><input type="checkbox" name="chk[terms_tags]" value="1" <?php checked( $s['terms_tags'] ); ?>><span><?php esc_html_e( 'پیشنهاد برچسب', 'rokhdadyar' ); ?></span></label>
					<label class="chk-check"><input type="checkbox" name="chk[terms_new]" value="1" <?php checked( $s['terms_new'] ); ?>><span><?php esc_html_e( 'برچسب تازه هم پیشنهاد بدهد (حداکثر ۳)', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'خاموش بماند تا برچسب‌های سایت بی‌رویه زیاد نشوند.', 'rokhdadyar' ); ?></em></span></label>
				</div>
				<div class="chk-grid chk-grid--4">
					<label class="chk-field">
						<span><?php esc_html_e( 'حداکثر دستهٔ پیشنهادی', 'rokhdadyar' ); ?></span>
						<input type="number" min="1" max="5" name="chk[terms_cats_max]" value="<?php echo (int) $s['terms_cats_max']; ?>">
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'حداکثر برچسب پیشنهادی', 'rokhdadyar' ); ?></span>
						<input type="number" min="1" max="15" name="chk[terms_tags_max]" value="<?php echo (int) $s['terms_tags_max']; ?>">
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'فهرست کوتاه برچسب‌ها', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'چند برچسب برای هر نوشته به مدل برود', 'rokhdadyar' ); ?></em></span>
						<input type="number" min="10" max="300" name="chk[terms_shortlist]" value="<?php echo (int) $s['terms_shortlist']; ?>">
					</label>
				</div>
				<div class="chk-grid chk-grid--4">
					<label class="chk-field">
						<span><?php esc_html_e( 'نگه‌داری فهرست دسته‌ها (روز)', 'rokhdadyar' ); ?></span>
						<input type="number" min="1" max="730" name="chk[terms_cats_days]" value="<?php echo (int) $s['terms_cats_days']; ?>">
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'نگه‌داری فهرست برچسب‌ها (روز)', 'rokhdadyar' ); ?></span>
						<input type="number" min="1" max="730" name="chk[terms_tags_days]" value="<?php echo (int) $s['terms_tags_days']; ?>">
					</label>
				</div>
				<p class="chk-hint" style="margin-block-start:12px">
					<span data-terms-status>
						<?php
						foreach ( array( 'cats' => __( 'دسته‌ها', 'rokhdadyar' ), 'tags' => __( 'برچسب‌ها', 'rokhdadyar' ) ) as $chk_k => $chk_l ) {
							echo esc_html(
								$chk_ts[ $chk_k ]
									/* translators: 1: label, 2: count, 3: date */
									? sprintf( __( '%1$s: %2$d مورد، ساخته‌شده در %3$s. ', 'rokhdadyar' ), $chk_l, $chk_ts[ $chk_k ]['count'], $chk_ts[ $chk_k ]['date'] )
									/* translators: %s: label */
									: sprintf( __( '%s: هنوز ساخته نشده (با اولین استفاده ساخته می‌شود). ', 'rokhdadyar' ), $chk_l )
							);
						}
						?>
					</span>
					<button type="button" class="button-link" data-terms-refresh><?php esc_html_e( 'همین حالا از نو بساز', 'rokhdadyar' ); ?></button>
				</p>
				<p class="chk-hint"><?php esc_html_e( 'دسته یا برچسبی که بعد از ساخت فهرست به سایت اضافه شود، تا بازسازی بعدی پیشنهاد داده نمی‌شود.', 'rokhdadyar' ); ?></p>
			</div>
			<div class="chk-card">
				<h2><?php esc_html_e( 'مصرف توکن', 'rokhdadyar' ); ?></h2>
				<label class="chk-check">
					<input type="checkbox" name="chk[bundle]" value="1" <?php checked( $s['bundle'] ); ?>>
					<span>
						<?php esc_html_e( 'عنوان، چکیده، نامک، دسته و برچسب و شرح تصویر یک‌جا ساخته شوند', 'rokhdadyar' ); ?>
						<em><?php esc_html_e( 'متن نوشته فقط یک بار فرستاده می‌شود و دکمه‌های بعدیِ همان نوشته فوری و بدون توکن از حافظه جواب می‌گیرند. در عوض، اولین دکمه کمی بیشتر طول می‌کشد. فقط وقتی کار می‌کند که مدلِ عنوان و چکیده یکی باشند.', 'rokhdadyar' ); ?></em>
					</span>
				</label>
				<p class="chk-hint"><?php esc_html_e( 'جدا از این، متن نوشته همیشه اولِ پیام فرستاده می‌شود تا «دوباره بساز» و درخواست‌های بعدیِ همان نوشته از تخفیف کشِ خودِ سرویس (OpenAI، DeepSeek، Gemini، Claude و …) استفاده کنند.', 'rokhdadyar' ); ?></p>
			</div>
			<div class="chk-card">
				<h2><?php esc_html_e( 'کلیدواژه', 'rokhdadyar' ); ?></h2>
				<label class="chk-check">
					<input type="checkbox" name="chk[ask_keyword]" value="1" <?php checked( $s['ask_keyword'] ); ?>>
					<span>
						<?php esc_html_e( 'اگر نوشته کلیدواژه ندارد، پیش از ساخت بپرس', 'rokhdadyar' ); ?>
						<em><?php esc_html_e( 'این‌طوری هیچ درخواستی بدون کلیدواژه هدر نمی‌رود. کلیدواژهٔ واردشده روی همان نوشته ذخیره می‌شود و دفعهٔ بعد پرسیده نمی‌شود.', 'rokhdadyar' ); ?></em>
					</span>
				</label>
			</div>
			<div class="chk-card">
				<h2><?php esc_html_e( 'ثبت نتیجه', 'rokhdadyar' ); ?></h2>
				<label class="chk-check">
					<input type="checkbox" name="chk[direct_apply]" value="1" <?php checked( $s['direct_apply'] ); ?>>
					<span>
						<?php esc_html_e( 'بدون پیش‌نمایش ثبت شود', 'rokhdadyar' ); ?>
						<em><?php esc_html_e( 'چکیده و اولین عنوان پیشنهادی مستقیم جایگزین می‌شوند. پیش‌فرض خاموش است تا چیزی ناخواسته بازنویسی نشود.', 'rokhdadyar' ); ?></em>
					</span>
				</label>
				<label class="chk-field chk-field--narrow">
					<span><?php esc_html_e( 'حافظهٔ نتایج (ساعت)', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'تا متن عوض نشده، کلیک دوباره هزینه ندارد. ۰ = خاموش', 'rokhdadyar' ); ?></em></span>
					<input type="number" min="0" max="720" name="chk[cache_hours]" value="<?php echo (int) $s['cache_hours']; ?>">
				</label>
			</div>
			<div class="chk-card">
				<h2><?php esc_html_e( 'محافظ نامکِ نوشته‌های منتشرشده', 'rokhdadyar' ); ?></h2>
				<p class="chk-hint"><?php esc_html_e( 'تغییر نامکِ یک نوشتهٔ منتشرشده یعنی تغییر آدرس صفحه؛ ممکن است رتبهٔ گوگل از دست برود یا صفحه از نتایج حذف شود. این محافظ روی همهٔ راه‌ها کار می‌کند: ویرایشگر کلاسیک، ویرایشگر بلوکی، ویرایش سریع و دکمهٔ نامکِ همین افزونه. در هر حالت، نامک قبلی در «تاریخچه و برگشت» می‌ماند.', 'rokhdadyar' ); ?></p>
				<div class="chk-options" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
					<?php
					$chk_guards = array(
						'confirm' => array( __( 'هشدار جدی و تأیید', 'rokhdadyar' ), __( 'پیش از تغییر، هشدار قرمز با پیامدها نشان داده می‌شود و فقط با تیکِ «می‌دانم…» اجازه می‌دهد. (پیشنهادی)', 'rokhdadyar' ) ),
						'lock'    => array( __( 'قفل؛ فقط مدیر سایت', 'rokhdadyar' ), __( 'بقیهٔ نقش‌ها به هیچ راهی نمی‌توانند نامکِ نوشتهٔ منتشرشده را عوض کنند؛ مدیر هم هشدار را می‌بیند.', 'rokhdadyar' ) ),
						'off'     => array( __( 'خاموش', 'rokhdadyar' ), __( 'بدون هشدار؛ فقط تاریخچه ثبت می‌شود.', 'rokhdadyar' ) ),
					);
					foreach ( $chk_guards as $chk_gk => $chk_gv ) :
						?>
						<label class="chk-option">
							<input type="radio" name="chk[slug_guard]" value="<?php echo esc_attr( $chk_gk ); ?>" <?php checked( Guard::mode(), $chk_gk ); ?>>
							<span><strong><?php echo esc_html( $chk_gv[0] ); ?></strong><em><?php echo esc_html( $chk_gv[1] ); ?></em></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="chk-card">
				<h2><?php esc_html_e( 'بار سرور', 'rokhdadyar' ); ?></h2>
				<label class="chk-field chk-field--narrow">
					<span><?php esc_html_e( 'حداکثر درخواست هم‌زمان در کل سایت', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'هر درخواست تا رسیدن پاسخ یک پردازهٔ PHP را نگه می‌دارد. روی هاست اشتراکی ۱ یا ۲ بگذارید. ۰ = بی‌سقف', 'rokhdadyar' ); ?></em></span>
					<input type="number" min="0" max="10" name="chk[max_parallel]" value="<?php echo (int) $s['max_parallel']; ?>">
				</label>
				<?php $chk_busy = Gate::busy(); ?>
				<?php if ( $chk_busy ) : ?>
					<p class="chk-hint">
						<?php
						/* translators: %d: running requests */
						echo esc_html( sprintf( __( 'همین حالا %d درخواست در حال انجام است.', 'rokhdadyar' ), $chk_busy ) );
						?>
					</p>
				<?php endif; ?>
			</div>
		</section>
