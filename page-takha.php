<?php
/**
 * Page template for specialties list (takha).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$archive_url          = get_post_type_archive_link( 'hvl_lawyer' );
$specialties          = get_terms(
	[
		'taxonomy'   => 'hvl_specialty',
		'hide_empty' => false,
		'orderby'    => 'count',
		'order'      => 'DESC',
	]
);
$specialties_count    = ! is_wp_error( $specialties ) ? count( $specialties ) : 0;
$lawyers_count        = (int) wp_count_posts( 'hvl_lawyer' )->publish;
$cities_count         = (int) wp_count_terms(
	[
		'taxonomy'   => 'hvl_city',
		'hide_empty' => false,
	]
);
$reservations_count   = (int) wp_count_posts( 'hvl_reservation' )->publish;
get_header();
?>
	<main id="content">
	<header class="takha-hero">
		<div class="container takha-hero-content">
			<div class="takha-breadcrumb"><span>خانه</span><span>←</span><span>تخصص ها</span></div>
			<h1>تخصص های حقوقی</h1>
			<div class="takha-search">
				<span class="material-symbols-outlined">search</span>
				<input id="takha-search-input" type="text" placeholder="جستجو در تخصص ها..." />
			</div>
		</div>
	</header>

	<section class="takha-stats container">
		<div class="takha-stat-card"><strong><?php echo esc_html( number_format_i18n( $specialties_count ) ); ?></strong><span>تخصص حقوقی</span></div>
		<div class="takha-stat-card"><strong><?php echo esc_html( number_format_i18n( $lawyers_count ) ); ?></strong><span>وکیل متخصص</span></div>
		<div class="takha-stat-card"><strong><?php echo esc_html( number_format_i18n( $cities_count ) ); ?></strong><span>شهر پوشش داده شده</span></div>
		<div class="takha-stat-card"><strong><?php echo esc_html( number_format_i18n( $reservations_count ) ); ?></strong><span>رزرو ثبت شده</span></div>
	</section>

	<section class="takha-specialties container">
		<div class="takha-specialties-head">
			<h2>همه تخصص های حقوقی</h2>
		</div>
		<div id="takha-specialties-grid" class="takha-grid">
			<?php if ( ! is_wp_error( $specialties ) && ! empty( $specialties ) ) : ?>
				<?php foreach ( $specialties as $term ) : ?>
					<article class="takha-card" data-title="<?php echo esc_attr( strtolower( $term->name ) ); ?>" data-lawyers="<?php echo esc_attr( (string) (int) $term->count ); ?>">
						<div class="takha-card-icon"><span class="material-symbols-outlined">gavel</span></div>
						<h3><?php echo esc_html( $term->name ); ?></h3>
						<p>خدمات تخصصی در حوزه <?php echo esc_html( $term->name ); ?></p>
						<div class="takha-card-footer">
							<span><?php echo esc_html( number_format_i18n( (int) $term->count ) ); ?> وکیل</span>
							<a href="<?php echo esc_url( add_query_arg( [ 'specialty[]' => $term->slug ], $archive_url ) ); ?>">مشاهده وکلا</a>
						</div>
					</article>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="text-on-surface-variant">هنوز تخصصی ثبت نشده است.</p>
			<?php endif; ?>
		</div>
	</section>

	<script>
		(function() {
			const searchInput = document.getElementById('takha-search-input');
			const cards = Array.from(document.querySelectorAll('#takha-specialties-grid .takha-card'));
			let timer = null;

			function applyFilter() {
				const q = (searchInput?.value || '').trim().toLowerCase().replaceAll('ي', 'ی').replaceAll('ك', 'ک');
				cards.forEach((card) => {
					const title = (card.dataset.title || '').replaceAll('ي', 'ی').replaceAll('ك', 'ک');
					const passSearch = !q || title.includes(q);
					card.style.display = passSearch ? '' : 'none';
				});
			}

			searchInput?.addEventListener('input', () => {
				if (timer) clearTimeout(timer);
				timer = setTimeout(applyFilter, 70);
			});
			searchInput?.addEventListener('keydown', (event) => {
				if (event.key !== 'Enter') return;
				event.preventDefault();
				applyFilter();
			});

		})();
	</script>
	</main>
<?php get_footer(); ?>

