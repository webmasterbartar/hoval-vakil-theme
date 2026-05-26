<?php
/**
 * Province ↔ city hierarchy: each hvl_city term stores parent province in term meta.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Term meta on hvl_city: WP_Term ID of hvl_province. */
const HOVALVAKIL_CITY_PROVINCE_META = 'hvl_province_id';

/**
 * @param int|WP_Term $city City term or ID.
 * @return int Province term_id or 0.
 */
function hovalvakil_city_get_province_id( $city ): int {
	$city_id = $city instanceof WP_Term ? (int) $city->term_id : (int) $city;
	if ( $city_id <= 0 ) {
		return 0;
	}
	return max( 0, (int) get_term_meta( $city_id, HOVALVAKIL_CITY_PROVINCE_META, true ) );
}

/**
 * @param int|WP_Term $city City term or ID.
 * @return WP_Term|null
 */
function hovalvakil_city_get_province_term( $city ): ?WP_Term {
	$pid = hovalvakil_city_get_province_id( $city );
	if ( $pid <= 0 ) {
		return null;
	}
	$term = get_term( $pid, 'hvl_province' );
	return ( $term instanceof WP_Term && ! is_wp_error( $term ) ) ? $term : null;
}

/**
 * Link a city term to a province term.
 *
 * @param int $city_id     hvl_city term_id.
 * @param int $province_id hvl_province term_id (0 clears link).
 * @return bool
 */
function hovalvakil_city_set_province_id( int $city_id, int $province_id ): bool {
	if ( $city_id <= 0 ) {
		return false;
	}
	if ( $province_id <= 0 ) {
		delete_term_meta( $city_id, HOVALVAKIL_CITY_PROVINCE_META );
		return true;
	}
	$prov = get_term( $province_id, 'hvl_province' );
	if ( ! $prov instanceof WP_Term || is_wp_error( $prov ) ) {
		return false;
	}
	return (bool) update_term_meta( $city_id, HOVALVAKIL_CITY_PROVINCE_META, $province_id );
}

/**
 * Analyze lawyer posts: how often each city co-occurs with each province.
 *
 * @return array<int, array<int, int>> city_id => [ province_id => count ].
 */
function hovalvakil_location_analyze_lawyer_cooccurrence(): array {
	global $wpdb;

	$sql = "SELECT ttc.term_id AS city_id, ttp.term_id AS province_id, COUNT(DISTINCT p.ID) AS cnt
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->term_relationships} trc ON p.ID = trc.object_id
		INNER JOIN {$wpdb->term_taxonomy} ttc ON trc.term_taxonomy_id = ttc.term_taxonomy_id AND ttc.taxonomy = %s
		INNER JOIN {$wpdb->term_relationships} trp ON p.ID = trp.object_id
		INNER JOIN {$wpdb->term_taxonomy} ttp ON trp.term_taxonomy_id = ttp.term_taxonomy_id AND ttp.taxonomy = %s
		WHERE p.post_type = %s AND p.post_status = %s
		GROUP BY ttc.term_id, ttp.term_id";

	$rows = $wpdb->get_results(
		$wpdb->prepare( $sql, 'hvl_city', 'hvl_province', 'hvl_lawyer', 'publish' ),
		ARRAY_A
	);

	$map = [];
	if ( ! is_array( $rows ) ) {
		return $map;
	}

	foreach ( $rows as $row ) {
		$cid = (int) ( $row['city_id'] ?? 0 );
		$pid = (int) ( $row['province_id'] ?? 0 );
		$cnt = (int) ( $row['cnt'] ?? 0 );
		if ( $cid <= 0 || $pid <= 0 || $cnt <= 0 ) {
			continue;
		}
		if ( ! isset( $map[ $cid ] ) ) {
			$map[ $cid ] = [];
		}
		$map[ $cid ][ $pid ] = $cnt;
	}

	return $map;
}

