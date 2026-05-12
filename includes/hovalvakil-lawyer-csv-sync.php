<?php
/**
 * همگام‌سازی پست‌های hvl_lawyer با dist/lawyers.csv (مسیر داخل تم).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * مسیر فایل CSV در تم.
 *
 * @return string
 */
function hovalvakil_lawyers_csv_sync_file_path() {
	return trailingslashit( get_template_directory() ) . 'dist/lawyers.csv';
}

/**
 * نرمال‌سازی سلول CSV: trim و NULL متنی.
 *
 * @param mixed $v Value.
 * @return string
 */
function hovalvakil_lawyers_csv_sync_cell( $v ) {
	if ( null === $v ) {
		return '';
	}
	$s = trim( (string) $v );
	if ( '' === $s ) {
		return '';
	}
	if ( strcasecmp( $s, 'NULL' ) === 0 ) {
		return '';
	}
	return $s;
}

/**
 * @param string   $taxonomy Taxonomy.
 * @param string   $name     Term name.
 * @param int|null $term_id  Out: term id.
 * @return bool|string True, false, or WP_Error message string.
 */
function hovalvakil_lawyers_csv_sync_resolve_term_id( $taxonomy, $name, &$term_id ) {
	$term_id = null;
	$name    = hovalvakil_lawyers_csv_sync_cell( $name );
	if ( '' === $name ) {
		return true;
	}

	$t = get_term_by( 'name', $name, $taxonomy );
	if ( $t && ! is_wp_error( $t ) ) {
		$term_id = (int) $t->term_id;
		return true;
	}

	$slug_try = sanitize_title( $name );
	if ( '' !== $slug_try ) {
		$t = get_term_by( 'slug', $slug_try, $taxonomy );
		if ( $t && ! is_wp_error( $t ) ) {
			$term_id = (int) $t->term_id;
			return true;
		}
	}

	$ins = wp_insert_term( $name, $taxonomy );
	if ( is_wp_error( $ins ) ) {
		if ( $ins->get_error_code() === 'term_exists' ) {
			$maybe = (int) $ins->get_error_data();
			if ( $maybe > 0 ) {
				$term_id = $maybe;
				return true;
			}
		}
		return $ins->get_error_message();
	}

	$term_id = (int) $ins['term_id'];
	return true;
}

/**
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy.
 * @param int|null $term_id Term ID or null to clear.
 * @return void
 */
function hovalvakil_lawyers_csv_sync_set_single_term( $post_id, $taxonomy, $term_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}
	if ( null === $term_id || $term_id <= 0 ) {
		wp_set_object_terms( $post_id, [], $taxonomy );
		return;
	}
	wp_set_object_terms( $post_id, [ (int) $term_id ], $taxonomy );
}

/**
 * به‌روزرسانی یا حذف متا در صورت خالی بودن مقدار نهایی.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param string $value   Value (may be empty).
 * @return void
 */
function hovalvakil_lawyers_csv_sync_update_meta( $post_id, $key, $value ) {
	$value = (string) $value;
	if ( '' === $value ) {
		delete_post_meta( $post_id, $key );
		return;
	}
	update_post_meta( $post_id, $key, $value );
}

/**
 * پیدا کردن پست وکیل: اول external_id در متا، سپس slug پست.
 *
 * @param array<string, string> $assoc Row.
 * @return int
 */
function hovalvakil_lawyers_csv_sync_find_post_id( array $assoc ) {
	$ext = hovalvakil_lawyers_csv_sync_cell( $assoc['external_id'] ?? '' );
	if ( '' === $ext ) {
		$ext = hovalvakil_lawyers_csv_sync_cell( $assoc['hvl_import_external_id'] ?? '' );
	}
	if ( '' !== $ext ) {
		$ids = get_posts(
			[
				'post_type'              => 'hvl_lawyer',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'suppress_filters'       => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'meta_query'             => [
					[
						'key'     => 'hvl_import_external_id',
						'value'   => $ext,
						'compare' => '=',
					],
				],
			]
		);
		if ( ! empty( $ids ) ) {
			return (int) $ids[0];
		}
	}

	$slug = hovalvakil_lawyers_csv_sync_cell( $assoc['slug'] ?? '' );
	if ( '' !== $slug ) {
		$ids = get_posts(
			[
				'post_type'              => 'hvl_lawyer',
				'name'                   => $slug,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'suppress_filters'       => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			]
		);
		if ( ! empty( $ids ) ) {
			return (int) $ids[0];
		}
	}

	return 0;
}

