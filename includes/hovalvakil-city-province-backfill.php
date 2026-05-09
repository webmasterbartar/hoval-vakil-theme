<?php
/**
 * Tools -> Backfill city/province terms for existing lawyers.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register standalone backfill tools page.
 *
 * @return void
 */
function hovalvakil_register_city_province_backfill_tools_page() {
	add_submenu_page(
		'tools.php',
		'Backfill شهر/استان وکلا',
		'Backfill شهر/استان وکلا',
		'manage_options',
		'hovalvakil-city-province-backfill',
		'hovalvakil_render_city_province_backfill_tools_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_city_province_backfill_tools_page' );

/**
 * AJAX chunk runner for backfill.
 *
 * @return void
 */
function hovalvakil_ajax_city_province_backfill_chunk() {
	if ( ! check_ajax_referer( 'hvl_city_province_backfill_ajax', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'nonce_failed' ], 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}
	if ( ! function_exists( 'hovalvakil_backfill_lawyer_city_province_terms' ) ) {
		wp_send_json_error( [ 'message' => 'backfill_function_missing' ], 500 );
	}

	$limit   = isset( $_POST['limit'] ) ? max( 1, min( 5000, absint( $_POST['limit'] ) ) ) : 800;
	$offset  = isset( $_POST['offset'] ) ? max( 0, absint( $_POST['offset'] ) ) : 0;
	$dry_run = ! empty( $_POST['dry_run'] );

	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	$out = hovalvakil_backfill_lawyer_city_province_terms(
		[
			'limit'   => $limit,
			'offset'  => $offset,
			'dry_run' => $dry_run,
		]
	);

	$processed = (int) ( $out['processed'] ?? 0 );
	wp_send_json_success(
		[
			'processed'    => $processed,
			'updated'      => (int) ( $out['updated'] ?? 0 ),
			'errors'       => (int) ( $out['errors'] ?? 0 ),
			'next_offset'  => $offset + $processed,
			'done'         => $processed < $limit,
		]
	);
}
add_action( 'wp_ajax_hovalvakil_city_province_backfill_chunk', 'hovalvakil_ajax_city_province_backfill_chunk' );

/**
 * Render standalone backfill tools page.
 *
 * @return void
 */
function hovalvakil_render_city_province_backfill_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$default_limit = 800;
	?>
	<div class="wrap">
		<h1>Backfill شهر/استان وکلا</h1>
		<p style="max-width:62rem;">
			این ابزار مستقل، برای وکلای قبلی که شهر/استان ندارند، از آدرس پروفایل (لوکیشن/آدرس دفتر) شهر و استان را حدس می‌زند و روی
			تاکسونومی‌های <code>hvl_city</code> و <code>hvl_province</code> ذخیره می‌کند.
		</p>
		<table class="form-table" style="max-width:62rem;">
			<tbody>
				<tr>
					<th scope="row"><label for="hvl-bf-limit">تعداد در هر مرحله</label></th>
					<td><input id="hvl-bf-limit" type="number" min="1" max="5000" value="<?php echo esc_attr( (string) $default_limit ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="hvl-bf-offset">offset شروع</label></th>
					<td><input id="hvl-bf-offset" type="number" min="0" value="0" /></td>
				</tr>
				<tr>
					<th scope="row">گزینه</th>
					<td><label><input id="hvl-bf-dry-run" type="checkbox" /> فقط گزارش (Dry-run)</label></td>
				</tr>
			</tbody>
		</table>
		<p>
			<button type="button" class="button button-primary" id="hvl-bf-run">شروع اجرای سریع تا انتها</button>
			<button type="button" class="button" id="hvl-bf-stop" disabled style="margin-inline-start:8px;">توقف</button>
		</p>
		<p id="hvl-bf-status" style="font-weight:700;" aria-live="polite"></p>
		<pre id="hvl-bf-log" style="max-width:62rem;max-height:300px;overflow:auto;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;direction:ltr;text-align:left;"></pre>
	</div>
	<script>
		(function(){
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce = <?php echo wp_json_encode( wp_create_nonce( 'hvl_city_province_backfill_ajax' ) ); ?>;
			const runBtn = document.getElementById('hvl-bf-run');
			const stopBtn = document.getElementById('hvl-bf-stop');
			const statusEl = document.getElementById('hvl-bf-status');
			const logEl = document.getElementById('hvl-bf-log');
			const limitEl = document.getElementById('hvl-bf-limit');
			const offsetEl = document.getElementById('hvl-bf-offset');
			const dryEl = document.getElementById('hvl-bf-dry-run');
			let stopped = false;

			function logLine(obj){
				if (!logEl) return;
				logEl.textContent += (typeof obj === 'string' ? obj : JSON.stringify(obj)) + '\n';
				logEl.scrollTop = logEl.scrollHeight;
			}

			async function runChunk(offset, limit, dryRun){
				const fd = new FormData();
				fd.append('action', 'hovalvakil_city_province_backfill_chunk');
				fd.append('nonce', nonce);
				fd.append('offset', String(offset));
				fd.append('limit', String(limit));
				if (dryRun) fd.append('dry_run', '1');
				const res = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
				let js = null;
				try { js = await res.json(); } catch (e) { throw new Error('invalid_json'); }
				if (!js || !js.success) {
					throw new Error((js && js.data && js.data.message) ? js.data.message : ('http_' + res.status));
				}
				return js.data || {};
			}

			runBtn?.addEventListener('click', async () => {
				const limit = Math.max(1, Math.min(5000, Number(limitEl?.value || 800)));
				let offset = Math.max(0, Number(offsetEl?.value || 0));
				const dryRun = !!dryEl?.checked;
				let totalProcessed = 0, totalUpdated = 0, totalErrors = 0, step = 0;
				stopped = false;
				runBtn.disabled = true;
				stopBtn.disabled = false;
				if (logEl) logEl.textContent = '';
				if (statusEl) statusEl.textContent = 'در حال اجرا...';

				try {
					while (!stopped) {
						step += 1;
						const d = await runChunk(offset, limit, dryRun);
						totalProcessed += Number(d.processed || 0);
						totalUpdated += Number(d.updated || 0);
						totalErrors += Number(d.errors || 0);
						offset = Number(d.next_offset || offset);
						if (offsetEl) offsetEl.value = String(offset);
						logLine({ step, processed: d.processed, updated: d.updated, errors: d.errors, next_offset: offset, done: !!d.done });
						if (statusEl) statusEl.textContent = `مرحله ${step} | پردازش ${totalProcessed} | آپدیت ${totalUpdated} | خطا ${totalErrors}`;
						if (d.done) break;
						await new Promise((r) => setTimeout(r, 25));
					}
					if (statusEl) {
						statusEl.textContent = stopped
							? `متوقف شد | پردازش ${totalProcessed} | آپدیت ${totalUpdated} | خطا ${totalErrors}`
							: `تمام شد | پردازش ${totalProcessed} | آپدیت ${totalUpdated} | خطا ${totalErrors}`;
					}
				} catch (e) {
					logLine('error: ' + String(e && e.message ? e.message : e));
					if (statusEl) statusEl.textContent = 'خطا در اجرا. لاگ را بررسی کنید.';
				} finally {
					runBtn.disabled = false;
					stopBtn.disabled = true;
				}
			});

			stopBtn?.addEventListener('click', () => {
				stopped = true;
			});
		})();
	</script>
	<?php
}

