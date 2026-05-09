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
 * Background CSV import job option key.
 *
 * @return string
 */
function hovalvakil_csv_bg_job_option_key() {
	return 'hovalvakil_csv_bg_job';
}

/**
 * Option key for one-click unique import cursor.
 *
 * @return string
 */
function hovalvakil_csv_quick5000_cursor_option_key() {
	return 'hovalvakil_csv_quick5000_cursor';
}

/**
 * Read current background CSV job.
 *
 * @return array
 */
function hovalvakil_csv_bg_job_get() {
	$job = get_option( hovalvakil_csv_bg_job_option_key(), [] );
	return is_array( $job ) ? $job : [];
}

/**
 * فاصلهٔ Cron برای نگه داشتن ایمپورت پس‌زمینه بدون باز بودن لپ‌تاپ (به‌شرط ترافیک یا Cron واقعی هاست).
 *
 * @param array<string, array<string, int|string>> $schedules Schedules.
 * @return array<string, array<string, int|string>>
 */
function hovalvakil_csv_bg_register_cron_interval( $schedules ) {
	if ( ! isset( $schedules['hovalvakil_csv_5min'] ) ) {
		$schedules['hovalvakil_csv_5min'] = [
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Hovalvakil CSV background (5 min)',
		];
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'hovalvakil_csv_bg_register_cron_interval' );

/**
 * @return void
 */
function hovalvakil_csv_bg_unschedule_heartbeat() {
	while ( ( $ts = wp_next_scheduled( 'hovalvakil_csv_bg_heartbeat' ) ) ) {
		wp_unschedule_event( $ts, 'hovalvakil_csv_bg_heartbeat' );
	}
}

/**
 * @return void
 */
function hovalvakil_csv_bg_schedule_heartbeat() {
	if ( wp_next_scheduled( 'hovalvakil_csv_bg_heartbeat' ) ) {
		return;
	}
	wp_schedule_event( time() + 90, 'hovalvakil_csv_5min', 'hovalvakil_csv_bg_heartbeat' );
}

/**
 * هر چند دقیقه یک‌بار (روی درخواست‌های وردپرس) ایمپورت پس‌زمینه را جلو می‌برد.
 *
 * @return void
 */
function hovalvakil_csv_bg_heartbeat_callback() {
	$job = hovalvakil_csv_bg_job_get();
	if ( empty( $job['active'] ) ) {
		hovalvakil_csv_bg_unschedule_heartbeat();
		return;
	}
	if ( get_transient( 'hovalvakil_csv_bg_heartbeat_lock' ) ) {
		return;
	}
	set_transient( 'hovalvakil_csv_bg_heartbeat_lock', '1', 240 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	@set_time_limit( 270 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	hovalvakil_csv_bg_run_burst( 120.0 );
	delete_transient( 'hovalvakil_csv_bg_heartbeat_lock' );
	hovalvakil_csv_bg_spawn_wp_cron();
}
add_action( 'hovalvakil_csv_bg_heartbeat', 'hovalvakil_csv_bg_heartbeat_callback' );

/**
 * اگر job فعال است و زمان‌بندی ضربان حذف شده بود، دوباره ثبت شود.
 *
 * @return void
 */
function hovalvakil_csv_bg_maybe_reschedule_heartbeat() {
	if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
		return;
	}
	$job = hovalvakil_csv_bg_job_get();
	if ( empty( $job['active'] ) ) {
		return;
	}
	if ( ! wp_next_scheduled( 'hovalvakil_csv_bg_heartbeat' ) ) {
		hovalvakil_csv_bg_schedule_heartbeat();
	}
}
add_action( 'init', 'hovalvakil_csv_bg_maybe_reschedule_heartbeat', 30 );

/**
 * Persist background CSV job.
 *
 * @param array $job Job payload.
 * @return void
 */
function hovalvakil_csv_bg_job_set( array $job ) {
	update_option( hovalvakil_csv_bg_job_option_key(), $job, false );
	if ( ! empty( $job['active'] ) ) {
		hovalvakil_csv_bg_schedule_heartbeat();
	} else {
		hovalvakil_csv_bg_unschedule_heartbeat();
	}
}

/**
 * Clear background CSV job.
 *
 * @return void
 */
function hovalvakil_csv_bg_job_clear() {
	hovalvakil_csv_bg_unschedule_heartbeat();
	delete_option( hovalvakil_csv_bg_job_option_key() );
}

/**
 * Worker tick: در یک بار فراخوانی چند بچ CSV را تا سقف زمان/تعداد اجرا می‌کند (Cron سریع‌تر).
 *
 * @return void
 */
function hovalvakil_csv_bg_worker_tick() {
	$job = hovalvakil_csv_bg_job_get();
	if ( empty( $job['active'] ) || empty( $job['csv_path'] ) ) {
		return;
	}
	$csv_path = (string) $job['csv_path'];
	if ( ! is_file( $csv_path ) || ! is_readable( $csv_path ) ) {
		$job['active']       = false;
		$job['last_error']   = 'CSV background job stopped: file not readable.';
		$job['finished_at']  = time();
		hovalvakil_csv_bg_job_set( $job );
		return;
	}

	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	$budget     = (float) apply_filters( 'hovalvakil_csv_bg_tick_budget_seconds', 85.0 );
	$max_chunks = (int) apply_filters( 'hovalvakil_csv_bg_max_chunks_per_tick', 80 );
	$deadline   = microtime( true ) + max( 20.0, $budget );
	$chunks     = 0;

	while ( microtime( true ) < $deadline && $chunks < $max_chunks ) {
		$job = hovalvakil_csv_bg_job_get();
		if ( empty( $job['active'] ) || empty( $job['csv_path'] ) ) {
			return;
		}
		$csv_path = (string) $job['csv_path'];
		if ( ! is_file( $csv_path ) || ! is_readable( $csv_path ) ) {
			$job['active']      = false;
			$job['last_error']  = 'CSV background job stopped: file not readable.';
			$job['finished_at'] = time();
			hovalvakil_csv_bg_job_set( $job );
			return;
		}

		$bundle_root = isset( $job['bundle_root'] ) ? (string) $job['bundle_root'] : '';
		$delimiter   = isset( $job['delimiter'] ) ? (string) $job['delimiter'] : ',';
		$offset      = isset( $job['offset'] ) ? (int) $job['offset'] : 0;
		$limit       = isset( $job['limit'] ) ? (int) $job['limit'] : 1000;
		$limit       = max( 1, min( 2000, $limit ) );
		$dry_run     = ! empty( $job['dry_run'] );
		$sideload    = ! empty( $job['sideload_url'] );

		$r = hovalvakil_import_csv_run_chunk( $csv_path, $bundle_root, $delimiter, $offset, $limit, $dry_run, $sideload );
		if ( empty( $r['ok'] ) ) {
			$job['active']      = false;
			$job['last_error']  = isset( $r['message'] ) ? (string) $r['message'] : 'unknown error';
			$job['finished_at'] = time();
			hovalvakil_csv_bg_job_set( $job );
			return;
		}

		$job['offset']           = (int) $r['next_offset'];
		$job['processed_total']  = (int) ( $job['processed_total'] ?? 0 ) + (int) $r['processed'];
		$job['created_total']    = (int) ( $job['created_total'] ?? 0 ) + count( (array) ( $r['created'] ?? [] ) );
		$job['updated_total']    = (int) ( $job['updated_total'] ?? 0 ) + count( (array) ( $r['updated'] ?? [] ) );
		$job['error_total']      = (int) ( $job['error_total'] ?? 0 ) + count( (array) ( $r['errors'] ?? [] ) );
		$job['last_tick_at']     = time();
		$job['last_error']       = '';
		$job['active']           = true;
		hovalvakil_csv_bg_job_set( $job );
		$chunks++;

		if ( ! empty( $r['done'] ) || (int) $r['processed'] <= 0 ) {
			$job['active']      = false;
			$job['finished_at'] = time();
			hovalvakil_csv_bg_job_set( $job );
			return;
		}
	}

	$job = hovalvakil_csv_bg_job_get();
	if ( empty( $job['active'] ) ) {
		return;
	}
	$job['active'] = true;
	hovalvakil_csv_bg_job_set( $job );
	if ( ! wp_next_scheduled( 'hovalvakil_csv_bg_worker' ) ) {
		wp_schedule_single_event( time() + 1, 'hovalvakil_csv_bg_worker' );
	}
}
add_action( 'hovalvakil_csv_bg_worker', 'hovalvakil_csv_bg_worker_tick' );

/**
 * اجرای پیاپی worker تا پایان زمان یا اتمام job (برای هاست‌هایی که WP-Cron ضعیف است).
 *
 * @param float $max_seconds حداکثر زمان wall-clock.
 * @return int تعداد tick اجرا شده.
 */
function hovalvakil_csv_bg_run_burst( $max_seconds = 120.0 ) {
	$deadline = microtime( true ) + max( 5.0, (float) $max_seconds );
	$ticks    = 0;
	while ( microtime( true ) < $deadline ) {
		$job = hovalvakil_csv_bg_job_get();
		if ( empty( $job['active'] ) ) {
			break;
		}
		$off_before = (int) ( $job['offset'] ?? 0 );
		hovalvakil_csv_bg_worker_tick();
		$ticks++;
		$job = hovalvakil_csv_bg_job_get();
		if ( empty( $job['active'] ) ) {
			break;
		}
		$off_after = (int) ( $job['offset'] ?? 0 );
		if ( $off_after <= $off_before ) {
			break;
		}
	}
	return $ticks;
}

/**
 * تلاش برای زنده کردن wp-cron بدون انتظار بازدید (بهتر از هیچ).
 *
 * @return void
 */
function hovalvakil_csv_bg_spawn_wp_cron() {
	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		return;
	}
	spawn_cron();
	$url = site_url( 'wp-cron.php?doing_wp_cron=' . rawurlencode( (string) microtime( true ) ) );
	wp_remote_get(
		$url,
		[
			'timeout'   => 0.01,
			'blocking'  => false,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		]
	);
}

/**
 * هر بار باز کردن صفحهٔ ایمپورت CSV، اگر job فعال است چند ثانیه جلو ببرد.
 *
 * @return void
 */
function hovalvakil_csv_bg_pulse_on_tools_load() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['hovalvakil_csv_run'] ) ) {
		return;
	}
	$job = hovalvakil_csv_bg_job_get();
	if ( empty( $job['active'] ) ) {
		return;
	}
	if ( get_transient( 'hovalvakil_csv_bg_pulse_lock' ) ) {
		return;
	}
	set_transient( 'hovalvakil_csv_bg_pulse_lock', '1', 140 );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	hovalvakil_csv_bg_run_burst( 120.0 );
	delete_transient( 'hovalvakil_csv_bg_pulse_lock' );
	hovalvakil_csv_bg_spawn_wp_cron();
}
add_action( 'load-tools_page_hovalvakil-csv-import', 'hovalvakil_csv_bg_pulse_on_tools_load', 5 );

