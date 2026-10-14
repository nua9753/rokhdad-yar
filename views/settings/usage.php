<?php
/**
 * مرکز کنترل — تب «گزارش مصرف». داخل views/settings.php بار می‌شود و متغیرهای آن را می‌بیند.
 */

namespace Rokhdadyar;

defined( 'ABSPATH' ) || exit;
?>
		<!-- گزارش مصرف -->
		
		<section class="chk-panel" data-panel="usage">
			<?php
			$chk_base = Admin::url();
			$chk_hash = '#usage';
			require ROKHDADYAR_DIR . 'views/usage.php';
			?>
		</section>
