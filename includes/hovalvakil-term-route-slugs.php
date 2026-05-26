<?php
/**
 * English (Latin) public route slugs for hvl_province / hvl_city archive URLs.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Term meta: canonical Latin slug used in /ostan/{slug}/ and /shahr/{slug}/. */
const HOVALVAKIL_TERM_ROUTE_META = 'hvl_route_slug';

/**
 * Known Iran province names → English route slug.
 *
 * @return array<string, string> normalized Persian name => route slug.
 */
function hovalvakil_iran_province_route_map(): array {
	return [
		'آذربایجانشرقی'     => 'east-azerbaijan',
		'آذربایجانغربی'     => 'west-azerbaijan',
		'اردبیل'            => 'ardabil',
		'اصفهان'            => 'isfahan',
		'البرز'             => 'alborz',
		'ایلام'             => 'ilam',
		'بوشهر'             => 'bushehr',
		'تهران'             => 'tehran',
		'چهارمحالوبختیاری'  => 'chaharmahal-bakhtiari',
		'خراسانجنوبی'       => 'south-khorasan',
		'خراسانرضوی'        => 'razavi-khorasan',
		'خراسانشمالی'       => 'north-khorasan',
		'خوزستان'           => 'khuzestan',
		'زنجان'             => 'zanjan',
		'سمنان'             => 'semnan',
		'سیستانوبلوچستان'   => 'sistan-baluchestan',
		'فارس'              => 'fars',
		'قزوین'             => 'qazvin',
		'قم'                => 'qom',
		'کردستان'           => 'kurdistan',
		'کرمان'             => 'kerman',
		'کرمانشاه'          => 'kermanshah',
		'کهگیلویهوبویراحمد' => 'kohgiluyeh-boyer-ahmad',
		'گلستان'            => 'golestan',
		'گیلان'             => 'gilan',
		'لرستان'            => 'lorestan',
		'مازندران'          => 'mazandaran',
		'مرکزی'             => 'markazi',
		'هرمزگان'           => 'hormozgan',
		'همدان'             => 'hamadan',
		'یزد'               => 'yazd',
	];
}

/**
 * @param string $text Label.
 * @return string
 */
function hovalvakil_normalize_fa_label( string $text ): string {
	$text = trim( $text );
	$text = str_replace(
		[ "\xE2\x80\x8C", ' ', '‌', 'ـ', 'ك', 'ي', 'ة', 'ؤ', 'إ', 'أ', 'ئ' ],
		[ '', '', '', '', 'ک', 'ی', 'ه', 'و', 'ا', 'ا', 'ی' ],
		$text
	);
	return $text;
}

/**
 * @param string $slug Candidate slug.
 * @return bool
 */
function hovalvakil_is_latin_route_slug( string $slug ): bool {
	$slug = trim( $slug );
	return '' !== $slug && (bool) preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
}

/**
 * Basic Persian → Latin for URL slugs.
 *
 * @param string $text Persian (or mixed) text.
 * @return string
 */
function hovalvakil_persian_to_latin_slug( string $text ): string {
	$text = hovalvakil_normalize_fa_label( $text );
	if ( '' === $text ) {
		return '';
	}

	$map = [
		'آ' => 'a', 'ا' => 'a', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
		'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z',
		'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's',
		'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f',
		'ق' => 'gh', 'ک' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
		'و' => 'v', 'ه' => 'h', 'ی' => 'y', 'ئ' => 'y', 'ء' => '',
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
	];

	$out = '';
	$len = mb_strlen( $text, 'UTF-8' );
	for ( $i = 0; $i < $len; $i++ ) {
		$ch = mb_substr( $text, $i, 1, 'UTF-8' );
		if ( isset( $map[ $ch ] ) ) {
			$out .= $map[ $ch ];
			continue;
		}
		if ( preg_match( '/[a-z0-9]/i', $ch ) ) {
			$out .= strtolower( $ch );
		} elseif ( in_array( $ch, [ '-', '_', ' ' ], true ) ) {
			$out .= '-';
		}
	}

	$out = strtolower( $out );
	$out = preg_replace( '/[^a-z0-9-]+/', '-', $out );
	$out = preg_replace( '/-+/', '-', (string) $out );
	return trim( (string) $out, '-' );
}

/**
 * @param WP_Term $term     Term.
 * @param string  $taxonomy Taxonomy slug.
 * @return string
 */
