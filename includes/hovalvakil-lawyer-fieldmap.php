<?php
/**
 * Lawyer post meta + taxonomy field map for admin, templates, REST, and future CSV import.
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
 * Meta keys used on hvl_lawyer (single source for importers).
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
 * @param int    $post_id Post ID.
 * @param string $size    Image size.
 * @return string
 */
function hovalvakil_lawyer_profile_image_url( $post_id, $size = 'medium' ) {
	$post_id = (int) $post_id;
	$thumb   = get_the_post_thumbnail_url( $post_id, $size );
	if ( is_string( $thumb ) && '' !== $thumb ) {
		return $thumb;
	}
	$url = (string) get_post_meta( $post_id, 'hvl_photo_url', true );
	return '' !== $url ? $url : '';
}
