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
			if ( apply_filters( 'hovalvakil_skip_lawyer_cache_bump_on_save', false, (int) $post_id ) ) {
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
			if ( apply_filters( 'hovalvakil_skip_lawyer_cache_bump_on_save', false, (int) $object_id ) ) {
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
	foreach ( [ 'hvl_city', 'hvl_specialty', 'hvl_province' ] as $tax ) {
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
 * Compact search key: Persian-normalized label with all spaces/ZWNJ removed.
 *
 * @param string $text Input.
 * @return string
 */
function hovalvakil_lawyer_search_compact_key( $text ) {
	$text = (string) $text;
	if ( function_exists( 'hovalvakil_normalize_fa_label' ) ) {
		return hovalvakil_normalize_fa_label( $text );
	}
	$text = trim( $text );
	$text = str_replace(
		[ "\xE2\x80\x8C", ' ', '‌', 'ـ', 'ك', 'ي', 'ة' ],
		[ '', '', '', '', 'ک', 'ی', 'ه' ],
		$text
	);
	return $text;
}

/**
 * Digits-only key for license search (ASCII).
 *
 * @param string $text Input.
 * @return string
 */
function hovalvakil_lawyer_search_digits_key( $text ) {
	if ( function_exists( 'hovalvakil_lawyer_digits_to_ascii' ) ) {
		return hovalvakil_lawyer_digits_to_ascii( $text );
	}
	return preg_replace( '/\D+/u', '', (string) $text ) ?? '';
}

/**
 * Meta keys that may hold lawyer license / external file numbers.
 *
 * @return string[]
 */
function hovalvakil_lawyer_search_license_meta_keys() {
	return [ 'hvl_license_no', 'hvl_license', 'hvl_import_external_id' ];
}

/**
 * True when query is essentially a number (license search), not a name.
 *
 * @param string $q Query.
 * @return bool
 */
function hovalvakil_lawyer_search_is_digit_primary( $q ) {
	$q = trim( (string) $q );
	if ( '' === $q ) {
		return false;
	}
	$digits = hovalvakil_lawyer_search_digits_key( $q );
	if ( strlen( $digits ) < 2 ) {
		return false;
	}
	$rest = preg_replace( '/[0-9۰-۹٠-٩\/\-\s\.,،‌_\x{200c}\x{00a0}]+/u', '', $q );
	return '' === trim( (string) $rest );
}

function hovalvakil_lawyer_search_is_license_like( $q ) {
	$q      = trim( (string) $q );
	$digits = hovalvakil_lawyer_search_digits_key( $q );
	$len    = mb_strlen( $q );
	if ( strlen( $digits ) < 3 || $len < 1 ) {
		return false;
	}
	return strlen( $digits ) >= (int) ceil( $len * 0.45 );
}

/**
 * Whether a search string should trigger strong search (name ≥2 chars or license ≥3 digits).
 *
 * @param string $q Query.
 * @return bool
 */
function hovalvakil_lawyer_search_query_is_usable( $q ) {
	$q = trim( (string) $q );
	if ( '' === $q ) {
		return false;
	}
	$digits = hovalvakil_lawyer_search_digits_key( $q );
	$rest   = preg_replace( '/[0-9۰-۹٠-٩\/\-\s\.,،‌_\x{200c}\x{00a0}]+/u', '', $q );
	if ( '' === trim( (string) $rest ) && strlen( $digits ) > 0 ) {
		return strlen( $digits ) >= 2;
	}
	if ( mb_strlen( $q ) >= 2 ) {
		return true;
	}
	return strlen( $digits ) >= 2;
}

/**
 * SQL expression: strip separators and normalize Persian/Arabic digits to ASCII in a column.
 *
 * @param string $column SQL column reference.
 * @return string
 */
function hovalvakil_lawyer_sql_digits_only_expr( $column ) {
	$expr = $column;
	foreach (
		[
			'۰' => '0',
			'۱' => '1',
			'۲' => '2',
			'۳' => '3',
			'۴' => '4',
			'۵' => '5',
			'۶' => '6',
			'۷' => '7',
			'۸' => '8',
			'۹' => '9',
			'٠' => '0',
			'١' => '1',
			'٢' => '2',
			'٣' => '3',
			'٤' => '4',
			'٥' => '5',
			'٦' => '6',
			'٧' => '7',
			'٨' => '8',
			'٩' => '9',
		] as $from => $to
	) {
		$expr = "REPLACE({$expr}, '{$from}', '{$to}')";
	}
	foreach ( [ '/', '-', ' ', '‌', "\xE2\x80\x8C", '_', '.', ',', '،' ] as $sep ) {
		$expr = "REPLACE({$expr}, '{$sep}', '')";
	}
	return $expr;
}

/**
 * SQL expression: compact Persian text column (spaces/ZWNJ stripped, ي→ی).
 *
 * @param string $column SQL column reference.
 * @return string
 */
function hovalvakil_lawyer_sql_compact_text_expr( $column ) {
	$expr = $column;
	foreach (
		[
			[ ' ', '' ],
			[ '‌', '' ],
			[ "\xE2\x80\x8C", '' ],
			[ 'ـ', '' ],
			[ 'ي', 'ی' ],
			[ 'ك', 'ک' ],
			[ 'ة', 'ه' ],
		] as $pair
	) {
		$from = $pair[0];
		$to   = $pair[1];
		$expr = "REPLACE({$expr}, '{$from}', '{$to}')";
	}
	return $expr;
}

/**
 * @param string   $where WHERE clause.
 * @param WP_Query $query Query.
 * @return string
 */
function hovalvakil_lawyer_posts_where_compact_title_search( $where, $query ) {
	$needle = isset( $GLOBALS['hovalvakil_lawyer_compact_search_needle'] )
		? (string) $GLOBALS['hovalvakil_lawyer_compact_search_needle']
		: '';
	if ( '' === $needle || ! $query instanceof WP_Query ) {
		return $where;
	}
	$pt = $query->get( 'post_type' );
	if ( 'hvl_lawyer' !== $pt && ( ! is_array( $pt ) || ! in_array( 'hvl_lawyer', $pt, true ) ) ) {
		return $where;
	}
	global $wpdb;
	$expr  = hovalvakil_lawyer_sql_compact_text_expr( "{$wpdb->posts}.post_title" );
	$where .= $wpdb->prepare( " AND ( {$expr} LIKE %s )", '%' . $wpdb->esc_like( $needle ) . '%' );
	return $where;
}

/**
 * @param string   $where WHERE clause.
 * @param WP_Query $query Query.
 * @return string
 */
function hovalvakil_lawyer_posts_where_license_meta_search( $where, $query ) {
	$raw    = isset( $GLOBALS['hovalvakil_lawyer_license_search_raw'] )
		? trim( (string) $GLOBALS['hovalvakil_lawyer_license_search_raw'] )
		: '';
	$digits = isset( $GLOBALS['hovalvakil_lawyer_license_search_digits'] )
		? (string) $GLOBALS['hovalvakil_lawyer_license_search_digits']
		: '';

	if ( ( '' === $raw && strlen( $digits ) < 2 ) || ! $query instanceof WP_Query ) {
		return $where;
	}
	$pt = $query->get( 'post_type' );
	if ( 'hvl_lawyer' !== $pt && ( ! is_array( $pt ) || ! in_array( 'hvl_lawyer', $pt, true ) ) ) {
		return $where;
	}

	global $wpdb;
	$keys    = hovalvakil_lawyer_search_license_meta_keys();
	$ors     = [];
	$norm    = hovalvakil_lawyer_sql_digits_only_expr( 'pm.meta_value' );

	if ( mb_strlen( $raw ) >= 2 ) {
		$ors[] = $wpdb->prepare( 'pm.meta_value LIKE %s', '%' . $wpdb->esc_like( $raw ) . '%' );
		if ( function_exists( 'hovalvakil_lawyer_digits_to_fa' ) ) {
			$fa_raw = hovalvakil_lawyer_digits_to_fa( $raw );
			if ( $fa_raw !== $raw ) {
				$ors[] = $wpdb->prepare( 'pm.meta_value LIKE %s', '%' . $wpdb->esc_like( $fa_raw ) . '%' );
			}
		}
	}
	if ( strlen( $digits ) >= 2 ) {
		$ors[] = $wpdb->prepare( "{$norm} LIKE %s", $wpdb->esc_like( $digits ) . '%' );
		$ors[] = $wpdb->prepare( "{$norm} LIKE %s", '%' . $wpdb->esc_like( $digits ) . '%' );
	}

	if ( empty( $ors ) ) {
		return $where;
	}

	$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
	$sql          = " AND EXISTS (
		SELECT 1 FROM {$wpdb->postmeta} pm
		WHERE pm.post_id = {$wpdb->posts}.ID
		AND pm.meta_key IN ({$placeholders})
		AND (" . implode( ' OR ', $ors ) . ')
	)';
	$where       .= $wpdb->prepare( $sql, ...$keys );
	return $where;
}

/**
 * Fast license lookup via SQL (no full-table WP_Query scan).
 *
 * @param string $q     Search string.
 * @param int    $limit Max IDs.
 * @return int[]
 */
/**
 * Whether normalized license digits for a post contain the needle digit string.
 *
 * @param int    $post_id       Post ID.
 * @param string $needle_digits ASCII digits only.
 * @return bool
 */
function hovalvakil_lawyer_post_license_contains_digits( $post_id, $needle_digits, $prefix_only = false ) {
	$needle_digits = (string) $needle_digits;
	if ( '' === $needle_digits ) {
		return false;
	}
	$post_id = (int) $post_id;
	foreach ( hovalvakil_lawyer_search_license_meta_keys() as $meta_key ) {
		$raw = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
		if ( '' === $raw ) {
			continue;
		}
		$norm = hovalvakil_lawyer_search_digits_key( $raw );
		if ( '' === $norm ) {
			continue;
		}
		if ( $prefix_only ) {
			if ( 0 === strpos( $norm, $needle_digits ) ) {
				return true;
			}
		} elseif ( false !== strpos( $norm, $needle_digits ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Keep only IDs whose license meta actually contains $needle_digits.
 *
 * @param int[]  $ids           Post IDs.
 * @param string $needle_digits ASCII digits.
 * @return int[]
 */
function hovalvakil_lawyer_filter_ids_license_digits( array $ids, $needle_digits, $prefix_only = false ) {
	$needle_digits = (string) $needle_digits;
	if ( '' === $needle_digits ) {
		return [];
	}
	$out = [];
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id > 0 && hovalvakil_lawyer_post_license_contains_digits( $id, $needle_digits, $prefix_only ) ) {
			$out[] = $id;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Restrict ID list to posts that also match tax/meta filters.
 *
 * @param int[]                $ids   Candidate IDs.
 * @param array<string, mixed> $extra WP_Query args (tax_query, meta_query).
 * @return int[]
 */
function hovalvakil_lawyer_filter_post_ids_with_query( array $ids, array $extra ) {
	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( empty( $ids ) ) {
		return [];
	}
	$args = array_merge(
		[
			'post_type'              => 'hvl_lawyer',
			'post_status'            => 'publish',
			'post__in'               => $ids,
			'posts_per_page'         => count( $ids ),
			'orderby'                => 'post__in',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		],
		$extra
	);
	$found = get_posts( $args );
	return is_array( $found ) ? array_values( array_map( 'intval', $found ) ) : [];
}

function hovalvakil_lawyer_license_search_post_ids_direct( $q, $limit = 50 ) {
	global $wpdb;

	$q          = trim( (string) $q );
	$digits     = hovalvakil_lawyer_search_digits_key( $q );
	$digit_only   = hovalvakil_lawyer_search_is_digit_primary( $q );
	$prefix_only  = $digit_only && strlen( $digits ) >= 2;
	if ( '' === $q || ( strlen( $digits ) < 2 && mb_strlen( $q ) < 2 ) ) {
		return [];
	}

	$keys = hovalvakil_lawyer_search_license_meta_keys();
	$ors  = [];
	$norm = hovalvakil_lawyer_sql_digits_only_expr( 'pm.meta_value' );

	// Pure digit queries: only license meta; prefer prefix (102 → 102142).
	if ( ! $digit_only && mb_strlen( $q ) >= 2 ) {
		$ors[] = $wpdb->prepare( 'pm.meta_value LIKE %s', '%' . $wpdb->esc_like( $q ) . '%' );
		if ( function_exists( 'hovalvakil_lawyer_digits_to_fa' ) ) {
			$fa_q = hovalvakil_lawyer_digits_to_fa( $q );
			if ( $fa_q !== $q ) {
				$ors[] = $wpdb->prepare( 'pm.meta_value LIKE %s', '%' . $wpdb->esc_like( $fa_q ) . '%' );
			}
		}
	}
	if ( strlen( $digits ) >= 2 ) {
		if ( $prefix_only ) {
			$ors[] = $wpdb->prepare( "{$norm} LIKE %s", $wpdb->esc_like( $digits ) . '%' );
		} else {
			$ors[] = $wpdb->prepare( "{$norm} LIKE %s", '%' . $wpdb->esc_like( $digits ) . '%' );
		}
		$ors[] = $wpdb->prepare( 'pm.meta_value = %s', $digits );
		if ( function_exists( 'hovalvakil_lawyer_digits_to_fa' ) ) {
			$fa_digits = hovalvakil_lawyer_digits_to_fa( $digits );
			if ( $fa_digits !== $digits ) {
				$ors[] = $wpdb->prepare( 'pm.meta_value = %s', $fa_digits );
				if ( $prefix_only ) {
					$ors[] = $wpdb->prepare( 'pm.meta_value LIKE %s', $wpdb->esc_like( $fa_digits ) . '%' );
				} else {
					$ors[] = $wpdb->prepare( 'pm.meta_value LIKE %s', '%' . $wpdb->esc_like( $fa_digits ) . '%' );
				}
			}
		}
	}

	if ( empty( $ors ) ) {
		return [];
	}

	$limit        = max( 1, min( 500, (int) $limit ) );
	$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
	$sql          = "SELECT DISTINCT pm.post_id
		FROM {$wpdb->postmeta} pm
		INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			AND p.post_type = 'hvl_lawyer'
			AND p.post_status = 'publish'
		WHERE pm.meta_key IN ({$placeholders})
		AND (" . implode( ' OR ', $ors ) . ")
		LIMIT %d";

	$prepared = $wpdb->prepare( $sql, ...array_merge( $keys, [ $limit ] ) );
	$ids      = $wpdb->get_col( $prepared );
	$ids      = array_values( array_filter( array_map( 'intval', is_array( $ids ) ? $ids : [] ) ) );

	if ( strlen( $digits ) >= 2 ) {
		$ids = hovalvakil_lawyer_filter_ids_license_digits( $ids, $digits, $prefix_only );
	}

	return $ids;
}

/**
 * Post IDs matching license / file number meta (formatted or digit-only).
 *
 * @param array<string, mixed> $filtered_base Base query args.
 * @param string               $q             Search string.
 * @return int[]
 */
function hovalvakil_lawyer_strong_search_license_ids( array $filtered_base, $q ) {
	$q = trim( (string) $q );
	if ( '' === $q ) {
		return [];
	}

	$digits = hovalvakil_lawyer_search_digits_key( $q );
	if ( strlen( $digits ) < 2 ) {
		return [];
	}

	$has_extra_filters = ! empty( $filtered_base['tax_query'] ) || ! empty( $filtered_base['meta_query'] );
	if ( ! $has_extra_filters ) {
		return hovalvakil_lawyer_license_search_post_ids_direct( $q, 200 );
	}

	$args = $filtered_base;
	unset( $args['post__in'], $args['orderby'], $args['s'] );

	$GLOBALS['hovalvakil_lawyer_license_search_raw']    = $q;
	$GLOBALS['hovalvakil_lawyer_license_search_digits'] = $digits;
	add_filter( 'posts_where', 'hovalvakil_lawyer_posts_where_license_meta_search', 10, 2 );
	$ids = hovalvakil_lawyer_collect_post_ids_batched( $args );
	remove_filter( 'posts_where', 'hovalvakil_lawyer_posts_where_license_meta_search', 10 );
	unset( $GLOBALS['hovalvakil_lawyer_license_search_raw'], $GLOBALS['hovalvakil_lawyer_license_search_digits'] );

	return $ids;
}

/**
 * Collect term slugs whose name contains $q (case-insensitive, multibyte; also compact/no-space).
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
	$q_compact = hovalvakil_lawyer_search_compact_key( $q );
	$slugs     = [];
	foreach ( hovalvakil_lawyer_cached_term_name_rows( $taxonomy ) as $row ) {
		$name = (string) ( $row['name'] ?? '' );
		if ( false !== mb_stripos( $name, $q ) ) {
			$slugs[] = $row['slug'];
			continue;
		}
		if ( '' !== $q_compact && strlen( $q_compact ) >= 2 ) {
			$name_compact = hovalvakil_lawyer_search_compact_key( $name );
			if ( '' !== $name_compact && false !== mb_stripos( $name_compact, $q_compact ) ) {
				$slugs[] = $row['slug'];
			}
		}
	}
	return array_values( array_unique( $slugs ) );
}

/**
 * Post IDs matching office address / license meta under base filters.
 *
 * @param array<string, mixed> $filtered_base Base query args.
 * @param string               $q             Search string.
 * @return int[]
 */
function hovalvakil_lawyer_strong_search_meta_ids( array $filtered_base, $q ) {
	$q = trim( (string) $q );
	if ( '' === $q ) {
		return [];
	}

	$clauses = [
		[
			'key'     => 'hvl_office_address',
			'value'   => $q,
			'compare' => 'LIKE',
		],
	];

	if ( count( $clauses ) < 1 ) {
		return [];
	}

	$meta_args = $filtered_base;
	unset( $meta_args['post__in'], $meta_args['orderby'], $meta_args['s'] );

	$existing_mq = ! empty( $meta_args['meta_query'] ) && is_array( $meta_args['meta_query'] )
		? $meta_args['meta_query']
		: null;

	$search_mq = $clauses;
	if ( count( $search_mq ) > 1 ) {
		$search_mq = array_merge( [ 'relation' => 'OR' ], $search_mq );
	}

	if ( $existing_mq ) {
		$meta_args['meta_query'] = [
			'relation' => 'AND',
			$existing_mq,
			$search_mq,
		];
	} else {
		$meta_args['meta_query'] = $search_mq;
	}

	return hovalvakil_lawyer_collect_post_ids_batched( $meta_args );
}

/**
 * Post IDs whose title matches compact query (no spaces / half-space).
 *
 * @param array<string, mixed> $filtered_base Base query args.
 * @param string               $q             Search string.
 * @return int[]
 */
function hovalvakil_lawyer_strong_search_compact_title_ids( array $filtered_base, $q ) {
	$compact = hovalvakil_lawyer_search_compact_key( $q );
	if ( strlen( $compact ) < 2 ) {
		return [];
	}

	$args = $filtered_base;
	unset( $args['post__in'], $args['orderby'], $args['s'] );

	$GLOBALS['hovalvakil_lawyer_compact_search_needle'] = $compact;
	add_filter( 'posts_where', 'hovalvakil_lawyer_posts_where_compact_title_search', 10, 2 );
	$ids = hovalvakil_lawyer_collect_post_ids_batched( $args );
	remove_filter( 'posts_where', 'hovalvakil_lawyer_posts_where_compact_title_search', 10 );
	unset( $GLOBALS['hovalvakil_lawyer_compact_search_needle'] );

	return $ids;
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
 * Resolve province keys (slug, route slug tehran, name) to WP_Term objects.
 *
 * @param string[] $province_keys Slugs, route slugs, or names.
 * @return WP_Term[]
 */
function hovalvakil_lawyer_resolve_province_terms( array $province_keys ): array {
	$terms  = [];
	$seen   = [];
	foreach ( $province_keys as $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			continue;
		}
		$term = null;
		if ( function_exists( 'hovalvakil_term_resolve_province_by_route' ) ) {
			$term = hovalvakil_term_resolve_province_by_route( $raw );
		}
		if ( ! $term instanceof WP_Term && function_exists( 'hovalvakil_term_resolve_by_route' ) ) {
			$term = hovalvakil_term_resolve_by_route( $raw, 'hvl_province' );
		}
		if ( ! $term instanceof WP_Term && function_exists( 'hovalvakil_lawyer_archive_resolve_term' ) ) {
			$term = hovalvakil_lawyer_archive_resolve_term( $raw, 'hvl_province' );
		}
		if ( ! $term instanceof WP_Term ) {
			$term = get_term_by( 'slug', $raw, 'hvl_province' );
		}
		if ( ! $term instanceof WP_Term ) {
			$term = get_term_by( 'name', $raw, 'hvl_province' );
		}
		if ( $term instanceof WP_Term && ! isset( $seen[ (int) $term->term_id ] ) ) {
			$seen[ (int) $term->term_id ] = true;
			$terms[]                      = $term;
		}
	}
	return $terms;
}

/**
 * Tax filter for province archive: match hvl_province term_id OR cities linked to that province.
 *
 * @param string[] $province_slugs Province slugs, route slugs, or names.
 * @return array<int|string, mixed>|null Single tax_query clause or null.
 */
function hovalvakil_lawyer_build_province_filter_tax_query( array $province_slugs ) {
	$terms = hovalvakil_lawyer_resolve_province_terms( $province_slugs );
	if ( empty( $terms ) ) {
		return null;
	}

	$term_ids = array_map( static fn( $t ) => (int) $t->term_id, $terms );

	// Province term only; city-in-province is handled in posts_where via hvl_province_id meta (Tehran-safe).
	return [
		'taxonomy'         => 'hvl_province',
		'field'            => 'term_id',
		'terms'            => $term_ids,
		'include_children' => false,
	];
}

/**
 * SQL WHERE fragment: lawyer has province term and/or city in province.
 *
 * @param array{province_ids?:int[],city_ids?:int[]} $ctx Filter context.
 * @return string Empty or AND (...).
 */
function hovalvakil_lawyer_province_filter_sql_where( array $ctx ): string {
	global $wpdb;

	$province_ids = ! empty( $ctx['province_ids'] ) ? array_values( array_map( 'intval', $ctx['province_ids'] ) ) : [];
	$province_ids = array_values( array_filter( $province_ids ) );
	if ( empty( $province_ids ) ) {
		return '';
	}

	$ors      = [];
	$meta_key = defined( 'HOVALVAKIL_CITY_PROVINCE_META' ) ? HOVALVAKIL_CITY_PROVINCE_META : 'hvl_province_id';

	foreach ( $province_ids as $province_id ) {
		$province_id = (int) $province_id;
		$ors[]       = $wpdb->prepare(
			"EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tr.object_id = {$wpdb->posts}.ID
				AND tt.taxonomy = 'hvl_province'
				AND tt.term_id = %d
			)",
			$province_id
		);
		// Cities explicitly linked to this province (avoids huge IN lists that break Tehran).
		$ors[] = $wpdb->prepare(
			"EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'hvl_city'
				INNER JOIN {$wpdb->termmeta} tm ON tm.term_id = tt.term_id AND tm.meta_key = %s AND CAST(tm.meta_value AS UNSIGNED) = %d
				WHERE tr.object_id = {$wpdb->posts}.ID
			)",
			$meta_key,
			$province_id
		);
	}

	return ' AND (' . implode( ' OR ', $ors ) . ')';
}

/**
 * @param string[]|int[] $province_keys Slugs, route slugs, names, or term IDs.
 * @return array{province_ids:int[],city_ids:int[]}|null
 */
function hovalvakil_lawyer_province_filter_context( array $province_keys ): ?array {
	$terms = hovalvakil_lawyer_resolve_province_terms( $province_keys );
	if ( empty( $terms ) ) {
		return null;
	}

	return [
		'province_ids' => array_values( array_map( static fn( $t ) => (int) $t->term_id, $terms ) ),
	];
}

/**
 * Enable province SQL filter for the next hvl_lawyer WP_Query (archive / REST).
 *
 * @param string[]|int[] $province_keys Province slugs or IDs.
 * @return bool
 */
function hovalvakil_lawyer_enable_province_filter( array $province_keys ): bool {
	$ctx = hovalvakil_lawyer_province_filter_context( $province_keys );
	if ( null === $ctx ) {
		return false;
	}
	$GLOBALS['hovalvakil_lawyer_province_filter_ctx'] = $ctx;
	add_filter( 'posts_where', 'hovalvakil_lawyer_posts_where_province_filter', 10, 2 );
	return true;
}

/**
 * @return void
 */
function hovalvakil_lawyer_disable_province_filter(): void {
	unset( $GLOBALS['hovalvakil_lawyer_province_filter_ctx'] );
	remove_filter( 'posts_where', 'hovalvakil_lawyer_posts_where_province_filter', 10 );
}

/**
 * @param string   $where   WHERE clause.
 * @param WP_Query $query   Query.
 * @return string
 */
function hovalvakil_lawyer_posts_where_province_filter( $where, $query ) {
	if ( empty( $GLOBALS['hovalvakil_lawyer_province_filter_ctx'] ) || ! is_array( $GLOBALS['hovalvakil_lawyer_province_filter_ctx'] ) ) {
		return $where;
	}
	if ( ! $query instanceof WP_Query ) {
		return $where;
	}
	$pt = $query->get( 'post_type' );
	if ( 'hvl_lawyer' !== $pt && ( ! is_array( $pt ) || ! in_array( 'hvl_lawyer', $pt, true ) ) ) {
		return $where;
	}
	$sql = hovalvakil_lawyer_province_filter_sql_where( $GLOBALS['hovalvakil_lawyer_province_filter_ctx'] );
	if ( '' !== $sql ) {
		$where .= $sql;
	}
	return $where;
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
	$q = trim( (string) $q );
	if ( '' === $q || ! hovalvakil_lawyer_search_query_is_usable( $q ) ) {
		return [];
	}

	// License-only query (e.g. ۹۹۹۷۸): fast path — avoid scanning all lawyers.
	if ( hovalvakil_lawyer_search_is_digit_primary( $q ) ) {
		$license_ids = hovalvakil_lawyer_strong_search_license_ids( $filtered_base, $q );
		if ( function_exists( 'hovalvakil_lawyer_sort_ids_image_first' ) ) {
			return hovalvakil_lawyer_sort_ids_image_first( $license_ids );
		}
		return $license_ids;
	}

	$matched_city_slugs      = hovalvakil_lawyer_term_slugs_matching_query( 'hvl_city', $q );
	$matched_province_slugs  = hovalvakil_lawyer_term_slugs_matching_query( 'hvl_province', $q );
	$matched_specialty_slugs = hovalvakil_lawyer_term_slugs_matching_query( 'hvl_specialty', $q );

	$text_args = $filtered_base;
	$text_args['s'] = $q;
	unset( $text_args['post__in'], $text_args['orderby'] );
	$text_ids = hovalvakil_lawyer_collect_post_ids_batched( $text_args );

	$compact_title_ids = hovalvakil_lawyer_strong_search_compact_title_ids( $filtered_base, $q );
	$license_ids       = hovalvakil_lawyer_strong_search_license_ids( $filtered_base, $q );
	$meta_ids          = hovalvakil_lawyer_strong_search_meta_ids( $filtered_base, $q );

	$tax_match_ids = [];
	if ( ! empty( $matched_city_slugs ) || ! empty( $matched_province_slugs ) || ! empty( $matched_specialty_slugs ) ) {
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
		if ( ! empty( $matched_province_slugs ) ) {
			$q_tax[] = [
				'taxonomy' => 'hvl_province',
				'field'    => 'slug',
				'terms'    => $matched_province_slugs,
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

	$merged = array_values(
		array_unique(
			array_merge( $text_ids, $compact_title_ids, $license_ids, $meta_ids, $tax_match_ids )
		)
	);
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

	$q              = trim( (string) $params['q'] );
	if ( '' !== $q && function_exists( 'hovalvakil_lawyer_search_query_is_usable' ) && ! hovalvakil_lawyer_search_query_is_usable( $q ) ) {
		$q = '';
	}
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
