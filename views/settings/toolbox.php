<?php
/**
 * مرکز کنترل — تب «جعبه ابزار». داخل views/settings.php بار می‌شود.
 */

namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

$chk_tools = Toolbox::config();
$chk_pin   = $chk_tools['pin'];
$chk_rl    = Access::roles();
$chk_map   = (array) ( Pin::front()['map'] ?? array() );
?>
		<!-- جعبه ابزار -->
		<section class="chk-panel" data-panel="toolbox">
			<div class="chk-card">
				<h2><?php esc_html_e( 'جعبه ابزار', 'rokhdadyar' ); ?></h2>
				<p class="chk-hint"><?php esc_html_e( 'ابزارهای جانبیِ نوشته‌ها. هر ابزار جدا روشن می‌شود و جدا تعیین می‌کنید کدام نقش‌ها به آن دسترسی دارند. ابزارهای روشن در ویرایشگر، در جعبه‌ای جدا به نام «جعبه ابزار» می‌آیند. مدیر سایت همیشه به همه دسترسی دارد.', 'rokhdadyar' ); ?></p>
			</div>

			<article class="chk-card chk-tool">
				<header class="chk-tool__head">
					<label class="chk-check">
						<input type="checkbox" name="chk[tools][pin][on]" value="1" <?php checked( $chk_pin['on'] ); ?>>
						<span>
							<strong><?php esc_html_e( 'پین در دسته‌بندی', 'rokhdadyar' ); ?></strong>
							<em><?php esc_html_e( 'مطلبی که تیک «پین» بخورد، در صفحهٔ دسته‌های سطح اول و دومِ خودش همیشه بالای فهرست می‌ماند و کنار عنوانش یک نشان می‌نشیند. مثلاً مطلبی در «مد › لاک ناخن › لاک ژله‌ای» در «مد» و «لاک ناخن» پین می‌شود.', 'rokhdadyar' ); ?></em>
						</span>
					</label>
					<?php if ( $chk_pin['on'] ) : ?>
						<span class="chk-badge is-style">
							<?php
							/* translators: %d: number of pinned posts */
							echo esc_html( sprintf( __( '%d مطلب پین است', 'rokhdadyar' ), count( $chk_map ) ) );
							?>
						</span>
					<?php endif; ?>
				</header>

				<div class="chk-grid chk-grid--4">
					<label class="chk-field">
						<span><?php esc_html_e( 'متن نشان', 'rokhdadyar' ); ?></span>
						<input type="text" name="chk[tools][pin][label]" value="<?php echo esc_attr( $chk_pin['label'] ); ?>" maxlength="30" data-pin-label>
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'رنگ نشان', 'rokhdadyar' ); ?></span>
						<input type="color" name="chk[tools][pin][color]" value="<?php echo esc_attr( $chk_pin['color'] ); ?>" data-pin-color>
					</label>
					<label class="chk-field">
						<span><?php esc_html_e( 'حداکثر پین در هر دسته', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'تازه‌ترین پین‌ها اول', 'rokhdadyar' ); ?></em></span>
						<input type="number" min="1" max="10" name="chk[tools][pin][max]" value="<?php echo (int) $chk_pin['max']; ?>">
					</label>
					<div class="chk-field">
						<span><?php esc_html_e( 'پیش‌نمایش', 'rokhdadyar' ); ?></span>
						<span class="chk-pin-preview"><span class="chk-pin-badge" data-pin-preview style="background:<?php echo esc_attr( $chk_pin['color'] ); ?>"><?php echo esc_html( $chk_pin['label'] ); ?></span> <?php esc_html_e( 'عنوان مطلب در سایت', 'rokhdadyar' ); ?></span>
					</div>
				</div>

				<div class="chk-tool__roles">
					<p class="chk-tool__label"><?php esc_html_e( 'چه نقش‌هایی بتوانند پین کنند', 'rokhdadyar' ); ?></p>
					<div class="chk-checks">
						<?php foreach ( $chk_rl as $chk_rs => $chk_rn ) : ?>
							<label class="chk-check">
								<input type="checkbox" name="chk[tools][pin][roles][]" value="<?php echo esc_attr( $chk_rs ); ?>" <?php checked( in_array( $chk_rs, (array) $chk_pin['roles'], true ) ); ?>>
								<span><?php echo esc_html( $chk_rn ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
				<p class="chk-hint"><?php esc_html_e( 'در خروجی سایت هیچ نامی از این افزونه نمی‌آید. نشان کنار عنوان در فهرست دستهٔ مربوط نشان داده می‌شود و نوشته کلاس «sticky» هم می‌گیرد تا پوسته‌هایی که برای مطلب سنجاق‌شده ظاهر ویژه دارند همان را نشان دهند.', 'rokhdadyar' ); ?></p>
			</article>
		</section>
