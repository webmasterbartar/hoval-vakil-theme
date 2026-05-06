<?php
/**
 * Lawyer post meta + taxonomy field map for admin, templates, REST, and CSV import (hovalvakil-lawyer-csv-import.php).
 *
 * Import column names (suggested): full_name → post_title; photo_url → featured image sideload;
 * province_slug/name → hvl_province; city_slug/name → hvl_city; mobile → hvl_mobile;
 * office_mobile → hvl_office_mobile; office_phone → hvl_office_phone; office_address → hvl_office_address;
 * license_no → hvl_license_no; license_issued → hvl_license_issued (YYYY-MM-DD); license_expires → hvl_license_expires (YYYY-MM-DD);
 * lawyer_grade → hvl_lawyer_grade; license_file_url → hvl_license_file_url;
 * photo_url (بدون سایدلود) → hvl_photo_url اگر تصویر شاخص نسازید.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta keys used on hvl_lawyer (canonical list for import/sync tools).
 *
 * @return string[]
 */
function hovalvakil_lawyer_meta_key_list() {
	return [
		'hvl_photo_url',
		'hvl_import_external_id',
		'hvl_mobile',
		'hvl_office_mobile',
		'hvl_license_no',
		'hvl_license_issued',
		'hvl_license_expires',
		'hvl_license_issued_jalali',
		'hvl_license_expires_jalali',
		'hvl_lawyer_grade',
		'hvl_license_file_url',
		'hvl_experience',
		'hvl_price_from',
		'hvl_rating',
		'hvl_reviews_count',
		'hvl_license',
		'hvl_education',
		'hvl_office_phone',
		'hvl_office_working_hours',
		'hvl_office_map_image',
		'hvl_records',
		'hvl_services',
		'hvl_office_address',
	];
}

/**
 * Admin form rows: key => [ label, type, hint ].
 *
 * @return array<string, array{label:string,type:string,hint?:string}>
 */
function hovalvakil_lawyer_profile_meta_rows() {
	return [
		'hvl_section_contact'    => [ 'label' => '— تماس —', 'type' => 'section', 'hint' => '' ],
		'hvl_mobile'             => [ 'label' => 'شماره همراه وکیل', 'type' => 'text', 'hint' => 'مثال: 09123456789' ],
		'hvl_office_mobile'      => [ 'label' => 'شماره همراه دفتر', 'type' => 'text', 'hint' => '' ],
		'hvl_office_phone'       => [ 'label' => 'تلفن ثابت دفتر', 'type' => 'text', 'hint' => '' ],
		'hvl_office_address'     => [ 'label' => 'آدرس دفتر', 'type' => 'textarea', 'hint' => '' ],
		'hvl_section_license'    => [ 'label' => '— پروانه —', 'type' => 'section', 'hint' => '' ],
		'hvl_lawyer_grade'       => [ 'label' => 'پایه / سطح وکالت', 'type' => 'text', 'hint' => 'مثال: پایه یک دادگستری' ],
		'hvl_license_no'         => [ 'label' => 'شماره پروانه', 'type' => 'text', 'hint' => '' ],
		'hvl_license_issued'     => [ 'label' => 'تاریخ صدور پروانه', 'type' => 'date', 'hint' => 'قالب YYYY-MM-DD؛ برای پروانهٔ کسب / وکالت.' ],
		'hvl_license_expires'    => [ 'label' => 'تاریخ انقضای پروانه', 'type' => 'date', 'hint' => 'قالب YYYY-MM-DD برای مرتب‌سازی و درون‌ریزی؛ در سایت با تقویم محلی نمایش داده می‌شود.' ],
		'hvl_license_issued_jalali'  => [ 'label' => 'تاریخ صدور (شمسی، نمایش)', 'type' => 'text', 'hint' => 'مثال ۱۳۹۴/۱۲/۱۲؛ در صورت پر بودن در سایت به‌جای تاریخ میلادی نمایش داده می‌شود.' ],
		'hvl_license_expires_jalali' => [ 'label' => 'تاریخ انقضا (شمسی، نمایش)', 'type' => 'text', 'hint' => 'مثال ۱۴۰۵/۰۹/۳۰' ],
		'hvl_license_file_url'   => [ 'label' => 'آدرس فایل پروانه (URL)', 'type' => 'url', 'hint' => 'PDF یا تصویر روی سرور یا CDN' ],
		'hvl_license'            => [ 'label' => 'توضیح آزاد پروانه (قدیمی)', 'type' => 'text', 'hint' => 'در صورت خالی بودن شماره پروانه، برای نمایش ترکیبی استفاده می‌شود.' ],
		'hvl_section_profile'    => [ 'label' => '— پروفایل نمایشی —', 'type' => 'section', 'hint' => '' ],
		'hvl_experience'         => [ 'label' => 'سابقه و تجربه', 'type' => 'text', 'hint' => 'مثال: ۱۲ سال تجربه' ],
		'hvl_price_from'         => [ 'label' => 'حداقل هزینه / قیمت از', 'type' => 'text', 'hint' => 'متن نمایشی برای کاربر' ],
		'hvl_rating'             => [ 'label' => 'امتیاز', 'type' => 'text', 'hint' => 'مثال: ۴٫۸' ],
		'hvl_reviews_count'      => [ 'label' => 'تعداد نظرات', 'type' => 'text', 'hint' => '' ],
		'hvl_education'          => [ 'label' => 'تحصیلات', 'type' => 'text', 'hint' => '' ],
		'hvl_office_working_hours' => [ 'label' => 'ساعات کاری دفتر', 'type' => 'text', 'hint' => '' ],
		'hvl_office_map_image'   => [ 'label' => 'آدرس تصویر نقشه (URL)', 'type' => 'text', 'hint' => '' ],
		'hvl_records'            => [ 'label' => 'سوابق، عضویت و افتخارات', 'type' => 'textarea', 'hint' => 'هر خط یک مورد' ],
		'hvl_services'           => [ 'label' => 'حوزه خدمات', 'type' => 'textarea', 'hint' => 'با ویرگول جدا کنید' ],
	];
}