/**
 * اعمال یک ردیف CSV روی پست.
 *
 * @param int                   $post_id Post ID.
 * @param array<string, string> $assoc   Row associative.
 * @return string|null Error message or null.
 */
function hovalvakil_lawyers_csv_sync_apply_row( $post_id, array $assoc ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return 'bad_post_id';
	}

	$title  = hovalvakil_lawyers_csv_sync_cell( $assoc['title'] ?? '' );
	$status = hovalvakil_lawyers_csv_sync_cell( $assoc['status'] ?? 'publish' );
	if ( '' === $status || ! in_array( $status, [ 'publish', 'draft', 'pending', 'private' ], true ) ) {
		$status = 'publish';
	}
	$slug = hovalvakil_lawyers_csv_sync_cell( $assoc['slug'] ?? '' );

	$postarr = [
		'ID'          => $post_id,
		'post_status' => $status,
	];
	if ( '' !== $title ) {
		$postarr['post_title'] = $title;
	}
	if ( '' !== $slug ) {
		$postarr['post_name'] = $slug;
	}

	$upd = wp_update_post( wp_slash( $postarr ), true );
	if ( is_wp_error( $upd ) ) {
		return $upd->get_error_message();
	}

	$norm_date = function_exists( 'hovalvakil_lawyer_normalize_license_expires' )
		? 'hovalvakil_lawyer_normalize_license_expires'
		: static function ( $raw ) {
			return trim( (string) $raw );
		};

	$ext_id = hovalvakil_lawyers_csv_sync_cell( $assoc['external_id'] ?? '' );
	if ( '' === $ext_id ) {
		$ext_id = hovalvakil_lawyers_csv_sync_cell( $assoc['hvl_import_external_id'] ?? '' );
	}
	hovalvakil_lawyers_csv_sync_update_meta( $post_id, 'hvl_import_external_id', $ext_id );

	$meta_map = [
		'hvl_mobile'                 => 'hvl_mobile',
		'hvl_office_mobile'          => 'hvl_office_mobile',
		'hvl_license_no'             => 'hvl_license_no',
		'hvl_license_issued'         => 'hvl_license_issued',
		'hvl_license_expires'        => 'hvl_license_expires',
		'hvl_license_issued_jalali'  => 'hvl_license_issued_jalali',
		'hvl_license_expires_jalali' => 'hvl_license_expires_jalali',
		'hvl_lawyer_grade'           => 'hvl_lawyer_grade',
		'hvl_office_phone'           => 'hvl_office_phone',
		'hvl_office_address'         => 'hvl_office_address',
		'hvl_photo_url'              => 'hvl_photo_url',
	];

	foreach ( $meta_map as $meta_key => $csv_col ) {
		$raw = hovalvakil_lawyers_csv_sync_cell( $assoc[ $csv_col ] ?? '' );
		if ( 'hvl_license_issued' === $meta_key || 'hvl_license_expires' === $meta_key ) {
			$raw = '' === $raw ? '' : (string) call_user_func( $norm_date, $raw );
		}
		hovalvakil_lawyers_csv_sync_update_meta( $post_id, $meta_key, $raw );
	}

	$city_name     = hovalvakil_lawyers_csv_sync_cell( $assoc['city'] ?? '' );
	$province_name = hovalvakil_lawyers_csv_sync_cell( $assoc['province'] ?? '' );

	$city_id = null;
	$res_c   = hovalvakil_lawyers_csv_sync_resolve_term_id( 'hvl_city', $city_name, $city_id );
	if ( true !== $res_c ) {
		return 'city: ' . (string) $res_c;
	}

	$prov_id = null;
	$res_p   = hovalvakil_lawyers_csv_sync_resolve_term_id( 'hvl_province', $province_name, $prov_id );
	if ( true !== $res_p ) {
		return 'province: ' . (string) $res_p;
	}

	hovalvakil_lawyers_csv_sync_set_single_term( $post_id, 'hvl_city', $city_id );
	hovalvakil_lawyers_csv_sync_set_single_term( $post_id, 'hvl_province', $prov_id );

	return null;
}

/**
 * @return void
 */
