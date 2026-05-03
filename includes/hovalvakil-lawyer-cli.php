<?php
/**
 * WP-CLI: bulk delete lawyers for clean re-import.
 *
 * Usage (from WordPress root, theme active):
 *   wp hovalvakil reset-lawyers --yes
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
			$ids = get_posts(
				[
					'post_type'              => 'hvl_lawyer',
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
				$thumb_id = (int) get_post_thumbnail_id( $post_id );
				if ( $thumb_id ) {
					wp_delete_attachment( $thumb_id, true );
				}
				if ( wp_delete_post( $post_id, true ) ) {
					$n++;
				}
			}
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
		'hovalvakil delete-products',
		[ new Hovalvakil_Lawyer_CLI_Command(), 'delete_products' ]
	);
}