/**
 * یک تکهٔ ایمپورت CSV (برای حالت «مرورگر خودش تا آخر» بدون SSH/WP-CLI).
 *
 * @return void
 */
function hovalvakil_csv_ajax_import_chunk() {
	if ( ! check_ajax_referer( 'hovalvakil_csv_ajax', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'نشست نامعتبر؛ صفحه را تازه کنید.' ], 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی کافی نیست.' ], 403 );
	}

	$dry_run      = ! empty( $_POST['dry_run'] );
	$sideload_url = ! empty( $_POST['sideload_url'] );
	$data_offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$row_limit    = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 100;
	$row_limit    = max( 1, min( 2000, $row_limit ) );
	$delimiter    = isset( $_POST['delimiter'] ) ? (string) wp_unslash( $_POST['delimiter'] ) : ',';
	if ( '' === $delimiter ) {
		$delimiter = ',';
	}
	$delimiter = $delimiter[0];

	$source = isset( $_POST['csv_source'] ) ? sanitize_key( (string) wp_unslash( $_POST['csv_source'] ) ) : 'path';
	$csv_abs = null;

	if ( 'upload' === $source ) {
		$staging = get_transient( hovalvakil_csv_staging_transient_key() );
		$p       = ( is_array( $staging ) && ! empty( $staging['csv_path'] ) ) ? (string) $staging['csv_path'] : '';
		if ( $p && is_file( $p ) && is_readable( $p ) ) {
			$csv_abs = wp_normalize_path( $p );
		} else {
			wp_send_json_error( [ 'message' => 'فایل CSV آپلودی پیدا نشد؛ دوباره بارگذاری کنید.' ] );
		}
	} else {
		$rel = isset( $_POST['csv_content_relpath'] ) ? (string) wp_unslash( $_POST['csv_content_relpath'] ) : 'hovalvakil-import/lawyers.csv';
		$rel = trim( $rel );
		if ( '' === $rel ) {
			$rel = 'hovalvakil-import/lawyers.csv';
		}
		$res = hovalvakil_import_csv_resolve_content_file( $rel );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( [ 'message' => $res->get_error_message() ] );
		}
		$csv_abs = $res;
	}

	$bundle_root = '';
	$bundle_err  = false;
	if ( ! empty( $_POST['csv_bundle_subdir'] ) ) {
		$bs = hovalvakil_import_bundle_resolve_content_subdir( (string) wp_unslash( $_POST['csv_bundle_subdir'] ) );
		if ( is_wp_error( $bs ) ) {
			wp_send_json_error( [ 'message' => $bs->get_error_message() ] );
		}
		$bundle_root = $bs;
	}
	if ( '' === $bundle_root && null !== $csv_abs ) {
		$maybe = dirname( (string) $csv_abs );
		if ( is_dir( $maybe ) ) {
			$bundle_root = $maybe;
		}
	}

	if ( null === $csv_abs || ( '' !== $bundle_root && ! is_dir( $bundle_root ) ) ) {
		wp_send_json_error( [ 'message' => 'مسیر CSV یا پوشهٔ بسته نامعتبر است.' ] );
	}

	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	$r = hovalvakil_import_csv_run_chunk(
		$csv_abs,
		$bundle_root,
		$delimiter,
		$data_offset,
		$row_limit,
		$dry_run,
		$sideload_url
	);

	if ( empty( $r['ok'] ) ) {
		wp_send_json_error(
			[
				'message' => isset( $r['message'] ) ? (string) $r['message'] : 'خطای ناشناخته',
				'chunk'   => $r,
			]
		);
	}

	wp_send_json_success( $r );
}
add_action( 'wp_ajax_hovalvakil_csv_import_chunk', 'hovalvakil_csv_ajax_import_chunk' );

