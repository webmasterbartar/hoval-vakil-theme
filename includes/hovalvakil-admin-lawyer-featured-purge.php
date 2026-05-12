<?php
/**
 * Admin: حذف دسته‌جمعی تصویر شاخص وکلا و پیوست مرتبط از رسانه.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return void
 */
function hovalvakil_register_lawyer_featured_purge_submenu() {
	add_submenu_page(
		'edit.php?post_type=hvl_lawyer',
		__( 'حذف تصاویر شاخص وکلا', 'hello-elementor' ),
		__( 'حذف تصاویر شاخص', 'hello-elementor' ),
		'manage_options',
		'hovalvakil-lawyer-remove-featured-media',
		'hovalvakil_render_lawyer_featured_purge_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_lawyer_featured_purge_submenu' );

/**
 * اگر هیچ پستی این attachment را به‌عنوان شاخص نداشت، پیوست و فایل‌ها حذف می‌شوند.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool True if attachment was deleted.
 */
function hovalvakil_maybe_delete_unused_featured_attachment( $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) {
		return false;
	}

	$q = new WP_Query(
		[
			'post_type'              => 'any',
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'   => '_thumbnail_id',
					'value' => (string) $attachment_id,
				],
			],
		]
	);

	if ( $q->have_posts() ) {
		return false;
	}

	if ( function_exists( 'hovalvakil_delete_image_attachment_fully' ) ) {
		return hovalvakil_delete_image_attachment_fully( $attachment_id );
	}
	return (bool) wp_delete_attachment( $attachment_id, true );
}

/**
 * @return void
 */
