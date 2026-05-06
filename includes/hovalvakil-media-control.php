<?php
/**
 * Media controls: disable image sub-sizes and clean existing generated files.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable all intermediate image sizes generation.
 *
 * @param array<string, array<string,int|string>> $sizes Requested sizes.
 * @return array<string, array<string,int|string>>
 */
function hovalvakil_disable_intermediate_image_sizes( $sizes ) {
	return [];
}
add_filter( 'intermediate_image_sizes_advanced', 'hovalvakil_disable_intermediate_image_sizes', 999 );

/**
 * Disable fallback intermediate sizes path.
 *
 * @param string[] $sizes Requested fallback sizes.
 * @return string[]
 */
function hovalvakil_disable_fallback_intermediate_sizes( $sizes ) {
	return [];
}
add_filter( 'fallback_intermediate_image_sizes', 'hovalvakil_disable_fallback_intermediate_sizes', 999 );

/**
 * Disable auto-scaling large images.
 *
 * @param int|false $threshold Threshold.
 * @return false
 */
function hovalvakil_disable_big_image_threshold( $threshold ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return false;
}
add_filter( 'big_image_size_threshold', 'hovalvakil_disable_big_image_threshold', 999 );

/**
 * Register media cleanup tools page.
 *
 * @return void
 */
function hovalvakil_register_media_cleanup_tools_page() {
	add_submenu_page(
		'tools.php',
		'Hovalvakil Media Cleanup',
		'Hovalvakil Media Cleanup',
		'manage_options',
		'hovalvakil-media-cleanup',
		'hovalvakil_render_media_cleanup_tools_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_media_cleanup_tools_page' );

/**
 * Delete registered sub-size files for one attachment.
 *
 * @param int $attachment_id Attachment ID.
 * @return array{deleted:int,failed:int}
 */
function hovalvakil_cleanup_attachment_subsizes( $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	$meta          = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
		return [ 'deleted' => 0, 'failed' => 0 ];
	}

	$rel_attached = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( '' === $rel_attached ) {
		return [ 'deleted' => 0, 'failed' => 0 ];
	}

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
		return [ 'deleted' => 0, 'failed' => 0 ];
	}

	$base_dir      = wp_normalize_path( (string) $uploads['basedir'] );
	$attached_abs  = wp_normalize_path( $base_dir . '/' . ltrim( str_replace( '\\', '/', $rel_attached ), '/' ) );
	$attached_dir  = wp_normalize_path( dirname( $attached_abs ) );
	$attached_name = basename( $attached_abs );

	$deleted = 0;
	$failed  = 0;
	foreach ( $meta['sizes'] as $size_meta ) {
		if ( ! is_array( $size_meta ) || empty( $size_meta['file'] ) ) {
			continue;
		}
		$size_file = basename( (string) $size_meta['file'] );
		if ( '' === $size_file || $size_file === $attached_name ) {
			continue;
		}

		$path = wp_normalize_path( $attached_dir . '/' . $size_file );
		if ( ! is_file( $path ) ) {
			continue;
		}

		if ( @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$deleted++;
		} else {
			$failed++;
		}
	}

	$meta['sizes'] = [];
	wp_update_attachment_metadata( $attachment_id, $meta );

	return [
		'deleted' => $deleted,
		'failed'  => $failed,
	];
}

/**
 * AJAX chunk cleanup endpoint.
 *
 * @return void
 */
