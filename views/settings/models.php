<?php
/**
 * مرکز کنترل — تب «مدل‌ها». داخل views/settings.php بار می‌شود و متغیرهای آن را می‌بیند.
 */

namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;
?>
		<!-- مدل‌ها -->
		<section class="chk-panel is-active" data-panel="models">
			<div class="chk-card chk-route">
				<label class="chk-field">
					<span><?php esc_html_e( 'مدلِ عنوان', 'rokhdadyar' ); ?></span>
					<select name="chk[title_profile]" data-route data-current="<?php echo esc_attr( $s['title_profile'] ); ?>"></select>
				</label>
				<label class="chk-field">
					<span><?php esc_html_e( 'مدلِ چکیده', 'rokhdadyar' ); ?></span>
					<select name="chk[excerpt_profile]" data-route data-current="<?php echo esc_attr( $s['excerpt_profile'] ); ?>"></select>
				</label>
				<p class="chk-hint"><?php esc_html_e( 'می‌توانید برای عنوان یک مدل خلاق‌تر و برای چکیده یک مدل ارزان و سریع انتخاب کنید.', 'rokhdadyar' ); ?></p>
			</div>

			<div data-profiles>
				<?php
				foreach ( array_values( $s['profiles'] ) as $chk_i => $chk_p ) {
					$chk_profile( $chk_i, $chk_p );
				}
				?>
			</div>

			<div class="chk-add">
				<label for="chk-preset"><?php esc_html_e( 'افزودن مدل از سرویس:', 'rokhdadyar' ); ?></label>
				<select id="chk-preset" data-preset>
					<?php foreach ( Settings::presets() as $chk_k => $chk_ps ) : ?>
						<option value="<?php echo esc_attr( $chk_k ); ?>"><?php echo esc_html( 'custom' === $chk_k ? __( 'سفارشی (هر سرویس سازگار با OpenAI)', 'rokhdadyar' ) : $chk_ps[0] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button button-primary" data-add><?php esc_html_e( 'افزودن', 'rokhdadyar' ); ?></button>
			</div>

			<template data-profile-tpl>
				<?php
				$chk_profile(
					'__i__',
					array(
						'temperature' => '0.7',
						'max_tokens'  => 0,
					)
				);
				?>
			</template>
		</section>
