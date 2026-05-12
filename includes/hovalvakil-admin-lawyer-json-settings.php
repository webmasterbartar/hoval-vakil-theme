<?php
/**
 * Admin: «تنظیمات وکیل‌ها» — ابزار بررسی JSON برای آخرین وکلا.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return void
 */
function hovalvakil_register_lawyer_json_admin_menu() {
	add_menu_page(
		__( 'تنظیمات وکیل‌ها', 'hello-elementor' ),
		__( 'تنظیمات وکیل‌ها', 'hello-elementor' ),
		'manage_options',
		'hovalvakil-lawyer-settings',
		'hovalvakil_render_lawyer_json_page',
		'dashicons-admin-users',
		58
	);

	// جایگزین زیرمنوی تکراری وردپرس با عنوان «جیسون وکیل».
	add_submenu_page(
		'hovalvakil-lawyer-settings',
		__( 'جیسون وکیل', 'hello-elementor' ),
		__( 'جیسون وکیل', 'hello-elementor' ),
		'manage_options',
		'hovalvakil-lawyer-settings',
		'hovalvakil_render_lawyer_json_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_lawyer_json_admin_menu' );

/**
 * ساخت آرایهٔ قابل‌سریالایز برای یک پست وکیل (پست + همهٔ متا + تاکسونومی‌ها + تصویر شاخص).
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>|null
 */
function hovalvakil_lawyer_full_export_array( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return null;
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'hvl_lawyer' !== $post->post_type ) {
		return null;
	}

	$post_row = [
		'ID'                    => (int) $post->ID,
		'post_author'           => (int) $post->post_author,
		'post_date'             => (string) $post->post_date,
		'post_date_gmt'         => (string) $post->post_date_gmt,
		'post_content'          => (string) $post->post_content,
		'post_title'            => (string) $post->post_title,
		'post_excerpt'          => (string) $post->post_excerpt,
		'post_status'           => (string) $post->post_status,
		'comment_status'        => (string) $post->comment_status,
		'ping_status'           => (string) $post->ping_status,
		'post_password'         => (string) $post->post_password,
		'post_name'             => (string) $post->post_name,
		'to_ping'               => (string) $post->to_ping,
		'pinged'                => (string) $post->pinged,
		'post_modified'         => (string) $post->post_modified,
		'post_modified_gmt'     => (string) $post->post_modified_gmt,
		'post_content_filtered' => (string) $post->post_content_filtered,
		'post_parent'           => (int) $post->post_parent,
		'guid'                  => (string) $post->guid,
		'menu_order'            => (int) $post->menu_order,
		'post_type'             => (string) $post->post_type,
		'post_mime_type'        => (string) $post->post_mime_type,
		'comment_count'         => (int) $post->comment_count,
	];

	$author = get_userdata( (int) $post->post_author );
	$post_row['author_login']  = $author ? (string) $author->user_login : '';
	$post_row['author_email']  = $author ? (string) $author->user_email : '';
	$post_row['author_display_name'] = $author ? (string) $author->display_name : '';

	$raw_meta = get_post_meta( $post_id );
	$meta     = [];
	if ( is_array( $raw_meta ) ) {
		foreach ( $raw_meta as $meta_key => $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}
			if ( count( $values ) === 1 ) {
				$meta[ $meta_key ] = maybe_unserialize( $values[0] );
			} else {
				$meta[ $meta_key ] = array_map( 'maybe_unserialize', $values );
			}
		}
	}

	$taxonomies = get_object_taxonomies( 'hvl_lawyer', 'names' );
	$terms_out  = [];
	foreach ( $taxonomies as $tax ) {
		$terms = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'all' ] );
		if ( is_wp_error( $terms ) ) {
			$terms_out[ $tax ] = [ '_error' => $terms->get_error_message() ];
			continue;
		}
		$terms_out[ $tax ] = array_map(
			static function ( WP_Term $t ) {
				return [
					'term_id'     => (int) $t->term_id,
					'name'        => (string) $t->name,
					'slug'        => (string) $t->slug,
					'term_group'  => (int) $t->term_group,
					'term_taxonomy_id' => (int) $t->term_taxonomy_id,
					'taxonomy'    => (string) $t->taxonomy,
					'description' => (string) $t->description,
					'parent'      => (int) $t->parent,
					'count'       => (int) $t->count,
				];
			},
			$terms
		);
	}

	$thumb_id = (int) get_post_thumbnail_id( $post_id );
	$feat     = [
		'attachment_id' => $thumb_id,
		'url'           => $thumb_id ? wp_get_attachment_url( $thumb_id ) : null,
		'metadata'      => $thumb_id ? wp_get_attachment_metadata( $thumb_id ) : null,
	];
	if ( $thumb_id ) {
		$feat['alt'] = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
	}

	return [
		'exported_at'      => gmdate( 'c' ),
		'post'             => $post_row,
		'permalink'        => get_permalink( $post_id ),
		'edit_link'        => get_edit_post_link( $post_id, 'raw' ),
		'featured_image'   => $feat,
		'meta'             => $meta,
		'taxonomies'       => $terms_out,
	];
}