/**
 * Pick dominant province for a city from co-occurrence counts.
 *
 * @param array<int, int> $province_counts province_id => count.
 * @return array{province_id:int, total:int, is_conflict:bool, counts:array<int,int>}
 */
function hovalvakil_location_pick_dominant_province( array $province_counts ): array {
	if ( empty( $province_counts ) ) {
		return [
			'province_id' => 0,
			'total'       => 0,
			'is_conflict' => false,
			'counts'      => [],
		];
	}

	arsort( $province_counts, SORT_NUMERIC );
	$counts    = array_values( $province_counts );
	$pids      = array_keys( $province_counts );
	$top       = (int) ( $counts[0] ?? 0 );
	$second    = (int) ( $counts[1] ?? 0 );
	$is_conflict = count( $counts ) > 1 && $second > 0 && $second >= (int) floor( $top * 0.15 );

	return [
		'province_id' => (int) ( $pids[0] ?? 0 ),
		'total'       => array_sum( $province_counts ),
		'is_conflict' => $is_conflict,
		'counts'      => $province_counts,
	];
}

/**
 * City term IDs belonging to one or more provinces (by slug or term_id).
 *
 * @param string[]|int[] $province_keys Province slugs or term IDs.
 * @return int[]
 */
function hovalvakil_get_city_ids_for_provinces( array $province_keys ): array {
	$province_ids = [];
	foreach ( $province_keys as $key ) {
		if ( is_numeric( $key ) ) {
			$province_ids[] = (int) $key;
			continue;
		}
		$key = trim( (string) $key );
		if ( '' === $key ) {
			continue;
		}
		$term = null;
		if ( function_exists( 'hovalvakil_term_resolve_by_route' ) ) {
			$term = hovalvakil_term_resolve_by_route( $key, 'hvl_province' );
		}
		if ( ! $term instanceof WP_Term && function_exists( 'hovalvakil_lawyer_archive_resolve_term' ) ) {
			$term = hovalvakil_lawyer_archive_resolve_term( $key, 'hvl_province' );
		}
		if ( ! $term instanceof WP_Term ) {
			$term = get_term_by( 'slug', $key, 'hvl_province' );
		}
		if ( ! $term instanceof WP_Term ) {
			$term = get_term_by( 'name', $key, 'hvl_province' );
		}
		if ( $term instanceof WP_Term ) {
			$province_ids[] = (int) $term->term_id;
		}
	}
	$province_ids = array_values( array_unique( array_filter( $province_ids ) ) );
	if ( empty( $province_ids ) ) {
		return [];
	}

	global $wpdb;
	$placeholders = implode( ',', array_fill( 0, count( $province_ids ), '%d' ) );
	$sql          = "SELECT tm.term_id
		FROM {$wpdb->termmeta} tm
		INNER JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id AND tt.taxonomy = %s
		WHERE tm.meta_key = %s AND CAST(tm.meta_value AS UNSIGNED) IN ($placeholders)";

	$prepare_args = array_merge( [ 'hvl_city', HOVALVAKIL_CITY_PROVINCE_META ], $province_ids );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_col( $wpdb->prepare( $sql, ...$prepare_args ) );

	$ids = [];
	if ( is_array( $rows ) ) {
		foreach ( $rows as $id ) {
			$ids[] = (int) $id;
		}
	}

	// Fallback: infer from lawyer co-occurrence when meta not set yet.
	if ( empty( $ids ) && function_exists( 'hovalvakil_home_city_province_pairs_for_lawyers' ) ) {
		foreach ( hovalvakil_home_city_province_pairs_for_lawyers() as $pair ) {
			$prov = $pair['province'] ?? null;
			$city = $pair['city'] ?? null;
			if ( ! $prov instanceof WP_Term || ! $city instanceof WP_Term ) {
				continue;
			}
			if ( in_array( (int) $prov->term_id, $province_ids, true ) ) {
				$ids[] = (int) $city->term_id;
			}
		}
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Cities grouped under provinces (for mega menu / filters).
 *
 * @return array<string, array<int, array{n:string, s:string, u:string}>> province_slug => city rows.
 */
function hovalvakil_get_cities_grouped_by_province(): array {
	$provinces = get_terms(
		[
			'taxonomy'   => 'hvl_province',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]
	);
	if ( is_wp_error( $provinces ) || empty( $provinces ) ) {
		return [];
	}

	$cities = get_terms(
		[
			'taxonomy'   => 'hvl_city',
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]
	);
	if ( is_wp_error( $cities ) ) {
		$cities = [];
	}

	$grouped = [];
	foreach ( $provinces as $prov ) {
		if ( ! $prov instanceof WP_Term ) {
			continue;
		}
		$grouped[ (string) $prov->slug ] = [];
	}

	foreach ( $cities as $city ) {
		if ( ! $city instanceof WP_Term ) {
			continue;
		}
		$prov = hovalvakil_city_get_province_term( $city );
		if ( ! $prov instanceof WP_Term ) {
			continue;
		}
		$pslug = (string) $prov->slug;
		if ( ! isset( $grouped[ $pslug ] ) ) {
			$grouped[ $pslug ] = [];
		}
		$grouped[ $pslug ][] = [
			'n' => (string) $city->name,
			's' => (string) $city->slug,
			'u' => function_exists( 'hovalvakil_lawyer_archive_city_url' )
				? hovalvakil_lawyer_archive_city_url( (string) $city->slug )
				: add_query_arg( 'city_term[]', $city->slug, get_post_type_archive_link( 'hvl_lawyer' ) ),
		];
	}

	foreach ( $grouped as $pslug => $rows ) {
		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( (string) ( $a['n'] ?? '' ), (string) ( $b['n'] ?? '' ) );
			}
		);
		$grouped[ $pslug ] = $rows;
	}

	return $grouped;
}

