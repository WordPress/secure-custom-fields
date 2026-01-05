<?php
/**
 * Plugin Name: SCF Test Plugin, All Field Types
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Description: Test helper for all SCF field types E2E tests
 * Author: SCF Team
 *
 * @package scf-test-plugins
 */

/**
 * Register field group with all testable field types.
 */
function scf_test_register_all_field_types_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_all_field_types',
			'title'                 => 'All Field Types Test Group',
			'fields'                => array(
				// Text-based fields
				array(
					'key'   => 'field_test_text',
					'label' => 'Text Field',
					'name'  => 'text_field',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_test_textarea',
					'label' => 'Textarea Field',
					'name'  => 'textarea_field',
					'type'  => 'textarea',
					'rows'  => 3,
				),
				array(
					'key'   => 'field_test_email',
					'label' => 'Email Field',
					'name'  => 'email_field',
					'type'  => 'email',
				),
				array(
					'key'   => 'field_test_url',
					'label' => 'URL Field',
					'name'  => 'url_field',
					'type'  => 'url',
				),
				array(
					'key'   => 'field_test_password',
					'label' => 'Password Field',
					'name'  => 'password_field',
					'type'  => 'password',
				),
				array(
					'key'   => 'field_test_number',
					'label' => 'Number Field',
					'name'  => 'number_field',
					'type'  => 'number',
				),
				array(
					'key'           => 'field_test_range',
					'label'         => 'Range Field',
					'name'          => 'range_field',
					'type'          => 'range',
					'min'           => 0,
					'max'           => 100,
					'default_value' => 50,
				),

				// Selection fields
				array(
					'key'     => 'field_test_select',
					'label'   => 'Select Field',
					'name'    => 'select_field',
					'type'    => 'select',
					'choices' => array(
						'option_1' => 'Option 1',
						'option_2' => 'Option 2',
						'option_3' => 'Option 3',
					),
				),
				array(
					'key'     => 'field_test_checkbox',
					'label'   => 'Checkbox Field',
					'name'    => 'checkbox_field',
					'type'    => 'checkbox',
					'choices' => array(
						'check_a' => 'Check A',
						'check_b' => 'Check B',
						'check_c' => 'Check C',
					),
				),
				array(
					'key'     => 'field_test_radio',
					'label'   => 'Radio Field',
					'name'    => 'radio_field',
					'type'    => 'radio',
					'choices' => array(
						'radio_1' => 'Radio 1',
						'radio_2' => 'Radio 2',
						'radio_3' => 'Radio 3',
					),
				),
				array(
					'key'     => 'field_test_button_group',
					'label'   => 'Button Group Field',
					'name'    => 'button_group_field',
					'type'    => 'button_group',
					'choices' => array(
						'button_1' => 'Button 1',
						'button_2' => 'Button 2',
						'button_3' => 'Button 3',
					),
				),
				array(
					'key'   => 'field_test_true_false',
					'label' => 'True/False Field',
					'name'  => 'true_false_field',
					'type'  => 'true_false',
					'ui'    => 1,
				),

				// Date/time fields
				array(
					'key'            => 'field_test_date_picker',
					'label'          => 'Date Picker Field',
					'name'           => 'date_picker_field',
					'type'           => 'date_picker',
					'display_format' => 'F j, Y',
					'return_format'  => 'Y-m-d',
				),
				array(
					'key'            => 'field_test_time_picker',
					'label'          => 'Time Picker Field',
					'name'           => 'time_picker_field',
					'type'           => 'time_picker',
					'display_format' => 'g:i a',
					'return_format'  => 'H:i:s',
				),
				array(
					'key'            => 'field_test_date_time_picker',
					'label'          => 'Date Time Picker Field',
					'name'           => 'date_time_picker_field',
					'type'           => 'date_time_picker',
					'display_format' => 'F j, Y g:i a',
					'return_format'  => 'Y-m-d H:i:s',
				),

				// Content fields
				array(
					'key'     => 'field_test_wysiwyg',
					'label'   => 'WYSIWYG Field',
					'name'    => 'wysiwyg_field',
					'type'    => 'wysiwyg',
					'tabs'    => 'all',
					'toolbar' => 'full',
				),
				array(
					'key'   => 'field_test_color_picker',
					'label' => 'Color Picker Field',
					'name'  => 'color_picker_field',
					'type'  => 'color_picker',
				),
				array(
					'key'   => 'field_test_link',
					'label' => 'Link Field',
					'name'  => 'link_field',
					'type'  => 'link',
				),

				// Media field
				array(
					'key'           => 'field_test_image',
					'label'         => 'Image Field',
					'name'          => 'image_field',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'thumbnail',
				),

				// Relationship fields
				array(
					'key'           => 'field_test_user',
					'label'         => 'User Field',
					'name'          => 'user_field',
					'type'          => 'user',
					'return_format' => 'object',
					'multiple'      => 0,
				),
				array(
					'key'           => 'field_test_taxonomy',
					'label'         => 'Taxonomy Field',
					'name'          => 'taxonomy_field',
					'type'          => 'taxonomy',
					'taxonomy'      => 'category',
					'field_type'    => 'select',
					'return_format' => 'object',
				),

				// Container fields
				array(
					'key'        => 'field_test_group',
					'label'      => 'Group Field',
					'name'       => 'group_field',
					'type'       => 'group',
					'layout'     => 'block',
					'sub_fields' => array(
						array(
							'key'   => 'field_test_group_text',
							'label' => 'Group Text',
							'name'  => 'group_text',
							'type'  => 'text',
						),
						array(
							'key'   => 'field_test_group_number',
							'label' => 'Group Number',
							'name'  => 'group_number',
							'type'  => 'number',
						),
					),
				),
				array(
					'key'        => 'field_test_repeater',
					'label'      => 'Repeater Field',
					'name'       => 'repeater_field',
					'type'       => 'repeater',
					'layout'     => 'table',
					'min'        => 0,
					'max'        => 5,
					'sub_fields' => array(
						array(
							'key'   => 'field_test_repeater_text',
							'label' => 'Repeater Text',
							'name'  => 'repeater_text',
							'type'  => 'text',
						),
						array(
							'key'   => 'field_test_repeater_number',
							'label' => 'Repeater Number',
							'name'  => 'repeater_number',
							'type'  => 'number',
						),
					),
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
		)
	);
}
add_action( 'acf/init', 'scf_test_register_all_field_types_group' );

