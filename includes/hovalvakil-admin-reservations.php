<?php
/**
 * Admin UX enhancements for reservations.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register reservation detail metabox.
 *
 * @return void
 */
function hovalvakil_register_reservation_metabox() {
	add_meta_box(
		'hovalvakil-reservation-details',
		'جزئیات رزرو',
		'hovalvakil_render_reservation_metabox',
		'hvl_reservation',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_hvl_reservation', 'hovalvakil_register_reservation_metabox' );

/**
 * Render reservation details metabox.
 *
 * @param WP_Post $post Post object.
 *
 * @return void
 */
function hovalvakil_render_reservation_metabox( $post ) {
	$fields = [
		'نام موکل'      => get_post_meta( $post->ID, 'hvl_res_full_name', true ),
		'شماره همراه'   => get_post_meta( $post->ID, 'hvl_res_phone', true ),
		'کد ملی'        => get_post_meta( $post->ID, 'hvl_res_national_id', true ),
		'وکیل'          => get_post_meta( $post->ID, 'hvl_res_lawyer_name', true ),
		'شناسه وکیل'    => get_post_meta( $post->ID, 'hvl_res_lawyer_id', true ),
		'خدمت'          => get_post_meta( $post->ID, 'hvl_res_service', true ),
		'تاریخ'         => get_post_meta( $post->ID, 'hvl_res_date', true ),
		'ساعت'          => get_post_meta( $post->ID, 'hvl_res_time', true ),
		'مبلغ'          => get_post_meta( $post->ID, 'hvl_res_price', true ),
		'روش پرداخت'    => get_post_meta( $post->ID, 'hvl_res_payment_type', true ),
		'وضعیت'         => get_post_meta( $post->ID, 'hvl_res_status', true ),
		'کد پیگیری'     => get_post_meta( $post->ID, 'hvl_res_tracking_code', true ),
		'تاریخ ثبت رزرو' => get_post_meta( $post->ID, 'hvl_res_created_at', true ),
	];

	$description = (string) get_post_meta( $post->ID, 'hvl_res_description', true );
	?>
	<div class="hvl-res-box">
		<table class="widefat striped" style="border-radius:8px;overflow:hidden;">
			<tbody>
			<?php foreach ( $fields as $label => $value ) : ?>
				<tr>
					<th style="width:180px;"><?php echo esc_html( $label ); ?></th>
					<td><?php echo esc_html( (string) $value ); ?></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th>توضیحات پرونده</th>
				<td><?php echo esc_html( $description ); ?></td>
			</tr>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Customize columns on reservations list table.
 *
 * @param array $columns Columns.
 *
 * @return array
 */
function hovalvakil_reservation_columns( $columns ) {
	return [
		'cb'          => $columns['cb'],
		'title'       => 'شناسه رزرو',
		'client'      => 'موکل',
		'lawyer'      => 'وکیل',
		'datetime'    => 'تاریخ/ساعت رزرو',
		'service'     => 'نوع خدمت',
		'phone'       => 'تماس',
		'status'      => 'وضعیت',
		'tracking'    => 'کد پیگیری',
		'date'        => 'تاریخ ثبت',
	];
}
add_filter( 'manage_hvl_reservation_posts_columns', 'hovalvakil_reservation_columns' );

/**
 * Render reservation custom columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 *
 * @return void
 */
function hovalvakil_render_reservation_columns( $column, $post_id ) {
	$status = (string) get_post_meta( $post_id, 'hvl_res_status', true );
	switch ( $column ) {
		case 'client':
			echo esc_html( (string) get_post_meta( $post_id, 'hvl_res_full_name', true ) );
			break;
		case 'lawyer':
			echo esc_html( (string) get_post_meta( $post_id, 'hvl_res_lawyer_name', true ) );
			break;
		case 'datetime':
			$date = (string) get_post_meta( $post_id, 'hvl_res_date', true );
			$time = (string) get_post_meta( $post_id, 'hvl_res_time', true );
			echo esc_html( trim( $date . ' - ' . $time, ' -' ) );
			break;
		case 'service':
			echo esc_html( (string) get_post_meta( $post_id, 'hvl_res_service', true ) );
			break;
		case 'phone':
			echo esc_html( (string) get_post_meta( $post_id, 'hvl_res_phone', true ) );
			break;
		case 'status':
			$label = 'در انتظار';
			if ( 'confirmed' === $status ) {
				$label = 'تایید شده';
			} elseif ( 'cancelled' === $status ) {
				$label = 'لغو شده';
			}
			printf(
				'<span class="hvl-res-status hvl-res-status-%1$s">%2$s</span>',
				esc_attr( $status ? $status : 'pending' ),
				esc_html( $label )
			);
			break;
		case 'tracking':
			echo esc_html( (string) get_post_meta( $post_id, 'hvl_res_tracking_code', true ) );
			break;
	}
}
add_action( 'manage_hvl_reservation_posts_custom_column', 'hovalvakil_render_reservation_columns', 10, 2 );

/**
 * Make selected columns sortable.
 *
 * @param array $columns Columns.
 *
 * @return array
 */
function hovalvakil_reservation_sortable_columns( $columns ) {
	$columns['datetime'] = 'datetime';
	$columns['status']   = 'status';
	return $columns;
}
add_filter( 'manage_edit-hvl_reservation_sortable_columns', 'hovalvakil_reservation_sortable_columns' );

/**
 * Handle sortable columns query.
 *
 * @param WP_Query $query Query.
 *
 * @return void
 */
function hovalvakil_reservation_sorting_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'hvl_reservation' !== $query->get( 'post_type' ) ) {
		return;
	}

	$orderby = (string) $query->get( 'orderby' );
	if ( 'status' === $orderby ) {
		$query->set( 'meta_key', 'hvl_res_status' );
		$query->set( 'orderby', 'meta_value' );
	}
	if ( 'datetime' === $orderby ) {
		$query->set( 'meta_key', 'hvl_res_created_at' );
		$query->set( 'orderby', 'meta_value' );
	}
}
add_action( 'pre_get_posts', 'hovalvakil_reservation_sorting_query' );

/**
 * Add clean admin styles for reservation list.
 *
 * @return void
 */
function hovalvakil_reservation_admin_styles() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-hvl_reservation' !== $screen->id ) {
		return;
	}
	?>
	<style>
		.wp-list-table .column-client { width: 130px; }
		.wp-list-table .column-lawyer { width: 140px; }
		.wp-list-table .column-datetime { width: 150px; }
		.wp-list-table .column-service { width: 130px; }
		.wp-list-table .column-phone { width: 120px; }
		.wp-list-table .column-status { width: 110px; }
		.hvl-res-status {
			display: inline-block;
			padding: 4px 10px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
		}
		.hvl-res-status-pending {
			background: #fff7ed;
			color: #b45309;
		}
		.hvl-res-status-confirmed {
			background: #ecfdf3;
			color: #15803d;
		}
		.hvl-res-status-cancelled {
			background: #fef2f2;
			color: #b91c1c;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'hovalvakil_reservation_admin_styles' );

