<?php
/**
 * Tools → ایمپورت بستهٔ وکلا از پوشهٔ روی سرور یا ZIP (بدون REST از کلاینت).
 *
 * ساختار پوشه (مثال زیر wp-content):
 *   hovalvakil-import/manifest.json
 *   hovalvakil-import/images/...
 *
 * manifest.json:
 *   { "items": [ { ... همان فیلدهای batch REST ... , "bundle_featured_relative": "images/x.webp" } ] }
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return string
 */
function hovalvakil_bundle_import_transient_key() {
	return 'hovalvakil_bundle_import_dir_' . get_current_user_id();
}

/**
 * @param string $relative Path under WP_CONTENT_DIR, no leading slash.
 * @return string|WP_Error Absolute normalized directory.
 */
function hovalvakil_import_bundle_resolve_content_subdir( $relative ) {
	$rel = trim( str_replace( '\\', '/', (string) $relative ), '/' );
	if ( '' === $rel || false !== strpos( $rel, '..' ) || false !== strpos( $rel, "\0" ) ) {
		return new WP_Error( 'bad_path', 'مسیر پوشه نامعتبر است (فقط زیرپوشهٔ wp-content).' );
	}
	$base = wp_normalize_path( WP_CONTENT_DIR );
	$full = wp_normalize_path( WP_CONTENT_DIR . '/' . $rel );
	if ( 0 !== strpos( $full, $base . '/' ) && $full !== $base ) {
		return new WP_Error( 'outside', 'مسیر باید داخل wp-content باشد.' );
	}
	if ( ! is_dir( $full ) ) {
		return new WP_Error( 'not_dir', 'پوشه وجود ندارد یا قابل خواندن نیست.' );
	}
	return $full;
}

/**
 * @param string $dir Absolute directory.
 * @return void
 */