/**
 * Output all SCF field values on post content.
 *
 * This plugin dynamically renders ALL field values for the current post
 * in identifiable HTML elements that E2E tests can verify.
 * Each field type has its own output format.
 *
 * @param string $content The post content.
 * @return string Modified content with field values appended.
 */
function scf_test_output_all_fields( $content ) {
	if ( ! function_exists( 'get_field_objects' ) ) {
		return $content;
	}

	$field_objects = get_field_objects();
	if ( empty( $field_objects ) ) {
		return $content;
	}

	$output = '';

	foreach ( $field_objects as $field_name => $field ) {
		$type = $field['type'] ?? '';

		switch ( $type ) {
			// Text-based fields
			case 'text':
			case 'textarea':
			case 'number':
			case 'range':
			case 'email':
			case 'url':
			case 'password':
			case 'select':
			case 'radio':
			case 'button_group':
			case 'date_picker':
			case 'date_time_picker':
			case 'time_picker':
				$output .= scf_test_render_text_field( $field_name, $type );
				break;

			case 'wysiwyg':
				$output .= scf_test_render_wysiwyg_field( $field_name );
				break;

			case 'checkbox':
				$output .= scf_test_render_array_field( $field_name, $type );
				break;

			case 'true_false':
				$output .= scf_test_render_boolean_field( $field_name, $type );
				break;

			case 'image':
				$output .= scf_test_render_image_field( $field_name );
				break;

			case 'file':
				$output .= scf_test_render_file_field( $field_name );
				break;

			case 'gallery':
				$output .= scf_test_render_gallery_field( $field_name );
				break;

			case 'oembed':
				$output .= scf_test_render_oembed_field( $field_name );
				break;

			case 'link':
				$output .= scf_test_render_link_field( $field_name );
				break;

			case 'color_picker':
				$output .= scf_test_render_color_field( $field_name );
				break;

			case 'google_map':
				$output .= scf_test_render_google_map_field( $field_name );
				break;

			case 'post_object':
				$output .= scf_test_render_post_object_field( $field_name );
				break;

			case 'page_link':
				$output .= scf_test_render_page_link_field( $field_name );
				break;

			case 'relationship':
				$output .= scf_test_render_relationship_field( $field_name );
				break;

			case 'taxonomy':
				$output .= scf_test_render_taxonomy_field( $field_name );
				break;

			case 'user':
				$output .= scf_test_render_user_field( $field_name );
				break;

			case 'group':
				$output .= scf_test_render_group_field( $field_name );
				break;

			case 'repeater':
				$output .= scf_test_render_repeater_field( $field_name );
				break;

			case 'flexible_content':
				$output .= scf_test_render_flexible_content_field( $field_name );
				break;

			case 'clone':
				$output .= scf_test_render_clone_field( $field_name );
				break;

			default:
				// Fallback for unknown types - render as text
				$output .= scf_test_render_text_field( $field_name, $type );
				break;
		}
	}

	return $content . $output;
}
add_filter( 'the_content', 'scf_test_output_all_fields' );

