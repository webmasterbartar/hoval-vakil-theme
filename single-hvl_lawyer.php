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
$mobile               = apply_filters( 'hvl_lawyer_show_mobile', true, (int) $post_id ) ? $mobile : '';
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
$lawyer_accepts_inperson = function_exists( 'hvl_ll_lawyer_accepts_inperson' )
	? hvl_ll_lawyer_accepts_inperson( (int) $post_id )
	: true;
$lawyer_accepts_phone    = function_exists( 'hvl_ll_lawyer_accepts_phone' )
	? hvl_ll_lawyer_accepts_phone( (int) $post_id )
	: true;
$lawyer_accepts_any      = $lawyer_accepts_inperson || $lawyer_accepts_phone;

// Social/contact networks from the lawyer panel wizard.
$hvl_social_defs = function_exists( 'hvl_ll_social_fields' ) ? hvl_ll_social_fields() : [];
$hvl_social_rows = [];
foreach ( $hvl_social_defs as $skey => $sinfo ) {
	$val = (string) get_post_meta( $post_id, 'hvl_url_' . $skey, true );
	if ( '' === trim( $val ) ) {
		continue;
	}
	$url = function_exists( 'hvl_ll_social_public_url' ) ? hvl_ll_social_public_url( $skey, $val ) : '';
	if ( '' === $url ) {
		continue;
	}
	$hvl_social_rows[ $skey ] = [
		'label' => (string) ( $sinfo['label'] ?? $skey ),
		'icon'  => (string) ( $sinfo['icon'] ?? 'link' ),
		'value' => $val,
		'url'   => $url,
	];
}

