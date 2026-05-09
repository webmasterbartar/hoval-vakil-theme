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
		 * Backfill missing city/province terms for existing lawyers from address text.
		 *
		 * ## OPTIONS
		 *
		 * [--yes]
		 * : Skip confirmation.
		 *
		 * [--limit=<n>]
		 * : Max lawyers to process (default 2000).
		 *
		 * [--offset=<n>]
		 * : Start offset (default 0).
		 *
		 * [--dry-run]
		 * : Only report what would be updated.
		 *
		 * @param array $args Positional args.
		 * @param array $assoc_args Flags.
		 * @return void
		 */
		public function backfill_city_province( $args, $assoc_args ) {
			if ( ! function_exists( 'hovalvakil_backfill_lawyer_city_province_terms' ) ) {
				WP_CLI::error( 'تابع backfill بارگذاری نشد.' );
			}
			$dry_run = ! empty( $assoc_args['dry-run'] );
			if ( empty( $assoc_args['yes'] ) ) {
				WP_CLI::confirm( $dry_run ? 'Dry-run اجرا شود؟' : 'برای وکلای قبلی شهر/استان از آدرس استخراج و ست شود؟' );
			}
			$limit  = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 2000;
			$offset = isset( $assoc_args['offset'] ) ? max( 0, (int) $assoc_args['offset'] ) : 0;
			$out    = hovalvakil_backfill_lawyer_city_province_terms(
				[
					'limit'   => $limit,
					'offset'  => $offset,
					'dry_run' => $dry_run,
				]
			);
			WP_CLI::log( sprintf( 'پردازش‌شده: %d', (int) ( $out['processed'] ?? 0 ) ) );
			WP_CLI::log( sprintf( 'به‌روزرسانی‌شده: %d', (int) ( $out['updated'] ?? 0 ) ) );
			WP_CLI::log( sprintf( 'بدون نتیجه/خطا: %d', (int) ( $out['errors'] ?? 0 ) ) );
			WP_CLI::success( $dry_run ? 'Dry-run تمام شد.' : 'Backfill شهر/استان تمام شد.' );
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
			if ( ! function_exists( 'hovalvakil_reset_import_data_run' ) ) {
				WP_CLI::error( 'تابع پاک‌سازی بارگذاری نشد (hovalvakil-lawyer-reset-core).' );
			}
			$out = hovalvakil_reset_import_data_run(
				[
					'with_centers'       => ! empty( $assoc_args['with-centers'] ),
					'with_services_cpt'  => ! empty( $assoc_args['with-services-cpt'] ),
					'with_taxonomies'    => ! empty( $assoc_args['with-taxonomies'] ),
				]
			);
			WP_CLI::log( sprintf( 'نظرات (hvl_review): %d', (int) ( $out['hvl_review'] ?? 0 ) ) );
			WP_CLI::log( sprintf( 'رزروها (hvl_reservation): %d', (int) ( $out['hvl_reservation'] ?? 0 ) ) );
			WP_CLI::log( sprintf( 'وکلا (hvl_lawyer): %d', (int) ( $out['hvl_lawyer'] ?? 0 ) ) );
			if ( ! empty( $assoc_args['with-centers'] ) ) {
				WP_CLI::log( sprintf( 'مراکز (hvl_center): %d', (int) ( $out['hvl_center'] ?? 0 ) ) );
			}
			if ( ! empty( $assoc_args['with-services-cpt'] ) ) {
				WP_CLI::log( sprintf( 'تخصص CPT (hvl_service): %d', (int) ( $out['hvl_service'] ?? 0 ) ) );
			}
			if ( ! empty( $assoc_args['with-taxonomies'] ) ) {
				WP_CLI::log( sprintf( 'ترم تاکسونومی (شهر/استان/تخصص): %d', (int) ( $out['taxonomy_terms'] ?? 0 ) ) );
			}
			WP_CLI::success( sprintf( 'پاک‌سازی انجام شد. وکیل حذف‌شده: %d.', (int) ( $out['hvl_lawyer'] ?? 0 ) ) );
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
			if ( ! function_exists( 'hovalvakil_reset_lawyers_only_run' ) ) {
				WP_CLI::error( 'تابع پاک‌سازی بارگذاری نشد.' );
			}
			$n = hovalvakil_reset_lawyers_only_run();
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
			if ( ! function_exists( 'hovalvakil_reset_delete_wc_products' ) ) {
				WP_CLI::error( 'تابع پاک‌سازی بارگذاری نشد.' );
			}
			$n = hovalvakil_reset_delete_wc_products();
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
	WP_CLI::add_command(
		'hovalvakil backfill-city-province',
		[ new Hovalvakil_Lawyer_CLI_Command(), 'backfill_city_province' ]
	);
}