/**
 * Normalize license expiry to Y-m-d or empty.
 *
 * @param string $raw Raw from POST or import.
 *
 * @return string
 */
function hovalvakil_lawyer_normalize_license_expires( $raw ) {
	$raw = trim( (string) wp_unslash( $raw ) );
	if ( '' === $raw ) {
		return '';
	}
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
		$t = strtotime( $raw . ' 12:00:00' );
		return ( $t && gmdate( 'Y-m-d', $t ) === $raw ) ? $raw : '';
	}
	$ts = strtotime( $raw );
	if ( $ts ) {
		return gmdate( 'Y-m-d', $ts );
	}
	return '';
}

/**
 * Display string for stored Y-m-d (site timezone).
 *
 * @param string $ymd Y-m-d or empty.
 *
 * @return string
 */
function hovalvakil_lawyer_format_license_expires_display( $ymd ) {
	$ymd = trim( (string) $ymd );
	if ( '' === $ymd || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
		return '';
	}
	$ts = strtotime( $ymd . ' 12:00:00' );
	if ( ! $ts ) {
		return $ymd;
	}
	return date_i18n( get_option( 'date_format' ), $ts );
}

/**
 * Effective license line for cards (number + legacy text).
 *
 * @param int $post_id Post ID.
 *
 * @return string
 */
function hovalvakil_lawyer_effective_license_label( $post_id ) {
	$no = (string) get_post_meta( $post_id, 'hvl_license_no', true );
	if ( '' !== $no ) {
		return $no;
	}
	return (string) get_post_meta( $post_id, 'hvl_license', true );
}

/**
 * First province term name for a lawyer.
 *
 * @param int $post_id Post ID.
 *
 * @return string
 */
function hovalvakil_lawyer_get_province_name( $post_id ) {
	$terms = get_the_terms( (int) $post_id, 'hvl_province' );
	if ( is_array( $terms ) && ! empty( $terms ) ) {
		return (string) $terms[0]->name;
	}
	return '';
}

/**
 * Featured image URL, or hvl_photo_url from import when thumbnail not set.
 *
 * آدرس‌های نامعتبر یا نسبیِ بدون دامنه رد می‌شوند تا مرورگر آیکن «تصویر شکسته» نشان ندهد.
 *
 * @param int    $post_id Post ID.
 * @param string $size    Image size.
 * @return string Absolute URL or empty string.
 */
