<?php
/**
 * Admin: list lawyers with the same license (hvl_license_no) and the same name (post title).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hovalvakil_register_duplicate_license_admin_page' ) ) {
	/**
	 * Register submenu under Lawyers.
	 *
	 * @return void
	 */
	function hovalvakil_register_duplicate_license_admin_page() {
		add_submenu_page(
			'edit.php?post_type=hvl_lawyer',
			'تکرار پروانه + نام',
			'تکرار پروانه + نام',
			'edit_others_posts',
			'hvl-lawyer-duplicate-license',
			'hovalvakil_render_duplicate_license_admin_page'
		);
	}
	add_action( 'admin_menu', 'hovalvakil_register_duplicate_license_admin_page' );
}

if ( ! function_exists( 'hovalvakil_normalize_lawyer_title_for_duplicate_match' ) ) {
	/**
	 * Normalize post title so “same name” ignores extra spaces / HTML / Latin letter case.
	 *
	 * @param string $title Raw post title.
	 * @return string
	 */
	function hovalvakil_normalize_lawyer_title_for_duplicate_match( $title ) {
		$t = wp_strip_all_tags( (string) $title );
		$t = trim( preg_replace( '/\s+/u', ' ', $t ) );
		if ( function_exists( 'mb_strtolower' ) ) {
			$t = mb_strtolower( $t, 'UTF-8' );
		} else {
			$t = strtolower( $t );
		}
		return $t;
	}
}

if ( ! function_exists( 'hovalvakil_get_duplicate_license_groups' ) ) {
	/**
	 * Find groups where the same non-empty license and the same normalized title appear on more than one lawyer post.
	 *
	 * Uses one simple SQL query and groups in PHP so MySQL/MariaDB version differences
	 * (GROUP BY TRIM, ONLY_FULL_GROUP_BY) cannot break the admin page.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	function hovalvakil_get_duplicate_license_groups() {
		global $wpdb;

		$meta_key = 'hvl_license_no';

		$sql = $wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value, p.post_title
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				AND p.post_type = 'hvl_lawyer'
				AND p.post_status NOT IN ('trash', 'auto-draft')
			WHERE pm.meta_key = %s",
			$meta_key
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) || $wpdb->last_error ) {
			return [];
		}

		// JSON key [license_trim, normalized_title] => set of post_id.
		$buckets = [];
		foreach ( $rows as $row ) {
			$pid = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $pid <= 0 ) {
				continue;
			}
			$trimmed = trim( (string) ( $row['meta_value'] ?? '' ) );
			if ( '' === $trimmed ) {
				continue;
			}
			$norm_title = hovalvakil_normalize_lawyer_title_for_duplicate_match( (string) ( $row['post_title'] ?? '' ) );
			if ( '' === $norm_title ) {
				continue;
			}
			$key = wp_json_encode( [ $trimmed, $norm_title ], JSON_UNESCAPED_UNICODE );
			if ( ! isset( $buckets[ $key ] ) ) {
				$buckets[ $key ] = [];
			}
			$buckets[ $key ][ $pid ] = true;
		}

		$groups = [];
		foreach ( $buckets as $bucket_key => $id_set ) {
			if ( count( $id_set ) <= 1 ) {
				continue;
			}
			$decoded = json_decode( (string) $bucket_key, true );
			$license_no = is_array( $decoded ) && isset( $decoded[0] ) ? (string) $decoded[0] : '';

			$post_ids = array_map( 'intval', array_keys( $id_set ) );
			sort( $post_ids );

			$items = [];
			foreach ( $post_ids as $post_id ) {
				$items[] = [
					'id'     => $post_id,
					'title'  => get_the_title( $post_id ),
					'status' => get_post_status( $post_id ),
				];
			}

			$display_name = $items[0]['title'] ?? '';

			$groups[] = [
				'license_no' => $license_no,
				'name'       => $display_name,
				'count'      => count( $items ),
				'items'      => $items,
			];
		}

		usort(
			$groups,
			function ( $a, $b ) {
				$c = strcmp( (string) ( $a['license_no'] ?? '' ), (string) ( $b['license_no'] ?? '' ) );
				if ( 0 !== $c ) {
					return $c;
				}
				return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
			}
		);

		return $groups;
	}
}

if ( ! function_exists( 'hovalvakil_duplicate_license_collect_post_ids' ) ) {
	/**
	 * Unique post IDs in the duplicate report (license + name).
	 *
	 * @return int[]
	 */
	function hovalvakil_duplicate_license_collect_post_ids() {
		$groups = hovalvakil_get_duplicate_license_groups();
		$ids    = [];
		foreach ( $groups as $group ) {
			foreach ( (array) ( $group['items'] ?? [] ) as $item ) {
				$ids[] = (int) ( $item['id'] ?? 0 );
			}
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		sort( $ids );
		return $ids;
	}
}

if ( ! function_exists( 'hovalvakil_handle_bulk_delete_duplicate_lawyers' ) ) {
	/**
	 * POST admin-post: delete every lawyer listed in the duplicate (license + name) report.
	 *
	 * @return void
	 */
	function hovalvakil_handle_bulk_delete_duplicate_lawyers() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'hvl_bulk_delete_dup_lawyers' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'hello-elementor' ), 403 );
		}
		if ( ! current_user_can( 'delete_others_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hello-elementor' ), 403 );
		}

		$force = isset( $_POST['hvl_force_delete'] ) && '1' === sanitize_key( wp_unslash( $_POST['hvl_force_delete'] ) );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$post_ids = hovalvakil_duplicate_license_collect_post_ids();

		$deleted = 0;
		$skipped = 0;
		foreach ( $post_ids as $post_id ) {
			if ( $post_id <= 0 ) {
				continue;
			}
			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				++$skipped;
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post || 'hvl_lawyer' !== $post->post_type ) {
				++$skipped;
				continue;
			}
			$result = wp_delete_post( $post_id, $force );
			if ( $result ) {
				++$deleted;
			} else {
				++$skipped;
			}
		}

		$url = add_query_arg(
			[
				'post_type'        => 'hvl_lawyer',
				'page'             => 'hvl-lawyer-duplicate-license',
				'hvl_dup_deleted'  => $deleted,
				'hvl_dup_skipped'  => $skipped,
				'hvl_dup_permanent' => $force ? '1' : '0',
			],
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
	add_action( 'admin_post_hvl_bulk_delete_duplicate_lawyers', 'hovalvakil_handle_bulk_delete_duplicate_lawyers' );
}

