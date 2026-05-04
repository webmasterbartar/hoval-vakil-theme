<?php
/**
 * Tools → پاک‌سازی دادهٔ ایمپورت (بدون WP-CLI).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return void
 */
function hovalvakil_register_reset_import_tools_page() {
	add_management_page(
		'پاک‌سازی دادهٔ ایمپورت',
		'پاک‌سازی ایمپورت',
		'manage_options',
		'hovalvakil-reset-import',
		'hovalvakil_render_reset_import_tools_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_reset_import_tools_page' );

/**
 * @return void
 */
function hovalvakil_render_reset_import_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$done = false;
	$result = null;
	if ( isset( $_POST['hovalvakil_reset_import'] ) && check_admin_referer( 'hovalvakil_reset_import_action', 'hovalvakil_reset_import_nonce' ) ) {
		$phrase = isset( $_POST['hovalvakil_confirm_phrase'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hovalvakil_confirm_phrase'] ) ) : '';
		if ( 'DELETE' !== $phrase ) {
			add_settings_error( 'hovalvakil_reset', 'bad_phrase', 'برای تأیید، دقیقاً DELETE را در کادر بنویسید.', 'error' );
		} elseif ( ! function_exists( 'hovalvakil_reset_import_data_run' ) ) {
			add_settings_error( 'hovalvakil_reset', 'missing', 'ماژول پاک‌سازی بارگذاری نشد.', 'error' );
		} else {
			$opts = [
				'with_centers'      => ! empty( $_POST['hovalvakil_with_centers'] ),
				'with_services_cpt' => ! empty( $_POST['hovalvakil_with_services_cpt'] ),
				'with_taxonomies'   => ! empty( $_POST['hovalvakil_with_taxonomies'] ),
			];
			$result  = hovalvakil_reset_import_data_run( $opts );
			$done    = true;
			add_settings_error( 'hovalvakil_reset', 'ok', 'پاک‌سازی با موفقیت انجام شد.', 'success' );
		}
	}

	if ( isset( $_POST['hovalvakil_reset_lawyers_only'] ) && check_admin_referer( 'hovalvakil_reset_lawyers_only_action', 'hovalvakil_reset_lawyers_only_nonce' ) ) {
		$phrase = isset( $_POST['hovalvakil_confirm_phrase_lawyers'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hovalvakil_confirm_phrase_lawyers'] ) ) : '';
		if ( 'DELETE' !== $phrase ) {
			add_settings_error( 'hovalvakil_reset', 'bad_phrase2', 'برای حذف فقط وکلا، دقیقاً DELETE را بنویسید.', 'error' );
		} elseif ( function_exists( 'hovalvakil_reset_lawyers_only_run' ) ) {
			$n = hovalvakil_reset_lawyers_only_run();
			add_settings_error( 'hovalvakil_reset', 'ok2', sprintf( 'حذف شد: %d وکیل.', (int) $n ), 'success' );
		}
	}

	if ( isset( $_POST['hovalvakil_delete_wc_products'] ) && check_admin_referer( 'hovalvakil_delete_wc_products_action', 'hovalvakil_delete_wc_products_nonce' ) ) {
		$phrase = isset( $_POST['hovalvakil_confirm_phrase_wc'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['hovalvakil_confirm_phrase_wc'] ) ) : '';
		if ( 'DELETE' !== $phrase ) {
			add_settings_error( 'hovalvakil_reset', 'bad_phrase3', 'برای حذف محصولات، دقیقاً DELETE را بنویسید.', 'error' );
		} elseif ( function_exists( 'hovalvakil_reset_delete_wc_products' ) ) {
			if ( ! post_type_exists( 'product' ) ) {
				add_settings_error( 'hovalvakil_reset', 'no_wc', 'نوع product (ووکامرس) وجود ندارد.', 'warning' );
			} else {
				$n = hovalvakil_reset_delete_wc_products();
				add_settings_error( 'hovalvakil_reset', 'ok3', sprintf( 'محصولات حذف شد: %d رکورد.', (int) $n ), 'success' );
			}
		}
	}

	settings_errors( 'hovalvakil_reset' );
	$rest_index = esc_url( rest_url( 'hovalvakil/v1' ) );
	?>
	<div class="wrap">
		<h1>پاک‌سازی دادهٔ ایمپورت</h1>
		<p>این ابزار برای زمانی است که <strong>WP-CLI</strong> ندارید. فقط مدیران (<code>manage_options</code>) به این صفحه دسترسی دارند.</p>

		<hr />

		<h2>۱) پاک‌سازی کامل قبل از ایمپورت مجدد</h2>
		<p>حذف می‌کند: <strong>نظرات وکیل</strong>، <strong>رزروها</strong>، <strong>همهٔ وکلا</strong> و تصویر شاخص هر وکیل.</p>
		<form method="post" action="">
			<?php wp_nonce_field( 'hovalvakil_reset_import_action', 'hovalvakil_reset_import_nonce' ); ?>
			<fieldset style="margin:1rem 0;">
				<label><input type="checkbox" name="hovalvakil_with_centers" value="1" /> حذف همهٔ <strong>مراکز حقوقی</strong> (<code>hvl_center</code>) و تصویرشان</label><br />
				<label><input type="checkbox" name="hovalvakil_with_services_cpt" value="1" /> حذف همهٔ <strong>تخصص/خدمت CPT</strong> (<code>hvl_service</code>)</label><br />
				<label><input type="checkbox" name="hovalvakil_with_taxonomies" value="1" /> حذف همهٔ <strong>ترم‌های</strong> شهر / استان / تخصص (تاکسونومی)</label>
			</fieldset>
			<p>
				<label>برای تأیید، عبارت <code>DELETE</code> را بنویسید:<br />
					<input type="text" name="hovalvakil_confirm_phrase" class="regular-text" autocomplete="off" required />
				</label>
			</p>
			<?php submit_button( 'اجرای پاک‌سازی کامل', 'delete', 'hovalvakil_reset_import', false ); ?>
		</form>

		<?php if ( $done && is_array( $result ) ) : ?>
			<div class="notice notice-info"><p><strong>نتایج:</strong></p>
				<ul style="list-style:disc;padding-right:1.5rem;">
					<?php foreach ( $result as $k => $v ) : ?>
						<li><code><?php echo esc_html( (string) $k ); ?></code>: <?php echo (int) $v; ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<hr />

		<h2>۲) فقط حذف وکلا</h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'hovalvakil_reset_lawyers_only_action', 'hovalvakil_reset_lawyers_only_nonce' ); ?>
			<p><label>تأیید با <code>DELETE</code>: <input type="text" name="hovalvakil_confirm_phrase_lawyers" class="regular-text" autocomplete="off" required /></label></p>
			<?php submit_button( 'حذف فقط وکلا', 'delete', 'hovalvakil_reset_lawyers_only', false ); ?>
		</form>

		<hr />

		<h2>۳) حذف همهٔ محصولات ووکامرس</h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'hovalvakil_delete_wc_products_action', 'hovalvakil_delete_wc_products_nonce' ); ?>
			<p><label>تأیید با <code>DELETE</code>: <input type="text" name="hovalvakil_confirm_phrase_wc" class="regular-text" autocomplete="off" required /></label></p>
			<?php submit_button( 'حذف محصولات ووکامرس', 'delete', 'hovalvakil_delete_wc_products', false ); ?>
		</form>

		<hr />

		<h2>۴) از طریق REST (مثلاً Postman یا اسکریپت)</h2>
		<p>با همان کاربری که در وردپرس لاگین است (کوکی) یا Application Password:</p>
		<ul>
			<li><code>POST <?php echo esc_html( $rest_index ); ?>/reset-import-data</code></li>
			<li>بدنهٔ JSON: <code>{"confirm":"DELETE","with_centers":false,"with_services_cpt":false,"with_taxonomies":false}</code></li>
			<li>فقط وکلا: <code>POST .../reset-lawyers</code> با <code>{"confirm":"DELETE"}</code></li>
			<li>محصولات: <code>POST .../delete-wc-products</code> با <code>{"confirm":"DELETE"}</code></li>
		</ul>
	</div>
	<?php
}
