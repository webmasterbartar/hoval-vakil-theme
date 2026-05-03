<?php
/**
 * One-time (or re-runnable) migration: lawyer post slugs + city term slugs → English (Latin) slugs.
 *
 * Run from: وکلا → اسلاگ انگلیسی (admin).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transliterate Persian/Arabic text to a Latin slug fragment.
 *
 * @param string $text Source text (title or slug).
 *
 * @return string
 */
function hovalvakil_text_to_latin_slug_base( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );
	if ( '' === $text ) {
		return '';
	}

	$latin = null;
	if ( function_exists( 'transliterator_transliterate' ) ) {
		// ICU: general transliteration + ASCII fold (works well for Fa/Ar when ext-intl is on).
		$latin = @transliterator_transliterate( 'Any-Latin; Latin-ASCII; Lower()', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	if ( ! is_string( $latin ) || '' === $latin ) {
		$latin = hovalvakil_manual_fa_ar_to_latin( $text );
	}

	$latin = strtolower( (string) $latin );
	$latin = preg_replace( '/[^a-z0-9]+/', '-', $latin );
	$latin = trim( (string) $latin, '-' );

	return $latin;
}

/**
 * Fallback map when intl is unavailable.
 *
 * @param string $text Text.
 *
 * @return string
 */
function hovalvakil_manual_fa_ar_to_latin( $text ) {
	$map = [
		'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ٱ' => 'a', 'ب' => 'b', 'پ' => 'p',
		'ت' => 't', 'ث' => 's', 'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh',
		'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's',
		'ش' => 'sh', 'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
		'غ' => 'gh', 'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g',
		'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'و' => 'v', 'ؤ' => 'o', 'ي' => 'y',
		'ی' => 'y', 'ئ' => 'y', 'ة' => 'h', 'ه' => 'h', 'ء' => '', 'ٔ' => '',
		'‌' => '-', ' ' => '-', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
	];
	$len = mb_strlen( $text, 'UTF-8' );
	$out = '';
	for ( $i = 0; $i < $len; $i++ ) {
		$ch = mb_substr( $text, $i, 1, 'UTF-8' );
		if ( isset( $map[ $ch ] ) ) {
			$out .= $map[ $ch ];
			continue;
		}
		if ( preg_match( '/^[a-zA-Z0-9]$/', $ch ) ) {
			$out .= strtolower( $ch );
			continue;
		}
		$out .= '-';
	}
	return $out;
}

/**
 * Unique slug among terms in taxonomy (excluding one term_id when updating).
 *
 * @param string $slug            Desired slug.
 * @param string $taxonomy        Taxonomy.
 * @param int    $exclude_term_id Term being updated.
 *
 * @return string
 */
function hovalvakil_unique_term_slug_among( $slug, $taxonomy, $exclude_term_id = 0 ) {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		$slug = 'item';
	}
	$base = $slug;
	$n    = 0;
	while ( true ) {
		$found = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'slug'       => $slug,
				'hide_empty' => false,
				'number'     => 1,
			]
		);
		if ( is_wp_error( $found ) || empty( $found ) ) {
			return $slug;
		}
		$t = $found[0];
		if ( (int) $t->term_id === (int) $exclude_term_id ) {
			return $slug;
		}
		$n++;
		$slug = $base . '-' . $n;
	}
}

/**
 * Run migration; returns counts [ 'lawyers' => int, 'cities' => int, 'errors' => string[] ].
 *
 * @return array{lawyers:int,cities:int,errors:string[]}
 */
