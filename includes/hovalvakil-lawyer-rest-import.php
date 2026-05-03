<?php
/**
 * REST: bulk upsert lawyers for overnight import scripts.
 *
 * Auth: WordPress Application Password (HTTPS) or session cookie.
 *       User must have edit_others_posts (معمولاً ویرایشگر/مدیر).
 *
 * POST /wp-json/hovalvakil/v1/lawyers/batch
 * Body JSON:
 * {
 *   "dry_run": false,
 *   "sideload_featured_image": false,
 *   "items": [
 *     {
 *       "external_id": "src-123",
 *       "title": "نام وکیل",
 *       "slug": "latin-slug",
 *       "content": "",
 *       "excerpt": "",
 *       "status": "publish",
 *       "city": "tehran",
 *       "province": "tehran-province",
 *       "specialties": ["حقوق-خانواده"],
 *       "featured_image_url": "https://...",
 *       "featured_image_filename": "نام-وکیل-بدون-پسوند",
 *       "hvl_mobile": "...",
 *       "...": "یا همه meta با پیشوند hvl_ در ریشه آبجکت"
 *     }
 *   ]
 * }
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return int
 */
function hovalvakil_lawyer_batch_max_items() {
	return (int) apply_filters( 'hovalvakil_lawyer_batch_max_items', 50 );
}

/**
 * @return bool
 */
function hovalvakil_lawyer_batch_can_import() {
	return (bool) apply_filters(
		'hovalvakil_lawyer_batch_can_import',
		current_user_can( 'edit_others_posts' )
	);
}

/**
 * Find lawyer post ID by import external id meta.
 *
 * @param string $external_id External id.
 * @return int 0 if not found.
 */
function hovalvakil_lawyer_find_id_by_external_id( $external_id ) {
	$external_id = sanitize_text_field( (string) $external_id );
	if ( '' === $external_id ) {
		return 0;
	}
	$q = new WP_Query(
		[
			'post_type'      => 'hvl_lawyer',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'   => 'hvl_import_external_id',
					'value' => $external_id,
				],
			],
		]
	);
	if ( $q->have_posts() ) {
		return (int) $q->posts[0];
	}
	return 0;
}

/**
 * Find lawyer by post slug.
 *
 * @param string $slug Post name.
 * @return int
 */
function hovalvakil_lawyer_find_id_by_slug( $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) {
		return 0;
	}
	$q = new WP_Query(
		[
			'post_type'      => 'hvl_lawyer',
			'post_status'    => 'any',
			'name'           => $slug,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		]
	);
	return $q->have_posts() ? (int) $q->posts[0] : 0;
}

/**
 * Resolve taxonomy term by slug then name.
 *
 * @param string $value   Slug or human name.
 * @param string $taxonomy Taxonomy slug.
 * @return int Term ID or 0.
 */
function hovalvakil_import_resolve_term_id( $value, $taxonomy ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0;
	}
	$t = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
	if ( $t && ! is_wp_error( $t ) ) {
		return (int) $t->term_id;
	}
	$t = get_term_by( 'slug', $value, $taxonomy );
	if ( $t && ! is_wp_error( $t ) ) {
		return (int) $t->term_id;
	}
	$t = get_term_by( 'name', $value, $taxonomy );
	if ( $t && ! is_wp_error( $t ) ) {
		return (int) $t->term_id;
	}
	return 0;
}

/**
 * Set terms on post (append=false replaces set for that taxonomy).
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy.
 * @param array  $term_ids Term IDs (non-zero).
 * @return void
 */
function hovalvakil_import_set_terms( $post_id, $taxonomy, array $term_ids ) {
	$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );
	if ( empty( $term_ids ) ) {
		wp_set_object_terms( $post_id, [], $taxonomy, false );
		return;
	}
	wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
}

/**
 * Sideload remote image as featured thumbnail.
 *
 * @param int    $post_id        Post ID.
 * @param string $url            Image URL.
 * @param string $preferred_stem Optional file base name without extension (e.g. lawyer display name); extension comes from URL.
 * @return int|WP_Error Attachment ID or error.
 */
