<?php
/**
 * Tools → ایمپورت CSV وکلا (مسیر زیر wp-content یا آپلود).
 *
 * سطر اول: نام ستون‌ها (UTF-8). جداکننده پیش‌فرض ویرگول انگلیسی.
 * ستون specialties: چند مقدار با | یا ; یا ویرگول فارسی/انگلیسی.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transient key for uploaded CSV (separate from bundle ZIP staging).
 *
 * @return string
 */
function hovalvakil_csv_staging_transient_key() {
	return 'hovalvakil_csv_staging_' . get_current_user_id();
}

/**
 * Allowed CSV column names (must match batch item keys + meta hvl_*).
 *
 * @return string[]
 */
function hovalvakil_lawyer_csv_allowed_headers() {
	static $headers = null;
	if ( null !== $headers ) {
		return $headers;
	}
	$core = [
		'external_id',
		'title',
		'slug',
		'content',
		'excerpt',
		'status',
		'city',
		'province',
		'specialties',
		'bundle_featured_relative',
		'featured_image_url',
		'featured_image_filename',
		'featured_attachment_id',
	];
	$headers = array_values( array_unique( array_merge( $core, hovalvakil_lawyer_meta_key_list() ) ) );
	return $headers;
}

/**
 * @param string $relative Path under WP_CONTENT_DIR to a file.
 * @return string|WP_Error Absolute path.
 */
function hovalvakil_import_csv_resolve_content_file( $relative ) {
	$rel = trim( str_replace( '\\', '/', (string) $relative ), '/' );
	if ( '' === $rel || false !== strpos( $rel, '..' ) || false !== strpos( $rel, "\0" ) ) {
		return new WP_Error( 'bad_path', 'مسیر فایل CSV نامعتبر است.' );
	}
	$base = wp_normalize_path( WP_CONTENT_DIR );
	$full = wp_normalize_path( WP_CONTENT_DIR . '/' . $rel );
	if ( 0 !== strpos( $full, $base . '/' ) && $full !== $base ) {
		return new WP_Error( 'outside', 'فایل باید داخل wp-content باشد.' );
	}
	if ( ! is_readable( $full ) || ! is_file( $full ) ) {
		return new WP_Error( 'not_file', 'فایل CSV پیدا نشد یا خواندنی نیست.' );
	}
	$ext = strtolower( (string) pathinfo( $full, PATHINFO_EXTENSION ) );
	if ( 'csv' !== $ext ) {
		return new WP_Error( 'not_csv', 'پسوند فایل باید .csv باشد.' );
	}
	return $full;
}

/**
 * Split specialties cell into array for batch item.
 *
 * @param string $raw Raw cell.
 * @return string[]
 */
function hovalvakil_csv_split_specialties( $raw ) {
	$raw = (string) $raw;
	if ( '' === trim( $raw ) ) {
		return [];
	}
	$parts = preg_split( '/\s*[|;،,]\s*/u', $raw, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $parts ) ) {
		return [];
	}
	return array_values( array_filter( array_map( 'trim', $parts ) ) );
}

/**
 * Map one CSV row (associative) to lawyer batch item array.
 *
 * @param array<string, string> $assoc Trimmed header => value (non-empty only).
 * @return array<string, mixed>
 */
function hovalvakil_import_csv_assoc_to_item( array $assoc ) {
	$allowed = array_flip( hovalvakil_lawyer_csv_allowed_headers() );
	$item      = [];
	foreach ( $assoc as $key => $val ) {
		$key = trim( (string) $key );
		if ( '' === $key || ! isset( $allowed[ $key ] ) ) {
			continue;
		}
		$val = (string) $val;
		if ( 'specialties' === $key ) {
			$item['specialties'] = hovalvakil_csv_split_specialties( $val );
			continue;
		}
		if ( 'featured_attachment_id' === $key ) {
			$n = absint( $val );
			if ( $n > 0 ) {
				$item['featured_attachment_id'] = $n;
			}
			continue;
		}
		$item[ $key ] = $val;
	}
	return $item;
}

