<?php
/**
 * Unified CSV importer for hvl_lawyer posts.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve city/province/specialty term id by slug or name.
 *
 * @param string $value Raw value.
 * @param string $taxonomy Taxonomy slug.
 * @return int
 */
function hovalvakil_importer_resolve_term_id( $value, $taxonomy ) {
	$value = trim( (string) $value );
	if ( '' === $value || 'null' === strtolower( $value ) ) {
		return 0;
	}

	$term = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}

	$term = get_term_by( 'name', $value, $taxonomy );
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}

	$inserted = wp_insert_term( $value, $taxonomy );
	if ( is_wp_error( $inserted ) || empty( $inserted['term_id'] ) ) {
		return 0;
	}

	return (int) $inserted['term_id'];
}

/**
 * Convert row values from CSV into an associative item by header.
 *
 * @param string[] $header Header columns.
 * @param string[] $row Row values.
 * @return array<string,string>
 */
function hovalvakil_importer_assoc_row( array $header, array $row ) {
	$out = [];
	foreach ( $header as $i => $col ) {
		$key       = trim( (string) $col );
		$out[ $key ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
	}
	return $out;
}

/**
 * Find lawyer post by external_id then slug.
 *
 * @param array<string,string> $item Parsed row.
 * @return int
 */
function hovalvakil_importer_find_lawyer_post_id( array $item ) {
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
		$existing = get_page_by_path( $slug, OBJECT, 'hvl_lawyer' );
		if ( $existing && ! empty( $existing->ID ) ) {
			return (int) $existing->ID;
		}
	}

	return 0;
}

/**
 * Normalize value before storing in meta.
 *
 * @param string $key Meta key.
 * @param string $value Raw value.
 * @return string
 */
function hovalvakil_importer_normalize_meta_value( $key, $value ) {
	$value = trim( (string) $value );
	if ( '' === $value || 'null' === strtolower( $value ) ) {
		return '';
	}
	if ( in_array( $key, [ 'hvl_license_issued', 'hvl_license_expires' ], true ) && function_exists( 'hovalvakil_lawyer_normalize_license_expires' ) ) {
		return (string) hovalvakil_lawyer_normalize_license_expires( $value );
	}
	if ( in_array( $key, [ 'hvl_license_file_url', 'hvl_photo_url', 'hvl_office_map_image' ], true ) ) {
		return esc_url_raw( $value );
	}
	if ( in_array( $key, [ 'hvl_records', 'hvl_services', 'hvl_office_address' ], true ) ) {
		return sanitize_textarea_field( $value );
	}
	return sanitize_text_field( $value );
}

/**
 * Import local image as featured image.
 *
 * @param int    $post_id Post ID.
 * @param string $absolute_path Absolute file path.
 * @param string $preferred_stem Preferred filename stem.
 * @return int|WP_Error
 */
