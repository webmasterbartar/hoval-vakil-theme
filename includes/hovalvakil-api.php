<?php
/**
 * Lightweight REST endpoints for Hovalvakil.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read specialty / city_term from query: supports comma string, specialty[]=a, city_term[]=b.
 *
 * @param WP_REST_Request $request Request.
 * @param string          $base_key city_term or specialty.
 * @return string Comma-separated for resolver.
 */
function hovalvakil_rest_get_csv_query_param( WP_REST_Request $request, $base_key ) {
	$query = $request->get_query_params();
	$raw   = null;
	if ( isset( $query[ $base_key . '[]' ] ) ) {
		$raw = $query[ $base_key . '[]' ];
	} elseif ( isset( $query[ $base_key ] ) ) {
		$raw = $query[ $base_key ];
	}
	if ( null !== $raw ) {
		if ( is_array( $raw ) ) {
			return implode( ',', array_filter( array_map( 'trim', array_map( 'strval', $raw ) ) ) );
		}
		return trim( (string) wp_unslash( $raw ) );
	}
	$fallback = $request->get_param( $base_key );
	if ( is_array( $fallback ) ) {
		return implode( ',', array_filter( array_map( 'trim', array_map( 'strval', $fallback ) ) ) );
	}
	if ( null === $fallback || false === $fallback ) {
		return '';
	}
	return trim( (string) wp_unslash( $fallback ) );
}

/**
 * Split CSV GET param into term slugs for tax_query (slug or term name, Persian-safe).
 *
 * @param string $csv      Comma-separated slugs or names.
 * @param string $taxonomy Taxonomy slug.
 * @return string[]
 */
function hovalvakil_rest_resolve_taxonomy_slugs( $csv, $taxonomy ) {
	$csv = trim( (string) wp_unslash( $csv ) );
	if ( '' === $csv ) {
		return [];
	}
	$out = [];
	foreach ( array_map( 'trim', explode( ',', $csv ) ) as $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( false !== strpos( $part, '%' ) ) {
			$part = rawurldecode( $part );
		}
		$t = get_term_by( 'slug', $part, $taxonomy );
		if ( ! $t || is_wp_error( $t ) ) {
			$t = get_term_by( 'name', $part, $taxonomy );
		}
		if ( $t && ! is_wp_error( $t ) ) {
			$out[] = $t->slug;
			continue;
		}
		$clean = sanitize_text_field( $part );
		if ( '' !== $clean ) {
			$out[] = $clean;
		}
	}
	return array_values( array_unique( array_filter( $out ) ) );
}

/**
 * Register REST routes.
 *
 * @return void
 */
