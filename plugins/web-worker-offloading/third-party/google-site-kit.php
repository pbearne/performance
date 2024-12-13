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
	$configuration['forward'][]   = 'dataLayer.push'; // See <https://partytown.builder.io/forwarding-event>.

	// See <https://github.com/google/site-kit-wp/blob/abbb74ff21f98a8779fbab0eeb9a16279a122bc4/includes/Core/Consent_Mode/Consent_Mode.php#L244-L259>.
	$configuration['mainWindowAccessors'][] = '_googlesitekitConsentCategoryMap';
	$configuration['mainWindowAccessors'][] = '_googlesitekitConsents';

	return $configuration;
}
add_filter( 'plwwo_configuration', 'plwwo_google_site_kit_configure' );

plwwo_mark_scripts_for_offloading(
	array(
		'google_gtagjs',
		'googlesitekit-consent-mode',
	)
);

/**
 * Filters inline script attributes to offload Rank Math's GTag script tag to Partytown.
 *
 * @since n.e.x.t
 * @access private
 * @link https://github.com/rankmath/seo-by-rank-math/blob/c78adba6f78079f27ff1430fabb75c6ac3916240/includes/modules/analytics/class-gtag.php#L169-L174
 *
 * @param array|mixed $attributes Script attributes.
 * @return array|mixed Filtered inline script attributes.
 */
function plwwo_google_site_kit_filter_inline_script_attributes( $attributes ) {
	if ( isset( $attributes['id'] ) && 'google_gtagjs-js-consent-mode-data-layer' === $attributes['id'] ) {
		wp_enqueue_script( 'web-worker-offloading' );
		$attributes['type'] = 'text/partytown';
	}
	return $attributes;
}

add_filter( 'wp_inline_script_attributes', 'plwwo_google_site_kit_filter_inline_script_attributes' );
