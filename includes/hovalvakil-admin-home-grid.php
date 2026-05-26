<?php
/**
 * Admin: ترتیب نمایش وکلای گرید صفحهٔ اصلی (تهران).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return void
 */
function hovalvakil_register_home_grid_admin_menu(): void {
	add_submenu_page(
		'hovalvakil-lawyer-settings',
		__( 'ترتیب گرید صفحه اصلی', 'hello-elementor' ),
		__( 'ترتیب صفحه اصلی', 'hello-elementor' ),
		'manage_options',
		'hovalvakil-home-grid-order',
		'hovalvakil_render_home_grid_admin_page'
	);
}
add_action( 'admin_menu', 'hovalvakil_register_home_grid_admin_menu', 20 );

/**
 * Enqueue jQuery UI Sortable on the pin-order admin page.
 *
 * The submenu hook suffix can be unreliable (parent has Persian title), so detect
 * the page by checking `$_GET['page']` in addition to the hook string.
 *
 * @param string $hook Hook suffix.
 * @return void
 */
function hovalvakil_home_grid_admin_assets( string $hook ): void {
	$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$is_pin_page  = ( 'hovalvakil-home-grid-order' === $current_page )
		|| ( false !== strpos( $hook, 'hovalvakil-home-grid-order' ) );

	if ( ! $is_pin_page ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'jquery-ui-core' );
	wp_enqueue_script( 'jquery-ui-sortable' );
}
add_action( 'admin_enqueue_scripts', 'hovalvakil_home_grid_admin_assets' );

/**
 * Admin-side lawyer search for the pin list (independent of front-page search).
 *
 * Two-pass strategy:
 *   1) Direct title LIKE on wp_posts (fast, indexed-ish).
 *   2) If query is digit-like, additional license meta lookup.
 *
 * Falls back gracefully when query is mixed.
 *
 * @param string $q Persian / digit query.
 * @return int[]
 */
function hovalvakil_admin_home_grid_search_ids( $q ) {
	global $wpdb;

	$q = trim( (string) $q );
	if ( mb_strlen( $q ) < 2 ) {
		return [];
	}

	$ids = [];

	$title_like = '%' . $wpdb->esc_like( $q ) . '%';
	$title_sql  = $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = %s
		   AND post_status = %s
		   AND post_title LIKE %s
		 ORDER BY post_title ASC
		 LIMIT 30",
		'hvl_lawyer',
		'publish',
		$title_like
	);
	$rows = $wpdb->get_col( $title_sql );
	if ( is_array( $rows ) ) {
		foreach ( $rows as $r ) {
			$id = (int) $r;
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
	}

	$digits = preg_replace( '/[^0-9]/u', '', $q );
	if ( '' === $digits ) {
		$digits = preg_replace( '/\D+/u', '', strtr(
			$q,
			[
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			]
		) );
	}
	if ( strlen( (string) $digits ) >= 2 ) {
		$fa_digits = strtr(
			$digits,
			[
				'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
				'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
			]
		);

		$meta_sql = $wpdb->prepare(
			"SELECT DISTINCT pm.post_id
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			   AND p.post_type = %s
			   AND p.post_status = %s
			 WHERE pm.meta_key IN (%s, %s, %s)
			   AND (
				   pm.meta_value LIKE %s
				   OR pm.meta_value LIKE %s
				   OR pm.meta_value = %s
				   OR pm.meta_value = %s
			   )
			 LIMIT 30",
			'hvl_lawyer',
			'publish',
			'hvl_license_no',
			'hvl_license',
			'hvl_import_external_id',
			'%' . $wpdb->esc_like( $digits ) . '%',
			'%' . $wpdb->esc_like( $fa_digits ) . '%',
			$digits,
			$fa_digits
		);
		$rows = $wpdb->get_col( $meta_sql );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$id = (int) $r;
				if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
		}
	}

	return array_slice( $ids, 0, 30 );
}

/**
 * AJAX: search published lawyers (any province) for the pin list.
 *
 * @return void
 */
