<?php
/**
 * Sync lawyer photo URLs from CSV only.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Find lawyer post id by external_id then slug.
 *
 * @param array<string,string> $item CSV row item.
 * @return int
 */
function hovalvakil_photo_sync_find_lawyer_id( array $item ) {
	$external_id = sanitize_text_field( (string) ( $item['external_id'] ?? '' ) );
	if ( '' !== $external_id ) {
		$q = new WP_Query(
			[
				'post_type'      => 'hvl_lawyer',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [
					[
						'key'   => 'hvl_import_external_id',
						'value' => $external_id,
					],
				],
			]
		);
		if ( $q->have_posts() ) {
			return (int) $q->posts[0];
		}
	}

	$slug = sanitize_title( (string) ( $item['slug'] ?? '' ) );
	if ( '' !== $slug ) {
		$p = get_page_by_path( $slug, OBJECT, 'hvl_lawyer' );
		if ( $p && ! empty( $p->ID ) ) {
			return (int) $p->ID;
		}
	}

	return 0;
}

/**
 * Build final photo URL from CSV row.
 *
 * @param array<string,string> $item CSV row.
 * @param string               $images_base_url Base URL for relative images path.
 * @return string
 */
function hovalvakil_photo_sync_build_url( array $item, $images_base_url ) {
	// 1) explicit URL in CSV has highest priority.
	$csv_url = trim( (string) ( $item['featured_image_url'] ?? '' ) );
	if ( '' !== $csv_url && preg_match( '#^https?://#i', $csv_url ) ) {
		return esc_url_raw( $csv_url );
	}

	$meta_url = trim( (string) ( $item['hvl_photo_url'] ?? '' ) );
	if ( '' !== $meta_url && preg_match( '#^https?://#i', $meta_url ) ) {
		return esc_url_raw( $meta_url );
	}

	// 2) bundle relative path, e.g. images/...
	$rel = trim( (string) ( $item['bundle_featured_relative'] ?? '' ) );
	if ( '' === $rel || 'null' === strtolower( $rel ) ) {
		return '';
	}
	$rel = ltrim( str_replace( '\\', '/', $rel ), '/' );
	if ( '' === $rel || false !== strpos( $rel, '..' ) ) {
		return '';
	}

	$base = trim( (string) $images_base_url );
	if ( '' === $base ) {
		$base = home_url( '/wp-content/themes/hello-elementor/dist/' );
	}
	$base = rtrim( $base, '/' ) . '/';
	$base_l = strtolower( $base );

	// Human-error-proof join:
	// - if base ends with /dist/images/ and rel starts with images/, strip leading images/ from rel.
	// - if base ends with /dist/ and rel starts with images/, keep rel as-is.
	if ( str_ends_with( $base_l, '/dist/images/' ) && 0 === strpos( strtolower( $rel ), 'images/' ) ) {
		$rel = substr( $rel, strlen( 'images/' ) );
	}

	return esc_url_raw( $base . $rel );
}

/**
 * Convert CSV row to assoc.
 *
 * @param string[] $header Header.
 * @param string[] $row Row.
 * @return array<string,string>
 */