function hovalvakil_ajax_delete_lawyer_featured_chunk() {
	if ( ! check_ajax_referer( 'hovalvakil_lawyer_featured_purge', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}

	$offset = isset( $_POST['offset'] ) ? max( 0, absint( $_POST['offset'] ) ) : 0;
	$limit  = isset( $_POST['limit'] ) ? max( 1, min( 150, absint( $_POST['limit'] ) ) ) : 80;

	$q = new WP_Query(
		[
			'post_type'              => 'hvl_lawyer',
			'post_status'            => 'any',
			'posts_per_page'         => $limit,
			'offset'                 => $offset,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]
	);

	$cleared_posts     = 0;
	$attachments_gone  = 0;
	$attachments_kept  = 0;

	foreach ( (array) $q->posts as $post_id ) {
		$post_id = (int) $post_id;
		$aid     = (int) get_post_thumbnail_id( $post_id );
		if ( $aid <= 0 ) {
			continue;
		}
		$cleared_posts++;
		delete_post_thumbnail( $post_id );
		if ( hovalvakil_maybe_delete_unused_featured_attachment( $aid ) ) {
			$attachments_gone++;
		} else {
			$attachments_kept++;
		}
	}

	$total       = (int) $q->found_posts;
	$processed   = count( (array) $q->posts );
	$next_offset = $offset + $processed;
	$done        = $processed <= 0 || $next_offset >= $total;

	wp_send_json_success(
		[
			'cleared_posts'    => $cleared_posts,
			'attachments_gone' => $attachments_gone,
			'attachments_kept' => $attachments_kept,
			'processed'        => $processed,
			'next_offset'      => $next_offset,
			'total'            => $total,
			'done'             => $done,
		]
	);
}
add_action( 'wp_ajax_hovalvakil_delete_lawyer_featured_chunk', 'hovalvakil_ajax_delete_lawyer_featured_chunk' );

/**
 * @return void
 */
function hovalvakil_render_lawyer_featured_purge_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازهٔ دسترسی ندارید.', 'hello-elementor' ) );
	}

	$ajax_url = admin_url( 'admin-ajax.php' );
	$nonce    = wp_create_nonce( 'hovalvakil_lawyer_featured_purge' );
	?>
	<div class="wrap" dir="rtl" style="direction:rtl;text-align:right;">
		<h1><?php echo esc_html__( 'حذف تصاویر شاخص همهٔ وکلا', 'hello-elementor' ); ?></h1>
		<div class="notice notice-warning">
			<p>
				<?php echo esc_html__( 'با اجرای این ابزار، برای هر پست وکیل، تصویر شاخص برداشته می‌شود. اگر همان فایل رسانه جایی دیگر به‌عنوان شاخص استفاده نشود، پیوست از پایگاه داده و فایل‌های آن (همهٔ سایزها) از هاست حذف می‌شود. عمل برگشت‌پذیر نیست.', 'hello-elementor' ); ?>
			</p>
		</div>
		<p><?php echo esc_html__( 'برای سایت‌های بزرگ، حذف به‌صورت مرحله‌ای انجام می‌شود تا زمان اجرای PHP تمام نشود.', 'hello-elementor' ); ?></p>
		<table class="form-table" style="max-width:42rem;">
			<tbody>
				<tr>
					<th scope="row"><label for="hvl-feat-limit"><?php echo esc_html__( 'تعداد پست در هر مرحله', 'hello-elementor' ); ?></label></th>
					<td><input id="hvl-feat-limit" type="number" min="1" max="150" value="80" /></td>
				</tr>
			</tbody>
		</table>
		<p>
			<button type="button" class="button button-primary" id="hvl-feat-run"><?php echo esc_html__( 'شروع حذف', 'hello-elementor' ); ?></button>
			<button type="button" class="button" id="hvl-feat-stop" disabled style="margin-inline-start:8px;"><?php echo esc_html__( 'توقف', 'hello-elementor' ); ?></button>
		</p>
		<p id="hvl-feat-status" style="font-weight:700;" aria-live="polite"></p>
		<pre id="hvl-feat-log" style="max-width:62rem;max-height:280px;overflow:auto;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;direction:ltr;text-align:left;font-size:12px;"></pre>
	</div>
	<script>
		(function () {
			const ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
			const nonce = <?php echo wp_json_encode( $nonce ); ?>;
			const runBtn = document.getElementById('hvl-feat-run');
			const stopBtn = document.getElementById('hvl-feat-stop');
			const statusEl = document.getElementById('hvl-feat-status');
			const logEl = document.getElementById('hvl-feat-log');
			const limitEl = document.getElementById('hvl-feat-limit');
			let stopped = false;

			function logLine(obj) {
				if (!logEl) return;
				logEl.textContent += (typeof obj === 'string' ? obj : JSON.stringify(obj)) + '\n';
				logEl.scrollTop = logEl.scrollHeight;
			}

			async function runChunk(offset, limit) {
				const fd = new FormData();
				fd.append('action', 'hovalvakil_delete_lawyer_featured_chunk');
				fd.append('nonce', nonce);
				fd.append('offset', String(offset));
				fd.append('limit', String(limit));
				const res = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
				const js = await res.json();
				if (!js || !js.success) throw new Error((js && js.data && js.data.message) ? js.data.message : ('http_' + res.status));
				return js.data || {};
			}

			runBtn?.addEventListener('click', async () => {
				let offset = 0;
				const limit = Math.max(1, Math.min(150, Number(limitEl?.value || 80)));
				let sumCleared = 0, sumGone = 0, sumKept = 0, sumProcessed = 0;
				let step = 0;
				stopped = false;
				runBtn.disabled = true;
				stopBtn.disabled = false;
				if (logEl) logEl.textContent = '';
				if (statusEl) statusEl.textContent = '<?php echo esc_js( __( 'در حال اجرا…', 'hello-elementor' ) ); ?>';
				try {
					while (!stopped) {
						step += 1;
						const d = await runChunk(offset, limit);
						sumCleared += Number(d.cleared_posts || 0);
						sumGone += Number(d.attachments_gone || 0);
						sumKept += Number(d.attachments_kept || 0);
						sumProcessed += Number(d.processed || 0);
						offset = Number(d.next_offset || offset);
						logLine({ step, ...d });
						if (statusEl) {
							statusEl.textContent = '<?php echo esc_js( __( 'مرحله', 'hello-elementor' ) ); ?> ' + step
								+ ' | <?php echo esc_js( __( 'پست بررسی‌شده', 'hello-elementor' ) ); ?> ' + sumProcessed
								+ ' | <?php echo esc_js( __( 'شاخص برداشته', 'hello-elementor' ) ); ?> ' + sumCleared
								+ ' | <?php echo esc_js( __( 'پیوست حذف‌شده', 'hello-elementor' ) ); ?> ' + sumGone
								+ ' | <?php echo esc_js( __( 'پیوست باقی‌مانده (اشتراکی)', 'hello-elementor' ) ); ?> ' + sumKept;
						}
						if (d.done) break;
						await new Promise((r) => setTimeout(r, 20));
					}
					if (statusEl) {
						statusEl.textContent = stopped
							? '<?php echo esc_js( __( 'متوقف شد', 'hello-elementor' ) ); ?>'
							: '<?php echo esc_js( __( 'پایان: همهٔ پست‌های وکیل پیمایش شد.', 'hello-elementor' ) ); ?>';
					}
				} catch (e) {
					logLine('error: ' + String(e && e.message ? e.message : e));
					if (statusEl) statusEl.textContent = '<?php echo esc_js( __( 'خطا در اجرا', 'hello-elementor' ) ); ?>';
				} finally {
					runBtn.disabled = false;
					stopBtn.disabled = true;
				}
			});

			stopBtn?.addEventListener('click', () => { stopped = true; });
		})();
	</script>
	<?php
}
