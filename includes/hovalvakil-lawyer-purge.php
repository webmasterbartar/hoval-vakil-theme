<?php
/**
 * Tools page to purge all lawyer-related data and media.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delete local upload file by URL when it belongs to this site.
 *
 * @param string $url Absolute URL.
 * @return bool
 */
function hovalvakil_purge_delete_upload_file_by_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return false;
	}

	$attachment_id = attachment_url_to_postid( $url );
	if ( $attachment_id > 0 ) {
		wp_delete_attachment( (int) $attachment_id, true );
		return true;
	}

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
		return false;
	}

	$base_url = trailingslashit( (string) $uploads['baseurl'] );
	$base_dir = wp_normalize_path( trailingslashit( (string) $uploads['basedir'] ) );
	if ( 0 !== strpos( $url, $base_url ) ) {
		return false;
	}

	$rel_path = ltrim( substr( $url, strlen( $base_url ) ), '/' );
	$abs_path = wp_normalize_path( $base_dir . $rel_path );
	if ( ! is_file( $abs_path ) ) {
		return false;
	}

	return (bool) @unlink( $abs_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * Delete media attached/referenced by a lawyer post.
 *
 * @param int $post_id Lawyer post ID.
 * @return int Number of media deletions attempted/succeeded.
 */
function hovalvakil_purge_delete_lawyer_media( $post_id ) {
	$post_id       = (int) $post_id;
	$deleted_media = 0;

	$thumb_id = (int) get_post_thumbnail_id( $post_id );
	if ( $thumb_id > 0 ) {
		wp_delete_attachment( $thumb_id, true );
		$deleted_media++;
	}

	$attachments = get_children(
		[
			'post_parent'    => $post_id,
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]
	);
	if ( is_array( $attachments ) ) {
		foreach ( $attachments as $att_id ) {
			wp_delete_attachment( (int) $att_id, true );
			$deleted_media++;
		}
	}

	$url_meta_keys = [ 'hvl_photo_url', 'hvl_license_file_url', 'hvl_office_map_image' ];
	foreach ( $url_meta_keys as $meta_key ) {
		$url = (string) get_post_meta( $post_id, $meta_key, true );
		if ( '' !== $url && hovalvakil_purge_delete_upload_file_by_url( $url ) ) {
			$deleted_media++;
		}
	}

	return $deleted_media;
}

/**
 * Purge one chunk of lawyer-related content.
 *
 * @param int $limit Max records per chunk.
 * @return array<string,int|bool>
 */
function hovalvakil_purge_lawyer_data_chunk( $limit = 200 ) {
	$limit = max( 1, min( 1000, (int) $limit ) );

	$deleted_lawyers = 0;
	$deleted_reviews = 0;
	$deleted_res     = 0;
	$deleted_media   = 0;

	$lawyers = get_posts(
		[
			'post_type'      => 'hvl_lawyer',
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		]
	);

	foreach ( (array) $lawyers as $lawyer_id ) {
		$deleted_media += hovalvakil_purge_delete_lawyer_media( (int) $lawyer_id );
		if ( wp_delete_post( (int) $lawyer_id, true ) ) {
			$deleted_lawyers++;
		}
	}

	$remaining_lawyers = count(
		get_posts(
			[
				'post_type'      => 'hvl_lawyer',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		)
	);

	if ( 0 === $remaining_lawyers ) {
		$reviews = get_posts(
			[
				'post_type'      => 'hvl_review',
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);
		foreach ( (array) $reviews as $rid ) {
			if ( wp_delete_post( (int) $rid, true ) ) {
				$deleted_reviews++;
			}
		}

		$reservations = get_posts(
			[
				'post_type'      => 'hvl_reservation',
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);
		foreach ( (array) $reservations as $rsid ) {
			if ( wp_delete_post( (int) $rsid, true ) ) {
				$deleted_res++;
			}
		}
	}

	$done = ( 0 === $remaining_lawyers )
		&& 0 === count(
			get_posts(
				[
					'post_type'      => 'hvl_review',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				]
			)
		)
		&& 0 === count(
			get_posts(
				[
					'post_type'      => 'hvl_reservation',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				]
			)
		);

	if ( $done ) {
		$taxonomies = [ 'hvl_city', 'hvl_province', 'hvl_specialty' ];
		foreach ( $taxonomies as $tax ) {
			$terms = get_terms(
				[
					'taxonomy'   => $tax,
					'hide_empty' => false,
					'fields'     => 'ids',
				]
			);
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term_id ) {
					wp_delete_term( (int) $term_id, $tax );
				}
			}
		}
	}

	return [
		'deleted_lawyers' => $deleted_lawyers,
		'deleted_reviews' => $deleted_reviews,
		'deleted_res'     => $deleted_res,
		'deleted_media'   => $deleted_media,
		'done'            => $done,
	];
}

/**
 * AJAX chunk endpoint for purge.
 *
 * @return void
 */
function hovalvakil_ajax_purge_lawyer_data_chunk() {
	if ( ! check_ajax_referer( 'hvl_purge_lawyers_nonce', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}
	$confirm = isset( $_POST['confirm_phrase'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['confirm_phrase'] ) ) : '';
	if ( 'DELETE LAWYERS NOW' !== $confirm ) {
		wp_send_json_error( [ 'message' => 'invalid_confirm_phrase' ], 400 );
	}

	$limit = isset( $_POST['limit'] ) ? max( 1, min( 1000, absint( $_POST['limit'] ) ) ) : 200;
	$out   = hovalvakil_purge_lawyer_data_chunk( $limit );
	wp_send_json_success( $out );
}
add_action( 'wp_ajax_hovalvakil_purge_lawyer_data_chunk', 'hovalvakil_ajax_purge_lawyer_data_chunk' );

/**
 * Register tools page for destructive purge.
 *
 * @return void
 */
function hovalvakil_register_lawyer_purge_tools_page() {
	add_submenu_page(
		'tools.php',
		'Hovalvakil Purge Lawyers',
		'Hovalvakil Purge Lawyers',
		'manage_options',
		'hovalvakil-purge-lawyers',
		'hovalvakil_render_lawyer_purge_tools_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_lawyer_purge_tools_page' );

/**
 * Render purge tools page.
 *
 * @return void
 */
function hovalvakil_render_lawyer_purge_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>حذف کامل داده‌های وکلا</h1>
		<p style="max-width:70rem;color:#b32d2e;font-weight:700;">
			هشدار: این عملیات غیرقابل بازگشت است و تمام وکلا + رسانه‌های مرتبط + رزروها + نظرات + شهر/استان/تخصص را حذف می‌کند.
		</p>
		<table class="form-table" style="max-width:62rem;">
			<tbody>
				<tr>
					<th scope="row"><label for="hvl-purge-limit">تعداد در هر مرحله</label></th>
					<td><input id="hvl-purge-limit" type="number" min="1" max="1000" value="200" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="hvl-purge-confirm">عبارت تایید</label></th>
					<td>
						<input id="hvl-purge-confirm" type="text" placeholder="DELETE LAWYERS NOW" style="width:340px;" />
						<p class="description">برای فعال شدن دکمه، دقیقا عبارت بالا را وارد کنید.</p>
					</td>
				</tr>
			</tbody>
		</table>
		<p>
			<button type="button" class="button button-primary" id="hvl-purge-run" disabled>حذف کامل داده‌های وکلا</button>
			<button type="button" class="button" id="hvl-purge-stop" disabled style="margin-inline-start:8px;">توقف</button>
		</p>
		<p id="hvl-purge-status" style="font-weight:700;" aria-live="polite"></p>
		<pre id="hvl-purge-log" style="max-width:70rem;max-height:320px;overflow:auto;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;direction:ltr;text-align:left;"></pre>
	</div>
	<script>
		(function () {
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce = <?php echo wp_json_encode( wp_create_nonce( 'hvl_purge_lawyers_nonce' ) ); ?>;
			const runBtn = document.getElementById('hvl-purge-run');
			const stopBtn = document.getElementById('hvl-purge-stop');
			const statusEl = document.getElementById('hvl-purge-status');
			const logEl = document.getElementById('hvl-purge-log');
			const limitEl = document.getElementById('hvl-purge-limit');
			const confirmEl = document.getElementById('hvl-purge-confirm');
			let stopped = false;

			function refreshButton() {
				const ok = (confirmEl?.value || '').trim() === 'DELETE LAWYERS NOW';
				if (runBtn && !runBtn.disabled) return;
				if (runBtn) runBtn.disabled = !ok;
			}

			function logLine(obj) {
				if (!logEl) return;
				logEl.textContent += (typeof obj === 'string' ? obj : JSON.stringify(obj)) + '\n';
				logEl.scrollTop = logEl.scrollHeight;
			}

			async function runChunk(limit, phrase) {
				const fd = new FormData();
				fd.append('action', 'hovalvakil_purge_lawyer_data_chunk');
				fd.append('nonce', nonce);
				fd.append('limit', String(limit));
				fd.append('confirm_phrase', phrase);
				const res = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
				const js = await res.json();
				if (!js || !js.success) throw new Error((js && js.data && js.data.message) ? js.data.message : ('http_' + res.status));
				return js.data || {};
			}

			confirmEl?.addEventListener('input', refreshButton);
			refreshButton();

			runBtn?.addEventListener('click', async () => {
				const limit = Math.max(1, Math.min(1000, Number(limitEl?.value || 200)));
				const phrase = (confirmEl?.value || '').trim();
				if (phrase !== 'DELETE LAWYERS NOW') return;

				let totalLawyers = 0;
				let totalReviews = 0;
				let totalRes = 0;
				let totalMedia = 0;
				let step = 0;
				stopped = false;
				runBtn.disabled = true;
				stopBtn.disabled = false;
				if (logEl) logEl.textContent = '';
				if (statusEl) statusEl.textContent = 'در حال حذف...';

				try {
					while (!stopped) {
						step += 1;
						const out = await runChunk(limit, phrase);
						totalLawyers += Number(out.deleted_lawyers || 0);
						totalReviews += Number(out.deleted_reviews || 0);
						totalRes += Number(out.deleted_res || 0);
						totalMedia += Number(out.deleted_media || 0);
						logLine({ step, deleted_lawyers: out.deleted_lawyers, deleted_reviews: out.deleted_reviews, deleted_res: out.deleted_res, deleted_media: out.deleted_media, done: !!out.done });
						if (statusEl) statusEl.textContent = `مرحله ${step} | وکلا ${totalLawyers} | نظرات ${totalReviews} | رزروها ${totalRes} | رسانه ${totalMedia}`;
						if (out.done) break;
						await new Promise((r) => setTimeout(r, 25));
					}
					if (statusEl) statusEl.textContent = stopped ? 'متوقف شد' : 'حذف کامل انجام شد';
				} catch (e) {
					logLine('error: ' + String(e && e.message ? e.message : e));
					if (statusEl) statusEl.textContent = 'خطا در حذف';
				} finally {
					stopBtn.disabled = true;
					refreshButton();
				}
			});

			stopBtn?.addEventListener('click', () => {
				stopped = true;
			});
		})();
	</script>
	<?php
}