if ( ! function_exists( 'hovalvakil_render_duplicate_license_admin_page' ) ) {
	/**
	 * Render admin page and optional CSV download.
	 *
	 * @return void
	 */
	function hovalvakil_render_duplicate_license_admin_page() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hello-elementor' ) );
		}

		if ( ! empty( $_GET['export'] ) && 'csv' === sanitize_key( wp_unslash( $_GET['export'] ) ) ) {
			hovalvakil_duplicate_license_export_csv();
			return;
		}

		$groups = hovalvakil_get_duplicate_license_groups();
		$total_rows = 0;
		foreach ( $groups as $g ) {
			$total_rows += (int) ( $g['count'] ?? 0 );
		}

		$export_url = add_query_arg(
			[
				'post_type' => 'hvl_lawyer',
				'page'      => 'hvl-lawyer-duplicate-license',
				'export'    => 'csv',
			],
			admin_url( 'edit.php' )
		);

		$deleted_notice  = isset( $_GET['hvl_dup_deleted'] ) ? (int) $_GET['hvl_dup_deleted'] : null;
		$skipped_notice  = isset( $_GET['hvl_dup_skipped'] ) ? (int) $_GET['hvl_dup_skipped'] : null;
		$permanent_notice = isset( $_GET['hvl_dup_permanent'] ) ? sanitize_key( wp_unslash( $_GET['hvl_dup_permanent'] ) ) : '';
		?>
		<div class="wrap">
			<h1>وکیل‌های با پروانه و نام یکسان</h1>
			<?php if ( null !== $deleted_notice && null !== $skipped_notice && current_user_can( 'delete_others_posts' ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: deleted count, 2: skipped count */
								__( 'حذف شد: %1$d مورد. رد شد: %2$d.', 'hello-elementor' ),
								$deleted_notice,
								$skipped_notice
							)
						);
						?>
						<?php if ( '1' === $permanent_notice ) : ?>
							<?php esc_html_e( '(حذف دائمی)', 'hello-elementor' ); ?>
						<?php else : ?>
							<?php esc_html_e( '(به زباله‌دان منتقل شد؛ در صورت نیاز خالی کنید.)', 'hello-elementor' ); ?>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>
			<p class="description">
				<?php esc_html_e( 'فقط رکوردهایی که هم شماره پروانه و هم عنوان (نام) پست پس از حذف فاصله‌های اضافه یکی باشد.', 'hello-elementor' ); ?>
			</p>
			<p>
				<?php echo esc_html( sprintf( 'تعداد گروه‌های تکراری: %d | مجموع رکوردها در این گروه‌ها: %d', count( $groups ), $total_rows ) ); ?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'دانلود CSV', 'hello-elementor' ); ?></a>
			</p>

			<?php if ( empty( $groups ) ) : ?>
				<p><?php esc_html_e( 'هیچ موردی با پروانه و نام یکسان یافت نشد.', 'hello-elementor' ); ?></p>
			<?php else : ?>
				<?php if ( current_user_can( 'delete_others_posts' ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:1em 0;padding:12px;background:#fff;border:1px solid #c3c4c7;max-width:960px;">
						<?php wp_nonce_field( 'hvl_bulk_delete_dup_lawyers' ); ?>
						<input type="hidden" name="action" value="hvl_bulk_delete_duplicate_lawyers" />
						<p><strong><?php esc_html_e( 'حذف گروهی همهٔ رکوردهای همین گزارش', 'hello-elementor' ); ?></strong></p>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of posts */
									__( '%d پست وکیل (همهٔ ردیف‌های جدول زیر) حذف می‌شود.', 'hello-elementor' ),
									$total_rows
								)
							);
							?>
						</p>
						<p>
							<label>
								<input type="checkbox" name="hvl_force_delete" value="1" />
								<?php esc_html_e( 'حذف دائمی (بدون زباله‌دان)', 'hello-elementor' ); ?>
							</label>
						</p>
						<p>
							<button type="submit" class="button button-link-delete" onclick="return window.confirm(<?php echo esc_js( __( 'همهٔ این رکوردها حذف شوند؟ این کار برگشت‌پذیر نیست مگر از زباله‌دان.', 'hello-elementor' ) ); ?>);">
								<?php esc_html_e( 'حذف همه', 'hello-elementor' ); ?>
							</button>
						</p>
					</form>
				<?php endif; ?>

				<?php foreach ( $groups as $group ) : ?>
					<h2 style="margin-top:1.5em;">
						<?php
						echo esc_html(
							sprintf(
								'پروانه: %s — نام: %s (%d رکورد)',
								(string) ( $group['license_no'] ?? '' ),
								(string) ( $group['name'] ?? '' ),
								(int) ( $group['count'] ?? 0 )
							)
						);
						?>
					</h2>
					<table class="widefat striped" style="max-width:960px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'شناسه', 'hello-elementor' ); ?></th>
								<th><?php esc_html_e( 'نام', 'hello-elementor' ); ?></th>
								<th><?php esc_html_e( 'وضعیت', 'hello-elementor' ); ?></th>
								<th><?php esc_html_e( 'ویرایش', 'hello-elementor' ); ?></th>
								<th><?php esc_html_e( 'حذف', 'hello-elementor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( (array) ( $group['items'] ?? [] ) as $item ) : ?>
								<?php
								$pid       = (int) ( $item['id'] ?? 0 );
								$edit_link = get_edit_post_link( $pid );
								$del_link  = get_delete_post_link( $pid, '', true );
								?>
								<tr>
									<td><?php echo esc_html( (string) $pid ); ?></td>
									<td><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $item['status'] ?? '' ) ); ?></td>
									<td>
										<?php if ( $edit_link ) : ?>
											<a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'ویرایش', 'hello-elementor' ); ?></a>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $del_link ) : ?>
											<a href="<?php echo esc_url( $del_link ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'حذف این وکیل؟', 'hello-elementor' ) ); ?>');">
												<?php esc_html_e( 'حذف', 'hello-elementor' ); ?>
											</a>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'hovalvakil_duplicate_license_export_csv' ) ) {
	/**
	 * Stream CSV of duplicate groups.
	 *
	 * @return void
	 */
	function hovalvakil_duplicate_license_export_csv() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hello-elementor' ) );
		}

		$groups = hovalvakil_get_duplicate_license_groups();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="hvl-duplicate-license-name-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		if ( ! $out ) {
			exit;
		}

		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, [ 'license_no', 'group_title', 'post_id', 'title', 'post_status', 'edit_url', 'delete_url' ] );

		foreach ( $groups as $group ) {
			$license = (string) ( $group['license_no'] ?? '' );
			$gtitle  = (string) ( $group['name'] ?? '' );
			foreach ( $group['items'] as $item ) {
				$pid = (int) ( $item['id'] ?? 0 );
				$el = get_edit_post_link( $pid );
				$dl = get_delete_post_link( $pid, '', true );
				fputcsv(
					$out,
					[
						$license,
						$gtitle,
						$pid,
						(string) ( $item['title'] ?? '' ),
						(string) ( $item['status'] ?? '' ),
						$el ? (string) $el : '',
						$dl ? (string) $dl : '',
					]
				);
			}
		}

		fclose( $out );
		exit;
	}
}
