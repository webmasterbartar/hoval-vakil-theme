<?php
/**
 * Shared lawyer list query: tax filters, strong search, pagination without tiny post__in caps.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var int Chunk size when collecting post IDs for strong search (avoids posts_per_page -1 on huge sets). */
const HOVALVAKIL_LAWYER_ID_BATCH = 500;

/**
 * Bump cache version so lawyer-list transients and term index keys miss.
 *
 * @return void
 */
function hovalvakil_lawyer_bump_cache_version() {
	$v = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
	update_option( 'hvl_lawyer_cache_ver', $v + 1, false );
}

/**
 * Bump version for cached counters that include hvl_reservation (lightweight; does not bust list queries).
 *
 * @return void
 */
function hovalvakil_bump_reservation_stats_version() {
	$v = (int) get_option( 'hvl_reservation_stats_ver', 1 );
	update_option( 'hvl_reservation_stats_ver', $v + 1, false );
}

/**
 * Register invalidation hooks for lawyer list / home counts cache.
 *
 * @return void
 */
function hovalvakil_lawyer_query_register_cache_invalidation() {
	add_action(
		'save_post_hvl_lawyer',
		static function ( $post_id ) {
			if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
				return;
			}
			hovalvakil_lawyer_bump_cache_version();
		}
	);
	add_action(
		'save_post_hvl_center',
		static function ( $post_id ) {
			if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
				return;
			}
			hovalvakil_lawyer_bump_cache_version();
		}
	);
	add_action(
		'save_post_hvl_reservation',
		static function ( $post_id ) {
			if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
				return;
			}
			hovalvakil_bump_reservation_stats_version();
		}
	);
	add_action(
		'deleted_post',
		static function ( $post_id, $post = null ) {
			if ( ! $post_id ) {
				return;
			}
			$pt = '';
			if ( $post instanceof WP_Post ) {
				$pt = $post->post_type;
			}
			if ( '' === $pt ) {
				$pt = (string) get_post_type( $post_id );
			}
			if ( 'hvl_reservation' === $pt ) {
				hovalvakil_bump_reservation_stats_version();
				return;
			}
			if ( 'hvl_lawyer' === $pt || 'hvl_center' === $pt ) {
				hovalvakil_lawyer_bump_cache_version();
			}
		},
		10,
		2
	);
	add_action(
		'set_object_terms',
		static function ( $object_id ) {
			if ( ! $object_id ) {
				return;
			}
			$pt = get_post_type( (int) $object_id );
			if ( 'hvl_lawyer' === $pt || 'hvl_center' === $pt ) {
				hovalvakil_lawyer_bump_cache_version();
			}
		},
		10,
		6
	);
	foreach ( [ 'hvl_city', 'hvl_specialty' ] as $tax ) {
		add_action(
			'edited_term',
			static function ( $term_id, $tt_id, $taxonomy ) use ( $tax ) {
				if ( $taxonomy === $tax ) {
					hovalvakil_lawyer_bump_cache_version();
				}
			},
			10,
			3
		);
		add_action(
			'created_term',
			static function ( $term_id, $tt_id, $taxonomy ) use ( $tax ) {
				if ( $taxonomy === $tax ) {
					hovalvakil_lawyer_bump_cache_version();
				}
			},
			10,
			3
		);
		add_action(
			'delete_term',
			static function ( $term_id, $tt_id, $taxonomy ) use ( $tax ) {
				if ( $taxonomy === $tax ) {
					hovalvakil_lawyer_bump_cache_version();
				}
			},
			10,
			4
		);
	}
}
add_action( 'init', 'hovalvakil_lawyer_query_register_cache_invalidation', 30 );

/**
 * Cached term rows for substring matching in strong search (slug + name).
 *
 * @param string $taxonomy Taxonomy slug.
 * @return array<int, array{slug:string, name:string}>
 */
