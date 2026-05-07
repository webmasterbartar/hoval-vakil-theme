<?php
/**
 * Prevent external requests in wp-admin (Google Fonts, Gravatar).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local fallback avatar URL.
 *
 * @return string
 */
function hovalvakil_local_admin_avatar_url() {
	return function_exists( 'hovalvakil_theme_lawyer_placeholder_url' )
		? (string) hovalvakil_theme_lawyer_placeholder_url()
		: (string) get_template_directory_uri() . '/assets/images/lawyer-placeholder.png';
}

/**
 * Whether a URL points to blocked external assets.
 *
 * @param string $src URL.
 * @return bool
 */
function hovalvakil_is_blocked_external_src( $src ) {
	$src = (string) $src;
	return ( false !== stripos( $src, 'fonts.googleapis.com' ) )
		|| ( false !== stripos( $src, 'fonts.gstatic.com' ) )
		|| ( false !== stripos( $src, 'secure.gravatar.com' ) )
		|| ( false !== stripos( $src, 'gravatar.com' ) );
}

/**
 * Dequeue any enqueued Google Fonts styles in admin.
 *
 * @return void
 */
function hovalvakil_admin_dequeue_external_styles() {
	if ( ! is_admin() ) {
		return;
	}

	// Disable Open Sans from core (prevents calls on some WP versions).
	wp_deregister_style( 'open-sans' );
	wp_register_style( 'open-sans', false );

	global $wp_styles;
	if ( ! isset( $wp_styles ) || ! is_object( $wp_styles ) || empty( $wp_styles->registered ) ) {
		return;
	}

	foreach ( $wp_styles->registered as $handle => $obj ) {
		if ( ! is_object( $obj ) || empty( $obj->src ) ) {
			continue;
		}
		$src = (string) $obj->src;
		if ( hovalvakil_is_blocked_external_src( $src ) ) {
			wp_dequeue_style( (string) $handle );
			wp_deregister_style( (string) $handle );
		}
	}
}
add_action( 'admin_enqueue_scripts', 'hovalvakil_admin_dequeue_external_styles', 999 );

/**
 * Safety net: block printing external style/script src in admin.
 *
 * @param string $src Source URL.
 * @return string
 */
function hovalvakil_admin_block_loader_src( $src ) {
	if ( is_admin() && hovalvakil_is_blocked_external_src( $src ) ) {
		return '';
	}
	return (string) $src;
}
add_filter( 'style_loader_src', 'hovalvakil_admin_block_loader_src', 999 );
add_filter( 'script_loader_src', 'hovalvakil_admin_block_loader_src', 999 );

/**
 * Disable avatar output in admin to prevent Gravatar calls.
 *
 * @return string
 */
function hovalvakil_admin_disable_avatars_option() {
	return '0';
}
add_filter( 'pre_option_show_avatars', 'hovalvakil_admin_disable_avatars_option', 999 );

/**
 * If something still asks for avatar URL, force local placeholder.
 *
 * @param string $url Avatar URL.
 * @return string
 */
function hovalvakil_admin_force_local_avatar_url( $url ) {
	if ( is_admin() ) {
		return esc_url_raw( hovalvakil_local_admin_avatar_url() );
	}
	return (string) $url;
}
add_filter( 'get_avatar_url', 'hovalvakil_admin_force_local_avatar_url', 999 );

