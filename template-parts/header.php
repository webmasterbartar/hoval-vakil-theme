<?php
/**
 * Coded Hovalvakil header.
 *
 * @package HelloElementor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$site_name = 'هوالوکیل';
$header_phone   = (string) get_theme_mod( 'hvl_footer_phone', '۰۲۱۲۲۰۰۰۷۵۲' );
$header_phone_ascii = strtr(
	$header_phone,
	[
		'۰' => '0',
		'۱' => '1',
		'۲' => '2',
		'۳' => '3',
		'۴' => '4',
		'۵' => '5',
		'۶' => '6',
		'۷' => '7',
		'۸' => '8',
		'۹' => '9',
	]
);
$header_phone_dial = preg_replace( '/\D+/', '', $header_phone_ascii );
if ( ! is_string( $header_phone_dial ) || '' === $header_phone_dial ) {
	$header_phone_dial = '02122000752';
}

$current_path = trim( (string) wp_parse_url( home_url( add_query_arg( [] ) ), PHP_URL_PATH ), '/' );

$header_links = [
	[
		'label' => 'خانه',
		'url'   => home_url( '/' ),
		'icon'  => 'home',
		'slug'  => '',
	],
	[
		'label' => 'وکلا',
		'url'   => get_post_type_archive_link( 'hvl_lawyer' ),
		'icon'  => 'gavel',
		'slug'  => 'hvl_lawyer',
	],
	[
		'label' => 'مراکز حقوقی',
		'url'   => home_url( '/marakez/' ),
		'icon'  => 'account_balance',
		'slug'  => 'marakez',
	],
	[
		'label' => 'تماس با ما',
		'url'   => home_url( '/tamas/' ),
		'icon'  => 'call',
		'slug'  => 'tamas',
	],
	[
		'label' => 'درباره ما',
		'url'   => home_url( '/about/' ),
		'icon'  => 'info',
		'slug'  => 'about',
	],
];

$desktop_links = array_values(
	array_filter(
		$header_links,
		static function ( $item ) {
			return 'marakez' !== (string) ( $item['slug'] ?? '' );
		}
	)
);

$desktop_provinces = get_terms(
	[
		'taxonomy'   => 'hvl_province',
		'hide_empty' => true,
		'orderby'    => 'name',
		'order'      => 'ASC',
		'number'     => 12,
	]
);
if ( is_wp_error( $desktop_provinces ) ) {
	$desktop_provinces = [];
}

/** شهرهای هر استان از روی هم‌آیی واقعی روی پست وکیل (همان منطق catalogue). */
$hvl_mega_cities_by_province = [];
if ( function_exists( 'hovalvakil_home_city_province_pairs_for_lawyers' ) ) {
	foreach ( hovalvakil_home_city_province_pairs_for_lawyers() as $pair ) {
		$p = $pair['province'];
		$c = $pair['city'];
		if ( ! $p instanceof WP_Term || ! $c instanceof WP_Term ) {
			continue;
		}
		$pslug = (string) $p->slug;
		if ( '' === $pslug ) {
			continue;
		}
		if ( ! isset( $hvl_mega_cities_by_province[ $pslug ] ) ) {
			$hvl_mega_cities_by_province[ $pslug ] = [];
		}
		$cid = (int) $c->term_id;
		if ( isset( $hvl_mega_cities_by_province[ $pslug ][ '_' . $cid ] ) ) {
			continue;
		}
		$hvl_mega_cities_by_province[ $pslug ][ '_' . $cid ] = [
			'n' => (string) $c->name,
			's' => (string) $c->slug,
			'u' => add_query_arg( 'city_term[]', $c->slug, get_post_type_archive_link( 'hvl_lawyer' ) ),
		];
	}
}
foreach ( $hvl_mega_cities_by_province as $pslug => $assoc ) {
	$rows                               = array_values( $assoc );
	$hvl_mega_cities_by_province[ $pslug ] = $rows;
	usort(
		$hvl_mega_cities_by_province[ $pslug ],
		static function ( $a, $b ) {
			return strcmp( (string) ( $a['n'] ?? '' ), (string) ( $b['n'] ?? '' ) );
		}
	);
}

$hvl_json_cities_payload = [];
foreach ( $desktop_provinces as $prov_term ) {
	if ( ! $prov_term instanceof WP_Term ) {
		continue;
	}
	$slug = (string) $prov_term->slug;
	$hvl_json_cities_payload[ $slug ] = $hvl_mega_cities_by_province[ $slug ] ?? [];
}

$hvl_first_province_slug = '';
if ( ! empty( $desktop_provinces ) && $desktop_provinces[0] instanceof WP_Term ) {
	$hvl_first_province_slug = (string) $desktop_provinces[0]->slug;
}
$hvl_initial_cities = ( '' !== $hvl_first_province_slug && isset( $hvl_mega_cities_by_province[ $hvl_first_province_slug ] ) )
	? $hvl_mega_cities_by_province[ $hvl_first_province_slug ]
	: [];

$desktop_grade_links = [
	[
		'label' => 'وکیل پایه یک',
		'url'   => add_query_arg( 'grade', 'p1', get_post_type_archive_link( 'hvl_lawyer' ) ),
	],
	[
		'label' => 'وکیل پایه دو',
		'url'   => add_query_arg( 'grade', 'p2', get_post_type_archive_link( 'hvl_lawyer' ) ),
	],
	[
		'label' => 'کارآموز وکالت',
		'url'   => add_query_arg( 'grade', 'karamooz', get_post_type_archive_link( 'hvl_lawyer' ) ),
	],
];

