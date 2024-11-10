<?php
/**
 * Hook callbacks used for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'init', 'od_initialize_extensions', PHP_INT_MAX );
add_filter( 'template_include', 'od_buffer_output', PHP_INT_MAX );
OD_URL_Metrics_Post_Type::add_hooks();
add_action( 'wp', 'od_maybe_add_template_output_buffer_filter' );
add_action( 'wp_head', 'od_render_generator_meta_tag' );
add_action( 'od_url_metric_stored', 'od_clean_queried_object_cache_for_stored_url_metric' );
