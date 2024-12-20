<?php
/**
 * Advanced Settings for Post Types
 *
 * Renders the advanced settings tabs for post type configuration.
 *
 * @package wordpress/secure-custom-fields
 */

global $acf_post_type;

foreach ( acf_get_combined_post_type_settings_tabs() as $tab_key => $tab_label ) {
	acf_render_field_wrap(
		array(
			'type'  => 'tab',
			'label' => $tab_label,
			'key'   => 'acf_post_type_tabs',
		)
	);

	$wrapper_class = str_replace( '_', '-', $tab_key );

	echo '<div class="acf-post-type-advanced-settings acf-post-type-' . esc_attr( $wrapper_class ) . '-settings">';

	switch ( $tab_key ) {
		case 'general':
			$acf_available_supports = array(
				'title'           => __( 'Title', 'secure-custom-fields' ),
				'author'          => __( 'Author', 'secure-custom-fields' ),
				'comments'        => __( 'Comments', 'secure-custom-fields' ),
				'trackbacks'      => __( 'Trackbacks', 'secure-custom-fields' ),
				'editor'          => __( 'Editor', 'secure-custom-fields' ),
				'excerpt'         => __( 'Excerpt', 'secure-custom-fields' ),
				'revisions'       => __( 'Revisions', 'secure-custom-fields' ),
				'page-attributes' => __( 'Page Attributes', 'secure-custom-fields' ),
				'thumbnail'       => __( 'Featured Image', 'secure-custom-fields' ),
				'custom-fields'   => __( 'Custom Fields', 'secure-custom-fields' ),
				'post-formats'    => __( 'Post Formats', 'secure-custom-fields' ),
			);
			$acf_available_supports = apply_filters( 'acf/post_type/available_supports', $acf_available_supports, $acf_post_type );
			$acf_selected_supports  = is_array( $acf_post_type['supports'] ) ? $acf_post_type['supports'] : array();

			acf_render_field_wrap(
				array(
					'type'                      => 'checkbox',
					'name'                      => 'supports',
					'key'                       => 'supports',
					'label'                     => __( 'Supports', 'secure-custom-fields' ),
					'instructions'              => __( 'Enable various features in the content editor.', 'secure-custom-fields' ),
					'prefix'                    => 'acf_post_type',
					'value'                     => array_unique( array_filter( $acf_selected_supports ) ),
					'choices'                   => $acf_available_supports,
					'allow_custom'              => true,
					'class'                     => 'acf_post_type_supports',
					'custom_choice_button_text' => __( 'Add Custom', 'secure-custom-fields' ),
				),
				'div'
			);

			acf_render_field_wrap( array( 'type' => 'seperator' ) );

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'description',
					'key'          => 'description',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['description'],
					'label'        => __( 'Description', 'secure-custom-fields' ),
					'instructions' => __( 'A descriptive summary of the post type.', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap( array( 'type' => 'seperator' ) );

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'active',
					'key'          => 'active',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['active'],
					'label'        => __( 'Active', 'secure-custom-fields' ),
					'instructions' => __( 'Active post types are enabled and registered with WordPress.', 'secure-custom-fields' ),
					'ui'           => true,
					'default'      => 1,
				)
			);

			break;
		case 'labels':
			echo '<div class="acf-field acf-regenerate-labels-bar">';
			echo '<span class="acf-btn acf-btn-sm acf-btn-clear acf-regenerate-labels"><i class="acf-icon acf-icon-regenerate"></i>' . esc_html__( 'Regenerate', 'secure-custom-fields' ) . '</span>';
			echo '<span class="acf-btn acf-btn-sm acf-btn-clear acf-clear-labels"><i class="acf-icon acf-icon-trash"></i>' . esc_html__( 'Clear', 'secure-custom-fields' ) . '</span>';
			echo '<span class="acf-tip acf-labels-tip"><i class="acf-icon acf-icon-help acf-js-tooltip" title="' . esc_attr__( 'Regenerate all labels using the Singular and Plural labels', 'secure-custom-fields' ) . '"></i></span>';
			echo '</div>';

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'menu_name',
					'key'          => 'menu_name',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['menu_name'],
					'data'         => array(
						'label'   => '%s',
						'replace' => 'plural',
					),
					'label'        => __( 'Menu Name', 'secure-custom-fields' ),
					'instructions' => __( 'Admin menu name for the post type.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Posts', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'all_items',
					'key'          => 'all_items',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['all_items'],
					'data'         => array(
						/* translators: %s Plural form of post type name */
						'label'   => __( 'All %s', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'All Items', 'secure-custom-fields' ),
					'instructions' => __( 'In the post type submenu in the admin dashboard.', 'secure-custom-fields' ),
					'placeholder'  => __( 'All Posts', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'edit_item',
					'key'          => 'edit_item',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['edit_item'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( 'Edit %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Edit Item', 'secure-custom-fields' ),
					'instructions' => __( 'At the top of the editor screen when editing an item.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Edit Post', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'view_item',
					'key'          => 'view_item',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['view_item'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( 'View %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'View Item', 'secure-custom-fields' ),
					'instructions' => __( 'In the admin bar to view item when editing it.', 'secure-custom-fields' ),
					'placeholder'  => __( 'View Post', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'view_items',
					'key'          => 'view_items',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['view_items'],
					'data'         => array(
						/* translators: %s Plural form of post type name */
						'label'   => __( 'View %s', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'View Items', 'secure-custom-fields' ),
					'instructions' => __( 'Appears in the admin bar in the \'All Posts\' view, provided the post type supports archives and the home page is not an archive of that post type.', 'secure-custom-fields' ),
					'placeholder'  => __( 'View Posts', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'add_new_item',
					'key'          => 'add_new_item',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['add_new_item'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( 'Add New %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Add New Item', 'secure-custom-fields' ),
					'instructions' => __( 'At the top of the editor screen when adding a new item.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Add New Post', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'add_new',
					'key'          => 'add_new',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['add_new'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( 'Add New %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Add New', 'secure-custom-fields' ),
					'instructions' => __( 'In the post type submenu in the admin dashboard.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Add New Post', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'new_item',
					'key'          => 'new_item',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['new_item'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( 'New %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'New Item', 'secure-custom-fields' ),
					'instructions' => __( 'In the post type submenu in the admin dashboard.', 'secure-custom-fields' ),
					'placeholder'  => __( 'New Post', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'parent_item_colon',
					'key'          => 'parent_item_colon',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['parent_item_colon'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( 'Parent %s:', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Parent Item Prefix', 'secure-custom-fields' ),
					'instructions' => __( 'For hierarchical types in the post type list screen.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Parent Page:', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'search_items',
					'key'          => 'search_items',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['search_items'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( 'Search %s', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'Search Items', 'secure-custom-fields' ),
					'instructions' => __( 'At the top of the items screen when searching for an item.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Search Posts', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'not_found',
					'key'          => 'not_found',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['not_found'],
					'data'         => array(
						/* translators: %s Plural form of post type name */
						'label'     => __( 'No %s found', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'No Items Found', 'secure-custom-fields' ),
					'instructions' => __( 'At the top of the post type list screen when there are no posts to display.', 'secure-custom-fields' ),
					'placeholder'  => __( 'No posts found', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'not_found_in_trash',
					'key'          => 'not_found_in_trash',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['not_found_in_trash'],
					'data'         => array(
						/* translators: %s Plural form of post type name */
						'label'     => __( 'No %s found in Trash', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'No Items Found in Trash', 'secure-custom-fields' ),
					'instructions' => __( 'At the top of the post type list screen when there are no posts in the trash.', 'secure-custom-fields' ),
					'placeholder'  => __( 'No posts found in Trash', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'archives',
					'key'          => 'archives',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['archives'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( '%s Archives', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Archives Nav Menu', 'secure-custom-fields' ),
					'instructions' => __( "Adds 'Post Type Archive' items with this label to the list of posts shown when adding items to an existing menu in a CPT with archives enabled. Only appears when editing menus in 'Live Preview' mode and a custom archive slug has been provided.", 'secure-custom-fields' ),
					'placeholder'  => __( 'Post Archives', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'attributes',
					'key'          => 'attributes',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['attributes'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( '%s Attributes', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Attributes Meta Box', 'secure-custom-fields' ),
					'instructions' => __( 'In the editor used for the title of the post attributes meta box.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Post Attributes', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'featured_image',
					'key'          => 'featured_image',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['featured_image'],
					'label'        => __( 'Featured Image Meta Box', 'secure-custom-fields' ),
					'instructions' => __( 'In the editor used for the title of the featured image meta box.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Featured image', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'set_featured_image',
					'key'          => 'set_featured_image',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['set_featured_image'],
					'label'        => __( 'Set Featured Image', 'secure-custom-fields' ),
					'instructions' => __( 'As the button label when setting the featured image.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Set featured image', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'remove_featured_image',
					'key'          => 'remove_featured_image',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['remove_featured_image'],
					'label'        => __( 'Remove Featured Image', 'secure-custom-fields' ),
					'instructions' => __( 'As the button label when removing the featured image.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Remove featured image', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'use_featured_image',
					'key'          => 'use_featured_image',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['use_featured_image'],
					'label'        => __( 'Use Featured Image', 'secure-custom-fields' ),
					'instructions' => __( 'As the button label for selecting to use an image as the featured image.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Use as featured image', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'insert_into_item',
					'key'          => 'insert_into_item',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['insert_into_item'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'     => __( 'Insert into %s', 'secure-custom-fields' ),
						'replace'   => 'singular',
						'transform' => 'lower',
					),
					'label'        => __( 'Insert Into Media Button', 'secure-custom-fields' ),
					'instructions' => __( 'As the button label when adding media to content.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Insert into post', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'uploaded_to_this_item',
					'key'          => 'uploaded_to_this_item',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['uploaded_to_this_item'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'     => __( 'Uploaded to this %s', 'secure-custom-fields' ),
						'replace'   => 'singular',
						'transform' => 'lower',
					),
					'label'        => __( 'Uploaded To This Item', 'secure-custom-fields' ),
					'instructions' => __( 'In the media modal showing all media uploaded to this item.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Uploaded to this post', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'filter_items_list',
					'key'          => 'filter_items_list',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['filter_items_list'],
					'data'         => array(
						/* translators: %s Plural form of post type name */
						'label'     => __( 'Filter %s list', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'Filter Items List', 'secure-custom-fields' ),
					'instructions' => __( 'Used by screen readers for the filter links heading on the post type list screen.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Filter posts list', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'filter_by_date',
					'key'          => 'filter_by_date',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['filter_by_date'],
					'data'         => array(
						/* translators: %s Plural form of post type name */
						'label'     => __( 'Filter %s by date', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'Filter Items By Date', 'secure-custom-fields' ),
					'instructions' => __( 'Used by screen readers for the filter by date heading on the post type list screen.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Filter posts by date', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);


			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'items_list_navigation',
					'key'          => 'items_list_navigation',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['items_list_navigation'],
					'data'         => array(
						/* translators: %s Plural form of post type name */
						'label'   => __( '%s list navigation', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'Items List Navigation', 'secure-custom-fields' ),
					'instructions' => __( 'Used by screen readers for the filter list pagination on the post type list screen.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Posts list navigation', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'items_list',
					'key'          => 'items_list',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['items_list'],
					'data'         => array(
						/* translators: %s Plural form of post type name */
						'label'   => __( '%s list', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'Items List', 'secure-custom-fields' ),
					'instructions' => __( 'Used by screen readers for the items list on the post type list screen.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Posts list', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'item_published',
					'key'          => 'item_published',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['item_published'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( '%s published.', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Item Published', 'secure-custom-fields' ),
					'instructions' => __( 'In the editor notice after publishing an item.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Post published.', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'item_published_privately',
					'key'          => 'item_published_privately',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['item_published_privately'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( '%s published privately.', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Item Published Privately', 'secure-custom-fields' ),
					'instructions' => __( 'In the editor notice after publishing a private item.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Post published privately.', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'item_reverted_to_draft',
					'key'          => 'item_reverted_to_draft',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['item_reverted_to_draft'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( '%s reverted to draft.', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Item Reverted To Draft', 'secure-custom-fields' ),
					'instructions' => __( 'In the editor notice after reverting an item to draft.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Post reverted to draft.', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'item_scheduled',
					'key'          => 'item_scheduled',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['item_scheduled'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( '%s scheduled.', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Item Scheduled', 'secure-custom-fields' ),
					'instructions' => __( 'In the editor notice after scheduling an item.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Post scheduled.', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'item_updated',
					'key'          => 'item_updated',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['item_updated'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( '%s updated.', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Item Updated', 'secure-custom-fields' ),
					'instructions' => __( 'In the editor notice after an item is updated.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Post updated.', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'item_link',
					'key'          => 'item_link',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['item_link'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'   => __( '%s Link', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Item Link', 'secure-custom-fields' ),
					'instructions' => __( 'Title for a navigation link block variation.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Post Link', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'item_link_description',
					'key'          => 'item_link_description',
					'prefix'       => 'acf_post_type[labels]',
					'value'        => $acf_post_type['labels']['item_link_description'],
					'data'         => array(
						/* translators: %s Singular form of post type name */
						'label'     => __( 'A link to a %s.', 'secure-custom-fields' ),
						'replace'   => 'singular',
						'transform' => 'lower',
					),
					'label'        => __( 'Item Link Description', 'secure-custom-fields' ),
					'instructions' => __( 'Description for a navigation link block variation.', 'secure-custom-fields' ),
					'placeholder'  => __( 'A link to a post.', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'enter_title_here',
					'key'          => 'enter_title_here',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['enter_title_here'],
					'label'        => __( 'Title Placeholder', 'secure-custom-fields' ),
					'instructions' => __( 'In the editor used as the placeholder of the title.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Add title', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			break;
		case 'visibility':
			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'show_ui',
					'key'          => 'show_ui',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['show_ui'],
					'label'        => __( 'Show In UI', 'secure-custom-fields' ),
					'instructions' => __( 'Items can be edited and managed in the admin dashboard.', 'secure-custom-fields' ),
					'ui'           => true,
					'default'      => 1,
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'show_in_menu',
					'key'          => 'show_in_menu',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['show_in_menu'],
					'label'        => __( 'Show In Admin Menu', 'secure-custom-fields' ),
					'instructions' => __( 'Admin editor navigation in the sidebar menu.', 'secure-custom-fields' ),
					'ui'           => true,
					'default'      => 1,
					'conditions'   => array(
						'field'    => 'show_ui',
						'operator' => '==',
						'value'    => 1,
					),
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'admin_menu_parent',
					'key'          => 'admin_menu_parent',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['admin_menu_parent'],
					'placeholder'  => 'edit.php?post_type={parent_page}',
					'label'        => __( 'Admin Menu Parent', 'secure-custom-fields' ),
					'instructions' => __( 'By default the post type will get a new top level item in the admin menu. If an existing top level item is supplied here, the post type will be added as a submenu item under it.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'show_in_menu',
						'operator' => '==',
						'value'    => 1,
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'number',
					'name'         => 'menu_position',
					'key'          => 'menu_position',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['menu_position'],
					'label'        => __( 'Menu Position', 'secure-custom-fields' ),
					'instructions' => __( 'The position in the sidebar menu in the admin dashboard.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'show_in_menu',
						'operator' => '==',
						'value'    => 1,
					),
				),
				'div',
				'field'
			);

			// Set the default value for the icon field.
			$acf_default_icon_value = array(
				'type'  => 'dashicons',
				'value' => 'dashicons-admin-post',
			);

			if ( empty( $acf_post_type['menu_icon'] ) ) {
				$acf_post_type['menu_icon'] = $acf_default_icon_value;
			}

			// Backwards compatibility for before the icon picker was introduced.
			if ( is_string( $acf_post_type['menu_icon'] ) ) {
				// If the old value was a string that starts with dashicons-, assume it's a dashicon.
				if ( false !== strpos( $acf_post_type['menu_icon'], 'dashicons-' ) ) {
					$acf_post_type['menu_icon'] = array(
						'type'  => 'dashicons',
						'value' => $acf_post_type['menu_icon'],
					);
				} else {
					$acf_post_type['menu_icon'] = array(
						'type'  => 'url',
						'value' => $acf_post_type['menu_icon'],
					);
				}
			}

			acf_render_field_wrap(
				array(
					'type'        => 'icon_picker',
					'name'        => 'menu_icon',
					'key'         => 'menu_icon',
					'prefix'      => 'acf_post_type',
					'value'       => $acf_post_type['menu_icon'],
					'label'       => __( 'Menu Icon', 'secure-custom-fields' ),
					'placeholder' => 'dashicons-admin-post',
					'conditions'  => array(
						array(
							'field'    => 'show_in_menu',
							'operator' => '==',
							'value'    => '1',
						),
						array(
							'field'    => 'admin_menu_parent',
							'operator' => '==',
							'value'    => '',
						),
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'register_meta_box_cb',
					'key'          => 'register_meta_box_cb',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['register_meta_box_cb'],
					'label'        => __( 'Custom Meta Box Callback', 'secure-custom-fields' ),
					'instructions' => __( 'A PHP function name to be called when setting up the meta boxes for the edit screen. For security, this callback will be executed in a special context without access to any superglobals like $_POST or $_GET.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'show_ui',
						'operator' => '==',
						'value'    => '1',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'       => 'seperator',
					'conditions' => array(
						'field'    => 'show_ui',
						'operator' => '==',
						'value'    => 1,
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'show_in_admin_bar',
					'key'          => 'show_in_admin_bar',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['show_in_admin_bar'],
					'label'        => __( 'Show In Admin Bar', 'secure-custom-fields' ),
					'instructions' => __( "Appears as an item in the 'New' menu in the admin bar.", 'secure-custom-fields' ),
					'ui'           => true,
					'default'      => 1,
					'conditions'   => array(
						'field'    => 'show_ui',
						'operator' => '==',
						'value'    => 1,
					),
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'show_in_nav_menus',
					'key'          => 'show_in_nav_menus',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['show_in_nav_menus'],
					'label'        => __( 'Appearance Menus Support', 'secure-custom-fields' ),
					'instructions' => __( "Allow items to be added to menus in the 'Appearance' > 'Menus' screen. Must be turned on in 'Screen options'.", 'secure-custom-fields' ),
					'ui'           => true,
					'default'      => 1,
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'exclude_from_search',
					'key'          => 'exclude_from_search',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['exclude_from_search'],
					'label'        => __( 'Exclude From Search', 'secure-custom-fields' ),
					'instructions' => __( 'Sets whether posts should be excluded from search results and taxonomy archive pages.', 'secure-custom-fields' ),
					'ui'           => true,
				)
			);

			break;
		case 'permissions':
			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'rename_capabilities',
					'key'          => 'rename_capabilities',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['rename_capabilities'],
					'label'        => __( 'Rename Capabilities', 'secure-custom-fields' ),
					'instructions' => __( "By default the capabilities of the post type will inherit the 'Post' capability names, eg. edit_post, delete_posts. Enable to use post type specific capabilities, eg. edit_{singular}, delete_{plural}.", 'secure-custom-fields' ),
					'default'      => false,
					'ui'           => true,
				),
				'div'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'singular_capability_name',
					'key'          => 'singular_capability_name',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['singular_capability_name'],
					'label'        => __( 'Singular Capability Name', 'secure-custom-fields' ),
					'instructions' => __( 'Choose another post type to base the capabilities for this post type.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'rename_capabilities',
						'operator' => '==',
						'value'    => '1',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'plural_capability_name',
					'key'          => 'plural_capability_name',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['plural_capability_name'],
					'label'        => __( 'Plural Capability Name', 'secure-custom-fields' ),
					'instructions' => __( 'Optionally provide a plural to be used in capabilities.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'rename_capabilities',
						'operator' => '==',
						'value'    => '1',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'       => 'seperator',
					'conditions' => array(
						'field'    => 'rename_capabilities',
						'operator' => '==',
						'value'    => '1',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'can_export',
					'key'          => 'can_export',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['can_export'],
					'label'        => __( 'Can Export', 'secure-custom-fields' ),
					'instructions' => __( "Allow the post type to be exported from 'Tools' > 'Export'.", 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => 1,
				),
				'div'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'delete_with_user',
					'key'          => 'delete_with_user',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['delete_with_user'],
					'label'        => __( 'Delete With User', 'secure-custom-fields' ),
					'instructions' => __( 'Delete items by a user when that user is deleted.', 'secure-custom-fields' ),
					'ui'           => 1,
				),
				'div'
			);
			break;
		case 'urls':
			acf_render_field_wrap(
				array(
					'type'         => 'select',
					'name'         => 'permalink_rewrite',
					'key'          => 'permalink_rewrite',
					'prefix'       => 'acf_post_type[rewrite]',
					'value'        => isset( $acf_post_type['rewrite']['permalink_rewrite'] ) ? $acf_post_type['rewrite']['permalink_rewrite'] : 'post_type_key',
					'label'        => __( 'Permalink Rewrite', 'secure-custom-fields' ),
					/* translators: this string will be appended with the new permalink structure. */
					'instructions' => __( 'Rewrite the URL using the post type key as the slug. Your permalink structure will be', 'secure-custom-fields' ) . ' {slug}.',
					'choices'      => array(
						'post_type_key'    => __( 'Post Type Key', 'secure-custom-fields' ),
						'custom_permalink' => __( 'Custom Permalink', 'secure-custom-fields' ),
						'no_permalink'     => __( 'No Permalink (prevent URL rewriting)', 'secure-custom-fields' ),
					),
					'default'      => 'post_type_key',
					'hide_search'  => true,
					'data'         => array(
						/* translators: this string will be appended with the new permalink structure. */
						'post_type_key_instructions'    => __( 'Rewrite the URL using the post type key as the slug. Your permalink structure will be', 'secure-custom-fields' ) . ' {slug}.',
						/* translators: this string will be appended with the new permalink structure. */
						'custom_permalink_instructions' => __( 'Rewrite the URL using a custom slug defined in the input below. Your permalink structure will be', 'secure-custom-fields' ) . ' {slug}.',
						'no_permalink_instructions'     => __( 'Permalinks for this post type are disabled.', 'secure-custom-fields' ),
						'site_url'                      => get_site_url(),
					),
					'class'        => 'permalink_rewrite',
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'slug',
					'key'          => 'slug',
					'prefix'       => 'acf_post_type[rewrite]',
					'value'        => isset( $acf_post_type['rewrite']['slug'] ) ? $acf_post_type['rewrite']['slug'] : '',
					'label'        => __( 'URL Slug', 'secure-custom-fields' ),
					'instructions' => __( 'Customize the slug used in the URL.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'permalink_rewrite',
						'operator' => '==',
						'value'    => 'custom_permalink',
					),
					'class'        => 'rewrite_slug_field',
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'with_front',
					'key'          => 'with_front',
					'prefix'       => 'acf_post_type[rewrite]',
					'value'        => isset( $acf_post_type['rewrite']['with_front'] ) ? $acf_post_type['rewrite']['with_front'] : true,
					'label'        => __( 'Front URL Prefix', 'secure-custom-fields' ),
					'instructions' => __( 'Alters the permalink structure to add the `WP_Rewrite::$front` prefix to URLs.', 'secure-custom-fields' ),
					'ui'           => true,
					'default'      => 1,
					'conditions'   => array(
						'field'    => 'permalink_rewrite',
						'operator' => '!=',
						'value'    => 'no_permalink',
					),
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'feeds',
					'key'          => 'feeds',
					'prefix'       => 'acf_post_type[rewrite]',
					'value'        => isset( $acf_post_type['rewrite']['feeds'] ) ? $acf_post_type['rewrite']['feeds'] : $acf_post_type['has_archive'],
					'label'        => __( 'Feed URL', 'secure-custom-fields' ),
					'instructions' => __( 'RSS feed URL for the post type items.', 'secure-custom-fields' ),
					'ui'           => true,
					'conditions'   => array(
						'field'    => 'permalink_rewrite',
						'operator' => '!=',
						'value'    => 'no_permalink',
					),
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'pages',
					'key'          => 'pages',
					'prefix'       => 'acf_post_type[rewrite]',
					'value'        => isset( $acf_post_type['rewrite']['pages'] ) ? $acf_post_type['rewrite']['pages'] : true,
					'label'        => __( 'Pagination', 'secure-custom-fields' ),
					'instructions' => __( 'Pagination support for the items URLs such as the archives.', 'secure-custom-fields' ),
					'ui'           => true,
					'default'      => 1,
					'conditions'   => array(
						'field'    => 'permalink_rewrite',
						'operator' => '!=',
						'value'    => 'no_permalink',
					),
				)
			);

			acf_render_field_wrap( array( 'type' => 'seperator' ) );

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'has_archive',
					'key'          => 'has_archive',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['has_archive'],
					'label'        => __( 'Archive', 'secure-custom-fields' ),
					'instructions' => __( 'Has an item archive that can be customized with an archive template file in your theme.', 'secure-custom-fields' ),
					'ui'           => true,
				),
				'div'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'has_archive_slug',
					'key'          => 'has_archive_slug',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['has_archive_slug'],
					'label'        => __( 'Archive Slug', 'secure-custom-fields' ),
					'instructions' => __( 'Custom slug for the Archive URL.', 'secure-custom-fields' ),
					'ui'           => true,
					'conditions'   => array(
						'field'    => 'has_archive',
						'operator' => '==',
						'value'    => '1',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap( array( 'type' => 'seperator' ) );

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'publicly_queryable',
					'key'          => 'publicly_queryable',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['publicly_queryable'],
					'label'        => __( 'Publicly Queryable', 'secure-custom-fields' ),
					'instructions' => __( 'URLs for an item and items can be accessed with a query string.', 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => true,
				),
				'div'
			);

			acf_render_field_wrap(
				array(
					'type'       => 'seperator',
					'conditions' => array(
						'field'    => 'publicly_queryable',
						'operator' => '==',
						'value'    => 1,
					),
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'select',
					'name'         => 'query_var',
					'key'          => 'query_var',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['query_var'],
					'label'        => __( 'Query Variable Support', 'secure-custom-fields' ),
					'instructions' => __( 'Items can be accessed using the non-pretty permalink, eg. {post_type}={post_slug}.', 'secure-custom-fields' ),
					'choices'      => array(
						'post_type_key'    => __( 'Post Type Key', 'secure-custom-fields' ),
						'custom_query_var' => __( 'Custom Query Variable', 'secure-custom-fields' ),
						'none'             => __( 'No Query Variable Support', 'secure-custom-fields' ),
					),
					'default'      => 1,
					'hide_search'  => true,
					'class'        => 'query_var',
					'conditions'   => array(
						'field'    => 'publicly_queryable',
						'operator' => '==',
						'value'    => '1',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'query_var_name',
					'key'          => 'query_var_name',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['query_var_name'],
					'label'        => __( 'Query Variable', 'secure-custom-fields' ),
					'instructions' => __( 'Customize the query variable name.', 'secure-custom-fields' ),
					'ui'           => true,
					'conditions'   => array(
						'field'    => 'query_var',
						'operator' => '==',
						'value'    => 'custom_query_var',
					),
				),
				'div',
				'field'
			);

			break;
		case 'rest_api':
			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'show_in_rest',
					'key'          => 'show_in_rest',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['show_in_rest'],
					'label'        => __( 'Show In REST API', 'secure-custom-fields' ),
					'instructions' => __( 'Exposes this post type in the REST API. Required to use the block editor.', 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => true,
				),
				'div'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'rest_base',
					'key'          => 'rest_base',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['rest_base'],
					'label'        => __( 'Base URL', 'secure-custom-fields' ),
					'instructions' => __( 'The base URL for the post type REST API URLs.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'show_in_rest',
						'operator' => '==',
						'value'    => '1',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'rest_namespace',
					'key'          => 'rest_namespace',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['rest_namespace'],
					'label'        => __( 'Namespace Route', 'secure-custom-fields' ),
					'instructions' => __( 'The namespace part of the REST API URL.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'show_in_rest',
						'operator' => '==',
						'value'    => '1',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'rest_controller_class',
					'key'          => 'rest_controller_class',
					'prefix'       => 'acf_post_type',
					'value'        => $acf_post_type['rest_controller_class'],
					'label'        => __( 'Controller Class', 'secure-custom-fields' ),
					'instructions' => __( 'Optional custom controller to use instead of `WP_REST_Posts_Controller`.', 'secure-custom-fields' ),
					'default'      => 'WP_REST_Posts_Controller',
					'conditions'   => array(
						'field'    => 'show_in_rest',
						'operator' => '==',
						'value'    => '1',
					),
				),
				'div',
				'field'
			);
			break;
	}

	do_action( "acf/post_type/render_settings_tab/{$tab_key}", $acf_post_type );

	echo '</div>';
}
