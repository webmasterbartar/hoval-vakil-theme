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
	: get_template_directory_uri() . '/assets/images/lawyer-placeholder.svg';
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

/** فهرست استان‌ها با تعداد وکیل برای سکشن صفحهٔ اصلی (بدون زیرمجموعهٔ شهر). */
$home_province_rows = [];
global $wpdb;
$home_prov_count_sql = $wpdb->get_results(
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
if ( is_array( $home_prov_count_sql ) ) {
	foreach ( $home_prov_count_sql as $prow ) {
		$tid = (int) ( $prow['term_id'] ?? 0 );
		$cnt = (int) ( $prow['lawyer_count'] ?? 0 );
		if ( $tid <= 0 || $cnt <= 0 ) {
			continue;
		}
		$pt = get_term( $tid, 'hvl_province' );
		if ( ! $pt instanceof WP_Term || is_wp_error( $pt ) ) {
			continue;
		}
		$home_province_rows[] = [
			'term'          => $pt,
			'lawyer_count'  => $cnt,
		];
	}
	usort(
		$home_province_rows,
		static function ( $a, $b ) {
			$ca = (int) ( $a['lawyer_count'] ?? 0 );
			$cb = (int) ( $b['lawyer_count'] ?? 0 );
			if ( $ca !== $cb ) {
				return $cb <=> $ca;
			}
			$ta = $a['term'] ?? null;
			$tb = $b['term'] ?? null;
			$na = $ta instanceof WP_Term ? $ta->name : '';
			$nb = $tb instanceof WP_Term ? $tb->name : '';
			return strnatcasecmp( (string) $na, (string) $nb );
		}
	);
}
$initial_query = function_exists( 'hovalvakil_home_grid_query' )
	? hovalvakil_home_grid_query(
		[
			'paged'            => 1,
			'posts_per_page'   => 20,
			'grade_slugs'      => [],
		]
	)
	: new WP_Query(
		[
			'post_type'              => 'hvl_lawyer',
			'post_status'            => 'publish',
			'posts_per_page'         => 20,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		]
	);
$home_province_slug = function_exists( 'hovalvakil_home_grid_primary_province_slug' )
	? hovalvakil_home_grid_primary_province_slug()
	: 'tehran';
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
					<input id="home-search-input" class="search-input" placeholder="نام وکیل، شماره پروانه، تخصص، شهر..." type="text" autocomplete="off" />
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
					$grade_link  = function_exists( 'hovalvakil_lawyer_archive_grade_url' )
						? hovalvakil_lawyer_archive_grade_url( $grade_slug )
						: add_query_arg( 'grade', $grade_slug, $archive_url );
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
					<?php foreach ( $home_province_rows as $prov_row ) : ?>
						<?php
						$province_term = $prov_row['term'];
						$prov_lawyers  = (int) ( $prov_row['lawyer_count'] ?? 0 );
						if ( ! $province_term instanceof WP_Term ) {
							continue;
						}
						$prov_href = function_exists( 'hovalvakil_lawyer_archive_province_url' )
							? hovalvakil_lawyer_archive_province_url( (string) $province_term->slug )
							: add_query_arg( [ 'province_term[]' => $province_term->slug ], $archive_url );
						?>
						<a class="city-card" href="<?php echo esc_url( $prov_href ); ?>">
							<div class="city-card-header">
								<div class="city-link-header">
									<span class="material-symbols-outlined text-primary">map</span>
									<span class="font-bold text-primary"><?php echo esc_html( $province_term->name ); ?></span>
								</div>
								<span class="material-symbols-outlined text-outline-variant text-lg">chevron_left</span>
							</div>
							<p class="text-xs text-on-surface-variant">
								<?php echo esc_html( hovalvakil_to_fa_digits( number_format_i18n( $prov_lawyers ) ) ); ?> <?php esc_html_e( 'وکیل فعال', 'hello-elementor' ); ?>
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
		const homeGridProvince = <?php echo wp_json_encode( $home_province_slug, JSON_UNESCAPED_UNICODE ); ?>;
		const archiveUrl = <?php echo wp_json_encode( esc_url_raw( $archive_url ) ); ?>;
		const archiveGradeUrls = <?php
		$grade_url_map = [];
		foreach ( $home_grade_cards as $gc ) {
			$gs = (string) ( $gc['slug'] ?? '' );
			if ( '' === $gs ) {
				continue;
			}
			$grade_url_map[ $gs ] = function_exists( 'hovalvakil_lawyer_archive_grade_url' )
				? hovalvakil_lawyer_archive_grade_url( $gs )
				: add_query_arg( 'grade', $gs, $archive_url );
		}
		echo wp_json_encode( $grade_url_map, JSON_UNESCAPED_UNICODE );
		?>;
		const homeGradeSlugs = <?php echo wp_json_encode( array_column( $home_grade_cards, 'slug' ), JSON_UNESCAPED_UNICODE ); ?>;
		const fallbackImage = <?php echo wp_json_encode( esc_url_raw( $profile_fallback_image ) ); ?>;
		const initialTotalPages = <?php echo (int) $initial_total_pages; ?>;

		const gridEl = document.getElementById('home-lawyer-grid');
		const queryInput = document.getElementById('home-search-input');
		const triggerBtn = document.getElementById('home-search-trigger');
		const loadMoreBtn = document.getElementById('home-load-more-btn');
		const gradeFilterWrap = document.getElementById('home-grade-filters');

		const GRID_PER_PAGE = 20;
		let currentPage = 1;
		let totalPages = initialTotalPages;
		let selectedGrade = '';
		let searchDebounceTimer = null;
		let searchAbortController = null;
		let searchRequestToken = 0;
		const searchCache = new Map();
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

		function digitsKey(value) {
			return (value || '')
				.toString()
				.replace(/[۰-۹٠-٩]/g, (ch) => {
					const map = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9', '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' };
					return map[ch] ?? ch;
				})
				.replace(/\D/g, '');
		}

		function searchQueryUsable(raw) {
			const q = (raw || '').trim();
			if (!q) return false;
			const d = digitsKey(q);
			const rest = q.replace(/[0-9۰-۹٠-٩\s/\-.,،‌_]/g, '');
			// License number: from 2 digits (102, 102142, …).
			if (rest.length === 0 && d.length > 0) {
				return d.length >= 2;
			}
			return q.length >= 2 || d.length >= 2;
		}

		function isDigitPrimaryQuery(raw) {
			const q = (raw || '').trim();
			if (!q) return false;
			const d = digitsKey(q);
			if (d.length < 2) return false;
			const rest = q.replace(/[0-9۰-۹٠-٩\s/\-.,،‌_]/g, '');
			return rest.length === 0;
		}

		function searchDebounceMs(raw) {
			return isDigitPrimaryQuery(raw) ? 280 : 160;
		}

		function appendHomeGridParams(params, opts) {
			params.set('home', '1');
			const searching = !!(opts && opts.searching);
			if (!searching && homeGridProvince) {
				params.set('province_term', homeGridProvince);
			}
		}

		function filterItemsForQuery(items, qTrim) {
			const list = Array.isArray(items) ? items : [];
			if (!isDigitPrimaryQuery(qTrim)) {
				return list;
			}
			const needle = digitsKey(qTrim);
			if (needle.length < 2) {
				return [];
			}
			return list.filter((item) => {
				const lic = digitsKey(
					[item?.license_no_raw, item?.license_no, item?.card_license_line].filter(Boolean).join(' ')
				);
				return lic.startsWith(needle);
			});
		}

		async function runHomeSearch() {
			const qTrim = queryInput?.value?.trim() || '';
			if (!searchQueryUsable(qTrim)) {
				await fetchLawyers({ page: 1, append: false, skeleton: true });
				return;
			}

			const params = new URLSearchParams({
				q: qTrim,
				per_page: String(GRID_PER_PAGE),
				page: '1',
			});
			appendHomeGridParams(params, { searching: true });
			if (selectedGrade && homeGradeSlugs.includes(selectedGrade)) {
				params.set('grade', selectedGrade);
			}
			const cacheKey = params.toString();

			if (searchCache.has(cacheKey)) {
				const cached = searchCache.get(cacheKey);
				const items = filterItemsForQuery(cached.items, qTrim);
				renderCards(items, false);
				totalPages = Number(cached.totalPages || 1);
				currentPage = 1;
				loadMoreBtn.style.display = currentPage < totalPages ? 'inline-flex' : 'none';
				return;
			}

			const token = ++searchRequestToken;
			if (searchAbortController) {
				searchAbortController.abort();
			}
			searchAbortController = new AbortController();

			gridEl.setAttribute('aria-busy', 'true');
			gridEl.classList.add('is-loading');
			showGridSkeleton(GRID_PER_PAGE);
			loadMoreBtn.disabled = true;

			try {
				const res = await fetch(`${apiBase}?${params.toString()}`, {
					credentials: 'same-origin',
					signal: searchAbortController.signal,
				});
				if (!res.ok) {
					throw new Error('search_request_failed');
				}
				const data = await res.json();
				if (token !== searchRequestToken) {
					return;
				}
				searchCache.set(cacheKey, data);
				if (searchCache.size > 40) {
					searchCache.delete(searchCache.keys().next().value);
				}
				gridCache.set(cacheKey, data);
				totalPages = Number(data.totalPages || 1);
				currentPage = Number(data.page || 1);
				let items = filterItemsForQuery(data.items, qTrim);
				if (isDigitPrimaryQuery(qTrim) && !items.length) {
					data.total = 0;
					data.totalPages = 0;
				}
				renderCards(items, false);
				loadMoreBtn.style.display = currentPage < totalPages ? 'inline-flex' : 'none';
			} catch (e) {
				if (e && e.name === 'AbortError') {
					return;
				}
				if (token !== searchRequestToken) {
					return;
				}
				gridEl.innerHTML = '<p class="text-on-surface-variant">خطا در جستجو. دوباره تلاش کنید.</p>';
				loadMoreBtn.style.display = 'none';
			} finally {
				if (token === searchRequestToken) {
					gridEl.classList.remove('is-loading');
					gridEl.removeAttribute('aria-busy');
					loadMoreBtn.disabled = false;
				}
			}
		}

		function scheduleHomeSearch() {
			if (searchDebounceTimer) {
				clearTimeout(searchDebounceTimer);
			}
			const qTrim = queryInput?.value?.trim() || '';
			const delay = searchDebounceMs(qTrim);
			searchDebounceTimer = setTimeout(() => {
				searchDebounceTimer = null;
				runHomeSearch();
			}, delay);
		}

		async function fetchLawyers({ page = 1, append = false, skeleton = true } = {}) {
			const qTrim = queryInput?.value?.trim() || '';
			const q = searchQueryUsable(qTrim) ? qTrim : '';
			const params = new URLSearchParams({
				page: String(page),
				per_page: String(GRID_PER_PAGE),
			});
			appendHomeGridParams(params, { searching: !!q });
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
			if ( !qTrim && selectedGrade && archiveGradeUrls[selectedGrade] ) {
				window.location.href = archiveGradeUrls[selectedGrade];
				return;
			}
			const params = new URLSearchParams();
			if (searchQueryUsable(qTrim)) params.set('q', qTrim);
			if (selectedGrade && homeGradeSlugs.includes(selectedGrade)) params.set('grade', selectedGrade);
			window.location.href = params.toString() ? `${archiveUrl}?${params.toString()}` : archiveUrl;
		}

		triggerBtn?.addEventListener('click', () => {
			const qTrim = queryInput?.value?.trim() || '';
			if (searchQueryUsable(qTrim)) {
				runHomeSearch();
				return;
			}
			goToArchive();
		});
		queryInput?.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault();
				const qTrim = queryInput?.value?.trim() || '';
				if (searchQueryUsable(qTrim)) {
					runHomeSearch();
					return;
				}
				goToArchive();
			}
		});
		queryInput?.addEventListener('input', () => {
			const qTrim = queryInput?.value?.trim() || '';
			if (!searchQueryUsable(qTrim)) {
				if (searchDebounceTimer) {
					clearTimeout(searchDebounceTimer);
					searchDebounceTimer = null;
				}
				if (searchAbortController) {
					searchAbortController.abort();
					searchAbortController = null;
				}
				fetchLawyers({ page: 1, append: false, skeleton: true });
				return;
			}
			scheduleHomeSearch();
		});
		queryInput?.addEventListener('focus', () => {
			const qTrim = queryInput?.value?.trim() || '';
			if (searchQueryUsable(qTrim)) {
				scheduleHomeSearch();
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
			scheduleHomeSearch();
		});

	</script>
	<style>
		@media (max-width: 767px) {
			.specialties > .container.text-right {
				padding-left: 0 !important;
				padding-right: 0 !important;
			}
		}
	</style>
<?php get_footer(); ?>