function hovalvakil_importer_set_featured_from_local_path( $post_id, $absolute_path, $preferred_stem = '' ) {
	$absolute_path = wp_normalize_path( (string) $absolute_path );
	if ( '' === $absolute_path || ! is_file( $absolute_path ) || ! is_readable( $absolute_path ) ) {
		return new WP_Error( 'image_missing', 'Local image not found or unreadable.' );
	}

	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) || empty( $upload['path'] ) || empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
		return new WP_Error( 'upload_dir_error', 'Cannot access uploads directory.' );
	}

	wp_mkdir_p( $upload['path'] );
	$ext       = strtolower( (string) pathinfo( $absolute_path, PATHINFO_EXTENSION ) );
	$file_stem = sanitize_file_name( preg_replace( '/\.[^.]+$/', '', (string) $preferred_stem ) );
	if ( '' === $file_stem ) {
		$file_stem = sanitize_file_name( (string) pathinfo( $absolute_path, PATHINFO_FILENAME ) );
	}
	if ( '' === $file_stem ) {
		$file_stem = 'lawyer-photo';
	}
	$filename    = wp_unique_filename( $upload['path'], $file_stem . ( $ext ? '.' . $ext : '.webp' ) );
	$target_file = trailingslashit( $upload['path'] ) . $filename;

	if ( ! copy( $absolute_path, $target_file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		return new WP_Error( 'copy_failed', 'Failed to copy image into uploads.' );
	}

	$mime = wp_check_filetype( $filename );
	$att_id = wp_insert_attachment(
		[
			'post_mime_type' => (string) ( $mime['type'] ?? 'image/webp' ),
			'post_title'     => $file_stem,
			'post_status'    => 'inherit',
		],
		$target_file,
		$post_id
	);
	if ( is_wp_error( $att_id ) || ! $att_id ) {
		return new WP_Error( 'attachment_failed', 'Failed to create attachment.' );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	$meta = wp_generate_attachment_metadata( (int) $att_id, $target_file );
	if ( is_array( $meta ) ) {
		wp_update_attachment_metadata( (int) $att_id, $meta );
	}
	set_post_thumbnail( $post_id, (int) $att_id );
	return (int) $att_id;
}

/**
 * Import one parsed CSV row.
 *
 * @param array<string,string> $item Parsed row.
 * @param string               $images_root Absolute base path for bundle_featured_relative.
 * @param bool                 $dry_run Whether to skip writes.
 * @return array{type:string,id:int,message:string}
 */
function hovalvakil_importer_process_row( array $item, $images_root, $images_base_url = '', $dry_run = false ) {
	$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
	if ( '' === $title ) {
		return [ 'type' => 'error', 'id' => 0, 'message' => 'title is empty' ];
	}

	$post_id = hovalvakil_importer_find_lawyer_post_id( $item );
	$slug    = sanitize_title( (string) ( $item['slug'] ?? '' ) );
	$status  = sanitize_key( (string) ( $item['status'] ?? 'publish' ) );
	if ( '' === $status ) {
		$status = 'publish';
	}

	if ( $dry_run ) {
		return [ 'type' => $post_id > 0 ? 'updated' : 'created', 'id' => $post_id, 'message' => 'dry_run' ];
	}

	$postarr = [
		'post_type'    => 'hvl_lawyer',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_status'  => $status,
		'post_content' => wp_kses_post( (string) ( $item['content'] ?? '' ) ),
		'post_excerpt' => sanitize_textarea_field( (string) ( $item['excerpt'] ?? '' ) ),
	];
	if ( $post_id > 0 ) {
		$postarr['ID'] = $post_id;
	}

	$saved_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $saved_id ) || ! $saved_id ) {
		return [ 'type' => 'error', 'id' => 0, 'message' => 'wp_insert_post failed' ];
	}
	$saved_id = (int) $saved_id;

	$external_id = sanitize_text_field( (string) ( $item['external_id'] ?? '' ) );
	if ( '' !== $external_id ) {
		update_post_meta( $saved_id, 'hvl_import_external_id', $external_id );
	}

	$meta_keys = function_exists( 'hovalvakil_lawyer_meta_key_list' ) ? hovalvakil_lawyer_meta_key_list() : [];
	foreach ( $meta_keys as $meta_key ) {
		$raw = isset( $item[ $meta_key ] ) ? (string) $item[ $meta_key ] : '';
		$val = hovalvakil_importer_normalize_meta_value( $meta_key, $raw );
		if ( '' === $val ) {
			delete_post_meta( $saved_id, $meta_key );
		} else {
			update_post_meta( $saved_id, $meta_key, $val );
		}
	}

	$city_id = hovalvakil_importer_resolve_term_id( (string) ( $item['city'] ?? '' ), 'hvl_city' );
	if ( $city_id > 0 ) {
		wp_set_object_terms( $saved_id, [ $city_id ], 'hvl_city', false );
	}
	$province_id = hovalvakil_importer_resolve_term_id( (string) ( $item['province'] ?? '' ), 'hvl_province' );
	if ( $province_id > 0 ) {
		wp_set_object_terms( $saved_id, [ $province_id ], 'hvl_province', false );
	}

	$specialty_ids = [];
	$specialties   = trim( (string) ( $item['specialties'] ?? '' ) );
	if ( '' !== $specialties ) {
		$chunks = preg_split( '/[|;,،]+/u', $specialties );
		if ( is_array( $chunks ) ) {
			foreach ( $chunks as $chunk ) {
				$tid = hovalvakil_importer_resolve_term_id( (string) $chunk, 'hvl_specialty' );
				if ( $tid > 0 ) {
					$specialty_ids[] = $tid;
				}
			}
		}
	}
	if ( ! empty( $specialty_ids ) ) {
		wp_set_object_terms( $saved_id, array_values( array_unique( $specialty_ids ) ), 'hvl_specialty', false );
	}

	$bundle_rel = trim( (string) ( $item['bundle_featured_relative'] ?? '' ) );
	if ( '' !== $bundle_rel ) {
		$bundle_rel = ltrim( str_replace( '\\', '/', $bundle_rel ), '/' );
		if ( false === strpos( $bundle_rel, '..' ) ) {
			// Prefer direct URL reference (no copy into uploads, no attachment).
			$base = trim( (string) $images_base_url );
			if ( '' === $base ) {
				$base = home_url( '/images/' );
			}
			$base = rtrim( $base, '/' ) . '/';
			$url  = $base . $bundle_rel;
			update_post_meta( $saved_id, 'hvl_photo_url', esc_url_raw( $url ) );
		}
	}

	return [ 'type' => $post_id > 0 ? 'updated' : 'created', 'id' => $saved_id, 'message' => 'ok' ];
}

