<?php
/**
 * Home page lawyer grid: Tehran-only list + admin-defined pin order.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string Option key: ordered post IDs pinned at top of home grid. */
const HOVALVAKIL_HOME_GRID_ORDER_OPTION = 'hvl_home_grid_pinned_ids';

/** @var string Bump this when the grid composition logic changes (busts transients). */
const HOVALVAKIL_HOME_GRID_CODE_VERSION = '2026-05-25-tehran-city-photo-v3';

/**
 * Province route slugs for the home grid (default: Tehran).
 *
 * @return string[]
 */
function hovalvakil_home_grid_province_keys(): array {
	$keys = [ 'tehran' ];
	/**
	 * @param string[] $keys Province slugs (e.g. tehran).
	 */
	return apply_filters( 'hovalvakil_home_grid_province_keys', $keys );
}

/**
 * City route slugs for the home grid (default: Tehran city). Empty = no city restriction.
 *
 * @return string[]
 */
function hovalvakil_home_grid_city_keys(): array {
	$keys = [ 'tehran' ];
	/**
	 * @param string[] $keys City slugs.
	 */
	return apply_filters( 'hovalvakil_home_grid_city_keys', $keys );
}

/**
 * Primary province slug for REST/JS (first key).
 *
 * @return string
 */
function hovalvakil_home_grid_primary_province_slug(): string {
	$keys = hovalvakil_home_grid_province_keys();
	return isset( $keys[0] ) ? sanitize_title( (string) $keys[0] ) : 'tehran';
}

/**
 * @return string
 */
function hovalvakil_home_grid_primary_city_slug(): string {
	$keys = hovalvakil_home_grid_city_keys();
	return isset( $keys[0] ) ? sanitize_title( (string) $keys[0] ) : '';
}

/**
 * Resolve a list of city term IDs (by slug or Persian name) within the home province.
 *
 * @return int[]
 */
function hovalvakil_home_grid_resolve_city_term_ids(): array {
	$cities = hovalvakil_home_grid_city_keys();
	if ( empty( $cities ) ) {
		return [];
	}
	$ids = [];
	foreach ( $cities as $needle ) {
		$needle = (string) $needle;
		if ( '' === $needle ) {
			continue;
		}
		$term = get_term_by( 'slug', $needle, 'hvl_city' );
		if ( ! $term instanceof WP_Term ) {
			$term = get_term_by( 'name', $needle, 'hvl_city' );
		}
		if ( ! $term instanceof WP_Term ) {
			$term = get_term_by( 'name', 'تهران', 'hvl_city' );
		}
		if ( $term instanceof WP_Term ) {
			$ids[] = (int) $term->term_id;
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * @return int[]
 */
function hovalvakil_home_grid_get_pinned_ids(): array {
	$raw = get_option( HOVALVAKIL_HOME_GRID_ORDER_OPTION, [] );
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$out = [];
	foreach ( $raw as $id ) {
		$id = (int) $id;
		if ( $id > 0 && ! in_array( $id, $out, true ) ) {
			$out[] = $id;
		}
	}
	return $out;
}

/**
 * @param int[] $ids Post IDs in display order (top first).
 * @return void
 */
function hovalvakil_home_grid_save_pinned_ids( array $ids ): void {
	$clean = [];
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 && ! in_array( $id, $clean, true ) ) {
			$clean[] = $id;
		}
	}
	update_option( HOVALVAKIL_HOME_GRID_ORDER_OPTION, $clean, false );
	$ver = (int) get_option( 'hvl_home_grid_order_ver', 1 );
	update_option( 'hvl_home_grid_order_ver', $ver + 1, false );
}

/**
 * Bump cache used by ordered-ID transients.
 *
 * @return void
 */
function hovalvakil_home_grid_bump_order_cache(): void {
	$ver = (int) get_option( 'hvl_home_grid_order_ver', 1 );
	update_option( 'hvl_home_grid_order_ver', $ver + 1, false );
}

/**
 * Put pinned IDs first (only if present in $ids); keep relative order of the rest.
 *
 * @param int[]      $ids    Base ordered IDs.
 * @param int[]|null $pinned Pinned IDs or null to read option.
 * @return int[]
 */
function hovalvakil_home_grid_apply_pin_order( array $ids, ?array $pinned = null ): array {
	$pinned = null !== $pinned ? $pinned : hovalvakil_home_grid_get_pinned_ids();
	if ( empty( $pinned ) ) {
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	$ids_map = [];
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$ids_map[ $id ] = true;
		}
	}

	$pinned_valid = [];
	foreach ( $pinned as $pid ) {
		$pid = (int) $pid;
		if ( $pid > 0 && isset( $ids_map[ $pid ] ) && ! in_array( $pid, $pinned_valid, true ) ) {
			$pinned_valid[] = $pid;
		}
	}

	$rest = [];
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 && ! in_array( $id, $pinned_valid, true ) ) {
			$rest[] = $id;
		}
	}

	return array_merge( $pinned_valid, $rest );
}

