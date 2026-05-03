<?php
/**
 * Archive template for Lawyers.
 *
 * Matches the UI of hovalvakil/arshive.html but uses WP data.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get GET param as trimmed string.
 *
 * @param string $key Key.
 *
 * @return string
 */
function hovalvakil_get_param( $key ) {
	$value = isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : '';
	$value = is_string( $value ) ? trim( $value ) : '';
	return $value;
}

/**
 * Get GET param as string array (supports comma separated too).
 *
 * @param string $key Key.
 *
 * @return string[]
 */
function hovalvakil_get_param_array( $key ) {
	$sources = [];
	if ( isset( $_GET[ $key ] ) ) {
		$sources[] = $_GET[ $key ];
	}
	// Some URLs use literal key "specialty[]" / "city_term[]" instead of PHP's nested array.
	$bracket_key = $key . '[]';
	if ( isset( $_GET[ $bracket_key ] ) ) {
		$sources[] = $_GET[ $bracket_key ];
	}

	if ( empty( $sources ) ) {
		return [];
	}

	$merged = [];
	foreach ( $sources as $raw ) {
		$value = wp_unslash( $raw );
		if ( is_array( $value ) ) {
			$merged = array_merge( $merged, $value );
		} elseif ( is_string( $value ) && '' !== $value ) {
			$merged = array_merge( $merged, array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
		}
	}

	$values = array_filter(
		array_map(
			static function ( $v ) {
				return is_string( $v ) ? sanitize_text_field( trim( $v ) ) : '';
			},
			$merged
		)
	);

	return array_values( array_unique( $values ) );
}

$q           = sanitize_text_field( hovalvakil_get_param( 'q' ) );
$city        = sanitize_text_field( hovalvakil_get_param( 'city' ) );
$specialties = hovalvakil_get_param_array( 'specialty' ); // term slugs.
$city_terms_selected = hovalvakil_get_param_array( 'city_term' ); // city term slugs.
$province_terms_selected = hovalvakil_get_param_array( 'province_term' ); // province term slugs.
$selected_specialty = ! empty( $specialties ) ? $specialties[0] : '';

$tax_query = [];

// Backward compatibility: if city param is a city name, convert to slug.
if ( '' !== $city ) {
	$city_term_obj = get_term_by( 'name', $city, 'hvl_city' );
	if ( $city_term_obj && ! is_wp_error( $city_term_obj ) ) {
		$city_terms_selected[] = $city_term_obj->slug;
	}
}
$city_terms_selected = array_values( array_unique( array_filter( $city_terms_selected ) ) );
$selected_city_slug  = ! empty( $city_terms_selected ) ? $city_terms_selected[0] : '';

$province_terms_selected = array_values( array_unique( array_filter( $province_terms_selected ) ) );
$selected_province_slug    = ! empty( $province_terms_selected ) ? $province_terms_selected[0] : '';

if ( '' !== $city ) {
	// Keep query param visible in URL but use slug-based filtering.
}

if ( ! empty( $city_terms_selected ) ) {
	$tax_query[] = [
		'taxonomy' => 'hvl_city',
		'field'    => 'slug',
		'terms'    => $city_terms_selected,
	];
}

if ( ! empty( $specialties ) ) {
	$tax_query[] = [
		'taxonomy' => 'hvl_specialty',
		'field'    => 'slug',
		'terms'    => $specialties,
	];
}

if ( ! empty( $province_terms_selected ) ) {
	$tax_query[] = [
		'taxonomy' => 'hvl_province',
		'field'    => 'slug',
		'terms'    => $province_terms_selected,
	];
}

if ( count( $tax_query ) > 1 ) {
	$tax_query['relation'] = 'AND';
}

$paged = max( 1, (int) get_query_var( 'paged' ) );

$base_query_args = [
	'post_type'      => 'hvl_lawyer',
	'post_status'    => 'publish',
	'posts_per_page' => 20,
	'paged'          => $paged,
];

if ( ! empty( $tax_query ) ) {
	$base_query_args['tax_query'] = $tax_query;
}

$city_terms = get_terms(
	[
		'taxonomy'   => 'hvl_city',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]
);

$specialty_terms = get_terms(
	[
		'taxonomy'   => 'hvl_specialty',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]
);

$province_terms = get_terms(
	[
		'taxonomy'   => 'hvl_province',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]
);

// Strong search: combine text match + taxonomy name match and then apply base filters.
if ( '' !== $q ) {
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

	$matched_province_slugs = [];
	if ( ! is_wp_error( $province_terms ) ) {
		foreach ( $province_terms as $pterm ) {
			if ( false !== mb_stripos( $pterm->name, $q ) ) {
				$matched_province_slugs[] = $pterm->slug;
			}
		}
	}

	$text_query_args = $base_query_args;
	$text_query_args['fields']         = 'ids';
	$text_query_args['posts_per_page'] = 300;
	$text_query_args['paged']          = 1;
	$text_query_args['no_found_rows']  = true;
	$text_query_args['s']              = $q;
	$text_ids = get_posts( $text_query_args );

	$tax_match_ids = [];
	if ( ! empty( $matched_city_slugs ) || ! empty( $matched_specialty_slugs ) || ! empty( $matched_province_slugs ) ) {
		$tax_match_query_args = $base_query_args;
		$tax_match_query_args['fields']         = 'ids';
		$tax_match_query_args['posts_per_page'] = 300;
		$tax_match_query_args['paged']          = 1;
		$tax_match_query_args['no_found_rows']  = true;

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
		if ( ! empty( $matched_province_slugs ) ) {
			$q_tax[] = [
				'taxonomy' => 'hvl_province',
				'field'    => 'slug',
				'terms'    => $matched_province_slugs,
			];
		}
		if ( count( $q_tax ) > 1 ) {
			$q_tax['relation'] = 'OR';
		}

		// Merge query-match tax with existing filters.
		if ( ! empty( $tax_match_query_args['tax_query'] ) ) {
			$tax_match_query_args['tax_query'] = [
				'relation' => 'AND',
				$tax_match_query_args['tax_query'],
				$q_tax,
			];
		} else {
			$tax_match_query_args['tax_query'] = $q_tax;
		}

		$tax_match_ids = get_posts( $tax_match_query_args );
	}

	$matched_ids = array_values( array_unique( array_map( 'intval', array_merge( $text_ids, $tax_match_ids ) ) ) );

	$query_args = $base_query_args;
	if ( empty( $matched_ids ) ) {
		$query_args['post__in'] = [ 0 ];
	} else {
		$query_args['post__in'] = $matched_ids;
		$query_args['orderby']  = 'post__in';
	}
} else {
	$query_args = $base_query_args;
}

$lawyers_query = new WP_Query( $query_args );

get_header();
?>

<main id="content" class="archive-main container">
	<section class="archive-search-card">
		<form id="archive-search-form" class="archive-search-grid" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'hvl_lawyer' ) ); ?>">
			<div class="archive-field">
				<span class="material-symbols-outlined">map</span>
				<select name="province_term[]" id="archive-province-select">
					<option value="">همه استان‌ها</option>
					<?php if ( ! is_wp_error( $province_terms ) && ! empty( $province_terms ) ) : ?>
						<?php foreach ( $province_terms as $prov_term_item ) : ?>
							<option value="<?php echo esc_attr( $prov_term_item->slug ); ?>" <?php selected( $selected_province_slug, $prov_term_item->slug ); ?>>
								<?php echo esc_html( $prov_term_item->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>
			<div class="archive-field">
				<span class="material-symbols-outlined">location_on</span>
				<select name="city_term[]" id="archive-city-select">
					<option value="">همه شهرها</option>
					<?php if ( ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ) : ?>
						<?php foreach ( $city_terms as $city_term_item ) : ?>
							<option value="<?php echo esc_attr( $city_term_item->slug ); ?>" <?php selected( $selected_city_slug, $city_term_item->slug ); ?>>
								<?php echo esc_html( $city_term_item->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>
			<div class="archive-field">
				<span class="material-symbols-outlined">gavel</span>
				<select name="specialty[]" id="archive-specialty-select">
					<option value="">همه تخصص‌ها</option>
					<?php
					if ( ! is_wp_error( $specialty_terms ) && ! empty( $specialty_terms ) ) :
						foreach ( $specialty_terms as $sterm ) :
							?>
							<option value="<?php echo esc_attr( $sterm->slug ); ?>" <?php selected( $selected_specialty, $sterm->slug ); ?>>
								<?php echo esc_html( $sterm->name ); ?>
							</option>
							<?php
						endforeach;
					endif;
					?>
				</select>
			</div>
			<div class="archive-field">
				<span class="material-symbols-outlined">person_search</span>
				<input id="archive-q-input" type="text" name="q" placeholder="نام وکیل، تخصص یا شهر" value="<?php echo esc_attr( $q ); ?>"/>
			</div>
			<button class="archive-search-btn" type="submit">
				<span class="material-symbols-outlined">search</span>
				جستجو
			</button>
		</form>
		<?php
		$active_line_parts = [];
		if ( '' !== $q && mb_strlen( trim( $q ) ) >= 2 ) {
			$active_line_parts[] = 'جستجو: ' . $q;
		}
		if ( ! empty( $city_terms_selected ) && ! is_wp_error( $city_terms ) ) {
			$city_names = [];
			foreach ( $city_terms as $ct ) {
				if ( in_array( $ct->slug, $city_terms_selected, true ) ) {
					$city_names[] = $ct->name;
				}
			}
			if ( ! empty( $city_names ) ) {
				$active_line_parts[] = 'شهر: ' . implode( '، ', $city_names );
			}
		}
		if ( ! empty( $specialties ) ) {
			$spec_names = [];
			if ( ! is_wp_error( $specialty_terms ) ) {
				foreach ( $specialty_terms as $st ) {
					if ( in_array( $st->slug, $specialties, true ) ) {
						$spec_names[] = $st->name;
					}
				}
			}
			$active_line_parts[] = 'تخصص: ' . implode( '، ', ( ! empty( $spec_names ) ? $spec_names : $specialties ) );
		}
		if ( ! empty( $province_terms_selected ) && ! is_wp_error( $province_terms ) ) {
			$prov_names = [];
			foreach ( $province_terms as $pt ) {
				if ( in_array( $pt->slug, $province_terms_selected, true ) ) {
					$prov_names[] = $pt->name;
				}
			}
			if ( ! empty( $prov_names ) ) {
				$active_line_parts[] = 'استان: ' . implode( '، ', $prov_names );
			}
		}
		$active_line_visible = ! empty( $active_line_parts );
		?>
		<div id="archive-active-filters" class="text-xs text-on-surface-variant" style="margin-top:10px;<?php echo $active_line_visible ? '' : ' display:none;'; ?>">
			<?php echo $active_line_visible ? esc_html( 'فیلتر فعال: ' . implode( ' | ', $active_line_parts ) ) : ''; ?>
		</div>

		<p id="archive-result-count" class="archive-result-count">
			<?php echo esc_html( number_format_i18n( (int) $lawyers_query->found_posts ) ); ?> وکیل یافت شد
		</p>
	</section>

	<section class="archive-layout">
		<aside id="archive-filters-panel" class="archive-filters">
			<div class="archive-panel">
				<div class="archive-panel-head">
					<h2><span class="material-symbols-outlined">tune</span>فیلترها</h2>
					<div class="archive-panel-head-actions">
						<a href="<?php echo esc_url( get_post_type_archive_link( 'hvl_lawyer' ) ); ?>" class="archive-clear-filters-link">پاک کردن همه</a>
						<button id="archive-filters-close" class="archive-filters-close-btn" type="button" aria-label="بستن فیلترها">
							<span class="material-symbols-outlined">close</span>
						</button>
					</div>
				</div>

				<div class="archive-filter-group">
					<h3>تخصص</h3>
					<div id="archive-specialty-group" class="archive-filter-group-content archive-filter-list-scroll">
						<?php
						$terms = get_terms(
							[
								'taxonomy'   => 'hvl_specialty',
								'hide_empty' => false,
								'orderby'    => 'name',
								'order'      => 'ASC',
							]
						);

						if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) :
							foreach ( $terms as $term ) :
								$checked = in_array( $term->slug, $specialties, true );
								?>
								<label>
									<input type="checkbox" name="specialty[]" value="<?php echo esc_attr( $term->slug ); ?>" form="archive-filters-form" <?php checked( $checked ); ?>/>
									<?php echo esc_html( $term->name ); ?>
								</label>
								<?php
							endforeach;
						else :
							?>
							<p class="text-xs text-on-surface-variant">هنوز تخصصی ثبت نشده است.</p>
							<?php
						endif;
						?>
					</div>
				</div>

				<form id="archive-filters-form" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'hvl_lawyer' ) ); ?>" style="display:none;">
					<input type="hidden" name="q" value="<?php echo esc_attr( $q ); ?>"/>
					<input type="hidden" name="city" value="<?php echo esc_attr( $city ); ?>"/>
				</form>

				<div class="archive-filter-group">
					<h3>استان</h3>
					<div class="archive-filter-list-scroll">
						<?php if ( ! is_wp_error( $province_terms ) && ! empty( $province_terms ) ) : ?>
							<?php foreach ( $province_terms as $prov_filter_term ) : ?>
								<?php $prov_checked = in_array( $prov_filter_term->slug, $province_terms_selected, true ); ?>
								<label>
									<input type="checkbox" name="province_term[]" value="<?php echo esc_attr( $prov_filter_term->slug ); ?>" form="archive-filters-form" <?php checked( $prov_checked ); ?>/>
									<?php echo esc_html( $prov_filter_term->name ); ?>
								</label>
							<?php endforeach; ?>
						<?php else : ?>
							<p class="text-xs text-on-surface-variant">هنوز استانی ثبت نشده است.</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="archive-filter-group">
					<h3>شهر</h3>
					<div class="archive-filter-list-scroll">
						<?php if ( ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ) : ?>
							<?php foreach ( $city_terms as $city_filter_term ) : ?>
								<?php $city_checked = in_array( $city_filter_term->slug, $city_terms_selected, true ); ?>
								<label>
									<input type="checkbox" name="city_term[]" value="<?php echo esc_attr( $city_filter_term->slug ); ?>" form="archive-filters-form" <?php checked( $city_checked ); ?>/>
									<?php echo esc_html( $city_filter_term->name ); ?>
								</label>
							<?php endforeach; ?>
						<?php else : ?>
							<p class="text-xs text-on-surface-variant">هنوز شهری ثبت نشده است.</p>
						<?php endif; ?>
					</div>
				</div>

			</div>
		</aside>

		<div class="archive-results">
			<div id="archive-results-list" aria-live="polite">
			<?php if ( $lawyers_query->have_posts() ) : ?>
				<?php
				while ( $lawyers_query->have_posts() ) :
					$lawyers_query->the_post();

					$avatar_url = function_exists( 'hovalvakil_lawyer_profile_image_url' )
						? hovalvakil_lawyer_profile_image_url( get_the_ID(), 'medium' )
						: (string) get_the_post_thumbnail_url( get_the_ID(), 'medium' );
					if ( ! $avatar_url ) {
						$avatar_url = 'https://via.placeholder.com/160x160.png?text=%D9%88%DA%A9%DB%8C%D9%84';
					}

					$specialty_term = null;
					$spec_terms     = get_the_terms( get_the_ID(), 'hvl_specialty' );
					if ( is_array( $spec_terms ) && ! empty( $spec_terms ) ) {
						$specialty_term = $spec_terms[0];
					}

					$city_term = null;
					$city_terms = get_the_terms( get_the_ID(), 'hvl_city' );
					if ( is_array( $city_terms ) && ! empty( $city_terms ) ) {
						$city_term = $city_terms[0];
					}

					$province_term = null;
					$province_terms_card = get_the_terms( get_the_ID(), 'hvl_province' );
					if ( is_array( $province_terms_card ) && ! empty( $province_terms_card ) ) {
						$province_term = $province_terms_card[0];
					}

					$lawyer_grade_tag = (string) get_post_meta( get_the_ID(), 'hvl_lawyer_grade', true );
					$license_exp_card = (string) get_post_meta( get_the_ID(), 'hvl_license_expires', true );
					$license_exp_disp = function_exists( 'hovalvakil_lawyer_format_license_expires_display' )
						? hovalvakil_lawyer_format_license_expires_display( $license_exp_card )
						: '';

					$experience = (string) get_post_meta( get_the_ID(), 'hvl_experience', true );
					$price_from = (string) get_post_meta( get_the_ID(), 'hvl_price_from', true );
					$rating     = (string) get_post_meta( get_the_ID(), 'hvl_rating', true );
					$reviews    = (string) get_post_meta( get_the_ID(), 'hvl_reviews_count', true );
					$reservation_url = add_query_arg(
						[
							'lawyer_id' => get_the_ID(),
							'name'      => get_the_title(),
							'specialty' => $specialty_term ? $specialty_term->name : '',
							'city'      => $city_term ? $city_term->name : '',
							'image'     => $avatar_url,
							'price'     => $price_from,
							'service'   => 'مشاوره حضوری',
						],
						home_url( '/rezerv' )
					);
					?>
					<article class="archive-lawyer-card">
						<div class="archive-lawyer-main">
							<img class="archive-lawyer-avatar" src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>"/>
							<div class="archive-lawyer-content">
								<div class="archive-lawyer-title-row">
									<h3><?php the_title(); ?></h3>
									<span class="material-symbols-outlined archive-verified-icon">verified</span>
								</div>
								<p class="archive-lawyer-specialty">
									<?php echo esc_html( $specialty_term ? $specialty_term->name : '—' ); ?>
								</p>
								<div class="archive-lawyer-tags">
									<span><?php echo esc_html( '' !== $lawyer_grade_tag ? $lawyer_grade_tag : '—' ); ?></span>
									<?php if ( $province_term ) : ?>
										<span><?php echo esc_html( $province_term->name ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== $license_exp_disp ) : ?>
										<span>انقضا: <?php echo esc_html( $license_exp_disp ); ?></span>
									<?php else : ?>
										<span>مشاوره حقوقی</span>
									<?php endif; ?>
								</div>
								<div class="archive-lawyer-meta">
									<p><span class="material-symbols-outlined">history_edu</span><?php echo esc_html( '' !== $experience ? $experience : '—' ); ?></p>
									<p><span class="material-symbols-outlined">location_on</span><?php echo esc_html( implode( '، ', array_filter( [ $province_term ? $province_term->name : '', $city_term ? $city_term->name : '' ] ) ) ?: '—' ); ?></p>
								</div>
							</div>
						</div>
						<div class="archive-lawyer-actions">
							<div class="archive-lawyer-rating">
								<span class="material-symbols-outlined">star</span>
								<strong><?php echo esc_html( '' !== $rating ? $rating : '—' ); ?></strong>
								<small>(<?php echo esc_html( '' !== $reviews ? $reviews : '۰' ); ?> نظر)</small>
							</div>
							<p><?php echo esc_html( '' !== $price_from ? 'از ' . $price_from : '—' ); ?></p>
							<div class="archive-lawyer-cta">
								<a href="<?php echo esc_url( $reservation_url ); ?>" class="archive-btn-secondary">رزرو نوبت</a>
								<a href="<?php the_permalink(); ?>" class="btn-primary-sm">مشاهده پروفایل</a>
							</div>
						</div>
					</article>
					<?php
				endwhile;
				?>

			<?php else : ?>
				<p class="text-on-surface-variant">موردی یافت نشد.</p>
			<?php endif; ?>
			</div>
			<div id="archive-pagination-wrap">
				<?php
				$pagination = paginate_links(
					[
						'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
						'format'    => '',
						'current'   => $paged,
						'total'     => (int) $lawyers_query->max_num_pages,
						'type'      => 'list',
						'prev_text' => 'قبلی',
						'next_text' => 'بعدی',
					]
				);
				if ( $pagination ) :
					echo wp_kses_post( $pagination );
				endif;
				?>
			</div>
			<?php wp_reset_postdata(); ?>
		</div>
	</section>