function hovalvakil_register_lawyer_csv_sync_menu() {
	add_submenu_page(
		'hovalvakil-lawyer-settings',
		__( 'همگام‌سازی CSV', 'hello-elementor' ),
		__( 'همگام‌سازی CSV', 'hello-elementor' ),
		'manage_options',
		'hovalvakil-lawyer-csv-sync',
		'hovalvakil_render_lawyer_csv_sync_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_lawyer_csv_sync_menu', 15 );

/**
 * AJAX: یک دسته از ردیف‌های CSV را پردازش می‌کند (موقعیت بایت ذخیره می‌شود).
 *
 * @return void
 */
function hovalvakil_ajax_lawyers_csv_sync_chunk() {
	if ( ! check_ajax_referer( 'hovalvakil_lawyers_csv_sync', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}

	$path = hovalvakil_lawyers_csv_sync_file_path();
	if ( ! is_readable( $path ) ) {
		wp_send_json_error(
			[
				'message' => 'csv_not_readable',
				'path'    => $path,
			],
			400
		);
	}

	$limit = isset( $_POST['limit'] ) ? max( 1, min( 1000, absint( $_POST['limit'] ) ) ) : 1000;
	$reset = ! empty( $_POST['reset'] );

	if ( $reset ) {
		delete_option( 'hvl_lawyer_csv_sync_pos' );
		delete_option( 'hvl_lawyer_csv_sync_sig' );
		delete_option( 'hvl_lawyer_csv_sync_headers' );
		wp_send_json_success(
			[
				'reset'           => true,
				'rows_in_chunk'   => 0,
				'updated'         => 0,
				'skipped'         => 0,
				'errors'          => [],
				'next_pos'        => 0,
				'done'            => false,
				'file_sig'        => '',
			]
		);
		return;
	}

	$sig = (string) filesize( $path ) . '|' . (string) filemtime( $path );
	$old = (string) get_option( 'hvl_lawyer_csv_sync_sig', '' );
	if ( $old !== $sig ) {
		update_option( 'hvl_lawyer_csv_sync_sig', $sig, false );
		delete_option( 'hvl_lawyer_csv_sync_pos' );
		delete_option( 'hvl_lawyer_csv_sync_headers' );
	}

	$pos = (int) get_option( 'hvl_lawyer_csv_sync_pos', 0 );

	$fh = fopen( $path, 'rb' );
	if ( false === $fh ) {
		wp_send_json_error( [ 'message' => 'fopen_failed' ], 500 );
	}

	$headers_json = get_option( 'hvl_lawyer_csv_sync_headers', '' );
	$headers      = is_string( $headers_json ) && '' !== $headers_json ? json_decode( $headers_json, true ) : null;

	if ( 0 === $pos ) {
		$bom = fread( $fh, 3 );
		if ( $bom !== "\xEF\xBB\xBF" ) {
			rewind( $fh );
		}
		$header_row = fgetcsv( $fh );
		if ( ! is_array( $header_row ) || empty( $header_row ) ) {
			fclose( $fh );
			wp_send_json_error( [ 'message' => 'empty_csv' ], 400 );
		}
		$headers = array_map(
			static function ( $h ) {
				return is_string( $h ) ? trim( $h ) : '';
			},
			$header_row
		);
		update_option( 'hvl_lawyer_csv_sync_headers', wp_json_encode( $headers ), false );
	} else {
		if ( ! is_array( $headers ) ) {
			fclose( $fh );
			wp_send_json_error( [ 'message' => 'missing_headers_reset' ], 400 );
		}
		if ( 0 !== fseek( $fh, $pos ) ) {
			fclose( $fh );
			wp_send_json_error( [ 'message' => 'fseek_failed' ], 500 );
		}
	}

	add_filter( 'hovalvakil_skip_lawyer_cache_bump_on_save', '__return_true', 999 );
	wp_defer_term_counting( true );

	$rows_read = 0;
	$updated   = 0;
	$skipped   = 0;
	$errors    = [];

	for ( $n = 0; $n < $limit; $n++ ) {
		$row = fgetcsv( $fh );
		if ( false === $row ) {
			break;
		}
		$rows_read++;

		$all_blank = true;
		foreach ( $row as $cell ) {
			if ( '' !== hovalvakil_lawyers_csv_sync_cell( $cell ) ) {
				$all_blank = false;
				break;
			}
		}
		if ( $all_blank ) {
			continue;
		}

		$assoc = [];
		foreach ( $headers as $idx => $col_name ) {
			if ( '' === $col_name ) {
				continue;
			}
			$assoc[ $col_name ] = isset( $row[ $idx ] ) ? $row[ $idx ] : '';
		}

		$post_id = hovalvakil_lawyers_csv_sync_find_post_id( $assoc );
		if ( $post_id <= 0 ) {
			$skipped++;
			continue;
		}

		$err = hovalvakil_lawyers_csv_sync_apply_row( $post_id, $assoc );
		if ( null !== $err ) {
			$errors[] = [
				'post_id' => $post_id,
				'ext'     => hovalvakil_lawyers_csv_sync_cell( $assoc['external_id'] ?? '' ),
				'error'   => $err,
			];
		} else {
			$updated++;
		}
	}

	$at_eof  = feof( $fh );
	$new_pos = (int) ftell( $fh );
	fclose( $fh );

	wp_defer_term_counting( false );
	remove_filter( 'hovalvakil_skip_lawyer_cache_bump_on_save', '__return_true', 999 );

	if ( function_exists( 'hovalvakil_lawyer_bump_cache_version' ) ) {
		hovalvakil_lawyer_bump_cache_version();
	}

	if ( $at_eof ) {
		delete_option( 'hvl_lawyer_csv_sync_pos' );
		delete_option( 'hvl_lawyer_csv_sync_headers' );
		$done = true;
	} else {
		update_option( 'hvl_lawyer_csv_sync_pos', $new_pos, false );
		$done = false;
	}

	if ( 0 === $rows_read && ! $at_eof ) {
		$done = true;
	}

	wp_send_json_success(
		[
			'rows_in_chunk' => $rows_read,
			'updated'       => $updated,
			'skipped'       => $skipped,
			'errors'        => $errors,
			'next_pos'      => $at_eof ? 0 : $new_pos,
			'done'          => $done,
			'file_sig'      => $sig,
		]
	);
}
add_action( 'wp_ajax_hovalvakil_lawyers_csv_sync_chunk', 'hovalvakil_ajax_lawyers_csv_sync_chunk' );

/**
 * @return void
 */
function hovalvakil_render_lawyer_csv_sync_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازهٔ دسترسی ندارید.', 'hello-elementor' ) );
	}

	$path   = hovalvakil_lawyers_csv_sync_file_path();
	$exists = is_readable( $path );
	$nonce  = wp_create_nonce( 'hovalvakil_lawyers_csv_sync' );
	$pos    = (int) get_option( 'hvl_lawyer_csv_sync_pos', 0 );
	?>
	<div class="wrap" dir="rtl" style="direction:rtl;text-align:right;">
		<h1><?php echo esc_html__( 'همگام‌سازی وکلا از lawyers.csv', 'hello-elementor' ); ?></h1>
		<p>
			<?php echo esc_html__( 'مسیر فایل روی سرور:', 'hello-elementor' ); ?>
			<code dir="ltr" style="display:inline-block;max-width:100%;word-break:break-all;"><?php echo esc_html( $path ); ?></code>
		</p>
		<?php if ( ! $exists ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html__( 'فایل CSV خوانا نیست. آن را در پوشهٔ dist تم قرار دهید.', 'hello-elementor' ); ?></p></div>
		<?php else : ?>
			<p><?php echo esc_html__( 'فیلدهای به‌روزشده از CSV: نام، وضعیت، اسلاگ، شناسهٔ خارجی، موبایل، شماره پروانه، تاریخ صدور/انقضا (میلادی در صورت معتبر بودن)، تاریخ شمسی، پایهٔ وکالت، تلفن و آدرس دفتر، آدرس عکس؛ همچنین شهر و استان (ترم یا ساخت ترم جدید). تطبیق پست: اول hvl_import_external_id با ستون external_id، سپس post_name با ستون slug.', 'hello-elementor' ); ?></p>
			<p>
				<?php echo esc_html__( 'موقعیت فعلی در فایل (بایت):', 'hello-elementor' ); ?>
				<strong id="hvl-csv-pos-label"><?php echo esc_html( (string) $pos ); ?></strong>
			</p>
			<table class="form-table" style="max-width:36rem;">
				<tr>
					<th><label for="hvl-csv-limit"><?php echo esc_html__( 'ردیف در هر درخواست', 'hello-elementor' ); ?></label></th>
					<td><input type="number" id="hvl-csv-limit" min="1" max="1000" value="1000" /></td>
				</tr>
			</table>
			<p>
				<button type="button" class="button button-primary" id="hvl-csv-run"><?php echo esc_html__( 'شروع / ادامهٔ همگام‌سازی', 'hello-elementor' ); ?></button>
				<button type="button" class="button" id="hvl-csv-reset"><?php echo esc_html__( 'شروع مجدد از ابتدای فایل', 'hello-elementor' ); ?></button>
				<button type="button" class="button" id="hvl-csv-stop" disabled><?php echo esc_html__( 'توقف', 'hello-elementor' ); ?></button>
			</p>
			<p id="hvl-csv-status" style="font-weight:600;" aria-live="polite"></p>
			<pre id="hvl-csv-log" style="max-width:900px;max-height:320px;overflow:auto;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;direction:ltr;text-align:left;font-size:12px;"></pre>
		<?php endif; ?>
	</div>
	<?php if ( $exists ) : ?>
	<script>
	(function () {
		const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const nonce = <?php echo wp_json_encode( $nonce ); ?>;
		const runBtn = document.getElementById('hvl-csv-run');
		const resetBtn = document.getElementById('hvl-csv-reset');
		const stopBtn = document.getElementById('hvl-csv-stop');
		const statusEl = document.getElementById('hvl-csv-status');
		const logEl = document.getElementById('hvl-csv-log');
		const limitEl = document.getElementById('hvl-csv-limit');
		const posLabel = document.getElementById('hvl-csv-pos-label');
		let stopped = false;
		let totalUp = 0, totalSkip = 0, step = 0;

		function log(o) {
			if (!logEl) return;
			logEl.textContent += JSON.stringify(o) + '\n';
			logEl.scrollTop = logEl.scrollHeight;
		}

		async function chunk(reset) {
			const fd = new FormData();
			fd.append('action', 'hovalvakil_lawyers_csv_sync_chunk');
			fd.append('nonce', nonce);
			fd.append('limit', String(Math.max(1, Math.min(1000, Number(limitEl?.value || 1000)))));
			if (reset) fd.append('reset', '1');
			const res = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
			const js = await res.json();
			if (!js || !js.success) throw new Error((js && js.data && js.data.message) ? js.data.message : 'http_' + res.status);
			return js.data;
		}

		runBtn?.addEventListener('click', async () => {
			stopped = false;
			runBtn.disabled = true;
			resetBtn.disabled = true;
			stopBtn.disabled = false;
			if (logEl) logEl.textContent = '';
			totalUp = 0; totalSkip = 0; step = 0;
			try {
				while (!stopped) {
					step++;
					const d = await chunk(false);
					totalUp += Number(d.updated || 0);
					totalSkip += Number(d.skipped || 0);
					if (posLabel) posLabel.textContent = String(d.next_pos != null ? d.next_pos : '—');
					log({ step, ...d });
					if (statusEl) statusEl.textContent = 'مرحله ' + step + ' | به‌روز ' + totalUp + ' | بدون تطبیق ' + totalSkip + (d.done ? ' | پایان فایل' : '');
					if (d.errors && d.errors.length) log({ errors: d.errors });
					if (d.done) break;
					await new Promise((r) => setTimeout(r, 30));
				}
			} catch (e) {
				log({ error: String(e && e.message ? e.message : e) });
				if (statusEl) statusEl.textContent = 'خطا';
			} finally {
				runBtn.disabled = false;
				resetBtn.disabled = false;
				stopBtn.disabled = true;
			}
		});

		resetBtn?.addEventListener('click', async () => {
			if (!confirm('<?php echo esc_js( __( 'موقعیت خواندن فایل صفر شود و از ردیف اول داده دوباره شروع شود؟', 'hello-elementor' ) ); ?>')) return;
			stopped = true;
			try {
				const d = await chunk(true);
				if (posLabel) posLabel.textContent = '0';
				log({ reset: true, ...d });
				if (statusEl) statusEl.textContent = '<?php echo esc_js( __( 'بازنشانی شد؛ دکمهٔ شروع را بزنید.', 'hello-elementor' ) ); ?>';
			} catch (e) {
				log({ error: String(e && e.message ? e.message : e) });
			}
		});

		stopBtn?.addEventListener('click', () => { stopped = true; });
	})();
	</script>
	<?php endif; ?>
	<?php
}