/**
 * Render a simple text-based field.
 *
 * @param string $field_name The field name.
 * @param string $type       The field type.
 * @return string HTML output.
 */
function scf_test_render_text_field( $field_name, $type ) {
	$value = get_field( $field_name );
	if ( empty( $value ) && '0' !== $value && 0 !== $value ) {
		return '';
	}

	$safe_value = is_scalar( $value ) ? esc_html( $value ) : '';

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="%s">%s: %s</div>',
		esc_attr( $field_name ),
		esc_attr( $type ),
		esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ),
		$safe_value
	);
}

/**
 * Render a WYSIWYG field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_wysiwyg_field( $field_name ) {
	$value = get_field( $field_name );
	if ( empty( $value ) ) {
		return '';
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="wysiwyg">%s</div>',
		esc_attr( $field_name ),
		wp_kses_post( $value )
	);
}

/**
 * Render an array field (checkbox, etc.).
 *
 * @param string $field_name The field name.
 * @param string $type       The field type.
 * @return string HTML output.
 */
function scf_test_render_array_field( $field_name, $type ) {
	$value = get_field( $field_name );
	if ( empty( $value ) || ! is_array( $value ) ) {
		return '';
	}

	$safe_value = implode( ', ', array_map( 'esc_html', $value ) );

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="%s">%s: %s</div>',
		esc_attr( $field_name ),
		esc_attr( $type ),
		esc_html( ucfirst( $type ) ),
		$safe_value
	);
}

/**
 * Render a boolean field (true/false).
 *
 * @param string $field_name The field name.
 * @param string $type       The field type.
 * @return string HTML output.
 */
function scf_test_render_boolean_field( $field_name, $type ) {
	$value = get_field( $field_name );

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="%s">%s: %s</div>',
		esc_attr( $field_name ),
		esc_attr( $type ),
		esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ),
		$value ? 'Yes' : 'No'
	);
}

