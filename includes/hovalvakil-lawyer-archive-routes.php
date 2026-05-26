<?php
/**
 * Clean archive URLs for lawyer list filters (grade, province, city).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hovalvakil_lawyer_archive_grade_map' ) ) :

/**
 * Grade internal slug => public route segment + label.
 *
 * @return array<string, array{route:string, label:string}>
 */
function hovalvakil_lawyer_archive_grade_map(): array {
	return [
		'p1'       => [
			'route' => 'paye-yek',
			'label' => __( 'وکیل پایه یک', 'hello-elementor' ),
		],
		'karamooz' => [
			'route' => 'karamooz',
			'label' => __( 'کارآموز وکالت', 'hello-elementor' ),
		],
	];
}

/**
 * @param string $route Public route segment (paye-yek, …).
 * @return string Internal slug (p1, …) or empty.
 */
function hovalvakil_lawyer_archive_grade_slug_from_route( string $route ): string {
	$route = sanitize_title( $route );
	if ( '' === $route ) {
		return '';
	}
	foreach ( hovalvakil_lawyer_archive_grade_map() as $slug => $data ) {
		if ( (string) ( $data['route'] ?? '' ) === $route ) {
			return (string) $slug;
		}
	}
	return '';
}

/**
 * @param string $grade_slug Internal slug: p1 | karamooz.
 * @return string
 */
function hovalvakil_lawyer_archive_grade_url( string $grade_slug ): string {
	$grade_slug = sanitize_key( $grade_slug );
	$map        = hovalvakil_lawyer_archive_grade_map();
	if ( ! isset( $map[ $grade_slug ]['route'] ) ) {
		return (string) get_post_type_archive_link( 'hvl_lawyer' );
	}
	return trailingslashit( home_url( '/' . $map[ $grade_slug ]['route'] ) );
}

/**
 * @param mixed  $ref      Term, ID, or internal slug/name.
 * @param string $taxonomy Taxonomy slug.
 * @return WP_Term|null
 */
function hovalvakil_lawyer_archive_locate_term( $ref, string $taxonomy ): ?WP_Term {
	if ( $ref instanceof WP_Term && ! is_wp_error( $ref ) ) {
		return $ref;
	}
	if ( is_numeric( $ref ) ) {
		$term = get_term( (int) $ref, $taxonomy );
		return ( $term instanceof WP_Term && ! is_wp_error( $term ) ) ? $term : null;
	}
	return hovalvakil_lawyer_archive_resolve_term( (string) $ref, $taxonomy );
}

/**
 * @param string $term_slug Province term slug, name, or term ID string.
 * @return string
 */
function hovalvakil_lawyer_archive_province_url( string $term_slug ): string {
	$term = hovalvakil_lawyer_archive_locate_term( $term_slug, 'hvl_province' );
	if ( ! $term instanceof WP_Term ) {
		return (string) get_post_type_archive_link( 'hvl_lawyer' );
	}
	$route = function_exists( 'hovalvakil_term_get_route_slug' )
		? hovalvakil_term_get_route_slug( $term )
		: sanitize_title( (string) $term->slug );
	if ( '' === $route ) {
		return (string) get_post_type_archive_link( 'hvl_lawyer' );
	}
	return trailingslashit( home_url( '/ostan/' . $route ) );
}

/**
 * @param string $term_slug City term slug, name, or term ID string.
 * @return string
 */
function hovalvakil_lawyer_archive_city_url( string $term_slug ): string {
	$term = hovalvakil_lawyer_archive_locate_term( $term_slug, 'hvl_city' );
	if ( ! $term instanceof WP_Term ) {
		return (string) get_post_type_archive_link( 'hvl_lawyer' );
	}
	$route = function_exists( 'hovalvakil_term_get_route_slug' )
		? hovalvakil_term_get_route_slug( $term )
		: sanitize_title( (string) $term->slug );
	if ( '' === $route ) {
		return (string) get_post_type_archive_link( 'hvl_lawyer' );
	}
	return trailingslashit( home_url( '/shahr/' . $route ) );
}

/**
 * Register rewrite rules + query vars for archive landing pages.
 *
 * @return void
 */