function hovalvakil_lawyer_cached_term_name_rows( $taxonomy ) {
	$ver  = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
	$key  = 'hvl_termnm_' . sanitize_key( $taxonomy ) . '_' . $ver;
	$data = get_transient( $key );
	if ( false !== $data && is_array( $data ) ) {
		return $data;
	}

	$terms = get_terms(
		[
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		]
	);

	$rows = [];
	if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
		foreach ( $terms as $t ) {
			if ( ! isset( $t->slug, $t->name ) ) {
				continue;
			}
			$rows[] = [
				'slug' => (string) $t->slug,
				'name' => (string) $t->name,
			];
		}
	}

	set_transient( $key, $rows, 30 * MINUTE_IN_SECONDS );
	return $rows;
}

/**
 * Collect term slugs whose name contains $q (case-insensitive, multibyte).
 *
 * @param string $taxonomy Taxonomy.
 * @param string $q      Needle.
 * @return string[]
 */
function hovalvakil_lawyer_term_slugs_matching_query( $taxonomy, $q ) {
	$q = (string) $q;
	if ( '' === $q ) {
		return [];
	}
	$slugs = [];
	foreach ( hovalvakil_lawyer_cached_term_name_rows( $taxonomy ) as $row ) {
		if ( false !== mb_stripos( $row['name'], $q ) ) {
			$slugs[] = $row['slug'];
		}
	}
	return array_values( array_unique( $slugs ) );
}

/**
 * Build tax_query array for lawyer list (same semantics as legacy archive / REST).
 *
 * @param string   $city               City name (backward compat).
 * @param string[] $city_term_slugs    City term slugs.
 * @param string[] $specialty_slugs    Specialty term slugs.
 * @return array<int|string, mixed>
 */
function hovalvakil_lawyer_build_tax_query( $city, array $city_term_slugs, array $specialty_slugs ) {
	$tax_query = [];
	$city      = is_string( $city ) ? trim( $city ) : '';

	if ( '' !== $city ) {
		$tax_query[] = [
			'taxonomy' => 'hvl_city',
			'field'    => 'name',
			'terms'    => $city,
		];
	}

	$city_term_slugs = array_values( array_filter( array_map( 'strval', $city_term_slugs ) ) );
	if ( ! empty( $city_term_slugs ) ) {
		$tax_query[] = [
			'taxonomy' => 'hvl_city',
			'field'    => 'slug',
			'terms'    => $city_term_slugs,
		];
	}

	$specialty_slugs = array_values( array_filter( array_map( 'strval', $specialty_slugs ) ) );
	if ( ! empty( $specialty_slugs ) ) {
		$tax_query[] = [
			'taxonomy' => 'hvl_specialty',
			'field'    => 'slug',
			'terms'    => $specialty_slugs,
		];
	}

	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}

	return $tax_query;
}

/**
 * Base WP_Query args shared by list views (before strong-search branch).
 *
 * @param array<string, mixed> $extra tax_query, for_rest flags, etc.
 * @return array<string, mixed>
 */
function hovalvakil_lawyer_base_list_args( array $extra = [] ) {
	$defaults = [
		'post_type'              => 'hvl_lawyer',
		'post_status'            => 'publish',
		'no_found_rows'          => false,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
	];
	return array_merge( $defaults, $extra );
}

/**
 * Collect all post IDs for a get_posts-style query using paged batches.
 *
 * @param array<string, mixed> $args Base args; posts_per_page/paged overwritten.
 * @return int[]
 */
function hovalvakil_collect_post_ids_batched( array $args ) {
	$args['fields']         = 'ids';
	$args['no_found_rows']  = true;
	$args['posts_per_page'] = HOVALVAKIL_LAWYER_ID_BATCH;

	$all      = [];
	$page     = 1;
	$max_iter = 500;

	while ( $page <= $max_iter ) {
		$args['paged'] = $page;
		$batch         = get_posts( $args );
		if ( empty( $batch ) ) {
			break;
		}
		foreach ( $batch as $id ) {
			$all[] = (int) $id;
		}
		if ( count( $batch ) < HOVALVAKIL_LAWYER_ID_BATCH ) {
			break;
		}
		++$page;
	}

	return array_values( array_unique( array_filter( array_map( 'intval', $all ) ) ) );
}

/**
 * @param array<string, mixed> $args Query args.
 * @return int[]
 */
function hovalvakil_lawyer_collect_post_ids_batched( array $args ) {
	return hovalvakil_collect_post_ids_batched( $args );
}

