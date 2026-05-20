<?php
/**
 * Serve static Hovalvakil HTML pages on clean WordPress URLs.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get static page map: request slug => html file name.
 *
 * @return array<string, string>
 */
function hovalvakil_static_pages_map() {
	return [
		'rezerv'   => 'rezerv.html',
	];
}

/**
 * Return current request slug relative to home path.
 *
 * @return string
 */
function hovalvakil_current_request_slug() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	$home_path   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

	$path      = '/' . ltrim( $path, '/' );
	$home_path = '/' . trim( $home_path, '/' );

	if ( '/' === $home_path ) {
		$home_path = '';
	}

	if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
		$path = substr( $path, strlen( $home_path ) );
	}

	return trim( $path, '/' );
}

/**
 * Check whether current request matches mapped static routes.
 *
 * @return bool
 */
function hovalvakil_is_static_route_request() {
	$slug       = hovalvakil_current_request_slug();
	$page_map   = hovalvakil_static_pages_map();
	$clean_slug = preg_replace( '/\.html$/', '', $slug );

	return is_string( $clean_slug ) && array_key_exists( $clean_slug, $page_map );
}

/**
 * Replace local static links/assets with clean WordPress URLs.
 *
 * @param string $html Raw HTML file contents.
 *
 * @return string
 */
function hovalvakil_rewrite_static_html_links( $html ) {
	$asset_base = trailingslashit( get_template_directory_uri() ) . 'hovalvakil/';

	$replacements = [
		'style.css'     => $asset_base . 'style.css',
		'index.html'    => home_url( '/' ),
		'arshive.html'  => trailingslashit( home_url( '/archive' ) ),
		'profil.html'   => trailingslashit( home_url( '/profil' ) ),
		'rezerv.html'   => trailingslashit( home_url( '/rezerv' ) ),
		'marakez.html'  => trailingslashit( home_url( '/marakez' ) ),
		'takha.html'    => trailingslashit( home_url( '/takha' ) ),
	];

	return str_replace(
		array_keys( $replacements ),
		array_values( $replacements ),
		$html
	);
}

/**
 * Serve mapped static page and stop WP template loading.
 *
 * @return void
 */
function hovalvakil_maybe_serve_static_page() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	// Serve dynamic specialties page on /takha even if no WP Page exists.
	if ( 'takha' === hovalvakil_current_request_slug() ) {
		$template_path = trailingslashit( get_template_directory() ) . 'page-takha.php';
		if ( file_exists( $template_path ) ) {
			status_header( 200 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			include $template_path;
			exit;
		}
	}

	// Serve dynamic centers page on /marakez even if no WP Page exists.
	if ( 'marakez' === hovalvakil_current_request_slug() ) {
		$template_path = trailingslashit( get_template_directory() ) . 'page-marakez.php';
		if ( file_exists( $template_path ) ) {
			status_header( 200 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			include $template_path;
			exit;
		}
	}

	// Serve dynamic contact page on /tamas even if no WP Page exists.
	if ( 'tamas' === hovalvakil_current_request_slug() ) {
		$template_path = trailingslashit( get_template_directory() ) . 'page-tamas.php';
		if ( file_exists( $template_path ) ) {
			status_header( 200 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			include $template_path;
			exit;
		}
	}

	// Serve lawyer login page.
	if ( 'vakil-login' === hovalvakil_current_request_slug() ) {
		$template_path = trailingslashit( get_template_directory() ) . 'page-vakil-login.php';
		if ( file_exists( $template_path ) ) {
			status_header( 200 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			include $template_path;
			exit;
		}
	}

	// Serve lawyer panel page.
	if ( 'vakil-panel' === hovalvakil_current_request_slug() ) {
		$template_path = trailingslashit( get_template_directory() ) . 'page-vakil-panel.php';
		if ( file_exists( $template_path ) ) {
			status_header( 200 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			include $template_path;
			exit;
		}
	}

	// Serve dynamic about page on /about (and legacy /darbare) even if no WP Page exists.
	$current_slug = hovalvakil_current_request_slug();
	if ( 'about' === $current_slug || 'darbare' === $current_slug ) {
		$template_path = trailingslashit( get_template_directory() ) . 'page-darbare.php';
		if ( file_exists( $template_path ) ) {
			status_header( 200 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			include $template_path;
			exit;
		}
	}

	if ( ! hovalvakil_is_static_route_request() ) {
		return;
	}

	$slug       = hovalvakil_current_request_slug();
	$page_map   = hovalvakil_static_pages_map();
	$clean_slug = preg_replace( '/\.html$/', '', $slug );
	$file_path = trailingslashit( get_template_directory() ) . 'hovalvakil/' . $page_map[ $clean_slug ];

	if ( ! file_exists( $file_path ) ) {
		return;
	}

	$html = file_get_contents( $file_path );
	if ( false === $html ) {
		return;
	}

	$html = hovalvakil_rewrite_static_html_links( $html );

	status_header( 200 );
	header( 'Content-Type: text/html; charset=UTF-8' );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'template_redirect', 'hovalvakil_maybe_serve_static_page', 0 );

/**
 * Hide WP admin bar on mapped static routes.
 *
 * @param bool $show Whether to show admin bar.
 *
 * @return bool
 */
function hovalvakil_hide_admin_bar_on_static_routes( $show ) {
	if ( hovalvakil_is_static_route_request() ) {
		return false;
	}

	return $show;
}
add_filter( 'show_admin_bar', 'hovalvakil_hide_admin_bar_on_static_routes' );