/**
 * Ensure lawyer province term matches city's linked province.
 *
 * @param int $post_id Lawyer post ID.
 * @return bool True if province was changed.
 */
function hovalvakil_lawyer_sync_province_from_city( int $post_id ): bool {
	if ( $post_id <= 0 || 'hvl_lawyer' !== get_post_type( $post_id ) ) {
		return false;
	}

	$city_terms = get_the_terms( $post_id, 'hvl_city' );
	if ( ! is_array( $city_terms ) || empty( $city_terms ) ) {
		return false;
	}

	$city = $city_terms[0];
	if ( ! $city instanceof WP_Term ) {
		return false;
	}

	$expected_prov_id = hovalvakil_city_get_province_id( $city );
	if ( $expected_prov_id <= 0 ) {
		return false;
	}

	$prov_terms = get_the_terms( $post_id, 'hvl_province' );
	$current_id = 0;
	if ( is_array( $prov_terms ) && ! empty( $prov_terms ) && $prov_terms[0] instanceof WP_Term ) {
		$current_id = (int) $prov_terms[0]->term_id;
	}

	if ( $current_id === $expected_prov_id ) {
		return false;
	}

	wp_set_object_terms( $post_id, [ $expected_prov_id ], 'hvl_province', false );
	return true;
}

/**
 * Sync city→province links from lawyer data and fix lawyer assignments.
 *
 * @param array{dry_run?:bool, fix_lawyers?:bool, overwrite_existing?:bool} $args Options.
 * @return array<string, mixed> Report.
 */