function hovalvakil_photo_sync_assoc_row( array $header, array $row ) {
	$out = [];
	foreach ( $header as $i => $col ) {
		$key         = trim( (string) $col );
		$out[ $key ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
	}
	return $out;
}

/**
 * Run one CSV chunk and set only hvl_photo_url.
 *
 * @param string $csv_path CSV absolute path.
 * @param string $images_base_url Base URL for relative paths.
 * @param int    $offset Row offset (without header).
 * @param int    $limit Chunk size.
 * @param bool   $dry_run Dry-run mode.
 * @return array<string,mixed>
 */
function hovalvakil_photo_sync_run_chunk( $csv_path, $images_base_url, $offset = 0, $limit = 1000, $dry_run = false ) {
	$csv_path = wp_normalize_path( (string) $csv_path );
	if ( ! is_file( $csv_path ) || ! is_readable( $csv_path ) ) {
		return [ 'ok' => false, 'message' => 'CSV file not readable.' ];
	}

	$h = fopen( $csv_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $h ) {
		return [ 'ok' => false, 'message' => 'Cannot open CSV.' ];
	}

	$offset = max( 0, (int) $offset );
	$limit  = max( 1, min( 5000, (int) $limit ) );
	$header = fgetcsv( $h, 0, ',' );
	if ( ! is_array( $header ) || empty( $header ) ) {
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return [ 'ok' => false, 'message' => 'CSV header invalid.' ];
	}

	$skipped = 0;
	while ( $skipped < $offset && false !== fgetcsv( $h, 0, ',' ) ) {
		$skipped++;
	}

	$processed = 0;
	$updated   = 0;
	$not_found = 0;
	$no_url    = 0;
	$errors    = 0;
	$done      = true;

	for ( $i = 0; $i < $limit; $i++ ) {
		$row = fgetcsv( $h, 0, ',' );
		if ( false === $row ) {
			$done = true;
			break;
		}
		$done = false;
		$processed++;

		$item = hovalvakil_photo_sync_assoc_row( $header, (array) $row );
		$url  = hovalvakil_photo_sync_build_url( $item, $images_base_url );
		if ( '' === $url ) {
			$no_url++;
			continue;
		}

		$post_id = hovalvakil_photo_sync_find_lawyer_id( $item );
		if ( $post_id <= 0 ) {
			$not_found++;
			continue;
		}

		if ( ! $dry_run ) {
			$ok = update_post_meta( $post_id, 'hvl_photo_url', $url );
			if ( false === $ok ) {
				// update_post_meta false can also mean no change; check current value.
				$current = (string) get_post_meta( $post_id, 'hvl_photo_url', true );
				if ( $current !== $url ) {
					$errors++;
					continue;
				}
			}
		}
		$updated++;
	}

	fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return [
		'ok'          => true,
		'processed'   => $processed,
		'updated'     => $updated,
		'not_found'   => $not_found,
		'no_url'      => $no_url,
		'errors'      => $errors,
		'next_offset' => $offset + $processed,
		'done'        => $done || $processed < $limit,
	];
}

/**
 * AJAX endpoint for photo sync chunk.
 *
 * @return void
 */
function hovalvakil_ajax_photo_sync_chunk() {
	if ( ! check_ajax_referer( 'hvl_photo_sync_nonce', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}

	$csv_path        = isset( $_POST['csv_path'] ) ? (string) wp_unslash( $_POST['csv_path'] ) : '';
	$images_base_url = isset( $_POST['images_base_url'] ) ? (string) wp_unslash( $_POST['images_base_url'] ) : '';
	$offset          = isset( $_POST['offset'] ) ? max( 0, absint( $_POST['offset'] ) ) : 0;
	$limit           = isset( $_POST['limit'] ) ? max( 1, min( 5000, absint( $_POST['limit'] ) ) ) : 2000;
	$dry_run         = ! empty( $_POST['dry_run'] );

	$out = hovalvakil_photo_sync_run_chunk( $csv_path, $images_base_url, $offset, $limit, $dry_run );
	if ( empty( $out['ok'] ) ) {
		wp_send_json_error( [ 'message' => (string) ( $out['message'] ?? 'sync_failed' ) ], 400 );
	}

	wp_send_json_success( $out );
}
add_action( 'wp_ajax_hovalvakil_photo_sync_chunk', 'hovalvakil_ajax_photo_sync_chunk' );

/**
 * Add photo sync tools page.
 *
 * @return void
 */
function hovalvakil_register_photo_sync_tools_page() {
	add_submenu_page(
		'tools.php',
		'Hovalvakil Lawyer Photo Sync',
		'Hovalvakil Lawyer Photo Sync',
		'manage_options',
		'hovalvakil-lawyer-photo-sync',
		'hovalvakil_render_photo_sync_tools_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_photo_sync_tools_page' );

/**
 * Render photo sync tools page.
 *
 * @return void
 */
function hovalvakil_render_photo_sync_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$default_csv  = wp_normalize_path( trailingslashit( get_template_directory() ) . 'dist/lawyers.csv' );
	$default_base = home_url( '/wp-content/themes/hello-elementor/dist/' );
	?>
	<div class="wrap">
		<h1>Sync Lawyer Photos Only</h1>
		<p>این ابزار فقط <code>hvl_photo_url</code> را از CSV ست می‌کند. هیچ فایل/رسانه‌ای کپی یا ساخته نمی‌شود.</p>
		<table class="form-table" style="max-width:70rem;">
			<tbody>
			<tr>
				<th scope="row"><label for="hvl-photo-csv">CSV Path</label></th>
				<td><input id="hvl-photo-csv" type="text" value="<?php echo esc_attr( $default_csv ); ?>" style="width:100%;" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="hvl-photo-base-url">Images Base URL</label></th>
				<td>
					<input id="hvl-photo-base-url" type="text" value="<?php echo esc_attr( $default_base ); ?>" style="width:100%;" />
					<p class="description">به‌صورت خودکار نرمال می‌شود. هر دو حالت <code>.../dist/</code> و <code>.../dist/images/</code> پشتیبانی می‌شود.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hvl-photo-limit">Chunk Size</label></th>
				<td><input id="hvl-photo-limit" type="number" min="1" max="5000" value="2000" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="hvl-photo-offset">Offset</label></th>
				<td><input id="hvl-photo-offset" type="number" min="0" value="0" /></td>
			</tr>
			<tr>
				<th scope="row">Mode</th>
				<td><label><input id="hvl-photo-dry-run" type="checkbox" /> Dry-run</label></td>
			</tr>
			</tbody>
		</table>
		<p>
			<button type="button" class="button button-primary" id="hvl-photo-run">Run Photo Sync</button>
			<button type="button" class="button" id="hvl-photo-stop" disabled style="margin-inline-start:8px;">Stop</button>
		</p>
		<p id="hvl-photo-status" style="font-weight:700;" aria-live="polite"></p>
		<pre id="hvl-photo-log" style="max-width:70rem;max-height:320px;overflow:auto;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;direction:ltr;text-align:left;"></pre>
	</div>
	<script>
		(function () {
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce = <?php echo wp_json_encode( wp_create_nonce( 'hvl_photo_sync_nonce' ) ); ?>;
			const runBtn = document.getElementById('hvl-photo-run');
			const stopBtn = document.getElementById('hvl-photo-stop');
			const statusEl = document.getElementById('hvl-photo-status');
			const logEl = document.getElementById('hvl-photo-log');
			const csvEl = document.getElementById('hvl-photo-csv');
			const baseEl = document.getElementById('hvl-photo-base-url');
			const limitEl = document.getElementById('hvl-photo-limit');
			const offsetEl = document.getElementById('hvl-photo-offset');
			const dryEl = document.getElementById('hvl-photo-dry-run');
			let stopped = false;

			function logLine(msg) {
				if (!logEl) return;
				logEl.textContent += (typeof msg === 'string' ? msg : JSON.stringify(msg)) + '\n';
				logEl.scrollTop = logEl.scrollHeight;
			}

			async function chunk(payload) {
				const fd = new FormData();
				fd.append('action', 'hovalvakil_photo_sync_chunk');
				fd.append('nonce', nonce);
				Object.keys(payload).forEach((k) => fd.append(k, String(payload[k])));
				const res = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
				const js = await res.json();
				if (!js || !js.success) throw new Error((js && js.data && js.data.message) ? js.data.message : ('http_' + res.status));
				return js.data || {};
			}

			runBtn?.addEventListener('click', async () => {
				const csvPath = (csvEl?.value || '').trim();
				const baseUrl = (baseEl?.value || '').trim();
				const limit = Math.max(1, Math.min(5000, Number(limitEl?.value || 2000)));
				let offset = Math.max(0, Number(offsetEl?.value || 0));
				const dryRun = !!dryEl?.checked;

				let totalProcessed = 0;
				let totalUpdated = 0;
				let totalNotFound = 0;
				let totalNoUrl = 0;
				let totalErrors = 0;
				let step = 0;
				stopped = false;
				runBtn.disabled = true;
				stopBtn.disabled = false;
				if (logEl) logEl.textContent = '';
				if (statusEl) statusEl.textContent = 'running...';

				try {
					while (!stopped) {
						step += 1;
						const out = await chunk({
							csv_path: csvPath,
							images_base_url: baseUrl,
							offset: offset,
							limit: limit,
							dry_run: dryRun ? 1 : 0
						});

						totalProcessed += Number(out.processed || 0);
						totalUpdated += Number(out.updated || 0);
						totalNotFound += Number(out.not_found || 0);
						totalNoUrl += Number(out.no_url || 0);
						totalErrors += Number(out.errors || 0);
						offset = Number(out.next_offset || offset);
						if (offsetEl) offsetEl.value = String(offset);

						logLine({ step, processed: out.processed, updated: out.updated, not_found: out.not_found, no_url: out.no_url, errors: out.errors, next_offset: offset, done: !!out.done });
						if (statusEl) statusEl.textContent = `step ${step} | processed ${totalProcessed} | updated ${totalUpdated} | not_found ${totalNotFound} | no_url ${totalNoUrl} | errors ${totalErrors}`;
						if (out.done) break;
						await new Promise((r) => setTimeout(r, 30));
					}
					if (statusEl) statusEl.textContent = stopped ? 'stopped' : 'completed';
				} catch (e) {
					logLine('error: ' + String(e && e.message ? e.message : e));
					if (statusEl) statusEl.textContent = 'failed';
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

