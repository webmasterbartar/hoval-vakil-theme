<?php
/**
 * Load theme header/footer on Hovalvakil standalone templates + enqueue main CSS via wp_enqueue_scripts.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current request uses a Hovalvakil full-page template bridged to get_header/get_footer.
 *
 * @return bool
 */
function hovalvakil_is_bridged_template() {
	$current_slug = hovalvakil_current_request_slug();

	if ( is_front_page() ) {
		return true;
	}
	if ( is_post_type_archive( 'hvl_lawyer' ) ) {
		return true;
	}
	$slug = hovalvakil_current_request_slug();
	if ( str_starts_with( $slug, 'archive/' ) || str_starts_with( $slug, 'ostan/' ) || str_starts_with( $slug, 'shahr/' ) ) {
		return true;
	}
	foreach ( [ 'paye-yek', 'karamooz' ] as $grade_route ) {
		if ( $slug === $grade_route || str_starts_with( $slug, $grade_route . '/' ) ) {
			return true;
		}
	}
	if ( is_singular( 'hvl_lawyer' ) ) {
		return true;
	}
	if ( is_page_template( 'page-marakez.php' ) || is_page_template( 'page-takha.php' ) || is_page_template( 'page-tamas.php' ) || is_page_template( 'page-darbare.php' ) ) {
		return true;
	}
	if ( in_array( $current_slug, [ 'takha', 'marakez', 'tamas', 'about', 'darbare', 'vakil-login', 'vakil-panel' ], true ) ) {
		return true;
	}
	return false;
}

/**
 * Enqueue local fonts (Yekan Bakh + Material Symbols) on every page.
 *
 * @return void
 */
function hovalvakil_enqueue_fonts(): void {
	$path = get_template_directory() . '/hovalvakil/fonts.css';
	$ver  = is_readable( $path ) ? (string) filemtime( $path ) : HELLO_ELEMENTOR_VERSION;
	wp_enqueue_style(
		'hovalvakil-fonts',
		get_template_directory_uri() . '/hovalvakil/fonts.css',
		[],
		$ver
	);
}
add_action( 'wp_enqueue_scripts', 'hovalvakil_enqueue_fonts', 5 );

/**
 * @return void
 */
function hovalvakil_enqueue_bridged_assets() {
	if ( ! hovalvakil_is_bridged_template() ) {
		return;
	}
	$path = get_template_directory() . '/hovalvakil/style.css';
	$ver  = is_readable( $path ) ? (string) filemtime( $path ) : HELLO_ELEMENTOR_VERSION;
	$deps = [];
	if ( wp_style_is( 'hello-elementor-theme-style', 'registered' ) ) {
		$deps[] = 'hello-elementor-theme-style';
	} elseif ( wp_style_is( 'hello-elementor', 'registered' ) ) {
		$deps[] = 'hello-elementor';
	}
	wp_enqueue_style(
		'hovalvakil-main',
		get_template_directory_uri() . '/hovalvakil/style.css',
		$deps,
		$ver
	);
}
add_action( 'wp_enqueue_scripts', 'hovalvakil_enqueue_bridged_assets', 25 );

/**
 * Enqueue shared custom footer stylesheet.
 *
 * @return void
 */
function hovalvakil_enqueue_footer_assets() {
	$path = get_template_directory() . '/hovalvakil/footer.css';
	$ver  = is_readable( $path ) ? (string) filemtime( $path ) : HELLO_ELEMENTOR_VERSION;
	wp_enqueue_style(
		'hovalvakil-footer',
		get_template_directory_uri() . '/hovalvakil/footer.css',
		[],
		$ver
	);
}
add_action( 'wp_enqueue_scripts', 'hovalvakil_enqueue_footer_assets', 30 );

/**
 * Enqueue shared custom header stylesheet.
 *
 * @return void
 */
function hovalvakil_enqueue_header_assets() {
	$path = get_template_directory() . '/hovalvakil/header.css';
	$ver  = is_readable( $path ) ? (string) filemtime( $path ) : HELLO_ELEMENTOR_VERSION;
	$deps = wp_style_is( 'hovalvakil-main', 'registered' ) || wp_style_is( 'hovalvakil-main', 'enqueued' )
		? [ 'hovalvakil-main' ]
		: [];
	wp_enqueue_style(
		'hovalvakil-header',
		get_template_directory_uri() . '/hovalvakil/header.css',
		$deps,
		$ver
	);
}
add_action( 'wp_enqueue_scripts', 'hovalvakil_enqueue_header_assets', 28 );

/**
 * Enqueue contact page stylesheet.
 *
 * @return void
 */
