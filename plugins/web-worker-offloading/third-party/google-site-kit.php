<?php
/**
 * Web Worker Offloading integration with Site Kit by Google.
 *
 * @since n.e.x.t
 * @package web-worker-offloading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Configures WWO for Site Kit and Google Analytics.
 *
 * @since n.e.x.t
 * @access private
 * @link https://partytown.builder.io/google-tag-manager#forward-events
 *
 * @param array<string, mixed>|mixed $configuration Configuration.
 * @return array<string, mixed> Configuration.
 */
function plwwo_google_site_kit_configure( $configuration ): array {
	$configuration = (array) $configuration;

	$configuration['globalFns'][] = 'gtag'; // Because gtag() is defined in one script and called in another.
	$configuration['forward'][]   = 'dataLayer.push'; // Because the Partytown integration has this in its example config.

	return $configuration;
}
add_filter( 'plwwo_configuration', 'plwwo_google_site_kit_configure' );

plwwo_mark_scripts_for_offloading(
	array(
		'google_gtagjs',
	)
);