/**
 * Merge text-search IDs and taxonomy-name-match IDs for strong search.
 *
 * @param array<string, mixed> $filtered_base Base args with tax filters already applied (no s, no post__in).
 * @param string               $q             Search string.
 * @return int[]
 */
function hovalvakil_lawyer_strong_search_all_ids( array $filtered_base, $q ) {
	$q = (string) $q;
	if ( '' === $q ) {
		return [];
	}

	$matched_city_slugs      = hovalvakil_lawyer_term_slugs_matching_query( 'hvl_city', $q );
	$matched_specialty_slugs = hovalvakil_lawyer_term_slugs_matching_query( 'hvl_specialty', $q );

	$text_args = $filtered_base;
	$text_args['s'] = $q;
	unset( $text_args['post__in'], $text_args['orderby'] );
	$text_ids = hovalvakil_lawyer_collect_post_ids_batched( $text_args );

	$tax_match_ids = [];
	if ( ! empty( $matched_city_slugs ) || ! empty( $matched_specialty_slugs ) ) {
		$tax_match_args = $filtered_base;
		unset( $tax_match_args['post__in'], $tax_match_args['orderby'], $tax_match_args['s'] );

		$q_tax = [];
		if ( ! empty( $matched_city_slugs ) ) {
			$q_tax[] = [
				'taxonomy' => 'hvl_city',
				'field'    => 'slug',
				'terms'    => $matched_city_slugs,
			];
		}
		if ( ! empty( $matched_specialty_slugs ) ) {
			$q_tax[] = [
				'taxonomy' => 'hvl_specialty',
				'field'    => 'slug',
				'terms'    => $matched_specialty_slugs,
			];
		}
		if ( count( $q_tax ) > 1 ) {
			$q_tax['relation'] = 'OR';
		}

		if ( ! empty( $tax_match_args['tax_query'] ) ) {
			$tax_match_args['tax_query'] = [
				'relation' => 'AND',
				$tax_match_args['tax_query'],
				$q_tax,
			];
		} else {
			$tax_match_args['tax_query'] = $q_tax;
		}

		$tax_match_ids = hovalvakil_lawyer_collect_post_ids_batched( $tax_match_args );
	}

	$merged = array_values( array_unique( array_merge( $text_ids, $tax_match_ids ) ) );
	if ( function_exists( 'hovalvakil_lawyer_sort_ids_image_first' ) ) {
		return hovalvakil_lawyer_sort_ids_image_first( $merged );
	}
	return $merged;
}

/**
 * Params for hovalvakil_lawyer_get_list_wp_query().
 *
 * @phpstan-type LawyerListParams array{
 *   q?: string,
 *   city?: string,
 *   city_term_slugs?: string[],
 *   specialty_slugs?: string[],
 *   paged?: int,
 *   posts_per_page?: int,
 *   for_rest?: bool
 * }
 */

/**
 * Run lawyer list WP_Query with optional strong search (correct pagination, bounded post__in).
 *
 * @param array $params Params (q, city, city_term_slugs, specialty_slugs, paged, posts_per_page, for_rest).
 * @return WP_Query
 */
