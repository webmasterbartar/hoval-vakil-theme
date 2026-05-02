<?php
/**
 * Page template for legal centers (marakez).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$archive_url = get_post_type_archive_link( 'hvl_lawyer' );
$rezerv_url  = home_url( '/rezerv' );
$centers_total = hovalvakil_marakez_get_centers_publish_count();

$center_query = hovalvakil_marakez_get_initial_centers_wp_query( 12 );

$all_city_terms = get_terms(
	[
		'taxonomy'   => 'hvl_city',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	]
);

$city_center_counts_map = hovalvakil_center_get_city_count_map();
$city_center_counts     = [];
$covered_cities         = 0;
$max_centers_in_city    = 0;
if ( ! is_wp_error( $all_city_terms ) && ! empty( $all_city_terms ) ) {
	foreach ( $all_city_terms as $city_term ) {
		$tid          = (int) $city_term->term_id;
		$center_count = (int) ( $city_center_counts_map[ $tid ] ?? 0 );
		$city_center_counts[ $tid ] = $center_count;
		if ( $center_count > 0 ) {
			$covered_cities++;
		}
		if ( $center_count > $max_centers_in_city ) {
			$max_centers_in_city = $center_count;
		}
	}
}
$avg_centers_per_city = $covered_cities > 0 ? (int) round( $centers_total / $covered_cities ) : 0;
get_header();
?>
	<main id="content">
	<header class="marakez-hero">
		<div class="container">
			<div class="marakez-breadcrumb"><span>خانه</span><span>←</span><span>مراکز حقوقی</span></div>
			<h1>مراکز حقوقی ایران</h1>
			<div class="marakez-search">
				<input id="marakez-search-input" type="text" placeholder="نام مرکز حقوقی یا کانون..." />
				<select id="marakez-city-select">
					<option value="">همه شهرها</option>
					<?php if ( ! is_wp_error( $all_city_terms ) ) : ?>
						<?php foreach ( $all_city_terms as $city_term ) : ?>
							<option value="<?php echo esc_attr( (string) (int) $city_term->term_id ); ?>"><?php echo esc_html( $city_term->name ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<button id="marakez-search-btn" type="button">جستجو</button>
			</div>
		</div>
	</header>

	<section class="marakez-stats container">
		<div class="marakez-stat"><strong><?php echo esc_html( number_format_i18n( $centers_total ) ); ?></strong><span>مرکز حقوقی فعال</span></div>
		<div class="marakez-stat"><strong><?php echo esc_html( number_format_i18n( $covered_cities ) ); ?></strong><span>شهر دارای مرکز</span></div>
		<div class="marakez-stat"><strong><?php echo esc_html( number_format_i18n( $avg_centers_per_city ) ); ?></strong><span>میانگین مرکز در هر شهر</span></div>
		<div class="marakez-stat"><strong><?php echo esc_html( number_format_i18n( $max_centers_in_city ) ); ?></strong><span>بیشترین مرکز در یک شهر</span></div>
	</section>

	<section class="marakez-main container">
		<aside id="marakez-filters-panel" class="marakez-filters">
			<div class="marakez-panel">
				<div class="marakez-panel-head">
					<h3><span class="material-symbols-outlined">tune</span>فیلتر شهر</h3>
					<div class="marakez-panel-head-actions">
						<button id="marakez-clear-cities" type="button">حذف همه</button>
						<button id="marakez-filters-close" class="marakez-filters-close-btn" type="button" aria-label="بستن فیلترها">
							<span class="material-symbols-outlined">close</span>
						</button>
					</div>
				</div>
				<div class="marakez-filter-group" id="marakez-city-filter-group">
					<h4>شهرها</h4>
					<?php if ( ! is_wp_error( $all_city_terms ) ) : ?>
						<?php foreach ( $all_city_terms as $city_term ) : ?>
							<label>
								<input type="checkbox" value="<?php echo esc_attr( (string) (int) $city_term->term_id ); ?>" />
								<?php echo esc_html( $city_term->name ); ?>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</aside>

		<div class="marakez-results">
			<div class="marakez-results-head">
				<span id="marakez-result-count">نمایش <?php echo esc_html( number_format_i18n( (int) $center_query->found_posts ) ); ?> نتیجه</span>
			</div>

			<div id="marakez-results-list">
				<?php if ( $center_query->have_posts() ) : ?>
					<?php while ( $center_query->have_posts() ) : ?>
						<?php
						$center_query->the_post();
						$post_id      = get_the_ID();
						$image        = get_the_post_thumbnail_url( $post_id, 'large' );
						$city_terms   = get_the_terms( $post_id, 'hvl_city' );
						$city_name    = is_array( $city_terms ) && ! empty( $city_terms ) ? $city_terms[0]->name : '';
						$city_slug    = is_array( $city_terms ) && ! empty( $city_terms ) ? (string) $city_terms[0]->slug : '';
						$archive_link = $city_slug ? add_query_arg( [ 'city_term[]' => $city_slug ], $archive_url ) : $archive_url;
						?>
						<article class="marakez-card">
							<img src="<?php echo esc_url( $image ? $image : 'https://via.placeholder.com/900x600.png?text=%D9%85%D8%B1%DA%A9%D8%B2+%D8%AD%D9%82%D9%88%D9%82%DB%8C' ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" />
							<div class="marakez-card-body">
								<h3><?php the_title(); ?></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt() ? get_the_excerpt() : get_the_content(), 18 ) ); ?></p>
								<div class="marakez-card-meta">
									<?php if ( $city_name ) : ?><span><?php echo esc_html( $city_name ); ?></span><?php endif; ?>
									<span>مرکز حقوقی</span>
								</div>
								<div class="marakez-card-actions">
									<a href="<?php echo esc_url( $archive_link ); ?>">مشاهده وکلا</a>
								</div>
							</div>
						</article>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<p class="text-on-surface-variant">هنوز مرکز حقوقی ثبت نشده است.</p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="marakez-cities">
		<div class="container">
			<h2>مراکز حقوقی به تفکیک شهر</h2>
			<div class="marakez-city-grid">
				<?php if ( ! is_wp_error( $all_city_terms ) ) : ?>
					<?php foreach ( $all_city_terms as $city_term ) : ?>
						<a href="<?php echo esc_url( add_query_arg( [ 'city_term_id' => (int) $city_term->term_id ], home_url( '/marakez' ) ) ); ?>">
							<?php echo esc_html( $city_term->name ); ?>
							<span><?php echo esc_html( number_format_i18n( (int) ( $city_center_counts[ (int) $city_term->term_id ] ?? 0 ) ) ); ?> مرکز</span>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<button id="marakez-filters-fab" class="marakez-filters-fab" type="button" aria-label="باز کردن فیلترها">
		<span class="material-symbols-outlined">tune</span>
		<span>فیلترها</span>
	</button>
	<div id="marakez-filters-backdrop" class="marakez-filters-backdrop"></div>

	<script>
		(function() {
			const centersApi = <?php echo wp_json_encode( esc_url_raw( rest_url( 'hovalvakil/v1/centers' ) ) ); ?>;
			const listEl = document.getElementById('marakez-results-list');
			const countEl = document.getElementById('marakez-result-count');
			const searchInput = document.getElementById('marakez-search-input');
			const citySelect = document.getElementById('marakez-city-select');
			const searchBtn = document.getElementById('marakez-search-btn');
			const cityFilterGroup = document.getElementById('marakez-city-filter-group');
			const clearCitiesBtn = document.getElementById('marakez-clear-cities');
			const filtersFab = document.getElementById('marakez-filters-fab');
			const filtersCloseBtn = document.getElementById('marakez-filters-close');
			const filtersPanel = document.getElementById('marakez-filters-panel');
			const filtersBackdrop = document.getElementById('marakez-filters-backdrop');
			const archiveUrl = <?php echo wp_json_encode( esc_url_raw( $archive_url ) ); ?>;

			const initialParams = new URLSearchParams(window.location.search);
			let activeCityId = initialParams.get('city_term_id') || '';
			const initialQuery = initialParams.get('q') || '';
			let timer = null;
			let controller = null;
			const cache = new Map();

			function esc(value) {
				return (value || '')
					.toString()
					.replaceAll('&', '&amp;')
					.replaceAll('<', '&lt;')
					.replaceAll('>', '&gt;')
					.replaceAll('"', '&quot;')
					.replaceAll("'", '&#039;');
			}

			function closeFiltersOffcanvas() {
				filtersPanel?.classList.remove('is-open');
				filtersBackdrop?.classList.remove('is-open');
				document.body.classList.remove('marakez-offcanvas-open');
			}

			function openFiltersOffcanvas() {
				filtersPanel?.classList.add('is-open');
				filtersBackdrop?.classList.add('is-open');
				document.body.classList.add('marakez-offcanvas-open');
			}

			function syncCityInputs() {
				if (citySelect) citySelect.value = activeCityId;
				cityFilterGroup?.querySelectorAll('input[type="checkbox"]').forEach((el) => {
					el.checked = el.value === activeCityId;
				});
			}

			function syncUrlState() {
				const params = new URLSearchParams();
				const q = searchInput?.value?.trim() || '';
				if (q) params.set('q', q);
				if (activeCityId) params.set('city_term_id', activeCityId);
				const next = params.toString() ? `${window.location.pathname}?${params.toString()}` : window.location.pathname;
				window.history.replaceState({}, '', next);
			}

			function renderCenters(items, total) {
				countEl.textContent = `نمایش ${Number(total || 0).toLocaleString('fa-IR')} نتیجه`;
				if (!items.length) {
					listEl.innerHTML = '<p class="text-on-surface-variant">موردی پیدا نشد.</p>';
					return;
				}
				listEl.innerHTML = items.map((item) => {
					const toArchive = item.city_slug
						? `${archiveUrl}?city_term[]=${encodeURIComponent(item.city_slug)}`
						: archiveUrl;
					return `
						<article class="marakez-card">
							<img src="${esc(item.image)}" alt="${esc(item.title)}" />
							<div class="marakez-card-body">
								<h3>${esc(item.title)}</h3>
								<p>${esc(item.content || '')}</p>
								<div class="marakez-card-meta">
									${item.city ? `<span>${esc(item.city)}</span>` : ''}
									<span>مرکز حقوقی</span>
								</div>
								<div class="marakez-card-actions">
									<a href="${esc(toArchive)}">مشاهده وکلا</a>
								</div>
							</div>
						</article>
					`;
				}).join('');
			}

			async function fetchCenters() {
				const q = searchInput?.value?.trim() || '';
				const params = new URLSearchParams({ page: '1', per_page: '12' });
				if (q) params.set('q', q);
				if (activeCityId) params.set('city_term_id', activeCityId);
				const cacheKey = params.toString();

				if (cache.has(cacheKey)) {
					const cached = cache.get(cacheKey);
					renderCenters(Array.isArray(cached.items) ? cached.items : [], Number(cached.total || 0));
					syncUrlState();
					return;
				}

				if (controller) controller.abort();
				controller = new AbortController();
				listEl.innerHTML = '<p class="text-on-surface-variant">در حال جستجو...</p>';

				try {
					const res = await fetch(`${centersApi}?${params.toString()}`, {
						credentials: 'same-origin',
						signal: controller.signal
					});
					if (!res.ok) throw new Error('centers_failed');
					const data = await res.json();
					cache.set(cacheKey, data);
					if (cache.size > 40) {
						const oldestKey = cache.keys().next().value;
						cache.delete(oldestKey);
					}
					renderCenters(Array.isArray(data.items) ? data.items : [], Number(data.total || 0));
					syncUrlState();
				} catch (e) {
					if (e && e.name === 'AbortError') return;
					listEl.innerHTML = '<p class="text-on-surface-variant">خطا در دریافت مراکز. دوباره تلاش کنید.</p>';
				}
			}

			searchBtn?.addEventListener('click', fetchCenters);
			searchInput?.addEventListener('input', () => {
				if (timer) clearTimeout(timer);
				timer = setTimeout(fetchCenters, 80);
			});
			searchInput?.addEventListener('keydown', (event) => {
				if (event.key !== 'Enter') return;
				event.preventDefault();
				fetchCenters();
			});
			citySelect?.addEventListener('change', () => {
				activeCityId = citySelect.value || '';
				syncCityInputs();
				fetchCenters();
			});
			cityFilterGroup?.addEventListener('change', (event) => {
				const target = event.target;
				if (!(target instanceof HTMLInputElement)) return;
				if (target.type !== 'checkbox') return;
				activeCityId = target.checked ? target.value : '';
				syncCityInputs();
				closeFiltersOffcanvas();
				fetchCenters();
			});
			clearCitiesBtn?.addEventListener('click', () => {
				activeCityId = '';
				syncCityInputs();
				fetchCenters();
			});

			filtersFab?.addEventListener('click', openFiltersOffcanvas);
			filtersBackdrop?.addEventListener('click', closeFiltersOffcanvas);
			filtersCloseBtn?.addEventListener('click', closeFiltersOffcanvas);

			if (searchInput && initialQuery) {
				searchInput.value = initialQuery;
			}
			syncCityInputs();
			fetchCenters();
		})();
	</script>
	</main>
<?php get_footer(); ?>

