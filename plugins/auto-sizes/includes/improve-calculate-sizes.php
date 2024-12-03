<?php
/**
 * Functionality to improve the calculation of image `sizes` attributes.
 *
 * @package auto-sizes
 * @since n.e.x.t
 */

/**
 * Gets the smaller image size if the layout width is bigger.
 *
 * It will return the smaller image size and return "px" if the layout width
 * is something else, e.g. min(640px, 90vw) or 90vw.
 *
 * @since 1.1.0
 *
 * @param string $layout_width The layout width.
 * @param int    $image_width  The image width.
 * @return string The proper width after some calculations.
 */
function auto_sizes_get_width( string $layout_width, int $image_width ): string {
	if ( str_ends_with( $layout_width, 'px' ) ) {
		return $image_width > (int) $layout_width ? $layout_width : $image_width . 'px';
	}
	return $image_width . 'px';
}

/**
 * Primes attachment into the cache with a single database query.
 *
 * @since n.e.x.t
 *
 * @param string|mixed $content The HTML content.
 * @return string The HTML content.
 */
function auto_sizes_prime_attachment_caches( $content ): string {
	if ( ! is_string( $content ) ) {
		return '';
	}

	$processor = new WP_HTML_Tag_Processor( $content );

	$images = array();
	while ( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
		$class = $processor->get_attribute( 'class' );

		if ( ! is_string( $class ) ) {
			continue;
		}

		if ( preg_match( '/(?:^|\s)wp-image-([1-9][0-9]*)(?:\s|$)/', $class, $class_id ) === 1 ) {
			$attachment_id = (int) $class_id[1];
			if ( $attachment_id > 0 ) {
				$images[] = $attachment_id;
			}
		}
	}

	// Reduce the array to unique attachment IDs.
	$attachment_ids = array_unique( $images );

	if ( count( $attachment_ids ) > 1 ) {
		/*
		 * Warm the object cache with post and meta information for all found
		 * images to avoid making individual database calls.
		 */
		_prime_post_caches( $attachment_ids, false, true );
	}

	return $content;
}

/**
 * Filter the sizes attribute for images to improve the default calculation.
 *
 * @since 1.1.0
 *
 * @param string|mixed                                             $content      The block content about to be rendered.
 * @param array{ attrs?: array{ align?: string, width?: string } } $parsed_block The parsed block.
 * @param WP_Block                                                 $block        Block instance.
 * @return string The updated block content.
 */
function auto_sizes_filter_image_tag( $content, array $parsed_block, WP_Block $block ): string {
	if ( ! is_string( $content ) ) {
		return '';
	}

	$processor = new WP_HTML_Tag_Processor( $content );
	$has_image = $processor->next_tag( array( 'tag_name' => 'IMG' ) );

	// Only update the markup if an image is found.
	if ( $has_image ) {

		/**
		 * Callback for calculating image sizes attribute value for an image block.
		 *
		 * This is a workaround to use block context data when calculating the img sizes attribute.
		 *
		 * @param string $sizes The image sizes attribute value.
		 * @param string $size  The image size data.
		 */
		$filter = static function ( $sizes, $size ) use ( $block, $parsed_block ) {
			$id                   = $block->attributes['id'] ?? 0;
			$alignment            = $block->attributes['align'] ?? '';
			$width                = $block->attributes['width'] ?? '';
			$has_parent_block     = isset( $parsed_block['parentLayout'] );
			$ancestor_block_align = $block->context['ancestor_block_align'] ?? '';

			return auto_sizes_calculate_better_sizes( (int) $id, (string) $size, (string) $alignment, (string) $width, $has_parent_block, (string) $ancestor_block_align );
		};

		// Hook this filter early, before default filters are run.
		add_filter( 'wp_calculate_image_sizes', $filter, 9, 2 );

		$sizes = wp_calculate_image_sizes(
			// If we don't have a size slug, assume the full size was used.
			$parsed_block['attrs']['sizeSlug'] ?? 'full',
			null,
			null,
			$parsed_block['attrs']['id'] ?? 0
		);

		remove_filter( 'wp_calculate_image_sizes', $filter, 9 );

		// Bail early if sizes are not calculated.
		if ( false === $sizes ) {
			return $content;
		}

		$processor->set_attribute( 'sizes', $sizes );

		return $processor->get_updated_html();
	}

	return $content;
}

/**
 * Modifies the sizes attribute of an image based on layout context.
 *
 * @since n.e.x.t
 *
 * @param int    $id                   The image id.
 * @param string $size                 The image size data.
 * @param string $align                The image alignment.
 * @param string $resize_width         Resize image width.
 * @param bool   $has_parent_block     Check if image block has parent block.
 * @param string $ancestor_block_align The ancestor block alignment.
 * @return string The sizes attribute value.
 */