function hovalvakil_lawyer_get_list_wp_query( array $params ) {
	$defaults = [
		'q'                => '',
		'city'             => '',
		'city_term_slugs'  => [],
		'specialty_slugs'  => [],
		'paged'            => 1,
		'posts_per_page'   => 20,
		'for_rest'         => false,
	];
	$params = wp_parse_args( $params, $defaults );

	$q              = (string) $params['q'];
	$city           = (string) $params['city'];
	$city_slugs     = is_array( $params['city_term_slugs'] ) ? $params['city_term_slugs'] : [];
	$specialty_slugs = is_array( $params['specialty_slugs'] ) ? $params['specialty_slugs'] : [];
	$paged          = max( 1, (int) $params['paged'] );
	$per_page       = max( 1, (int) $params['posts_per_page'] );

	$tax_query = hovalvakil_lawyer_build_tax_query( $city, $city_slugs, $specialty_slugs );

	$base_extra = [
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'no_found_rows'  => false,
	];
	if ( ! empty( $tax_query ) ) {
		$base_extra['tax_query'] = $tax_query;
	}
	if ( ! empty( $params['for_rest'] ) ) {
		$base_extra['update_post_meta_cache'] = false;
		$base_extra['update_post_term_cache'] = true;
	}

	$base = hovalvakil_lawyer_base_list_args( $base_extra );

	if ( '' === $q ) {
		$GLOBALS['hovalvakil_lawyer_query_order_image_first'] = true;
		$qobj                                                = new WP_Query( $base );
		unset( $GLOBALS['hovalvakil_lawyer_query_order_image_first'] );
		return $qobj;
	}

	// Strong search: full ID set under filters, then slice page (keeps post__in small).
	$filter_only = [
		'post_type'              => 'hvl_lawyer',
		'post_status'            => 'publish',
		'no_found_rows'          => true,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
	];
	if ( ! empty( $tax_query ) ) {
		$filter_only['tax_query'] = $tax_query;
	}
	if ( ! empty( $params['for_rest'] ) ) {
		$filter_only['update_post_meta_cache'] = false;
		$filter_only['update_post_term_cache'] = true;
	}

	$matched_ids = hovalvakil_lawyer_strong_search_all_ids( $filter_only, $q );
	$total       = count( $matched_ids );

	if ( $total === 0 ) {
		$empty_args = [
			'post_type'              => 'hvl_lawyer',
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $paged,
			'post__in'               => [ 0 ],
			'orderby'                => 'post__in',
			'no_found_rows'          => false,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		];
		if ( ! empty( $tax_query ) ) {
			$empty_args['tax_query'] = $tax_query;
		}
		if ( ! empty( $params['for_rest'] ) ) {
			$empty_args['update_post_meta_cache'] = false;
			$empty_args['update_post_term_cache'] = true;
		}
		$query                = new WP_Query( $empty_args );
		$query->found_posts   = 0;
		$query->max_num_pages = 0;
		return $query;
	}

	$offset   = ( $paged - 1 ) * $per_page;
	$page_ids = array_slice( $matched_ids, $offset, $per_page );

	if ( empty( $page_ids ) ) {
		$max_pages = (int) max( 1, (int) ceil( $total / $per_page ) );
		$out_args  = [
			'post_type'              => 'hvl_lawyer',
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => 1,
			'post__in'               => [ 0 ],
			'orderby'                => 'post__in',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		];
		if ( ! empty( $params['for_rest'] ) ) {
			$out_args['update_post_meta_cache'] = false;
			$out_args['update_post_term_cache'] = true;
		}
		$query                = new WP_Query( $out_args );
		$query->found_posts   = $total;
		$query->max_num_pages = $max_pages;
		return $query;
	}

	$qargs = hovalvakil_lawyer_base_list_args(
		[
			'post__in'            => $page_ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $page_ids ),
			'paged'               => 1,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		]
	);
	if ( ! empty( $params['for_rest'] ) ) {
		$qargs['update_post_meta_cache'] = false;
		$qargs['update_post_term_cache'] = true;
	}

	$query                = new WP_Query( $qargs );
	$query->found_posts   = $total;
	$query->max_num_pages = (int) max( 1, (int) ceil( $total / $per_page ) );

	return $query;
}

/**
 * Strong-search candidate IDs for legal centers (text + city name contains q).
 *
 * @param array<string, mixed> $filtered_base post_type hvl_center, tax optional.
 * @param string               $q             Search string.
 * @return int[]
 */
function hovalvakil_center_strong_search_all_ids( array $filtered_base, $q ) {
	$q = (string) $q;
	if ( '' === $q ) {
		return [];
	}

	$matched_city_slugs = hovalvakil_lawyer_term_slugs_matching_query( 'hvl_city', $q );

	$text_args = $filtered_base;
	$text_args['s'] = $q;
	unset( $text_args['post__in'], $text_args['orderby'] );
	$text_ids = hovalvakil_collect_post_ids_batched( $text_args );

	$tax_match_ids = [];
	if ( ! empty( $matched_city_slugs ) ) {
		$tax_match_args = $filtered_base;
		unset( $tax_match_args['post__in'], $tax_match_args['orderby'], $tax_match_args['s'] );

		$q_tax = [
			[
				'taxonomy' => 'hvl_city',
				'field'    => 'slug',
				'terms'    => $matched_city_slugs,
			],
		];
		if ( ! empty( $tax_match_args['tax_query'] ) ) {
			$tax_match_args['tax_query'] = [
				'relation' => 'AND',
				$tax_match_args['tax_query'],
				$q_tax,
			];
		} else {
			$tax_match_args['tax_query'] = $q_tax;
		}

		$tax_match_ids = hovalvakil_collect_post_ids_batched( $tax_match_args );
	}

	return array_values( array_unique( array_merge( $text_ids, $tax_match_ids ) ) );
}