/**
 * Render an image field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_image_field( $field_name ) {
	$image = get_field( $field_name );
	if ( empty( $image ) ) {
		return '';
	}

	// Handle both array and ID return formats
	if ( is_array( $image ) ) {
		$url   = $image['url'] ?? '';
		$alt   = $image['alt'] ?? '';
		$title = $image['title'] ?? '';
	} else {
		$url   = wp_get_attachment_url( $image );
		$alt   = get_post_meta( $image, '_wp_attachment_image_alt', true );
		$title = get_the_title( $image );
	}

	if ( empty( $url ) ) {
		return '';
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="image"><img src="%s" alt="%s" title="%s" class="scf-test-image"></div>',
		esc_attr( $field_name ),
		esc_url( $url ),
		esc_attr( $alt ),
		esc_attr( $title )
	);
}

/**
 * Render a file field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_file_field( $field_name ) {
	$file = get_field( $field_name );
	if ( empty( $file ) ) {
		return '';
	}

	// Handle both array and ID return formats
	if ( is_array( $file ) ) {
		$url      = $file['url'] ?? '';
		$filename = $file['filename'] ?? '';
	} else {
		$url      = wp_get_attachment_url( $file );
		$filename = basename( get_attached_file( $file ) );
	}

	if ( empty( $url ) ) {
		return '';
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="file"><a href="%s" class="scf-test-file">%s</a></div>',
		esc_attr( $field_name ),
		esc_url( $url ),
		esc_html( $filename )
	);
}

/**
 * Render a gallery field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_gallery_field( $field_name ) {
	$images = get_field( $field_name );
	if ( empty( $images ) || ! is_array( $images ) ) {
		return '';
	}

	$output = sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="gallery" data-count="%d">',
		esc_attr( $field_name ),
		count( $images )
	);

	foreach ( $images as $image ) {
		$url = is_array( $image ) ? ( $image['url'] ?? '' ) : wp_get_attachment_url( $image );
		if ( $url ) {
			$output .= sprintf( '<img src="%s" class="scf-test-gallery-image">', esc_url( $url ) );
		}
	}

	$output .= '</div>';

	return $output;
}

/**
 * Render an oEmbed field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_oembed_field( $field_name ) {
	$value = get_field( $field_name );
	if ( empty( $value ) ) {
		return '';
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="oembed">%s</div>',
		esc_attr( $field_name ),
		$value // Already contains the iframe/embed HTML
	);
}

/**
 * Render a link field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_link_field( $field_name ) {
	$link = get_field( $field_name );
	if ( empty( $link ) || ! is_array( $link ) ) {
		return '';
	}

	$url    = $link['url'] ?? '';
	$title  = $link['title'] ?? '';
	$target = $link['target'] ?? '';

	if ( empty( $url ) ) {
		return '';
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="link"><a href="%s" target="%s" class="scf-test-link">%s</a></div>',
		esc_attr( $field_name ),
		esc_url( $url ),
		esc_attr( $target ),
		esc_html( ( $title ? $title : $url ) )
	);
}

/**
 * Render a color picker field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_color_field( $field_name ) {
	$value = get_field( $field_name );
	if ( empty( $value ) ) {
		return '';
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="color-picker" style="background-color: %s">Color: %s</div>',
		esc_attr( $field_name ),
		esc_attr( $value ),
		esc_html( $value )
	);
}

/**
 * Render a Google Map field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_google_map_field( $field_name ) {
	$location = get_field( $field_name );
	if ( empty( $location ) || ! is_array( $location ) ) {
		return '';
	}

	$address = $location['address'] ?? '';
	$lat     = $location['lat'] ?? '';
	$lng     = $location['lng'] ?? '';

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="google-map" data-lat="%s" data-lng="%s">Location: %s</div>',
		esc_attr( $field_name ),
		esc_attr( $lat ),
		esc_attr( $lng ),
		esc_html( $address )
	);
}

/**
 * Render a post object field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_post_object_field( $field_name ) {
	$post_object = get_field( $field_name );
	if ( empty( $post_object ) ) {
		return '';
	}

	// Handle single or multiple posts
	$posts = is_array( $post_object ) && ! isset( $post_object['ID'] ) ? $post_object : array( $post_object );

	$titles = array();
	foreach ( $posts as $p ) {
		if ( is_object( $p ) ) {
			$titles[] = $p->post_title;
		} elseif ( is_numeric( $p ) ) {
			$titles[] = get_the_title( $p );
		}
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="post-object">Post: %s</div>',
		esc_attr( $field_name ),
		esc_html( implode( ', ', $titles ) )
	);
}

/**
 * Render a page link field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_page_link_field( $field_name ) {
	$value = get_field( $field_name );
	if ( empty( $value ) ) {
		return '';
	}

	// Handle single or multiple links
	$links = is_array( $value ) ? $value : array( $value );
	$urls  = array();

	foreach ( $links as $link ) {
		if ( is_numeric( $link ) ) {
			$urls[] = get_permalink( $link );
		} else {
			$urls[] = $link;
		}
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="page-link">Links: %s</div>',
		esc_attr( $field_name ),
		esc_html( implode( ', ', $urls ) )
	);
}

/**
 * Render a relationship field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_relationship_field( $field_name ) {
	$posts = get_field( $field_name );
	if ( empty( $posts ) || ! is_array( $posts ) ) {
		return '';
	}

	$titles = array();
	foreach ( $posts as $p ) {
		if ( is_object( $p ) ) {
			$titles[] = $p->post_title;
		}
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="relationship" data-count="%d">Related: %s</div>',
		esc_attr( $field_name ),
		count( $posts ),
		esc_html( implode( ', ', $titles ) )
	);
}

/**
 * Render a taxonomy field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_taxonomy_field( $field_name ) {
	$terms = get_field( $field_name );
	if ( empty( $terms ) ) {
		return '';
	}

	// Handle single term or array of terms
	$terms = is_array( $terms ) ? $terms : array( $terms );
	$names = array();

	foreach ( $terms as $term ) {
		if ( is_object( $term ) ) {
			$names[] = $term->name;
		} elseif ( is_numeric( $term ) ) {
			$t = get_term( $term );
			if ( $t && ! is_wp_error( $t ) ) {
				$names[] = $t->name;
			}
		}
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="taxonomy">Terms: %s</div>',
		esc_attr( $field_name ),
		esc_html( implode( ', ', $names ) )
	);
}

/**
 * Render a user field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_user_field( $field_name ) {
	$user = get_field( $field_name );
	if ( empty( $user ) ) {
		return '';
	}

	// Handle single user or array of users
	$users = is_array( $user ) && ! isset( $user['ID'] ) ? $user : array( $user );
	$names = array();

	foreach ( $users as $u ) {
		if ( is_object( $u ) ) {
			$names[] = $u->display_name;
		} elseif ( is_array( $u ) && isset( $u['display_name'] ) ) {
			$names[] = $u['display_name'];
		} elseif ( is_numeric( $u ) ) {
			$user_obj = get_user_by( 'ID', $u );
			if ( $user_obj ) {
				$names[] = $user_obj->display_name;
			}
		}
	}

	return sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="user">User: %s</div>',
		esc_attr( $field_name ),
		esc_html( implode( ', ', $names ) )
	);
}

/**
 * Render a group field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_group_field( $field_name ) {
	$group = get_field( $field_name );
	if ( empty( $group ) || ! is_array( $group ) ) {
		return '';
	}

	$output  = sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="group">',
		esc_attr( $field_name )
	);
	$output .= '<ul class="scf-test-group-items">';

	foreach ( $group as $key => $value ) {
		$safe_value = is_scalar( $value ) ? esc_html( $value ) : wp_json_encode( $value );
		$output    .= sprintf(
			'<li class="scf-test-group-item" data-key="%s">%s: %s</li>',
			esc_attr( $key ),
			esc_html( $key ),
			$safe_value
		);
	}

	$output .= '</ul></div>';

	return $output;
}

/**
 * Render a repeater field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_repeater_field( $field_name ) {
	if ( ! have_rows( $field_name ) ) {
		return '';
	}

	$output  = sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="repeater">',
		esc_attr( $field_name )
	);
	$output .= '<ul class="scf-test-repeater-rows">';

	$row_index = 0;
	while ( have_rows( $field_name ) ) {
		the_row();
		$output .= sprintf( '<li class="scf-test-repeater-row" data-row="%d">', $row_index );

		// Get all sub field values
		$row = get_row();
		if ( is_array( $row ) ) {
			foreach ( $row as $key => $value ) {
				$safe_value = is_scalar( $value ) ? esc_html( $value ) : wp_json_encode( $value );
				$output    .= sprintf(
					'<span class="scf-test-subfield" data-name="%s">%s</span>',
					esc_attr( $key ),
					$safe_value
				);
			}
		}

		$output .= '</li>';
		++$row_index;
	}

	$output .= '</ul></div>';

	return $output;
}

/**
 * Render a flexible content field.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_flexible_content_field( $field_name ) {
	if ( ! have_rows( $field_name ) ) {
		return '';
	}

	$output  = sprintf(
		'<div id="scf-test-%s" class="scf-test-field" data-field-type="flexible-content">',
		esc_attr( $field_name )
	);
	$output .= '<ul class="scf-test-fc-layouts">';

	$layout_index = 0;
	while ( have_rows( $field_name ) ) {
		the_row();
		$layout_name = get_row_layout();

		$output .= sprintf(
			'<li class="scf-test-fc-layout" data-layout="%s" data-index="%d">',
			esc_attr( $layout_name ),
			$layout_index
		);
		$output .= sprintf( '<strong>Layout: %s</strong>', esc_html( $layout_name ) );

		// Get all sub field values for this layout
		$row = get_row();
		if ( is_array( $row ) ) {
			$output .= '<ul class="scf-test-layout-fields">';
			foreach ( $row as $key => $value ) {
				if ( 'acf_fc_layout' === $key ) {
					continue; // Skip the layout type marker
				}
				$safe_value = is_scalar( $value ) ? esc_html( $value ) : wp_json_encode( $value );
				$output    .= sprintf(
					'<li class="scf-test-layout-field" data-name="%s">%s: %s</li>',
					esc_attr( $key ),
					esc_html( $key ),
					$safe_value
				);
			}
			$output .= '</ul>';
		}

		$output .= '</li>';
		++$layout_index;
	}

	$output .= '</ul></div>';

	return $output;
}

/**
 * Render a clone field.
 *
 * Clone fields output their cloned fields directly, so we look for them by prefix.
 *
 * @param string $field_name The field name.
 * @return string HTML output.
 */
function scf_test_render_clone_field( $field_name ) {
	// Clone fields can use different prefix modes
	// For "group" display mode, values are nested under the clone field name
	$group = get_field( $field_name );

	if ( ! empty( $group ) && is_array( $group ) ) {
		$output  = sprintf(
			'<div id="scf-test-%s" class="scf-test-field" data-field-type="clone">',
			esc_attr( $field_name )
		);
		$output .= '<ul class="scf-test-clone-fields">';

		foreach ( $group as $key => $value ) {
			$safe_value = is_scalar( $value ) ? esc_html( $value ) : wp_json_encode( $value );
			$output    .= sprintf(
				'<li class="scf-test-clone-field" data-name="%s">%s: %s</li>',
				esc_attr( $key ),
				esc_html( $key ),
				$safe_value
			);
		}

		$output .= '</ul></div>';

		return $output;
	}

	return '';
}