function hovalvakil_lawyer_archive_routes_register(): void {
	foreach ( hovalvakil_lawyer_archive_grade_map() as $data ) {
		$route = preg_quote( (string) ( $data['route'] ?? '' ), '/' );
		if ( '' === $route ) {
			continue;
		}
		add_rewrite_rule(
			'^' . $route . '/page/?([0-9]{1,})/?$',
			'index.php?post_type=hvl_lawyer&hvl_archive_grade=' . $data['route'] . '&paged=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . $route . '/?$',
			'index.php?post_type=hvl_lawyer&hvl_archive_grade=' . $data['route'],
			'top'
		);
	}

	add_rewrite_rule(
		'^ostan/([^/]+)/page/?([0-9]{1,})/?$',
		'index.php?post_type=hvl_lawyer&hvl_archive_province=$matches[1]&paged=$matches[2]',
		'top'
	);
	add_rewrite_rule(
		'^ostan/([^/]+)/?$',
		'index.php?post_type=hvl_lawyer&hvl_archive_province=$matches[1]',
		'top'
	);

	add_rewrite_rule(
		'^shahr/([^/]+)/page/?([0-9]{1,})/?$',
		'index.php?post_type=hvl_lawyer&hvl_archive_city=$matches[1]&paged=$matches[2]',
		'top'
	);
	add_rewrite_rule(
		'^shahr/([^/]+)/?$',
		'index.php?post_type=hvl_lawyer&hvl_archive_city=$matches[1]',
		'top'
	);
}

/**
 * @param string[] $vars Query vars.
 * @return string[]
 */
function hovalvakil_lawyer_archive_routes_query_vars( array $vars ): array {
	$vars[] = 'hvl_archive_grade';
	$vars[] = 'hvl_archive_province';
	$vars[] = 'hvl_archive_city';
	return $vars;
}

function hovalvakil_lawyer_archive_resolve_term( string $raw, string $taxonomy ): ?WP_Term {
	if ( function_exists( 'hovalvakil_term_resolve_by_route' ) ) {
		$term = hovalvakil_term_resolve_by_route( $raw, $taxonomy );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}

	$raw = trim( rawurldecode( $raw ) );
	if ( '' === $raw ) {
		return null;
	}

	$candidates = array_unique(
		array_filter(
			[
				$raw,
				sanitize_title( $raw ),
				sanitize_title( urldecode( $raw ) ),
			]
		)
	);

	foreach ( $candidates as $candidate ) {
		$term = get_term_by( 'slug', $candidate, $taxonomy );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}

	$term = get_term_by( 'name', $raw, $taxonomy );
	return ( $term instanceof WP_Term ) ? $term : null;
}

/**
 * Parse /ostan/... /shahr/... /paye-yek/ paths (also legacy /archive/... before redirect).
 *
 * @return array<string, mixed>|null
 */
function hovalvakil_lawyer_archive_parse_request_path(): ?array {
	$path  = trim( (string) hovalvakil_current_request_slug(), '/' );
	if ( '' === $path ) {
		return null;
	}
	$paged = 1;

	if ( preg_match( '#^(?:archive/)?(ostan|shahr)/([^/]+)/page/([0-9]+)$#', $path, $m ) ) {
		$paged = max( 1, (int) $m[3] );
		$path  = ( str_starts_with( $path, 'archive/' ) ? 'archive/' : '' ) . $m[1] . '/' . $m[2];
	} elseif ( preg_match( '#^(?:archive/)?(paye-yek|karamooz)/page/([0-9]+)$#', $path, $m ) ) {
		$paged = max( 1, (int) $m[2] );
		$path  = ( str_starts_with( $path, 'archive/' ) ? 'archive/' : '' ) . $m[1];
	} elseif ( preg_match( '#^archive/(.+)/page/([0-9]+)$#', $path, $m ) ) {
		$path  = 'archive/' . $m[1];
		$paged = max( 1, (int) $m[2] );
	}

	if ( preg_match( '#^(?:archive/)?ostan/(.+)$#', $path, $m ) ) {
		$term = hovalvakil_lawyer_archive_resolve_term( (string) $m[1], 'hvl_province' );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}
		return [
			'route_type'              => 'province',
			'route_slug'              => (string) $term->slug,
			'grade_internal'          => '',
			'grade_slugs'             => [],
			'province_terms_selected' => [ (string) $term->slug ],
			'city_terms_selected'     => [],
			'base_path'               => hovalvakil_lawyer_archive_province_url( (string) $term->slug ),
			'paged'                   => $paged,
		];
	}

	if ( preg_match( '#^(?:archive/)?shahr/(.+)$#', $path, $m ) ) {
		$term = hovalvakil_lawyer_archive_resolve_term( (string) $m[1], 'hvl_city' );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}
		return [
			'route_type'              => 'city',
			'route_slug'              => (string) $term->slug,
			'grade_internal'          => '',
			'grade_slugs'             => [],
			'province_terms_selected' => [],
			'city_terms_selected'     => [ (string) $term->slug ],
			'base_path'               => hovalvakil_lawyer_archive_city_url( (string) $term->slug ),
			'paged'                   => $paged,
		];
	}

	$grade_segments = [];
	foreach ( hovalvakil_lawyer_archive_grade_map() as $data ) {
		$r = (string) ( $data['route'] ?? '' );
		if ( '' !== $r ) {
			$grade_segments[] = preg_quote( $r, '/' );
		}
	}
	if ( ! empty( $grade_segments ) && preg_match( '#^(?:archive/)?(' . implode( '|', $grade_segments ) . ')$#', $path, $m ) ) {
		$segment  = sanitize_title( (string) $m[1] );
		$internal = hovalvakil_lawyer_archive_grade_slug_from_route( $segment );
		if ( '' === $internal ) {
			return null;
		}
		return [
			'route_type'              => 'grade',
			'route_slug'              => $segment,
			'grade_internal'          => $internal,
			'grade_slugs'             => [ $internal ],
			'province_terms_selected' => [],
			'city_terms_selected'     => [],
			'base_path'               => trailingslashit( home_url( '/' . $segment ) ),
			'paged'                   => $paged,
		];
	}

	return null;
}