/**
 * Read CSV headers and a slice of data rows (0-based data row offset, excluding header).
 *
 * @param string $csv_path   Absolute path.
 * @param string $delimiter Single-byte delimiter (default comma).
 * @param int    $data_offset Skip this many data rows after header.
 * @param int    $limit       Max data rows to return.
 * @return array|WP_Error { headers: string[], rows: array<int, array<int, string>>, next_offset: int, eof: bool }
 */
function hovalvakil_import_csv_read_slice( $csv_path, $delimiter, $data_offset, $limit ) {
	$delimiter = (string) $delimiter;
	if ( '' === $delimiter ) {
		$delimiter = ',';
	}
	$delimiter = $delimiter[0];

	$h = fopen( $csv_path, 'rb' );
	if ( false === $h ) {
		return new WP_Error( 'open', 'باز کردن CSV ناموفق بود.' );
	}

	$bom = fread( $h, 3 );
	if ( "\xEF\xBB\xBF" !== $bom ) {
		rewind( $h );
	}

	$header_line = fgetcsv( $h, 0, $delimiter );
	if ( false === $header_line || ! is_array( $header_line ) ) {
		fclose( $h );
		return new WP_Error( 'no_header', 'سطر اول CSV (سرستون) خوانده نشد.' );
	}

	$headers = array_map(
		static function ( $c ) {
			return trim( (string) $c );
		},
		$header_line
	);
	if ( isset( $headers[0] ) ) {
		$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $headers[0] );
		$headers[0] = trim( $headers[0] );
	}

	$data_offset = max( 0, (int) $data_offset );
	$limit       = max( 1, min( 200, (int) $limit ) );

	$is_nonempty_row = static function ( $row ) {
		if ( ! is_array( $row ) ) {
			return false;
		}
		foreach ( $row as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return true;
			}
		}
		return false;
	};

	for ( $skipped = 0; $skipped < $data_offset; ) {
		$row = fgetcsv( $h, 0, $delimiter );
		if ( false === $row ) {
			fclose( $h );
			return [
				'headers'        => $headers,
				'rows'           => [],
				'next_offset'    => $data_offset,
				'file_exhausted' => true,
			];
		}
		if ( $is_nonempty_row( $row ) ) {
			$skipped++;
		}
	}

	$rows            = [];
	$file_exhausted = false;
	while ( count( $rows ) < $limit ) {
		$row = fgetcsv( $h, 0, $delimiter );
		if ( false === $row ) {
			$file_exhausted = true;
			break;
		}
		if ( ! $is_nonempty_row( $row ) ) {
			continue;
		}
		$rows[] = $row;
	}

	fclose( $h );

	return [
		'headers'        => $headers,
		'rows'           => $rows,
		'next_offset'    => $data_offset + count( $rows ),
		'file_exhausted' => $file_exhausted,
	];
}

/**
 * @param array<int, string>   $headers
 * @param array<int, string>   $row_cells
 * @return array<string, string>
 */
function hovalvakil_import_csv_row_to_assoc( array $headers, array $row_cells ) {
	$assoc = [];
	foreach ( $headers as $i => $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			continue;
		}
		$val = isset( $row_cells[ $i ] ) ? trim( (string) $row_cells[ $i ] ) : '';
		if ( '' === $val ) {
			continue;
		}
		$assoc[ $name ] = $val;
	}
	return $assoc;
}

/**
 * @param string      $csv_path     Absolute CSV path.
 * @param string|null $bundle_root  Absolute bundle dir for local images, or null/empty.
 * @param string      $delimiter
 * @param int         $data_offset
 * @param int         $limit
 * @param bool        $dry_run
 * @param bool        $sideload_url
 * @return array
 */
