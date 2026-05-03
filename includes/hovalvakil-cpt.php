<?php
/**
 * Custom Post Types & Taxonomies for Hovalvakil.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flush rewrite rules once when CPTs change.
 *
 * @return void
 */
function hovalvakil_maybe_flush_rewrite_rules() {
	$current = '2026-05-03-hvl-province-tax';
	$saved   = get_option( 'hovalvakil_rewrite_version' );

	if ( $saved === $current ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'hovalvakil_rewrite_version', $current );
}
add_action( 'admin_init', 'hovalvakil_maybe_flush_rewrite_rules' );

/**
 * Register CPTs and taxonomies.
 *
 * @return void
 */
function hovalvakil_register_content_types() {
	$common_supports = [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ];
	$menu_icon       = 'dashicons-admin-users';

	register_post_type(
		'hvl_lawyer',
		[
			'labels'              => [
				'name'                     => 'وکلا',
				'singular_name'            => 'وکیل',
				'menu_name'                => 'وکلا',
				'name_admin_bar'           => 'وکیل',
				'add_new'                  => 'افزودن',
				'add_new_item'             => 'افزودن وکیل جدید',
				'edit_item'                => 'ویرایش وکیل',
				'new_item'                 => 'وکیل جدید',
				'view_item'                => 'مشاهده در سایت',
				'view_items'               => 'مشاهدهٔ وکلا',
				'search_items'             => 'جستجوی وکلا',
				'not_found'                => 'وکیلی پیدا نشد',
				'not_found_in_trash'       => 'در زباله‌دان وکیلی پیدا نشد',
				'all_items'                => 'همهٔ وکلا',
				'archives'                 => 'آرشیو وکلا',
				'attributes'               => 'ویژگی‌های وکیل',
				'insert_into_item'         => 'درج در متن وکیل',
				'uploaded_to_this_item'    => 'پیوست به این وکیل',
				'featured_image'           => 'تصویر وکیل',
				'set_featured_image'      => 'انتخاب تصویر وکیل',
				'remove_featured_image'   => 'حذف تصویر وکیل',
				'use_featured_image'      => 'استفاده به‌عنوان تصویر وکیل',
				'filter_items_list'       => 'فیلتر فهرست وکلا',
				'items_list_navigation'   => 'ناوبری فهرست وکلا',
				'items_list'              => 'فهرست وکلا',
			],
			'description'         => 'مدیریت پروفایل وکلا برای نمایش در سایت.',
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => $menu_icon,
			'supports'     => $common_supports,
			'has_archive'  => 'arshive',
			'rewrite'      => [ 'slug' => 'vakil' ],
		]
	);

	register_post_type(
		'hvl_center',
		[
			'labels'              => [
				'name'                     => 'مراکز حقوقی',
				'singular_name'            => 'مرکز حقوقی',
				'menu_name'                => 'مراکز حقوقی',
				'name_admin_bar'           => 'مرکز حقوقی',
				'add_new'                  => 'افزودن',
				'add_new_item'             => 'افزودن مرکز جدید',
				'edit_item'                => 'ویرایش مرکز',
				'new_item'                 => 'مرکز جدید',
				'view_item'                => 'مشاهده در سایت',
				'view_items'               => 'مشاهدهٔ مراکز',
				'search_items'             => 'جستجوی مراکز',
				'not_found'                => 'مرکزی پیدا نشد',
				'not_found_in_trash'       => 'در زباله‌دان مرکزی پیدا نشد',
				'all_items'                => 'همهٔ مراکز',
				'archives'                 => 'آرشیو مراکز',
				'attributes'               => 'ویژگی‌های مرکز',
				'insert_into_item'         => 'درج در متن مرکز',
				'uploaded_to_this_item'    => 'پیوست به این مرکز',
				'featured_image'           => 'تصویر مرکز',
				'set_featured_image'      => 'انتخاب تصویر مرکز',
				'remove_featured_image'   => 'حذف تصویر مرکز',
				'use_featured_image'      => 'استفاده به‌عنوان تصویر مرکز',
				'filter_items_list'       => 'فیلتر فهرست مراکز',
				'items_list_navigation'   => 'ناوبری فهرست مراکز',
				'items_list'              => 'فهرست مراکز',
			],
			'description'         => 'مراکز حقوقی و دفاتر وکالت.',
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-building',
			'supports'     => $common_supports,
			'has_archive'  => true,
			'rewrite'      => [ 'slug' => 'marakez' ],
		]
	);

	register_post_type(
		'hvl_service',
		[
			'labels'              => [
				'name'                     => 'تخصص‌ها/خدمات',
				'singular_name'            => 'تخصص/خدمت',
				'menu_name'                => 'تخصص‌ها/خدمات',
				'name_admin_bar'           => 'تخصص/خدمت',
				'add_new'                  => 'افزودن',
				'add_new_item'             => 'افزودن تخصص جدید',
				'edit_item'                => 'ویرایش تخصص',
				'new_item'                 => 'تخصص جدید',
				'view_item'                => 'مشاهده در سایت',
				'view_items'               => 'مشاهدهٔ تخصص‌ها',
				'search_items'             => 'جستجوی تخصص‌ها',
				'not_found'                => 'تخصصی پیدا نشد',
				'not_found_in_trash'       => 'در زباله‌دان تخصصی پیدا نشد',
				'all_items'                => 'همهٔ تخصص‌ها',
				'archives'                 => 'آرشیو تخصص‌ها',
				'attributes'               => 'ویژگی‌های تخصص',
				'insert_into_item'         => 'درج در متن تخصص',
				'uploaded_to_this_item'    => 'پیوست به این تخصص',
				'featured_image'           => 'تصویر تخصص',
				'set_featured_image'      => 'انتخاب تصویر تخصص',
				'remove_featured_image'   => 'حذف تصویر تخصص',
				'use_featured_image'      => 'استفاده به‌عنوان تصویر تخصص',
				'filter_items_list'       => 'فیلتر فهرست تخصص‌ها',
				'items_list_navigation'   => 'ناوبری فهرست تخصص‌ها',
				'items_list'              => 'فهرست تخصص‌ها',
			],
			'description'         => 'خدمات و تخصص‌های حقوقی (برای صفحات تخصص).',
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-tag',
			'supports'     => $common_supports,
			'has_archive'  => true,
			'rewrite'      => [ 'slug' => 'takhassos' ],
		]
	);

	register_post_type(
		'hvl_reservation',
		[
			'labels'              => [
				'name'                     => 'رزروها',
				'singular_name'            => 'رزرو',
				'menu_name'                => 'رزروها',
				'name_admin_bar'           => 'رزرو',
				'add_new'                  => 'افزودن',
				'add_new_item'             => 'افزودن رزرو جدید',
				'edit_item'                => 'ویرایش رزرو',
				'new_item'                 => 'رزرو جدید',
				'view_item'                => 'مشاهده رزرو',
				'view_items'               => 'مشاهدهٔ رزروها',
				'search_items'             => 'جستجوی رزروها',
				'not_found'                => 'رزروی پیدا نشد',
				'not_found_in_trash'       => 'در زباله‌دان رزروی پیدا نشد',
				'all_items'                => 'همهٔ رزروها',
				'attributes'               => 'ویژگی‌های رزرو',
				'filter_items_list'       => 'فیلتر فهرست رزروها',
				'items_list_navigation'   => 'ناوبری فهرست رزروها',
				'items_list'              => 'فهرست رزروها',
			],
			'description'         => 'درخواست‌های رزرو نوبت از فرم سایت.',
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => [ 'title', 'custom-fields' ],
			'has_archive'  => false,
			'rewrite'      => false,
		]
	);

	register_post_type(
		'hvl_review',
		[
			'labels'              => [
				'name'                     => 'نظرات وکیل',
				'singular_name'            => 'نظر',
				'menu_name'                => 'نظرات وکیل',
				'name_admin_bar'           => 'نظر',
				'add_new'                  => 'افزودن',
				'add_new_item'             => 'افزودن نظر جدید',
				'edit_item'                => 'ویرایش نظر',
				'new_item'                 => 'نظر جدید',
				'view_item'                => 'مشاهده نظر',
				'view_items'               => 'مشاهدهٔ نظرات',
				'search_items'             => 'جستجوی نظرات',
				'not_found'                => 'نظری پیدا نشد',
				'not_found_in_trash'       => 'در زباله‌دان نظری پیدا نشد',
				'all_items'                => 'همهٔ نظرات',
				'archives'                 => 'آرشیو نظرات',
				'attributes'               => 'ویژگی‌های نظر',
				'insert_into_item'         => 'درج در متن نظر',
				'uploaded_to_this_item'    => 'پیوست به این نظر',
				'filter_items_list'       => 'فیلتر فهرست نظرات',
				'items_list_navigation'   => 'ناوبری فهرست نظرات',
				'items_list'              => 'فهرست نظرات',
			],
			'description'         => 'نظرات ثبت‌شده دربارهٔ وکلا.',
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-star-filled',
			'supports'     => [ 'title', 'editor', 'custom-fields' ],
			'has_archive'  => false,
			'rewrite'      => false,
		]
	);

	register_taxonomy(
		'hvl_city',
		[ 'hvl_lawyer', 'hvl_center' ],
		[
			'labels'            => [
				'name'                       => 'شهرها',
				'singular_name'              => 'شهر',
				'menu_name'                  => 'شهرها',
				'search_items'               => 'جستجوی شهرها',
				'popular_items'              => 'شهرهای پرکاربرد',
				'all_items'                  => 'همهٔ شهرها',
				'edit_item'                  => 'ویرایش شهر',
				'update_item'                => 'به‌روزرسانی شهر',
				'add_new_item'               => 'افزودن شهر',
				'new_item_name'              => 'نام شهر جدید',
				'separate_items_with_commas' => 'شهرها را با ویرگول جدا کنید',
				'add_or_remove_items'        => 'افزودن یا حذف شهر',
				'choose_from_most_used'      => 'از پرکاربردترین شهرها انتخاب کنید',
				'not_found'                  => 'شهری پیدا نشد',
				'no_terms'                   => 'هیچ شهری نیست',
				'filter_by_item'             => 'فیلتر بر اساس شهر',
				'items_list_navigation'      => 'ناوبری فهرست شهرها',
				'items_list'                 => 'فهرست شهرها',
				'most_used'                  => 'پرکاربردترین',
				'back_to_items'              => '← بازگشت به شهرها',
				'view_item'                  => 'مشاهده شهر',
			],
			'description'       => 'شهر مرتبط با وکیل یا مرکز.',
			'public'       => true,
			'show_in_rest' => true,
			'hierarchical' => false,
			'rewrite'      => [ 'slug' => 'shahr' ],
		]
	);

	register_taxonomy(
		'hvl_specialty',
		[ 'hvl_lawyer', 'hvl_service' ],
		[
			'labels'            => [
				'name'                       => 'تخصص‌ها',
				'singular_name'              => 'تخصص',
				'menu_name'                  => 'تخصص‌ها',
				'search_items'               => 'جستجوی تخصص‌ها',
				'popular_items'              => 'تخصص‌های پرکاربرد',
				'all_items'                  => 'همهٔ تخصص‌ها',
				'parent_item'                => 'تخصص والد',
				'parent_item_colon'          => 'تخصص والد:',
				'edit_item'                  => 'ویرایش تخصص',
				'update_item'                => 'به‌روزرسانی تخصص',
				'add_new_item'               => 'افزودن تخصص',
				'new_item_name'              => 'نام تخصص جدید',
				'separate_items_with_commas' => 'تخصص‌ها را با ویرگول جدا کنید',
				'add_or_remove_items'        => 'افزودن یا حذف تخصص',
				'choose_from_most_used'      => 'از پرکاربردترین تخصص‌ها انتخاب کنید',
				'not_found'                  => 'تخصصی پیدا نشد',
				'no_terms'                   => 'هیچ تخصصی نیست',
				'filter_by_item'             => 'فیلتر بر اساس تخصص',
				'items_list_navigation'      => 'ناوبری فهرست تخصص‌ها',
				'items_list'                 => 'فهرست تخصص‌ها',
				'most_used'                  => 'پرکاربردترین',
				'back_to_items'              => '← بازگشت به تخصص‌ها',
				'view_item'                  => 'مشاهده تخصص',
			],
			'description'       => 'حوزهٔ تخصص وکیل یا خدمت (سلسله‌مراتبی).',
			'public'       => true,
			'show_in_rest' => true,
			'hierarchical' => true,
			'rewrite'      => [ 'slug' => 'takhassos' ],
		]
	);

	register_taxonomy(
		'hvl_province',
		[ 'hvl_lawyer', 'hvl_center' ],
		[
			'labels'            => [
				'name'                       => 'استان‌ها',
				'singular_name'              => 'استان',
				'menu_name'                  => 'استان‌ها',
				'search_items'               => 'جستجوی استان‌ها',
				'popular_items'              => 'استان‌های پرکاربرد',
				'all_items'                  => 'همهٔ استان‌ها',
				'edit_item'                  => 'ویرایش استان',
				'update_item'                => 'به‌روزرسانی استان',
				'add_new_item'               => 'افزودن استان',
				'new_item_name'              => 'نام استان جدید',
				'separate_items_with_commas' => 'استان‌ها را با ویرگول جدا کنید',
				'add_or_remove_items'        => 'افزودن یا حذف استان',
				'choose_from_most_used'      => 'از پرکاربردترین استان‌ها انتخاب کنید',
				'not_found'                  => 'استانی پیدا نشد',
				'no_terms'                   => 'هیچ استانی نیست',
				'filter_by_item'             => 'فیلتر بر اساس استان',
				'items_list_navigation'      => 'ناوبری فهرست استان‌ها',
				'items_list'                 => 'فهرست استان‌ها',
				'most_used'                  => 'پرکاربردترین',
				'back_to_items'              => '← بازگشت به استان‌ها',
				'view_item'                  => 'مشاهده استان',
			],
			'description'       => 'استان مرتبط با وکیل یا مرکز.',
			'public'            => true,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'ostan' ],
		]
	);
}
add_action( 'init', 'hovalvakil_register_content_types', 5 );

