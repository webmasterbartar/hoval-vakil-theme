<?php
/**
 * Demo data seeder for Hovalvakil.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seed base terms/posts once.
 *
 * @return void
 */
function hovalvakil_seed_demo_data_once() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$seed_version = '2026-04-09-4';
	$saved        = get_option( 'hovalvakil_seed_version' );
	if ( $saved === $seed_version ) {
		return;
	}

	$specialties = [
		'حقوق خانواده',
		'حقوق کیفری',
		'دعاوی ملکی',
		'حقوق تجاری',
		'حقوق ثبتی',
		'حقوق بین‌الملل',
		'حقوق شرکت‌ها',
		'حقوق مالیاتی',
		'حقوق کار و تامین اجتماعی',
		'چک و سفته',
		'قراردادها',
		'داوری و میانجی‌گری',
		'ارث و انحصار وراثت',
		'مالکیت فکری',
		'جرایم سایبری',
		'دعاوی بانکی',
		'ثبت برند و علائم',
		'حقوق پزشکی',
		'حقوق بیمه',
		'مهاجرت',
		'اجرای احکام',
		'حقوق گمرکی',
		'حقوق فناوری اطلاعات',
		'اختلافات کارگری',
		'تخلفات رانندگی',
		'حقوق محیط زیست',
		'حقوق انرژی',
		'ورشکستگی',
		'مطالبات قراردادی',
		'حقوق حمل و نقل',
		'حقوق دریایی',
		'حقوق هوایی',
		'دعاوی شهرداری',
		'دعاوی دیوان عدالت اداری',
		'دعاوی پیمانکاری',
		'حقوق بورس و اوراق بهادار',
		'حل اختلافات بین‌المللی',
		'حقوق ورزش',
		'حقوق رسانه و نشر',
		'حمایت از مصرف کننده',
	];

	$cities = [
		'تهران',
		'مشهد',
		'اصفهان',
		'شیراز',
		'کرج',
		'قم',
		'اهواز',
		'تبریز',
		'کرمانشاه',
		'رشت',
		'یزد',
		'ارومیه',
		'همدان',
		'کرمان',
		'زاهدان',
		'قزوین',
		'گرگان',
		'بندرعباس',
		'اردبیل',
		'ساری',
	];

	$center_names = [
		'کانون وکلای دادگستری تهران',
		'مرکز حقوقی دادپویان',
		'موسسه حقوقی رای‌نگار',
		'دفتر حقوقی عدالت‌گستر',
		'مرکز مشاوره حقوقی میزان',
		'دفتر وکلای آفاق',
		'موسسه حقوقی پارسیان',
		'مرکز داوری بهین‌داد',
		'دفتر حقوقی بهداد',
		'مرکز حقوقی مهرآیین',
	];
	$center_prefixes = [ 'کانون', 'مرکز', 'موسسه', 'دفتر', 'گروه' ];
	$center_brands   = [ 'دادگستر', 'دادپویان', 'عدالت‌نگر', 'دادآور', 'قانون‌یار', 'رای‌نگار', 'وکیل‌یار', 'آیین‌دادرسی', 'همیار حقوق', 'پارس‌داد', 'میزان', 'افق عدالت' ];
	$center_suffixes = [ 'حقوقی', 'وکلا', 'مشاوره حقوقی', 'حل اختلاف', 'خدمات قضایی', 'امور قراردادها', 'دفاع کیفری', 'دعاوی ملکی' ];
	$target_centers_count = 140;
	if ( count( $center_names ) < $target_centers_count ) {
		for ( $i = 0; $i < 1200 && count( $center_names ) < $target_centers_count; $i++ ) {
			$full_name = $center_prefixes[ $i % count( $center_prefixes ) ] . ' ' .
				$center_brands[ ( $i * 2 ) % count( $center_brands ) ] . ' ' .
				$center_suffixes[ ( $i * 3 ) % count( $center_suffixes ) ] . ' ' .
				$cities[ $i % count( $cities ) ];
			if ( ! in_array( $full_name, $center_names, true ) ) {
				$center_names[] = $full_name;
			}
		}
	}

	$lawyer_names = [
		'دکتر علیرضا افشار',
		'امیر پارسا',
		'سارا رادمان',
		'علیرضا فرهمند',
		'رضا ناصری',
		'مریم کریمی',
		'سعید احمدی',
		'نیلوفر عباسی',
		'پرهام رستمی',
		'حسین داوودی',
		'فاطمه یوسفی',
		'کیوان مرادی',
		'الناز باقری',
		'عماد قادری',
		'آتنا منصوری',
		'سامان کیانی',
		'محمد پارسا',
		'زهرا صادقی',
		'پویان حق‌شناس',
		'مهسا نوروزی',
	];
	$first_names = [ 'امیر', 'سارا', 'علیرضا', 'رضا', 'مریم', 'سعید', 'نیلوفر', 'پرهام', 'حسین', 'فاطمه', 'کیوان', 'الناز', 'عماد', 'آتنا', 'سامان', 'محمد', 'زهرا', 'پویان', 'مهسا', 'نرگس', 'آرمان', 'بهاره', 'کوروش', 'مهدی', 'الهام' ];
	$last_names  = [ 'احمدی', 'کریمی', 'مرادی', 'صادقی', 'یزدانی', 'فرهادی', 'نیکخواه', 'رضایی', 'حیدری', 'محمدی', 'امینی', 'قاسمی', 'باقری', 'یوسفی', 'کاظمی', 'سپهری', 'راد', 'دانشور', 'حق شناس', 'توکلی' ];
	$target_lawyers_count = 80;
	if ( count( $lawyer_names ) < $target_lawyers_count ) {
		for ( $i = 0; $i < 500 && count( $lawyer_names ) < $target_lawyers_count; $i++ ) {
			$full_name = $first_names[ $i % count( $first_names ) ] . ' ' . $last_names[ ( $i * 3 ) % count( $last_names ) ];
			if ( ! in_array( $full_name, $lawyer_names, true ) ) {
				$lawyer_names[] = $full_name;
			}
		}
	}
	$districts = [
		'سعادت‌آباد',
		'ونک',
		'الهیه',
		'احمدآباد',
		'معالی‌آباد',
		'مرکز شهر',
		'گوهردشت',
		'شهرک غرب',
	];

	$specialty_ids = [];
	foreach ( $specialties as $name ) {
		$term = term_exists( $name, 'hvl_specialty' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'hvl_specialty' );
		}
		if ( is_array( $term ) && isset( $term['term_id'] ) ) {
			$specialty_ids[] = (int) $term['term_id'];
		} elseif ( is_object( $term ) && isset( $term->term_id ) ) {
			$specialty_ids[] = (int) $term->term_id;
		}
	}
	$specialty_ids = array_values( array_unique( array_filter( $specialty_ids ) ) );

	$city_ids = [];
	foreach ( $cities as $name ) {
		$term = term_exists( $name, 'hvl_city' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'hvl_city' );
		}
		if ( is_array( $term ) && isset( $term['term_id'] ) ) {
			$city_ids[] = (int) $term['term_id'];
		} elseif ( is_object( $term ) && isset( $term->term_id ) ) {
			$city_ids[] = (int) $term->term_id;
		}
	}
	$city_ids = array_values( array_unique( array_filter( $city_ids ) ) );

	foreach ( $center_names as $index => $name ) {
		$existing = get_page_by_title( $name, OBJECT, 'hvl_center' );
		$post_id  = $existing ? (int) $existing->ID : 0;
		if ( $post_id <= 0 ) {
			$post_id = wp_insert_post(
				[
					'post_type'    => 'hvl_center',
					'post_status'  => 'publish',
					'post_title'   => $name,
					'post_content' => 'مرکز حقوقی فعال با خدمات مشاوره حضوری و آنلاین.',
				]
			);
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		if ( isset( $city_ids[ $index % max( 1, count( $city_ids ) ) ] ) ) {
			wp_set_post_terms( $post_id, [ $city_ids[ $index % count( $city_ids ) ] ], 'hvl_city', false );
		}

		update_post_meta( $post_id, 'hvl_center_rating', (string) ( 4 + ( ( $index % 10 ) / 10 ) ) );
		update_post_meta( $post_id, 'hvl_center_lawyers_count', (string) ( 8 + ( $index % 90 ) ) );
		update_post_meta( $post_id, 'hvl_center_hotline', '۰۲۱-' . (string) ( 20000000 + ( $index * 413 ) ) );
		update_post_meta( $post_id, 'hvl_center_open_hours', 'شنبه تا پنجشنبه ۸:۰۰ تا ۲۰:۰۰' );
	}

	foreach ( $specialties as $name ) {
		$existing = get_page_by_title( $name, OBJECT, 'hvl_service' );
		if ( $existing ) {
			continue;
		}

		$post_id = wp_insert_post(
			[
				'post_type'    => 'hvl_service',
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => 'خدمات تخصصی حقوقی در این حوزه توسط وکلای حرفه‌ای ارائه می‌شود.',
			]
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		$term = term_exists( $name, 'hvl_specialty' );
		$tid  = is_array( $term ) && isset( $term['term_id'] ) ? (int) $term['term_id'] : 0;
		if ( $tid > 0 ) {
			wp_set_post_terms( $post_id, [ $tid ], 'hvl_specialty', false );
		}
	}

	foreach ( $lawyer_names as $index => $name ) {
		$existing = get_page_by_title( $name, OBJECT, 'hvl_lawyer' );
		$post_id  = $existing ? (int) $existing->ID : 0;
		if ( $post_id <= 0 ) {
			$post_id = wp_insert_post(
				[
					'post_type'    => 'hvl_lawyer',
					'post_status'  => 'publish',
					'post_title'   => $name,
					'post_excerpt' => 'وکیل پایه یک دادگستری با سابقه موفق در پرونده‌های تخصصی.',
					'post_content' => 'بیوگرافی نمونه برای تست توسعه. این محتوا قابل ویرایش از پنل وردپرس است.',
				]
			);
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		$city_id = isset( $city_ids[ $index % max( 1, count( $city_ids ) ) ] ) ? (int) $city_ids[ $index % count( $city_ids ) ] : 0;
		if ( $city_id > 0 ) {
			wp_set_post_terms( $post_id, [ $city_id ], 'hvl_city', false );
		}

		if ( ! empty( $specialty_ids ) ) {
			$first  = (int) $specialty_ids[ $index % count( $specialty_ids ) ];
			$second = (int) $specialty_ids[ ( $index + 3 ) % count( $specialty_ids ) ];
			wp_set_post_terms( $post_id, array_values( array_unique( [ $first, $second ] ) ), 'hvl_specialty', false );
		}

		update_post_meta( $post_id, 'hvl_experience', (string) ( 5 + ( $index % 16 ) ) . ' سال تجربه' );
		update_post_meta( $post_id, 'hvl_price_from', number_format_i18n( 250000 + ( $index * 25000 ) ) . ' تومان' );
		update_post_meta( $post_id, 'hvl_rating', (string) ( 4 + ( ( $index % 10 ) / 10 ) ) );
		update_post_meta( $post_id, 'hvl_reviews_count', (string) ( 20 + ( $index * 7 ) ) );
		update_post_meta( $post_id, 'hvl_license', 'شماره پروانه: ' . ( 120000 + $index ) );
		update_post_meta( $post_id, 'hvl_education', 'کارشناسی ارشد حقوق - دانشگاه ' . $cities[ $index % count( $cities ) ] );
		update_post_meta(
			$post_id,
			'hvl_records',
			"عضو کانون وکلای دادگستری\nبیش از " . ( 5 + ( $index % 16 ) ) . " سال سابقه وکالت\nتجربه در پرونده‌های حقوقی پیچیده"
		);
		update_post_meta(
			$post_id,
			'hvl_services',
			$specialties[ $index % count( $specialties ) ] . ', ' . $specialties[ ( $index + 4 ) % count( $specialties ) ] . ', مشاوره حقوقی عمومی'
		);
		$office_city = $cities[ $index % count( $cities ) ];
		$office_district = $districts[ $index % count( $districts ) ];
		update_post_meta( $post_id, 'hvl_office_address', $office_city . '، ' . $office_district . '، خیابان نمونه، پلاک ' . ( 10 + $index ) );
		update_post_meta( $post_id, 'hvl_office_phone', '۰۲۱-' . (string) ( 10000000 + ( $index * 731 ) ) );
		update_post_meta( $post_id, 'hvl_office_working_hours', 'شنبه تا چهارشنبه ۹:۰۰ تا ۱۸:۰۰' );
		$map_placeholder = function_exists( 'hovalvakil_theme_lawyer_placeholder_url' )
			? hovalvakil_theme_lawyer_placeholder_url()
			: get_template_directory_uri() . '/assets/images/lawyer-placeholder.svg';
		update_post_meta( $post_id, 'hvl_office_map_image', $map_placeholder );
	}

	update_option( 'hovalvakil_seed_version', $seed_version );
}
add_action( 'admin_init', 'hovalvakil_seed_demo_data_once', 20 );