function hovalvakil_import_csv_run_chunk( $csv_path, $bundle_root, $delimiter, $data_offset, $limit, $dry_run, $sideload_url ) {
	$slice = hovalvakil_import_csv_read_slice( $csv_path, $delimiter, $data_offset, $limit );
	if ( is_wp_error( $slice ) ) {
		return [ 'ok' => false, 'message' => $slice->get_error_message() ];
	}

	$headers = $slice['headers'];
	$rows    = $slice['rows'];
	$root    = is_string( $bundle_root ) ? trim( $bundle_root ) : '';
	if ( '' === $root || ! is_dir( $root ) ) {
		$root = '';
	}

	$created = [];
	$updated = [];
	$errors  = [];

	$ctx = [
		'dry_run'      => $dry_run,
		'sideload_url' => $sideload_url,
		'local_root'   => $root,
	];

	foreach ( $rows as $ridx => $cells ) {
		$global_index = $data_offset + $ridx;
		$assoc        = hovalvakil_import_csv_row_to_assoc( $headers, $cells );
		$item         = hovalvakil_import_csv_assoc_to_item( $assoc );
		if ( empty( $item['title'] ) ) {
			$errors[] = [
				'index'   => $global_index,
				'message' => 'ستون title خالی است (سطر دادهٔ ' . ( $global_index + 2 ) . ' تقریبی).',
			];
			continue;
		}

		$r = hovalvakil_import_process_lawyer_batch_item( $item, (int) $global_index, $ctx );
		if ( 'e' === $r['t'] ) {
			$errors[] = [ 'index' => $r['index'], 'message' => $r['message'] ];
			continue;
		}
		if ( 'd' === $r['t'] ) {
			$created[] = [
				'index'       => $r['index'],
				'would_be'    => $r['would_be'],
				'resolved_id' => $r['resolved_id'],
			];
			continue;
		}
		if ( 'c' === $r['t'] ) {
			$created[] = [ 'index' => $r['index'], 'id' => $r['id'] ];
		} elseif ( 'u' === $r['t'] ) {
			$updated[] = [ 'index' => $r['index'], 'id' => $r['id'] ];
		}
		if ( ! empty( $r['warning'] ) ) {
			$errors[] = [ 'index' => $r['index'], 'message' => (string) $r['warning'] ];
		}
	}

	return [
		'ok'             => true,
		'offset'         => $data_offset,
		'processed'      => count( $rows ),
		'next_offset'    => (int) $slice['next_offset'],
		'done'           => ! empty( $slice['file_exhausted'] ),
		'created'        => $created,
		'updated'        => $updated,
		'errors'         => $errors,
	];
}

/**
 * @return void
 */