$hvl_lawyer_archive_url = get_post_type_archive_link( 'hvl_lawyer' );
$hvl_search_img_fallback  = function_exists( 'hovalvakil_theme_lawyer_placeholder_url' )
	? hovalvakil_theme_lawyer_placeholder_url()
	: get_template_directory_uri() . '/assets/images/lawyer-placeholder.svg';
$hvl_lawyers_rest_url     = rest_url( 'hovalvakil/v1/lawyers' );
?>

<header id="site-header" class="site-header hvl-header">
	<div class="hvl-header-main">
		<div class="container hvl-header-main-inner">
			<div class="hvl-header-logo">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $site_name ); ?></a>
			</div>
			<nav class="hvl-header-nav" aria-label="<?php echo esc_attr__( 'Main menu', 'hello-elementor' ); ?>">
				<?php foreach ( $desktop_links as $item ) : ?>
					<?php
					$active = '';
					if ( '' === $item['slug'] ) {
						$active = '' === $current_path ? ' is-active' : '';
					} elseif ( false !== strpos( $current_path, (string) $item['slug'] ) ) {
						$active = ' is-active';
					}
					?>
					<a class="<?php echo esc_attr( 'hvl-header-link' . $active ); ?>" href="<?php echo esc_url( $item['url'] ); ?>">
						<span class="material-symbols-outlined"><?php echo esc_html( $item['icon'] ); ?></span>
						<?php echo esc_html( $item['label'] ); ?>
					</a>
				<?php endforeach; ?>
				<div class="hvl-header-menu-item hvl-header-menu-item--mega">
					<a class="hvl-header-link hvl-header-mega-trigger" href="<?php echo esc_url( get_post_type_archive_link( 'hvl_lawyer' ) ); ?>">
						<span class="material-symbols-outlined">map</span>
						استان و شهر
					</a>
					<div
						id="hvl-header-mega-loc-panel"
						class="hvl-header-mega-panel hvl-header-mega-panel--loc"
						data-empty-msg="<?php echo esc_attr__( 'برای این استان در داده‌ها شهری ثبت نشده است.', 'hello-elementor' ); ?>"
					>
						<div class="hvl-header-mega-col hvl-header-mega-col--provinces">
							<p class="hvl-header-mega-title">استان‌ها</p>
							<div class="hvl-header-mega-prov-scroll">
								<?php foreach ( $desktop_provinces as $idx => $term ) : ?>
									<?php
									$pactive = ( 0 === (int) $idx ) ? ' is-active' : '';
									$phref   = add_query_arg( 'province_term[]', $term->slug, get_post_type_archive_link( 'hvl_lawyer' ) );
									?>
									<a
										class="<?php echo esc_attr( 'hvl-header-mega-prov-link' . $pactive ); ?>"
										href="<?php echo esc_url( $phref ); ?>"
										data-province-slug="<?php echo esc_attr( $term->slug ); ?>"
									><?php echo esc_html( $term->name ); ?></a>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="hvl-header-mega-col hvl-header-mega-col--cities">
							<p class="hvl-header-mega-title">شهرها</p>
							<div
								class="hvl-header-mega-cities-list"
								role="region"
								aria-live="polite"
								aria-label="<?php echo esc_attr__( 'شهرهای استان انتخاب‌شده', 'hello-elementor' ); ?>"
							>
								<?php if ( empty( $hvl_initial_cities ) ) : ?>
									<p class="hvl-header-mega-empty"><?php esc_html_e( 'برای این استان در داده‌ها شهری ثبت نشده است.', 'hello-elementor' ); ?></p>
								<?php else : ?>
									<?php foreach ( $hvl_initial_cities as $crow ) : ?>
										<a href="<?php echo esc_url( $crow['u'] ?? '#' ); ?>"><?php echo esc_html( $crow['n'] ?? '' ); ?></a>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				<div class="hvl-header-menu-item hvl-header-menu-item--mega">
					<a class="hvl-header-link hvl-header-mega-trigger" href="<?php echo esc_url( get_post_type_archive_link( 'hvl_lawyer' ) ); ?>">
						<span class="material-symbols-outlined">badge</span>
						پایه‌ها
					</a>
					<div class="hvl-header-mega-panel hvl-header-mega-panel--sm">
						<div class="hvl-header-mega-col">
							<p class="hvl-header-mega-title">فیلتر مقطع وکلا</p>
							<?php foreach ( $desktop_grade_links as $grade_item ) : ?>
								<a href="<?php echo esc_url( $grade_item['url'] ); ?>"><?php echo esc_html( $grade_item['label'] ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</nav>
			<div class="hvl-header-actions">
				<a class="hvl-header-login" href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>">ورود | ثبت‌نام</a>
				<button id="hvl-mobile-menu-toggle" class="hvl-header-mobile-toggle" type="button" aria-label="باز کردن منو" aria-expanded="false" aria-controls="hvl-mobile-drawer">
					<span class="material-symbols-outlined">menu</span>
				</button>
			</div>
		</div>
	</div>

	<div id="hvl-mobile-backdrop" class="hvl-mobile-backdrop" hidden></div>
	<aside id="hvl-mobile-drawer" class="hvl-mobile-drawer" aria-hidden="true" tabindex="-1">
		<div class="hvl-mobile-drawer-head">
			<div class="hvl-mobile-drawer-brand"><?php echo esc_html( $site_name ); ?></div>
			<button id="hvl-mobile-menu-close" type="button" class="hvl-mobile-drawer-close" aria-label="بستن منو">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<form id="hvl-mobile-drawer-search-form" class="hvl-mobile-drawer-search" method="get" action="<?php echo esc_url( $hvl_lawyer_archive_url ); ?>" role="search">
			<label class="screen-reader-text" for="hvl-mobile-drawer-search-q"><?php esc_html_e( 'جستجوی وکیل', 'hello-elementor' ); ?></label>
			<input id="hvl-mobile-drawer-search-q" type="search" name="q" placeholder="<?php echo esc_attr__( 'جستجوی وکیل، شهر…', 'hello-elementor' ); ?>" autocomplete="off" />
			<button type="submit" aria-label="<?php echo esc_attr__( 'جستجو', 'hello-elementor' ); ?>">
				<span class="material-symbols-outlined" aria-hidden="true">search</span>
			</button>
		</form>
		<p id="hvl-mobile-drawer-search-hint" class="hvl-mobile-search-hint" hidden></p>
		<div id="hvl-mobile-drawer-live-wrap" class="hvl-mobile-search-live-wrap hvl-mobile-drawer-live-wrap" hidden>
			<div
				id="hvl-mobile-drawer-live"
				class="hvl-mobile-search-live"
				role="listbox"
				aria-label="<?php echo esc_attr__( 'نتایج جستجو', 'hello-elementor' ); ?>"
			></div>
			<a id="hvl-mobile-drawer-see-all" class="hvl-mobile-search-see-all" href="<?php echo esc_url( $hvl_lawyer_archive_url ); ?>" hidden><?php esc_html_e( 'مشاهدهٔ همهٔ نتایج', 'hello-elementor' ); ?></a>
		</div>
		<nav class="hvl-mobile-nav" aria-label="منوی موبایل">
			<?php foreach ( $header_links as $item ) : ?>
				<?php
				$active = '';
				if ( '' === $item['slug'] ) {
					$active = '' === $current_path ? ' is-active' : '';
				} elseif ( false !== strpos( $current_path, (string) $item['slug'] ) ) {
					$active = ' is-active';
				}
				?>
				<a class="<?php echo esc_attr( 'hvl-mobile-link' . $active ); ?>" href="<?php echo esc_url( $item['url'] ); ?>">
					<span class="material-symbols-outlined"><?php echo esc_html( $item['icon'] ); ?></span>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
			<div
				id="hvl-mobile-loc-block"
				class="hvl-mobile-loc-block"
				data-empty-msg="<?php echo esc_attr__( 'برای این استان در داده‌ها شهری ثبت نشده است.', 'hello-elementor' ); ?>"
				data-cities-heading="<?php echo esc_attr__( 'شهرها', 'hello-elementor' ); ?>"
			>
				<button
					type="button"
					class="hvl-mobile-loc-toggle"
					id="hvl-mobile-loc-toggle"
					aria-expanded="false"
					aria-controls="hvl-mobile-loc-stack"
				>
					<span class="hvl-mobile-loc-toggle-main">
						<span class="material-symbols-outlined" aria-hidden="true">map</span>
						<span><?php esc_html_e( 'استان و شهر', 'hello-elementor' ); ?></span>
					</span>
					<span class="material-symbols-outlined hvl-mobile-loc-toggle-chevron" aria-hidden="true">expand_more</span>
				</button>
				<div class="hvl-mobile-loc-stack" id="hvl-mobile-loc-stack" hidden>
					<div class="hvl-mobile-loc-pane hvl-mobile-loc-pane--provinces is-active" id="hvl-mobile-loc-pane-provinces">
						<p class="hvl-mobile-loc-hint"><?php esc_html_e( 'ابتدا استان را انتخاب کنید، سپس شهر.', 'hello-elementor' ); ?></p>
						<div class="hvl-mobile-loc-prov-scroll">
							<?php foreach ( $desktop_provinces as $term ) : ?>
								<button
									type="button"
									class="hvl-mobile-prov-btn"
									data-province-slug="<?php echo esc_attr( $term->slug ); ?>"
									data-province-label="<?php echo esc_attr( $term->name ); ?>"
								><?php echo esc_html( $term->name ); ?></button>
							<?php endforeach; ?>
						</div>
						<a class="hvl-mobile-loc-archive-link hvl-mobile-link" href="<?php echo esc_url( $hvl_lawyer_archive_url ); ?>">
							<span class="material-symbols-outlined">gavel</span>
							<span><?php esc_html_e( 'همهٔ وکلا', 'hello-elementor' ); ?></span>
						</a>
					</div>
					<div class="hvl-mobile-loc-pane hvl-mobile-loc-pane--cities" id="hvl-mobile-loc-pane-cities">
						<button type="button" class="hvl-mobile-loc-back" id="hvl-mobile-loc-back">
							<span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
							<?php esc_html_e( 'بازگشت به استان‌ها', 'hello-elementor' ); ?>
						</button>
						<p class="hvl-mobile-loc-city-heading" id="hvl-mobile-loc-city-heading"></p>
						<div
							class="hvl-mobile-loc-cities-list"
							id="hvl-mobile-loc-cities-list"
							role="region"
							aria-live="polite"
							aria-label="<?php echo esc_attr__( 'شهرهای استان انتخاب‌شده', 'hello-elementor' ); ?>"
						></div>
					</div>
				</div>
			</div>
			<div id="hvl-mobile-grade-block" class="hvl-mobile-loc-block hvl-mobile-grade-block">
				<button
					type="button"
					class="hvl-mobile-loc-toggle"
					id="hvl-mobile-grade-toggle"
					aria-expanded="false"
					aria-controls="hvl-mobile-grade-stack"
				>
					<span class="hvl-mobile-loc-toggle-main">
						<span class="material-symbols-outlined" aria-hidden="true">badge</span>
						<span><?php esc_html_e( 'پایه‌ها', 'hello-elementor' ); ?></span>
					</span>
					<span class="material-symbols-outlined hvl-mobile-loc-toggle-chevron" aria-hidden="true">expand_more</span>
				</button>
				<div class="hvl-mobile-loc-stack" id="hvl-mobile-grade-stack" hidden>
					<?php foreach ( $desktop_grade_links as $grade_item ) : ?>
						<a class="hvl-mobile-link" href="<?php echo esc_url( $grade_item['url'] ); ?>">
							<span class="material-symbols-outlined">grading</span>
							<span><?php echo esc_html( $grade_item['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</nav>
		<div class="hvl-mobile-drawer-footer">
			<a class="hvl-mobile-primary-cta" href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>">ورود | ثبت‌نام</a>
			<a class="hvl-mobile-support-link" href="<?php echo esc_url( 'tel:' . (string) $header_phone_dial ); ?>">
				<span class="material-symbols-outlined">call</span>
				پشتیبانی تلفنی <?php echo esc_html( $header_phone ); ?>
			</a>
		</div>
	</aside>

	<nav id="hvl-mobile-quick-dock" class="hvl-mobile-quick-dock" aria-label="<?php echo esc_attr__( 'میانبر موبایل', 'hello-elementor' ); ?>">
		<a class="hvl-mobile-dock-btn hvl-mobile-dock-btn--link" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr__( 'خانه', 'hello-elementor' ); ?>">
			<span class="material-symbols-outlined" aria-hidden="true">home</span>
			<span class="hvl-mobile-dock-label"><?php esc_html_e( 'خانه', 'hello-elementor' ); ?></span>
		</a>
		<button type="button" class="hvl-mobile-dock-btn hvl-mobile-dock-btn--primary" id="hvl-mobile-dock-search" aria-label="<?php echo esc_attr__( 'جستجو', 'hello-elementor' ); ?>" aria-controls="hvl-mobile-search-sheet" aria-expanded="false">
			<span class="material-symbols-outlined" aria-hidden="true">search</span>
			<span class="hvl-mobile-dock-label"><?php esc_html_e( 'جستجو', 'hello-elementor' ); ?></span>
		</button>
		<a class="hvl-mobile-dock-btn hvl-mobile-dock-btn--link" href="<?php echo esc_url( $hvl_lawyer_archive_url ); ?>" aria-label="<?php echo esc_attr__( 'وکلا', 'hello-elementor' ); ?>">
			<span class="material-symbols-outlined" aria-hidden="true">gavel</span>
			<span class="hvl-mobile-dock-label"><?php esc_html_e( 'وکلا', 'hello-elementor' ); ?></span>
		</a>
		<button type="button" class="hvl-mobile-dock-btn" id="hvl-mobile-dock-menu" aria-label="<?php echo esc_attr__( 'منو', 'hello-elementor' ); ?>" aria-controls="hvl-mobile-drawer">
			<span class="material-symbols-outlined" aria-hidden="true">menu</span>
			<span class="hvl-mobile-dock-label"><?php esc_html_e( 'منو', 'hello-elementor' ); ?></span>
		</button>
	</nav>

	<div id="hvl-mobile-search-sheet" class="hvl-mobile-search-sheet" hidden aria-hidden="true">
		<div id="hvl-mobile-search-backdrop" class="hvl-mobile-search-sheet-backdrop" role="presentation"></div>
		<div class="hvl-mobile-search-sheet-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'جستجوی وکیل', 'hello-elementor' ); ?>">
			<form id="hvl-mobile-search-form" class="hvl-mobile-search-form" method="get" action="<?php echo esc_url( $hvl_lawyer_archive_url ); ?>" role="search">
				<label class="screen-reader-text" for="hvl-mobile-search-q"><?php esc_html_e( 'نام وکیل، تخصص یا شهر', 'hello-elementor' ); ?></label>
				<input id="hvl-mobile-search-q" name="q" type="search" placeholder="<?php echo esc_attr__( 'نام وکیل، تخصص، شهر…', 'hello-elementor' ); ?>" autocomplete="off" autocorrect="off" spellcheck="false" />
				<button type="submit" aria-label="<?php echo esc_attr__( 'جستجو', 'hello-elementor' ); ?>">
					<span class="material-symbols-outlined" aria-hidden="true">search</span>
				</button>
			</form>
			<p id="hvl-mobile-search-hint" class="hvl-mobile-search-hint" hidden></p>
			<div id="hvl-mobile-search-live-wrap" class="hvl-mobile-search-live-wrap" hidden>
				<div
					id="hvl-mobile-search-live"
					class="hvl-mobile-search-live"
					role="listbox"
					aria-label="<?php echo esc_attr__( 'نتایج زنده', 'hello-elementor' ); ?>"
				></div>
				<a id="hvl-mobile-search-see-all" class="hvl-mobile-search-see-all" href="<?php echo esc_url( $hvl_lawyer_archive_url ); ?>" hidden><?php esc_html_e( 'مشاهدهٔ همهٔ نتایج در آرشیو', 'hello-elementor' ); ?></a>
			</div>
			<button type="button" class="hvl-mobile-search-close" id="hvl-mobile-search-sheet-close"><?php esc_html_e( 'بستن', 'hello-elementor' ); ?></button>
		</div>
	</div>

	<script type="application/json" id="hvl-loc-cities-data"><?php echo wp_json_encode( $hvl_json_cities_payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</header>

<script>
	(function () {
		const toggleBtn = document.getElementById('hvl-mobile-menu-toggle');
		const closeBtn = document.getElementById('hvl-mobile-menu-close');
		const drawer = document.getElementById('hvl-mobile-drawer');
		const backdrop = document.getElementById('hvl-mobile-backdrop');
		if (!toggleBtn || !closeBtn || !drawer || !backdrop) return;

		let lastFocused = null;

		const setOpenState = (open) => {
			if (open) {
				lastFocused = document.activeElement;
				drawer.classList.add('is-open');
				backdrop.classList.add('is-open');
				backdrop.hidden = false;
				drawer.setAttribute('aria-hidden', 'false');
				toggleBtn.setAttribute('aria-expanded', 'true');
				document.body.classList.add('hvl-mobile-menu-open');
				window.setTimeout(() => closeBtn.focus(), 30);
			} else {
				drawer.classList.remove('is-open');
				backdrop.classList.remove('is-open');
				drawer.setAttribute('aria-hidden', 'true');
				toggleBtn.setAttribute('aria-expanded', 'false');
				document.body.classList.remove('hvl-mobile-menu-open');
				drawer.dispatchEvent(new CustomEvent('hvlMobileDrawerClosed', { bubbles: true }));
				window.setTimeout(() => { backdrop.hidden = true; }, 220);
				if (lastFocused && typeof lastFocused.focus === 'function') {
					lastFocused.focus();
				}
			}
		};

		toggleBtn.addEventListener('click', () => setOpenState(true));
		closeBtn.addEventListener('click', () => setOpenState(false));
		backdrop.addEventListener('click', () => setOpenState(false));

		drawer.addEventListener('click', (event) => {
			const link = event.target.closest('a');
			if (!link || !drawer.contains(link)) return;
			if (link.matches('.hvl-mobile-link, .hvl-mobile-primary-cta, .hvl-mobile-support-link, .hvl-mobile-city-link, .hvl-mobile-live-item')) {
				setOpenState(false);
			}
		});

		document.addEventListener('keydown', (event) => {
			if (event.key !== 'Escape' || !drawer.classList.contains('is-open')) return;
			const paneCit = document.getElementById('hvl-mobile-loc-pane-cities');
			const paneProv = document.getElementById('hvl-mobile-loc-pane-provinces');
			if (paneCit && paneCit.classList.contains('is-active') && paneProv) {
				event.preventDefault();
				paneCit.classList.remove('is-active');
				paneProv.classList.add('is-active');
				return;
			}
				setOpenState(false);
		});
	})();

	(function () {
		const dataEl = document.getElementById('hvl-loc-cities-data');
		let map = {};
		if (dataEl) {
			try {
				map = JSON.parse(dataEl.textContent || '{}');
			} catch (e) {
				map = {};
			}
		}

		const renderCitiesInto = (listEl, emptyText, slug, cityLinkClass) => {
			if (!listEl) return;
			const rows = map[slug] || [];
			listEl.replaceChildren();
			if (!rows.length) {
				const p = document.createElement('p');
				p.className = 'hvl-header-mega-empty';
				p.textContent = emptyText;
				listEl.appendChild(p);
				return;
			}
			const frag = document.createDocumentFragment();
			rows.forEach((row) => {
				if (!row || !row.u) return;
				const a = document.createElement('a');
				a.href = row.u;
				a.textContent = row.n || '';
				if (cityLinkClass) a.classList.add(cityLinkClass);
				frag.appendChild(a);
			});
			listEl.appendChild(frag);
		};

		const panel = document.getElementById('hvl-header-mega-loc-panel');
		if (panel) {
			const listEl = panel.querySelector('.hvl-header-mega-cities-list');
			const provLinks = panel.querySelectorAll('.hvl-header-mega-prov-link');
			const emptyText = panel.getAttribute('data-empty-msg') || '';

			const setActiveDesk = (activeLink) => {
				provLinks.forEach((a) => a.classList.remove('is-active'));
				activeLink.classList.add('is-active');
			};

			provLinks.forEach((link) => {
				const slug = link.getAttribute('data-province-slug');
				if (!slug) return;
				const activate = () => {
					renderCitiesInto(listEl, emptyText, slug, '');
					setActiveDesk(link);
				};
				link.addEventListener('mouseenter', activate);
				link.addEventListener('focus', activate);
			});
		}

		const drawerEl = document.getElementById('hvl-mobile-drawer');
		const mob = document.getElementById('hvl-mobile-loc-block');
		if (mob) {
			const locToggle = document.getElementById('hvl-mobile-loc-toggle');
			const locStack = document.getElementById('hvl-mobile-loc-stack');
			const paneProv = document.getElementById('hvl-mobile-loc-pane-provinces');
			const paneCit = document.getElementById('hvl-mobile-loc-pane-cities');
			const listEl = document.getElementById('hvl-mobile-loc-cities-list');
			const cityHeading = document.getElementById('hvl-mobile-loc-city-heading');
			const backBtn = document.getElementById('hvl-mobile-loc-back');
			const provBtns = mob.querySelectorAll('.hvl-mobile-prov-btn');
			const emptyText = mob.getAttribute('data-empty-msg') || '';
			const citiesWord = mob.getAttribute('data-cities-heading') || '';

			const setLocAccordionOpen = (open) => {
				if (locToggle) locToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				if (locStack) locStack.hidden = !open;
				mob.classList.toggle('is-open', !!open);
			};

			const showProvincesPane = () => {
				if (paneProv) paneProv.classList.add('is-active');
				if (paneCit) paneCit.classList.remove('is-active');
			};

			const collapseLocAccordion = () => {
				setLocAccordionOpen(false);
				showProvincesPane();
			};

			if (locToggle && locStack) {
				locToggle.addEventListener('click', () => {
					if (!mob.classList.contains('is-open')) {
						setLocAccordionOpen(true);
						return;
					}
					collapseLocAccordion();
				});
			}

			const openCitiesForProvince = (slug, provinceLabel) => {
				renderCitiesInto(listEl, emptyText, slug, 'hvl-mobile-city-link');
				if (cityHeading) {
					cityHeading.textContent =
						provinceLabel && citiesWord
							? provinceLabel + ' — ' + citiesWord
							: provinceLabel || '';
				}
				if (paneProv) paneProv.classList.remove('is-active');
				if (paneCit) paneCit.classList.add('is-active');
			};

			provBtns.forEach((btn) => {
				const slug = btn.getAttribute('data-province-slug');
				if (!slug) return;
				btn.addEventListener('click', () => {
					const label = btn.getAttribute('data-province-label') || btn.textContent.trim();
					openCitiesForProvince(slug, label);
				});
			});

			if (backBtn) {
				backBtn.addEventListener('click', showProvincesPane);
			}

			if (drawerEl) {
				drawerEl.addEventListener('hvlMobileDrawerClosed', collapseLocAccordion);
			}
		}

		const gradeMob = document.getElementById('hvl-mobile-grade-block');
		if (gradeMob) {
			const gradeToggle = document.getElementById('hvl-mobile-grade-toggle');
			const gradeStack = document.getElementById('hvl-mobile-grade-stack');

			const setGradeAccordionOpen = (open) => {
				if (gradeToggle) gradeToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				if (gradeStack) gradeStack.hidden = !open;
				gradeMob.classList.toggle('is-open', !!open);
			};

			const collapseGradeAccordion = () => setGradeAccordionOpen(false);

			if (gradeToggle && gradeStack) {
				gradeToggle.addEventListener('click', () => {
					setGradeAccordionOpen(!gradeMob.classList.contains('is-open'));
				});
			}

			if (drawerEl) {
				drawerEl.addEventListener('hvlMobileDrawerClosed', collapseGradeAccordion);
			}
		}
	})();

	(function () {
		document.querySelectorAll('.hvl-header-menu-item--mega').forEach((item) => {
			let closeT = null;
			item.addEventListener('mouseenter', () => {
				if (closeT) window.clearTimeout(closeT);
				item.classList.add('is-mega-open');
			});
			item.addEventListener('mouseleave', () => {
				closeT = window.setTimeout(() => item.classList.remove('is-mega-open'), 220);
			});
		});
	})();

	(function () {
		const sheet = document.getElementById('hvl-mobile-search-sheet');
		const backdrop = document.getElementById('hvl-mobile-search-backdrop');
		const closeBtn = document.getElementById('hvl-mobile-search-sheet-close');
		const dockSearchBtn = document.getElementById('hvl-mobile-dock-search');
		const dockMenuBtn = document.getElementById('hvl-mobile-dock-menu');
		const menuToggle = document.getElementById('hvl-mobile-menu-toggle');

		const setSearchSheetOpen = (open) => {
			if (!sheet) return;
			if (open) {
				sheet.hidden = false;
				window.requestAnimationFrame(() => {
					sheet.classList.add('is-visible');
				});
				sheet.setAttribute('aria-hidden', 'false');
				document.body.classList.add('hvl-mobile-search-sheet-open');
				if (dockSearchBtn) dockSearchBtn.setAttribute('aria-expanded', 'true');
				window.setTimeout(() => {
					const qInput = document.getElementById('hvl-mobile-search-q');
					if (qInput) qInput.focus();
				}, 60);
			} else {
				sheet.classList.remove('is-visible');
				sheet.setAttribute('aria-hidden', 'true');
				document.body.classList.remove('hvl-mobile-search-sheet-open');
				if (dockSearchBtn) dockSearchBtn.setAttribute('aria-expanded', 'false');
				window.setTimeout(() => {
					sheet.hidden = true;
				}, 320);
			}
		};

		dockSearchBtn?.addEventListener('click', () => setSearchSheetOpen(true));
		closeBtn?.addEventListener('click', () => setSearchSheetOpen(false));
		backdrop?.addEventListener('click', () => setSearchSheetOpen(false));
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && sheet && !sheet.hidden && sheet.classList.contains('is-visible')) {
				setSearchSheetOpen(false);
			}
		});

		dockMenuBtn?.addEventListener('click', () => menuToggle?.click());

		const HVL_LAWYERS_API = <?php echo wp_json_encode( esc_url_raw( $hvl_lawyers_rest_url ) ); ?>;
		const HVL_ARCHIVE_URL = <?php echo wp_json_encode( esc_url_raw( $hvl_lawyer_archive_url ) ); ?>;
		const HVL_PLACEHOLDER_IMG = <?php echo wp_json_encode( esc_url_raw( $hvl_search_img_fallback ) ); ?>;

		function hvlEsc(s) {
			return (s || '')
				.replaceAll('&', '&amp;')
				.replaceAll('<', '&lt;')
				.replaceAll('>', '&gt;')
				.replaceAll('"', '&quot;')
				.replaceAll("'", '&#039;');
		}

		function hvlNorm(s) {
			return (s || '')
				.toString()
				.toLowerCase()
				.replaceAll('ي', 'ی')
				.replaceAll('ك', 'ک')
				.replaceAll('ة', 'ه')
				.replace(/\s+/g, ' ')
				.trim();
		}

		function hvlImgOnerr() {
			return ` onerror="this.onerror=null;this.src=${JSON.stringify(HVL_PLACEHOLDER_IMG)};this.classList.add('hvl-img-fallback');"`;
		}

		function hvlScoreItem(item, query) {
			const q = hvlNorm(query);
			if (!q) return 0;
			const name = hvlNorm(item?.name || '');
			const spec = hvlNorm(
				(Array.isArray(item?.specialties) && item.specialties.length ? item.specialties.join(' ') : '') || item?.specialty || ''
			);
			const loc = hvlNorm((item?.location_line || item?.city || '').toString());
			const prov = hvlNorm((item?.province || '').toString());
			const grade = hvlNorm((item?.lawyer_grade || '').toString());
			let sc = 0;
			if (name === q) sc += 1400;
			else if (name.startsWith(q)) sc += 900;
			else if (name.includes(q)) sc += 560;
			if (spec === q) sc += 620;
			else if (spec.includes(q)) sc += 340;
			if (loc === q) sc += 520;
			else if (loc.includes(q)) sc += 280;
			if (prov === q) sc += 460;
			else if (prov.includes(q)) sc += 250;
			if (grade.includes(q)) sc += 200;
			return sc;
		}

		function hvlAttachLiveSearch(cfg) {
			const {
				input,
				liveEl,
				wrapEl,
				seeAllEl,
				form,
				hintEl,
				onResultNavigate,
			} = cfg;
			if (!input || !liveEl || !wrapEl || !form) return;

			let debounceT = null;
			let abortC = null;
			let reqId = 0;
			let hlIdx = -1;
			const cache = new Map();
			const DEBOUNCE_MS = 85;
			const FETCH_N = 14;
			const SHOW_N = 8;

			const clearHl = () => {
				hlIdx = -1;
				liveEl.querySelectorAll('.hvl-mobile-live-item').forEach((el) => el.classList.remove('is-highlighted'));
			};

			const setHl = (idx) => {
				const items = liveEl.querySelectorAll('.hvl-mobile-live-item');
				clearHl();
				if (idx < 0 || idx >= items.length) return;
				hlIdx = idx;
				items[idx].classList.add('is-highlighted');
				items[idx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
			};

			const hideAll = () => {
				wrapEl.hidden = true;
				if (seeAllEl) seeAllEl.hidden = true;
				if (hintEl) {
					hintEl.hidden = true;
					hintEl.textContent = '';
				}
				liveEl.innerHTML = '';
				clearHl();
			};

			const renderHint = (text) => {
				if (!hintEl) return;
				hintEl.textContent = text;
				hintEl.hidden = !text;
			};

			const renderSkeleton = () => {
				wrapEl.hidden = false;
				liveEl.innerHTML = [1, 2, 3]
					.map(
						() =>
							'<div class="hvl-mobile-live-skel" aria-hidden="true"><span class="hvl-mobile-live-skel-av"></span><span class="hvl-mobile-live-skel-tx"><i></i><i></i></span></div>'
					)
					.join('');
				if (seeAllEl) seeAllEl.hidden = true;
			};

			const runFetch = async () => {
				const qRaw = input.value.trim();
				if (qRaw.length < 2) {
					if (abortC) abortC.abort();
					hideAll();
					if (qRaw.length === 1) renderHint('حداقل ۲ نویسه برای جستجو وارد کنید.');
					else renderHint('');
					return;
				}
				renderHint('');

				const params = new URLSearchParams({ q: qRaw, per_page: String(FETCH_N), page: '1' });
				const cacheKey = params.toString();
				if (cache.has(cacheKey)) {
					renderResults(cache.get(cacheKey), qRaw);
					return;
				}

				if (abortC) abortC.abort();
				abortC = new AbortController();
				const myId = ++reqId;
				renderSkeleton();

				try {
					const res = await fetch(`${HVL_LAWYERS_API}?${params.toString()}`, {
						credentials: 'same-origin',
						signal: abortC.signal,
					});
					if (!res.ok) throw new Error('req');
					const data = await res.json();
					if (myId !== reqId) return;
					const raw = Array.isArray(data.items) ? data.items : [];
					const ranked = raw
						.map((item) => ({ item, score: hvlScoreItem(item, qRaw) }))
						.sort((a, b) => b.score - a.score)
						.map((x) => x.item)
						.slice(0, SHOW_N);
					cache.set(cacheKey, { items: ranked, total: Number(data.total || ranked.length) });
					if (cache.size > 32) cache.delete(cache.keys().next().value);
					renderResults(cache.get(cacheKey), qRaw);
				} catch (e) {
					if (e && e.name === 'AbortError') return;
					if (myId !== reqId) return;
					wrapEl.hidden = false;
					liveEl.innerHTML = '<div class="hvl-mobile-live-empty hvl-mobile-live-err">موقتاً دسترسی برقرار نشد. دوباره تلاش کنید.</div>';
					if (seeAllEl) seeAllEl.hidden = true;
				}
			};

			const renderResults = (payload, qRaw) => {
				const items = payload.items || [];
				const total = payload.total || items.length;
				wrapEl.hidden = false;
				if (!items.length) {
					liveEl.innerHTML = '<div class="hvl-mobile-live-empty">موردی پیدا نشد.</div>';
					if (seeAllEl) seeAllEl.hidden = true;
					clearHl();
					return;
				}

				const rows = items
					.map((item) => {
						const spec0 = (Array.isArray(item.specialties) && item.specialties.length
							? item.specialties[0]
							: '') || (item.specialty || '');
						const loc = (item.location_line || item.city || '').toString().trim();
						const bits = [spec0, loc].filter((x) => String(x).trim());
						const meta = bits.join(' · ');
						const metaHtml = meta ? `<div class="hvl-mobile-live-meta">${hvlEsc(meta)}</div>` : '';
						return `
							<a role="option" class="hvl-mobile-live-item" href="${hvlEsc(item.permalink || '#')}" data-permalink="${hvlEsc(item.permalink || '')}">
								<img class="hvl-mobile-live-avatar" src="${hvlEsc(item.image || HVL_PLACEHOLDER_IMG)}" alt="${hvlEsc(item.name || '')}"${hvlImgOnerr()} />
								<div class="hvl-mobile-live-body">
									<div class="hvl-mobile-live-name">${hvlEsc(item.name || '')}</div>
									${metaHtml}
								</div>
							</a>`;
					})
					.join('');
				liveEl.innerHTML = rows;
				if (seeAllEl) {
					seeAllEl.href = `${HVL_ARCHIVE_URL}?${new URLSearchParams({ q: qRaw }).toString()}`;
					seeAllEl.hidden = false;
					seeAllEl.textContent = total > items.length ? `مشاهدهٔ همه (${total})` : 'مشاهده در آرشیو';
				}
				clearHl();
			};

			input.addEventListener('input', () => {
				if (debounceT) clearTimeout(debounceT);
				debounceT = window.setTimeout(runFetch, DEBOUNCE_MS);
			});

			input.addEventListener('keydown', (ev) => {
				const items = liveEl.querySelectorAll('.hvl-mobile-live-item');
				if (ev.key === 'ArrowDown') {
					if (!items.length) return;
					ev.preventDefault();
					setHl(hlIdx < items.length - 1 ? hlIdx + 1 : 0);
				} else if (ev.key === 'ArrowUp') {
					if (!items.length) return;
					ev.preventDefault();
					setHl(hlIdx > 0 ? hlIdx - 1 : items.length - 1);
				} else if (ev.key === 'Enter') {
					if (hlIdx >= 0 && items[hlIdx]) {
						ev.preventDefault();
						window.location.href = items[hlIdx].getAttribute('href') || '#';
					}
				}
			});

			liveEl.addEventListener('mousemove', () => clearHl());

			form.addEventListener('submit', (ev) => {
				const q = input.value.trim();
				if (q.length > 0 && q.length < 2) {
					ev.preventDefault();
					renderHint('حداقل ۲ نویسه برای جستجو وارد کنید.');
					return;
				}
				if (q.length >= 2) {
					ev.preventDefault();
					window.location.href = `${HVL_ARCHIVE_URL}?${new URLSearchParams({ q }).toString()}`;
				}
			});

			liveEl.addEventListener('click', (ev) => {
				const a = ev.target.closest('.hvl-mobile-live-item');
				if (!a) return;
				if (typeof onResultNavigate === 'function') onResultNavigate();
			});
		}

		hvlAttachLiveSearch({
			input: document.getElementById('hvl-mobile-search-q'),
			liveEl: document.getElementById('hvl-mobile-search-live'),
			wrapEl: document.getElementById('hvl-mobile-search-live-wrap'),
			seeAllEl: document.getElementById('hvl-mobile-search-see-all'),
			form: document.getElementById('hvl-mobile-search-form'),
			hintEl: document.getElementById('hvl-mobile-search-hint'),
			onResultNavigate: () => setSearchSheetOpen(false),
		});

		hvlAttachLiveSearch({
			input: document.getElementById('hvl-mobile-drawer-search-q'),
			liveEl: document.getElementById('hvl-mobile-drawer-live'),
			wrapEl: document.getElementById('hvl-mobile-drawer-live-wrap'),
			seeAllEl: document.getElementById('hvl-mobile-drawer-see-all'),
			form: document.getElementById('hvl-mobile-drawer-search-form'),
			hintEl: document.getElementById('hvl-mobile-drawer-search-hint'),
		});
	})();
</script>
