<?php
/**
 * مرکز کنترل.
 *
 * @var array $s تنظیمات فعلی.
 */

namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;

$chk_formats = array(
	'openai'    => __( 'سازگار با OpenAI', 'rokhdadyar' ),
	'anthropic' => 'Anthropic',
	'gemini'    => 'Google Gemini',
);

$chk_profile = static function ( $i, array $p ) use ( $chk_formats ) {
	$n    = 'chk[profiles][' . $i . ']';
	$mask = Settings::mask( (string) ( $p['api_key'] ?? '' ) );
	?>
	<article class="chk-card chk-profile" data-profile>
		<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $p['id'] ?? '' ); ?>" data-f="id">
		<header class="chk-profile__head">
			<input class="chk-profile__label" type="text" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $p['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'نام دلخواه، مثلاً «GPT سریع»', 'rokhdadyar' ); ?>" data-f="label" aria-label="<?php esc_attr_e( 'نام مدل', 'rokhdadyar' ); ?>">
			<span class="chk-test" data-test-out aria-live="polite"></span>
			<button type="button" class="button" data-test><?php esc_html_e( 'آزمایش اتصال', 'rokhdadyar' ); ?></button>
			<button type="button" class="chk-icon-btn" data-remove aria-label="<?php esc_attr_e( 'حذف مدل', 'rokhdadyar' ); ?>" title="<?php esc_attr_e( 'حذف مدل', 'rokhdadyar' ); ?>">×</button>
		</header>
		<div class="chk-grid">
			<label class="chk-field">
				<span><?php esc_html_e( 'قالب API', 'rokhdadyar' ); ?></span>
				<select name="<?php echo esc_attr( $n ); ?>[format]" data-f="format">
					<?php foreach ( $chk_formats as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $p['format'] ?? 'openai', $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="chk-field">
				<span><?php esc_html_e( 'نام مدل', 'rokhdadyar' ); ?></span>
				<span class="chk-combo">
					<input type="text" class="chk-ltr" name="<?php echo esc_attr( $n ); ?>[model]" value="<?php echo esc_attr( $p['model'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'model-name', 'rokhdadyar' ); ?>" data-f="model" spellcheck="false" autocomplete="off">
					<button type="button" class="button" data-models><?php esc_html_e( 'فهرست مدل‌ها', 'rokhdadyar' ); ?></button>
				</span>
				<datalist data-model-list></datalist>
				<small class="chk-models-out" data-models-out aria-live="polite"></small>
			</label>
			<label class="chk-field chk-field--wide">
				<span><?php esc_html_e( 'Base URL', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'خالی = نشانی رسمی همان قالب', 'rokhdadyar' ); ?></em></span>
				<input type="url" class="chk-ltr" name="<?php echo esc_attr( $n ); ?>[base_url]" value="<?php echo esc_attr( $p['base_url'] ?? '' ); ?>" placeholder="https://api.openai.com/v1" data-f="base_url" spellcheck="false">
			</label>
			<label class="chk-field chk-field--wide">
				<span><?php esc_html_e( 'کلید API', 'rokhdadyar' ); ?> <em><?php esc_html_e( 'رمزشده ذخیره می‌شود و دوباره نمایش داده نمی‌شود', 'rokhdadyar' ); ?></em></span>
				<input type="password" class="chk-ltr" name="<?php echo esc_attr( $n ); ?>[api_key]" value="" placeholder="<?php echo esc_attr( $mask ? $mask : 'sk-…' ); ?>" data-f="api_key" autocomplete="new-password" spellcheck="false">
				<?php if ( Settings::key_lost( (string) ( $p['api_key'] ?? '' ) ) ) : ?>
					<span class="chk-keylost"><?php esc_html_e( 'کلید ذخیره‌شده دیگر خوانده نمی‌شود؛ دوباره واردش کنید.', 'rokhdadyar' ); ?></span>
				<?php endif; ?>
			</label>
			<label class="chk-field">
				<span><?php esc_html_e( 'خلاقیت (temperature)', 'rokhdadyar' ); ?></span>
				<input type="number" class="chk-ltr" min="0" max="2" step="0.1" name="<?php echo esc_attr( $n ); ?>[temperature]" value="<?php echo esc_attr( $p['temperature'] ?? '0.7' ); ?>" data-f="temperature">
			</label>
			<label class="chk-field">
				<span><?php esc_html_e( 'سقف توکن خروجی', 'rokhdadyar' ); ?> <em><?php esc_html_e( '۰ = پیش‌فرض مدل', 'rokhdadyar' ); ?></em></span>
				<input type="number" class="chk-ltr" min="0" max="32000" step="1" name="<?php echo esc_attr( $n ); ?>[max_tokens]" value="<?php echo esc_attr( $p['max_tokens'] ?? 0 ); ?>" data-f="max_tokens">
			</label>
		</div>
	</article>
	<?php
};

$chk_vars = array( '{content}', '{title}', '{keyword}', '{secondary}', '{categories}', '{tags}', '{excerpt}', '{count}', '{styles}', '{examples}', '{max_chars}', '{max_words}', '{slug_rules}', '{language}', '{site_name}' );

$chk_prompt = static function ( string $key, string $label, string $hint, int $rows ) use ( $s ) {
	?>
	<div class="chk-card">
		<div class="chk-card__head">
			<div>
				<h2><?php echo esc_html( $label ); ?></h2>
				<p class="chk-hint"><?php echo esc_html( $hint ); ?></p>
			</div>
			<button type="button" class="button-link" data-reset="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'برگرداندن به پیش‌فرض', 'rokhdadyar' ); ?></button>
		</div>
		<textarea class="chk-prompt" name="chk[<?php echo esc_attr( $key ); ?>_prompt]" rows="<?php echo (int) $rows; ?>" data-prompt="<?php echo esc_attr( $key ); ?>" dir="auto"><?php echo esc_textarea( $s[ $key . '_prompt' ] ); ?></textarea>
	</div>
	<?php
};
?>
<div class="wrap chk-wrap">
	<header class="chk-head">
		<div>
			<h1><img class="chk-logo" src="<?php echo esc_url( plugins_url( 'assets/logo.svg', ROKHDADYAR_FILE ) ); ?>" height="44" alt="<?php esc_attr_e( 'رخدادیار', 'rokhdadyar' ); ?>"></h1>
			<p><?php esc_html_e( 'مرکز کنترل ساخت عنوان و چکیده با هوش مصنوعی', 'rokhdadyar' ); ?></p>
		</div>
		<span class="chk-status <?php echo $s['profiles'] ? 'is-on' : ''; ?>">
			<?php
			echo $s['profiles']
				/* translators: %d: number of models */
				? esc_html( sprintf( _n( '%d مدل آماده', '%d مدل آماده', count( $s['profiles'] ), 'rokhdadyar' ), count( $s['profiles'] ) ) )
				: esc_html__( 'هنوز مدلی تعریف نشده', 'rokhdadyar' );
			?>
		</span>
	</header>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تنظیمات ذخیره شد.', 'rokhdadyar' ); ?></p></div>
	<?php endif; ?>
	<?php $chk_lost = Settings::lost_keys(); ?>
	<?php if ( $chk_lost ) : ?>
		<div class="notice notice-error"><p>
			<?php
			/* translators: %s: list of model names */
			echo esc_html( sprintf( __( 'کلید API این بخش‌ها دیگر خوانده نمی‌شود: %s. معمولاً یعنی کلیدهای امنیتی وردپرس (salts) عوض شده‌اند، مثلاً به دست یک افزونهٔ امنیتی. کلیدها را دوباره وارد و ذخیره کنید.', 'rokhdadyar' ), implode( '، ', array_filter( $chk_lost ) ) ) );
			?>
		</p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="chk-form">
		<input type="hidden" name="action" value="rokhdadyar_save">
		<?php wp_nonce_field( 'rokhdadyar_save' ); ?>

		<nav class="chk-tabs" role="tablist">
			<button type="button" role="tab" class="is-active" data-tab="models"><?php esc_html_e( 'مدل‌ها', 'rokhdadyar' ); ?></button>
			<button type="button" role="tab" data-tab="prompts"><?php esc_html_e( 'پرامپت‌ها', 'rokhdadyar' ); ?></button>
			<button type="button" role="tab" data-tab="source"><?php esc_html_e( 'منبع محتوا', 'rokhdadyar' ); ?></button>
			<button type="button" role="tab" data-tab="image"><?php esc_html_e( 'تصویر شاخص', 'rokhdadyar' ); ?></button>
			<button type="button" role="tab" data-tab="behavior"><?php esc_html_e( 'رفتار', 'rokhdadyar' ); ?></button>
			<button type="button" role="tab" data-tab="toolbox"><?php esc_html_e( 'جعبه ابزار', 'rokhdadyar' ); ?></button>
			<button type="button" role="tab" data-tab="usage"><?php esc_html_e( 'گزارش مصرف', 'rokhdadyar' ); ?></button>
		</nav>

		<?php require __DIR__ . '/settings/models.php'; ?>

		<?php require __DIR__ . '/settings/prompts.php'; ?>

		<?php require __DIR__ . '/settings/source.php'; ?>

		<?php require __DIR__ . '/settings/behavior.php'; ?>

		<?php require __DIR__ . '/settings/image.php'; ?>

		<?php require __DIR__ . '/settings/toolbox.php'; ?>

		<?php require __DIR__ . '/settings/usage.php'; ?>

		<footer class="chk-save">
			<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'ذخیرهٔ تنظیمات', 'rokhdadyar' ); ?></button>
		</footer>
	</form>
</div>