/**
 * Center list query (REST / marakez) with scalable strong search.
 *
 * @param array $params q, city_term_ids (int[]), paged, posts_per_page.
 * @return WP_Query
 */
function hovalvakil_center_get_list_wp_query( array $params ) {
	$defaults = [
		'q'               => '',
		'city_term_ids'   => [],
		'paged'           => 1,
		'posts_per_page'  => 8,
	];
	$params = wp_parse_args( $params, $defaults );

	$q              = (string) $params['q'];
	$city_term_ids  = is_array( $params['city_term_ids'] ) ? array_values( array_unique( array_filter( array_map( 'intval', $params['city_term_ids'] ) ) ) ) : [];
	$paged          = max( 1, (int) $params['paged'] );
	$per_page       = max( 1, (int) $params['posts_per_page'] );

	$tax_query = [];
	if ( ! empty( $city_term_ids ) ) {
		$tax_query = [
			[
				'taxonomy' => 'hvl_city',
				'field'    => 'term_id',
				'terms'    => $city_term_ids,
			],
		];
	}

	$base = [
		'post_type'              => 'hvl_center',
		'post_status'            => 'publish',
		'posts_per_page'         => $per_page,
		'paged'                  => $paged,
		'no_found_rows'          => false,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => true,
	];
	if ( ! empty( $tax_query ) ) {
		$base['tax_query'] = $tax_query;
	}

	if ( '' === $q ) {
		return new WP_Query( $base );
	}

	$filter_only = [
		'post_type'              => 'hvl_center',
		'post_status'            => 'publish',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => true,
	];
	if ( ! empty( $tax_query ) ) {
		$filter_only['tax_query'] = $tax_query;
	}

	$matched_ids = hovalvakil_center_strong_search_all_ids( $filter_only, $q );
	$total       = count( $matched_ids );

	if ( $total === 0 ) {
		$empty_args = [
			'post_type'              => 'hvl_center',
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $paged,
			'post__in'               => [ 0 ],
			'orderby'                => 'post__in',
			'no_found_rows'          => false,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		];
		if ( ! empty( $tax_query ) ) {
			$empty_args['tax_query'] = $tax_query;
		}
		$query                = new WP_Query( $empty_args );
		$query->found_posts   = 0;
		$query->max_num_pages = 0;
		return $query;
	}

	$offset   = ( $paged - 1 ) * $per_page;
	$page_ids = array_slice( $matched_ids, $offset, $per_page );

	if ( empty( $page_ids ) ) {
		$max_pages = (int) max( 1, (int) ceil( $total / $per_page ) );
		$out_args  = [
			'post_type'              => 'hvl_center',
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => 1,
			'post__in'               => [ 0 ],
			'orderby'                => 'post__in',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		];
		$query                = new WP_Query( $out_args );
		$query->found_posts   = $total;
		$query->max_num_pages = $max_pages;
		return $query;
	}

	$qargs = [
		'post_type'              => 'hvl_center',
		'post_status'            => 'publish',
		'post__in'               => $page_ids,
		'orderby'                => 'post__in',
		'posts_per_page'         => count( $page_ids ),
		'paged'                  => 1,
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => true,
	];

	$query                = new WP_Query( $qargs );
	$query->found_posts   = $total;
	$query->max_num_pages = (int) max( 1, (int) ceil( $total / $per_page ) );

	return $query;
}

/**
 * Transient cache TTL for GET /centers JSON (seconds).
 *
 * @return int
 */