/**
 * Run CSV import in a chunk.
 *
 * @param string $csv_path Absolute CSV path.
 * @param string $images_root Absolute base directory for images.
 * @param int    $offset Data row offset (without header).
 * @param int    $limit Number of rows.
 * @param bool   $dry_run Dry run.
 * @return array<string,mixed>
 */
function hovalvakil_importer_run_chunk( $csv_path, $images_root, $images_base_url = '', $offset = 0, $limit = 1000, $dry_run = false ) {
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
		return [ 'ok' => false, 'message' => 'CSV header is invalid.' ];
	}

	$skipped = 0;
	while ( $skipped < $offset && false !== ( $row = fgetcsv( $h, 0, ',' ) ) ) {
		$skipped++;
	}

	$created = 0;
	$updated = 0;
	$errors  = 0;
	$done    = true;
	$count   = 0;
	for ( $i = 0; $i < $limit; $i++ ) {
		$row = fgetcsv( $h, 0, ',' );
		if ( false === $row ) {
			$done = true;
			break;
		}
		$count++;
		$item = hovalvakil_importer_assoc_row( $header, (array) $row );
		$res  = hovalvakil_importer_process_row( $item, $images_root, $images_base_url, $dry_run );
		if ( 'created' === $res['type'] ) {
			$created++;
		} elseif ( 'updated' === $res['type'] ) {
			$updated++;
		} else {
			$errors++;
		}
		$done = false;
	}

	fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return [
		'ok'          => true,
		'created'     => $created,
		'updated'     => $updated,
		'errors'      => $errors,
		'processed'   => $count,
		'next_offset' => $offset + $count,
		'done'        => $done || $count < $limit,
	];
}

/**
 * Register importer tools page.
 *
 * @return void
 */