function hovalvakil_term_build_route_slug( WP_Term $term, string $taxonomy ): string {
	$slug = (string) $term->slug;
	if ( hovalvakil_is_latin_route_slug( $slug ) ) {
		return $slug;
	}

	if ( 'hvl_province' === $taxonomy ) {
		$key = hovalvakil_normalize_fa_label( (string) $term->name );
		$map = hovalvakil_iran_province_route_map();
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		$from_name = hovalvakil_persian_to_latin_slug( (string) $term->name );
		if ( hovalvakil_is_latin_route_slug( $from_name ) ) {
			return $from_name;
		}
	}

	$base = hovalvakil_persian_to_latin_slug( (string) $term->name );
	if ( '' === $base ) {
		$base = 'term-' . (int) $term->term_id;
	}

	if ( 'hvl_city' === $taxonomy && function_exists( 'hovalvakil_city_get_province_term' ) ) {
		$prov = hovalvakil_city_get_province_term( $term );
		if ( $prov instanceof WP_Term ) {
			$prov_route = hovalvakil_term_get_route_slug( $prov, false );
			if ( '' !== $prov_route ) {
				$candidate = $prov_route . '-' . $base;
				if ( ! hovalvakil_term_route_slug_taken( $candidate, $taxonomy, (int) $term->term_id ) ) {
					return $candidate;
				}
			}
		}
	}

	if ( hovalvakil_term_route_slug_taken( $base, $taxonomy, (int) $term->term_id ) ) {
		$base .= '-' . (int) $term->term_id;
	}

	return $base;
}

/**
 * @param string $route    Route slug.
 * @param string $taxonomy Taxonomy.
 * @param int    $exclude  Term ID to exclude.
 * @return bool
 */
function hovalvakil_term_route_slug_taken( string $route, string $taxonomy, int $exclude = 0 ): bool {
	global $wpdb;

	$route = sanitize_title( $route );
	if ( '' === $route ) {
		return false;
	}

	$sql = "SELECT tm.term_id
		FROM {$wpdb->termmeta} tm
		INNER JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id AND tt.taxonomy = %s
		WHERE tm.meta_key = %s AND tm.meta_value = %s";
	if ( $exclude > 0 ) {
		$sql .= $wpdb->prepare( ' AND tm.term_id != %d', $exclude );
	}
	$sql .= ' LIMIT 1';

	$found = $wpdb->get_var( $wpdb->prepare( $sql, $taxonomy, HOVALVAKIL_TERM_ROUTE_META, $route ) );
	return ! empty( $found );
}

/**
 * @param WP_Term|int $term     Term or ID.
 * @param bool        $generate Create slug if missing.
 * @return string Latin route slug or empty.
 */
function hovalvakil_term_get_route_slug( $term, bool $generate = true ): string {
	if ( is_numeric( $term ) ) {
		$term = get_term( (int) $term );
	}
	if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
		return '';
	}

	$stored = trim( (string) get_term_meta( (int) $term->term_id, HOVALVAKIL_TERM_ROUTE_META, true ) );
	if ( hovalvakil_is_latin_route_slug( $stored ) ) {
		return $stored;
	}

	if ( ! $generate ) {
		return '';
	}

	$built = hovalvakil_term_build_route_slug( $term, (string) $term->taxonomy );
	if ( ! hovalvakil_is_latin_route_slug( $built ) ) {
		return '';
	}

	update_term_meta( (int) $term->term_id, HOVALVAKIL_TERM_ROUTE_META, $built );
	return $built;
}

/**
 * @param int    $term_id Term ID.
 * @param string $route   Latin slug.
 * @return bool
 */
function hovalvakil_term_set_route_slug( int $term_id, string $route ): bool {
	$route = sanitize_title( $route );
	if ( ! hovalvakil_is_latin_route_slug( $route ) ) {
		return false;
	}
	return (bool) update_term_meta( $term_id, HOVALVAKIL_TERM_ROUTE_META, $route );
}

/**
 * Find term by public route slug (meta), WP slug, or Persian name.
 *
 * @param string $raw      Path segment or slug.
 * @param string $taxonomy hvl_province | hvl_city.
 * @return WP_Term|null
 */
/**
 * Resolve hvl_province by route segment (tehran, isfahan, …) — map-first to avoid slug collisions.
 *
 * @param string $raw Path segment or slug.
 * @return WP_Term|null
 */
