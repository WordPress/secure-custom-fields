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
	);

	$meta_data = get_file_data( $pattern_directory, $headers );

	if ( empty( $meta_data['title'] ) || empty( $meta_data['slug'] ) ) {
		return new WP_Error( 'invalid_pattern', 'Pattern missing required title or slug' );
	}

	// Use the new pattern loading method that doesn't require output buffering
	$pattern_content = scf_load_pattern_from_file( $pattern_directory );

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
}

function create_block_with_binding( string $tag, string $source, array $bindings_args = array(), string $inner_content = '' ) {
    // If tag is specified, map it to the appropriate block type
    $block = 'core/paragraph'; // Default block type
    $wrapper_tag = 'p'; // Default HTML wrapper tag
    $attributes = array(); // Block attributes
    
    if ($tag !== null) {
        switch ($tag) {
            case 'p':
                $block = 'core/paragraph';
                $wrapper_tag = 'p';
                break;
            case 'h1':
                $block = 'core/heading';
                $wrapper_tag = 'h1';
                $attributes['level'] = 1;
                break;
            case 'h2':
            case 'h': // Support legacy 'h' tag as h2
                $block = 'core/heading';
                $wrapper_tag = 'h2';
                $attributes['level'] = 2;
                break;
            case 'h3':
                $block = 'core/heading';
                $wrapper_tag = 'h3';
                $attributes['level'] = 3;
                break;
            case 'h4':
                $block = 'core/heading';
                $wrapper_tag = 'h4';
                $attributes['level'] = 4;
                break;
            case 'h5':
                $block = 'core/heading';
                $wrapper_tag = 'h5';
                $attributes['level'] = 5;
                break;
            case 'h6':
                $block = 'core/heading';
                $wrapper_tag = 'h6';
                $attributes['level'] = 6;
                break;
			case 'figure':
				$block = 'core/image';
				$wrapper_tag = 'figure';
				break;
            case 'img':
                $block = 'core/image';
                $wrapper_tag = 'figure';
                break;
            case 'button':
                $block = 'core/button';
                $wrapper_tag = 'div';
                break;
            case 'div':
                $block = 'core/group';
                $wrapper_tag = 'div';
                break;
            default:
                $block = 'core/paragraph';
                $wrapper_tag = 'p';
                break;
        }
    }
	
    
    // Create inner content with the correct HTML structure
    $class_attr = '';
    if (strpos($block, 'heading') !== false) {
        $class_attr = ' class="wp-block-heading"';
    }
	if (strpos($block, 'image') !== false) {
		$class_attr = ' class="wp-block-image"';
	}
    
    // Generate content with proper HTML structure
    if (empty(trim($inner_content))) {
		if ($tag === 'img' || $tag === 'figure') {
 			$inner_content = sprintf('<%1$s%3$s><img src="#%2$s" alt="%2$s" /></%1$s>', $wrapper_tag, esc_attr(''), $class_attr);
		} else {
 			$inner_content = sprintf('<%1$s%3$s>%2$s</%1$s>', $wrapper_tag, esc_attr(''), $class_attr);
		}
       
    } else {
        // Check if we need to add proper HTML structure
        if (!preg_match('/^\s*<' . preg_quote($wrapper_tag, '/') . '[\s>]/i', $inner_content)) {
            // Add class for headings if needed
            $inner_content = sprintf('<%1$s%3$s>%2$s</%1$s>', $wrapper_tag, $inner_content, $class_attr);
        }
    }
    
    // Build block attributes JSON
    $attr_json = '';
    if (!empty($bindings_args)) {
        // Initialize metadata bindings array
        $attributes['metadata'] = array(
            'bindings' => array()
        );
        
        // Process each binding argument
        foreach ((array)$bindings_args as $binding) {
            // Check if this is a properly formatted binding
            if (isset($binding['attribute']) && isset($binding['field'])) {
                $attributes['metadata']['bindings'][$binding['attribute']] = array(
                    'source' => $source,
                    'args' => array(
                        'field' => $binding['field']
                    )
                );
            }
        }
        
        $attr_json = wp_json_encode($attributes);
    }
    
    // Format according to WordPress block structure
    $content = sprintf(
        '<!-- wp:%s %s -->
%s
<!-- /wp:%s -->',
        esc_attr($block),
        $attr_json,
        $inner_content,
        esc_attr($block)
    );
    
    return $content;
}

/**
 * Load pattern content from a file by reading and processing it directly.
 *
 * This is the recommended method to use instead of scf_parse_pattern_file()
 * as it doesn't rely on output buffering, which can cause issues.
 *
 * @since SCF 6.5.1
 * @param string $pattern_file The pattern file path.
 * @return string|WP_Error The pattern content or WP_Error on failure.
 */
function scf_load_pattern_from_file( $pattern_file ) {
    if ( ! file_exists( $pattern_file ) || ! is_readable( $pattern_file ) ) {
        return new WP_Error( 'pattern_not_found', 'Pattern file not found or not readable' );
    }

    // For PHP files, execute directly without reading the entire file first
    if ( pathinfo( $pattern_file, PATHINFO_EXTENSION ) === 'php' ) {
        try {
            // Create a closure that mimics the include environment but returns the content
            $sandbox = function( $file_path ) {
                ob_start();
                $result = include $file_path;
                $output = ob_get_clean();
                
                // If the file returns a string directly (recommended pattern),
                // use that instead of captured output
                if ( is_string( $result ) ) {
                    return $result;
                }
                
                // Otherwise return the captured output
                return $output;
            };
            
            return $sandbox( $pattern_file );
        } catch ( Exception $e ) {
            return new WP_Error( 'pattern_execution_error', $e->getMessage() );
        }
    }

    // For non-PHP files (like HTML), only now do we read the file
    $file_content = file_get_contents( $pattern_file );
    if ( false === $file_content ) {
        return new WP_Error( 'pattern_read_error', 'Unable to read pattern file contents' );
    }
    
    return $file_content;
}