function hovalvakil_importer_register_tools_page() {
	add_submenu_page(
		'tools.php',
		'Hovalvakil Importer',
		'Hovalvakil Importer',
		'manage_options',
		'hovalvakil-importer',
		'hovalvakil_importer_render_tools_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_importer_register_tools_page' );

/**
 * AJAX chunk endpoint for importer.
 *
 * @return void
 */
function hovalvakil_importer_ajax_run_chunk() {
	if ( ! check_ajax_referer( 'hvl_importer_nonce', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}

	$csv_path    = isset( $_POST['csv_path'] ) ? (string) wp_unslash( $_POST['csv_path'] ) : '';
	$images_root = isset( $_POST['images_root'] ) ? (string) wp_unslash( $_POST['images_root'] ) : '';
	$images_base_url = isset( $_POST['images_base_url'] ) ? (string) wp_unslash( $_POST['images_base_url'] ) : '';
	$offset      = isset( $_POST['offset'] ) ? max( 0, absint( $_POST['offset'] ) ) : 0;
	$limit       = isset( $_POST['limit'] ) ? max( 1, min( 5000, absint( $_POST['limit'] ) ) ) : 1000;
	$dry_run     = ! empty( $_POST['dry_run'] );

	$out = hovalvakil_importer_run_chunk( $csv_path, $images_root, $images_base_url, $offset, $limit, $dry_run );
	if ( empty( $out['ok'] ) ) {
		wp_send_json_error( [ 'message' => (string) ( $out['message'] ?? 'import_failed' ) ], 400 );
	}

	wp_send_json_success( $out );
}
add_action( 'wp_ajax_hovalvakil_importer_chunk', 'hovalvakil_importer_ajax_run_chunk' );

/**
 * Render importer page in WP admin.
 *
 * @return void
 */
function hovalvakil_importer_render_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$default_csv   = wp_normalize_path( trailingslashit( get_template_directory() ) . 'dist/lawyers.csv' );
	$default_root  = wp_normalize_path( trailingslashit( get_template_directory() ) . 'dist' );
	$default_base  = home_url( '/images/' );
	?>
	<div class="wrap">
		<h1>Hovalvakil Unified Importer</h1>
		<p>این ایمپورتر فقط فایل‌های محلی را می‌خواند و هیچ درخواست خارجی به CDN/Google Fonts ارسال نمی‌کند.</p>
		<table class="form-table" style="max-width:70rem;">
			<tbody>
			<tr>
				<th scope="row"><label for="hvl-import-csv">CSV Path</label></th>
				<td><input id="hvl-import-csv" type="text" value="<?php echo esc_attr( $default_csv ); ?>" style="width:100%;" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="hvl-import-root">Images Root</label></th>
				<td><input id="hvl-import-root" type="text" value="<?php echo esc_attr( $default_root ); ?>" style="width:100%;" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="hvl-import-base-url">Images Base URL</label></th>
				<td>
					<input id="hvl-import-base-url" type="text" value="<?php echo esc_attr( $default_base ); ?>" style="width:100%;" />
					<p class="description">مثال: <code><?php echo esc_html( home_url( '/images/' ) ); ?></code> (همان پوشه‌ای که فایل‌های <code>images/...</code> داخلش است)</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hvl-import-limit">Chunk Size</label></th>
				<td><input id="hvl-import-limit" type="number" min="1" max="5000" value="1000" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="hvl-import-offset">Offset</label></th>
				<td><input id="hvl-import-offset" type="number" min="0" value="0" /></td>
			</tr>
			<tr>
				<th scope="row">Mode</th>
				<td><label><input id="hvl-import-dry-run" type="checkbox" /> Dry-run</label></td>
			</tr>
			</tbody>
		</table>
		<p>
			<button type="button" class="button button-primary" id="hvl-import-run">Run Import</button>
			<button type="button" class="button" id="hvl-import-stop" disabled style="margin-inline-start:8px;">Stop</button>
		</p>
		<p id="hvl-import-status" style="font-weight:700;" aria-live="polite"></p>
		<pre id="hvl-import-log" style="max-width:70rem;max-height:320px;overflow:auto;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;direction:ltr;text-align:left;"></pre>
	</div>
	<script>
		(function () {
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce = <?php echo wp_json_encode( wp_create_nonce( 'hvl_importer_nonce' ) ); ?>;
			const runBtn = document.getElementById('hvl-import-run');
			const stopBtn = document.getElementById('hvl-import-stop');
			const statusEl = document.getElementById('hvl-import-status');
			const logEl = document.getElementById('hvl-import-log');
			const csvEl = document.getElementById('hvl-import-csv');
			const rootEl = document.getElementById('hvl-import-root');
			const baseUrlEl = document.getElementById('hvl-import-base-url');
			const limitEl = document.getElementById('hvl-import-limit');
			const offsetEl = document.getElementById('hvl-import-offset');
			const dryRunEl = document.getElementById('hvl-import-dry-run');
			let stopped = false;

			function logLine(msg) {
				if (!logEl) return;
				logEl.textContent += (typeof msg === 'string' ? msg : JSON.stringify(msg)) + '\n';
				logEl.scrollTop = logEl.scrollHeight;
			}

			async function chunk(payload) {
				const fd = new FormData();
				fd.append('action', 'hovalvakil_importer_chunk');
				fd.append('nonce', nonce);
				Object.keys(payload).forEach((k) => fd.append(k, String(payload[k])));
				const res = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
				const js = await res.json();
				if (!js || !js.success) throw new Error((js && js.data && js.data.message) ? js.data.message : ('http_' + res.status));
				return js.data || {};
			}

			runBtn?.addEventListener('click', async () => {
				const csvPath = (csvEl?.value || '').trim();
				const imagesRoot = (rootEl?.value || '').trim();
				const imagesBaseUrl = (baseUrlEl?.value || '').trim();
				const limit = Math.max(1, Math.min(5000, Number(limitEl?.value || 1000)));
				let offset = Math.max(0, Number(offsetEl?.value || 0));
				const dryRun = !!dryRunEl?.checked;

				let totalProcessed = 0;
				let totalCreated = 0;
				let totalUpdated = 0;
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
							images_root: imagesRoot,
							images_base_url: imagesBaseUrl,
							offset: offset,
							limit: limit,
							dry_run: dryRun ? 1 : 0
						});
						totalProcessed += Number(out.processed || 0);
						totalCreated += Number(out.created || 0);
						totalUpdated += Number(out.updated || 0);
						totalErrors += Number(out.errors || 0);
						offset = Number(out.next_offset || offset);
						if (offsetEl) offsetEl.value = String(offset);
						logLine({ step, processed: out.processed, created: out.created, updated: out.updated, errors: out.errors, next_offset: offset, done: !!out.done });
						if (statusEl) statusEl.textContent = `step ${step} | processed ${totalProcessed} | created ${totalCreated} | updated ${totalUpdated} | errors ${totalErrors}`;
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