/**
 * Serve archive template for clean /archive/... URLs (no rewrite flush required).
 *
 * @return void
 */
function hovalvakil_lawyer_archive_maybe_serve_from_path(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	$parsed = hovalvakil_lawyer_archive_parse_request_path();
	if ( null === $parsed ) {
		return;
	}

	global $wp_query;
	if ( $wp_query instanceof WP_Query ) {
		$wp_query->is_404 = false;
	}

	$GLOBALS['hvl_archive_route_override'] = $parsed;

	if ( ! empty( $parsed['paged'] ) ) {
		set_query_var( 'paged', (int) $parsed['paged'] );
	}

	$template = trailingslashit( get_template_directory() ) . 'archive-hvl_lawyer.php';
	if ( ! is_readable( $template ) ) {
		return;
	}

	status_header( 200 );
	nocache_headers();
	include $template;
	exit;
}

/**
 * 301 redirect removed grade route /paye-do/ (وکیل پایه دو) to main lawyer archive.
 *
 * @return void
 */
function hovalvakil_lawyer_archive_redirect_removed_grade_routes(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! function_exists( 'hovalvakil_current_request_slug' ) ) {
		return;
	}

	$path = trim( (string) hovalvakil_current_request_slug(), '/' );
	if ( ! preg_match( '#^(?:archive/)?paye-do(?:/page/([0-9]+))?/?$#', $path, $m ) ) {
		return;
	}

	$target = (string) get_post_type_archive_link( 'hvl_lawyer' );
	if ( ! empty( $m[1] ) ) {
		$target = trailingslashit( untrailingslashit( $target ) ) . 'page/' . max( 1, (int) $m[1] ) . '/';
	}

	wp_safe_redirect( $target, 301 );
	exit;
}

/**
 * 301 redirect legacy /archive/ostan|shahr|paye-yek/... → /ostan|shahr|paye-yek/...
 *
 * @return void
 */
function hovalvakil_lawyer_archive_redirect_legacy_archive_prefix(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! function_exists( 'hovalvakil_current_request_slug' ) ) {
		return;
	}

	$path  = trim( (string) hovalvakil_current_request_slug(), '/' );
	$paged = 0;
	if ( preg_match( '#^archive/(.+)/page/([0-9]+)$#', $path, $m ) ) {
		$inner = (string) $m[1];
		$paged = max( 1, (int) $m[2] );
	} elseif ( ! str_starts_with( $path, 'archive/' ) ) {
		return;
	} else {
		$inner = substr( $path, strlen( 'archive/' ) );
	}

	if ( '' === $inner ) {
		return;
	}

	$target = trailingslashit( home_url( '/' . $inner ) );
	if ( $paged > 1 ) {
		$target = trailingslashit( untrailingslashit( $target ) ) . 'page/' . $paged . '/';
	}

	wp_safe_redirect( $target, 301 );
	exit;
}

/**
 * 301 redirect /ostan|shahr/{persian-or-old-slug}/ → Latin route slug.
 *
 * @return void
 */
