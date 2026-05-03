<?php
/**
 * Persian-labeled meta fields for lawyers & centers (replaces raw Custom Fields UI).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove core «Custom fields» dropdown (shows raw hvl_* keys).
 *
 * @return void
 */
function hovalvakil_remove_postcustom_metabox() {
	$types = [ 'hvl_lawyer', 'hvl_center' ];
	foreach ( $types as $pt ) {
		remove_meta_box( 'postcustom', $pt, 'normal' );
		remove_meta_box( 'postcustom', $pt, 'advanced' );
	}
}
add_action( 'add_meta_boxes', 'hovalvakil_remove_postcustom_metabox', 1000 );

/**
 * Lawyer profile meta box.
 *
 * @return void
 */
function hovalvakil_register_lawyer_profile_metabox() {
	add_meta_box(
		'hovalvakil-lawyer-profile',
		'جزئیات پروفایل وکیل',
		'hovalvakil_render_lawyer_profile_metabox',
		'hvl_lawyer',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_hvl_lawyer', 'hovalvakil_register_lawyer_profile_metabox' );

/**
 * Center meta box.
 *
 * @return void
 */
function hovalvakil_register_center_meta_metabox() {
	add_meta_box(
		'hovalvakil-center-meta',
		'اطلاعات تکمیلی مرکز',
		'hovalvakil_render_center_meta_metabox',
		'hvl_center',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_hvl_center', 'hovalvakil_register_center_meta_metabox' );

/**
 * @param WP_Post $post Post.
 *
 * @return void
 */
function hovalvakil_render_lawyer_profile_metabox( $post ) {
	wp_nonce_field( 'hovalvakil_lawyer_profile_save', 'hovalvakil_lawyer_profile_nonce' );

	$rows = function_exists( 'hovalvakil_lawyer_profile_meta_rows' ) ? hovalvakil_lawyer_profile_meta_rows() : [];

	echo '<table class="form-table" role="presentation">';
	foreach ( $rows as $key => $cfg ) {
		if ( 'section' === ( $cfg['type'] ?? '' ) ) {
			echo '<tr class="hvl-meta-section"><td colspan="2"><strong>' . esc_html( $cfg['label'] ) . '</strong>';
			if ( '' !== ( $cfg['hint'] ?? '' ) ) {
				echo '<p class="description" style="margin:.35rem 0 0;">' . esc_html( $cfg['hint'] ) . '</p>';
			}
			echo '</td></tr>';
			continue;
		}

		$val = (string) get_post_meta( $post->ID, $key, true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $cfg['label'] ) . '</label></th><td>';

		if ( 'textarea' === $cfg['type'] ) {
			echo '<textarea class="large-text" rows="4" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $val ) . '</textarea>';
		} elseif ( 'date' === $cfg['type'] ) {
			$date_val = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ? $val : '';
			echo '<input type="date" class="regular-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $date_val ) . '" />';
			if ( '' !== $val && '' === $date_val ) {
				echo '<p class="description">' . esc_html__( 'مقدار ذخیره‌شده (متن): ', 'hello-elementor' ) . esc_html( $val ) . '</p>';
			}
		} elseif ( 'url' === $cfg['type'] ) {
			echo '<input type="url" class="large-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" placeholder="https://..." />';
		} else {
			echo '<input type="text" class="large-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" />';
		}
		if ( '' !== ( $cfg['hint'] ?? '' ) ) {
			echo '<p class="description">' . esc_html( $cfg['hint'] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</table>';
}

/**
 * @param WP_Post $post Post.
 *
 * @return void
 */
function hovalvakil_render_center_meta_metabox( $post ) {
	wp_nonce_field( 'hovalvakil_center_meta_save', 'hovalvakil_center_meta_nonce' );

	$rows = [
		'hvl_center_rating'         => [ 'label' => 'امتیاز مرکز', 'type' => 'text' ],
		'hvl_center_lawyers_count'  => [ 'label' => 'تعداد وکلا (نمایشی)', 'type' => 'text' ],
		'hvl_center_hotline'        => [ 'label' => 'تلفن / خط ویژه', 'type' => 'text' ],
		'hvl_center_open_hours'     => [ 'label' => 'ساعات پاسخگویی', 'type' => 'text' ],
	];

	echo '<table class="form-table" role="presentation">';
	foreach ( $rows as $key => $cfg ) {
		$val = (string) get_post_meta( $post->ID, $key, true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $cfg['label'] ) . '</label></th><td>';
		echo '<input type="text" class="large-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" />';
		echo '</td></tr>';
	}
	echo '</table>';
}

/**
 * @param int $post_id Post ID.
 *
 * @return void
 */
function hovalvakil_save_lawyer_profile_meta( $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['hovalvakil_lawyer_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hovalvakil_lawyer_profile_nonce'] ) ), 'hovalvakil_lawyer_profile_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( 'hvl_lawyer' !== get_post_type( $post_id ) ) {
		return;
	}

	$text_keys = [
		'hvl_mobile',
		'hvl_office_mobile',
		'hvl_license_no',
		'hvl_lawyer_grade',
		'hvl_license',
		'hvl_experience',
		'hvl_price_from',
		'hvl_rating',
		'hvl_reviews_count',
		'hvl_education',
		'hvl_office_phone',
		'hvl_office_working_hours',
		'hvl_office_map_image',
	];
	foreach ( $text_keys as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
	}

	if ( isset( $_POST['hvl_license_issued'] ) && function_exists( 'hovalvakil_lawyer_normalize_license_expires' ) ) {
		update_post_meta(
			$post_id,
			'hvl_license_issued',
			hovalvakil_lawyer_normalize_license_expires( wp_unslash( $_POST['hvl_license_issued'] ) )
		);
	}
	if ( isset( $_POST['hvl_license_expires'] ) && function_exists( 'hovalvakil_lawyer_normalize_license_expires' ) ) {
		update_post_meta(
			$post_id,
			'hvl_license_expires',
			hovalvakil_lawyer_normalize_license_expires( wp_unslash( $_POST['hvl_license_expires'] ) )
		);
	}

	if ( isset( $_POST['hvl_license_file_url'] ) ) {
		update_post_meta( $post_id, 'hvl_license_file_url', esc_url_raw( trim( wp_unslash( $_POST['hvl_license_file_url'] ) ) ) );
	}

	$areas = [ 'hvl_records', 'hvl_services', 'hvl_office_address' ];
	foreach ( $areas as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		update_post_meta( $post_id, $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
	}
}
add_action( 'save_post_hvl_lawyer', 'hovalvakil_save_lawyer_profile_meta' );

/**
 * @param int $post_id Post ID.
 *
 * @return void
 */
function hovalvakil_save_center_meta( $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['hovalvakil_center_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hovalvakil_center_meta_nonce'] ) ), 'hovalvakil_center_meta_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( 'hvl_center' !== get_post_type( $post_id ) ) {
		return;
	}

	$keys = [ 'hvl_center_rating', 'hvl_center_lawyers_count', 'hvl_center_hotline', 'hvl_center_open_hours' ];
	foreach ( $keys as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
	}
}
add_action( 'save_post_hvl_center', 'hovalvakil_save_center_meta' );
