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
$chk_imp   = $chk_tools['important'];
$chk_imps  = $chk_imp['on'] ? Links::index() : array();
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
				</header>

				<?php if ( $chk_pin['on'] ) : ?>
					<details class="chk-pins" <?php echo $chk_map ? 'open' : ''; ?>>
						<summary>
							<?php
							/* translators: %d: number of pinned posts */
							echo esc_html( sprintf( __( 'مطلب‌های پین‌شده: %d', 'rokhdadyar' ), count( $chk_map ) ) );
							?>
						</summary>
						<?php if ( ! $chk_map ) : ?>
							<p class="chk-hint"><?php esc_html_e( 'هنوز مطلبی پین نشده است. در ویرایشگرِ مطلب، در جعبهٔ «جعبه ابزار» تیک «پین در دسته‌بندی» را بزنید.', 'rokhdadyar' ); ?></p>
						<?php else : ?>
							<ul class="chk-pins__list">
								<?php
								uasort(
									$chk_map,
									static function ( $a, $b ) {
										return (int) $b['t'] <=> (int) $a['t'];
									}
								);
								foreach ( $chk_map as $chk_pid => $chk_row ) :
									$chk_p = get_post( (int) $chk_pid );
									if ( ! $chk_p ) {
										continue;
									}
									$chk_cats = array();
									foreach ( (array) $chk_row['c'] as $chk_t ) {
										$chk_term = get_term( (int) $chk_t, 'category' );
										if ( $chk_term && ! is_wp_error( $chk_term ) ) {
											$chk_cats[] = '<a href="' . esc_url( get_term_link( $chk_term ) ) . '" target="_blank">' . esc_html( $chk_term->name ) . '</a>';
										}
									}
									?>
									<li>
										<a class="chk-pins__title" href="<?php echo esc_url( get_permalink( $chk_p ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $chk_p ) ); ?></a>
										<span class="chk-pins__meta">
											<?php echo $chk_cats ? esc_html__( 'پین در:', 'rokhdadyar' ) . ' ' . implode( '، ', $chk_cats ) : esc_html__( 'دسته‌ای برای پین ندارد', 'rokhdadyar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
											· <?php echo esc_html( wp_date( get_option( 'date_format' ), (int) $chk_row['t'] ) ); ?>
										</span>
										<span class="chk-pins__actions">
											<?php if ( current_user_can( 'edit_post', $chk_p->ID ) ) : ?>
												<a href="<?php echo esc_url( get_edit_post_link( $chk_p->ID ) ); ?>"><?php esc_html_e( 'ویرایش', 'rokhdadyar' ); ?></a>
												<a class="chk-pins__unpin" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rokhdadyar_unpin&post=' . $chk_p->ID ), 'rokhdadyar_unpin_' . $chk_p->ID ) ); ?>"><?php esc_html_e( 'برداشتن پین', 'rokhdadyar' ); ?></a>
											<?php endif; ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</details>
				<?php endif; ?>

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

			<article class="chk-card chk-tool">
				<header class="chk-tool__head">
					<label class="chk-check">
						<input type="checkbox" name="chk[tools][important][on]" value="1" <?php checked( $chk_imp['on'] ); ?>>
						<span>
							<strong><?php esc_html_e( 'مطالب مهم', 'rokhdadyar' ); ?></strong>
							<em><?php esc_html_e( 'در جعبه ابزارِ ویرایشگر، تیک «مطلب مهم» اضافه می‌شود. مطالب مهم همان صفحه‌هایی‌اند که می‌خواهید لینک داخلی بگیرند (مثلاً صفحه‌های اصلی و راهنماهای جامع).', 'rokhdadyar' ); ?></em>
						</span>
					</label>
				</header>

				<label class="chk-check">
					<input type="checkbox" name="chk[tools][important][links]" value="1" <?php checked( $chk_imp['links'] ); ?>>
					<span>
						<strong><?php esc_html_e( 'پیشنهاد لینک داخلی با هوش مصنوعی', 'rokhdadyar' ); ?></strong>
						<em><?php esc_html_e( 'کنار دکمه‌های عنوان و چکیده، دکمهٔ «لینک داخلی» می‌آید. هوش مصنوعی متن را می‌خواند و از میان «مطالب مهم» (نه هر مطلبی) آن‌هایی را که واقعاً مرتبط‌اند پیشنهاد می‌دهد، همراه با عبارتی از خودِ متن برای لینک. شما انتخاب می‌کنید و لینک‌ها در متن درج می‌شوند.', 'rokhdadyar' ); ?></em>
					</span>
				</label>
				<div class="chk-grid chk-grid--4">
					<label class="chk-field">
						<span><?php esc_html_e( 'حداکثر لینک پیشنهادی', 'rokhdadyar' ); ?></span>
						<input type="number" min="1" max="10" name="chk[tools][important][max]" value="<?php echo (int) $chk_imp['max']; ?>">
					</label>
				</div>

				<?php if ( $chk_imp['on'] ) : ?>
					<details class="chk-pins" <?php echo $chk_imps ? 'open' : ''; ?>>
						<summary>
							<?php
							/* translators: %d: number of important posts */
							echo esc_html( sprintf( __( 'مطالب مهم: %d', 'rokhdadyar' ), count( $chk_imps ) ) );
							?>
						</summary>
						<?php if ( ! $chk_imps ) : ?>
							<p class="chk-hint"><?php esc_html_e( 'هنوز مطلبی مهم علامت نخورده است. در ویرایشگرِ مطلب، در «جعبه ابزار» تیک «مطلب مهم» را بزنید.', 'rokhdadyar' ); ?></p>
						<?php else : ?>
							<ul class="chk-pins__list">
								<?php foreach ( $chk_imps as $chk_it ) : ?>
									<li>
										<a class="chk-pins__title" href="<?php echo esc_url( $chk_it['url'] ); ?>" target="_blank"><?php echo esc_html( $chk_it['title'] ); ?></a>
										<?php if ( '' !== $chk_it['kw'] ) : ?>
											<span class="chk-pins__meta"><?php echo esc_html__( 'کلیدواژه:', 'rokhdadyar' ) . ' ' . esc_html( $chk_it['kw'] ); ?></span>
										<?php endif; ?>
										<span class="chk-pins__actions">
											<?php if ( current_user_can( 'edit_post', $chk_it['id'] ) ) : ?>
												<a href="<?php echo esc_url( get_edit_post_link( $chk_it['id'] ) ); ?>"><?php esc_html_e( 'ویرایش', 'rokhdadyar' ); ?></a>
												<a class="chk-pins__unpin" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rokhdadyar_unimportant&post=' . $chk_it['id'] ), 'rokhdadyar_unimportant_' . $chk_it['id'] ) ); ?>"><?php esc_html_e( 'برداشتن از مهم‌ها', 'rokhdadyar' ); ?></a>
											<?php endif; ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</details>
				<?php endif; ?>

				<div class="chk-tool__roles">
					<p class="chk-tool__label"><?php esc_html_e( 'چه نقش‌هایی بتوانند مطلب را مهم علامت بزنند', 'rokhdadyar' ); ?></p>
					<div class="chk-checks">
						<?php foreach ( $chk_rl as $chk_rs => $chk_rn ) : ?>
							<label class="chk-check">
								<input type="checkbox" name="chk[tools][important][roles][]" value="<?php echo esc_attr( $chk_rs ); ?>" <?php checked( in_array( $chk_rs, (array) $chk_imp['roles'], true ) ); ?>>
								<span><?php echo esc_html( $chk_rn ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
				<p class="chk-hint"><?php esc_html_e( 'دکمهٔ «لینک داخلی» را هر کس که به ابزارهای متنی (عنوان و چکیده) دسترسی دارد می‌بیند.', 'rokhdadyar' ); ?></p>
			</article>
		</section>
