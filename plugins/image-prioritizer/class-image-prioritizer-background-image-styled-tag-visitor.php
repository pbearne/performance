<?php
/**
 * Image Prioritizer: IP_Background_Image_Styled_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag visitor that optimizes elements with background-image styles.
 *
 * @since 0.1.0
 * @access private
 */
final class Image_Prioritizer_Background_Image_Styled_Tag_Visitor extends Image_Prioritizer_Tag_Visitor {

	/**
	 * Class name used to indicate a background image which is lazy-loaded.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const LAZY_BG_IMAGE_CLASS_NAME = 'od-lazy-bg-image';

	/**
	 * Whether the lazy-loading script and stylesheet have been added.
	 *
	 * @since n.e.x.t
	 * @var bool
	 */
	private $added_lazy_assets = false;

	/**
	 * Visits a tag.
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return bool Whether the tag should be tracked in URL Metrics.
	 */
	public function __invoke( OD_Tag_Visitor_Context $context ): bool {
		$processor = $context->processor;

		/*
		 * Note that CSS allows for a `background`/`background-image` to have multiple `url()` CSS functions, resulting
		 * in multiple background images being layered on top of each other. This ability is not employed in core. Here
		 * is a regex to search WPDirectory for instances of this: /background(-image)?:[^;}]+?url\([^;}]+?[^_]url\(/.
		 * It is used in Jetpack with the second background image being a gradient. To support multiple background
		 * images, this logic would need to be modified to make $background_image an array and to have a more robust
		 * parser of the `url()` functions from the property value.
		 */
		$background_image_url = null;
		$style                = $processor->get_attribute( 'style' );
		if (
			is_string( $style )
			&&
			1 === preg_match( '/background(?:-image)?\s*:[^;]*?url\(\s*[\'"]?\s*(?<background_image>.+?)\s*[\'"]?\s*\)/', $style, $matches )
			&&
			! $this->is_data_url( $matches['background_image'] )
		) {
			$background_image_url = $matches['background_image'];
		}

		if ( is_null( $background_image_url ) ) {
			return false;
		}

		$xpath = $processor->get_xpath();

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		foreach ( $context->url_metric_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
			$link_attributes = array(
				'rel'           => 'preload',
				'fetchpriority' => 'high',
				'as'            => 'image',
				'href'          => $background_image_url,
				'media'         => 'screen',
			);

			$context->link_collection->add_link(
				$link_attributes,
				$group->get_minimum_viewport_width(),
				$group->get_maximum_viewport_width()
			);
		}

		$this->lazy_load_bg_images( $context );

		return true;
	}

	/**
	 * Optimizes an element with a background image based on whether it is displayed in any initial viewport.
	 *
	 * @since n.e.x.t
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context, with the cursor currently at block with a background image.
	 */
	private function lazy_load_bg_images( OD_Tag_Visitor_Context $context ): void {
		$processor = $context->processor;

		// Lazy-loading can only be done once there are URL Metrics collected for both mobile and desktop.
		if (
			$context->url_metric_group_collection->get_first_group()->count() === 0
			||
			$context->url_metric_group_collection->get_last_group()->count() === 0
		) {
			return;
		}

		$xpath = $processor->get_xpath();

		// If the element is in the initial viewport, do not lazy load its background image.
		if ( false !== $context->url_metric_group_collection->is_element_positioned_in_any_initial_viewport( $xpath ) ) {
			return;
		}

		$processor->add_class( self::LAZY_BG_IMAGE_CLASS_NAME );

		if ( ! $this->added_lazy_assets ) {
			$processor->append_head_html( sprintf( "<style>\n%s\n</style>\n", image_prioritizer_get_lazy_load_bg_image_stylesheet() ) );
			$processor->append_body_html( wp_get_inline_script_tag( image_prioritizer_get_lazy_load_bg_image_script(), array( 'type' => 'module' ) ) );
			$this->added_lazy_assets = true;
		}
	}
}