function hovalvakil_ajax_media_cleanup_chunk() {
	if ( ! check_ajax_referer( 'hvl_media_cleanup_nonce', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}

	$offset = isset( $_POST['offset'] ) ? max( 0, absint( $_POST['offset'] ) ) : 0;
	$limit  = isset( $_POST['limit'] ) ? max( 1, min( 500, absint( $_POST['limit'] ) ) ) : 100;

	$q = new WP_Query(
		[
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => false,
		]
	);

	$processed = 0;
	$deleted   = 0;
	$failed    = 0;
	foreach ( (array) $q->posts as $attachment_id ) {
		$res      = hovalvakil_cleanup_attachment_subsizes( (int) $attachment_id );
		$deleted += (int) $res['deleted'];
		$failed  += (int) $res['failed'];
		$processed++;
	}

	$total       = (int) $q->found_posts;
	$next_offset = $offset + $processed;
	$done        = $processed <= 0 || $next_offset >= $total;

	wp_send_json_success(
		[
			'processed'    => $processed,
			'deleted'      => $deleted,
			'failed'       => $failed,
			'next_offset'  => $next_offset,
			'total'        => $total,
			'done'         => $done,
		]
	);
}
add_action( 'wp_ajax_hovalvakil_media_cleanup_chunk', 'hovalvakil_ajax_media_cleanup_chunk' );

/**
 * Render tools page.
 *
 * @return void
 */
function hovalvakil_render_media_cleanup_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>Hovalvakil Media Cleanup</h1>
		<p>
			تولید سایزهای اضافی تصاویر غیرفعال شده است. این ابزار فقط فایل‌های sub-size ثبت‌شده در metadata را حذف می‌کند و فایل اصلی را نگه می‌دارد.
		</p>
		<table class="form-table" style="max-width:62rem;">
			<tbody>
				<tr>
					<th scope="row"><label for="hvl-media-limit">تعداد در هر مرحله</label></th>
					<td><input id="hvl-media-limit" type="number" min="1" max="500" value="100" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="hvl-media-offset">offset شروع</label></th>
					<td><input id="hvl-media-offset" type="number" min="0" value="0" /></td>
				</tr>
			</tbody>
		</table>
		<p>
			<button type="button" class="button button-primary" id="hvl-media-run">شروع پاکسازی</button>
			<button type="button" class="button" id="hvl-media-stop" disabled style="margin-inline-start:8px;">توقف</button>
		</p>
		<p id="hvl-media-status" style="font-weight:700;" aria-live="polite"></p>
		<pre id="hvl-media-log" style="max-width:62rem;max-height:300px;overflow:auto;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;direction:ltr;text-align:left;"></pre>
	</div>
	<script>
		(function () {
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce = <?php echo wp_json_encode( wp_create_nonce( 'hvl_media_cleanup_nonce' ) ); ?>;
			const runBtn = document.getElementById('hvl-media-run');
			const stopBtn = document.getElementById('hvl-media-stop');
			const statusEl = document.getElementById('hvl-media-status');
			const logEl = document.getElementById('hvl-media-log');
			const limitEl = document.getElementById('hvl-media-limit');
			const offsetEl = document.getElementById('hvl-media-offset');
			let stopped = false;

			function logLine(obj) {
				if (!logEl) return;
				logEl.textContent += (typeof obj === 'string' ? obj : JSON.stringify(obj)) + '\n';
				logEl.scrollTop = logEl.scrollHeight;
			}

			async function runChunk(offset, limit) {
				const fd = new FormData();
				fd.append('action', 'hovalvakil_media_cleanup_chunk');
				fd.append('nonce', nonce);
				fd.append('offset', String(offset));
				fd.append('limit', String(limit));
				const res = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
				const js = await res.json();
				if (!js || !js.success) throw new Error((js && js.data && js.data.message) ? js.data.message : ('http_' + res.status));
				return js.data || {};
			}

			runBtn?.addEventListener('click', async () => {
				let offset = Math.max(0, Number(offsetEl?.value || 0));
				const limit = Math.max(1, Math.min(500, Number(limitEl?.value || 100)));
				let totalProcessed = 0;
				let totalDeleted = 0;
				let totalFailed = 0;
				let step = 0;
				stopped = false;
				runBtn.disabled = true;
				stopBtn.disabled = false;
				if (logEl) logEl.textContent = '';
				if (statusEl) statusEl.textContent = 'در حال اجرا...';
				try {
					while (!stopped) {
						step += 1;
						const d = await runChunk(offset, limit);
						totalProcessed += Number(d.processed || 0);
						totalDeleted += Number(d.deleted || 0);
						totalFailed += Number(d.failed || 0);
						offset = Number(d.next_offset || offset);
						if (offsetEl) offsetEl.value = String(offset);
						logLine({ step, processed: d.processed, deleted: d.deleted, failed: d.failed, next_offset: offset, total: d.total, done: !!d.done });
						if (statusEl) statusEl.textContent = `مرحله ${step} | پردازش ${totalProcessed} | حذف ${totalDeleted} | خطا ${totalFailed}`;
						if (d.done) break;
						await new Promise((r) => setTimeout(r, 25));
					}
					if (statusEl) statusEl.textContent = stopped ? 'متوقف شد' : 'پاکسازی کامل شد';
				} catch (e) {
					logLine('error: ' + String(e && e.message ? e.message : e));
					if (statusEl) statusEl.textContent = 'خطا در اجرا';
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

