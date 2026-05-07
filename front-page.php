<?php
/**
 * Front page for Hovalvakil (lightweight + dynamic).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hovalvakil_to_fa_digits' ) ) {
	/**
	 * Convert ASCII digits to Persian digits.
	 *
	 * @param string $value Input.
	 *
	 * @return string
	 */
	function hovalvakil_to_fa_digits( $value ) {
		return strtr(
			(string) $value,
			[
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			]
		);
	}
}

$archive_url  = get_post_type_archive_link( 'hvl_lawyer' );
$profile_fallback_image = function_exists( 'hovalvakil_theme_lawyer_placeholder_url' )
	? hovalvakil_theme_lawyer_placeholder_url()
	: get_template_directory_uri() . '/assets/images/lawyer-placeholder.png';
$hovalvakil_lawyer_img_onerror = function_exists( 'hovalvakil_lawyer_image_onerror_placeholder_attr' )
	? hovalvakil_lawyer_image_onerror_placeholder_attr()
	: '';

$home_grade_cards = [
	[
		'slug'  => 'p1',
		'label' => __( 'وکیل پایه یک', 'hello-elementor' ),
		'icon'  => 'military_tech',
	],
	[
		'slug'  => 'p2',
		'label' => __( 'وکیل پایه دو', 'hello-elementor' ),
		'icon'  => 'workspace_premium',
	],
	[
		'slug'  => 'karamooz',
		'label' => __( 'کارآموز وکالت', 'hello-elementor' ),
		'icon'  => 'school',
	],
];
$home_grade_counts = [];
foreach ( $home_grade_cards as $g ) {
	$slug                    = $g['slug'];
	$home_grade_counts[ $slug ] = function_exists( 'hovalvakil_lawyer_grade_count_for_slug' )
		? hovalvakil_lawyer_grade_count_for_slug( $slug )
		: 0;
}

$home_city_province_pairs = function_exists( 'hovalvakil_home_city_province_pairs_for_lawyers' )
	? hovalvakil_home_city_province_pairs_for_lawyers()
	: [];
$home_cities_by_province  = [];
foreach ( $home_city_province_pairs as $row ) {
	$prov = $row['province'];
	$city = $row['city'];
	if ( ! $prov instanceof WP_Term || ! $city instanceof WP_Term ) {
		continue;
	}
	$pid = (int) $prov->term_id;
	if ( ! isset( $home_cities_by_province[ $pid ] ) ) {
		$home_cities_by_province[ $pid ] = [
			'province' => $prov,
			'cities'   => [],
		];
	}
	$home_cities_by_province[ $pid ]['cities'][] = [
		'term'          => $city,
		'lawyer_count'  => (int) ( $row['lawyer_count'] ?? 0 ),
	];
}
uasort(
	$home_cities_by_province,
	static function ( $a, $b ) {
		return strnatcasecmp( $a['province']->name, $b['province']->name );
	}
);
foreach ( $home_cities_by_province as &$prov_block ) {
	usort(
		$prov_block['cities'],
		static function ( $x, $y ) {
			$cx = (int) ( $x['lawyer_count'] ?? 0 );
			$cy = (int) ( $y['lawyer_count'] ?? 0 );
			if ( $cx !== $cy ) {
				return $cy <=> $cx;
			}
			return strnatcasecmp( $x['term']->name, $y['term']->name );
		}
	);
}
unset( $prov_block );

$home_province_rows = [];
foreach ( $home_cities_by_province as $prov_block ) {
	$province_term = $prov_block['province'];
	$cities_rows   = isset( $prov_block['cities'] ) && is_array( $prov_block['cities'] ) ? $prov_block['cities'] : [];
	$province_count = 0;
	foreach ( $cities_rows as $city_row ) {
		$province_count += (int) ( $city_row['lawyer_count'] ?? 0 );
	}
	if ( $province_count <= 0 ) {
		continue;
	}
	$home_province_rows[] = [
		'term'         => $province_term,
		'lawyer_count' => $province_count,
	];
}