function hovalvakil_enqueue_tamas_assets() {
	$is_tamas = is_page_template( 'page-tamas.php' );
	if ( ! $is_tamas && function_exists( 'hovalvakil_current_request_slug' ) ) {
		$is_tamas = ( 'tamas' === hovalvakil_current_request_slug() );
	}
	if ( ! $is_tamas ) {
		return;
	}

	$path = get_template_directory() . '/hovalvakil/tamas.css';
	$ver  = is_readable( $path ) ? (string) filemtime( $path ) : HELLO_ELEMENTOR_VERSION;
	$tamas_deps = wp_style_is( 'hovalvakil-main', 'registered' ) || wp_style_is( 'hovalvakil-main', 'enqueued' )
		? [ 'hovalvakil-main' ]
		: [];
	wp_enqueue_style(
		'hovalvakil-tamas',
		get_template_directory_uri() . '/hovalvakil/tamas.css',
		$tamas_deps,
		$ver
	);
}
add_action( 'wp_enqueue_scripts', 'hovalvakil_enqueue_tamas_assets', 31 );

/**
 * Enqueue about page stylesheet.
 *
 * @return void
 */
function hovalvakil_enqueue_darbare_assets() {
	$is_darbare = is_page_template( 'page-darbare.php' );
	if ( ! $is_darbare && function_exists( 'hovalvakil_current_request_slug' ) ) {
		$is_darbare = in_array( hovalvakil_current_request_slug(), [ 'about', 'darbare' ], true );
	}
	if ( ! $is_darbare ) {
		return;
	}

	$path = get_template_directory() . '/hovalvakil/darbare.css';
	$ver  = is_readable( $path ) ? (string) filemtime( $path ) : HELLO_ELEMENTOR_VERSION;
	$darbare_deps = wp_style_is( 'hovalvakil-main', 'registered' ) || wp_style_is( 'hovalvakil-main', 'enqueued' )
		? [ 'hovalvakil-main' ]
		: [];
	wp_enqueue_style(
		'hovalvakil-darbare',
		get_template_directory_uri() . '/hovalvakil/darbare.css',
		$darbare_deps,
		$ver
	);
}
add_action( 'wp_enqueue_scripts', 'hovalvakil_enqueue_darbare_assets', 32 );

/**
 * Decide whether custom coded footer should be forced.
 *
 * @return bool
 */
function hovalvakil_use_code_footer() {
	return (bool) apply_filters( 'hovalvakil_use_code_footer', true );
}

/**
 * Decide whether custom coded header should be forced.
 *
 * @return bool
 */
function hovalvakil_use_code_header() {
	return (bool) apply_filters( 'hovalvakil_use_code_header', true );
}

/**
 * @param string $output Attributes string.
 *
 * @return string
 */
function hovalvakil_bridged_language_attributes( $output ) {
	if ( ! hovalvakil_is_bridged_template() ) {
		return $output;
	}
	if ( false === strpos( $output, 'dir=' ) ) {
		$output .= ' dir="rtl"';
	}
	if ( false === strpos( $output, 'lang=' ) ) {
		$output .= ' lang="fa"';
	}
	return $output;
}
add_filter( 'language_attributes', 'hovalvakil_bridged_language_attributes', 20 );

/**
 * @param string[] $classes Body classes.
 *
 * @return string[]
 */
function hovalvakil_bridged_body_class( $classes ) {
	if ( ! hovalvakil_is_bridged_template() ) {
		return $classes;
	}
	$classes[] = 'hovalvakil-bridged';
	$classes[] = 'text-on-surface';
	if ( is_post_type_archive( 'hvl_lawyer' ) ) {
		$classes[] = 'archive-page';
	}
	if ( str_starts_with( hovalvakil_current_request_slug(), 'archive/' ) ) {
		$classes[] = 'archive-page';
	}
	if ( is_page_template( 'page-marakez.php' ) ) {
		$classes[] = 'marakez-page';
	}
	if ( is_page_template( 'page-takha.php' ) ) {
		$classes[] = 'takha-page';
	}
	return $classes;
}
add_filter( 'body_class', 'hovalvakil_bridged_body_class' );

/**
 * When Hello Elementor dynamic header/footer would hide everything, still show bar on bridged pages.
 *
 * @param string $value Setting value.
 *
 * @return string
 */
function hovalvakil_bridged_force_header_footer_toggle( $value ) {
	if ( ! hovalvakil_is_bridged_template() ) {
		return $value;
	}
	return 'yes' === $value ? $value : 'yes';
}

foreach (
	[
		'hello_header_logo_display',
		'hello_header_tagline_display',
		'hello_header_menu_display',
		'hello_footer_logo_display',
		'hello_footer_tagline_display',
		'hello_footer_menu_display',
		'hello_footer_copyright_display',
	] as $hvl_toggle_id
) {
	add_filter( 'hello_elementor_' . $hvl_toggle_id, 'hovalvakil_bridged_force_header_footer_toggle', 999 );
}