function hovalvakil_ajax_home_grid_search_lawyers(): void {
	check_ajax_referer( 'hovalvakil_home_grid', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		return;
	}

	$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$q = trim( $q );
	if ( mb_strlen( $q ) < 2 ) {
		wp_send_json_success( [ 'items' => [] ] );
		return;
	}

	$matched_ids = hovalvakil_admin_home_grid_search_ids( $q );

	if ( empty( $matched_ids ) ) {
		$wpq = new WP_Query(
			[
				'post_type'              => 'hvl_lawyer',
				'post_status'            => 'publish',
				's'                      => $q,
				'posts_per_page'         => 30,
				'no_found_rows'          => true,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'title',
				'order'                  => 'ASC',
			]
		);
		if ( ! empty( $wpq->posts ) ) {
			$matched_ids = array_map( 'intval', $wpq->posts );
		}
	}

	$matched_ids = array_values( array_unique( array_filter( array_map( 'intval', $matched_ids ) ) ) );
	$matched_ids = array_slice( $matched_ids, 0, 30 );

	$items = [];
	foreach ( $matched_ids as $pid ) {
		$post = get_post( $pid );
		if ( ! $post instanceof WP_Post || 'hvl_lawyer' !== $post->post_type || 'publish' !== $post->post_status ) {
			continue;
		}

		$img = function_exists( 'hovalvakil_lawyer_profile_image_url' )
			? hovalvakil_lawyer_profile_image_url( $pid, 'thumbnail' )
			: (string) get_the_post_thumbnail_url( $pid, 'thumbnail' );

		$license = trim( (string) get_post_meta( $pid, 'hvl_license_no', true ) );
		if ( '' === $license ) {
			$license = trim( (string) get_post_meta( $pid, 'hvl_license', true ) );
		}

		$prov_terms = get_the_terms( $pid, 'hvl_province' );
		$city_terms = get_the_terms( $pid, 'hvl_city' );
		$prov_name  = is_array( $prov_terms ) && ! empty( $prov_terms ) ? (string) $prov_terms[0]->name : '';
		$city_name  = is_array( $city_terms ) && ! empty( $city_terms ) ? (string) $city_terms[0]->name : '';
		$loc        = trim( $prov_name . ( '' !== $city_name ? ' / ' . $city_name : '' ) );

		$items[] = [
			'id'       => $pid,
			'title'    => get_the_title( $post ) ?: __( '(بدون عنوان)', 'hello-elementor' ),
			'image'    => (string) $img,
			'license'  => $license,
			'location' => $loc,
		];
	}

	wp_send_json_success( [ 'items' => $items ] );
}
add_action( 'wp_ajax_hovalvakil_home_grid_search', 'hovalvakil_ajax_home_grid_search_lawyers' );

/**
 * @return void
 */
