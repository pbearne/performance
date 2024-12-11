<?php
/**
 * Helper functions for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Initializes Image Prioritizer when Optimization Detective has loaded.
 *
 * @since 0.2.0
 *
 * @param string $optimization_detective_version Current version of the optimization detective plugin.
 */
function image_prioritizer_init( string $optimization_detective_version ): void {
	$required_od_version = '0.9.0';
	if ( ! version_compare( (string) strtok( $optimization_detective_version, '-' ), $required_od_version, '>=' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				global $pagenow;
				if ( ! in_array( $pagenow, array( 'index.php', 'plugins.php' ), true ) ) {
					return;
				}
				wp_admin_notice(
					esc_html__( 'The Image Prioritizer plugin requires a newer version of the Optimization Detective plugin. Please update your plugins.', 'image-prioritizer' ),
					array( 'type' => 'warning' )
				);
			}
		);
		return;
	}

	// Classes are required here because only here do we know the expected version of Optimization Detective is active.
	require_once __DIR__ . '/class-image-prioritizer-tag-visitor.php';
	require_once __DIR__ . '/class-image-prioritizer-img-tag-visitor.php';
	require_once __DIR__ . '/class-image-prioritizer-background-image-styled-tag-visitor.php';
	require_once __DIR__ . '/class-image-prioritizer-video-tag-visitor.php';

	add_action( 'wp_head', 'image_prioritizer_render_generator_meta_tag' );
	add_action( 'od_register_tag_visitors', 'image_prioritizer_register_tag_visitors' );
}

/**
 * Displays the HTML generator meta tag for the Image Prioritizer plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function image_prioritizer_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="image-prioritizer ' . esc_attr( IMAGE_PRIORITIZER_VERSION ) . '">' . "\n";
}

/**
 * Registers tag visitors.
 *
 * @since 0.1.0
 *
 * @param OD_Tag_Visitor_Registry $registry Tag visitor registry.
 */
function image_prioritizer_register_tag_visitors( OD_Tag_Visitor_Registry $registry ): void {
	// Note: The class is invocable (it has an __invoke() method).
	$img_visitor = new Image_Prioritizer_Img_Tag_Visitor();
	$registry->register( 'image-prioritizer/img', $img_visitor );

	$bg_image_visitor = new Image_Prioritizer_Background_Image_Styled_Tag_Visitor();
	$registry->register( 'image-prioritizer/background-image', $bg_image_visitor );

	$video_visitor = new Image_Prioritizer_Video_Tag_Visitor();
	$registry->register( 'image-prioritizer/video', $video_visitor );
}

/**
 * Filters the list of Optimization Detective extension module URLs to include the extension for Image Prioritizer.
 *
 * @since n.e.x.t
 *
 * @param string[]|mixed $extension_module_urls Extension module URLs.
 * @return string[] Extension module URLs.
 */
function image_prioritizer_filter_extension_module_urls( $extension_module_urls ): array {
	if ( ! is_array( $extension_module_urls ) ) {
		$extension_module_urls = array();
	}
	$extension_module_urls[] = add_query_arg( 'ver', IMAGE_PRIORITIZER_VERSION, plugin_dir_url( __FILE__ ) . image_prioritizer_get_asset_path( 'detect.js' ) );
	return $extension_module_urls;
}

/**
 * Filters additional properties for the element item schema for Optimization Detective.
 *
 * @since n.e.x.t
 *
 * @param array<string, array{type: string}> $additional_properties Additional properties.
 * @return array<string, array{type: string}> Additional properties.
 */
function image_prioritizer_add_element_item_schema_properties( array $additional_properties ): array {
	$additional_properties['lcpElementExternalBackgroundImage'] = array(
		'type'       => 'object',
		'properties' => array(
			'url'   => array(
				'type'      => 'string',
				'format'    => 'uri', // Note: This is excessively lax, as it is used exclusively in rest_sanitize_value_from_schema() and not in rest_validate_value_from_schema().
				'pattern'   => '^https?://',
				'required'  => true,
				'maxLength' => 500, // Image URLs can be quite long.
			),
			'tag'   => array(
				'type'      => 'string',
				'required'  => true,
				'minLength' => 1,
				// The longest HTML tag name is 10 characters (BLOCKQUOTE and FIGCAPTION), but SVG tag names can be longer
				// (e.g. feComponentTransfer). This maxLength accounts for possible Custom Elements that are even longer,
				// although the longest known Custom Element from HTTP Archive is 32 characters. See data from <https://almanac.httparchive.org/en/2024/markup#fig-18>.
				'maxLength' => 100,
				'pattern'   => '^[a-zA-Z0-9\-]+\z', // Technically emoji can be allowed in a custom element's tag name, but this is not supported here.
			),
			'id'    => array(
				'type'      => array( 'string', 'null' ),
				'maxLength' => 100, // A reasonable upper-bound length for a long ID.
				'required'  => true,
			),
			'class' => array(
				'type'      => array( 'string', 'null' ),
				'maxLength' => 500, // There can be a ton of class names on an element.
				'required'  => true,
			),
		),
	);
	return $additional_properties;
}