function hovalvakil_import_bundle_delete_tree( $dir ) {
	$dir = trailingslashit( $dir );
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = array_diff( scandir( $dir ), [ '.', '..' ] );
	foreach ( $items as $item ) {
		$p = $dir . $item;
		if ( is_dir( $p ) ) {
			hovalvakil_import_bundle_delete_tree( $p );
		} else {
			@unlink( $p ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * Load items array from manifest.json in bundle root.
 *
 * @param string $bundle_root Absolute path.
 * @return array|WP_Error { 'items' => array, 'total' => int }
 */
function hovalvakil_import_bundle_load_manifest( $bundle_root ) {
	$path = trailingslashit( $bundle_root ) . 'manifest.json';
	if ( ! is_readable( $path ) ) {
		return new WP_Error( 'no_manifest', 'فایل manifest.json در ریشهٔ بسته پیدا نشد.' );
	}
	$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $raw ) {
		return new WP_Error( 'read_fail', 'خواندن manifest.json ناموفق بود.' );
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'bad_json', 'manifest.json JSON معتبر نیست.' );
	}
	if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
		$items = $data['items'];
	} elseif ( isset( $data[0] ) && is_array( $data[0] ) ) {
		$items = $data;
	} else {
		return new WP_Error( 'bad_shape', 'manifest باید آرایهٔ items یا آرایهٔ مستقیم رکوردها باشد.' );
	}
	return [ 'items' => $items, 'total' => count( $items ) ];
}

/**
 * @param string $bundle_root Absolute bundle root.
 * @param int    $offset      Zero-based offset.
 * @param int    $limit       Max items this chunk.
 * @param bool   $dry_run
 * @param bool   $sideload_url Sideload featured_image_url when no local file.
 * @return array Summary for admin UI.
 */
function hovalvakil_import_bundle_run_chunk( $bundle_root, $offset, $limit, $dry_run, $sideload_url ) {
	$loaded = hovalvakil_import_bundle_load_manifest( $bundle_root );
	if ( is_wp_error( $loaded ) ) {
		return [ 'ok' => false, 'message' => $loaded->get_error_message() ];
	}
	$items = $loaded['items'];
	$total = (int) $loaded['total'];
	$offset = max( 0, (int) $offset );
	$limit  = max( 1, min( 200, (int) $limit ) );
	$slice  = array_slice( $items, $offset, $limit );

	$created = [];
	$updated = [];
	$errors  = [];

	$ctx = [
		'dry_run'      => $dry_run,
		'sideload_url' => $sideload_url,
		'local_root'   => $bundle_root,
	];

	foreach ( $slice as $i => $raw_item ) {
		if ( ! is_array( $raw_item ) ) {
			$errors[] = [ 'index' => $offset + $i, 'message' => 'آیتم باید آبجکت باشد.' ];
			continue;
		}
		$index = $offset + $i;
		$r     = hovalvakil_import_process_lawyer_batch_item( $raw_item, $index, $ctx );
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

	$next = $offset + count( $slice );
	return [
		'ok'          => true,
		'total'       => $total,
		'offset'      => $offset,
		'processed'   => count( $slice ),
		'next_offset' => $next,
		'done'        => $next >= $total,
		'created'     => $created,
		'updated'     => $updated,
		'errors'      => $errors,
	];
}

/**
 * @return void
 */
function hovalvakil_register_bundle_import_tools_page() {
	add_management_page(
		'ایمپورت بستهٔ وکلا',
		'ایمپورت بستهٔ وکلا',
		'manage_options',
		'hovalvakil-bundle-import',
		'hovalvakil_render_bundle_import_tools_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_bundle_import_tools_page' );

/**
 * @return void
 */
function hovalvakil_render_bundle_import_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$staging = get_transient( hovalvakil_bundle_import_transient_key() );
	if ( ! is_array( $staging ) || empty( $staging['path'] ) ) {
		$staging = null;
	} else {
		$p = (string) $staging['path'];
		if ( ! is_dir( $p ) || ! is_readable( trailingslashit( $p ) . 'manifest.json' ) ) {
			delete_transient( hovalvakil_bundle_import_transient_key() );
			$staging = null;
		}
	}

	if ( isset( $_POST['hovalvakil_bundle_upload_zip'] ) && check_admin_referer( 'hovalvakil_bundle_import_action', 'hovalvakil_bundle_import_nonce' ) ) {
		if ( empty( $_FILES['bundle_zip']['tmp_name'] ) ) {
			add_settings_error( 'hovalvakil_bundle', 'no_zip', 'فایل ZIP را انتخاب کنید.', 'error' );
		} elseif ( ! class_exists( 'ZipArchive' ) ) {
			add_settings_error( 'hovalvakil_bundle', 'no_ziparchive', 'افزونهٔ ZipArchive روی PHP فعال نیست.', 'error' );
		} else {
			$tmp = (string) $_FILES['bundle_zip']['tmp_name'];
			$zip = new ZipArchive();
			if ( true !== $zip->open( $tmp ) ) {
				add_settings_error( 'hovalvakil_bundle', 'zip_open', 'باز کردن ZIP ناموفق بود.', 'error' );
			} else {
				$upload = wp_upload_dir();
				if ( ! empty( $upload['error'] ) ) {
					add_settings_error( 'hovalvakil_bundle', 'updir', 'پوشهٔ آپلود در دسترس نیست.', 'error' );
				} else {
					$dest = trailingslashit( $upload['basedir'] ) . 'hovalvakil-bundle-' . wp_generate_password( 12, false, false );
					wp_mkdir_p( $dest );
					$zip->extractTo( $dest );
					$zip->close();
					if ( ! is_readable( trailingslashit( $dest ) . 'manifest.json' ) ) {
						hovalvakil_import_bundle_delete_tree( $dest );
						add_settings_error( 'hovalvakil_bundle', 'no_manifest_zip', 'داخل ZIP فایل manifest.json در ریشه نیست.', 'error' );
					} else {
						set_transient(
							hovalvakil_bundle_import_transient_key(),
							[
								'path'    => wp_normalize_path( $dest ),
								'created' => time(),
							],
							HOUR_IN_SECONDS
						);
						add_settings_error( 'hovalvakil_bundle', 'zip_ok', 'ZIP استخراج شد. می‌توانید ایمپورت را اجرا کنید.', 'success' );
						$staging = get_transient( hovalvakil_bundle_import_transient_key() );
					}
				}
			}
		}
	}

	if ( isset( $_POST['hovalvakil_bundle_clear_staging'] ) && check_admin_referer( 'hovalvakil_bundle_import_action', 'hovalvakil_bundle_import_nonce' ) ) {
		$t = get_transient( hovalvakil_bundle_import_transient_key() );
		if ( is_array( $t ) && ! empty( $t['path'] ) && is_dir( $t['path'] ) ) {
			hovalvakil_import_bundle_delete_tree( $t['path'] );
		}
		delete_transient( hovalvakil_bundle_import_transient_key() );
		add_settings_error( 'hovalvakil_bundle', 'cleared', 'پوشهٔ موقت پاک شد.', 'success' );
		$staging = null;
	}

	$last_result = null;
	if ( isset( $_POST['hovalvakil_bundle_run'] ) && check_admin_referer( 'hovalvakil_bundle_import_action', 'hovalvakil_bundle_import_nonce' ) ) {
		$dry_run      = ! empty( $_POST['bundle_dry_run'] );
		$sideload_url = ! empty( $_POST['bundle_sideload_url'] );
		$offset       = isset( $_POST['bundle_offset'] ) ? absint( $_POST['bundle_offset'] ) : 0;
		$limit        = isset( $_POST['bundle_limit'] ) ? absint( $_POST['bundle_limit'] ) : 50;
		if ( $limit < 1 ) {
			$limit = 50;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}

		$source = isset( $_POST['bundle_source'] ) ? sanitize_key( (string) $_POST['bundle_source'] ) : 'folder';
		$root   = null;

		if ( 'staging' === $source ) {
			if ( $staging && ! empty( $staging['path'] ) ) {
				$root = (string) $staging['path'];
			} else {
				add_settings_error( 'hovalvakil_bundle', 'no_staging', 'پوشهٔ موقت وجود ندارد؛ ابتدا ZIP را استخراج کنید یا منبع «پوشه زیر wp-content» را انتخاب کنید.', 'error' );
			}
		} else {
			$rel = isset( $_POST['bundle_content_subdir'] ) ? (string) wp_unslash( $_POST['bundle_content_subdir'] ) : 'hovalvakil-import';
			$rel = trim( $rel );
			if ( '' === $rel ) {
				$rel = 'hovalvakil-import';
			}
			$res = hovalvakil_import_bundle_resolve_content_subdir( $rel );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'hovalvakil_bundle', 'bad_dir', $res->get_error_message(), 'error' );
			} else {
				$root = $res;
			}
		}

		if ( null !== $root ) {
			if ( function_exists( 'wp_raise_memory_limit' ) ) {
				wp_raise_memory_limit( 'admin' );
			}
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$last_result = hovalvakil_import_bundle_run_chunk( $root, $offset, $limit, $dry_run, $sideload_url );
			if ( empty( $last_result['ok'] ) ) {
				add_settings_error( 'hovalvakil_bundle', 'run_fail', $last_result['message'] ?? 'خطا', 'error' );
			} else {
				$msg = sprintf(
					/* translators: 1: processed count, 2: total, 3: next offset */
					'پردازش شد: %1$d از مجموع %2$d (شروع از ایندکس %3$d).',
					(int) $last_result['processed'],
					(int) $last_result['total'],
					(int) $last_result['offset']
				);
				add_settings_error( 'hovalvakil_bundle', 'run_ok', $msg, 'success' );
			}
		}
	}

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'ایمپورت بستهٔ وکلا (پوشهٔ سرور یا ZIP)', 'hello-elementor' ) . '</h1>';
	echo '<p style="max-width: 52rem;">';
	echo esc_html__(
		'داده‌ها را در یک فایل manifest.json کنار تصاویر روی سرور قرار دهید (مثلاً با SFTP داخل wp-content/hovalvakil-import)، یا یک ZIP شامل manifest.json و پوشهٔ تصاویر آپلود کنید. در هر رکورد می‌توانید با فیلد bundle_featured_relative مسیر تصویر را نسبت به ریشهٔ بسته بدهید؛ نیازی به REST از IP شما نیست.',
		'hello-elementor'
	);
	echo '</p>';

	settings_errors( 'hovalvakil_bundle' );

	$form_source = isset( $_POST['bundle_source'] ) ? sanitize_key( (string) wp_unslash( $_POST['bundle_source'] ) ) : 'folder';
	if ( 'staging' !== $form_source ) {
		$form_source = 'folder';
	}
	if ( 'staging' === $form_source && ! $staging ) {
		$form_source = 'folder';
	}

	echo '<h2>' . esc_html__( '۱) آپلود ZIP (اختیاری)', 'hello-elementor' ) . '</h2>';
	echo '<form method="post" enctype="multipart/form-data" style="margin-bottom: 2rem;">';
	wp_nonce_field( 'hovalvakil_bundle_import_action', 'hovalvakil_bundle_import_nonce' );
	echo '<p><input type="file" name="bundle_zip" accept=".zip,application/zip" required></p>';
	echo '<p><button type="submit" name="hovalvakil_bundle_upload_zip" class="button">' . esc_html__( 'استخراج ZIP در سرور', 'hello-elementor' ) . '</button></p>';
	echo '</form>';

	if ( $staging ) {
		echo '<p><strong>' . esc_html__( 'پوشهٔ موقت فعال:', 'hello-elementor' ) . '</strong> <code>' . esc_html( (string) $staging['path'] ) . '</code></p>';
		echo '<form method="post" style="margin-bottom: 2rem;">';
		wp_nonce_field( 'hovalvakil_bundle_import_action', 'hovalvakil_bundle_import_nonce' );
		echo '<button type="submit" name="hovalvakil_bundle_clear_staging" class="button">' . esc_html__( 'پاک کردن پوشهٔ موقت', 'hello-elementor' ) . '</button>';
		echo '</form>';
	}

	echo '<h2>' . esc_html__( '۲) اجرای ایمپورت (تکه‌تکه)', 'hello-elementor' ) . '</h2>';
	echo '<form method="post">';
	wp_nonce_field( 'hovalvakil_bundle_import_action', 'hovalvakil_bundle_import_nonce' );

	echo '<table class="form-table"><tbody>';

	echo '<tr><th scope="row">' . esc_html__( 'منبع', 'hello-elementor' ) . '</th><td>';
	echo '<label><input type="radio" name="bundle_source" value="folder" ' . checked( 'folder' === $form_source, true, false ) . '> ' . esc_html__( 'پوشه زیر wp-content', 'hello-elementor' ) . '</label><br>';
	echo '<label><input type="radio" name="bundle_source" value="staging" ' . ( $staging ? '' : 'disabled="disabled" ' ) . checked( 'staging' === $form_source, true, false ) . '> ' . esc_html__( 'پوشهٔ موقت (بعد از ZIP)', 'hello-elementor' ) . '</label>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="bundle_content_subdir">' . esc_html__( 'زیرپوشهٔ wp-content', 'hello-elementor' ) . '</label></th><td>';
	echo '<input name="bundle_content_subdir" id="bundle_content_subdir" type="text" class="regular-text" value="hovalvakil-import" placeholder="hovalvakil-import">';
	echo '<p class="description">' . esc_html__( 'مثال: hovalvakil-import — مسیر نهایی wp-content/hovalvakil-import', 'hello-elementor' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="bundle_offset">' . esc_html__( 'offset (ایندکس شروع)', 'hello-elementor' ) . '</label></th><td>';
	$next_off = ( is_array( $last_result ) && ! empty( $last_result['ok'] ) && empty( $last_result['done'] ) ) ? (int) $last_result['next_offset'] : 0;
	echo '<input name="bundle_offset" id="bundle_offset" type="number" min="0" value="' . esc_attr( (string) $next_off ) . '">';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="bundle_limit">' . esc_html__( 'تعداد در هر اجرا', 'hello-elementor' ) . '</label></th><td>';
	echo '<input name="bundle_limit" id="bundle_limit" type="number" min="1" max="200" value="50">';
	echo '</td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'گزینه‌ها', 'hello-elementor' ) . '</th><td>';
	echo '<label><input type="checkbox" name="bundle_dry_run" value="1"> ' . esc_html__( 'فقط پیش‌نمایش (dry run)', 'hello-elementor' ) . '</label><br>';
	echo '<label><input type="checkbox" name="bundle_sideload_url" value="1"> ' . esc_html__( 'اگر URL تصویر بود، از اینترنت سرور سایدلود شود', 'hello-elementor' ) . '</label>';
	echo '</td></tr>';

	echo '</tbody></table>';
	echo '<p><button type="submit" name="hovalvakil_bundle_run" class="button button-primary">' . esc_html__( 'اجرای این تکه', 'hello-elementor' ) . '</button></p>';
	echo '</form>';

	if ( is_array( $last_result ) && ! empty( $last_result['ok'] ) ) {
		echo '<h3>' . esc_html__( 'خلاصهٔ آخرین اجرا', 'hello-elementor' ) . '</h3>';
		echo '<ul>';
		echo '<li>' . esc_html( sprintf( /* translators: %d: number of created posts */ __( 'ایجاد: %d', 'hello-elementor' ), count( $last_result['created'] ?? [] ) ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: %d: number of updated posts */ __( 'به‌روزرسانی: %d', 'hello-elementor' ), count( $last_result['updated'] ?? [] ) ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( /* translators: %d: number of errors/warnings */ __( 'خطا/هشدار: %d', 'hello-elementor' ), count( $last_result['errors'] ?? [] ) ) ) . '</li>';
		if ( ! empty( $last_result['errors'] ) ) {
			echo '<li><details><summary>' . esc_html__( 'جزئیات خطاها', 'hello-elementor' ) . '</summary><pre style="white-space:pre-wrap;max-height:240px;overflow:auto;">';
			echo esc_html( wp_json_encode( $last_result['errors'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
			echo '</pre></details></li>';
		}
		echo '</ul>';
		if ( empty( $last_result['done'] ) ) {
			echo '<p><em>' . esc_html__( 'هنوز رکورد باقی مانده؛ offset بعدی در فرم بالا پر شده است. دوباره «اجرای این تکه» را بزنید.', 'hello-elementor' ) . '</em></p>';
		}
	}

	echo '<h2>' . esc_html__( 'نمونهٔ manifest.json', 'hello-elementor' ) . '</h2>';
	echo '<p class="description" style="max-width:52rem;">';
	echo esc_html__(
		'شماره پروانه و تاریخ‌ها را با کلیدهای hvl_license_no، hvl_license_issued و hvl_license_expires بگذارید (تاریخ میلادی YYYY-MM-DD). برای نمایش شمسی اختیاری: hvl_license_issued_jalali و hvl_license_expires_jalali. پایهٔ وکالت: hvl_lawyer_grade. فایل پروانه فقط URL: hvl_license_file_url.',
		'hello-elementor'
	);
	echo '</p>';
	echo '<pre style="max-width:52rem;overflow:auto;background:#f6f7f7;padding:12px;">';
	echo esc_html(
		wp_json_encode(
			[
				'items' => [
					[
						'external_id'                => 'demo-1',
						'title'                      => 'نام وکیل',
						'slug'                       => 'lawyer-demo',
						'bundle_featured_relative'   => 'images/demo/profile.webp',
						'city'                       => 'tehran',
						'province'                   => 'tehran',
						'specialties'                => [ 'حقوق-خانواده' ],
						'hvl_mobile'                 => '09121234567',
						'hvl_license_no'             => '۱۲۳۴۵',
						'hvl_license_issued'         => '2016-03-20',
						'hvl_license_expires'        => '2027-09-15',
						'hvl_license_issued_jalali'  => '۱۳۹۴/۱۲/۳۰',
						'hvl_license_expires_jalali' => '۱۴۰۶/۰۶/۲۴',
						'hvl_lawyer_grade'           => 'پایه یک دادگستری',
						'hvl_license_file_url'       => 'https://example.com/licenses/demo.pdf',
					],
				],
			],
			JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		)
	);
	echo '</pre>';

	echo '</div>';
}