if ( empty( $home_province_rows ) ) {
	$home_province_terms = get_terms(
		[
			'taxonomy'   => 'hvl_province',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]
	);
	if ( ! is_wp_error( $home_province_terms ) && ! empty( $home_province_terms ) ) {
		global $wpdb;
		$province_counts = [];
		$rows            = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_id, COUNT(DISTINCT p.ID) AS lawyer_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
				WHERE p.post_type = %s AND p.post_status = %s
				GROUP BY tt.term_id",
				'hvl_province',
				'hvl_lawyer',
				'publish'
			),
			ARRAY_A
		);
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$province_counts[ (int) $row['term_id'] ] = (int) $row['lawyer_count'];
			}
		}
		foreach ( $home_province_terms as $province_term ) {
			$count = (int) ( $province_counts[ (int) $province_term->term_id ] ?? 0 );
			if ( $count <= 0 ) {
				continue;
			}
			$home_province_rows[] = [
				'term'         => $province_term,
				'lawyer_count' => $count,
			];
		}
	}
}

// Fallback: اگر جفت استان+شهر در داده‌ها کامل نبود، شهرها را مستقیم از taxonomy وکیل‌ها نمایش بده.
$home_city_fallback_rows = [];
if ( empty( $home_cities_by_province ) ) {
	$home_city_terms = get_terms(
		[
			'taxonomy'   => 'hvl_city',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		]
	);
	if ( ! is_wp_error( $home_city_terms ) && ! empty( $home_city_terms ) ) {
		global $wpdb;
		$city_counts = [];
		$rows        = $wpdb->get_results(
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
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$city_counts[ (int) $row['term_id'] ] = (int) $row['lawyer_count'];
			}
		}
		foreach ( $home_city_terms as $city_term ) {
			$count = (int) ( $city_counts[ (int) $city_term->term_id ] ?? 0 );
			if ( $count <= 0 ) {
				continue;
			}
			$home_city_fallback_rows[] = [
				'term'         => $city_term,
				'lawyer_count' => $count,
			];
		}
		usort(
			$home_city_fallback_rows,
			static function ( $a, $b ) {
				$ca = (int) ( $a['lawyer_count'] ?? 0 );
				$cb = (int) ( $b['lawyer_count'] ?? 0 );
				if ( $ca !== $cb ) {
					return $cb <=> $ca;
				}
				return strnatcasecmp( $a['term']->name, $b['term']->name );
			}
		);
	}
}
// Fallback سطح ۲: اگر taxonomy شهر هم خالی/بی‌استفاده بود، شهر را از آدرس/لوکیشن خود وکیل‌ها استخراج کن.
if ( empty( $home_cities_by_province ) && empty( $home_city_fallback_rows ) ) {
	$lawyer_ids = get_posts(
		[
			'post_type'      => 'hvl_lawyer',
			'post_status'    => 'publish',
			'posts_per_page' => 1200,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		]
	);
	$city_counts_by_name = [];
	foreach ( $lawyer_ids as $lid ) {
		$lid = (int) $lid;
		if ( $lid <= 0 ) {
			continue;
		}
		$city_name = '';
		$city_terms = get_the_terms( $lid, 'hvl_city' );
		if ( is_array( $city_terms ) && ! empty( $city_terms ) ) {
			$city_name = trim( (string) $city_terms[0]->name );
		}
		if ( '' === $city_name ) {
			$raw_loc = function_exists( 'hovalvakil_lawyer_card_location_line' )
				? (string) hovalvakil_lawyer_card_location_line( $lid )
				: '';
			if ( '' === trim( $raw_loc ) ) {
				$raw_loc = (string) get_post_meta( $lid, 'hvl_office_address', true );
			}
			$raw_loc = trim( preg_replace( '/\s+/u', ' ', $raw_loc ) );
			if ( '' !== $raw_loc ) {
				$parts = preg_split( '/\s*[،,\-–—]\s*/u', $raw_loc );
				if ( is_array( $parts ) && ! empty( $parts ) ) {
					$city_name = trim( (string) $parts[0] );
				}
			}
		}
		if ( '' === $city_name ) {
			continue;
		}
		if ( ! isset( $city_counts_by_name[ $city_name ] ) ) {
			$city_counts_by_name[ $city_name ] = 0;
		}
		$city_counts_by_name[ $city_name ]++;
	}
	foreach ( $city_counts_by_name as $city_name => $count ) {
		$home_city_fallback_rows[] = [
			'term'           => null,
			'city_name'      => (string) $city_name,
			'lawyer_count'   => (int) $count,
			'use_query_fallback' => true,
		];
	}
	usort(
		$home_city_fallback_rows,
		static function ( $a, $b ) {
			$ca = (int) ( $a['lawyer_count'] ?? 0 );
			$cb = (int) ( $b['lawyer_count'] ?? 0 );
			if ( $ca !== $cb ) {
				return $cb <=> $ca;
			}
			return strnatcasecmp( (string) ( $a['city_name'] ?? '' ), (string) ( $b['city_name'] ?? '' ) );
		}
	);
}
$initial_query = new WP_Query(
	[
		'post_type'              => 'hvl_lawyer',
		'post_status'            => 'publish',
		'posts_per_page'         => 16,
		'no_found_rows'          => false,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
	]
);
$initial_total_pages = max( 1, (int) $initial_query->max_num_pages );
get_header();
?>
	<header class="hero hero-pattern">
		<div class="container hero-container">
			<h1 class="hero-title">جستجوی وکیل و مشاوره حقوقی آنلاین</h1>
			<p class="hero-subtitle">وکیل، تخصص یا شهر مورد نظر خود را جستجو کنید</p>

			<div class="search-container">
				<div class="search-field">
					<div id="home-search-trigger" class="search-icon-right search-icon-variant">
						<span class="material-symbols-outlined">search</span>
					</div>
					<input id="home-search-input" class="search-input" placeholder="نام وکیل، تخصص، خدمت..." type="text" />
					<div id="home-live-results" class="home-live-results" hidden></div>
				</div>
			</div>
		</div>
	</header>

	<main id="content" class="main-content">
		<div class="container">
			<div class="filter-bar">
				<div class="filter-group" id="home-grade-filters">
					<span class="filter-label"><?php esc_html_e( 'فیلتر مقطع:', 'hello-elementor' ); ?></span>
					<button type="button" class="filter-btn active" data-grade=""><?php esc_html_e( 'همه', 'hello-elementor' ); ?></button>
					<?php foreach ( $home_grade_cards as $grade_card ) : ?>
						<button type="button" class="filter-btn" data-grade="<?php echo esc_attr( $grade_card['slug'] ); ?>">
							<?php echo esc_html( $grade_card['label'] ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="lawyer-grid" id="home-lawyer-grid" aria-live="polite">
				<?php if ( $initial_query->have_posts() ) : ?>
					<?php while ( $initial_query->have_posts() ) : ?>
						<?php
						$initial_query->the_post();
						$post_id      = get_the_ID();
						$img          = function_exists( 'hovalvakil_lawyer_profile_image_url' )
							? hovalvakil_lawyer_profile_image_url( $post_id, 'medium' )
							: (string) get_the_post_thumbnail_url( $post_id, 'medium' );
						$spec_names   = function_exists( 'hovalvakil_lawyer_card_specialty_labels' )
							? hovalvakil_lawyer_card_specialty_labels( $post_id )
							: [];
						$location_line = function_exists( 'hovalvakil_lawyer_card_location_line' )
							? hovalvakil_lawyer_card_location_line( $post_id )
							: '';
						$license_line  = function_exists( 'hovalvakil_lawyer_card_license_line' )
							? hovalvakil_lawyer_card_license_line( $post_id )
							: '';
						?>
						<a href="<?php the_permalink(); ?>" class="lawyer-card lawyer-card--link ghost-border editorial-shadow">
							<div class="lawyer-card-image-wrapper">
								<img class="lawyer-card-image" src="<?php echo esc_url( $img ? $img : $profile_fallback_image ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>"<?php echo $hovalvakil_lawyer_img_onerror; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
							</div>
							<div class="lawyer-card-content">
								<h3 class="lawyer-name"><?php the_title(); ?></h3>
								<?php if ( ! empty( $spec_names ) ) : ?>
									<div class="lawyer-card-specialties">
										<?php foreach ( $spec_names as $spec_label ) : ?>
											<span class="lawyer-card-specialty-tag"><?php echo esc_html( $spec_label ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
								<?php if ( '' !== $location_line ) : ?>
									<p class="text-secondary font-bold text-xs lawyer-card-city-line"><?php echo esc_html( $location_line ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== $license_line ) : ?>
									<p class="text-on-surface-variant text-xs lawyer-card-license-line"><?php echo esc_html( $license_line ); ?></p>
								<?php endif; ?>
								<span class="btn-primary-full">مشاهده پروفایل</span>
							</div>
						</a>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<p class="text-on-surface-variant">هنوز وکیلی ثبت نشده است.</p>
				<?php endif; ?>
			</div>

			<div class="load-more-wrapper">
				<button id="home-load-more-btn" class="btn-load-more">مشاهده وکلای بیشتر</button>
			</div>
		</div>
	</main>

	<section class="specialties py-24 bg-surface px-6">
		<div class="container text-right">
			<div class="section-header mb-12">
				<h2 class="text-3xl font-bold text-primary mb-2"><?php esc_html_e( 'مقطع وکالت', 'hello-elementor' ); ?></h2>
				<a class="section-header-link text-primary font-bold text-sm" href="<?php echo esc_url( $archive_url ); ?>">
					<?php esc_html_e( 'مشاهده همه', 'hello-elementor' ); ?>
					<span class="material-symbols-outlined text-lg">chevron_left</span>
				</a>
			</div>
			<div class="specialty-grid">
				<?php foreach ( $home_grade_cards as $grade_card ) : ?>
					<?php
					$grade_slug  = $grade_card['slug'];
					$grade_icon  = $grade_card['icon'];
					$grade_count = (int) ( $home_grade_counts[ $grade_slug ] ?? 0 );
					$grade_link  = add_query_arg( 'grade', $grade_slug, $archive_url );
					?>
					<a class="category-card ghost-border" href="<?php echo esc_url( $grade_link ); ?>">
						<div class="category-icon-wrapper">
							<span class="material-symbols-outlined text-primary text-3xl"><?php echo esc_html( $grade_icon ); ?></span>
						</div>
						<h3 class="text-xl font-bold text-primary mb-2"><?php echo esc_html( $grade_card['label'] ); ?></h3>
						<p class="text-sm text-on-surface-variant"><?php echo esc_html( hovalvakil_to_fa_digits( number_format_i18n( $grade_count ) ) ); ?> <?php esc_html_e( 'وکیل', 'hello-elementor' ); ?></p>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="cities">
		<div class="container">
			<div class="section-header city-section-header">
				<h2 class="text-2xl font-bold text-primary"><?php esc_html_e( 'جستجو بر اساس استان', 'hello-elementor' ); ?></h2>
				<a class="section-header-link text-primary font-bold text-sm" href="<?php echo esc_url( $archive_url ); ?>">
					<?php esc_html_e( 'مشاهده همه وکلا', 'hello-elementor' ); ?>
					<span class="material-symbols-outlined text-lg">chevron_left</span>
				</a>
			</div>
			<?php if ( ! empty( $home_province_rows ) ) : ?>
				<div class="city-grid">
					<?php foreach ( $home_province_rows as $province_row ) : ?>
						<?php
						$province_term = $province_row['term'];
						$province_lawyers = (int) ( $province_row['lawyer_count'] ?? 0 );
						$province_href = add_query_arg(
							[
								'province_term[]' => $province_term->slug,
							],
							$archive_url
						);
						?>
						<a class="city-card" href="<?php echo esc_url( $province_href ); ?>">
							<div class="city-card-header">
								<div class="city-link-header">
									<span class="material-symbols-outlined text-primary">map</span>
									<span class="font-bold text-primary"><?php echo esc_html( $province_term->name ); ?></span>
								</div>
								<span class="material-symbols-outlined text-outline-variant text-lg">chevron_left</span>
							</div>
							<p class="text-xs text-on-surface-variant">
								<?php echo esc_html( hovalvakil_to_fa_digits( number_format_i18n( $province_lawyers ) ) ); ?> <?php esc_html_e( 'وکیل فعال', 'hello-elementor' ); ?>
							</p>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="text-on-surface-variant"><?php esc_html_e( 'هنوز دادهٔ استان برای وکلا ثبت نشده است.', 'hello-elementor' ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<script>
		const apiBase = <?php echo wp_json_encode( esc_url_raw( rest_url( 'hovalvakil/v1/lawyers' ) ) ); ?>;
		const archiveUrl = <?php echo wp_json_encode( esc_url_raw( $archive_url ) ); ?>;
		const homeGradeSlugs = <?php echo wp_json_encode( array_column( $home_grade_cards, 'slug' ), JSON_UNESCAPED_UNICODE ); ?>;
		const fallbackImage = <?php echo wp_json_encode( esc_url_raw( $profile_fallback_image ) ); ?>;
		const initialTotalPages = <?php echo (int) $initial_total_pages; ?>;

		const gridEl = document.getElementById('home-lawyer-grid');
		const queryInput = document.getElementById('home-search-input');
		const triggerBtn = document.getElementById('home-search-trigger');
		const loadMoreBtn = document.getElementById('home-load-more-btn');
		const gradeFilterWrap = document.getElementById('home-grade-filters');
		const liveResultsEl = document.getElementById('home-live-results');

		const GRID_PER_PAGE = 16;
		let currentPage = 1;
		let totalPages = initialTotalPages;
		let selectedGrade = '';
		let liveTimer = null;
		let liveAbortController = null;
		const liveSearchCache = new Map();
		let gridTimer = null;
		let gridAbortController = null;
		const gridCache = new Map();
		let gridRequestToken = 0;

		if (loadMoreBtn) {
			loadMoreBtn.style.display = totalPages > 1 ? 'inline-flex' : 'none';
		}

		function escapeHtml(str) {
			return (str || '')
				.replaceAll('&', '&amp;')
				.replaceAll('<', '&lt;')
				.replaceAll('>', '&gt;')
				.replaceAll('"', '&quot;')
				.replaceAll("'", '&#039;');
		}

		function hvlLawyerImageOnerrorAttr() {
			return ` onerror="this.onerror=null;this.src=${JSON.stringify(fallbackImage)};this.classList.add('hvl-img-fallback');"`;
		}

		function specialtiesMarkup(item) {
			const names = Array.isArray(item.specialties) && item.specialties.length
				? item.specialties
				: (item.specialty ? [item.specialty] : []);
			if (!names.length) {
				return '';
			}
			const tags = names.map((n) => `<span class="lawyer-card-specialty-tag">${escapeHtml(n)}</span>`).join('');
			return `<div class="lawyer-card-specialties">${tags}</div>`;
		}

		function cardLocationText(item) {
			const loc = (item.location_line || item.city || '').toString().trim();
			return loc;
		}

		function skeletonCardHtml() {
			return (
				'<div class="lawyer-card-skeleton ghost-border" aria-hidden="true">' +
				'<div class="lawyer-card-skeleton-img"></div>' +
				'<div class="lawyer-card-skeleton-body">' +
				'<div class="lawyer-card-skeleton-line lawyer-card-skeleton-line--title"></div>' +
				'<div class="lawyer-card-skeleton-tags"><span></span><span></span><span></span></div>' +
				'<div class="lawyer-card-skeleton-line lawyer-card-skeleton-line--meta"></div>' +
				'<div class="lawyer-card-skeleton-btn"></div>' +
				'</div></div>'
			);
		}

		function showGridSkeleton(count) {
			let html = '';
			for (let i = 0; i < count; i++) html += skeletonCardHtml();
			gridEl.innerHTML = html;
		}

		function renderCards(items, append = false) {
			if (!append) gridEl.innerHTML = '';
			if (!items.length && !append) {
				gridEl.innerHTML = '<p class="text-on-surface-variant">موردی یافت نشد.</p>';
				return;
			}
			const html = items.map((item) => {
				const loc = cardLocationText(item);
				const lic = (item.card_license_line || '').toString().trim();
				const locHtml = loc
					? `<p class="text-secondary font-bold text-xs lawyer-card-city-line">${escapeHtml(loc)}</p>`
					: '';
				const licHtml = lic
					? `<p class="text-on-surface-variant text-xs lawyer-card-license-line">${escapeHtml(lic)}</p>`
					: '';
				return `
				<a href="${escapeHtml(item.permalink || '#')}" class="lawyer-card lawyer-card--link ghost-border editorial-shadow">
					<div class="lawyer-card-image-wrapper">
						<img class="lawyer-card-image" src="${escapeHtml(item.image || fallbackImage)}" alt="${escapeHtml(item.name || '')}"${hvlLawyerImageOnerrorAttr()} />
					</div>
					<div class="lawyer-card-content">
						<h3 class="lawyer-name">${escapeHtml(item.name || '')}</h3>
						${specialtiesMarkup(item)}
						${locHtml}
						${licHtml}
						<span class="btn-primary-full">مشاهده پروفایل</span>
					</div>
				</a>`;
			}).join('');
			gridEl.insertAdjacentHTML('beforeend', html);
		}

		async function fetchLawyers({ page = 1, append = false, skeleton = true } = {}) {
			const qTrim = queryInput?.value?.trim() || '';
			// Match archive REST: sending short q triggers strong-search branch → often zero items with tax filters.
			const q = qTrim.length >= 2 ? qTrim : '';
			const params = new URLSearchParams({
				page: String(page),
				per_page: String(GRID_PER_PAGE),
			});
			if (selectedGrade && homeGradeSlugs.includes(selectedGrade)) params.set('grade', selectedGrade);
			if (q) params.set('q', q);
			const cacheKey = params.toString();

			if (gridCache.has(cacheKey)) {
				const cached = gridCache.get(cacheKey);
				totalPages = Number(cached.totalPages || 1);
				currentPage = Number(cached.page || 1);
				if (!append) {
					gridEl.classList.remove('is-loading');
					gridEl.removeAttribute('aria-busy');
				}
				renderCards(Array.isArray(cached.items) ? cached.items : [], append);
				loadMoreBtn.style.display = currentPage < totalPages ? 'inline-flex' : 'none';
				return;
			}

			if (gridAbortController) {
				gridAbortController.abort();
			}
			gridAbortController = new AbortController();

			const token = ++gridRequestToken;
			if (!append) {
				gridEl.setAttribute('aria-busy', 'true');
				if (skeleton) {
					gridEl.classList.add('is-loading');
					showGridSkeleton(GRID_PER_PAGE);
				}
			}

			loadMoreBtn.disabled = true;
			try {
				const res = await fetch(`${apiBase}?${params.toString()}`, {
					credentials: 'same-origin',
					signal: gridAbortController.signal,
				});
				if (!res.ok) throw new Error('request_failed');
				const data = await res.json();
				if (token !== gridRequestToken) return;
				gridCache.set(cacheKey, data);
				if (gridCache.size > 50) {
					const oldestKey = gridCache.keys().next().value;
					gridCache.delete(oldestKey);
				}
				totalPages = Number(data.totalPages || 1);
				currentPage = Number(data.page || 1);
				renderCards(Array.isArray(data.items) ? data.items : [], append);
				loadMoreBtn.style.display = currentPage < totalPages ? 'inline-flex' : 'none';
			} catch (e) {
				if (e && e.name === 'AbortError') return;
				if (token !== gridRequestToken) return;
				if (!append) {
					gridEl.innerHTML = '<p class="text-on-surface-variant">خطا در دریافت اطلاعات. دوباره تلاش کنید.</p>';
				}
				loadMoreBtn.style.display = 'none';
			} finally {
				if (token === gridRequestToken) {
					if (!append) {
						gridEl.classList.remove('is-loading');
						gridEl.removeAttribute('aria-busy');
					}
					loadMoreBtn.disabled = false;
				}
			}
		}

		function goToArchive() {
			const qTrim = queryInput?.value?.trim() || '';
			const params = new URLSearchParams();
			if (qTrim.length >= 2) params.set('q', qTrim);
			window.location.href = params.toString() ? `${archiveUrl}?${params.toString()}` : archiveUrl;
		}

		function archiveUrlWithFilters() {
			const qTrim = queryInput?.value?.trim() || '';
			const params = new URLSearchParams();
			if (qTrim.length >= 2) params.set('q', qTrim);
			return params.toString() ? `${archiveUrl}?${params.toString()}` : archiveUrl;
		}

		function hideLiveResults() {
			if (!liveResultsEl) return;
			liveResultsEl.hidden = true;
			liveResultsEl.innerHTML = '';
		}

		function renderLiveLoading() {
			if (!liveResultsEl) return;
			liveResultsEl.innerHTML = '<div class="home-live-empty">در حال جستجو...</div>';
			liveResultsEl.hidden = false;
		}

		function normalizeFaText(value) {
			return (value || '')
				.toString()
				.toLowerCase()
				.replaceAll('ي', 'ی')
				.replaceAll('ك', 'ک')
				.replaceAll('ة', 'ه')
				.replace(/\s+/g, ' ')
				.trim();
		}

		function scoreLiveItem(item, query) {
			const q = normalizeFaText(query);
			if (!q) return 0;
			const name = normalizeFaText(item?.name || '');
			const specialty = normalizeFaText(
				(Array.isArray(item?.specialties) && item.specialties.length ? item.specialties.join(' ') : '') || item?.specialty || ''
			);
			const city = normalizeFaText(
				(item?.location_line || item?.city || '').toString()
			);
			let score = 0;
			if (name === q) score += 1000;
			else if (name.startsWith(q)) score += 700;
			else if (name.includes(q)) score += 450;
			if (specialty === q) score += 500;
			else if (specialty.includes(q)) score += 280;
			if (city === q) score += 420;
			else if (city.includes(q)) score += 240;
			return score;
		}

		function renderLiveResults(items, total) {
			if (!liveResultsEl) return;
			if (!items.length) {
				liveResultsEl.innerHTML = '<div class="home-live-empty">موردی پیدا نشد.</div>';
				liveResultsEl.hidden = false;
				return;
			}

			const rows = items.map((item) => {
				const spec0 = (Array.isArray(item.specialties) && item.specialties.length
					? item.specialties[0]
					: '') || (item.specialty || '');
				const loc = cardLocationText(item);
				const lic = (item.card_license_line || '').toString().trim();
				const bits = [ spec0, loc, lic ].filter((x) => String(x).trim());
				const metaLine = bits.join(' · ');
				const metaHtml = metaLine
					? `<div class="home-live-item-meta">${escapeHtml(metaLine)}</div>`
					: '';
				return `
				<a class="home-live-item" href="${escapeHtml(item.permalink || '#')}">
					<img class="home-live-item-image" src="${escapeHtml(item.image || fallbackImage)}" alt="${escapeHtml(item.name || '')}"${hvlLawyerImageOnerrorAttr()} />
					<div class="home-live-item-content">
						<div class="home-live-item-title">${escapeHtml(item.name || '')}</div>
						${metaHtml}
					</div>
				</a>`;
			}).join('');

			const footer = `
				<a class="home-live-all" href="${escapeHtml(archiveUrlWithFilters())}">
					مشاهده همه نتایج (${escapeHtml(String(total || items.length))})
				</a>
			`;

			liveResultsEl.innerHTML = rows + footer;
			liveResultsEl.hidden = false;
		}

		async function fetchLiveResults() {
			const q = queryInput?.value?.trim() || '';
			if (!q || q.length < 2) {
				hideLiveResults();
				return;
			}

			const params = new URLSearchParams({ q, per_page: '10', page: '1' });
			if (selectedGrade && homeGradeSlugs.includes(selectedGrade)) params.set('grade', selectedGrade);
			const cacheKey = params.toString();

			if (liveSearchCache.has(cacheKey)) {
				const cached = liveSearchCache.get(cacheKey);
				renderLiveResults(cached.items, cached.total);
				return;
			}

			if (liveAbortController) {
				liveAbortController.abort();
			}
			liveAbortController = new AbortController();
			renderLiveLoading();

			try {
				const res = await fetch(`${apiBase}?${params.toString()}`, {
					credentials: 'same-origin',
					signal: liveAbortController.signal,
				});
				if (!res.ok) throw new Error('live_request_failed');
				const data = await res.json();
				const rawItems = Array.isArray(data.items) ? data.items : [];
				const scoredItems = rawItems
					.map((item) => ({ item, score: scoreLiveItem(item, q) }))
					.sort((a, b) => b.score - a.score)
					.map((row) => row.item)
					.slice(0, 10);
				const total = Number(data.total || scoredItems.length);
				liveSearchCache.set(cacheKey, { items: scoredItems, total });
				if (liveSearchCache.size > 30) {
					const oldestKey = liveSearchCache.keys().next().value;
					liveSearchCache.delete(oldestKey);
				}
				renderLiveResults(scoredItems, total);
			} catch (e) {
				if (e && e.name === 'AbortError') return;
				hideLiveResults();
			}
		}

		triggerBtn?.addEventListener('click', goToArchive);
		queryInput?.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault();
				hideLiveResults();
				goToArchive();
			}
		});
		queryInput?.addEventListener('input', () => {
			if (liveTimer) clearTimeout(liveTimer);
			liveTimer = setTimeout(fetchLiveResults, 70);
			if (gridTimer) clearTimeout(gridTimer);
			gridTimer = setTimeout(() => {
				fetchLawyers({ page: 1, append: false, skeleton: false });
			}, 90);
		});
		queryInput?.addEventListener('focus', () => {
			if ((queryInput?.value?.trim() || '').length >= 2) {
				fetchLiveResults();
			}
		});
		loadMoreBtn?.addEventListener('click', () => {
			const nextPage = currentPage + 1;
			if (nextPage > totalPages) return;
			fetchLawyers({ page: nextPage, append: true });
		});

		gradeFilterWrap?.addEventListener('click', (event) => {
			const target = event.target;
			if (!(target instanceof HTMLElement)) return;
			const btn = target.closest('.filter-btn');
			if (!(btn instanceof HTMLElement)) return;

			const next = (btn.dataset.grade || '').trim();
			selectedGrade = homeGradeSlugs.includes(next) ? next : '';
			gradeFilterWrap.querySelectorAll('.filter-btn').forEach((el) => {
				el.classList.remove('active');
			});
			btn.classList.add('active');
			fetchLawyers({ page: 1, skeleton: true });
			fetchLiveResults();
		});

		document.addEventListener('click', (event) => {
			const target = event.target;
			if (!(target instanceof Node)) return;
			if (
				liveResultsEl &&
				!liveResultsEl.contains(target) &&
				target !== queryInput
			) {
				hideLiveResults();
			}
		});

	</script>
	<style>
		.home-live-results {
			position: absolute;
			top: calc(100% + 8px);
			right: 0;
			left: 0;
			background: #fff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
			z-index: 40;
			max-height: 420px;
			overflow-y: auto;
		}
		.home-live-item {
			display: flex;
			gap: 12px;
			align-items: center;
			padding: 10px 12px;
			border-bottom: 1px solid #f1f5f9;
			text-decoration: none;
			color: inherit;
		}
		.home-live-item:hover {
			background: #f8fafc;
		}
		.home-live-item-image {
			width: 48px;
			height: 48px;
			object-fit: cover;
			border-radius: 10px;
			flex-shrink: 0;
		}
		.home-live-item-title {
			font-size: 14px;
			font-weight: 700;
			color: #0f172a;
			margin-bottom: 2px;
		}
		.home-live-item-meta {
			display: flex;
			gap: 6px;
			flex-wrap: wrap;
			font-size: 12px;
			color: #64748b;
		}
		.home-live-all {
			display: block;
			padding: 12px;
			text-align: center;
			font-size: 13px;
			font-weight: 700;
			color: #0f3d75;
			text-decoration: none;
			background: #f8fafc;
			border-top: 1px solid #e2e8f0;
			border-radius: 0 0 14px 14px;
		}
		.home-live-all:hover {
			background: #f1f5f9;
		}
		.home-live-empty {
			padding: 14px;
			font-size: 13px;
			color: #64748b;
			text-align: center;
		}
		@media (max-width: 767px) {
			.specialties > .container.text-right {
				padding-left: 0 !important;
				padding-right: 0 !important;
			}
		}
		.home-city-by-province {
			display: flex;
			flex-direction: column;
			gap: 2rem;
		}
		.home-province-block {
			text-align: right;
		}
		.home-province-title {
			font-size: 1.125rem;
			font-weight: 700;
			color: #0f3d75;
			margin: 0 0 0.75rem;
			padding-bottom: 0.35rem;
			border-bottom: 1px solid #e2e8f0;
		}
		.home-province-city-grid {
			margin-top: 0.25rem;
		}
	</style>
<?php get_footer(); ?>

