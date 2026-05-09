<?php
/**
 * Home card image fallback report for lawyers.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hovalvakil_register_missing_thumbnail_admin_page' ) ) {
	function hovalvakil_register_missing_thumbnail_admin_page() {
		add_submenu_page(
			'edit.php?post_type=hvl_lawyer',
			'وکلای بدون عکس واقعی (Home)',
			'گزارش عکس Home',
			'edit_others_posts',
			'hvl-lawyers-without-thumbnail',
			'hovalvakil_render_missing_thumbnail_admin_page'
		);
	}
}
add_action( 'admin_menu', 'hovalvakil_register_missing_thumbnail_admin_page' );

if ( ! function_exists( 'hovalvakil_local_path_from_url' ) ) {
	function hovalvakil_local_path_from_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return '';
		}
		$home_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$url_host  = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' === $home_host || '' === $url_host || strtolower( $home_host ) !== strtolower( $url_host ) ) {
			return '';
		}
		$theme_uri = trailingslashit( get_template_directory_uri() );
		if ( 0 === strpos( $url, $theme_uri ) ) {
			$relative = ltrim( (string) substr( $url, strlen( $theme_uri ) ), '/' );
			return wp_normalize_path( trailingslashit( get_template_directory() ) . $relative );
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) ) {
			$baseurl = trailingslashit( (string) $uploads['baseurl'] );
			if ( 0 === strpos( $url, $baseurl ) ) {
				$relative = ltrim( (string) substr( $url, strlen( $baseurl ) ), '/' );
				return wp_normalize_path( trailingslashit( (string) $uploads['basedir'] ) . $relative );
			}
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' === $path ? '' : wp_normalize_path( untrailingslashit( ABSPATH ) . '/' . ltrim( $path, '/' ) );
	}
}

if ( ! function_exists( 'hovalvakil_get_image_url_status_code' ) ) {
	function hovalvakil_get_image_url_status_code( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return 0;
		}
		$cache_key = 'hvl_img_status_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$local_path = hovalvakil_local_path_from_url( $url );
		if ( '' !== $local_path ) {
			$status = is_file( $local_path ) ? 200 : 404;
			set_transient( $cache_key, (int) $status, 6 * HOUR_IN_SECONDS );
			return (int) $status;
		}
		$status = 0;
		$head   = wp_remote_head( $url, [ 'timeout' => 6, 'redirection' => 3, 'sslverify' => false ] );
		if ( ! is_wp_error( $head ) ) {
			$status = (int) wp_remote_retrieve_response_code( $head );
		}
		if ( $status <= 0 || 405 === $status ) {
			$get = wp_remote_get( $url, [ 'timeout' => 8, 'redirection' => 3, 'sslverify' => false, 'limit_response_size' => 1024 ] );
			if ( ! is_wp_error( $get ) ) {
				$status = (int) wp_remote_retrieve_response_code( $get );
			}
		}
		set_transient( $cache_key, (int) $status, 6 * HOUR_IN_SECONDS );
		return (int) $status;
	}
}

if ( ! function_exists( 'hovalvakil_get_home_placeholder_rows' ) ) {
	function hovalvakil_get_home_placeholder_rows() {
		$query = new WP_Query(
			[
				'post_type'              => 'hvl_lawyer',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
		$rows = [];
		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			$image   = function_exists( 'hovalvakil_lawyer_profile_image_url' ) ? (string) hovalvakil_lawyer_profile_image_url( $post_id, 'medium' ) : (string) get_the_post_thumbnail_url( $post_id, 'medium' );
			$image   = trim( $image );
			$reason  = '';
			$status  = 0;
			if ( '' === $image ) {
				$reason = 'no_image_source';
			} else {
				$status = hovalvakil_get_image_url_status_code( $image );
				if ( 404 === $status ) {
					$reason = 'image_url_404';
				}
			}
			if ( '' === $reason ) {
				continue;
			}
			$rows[] = [ 'id' => $post_id, 'title' => (string) get_the_title( $post_id ), 'image_url' => $image, 'status' => $status, 'reason' => $reason ];
		}
		return [ 'rows' => $rows, 'total' => (int) count( $query->posts ) ];
	}
}

if ( ! function_exists( 'hovalvakil_generate_home_placeholder_csv_file' ) ) {
	function hovalvakil_generate_home_placeholder_csv_file() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hello-elementor' ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			return [ 'ok' => false, 'message' => 'Cannot access uploads directory.' ];
		}
		$dir = trailingslashit( (string) $upload['basedir'] ) . 'hovalvakil-reports';
		if ( ! wp_mkdir_p( $dir ) ) {
			return [ 'ok' => false, 'message' => 'Cannot create report directory.' ];
		}
		$filename  = 'hvl-home-image-report-' . gmdate( 'Ymd-His' ) . '.csv';
		$file_path = trailingslashit( $dir ) . $filename;
		$file_url  = trailingslashit( (string) $upload['baseurl'] ) . 'hovalvakil-reports/' . $filename;

		$out = fopen( $file_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $out ) {
			return [ 'ok' => false, 'message' => 'Cannot open output file for writing.' ];
		}

		// UTF-8 BOM for correct Persian display in Excel.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, [ 'id', 'title', 'reason', 'image_url', 'status', 'edit_url', 'view_url' ] );

		$paged = 1;
		$chunk = 500;
		while ( true ) {
			$query = new WP_Query(
				[
					'post_type'              => 'hvl_lawyer',
					'post_status'            => 'publish',
					'posts_per_page'         => $chunk,
					'paged'                  => $paged,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'fields'                 => 'ids',
					'ignore_sticky_posts'    => true,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				$post_id = (int) $post_id;
				$image   = function_exists( 'hovalvakil_lawyer_profile_image_url' )
					? (string) hovalvakil_lawyer_profile_image_url( $post_id, 'medium' )
					: (string) get_the_post_thumbnail_url( $post_id, 'medium' );
				$image   = trim( $image );
				$reason  = '';
				$status  = 0;

				if ( '' === $image ) {
					$reason = 'no_image_source';
				} else {
					$status = hovalvakil_get_image_url_status_code( $image );
					if ( 404 === $status ) {
						$reason = 'image_url_404';
					}
				}

				if ( '' === $reason ) {
					continue;
				}

				fputcsv(
					$out,
					[
						$post_id,
						(string) get_the_title( $post_id ),
						hovalvakil_placeholder_reason_label( $reason ),
						$image,
						$status,
						(string) get_edit_post_link( $post_id ),
						(string) get_permalink( $post_id ),
					]
				);
			}

			fflush( $out );
			$paged++;
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		update_option(
			'hovalvakil_home_image_report_last_file',
			[
				'path'         => $file_path,
				'url'          => $file_url,
				'generated_at' => current_time( 'mysql' ),
			],
			false
		);

		return [
			'ok'           => true,
			'path'         => $file_path,
			'url'          => $file_url,
			'generated_at' => current_time( 'mysql' ),
		];
	}
}

if ( ! function_exists( 'hovalvakil_placeholder_reason_label' ) ) {
	function hovalvakil_placeholder_reason_label( $reason ) {
		$map = [ 'no_image_source' => 'منبع عکس ندارد (مستقیم placeholder)', 'image_url_404' => 'لینک عکس 404 است (fallback با onerror)' ];
		return isset( $map[ $reason ] ) ? $map[ $reason ] : (string) $reason;
	}
}

if ( ! function_exists( 'hovalvakil_render_missing_thumbnail_admin_page' ) ) {
	function hovalvakil_render_missing_thumbnail_admin_page() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hello-elementor' ) );
		}

		$generated = null;
		if ( isset( $_GET['generate_csv'] ) && '1' === (string) $_GET['generate_csv'] ) {
			$generated = hovalvakil_generate_home_placeholder_csv_file();
		}
		$last_file = get_option( 'hovalvakil_home_image_report_last_file', [] );
		$page_url  = add_query_arg(
			[
				'post_type' => 'hvl_lawyer',
				'page'      => 'hvl-lawyers-without-thumbnail',
			],
			admin_url( 'edit.php' )
		);
		?>
		<div class="wrap">
			<h1>گزارش وکلای بدون عکس واقعی (Home)</h1>
			<p>برای جلوگیری از مصرف بالای RAM مرورگر، خروجی به فایل CSV روی سرور ساخته می‌شود.</p>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( [ 'generate_csv' => 1 ], $page_url ) ); ?>">
					ساخت فایل خروجی CSV روی سرور
				</a>
			</p>

			<?php if ( is_array( $generated ) && ! empty( $generated['ok'] ) ) : ?>
				<div class="notice notice-success"><p>فایل جدید ساخته شد:
					<a href="<?php echo esc_url( (string) $generated['url'] ); ?>" target="_blank" rel="noopener">دانلود فایل CSV</a>
				</p></div>
			<?php elseif ( is_array( $generated ) && isset( $generated['ok'] ) && empty( $generated['ok'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( (string) ( $generated['message'] ?? 'Failed to generate report file.' ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( is_array( $last_file ) && ! empty( $last_file['url'] ) ) : ?>
				<p>
					آخرین فایل ساخته‌شده:
					<a href="<?php echo esc_url( (string) $last_file['url'] ); ?>" target="_blank" rel="noopener">دانلود</a>
					<?php if ( ! empty( $last_file['generated_at'] ) ) : ?>
						(<?php echo esc_html( (string) $last_file['generated_at'] ); ?>)
					<?php endif; ?>
				</p>
			<?php else : ?>
				<p>هنوز فایلی ساخته نشده است.</p>
			<?php endif; ?>
		</div>
		<?php
	}
}

