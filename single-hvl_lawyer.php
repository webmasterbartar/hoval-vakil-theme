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

$post_id         = get_the_ID();
$hvl_placeholder = function_exists( 'hovalvakil_theme_lawyer_placeholder_url' )
	? hovalvakil_theme_lawyer_placeholder_url()
	: get_template_directory_uri() . '/assets/images/lawyer-placeholder.svg';
$hvl_img_onerror = function_exists( 'hovalvakil_lawyer_image_onerror_placeholder_attr' )
	? hovalvakil_lawyer_image_onerror_placeholder_attr()
	: '';
$image_url       = function_exists( 'hovalvakil_lawyer_profile_image_url' )
	? hovalvakil_lawyer_profile_image_url( $post_id, 'large' )
	: (string) get_the_post_thumbnail_url( $post_id, 'large' );

if ( ! $image_url ) {
	$image_url = $hvl_placeholder;
}

$specialties = get_the_terms( $post_id, 'hvl_specialty' );
$cities      = get_the_terms( $post_id, 'hvl_city' );
$provinces   = get_the_terms( $post_id, 'hvl_province' );

$experience           = (string) get_post_meta( $post_id, 'hvl_experience', true );
$price_from           = (string) get_post_meta( $post_id, 'hvl_price_from', true );
$rating               = (string) get_post_meta( $post_id, 'hvl_rating', true );
$reviews              = (string) get_post_meta( $post_id, 'hvl_reviews_count', true );
$license              = (string) get_post_meta( $post_id, 'hvl_license', true );
$license_no           = (string) get_post_meta( $post_id, 'hvl_license_no', true );
$license_issued_raw   = (string) get_post_meta( $post_id, 'hvl_license_issued', true );
$license_expires_raw  = (string) get_post_meta( $post_id, 'hvl_license_expires', true );
$license_issued_jalali  = trim( (string) get_post_meta( $post_id, 'hvl_license_issued_jalali', true ) );
$license_expires_jalali = trim( (string) get_post_meta( $post_id, 'hvl_license_expires_jalali', true ) );
$license_file_url     = (string) get_post_meta( $post_id, 'hvl_license_file_url', true );
$lawyer_grade         = (string) get_post_meta( $post_id, 'hvl_lawyer_grade', true );
$mobile               = (string) get_post_meta( $post_id, 'hvl_mobile', true );
$office_mobile        = (string) get_post_meta( $post_id, 'hvl_office_mobile', true );
$education            = (string) get_post_meta( $post_id, 'hvl_education', true );
$records              = (string) get_post_meta( $post_id, 'hvl_records', true );
$services             = (string) get_post_meta( $post_id, 'hvl_services', true );
$office_address       = (string) get_post_meta( $post_id, 'hvl_office_address', true );
$office_map_meta      = trim( (string) get_post_meta( $post_id, 'hvl_office_map_image', true ) );
$office_phone         = (string) get_post_meta( $post_id, 'hvl_office_phone', true );
$office_working_hours = (string) get_post_meta( $post_id, 'hvl_office_working_hours', true );

$license_issued_display = '' !== $license_issued_jalali ? $license_issued_jalali : '';
if ( '' === $license_issued_display && '' !== $license_issued_raw && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $license_issued_raw ) ) {
	$license_issued_display = $license_issued_raw;
}

$license_expires_display = '' !== $license_expires_jalali ? $license_expires_jalali : '';
if ( '' === $license_expires_display && '' !== $license_expires_raw && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $license_expires_raw ) ) {
	$license_expires_display = $license_expires_raw;
}

$province_name = ( is_array( $provinces ) && ! empty( $provinces ) ) ? (string) $provinces[0]->name : '';
$city_name     = ( is_array( $cities ) && ! empty( $cities ) ) ? (string) $cities[0]->name : '';

if ( '' === $license_no && '' === $license ) {
	$license = '';
} elseif ( '' !== $license_no ) {
	$license = $license_no;
}

$office_map_display = '';
if ( '' !== $office_map_meta ) {
	$norm_meta = untrailingslashit( $office_map_meta );
	$norm_ph   = untrailingslashit( $hvl_placeholder );
	if ( $norm_meta !== $norm_ph ) {
		$office_map_display = $office_map_meta;
	}
}