function hovalvakil_center_rest_list_cache_ttl() {
	return (int) apply_filters( 'hovalvakil_center_rest_list_cache_ttl', 3 * MINUTE_IN_SECONDS );
}

/**
 * Cache key for GET /centers.
 *
 * @param array<string, mixed> $parts Stable parts.
 * @return string
 */
function hovalvakil_center_rest_cache_key( array $parts ) {
	$ver = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
	return 'hvl_rcnt_' . md5( wp_json_encode( $parts, JSON_UNESCAPED_UNICODE ) . '|' . $ver );
}

/**
 * Published center counts per city term (term_id => count). Cached with hvl_lawyer_cache_ver.
 *
 * @return array<int, int>
 */
function hovalvakil_center_get_city_count_map() {
	$skip_cache = is_user_logged_in() && current_user_can( 'edit_posts' );
	$enabled    = (bool) apply_filters( 'hovalvakil_center_city_counts_cache_enabled', true );

	if ( $enabled && ! $skip_cache ) {
		$ver = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
		$key = 'hvl_ccntcity_' . $ver;
		$cached = get_transient( $key );
		if ( false !== $cached && is_array( $cached ) ) {
			$out = [];
			foreach ( $cached as $k => $v ) {
				$out[ (int) $k ] = (int) $v;
			}
			return $out;
		}
	}

	global $wpdb;
	$map   = [];
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.term_id, COUNT(DISTINCT p.ID) AS center_count
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
			WHERE p.post_type = %s AND p.post_status = %s
			GROUP BY tt.term_id",
			'hvl_city',
			'hvl_center',
			'publish'
		),
		ARRAY_A
	);
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$map[ (int) $row['term_id'] ] = (int) $row['center_count'];
		}
	}

	if ( $enabled && ! $skip_cache ) {
		$ver = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
		$key = 'hvl_ccntcity_' . $ver;
		$to_store = [];
		foreach ( $map as $k => $v ) {
			$to_store[ (string) $k ] = $v;
		}
		set_transient( $key, $to_store, 30 * MINUTE_IN_SECONDS );
	}

	return $map;
}

/**
 * Transient cache TTL for GET /lawyers JSON (seconds).
 *
 * @return int
 */
function hovalvakil_lawyer_rest_list_cache_ttl() {
	return (int) apply_filters( 'hovalvakil_lawyer_rest_list_cache_ttl', 3 * MINUTE_IN_SECONDS );
}

/**
 * Normalize REST-ish params for cache key.
 *
 * @param array<string, mixed> $parts Stable ordered parts.
 * @return string
 */
function hovalvakil_lawyer_rest_cache_key( array $parts ) {
	$ver = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
	return 'hvl_rlaw_' . md5( wp_json_encode( $parts, JSON_UNESCAPED_UNICODE ) . '|' . $ver );
}

/**
 * @return int
 */
function hovalvakil_rest_specialties_cache_ttl() {
	return (int) apply_filters( 'hovalvakil_rest_specialties_cache_ttl', 5 * MINUTE_IN_SECONDS );
}

/**
 * Transient key for GET /specialties JSON (invalidates with hvl_lawyer_cache_ver).
 *
 * @return string
 */
function hovalvakil_rest_specialties_cache_key() {
	$ver = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
	return 'hvl_rspec_' . (string) $ver;
}

/**
 * Published lawyer counts per hvl_specialty term_id.
 *
 * @return array<int, int>
 */
function hovalvakil_lawyer_get_specialty_lawyer_counts() {
	$maps = hovalvakil_lawyer_get_home_lawyer_count_maps();
	$spec = isset( $maps['specialty'] ) && is_array( $maps['specialty'] ) ? $maps['specialty'] : [];
	$out  = [];
	foreach ( $spec as $k => $v ) {
		$out[ (int) $k ] = (int) $v;
	}
	return $out;
}

/**
 * Lawyer counts per taxonomy term for home page (only hvl_lawyer posts).
 *
 * @return array{city: array<int, int>, specialty: array<int, int>}
 */