function hovalvakil_run_english_slug_migration() {
	$errors  = [];
	$lawyers = 0;
	$cities  = 0;

	$city_terms = get_terms(
		[
			'taxonomy'   => 'hvl_city',
			'hide_empty' => false,
			'number'     => 0,
		]
	);
	if ( ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ) {
		foreach ( $city_terms as $term ) {
			$base = hovalvakil_text_to_latin_slug_base( $term->name );
			if ( '' === $base ) {
				$base = 'city-' . (int) $term->term_id;
			}
			$new_slug = hovalvakil_unique_term_slug_among( $base, 'hvl_city', (int) $term->term_id );
			if ( $new_slug === $term->slug ) {
				continue;
			}
			$upd = wp_update_term( (int) $term->term_id, 'hvl_city', [ 'slug' => $new_slug ] );
			if ( is_wp_error( $upd ) ) {
				$errors[] = 'city ' . (int) $term->term_id . ': ' . $upd->get_error_message();
				continue;
			}
			$cities++;
		}
	}

	$post_ids = get_posts(
		[
			'post_type'              => 'hvl_lawyer',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]
	);
	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'hvl_lawyer' !== $post->post_type ) {
			continue;
		}
		$base = hovalvakil_text_to_latin_slug_base( $post->post_title );
		if ( '' === $base ) {
			$base = 'lawyer-' . (int) $post_id;
		}
		$candidate = sanitize_title( $base );
		$new_slug  = wp_unique_post_slug( $candidate, (int) $post_id, $post->post_status, 'hvl_lawyer', (int) $post->post_parent );
		if ( $new_slug === $post->post_name ) {
			continue;
		}
		$upd = wp_update_post(
			[
				'ID'        => (int) $post_id,
				'post_name' => $new_slug,
			],
			true
		);
		if ( is_wp_error( $upd ) ) {
			$errors[] = 'lawyer ' . (int) $post_id . ': ' . $upd->get_error_message();
			continue;
		}
		$lawyers++;
	}

	clean_term_cache( null, 'hvl_city' );
	flush_rewrite_rules( false );

	return [
		'lawyers' => $lawyers,
		'cities'  => $cities,
		'errors'  => $errors,
	];
}

/**
 * Admin page callback.
 *
 * @return void
 */
function hovalvakil_slug_migrate_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'مجوز ندارید.', 'hello-elementor' ) );
	}

	$notice = '';
	if ( isset( $_POST['hovalvakil_slug_migrate'] ) && check_admin_referer( 'hovalvakil_slug_migrate' ) ) {
		$result = hovalvakil_run_english_slug_migration();
		$notice = sprintf(
			/* translators: 1: lawyers count, 2: cities count */
			'<div class="notice notice-success is-dismissible"><p>به‌روز شد: %1$d وکیل، %2$d شهر.</p></div>',
			(int) $result['lawyers'],
			(int) $result['cities']
		);
		if ( ! empty( $result['errors'] ) ) {
			$notice .= '<div class="notice notice-warning"><p>' . esc_html( implode( ' | ', array_slice( $result['errors'], 0, 10 ) ) ) . '</p></div>';
		}
	}

	echo '<div class="wrap"><h1>تبدیل اسلاگ به انگلیسی (لاتین)</h1>';
	echo wp_kses_post( $notice );
	echo '<p>اسلاگ <strong>آدرس تک‌وکیل</strong> و <strong>شهرها</strong> (ترم <code>hvl_city</code>) بر اساس عنوان فارسی به حروف لاتین تبدیل می‌شود. یک‌بار بعد از تغییر آدرس‌ها در تنظیمات CPT اجرا کنید؛ اجرای دوباره فقط مواردی را عوض می‌کند که هنوز فارسی هستند.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'hovalvakil_slug_migrate' );
	echo '<p><button type="submit" name="hovalvakil_slug_migrate" class="button button-primary" value="1" onclick="return confirm(\'اسلاگ‌ها در دیتابیس به‌روز می‌شوند. ادامه؟\');">اجرای مهاجرت</button></p>';
	echo '</form></div>';
}

/**
 * Register admin submenu.
 *
 * @return void
 */
function hovalvakil_register_slug_migrate_menu() {
	add_submenu_page(
		'edit.php?post_type=hvl_lawyer',
		'اسلاگ انگلیسی',
		'اسلاگ انگلیسی',
		'manage_options',
		'hovalvakil-slug-migrate',
		'hovalvakil_slug_migrate_admin_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_slug_migrate_menu' );