</main>

<button id="archive-filters-fab" class="archive-filters-fab" type="button" aria-label="باز کردن فیلترها">
	<span class="material-symbols-outlined">tune</span>
	<span>فیلترها</span>
</button>

<div id="archive-filters-backdrop" class="archive-filters-backdrop"></div>

<script>
	const filtersFab = document.getElementById('archive-filters-fab');
	const filtersCloseBtn = document.getElementById('archive-filters-close');
	const filtersPanel = document.getElementById('archive-filters-panel');
	const filtersBackdrop = document.getElementById('archive-filters-backdrop');
	const specialtySelect = document.getElementById('archive-specialty-select');
	const citySelect = document.getElementById('archive-city-select');
	const provinceSelect = document.getElementById('archive-province-select');
	const searchForm = document.getElementById('archive-search-form');
	const qInput = document.getElementById('archive-q-input');
	const resultsList = document.getElementById('archive-results-list');
	const resultCount = document.getElementById('archive-result-count');
	const paginationWrap = document.getElementById('archive-pagination-wrap');
	const archiveActiveFiltersEl = document.getElementById('archive-active-filters');
	const ajaxApiBase = <?php echo wp_json_encode( esc_url_raw( rest_url( 'hovalvakil/v1/lawyers' ) ) ); ?>;
	const archiveInitialSlugs = <?php echo wp_json_encode( [ 'specialty' => $selected_specialty, 'city_term' => $selected_city_slug, 'province_term' => $selected_province_slug ], JSON_UNESCAPED_UNICODE ); ?>;
	const ARCHIVE_PER_PAGE = 20;
	let archiveDebounceTimer = null;
	let archiveCurrentPage = 1;
	let archiveTotalPages = 1;
	let archiveRequestToken = 0;
	let archiveFetchController = null;

	function closeFiltersOffcanvas() {
		filtersPanel?.classList.remove('is-open');
		filtersBackdrop?.classList.remove('is-open');
		document.body.classList.remove('archive-offcanvas-open');
	}

	function openFiltersOffcanvas() {
		filtersPanel?.classList.add('is-open');
		filtersBackdrop?.classList.add('is-open');
		document.body.classList.add('archive-offcanvas-open');
	}

	filtersFab?.addEventListener('click', () => {
		if (filtersPanel?.classList.contains('is-open')) {
			closeFiltersOffcanvas();
			return;
		}
		openFiltersOffcanvas();
	});
	filtersBackdrop?.addEventListener('click', closeFiltersOffcanvas);
	filtersCloseBtn?.addEventListener('click', closeFiltersOffcanvas);

	// Submit filters when checkbox changes.
	document.querySelectorAll('#archive-filters-panel input[type="checkbox"]').forEach((cb) => {
		cb.addEventListener('change', () => {
			runArchiveAjaxFilter();
		});
	});

	specialtySelect?.addEventListener('change', () => {
		const checkedBoxes = document.querySelectorAll('#archive-filters-panel input[type="checkbox"]');
		checkedBoxes.forEach((box) => {
			if (box.getAttribute('name') === 'specialty[]') {
				box.checked = false;
			}
		});
		const selectedValue = specialtySelect.value;
		if (selectedValue) {
			const target = document.querySelector(`#archive-filters-panel input[type="checkbox"][name="specialty[]"][value="${CSS.escape(selectedValue)}"]`);
			if (target) target.checked = true;
		}
		runArchiveAjaxFilter();
	});

	citySelect?.addEventListener('change', () => {
		const cityBoxes = document.querySelectorAll('#archive-filters-panel input[type="checkbox"][name="city_term[]"]');
		cityBoxes.forEach((box) => {
			box.checked = false;
		});
		const selectedValue = citySelect.value;
		if (selectedValue) {
			const target = document.querySelector(`#archive-filters-panel input[type="checkbox"][name="city_term[]"][value="${CSS.escape(selectedValue)}"]`);
			if (target) target.checked = true;
		}
		runArchiveAjaxFilter();
	});

	provinceSelect?.addEventListener('change', () => {
		const provBoxes = document.querySelectorAll('#archive-filters-panel input[type="checkbox"][name="province_term[]"]');
		provBoxes.forEach((box) => {
			box.checked = false;
		});
		const selectedValue = provinceSelect.value;
		if (selectedValue) {
			const target = document.querySelector(`#archive-filters-panel input[type="checkbox"][name="province_term[]"][value="${CSS.escape(selectedValue)}"]`);
			if (target) target.checked = true;
		}
		runArchiveAjaxFilter();
	});

	qInput?.addEventListener('input', () => {
		if (archiveDebounceTimer) clearTimeout(archiveDebounceTimer);
		archiveDebounceTimer = setTimeout(runArchiveAjaxFilter, 220);
	});

	searchForm?.addEventListener('submit', (event) => {
		event.preventDefault();
		runArchiveAjaxFilter();
	});

	function escapeHtml(str) {
		return (str || '')
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;')
			.replaceAll("'", '&#039;');
	}

	function archiveSkeletonCardHtml() {
		return (
			'<article class="archive-lawyer-card archive-lawyer-card--skeleton" aria-hidden="true">' +
			'<div class="archive-lawyer-main">' +
			'<div class="archive-lawyer-skeleton-avatar"></div>' +
			'<div class="archive-lawyer-skeleton-content">' +
			'<div class="archive-lawyer-skeleton-line archive-lawyer-skeleton-line--title"></div>' +
			'<div class="archive-lawyer-skeleton-line archive-lawyer-skeleton-line--mid"></div>' +
			'<div class="archive-lawyer-skeleton-tags"><span></span><span></span></div>' +
			'<div class="archive-lawyer-skeleton-line archive-lawyer-skeleton-line--short"></div>' +
			'</div></div>' +
			'<div class="archive-lawyer-actions">' +
			'<div class="archive-lawyer-skeleton-actions">' +
			'<div class="archive-lawyer-skeleton-line archive-lawyer-skeleton-line--xs"></div>' +
			'<div class="archive-lawyer-skeleton-cta">' +
			'<div class="archive-lawyer-skeleton-btn"></div>' +
			'<div class="archive-lawyer-skeleton-btn"></div>' +
			'</div></div></div></article>'
		);
	}

	function showArchiveSkeleton(count) {
		if (!resultsList) return;
		let html = '';
		for (let i = 0; i < count; i += 1) {
			html += archiveSkeletonCardHtml();
		}
		resultsList.innerHTML = html;
	}

	function getCheckedValues(name) {
		return Array.from(document.querySelectorAll(`#archive-filters-panel input[name="${name}"]:checked`))
			.map((el) => el.value)
			.filter(Boolean);
	}

	function syncTopFiltersFromSidebar() {
		const citySlugs = getCheckedValues('city_term[]');
		const specialtySlugs = getCheckedValues('specialty[]');
		const provinceSlugs = getCheckedValues('province_term[]');
		if (citySelect) {
			citySelect.value = citySlugs[0] || '';
		}
		if (specialtySelect) {
			specialtySelect.value = specialtySlugs[0] || '';
		}
		if (provinceSelect) {
			provinceSelect.value = provinceSlugs[0] || '';
		}
	}

	function archiveTermDisplayName(type, slug) {
		if (!slug) return '';
		let select = citySelect;
		let inputName = 'city_term[]';
		if (type === 'specialty') {
			select = specialtySelect;
			inputName = 'specialty[]';
		} else if (type === 'province') {
			select = provinceSelect;
			inputName = 'province_term[]';
		}
		const opt = select?.querySelector(`option[value="${CSS.escape(slug)}"]`);
		if (opt && opt.value) return opt.textContent.trim();
		const input = document.querySelector(`#archive-filters-panel input[name="${inputName}"][value="${CSS.escape(slug)}"]`);
		if (input?.labels?.length) return input.labels[0].textContent.replace(/\s+/g, ' ').trim();
		return slug;
	}

	/** Keep «فیلتر فعال» in sync with selects/checkboxes/q after AJAX (PHP only renders first paint). */
	function updateArchiveActiveFiltersDisplay() {
		if (!archiveActiveFiltersEl) return;
		const qTrim = qInput?.value?.trim() || '';
		const qLine = qTrim.length >= 2 ? qTrim : '';
		const selectedCity = citySelect?.value?.trim() || '';
		const selectedSpecialty = specialtySelect?.value?.trim() || '';
		const selectedProvince = provinceSelect?.value?.trim() || '';
		const mergedCitySlugs = Array.from(new Set([...getCheckedValues('city_term[]'), ...(selectedCity ? [selectedCity] : [])]));
		const mergedSpecialtySlugs = Array.from(new Set([...getCheckedValues('specialty[]'), ...(selectedSpecialty ? [selectedSpecialty] : [])]));
		const mergedProvinceSlugs = Array.from(new Set([...getCheckedValues('province_term[]'), ...(selectedProvince ? [selectedProvince] : [])]));
		const parts = [];
		if (qLine) parts.push(`جستجو: ${qLine}`);
		if (mergedProvinceSlugs.length) {
			const names = mergedProvinceSlugs.map((s) => archiveTermDisplayName('province', s)).filter(Boolean);
			if (names.length) parts.push(`استان: ${names.join('، ')}`);
		}
		if (mergedCitySlugs.length) {
			const names = mergedCitySlugs.map((s) => archiveTermDisplayName('city', s)).filter(Boolean);
			if (names.length) parts.push(`شهر: ${names.join('، ')}`);
		}
		if (mergedSpecialtySlugs.length) {
			const names = mergedSpecialtySlugs.map((s) => archiveTermDisplayName('specialty', s)).filter(Boolean);
			if (names.length) parts.push(`تخصص: ${names.join('، ')}`);
		}
		if (!parts.length) {
			archiveActiveFiltersEl.style.display = 'none';
			archiveActiveFiltersEl.textContent = '';
			return;
		}
		archiveActiveFiltersEl.style.display = '';
		archiveActiveFiltersEl.textContent = `فیلتر فعال: ${parts.join(' | ')}`;
	}

	function updateArchiveUrlState({ q, provinceSlugs, citySlugs, specialtySlugs, page }) {
		const params = new URLSearchParams();
		if (q) params.set('q', q);
		provinceSlugs.forEach((slug) => params.append('province_term[]', slug));
		citySlugs.forEach((slug) => params.append('city_term[]', slug));
		specialtySlugs.forEach((slug) => params.append('specialty[]', slug));
		if (page && page > 1) params.set('paged', String(page));
		const nextUrl = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}`;
		window.history.replaceState({}, '', nextUrl);
	}

	function renderArchiveItems(items) {
		if (!resultsList) return;
		if (!items.length) {
			resultsList.innerHTML = '<p class="text-on-surface-variant">موردی یافت نشد.</p>';
			return;
		}
		const html = items.map((item) => {
			const grade = escapeHtml(item.lawyer_grade || '—');
			const prov = escapeHtml(item.province || '');
			const expDisp = escapeHtml(item.license_expires_display || '');
			const tag3 = expDisp ? `انقضا: ${expDisp}` : 'مشاوره حقوقی';
			const loc = escapeHtml(item.location_line || item.city || '—');
			return `
			<article class="archive-lawyer-card">
				<div class="archive-lawyer-main">
					<img class="archive-lawyer-avatar" src="${escapeHtml(item.image || '')}" alt="${escapeHtml(item.name || '')}"/>
					<div class="archive-lawyer-content">
						<div class="archive-lawyer-title-row">
							<h3>${escapeHtml(item.name || '')}</h3>
							<span class="material-symbols-outlined archive-verified-icon">verified</span>
						</div>
						<p class="archive-lawyer-specialty">${escapeHtml(item.specialty || '—')}</p>
						<div class="archive-lawyer-tags">
							<span>${grade}</span>
							${prov ? `<span>${prov}</span>` : ''}
							<span>${tag3}</span>
						</div>
						<div class="archive-lawyer-meta">
							<p><span class="material-symbols-outlined">history_edu</span>${escapeHtml(item.experience || '—')}</p>
							<p><span class="material-symbols-outlined">location_on</span>${loc}</p>
						</div>
					</div>
				</div>
				<div class="archive-lawyer-actions">
					<div class="archive-lawyer-rating">
						<span class="material-symbols-outlined">star</span>
						<strong>${escapeHtml(item.rating || '—')}</strong>
						<small>(${escapeHtml(item.reviews || '۰')} نظر)</small>
					</div>
					<p>${escapeHtml(item.price_from ? `از ${item.price_from}` : '—')}</p>
					<div class="archive-lawyer-cta">
						<a href="${escapeHtml(item.reservation_url || '#')}" class="archive-btn-secondary">رزرو نوبت</a>
						<a href="${escapeHtml(item.permalink || '#')}" class="btn-primary-sm">مشاهده پروفایل</a>
					</div>
				</div>
			</article>
		`;
		}).join('');
		resultsList.innerHTML = html;
	}

	function renderArchivePagination(currentPage, totalPages) {
		if (!paginationWrap) return;
		if (!totalPages || totalPages <= 1) {
			paginationWrap.innerHTML = '';
			return;
		}

		const maxButtons = 5;
		let start = Math.max(1, currentPage - Math.floor(maxButtons / 2));
		let end = Math.min(totalPages, start + maxButtons - 1);
		start = Math.max(1, end - maxButtons + 1);

		let html = '<ul class="archive-pagination-list">';
		html += `<li><button type="button" class="archive-page-btn archive-page-prev" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>قبلی</button></li>`;

		for (let p = start; p <= end; p += 1) {
			html += `<li><button type="button" class="archive-page-btn ${p === currentPage ? 'is-active' : ''}" data-page="${p}">${p}</button></li>`;
		}

		html += `<li><button type="button" class="archive-page-btn archive-page-next" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>بعدی</button></li>`;
		html += '</ul>';
		paginationWrap.innerHTML = html;
	}

	async function runArchiveAjaxFilter(page = 1) {
		const qTrim = qInput?.value?.trim() || '';
		// Match API: very short q triggers "strong search" with empty matches → no posts; skip sending.
		const q = qTrim.length >= 2 ? qTrim : '';
		const selectedCity = citySelect?.value?.trim() || '';
		const selectedSpecialty = specialtySelect?.value?.trim() || '';
		const selectedProvince = provinceSelect?.value?.trim() || '';
		const citySlugs = getCheckedValues('city_term[]');
		const specialtySlugs = getCheckedValues('specialty[]');
		const provinceSlugs = getCheckedValues('province_term[]');

		const mergedCitySlugs = Array.from(new Set([...citySlugs, ...(selectedCity ? [selectedCity] : [])]));
		const mergedSpecialtySlugs = Array.from(new Set([...specialtySlugs, ...(selectedSpecialty ? [selectedSpecialty] : [])]));
		const mergedProvinceSlugs = Array.from(new Set([...provinceSlugs, ...(selectedProvince ? [selectedProvince] : [])]));

		const params = new URLSearchParams({ per_page: String(ARCHIVE_PER_PAGE), page: String(page) });
		if (q) params.set('q', q);
		if (mergedProvinceSlugs.length) params.set('province_term', mergedProvinceSlugs.join(','));
		if (mergedCitySlugs.length) params.set('city_term', mergedCitySlugs.join(','));
		if (mergedSpecialtySlugs.length) params.set('specialty', mergedSpecialtySlugs.join(','));

		if (archiveFetchController) {
			archiveFetchController.abort();
		}
		archiveFetchController = new AbortController();
		const token = ++archiveRequestToken;

		if (resultsList) {
			resultsList.setAttribute('aria-busy', 'true');
			resultsList.classList.add('archive-results-loading');
			showArchiveSkeleton(ARCHIVE_PER_PAGE);
		}

		try {
			const response = await fetch(`${ajaxApiBase}?${params.toString()}`, {
				credentials: 'same-origin',
				signal: archiveFetchController.signal,
			});
			if (!response.ok) throw new Error('ajax_filter_failed');
			const data = await response.json();
			if (token !== archiveRequestToken) return;
			renderArchiveItems(Array.isArray(data.items) ? data.items : []);
			archiveCurrentPage = Number(data.page || 1);
			archiveTotalPages = Number(data.totalPages || 1);
			renderArchivePagination(archiveCurrentPage, archiveTotalPages);
			updateArchiveUrlState({
				q,
				provinceSlugs: mergedProvinceSlugs,
				citySlugs: mergedCitySlugs,
				specialtySlugs: mergedSpecialtySlugs,
				page: archiveCurrentPage,
			});
			if (resultCount) {
				resultCount.textContent = `${data.total || 0} وکیل یافت شد`;
			}
			updateArchiveActiveFiltersDisplay();
		} catch (error) {
			if (error && error.name === 'AbortError') return;
			if (token !== archiveRequestToken) return;
			if (resultsList) {
				resultsList.innerHTML = '<p class="text-on-surface-variant">خطا در جستجو. لطفا دوباره تلاش کنید.</p>';
			}
			if (paginationWrap) paginationWrap.innerHTML = '';
			updateArchiveActiveFiltersDisplay();
		} finally {
			if (token === archiveRequestToken && resultsList) {
				resultsList.classList.remove('archive-results-loading');
				resultsList.removeAttribute('aria-busy');
			}
		}
	}

	paginationWrap?.addEventListener('click', (event) => {
		const target = event.target;
		if (!(target instanceof HTMLElement)) return;
		const btn = target.closest('.archive-page-btn');
		if (!(btn instanceof HTMLButtonElement) || btn.disabled) return;
		const page = Number(btn.dataset.page || '1');
		if (!page || page < 1 || page > archiveTotalPages) return;
		runArchiveAjaxFilter(page);
		window.scrollTo({ top: 0, behavior: 'smooth' });
	});

	document.querySelectorAll('#archive-filters-panel input[name="city_term[]"], #archive-filters-panel input[name="specialty[]"], #archive-filters-panel input[name="province_term[]"]').forEach((el) => {
		el.addEventListener('change', syncTopFiltersFromSidebar);
	});

	/** Read repeated / bracketed GET params (e.g. specialty[], specialty[0], specialty=a,b). */
	function getQuerySlugList(paramBase) {
		const p = new URLSearchParams(window.location.search);
		const out = [];
		const seen = new Set();
		function pushSlug(raw) {
			if (!raw) return;
			raw.split(',').forEach((s) => {
				const t = s.trim();
				if (t && !seen.has(t)) {
					seen.add(t);
					out.push(t);
				}
			});
		}
		for (const [k, v] of p.entries()) {
			if (!v) continue;
			const isBase = k === paramBase || k === `${paramBase}[]`;
			const isIndexed = k.startsWith(`${paramBase}[`) && k.endsWith(']');
			if (isBase || isIndexed) {
				pushSlug(v);
			}
		}
		const single = p.get(paramBase);
		if (single) {
			pushSlug(single);
		}
		return out;
	}

	/** Pick <option> for a taxonomy slug (exact, then decoded / loose match for Persian URLs). */
	function archiveSelectOptionForSlug(selectEl, slug) {
		if (!selectEl || !slug) return null;
		const candidates = [slug, decodeURIComponent(String(slug).replace(/\+/g, ' '))];
		for (const s of candidates) {
			if (!s) continue;
			const opt = selectEl.querySelector(`option[value="${CSS.escape(s)}"]`);
			if (opt) return opt;
		}
		for (const opt of selectEl.querySelectorAll('option[value]')) {
			const v = opt.value;
			if (!v) continue;
			if (v === slug || v === candidates[1]) return opt;
			try {
				if (decodeURIComponent(v) === candidates[1] || decodeURIComponent(slug) === v) return opt;
			} catch (e) { /* ignore */ }
		}
		return null;
	}

	function syncSidebarCheckboxesFromTopSelects() {
		const sv = specialtySelect?.value?.trim() || '';
		document.querySelectorAll('#archive-filters-panel input[name="specialty[]"]').forEach((box) => {
			box.checked = sv !== '' && box.value === sv;
		});
		const cv = citySelect?.value?.trim() || '';
		document.querySelectorAll('#archive-filters-panel input[name="city_term[]"]').forEach((box) => {
			box.checked = cv !== '' && box.value === cv;
		});
		const pv = provinceSelect?.value?.trim() || '';
		document.querySelectorAll('#archive-filters-panel input[name="province_term[]"]').forEach((box) => {
			box.checked = pv !== '' && box.value === pv;
		});
	}

	/**
	 * On load: do NOT call syncTopFiltersFromSidebar first — it clears selects when checkboxes
	 * are still out of sync. Prefer URL + PHP-rendered slugs, then align checkboxes.
	 */
	function initArchiveFiltersFromPageState() {
		const urlSpec = getQuerySlugList('specialty')[0] || '';
		const urlCity = getQuerySlugList('city_term')[0] || '';
		const urlProv = getQuerySlugList('province_term')[0] || '';
		const firstSpec = urlSpec || (archiveInitialSlugs && archiveInitialSlugs.specialty) || '';
		const firstCity = urlCity || (archiveInitialSlugs && archiveInitialSlugs.city_term) || '';
		const firstProv = urlProv || (archiveInitialSlugs && archiveInitialSlugs.province_term) || '';

		if (specialtySelect && firstSpec) {
			const opt = archiveSelectOptionForSlug(specialtySelect, firstSpec);
			if (opt) specialtySelect.value = opt.value;
		} else if (specialtySelect) {
			const fromBoxes = getCheckedValues('specialty[]')[0];
			if (fromBoxes) {
				const opt = archiveSelectOptionForSlug(specialtySelect, fromBoxes);
				if (opt) specialtySelect.value = opt.value;
			}
		}

		if (citySelect && firstCity) {
			const opt = archiveSelectOptionForSlug(citySelect, firstCity);
			if (opt) citySelect.value = opt.value;
		} else if (citySelect) {
			const fromBoxes = getCheckedValues('city_term[]')[0];
			if (fromBoxes) {
				const opt = archiveSelectOptionForSlug(citySelect, fromBoxes);
				if (opt) citySelect.value = opt.value;
			}
		}

		if (provinceSelect && firstProv) {
			const opt = archiveSelectOptionForSlug(provinceSelect, firstProv);
			if (opt) provinceSelect.value = opt.value;
		} else if (provinceSelect) {
			const fromBoxes = getCheckedValues('province_term[]')[0];
			if (fromBoxes) {
				const opt = archiveSelectOptionForSlug(provinceSelect, fromBoxes);
				if (opt) provinceSelect.value = opt.value;
			}
		}

		syncSidebarCheckboxesFromTopSelects();
		syncTopFiltersFromSidebar();
		updateArchiveActiveFiltersDisplay();
	}

	initArchiveFiltersFromPageState();

</script>
<style>
	.archive-search-card {
		background: #f4f4f6;
		border: 1px solid #ececf1;
		border-radius: 14px;
		padding: 12px;
		margin-bottom: 18px;
	}
	.archive-search-card .archive-search-grid {
		display: grid;
		grid-template-columns: 1fr 1fr 1fr 1.1fr auto;
		gap: 10px;
		align-items: center;
	}
	.archive-search-card .archive-field {
		position: relative;
		border-radius: 10px;
		overflow: clip;
	}
	.archive-search-card .archive-field .material-symbols-outlined {
		position: absolute;
		left: 29px;
		top: 50%;
		transform: translateY(-50%);
		font-size: 18px;
		line-height: 1;
		color: #6b7280;
		pointer-events: none;
		z-index: 2;
		width: 18px;
		height: 18px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
	}
	.archive-search-card .archive-field input,
	.archive-search-card .archive-field select {
		width: 100%;
		height: 44px;
		border: 1px solid #e4e4e8;
		border-radius: 10px;
		background: #fff;
		padding-left: 34px;
		padding-right: 16px;
		color: #111827;
		font-size: 14px;
		outline: none;
		box-sizing: border-box;
		display: block;
		text-align: right;
	}
	.archive-search-card .archive-field select {
		appearance: none;
		-webkit-appearance: none;
		-moz-appearance: none;
	}
	.archive-search-card .archive-field input:focus,
	.archive-search-card .archive-field select:focus {
		border-color: #d4b254;
		box-shadow: 0 0 0 3px rgba(212, 178, 84, 0.18);
	}
	.archive-search-card .archive-search-btn {
		height: 44px;
		min-width: 94px;
		border-radius: 10px;
		border: 0;
		background: #f2d16b;
		color: #2f2a1d;
		font-weight: 700;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
		cursor: pointer;
		transition: background-color 0.2s ease;
	}
	.archive-search-card .archive-search-btn:hover {
		background: #e9c659;
	}
	.archive-search-card .archive-result-count {
		margin-top: 10px;
		text-align: left;
		color: #6b7280;
		font-size: 14px;
	}
	@media (max-width: 900px) {
		.archive-search-card .archive-search-grid {
			grid-template-columns: 1fr;
		}
		.archive-search-card .archive-result-count {
			text-align: right;
		}
	}

	.archive-filter-list-scroll {
		max-height: 210px;
		overflow-y: auto;
		padding-right: 4px;
	}
	.archive-filter-group {
		margin-bottom: 10px;
	}
	.archive-filter-group:last-of-type {
		margin-bottom: 0;
	}
	.archive-filter-list-scroll label {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 6px;
	}
	.archive-filter-list-scroll label:last-child {
		margin-bottom: 0;
	}
	#archive-results-list {
		display: flex;
		flex-direction: column;
		gap: 1.25rem;
	}
	#archive-results-list > .archive-lawyer-card {
		margin: 0;
	}
	#archive-results-list.archive-results-loading {
		pointer-events: none;
	}
</style>
<?php get_footer(); ?>