function hovalvakil_lawyer_profile_image_url( $post_id, $size = 'medium' ) {
	$post_id = (int) $post_id;
	$thumb   = get_the_post_thumbnail_url( $post_id, $size );
	if ( is_string( $thumb ) && '' !== $thumb ) {
		return esc_url_raw( $thumb );
	}
	$url = trim( (string) get_post_meta( $post_id, 'hvl_photo_url', true ) );
	if ( '' === $url ) {
		return '';
	}
	// Protocol-relative (//cdn.example/…).
	if ( 0 === strpos( $url, '//' ) ) {
		$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
	} elseif ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
		$url = home_url( $url );
	}
	if ( ! function_exists( 'wp_http_validate_url' ) || ! wp_http_validate_url( $url ) ) {
		return '';
	}
	return esc_url_raw( $url );
}

/**
 * Theme-hosted placeholder when no profile image (avoids external placeholder CDNs).
 *
 * @return string Absolute URL (not HTML-escaped).
 */
function hovalvakil_theme_lawyer_placeholder_url() {
	return HELLO_THEME_IMAGES_URL . 'lawyer-placeholder.svg';
}

/**
 * Attribute onerror برای تگ img: در صورت ۴۰۴ یا لینک خراب، جایگزینی با placeholder تم.
 *
 * @return string Space + onerror="…" (خالی اگر URL پلیس‌هولدر ساخته نشود).
 */
function hovalvakil_lawyer_image_onerror_placeholder_attr() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}
	$url = function_exists( 'hovalvakil_theme_lawyer_placeholder_url' )
		? hovalvakil_theme_lawyer_placeholder_url()
		: (string) get_template_directory_uri() . '/assets/images/lawyer-placeholder.svg';
	$url = esc_url_raw( $url );
	if ( '' === $url ) {
		$cached = '';
		return $cached;
	}
	$js     = 'this.onerror=null;this.src=' . wp_json_encode( $url ) . ';this.classList.add(\'hvl-img-fallback\');';
	$cached = ' onerror="' . esc_attr( $js ) . '"';
	return $cached;
}

/**
 * ASCII digits to Persian digits for short UI strings.
 *
 * @param string $value Input.
 * @return string
 */
function hovalvakil_lawyer_digits_to_fa( $value ) {
	return strtr(
		(string) $value,
		[
			'0' => '۰',
			'1' => '۱',
			'2' => '۲',
			'3' => '۳',
			'4' => '۴',
			'5' => '۵',
			'6' => '۶',
			'7' => '۷',
			'8' => '۸',
			'9' => '۹',
		]
	);
}

/**
 * Taxonomy specialty names for cards; if none, single tag from پایهٔ وکالت meta.
 *
 * @param int $post_id Post ID.
 * @return string[]
 */
function hovalvakil_lawyer_card_specialty_labels( $post_id ) {
	$post_id = (int) $post_id;
	$labels  = [];
	$terms   = get_the_terms( $post_id, 'hvl_specialty' );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $t ) {
			if ( isset( $t->name ) && '' !== $t->name ) {
				$labels[] = $t->name;
			}
		}
		$labels = array_values( array_unique( $labels ) );
		sort( $labels, SORT_STRING );
	}
	if ( ! empty( $labels ) ) {
		return $labels;
	}
	$grade = trim( (string) get_post_meta( $post_id, 'hvl_lawyer_grade', true ) );
	return '' !== $grade ? [ $grade ] : [];
}

/**
 * یک خط موقعیت برای کارت: استان و شهر از تاکسونومی؛ در غیر این صورت خلاصهٔ آدرس دفتر.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function hovalvakil_lawyer_card_location_line( $post_id ) {
	$post_id = (int) $post_id;
	$prov    = function_exists( 'hovalvakil_lawyer_get_province_name' ) ? hovalvakil_lawyer_get_province_name( $post_id ) : '';
	$city    = '';
	$ct      = get_the_terms( $post_id, 'hvl_city' );
	if ( is_array( $ct ) && ! empty( $ct ) ) {
		$city = (string) $ct[0]->name;
	}
	$line = implode( '، ', array_filter( [ $prov, $city ] ) );
	if ( '' !== $line ) {
		return $line;
	}
	$addr = trim( (string) get_post_meta( $post_id, 'hvl_office_address', true ) );
	if ( '' === $addr ) {
		return '';
	}
	return wp_trim_words( $addr, 14, '…' );
}

/**
 * خط فرعی کارت (شمارهٔ پروانه) در صورت وجود در متا.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function hovalvakil_lawyer_card_license_line( $post_id ) {
	$no = trim( (string) get_post_meta( (int) $post_id, 'hvl_license_no', true ) );
	if ( '' === $no ) {
		return '';
	}
	return 'شمارهٔ پروانه ' . hovalvakil_lawyer_digits_to_fa( $no );
}

/**
 * Guess province/city from free-text office address/location.
 *
 * @param string $raw Raw location string.
 * @return array{province:string,city:string}
 */