function hovalvakil_lawyer_archive_redirect_non_canonical_path(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! function_exists( 'hovalvakil_current_request_slug' ) || ! function_exists( 'hovalvakil_term_get_route_slug' ) ) {
		return;
	}

	$path    = trim( (string) hovalvakil_current_request_slug(), '/' );
	$paged   = 0;
	$segment = '';
	$type    = '';

	if ( preg_match( '#^(?:archive/)?ostan/(.+)/page/([0-9]+)$#', $path, $m ) ) {
		$segment = (string) $m[1];
		$paged   = max( 1, (int) $m[2] );
		$type    = 'province';
	} elseif ( preg_match( '#^(?:archive/)?ostan/(.+)$#', $path, $m ) ) {
		$segment = (string) $m[1];
		$type    = 'province';
	} elseif ( preg_match( '#^(?:archive/)?shahr/(.+)/page/([0-9]+)$#', $path, $m ) ) {
		$segment = (string) $m[1];
		$paged   = max( 1, (int) $m[2] );
		$type    = 'city';
	} elseif ( preg_match( '#^(?:archive/)?shahr/(.+)$#', $path, $m ) ) {
		$segment = (string) $m[1];
		$type    = 'city';
	} else {
		return;
	}

	$taxonomy = ( 'city' === $type ) ? 'hvl_city' : 'hvl_province';
	$term     = hovalvakil_lawyer_archive_resolve_term( $segment, $taxonomy );
	if ( ! $term instanceof WP_Term ) {
		return;
	}

	$canonical = hovalvakil_term_get_route_slug( $term, false );
	if ( '' === $canonical ) {
		$canonical = hovalvakil_term_get_route_slug( $term, true );
	}

	$segment_decoded = rawurldecode( $segment );
	if ( $segment_decoded === $canonical || sanitize_title( $segment_decoded ) === $canonical ) {
		return;
	}

	$target = ( 'city' === $type )
		? hovalvakil_lawyer_archive_city_url( (string) $term->slug )
		: hovalvakil_lawyer_archive_province_url( (string) $term->slug );

	if ( $paged > 1 ) {
		$target = trailingslashit( untrailingslashit( $target ) ) . 'page/' . $paged . '/';
	}

	wp_safe_redirect( $target, 301 );
	exit;
}

/**
 * Parse current archive route into filter slugs + JS context.
 *
 * @return array{
 *   route_type:string,
 *   route_slug:string,
 *   grade_internal:string,
 *   grade_slugs:string[],
 *   province_terms_selected:string[],
 *   city_terms_selected:string[],
 *   base_path:string,
 *   paged?:int
 * }
 */
function hovalvakil_lawyer_archive_route_context(): array {
	if ( ! empty( $GLOBALS['hvl_archive_route_override'] ) && is_array( $GLOBALS['hvl_archive_route_override'] ) ) {
		return $GLOBALS['hvl_archive_route_override'];
	}

	$empty = [
		'route_type'              => '',
		'route_slug'              => '',
		'grade_internal'          => '',
		'grade_slugs'             => [],
		'province_terms_selected' => [],
		'city_terms_selected'     => [],
		'base_path'               => '',
	];

	$grade_route = sanitize_title( (string) get_query_var( 'hvl_archive_grade' ) );
	if ( '' !== $grade_route ) {
		$internal = hovalvakil_lawyer_archive_grade_slug_from_route( $grade_route );
		if ( '' === $internal ) {
			return $empty;
		}
		return [
			'route_type'              => 'grade',
			'route_slug'              => $grade_route,
			'grade_internal'          => $internal,
			'grade_slugs'             => [ $internal ],
			'province_terms_selected' => [],
			'city_terms_selected'     => [],
			'base_path'               => trailingslashit( home_url( '/' . $grade_route ) ),
		];
	}

	$province_slug = trim( rawurldecode( (string) get_query_var( 'hvl_archive_province' ) ) );
	if ( '' !== $province_slug ) {
		$term = hovalvakil_lawyer_archive_resolve_term( $province_slug, 'hvl_province' );
		if ( ! $term instanceof WP_Term ) {
			return $empty;
		}
		return [
			'route_type'              => 'province',
			'route_slug'              => (string) $term->slug,
			'grade_internal'          => '',
			'grade_slugs'             => [],
			'province_terms_selected' => [ (string) $term->slug ],
			'city_terms_selected'     => [],
			'base_path'               => hovalvakil_lawyer_archive_province_url( (string) $term->slug ),
		];
	}

	$city_slug = trim( rawurldecode( (string) get_query_var( 'hvl_archive_city' ) ) );
	if ( '' !== $city_slug ) {
		$term = hovalvakil_lawyer_archive_resolve_term( $city_slug, 'hvl_city' );
		if ( ! $term instanceof WP_Term ) {
			return $empty;
		}
		return [
			'route_type'              => 'city',
			'route_slug'              => (string) $term->slug,
			'grade_internal'          => '',
			'grade_slugs'             => [],
			'province_terms_selected' => [],
			'city_terms_selected'     => [ (string) $term->slug ],
			'base_path'               => hovalvakil_lawyer_archive_city_url( (string) $term->slug ),
		];
	}

	return $empty;
}