function hovalvakil_register_csv_import_tools_page() {
	add_management_page(
		'ایمپورت CSV وکلا',
		'ایمپورت CSV وکلا',
		'manage_options',
		'hovalvakil-csv-import',
		'hovalvakil_render_csv_import_tools_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_csv_import_tools_page' );

/**
 * @return void
 */
function hovalvakil_render_csv_import_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$staging = get_transient( hovalvakil_csv_staging_transient_key() );
	if ( ! is_array( $staging ) || empty( $staging['csv_path'] ) ) {
		$staging_csv = null;
	} else {
		$p = (string) $staging['csv_path'];
		$staging_csv = ( is_file( $p ) && is_readable( $p ) ) ? $p : null;
		if ( ! $staging_csv ) {
			delete_transient( hovalvakil_csv_staging_transient_key() );
		}
	}

	if ( isset( $_POST['hovalvakil_csv_upload'] ) && check_admin_referer( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' ) ) {
		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			add_settings_error( 'hovalvakil_csv', 'no_file', 'فایل CSV را انتخاب کنید.', 'error' );
		} else {
			$upload = wp_upload_dir();
			if ( ! empty( $upload['error'] ) ) {
				add_settings_error( 'hovalvakil_csv', 'updir', 'پوشهٔ آپلود در دسترس نیست.', 'error' );
			} else {
				$name     = sanitize_file_name( (string) $_FILES['csv_file']['name'] );
				$dest     = trailingslashit( $upload['basedir'] ) . 'hovalvakil-csv-' . wp_generate_password( 10, false, false ) . '.csv';
				$moved_ok = @move_uploaded_file( (string) $_FILES['csv_file']['tmp_name'], $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( ! $moved_ok ) {
					add_settings_error( 'hovalvakil_csv', 'move', 'ذخیرهٔ فایل آپلودی ناموفق بود.', 'error' );
				} else {
					set_transient(
						hovalvakil_csv_staging_transient_key(),
						[
							'csv_path' => wp_normalize_path( $dest ),
							'created'  => time(),
						],
						HOUR_IN_SECONDS
					);
					add_settings_error( 'hovalvakil_csv', 'up_ok', 'CSV ذخیره شد. منبع «فایل آپلودی» را انتخاب و ایمپورت را اجرا کنید.', 'success' );
					$staging_csv = $dest;
				}
			}
		}
	}

	if ( isset( $_POST['hovalvakil_csv_clear_staging'] ) && check_admin_referer( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' ) ) {
		$t = get_transient( hovalvakil_csv_staging_transient_key() );
		if ( is_array( $t ) && ! empty( $t['csv_path'] ) && is_file( $t['csv_path'] ) ) {
			@unlink( $t['csv_path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		delete_transient( hovalvakil_csv_staging_transient_key() );
		add_settings_error( 'hovalvakil_csv', 'clr', 'فایل CSV موقت حذف شد.', 'success' );
		$staging_csv = null;
	}

	$last_result = null;
	if ( isset( $_POST['hovalvakil_csv_run'] ) && check_admin_referer( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' ) ) {
		$dry_run       = ! empty( $_POST['csv_dry_run'] );
		$sideload_url  = ! empty( $_POST['csv_sideload_url'] );
		$data_offset   = isset( $_POST['csv_offset'] ) ? absint( $_POST['csv_offset'] ) : 0;
		$row_limit     = isset( $_POST['csv_limit'] ) ? absint( $_POST['csv_limit'] ) : 50;
		$delimiter     = isset( $_POST['csv_delimiter'] ) ? (string) wp_unslash( $_POST['csv_delimiter'] ) : ',';
		if ( '' === $delimiter ) {
			$delimiter = ',';
		}
		$delimiter = $delimiter[0];

		$source = isset( $_POST['csv_source'] ) ? sanitize_key( (string) $_POST['csv_source'] ) : 'path';
		$csv_abs = null;

		if ( 'upload' === $source ) {
			if ( $staging_csv ) {
				$csv_abs = $staging_csv;
			} else {
				add_settings_error( 'hovalvakil_csv', 'no_up', 'ابتدا CSV را آپلود کنید.', 'error' );
			}
		} else {
			$rel = isset( $_POST['csv_content_relpath'] ) ? (string) wp_unslash( $_POST['csv_content_relpath'] ) : 'hovalvakil-import/lawyers.csv';
			$rel = trim( $rel );
			if ( '' === $rel ) {
				$rel = 'hovalvakil-import/lawyers.csv';
			}
			$res = hovalvakil_import_csv_resolve_content_file( $rel );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'hovalvakil_csv', 'path', $res->get_error_message(), 'error' );
			} else {
				$csv_abs = $res;
			}
		}

		$bundle_root = '';
		$bundle_err  = false;
		if ( ! empty( $_POST['csv_bundle_subdir'] ) ) {
			$bs = hovalvakil_import_bundle_resolve_content_subdir( (string) wp_unslash( $_POST['csv_bundle_subdir'] ) );
			if ( is_wp_error( $bs ) ) {
				add_settings_error( 'hovalvakil_csv', 'bundle', $bs->get_error_message(), 'error' );
				$bundle_err = true;
			} else {
				$bundle_root = $bs;
			}
		}

		if ( null !== $csv_abs && ! $bundle_err && ( '' === $bundle_root || is_dir( $bundle_root ) ) ) {
			if ( function_exists( 'wp_raise_memory_limit' ) ) {
				wp_raise_memory_limit( 'admin' );
			}
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$last_result = hovalvakil_import_csv_run_chunk(
				$csv_abs,
				$bundle_root,
				$delimiter,
				$data_offset,
				$row_limit,
				$dry_run,
				$sideload_url
			);
			if ( empty( $last_result['ok'] ) ) {
				add_settings_error( 'hovalvakil_csv', 'run', $last_result['message'] ?? 'خطا', 'error' );
			} else {
				add_settings_error(
					'hovalvakil_csv',
					'run_ok',
					sprintf(
						/* translators: 1: rows processed, 2: start offset */
						'پردازش شد: %1$d سطر (از offset=%2$d).',
						(int) $last_result['processed'],
						(int) $last_result['offset']
					),
					'success'
				);
			}
		}
	}

	$form_source = isset( $_POST['csv_source'] ) ? sanitize_key( (string) wp_unslash( $_POST['csv_source'] ) ) : 'path';
	if ( 'upload' !== $form_source ) {
		$form_source = 'path';
	}
	if ( 'upload' === $form_source && ! $staging_csv ) {
		$form_source = 'path';
	}

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'ایمپورت CSV وکلا', 'hello-elementor' ) . '</h1>';
	echo '<p style="max-width: 52rem;">';
	echo esc_html__(
		'فایل UTF-8 با سطر اول سرستون. ستون‌های مجاز همان فیلدهای ایمپورت دسته‌ای است (title، external_id، city، province، specialties، bundle_featured_relative، hvl_*). برای تصویر محلی، پوشهٔ بسته (همان manifest) را در «زیرپوشهٔ تصاویر» بدهید.',
		'hello-elementor'
	);
	echo '</p>';

	settings_errors( 'hovalvakil_csv' );

	echo '<h2>' . esc_html__( 'آپلود CSV (اختیاری)', 'hello-elementor' ) . '</h2>';
	echo '<form method="post" enctype="multipart/form-data" style="margin-bottom:1.5rem;">';
	wp_nonce_field( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' );
	echo '<p><input type="file" name="csv_file" accept=".csv,text/csv"></p>';
	echo '<p><button type="submit" name="hovalvakil_csv_upload" class="button">' . esc_html__( 'بارگذاری موقت روی سرور', 'hello-elementor' ) . '</button></p>';
	echo '</form>';
	if ( $staging_csv ) {
		echo '<p><code>' . esc_html( $staging_csv ) . '</code></p>';
		echo '<form method="post" style="margin-bottom:2rem;">';
		wp_nonce_field( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' );
		echo '<button type="submit" name="hovalvakil_csv_clear_staging" class="button">' . esc_html__( 'حذف CSV موقت', 'hello-elementor' ) . '</button>';
		echo '</form>';
	}

	echo '<h2>' . esc_html__( 'اجرای ایمپورت', 'hello-elementor' ) . '</h2>';
	echo '<form method="post">';
	wp_nonce_field( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' );

	echo '<table class="form-table"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'منبع CSV', 'hello-elementor' ) . '</th><td>';
	echo '<label><input type="radio" name="csv_source" value="path" ' . checked( 'path' === $form_source, true, false ) . '> ' . esc_html__( 'مسیر زیر wp-content', 'hello-elementor' ) . '</label><br>';
	echo '<label><input type="radio" name="csv_source" value="upload" ' . ( $staging_csv ? '' : 'disabled="disabled" ' ) . checked( 'upload' === $form_source, true, false ) . '> ' . esc_html__( 'فایل آپلودی موقت', 'hello-elementor' ) . '</label>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_content_relpath">' . esc_html__( 'مسیر نسبت به wp-content', 'hello-elementor' ) . '</label></th><td>';
	echo '<input type="text" class="large-text" name="csv_content_relpath" id="csv_content_relpath" value="hovalvakil-import/lawyers.csv">';
	echo '<p class="description">' . esc_html__( 'مثال: hovalvakil-import/lawyers.csv', 'hello-elementor' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_bundle_subdir">' . esc_html__( 'زیرپوشهٔ تصاویر (اختیاری)', 'hello-elementor' ) . '</label></th><td>';
	echo '<input type="text" class="regular-text" name="csv_bundle_subdir" id="csv_bundle_subdir" value="" placeholder="hovalvakil-import">';
	echo '<p class="description">' . esc_html__( 'اگر bundle_featured_relative دارید، همان پوشه‌ای که images/ داخلش است (معمولاً hovalvakil-import). خالی = فقط URL/پیوست.', 'hello-elementor' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_delimiter">' . esc_html__( 'جداکننده', 'hello-elementor' ) . '</label></th><td>';
	echo '<input type="text" name="csv_delimiter" id="csv_delimiter" maxlength="3" value="," style="width:4rem;">';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_offset">' . esc_html__( 'offset (سطر داده، بدون سرستون)', 'hello-elementor' ) . '</label></th><td>';
	$next_off = ( is_array( $last_result ) && ! empty( $last_result['ok'] ) && empty( $last_result['done'] ) ) ? (int) $last_result['next_offset'] : 0;
	echo '<input type="number" name="csv_offset" id="csv_offset" min="0" value="' . esc_attr( (string) $next_off ) . '">';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_limit">' . esc_html__( 'سطر در هر اجرا', 'hello-elementor' ) . '</label></th><td>';
	echo '<input type="number" name="csv_limit" id="csv_limit" min="1" max="200" value="50">';
	echo '</td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'گزینه‌ها', 'hello-elementor' ) . '</th><td>';
	echo '<label><input type="checkbox" name="csv_dry_run" value="1"> ' . esc_html__( 'فقط پیش‌نمایش', 'hello-elementor' ) . '</label><br>';
	echo '<label><input type="checkbox" name="csv_sideload_url" value="1"> ' . esc_html__( 'سایدلود featured_image_url از سرور', 'hello-elementor' ) . '</label>';
	echo '</td></tr>';
	echo '</tbody></table>';
	echo '<p><button type="submit" name="hovalvakil_csv_run" class="button button-primary">' . esc_html__( 'اجرای این تکه', 'hello-elementor' ) . '</button></p>';
	echo '</form>';

	if ( is_array( $last_result ) && ! empty( $last_result['ok'] ) ) {
		echo '<h3>' . esc_html__( 'خلاصه', 'hello-elementor' ) . '</h3><ul>';
		echo '<li>' . esc_html( sprintf( __( 'ایجاد: %d', 'hello-elementor' ), count( $last_result['created'] ?? [] ) ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( __( 'به‌روزرسانی: %d', 'hello-elementor' ), count( $last_result['updated'] ?? [] ) ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( __( 'خطا/هشدار: %d', 'hello-elementor' ), count( $last_result['errors'] ?? [] ) ) ) . '</li>';
		echo '</ul>';
		if ( ! empty( $last_result['errors'] ) ) {
			echo '<details><summary>' . esc_html__( 'جزئیات', 'hello-elementor' ) . '</summary><pre style="white-space:pre-wrap;max-height:200px;overflow:auto;">';
			echo esc_html( wp_json_encode( $last_result['errors'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
			echo '</pre></details>';
		}
		if ( empty( $last_result['done'] ) ) {
			echo '<p><em>' . esc_html__( 'offset بعدی در فرم پر شده؛ در صورت وجود سطر بیشتر دوباره اجرا کنید.', 'hello-elementor' ) . '</em></p>';
		}
	}

	$sample_headers = array_slice( hovalvakil_lawyer_csv_allowed_headers(), 0, 18 );
	echo '<h2>' . esc_html__( 'نمونهٔ سرستون (بخشی از ستون‌های مجاز)', 'hello-elementor' ) . '</h2>';
	echo '<p><code>' . esc_html( implode( ',', $sample_headers ) ) . ',...</code></p>';

	echo '</div>';
}