function hovalvakil_import_sideload_featured( $post_id, $url, $preferred_stem = '' ) {
	$url = esc_url_raw( trim( (string) $url ) );
	if ( '' === $url ) {
		return new WP_Error( 'empty_url', 'Empty image URL' );
	}
	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$tmp = download_url( $url, 25 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	$url_path     = (string) wp_parse_url( $url, PHP_URL_PATH );
	$url_basename = basename( $url_path ?: 'image.jpg' );
	$ext          = strtolower( (string) pathinfo( $url_basename, PATHINFO_EXTENSION ) );
	if ( ! preg_match( '/^(jpe?g|png|gif|webp)$/', $ext ) ) {
		$ext = 'webp';
	}

	$preferred_stem = trim( (string) $preferred_stem );
	$preferred_stem = preg_replace( '/\.[^.]+$/', '', $preferred_stem );
	if ( '' !== $preferred_stem ) {
		$file_stem = sanitize_file_name( $preferred_stem );
		if ( '' === $file_stem ) {
			$file_stem = '';
		}
	} else {
		$file_stem = '';
	}
	if ( '' === $file_stem ) {
		$post = get_post( $post_id );
		if ( $post && '' !== (string) $post->post_name ) {
			$file_stem = sanitize_file_name( (string) $post->post_name );
		}
	}
	if ( '' === $file_stem ) {
		$file_stem = pathinfo( $url_basename, PATHINFO_FILENAME );
		$file_stem = sanitize_file_name( (string) $file_stem );
	}
	if ( '' === $file_stem ) {
		$file_stem = 'image';
	}

	$file_array = [
		'name'     => $file_stem . '.' . $ext,
		'tmp_name' => $tmp,
	];
	$att_id = media_handle_sideload( $file_array, $post_id );
	if ( is_wp_error( $att_id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $att_id;
	}
	set_post_thumbnail( $post_id, (int) $att_id );
	return (int) $att_id;
}

/**
 * Apply meta keys from item array (hvl_* only + hvl_import_external_id).
 *
 * @param int   $post_id Post ID.
 * @param array $item    Flat item.
 * @return void
 */
function hovalvakil_import_apply_lawyer_meta( $post_id, array $item ) {
	$allowed = array_flip( hovalvakil_lawyer_meta_key_list() );
	foreach ( $item as $key => $val ) {
		if ( ! is_string( $key ) || ! isset( $allowed[ $key ] ) ) {
			continue;
		}
		if ( is_array( $val ) || is_object( $val ) ) {
			continue;
		}
		$key = (string) $key;
		if ( in_array( $key, [ 'hvl_records', 'hvl_services', 'hvl_office_address' ], true ) ) {
			update_post_meta( $post_id, $key, sanitize_textarea_field( (string) $val ) );
			continue;
		}
		if ( 'hvl_license_file_url' === $key || 'hvl_photo_url' === $key || 'hvl_office_map_image' === $key ) {
			update_post_meta( $post_id, $key, esc_url_raw( trim( (string) $val ) ) );
			continue;
		}
		if ( 'hvl_license_issued' === $key || 'hvl_license_expires' === $key ) {
			$norm = function_exists( 'hovalvakil_lawyer_normalize_license_expires' )
				? hovalvakil_lawyer_normalize_license_expires( (string) $val )
				: '';
			update_post_meta( $post_id, $key, $norm );
			continue;
		}
		update_post_meta( $post_id, $key, sanitize_text_field( (string) $val ) );
	}
}

/**
 * Register batch route.
 *
 * @return void
 */
function hovalvakil_register_lawyer_batch_route() {
	register_rest_route(
		'hovalvakil/v1',
		'/lawyers/batch',
		[
			'methods'             => 'POST',
			'callback'            => 'hovalvakil_rest_post_lawyers_batch',
			'permission_callback' => static function () {
				return is_user_logged_in() && hovalvakil_lawyer_batch_can_import();
			},
		]
	);
}
add_action( 'rest_api_init', 'hovalvakil_register_lawyer_batch_route', 11 );

/**
 * Batch upsert lawyers.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function hovalvakil_rest_post_lawyers_batch( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		$params = [];
	}
	$dry_run = ! empty( $params['dry_run'] );
	$sideload = ! empty( $params['sideload_featured_image'] );
	$items    = isset( $params['items'] ) && is_array( $params['items'] ) ? $params['items'] : [];

	$max = hovalvakil_lawyer_batch_max_items();
	if ( count( $items ) > $max ) {
		return new WP_REST_Response(
			[
				'ok'      => false,
				'message' => sprintf(
					/* translators: %d max batch size */
					'حداکثر %d رکورد در هر درخواست.',
					$max
				),
			],
			400
		);
	}

	$created = [];
	$updated = [];
	$errors  = [];

	foreach ( $items as $index => $raw_item ) {
		if ( ! is_array( $raw_item ) ) {
			$errors[] = [ 'index' => $index, 'message' => 'آیتم باید آبجکت باشد.' ];
			continue;
		}

		$title = isset( $raw_item['title'] ) ? sanitize_text_field( (string) $raw_item['title'] ) : '';
		if ( '' === $title ) {
			$errors[] = [ 'index' => $index, 'message' => 'فیلد title الزامی است.' ];
			continue;
		}

		$external_id = isset( $raw_item['external_id'] ) ? sanitize_text_field( (string) $raw_item['external_id'] ) : '';
		$slug_in     = isset( $raw_item['slug'] ) ? sanitize_title( (string) $raw_item['slug'] ) : '';

		$post_id = 0;
		if ( '' !== $external_id ) {
			$post_id = hovalvakil_lawyer_find_id_by_external_id( $external_id );
		}
		if ( ! $post_id && '' !== $slug_in ) {
			$post_id = hovalvakil_lawyer_find_id_by_slug( $slug_in );
		}

		$content = isset( $raw_item['content'] ) ? wp_kses_post( (string) $raw_item['content'] ) : '';
		$excerpt = isset( $raw_item['excerpt'] ) ? sanitize_textarea_field( (string) $raw_item['excerpt'] ) : '';
		$status  = isset( $raw_item['status'] ) ? sanitize_key( (string) $raw_item['status'] ) : 'publish';
		if ( ! in_array( $status, [ 'publish', 'draft', 'pending', 'private' ], true ) ) {
			$status = 'publish';
		}

		$postarr = [
			'post_type'    => 'hvl_lawyer',
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => $status,
		];
		if ( '' !== $slug_in ) {
			$postarr['post_name'] = $slug_in;
		}

		if ( $dry_run ) {
			$created[] = [
				'index'       => $index,
				'would_be'    => $post_id ? 'update' : 'create',
				'resolved_id' => $post_id,
			];
			continue;
		}

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			if ( '' === $slug_in ) {
				unset( $postarr['post_name'] );
			}
			$r = wp_update_post( $postarr, true );
			if ( is_wp_error( $r ) ) {
				$errors[] = [ 'index' => $index, 'message' => $r->get_error_message() ];
				continue;
			}
			$post_id = (int) $r;
			$updated[] = [ 'index' => $index, 'id' => $post_id ];
		} else {
			$r = wp_insert_post( $postarr, true );
			if ( is_wp_error( $r ) ) {
				$errors[] = [ 'index' => $index, 'message' => $r->get_error_message() ];
				continue;
			}
			$post_id = (int) $r;
			$created[] = [ 'index' => $index, 'id' => $post_id ];
		}

		if ( '' !== $external_id ) {
			update_post_meta( $post_id, 'hvl_import_external_id', $external_id );
		}

		// Taxonomies.
		$city_id = isset( $raw_item['city'] ) ? hovalvakil_import_resolve_term_id( (string) $raw_item['city'], 'hvl_city' ) : 0;
		$prov_id = isset( $raw_item['province'] ) ? hovalvakil_import_resolve_term_id( (string) $raw_item['province'], 'hvl_province' ) : 0;
		hovalvakil_import_set_terms( $post_id, 'hvl_city', $city_id ? [ $city_id ] : [] );
		hovalvakil_import_set_terms( $post_id, 'hvl_province', $prov_id ? [ $prov_id ] : [] );

		$spec_ids = [];
		if ( isset( $raw_item['specialties'] ) && is_array( $raw_item['specialties'] ) ) {
			foreach ( $raw_item['specialties'] as $spec_val ) {
				$tid = hovalvakil_import_resolve_term_id( (string) $spec_val, 'hvl_specialty' );
				if ( $tid ) {
					$spec_ids[] = $tid;
				}
			}
		}
		hovalvakil_import_set_terms( $post_id, 'hvl_specialty', $spec_ids );

		hovalvakil_import_apply_lawyer_meta( $post_id, $raw_item );

		// Remote photo: either sideload as thumbnail or store URL only.
		$img_url = isset( $raw_item['featured_image_url'] ) ? esc_url_raw( trim( (string) $raw_item['featured_image_url'] ) ) : '';
		if ( '' !== $img_url ) {
			if ( $sideload ) {
				$img_stem = isset( $raw_item['featured_image_filename'] ) ? sanitize_text_field( (string) $raw_item['featured_image_filename'] ) : '';
				$sd       = hovalvakil_import_sideload_featured( $post_id, $img_url, $img_stem );
				if ( is_wp_error( $sd ) ) {
					update_post_meta( $post_id, 'hvl_photo_url', $img_url );
					$errors[] = [
						'index'   => $index,
						'message' => 'سایدلود تصویر ناموفق؛ URL در hvl_photo_url ذخیره شد: ' . $sd->get_error_message(),
					];
				}
			} else {
				update_post_meta( $post_id, 'hvl_photo_url', $img_url );
			}
		}
	}

	return rest_ensure_response(
		[
			'ok'       => true,
			'dry_run'  => $dry_run,
			'created'  => $created,
			'updated'  => $updated,
			'errors'   => $errors,
			'message'  => $dry_run ? 'dry_run: هیچ ذخیره‌ای انجام نشد.' : 'پردازش انجام شد.',
		]
	);
}