/**
 * Redirect legacy ?grade= / ?province_term[]= URLs to clean paths (single filter only).
 *
 * @return void
 */
function hovalvakil_lawyer_archive_redirect_legacy_query_urls(): void {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! is_post_type_archive( 'hvl_lawyer' ) ) {
		return;
	}
	if ( get_query_var( 'hvl_archive_grade' ) || get_query_var( 'hvl_archive_province' ) || get_query_var( 'hvl_archive_city' ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	$archive     = (string) wp_parse_url( (string) get_post_type_archive_link( 'hvl_lawyer' ), PHP_URL_PATH );
	$path        = untrailingslashit( $path );
	$archive     = untrailingslashit( $archive );
	if ( $path !== $archive ) {
		return;
	}

	$q = isset( $_GET['q'] ) ? trim( (string) wp_unslash( $_GET['q'] ) ) : '';
	if ( '' !== $q ) {
		return;
	}

	$grades = [];
	foreach ( [ 'grade', 'grade[]' ] as $key ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_GET[ $key ] );
		$parts = is_array( $raw ) ? $raw : [ $raw ];
		foreach ( $parts as $part ) {
			$g = sanitize_key( (string) $part );
			if ( in_array( $g, [ 'p1', 'karamooz' ], true ) && ! in_array( $g, $grades, true ) ) {
				$grades[] = $g;
			}
		}
	}

	$provinces = [];
	foreach ( [ 'province_term', 'province_term[]' ] as $key ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_GET[ $key ] );
		$parts = is_array( $raw ) ? $raw : [ $raw ];
	foreach ( $parts as $part ) {
			$term = hovalvakil_lawyer_archive_resolve_term( (string) $part, 'hvl_province' );
			if ( $term instanceof WP_Term && ! in_array( (string) $term->slug, $provinces, true ) ) {
				$provinces[] = (string) $term->slug;
			}
		}
	}

	$cities = [];
	foreach ( [ 'city_term', 'city_term[]' ] as $key ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_GET[ $key ] );
		$parts = is_array( $raw ) ? $raw : [ $raw ];
		foreach ( $parts as $part ) {
			$term = hovalvakil_lawyer_archive_resolve_term( (string) $part, 'hvl_city' );
			if ( $term instanceof WP_Term && ! in_array( (string) $term->slug, $cities, true ) ) {
				$cities[] = (string) $term->slug;
			}
		}
	}

	$filter_sets = (int) ( ! empty( $grades ) ) + (int) ( ! empty( $provinces ) ) + (int) ( ! empty( $cities ) );
	if ( 1 !== $filter_sets ) {
		return;
	}

	if ( 1 === count( $grades ) ) {
		wp_safe_redirect( hovalvakil_lawyer_archive_grade_url( $grades[0] ), 301 );
		exit;
	}
	if ( 1 === count( $provinces ) ) {
		wp_safe_redirect( hovalvakil_lawyer_archive_province_url( $provinces[0] ), 301 );
		exit;
	}
	if ( 1 === count( $cities ) ) {
		wp_safe_redirect( hovalvakil_lawyer_archive_city_url( $cities[0] ), 301 );
		exit;
	}
}

endif;

if ( ! defined( 'HOVALVAKIL_ARCHIVE_ROUTES_HOOKS' ) ) {
	define( 'HOVALVAKIL_ARCHIVE_ROUTES_HOOKS', true );
	add_action( 'init', 'hovalvakil_lawyer_archive_routes_register', 20 );
	add_filter( 'query_vars', 'hovalvakil_lawyer_archive_routes_query_vars' );
	add_action( 'template_redirect', 'hovalvakil_lawyer_archive_redirect_removed_grade_routes', 0 );
	add_action( 'template_redirect', 'hovalvakil_lawyer_archive_redirect_legacy_archive_prefix', 0 );
	add_action( 'template_redirect', 'hovalvakil_lawyer_archive_redirect_non_canonical_path', 1 );
	add_action( 'template_redirect', 'hovalvakil_lawyer_archive_maybe_serve_from_path', 2 );
	add_action( 'template_redirect', 'hovalvakil_lawyer_archive_redirect_legacy_query_urls', 5 );
}