/**
 * چند ثانیهٔ پشت‌سرهم worker پس‌زمینه (برای ادامهٔ مطمئن بدون Cron قوی).
 *
 * @return void
 */
function hovalvakil_csv_ajax_bg_burst() {
	if ( ! check_ajax_referer( 'hovalvakil_csv_ajax', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'نشست نامعتبر؛ صفحه را تازه کنید.' ], 403 );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی کافی نیست.' ], 403 );
	}
	$seconds = isset( $_POST['seconds'] ) ? absint( $_POST['seconds'] ) : 150;
	$seconds = max( 20, min( 360, $seconds ) );
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	@set_time_limit( $seconds + 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$ticks = hovalvakil_csv_bg_run_burst( (float) $seconds );
	hovalvakil_csv_bg_spawn_wp_cron();
	$job = hovalvakil_csv_bg_job_get();
	wp_send_json_success(
		[
			'ticks' => $ticks,
			'job'   => $job,
		]
	);
}
add_action( 'wp_ajax_hovalvakil_csv_bg_burst', 'hovalvakil_csv_ajax_bg_burst' );

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
	$alias_map = [
		'full_name'      => 'title',
		'mobile'         => 'hvl_mobile',
		'office_mobile'  => 'hvl_office_mobile',
		'office_phone'   => 'hvl_office_phone',
		'office_address' => 'hvl_office_address',
		'license_no'     => 'hvl_license_no',
		'lawyer_level'   => 'hvl_lawyer_grade',
		'grade'          => 'hvl_lawyer_grade',
		'photo_url'      => 'featured_image_url',
	];

	$read = static function ( $k ) use ( $assoc ) {
		return isset( $assoc[ $k ] ) ? trim( (string) $assoc[ $k ] ) : '';
	};
	foreach ( $assoc as $key => $val ) {
		$key = trim( (string) $key );
		if ( isset( $alias_map[ $key ] ) ) {
			$key = $alias_map[ $key ];
		}
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
		if ( 'bundle_featured_relative' === $key ) {
			$norm = function_exists( 'hovalvakil_import_normalize_bundle_relative' )
				? hovalvakil_import_normalize_bundle_relative( $val )
				: trim( (string) $val );
			if ( '' !== $norm ) {
				$item['bundle_featured_relative'] = $norm;
			}
			continue;
		}
		$item[ $key ] = $val;
	}

	// Raw checkpoint-style image directory.
	if ( empty( $item['bundle_featured_relative'] ) ) {
		$img_rel = $read( 'image_relative_dir' );
		if ( '' === $img_rel ) {
			$img_rel = $read( 'image_folder' );
		}
		if ( '' === $img_rel ) {
			$img_rel = $read( 'image_path' );
		}
		if ( '' !== $img_rel ) {
			$norm_img = function_exists( 'hovalvakil_import_normalize_bundle_relative' )
				? hovalvakil_import_normalize_bundle_relative( $img_rel )
				: trim( (string) $img_rel );
			if ( '' !== $norm_img ) {
				if ( ! preg_match( '#\.(webp|jpe?g|png|gif)$#i', $norm_img ) ) {
					$norm_img = rtrim( $norm_img, '/' ) . '/profile.webp';
				}
				if ( 0 !== stripos( $norm_img, 'images/' ) ) {
					$norm_img = 'images/' . ltrim( $norm_img, '/' );
				}
				$item['bundle_featured_relative'] = $norm_img;
			}
		}
	}

	// Raw checkpoint-style Jalali dates.
	$issue_raw = $read( 'issue_date' );
	if ( '' !== $issue_raw && empty( $item['hvl_license_issued_jalali'] ) ) {
		$item['hvl_license_issued_jalali'] = $issue_raw;
	}
	$exp_raw = $read( 'validity_date' );
	if ( '' !== $exp_raw && empty( $item['hvl_license_expires_jalali'] ) ) {
		$item['hvl_license_expires_jalali'] = $exp_raw;
	}

	// Keep external_id fallback stable for checkpoint rows.
	if ( empty( $item['external_id'] ) ) {
		$pid = $read( 'public_lawyer_id' );
		if ( '' !== $pid ) {
			$item['external_id'] = 'hub23055-' . $pid;
		}
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
	$limit       = max( 1, min( 2000, (int) $limit ) );

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

	if ( isset( $_POST['hovalvakil_csv_bg_stop'] ) && check_admin_referer( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' ) ) {
		$job = hovalvakil_csv_bg_job_get();
		if ( ! empty( $job ) ) {
			$job['active'] = false;
			$job['finished_at'] = time();
			$job['last_error'] = 'متوقف شد توسط کاربر.';
			hovalvakil_csv_bg_job_set( $job );
		}
		add_settings_error( 'hovalvakil_csv', 'bg_stop', 'ایمپورت پس‌زمینه متوقف شد.', 'success' );
	}

	$backfill_limit  = isset( $_POST['hvl_backfill_limit'] ) ? max( 1, min( 5000, absint( $_POST['hvl_backfill_limit'] ) ) ) : 800;
	$backfill_offset = isset( $_POST['hvl_backfill_offset'] ) ? max( 0, absint( $_POST['hvl_backfill_offset'] ) ) : 0;
	$backfill_dry    = ! empty( $_POST['hvl_backfill_dry_run'] );
	$quick5000_cursor = (int) get_option( hovalvakil_csv_quick5000_cursor_option_key(), 0 );
	if ( $quick5000_cursor < 0 ) {
		$quick5000_cursor = 0;
	}
	if ( isset( $_POST['hvl_backfill_city_province'] ) && check_admin_referer( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' ) ) {
		if ( ! function_exists( 'hovalvakil_backfill_lawyer_city_province_terms' ) ) {
			add_settings_error( 'hovalvakil_csv', 'bf_missing', 'تابع backfill بارگذاری نشد.', 'error' );
		} else {
			if ( function_exists( 'wp_raise_memory_limit' ) ) {
				wp_raise_memory_limit( 'admin' );
			}
			@set_time_limit( 240 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$out = hovalvakil_backfill_lawyer_city_province_terms(
				[
					'limit'   => $backfill_limit,
					'offset'  => $backfill_offset,
					'dry_run' => $backfill_dry,
				]
			);
			$next_offset = $backfill_offset + (int) ( $out['processed'] ?? 0 );
			$msg         = sprintf(
				'Backfill شهر/استان انجام شد. پردازش: %1$d، به‌روزرسانی: %2$d، بدون نتیجه/خطا: %3$d، offset بعدی: %4$d',
				(int) ( $out['processed'] ?? 0 ),
				(int) ( $out['updated'] ?? 0 ),
				(int) ( $out['errors'] ?? 0 ),
				$next_offset
			);
			$backfill_offset = $next_offset;
			add_settings_error( 'hovalvakil_csv', 'bf_done', $msg, $backfill_dry ? 'warning' : 'success' );
		}
	}
	if ( isset( $_POST['hovalvakil_quick5000_reset_cursor'] ) && check_admin_referer( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' ) ) {
		$quick5000_cursor = 0;
		update_option( hovalvakil_csv_quick5000_cursor_option_key(), 0, false );
		add_settings_error( 'hovalvakil_csv', 'q5_reset', 'کرسر ایمپورت ۵۰۰۰تایی ریست شد.', 'success' );
	}
	if ( isset( $_POST['hovalvakil_quick5000_run'] ) && check_admin_referer( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' ) ) {
		$source = isset( $_POST['csv_source'] ) ? sanitize_key( (string) wp_unslash( $_POST['csv_source'] ) ) : 'path';
		$csv_abs = null;
		if ( 'upload' === $source ) {
			if ( $staging_csv ) {
				$csv_abs = $staging_csv;
			} else {
				add_settings_error( 'hovalvakil_csv', 'q5_no_up', 'برای اجرای ۵۰۰۰تایی، ابتدا CSV آپلودی را انتخاب کنید یا منبع مسیر را بگذارید.', 'error' );
			}
		} else {
			$rel = isset( $_POST['csv_content_relpath'] ) ? (string) wp_unslash( $_POST['csv_content_relpath'] ) : 'hovalvakil-import/lawyers.csv';
			$rel = trim( $rel );
			if ( '' === $rel ) {
				$rel = 'hovalvakil-import/lawyers.csv';
			}
			$res = hovalvakil_import_csv_resolve_content_file( $rel );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'hovalvakil_csv', 'q5_path', $res->get_error_message(), 'error' );
			} else {
				$csv_abs = $res;
			}
		}

		$bundle_root = '';
		$bundle_err  = false;
		if ( ! empty( $_POST['csv_bundle_subdir'] ) ) {
			$bs = hovalvakil_import_bundle_resolve_content_subdir( (string) wp_unslash( $_POST['csv_bundle_subdir'] ) );
			if ( is_wp_error( $bs ) ) {
				add_settings_error( 'hovalvakil_csv', 'q5_bundle', $bs->get_error_message(), 'error' );
				$bundle_err = true;
			} else {
				$bundle_root = $bs;
			}
		}
		if ( '' === $bundle_root && null !== $csv_abs ) {
			$maybe = dirname( (string) $csv_abs );
			if ( is_dir( $maybe ) ) {
				$bundle_root = $maybe;
			}
		}

		if ( null !== $csv_abs && ! $bundle_err && ( '' === $bundle_root || is_dir( $bundle_root ) ) ) {
			if ( function_exists( 'wp_raise_memory_limit' ) ) {
				wp_raise_memory_limit( 'admin' );
			}
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			$delimiter    = isset( $_POST['csv_delimiter'] ) ? (string) wp_unslash( $_POST['csv_delimiter'] ) : ',';
			$delimiter    = '' === $delimiter ? ',' : $delimiter[0];
			$sideload_url = ! empty( $_POST['csv_sideload_url'] );
			$target_new   = 5000;
			$chunk_limit  = 1000;
			$max_loops    = 120;
			$loops        = 0;
			$cursor       = $quick5000_cursor;
			$total_new    = 0;
			$total_upd    = 0;
			$total_proc   = 0;
			$total_err    = 0;
			$done         = false;

			while ( $loops < $max_loops ) {
				$loops++;
				$one = hovalvakil_import_csv_run_chunk(
					$csv_abs,
					$bundle_root,
					$delimiter,
					$cursor,
					$chunk_limit,
					false,
					$sideload_url
				);
				if ( empty( $one['ok'] ) ) {
					add_settings_error( 'hovalvakil_csv', 'q5_run_err', (string) ( $one['message'] ?? 'خطا در ایمپورت ۵۰۰۰تایی.' ), 'error' );
					break;
				}
				$created_n  = count( (array) ( $one['created'] ?? [] ) );
				$updated_n  = count( (array) ( $one['updated'] ?? [] ) );
				$processed  = (int) ( $one['processed'] ?? 0 );
				$total_new += $created_n;
				$total_upd += $updated_n;
				$total_proc += $processed;
				$total_err += count( (array) ( $one['errors'] ?? [] ) );
				$cursor = (int) ( $one['next_offset'] ?? $cursor );
				if ( ! empty( $one['done'] ) || $processed <= 0 ) {
					$done = true;
					break;
				}
				if ( $total_new >= $target_new ) {
					break;
				}
			}

			$quick5000_cursor = max( 0, $cursor );
			update_option( hovalvakil_csv_quick5000_cursor_option_key(), $quick5000_cursor, false );
			$msg = sprintf(
				'ایمپورت ۵۰۰۰تایی اجرا شد. ایجاد جدید: %1$d | به‌روزرسانی: %2$d | پردازش: %3$d | خطا/هشدار: %4$d | کرسر جدید: %5$d',
				(int) $total_new,
				(int) $total_upd,
				(int) $total_proc,
				(int) $total_err,
				(int) $quick5000_cursor
			);
			if ( $done ) {
				$msg .= ' | فایل CSV به انتها رسید.';
			} elseif ( $total_new < $target_new ) {
				$msg .= ' | به سقف زمان/حلقه رسید؛ دوباره کلیک کنید تا ادامه دهد.';
			}
			add_settings_error( 'hovalvakil_csv', 'q5_ok', $msg, 'success' );
		}
	}

	$last_result = null;
	$auto_chunks_done = 0;
	$bg_job_started = false;
	if ( isset( $_POST['hovalvakil_csv_run'] ) && check_admin_referer( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' ) ) {
		$dry_run       = ! empty( $_POST['csv_dry_run'] );
		$sideload_url  = ! empty( $_POST['csv_sideload_url'] );
		$data_offset   = isset( $_POST['csv_offset'] ) ? absint( $_POST['csv_offset'] ) : 0;
		$row_limit     = isset( $_POST['csv_limit'] ) ? absint( $_POST['csv_limit'] ) : 1000;
		$row_limit     = max( 1, min( 2000, $row_limit ) );
		$auto_run      = ! empty( $_POST['csv_auto_run'] );
		$bg_run        = ! empty( $_POST['csv_background_run'] );
		$auto_max_runs = isset( $_POST['csv_auto_max_runs'] ) ? absint( $_POST['csv_auto_max_runs'] ) : 20;
		$auto_max_runs = max( 1, min( 500, $auto_max_runs ) );
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
		// If omitted, auto-detect bundle root from CSV location (same directory as lawyers.csv).
		if ( '' === $bundle_root && null !== $csv_abs ) {
			$maybe = dirname( (string) $csv_abs );
			if ( is_dir( $maybe ) ) {
				$bundle_root = $maybe;
			}
		}

		if ( null !== $csv_abs && ! $bundle_err && ( '' === $bundle_root || is_dir( $bundle_root ) ) ) {
			if ( $bg_run && ! $dry_run ) {
				$job = [
					'active'          => true,
					'started_at'      => time(),
					'last_tick_at'    => 0,
					'finished_at'     => 0,
					'csv_path'        => $csv_abs,
					'bundle_root'     => $bundle_root,
					'delimiter'       => $delimiter,
					'offset'          => $data_offset,
					'limit'           => $row_limit,
					'dry_run'         => false,
					'sideload_url'    => $sideload_url,
					'processed_total' => 0,
					'created_total'   => 0,
					'updated_total'   => 0,
					'error_total'     => 0,
					'last_error'      => '',
				];
				hovalvakil_csv_bg_job_set( $job );
				if ( ! wp_next_scheduled( 'hovalvakil_csv_bg_worker' ) ) {
					wp_schedule_single_event( time() + 1, 'hovalvakil_csv_bg_worker' );
				}
				$bg_ticks = hovalvakil_csv_bg_run_burst( 300.0 );
				hovalvakil_csv_bg_spawn_wp_cron();
				$bg_job_started = true;
			}
			if ( $bg_job_started ) {
				$job_now = hovalvakil_csv_bg_job_get();
				$msg     = sprintf(
					/* translators: 1: burst tick count, 2: processed rows, 3: created, 4: updated */
					'ایمپورت پس‌زمینه شروع شد؛ همین حالا %1$d مرحله پردازش شد (جمع پردازش‌شده: %2$d، ایجاد: %3$d، به‌روز: %4$d). اگر هنوز تمام نشد، هر چند دقیقه همین صفحهٔ ابزارها را باز کنید یا cron سرور را برای wp-cron فعال کنید.',
					(int) $bg_ticks,
					(int) ( $job_now['processed_total'] ?? 0 ),
					(int) ( $job_now['created_total'] ?? 0 ),
					(int) ( $job_now['updated_total'] ?? 0 )
				);
				if ( ! empty( $job_now['last_error'] ) ) {
					$msg .= ' آخرین خطا: ' . $job_now['last_error'];
				}
				add_settings_error( 'hovalvakil_csv', 'bg_start', $msg, 'success' );
			} else {
				if ( function_exists( 'wp_raise_memory_limit' ) ) {
					wp_raise_memory_limit( 'admin' );
				}
				@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$start_offset = $data_offset;
			$run_count    = 0;
			$cur_offset   = $data_offset;
			$agg          = [
				'ok'          => true,
				'offset'      => $start_offset,
				'processed'   => 0,
				'next_offset' => $start_offset,
				'done'        => false,
				'created'     => [],
				'updated'     => [],
				'errors'      => [],
			];

			while ( true ) {
				$one = hovalvakil_import_csv_run_chunk(
					$csv_abs,
					$bundle_root,
					$delimiter,
					$cur_offset,
					$row_limit,
					$dry_run,
					$sideload_url
				);
				if ( empty( $one['ok'] ) ) {
					$last_result = $one;
					break;
				}
				$run_count++;
				$agg['processed']   += (int) $one['processed'];
				$agg['next_offset'] = (int) $one['next_offset'];
				$agg['done']        = ! empty( $one['done'] );
				$agg['created']     = array_merge( $agg['created'], (array) ( $one['created'] ?? [] ) );
				$agg['updated']     = array_merge( $agg['updated'], (array) ( $one['updated'] ?? [] ) );
				$agg['errors']      = array_merge( $agg['errors'], (array) ( $one['errors'] ?? [] ) );
				if ( count( $agg['errors'] ) > 500 ) {
					$agg['errors'] = array_slice( $agg['errors'], -500 );
				}

				$cur_offset = (int) $one['next_offset'];
				if ( ! $auto_run || $dry_run || $agg['done'] || (int) $one['processed'] <= 0 || $run_count >= $auto_max_runs ) {
					break;
				}
			}
			$last_result       = $last_result ?: $agg;
			$auto_chunks_done  = $run_count;
			if ( empty( $last_result['ok'] ) ) {
				add_settings_error( 'hovalvakil_csv', 'run', $last_result['message'] ?? 'خطا', 'error' );
			} else {
				$msg = sprintf(
					/* translators: 1: rows processed, 2: start offset, 3: chunks */
					'پردازش شد: %1$d سطر (از offset=%2$d) در %3$d اجرا.',
					(int) $last_result['processed'],
					(int) $last_result['offset'],
					$auto_chunks_done
				);
				if ( ! empty( $auto_run ) && empty( $last_result['done'] ) && ! $dry_run ) {
					$msg .= ' به سقف اجرای خودکار رسید؛ دوباره اجرا کنید.';
				}
				add_settings_error( 'hovalvakil_csv', 'run_ok', $msg, 'success' );
			}
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
	$form_relpath     = isset( $_POST['csv_content_relpath'] ) ? (string) wp_unslash( $_POST['csv_content_relpath'] ) : 'hovalvakil-import/lawyers.csv';
	$form_bundle_sub  = isset( $_POST['csv_bundle_subdir'] ) ? (string) wp_unslash( $_POST['csv_bundle_subdir'] ) : '';
	$form_delimiter   = isset( $_POST['csv_delimiter'] ) ? (string) wp_unslash( $_POST['csv_delimiter'] ) : ',';
	$form_limit       = isset( $_POST['csv_limit'] ) ? absint( $_POST['csv_limit'] ) : 1000;
	$form_auto_run    = ! empty( $_POST['csv_auto_run'] );
	$form_auto_max    = isset( $_POST['csv_auto_max_runs'] ) ? absint( $_POST['csv_auto_max_runs'] ) : 20;
	$form_bg_run      = ! empty( $_POST['csv_background_run'] );
	$bg_job           = hovalvakil_csv_bg_job_get();

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'ایمپورت CSV وکلا', 'hello-elementor' ) . '</h1>';
	echo '<p style="max-width: 52rem;">';
	echo esc_html__(
		'فایل UTF-8 با سطر اول سرستون. ستون‌های مجاز همان فیلدهای ایمپورت دسته‌ای است (title، external_id، city، province، specialties، bundle_featured_relative، hvl_*). برای تصویر محلی، پوشهٔ بسته (همان manifest) را در «زیرپوشهٔ تصاویر» بدهید.',
		'hello-elementor'
	);
	echo '</p>';

	settings_errors( 'hovalvakil_csv' );

	echo '<div class="notice notice-info" style="max-width:52rem;padding:12px 14px;"><p style="margin:0 0 10px;"><strong>' . esc_html__( 'لپ‌تاپ را می‌خواهید جمع کنید؟', 'hello-elementor' ) . '</strong> ';
	echo esc_html__( 'ایمپورت «در همین تب مرورگر» فقط وقتی کار می‌کند که همان تب باز و لپ‌تاپ روشن باشد. برای ادامهٔ خودکار روی سرور، حتماً گزینهٔ «ایمپورت پس‌زمینه» را بزنید؛ وردپرس هر ~۵ دقیقه یک ضربان Cron ثبت می‌کند تا با هر بازدید از سایت (یا ربات‌ها) یا Cron واقعی هاست، کار جلو برود.', 'hello-elementor' );
	echo '</p><p style="margin:0 0 10px;">';
	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		echo '<strong>' . esc_html__( 'توجه:', 'hello-elementor' ) . '</strong> ';
		echo esc_html__( 'روی این سایت DISABLE_WP_CRON فعال است؛ باید در cPanel → Cron Jobs هر ۱ تا ۵ دقیقه آدرس wp-cron.php را صدا بزنید، مثلاً:', 'hello-elementor' );
		echo ' <code style="word-break:break-all;">wget -q -O /dev/null ' . esc_html( site_url( 'wp-cron.php?doing_wp_cron=1' ) ) . '</code>';
	} else {
		echo esc_html__( 'اگر سایت شما خیلی کم بازدید دارد، همان یک خط Cron در هاست (wget به wp-cron.php) اطمینان را بیشتر می‌کند.', 'hello-elementor' );
	}
	echo '</p><p style="margin:0;">' . esc_html__( 'تایمرهای داخل صفحه فقط برای هماهنگی درخواست‌ها هستند؛ پردازش واقعی روی سرور انجام می‌شود.', 'hello-elementor' ) . '</p></div>';

	echo '<h2>' . esc_html__( 'Backfill شهر/استان برای وکلای قبلی (بدون WP-CLI)', 'hello-elementor' ) . '</h2>';
	echo '<p class="description" style="max-width:52rem;">' . esc_html__( 'وقتی داده‌های قدیمی term شهر/استان ندارند، از آدرس و لوکیشن خود وکیل‌ها حدس زده و روی taxonomy ذخیره می‌کند. امن است: فقط موارد ناقص را تکمیل می‌کند.', 'hello-elementor' ) . '</p>';
	echo '<form method="post" style="max-width:52rem;margin:0 0 1.75rem;">';
	wp_nonce_field( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' );
	echo '<p><label for="hvl_backfill_limit" style="display:inline-block;min-width:170px;">' . esc_html__( 'تعداد در هر اجرا', 'hello-elementor' ) . '</label> ';
	echo '<input type="number" id="hvl_backfill_limit" name="hvl_backfill_limit" min="1" max="5000" value="' . esc_attr( (string) $backfill_limit ) . '" style="width:120px;"></p>';
	echo '<p><label for="hvl_backfill_offset" style="display:inline-block;min-width:170px;">' . esc_html__( 'offset شروع', 'hello-elementor' ) . '</label> ';
	echo '<input type="number" id="hvl_backfill_offset" name="hvl_backfill_offset" min="0" value="' . esc_attr( (string) $backfill_offset ) . '" style="width:120px;"></p>';
	echo '<p><label><input type="checkbox" name="hvl_backfill_dry_run" value="1" ' . checked( $backfill_dry, true, false ) . '> ' . esc_html__( 'Dry-run (فقط گزارش، بدون ذخیره)', 'hello-elementor' ) . '</label></p>';
	echo '<p><button type="submit" name="hvl_backfill_city_province" class="button button-primary">' . esc_html__( 'اجرای Backfill شهر/استان', 'hello-elementor' ) . '</button></p>';
	echo '</form>';

	echo '<h2>' . esc_html__( 'ایمپورت سریع ۵۰۰۰ وکیلِ غیرتکراری در هر کلیک', 'hello-elementor' ) . '</h2>';
	echo '<p class="description" style="max-width:52rem;">' . esc_html__( 'این دکمه از کرسر داخلی استفاده می‌کند و هر بار تا سقف ۵۰۰۰ رکورد جدید (غیرتکراری) ایجاد می‌کند. موارد تکراری به‌جای ایجاد، به‌روزرسانی می‌شوند. برای ادامه، دوباره همین دکمه را بزنید.', 'hello-elementor' ) . '</p>';
	echo '<form method="post" style="max-width:52rem;margin:0 0 1.75rem;">';
	wp_nonce_field( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' );
	echo '<p><strong>' . esc_html__( 'کرسر فعلی ایمپورت ۵۰۰۰تایی:', 'hello-elementor' ) . '</strong> <code>' . esc_html( (string) $quick5000_cursor ) . '</code></p>';
	echo '<p><button type="submit" name="hovalvakil_quick5000_run" class="button button-primary">' . esc_html__( 'شروع ایمپورت ۵۰۰۰تایی', 'hello-elementor' ) . '</button> ';
	echo '<button type="submit" name="hovalvakil_quick5000_reset_cursor" class="button" style="margin-inline-start:8px;">' . esc_html__( 'ریست کرسر ۵۰۰۰تایی', 'hello-elementor' ) . '</button></p>';
	echo '</form>';

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
	echo '<form method="post" id="hovalvakil-csv-import-form">';
	wp_nonce_field( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' );

	echo '<table class="form-table"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'منبع CSV', 'hello-elementor' ) . '</th><td>';
	echo '<label><input type="radio" name="csv_source" value="path" ' . checked( 'path' === $form_source, true, false ) . '> ' . esc_html__( 'مسیر زیر wp-content', 'hello-elementor' ) . '</label><br>';
	echo '<label><input type="radio" name="csv_source" value="upload" ' . ( $staging_csv ? '' : 'disabled="disabled" ' ) . checked( 'upload' === $form_source, true, false ) . '> ' . esc_html__( 'فایل آپلودی موقت', 'hello-elementor' ) . '</label>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_content_relpath">' . esc_html__( 'مسیر نسبت به wp-content', 'hello-elementor' ) . '</label></th><td>';
	echo '<input type="text" class="large-text" name="csv_content_relpath" id="csv_content_relpath" value="' . esc_attr( $form_relpath ) . '">';
	echo '<p class="description">' . esc_html__( 'مثال: hovalvakil-import/lawyers.csv', 'hello-elementor' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_bundle_subdir">' . esc_html__( 'زیرپوشهٔ تصاویر (اختیاری)', 'hello-elementor' ) . '</label></th><td>';
	echo '<input type="text" class="regular-text" name="csv_bundle_subdir" id="csv_bundle_subdir" value="' . esc_attr( $form_bundle_sub ) . '" placeholder="hovalvakil-import">';
	echo '<p class="description">' . esc_html__( 'اگر bundle_featured_relative دارید، همان پوشه‌ای که images/ داخلش است (معمولاً hovalvakil-import). خالی = فقط URL/پیوست.', 'hello-elementor' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_delimiter">' . esc_html__( 'جداکننده', 'hello-elementor' ) . '</label></th><td>';
	echo '<input type="text" name="csv_delimiter" id="csv_delimiter" maxlength="3" value="' . esc_attr( $form_delimiter ) . '" style="width:4rem;">';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_offset">' . esc_html__( 'offset (سطر داده، بدون سرستون)', 'hello-elementor' ) . '</label></th><td>';
	$next_off = ( is_array( $last_result ) && ! empty( $last_result['ok'] ) && empty( $last_result['done'] ) ) ? (int) $last_result['next_offset'] : 0;
	echo '<input type="number" name="csv_offset" id="csv_offset" min="0" value="' . esc_attr( (string) $next_off ) . '">';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_limit">' . esc_html__( 'سطر در هر اجرا', 'hello-elementor' ) . '</label></th><td>';
	echo '<input type="number" name="csv_limit" id="csv_limit" min="1" max="2000" value="' . esc_attr( (string) max( 1, min( 2000, $form_limit ) ) ) . '">';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="csv_auto_max_runs">' . esc_html__( 'اجرای خودکار پشت‌سرهم', 'hello-elementor' ) . '</label></th><td>';
	echo '<label><input type="checkbox" name="csv_auto_run" value="1" ' . checked( $form_auto_run, true, false ) . '> ' . esc_html__( 'با یک کلیک چند بچ پشت‌سرهم اجرا شود', 'hello-elementor' ) . '</label><br>';
	echo '<input type="number" name="csv_auto_max_runs" id="csv_auto_max_runs" min="1" max="500" value="' . esc_attr( (string) $form_auto_max ) . '">';
	echo '<p class="description">' . esc_html__( 'حداکثر تعداد اجرا در یک درخواست (مثلاً 30). اگر کامل نشد دوباره اجرا کنید.', 'hello-elementor' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'ایمپورت پس‌زمینه (بدون سقف)', 'hello-elementor' ) . '</th><td>';
	echo '<label><input type="checkbox" name="csv_background_run" value="1" ' . checked( $form_bg_run, true, false ) . '> ' . esc_html__( 'ایمپورت پس‌زمینه روی سرور (برای جمع کردن لپ‌تاپ)', 'hello-elementor' ) . '</label>';
	echo '<p class="description">' . esc_html__( 'کار روی هاست ادامه می‌یابد؛ هر ~۵ دقیقه زمان‌بندی ضربان فعال می‌شود. بازدید از سایت یا Cron هاست wp-cron را اجرا کند کافی است. تب مرورگر را لازم نیست باز نگه دارید.', 'hello-elementor' ) . '</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'گزینه‌ها', 'hello-elementor' ) . '</th><td>';
	echo '<label><input type="checkbox" name="csv_dry_run" value="1"> ' . esc_html__( 'فقط پیش‌نمایش', 'hello-elementor' ) . '</label><br>';
	echo '<label><input type="checkbox" name="csv_sideload_url" value="1"> ' . esc_html__( 'سایدلود featured_image_url از سرور', 'hello-elementor' ) . '</label>';
	echo '</td></tr>';
	echo '</tbody></table>';
	echo '<p><button type="submit" name="hovalvakil_csv_run" class="button button-primary">' . esc_html__( 'اجرای این تکه', 'hello-elementor' ) . '</button></p>';
	echo '</form>';

	$csv_browser_cfg = wp_json_encode(
		[
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hovalvakil_csv_ajax' ),
			'msg'     => [
				'stopping' => __( 'در حال توقف بعد از این تکه…', 'hello-elementor' ),
				'stopped'  => __( 'متوقف شد (دکمهٔ توقف).', 'hello-elementor' ),
				'done'     => __( 'تمام شد.', 'hello-elementor' ),
				'jsonErr'  => __( 'پاسخ سرور نامعتبر بود.', 'hello-elementor' ),
				'maxSteps' => __( 'به سقف امنیت تعداد مرحله رسید؛ offset در فرم به‌روز است — در صورت نیاز دوباره بزنید.', 'hello-elementor' ),
			],
			'bg'      => [
				'active'         => ! empty( $bg_job['active'] ),
				'burstSeconds'   => 150,
				'autoBurstMs'    => 16000,
				'burstRunning'   => __( 'در حال پردازش روی سرور…', 'hello-elementor' ),
				'burstDone'      => __( 'راند پردازش تمام شد.', 'hello-elementor' ),
				'burstErr'       => __( 'خطا در پردازش پس‌زمینه.', 'hello-elementor' ),
			],
		],
		JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);

	echo '<h2 id="hovalvakil-csv-browser">' . esc_html__( 'ایمپورت خودکار در همین تب (بدون SSH / WP-CLI)', 'hello-elementor' ) . '</h2>';
	echo '<p class="description" style="max-width:52rem;">' . esc_html__( 'همان مقادیر فرم بالا را می‌خواند. با زدن دکمه، مرورگر تکه‌تکه درخواست می‌زند تا CSV تمام شود. این تب را باز نگه دارید. اگر میزبان زمان را قطع کرد، با همان offset ذخیره‌شده در فرم دوباره «شروع» کنید.', 'hello-elementor' ) . '</p>';
	echo '<p><button type="button" class="button button-secondary" id="hovalvakil-csv-browser-run">' . esc_html__( 'شروع ایمپورت خودکار تا آخر CSV', 'hello-elementor' ) . '</button> ';
	echo '<button type="button" class="button" id="hovalvakil-csv-browser-stop" disabled style="margin-inline-start:6px;">' . esc_html__( 'توقف', 'hello-elementor' ) . '</button></p>';
	echo '<p id="hovalvakil-csv-browser-status" aria-live="polite" style="font-weight:600;"></p>';
	echo '<pre id="hovalvakil-csv-browser-log" style="max-height:240px;overflow:auto;background:#f6f7f7;padding:10px;border:1px solid #c3c4c7;font-size:12px;direction:ltr;text-align:left;"></pre>';
	echo '<script type="text/javascript">';
	echo '(function(){var cfg=' . $csv_browser_cfg . ';var form=document.getElementById("hovalvakil-csv-import-form");';
	echo 'if(!form||!cfg||!cfg.ajaxUrl)return;var runBtn=document.getElementById("hovalvakil-csv-browser-run");';
	echo 'var stopBtn=document.getElementById("hovalvakil-csv-browser-stop");var statusEl=document.getElementById("hovalvakil-csv-browser-status");';
	echo 'var logEl=document.getElementById("hovalvakil-csv-browser-log");var stopped=false;var M=cfg.msg||{};';
	echo 'function logLine(o){if(logEl){logEl.textContent+=(typeof o==="string"?o:JSON.stringify(o))+"\n";logEl.scrollTop=logEl.scrollHeight;}}';
	echo 'stopBtn.addEventListener("click",function(){stopped=true;if(statusEl)statusEl.textContent=M.stopping||"";});';
	echo 'runBtn.addEventListener("click",function(){';
	echo 'stopped=false;runBtn.disabled=true;stopBtn.disabled=false;if(logEl)logEl.textContent="";';
	echo 'var totalC=0,totalU=0,step=0,maxSteps=20000;var offEl=form.querySelector("[name=csv_offset]");';
	echo 'var offset=parseInt(offEl&&offEl.value,10)||0;if(isNaN(offset))offset=0;';
	echo 'async function tick(){';
	echo 'var data=new FormData();data.append("action","hovalvakil_csv_import_chunk");data.append("nonce",cfg.nonce);';
	echo 'var srcEl=form.querySelector("input[name=csv_source]:checked");data.append("csv_source",srcEl?srcEl.value:"path");';
	echo 'data.append("csv_content_relpath",(form.querySelector("[name=csv_content_relpath]")||{value:""}).value||"");';
	echo 'data.append("csv_bundle_subdir",(form.querySelector("[name=csv_bundle_subdir]")||{value:""}).value||"");';
	echo 'data.append("delimiter",(form.querySelector("[name=csv_delimiter]")||{value:","}).value||",");';
	echo 'data.append("limit",(form.querySelector("[name=csv_limit]")||{value:"100"}).value||"100");';
	echo 'data.append("offset",String(offset));';
	echo 'var dr=form.querySelector("[name=csv_dry_run]");if(dr&&dr.checked)data.append("dry_run","1");';
	echo 'var sl=form.querySelector("[name=csv_sideload_url]");if(sl&&sl.checked)data.append("sideload_url","1");';
	echo 'var res=await fetch(cfg.ajaxUrl,{method:"POST",credentials:"same-origin",body:data});';
	echo 'var js;try{js=await res.json();}catch(e){logLine(M.jsonErr||String(e));return{fatal:true};}';
	echo 'if(!js||!js.success){var m=(js&&js.data&&js.data.message)?js.data.message:("HTTP "+res.status);logLine(m);if(statusEl)statusEl.textContent=m;return{fatal:true};}';
	echo 'return{fatal:false,data:js.data||{}};}';
	echo '(async function(){';
	echo 'while(!stopped&&step<maxSteps){step++;var r=await tick();if(r.fatal)break;var d=r.data||{};';
	echo 'totalC+=(d.created||[]).length;totalU+=(d.updated||[]).length;';
	echo 'if(statusEl)statusEl.textContent="مرحله "+step+" — offset بعدی "+(d.next_offset||0)+" — این تکه: "+(d.processed||0);';
	echo 'logLine({step:step,offsetBefore:offset,next:d.next_offset,processed:d.processed,done:!!d.done,c:(d.created||[]).length,u:(d.updated||[]).length,e:(d.errors||[]).length});';
	echo 'offset=d.next_offset||0;if(offEl)offEl.value=String(offset);';
	echo 'if(d.done){if(statusEl)statusEl.textContent=(M.done||"")+" — ایجاد: "+totalC+"، به‌روز: "+totalU;logLine(M.done);break;}';
	echo 'if(!(d.processed>0)){logLine("no rows");break;}';
	echo 'await new Promise(function(x){setTimeout(x,40)});}';
	echo 'if(step>=maxSteps){logLine(M.maxSteps||"max");if(statusEl)statusEl.textContent=M.maxSteps||"";}';
	echo 'if(stopped&&statusEl)statusEl.textContent=M.stopped||"";';
	echo 'runBtn.disabled=false;stopBtn.disabled=true;})().catch(function(e){logLine(String(e));runBtn.disabled=false;stopBtn.disabled=true;});});})();</script>';

	if ( ! empty( $bg_job ) ) {
		echo '<h3>' . esc_html__( 'وضعیت ایمپورت پس‌زمینه', 'hello-elementor' ) . '</h3>';
		echo '<ul id="hovalvakil-bg-job-stats" style="list-style:disc;padding-inline-start:1.25rem;">';
		echo '<li id="hovalvakil-bg-stat-active">' . esc_html( 'active: ' . ( ! empty( $bg_job['active'] ) ? 'yes' : 'no' ) ) . '</li>';
		echo '<li id="hovalvakil-bg-stat-offset">' . esc_html( 'offset: ' . (int) ( $bg_job['offset'] ?? 0 ) ) . '</li>';
		echo '<li id="hovalvakil-bg-stat-processed">' . esc_html( 'processed_total: ' . (int) ( $bg_job['processed_total'] ?? 0 ) ) . '</li>';
		echo '<li id="hovalvakil-bg-stat-created">' . esc_html( 'created_total: ' . (int) ( $bg_job['created_total'] ?? 0 ) ) . '</li>';
		echo '<li id="hovalvakil-bg-stat-updated">' . esc_html( 'updated_total: ' . (int) ( $bg_job['updated_total'] ?? 0 ) ) . '</li>';
		echo '<li id="hovalvakil-bg-stat-errors">' . esc_html( 'error_total: ' . (int) ( $bg_job['error_total'] ?? 0 ) ) . '</li>';
		$lta = ! empty( $bg_job['last_tick_at'] ) ? (int) $bg_job['last_tick_at'] : 0;
		echo '<li id="hovalvakil-bg-stat-tick">' . esc_html( 'last_tick_at: ' . ( $lta ? gmdate( 'Y-m-d H:i:s', $lta ) . ' UTC' : '—' ) ) . '</li>';
		if ( ! empty( $bg_job['last_error'] ) ) {
			echo '<li id="hovalvakil-bg-stat-lasterr">' . esc_html( 'last_error: ' . (string) $bg_job['last_error'] ) . '</li>';
		} else {
			echo '<li id="hovalvakil-bg-stat-lasterr" style="display:none;"></li>';
		}
		echo '</ul>';
		if ( ! empty( $bg_job['active'] ) ) {
			echo '<p id="hovalvakil-bg-burst-status" style="font-weight:600;margin:0.5rem 0;"></p>';
			echo '<p><button type="button" class="button button-primary" id="hovalvakil-bg-burst-btn">' . esc_html__( 'پردازش فوری روی سرور (~۱۵۰ ثانیه)', 'hello-elementor' ) . '</button> ';
			echo '<label style="margin-inline-start:8px;"><input type="checkbox" id="hovalvakil-bg-auto-burst"> ' . esc_html__( 'هر ~۱۶ ثانیه خودکار همان کار را تکرار کن (این تب باز بماند)', 'hello-elementor' ) . '</label></p>';
			echo '<form method="post" style="margin-top:0.75rem;">';
			wp_nonce_field( 'hovalvakil_csv_import_action', 'hovalvakil_csv_import_nonce' );
			echo '<button type="submit" name="hovalvakil_csv_bg_stop" class="button">' . esc_html__( 'توقف ایمپورت پس‌زمینه', 'hello-elementor' ) . '</button>';
			echo '</form>';
			echo '<script type="text/javascript">';
			echo '(function(){var cfg=' . $csv_browser_cfg . ';var B=cfg.bg||{};if(!cfg.ajaxUrl||!B.active)return;';
			echo 'var btn=document.getElementById("hovalvakil-bg-burst-btn");var st=document.getElementById("hovalvakil-bg-burst-status");';
			echo 'var auto=document.getElementById("hovalvakil-bg-auto-burst");var timer=null;';
			echo 'function setLi(id,t){var e=document.getElementById(id);if(e)e.textContent=t;}';
			echo 'function applyJob(j){if(!j)return;setLi("hovalvakil-bg-stat-active","active: "+(j.active?"yes":"no"));';
			echo 'setLi("hovalvakil-bg-stat-offset","offset: "+(j.offset||0));setLi("hovalvakil-bg-stat-processed","processed_total: "+(j.processed_total||0));';
			echo 'setLi("hovalvakil-bg-stat-created","created_total: "+(j.created_total||0));setLi("hovalvakil-bg-stat-updated","updated_total: "+(j.updated_total||0));';
			echo 'setLi("hovalvakil-bg-stat-errors","error_total: "+(j.error_total||0));';
			echo 'var lt=j.last_tick_at||0;setLi("hovalvakil-bg-stat-tick","last_tick_at: "+(lt?new Date(lt*1000).toISOString().replace("T"," ").slice(0,19)+" UTC":"—"));';
			echo 'var le=document.getElementById("hovalvakil-bg-stat-lasterr");if(le){if(j.last_error){le.style.display="";le.textContent="last_error: "+j.last_error;}else{le.style.display="none";le.textContent="";}}}';
			echo 'async function burst(){if(!btn||btn.disabled)return;btn.disabled=true;if(st)st.textContent=B.burstRunning||"";';
			echo 'var fd=new FormData();fd.append("action","hovalvakil_csv_bg_burst");fd.append("nonce",cfg.nonce);fd.append("seconds",String(B.burstSeconds||150));';
			echo 'try{var res=await fetch(cfg.ajaxUrl,{method:"POST",credentials:"same-origin",body:fd});var js=await res.json();';
			echo 'if(js&&js.success&&js.data&&js.data.job){applyJob(js.data.job);if(st)st.textContent=(B.burstDone||"")+" ticks="+(js.data.ticks||0);if(!js.data.job.active&&timer){clearInterval(timer);timer=null;if(auto)auto.checked=false;}}';
			echo 'else{if(st)st.textContent=(js&&js.data&&js.data.message)?js.data.message:(B.burstErr||"error");}}catch(e){if(st)st.textContent=String(e);}';
			echo 'btn.disabled=false;}';
			echo 'if(btn)btn.addEventListener("click",function(){burst();});';
			echo 'if(auto){setInterval(function(){if(auto.checked&&btn&&!btn.disabled)burst();},B.autoBurstMs||16000);}';
			echo '})();</script>';
		}
	}

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
