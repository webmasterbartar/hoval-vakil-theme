<?php
/**
 * Single template for Lawyer profile.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! have_posts() ) {
	status_header( 404 );
	exit;
}

the_post();

$post_id   = get_the_ID();
$image_url = get_the_post_thumbnail_url( $post_id, 'large' );

if ( ! $image_url ) {
	$image_url = 'https://via.placeholder.com/600x600.png?text=%D9%88%DA%A9%DB%8C%D9%84';
}

$specialties = get_the_terms( $post_id, 'hvl_specialty' );
$cities      = get_the_terms( $post_id, 'hvl_city' );

$experience = (string) get_post_meta( $post_id, 'hvl_experience', true );
$price_from = (string) get_post_meta( $post_id, 'hvl_price_from', true );
$rating     = (string) get_post_meta( $post_id, 'hvl_rating', true );
$reviews    = (string) get_post_meta( $post_id, 'hvl_reviews_count', true );
$license    = (string) get_post_meta( $post_id, 'hvl_license', true );
$education  = (string) get_post_meta( $post_id, 'hvl_education', true );
$records    = (string) get_post_meta( $post_id, 'hvl_records', true );
$services   = (string) get_post_meta( $post_id, 'hvl_services', true );
$office_address = (string) get_post_meta( $post_id, 'hvl_office_address', true );
$office_map_image = (string) get_post_meta( $post_id, 'hvl_office_map_image', true );
$office_phone = (string) get_post_meta( $post_id, 'hvl_office_phone', true );
$office_working_hours = (string) get_post_meta( $post_id, 'hvl_office_working_hours', true );

if ( '' === $experience ) {
	$experience = '۵ سال تجربه';
}

if ( '' === $price_from ) {
	$price_from = '۳۵۰,۰۰۰ تومان';
}

if ( '' === $rating ) {
	$rating = '4.7';
}

if ( '' === $reviews ) {
	$reviews = '20';
}

if ( '' === $license ) {
	$license = 'شماره پروانه: ' . str_pad( (string) $post_id, 6, '0', STR_PAD_LEFT );
}
if ( '' === $education ) {
	$education = 'کارشناسی ارشد حقوق';
}
$city_name = ( is_array( $cities ) && ! empty( $cities ) ) ? $cities[0]->name : 'تهران';
if ( '' === $office_address ) {
	$office_address = $city_name . '، دفتر مرکزی وکالت';
}
if ( '' === $office_map_image ) {
	$office_map_image = 'https://via.placeholder.com/900x360.png?text=%D9%86%D9%82%D8%B4%D9%87+%D8%AF%D9%81%D8%AA%D8%B1';
}
if ( '' === $office_phone ) {
	$office_phone = '۰۲۱-۱۲۳۴۵۶۷۸';
}
if ( '' === $office_working_hours ) {
	$office_working_hours = 'شنبه تا چهارشنبه ۹:۰۰ تا ۱۸:۰۰';
}

$primary_specialty = ( is_array( $specialties ) && ! empty( $specialties ) ) ? $specialties[0]->name : 'مشاوره حقوقی';
$secondary_specs   = [];
if ( is_array( $specialties ) ) {
	foreach ( $specialties as $idx => $term ) {
		if ( 0 === $idx ) {
			continue;
		}
		$secondary_specs[] = $term->name;
	}
}

if ( empty( $secondary_specs ) ) {
	$secondary_specs = [ 'قراردادها', 'پایه یک دادگستری' ];
}

$bio = get_the_content();
if ( '' === trim( wp_strip_all_tags( $bio ) ) ) {
	$bio = 'این وکیل دارای سابقه موفق در پرونده‌های حقوقی و ارائه مشاوره تخصصی می‌باشد.';
}

$reservation_url = add_query_arg(
	[
		'lawyer_id' => $post_id,
		'name'      => get_the_title(),
		'specialty' => $primary_specialty,
		'city'      => $city_name,
		'image'     => $image_url,
		'price'     => $price_from,
		'service'   => 'مشاوره حضوری',
	],
	home_url( '/rezerv' )
);

$records_items = array_values(
	array_filter(
		array_map( 'trim', explode( "\n", str_replace( [ "\r\n", "\r" ], "\n", $records ) ) )
	)
);
if ( empty( $records_items ) ) {
	$records_items = [
		'وکیل پایه یک دادگستری',
		'دارای سابقه موفق در پرونده‌های حقوقی',
		'ارائه مشاوره حضوری و آنلاین',
	];
}

$services_items = array_values(
	array_filter(
		array_map( 'trim', explode( ',', $services ) )
	)
);
if ( empty( $services_items ) ) {
	$services_items = is_array( $specialties ) && ! empty( $specialties ) ? wp_list_pluck( $specialties, 'name' ) : [ 'مشاوره حقوقی عمومی' ];
}

$reviews_query = new WP_Query(
	[
		'post_type'              => 'hvl_review',
		'post_status'            => 'publish',
		'posts_per_page'         => 6,
		'no_found_rows'          => true,
		'update_post_meta_cache' => true,
		'meta_query'             => [
			[
				'key'   => 'hvl_review_lawyer_id',
				'value' => $post_id,
			],
		],
	]
);

$related_args = [
	'post_type'              => 'hvl_lawyer',
	'post_status'            => 'publish',
	'posts_per_page'         => 4,
	'post__not_in'           => [ $post_id ],
	'no_found_rows'          => true,
	'update_post_meta_cache' => false,
];

if ( is_array( $specialties ) && ! empty( $specialties ) ) {
	$related_args['tax_query'] = [
		[
			'taxonomy' => 'hvl_specialty',
			'field'    => 'term_id',
			'terms'    => wp_list_pluck( $specialties, 'term_id' ),
		],
	];
}

$related_query = new WP_Query( $related_args );
get_header();
?>
	<main id="content" class="profile-content">
		<section class="profile-hero">
			<div class="profile-main-info">
				<div class="profile-image-container">
					<img id="profile-image" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
					<div class="verified-badge">
						<span class="material-symbols-outlined" style="font-size:14px;font-variation-settings:'FILL' 1;">check</span>
					</div>
				</div>

				<div class="profile-text-info">
					<div class="profile-title-row">
						<h1 class="profile-name"><?php the_title(); ?></h1>
						<span class="badge-premium">
							<span class="material-symbols-outlined" style="font-size:16px;font-variation-settings:'FILL' 1;">workspace_premium</span>
							وکیل تایید شده
						</span>
					</div>

					<div class="profile-tags">
						<span class="tag-item"><?php echo esc_html( $primary_specialty ); ?></span>
						<span class="tag-item"><?php echo esc_html( $secondary_specs[0] ); ?></span>
						<span class="tag-item primary"><?php echo esc_html( $secondary_specs[1] ?? 'پایه یک دادگستری' ); ?></span>
					</div>

					<div class="profile-stats-mini">
						<div class="stat-mini-item">
							<span class="font-bold"><?php echo esc_html( $rating ); ?></span>
							<span class="material-symbols-outlined" style="color:#f59e0b;font-variation-settings:'FILL' 1;">star</span>
							<span class="text-xs text-on-surface-variant">(<?php echo esc_html( $reviews ); ?> نظر)</span>
						</div>
					</div>

					<div class="profile-details-grid">
						<div class="detail-item">
							<span class="material-symbols-outlined">location_on</span>
							<span><?php echo esc_html( $city_name ); ?></span>
						</div>
						<div class="detail-item">
							<span class="material-symbols-outlined">work_history</span>
							<span><?php echo esc_html( $experience ); ?></span>
						</div>
						<div class="detail-item">
							<span class="material-symbols-outlined">school</span>
							<span><?php echo esc_html( $education ); ?></span>
						</div>
						<div class="detail-item">
							<span class="material-symbols-outlined">description</span>
							<span><?php echo esc_html( $license ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<div class="profile-action-sidebar">
				<div class="action-card">
					<div class="pricing-info">
						<p class="price-label">حق‌الوکاله از</p>
						<p class="price-value"><?php echo esc_html( $price_from ); ?></p>
					</div>
					<div class="action-buttons">
						<a href="<?php echo esc_url( $reservation_url ); ?>" class="btn-primary-sm" style="display:block;width:100%;padding:.875rem;text-align:center;">رزرو نوبت حضوری</a>
					</div>
				</div>
			</div>
		</section>

		<div class="tab-nav-wrapper">
			<ul class="tab-nav" id="profile-tabs">
				<li class="active" data-tab="about">درباره وکیل</li>
				<li data-tab="specs">تخصص‌ها</li>
				<li data-tab="records">سوابق</li>
				<li data-tab="reviews">نظرات</li>
				<li data-tab="calendar">رزرو نوبت</li>
			</ul>
		</div>

		<div class="profile-grid-layout">
			<div class="profile-main-content">
				<div id="about" class="tab-content active">
					<section class="content-block">
						<h2 class="block-title"><span class="material-symbols-outlined">person</span>بیوگرافی</h2>
						<div class="text-on-surface-variant" style="line-height:1.9;">
							<?php echo wp_kses_post( wpautop( $bio ) ); ?>
						</div>
					</section>
				</div>

				<div id="specs" class="tab-content">
					<section class="content-block">
						<h2 class="block-title">تخصص‌های وکیل</h2>
						<div class="service-grid">
							<?php foreach ( $services_items as $service_name ) : ?>
								<div class="service-card"><?php echo esc_html( $service_name ); ?></div>
							<?php endforeach; ?>
						</div>
					</section>
				</div>

				<div id="records" class="tab-content">
					<section class="content-block">
						<h2 class="block-title">سوابق تحصیلی و اجرایی</h2>
						<ul style="list-style:disc;padding-right:1.5rem;color:var(--on-surface-variant);">
							<?php foreach ( $records_items as $record_item ) : ?>
								<li style="margin-bottom:1rem;"><?php echo esc_html( $record_item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				</div>

				<div id="reviews" class="tab-content">
					<section class="content-block">
						<h2 class="block-title">
							<span class="material-symbols-outlined">reviews</span>
							نظرات موکلین
						</h2>
						<?php if ( $reviews_query->have_posts() ) : ?>
							<?php while ( $reviews_query->have_posts() ) : ?>
								<?php
								$reviews_query->the_post();
								$review_rating = (string) get_post_meta( get_the_ID(), 'hvl_review_rating', true );
								?>
								<div class="review-card">
									<div class="review-header">
										<div class="reviewer-info">
											<div class="reviewer-avatar">ن</div>
											<div class="reviewer-text-info">
												<p class="font-bold"><?php the_title(); ?></p>
											</div>
										</div>
										<div class="review-rating">
											<span class="material-symbols-outlined filled">star</span>
											<span class="text-xs"><?php echo esc_html( '' !== $review_rating ? $review_rating : '5' ); ?>/5</span>
										</div>
									</div>
									<p class="review-text"><?php echo esc_html( wp_strip_all_tags( get_the_content() ) ); ?></p>
								</div>
							<?php endwhile; ?>
							<?php wp_reset_postdata(); ?>
						<?php else : ?>
							<p class="text-on-surface-variant">هنوز نظری ثبت نشده است.</p>
						<?php endif; ?>
					</section>
				</div>

				<div id="calendar" class="tab-content">
					<section class="content-block">
						<h2 class="block-title">
							<span class="material-symbols-outlined">event_available</span>
							رزرو نوبت مشاوره
						</h2>
						<div class="calendar-wrapper">
							<div class="calendar-header">
								<p class="text-sm font-bold mb-4">۱. انتخاب روز:</p>
								<div class="date-selector">
									<div class="day-slot active">
										<span class="day-name">امروز</span>
										<span class="day-number">۲۰</span>
										<span class="day-name">فروردین</span>
									</div>
									<div class="day-slot">
										<span class="day-name">فردا</span>
										<span class="day-number">۲۱</span>
										<span class="day-name">فروردین</span>
									</div>
									<div class="day-slot">
										<span class="day-name">پس‌فردا</span>
										<span class="day-number">۲۲</span>
										<span class="day-name">فروردین</span>
									</div>
								</div>
							</div>
							<div class="time-slots-section">
								<p class="time-grid-title">
									<span class="material-symbols-outlined">schedule</span>
									نوبت‌های موجود
								</p>
								<div class="time-grid">
									<div class="time-item selected">۰۹:۰۰</div>
									<div class="time-item">۱۰:۰۰</div>
									<div class="time-item">۱۱:۳۰</div>
									<div class="time-item">۱۶:۰۰</div>
								</div>
							</div>
							<div style="margin-top:2rem;text-align:center;">
								<a id="profile-reservation-next-btn" href="<?php echo esc_url( $reservation_url ); ?>" class="btn-primary-sm" style="display:inline-block;padding:1rem 2.5rem;">
									ادامه و ثبت رزرو
								</a>
							</div>
						</div>
					</section>
				</div>
			</div>
			<div class="profile-sidebar-content">
				<section class="content-block">
					<h2 class="block-title">
						<span class="material-symbols-outlined">apartment</span>
						اطلاعات دفتر
					</h2>
					<div style="background:#f1f5f9;height:160px;border-radius:1rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:center;overflow:hidden;">
						<img src="<?php echo esc_url( $office_map_image ); ?>" alt="نقشه دفتر وکالت" style="width:100%;height:100%;object-fit:cover;opacity:.78;">
					</div>
					<p class="text-sm text-on-surface-variant" style="line-height:1.9;margin-bottom:.75rem;">
						<?php echo esc_html( $office_address ); ?>
					</p>
					<p class="text-xs text-on-surface-variant" style="margin-bottom:.35rem;">
						<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">call</span>
						<?php echo esc_html( $office_phone ); ?>
					</p>
					<p class="text-xs text-on-surface-variant">
						<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">schedule</span>
						<?php echo esc_html( $office_working_hours ); ?>
					</p>
				</section>

				<div style="background:#ffffff;padding:1.25rem;border-radius:1rem;text-align:center;box-shadow:var(--shadow-sm);border:1px solid var(--surface-variant);">
					<span class="material-symbols-outlined" style="color:#f59e0b;font-size:2.5rem;margin-bottom:.5rem;font-variation-settings:'FILL' 1;">military_tech</span>
					<h4 class="font-bold">گواهی اعتبار وکالت</h4>
					<p class="text-xs text-on-surface-variant" style="margin-top:.45rem;line-height:1.7;">
						این وکیل دارای پروانه معتبر و هویت احراز‌شده است.
					</p>
				</div>
			</div>
		</div>

		<?php if ( $related_query->have_posts() ) : ?>
			<section style="margin-top:5rem;padding-bottom:5rem;">
				<div class="section-header">
					<h2 class="profile-name" style="font-size:1.75rem;">وکلای مشابه و پیشنهادی</h2>
				</div>
				<div class="lawyer-grid">
					<?php while ( $related_query->have_posts() ) : ?>
						<?php
						$related_query->the_post();
						$rid      = get_the_ID();
						$rimg     = get_the_post_thumbnail_url( $rid, 'medium' );
						$rspecs   = get_the_terms( $rid, 'hvl_specialty' );
						$rcities  = get_the_terms( $rid, 'hvl_city' );
						$rspec    = is_array( $rspecs ) && ! empty( $rspecs ) ? $rspecs[0]->name : '—';
						$rcity    = is_array( $rcities ) && ! empty( $rcities ) ? $rcities[0]->name : '—';
						?>
						<div class="lawyer-card ghost-border editorial-shadow">
							<div class="lawyer-card-image-wrapper">
								<img class="lawyer-card-image" src="<?php echo esc_url( $rimg ? $rimg : $image_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" />
							</div>
							<div class="lawyer-card-content">
								<h3 class="lawyer-name"><?php the_title(); ?></h3>
								<p class="text-on-surface-variant text-sm mb-1"><?php echo esc_html( $rspec ); ?></p>
								<p class="text-secondary font-bold text-xs mb-6"><?php echo esc_html( $rcity ); ?></p>
								<a href="<?php the_permalink(); ?>" class="btn-primary-full">مشاهده پروفایل</a>
							</div>
						</div>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				</div>
			</section>
		<?php endif; ?>
	</main>

	<script>
		const tabs = document.querySelectorAll('#profile-tabs li');
		const tabContents = document.querySelectorAll('.tab-content');
		tabs.forEach((tab) => {
			tab.addEventListener('click', () => {
				const targetTab = tab.getAttribute('data-tab');
				tabs.forEach((t) => t.classList.remove('active'));
				tab.classList.add('active');
				tabContents.forEach((content) => {
					content.classList.remove('active');
					if (content.id === targetTab) content.classList.add('active');
				});
			});
		});

		// Calendar interactivity on profile page.
		const daySlots = document.querySelectorAll('#calendar .day-slot');
		const timeItems = document.querySelectorAll('#calendar .time-item');
		const reservationNextBtn = document.getElementById('profile-reservation-next-btn');

		function setActiveDay(selectedSlot) {
			daySlots.forEach((slot) => slot.classList.remove('active'));
			selectedSlot.classList.add('active');
		}

		function setActiveTime(selectedTime) {
			timeItems.forEach((item) => item.classList.remove('selected'));
			selectedTime.classList.add('selected');
		}

		function updateReservationLink() {
			if (!reservationNextBtn) return;
			const currentUrl = new URL(reservationNextBtn.getAttribute('href'), window.location.origin);
			const activeDay = document.querySelector('#calendar .day-slot.active');
			const activeTime = document.querySelector('#calendar .time-item.selected');
			if (!activeDay || !activeTime) return;

			const dayParts = Array.from(activeDay.querySelectorAll('span')).map((s) => s.textContent.trim());
			const selectedDate = dayParts.join(' ').trim();
			const selectedTime = activeTime.textContent.trim();

			currentUrl.searchParams.set('date', selectedDate);
			currentUrl.searchParams.set('time', selectedTime);
			reservationNextBtn.setAttribute('href', currentUrl.toString());
		}

		daySlots.forEach((slot) => {
			slot.style.cursor = 'pointer';
			slot.addEventListener('click', () => {
				setActiveDay(slot);
				updateReservationLink();
			});
		});

		timeItems.forEach((item) => {
			item.style.cursor = 'pointer';
			item.addEventListener('click', () => {
				setActiveTime(item);
				updateReservationLink();
			});
		});

		updateReservationLink();
	</script>
<?php get_footer(); ?>

