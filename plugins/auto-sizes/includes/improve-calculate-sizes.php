<?php
/**
 * Functionality to improve the calculation of image `sizes` attributes.
 *
 * @package auto-sizes
 * @since n.e.x.t
 */

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

			$id            = $block->attributes['id'] ?? 0;
			$alignment     = $block->attributes['align'] ?? '';
			$width         = $block->attributes['width'] ?? '';
			$max_alignment = $block->context['max_alignment'] ?? '';

			/*
			 * Update width for cover block.
			 * See https://github.com/WordPress/gutenberg/blob/938720602082dc50a1746bd2e33faa3d3a6096d4/packages/block-library/src/cover/style.scss#L82-L87.
			 */
			if ( 'core/cover' === $block->name && in_array( $alignment, array( 'left', 'right' ), true ) ) {
				$size = array( 420, 420 );
			}

			$better_sizes = auto_sizes_calculate_better_sizes( (int) $id, $size, (string) $alignment, (string) $width, (string) $max_alignment );

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
 * @param int                    $id            The image attachment post ID.
 * @param string|array{int, int} $size          Image size name or array of width and height.
 * @param string                 $align         The image alignment.
 * @param string                 $resize_width  Resize image width.
 * @param string                 $max_alignment The maximum usable layout alignment.
 * @return string|false An improved sizes attribute or false if a better size cannot be calculated.
 */
function auto_sizes_calculate_better_sizes( int $id, $size, string $align, string $resize_width, string $max_alignment ) {
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
	$align = '' !== $align ? $align : 'default';

	/*
	 * Map alignment values to a weighting value so they can be compared.
	 * Note that 'left' and 'right' alignments are only constrained by max alignment.
	 */
	$constraints = array(
		'full'    => 0,
		'wide'    => 1,
		'left'    => 2,
		'right'   => 2,
		'default' => 3,
		'center'  => 3,
	);

	$alignment = $constraints[ $align ] > $constraints[ $max_alignment ] ? $align : $max_alignment;

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
			$layout_width = sprintf( '%1$spx', $image_width );
			break;

		case 'center':
		default:
			$alignment    = auto_sizes_get_layout_width( 'default' );
			$layout_width = sprintf( '%1$spx', min( (int) $alignment, $image_width ) );
			break;
	}

	// Format layout width when not 'full'.
	if ( 'full' !== $alignment ) {
		$layout_width = sprintf( '(max-width: %1$s) 100vw, %1$s', $layout_width );
	}

	return $layout_width;
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
		'full'    => '100vw', // Todo: incorporate useRootPaddingAwareAlignments.
		'wide'    => array_key_exists( 'wideSize', $layout ) ? $layout['wideSize'] : '',
		'default' => array_key_exists( 'contentSize', $layout ) ? $layout['contentSize'] : '',
	);

	return $layout_widths[ $alignment ] ?? '';
}

/**
 * Filters the context keys that a block type uses.
 *
 * @since n.e.x.t
 *
 * @param string[]      $uses_context Array of registered uses context for a block type.
 * @param WP_Block_Type $block_type   The full block type object.
 * @return string[] The filtered context keys used by the block type.
 */
function auto_sizes_filter_uses_context( array $uses_context, WP_Block_Type $block_type ): array {
	// The list of blocks that can consume outer layout context.
	$consumer_blocks = array(
		'core/cover',
		'core/image',
	);

	if ( in_array( $block_type->name, $consumer_blocks, true ) ) {
		// Use array_values to reset the array keys after merging.
		return array_values( array_unique( array_merge( $uses_context, array( 'max_alignment' ) ) ) );
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
	// When no max alignment is set, the maximum is assumed to be 'full'.
	$context['max_alignment'] = $context['max_alignment'] ?? 'full';

	// The list of blocks that can modify outer layout context.
	$provider_blocks = array(
		'core/columns',
		'core/group',
	);

	if ( in_array( $block['blockName'], $provider_blocks, true ) ) {
		$alignment = $block['attrs']['align'] ?? '';

		// If the container block doesn't have alignment, it's assumed to be 'default'.
		if ( ! (bool) $alignment ) {
			$context['max_alignment'] = 'default';
		} elseif ( 'wide' === $block['attrs']['align'] ) {
			$context['max_alignment'] = 'wide';
		}
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