function auto_sizes_calculate_better_sizes( int $id, string $size, string $align, string $resize_width, bool $has_parent_block, string $ancestor_block_align ): string {
	$image = wp_get_attachment_image_src( $id, $size );

	if ( false === $image ) {
		return '';
	}

	// Retrieve width from the image tag itself.
	$image_width = '' !== $resize_width ? (int) $resize_width : $image[1];

	if ( $has_parent_block ) {
		if ( 'full' === $ancestor_block_align && 'full' === $align ) {
			$width = auto_sizes_calculate_width( $align, $image_width );
			return auto_sizes_format_sizes_attribute( $align, $width );
		} elseif ( 'full' !== $ancestor_block_align && 'full' === $align ) {
			$width = auto_sizes_calculate_width( $ancestor_block_align, $image_width );
			return auto_sizes_format_sizes_attribute( $ancestor_block_align, $width );
		} elseif ( 'full' !== $ancestor_block_align ) {
			$parent_block_alignment_width = auto_sizes_calculate_width( $ancestor_block_align, $image_width );
			$block_alignment_width        = auto_sizes_calculate_width( $align, $image_width );
			if ( (int) $parent_block_alignment_width < (int) $block_alignment_width ) {
				return auto_sizes_format_sizes_attribute( $ancestor_block_align, $parent_block_alignment_width );
			} else {
				return auto_sizes_format_sizes_attribute( $align, $block_alignment_width );
			}
		}
	}

	$width = auto_sizes_calculate_width( $align, $image_width );
	return auto_sizes_format_sizes_attribute( $align, $width );
}

/**
 * Calculates the width value for the `sizes` attribute based on block information.
 *
 * @since n.e.x.t
 *
 * @param string $alignment   The alignment.
 * @param int    $image_width The image width.
 * @return string The calculated width value.
 */
function auto_sizes_calculate_width( string $alignment, int $image_width ): string {
	$sizes  = '';
	$layout = auto_sizes_get_layout_settings();

	// Handle different alignment use cases.
	switch ( $alignment ) {
		case 'full':
			$sizes = '100vw';
			break;

		case 'wide':
			$sizes = array_key_exists( 'wideSize', $layout ) ? $layout['wideSize'] : '';
			break;

		case 'left':
		case 'right':
		case 'center':
			$sizes = auto_sizes_get_width( '', $image_width );
			break;

		default:
			$sizes = array_key_exists( 'contentSize', $layout )
				? auto_sizes_get_width( $layout['contentSize'], $image_width )
				: '';
			break;
	}
	return $sizes;
}

/**
 * Formats the `sizes` attribute value.
 *
 * @since n.e.x.t
 *
 * @param string $alignment The alignment.
 * @param string $width     The calculated width value.
 * @return string The formatted sizes attribute value.
 */
function auto_sizes_format_sizes_attribute( string $alignment, string $width ): string {
	return 'full' === $alignment
		? $width
		: sprintf( '(max-width: %1$s) 100vw, %1$s', $width );
}

/**
 * Generates the `sizes` attribute value based on block information.
 *
 * @since n.e.x.t
 *
 * @param string $alignment   The alignment.
 * @param int    $image_width The image width.
 * @param bool   $print_sizes Print the sizes attribute. Default is false.
 * @return string The sizes attribute value.
 */
function auto_sizes_get_sizes_by_block_alignments( string $alignment, int $image_width, bool $print_sizes = false ): string {
	$sizes = '';

	$layout = auto_sizes_get_layout_settings();

	// Handle different alignment use cases.
	switch ( $alignment ) {
		case 'full':
			$sizes = '100vw';
			break;

		case 'wide':
			if ( array_key_exists( 'wideSize', $layout ) ) {
				$sizes = $layout['wideSize'];
			}
			break;

		case 'left':
		case 'right':
		case 'center':
			$sizes = auto_sizes_get_width( '', $image_width );
			break;

		default:
			if ( array_key_exists( 'contentSize', $layout ) ) {
				$sizes = auto_sizes_get_width( $layout['contentSize'], $image_width );
			}
			break;
	}

	if ( $print_sizes ) {
		$sizes = 'full' === $alignment ? $sizes : sprintf( '(max-width: %1$s) 100vw, %1$s', $sizes );
	}

	return $sizes;
}

/**
 * Filters the context keys that a block type uses.
 *
 * @since n.e.x.t
 *
 * @param array<string> $uses_context Array of registered uses context for a block type.
 * @param WP_Block_Type $block_type   The full block type object.
 * @return array<string> The filtered context keys used by the block type.
 */
function auto_sizes_allowed_uses_context_for_image_blocks( array $uses_context, WP_Block_Type $block_type ): array {
	if ( 'core/image' === $block_type->name ) {
		// Use array_values to reset the array keys after merging.
		return array_values( array_unique( array_merge( $uses_context, array( 'ancestor_block_align' ) ) ) );
	}
	return $uses_context;
}

/**
 * Modifies the block context during rendering to blocks.
 *
 * @since n.e.x.t
 *
 * @param array<string, mixed> $context Current block context.
 * @param array<string, mixed> $block   The block being rendered.
 * @return array<string, mixed> Modified block context.
 */
function auto_sizes_modify_render_block_context( array $context, array $block ): array {
	if ( 'core/group' === $block['blockName'] || 'core/columns' === $block['blockName'] ) {
		$context['ancestor_block_align'] = $block['attrs']['align'] ?? '';
	}
	return $context;
}

/**
 * Retrieves the layout settings defined in theme.json.
 *
 * @since n.e.x.t
 *
 * @return array<string, mixed> Associative array of layout settings.
 */
function auto_sizes_get_layout_settings(): array {
	static $layout = array();
	if ( count( $layout ) === 0 ) {
		$layout = wp_get_global_settings( array( 'layout' ) );
	}
	return $layout;
}