function hovalvakil_lawyer_get_home_lawyer_count_maps() {
	$skip_cache = is_user_logged_in() && current_user_can( 'edit_posts' );
	$enabled    = (bool) apply_filters( 'hovalvakil_home_lawyer_counts_cache_enabled', true );

	if ( $enabled && ! $skip_cache ) {
		$ver = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
		$key = 'hvl_homecnt_' . $ver;
		$cached = get_transient( $key );
		if ( false !== $cached && is_array( $cached ) && isset( $cached['city'], $cached['specialty'] ) && is_array( $cached['city'] ) && is_array( $cached['specialty'] ) ) {
			return $cached;
		}
	}

	global $wpdb;

	$city_map = [];
	$count_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.term_id, COUNT(DISTINCT p.ID) AS lawyer_count
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
			WHERE p.post_type = %s AND p.post_status = %s
			GROUP BY tt.term_id",
			'hvl_city',
			'hvl_lawyer',
			'publish'
		),
		ARRAY_A
	);
	if ( is_array( $count_rows ) ) {
		foreach ( $count_rows as $row ) {
			$city_map[ (int) $row['term_id'] ] = (int) $row['lawyer_count'];
		}
	}

	$spec_map = [];
	$spec_count_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.term_id, COUNT(DISTINCT p.ID) AS lawyer_count
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
			WHERE p.post_type = %s AND p.post_status = %s
			GROUP BY tt.term_id",
			'hvl_specialty',
			'hvl_lawyer',
			'publish'
		),
		ARRAY_A
	);
	if ( is_array( $spec_count_rows ) ) {
		foreach ( $spec_count_rows as $row ) {
			$spec_map[ (int) $row['term_id'] ] = (int) $row['lawyer_count'];
		}
	}

	$out = [
		'city'      => $city_map,
		'specialty' => $spec_map,
	];

	if ( $enabled && ! $skip_cache ) {
		$ver = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
		$key = 'hvl_homecnt_' . $ver;
		set_transient( $key, $out, 30 * MINUTE_IN_SECONDS );
	}

	return $out;
}

/**
 * Takha page hero stats (lawyers, cities, reservations, specialty terms).
 * Cached briefly; invalidated via hvl_lawyer_cache_ver and hvl_reservation_stats_ver.
 *
 * @return array{
 *   lawyers: int,
 *   cities: int,
 *   reservations: int,
 *   specialties: int
 * }
 */
function hovalvakil_takha_get_header_stats() {
	$enabled    = (bool) apply_filters( 'hovalvakil_takha_header_stats_cache_enabled', true );
	$skip_cache = is_user_logged_in() && current_user_can( 'edit_posts' );

	$lawyer_ver = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
	$res_ver    = (int) get_option( 'hvl_reservation_stats_ver', 1 );
	$key        = 'hvl_takhead_' . $lawyer_ver . '_' . $res_ver;

	if ( $enabled && ! $skip_cache ) {
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['lawyers'], $cached['cities'], $cached['reservations'], $cached['specialties'] ) ) {
			return $cached;
		}
	}

	$lawyers_counts = wp_count_posts( 'hvl_lawyer' );
	$res_counts     = wp_count_posts( 'hvl_reservation' );

	$out = [
		'lawyers'       => isset( $lawyers_counts->publish ) ? (int) $lawyers_counts->publish : 0,
		'cities'        => (int) wp_count_terms(
			[
				'taxonomy'   => 'hvl_city',
				'hide_empty' => false,
			]
		),
		'reservations' => isset( $res_counts->publish ) ? (int) $res_counts->publish : 0,
		'specialties'  => (int) wp_count_terms(
			[
				'taxonomy'   => 'hvl_specialty',
				'hide_empty' => false,
			]
		),
	];

	if ( $enabled && ! $skip_cache ) {
		set_transient( $key, $out, 15 * MINUTE_IN_SECONDS );
	}

	return $out;
}

/**
 * Published hvl_center count for Marakez page (cached with lawyer cache version).
 *
 * @return int
 */