function hovalvakil_render_home_grid_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved_notice = false;
	if ( isset( $_POST['hovalvakil_home_grid_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hovalvakil_home_grid_nonce'] ) ), 'hovalvakil_home_grid_save' ) ) {
		$raw = isset( $_POST['hvl_home_pinned_ids'] ) ? wp_unslash( $_POST['hvl_home_pinned_ids'] ) : [];
		$ids = [];
		if ( is_array( $raw ) ) {
			foreach ( $raw as $v ) {
				$ids[] = (int) $v;
			}
		}
		$ids = function_exists( 'hovalvakil_home_grid_sanitize_pinned_ids' )
			? hovalvakil_home_grid_sanitize_pinned_ids( $ids )
			: array_values( array_filter( array_map( 'intval', $ids ) ) );
		hovalvakil_home_grid_save_pinned_ids( $ids );
		$saved_notice = true;
	}

	$pinned = hovalvakil_home_grid_get_pinned_ids();
	$prov   = hovalvakil_home_grid_primary_province_slug();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ترتیب گرید وکلای صفحه اصلی', 'hello-elementor' ); ?></h1>

		<?php if ( $saved_notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'ترتیب ذخیره شد.', 'hello-elementor' ); ?></p></div>
		<?php endif; ?>

		<p class="description">
			<?php
			printf(
				/* translators: %s: province slug */
				esc_html__( 'گرید صفحهٔ اصلی فقط وکلای استان «%s» را نشان می‌دهد. وکلایی که اینجا اضافه می‌کنید در ابتدای لیست قرار می‌گیرند؛ بقیه مثل قبل (عکس‌دارها اول، سپس تاریخ) می‌آیند.', 'hello-elementor' ),
				esc_html( $prov )
			);
			?>
		</p>

		<form method="post" id="hovalvakil-home-grid-form">
			<?php wp_nonce_field( 'hovalvakil_home_grid_save', 'hovalvakil_home_grid_nonce' ); ?>

			<table class="widefat striped hvl-home-grid-table" style="max-width:820px;margin-top:1rem;">
				<thead>
					<tr>
						<th style="width:70px;"><?php esc_html_e( 'رتبه', 'hello-elementor' ); ?></th>
						<th style="width:64px;"></th>
						<th><?php esc_html_e( 'وکیل', 'hello-elementor' ); ?></th>
						<th style="width:90px;"></th>
					</tr>
				</thead>
				<tbody id="hovalvakil-home-grid-rows">
					<?php
					$rank = 0;
					foreach ( $pinned as $pid ) :
						$post = get_post( (int) $pid );
						if ( ! $post instanceof WP_Post ) {
							continue;
						}
						++$rank;
						$img = function_exists( 'hovalvakil_lawyer_profile_image_url' )
							? hovalvakil_lawyer_profile_image_url( (int) $pid, 'thumbnail' )
							: (string) get_the_post_thumbnail_url( (int) $pid, 'thumbnail' );
						$license = trim( (string) get_post_meta( (int) $pid, 'hvl_license_no', true ) );
						if ( '' === $license ) {
							$license = trim( (string) get_post_meta( (int) $pid, 'hvl_license', true ) );
						}
						$prov_terms = get_the_terms( (int) $pid, 'hvl_province' );
						$city_terms = get_the_terms( (int) $pid, 'hvl_city' );
						$prov_name  = is_array( $prov_terms ) && ! empty( $prov_terms ) ? (string) $prov_terms[0]->name : '';
						$city_name  = is_array( $city_terms ) && ! empty( $city_terms ) ? (string) $city_terms[0]->name : '';
						$loc        = trim( $prov_name . ( '' !== $city_name ? ' / ' . $city_name : '' ) );
						?>
						<tr class="hvl-home-grid-row">
							<td class="hvl-home-grid-rank"><span class="dashicons dashicons-menu" style="cursor:move;vertical-align:middle;"></span> <?php echo (int) $rank; ?></td>
							<td>
								<?php if ( $img ) : ?>
									<img src="<?php echo esc_url( $img ); ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;" />
								<?php else : ?>
									<span class="dashicons dashicons-businessperson" style="font-size:42px;width:48px;height:48px;line-height:48px;color:#a7aaad;"></span>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php echo esc_html( get_the_title( $post ) ?: __( '(بدون عنوان)', 'hello-elementor' ) ); ?></strong>
								<div style="color:#646970;font-size:12px;margin-top:2px;">
									<?php if ( '' !== $loc ) : ?><span><?php echo esc_html( $loc ); ?></span><?php endif; ?>
									<?php if ( '' !== $license ) : ?> · <span><?php esc_html_e( 'پروانه:', 'hello-elementor' ); ?> <?php echo esc_html( $license ); ?></span><?php endif; ?>
									 · ID: <?php echo (int) $pid; ?>
								</div>
								<input type="hidden" name="hvl_home_pinned_ids[]" value="<?php echo (int) $pid; ?>" />
							</td>
							<td><button type="button" class="button-link-delete hvl-home-grid-remove"><?php esc_html_e( 'حذف', 'hello-elementor' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p style="margin-top:1rem;max-width:820px;">
				<label for="hvl-home-grid-search"><strong><?php esc_html_e( 'افزودن وکیل', 'hello-elementor' ); ?></strong></label><br />
				<input type="search" id="hvl-home-grid-search" class="regular-text" placeholder="<?php esc_attr_e( 'نام، شماره پروانه یا شهر…', 'hello-elementor' ); ?>" autocomplete="off" style="max-width:360px;" />
				<button type="button" class="button" id="hvl-home-grid-search-btn"><?php esc_html_e( 'جستجو', 'hello-elementor' ); ?></button>
				<span class="description" style="display:block;margin-top:.35rem;color:#646970;"><?php esc_html_e( 'حداقل ۲ کاراکتر تایپ کنید. وکلای همهٔ استان‌ها قابل افزودن هستند.', 'hello-elementor' ); ?></span>
			</p>
			<ul id="hvl-home-grid-search-results" style="max-width:820px;list-style:none;margin:0;padding:0;"></ul>

			<?php submit_button( __( 'ذخیره ترتیب', 'hello-elementor' ) ); ?>
		</form>
	</div>

	<script>
	(function ($) {
		const $tbody = $('#hovalvakil-home-grid-rows');
		const $results = $('#hvl-home-grid-search-results');
		const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		const nonce = <?php echo wp_json_encode( wp_create_nonce( 'hovalvakil_home_grid' ) ); ?>;

		function refreshRanks() {
			$tbody.find('.hvl-home-grid-row').each(function (i) {
				$(this).find('.hvl-home-grid-rank').html(
					'<span class="dashicons dashicons-menu" style="cursor:move;vertical-align:middle;"></span> ' + (i + 1)
				);
			});
		}

		function pinnedIds() {
			const ids = [];
			$tbody.find('input[name="hvl_home_pinned_ids[]"]').each(function () {
				const v = parseInt($(this).val(), 10);
				if (v > 0) ids.push(v);
			});
			return ids;
		}

		function addRow(item) {
			const id = parseInt(item.id, 10);
			if (!id || pinnedIds().indexOf(id) !== -1) return;
			const title = item.title || '';
			const img = item.image || '';
			const license = item.license || '';
			const location = item.location || '';

			const $row = $('<tr class="hvl-home-grid-row"></tr>');
			$row.append('<td class="hvl-home-grid-rank"></td>');

			const $imgCell = $('<td></td>');
			if (img) {
				$('<img />')
					.attr('src', img)
					.attr('alt', '')
					.css({ width: '48px', height: '48px', borderRadius: '50%', objectFit: 'cover' })
					.appendTo($imgCell);
			} else {
				$('<span class="dashicons dashicons-businessperson"></span>')
					.css({ fontSize: '42px', width: '48px', height: '48px', lineHeight: '48px', color: '#a7aaad' })
					.appendTo($imgCell);
			}
			$row.append($imgCell);

			const $infoCell = $('<td></td>');
			$('<strong></strong>').text(title).appendTo($infoCell);
			const metaBits = [];
			if (location) metaBits.push(location);
			if (license) metaBits.push('<?php echo esc_js( __( 'پروانه:', 'hello-elementor' ) ); ?> ' + license);
			metaBits.push('ID: ' + id);
			$('<div></div>')
				.css({ color: '#646970', fontSize: '12px', marginTop: '2px' })
				.text(metaBits.join(' · '))
				.appendTo($infoCell);
			$('<input type="hidden" name="hvl_home_pinned_ids[]" />').val(id).appendTo($infoCell);
			$row.append($infoCell);

			$row.append('<td><button type="button" class="button-link-delete hvl-home-grid-remove"><?php echo esc_js( __( 'حذف', 'hello-elementor' ) ); ?></button></td>');
			$tbody.append($row);
			refreshRanks();
		}

		if (typeof $tbody.sortable === 'function') {
			$tbody.sortable({
				handle: '.dashicons-menu',
				axis: 'y',
				update: refreshRanks,
			});
		} else if (window.console && console.warn) {
			console.warn('[hvl-pin] jQuery UI Sortable not loaded; drag-reorder disabled.');
		}

		$tbody.on('click', '.hvl-home-grid-remove', function () {
			$(this).closest('tr').remove();
			refreshRanks();
		});

		let searchRequest = null;
		let searchToken = 0;
		let searchTimer = null;

		function runSearch() {
			const q = ($('#hvl-home-grid-search').val() || '').trim();
			if (q.length < 2) {
				$results.empty();
				return;
			}

			const token = ++searchToken;
			$results.html('<li style="padding:6px 0;color:#646970;"><?php echo esc_js( __( 'در حال جستجو…', 'hello-elementor' ) ); ?></li>');

			if (searchRequest && searchRequest.abort) {
				try { searchRequest.abort(); } catch (e) {}
			}

			searchRequest = $.ajax({
				url: ajaxUrl,
				method: 'GET',
				dataType: 'json',
				cache: false,
				data: { action: 'hovalvakil_home_grid_search', nonce: nonce, q: q },
			})
				.done(function (res) {
					if (token !== searchToken) return;
					$results.empty();
					if (window.console && console.log) {
						console.log('[hvl-pin-search] response:', res);
					}
					if (!res || !res.success || !res.data || !Array.isArray(res.data.items) || !res.data.items.length) {
						$results.html('<li style="padding:8px 0;color:#646970;"><?php echo esc_js( __( 'موردی یافت نشد. عبارت دیگری امتحان کنید.', 'hello-elementor' ) ); ?></li>');
						return;
					}
					res.data.items.forEach(function (item) {
						const $li = $('<li></li>').css({
							padding: '8px 0',
							borderBottom: '1px solid #dcdcde',
							display: 'flex',
							alignItems: 'center',
							gap: '10px',
						});
						const $imgWrap = $('<span></span>').css({ flex: '0 0 40px' });
						if (item.image) {
							$('<img />')
								.attr('src', item.image)
								.attr('alt', '')
								.css({ width: '40px', height: '40px', borderRadius: '50%', objectFit: 'cover' })
								.appendTo($imgWrap);
						} else {
							$('<span class="dashicons dashicons-businessperson"></span>')
								.css({ fontSize: '36px', width: '40px', height: '40px', lineHeight: '40px', color: '#a7aaad' })
								.appendTo($imgWrap);
						}
						$li.append($imgWrap);

						const $info = $('<div></div>').css({ flex: '1 1 auto', minWidth: 0 });
						$('<div></div>').css({ fontWeight: '600' }).text(item.title || '').appendTo($info);
						const metaBits = [];
						if (item.location) metaBits.push(item.location);
						if (item.license) metaBits.push('<?php echo esc_js( __( 'پروانه:', 'hello-elementor' ) ); ?> ' + item.license);
						metaBits.push('ID: ' + item.id);
						$('<div></div>')
							.css({ color: '#646970', fontSize: '12px', marginTop: '2px' })
							.text(metaBits.join(' · '))
							.appendTo($info);
						$li.append($info);

						const $btn = $('<button type="button" class="button button-small"></button>')
							.text('<?php echo esc_js( __( 'افزودن', 'hello-elementor' ) ); ?>')
							.on('click', function () {
								addRow(item);
								$results.empty();
								$('#hvl-home-grid-search').val('').focus();
							});
						$li.append($btn);
						$results.append($li);
					});
				})
				.fail(function (jqXHR, textStatus, errorThrown) {
					if (token !== searchToken) return;
					if (textStatus === 'abort') return;
					if (window.console && console.error) {
						console.error('[hvl-pin-search] AJAX failed', {
							status: jqXHR ? jqXHR.status : null,
							statusText: jqXHR ? jqXHR.statusText : null,
							responseText: jqXHR ? jqXHR.responseText : null,
							textStatus: textStatus,
							errorThrown: errorThrown,
						});
					}
					const msg = jqXHR && jqXHR.status
						? '<?php echo esc_js( __( 'خطا در جستجو', 'hello-elementor' ) ); ?> (HTTP ' + jqXHR.status + ')'
						: '<?php echo esc_js( __( 'خطا در ارتباط با سرور.', 'hello-elementor' ) ); ?>';
					$results.html('<li style="padding:8px 0;color:#b32d2e;">' + msg + '</li>');
				});
		}

		function scheduleSearch() {
			if (searchTimer) clearTimeout(searchTimer);
			searchTimer = setTimeout(runSearch, 250);
		}

		$('#hvl-home-grid-search-btn').on('click', function () {
			if (searchTimer) { clearTimeout(searchTimer); searchTimer = null; }
			runSearch();
		});
		$('#hvl-home-grid-search').on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				if (searchTimer) { clearTimeout(searchTimer); searchTimer = null; }
				runSearch();
			}
		});
		$('#hvl-home-grid-search').on('input', scheduleSearch);

		refreshRanks();
	})(jQuery);
	</script>
	<?php
}