function hovalvakil_location_sync_all( array $args = [] ): array {
	$dry_run             = ! empty( $args['dry_run'] );
	$fix_lawyers         = ! array_key_exists( 'fix_lawyers', $args ) || ! empty( $args['fix_lawyers'] );
	$overwrite_existing  = ! empty( $args['overwrite_existing'] );

	$cooccurrence = hovalvakil_location_analyze_lawyer_cooccurrence();

	$report = [
		'dry_run'            => $dry_run,
		'cities_linked'      => 0,
		'cities_skipped'     => 0,
		'cities_conflicts'   => [],
		'lawyers_checked'    => 0,
		'lawyers_fixed'      => 0,
		'lawyers_no_city'    => 0,
		'lawyers_no_mapping' => 0,
	];

	$cities = get_terms(
		[
			'taxonomy'   => 'hvl_city',
			'hide_empty' => false,
		]
	);
	if ( is_wp_error( $cities ) ) {
		$report['error'] = $cities->get_error_message();
		return $report;
	}

	foreach ( $cities as $city ) {
		if ( ! $city instanceof WP_Term ) {
			continue;
		}
		$cid = (int) $city->term_id;

		$existing = hovalvakil_city_get_province_id( $city );
		if ( $existing > 0 && ! $overwrite_existing ) {
			++$report['cities_skipped'];
			continue;
		}

		$pick = hovalvakil_location_pick_dominant_province( $cooccurrence[ $cid ] ?? [] );
		if ( $pick['province_id'] <= 0 ) {
			++$report['cities_skipped'];
			continue;
		}

		if ( $pick['is_conflict'] ) {
			$prov_names = [];
			foreach ( $pick['counts'] as $pid => $cnt ) {
				$pt = get_term( (int) $pid, 'hvl_province' );
				$prov_names[] = ( $pt instanceof WP_Term ? $pt->name : (string) $pid ) . " ($cnt)";
			}
			$report['cities_conflicts'][] = [
				'city_id'   => $cid,
				'city_name' => $city->name,
				'provinces' => $prov_names,
				'chosen'    => $pick['province_id'],
			];
		}

		if ( ! $dry_run ) {
			hovalvakil_city_set_province_id( $cid, $pick['province_id'] );
		}
		++$report['cities_linked'];
	}

	if ( $fix_lawyers ) {
		$lawyer_ids = get_posts(
			[
				'post_type'              => 'hvl_lawyer',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		foreach ( $lawyer_ids as $lawyer_id ) {
			$lawyer_id = (int) $lawyer_id;
			++$report['lawyers_checked'];

			$city_terms = wp_get_object_terms( $lawyer_id, 'hvl_city', [ 'fields' => 'ids' ] );
			if ( empty( $city_terms ) || is_wp_error( $city_terms ) ) {
				++$report['lawyers_no_city'];
				continue;
			}

			$city_id = (int) $city_terms[0];
			if ( hovalvakil_city_get_province_id( $city_id ) <= 0 ) {
				++$report['lawyers_no_mapping'];
				continue;
			}

			if ( $dry_run ) {
				$prov_terms = wp_get_object_terms( $lawyer_id, 'hvl_province', [ 'fields' => 'ids' ] );
				$expected   = hovalvakil_city_get_province_id( $city_id );
				$current    = ! empty( $prov_terms ) && ! is_wp_error( $prov_terms ) ? (int) $prov_terms[0] : 0;
				if ( $current !== $expected ) {
					++$report['lawyers_fixed'];
				}
				continue;
			}

			if ( hovalvakil_lawyer_sync_province_from_city( $lawyer_id ) ) {
				++$report['lawyers_fixed'];
			}
		}
	}

	if ( ! $dry_run && function_exists( 'hovalvakil_lawyer_bump_cache_version' ) ) {
		hovalvakil_lawyer_bump_cache_version();
	}

	if ( ! $dry_run && function_exists( 'hovalvakil_term_sync_all_route_slugs' ) ) {
		hovalvakil_term_sync_all_route_slugs( false );
	}

	return $report;
}

/**
 * After city/province terms change on a lawyer, keep province aligned with city.
 *
 * @param int    $object_id  Post ID.
 * @param array  $terms      Term IDs.
 * @param array  $tt_ids     Term taxonomy IDs.
 * @param string $taxonomy   Taxonomy slug.
 * @return void
 */
function hovalvakil_location_on_lawyer_terms_set( $object_id, $terms, $tt_ids, $taxonomy ): void {
	unset( $terms, $tt_ids );
	if ( 'hvl_city' !== $taxonomy || ! $object_id ) {
		return;
	}
	if ( 'hvl_lawyer' !== get_post_type( (int) $object_id ) ) {
		return;
	}
	static $guard = [];
	$key = (int) $object_id;
	if ( isset( $guard[ $key ] ) ) {
		return;
	}
	$guard[ $key ] = true;
	hovalvakil_lawyer_sync_province_from_city( $key );
	unset( $guard[ $key ] );
}
add_action( 'set_object_terms', 'hovalvakil_location_on_lawyer_terms_set', 20, 4 );

/**
 * City term edit: province selector.
 *
 * @param WP_Term $term Term.
 * @return void
 */
function hovalvakil_location_city_edit_province_field( WP_Term $term ): void {
	$current = hovalvakil_city_get_province_id( $term );
	$provs   = get_terms(
		[
			'taxonomy'   => 'hvl_province',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]
	);
	?>
	<tr class="form-field">
		<th scope="row"><label for="hvl-city-province-id"><?php esc_html_e( 'استان', 'hello-elementor' ); ?></label></th>
		<td>
			<select name="hvl_city_province_id" id="hvl-city-province-id">
				<option value="0"><?php esc_html_e( '— انتخاب استان —', 'hello-elementor' ); ?></option>
				<?php if ( ! is_wp_error( $provs ) ) : ?>
					<?php foreach ( $provs as $prov ) : ?>
						<?php if ( ! $prov instanceof WP_Term ) { continue; } ?>
						<option value="<?php echo esc_attr( (string) $prov->term_id ); ?>" <? selected( $current, (int) $prov->term_id ); ?>>
							<?php echo esc_html( $prov->name ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
			<p class="description"><?php esc_html_e( 'شهر زیرمجموعهٔ این استان است.', 'hello-elementor' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'hvl_city_edit_form_fields', 'hovalvakil_location_city_edit_province_field' );

/**
 * @return void
 */
function hovalvakil_location_city_add_province_field(): void {
	$provs = get_terms(
		[
			'taxonomy'   => 'hvl_province',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]
	);
	?>
	<div class="form-field">
		<label for="hvl-city-province-id"><?php esc_html_e( 'استان', 'hello-elementor' ); ?></label>
		<select name="hvl_city_province_id" id="hvl-city-province-id">
			<option value="0"><?php esc_html_e( '— انتخاب استان —', 'hello-elementor' ); ?></option>
			<?php if ( ! is_wp_error( $provs ) ) : ?>
				<?php foreach ( $provs as $prov ) : ?>
					<?php if ( ! $prov instanceof WP_Term ) { continue; } ?>
					<option value="<?php echo esc_attr( (string) $prov->term_id ); ?>">
						<?php echo esc_html( $prov->name ); ?>
					</option>
				<?php endforeach; ?>
			<?php endif; ?>
		</select>
		<p><?php esc_html_e( 'شهر زیرمجموعهٔ این استان است.', 'hello-elementor' ); ?></p>
	</div>
	<?php
}
add_action( 'hvl_city_add_form_fields', 'hovalvakil_location_city_add_province_field' );

/**
 * @param int $term_id Term ID.
 * @return void
 */
function hovalvakil_location_save_city_province_field( int $term_id ): void {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	if ( ! isset( $_POST['hvl_city_province_id'] ) ) {
		return;
	}
	$pid = max( 0, (int) wp_unslash( $_POST['hvl_city_province_id'] ) );
	hovalvakil_city_set_province_id( $term_id, $pid );
	if ( function_exists( 'hovalvakil_lawyer_bump_cache_version' ) ) {
		hovalvakil_lawyer_bump_cache_version();
	}
}
add_action( 'created_hvl_city', 'hovalvakil_location_save_city_province_field' );
add_action( 'edited_hvl_city', 'hovalvakil_location_save_city_province_field' );

/**
 * Admin: location sync page.
 *
 * @return void
 */
function hovalvakil_register_location_sync_menu(): void {
	add_submenu_page(
		'hovalvakil-lawyer-settings',
		__( 'هم‌ترازی استان و شهر', 'hello-elementor' ),
		__( 'استان و شهر', 'hello-elementor' ),
		'manage_options',
		'hovalvakil-location-sync',
		'hovalvakil_render_location_sync_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_location_sync_menu', 16 );

/**
 * @return void
 */
function hovalvakil_render_location_sync_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازهٔ دسترسی ندارید.', 'hello-elementor' ) );
	}

	$report = null;
	if ( isset( $_POST['hovalvakil_location_sync'] ) && check_admin_referer( 'hovalvakil_location_sync' ) ) {
		$report = hovalvakil_location_sync_all(
			[
				'dry_run'            => ! empty( $_POST['dry_run'] ),
				'fix_lawyers'        => ! empty( $_POST['fix_lawyers'] ),
				'overwrite_existing' => ! empty( $_POST['overwrite_existing'] ),
			]
		);
	}

	$cooccurrence = hovalvakil_location_analyze_lawyer_cooccurrence();
	$city_total   = (int) wp_count_terms( [ 'taxonomy' => 'hvl_city', 'hide_empty' => false ] );
	$prov_total   = (int) wp_count_terms( [ 'taxonomy' => 'hvl_province', 'hide_empty' => false ] );
	$linked       = 0;
	$cities       = get_terms( [ 'taxonomy' => 'hvl_city', 'hide_empty' => false, 'fields' => 'ids' ] );
	if ( ! is_wp_error( $cities ) ) {
		foreach ( $cities as $cid ) {
			if ( hovalvakil_city_get_province_id( (int) $cid ) > 0 ) {
				++$linked;
			}
		}
	}
	?>
	<div class="wrap" dir="rtl" style="direction:rtl;text-align:right;">
		<h1><?php esc_html_e( 'هم‌ترازی استان و شهر', 'hello-elementor' ); ?></h1>
		<p><?php esc_html_e( 'هر شهر باید به یک استان وصل باشد. این ابزار از روی وکلای منتشرشده، پرتکرارترین جفت شهر+استان را پیدا می‌کند، شهرها را به استان درست وصل می‌کند و استان وکلا را با شهرشان هماهنگ می‌کند.', 'hello-elementor' ); ?></p>
		<ul>
			<li><?php echo esc_html( sprintf( __( 'استان‌ها: %d', 'hello-elementor' ), $prov_total ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'شهرها: %d', 'hello-elementor' ), $city_total ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'شهرهای دارای استان (meta): %d', 'hello-elementor' ), $linked ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'جفت‌های شهر+استان روی وکلا: %d', 'hello-elementor' ), count( $cooccurrence ) ) ); ?></li>
		</ul>

		<form method="post">
			<?php wp_nonce_field( 'hovalvakil_location_sync' ); ?>
			<p>
				<label><input type="checkbox" name="dry_run" value="1" checked /> <?php esc_html_e( 'فقط گزارش (بدون ذخیره)', 'hello-elementor' ); ?></label><br/>
				<label><input type="checkbox" name="fix_lawyers" value="1" checked /> <?php esc_html_e( 'اصلاح استان روی وکلا', 'hello-elementor' ); ?></label><br/>
				<label><input type="checkbox" name="overwrite_existing" value="1" /> <?php esc_html_e( 'بازنویسی شهرهایی که قبلاً استان دارند', 'hello-elementor' ); ?></label>
			</p>
			<p><button type="submit" name="hovalvakil_location_sync" class="button button-primary"><?php esc_html_e( 'اجرای هم‌ترازی', 'hello-elementor' ); ?></button></p>
		</form>

		<?php if ( is_array( $report ) ) : ?>
			<h2><?php esc_html_e( 'نتیجه', 'hello-elementor' ); ?></h2>
			<pre style="direction:ltr;text-align:left;background:#f6f7f7;padding:12px;max-width:900px;overflow:auto;"><?php echo esc_html( wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre>
		<?php endif; ?>
	</div>
	<?php
}