function hovalvakil_term_resolve_province_by_route( string $raw ): ?WP_Term {
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

	$map = hovalvakil_iran_province_route_map();
	$terms = get_terms(
		[
			'taxonomy'   => 'hvl_province',
			'hide_empty' => false,
		]
	);
	if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
		foreach ( $candidates as $candidate ) {
			if ( hovalvakil_is_latin_route_slug( $candidate ) && in_array( $candidate, $map, true ) ) {
				foreach ( $terms as $term ) {
					if ( ! $term instanceof WP_Term ) {
						continue;
					}
					foreach ( $map as $fa_key => $route_slug ) {
						if ( $route_slug !== $candidate ) {
							continue;
						}
						if ( hovalvakil_normalize_fa_label( (string) $term->name ) === $fa_key ) {
							return $term;
						}
					}
				}
			}
			$key = hovalvakil_normalize_fa_label( $candidate );
			if ( isset( $map[ $key ] ) ) {
				foreach ( $terms as $term ) {
					if ( $term instanceof WP_Term && hovalvakil_normalize_fa_label( (string) $term->name ) === $key ) {
						return $term;
					}
				}
			}
		}
		// Stored / computed Latin route slug (e.g. tehran) — before generic WP slug lookup.
		foreach ( $candidates as $candidate ) {
			if ( ! hovalvakil_is_latin_route_slug( $candidate ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$route = hovalvakil_term_get_route_slug( $term, false );
				if ( '' === $route ) {
					$route = hovalvakil_term_get_route_slug( $term, true );
				}
				if ( $route === $candidate ) {
					return $term;
				}
			}
		}
	}

	global $wpdb;
	foreach ( $candidates as $candidate ) {
		if ( ! hovalvakil_is_latin_route_slug( $candidate ) ) {
			continue;
		}
		$term_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tm.term_id
				FROM {$wpdb->termmeta} tm
				INNER JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id AND tt.taxonomy = %s
				WHERE tm.meta_key = %s AND tm.meta_value = %s
				LIMIT 1",
				'hvl_province',
				HOVALVAKIL_TERM_ROUTE_META,
				$candidate
			)
		);
		if ( $term_id > 0 ) {
			$term = get_term( $term_id, 'hvl_province' );
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}
	}

	foreach ( $candidates as $candidate ) {
		$term = get_term_by( 'name', $candidate, 'hvl_province' );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}

	// Last resort: WP slug (can collide on "tehran" — prefer map hits above).
	foreach ( $candidates as $candidate ) {
		$term = get_term_by( 'slug', $candidate, 'hvl_province' );
		if ( $term instanceof WP_Term ) {
			$fa = hovalvakil_normalize_fa_label( (string) $term->name );
			if ( isset( $map[ $fa ] ) ) {
				return $term;
			}
		}
	}

	return null;
}

function hovalvakil_term_resolve_by_route( string $raw, string $taxonomy ): ?WP_Term {
	$raw = trim( rawurldecode( $raw ) );
	if ( '' === $raw ) {
		return null;
	}

	if ( 'hvl_province' === $taxonomy ) {
		$prov = hovalvakil_term_resolve_province_by_route( $raw );
		if ( $prov instanceof WP_Term ) {
			return $prov;
		}
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

	global $wpdb;
	foreach ( $candidates as $candidate ) {
		if ( ! hovalvakil_is_latin_route_slug( $candidate ) && ! preg_match( '/[\x{0600}-\x{06FF}]/u', $candidate ) ) {
			continue;
		}
		$term_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tm.term_id
				FROM {$wpdb->termmeta} tm
				INNER JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id AND tt.taxonomy = %s
				WHERE tm.meta_key = %s AND tm.meta_value = %s
				LIMIT 1",
				$taxonomy,
				HOVALVAKIL_TERM_ROUTE_META,
				$candidate
			)
		);
		if ( $term_id > 0 ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}
	}

	foreach ( $candidates as $candidate ) {
		$term = get_term_by( 'slug', $candidate, $taxonomy );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}

	$term = get_term_by( 'name', $raw, $taxonomy );
	if ( $term instanceof WP_Term ) {
		return $term;
	}

	// Match Latin route slug against computed map (works before term meta sync).
	foreach ( $candidates as $candidate ) {
		if ( ! hovalvakil_is_latin_route_slug( $candidate ) ) {
			continue;
		}

		if ( 'hvl_province' === $taxonomy ) {
			$map = hovalvakil_iran_province_route_map();
			if ( in_array( $candidate, $map, true ) ) {
				$terms = get_terms(
					[
						'taxonomy'   => 'hvl_province',
						'hide_empty' => false,
					]
				);
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						if ( ! $term instanceof WP_Term ) {
							continue;
						}
						if ( hovalvakil_term_get_route_slug( $term ) === $candidate ) {
							return $term;
						}
						foreach ( $map as $fa => $route ) {
							if ( $route !== $candidate ) {
								continue;
							}
							if ( hovalvakil_normalize_fa_label( (string) $term->name ) === $fa ) {
								hovalvakil_term_set_route_slug( (int) $term->term_id, $route );
								return $term;
							}
						}
					}
				}
			}
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			]
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof WP_Term && hovalvakil_term_get_route_slug( $term ) === $candidate ) {
					return $term;
				}
			}
		}
	}

	if ( 'hvl_province' === $taxonomy ) {
		$key = hovalvakil_normalize_fa_label( $raw );
		$map = hovalvakil_iran_province_route_map();
		if ( isset( $map[ $key ] ) ) {
			$terms = get_terms(
				[
					'taxonomy'   => 'hvl_province',
					'hide_empty' => false,
				]
			);
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( ! $term instanceof WP_Term ) {
						continue;
					}
					if ( hovalvakil_normalize_fa_label( (string) $term->name ) === $key ) {
						return $term;
					}
				}
			}
		}
	}

	return null;
}

