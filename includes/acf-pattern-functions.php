<?php
/**
 * Functions for registering and managing ACF block patterns.
 *
 * @package SecureCustomFields
 */

/**
 * Smart Pattern Registration with PHP-to-Binding Conversion
 *
 * @since SCF 6.5.0
 * @param string $pattern_directory The directory containing the pattern file.
 * @return array|WP_Error The pattern registration result or a WP_Error if the pattern is invalid.
 */
function scf_register_smart_pattern( $pattern_directory ) {
	if ( ! file_exists( $pattern_directory ) || ! is_readable( $pattern_directory ) ) {
		return new WP_Error( 'pattern_not_found', 'Pattern file not found' );
	}

	$headers = array(
		'title'          => 'Title',
		'slug'           => 'Slug',
		'categories'     => 'Categories',
		'keywords'       => 'Keywords',
		'description'    => 'Description',
		'scf_fieldgroup' => 'SCF Fieldgroup',
		'conversion'     => 'Conversion', // auto, bindings, php
	);

	$meta_data = get_file_data( $pattern_directory, $headers );

	if ( empty( $meta_data['title'] ) || empty( $meta_data['slug'] ) ) {
		return new WP_Error( 'invalid_pattern', 'Pattern missing required title or slug' );
	}

	// Determine conversion method
	$conversion_method = ( array_key_exists( 'conversion', $meta_data ) ) ? $meta_data['conversion'] : 'auto';

	$pattern_content = '';

	switch ( $conversion_method ) {
		case 'bindings':
			// Pure binding approach - read static content
			$pattern_content = scf_get_static_pattern_content( $pattern_directory );
			break;

		case 'auto':
		default:
			// Smart conversion: PHP to bindings
			$pattern_content = scf_convert_php_to_bindings( $pattern_directory );
			break;
	}

	if ( is_wp_error( $pattern_content ) ) {
		return $pattern_content;
	}

	// Process metadata
	$categories = ! empty( $meta_data['categories'] ) ?
		array_map( 'trim', explode( ',', $meta_data['categories'] ) ) : array( 'text' );
	$keywords   = ! empty( $meta_data['keywords'] ) ?
		array_map( 'trim', explode( ',', $meta_data['keywords'] ) ) : array();

	// Register pattern
	register_block_pattern(
		$meta_data['slug'],
		array(
			'title'       => $meta_data['title'],
			'categories'  => $categories,
			'keywords'    => $keywords,
			'description' => array_key_exists( 'description', $meta_data ) ? $meta_data['description'] : __( 'SCF Pattern', 'secure-custom-fields' ),
			'content'     => $pattern_content,
		)
	);

	return array(
		'slug'   => $meta_data['slug'],
		'title'  => $meta_data['title'],
		'status' => 'registered',
		'method' => $conversion_method,
	);
}

/**
 * Convert PHP SCF get_field('my_field') to bindings.
 *
 * @param string $pattern_file The path to the pattern file.
 * @return string The converted HTML content.
 */
function scf_convert_php_to_bindings( $pattern_file ) {
	// Read the file content
	if ( ! file_exists( $pattern_file ) || ! is_readable( $pattern_file ) ) {
		return new WP_Error( 'pattern_not_readable', 'Pattern file is not readable' );
	}

	$content = file_get_contents( $pattern_file );
	if ( false === $content ) {
		return new WP_Error( 'pattern_not_readable', 'Failed to read pattern file' );
	}

	// Extract PHP section and HTML section
	if ( ! preg_match( '/^<\?php.*?\?>(.*)/s', $content, $matches ) ) {
		// No PHP section, treat as static content
		return trim( $content );
	}

	$php_section  = $matches[0];
	$html_section = $matches[1];

	// Parse PHP section to find get_field() calls
	$field_mappings = scf_extract_field_mappings( $php_section );

	// Convert HTML section by replacing PHP echoes with bindings
	$converted_html = scf_replace_php_echoes_with_bindings( $html_section, $field_mappings );

	return trim( $converted_html );
}

/**
 * Extract field mappings from PHP.
 *
 * @param string $php_content The PHP content to extract field mappings from.
 * @return array The field mappings.
 */
function scf_extract_field_mappings( $php_content ) {
	$mappings = array();

	// Pattern to match: $variable = get_field('field_name') with optional post ID
	preg_match_all( '/\$(\w+)\s*=\s*get_field\([\'"]([^\'"]+)[\'"](?:\s*,\s*[^)]+)?\)/', $php_content, $matches, PREG_SET_ORDER );

	foreach ( $matches as $match ) {
		$variable              = $match[1];
		$field_name            = $match[2];
		$mappings[ $variable ] = $field_name;
	}

	// Also match get_sub_field patterns
	preg_match_all( '/\$(\w+)\s*=\s*get_sub_field\([\'"]([^\'"]+)[\'"]\)/', $php_content, $sub_matches, PREG_SET_ORDER );

	foreach ( $sub_matches as $match ) {
		$variable              = $match[1];
		$field_name            = $match[2];
		$mappings[ $variable ] = $field_name;
	}

	// Match get_the_terms patterns
	preg_match_all( '/\$(\w+)\s*=\s*get_the_terms\([^,]+,\s*[\'"]([^\'"]+)[\'"]\)/', $php_content, $terms_matches, PREG_SET_ORDER );

	foreach ( $terms_matches as $match ) {
		$variable              = $match[1];
		$taxonomy_name         = $match[2];
		$mappings[ $variable ] = $taxonomy_name;
	}

	return $mappings;
}

/**
 * Replace PHP echoes with block bindings.
 *
 * @param string $html_content The HTML content to replace PHP echoes with block bindings.
 * @param array  $field_mappings The field mappings.
 * @return string The converted HTML content.
 */