function hovalvakil_guess_province_city_from_text( $raw ) {
	$raw = trim( preg_replace( '/\s+/u', ' ', (string) $raw ) );
	if ( '' === $raw ) {
		return [ 'province' => '', 'city' => '' ];
	}
	$parts = preg_split( '/\s*[،,\-–—]\s*/u', $raw );
	$parts = is_array( $parts ) ? array_values( array_filter( array_map( 'trim', $parts ) ) ) : [];
	if ( empty( $parts ) ) {
		return [ 'province' => '', 'city' => '' ];
	}
	$province = $parts[0] ?? '';
	$city     = $parts[1] ?? '';
	if ( '' === $city && '' !== $province ) {
		// Fallback when only one location token exists.
		$city = $province;
	}
	return [
		'province' => (string) $province,
		'city'     => (string) $city,
	];
}

/**
 * Resolve existing term by name/slug; create if missing.
 *
 * @param string $name     Term name.
 * @param string $taxonomy Taxonomy slug.
 * @return int Term ID or 0.
 */
function hovalvakil_get_or_create_term_id_by_name( $name, $taxonomy ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return 0;
	}
	$t = get_term_by( 'name', $name, $taxonomy );
	if ( $t && ! is_wp_error( $t ) ) {
		return (int) $t->term_id;
	}
	$t = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
	if ( $t && ! is_wp_error( $t ) ) {
		return (int) $t->term_id;
	}
	$ins = wp_insert_term( $name, $taxonomy );
	if ( is_wp_error( $ins ) ) {
		return 0;
	}
	return (int) ( $ins['term_id'] ?? 0 );
}

/**
 * Backfill missing city/province taxonomy terms for lawyers using location text.
 *
 * @param array{limit?:int,offset?:int,dry_run?:bool} $args Args.
 * @return array{processed:int,updated:int,errors:int}
 */
function hovalvakil_backfill_lawyer_city_province_terms( array $args = [] ) {
	$limit   = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 2000;
	$offset  = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
	$dry_run = ! empty( $args['dry_run'] );

	$ids = get_posts(
		[
			'post_type'      => 'hvl_lawyer',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		]
	);

	$out = [ 'processed' => 0, 'updated' => 0, 'errors' => 0 ];
	foreach ( $ids as $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			continue;
		}
		$out['processed']++;

		$city_terms = get_the_terms( $post_id, 'hvl_city' );
		$prov_terms = get_the_terms( $post_id, 'hvl_province' );
		$has_city   = is_array( $city_terms ) && ! empty( $city_terms );
		$has_prov   = is_array( $prov_terms ) && ! empty( $prov_terms );
		if ( $has_city && $has_prov ) {
			continue;
		}

		$raw_loc = function_exists( 'hovalvakil_lawyer_card_location_line' ) ? hovalvakil_lawyer_card_location_line( $post_id ) : '';
		if ( '' === trim( (string) $raw_loc ) ) {
			$raw_loc = (string) get_post_meta( $post_id, 'hvl_office_address', true );
		}
		$guess = hovalvakil_guess_province_city_from_text( (string) $raw_loc );
		if ( '' === $guess['city'] && '' === $guess['province'] ) {
			$out['errors']++;
			continue;
		}

		$city_id = $has_city ? 0 : hovalvakil_get_or_create_term_id_by_name( $guess['city'], 'hvl_city' );
		$prov_id = $has_prov ? 0 : hovalvakil_get_or_create_term_id_by_name( $guess['province'], 'hvl_province' );

		if ( ! $dry_run ) {
			if ( $city_id > 0 ) {
				$ok = wp_set_object_terms( $post_id, [ $city_id ], 'hvl_city', false );
				if ( is_wp_error( $ok ) ) {
					$out['errors']++;
					continue;
				}
			}
			if ( $prov_id > 0 ) {
				$ok = wp_set_object_terms( $post_id, [ $prov_id ], 'hvl_province', false );
				if ( is_wp_error( $ok ) ) {
					$out['errors']++;
					continue;
				}
			}
		}
		if ( $city_id > 0 || $prov_id > 0 ) {
			$out['updated']++;
		}
	}
	return $out;
}