$primary_specialty = ( is_array( $specialties ) && ! empty( $specialties ) ) ? $specialties[0]->name : '';
$secondary_specs   = [];
if ( is_array( $specialties ) ) {
	foreach ( $specialties as $idx => $term ) {
		if ( 0 === $idx ) {
			continue;
		}
		$secondary_specs[] = $term->name;
	}
}

$lawyer_grade_display = '' !== $lawyer_grade ? $lawyer_grade : '';

$records_items = array_values(
	array_filter(
		array_map( 'trim', explode( "\n", str_replace( [ "\r\n", "\r" ], "\n", $records ) ) )
	)
);

$services_items = [];
if ( '' !== trim( $services ) ) {
	$services_items = array_values(
		array_filter(
			array_map( 'trim', preg_split( '/[,،]+/u', $services ) ),
			static function ( $s ) {
				return '' !== $s;
			}
		)
	);
}

$reservation_args = [
	'lawyer_id' => (string) $post_id,
	'name'      => get_the_title(),
];
if ( '' !== $primary_specialty ) {
	$reservation_args['specialty'] = $primary_specialty;
}
if ( '' !== $city_name ) {
	$reservation_args['city'] = $city_name;
} elseif ( '' !== $province_name ) {
	$reservation_args['city'] = $province_name;
}
if ( '' !== $image_url && $image_url !== $hvl_placeholder ) {
	$reservation_args['image'] = $image_url;
}
if ( '' !== $price_from ) {
	$reservation_args['price'] = $price_from;
}
$reservation_args = array_filter(
	$reservation_args,
	static function ( $v ) {
		return null !== $v && '' !== $v;
	}
);
$reservation_url = add_query_arg( $reservation_args, home_url( '/rezerv' ) );

$has_license_dl = ( '' !== $lawyer_grade )
	|| ( '' !== $license_no || '' !== $license )
	|| ( '' !== $license_issued_display )
	|| ( '' !== $license_expires_display )
	|| ( '' !== $license_file_url );

$has_phones = ( '' !== $mobile ) || ( '' !== $office_mobile ) || ( '' !== $office_phone );

$tab_specs           = ! empty( $services_items );
$tab_records         = ! empty( $records_items );
$tab_license_contact = $has_license_dl || $has_phones;

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
$tab_reviews = $reviews_query->post_count > 0;

$related_args = [
	'post_type'              => 'hvl_lawyer',
	'post_status'            => 'publish',
	'posts_per_page'         => 4,
	'post__not_in'           => [ $post_id ],
	'no_found_rows'          => true,
	'update_post_meta_cache' => true,
];

// وکلای مرتبط: اولویت با همان شهر(های) وکیل جاری؛ اگر شهری نبود، پشتیبان = همان تخصص.
if ( is_array( $cities ) && ! empty( $cities ) ) {
	$related_args['tax_query'] = [
		[
			'taxonomy' => 'hvl_city',
			'field'    => 'term_id',
			'terms'    => wp_list_pluck( $cities, 'term_id' ),
		],
	];
} elseif ( is_array( $specialties ) && ! empty( $specialties ) ) {
	$related_args['tax_query'] = [
		[
			'taxonomy' => 'hvl_specialty',
			'field'    => 'term_id',
			'terms'    => wp_list_pluck( $specialties, 'term_id' ),
		],
	];
}

$related_query = new WP_Query( $related_args );

$profile_tabs = [];
if ( $tab_specs ) {
	$profile_tabs['specs'] = 'خدمات';
}
if ( $tab_records ) {
	$profile_tabs['records'] = 'سوابق';
}
if ( $tab_license_contact ) {
	$profile_tabs['license-contact'] = 'پروانه و تماس';
}
if ( $tab_reviews ) {
	$profile_tabs['reviews'] = 'نظرات';
}
$profile_tabs['calendar'] = 'رزرو نوبت';

$first_tab_id = array_key_first( $profile_tabs );

$has_office_sidebar = ( '' !== $office_map_display )
	|| ( '' !== $office_address )
	|| ( '' !== $mobile )
	|| ( '' !== $office_mobile )
	|| ( '' !== $office_phone )
	|| ( '' !== $office_working_hours );

$show_verified_strip = ( '' !== $license_no ) || ( '' !== $lawyer_grade );