if ( $lawyer_accepts_any ) {
	$profile_tabs['calendar'] = 'رزرو نوبت';
}

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
						<?php if ( $lawyer_accepts_inperson ) : ?>
							<a href="<?php echo esc_url( add_query_arg( [ 'service' => 'مشاوره حضوری' ], $reservation_url ) ); ?>" class="btn-primary-sm" style="display:block;width:100%;padding:.875rem;text-align:center;"><?php echo esc_html__( 'رزرو نوبت حضوری', 'hello-elementor' ); ?></a>
						<?php endif; ?>
						<?php if ( $lawyer_accepts_phone ) : ?>
							<a href="<?php echo esc_url( add_query_arg( [ 'service' => 'مشاوره تلفنی' ], $reservation_url ) ); ?>" class="btn-primary-sm" style="display:block;width:100%;padding:.875rem;text-align:center;margin-top:.5rem;background:var(--secondary-container,#fed977);color:#2d2200;"><?php echo esc_html__( 'رزرو مشاوره تلفنی', 'hello-elementor' ); ?></a>
						<?php endif; ?>
						<?php if ( ! $lawyer_accepts_inperson && ! $lawyer_accepts_phone ) : ?>
							<div style="text-align:center;padding:1rem;background:#fff7ed;border:1px solid #fed7aa;border-radius:.5rem;color:#9a3412;font-size:.875rem;line-height:1.7;">
								<span class="material-symbols-outlined" style="vertical-align:-4px;font-size:18px;margin-left:4px;">event_busy</span>
								<?php echo esc_html__( 'این وکیل در حال حاضر پذیرای رزرو نوبت آنلاین نیست.', 'hello-elementor' ); ?>
							</div>
						<?php endif; ?>
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
						<style>
							.hvl-mobile-blur {
								filter: blur(6px);
								user-select: none;
								-webkit-user-select: none;
								cursor: not-allowed;
								color: inherit;
								background: rgba(0,0,0,.04);
								padding: 0 .35rem;
								border-radius: 4px;
								display: inline-block;
							}
							.hvl-mobile-blur-note {
								margin-inline-start: .4rem;
								font-size: 12px;
								color: var(--on-surface-variant, #6b7280);
								background: var(--surface-variant, #eef0f3);
								padding: 2px 8px;
								border-radius: 999px;
								vertical-align: middle;
							}

							/* ─── Working hours (structured) ─── */
							.hvl-wh-public {
								margin-top: 1rem;
								background: linear-gradient(180deg, #ffffff, #fafbfc);
								border: 1px solid #eef0f3;
								border-radius: .875rem;
								padding: .85rem 1rem;
							}
							.hvl-wh-public-title {
								display: flex;
								align-items: center;
								gap: .35rem;
								margin: 0 0 .6rem;
								font-size: .8125rem;
								font-weight: 700;
								color: #1b1c1c;
							}
							.hvl-wh-public-title .material-symbols-outlined { font-size: 18px; color: #041534; }
							.hvl-wh-public-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 2px; }
							.hvl-wh-public-row {
								display: flex;
								justify-content: space-between;
								align-items: center;
								padding: .45rem .55rem;
								border-radius: .5rem;
								font-size: .8125rem;
								line-height: 1.6;
								transition: background .2s;
							}
							.hvl-wh-public-row + .hvl-wh-public-row { border-top: 1px dashed #eef0f3; }
							.hvl-wh-public-row.is-closed { color: #6b7280; }
							.hvl-wh-public-row.is-today {
								background: rgba(254,217,119,.18);
								border: 1px solid rgba(254,217,119,.5);
							}
							.hvl-wh-public-day {
								font-weight: 700;
								color: #1b1c1c;
								display: inline-flex;
								gap: .35rem;
								align-items: center;
							}
							.hvl-wh-public-row.is-closed .hvl-wh-public-day { color: #6b7280; }
							.hvl-wh-public-today {
								font-style: normal;
								font-size: .625rem;
								background: #041534;
								color: #fff;
								padding: 2px 6px;
								border-radius: 999px;
								font-weight: 700;
							}
							.hvl-wh-public-time {
								font-variant-numeric: tabular-nums;
								font-weight: 700;
								color: #041534;
								background: #fff;
								padding: 2px 8px;
								border: 1px solid #eef0f3;
								border-radius: .375rem;
							}
							.hvl-wh-public-closed {
								font-size: .75rem;
								color: #9ca3af;
								background: #fafafa;
								padding: 2px 10px;
								border-radius: 999px;
								font-weight: 600;
							}

							/* ─── Social / contact cards (sleek) ─── */
							.hvl-social-section .block-title { margin-bottom: .4rem; }
							.hvl-social-sub {
								font-size: .8125rem;
								color: #6b7280;
								margin: 0 0 .9rem;
								line-height: 1.7;
							}
							.hvl-social-cards {
								display: grid;
								grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
								gap: .65rem;
							}
							.hvl-social-card {
								display: flex;
								align-items: center;
								gap: .65rem;
								padding: .65rem .8rem;
								background: #fff;
								border: 1.5px solid #eef0f3;
								border-radius: .875rem;
								color: #1b1c1c;
								text-decoration: none;
								transition: border-color .2s, background .2s, transform .15s, box-shadow .2s;
								min-width: 0;
							}
							.hvl-social-card:hover {
								border-color: #041534;
								background: #fafafa;
								transform: translateY(-1px);
								box-shadow: 0 4px 14px rgba(4,21,52,.08);
							}
							.hvl-social-card-icon {
								width: 38px;
								height: 38px;
								flex-shrink: 0;
								border-radius: 10px;
								display: inline-flex;
								align-items: center;
								justify-content: center;
								background: #f6f7fb;
								line-height: 0;
							}
							.hvl-social-card-icon svg { display: block; }
							.hvl-social-card-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; flex: 1; }
							.hvl-social-card-label { font-size: .8125rem; font-weight: 700; color: #1b1c1c; }
							.hvl-social-card-value {
								font-size: .6875rem;
								color: #6b7280;
								white-space: nowrap;
								overflow: hidden;
								text-overflow: ellipsis;
								max-width: 100%;
							}
							.hvl-social-card-arrow { font-size: 18px !important; color: #c5c6cf; flex-shrink: 0; transition: color .2s, transform .15s; }
							.hvl-social-card:hover .hvl-social-card-arrow { color: #041534; transform: translateX(-2px); }
							/* Per-platform tinted icon backgrounds */
							.hvl-social-card[data-platform="website"]   .hvl-social-card-icon { color: #6b7280; }
							.hvl-social-card[data-platform="instagram"] .hvl-social-card-icon { color: #E1306C; background: #FCE4EC; }
							.hvl-social-card[data-platform="telegram"]  .hvl-social-card-icon { color: #26A5E4; background: #E3F2FD; }
							.hvl-social-card[data-platform="whatsapp"]  .hvl-social-card-icon { color: #25D366; background: #E8F5E9; }
							.hvl-social-card[data-platform="bale"]      .hvl-social-card-icon { color: #22A4EE; background: #E3F2FD; }
							.hvl-social-card[data-platform="eitaa"]     .hvl-social-card-icon { color: #F8A116; background: #FFF3E0; }
							.hvl-social-card[data-platform="soroush"]   .hvl-social-card-icon { color: #00BAEC; background: #E0F7FA; }
							.hvl-social-card[data-platform="rubika"]    .hvl-social-card-icon { background: #fafafa; }
							.hvl-social-card[data-platform="igap"]      .hvl-social-card-icon { color: #16A085; background: #E0F2F1; }
						</style>
						<ul class="hvl-contact-list" style="list-style:none;padding:0;margin:0;line-height:2;">
							<?php
							$reveal_mobile = ( '1' === (string) get_post_meta( $post_id, 'hvl_reveal_mobile', true ) );
							$hide_mobile   = ( '1' === (string) get_post_meta( $post_id, 'hvl_hide_mobile', true ) );
							/** Placeholder shown in HTML when lawyer hasn't revealed their number. Real number never reaches the page. */
							$mobile_placeholder = '09121111111';
							?>
							<?php if ( '' !== $mobile && ! $hide_mobile ) : ?>
								<li>
									<span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">smartphone</span>
									<?php echo esc_html__( 'همراه وکیل', 'hello-elementor' ); ?>:
									<?php if ( $reveal_mobile ) : ?>
										<a dir="ltr" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $mobile ) ); ?>"><?php echo esc_html( $mobile ); ?></a>
									<?php else : ?>
										<span class="hvl-mobile-blur" aria-label="<?php esc_attr_e( 'شماره مخفی', 'hello-elementor' ); ?>" title="<?php esc_attr_e( 'برای نمایش شماره، وکیل باید آن را در پنل خود فعال کند.', 'hello-elementor' ); ?>" dir="ltr"><?php echo esc_html( $mobile_placeholder ); ?></span>
										<span class="hvl-mobile-blur-note"><?php esc_html_e( 'مخفی‌شده توسط وکیل', 'hello-elementor' ); ?></span>
									<?php endif; ?>
								</li>
							<?php endif; ?>
							<?php if ( '' !== $office_mobile && trim( (string) $office_mobile ) !== trim( (string) $mobile ) ) : ?>
								<li><span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;">phone_iphone</span> <?php echo esc_html__( 'تلفن دفتر', 'hello-elementor' ); ?>: <a dir="ltr" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $office_mobile ) ); ?>"><?php echo esc_html( $office_mobile ); ?></a></li>
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

				<?php if ( ! empty( $hvl_social_rows ) ) : ?>
				<section class="content-block hvl-social-section" style="margin-top:1.25rem;">
					<h2 class="block-title">
						<span class="material-symbols-outlined">share</span>
						<?php echo esc_html__( 'راه‌های ارتباطی و شبکه‌های اجتماعی', 'hello-elementor' ); ?>
					</h2>
					<p class="hvl-social-sub">برای ارتباط مستقیم با وکیل، روی هر گزینه کلیک کنید.</p>
					<div class="hvl-social-cards">
						<?php foreach ( $hvl_social_rows as $skey => $srow ) : ?>
							<a href="<?php echo esc_url( $srow['url'] ); ?>" target="_blank" rel="noopener"
								class="hvl-social-card" data-platform="<?php echo esc_attr( $skey ); ?>"
								aria-label="<?php echo esc_attr( $srow['label'] ); ?>">
								<span class="hvl-social-card-icon" aria-hidden="true">
									<?php echo function_exists( 'hvl_ll_social_icon_svg' ) ? hvl_ll_social_icon_svg( $skey, 22 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</span>
								<span class="hvl-social-card-text">
									<span class="hvl-social-card-label"><?php echo esc_html( $srow['label'] ); ?></span>
									<span class="hvl-social-card-value" dir="ltr"><?php echo esc_html( $srow['value'] ); ?></span>
								</span>
								<span class="hvl-social-card-arrow material-symbols-outlined" aria-hidden="true">chevron_left</span>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
				<?php endif; ?>

				<?php if ( $lawyer_accepts_any ) : ?>
				<div id="calendar" class="tab-content<?php echo ( 'calendar' === $first_tab_id ) ? ' active' : ''; ?>">
					<section class="content-block">
						<h2 class="block-title">
							<span class="material-symbols-outlined">event_available</span>
							<?php echo esc_html__( 'رزرو نوبت', 'hello-elementor' ); ?>
						</h2>
						<p class="text-on-surface-variant text-sm" style="line-height:1.85;margin-bottom:1.25rem;">
							<?php echo esc_html__( 'نوع مشاوره را انتخاب کنید تا به صفحهٔ ثبت رزرو منتقل شوید.', 'hello-elementor' ); ?>
						</p>
						<div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center;">
							<?php if ( $lawyer_accepts_inperson ) : ?>
								<a href="<?php echo esc_url( add_query_arg( [ 'service' => 'مشاوره حضوری' ], $reservation_url ) ); ?>" class="btn-primary-sm" style="display:inline-flex;align-items:center;gap:.5rem;padding:1rem 2rem;">
									<span class="material-symbols-outlined">apartment</span>
									<?php echo esc_html__( 'رزرو نوبت حضوری', 'hello-elementor' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $lawyer_accepts_phone ) : ?>
								<a href="<?php echo esc_url( add_query_arg( [ 'service' => 'مشاوره تلفنی' ], $reservation_url ) ); ?>" class="btn-primary-sm" style="display:inline-flex;align-items:center;gap:.5rem;padding:1rem 2rem;background:var(--secondary-container,#fed977);color:#2d2200;">
									<span class="material-symbols-outlined">call</span>
									<?php echo esc_html__( 'رزرو مشاوره تلفنی', 'hello-elementor' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</section>
				</div>
				<?php endif; ?>
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
					<?php
					$sidebar_reveal_mobile = ( '1' === (string) get_post_meta( $post_id, 'hvl_reveal_mobile', true ) );
					$sidebar_hide_mobile   = ( '1' === (string) get_post_meta( $post_id, 'hvl_hide_mobile', true ) );
					$sidebar_mobile_holder = '09121111111';
					?>
					<?php if ( '' !== $mobile && ! $sidebar_hide_mobile ) : ?>
						<p class="text-xs text-on-surface-variant" style="margin-bottom:.35rem;">
							<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">smartphone</span>
							<?php if ( $sidebar_reveal_mobile ) : ?>
								<a dir="ltr" href="<?php echo esc_url( 'tel:' . preg_replace( '/\D+/', '', $mobile ) ); ?>"><?php echo esc_html( $mobile ); ?></a>
							<?php else : ?>
								<span class="hvl-mobile-blur" aria-label="<?php esc_attr_e( 'شماره مخفی', 'hello-elementor' ); ?>" dir="ltr"><?php echo esc_html( $sidebar_mobile_holder ); ?></span>
							<?php endif; ?>
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
					<?php
					// ── Structured working-hours display ──
					$hvl_wh_json = (string) get_post_meta( $post_id, 'hvl_office_working_hours_json', true );
					$hvl_wh_data = '' !== $hvl_wh_json ? json_decode( $hvl_wh_json, true ) : null;
					$hvl_wh_days_def = [
						'shanbe'  => [ 'label' => 'شنبه',     'js' => 6 ],
						'1shanbe' => [ 'label' => 'یک‌شنبه',  'js' => 0 ],
						'2shanbe' => [ 'label' => 'دوشنبه',   'js' => 1 ],
						'3shanbe' => [ 'label' => 'سه‌شنبه',   'js' => 2 ],
						'4shanbe' => [ 'label' => 'چهارشنبه', 'js' => 3 ],
						'5shanbe' => [ 'label' => 'پنج‌شنبه', 'js' => 4 ],
						'jome'    => [ 'label' => 'جمعه',     'js' => 5 ],
					];
					$hvl_today_js = (int) wp_date( 'w' );
					$hvl_wh_to_fa = function ( $s ) {
						return str_replace( [ '0','1','2','3','4','5','6','7','8','9' ], [ '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' ], (string) $s );
					};
					?>
					<?php if ( is_array( $hvl_wh_data ) && ! empty( $hvl_wh_data ) ) : ?>
					<div class="hvl-wh-public">
						<h3 class="hvl-wh-public-title">
							<span class="material-symbols-outlined">schedule</span>
							<?php echo esc_html__( 'ساعات کاری', 'hello-elementor' ); ?>
						</h3>
						<ul class="hvl-wh-public-list">
							<?php foreach ( $hvl_wh_days_def as $dkey => $dmeta ) :
								$row     = isset( $hvl_wh_data[ $dkey ] ) && is_array( $hvl_wh_data[ $dkey ] ) ? $hvl_wh_data[ $dkey ] : [];
								$is_open = ! empty( $row['open'] );
								$from    = isset( $row['from'] ) ? (string) $row['from'] : '';
								$to      = isset( $row['to'] )   ? (string) $row['to']   : '';
								$is_today = ( (int) $dmeta['js'] === $hvl_today_js );
								?>
								<li class="hvl-wh-public-row<?php echo $is_open ? ' is-open' : ' is-closed'; ?><?php echo $is_today ? ' is-today' : ''; ?>">
									<span class="hvl-wh-public-day">
										<?php echo esc_html( $dmeta['label'] ); ?>
										<?php if ( $is_today ) : ?><em class="hvl-wh-public-today">امروز</em><?php endif; ?>
									</span>
									<?php if ( $is_open && '' !== $from && '' !== $to ) : ?>
										<span class="hvl-wh-public-time" dir="ltr"><?php echo esc_html( $hvl_wh_to_fa( $from ) . ' – ' . $hvl_wh_to_fa( $to ) ); ?></span>
									<?php else : ?>
										<span class="hvl-wh-public-closed">تعطیل</span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<?php elseif ( '' !== $office_working_hours ) : ?>
					<p class="text-xs text-on-surface-variant" style="line-height:1.85;">
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
