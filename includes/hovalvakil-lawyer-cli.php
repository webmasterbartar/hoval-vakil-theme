<?php
/**
 * WP-CLI: bulk delete lawyers and related data for clean re-import.
 *
 * Usage (from WordPress root, theme active):
 *   wp hovalvakil reset-lawyers --yes
 *   wp hovalvakil reset-import-data --yes
 *   wp hovalvakil reset-import-data --yes --with-centers --with-services-cpt --with-taxonomies
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Hovalvakil maintenance commands.
	 */
	class Hovalvakil_Lawyer_CLI_Command {

		/**
		 * Delete all posts of a type (optional featured image per post).
		 *
		 * @param string $post_type Post type slug.
		 * @param bool   $delete_thumb Whether to delete featured image attachment.
		 * @return int Number deleted.
		 */
		private function delete_all_of_type( $post_type, $delete_thumb = true ) {
			$post_type = sanitize_key( (string) $post_type );
			if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
				return 0;
			}
			$ids = get_posts(
				[
					'post_type'              => $post_type,
					'post_status'            => 'any',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
				]
			);
			$n = 0;
			foreach ( $ids as $post_id ) {
				$post_id = (int) $post_id;
				if ( $delete_thumb ) {
					$thumb_id = (int) get_post_thumbnail_id( $post_id );
					if ( $thumb_id ) {
						wp_delete_attachment( $thumb_id, true );
					}
				}
				if ( wp_delete_post( $post_id, true ) ) {
					$n++;
				}
			}
			return $n;
		}

		/**
		 * Remove all terms in given taxonomies (empty term tables for re-import).
		 *
		 * @param string[] $taxonomies Taxonomy slugs.
		 * @return int Terms deleted.
		 */
		private function delete_all_terms_in_taxonomies( array $taxonomies ) {
			$total = 0;
			foreach ( $taxonomies as $tax ) {
				$tax = sanitize_key( (string) $tax );
				if ( '' === $tax || ! taxonomy_exists( $tax ) ) {
					continue;
				}
				$terms = get_terms(
					[
						'taxonomy'   => $tax,
						'hide_empty' => false,
						'fields'     => 'ids',
					]
				);
				if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term_id ) {
					$r = wp_delete_term( (int) $term_id, $tax );
					if ( ! is_wp_error( $r ) && false !== $r ) {
						$total++;
					}
				}
			}
			return $total;
		}

		/**
		 * Full reset for lawyer import: reviews, reservations, lawyers (+ thumbs).
		 *
		 * ## OPTIONS
		 *
		 * [--yes]
		 * : Skip confirmation.
		 *
		 * [--with-centers]
		 * : Also delete all hvl_center posts and their featured images.
		 *
		 * [--with-services-cpt]
		 * : Also delete all hvl_service (CPT) posts and their featured images.
		 *
		 * [--with-taxonomies]
		 * : Also delete ALL terms in hvl_city, hvl_province, hvl_specialty (use after centers if needed).
		 *
		 * @param array $args Positional args.
		 * @param array $assoc_args Flags.
		 * @return void
		 */
		public function reset_import_data( $args, $assoc_args ) {
			if ( empty( $assoc_args['yes'] ) ) {
				WP_CLI::confirm( 'رزروها، نظرات و همهٔ وکلا (و تصویر شاخص) حذف شوند؟ در صورت فلگ‌های اضافی، مراکز/تخصص CPT/تاکسونومی هم پاک می‌شود.' );
			}

			$n_rev = $this->delete_all_of_type( 'hvl_review', false );
			WP_CLI::log( sprintf( 'نظرات (hvl_review): %d', $n_rev ) );

			$n_res = $this->delete_all_of_type( 'hvl_reservation', false );
			WP_CLI::log( sprintf( 'رزروها (hvl_reservation): %d', $n_res ) );

			$n_law = $this->delete_all_of_type( 'hvl_lawyer', true );
			WP_CLI::log( sprintf( 'وکلا (hvl_lawyer): %d', $n_law ) );

			if ( ! empty( $assoc_args['with-centers'] ) ) {
				$n_c = $this->delete_all_of_type( 'hvl_center', true );
				WP_CLI::log( sprintf( 'مراکز (hvl_center): %d', $n_c ) );
			}

			if ( ! empty( $assoc_args['with-services-cpt'] ) ) {
				$n_s = $this->delete_all_of_type( 'hvl_service', true );
				WP_CLI::log( sprintf( 'تخصص CPT (hvl_service): %d', $n_s ) );
			}

			if ( ! empty( $assoc_args['with-taxonomies'] ) ) {
				$n_t = $this->delete_all_terms_in_taxonomies( [ 'hvl_city', 'hvl_province', 'hvl_specialty' ] );
				WP_CLI::log( sprintf( 'ترم تاکسونومی (شهر/استان/تخصص): %d', $n_t ) );
			}

			WP_CLI::success(
				sprintf(
					'پاک‌سازی انجام شد. وکیل: %d؛ مجموع مراحل بالا را در لاگ ببینید.',
					$n_law
				)
			);
		}

		/**
		 * Delete all hvl_lawyer posts (and their featured attachments).
		 *
		 * ## OPTIONS
		 *
		 * [--yes]
		 * : Skip confirmation.
		 *
		 * @param array $args Positional args.
		 * @param array $assoc_args Flags.
		 * @return void
		 */
		public function reset_lawyers( $args, $assoc_args ) {
			if ( empty( $assoc_args['yes'] ) ) {
				WP_CLI::confirm( 'همهٔ پست‌های نوع hvl_lawyer و تصویر شاخص هر کدام حذف شود؟' );
			}
			$n = $this->delete_all_of_type( 'hvl_lawyer', true );
			WP_CLI::success( sprintf( 'حذف شد: %d وکیل.', $n ) );
		}

		/**
		 * Delete all WooCommerce products and variations (fast bulk delete).
		 *
		 * ## OPTIONS
		 *
		 * [--yes]
		 * : Skip confirmation.
		 *
		 * @param array $args Positional args.
		 * @param array $assoc_args Flags.
		 * @return void
		 */
		public function delete_products( $args, $assoc_args ) {
			if ( ! post_type_exists( 'product' ) ) {
				WP_CLI::warning( 'نوع پست product وجود ندارد (ووکامرس نصب یا فعال نیست؟).' );
				return;
			}
			if ( empty( $assoc_args['yes'] ) ) {
				WP_CLI::confirm( 'همهٔ محصولات ووکامرس (شامل متغیرها) برای همیشه حذف شوند؟' );
			}
			$variation_ids = get_posts(
				[
					'post_type'              => 'product_variation',
					'post_status'            => 'any',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
				]
			);
			$product_ids = get_posts(
				[
					'post_type'              => 'product',
					'post_status'            => 'any',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
				]
			);
			$n = 0;
			foreach ( $variation_ids as $vid ) {
				if ( wp_delete_post( (int) $vid, true ) ) {
					$n++;
				}
			}
			foreach ( $product_ids as $pid ) {
				if ( wp_delete_post( (int) $pid, true ) ) {
					$n++;
				}
			}
			WP_CLI::success( sprintf( 'حذف شد: %d رکورد محصول/متغیر.', $n ) );
		}
	}

	WP_CLI::add_command(
		'hovalvakil reset-lawyers',
		[ new Hovalvakil_Lawyer_CLI_Command(), 'reset_lawyers' ]
	);
	WP_CLI::add_command(
		'hovalvakil reset-import-data',
		[ new Hovalvakil_Lawyer_CLI_Command(), 'reset_import_data' ]
	);
	WP_CLI::add_command(
		'hovalvakil delete-products',
		[ new Hovalvakil_Lawyer_CLI_Command(), 'delete_products' ]
	);
}