function hovalvakil_marakez_get_centers_publish_count() {
	$enabled    = (bool) apply_filters( 'hovalvakil_marakez_centers_count_cache_enabled', true );
	$skip_cache = is_user_logged_in() && current_user_can( 'edit_posts' );

	$ver = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
	$key = 'hvl_mkzcnt_' . $ver;

	if ( $enabled && ! $skip_cache ) {
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}
	}

	$counts = wp_count_posts( 'hvl_center' );
	$n      = isset( $counts->publish ) ? (int) $counts->publish : 0;

	if ( $enabled && ! $skip_cache ) {
		set_transient( $key, $n, 30 * MINUTE_IN_SECONDS );
	}

	return $n;
}

/**
 * Build WP_Query for Marakez initial list from cached first-page IDs (same meta as center list queries).
 *
 * @param int[] $ids      Post IDs in display order.
 * @param int   $total    found_posts for full unfiltered list.
 * @param int   $per_page Page size (used for max_num_pages).
 * @return WP_Query
 */
function hovalvakil_marakez_build_initial_centers_wp_query( array $ids, $total, $per_page ) {
	$total    = (int) $total;
	$per_page = max( 1, (int) $per_page );

	if ( $total < 1 ) {
		$out = new WP_Query(
			[
				'post_type'              => 'hvl_center',
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => 1,
				'post__in'               => [ 0 ],
				'orderby'                => 'post__in',
				'no_found_rows'          => false,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
			]
		);
		$out->found_posts   = 0;
		$out->max_num_pages = 0;
		return $out;
	}

	if ( empty( $ids ) ) {
		return hovalvakil_center_get_list_wp_query(
			[
				'q'              => '',
				'city_term_ids'  => [],
				'paged'          => 1,
				'posts_per_page' => $per_page,
			]
		);
	}

	$out = new WP_Query(
		[
			'post_type'              => 'hvl_center',
			'post_status'            => 'publish',
			'post__in'               => $ids,
			'orderby'                => 'post__in',
			'posts_per_page'         => count( $ids ),
			'paged'                  => 1,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		]
	);
	$out->found_posts   = $total;
	$out->max_num_pages = (int) max( 1, (int) ceil( $total / $per_page ) );

	return $out;
}

/**
 * Marakez page: first page of published centers (no q / city filter), aligned with hovalvakil_center_get_list_wp_query().
 * Transient stores ordered IDs + total; invalidates with hvl_lawyer_cache_ver.
 *
 * @param int $per_page Posts per page (template uses 12).
 * @return WP_Query
 */
function hovalvakil_marakez_get_initial_centers_wp_query( $per_page = 12 ) {
	$per_page   = max( 1, (int) $per_page );
	$enabled    = (bool) apply_filters( 'hovalvakil_marakez_initial_centers_query_cache_enabled', true );
	$skip_cache = is_user_logged_in() && current_user_can( 'edit_posts' );
	$ver        = (int) get_option( 'hvl_lawyer_cache_ver', 1 );
	$key        = 'hvl_mzini_' . $ver . '_' . $per_page;
	$ttl        = (int) apply_filters( 'hovalvakil_marakez_initial_centers_query_cache_ttl', 15 * MINUTE_IN_SECONDS );

	$list_params = [
		'q'              => '',
		'city_term_ids'  => [],
		'paged'          => 1,
		'posts_per_page' => $per_page,
	];

	if ( $enabled && ! $skip_cache ) {
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['total'], $cached['ids'] ) && is_array( $cached['ids'] ) ) {
			$total = (int) $cached['total'];
			$ids   = array_values( array_map( 'intval', $cached['ids'] ) );
			$ok    = ( $total === 0 && empty( $ids ) ) || ( $total > 0 && ! empty( $ids ) );
			if ( $ok ) {
				if ( $total > 0 && count( $ids ) > $per_page ) {
					$ids = array_slice( $ids, 0, $per_page );
				}
				return hovalvakil_marakez_build_initial_centers_wp_query( $ids, $total, $per_page );
			}
		}
	}

	$query = hovalvakil_center_get_list_wp_query( $list_params );

	if ( $enabled && ! $skip_cache ) {
		$ids = [];
		if ( ! empty( $query->posts ) && is_array( $query->posts ) ) {
			$ids = array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) );
		}
		set_transient(
			$key,
			[
				'total' => (int) $query->found_posts,
				'ids'   => $ids,
			],
			$ttl
		);
	}

	return $query;
}
