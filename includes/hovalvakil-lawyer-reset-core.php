<?php
/**
 * Shared bulk-delete helpers (WP-CLI, REST, admin tools).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delete all posts of a type (optional featured image per post).
 *
 * @param string $post_type    Post type slug.
 * @param bool   $delete_thumb Whether to delete featured image attachment.
 * @return int Number deleted.
 */
function hovalvakil_reset_delete_all_posts_of_type( $post_type, $delete_thumb = true ) {
	$post_type = sanitize_key( (string) $post_type );
	if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
		return 0;
	}
	$ids = get_posts(
		[
			'post_type'              => $post_type,
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		]
	);
	$n = 0;
	foreach ( $ids as $post_id ) {
		$post_id = (int) $post_id;
		if ( $delete_thumb ) {
			$thumb_id = (int) get_post_thumbnail_id( $post_id );
			if ( $thumb_id ) {
				wp_delete_attachment( $thumb_id, true );
			}
		}
		if ( wp_delete_post( $post_id, true ) ) {
			$n++;
		}
	}
	return $n;
}

/**
 * Remove all terms in given taxonomies.
 *
 * @param string[] $taxonomies Taxonomy slugs.
 * @return int Terms deleted.
 */
function hovalvakil_reset_delete_all_terms_in_taxonomies( array $taxonomies ) {
	$total = 0;
	foreach ( $taxonomies as $tax ) {
		$tax = sanitize_key( (string) $tax );
		if ( '' === $tax || ! taxonomy_exists( $tax ) ) {
			continue;
		}
		$terms = get_terms(
			[
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term_id ) {
			$r = wp_delete_term( (int) $term_id, $tax );
			if ( ! is_wp_error( $r ) && false !== $r ) {
				$total++;
			}
		}
	}
	return $total;
}

/**
 * Delete all WooCommerce products and variations.
 *
 * @return int Rows deleted.
 */
function hovalvakil_reset_delete_wc_products() {
	if ( ! post_type_exists( 'product' ) ) {
		return 0;
	}
	$n = 0;
	foreach ( [ 'product_variation', 'product' ] as $pt ) {
		$ids = get_posts(
			[
				'post_type'              => $pt,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			]
		);
		foreach ( $ids as $pid ) {
			if ( wp_delete_post( (int) $pid, true ) ) {
				$n++;
			}
		}
	}
	return $n;
}

/**
 * Full reset for lawyer re-import.
 *
 * @param array $opts Keys: with_centers (bool), with_services_cpt (bool), with_taxonomies (bool).
 * @return array<string,int> Counts per step.
 */
function hovalvakil_reset_import_data_run( array $opts ) {
	$with_centers        = ! empty( $opts['with_centers'] );
	$with_services_cpt   = ! empty( $opts['with_services_cpt'] );
	$with_taxonomies     = ! empty( $opts['with_taxonomies'] );

	$out = [
		'hvl_review'       => hovalvakil_reset_delete_all_posts_of_type( 'hvl_review', false ),
		'hvl_reservation'  => hovalvakil_reset_delete_all_posts_of_type( 'hvl_reservation', false ),
		'hvl_lawyer'       => hovalvakil_reset_delete_all_posts_of_type( 'hvl_lawyer', true ),
		'hvl_center'       => 0,
		'hvl_service'      => 0,
		'taxonomy_terms'   => 0,
	];

	if ( $with_centers ) {
		$out['hvl_center'] = hovalvakil_reset_delete_all_posts_of_type( 'hvl_center', true );
	}
	if ( $with_services_cpt ) {
		$out['hvl_service'] = hovalvakil_reset_delete_all_posts_of_type( 'hvl_service', true );
	}
	if ( $with_taxonomies ) {
		$out['taxonomy_terms'] = hovalvakil_reset_delete_all_terms_in_taxonomies(
			[ 'hvl_city', 'hvl_province', 'hvl_specialty' ]
		);
	}

	return $out;
}

/**
 * Delete lawyers only (+ featured images).
 *
 * @return int Count.
 */
function hovalvakil_reset_lawyers_only_run() {
	return hovalvakil_reset_delete_all_posts_of_type( 'hvl_lawyer', true );
}