/**
 * Generate / refresh Latin route slugs for all province and city terms.
 *
 * @param bool $overwrite Replace existing meta.
 * @return array{cities:int, provinces:int}
 */
function hovalvakil_term_sync_all_route_slugs( bool $overwrite = false ): array {
	$counts = [ 'cities' => 0, 'provinces' => 0 ];

	foreach ( [ 'hvl_province', 'hvl_city' ] as $taxonomy ) {
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			]
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		if ( 'hvl_province' === $taxonomy ) {
			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$existing = trim( (string) get_term_meta( (int) $term->term_id, HOVALVAKIL_TERM_ROUTE_META, true ) );
				if ( ! $overwrite && hovalvakil_is_latin_route_slug( $existing ) ) {
					continue;
				}
				$built = hovalvakil_term_build_route_slug( $term, $taxonomy );
				if ( hovalvakil_is_latin_route_slug( $built ) ) {
					hovalvakil_term_set_route_slug( (int) $term->term_id, $built );
					++$counts['provinces'];
				}
			}
		}
	}

	$city_terms = get_terms(
		[
			'taxonomy'   => 'hvl_city',
			'hide_empty' => false,
		]
	);
	if ( ! is_wp_error( $city_terms ) && is_array( $city_terms ) ) {
		foreach ( $city_terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$existing = trim( (string) get_term_meta( (int) $term->term_id, HOVALVAKIL_TERM_ROUTE_META, true ) );
			if ( ! $overwrite && hovalvakil_is_latin_route_slug( $existing ) ) {
				continue;
			}
			$built = hovalvakil_term_build_route_slug( $term, 'hvl_city' );
			if ( hovalvakil_is_latin_route_slug( $built ) ) {
				hovalvakil_term_set_route_slug( (int) $term->term_id, $built );
				++$counts['cities'];
			}
		}
	}

	return $counts;
}

/**
 * One-time sync after deploy (admin or front).
 *
 * @return void
 */
function hovalvakil_term_maybe_sync_route_slugs(): void {
	$ver     = '2026-05-20-hvl-route-slugs-v1';
	$stored  = get_option( 'hovalvakil_term_route_slug_ver', '' );
	if ( $stored === $ver ) {
		return;
	}
	hovalvakil_term_sync_all_route_slugs( false );
	update_option( 'hovalvakil_term_route_slug_ver', $ver, false );
}
add_action( 'init', 'hovalvakil_term_maybe_sync_route_slugs', 25 );

/**
 * Ensure route slug exists when terms are created/updated.
 *
 * @param int    $term_id  Term ID.
 * @param int    $tt_id    Term taxonomy ID.
 * @param string $taxonomy Taxonomy slug.
 * @return void
 */
function hovalvakil_term_ensure_route_slug_on_save( int $term_id, int $tt_id, string $taxonomy ): void {
	unset( $tt_id );
	if ( ! in_array( $taxonomy, [ 'hvl_province', 'hvl_city' ], true ) ) {
		return;
	}
	$term = get_term( $term_id, $taxonomy );
	if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
		hovalvakil_term_get_route_slug( $term, true );
	}
}
add_action( 'created_term', 'hovalvakil_term_ensure_route_slug_on_save', 20, 3 );
add_action( 'edited_term', 'hovalvakil_term_ensure_route_slug_on_save', 20, 3 );
