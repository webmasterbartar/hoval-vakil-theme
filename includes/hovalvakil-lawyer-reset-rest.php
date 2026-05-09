<?php
/**
 * REST: maintenance reset (no WP-CLI required). Admins only.
 *
 * POST /wp-json/hovalvakil/v1/reset-import-data
 * Body JSON: { "confirm": "DELETE", "with_centers": false, "with_services_cpt": false, "with_taxonomies": false }
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return bool
 */
function hovalvakil_reset_rest_can_manage() {
	return (bool) apply_filters(
		'hovalvakil_reset_rest_can_manage',
		current_user_can( 'manage_options' )
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function hovalvakil_rest_post_reset_import_data( WP_REST_Request $request ) {
	if ( ! hovalvakil_reset_rest_can_manage() ) {
		return new WP_Error(
			'hvl_reset_forbidden',
			'مجوز انجام این عملیات را ندارید.',
			[ 'status' => 403 ]
		);
	}
	if ( ! function_exists( 'hovalvakil_reset_import_data_run' ) ) {
		return new WP_Error(
			'hvl_reset_missing',
			'ماژول پاک‌سازی در تم بارگذاری نشده است.',
			[ 'status' => 500 ]
		);
	}

	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		$params = [];
	}
	if ( ( $params['confirm'] ?? '' ) !== 'DELETE' ) {
		return new WP_Error(
			'hvl_reset_confirm',
			'در بدنهٔ JSON مقدار "confirm": "DELETE" الزامی است.',
			[ 'status' => 400 ]
		);
	}

	$opts = [
		'with_centers'      => ! empty( $params['with_centers'] ),
		'with_services_cpt' => ! empty( $params['with_services_cpt'] ),
		'with_taxonomies'   => ! empty( $params['with_taxonomies'] ),
	];

	$deleted = hovalvakil_reset_import_data_run( $opts );

	return rest_ensure_response(
		[
			'ok'      => true,
			'deleted' => $deleted,
		]
	);
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function hovalvakil_rest_post_reset_lawyers_only( WP_REST_Request $request ) {
	if ( ! hovalvakil_reset_rest_can_manage() ) {
		return new WP_Error( 'hvl_reset_forbidden', 'مجوز انجام این عملیات را ندارید.', [ 'status' => 403 ] );
	}
	if ( ! function_exists( 'hovalvakil_reset_lawyers_only_run' ) ) {
		return new WP_Error( 'hvl_reset_missing', 'ماژول پاک‌سازی در تم بارگذاری نشده است.', [ 'status' => 500 ] );
	}
	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		$params = [];
	}
	if ( ( $params['confirm'] ?? '' ) !== 'DELETE' ) {
		return new WP_Error( 'hvl_reset_confirm', 'در بدنهٔ JSON مقدار "confirm": "DELETE" الزامی است.', [ 'status' => 400 ] );
	}
	$n = hovalvakil_reset_lawyers_only_run();
	return rest_ensure_response( [ 'ok' => true, 'deleted' => [ 'hvl_lawyer' => $n ] ] );
}

/**
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function hovalvakil_rest_post_delete_wc_products( WP_REST_Request $request ) {
	if ( ! hovalvakil_reset_rest_can_manage() ) {
		return new WP_Error( 'hvl_reset_forbidden', 'مجوز انجام این عملیات را ندارید.', [ 'status' => 403 ] );
	}
	if ( ! function_exists( 'hovalvakil_reset_delete_wc_products' ) ) {
		return new WP_Error( 'hvl_reset_missing', 'ماژول پاک‌سازی در تم بارگذاری نشده است.', [ 'status' => 500 ] );
	}
	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		$params = [];
	}
	if ( ( $params['confirm'] ?? '' ) !== 'DELETE' ) {
		return new WP_Error( 'hvl_reset_confirm', 'در بدنهٔ JSON مقدار "confirm": "DELETE" الزامی است.', [ 'status' => 400 ] );
	}
	if ( ! post_type_exists( 'product' ) ) {
		return rest_ensure_response( [ 'ok' => true, 'deleted' => 0, 'message' => 'ووکامرس یا نوع product وجود ندارد.' ] );
	}
	$n = hovalvakil_reset_delete_wc_products();
	return rest_ensure_response( [ 'ok' => true, 'deleted' => $n ] );
}

/**
 * @return void
 */
function hovalvakil_register_lawyer_reset_rest_routes() {
	register_rest_route(
		'hovalvakil/v1',
		'/reset-import-data',
		[
			'methods'             => 'POST',
			'callback'            => 'hovalvakil_rest_post_reset_import_data',
			'permission_callback' => static function () {
				return hovalvakil_reset_rest_can_manage();
			},
		]
	);
	register_rest_route(
		'hovalvakil/v1',
		'/reset-lawyers',
		[
			'methods'             => 'POST',
			'callback'            => 'hovalvakil_rest_post_reset_lawyers_only',
			'permission_callback' => static function () {
				return hovalvakil_reset_rest_can_manage();
			},
		]
	);
	register_rest_route(
		'hovalvakil/v1',
		'/delete-wc-products',
		[
			'methods'             => 'POST',
			'callback'            => 'hovalvakil_rest_post_delete_wc_products',
			'permission_callback' => static function () {
				return hovalvakil_reset_rest_can_manage();
			},
		]
	);
}
add_action( 'rest_api_init', 'hovalvakil_register_lawyer_reset_rest_routes', 12 );