get_header();
?>
	<main id="content" class="profile-content">
		<section class="profile-hero">
			<div class="profile-main-info">
				<div class="profile-image-container">
					<img id="profile-image" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>"<?php echo $hvl_img_onerror; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php if ( $show_verified_strip ) : ?>
					<div class="verified-badge" title="<?php echo esc_attr__( 'اطلاعات پروانه در پروفایل', 'hello-elementor' ); ?>">
						<span class="material-symbols-outlined" style="font-size:14px;font-variation-settings:'FILL' 1;">check</span>
					</div>
					<?php endif; ?>
				</div>

				<div class="profile-text-info">
					<div class="profile-title-row">
						<h1 class="profile-name"><?php the_title(); ?></h1>
						<?php if ( $show_verified_strip ) : ?>
						<span class="badge-premium">
							<span class="material-symbols-outlined" style="font-size:16px;font-variation-settings:'FILL' 1;">workspace_premium</span>
							<?php echo esc_html__( 'پروانهٔ ثبت‌شده', 'hello-elementor' ); ?>
						</span>
						<?php endif; ?>
					</div>

					<?php if ( $primary_specialty || ! empty( $secondary_specs ) || '' !== $lawyer_grade_display ) : ?>
						<div class="profile-tags">
							<?php if ( $primary_specialty ) : ?>
								<span class="tag-item"><?php echo esc_html( $primary_specialty ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $secondary_specs ) ) : ?>
								<span class="tag-item"><?php echo esc_html( $secondary_specs[0] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $lawyer_grade_display ) : ?>
								<span class="tag-item primary"><?php echo esc_html( $lawyer_grade_display ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $rating ) : ?>
						<div class="profile-stats-mini">
							<div class="stat-mini-item">
								<span class="font-bold"><?php echo esc_html( $rating ); ?></span>
								<span class="material-symbols-outlined" style="color:#f59e0b;font-variation-settings:'FILL' 1;">star</span>
								<?php if ( '' !== $reviews ) : ?>
									<span class="text-xs text-on-surface-variant">(<?php echo esc_html( $reviews ); ?>)</span>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<div class="profile-details-grid">
						<?php
						$loc_line = implode( '، ', array_filter( [ $province_name, $city_name ] ) );
						if ( '' !== $loc_line ) :
							?>
						<div class="detail-item">
							<span class="material-symbols-outlined">location_on</span>
							<span><?php echo esc_html( $loc_line ); ?></span>
						</div>
						<?php endif; ?>
						<?php if ( '' !== $experience ) : ?>
						<div class="detail-item">
							<span class="material-symbols-outlined">work_history</span>
							<span><?php echo esc_html( $experience ); ?></span>
						</div>
						<?php endif; ?>
						<?php if ( '' !== $education ) : ?>
						<div class="detail-item">
							<span class="material-symbols-outlined">school</span>
							<span><?php echo esc_html( $education ); ?></span>
						</div>
						<?php endif; ?>
						<?php if ( '' !== $license ) : ?>
						<div class="detail-item">
							<span class="material-symbols-outlined">description</span>
							<span><?php echo esc_html__( 'شماره پروانه: ', 'hello-elementor' ) . esc_html( $license ); ?></span>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="profile-action-sidebar">
				<div class="action-card">
					<?php if ( '' !== $price_from ) : ?>
					<div class="pricing-info">
						<p class="price-label"><?php echo esc_html__( 'حق‌الوکاله از', 'hello-elementor' ); ?></p>
						<p class="price-value"><?php echo esc_html( $price_from ); ?></p>
					</div>
					<?php endif; ?>
					<div class="action-buttons">
						<a href="<?php echo esc_url( $reservation_url ); ?>" class="btn-primary-sm" style="display:block;width:100%;padding:.875rem;text-align:center;"><?php echo esc_html__( 'رزرو نوبت حضوری', 'hello-elementor' ); ?></a>
					</div>
				</div>
			</div>
		</section>

		<div class="tab-nav-wrapper">
			<ul class="tab-nav" id="profile-tabs">
				<?php
				$t_i = 0;
				foreach ( $profile_tabs as $tid => $tlabel ) :
					$is_active = ( $tid === $first_tab_id );
					?>
				<li class="<?php echo $is_active ? 'active' : ''; ?>" data-tab="<?php echo esc_attr( $tid ); ?>"><?php echo esc_html( $tlabel ); ?></li>
					<?php
					++$t_i;
				endforeach;
				?>
			</ul>
		</div>

		<div class="profile-grid-layout">
			<div class="profile-main-content">
				<?php if ( isset( $profile_tabs['specs'] ) ) : ?>
				<div id="specs" class="tab-content<?php echo ( 'specs' === $first_tab_id ) ? ' active' : ''; ?>">
					<section class="content-block">
						<h2 class="block-title"><?php echo esc_html__( 'خدمات', 'hello-elementor' ); ?></h2>
						<div class="service-grid">
							<?php foreach ( $services_items as $service_name ) : ?>
								<div class="service-card"><?php echo esc_html( $service_name ); ?></div>
							<?php endforeach; ?>
						</div>
					</section>
				</div>
				<?php endif; ?>

				<?php if ( isset( $profile_tabs['records'] ) ) : ?>
				<div id="records" class="tab-content<?php echo ( 'records' === $first_tab_id ) ? ' active' : ''; ?>">
					<section class="content-block">
						<h2 class="block-title"><?php echo esc_html__( 'سوابق', 'hello-elementor' ); ?></h2>
						<ul style="list-style:disc;padding-right:1.5rem;color:var(--on-surface-variant);">
							<?php foreach ( $records_items as $record_item ) : ?>
								<li style="margin-bottom:1rem;"><?php echo esc_html( $record_item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				</div>
				<?php endif; ?>

				<?php if ( isset( $profile_tabs['license-contact'] ) ) : ?>
				<div id="license-contact" class="tab-content<?php echo ( 'license-contact' === $first_tab_id ) ? ' active' : ''; ?>">
					<?php if ( $has_license_dl ) : ?>
					<section class="content-block">
						<h2 class="block-title"><span class="material-symbols-outlined">badge</span><?php echo esc_html__( 'پروانه وکالت', 'hello-elementor' ); ?></h2>
						<dl class="hvl-dl-grid" style="display:grid;gap:.75rem 1.5rem;grid-template-columns:minmax(0,140px) 1fr;align-items:start;">
							<?php if ( '' !== $lawyer_grade ) : ?>
							<dt class="text-on-surface-variant text-sm"><?php echo esc_html__( 'پایه / سطح', 'hello-elementor' ); ?></dt>
							<dd class="m-0 font-bold"><?php echo esc_html( $lawyer_grade ); ?></dd>
							<?php endif; ?>
							<?php if ( '' !== $license_no || '' !== $license ) : ?>
							<dt class="text-on-surface-variant text-sm"><?php echo esc_html__( 'شماره پروانه', 'hello-elementor' ); ?></dt>
							<dd class="m-0"><?php echo esc_html( '' !== $license_no ? $license_no : $license ); ?></dd>
							<?php endif; ?>
							<?php if ( '' !== $license_issued_display ) : ?>
							<dt class="text-on-surface-variant text-sm"><?php echo esc_html__( 'تاریخ صدور پروانه', 'hello-elementor' ); ?></dt>
							<dd class="m-0"><?php echo esc_html( $license_issued_display ); ?></dd>
							<?php endif; ?>
							<?php if ( '' !== $license_expires_display ) : ?>
							<dt class="text-on-surface-variant text-sm"><?php echo esc_html__( 'تاریخ انقضا', 'hello-elementor' ); ?></dt>
							<dd class="m-0"><?php echo esc_html( $license_expires_display ); ?></dd>
							<?php endif; ?>
							<?php if ( '' !== $license_file_url ) : ?>
								<dt class="text-on-surface-variant text-sm"><?php echo esc_html__( 'فایل پروانه', 'hello-elementor' ); ?></dt>
								<dd class="m-0"><a class="btn-primary-sm" style="display:inline-block;padding:.5rem 1rem;" href="<?php echo esc_url( $license_file_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'دانلود / مشاهده', 'hello-elementor' ); ?></a></dd>
							<?php endif; ?>
						</dl>
					</section>
					<?php endif; ?>
					<?php if ( $has_phones ) : ?>
					<section class="content-block" style="<?php echo $has_license_dl ? 'margin-top:1.5rem;' : ''; ?>">
						<h2 class="block-title"><span class="material-symbols-outlined">call</span><?php echo esc_html__( 'تماس', 'hello-elementor' ); ?></h2>
						<ul class="hvl-contact-list" style="list-style:none;padding:0;margin:0;line-height:2;">
							<?php if ( '' !== $mobile ) : ?>
								<li><span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">smartphone</span> <?php echo esc_html__( 'همراه وکیل', 'hello-elementor' ); ?>: <a dir="ltr" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $mobile ) ); ?>"><?php echo esc_html( $mobile ); ?></a></li>
							<?php endif; ?>
							<?php if ( '' !== $office_mobile && trim( (string) $office_mobile ) !== trim( (string) $mobile ) ) : ?>
								<li><span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">phone_iphone</span> <?php echo esc_html__( 'همراه دفتر', 'hello-elementor' ); ?>: <a dir="ltr" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $office_mobile ) ); ?>"><?php echo esc_html( $office_mobile ); ?></a></li>
							<?php endif; ?>
							<?php if ( '' !== $office_phone ) : ?>
								<li><span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">call</span> <?php echo esc_html__( 'تلفن دفتر', 'hello-elementor' ); ?>: <a dir="ltr" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $office_phone ) ); ?>"><?php echo esc_html( $office_phone ); ?></a></li>
							<?php endif; ?>
						</ul>
					</section>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<?php if ( isset( $profile_tabs['reviews'] ) ) : ?>
				<div id="reviews" class="tab-content<?php echo ( 'reviews' === $first_tab_id ) ? ' active' : ''; ?>">
					<section class="content-block">
						<h2 class="block-title">
							<span class="material-symbols-outlined">reviews</span>
							<?php echo esc_html__( 'نظرات', 'hello-elementor' ); ?>
						</h2>
						<?php while ( $reviews_query->have_posts() ) : ?>
							<?php
							$reviews_query->the_post();
							$review_rating = trim( (string) get_post_meta( get_the_ID(), 'hvl_review_rating', true ) );
							$reviewer_initial = get_the_title();
							$reviewer_initial = $reviewer_initial !== '' && function_exists( 'mb_substr' )
								? mb_substr( $reviewer_initial, 0, 1 )
								: ( $reviewer_initial !== '' ? substr( $reviewer_initial, 0, 1 ) : '' );
							?>
							<div class="review-card">
								<div class="review-header">
									<div class="reviewer-info">
										<div class="reviewer-avatar"><?php echo esc_html( $reviewer_initial ); ?></div>
										<div class="reviewer-text-info">
											<p class="font-bold"><?php the_title(); ?></p>
										</div>
									</div>
									<?php if ( '' !== $review_rating ) : ?>
									<div class="review-rating">
										<span class="material-symbols-outlined filled">star</span>
										<span class="text-xs"><?php echo esc_html( $review_rating ); ?>/5</span>
									</div>
									<?php endif; ?>
								</div>
								<?php
								$review_body = trim( wp_strip_all_tags( get_the_content() ) );
								if ( '' !== $review_body ) :
									?>
								<p class="review-text"><?php echo esc_html( $review_body ); ?></p>
								<?php endif; ?>
							</div>
						<?php endwhile; ?>
						<?php wp_reset_postdata(); ?>
					</section>
				</div>
				<?php endif; ?>

				<div id="calendar" class="tab-content<?php echo ( 'calendar' === $first_tab_id ) ? ' active' : ''; ?>">
					<section class="content-block">
						<h2 class="block-title">
							<span class="material-symbols-outlined">event_available</span>
							<?php echo esc_html__( 'رزرو نوبت', 'hello-elementor' ); ?>
						</h2>
						<p class="text-on-surface-variant text-sm" style="line-height:1.85;margin-bottom:1.25rem;">
							<?php echo esc_html__( 'تکمیل رزرو از صفحهٔ بعد انجام می‌شود.', 'hello-elementor' ); ?>
						</p>
						<div style="text-align:center;">
							<a href="<?php echo esc_url( $reservation_url ); ?>" class="btn-primary-sm" style="display:inline-block;padding:1rem 2.5rem;">
								<?php echo esc_html__( 'ادامهٔ رزرو', 'hello-elementor' ); ?>
							</a>
						</div>
					</section>
				</div>
			</div>
			<?php if ( $has_office_sidebar ) : ?>
			<div class="profile-sidebar-content">
				<section class="content-block">
					<h2 class="block-title">
						<span class="material-symbols-outlined">apartment</span>
						<?php echo esc_html__( 'اطلاعات دفتر', 'hello-elementor' ); ?>
					</h2>
					<?php if ( '' !== $office_map_display ) : ?>
					<div style="background:#f1f5f9;height:160px;border-radius:1rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:center;overflow:hidden;">
						<img src="<?php echo esc_url( $office_map_display ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;opacity:.78;">
					</div>
					<?php endif; ?>
					<?php if ( '' !== $office_address ) : ?>
					<p class="text-sm text-on-surface-variant" style="line-height:1.9;margin-bottom:.75rem;">
						<?php echo esc_html( $office_address ); ?>
					</p>
					<?php endif; ?>
					<?php if ( '' !== $mobile ) : ?>
						<p class="text-xs text-on-surface-variant" style="margin-bottom:.35rem;">
							<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">smartphone</span>
							<a dir="ltr" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $mobile ) ); ?>"><?php echo esc_html( $mobile ); ?></a>
						</p>
					<?php endif; ?>
					<?php if ( '' !== $office_mobile && trim( (string) $office_mobile ) !== trim( (string) $mobile ) ) : ?>
						<p class="text-xs text-on-surface-variant" style="margin-bottom:.35rem;">
							<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">phone_iphone</span>
							<a dir="ltr" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $office_mobile ) ); ?>"><?php echo esc_html( $office_mobile ); ?></a>
						</p>
					<?php endif; ?>
					<?php if ( '' !== $office_phone ) : ?>
					<p class="text-xs text-on-surface-variant" style="margin-bottom:.35rem;">
						<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">call</span>
						<a dir="ltr" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $office_phone ) ); ?>"><?php echo esc_html( $office_phone ); ?></a>
					</p>
					<?php endif; ?>
					<?php if ( '' !== $office_working_hours ) : ?>
					<p class="text-xs text-on-surface-variant">
						<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">schedule</span>
						<?php echo esc_html( $office_working_hours ); ?>
					</p>
					<?php endif; ?>
				</section>
			</div>
			<?php endif; ?>
		</div>

		<?php if ( $related_query->have_posts() ) : ?>
			<section style="margin-top:5rem;padding-bottom:5rem;">
				<div class="section-header">
					<h2 class="profile-name" style="font-size:1.75rem;"><?php echo esc_html__( 'وکلای مرتبط', 'hello-elementor' ); ?></h2>
				</div>
				<div class="lawyer-grid">
					<?php while ( $related_query->have_posts() ) : ?>
						<?php
						$related_query->the_post();
						$rid         = get_the_ID();
						$rimg        = function_exists( 'hovalvakil_lawyer_profile_image_url' )
							? hovalvakil_lawyer_profile_image_url( $rid, 'medium' )
							: (string) get_the_post_thumbnail_url( $rid, 'medium' );
						$r_spec_tags = function_exists( 'hovalvakil_lawyer_card_specialty_labels' )
							? hovalvakil_lawyer_card_specialty_labels( $rid )
							: [];
						$r_loc       = function_exists( 'hovalvakil_lawyer_card_location_line' )
							? hovalvakil_lawyer_card_location_line( $rid )
							: '';
						$r_spec_line = ! empty( $r_spec_tags ) ? implode( '، ', $r_spec_tags ) : '';
						?>
						<div class="lawyer-card ghost-border editorial-shadow">
							<div class="lawyer-card-image-wrapper">
								<img class="lawyer-card-image" src="<?php echo esc_url( $rimg ? $rimg : $hvl_placeholder ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>"<?php echo $hvl_img_onerror; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
							</div>
							<div class="lawyer-card-content">
								<h3 class="lawyer-name"><?php the_title(); ?></h3>
								<?php if ( '' !== $r_spec_line ) : ?>
								<p class="text-on-surface-variant text-sm mb-1"><?php echo esc_html( $r_spec_line ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== $r_loc ) : ?>
								<p class="text-secondary font-bold text-xs mb-6"><?php echo esc_html( $r_loc ); ?></p>
								<?php endif; ?>
								<a href="<?php the_permalink(); ?>" class="btn-primary-full"><?php echo esc_html__( 'مشاهده پروفایل', 'hello-elementor' ); ?></a>
							</div>
						</div>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				</div>
			</section>
		<?php endif; ?>
	</main>

	<script>
		(function () {
			const tabs = document.querySelectorAll('#profile-tabs li');
			const tabContents = document.querySelectorAll('.profile-main-content .tab-content');
			tabs.forEach((tab) => {
				tab.addEventListener('click', () => {
					const targetTab = tab.getAttribute('data-tab');
					tabs.forEach((t) => t.classList.remove('active'));
					tab.classList.add('active');
					tabContents.forEach((content) => {
						content.classList.remove('active');
						if (content.id === targetTab) {
							content.classList.add('active');
						}
					});
				});
			});
		})();
	</script>
<?php get_footer(); ?>