function hovalvakil_register_rest_routes() {
	register_rest_route(
		'hovalvakil/v1',
		'/lawyers',
		[
			'methods'             => 'GET',
			'callback'            => 'hovalvakil_rest_get_lawyers',
			'permission_callback' => '__return_true',
			'args'                => [
				'q'         => [ 'type' => 'string', 'required' => false ],
				'city'      => [ 'type' => 'string', 'required' => false ],
				'city_term' => [ 'type' => 'string', 'required' => false ],
				'specialty' => [ 'type' => 'string', 'required' => false ],
				'page'      => [ 'type' => 'integer', 'required' => false, 'default' => 1 ],
				'per_page'  => [ 'type' => 'integer', 'required' => false, 'default' => 16 ],
			],
		]
	);

	register_rest_route(
		'hovalvakil/v1',
		'/specialties',
		[
			'methods'             => 'GET',
			'callback'            => 'hovalvakil_rest_get_specialties',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		'hovalvakil/v1',
		'/centers',
		[
			'methods'             => 'GET',
			'callback'            => 'hovalvakil_rest_get_centers',
			'permission_callback' => '__return_true',
			'args'                => [
				'q'        => [ 'type' => 'string', 'required' => false ],
				'city'     => [ 'type' => 'string', 'required' => false ],
				'city_term'=> [ 'type' => 'string', 'required' => false ],
				'city_term_id' => [ 'type' => 'integer', 'required' => false ],
				'page'     => [ 'type' => 'integer', 'required' => false, 'default' => 1 ],
				'per_page' => [ 'type' => 'integer', 'required' => false, 'default' => 8 ],
			],
		]
	);

	register_rest_route(
		'hovalvakil/v1',
		'/lawyers/(?P<id>\d+)',
		[
			'methods'             => 'GET',
			'callback'            => 'hovalvakil_rest_get_lawyer_by_id',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		'hovalvakil/v1',
		'/reservations',
		[
			'methods'             => 'POST',
			'callback'            => 'hovalvakil_rest_create_reservation',
			'permission_callback' => '__return_true',
		]
	);
}
add_action( 'rest_api_init', 'hovalvakil_register_rest_routes' );

/**
 * Return specialties list for dynamic filters.
 *
 * @return WP_REST_Response
 */
function hovalvakil_rest_get_specialties() {
	$terms = get_terms(
		[
			'taxonomy'   => 'hvl_specialty',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]
	);

	if ( is_wp_error( $terms ) ) {
		return rest_ensure_response(
			[
				'items' => [],
			]
		);
	}

	$items = [];
	foreach ( $terms as $term ) {
		$items[] = [
			'id'    => (int) $term->term_id,
			'name'  => (string) $term->name,
			'slug'  => (string) $term->slug,
			'count' => (int) $term->count,
		];
	}

	return rest_ensure_response(
		[
			'items' => $items,
		]
	);
}

/**
 * Return legal centers list for dynamic page.
 *
 * @param WP_REST_Request $request Request.
 *
 * @return WP_REST_Response
 */
function hovalvakil_rest_get_centers( WP_REST_Request $request ) {
	$q         = sanitize_text_field( (string) $request->get_param( 'q' ) );
	$city      = sanitize_text_field( (string) $request->get_param( 'city' ) );
	$city_term = sanitize_text_field( (string) $request->get_param( 'city_term' ) );
	$city_term_id = (int) $request->get_param( 'city_term_id' );
	$page      = max( 1, (int) $request->get_param( 'page' ) );
	$per_page  = min( 24, max( 1, (int) $request->get_param( 'per_page' ) ) );

	$args = [
		'post_type'              => 'hvl_center',
		'post_status'            => 'publish',
		'posts_per_page'         => $per_page,
		'paged'                  => $page,
		'no_found_rows'          => false,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => true,
	];

	$resolved_city_term_ids = [];
	if ( $city_term_id > 0 ) {
		$resolved_city_term_ids[] = $city_term_id;
	}
	if ( '' !== $city_term ) {
		$city_term_raw = trim( wp_unslash( $city_term ) );
		$city_term_decoded = rawurldecode( $city_term_raw );
		$by_slug = get_term_by( 'slug', $city_term_raw, 'hvl_city' );
		if ( ! $by_slug && $city_term_decoded !== $city_term_raw ) {
			$by_slug = get_term_by( 'slug', $city_term_decoded, 'hvl_city' );
		}
		if ( $by_slug && ! is_wp_error( $by_slug ) ) {
			$resolved_city_term_ids[] = (int) $by_slug->term_id;
		} else {
			$by_name = get_term_by( 'name', $city_term_decoded, 'hvl_city' );
			if ( $by_name && ! is_wp_error( $by_name ) ) {
				$resolved_city_term_ids[] = (int) $by_name->term_id;
			}
		}
	}
	if ( '' !== $city ) {
		$by_name = get_term_by( 'name', $city, 'hvl_city' );
		if ( $by_name && ! is_wp_error( $by_name ) ) {
			$resolved_city_term_ids[] = (int) $by_name->term_id;
		}
	}
	$resolved_city_term_ids = array_values( array_unique( array_filter( array_map( 'intval', $resolved_city_term_ids ) ) ) );
	if ( ! empty( $resolved_city_term_ids ) ) {
		$args['tax_query'] = [
			[
				'taxonomy' => 'hvl_city',
				'field'    => 'term_id',
				'terms'    => $resolved_city_term_ids,
			],
		];
	}

	// Robust search: match center text + city term names.
	if ( '' !== $q ) {
		$base_args = $args;

		$text_match_args = $base_args;
		$text_match_args['fields']         = 'ids';
		$text_match_args['posts_per_page'] = 300;
		$text_match_args['paged']          = 1;
		$text_match_args['no_found_rows']  = true;
		$text_match_args['s']              = $q;
		$text_ids = get_posts( $text_match_args );

		$matched_city_slugs = [];
		$city_terms_all     = get_terms(
			[
				'taxonomy'   => 'hvl_city',
				'hide_empty' => false,
			]
		);
		if ( ! is_wp_error( $city_terms_all ) ) {
			foreach ( $city_terms_all as $cterm ) {
				if ( false !== mb_stripos( $cterm->name, $q ) ) {
					$matched_city_slugs[] = $cterm->slug;
				}
			}
		}

		$tax_match_ids = [];
		if ( ! empty( $matched_city_slugs ) ) {
			$tax_match_args = $base_args;
			$tax_match_args['fields']         = 'ids';
			$tax_match_args['posts_per_page'] = 300;
			$tax_match_args['paged']          = 1;
			$tax_match_args['no_found_rows']  = true;

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
			$tax_match_ids = get_posts( $tax_match_args );
		}

		$matched_ids = array_values( array_unique( array_map( 'intval', array_merge( $text_ids, $tax_match_ids ) ) ) );
		if ( empty( $matched_ids ) ) {
			$args['post__in'] = [ 0 ];
		} else {
			$args['post__in'] = $matched_ids;
			$args['orderby']  = 'post__in';
		}
	}

	$query = new WP_Query( $args );
	$items = [];

	while ( $query->have_posts() ) {
		$query->the_post();
		$post_id    = get_the_ID();
		$image      = get_the_post_thumbnail_url( $post_id, 'large' );
		$city_terms = get_the_terms( $post_id, 'hvl_city' );
		$city_name  = is_array( $city_terms ) && ! empty( $city_terms ) ? (string) $city_terms[0]->name : '';
		$city_slug  = is_array( $city_terms ) && ! empty( $city_terms ) ? (string) $city_terms[0]->slug : '';

		if ( ! $image ) {
			$image = 'https://via.placeholder.com/900x600.png?text=%D9%85%D8%B1%DA%A9%D8%B2+%D8%AD%D9%82%D9%88%D9%82%DB%8C';
		}

		$items[] = [
			'id'        => $post_id,
			'title'     => get_the_title(),
			'content'   => wp_strip_all_tags( get_the_excerpt() ? get_the_excerpt() : get_the_content() ),
			'image'     => $image,
			'city'      => $city_name,
			'city_slug' => $city_slug,
			'permalink' => get_permalink(),
		];
	}
	wp_reset_postdata();

	return rest_ensure_response(
		[
			'items'      => $items,
			'total'      => (int) $query->found_posts,
			'totalPages' => (int) $query->max_num_pages,
			'page'       => $page,
		]
	);
}

/**
 * Return lightweight lawyers list for AJAX search.
 *
 * @param WP_REST_Request $request Request.
 *
 * @return WP_REST_Response
 */
function hovalvakil_rest_get_lawyers( WP_REST_Request $request ) {
	$q = sanitize_text_field( trim( (string) wp_unslash( $request->get_param( 'q' ) ) ) );
	// Ignore 0–1 char / accidental IME noise so tax-only filters are not killed by the "strong search" branch.
	if ( mb_strlen( $q ) < 2 ) {
		$q = '';
	}

	$city      = sanitize_text_field( trim( (string) wp_unslash( $request->get_param( 'city' ) ) ) ); // backward-compatible city name.
	$city_term = hovalvakil_rest_get_csv_query_param( $request, 'city_term' );
	$specialty = hovalvakil_rest_get_csv_query_param( $request, 'specialty' );
	$page      = max( 1, (int) $request->get_param( 'page' ) );
	$per_page  = min( 48, max( 1, (int) $request->get_param( 'per_page' ) ) );

	$tax_query = [];
	$city_slugs      = hovalvakil_rest_resolve_taxonomy_slugs( $city_term, 'hvl_city' );
	$specialty_slugs = hovalvakil_rest_resolve_taxonomy_slugs( $specialty, 'hvl_specialty' );

	if ( '' !== $city ) {
		$tax_query[] = [
			'taxonomy' => 'hvl_city',
			'field'    => 'name',
			'terms'    => $city,
		];
	}

	if ( ! empty( $city_slugs ) ) {
		$tax_query[] = [
			'taxonomy' => 'hvl_city',
			'field'    => 'slug',
			'terms'    => $city_slugs,
		];
	}
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

	$base_args = [
		'post_type'              => 'hvl_lawyer',
		'post_status'            => 'publish',
		'posts_per_page'         => $per_page,
		'paged'                  => $page,
		'no_found_rows'          => false,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => true,
	];

	if ( ! empty( $tax_query ) ) {
		$base_args['tax_query'] = $tax_query;
	}

	$args = $base_args;

	// Strong search for archive AJAX: match by lawyer name/content + city names + specialty names.
	if ( '' !== $q ) {
		$city_terms = get_terms(
			[
				'taxonomy'   => 'hvl_city',
				'hide_empty' => false,
			]
		);
		$specialty_terms = get_terms(
			[
				'taxonomy'   => 'hvl_specialty',
				'hide_empty' => false,
			]
		);

		$matched_city_slugs = [];
		if ( ! is_wp_error( $city_terms ) ) {
			foreach ( $city_terms as $cterm ) {
				if ( false !== mb_stripos( $cterm->name, $q ) ) {
					$matched_city_slugs[] = $cterm->slug;
				}
			}
		}

		$matched_specialty_slugs = [];
		if ( ! is_wp_error( $specialty_terms ) ) {
			foreach ( $specialty_terms as $sterm ) {
				if ( false !== mb_stripos( $sterm->name, $q ) ) {
					$matched_specialty_slugs[] = $sterm->slug;
				}
			}
		}

		$text_match_args = $base_args;
		$text_match_args['fields']         = 'ids';
		$text_match_args['posts_per_page'] = 300;
		$text_match_args['paged']          = 1;
		$text_match_args['no_found_rows']  = true;
		$text_match_args['s']              = $q;
		$text_ids = get_posts( $text_match_args );

		$tax_match_ids = [];
		if ( ! empty( $matched_city_slugs ) || ! empty( $matched_specialty_slugs ) ) {
			$tax_match_args = $base_args;
			$tax_match_args['fields']         = 'ids';
			$tax_match_args['posts_per_page'] = 300;
			$tax_match_args['paged']          = 1;
			$tax_match_args['no_found_rows']  = true;

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

			$tax_match_ids = get_posts( $tax_match_args );
		}

		$matched_ids = array_values( array_unique( array_map( 'intval', array_merge( $text_ids, $tax_match_ids ) ) ) );

		if ( empty( $matched_ids ) ) {
			$args['post__in'] = [ 0 ];
		} else {
			$args['post__in'] = $matched_ids;
			$args['orderby']  = 'post__in';
		}
	}

	$query = new WP_Query( $args );
	$items = [];

	while ( $query->have_posts() ) {
		$query->the_post();
		$post_id = get_the_ID();

		$img = get_the_post_thumbnail_url( $post_id, 'medium' );
		if ( ! $img ) {
			$img = 'https://via.placeholder.com/600x600.png?text=%D9%88%DA%A9%DB%8C%D9%84';
		}

		$spec_terms = get_the_terms( $post_id, 'hvl_specialty' );
		$city_terms = get_the_terms( $post_id, 'hvl_city' );

		$spec_names = [];
		if ( is_array( $spec_terms ) && ! empty( $spec_terms ) ) {
			foreach ( $spec_terms as $t ) {
				if ( isset( $t->name ) && '' !== $t->name ) {
					$spec_names[] = $t->name;
				}
			}
			$spec_names = array_values( array_unique( $spec_names ) );
			sort( $spec_names, SORT_STRING );
		}
		$primary_specialty = ! empty( $spec_names ) ? $spec_names[0] : '';

		$items[] = [
			'id'         => $post_id,
			'name'       => get_the_title(),
			'permalink'  => get_permalink(),
			'image'      => $img,
			'specialty'  => $primary_specialty,
			'specialties'=> $spec_names,
			'city'       => is_array( $city_terms ) && ! empty( $city_terms ) ? $city_terms[0]->name : '',
			'experience' => (string) get_post_meta( $post_id, 'hvl_experience', true ),
			'price_from' => (string) get_post_meta( $post_id, 'hvl_price_from', true ),
			'rating'     => (string) get_post_meta( $post_id, 'hvl_rating', true ),
			'reviews'    => (string) get_post_meta( $post_id, 'hvl_reviews_count', true ),
			'reservation_url' => add_query_arg(
				[
					'lawyer_id' => $post_id,
					'name'      => get_the_title(),
					'specialty' => $primary_specialty,
					'city'      => is_array( $city_terms ) && ! empty( $city_terms ) ? $city_terms[0]->name : '',
					'image'     => $img,
					'price'     => (string) get_post_meta( $post_id, 'hvl_price_from', true ),
					'service'   => 'مشاوره حضوری',
				],
				home_url( '/rezerv' )
			),
		];
	}
	wp_reset_postdata();

	return rest_ensure_response(
		[
			'items'      => $items,
			'total'      => (int) $query->found_posts,
			'totalPages' => (int) $query->max_num_pages,
			'page'       => $page,
		]
	);
}

/**
 * Create reservation post from frontend form.
 *
 * @param WP_REST_Request $request Request.
 *
 * @return WP_REST_Response
 */
function hovalvakil_rest_create_reservation( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		$params = [];
	}

	$full_name    = sanitize_text_field( (string) ( $params['full_name'] ?? '' ) );
	$phone        = sanitize_text_field( (string) ( $params['phone'] ?? '' ) );
	$national_id  = sanitize_text_field( (string) ( $params['national_id'] ?? '' ) );
	$description  = sanitize_textarea_field( (string) ( $params['description'] ?? '' ) );
	$service      = sanitize_text_field( (string) ( $params['service'] ?? '' ) );
	$date         = sanitize_text_field( (string) ( $params['date'] ?? '' ) );
	$time         = sanitize_text_field( (string) ( $params['time'] ?? '' ) );
	$lawyer_id    = (int) ( $params['lawyer_id'] ?? 0 );
	$lawyer_name  = sanitize_text_field( (string) ( $params['lawyer_name'] ?? '' ) );
	$payment_type = sanitize_text_field( (string) ( $params['payment_type'] ?? '' ) );
	$price        = sanitize_text_field( (string) ( $params['price'] ?? '' ) );

	if ( '' === $full_name || '' === $phone ) {
		return new WP_REST_Response(
			[
				'ok'      => false,
				'message' => 'نام و شماره همراه الزامی است.',
			],
			400
		);
	}

	if ( ! preg_match( '/^(\+98|0)?9\d{9}$/', preg_replace( '/\s+/', '', $phone ) ) ) {
		return new WP_REST_Response(
			[
				'ok'      => false,
				'message' => 'شماره همراه معتبر نیست.',
			],
			400
		);
	}

	// Try to resolve lawyer by name when lawyer_id is missing/invalid, so reservations are not lost.
	if ( $lawyer_id <= 0 || 'hvl_lawyer' !== get_post_type( $lawyer_id ) || 'publish' !== get_post_status( $lawyer_id ) ) {
		if ( '' !== $lawyer_name ) {
			$matched_lawyer = get_page_by_title( $lawyer_name, OBJECT, 'hvl_lawyer' );
			if ( $matched_lawyer && ! empty( $matched_lawyer->ID ) ) {
				$lawyer_id = (int) $matched_lawyer->ID;
			}
		}
	}
	if ( $lawyer_id > 0 && '' === $lawyer_name ) {
		$lawyer_name = get_the_title( $lawyer_id );
	}
	if ( '' === $lawyer_name ) {
		$lawyer_name = 'وکیل انتخاب نشده';
	}

	$allowed_services = [ 'مشاوره حضوری', 'مشاوره تلفنی', 'مشاوره متنی', 'مشاوره تصویری' ];
	if ( '' !== $service && ! in_array( $service, $allowed_services, true ) ) {
		return new WP_REST_Response(
			[
				'ok'      => false,
				'message' => 'نوع خدمت معتبر نیست.',
			],
			400
		);
	}
	if ( '' === $date || '' === $time ) {
		return new WP_REST_Response(
			[
				'ok'      => false,
				'message' => 'لطفا تاریخ و ساعت رزرو را انتخاب کنید.',
			],
			400
		);
	}

	$rate_limit_key = 'hvl_reservation_rate_' . md5( $phone . '|' . $lawyer_id . '|' . $lawyer_name );
	if ( get_transient( $rate_limit_key ) ) {
		return new WP_REST_Response(
			[
				'ok'      => false,
				'message' => 'درخواست شما به‌تازگی ثبت شده است. لطفا چند ثانیه بعد دوباره تلاش کنید.',
			],
			429
		);
	}
	set_transient( $rate_limit_key, 1, 20 );

	// Prevent duplicate booking for same lawyer/date/time (pending or confirmed).
	if ( $lawyer_id > 0 ) {
		$duplicate_query = new WP_Query(
			[
				'post_type'      => 'hvl_reservation',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => 'hvl_res_lawyer_id',
						'value' => $lawyer_id,
					],
					[
						'key'   => 'hvl_res_date',
						'value' => $date,
					],
					[
						'key'   => 'hvl_res_time',
						'value' => $time,
					],
					[
						'key'     => 'hvl_res_status',
						'value'   => [ 'pending', 'confirmed' ],
						'compare' => 'IN',
					],
				],
			]
		);
		if ( $duplicate_query->have_posts() ) {
			return new WP_REST_Response(
				[
					'ok'      => false,
					'message' => 'این زمان قبلا رزرو شده است. لطفا ساعت دیگری انتخاب کنید.',
				],
				409
			);
		}
	}

	$reservation_title = sprintf(
		'رزرو %s - %s',
		$full_name,
		current_time( 'Y-m-d H:i' )
	);

	$post_id = wp_insert_post(
		[
			'post_type'   => 'hvl_reservation',
			'post_status' => 'publish',
			'post_title'  => $reservation_title,
		]
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return new WP_REST_Response(
			[
				'ok'      => false,
				'message' => 'ثبت رزرو انجام نشد.',
			],
			500
		);
	}

	update_post_meta( $post_id, 'hvl_res_full_name', $full_name );
	update_post_meta( $post_id, 'hvl_res_phone', $phone );
	update_post_meta( $post_id, 'hvl_res_national_id', $national_id );
	update_post_meta( $post_id, 'hvl_res_description', $description );
	update_post_meta( $post_id, 'hvl_res_service', $service );
	update_post_meta( $post_id, 'hvl_res_date', $date );
	update_post_meta( $post_id, 'hvl_res_time', $time );
	update_post_meta( $post_id, 'hvl_res_lawyer_id', max( 0, $lawyer_id ) );
	update_post_meta( $post_id, 'hvl_res_lawyer_name', $lawyer_name );
	update_post_meta( $post_id, 'hvl_res_payment_type', $payment_type );
	update_post_meta( $post_id, 'hvl_res_price', $price );
	update_post_meta( $post_id, 'hvl_res_status', 'pending' );
	update_post_meta( $post_id, 'hvl_res_created_at', current_time( 'mysql' ) );

	$tracking_code = 'HK-' . gmdate( 'Y' ) . '-' . $post_id;
	update_post_meta( $post_id, 'hvl_res_tracking_code', $tracking_code );

	return rest_ensure_response(
		[
			'ok'            => true,
			'reservationId' => $post_id,
			'trackingCode'  => $tracking_code,
			'message'       => 'رزرو با موفقیت ثبت شد.',
		]
	);
}

