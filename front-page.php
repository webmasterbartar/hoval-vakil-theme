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
$profile_fallback_image = 'https://via.placeholder.com/600x600.png?text=%D9%88%DA%A9%DB%8C%D9%84';
$home_specialties = get_terms(
	[
		'taxonomy'   => 'hvl_specialty',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]
);
$home_cities = get_terms(
	[
		'taxonomy'   => 'hvl_city',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]
);
// Lawyer counts per city/specialty (cached; busted via hvl_lawyer_cache_ver).
$home_count_maps           = hovalvakil_lawyer_get_home_lawyer_count_maps();
$home_city_lawyer_counts   = $home_count_maps['city'];
$home_specialty_lawyer_counts = $home_count_maps['specialty'];
$initial_query = new WP_Query(
	[
		'post_type'              => 'hvl_lawyer',
		'post_status'            => 'publish',
		'posts_per_page'         => 16,
		'no_found_rows'          => false,
		'update_post_meta_cache' => false,
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
				<div class="filter-group" id="home-specialty-filters">
					<span class="filter-label">فیلتر تخصص:</span>
					<button class="filter-btn active" data-specialty="">همه</button>
					<?php if ( ! is_wp_error( $home_specialties ) ) : ?>
						<?php foreach ( $home_specialties as $spec_term ) : ?>
							<button class="filter-btn" data-specialty="<?php echo esc_attr( $spec_term->slug ); ?>">
								<?php echo esc_html( $spec_term->name ); ?>
							</button>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="lawyer-grid" id="home-lawyer-grid" aria-live="polite">
				<?php if ( $initial_query->have_posts() ) : ?>
					<?php while ( $initial_query->have_posts() ) : ?>
						<?php
						$initial_query->the_post();
						$post_id      = get_the_ID();
						$img          = get_the_post_thumbnail_url( $post_id, 'medium' );
						$specialties  = get_the_terms( $post_id, 'hvl_specialty' );
						$cities       = get_the_terms( $post_id, 'hvl_city' );
						$spec_names   = [];
						if ( is_array( $specialties ) && ! empty( $specialties ) ) {
							foreach ( $specialties as $t ) {
								if ( isset( $t->name ) && '' !== $t->name ) {
									$spec_names[] = $t->name;
								}
							}
							$spec_names = array_values( array_unique( $spec_names ) );
							sort( $spec_names, SORT_STRING );
						}
						$city = is_array( $cities ) && ! empty( $cities ) ? $cities[0]->name : '';
						?>
						<a href="<?php the_permalink(); ?>" class="lawyer-card lawyer-card--link ghost-border editorial-shadow">
							<div class="lawyer-card-image-wrapper">
								<img class="lawyer-card-image" src="<?php echo esc_url( $img ? $img : $profile_fallback_image ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" />
							</div>
							<div class="lawyer-card-content">
								<h3 class="lawyer-name"><?php the_title(); ?></h3>
								<?php if ( ! empty( $spec_names ) ) : ?>
									<div class="lawyer-card-specialties">
										<?php foreach ( $spec_names as $spec_label ) : ?>
											<span class="lawyer-card-specialty-tag"><?php echo esc_html( $spec_label ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<p class="lawyer-card-specialties-placeholder text-on-surface-variant text-sm">—</p>
								<?php endif; ?>
								<p class="text-secondary font-bold text-xs lawyer-card-city-line"><?php echo esc_html( $city ? $city : '—' ); ?></p>
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
				<h2 class="text-3xl font-bold text-primary mb-2">تخصص‌های کلیدی</h2>
				<a class="section-header-link text-primary font-bold text-sm" href="<?php echo esc_url( home_url( '/takha/' ) ); ?>">
					مشاهده همه
					<span class="material-symbols-outlined text-lg">chevron_left</span>
				</a>
			</div>
			<div class="specialty-grid">
				<?php if ( ! is_wp_error( $home_specialties ) && ! empty( $home_specialties ) ) : ?>
					<?php foreach ( $home_specialties as $term ) : ?>
						<?php $specialty_lawyers = (int) ( $home_specialty_lawyer_counts[ (int) $term->term_id ] ?? 0 ); ?>
						<a class="category-card ghost-border" href="<?php echo esc_url( add_query_arg( [ 'specialty[]' => $term->slug ], $archive_url ) ); ?>">
							<div class="category-icon-wrapper">
								<span class="material-symbols-outlined text-primary text-3xl">gavel</span>
							</div>
							<h3 class="text-xl font-bold text-primary mb-2"><?php echo esc_html( $term->name ); ?></h3>
							<p class="text-sm text-on-surface-variant"><?php echo esc_html( hovalvakil_to_fa_digits( number_format_i18n( $specialty_lawyers ) ) ); ?> وکیل</p>
						</a>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="text-on-surface-variant">هنوز تخصصی ثبت نشده است.</p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="cities">
		<div class="container">
			<div class="section-header city-section-header">
				<h2 class="text-2xl font-bold text-primary">جستجو بر اساس شهر</h2>
				<a class="section-header-link text-primary font-bold text-sm" href="<?php echo esc_url( home_url( '/marakez/' ) ); ?>">
					مشاهده همه
					<span class="material-symbols-outlined text-lg">chevron_left</span>
				</a>
			</div>
			<div class="city-grid">
				<?php if ( ! is_wp_error( $home_cities ) && ! empty( $home_cities ) ) : ?>
					<?php foreach ( $home_cities as $city_term ) : ?>
						<?php
						$city_lawyers = (int) ( $home_city_lawyer_counts[ (int) $city_term->term_id ] ?? 0 );
						?>
						<a class="city-card" href="<?php echo esc_url( add_query_arg( [ 'city_term[]' => $city_term->slug ], $archive_url ) ); ?>">
							<div class="city-card-header">
								<div class="city-link-header">
									<span class="material-symbols-outlined text-primary">location_on</span>
									<span class="font-bold text-primary"><?php echo esc_html( $city_term->name ); ?></span>
								</div>
								<span class="material-symbols-outlined text-outline-variant text-lg">chevron_left</span>
							</div>
							<p class="text-xs text-on-surface-variant">
								<?php echo esc_html( hovalvakil_to_fa_digits( number_format_i18n( $city_lawyers ) ) ); ?> وکیل فعال
							</p>
						</a>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="text-on-surface-variant">هنوز شهری ثبت نشده است.</p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<script>
		const apiBase = <?php echo wp_json_encode( esc_url_raw( rest_url( 'hovalvakil/v1/lawyers' ) ) ); ?>;
		const specialtyApi = <?php echo wp_json_encode( esc_url_raw( rest_url( 'hovalvakil/v1/specialties' ) ) ); ?>;
		const archiveUrl = <?php echo wp_json_encode( esc_url_raw( $archive_url ) ); ?>;
		const fallbackImage = 'https://via.placeholder.com/600x600.png?text=%D9%88%DA%A9%DB%8C%D9%84';
		const initialTotalPages = <?php echo (int) $initial_total_pages; ?>;

		const gridEl = document.getElementById('home-lawyer-grid');
		const queryInput = document.getElementById('home-search-input');
		const triggerBtn = document.getElementById('home-search-trigger');
		const loadMoreBtn = document.getElementById('home-load-more-btn');
		const specialtyFilterWrap = document.getElementById('home-specialty-filters');
		const liveResultsEl = document.getElementById('home-live-results');

		const GRID_PER_PAGE = 16;
		let currentPage = 1;
		let totalPages = initialTotalPages;
		let selectedSpecialty = '';
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

		function specialtiesMarkup(item) {
			const names = Array.isArray(item.specialties) && item.specialties.length
				? item.specialties
				: (item.specialty ? [item.specialty] : []);
			if (!names.length) {
				return '<p class="lawyer-card-specialties-placeholder text-on-surface-variant text-sm">—</p>';
			}
			const tags = names.map((n) => `<span class="lawyer-card-specialty-tag">${escapeHtml(n)}</span>`).join('');
			return `<div class="lawyer-card-specialties">${tags}</div>`;
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

		async function hydrateSpecialtyFilters() {
			if (!specialtyFilterWrap) return;
			try {
				const res = await fetch(specialtyApi, { credentials: 'same-origin' });
				if (!res.ok) throw new Error('specialties_failed');
				const data = await res.json();
				const terms = Array.isArray(data.items) ? data.items : [];
				const dynamicButtons = terms.map((term) => `
					<button class="filter-btn" data-specialty="${escapeHtml(term.slug || '')}">
						${escapeHtml(term.name || '')}
					</button>
				`).join('');
				specialtyFilterWrap.innerHTML = `
					<span class="filter-label">فیلتر تخصص:</span>
					<button class="filter-btn ${selectedSpecialty ? '' : 'active'}" data-specialty="">همه</button>
					${dynamicButtons}
				`;
				if (selectedSpecialty) {
					const activeBtn = specialtyFilterWrap.querySelector(`.filter-btn[data-specialty="${CSS.escape(selectedSpecialty)}"]`);
					if (activeBtn) activeBtn.classList.add('active');
				}
			} catch (e) {
				// Keep SSR fallback buttons.
			}
		}

		function renderCards(items, append = false) {
			if (!append) gridEl.innerHTML = '';
			if (!items.length && !append) {
				gridEl.innerHTML = '<p class="text-on-surface-variant">موردی یافت نشد.</p>';
				return;
			}
			const html = items.map((item) => `
				<a href="${escapeHtml(item.permalink || '#')}" class="lawyer-card lawyer-card--link ghost-border editorial-shadow">
					<div class="lawyer-card-image-wrapper">
						<img class="lawyer-card-image" src="${escapeHtml(item.image || fallbackImage)}" alt="${escapeHtml(item.name || '')}" />
					</div>
					<div class="lawyer-card-content">
						<h3 class="lawyer-name">${escapeHtml(item.name || '')}</h3>
						${specialtiesMarkup(item)}
						<p class="text-secondary font-bold text-xs lawyer-card-city-line">${escapeHtml(item.city || '—')}</p>
						<span class="btn-primary-full">مشاهده پروفایل</span>
					</div>
				</a>
			`).join('');
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
			if (selectedSpecialty) params.set('specialty', selectedSpecialty);
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
			const city = normalizeFaText(item?.city || '');
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

			const rows = items.map((item) => `
				<a class="home-live-item" href="${escapeHtml(item.permalink || '#')}">
					<img class="home-live-item-image" src="${escapeHtml(item.image || fallbackImage)}" alt="${escapeHtml(item.name || '')}" />
					<div class="home-live-item-content">
						<div class="home-live-item-title">${escapeHtml(item.name || '')}</div>
						<div class="home-live-item-meta">
							<span>${escapeHtml(item.specialty || '—')}</span>
							<span>•</span>
							<span>${escapeHtml(item.city || '—')}</span>
							<span>•</span>
							<span>${escapeHtml(item.experience || '—')}</span>
							<span>•</span>
							<span>${escapeHtml(item.price_from || '—')}</span>
						</div>
					</div>
				</a>
			`).join('');

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
			if (selectedSpecialty) params.set('specialty', selectedSpecialty);
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

		specialtyFilterWrap?.addEventListener('click', (event) => {
			const target = event.target;
			if (!(target instanceof HTMLElement)) return;
			const btn = target.closest('.filter-btn');
			if (!(btn instanceof HTMLElement)) return;

			selectedSpecialty = btn.dataset.specialty || '';
			specialtyFilterWrap.querySelectorAll('.filter-btn').forEach((el) => {
				el.classList.remove('active');
			});
			btn.classList.add('active');
			fetchLawyers({ page: 1, skeleton: true });
			fetchLiveResults();
		});

		hydrateSpecialtyFilters();

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
	</style>
<?php get_footer(); ?>