function scf_replace_php_echoes_with_bindings( $html_content, $field_mappings ) {
	// Process each individual block, including nested ones
	$html_content = scf_process_blocks_recursively( $html_content, $field_mappings );

	return $html_content;
}

/**
 * Process blocks recursively.
 *
 * @param string $content The content to process.
 * @param array  $field_mappings The field mappings.
 * @return string The processed content.
 */
function scf_process_blocks_recursively( $content, $field_mappings ) {
	// Pattern to match WordPress blocks (including nested ones)
	return preg_replace_callback(
		'/<!--\s*wp:(\w+(?:\/\w+)?)\s*({[^}]*})?\s*-->(.*?)<!--\s*\/wp:\1\s*-->/s',
		function ( $matches ) use ( $field_mappings ) {
			$block_name     = $matches[1];
			$attributes_str = $matches[2] ?? '{}';
			$block_content  = $matches[3];

			// Parse existing attributes
			$attributes_str = $attributes_str ? $attributes_str : '{}';
			$attributes     = json_decode( $attributes_str, true );
			$attributes     = $attributes ? $attributes : array();

			// Process nested blocks first
			$block_content = scf_process_blocks_recursively( $block_content, $field_mappings );

			// Now process PHP echoes in this block's direct content
			$block_modified = false;
			$block_content  = preg_replace_callback(
				'/<\?php\s+echo\s+esc_html\(\s*(?:the_title\(\)|\$(\w+))\s*\)\s*;\s*\?>|<\?php\s+echo\s+esc_url\(\s*\$(\w+)\s*\)\s*;\s*\?>|<\?php\s+echo\s+\$(\w+)\s*;\s*\?>|<\?php\s+the_title\(\)\s*;\s*\?>/',
				function ( $echo_matches ) use ( $field_mappings, &$attributes, $block_name, &$block_modified ) {
					// Extract variable name from different echo patterns
					$variable = '';
					for ( $i = 1; $i <= 4; $i++ ) {
						if ( ! empty( $echo_matches[ $i ] ) ) {
							$variable = $echo_matches[ $i ];
							break;
						}
					}

					if ( $variable && isset( $field_mappings[ $variable ] ) ) {
						$field_name = $field_mappings[ $variable ];

						// Handle special cases for image blocks
						if ( 'image' === $block_name ) {
							if ( strpos( $echo_matches[0], 'esc_url' ) !== false ) {
								$attributes['metadata']['bindings']['url'] = array(
									'source' => 'scf/field',
									'args'   => array( 'field' => $field_name ),
								);
								$block_modified                            = true;
								return '/api/placeholder/400/300';
							} elseif ( strpos( $echo_matches[0], 'esc_html' ) !== false ) {
								// This is the image ID
								$attributes['id'] = 0; // Set a placeholder ID
								$block_modified   = true;
								return '0';
							}
						} else {
							// For non-image blocks, use the standard binding
							$binding_attributes                                       = scf_get_binding_attribute_for_block( $block_name );
							$primary_attribute                                        = $binding_attributes[0] ?? 'content';
							$attributes['metadata']['bindings'][ $primary_attribute ] = array(
								'source' => 'scf/field',
								'args'   => array( 'field' => $field_name ),
							);
							$block_modified = true;
							return scf_get_placeholder_for_field( $field_name, $primary_attribute );
						}
					}

					// Return original if no mapping found
					return $echo_matches[0];
				},
				$block_content
			);

			// For image blocks, ensure we have the proper structure
			if ( 'image' === $block_name ) {
				// If we have bindings but no proper image structure, fix it
				if ( isset( $attributes['metadata']['bindings'] ) && ! strpos( $block_content, '<img' ) ) {
					$block_content = '<figure class="wp-block-image"><img src="/api/placeholder/400/300" alt="Post Title"/></figure>';
				}
			}

			// Rebuild block
			$attributes_json = ! empty( $attributes ) ? wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES ) : '';

			// Only include attributes if we have some
			$attr_part = '{}' !== $attributes_json ? " {$attributes_json}" : '';

			return "<!-- wp:{$block_name}{$attr_part} -->{$block_content}<!-- /wp:{$block_name} -->";
		},
		$content
	);
}

/**
 * Get the supported binding attributes for a block.
 *
 * @param string $block_name The name of the block.
 * @return array The supported binding attributes for the block.
 */
function scf_get_binding_attribute_for_block( $block_name ) {
	$supported_block_attributes = array(
		'core/paragraph' => array( 'content' ),
		'core/heading'   => array( 'content' ),
		'core/image'     => array( 'id', 'url', 'title', 'alt' ),
		'core/button'    => array( 'url', 'text', 'linkTarget', 'rel' ),
	);

	return $supported_block_attributes[ $block_name ] ?? array( 'content' );
}

/**
 * Get a placeholder value for a field based on its binding attribute.
 *
 * @param string $field_name The name of the field.
 * @param string $binding_attribute The binding attribute to get a placeholder for.
 * @return string The placeholder value.
 */
function scf_get_placeholder_for_field( $field_name, $binding_attribute = 'content' ) {
	// Handle specific binding attributes that need special values
	if ( 'url' === $binding_attribute || 'src' === $binding_attribute ) {
		return '/api/placeholder/400/300';
	}

	if ( 'alt' === $binding_attribute ) {
		return 'Image description';
	}

	if ( 'id' === $binding_attribute ) {
		return '0';
	}

	if ( 'linkTarget' === $binding_attribute ) {
		return '_self';
	}

	if ( 'rel' === $binding_attribute ) {
		return '';
	}

	// For all other attributes, return a simple generic placeholder
	return sprintf( '[%s]', ucwords( str_replace( array( '_', '-' ), ' ', $field_name ) ) );
}
