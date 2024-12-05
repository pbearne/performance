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
		$filter = static function ( $sizes, $size ) use ( $block ) {

			$id                   = $block->attributes['id'] ?? 0;
			$alignment            = $block->attributes['align'] ?? '';
			$width                = $block->attributes['width'] ?? '';
			$ancestor_block_align = $block->context['ancestor_block_align'] ?? 'full';

			$better_sizes = auto_sizes_calculate_better_sizes( (int) $id, (string) $size, (string) $alignment, (string) $width, (string) $ancestor_block_align );

			// If better sizes can't be calculated, use the default sizes.
			return false !== $better_sizes ? $better_sizes : $sizes;
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
 * @param string $ancestor_block_align The ancestor block alignment.
 * @return string|false An improved sizes attribute or false if a better size cannot be calculated.
 */
function auto_sizes_calculate_better_sizes( int $id, string $size, string $align, string $resize_width, string $ancestor_block_align ) {
	// Without an image ID or a resize width, we cannot calculate a better size.
	if ( ! (bool) $id && ! (bool) $resize_width ) {
		return false;
	}

	$image_data = wp_get_attachment_image_src( $id, $size );

	$resize_width = (int) $resize_width;
	$image_width  = false !== $image_data ? $image_data[1] : 0;

	// If we don't have an image width or a resize width, we cannot calculate a better size.
	if ( ! ( (bool) $image_width || (bool) $resize_width ) ) {
		return false;
	}

	/*
	 * If we don't have an image width, use the resize width.
	 * If we have both an image width and a resize width, use the smaller of the two.
	 */
	if ( ! (bool) $image_width ) {
		$image_width = $resize_width;
	} elseif ( (bool) $resize_width ) {
		$image_width = min( $image_width, $resize_width );
	}

	// Normalize default alignment values.
	$align                = '' !== $align ? $align : 'default';
	$ancestor_block_align = '' !== $ancestor_block_align ? $ancestor_block_align : 'default';

	// We'll choose which alignment to use, based on which is more constraining.
	$constraint = array(
		'full'    => 0,
		'wide'    => 1,
		'left'    => 2,
		'right'   => 2,
		'center'  => 2,
		'default' => 3,
	);

	$alignment = $constraint[ $align ] > $constraint[ $ancestor_block_align ] ? $align : $ancestor_block_align;

	return auto_sizes_calculate_width( $alignment, $image_width, $ancestor_block_align );
}

/**
 * Retrieves the layout width for an alignment defined in theme.json.
 *
 * @since n.e.x.t
 *
 * @param string $alignment The alignment value.
 * @return string The alignment width based.
 */
function auto_sizes_get_layout_width( string $alignment ): string {
	$layout = auto_sizes_get_layout_settings();

	$layout_widths = array(
		'full'    => '100vw',
		'wide'    => array_key_exists( 'wideSize', $layout ) ? $layout['wideSize'] : '',
		'default' => array_key_exists( 'contentSize', $layout ) ? $layout['contentSize'] : '',
	);

	return $layout_widths[ $alignment ] ?? '';
}

/**
 * Calculates the width value for the `sizes` attribute based on block information.
 *
 * @since n.e.x.t
 *
 * @param string $alignment          The alignment.
 * @param int    $image_width        The image width.
 * @param string $ancestor_alignment The ancestor alignment.
 * @return string The calculated width value.
 */
function auto_sizes_calculate_width( string $alignment, int $image_width, string $ancestor_alignment ): string {
	$sizes = '';

	// Handle different alignment use cases.
	switch ( $alignment ) {
		case 'full':
			$layout_width = auto_sizes_get_layout_width( 'full' );
			break;

		case 'wide':
			$layout_width = auto_sizes_get_layout_width( 'wide' );
			break;

		case 'left':
		case 'right':
		case 'center':
			// Todo: use smaller fo the two values.
			$content_width = auto_sizes_get_layout_width( $ancestor_alignment );
			$layout_width  = sprintf( '%1$spx', $image_width );
			break;

		default:
			// Todo: use smaller fo the two values.
			$content_width = auto_sizes_get_layout_width( 'default' );
			$layout_width  = min( (int) $content_width, $image_width ) . 'px';
			break;
	}

	return 'full' === $alignment ? $layout_width : sprintf( '(max-width: %1$s) 100vw, %1$s', $layout_width );
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
function auto_sizes_filter_uses_context( array $uses_context, WP_Block_Type $block_type ): array {
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
function auto_sizes_filter_render_block_context( array $context, array $block ): array {
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