/**
 * Collect published lawyer IDs for home grid (Tehran + with-own-photo + optional grade).
 *
 * Lawyers without a real image (no _thumbnail_id and no hvl_photo_url) are excluded so the
 * home grid never shows placeholder avatars.
 *
 * @param string[] $grade_slugs p1 | karamooz.
 * @return int[]
 */
function hovalvakil_home_grid_collect_base_ids( array $grade_slugs = [] ): array {
	$args = [
		'post_type'              => 'hvl_lawyer',
		'post_status'            => 'publish',
		'fields'                 => 'ids',
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	];

	if ( ! empty( $grade_slugs ) && function_exists( 'hovalvakil_lawyer_grade_meta_query_for_slugs' ) ) {
		$meta = hovalvakil_lawyer_grade_meta_query_for_slugs( $grade_slugs );
		if ( is_array( $meta ) && ! empty( $meta ) ) {
			$args['meta_query'] = $meta;
		}
	}

	$city_term_ids = hovalvakil_home_grid_resolve_city_term_ids();
	if ( ! empty( $city_term_ids ) ) {
		$args['tax_query'] = [
			[
				'taxonomy'         => 'hvl_city',
				'field'            => 'term_id',
				'terms'            => $city_term_ids,
				'include_children' => false,
			],
		];
	}

	$enabled = false;
	if ( function_exists( 'hovalvakil_lawyer_enable_province_filter' ) ) {
		$enabled = hovalvakil_lawyer_enable_province_filter( hovalvakil_home_grid_province_keys() );
	}

	$GLOBALS['hovalvakil_lawyer_query_order_image_first'] = true;
	$ids = function_exists( 'hovalvakil_collect_post_ids_batched' )
		? hovalvakil_collect_post_ids_batched( $args )
		: array_map( 'intval', get_posts( $args ) );
	unset( $GLOBALS['hovalvakil_lawyer_query_order_image_first'] );

	if ( $enabled && function_exists( 'hovalvakil_lawyer_disable_province_filter' ) ) {
		hovalvakil_lawyer_disable_province_filter();
	}

	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

	if ( function_exists( 'hovalvakil_lawyer_post_has_listing_image' ) ) {
		$ids = array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return hovalvakil_lawyer_post_has_listing_image( (int) $id );
				}
			)
		);
	}

	return $ids;
}

/**
 * Full home grid order (cached). Pinned admin IDs are prepended (deduped) — even if outside
 * Tehran or photo-less — followed by the photo-only Tehran base list.
 *
 * @param string[] $grade_slugs Grade filter slugs.
 * @return int[]
 */
function hovalvakil_home_grid_ordered_ids( array $grade_slugs = [] ): array {
	$grade_slugs = array_values( array_filter( array_map( 'sanitize_key', $grade_slugs ) ) );
	sort( $grade_slugs );

	$order_ver = (int) get_option( 'hvl_home_grid_order_ver', 1 );
	$list_ver  = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
	$key       = 'hvl_hgrid_' . md5( HOVALVAKIL_HOME_GRID_CODE_VERSION . '|' . $order_ver . '|' . $list_ver . '|' . implode( ',', $grade_slugs ) );

	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return array_values( array_map( 'intval', $cached ) );
	}

	$base    = hovalvakil_home_grid_collect_base_ids( $grade_slugs );
	$pinned  = hovalvakil_home_grid_get_pinned_ids();

	if ( ! empty( $pinned ) && ! empty( $grade_slugs ) ) {
		$pinned = array_values(
			array_filter(
				$pinned,
				static function ( $pid ) use ( $grade_slugs ) {
					return hovalvakil_home_grid_pinned_id_matches_grades( (int) $pid, $grade_slugs );
				}
			)
		);
	}

	$pinned_valid = [];
	foreach ( $pinned as $pid ) {
		$pid = (int) $pid;
		if ( $pid <= 0 || in_array( $pid, $pinned_valid, true ) ) {
			continue;
		}
		$post = get_post( $pid );
		if ( ! $post instanceof WP_Post || 'hvl_lawyer' !== $post->post_type || 'publish' !== $post->post_status ) {
			continue;
		}
		$pinned_valid[] = $pid;
	}

	$rest = [];
	foreach ( $base as $id ) {
		$id = (int) $id;
		if ( $id > 0 && ! in_array( $id, $pinned_valid, true ) ) {
			$rest[] = $id;
		}
	}

	$ids = array_merge( $pinned_valid, $rest );

	set_transient( $key, $ids, 15 * MINUTE_IN_SECONDS );

	return $ids;
}