/**
 * @return void
 */
function hovalvakil_render_lawyer_json_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازهٔ دسترسی ندارید.', 'hello-elementor' ) );
	}

	$lawyer_id = isset( $_GET['lawyer_id'] ) ? absint( $_GET['lawyer_id'] ) : 0;
	if ( $lawyer_id > 0 ) {
		check_admin_referer( 'hovalvakil_lawyer_json_' . $lawyer_id );
	}

	$list_url = admin_url( 'admin.php?page=hovalvakil-lawyer-settings' );

	echo '<div class="wrap hovalvakil-lawyer-json-wrap" dir="rtl" style="direction:rtl;text-align:right;">';
	echo '<h1>' . esc_html__( 'جیسون وکیل — بررسی داده', 'hello-elementor' ) . '</h1>';

	if ( $lawyer_id > 0 ) {
		$data = hovalvakil_lawyer_full_export_array( $lawyer_id );
		if ( null === $data ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'پست وکیل پیدا نشد یا نوع پست نامعتبر است.', 'hello-elementor' ) . '</p></div>';
			echo '<p><a href="' . esc_url( $list_url ) . '">' . esc_html__( '← بازگشت به فهرست ۱۰ وکیل اخیر', 'hello-elementor' ) . '</a></p>';
			echo '</div>';
			return;
		}

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			$json = '{}';
		}

		echo '<p>';
		echo '<a href="' . esc_url( $list_url ) . '">' . esc_html__( '← بازگشت به فهرست', 'hello-elementor' ) . '</a>';
		if ( ! empty( $data['edit_link'] ) ) {
			echo ' &nbsp;|&nbsp; <a href="' . esc_url( $data['edit_link'] ) . '">' . esc_html__( 'ویرایش در پیشخوان', 'hello-elementor' ) . '</a>';
		}
		echo '</p>';
		echo '<p><strong>' . esc_html__( 'شناسه:', 'hello-elementor' ) . '</strong> ' . esc_html( (string) $lawyer_id ) . ' — <strong>' . esc_html__( 'عنوان:', 'hello-elementor' ) . '</strong> ' . esc_html( (string) ( $data['post']['post_title'] ?? '' ) ) . '</p>';
		echo '<p><label><strong>' . esc_html__( 'خروجی JSON', 'hello-elementor' ) . '</strong></label></p>';
		echo '<textarea readonly="readonly" id="hovalvakil-lawyer-json-out" class="large-text code" rows="28" style="width:100%;max-width:100%;font-family:Consolas,monospace;direction:ltr;text-align:left;">';
		echo esc_textarea( $json );
		echo '</textarea>';
		echo '<p><button type="button" class="button button-primary" id="hovalvakil-copy-json">' . esc_html__( 'کپی JSON', 'hello-elementor' ) . '</button></p>';
		echo '<script>(function(){var t=document.getElementById("hovalvakil-lawyer-json-out"),b=document.getElementById("hovalvakil-copy-json");if(!t||!b)return;b.addEventListener("click",function(){t.select();try{document.execCommand("copy");b.textContent="' . esc_js( __( 'کپی شد', 'hello-elementor' ) ) . '";setTimeout(function(){b.textContent="' . esc_js( __( 'کپی JSON', 'hello-elementor' ) ) . '";},1500);}catch(e){}});})();</script>';
		echo '</div>';
		return;
	}

	$query = new WP_Query(
		[
			'post_type'              => 'hvl_lawyer',
			'post_status'            => 'any',
			'posts_per_page'         => 10,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]
	);

	echo '<p>' . esc_html__( '۱۰ وکیل آخر بر اساس تاریخ ایجاد (نزولی). روی هر مورد کلیک کنید تا JSON کامل همان رکورد نمایش داده شود.', 'hello-elementor' ) . '</p>';

	if ( ! $query->have_posts() ) {
		echo '<p>' . esc_html__( 'هیچ وکیلی در سایت ثبت نشده است.', 'hello-elementor' ) . '</p>';
		echo '</div>';
		return;
	}

	echo '<ul class="ul-disc" style="font-size:14px;line-height:1.8;">';
	while ( $query->have_posts() ) {
		$query->the_post();
		$pid   = get_the_ID();
		$url   = wp_nonce_url(
			add_query_arg( 'lawyer_id', $pid, $list_url ),
			'hovalvakil_lawyer_json_' . $pid
		);
		$title = get_the_title() ?: __( '(بدون عنوان)', 'hello-elementor' );
		$st    = get_post_status();
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
		echo ' <span style="color:#646970;">— ID: ' . esc_html( (string) $pid ) . ' — ' . esc_html( $st ) . '</span></li>';
	}
	wp_reset_postdata();
	echo '</ul>';
	echo '</div>';
}
