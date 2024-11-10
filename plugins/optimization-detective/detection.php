<?php
/**
 * Detection for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Prints the script for detecting loaded images and the LCP element.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string                         $slug             URL Metrics slug.
 * @param OD_URL_Metric_Group_Collection $group_collection URL Metric group collection.
 */
function od_get_detection_script( string $slug, OD_URL_Metric_Group_Collection $group_collection ): string {
	$web_vitals_lib_data = require __DIR__ . '/build/web-vitals.asset.php';
	$web_vitals_lib_src  = add_query_arg( 'ver', $web_vitals_lib_data['version'], plugin_dir_url( __FILE__ ) . 'build/web-vitals.js' );

	/**
	 * Filters the list of extension script module URLs to import when performing detection.
	 *
	 * @since 0.7.0
	 *
	 * @param string[] $extension_module_urls Extension module URLs.
	 */
	$extension_module_urls = (array) apply_filters( 'od_extension_module_urls', array() );

	// Obtain the queried object so when a URL Metric is stored the endpoint will know which object's cache to clean.
	// Note that WP_Post_Type is intentionally excluded here since there is no equivalent to clean_post_cache(), clean_term_cache(), and clean_user_cache().
	$queried_object = get_queried_object();
	if ( $queried_object instanceof WP_Post ) {
		$queried_object_type = 'post';
	} elseif ( $queried_object instanceof WP_Term ) {
		$queried_object_type = 'term';
	} elseif ( $queried_object instanceof WP_User ) {
		$queried_object_type = 'user';
	} else {
		$queried_object_type = null;
	}
	$queried_object_id = null === $queried_object_type ? null : (int) get_queried_object_id();

	$current_url = od_get_current_url();
	$detect_args = array(
		'minViewportAspectRatio' => od_get_minimum_viewport_aspect_ratio(),
		'maxViewportAspectRatio' => od_get_maximum_viewport_aspect_ratio(),
		'isDebug'                => WP_DEBUG,
		'extensionModuleUrls'    => $extension_module_urls,
		'restApiEndpoint'        => rest_url( OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE ),
		'currentUrl'             => $current_url,
		'urlMetricSlug'          => $slug,
		'queriedObject'          => null === $queried_object_type ? null : array(
			'type' => $queried_object_type,
			'id'   => $queried_object_id,
		),
		'urlMetricHMAC'          => od_get_url_metrics_storage_hmac( $slug, $current_url, $queried_object_type, $queried_object_id ),
		'urlMetricGroupStatuses' => array_map(
			static function ( OD_URL_Metric_Group $group ): array {
				return array(
					'minimumViewportWidth' => $group->get_minimum_viewport_width(),
					'complete'             => $group->is_complete(),
				);
			},
			iterator_to_array( $group_collection )
		),
		'storageLockTTL'         => OD_Storage_Lock::get_ttl(),
		'webVitalsLibrarySrc'    => $web_vitals_lib_src,
	);
	if ( WP_DEBUG ) {
		$detect_args['urlMetricGroupCollection'] = $group_collection;
	}

	return wp_get_inline_script_tag(
		sprintf(
			'import detect from %s; detect( %s );',
			wp_json_encode( add_query_arg( 'ver', OPTIMIZATION_DETECTIVE_VERSION, plugin_dir_url( __FILE__ ) . sprintf( 'detect%s.js', wp_scripts_get_suffix() ) ) ),
			wp_json_encode( $detect_args )
		),
		array( 'type' => 'module' )
	);
}