/**
 * Validates that the provided background image URL is valid.
 *
 * @since n.e.x.t
 *
 * @param bool|WP_Error|mixed  $validity   Validity. Valid if true or a WP_Error without any errors, or invalid otherwise.
 * @param OD_Strict_URL_Metric $url_metric URL Metric, already validated against the JSON Schema.
 * @return bool|WP_Error Validity. Valid if true or a WP_Error without any errors, or invalid otherwise.
 */
function image_prioritizer_filter_store_url_metric_validity( $validity, OD_Strict_URL_Metric $url_metric ) {
	if ( ! is_bool( $validity ) && ! ( $validity instanceof WP_Error ) ) {
		$validity = (bool) $validity;
	}

	$data = $url_metric->get( 'lcpElementExternalBackgroundImage' );
	if ( ! is_array( $data ) ) {
		return $validity;
	}

	$r = wp_safe_remote_head(
		$data['url'],
		array(
			'redirection' => 3, // Allow up to 3 redirects.
		)
	);
	if ( $r instanceof WP_Error ) {
		return new WP_Error(
			WP_DEBUG ? $r->get_error_code() : 'head_request_failure',
			__( 'HEAD request for background image URL failed.', 'image-prioritizer' ) . ( WP_DEBUG ? ' ' . $r->get_error_message() : '' ),
			array(
				'code' => 500,
			)
		);
	}
	$response_code = wp_remote_retrieve_response_code( $r );
	if ( $response_code < 200 || $response_code >= 400 ) {
		return new WP_Error(
			'background_image_response_not_ok',
			__( 'HEAD request for background image URL did not return with a success status code.', 'image-prioritizer' ),
			array(
				'code' => WP_DEBUG ? $response_code : 400,
			)
		);
	}

	$content_type = wp_remote_retrieve_header( $r, 'Content-Type' );
	if ( ! is_string( $content_type ) || ! str_starts_with( $content_type, 'image/' ) ) {
		return new WP_Error(
			'background_image_response_not_image',
			__( 'HEAD request for background image URL did not return an image Content-Type.', 'image-prioritizer' ),
			array(
				'code' => 400,
			)
		);
	}

	// TODO: Check for the Content-Length and return invalid if it is gigantic?
	return $validity;
}

/**
 * Gets the path to a script or stylesheet.
 *
 * @since n.e.x.t
 *
 * @param string      $src_path Source path, relative to plugin root.
 * @param string|null $min_path Minified path. If not supplied, then '.min' is injected before the file extension in the source path.
 * @return string URL to script or stylesheet.
 */
function image_prioritizer_get_asset_path( string $src_path, ?string $min_path = null ): string {
	if ( null === $min_path ) {
		// Note: wp_scripts_get_suffix() is not used here because we need access to both the source and minified paths.
		$min_path = (string) preg_replace( '/(?=\.\w+$)/', '.min', $src_path );
	}

	$force_src = false;
	if ( WP_DEBUG && ! file_exists( trailingslashit( __DIR__ ) . $min_path ) ) {
		$force_src = true;
		wp_trigger_error(
			__FUNCTION__,
			sprintf(
				/* translators: %s is the minified asset path */
				__( 'Minified asset has not been built: %s', 'image-prioritizer' ),
				$min_path
			),
			E_USER_WARNING
		);
	}

	if ( SCRIPT_DEBUG || $force_src ) {
		return $src_path;
	}

	return $min_path;
}

/**
 * Gets the script to lazy-load videos.
 *
 * Load a video and its poster image when it approaches the viewport using an IntersectionObserver.
 *
 * Handles 'autoplay' and 'preload' attributes accordingly.
 *
 * @since 0.2.0
 *
 * @return string Lazy load script.
 */
function image_prioritizer_get_video_lazy_load_script(): string {
	$path = image_prioritizer_get_asset_path( 'lazy-load-video.js' );
	return (string) file_get_contents( __DIR__ . '/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
}

/**
 * Gets the script to lazy-load background images.
 *
 * Load the background image when it approaches the viewport using an IntersectionObserver.
 *
 * @since n.e.x.t
 *
 * @return string Lazy load script.
 */
function image_prioritizer_get_lazy_load_bg_image_script(): string {
	$path = image_prioritizer_get_asset_path( 'lazy-load-bg-image.js' );
	return (string) file_get_contents( __DIR__ . '/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
}

/**
 * Gets the stylesheet to lazy-load background images.
 *
 * @since n.e.x.t
 *
 * @return string Lazy load stylesheet.
 */
function image_prioritizer_get_lazy_load_bg_image_stylesheet(): string {
	$path = image_prioritizer_get_asset_path( 'lazy-load-bg-image.css' );
	return (string) file_get_contents( __DIR__ . '/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
}