/**
 * Whether a pinned lawyer has a grade matching any of the given grade slugs.
 *
 * @param int      $post_id     Lawyer post ID.
 * @param string[] $grade_slugs Filter slugs (p1 | karamooz).
 * @return bool
 */
function hovalvakil_home_grid_pinned_id_matches_grades( int $post_id, array $grade_slugs ): bool {
	if ( empty( $grade_slugs ) ) {
		return true;
	}
	$grade = (string) get_post_meta( $post_id, 'hvl_lawyer_grade', true );
	if ( '' === $grade ) {
		return false;
	}
	foreach ( $grade_slugs as $slug ) {
		if ( 'p1' === $slug && false !== mb_strpos( $grade, 'پایه یک' ) ) {
			return true;
		}
		if ( 'karamooz' === $slug && false !== mb_strpos( $grade, 'کارآموز' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * WP_Query for one page of the home grid (Tehran + pin order).
 *
 * @param array<string, mixed> $params posts_per_page, paged, grade_slugs.
 * @return WP_Query
 */
function hovalvakil_home_grid_query( array $params = [] ): WP_Query {
	$page         = max( 1, (int) ( $params['paged'] ?? 1 ) );
	$per_page     = max( 1, (int) ( $params['posts_per_page'] ?? 20 ) );
	$grade_slugs  = isset( $params['grade_slugs'] ) && is_array( $params['grade_slugs'] ) ? $params['grade_slugs'] : [];
	$all_ids      = hovalvakil_home_grid_ordered_ids( $grade_slugs );
	$total        = count( $all_ids );
	$offset       = ( $page - 1 ) * $per_page;
	$page_ids     = array_slice( $all_ids, $offset, $per_page );

	if ( empty( $page_ids ) ) {
		$page_ids = [ 0 ];
	}

	$query = new WP_Query(
		[
			'post_type'              => 'hvl_lawyer',
			'post_status'            => 'publish',
			'post__in'               => $page_ids,
			'orderby'                => 'post__in',
			'posts_per_page'         => count( $page_ids ),
			'paged'                  => 1,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		]
	);

	$query->found_posts   = $total;
	$query->max_num_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

	return $query;
}

/**
 * Whether a lawyer belongs to home-grid province(s).
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function hovalvakil_home_grid_lawyer_in_province( int $post_id ): bool {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return false;
	}
	if ( ! function_exists( 'hovalvakil_lawyer_resolve_province_terms' ) ) {
		return true;
	}
	$terms = hovalvakil_lawyer_resolve_province_terms( hovalvakil_home_grid_province_keys() );
	if ( empty( $terms ) ) {
		return true;
	}
	$allowed = array_map( static fn( $t ) => (int) $t->term_id, $terms );
	$lawyer  = wp_get_object_terms( $post_id, 'hvl_province', [ 'fields' => 'ids' ] );
	if ( is_wp_error( $lawyer ) || ! is_array( $lawyer ) ) {
		return false;
	}
	foreach ( $lawyer as $tid ) {
		if ( in_array( (int) $tid, $allowed, true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Front-page lawyer search (independent of admin search).
 *
 * Single SQL pass — title LIKE + compact title LIKE + license meta LIKE — limited
 * to a small result set. Used ONLY by the home page REST endpoint.
 *
 * @param string $q     Persian / digit query.
 * @param int    $limit Max results (1..100).
 * @return int[]
 */
function hovalvakil_home_search_lawyer_ids( $q, $limit = 60 ) {
	global $wpdb;

	$q = trim( (string) $q );
	if ( '' === $q ) {
		return [];
	}

	$limit = max( 1, min( 100, (int) $limit ) );

	$digits     = function_exists( 'hovalvakil_lawyer_search_digits_key' )
		? hovalvakil_lawyer_search_digits_key( $q )
		: preg_replace( '/\D+/u', '', $q );
	$digit_only = function_exists( 'hovalvakil_lawyer_search_is_digit_primary' )
		? hovalvakil_lawyer_search_is_digit_primary( $q )
		: ( '' !== $digits && strlen( (string) $digits ) === mb_strlen( $q ) );
	$compact    = function_exists( 'hovalvakil_lawyer_search_compact_key' )
		? hovalvakil_lawyer_search_compact_key( $q )
		: preg_replace( '/\s+/u', '', $q );

	$has_text = ! $digit_only && mb_strlen( $q ) >= 2;
	$has_dig  = strlen( (string) $digits ) >= 2;
	if ( ! $has_text && ! $has_dig ) {
		return [];
	}

	$license_keys = function_exists( 'hovalvakil_lawyer_search_license_meta_keys' )
		? hovalvakil_lawyer_search_license_meta_keys()
		: [ 'hvl_license_no', 'hvl_license' ];

	$title_compact_expr = function_exists( 'hovalvakil_lawyer_sql_compact_text_expr' )
		? hovalvakil_lawyer_sql_compact_text_expr( 'p.post_title' )
		: 'p.post_title';
	$digits_expr        = function_exists( 'hovalvakil_lawyer_sql_digits_only_expr' )
		? hovalvakil_lawyer_sql_digits_only_expr( 'pm.meta_value' )
		: 'pm.meta_value';

	$title_or  = [];
	$meta_or   = [];
	$prep_args = [];

	if ( $has_text ) {
		$title_or[]  = 'p.post_title LIKE %s';
		$prep_args[] = '%' . $wpdb->esc_like( $q ) . '%';
		if ( '' !== $compact && $compact !== $q ) {
			$title_or[]  = "{$title_compact_expr} LIKE %s";
			$prep_args[] = '%' . $wpdb->esc_like( $compact ) . '%';
		}
		$meta_or[]   = 'pm.meta_value LIKE %s';
		$prep_args[] = '%' . $wpdb->esc_like( $q ) . '%';
	}

	if ( $has_dig ) {
		if ( $digit_only ) {
			$meta_or[]   = "{$digits_expr} LIKE %s";
			$prep_args[] = $wpdb->esc_like( (string) $digits ) . '%';
			$meta_or[]   = 'pm.meta_value = %s';
			$prep_args[] = (string) $digits;
		} else {
			$meta_or[]   = "{$digits_expr} LIKE %s";
			$prep_args[] = '%' . $wpdb->esc_like( (string) $digits ) . '%';
		}
	}

	$title_sql    = empty( $title_or ) ? '0=1' : '(' . implode( ' OR ', $title_or ) . ')';
	$meta_sql     = empty( $meta_or ) ? '0=1' : '(' . implode( ' OR ', $meta_or ) . ')';
	$placeholders = implode( ',', array_fill( 0, count( $license_keys ), '%s' ) );

	$sql = "
		SELECT DISTINCT p.ID
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm
			ON pm.post_id = p.ID
			AND pm.meta_key IN ({$placeholders})
		WHERE p.post_type = 'hvl_lawyer'
			AND p.post_status = 'publish'
			AND (
				{$title_sql}
				OR ( pm.post_id IS NOT NULL AND {$meta_sql} )
			)
		ORDER BY p.post_date DESC
		LIMIT %d
	";

	$prepared = $wpdb->prepare( $sql, ...array_merge( $license_keys, $prep_args, [ $limit ] ) );
	$ids      = $wpdb->get_col( $prepared );
	$ids      = array_values( array_filter( array_map( 'intval', is_array( $ids ) ? $ids : [] ) ) );

	if ( function_exists( 'hovalvakil_lawyer_sort_ids_image_first' ) ) {
		$ids = hovalvakil_lawyer_sort_ids_image_first( $ids );
	}

	return $ids;
}

/**
 * Validate pinned IDs: published lawyers (any province, any photo state).
 *
 * Admin overrides everything: a pinned lawyer is appended to the grid even if outside
 * Tehran or without a photo. Sanitization only drops missing / non-lawyer / non-published.
 *
 * @param int[] $ids Candidate IDs.
 * @return int[]
 */
function hovalvakil_home_grid_sanitize_pinned_ids( array $ids ): array {
	$out = [];
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id <= 0 || in_array( $id, $out, true ) ) {
			continue;
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || 'hvl_lawyer' !== $post->post_type || 'publish' !== $post->post_status ) {
			continue;
		}
		$out[] = $id;
	}
	return $out;
}