/**
 * Get lawyer profile data by ID.
 *
 * @param WP_REST_Request $request Request.
 *
 * @return WP_REST_Response
 */
function hovalvakil_rest_get_lawyer_by_id( WP_REST_Request $request ) {
	$lawyer_id = (int) $request['id'];
	if ( $lawyer_id <= 0 || 'hvl_lawyer' !== get_post_type( $lawyer_id ) || 'publish' !== get_post_status( $lawyer_id ) ) {
		return new WP_REST_Response(
			[
				'ok'      => false,
				'message' => 'وکیل یافت نشد.',
			],
			404
		);
	}

	$image = get_the_post_thumbnail_url( $lawyer_id, 'large' );
	if ( ! $image ) {
		$image = 'https://via.placeholder.com/600x600.png?text=%D9%88%DA%A9%DB%8C%D9%84';
	}

	$spec_terms = get_the_terms( $lawyer_id, 'hvl_specialty' );
	$city_terms = get_the_terms( $lawyer_id, 'hvl_city' );

	return rest_ensure_response(
		[
			'ok'    => true,
			'item'  => [
				'id'         => $lawyer_id,
				'name'       => get_the_title( $lawyer_id ),
				'image'      => $image,
				'specialty'  => is_array( $spec_terms ) && ! empty( $spec_terms ) ? $spec_terms[0]->name : '',
				'city'       => is_array( $city_terms ) && ! empty( $city_terms ) ? $city_terms[0]->name : '',
				'price_from' => (string) get_post_meta( $lawyer_id, 'hvl_price_from', true ),
				'rating'     => (string) get_post_meta( $lawyer_id, 'hvl_rating', true ),
				'reviews'    => (string) get_post_meta( $lawyer_id, 'hvl_reviews_count', true ),
			],
		]
	);
}

