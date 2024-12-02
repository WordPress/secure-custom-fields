<?php

global $acf_taxonomy;

foreach ( acf_get_combined_taxonomy_settings_tabs() as $tab_key => $tab_label ) {
	acf_render_field_wrap(
		array(
			'type'  => 'tab',
			'label' => $tab_label,
			'key'   => 'acf_taxonomy_tabs',
		)
	);

	$wrapper_class = str_replace( '_', '-', $tab_key );

	echo '<div class="acf-taxonomy-advanced-settings acf-taxonomy-' . esc_attr( $wrapper_class ) . '-settings">';

	switch ( $tab_key ) {
		case 'general':
			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'key'          => 'sort',
					'name'         => 'sort',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['sort'],
					'label'        => __( 'Sort Terms', 'secure-custom-fields' ),
					'instructions' => __( 'Whether terms in this taxonomy should be sorted in the order they are provided to `wp_set_object_terms()`.', 'secure-custom-fields' ),
					'ui'           => true,
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'key'          => 'default_term_enabled',
					'name'         => 'default_term_enabled',
					'prefix'       => 'acf_taxonomy[default_term]',
					'value'        => $acf_taxonomy['default_term']['default_term_enabled'],
					'label'        => __( 'Default Term', 'secure-custom-fields' ),
					'instructions' => __( 'Create a term for the taxonomy that cannot be deleted. It will not be selected for posts by default.', 'secure-custom-fields' ),
					'ui'           => true,
				)
			);
			?>
			
			<?php
			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'default_term_name',
					'key'          => 'default_term_name',
					'prefix'       => 'acf_taxonomy[default_term]',
					'value'        => isset( $acf_taxonomy['default_term']['default_term_name'] ) ? $acf_taxonomy['default_term']['default_term_name'] : '',
					'label'        => __( 'Term Name', 'secure-custom-fields' ),
					'instructions' => __( 'The name of the default term.', 'secure-custom-fields' ),
					'required'     => 1,
					'conditions'   => array(
						'field'    => 'default_term_enabled',
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
					'name'         => 'default_term_slug',
					'key'          => 'default_term_slug',
					'prefix'       => 'acf_taxonomy[default_term]',
					'value'        => isset( $acf_taxonomy['default_term']['default_term_slug'] ) ? $acf_taxonomy['default_term']['default_term_slug'] : '',
					'label'        => __( 'Term Slug', 'secure-custom-fields' ),
					'instructions' => __( 'Single word, no spaces. Underscores and dashes allowed.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'default_term_enabled',
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
					'name'         => 'description',
					'key'          => 'default_term_description',
					'prefix'       => 'acf_taxonomy[default_term]',
					'value'        => isset( $acf_taxonomy['default_term']['default_term_description'] ) ? $acf_taxonomy['default_term']['default_term_description'] : '',
					'label'        => __( 'Term Description', 'secure-custom-fields' ),
					'instructions' => __( 'A descriptive summary of the term.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'default_term_enabled',
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
					'type'         => 'text',
					'name'         => 'description',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['description'],
					'label'        => __( 'Description', 'secure-custom-fields' ),
					'instructions' => __( 'A descriptive summary of the taxonomy.', 'secure-custom-fields' ),
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
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['active'],
					'label'        => __( 'Active', 'secure-custom-fields' ),
					'instructions' => __( 'Active taxonomies are enabled and registered with WordPress.', 'secure-custom-fields' ),
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
					'key'          => 'menu_name',
					'name'         => 'menu_name',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['menu_name'],
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'   => '%s',
						'replace' => 'plural',
					),
					'label'        => __( 'Menu Label', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns the menu name text.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Tags', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'all_items',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['all_items'],
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'   => __( 'All %s', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'All Items', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns the all items text.', 'secure-custom-fields' ),
					'placeholder'  => __( 'All Tags', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'edit_item',
					'name'         => 'edit_item',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['edit_item'],
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'   => __( 'Edit %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Edit Item', 'secure-custom-fields' ),
					'instructions' => __( 'At the top of the editor screen when editing a term.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Edit Tag', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'view_item',
					'name'         => 'view_item',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['view_item'],
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'   => __( 'View %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'View Item', 'secure-custom-fields' ),
					'instructions' => __( 'In the admin bar to view term during editing.', 'secure-custom-fields' ),
					'placeholder'  => __( 'View Tag', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'update_item',
					'name'         => 'update_item',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['update_item'],
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'   => __( 'Update %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Update Item', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns the update item text.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Update Tag', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'add_new_item',
					'name'         => 'add_new_item',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['add_new_item'],
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'   => __( 'Add New %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Add New Item', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns the add new item text.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Add New Tag', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'new_item_name',
					'name'         => 'new_item_name',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['new_item_name'],
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'   => __( 'New %s Name', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'New Item Name', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns the new item name text.', 'secure-custom-fields' ),
					'placeholder'  => __( 'New Tag Name', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'parent_item',
					'name'         => 'parent_item',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => isset( $acf_taxonomy['labels']['parent_item'] ) ? $acf_taxonomy['labels']['parent_item'] : '',
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'   => __( 'Parent %s', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Parent Item', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns parent item text. Only used on hierarchical taxonomies.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Parent Category', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'hierarchical',
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
					'key'          => 'parent_item_colon',
					'name'         => 'parent_item_colon',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => isset( $acf_taxonomy['labels']['parent_item_colon'] ) ? $acf_taxonomy['labels']['parent_item_colon'] : '',
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'   => __( 'Parent %s:', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Parent Item With Colon', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns parent item text, but with a colon (:) added to the end.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Parent Category:', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'hierarchical',
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
					'name'         => 'search_items',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['search_items'],
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'   => __( 'Search %s', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'Search Items', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns search items text.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Search Tags', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'popular_items',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => isset( $acf_taxonomy['labels']['popular_items'] ) ? $acf_taxonomy['labels']['popular_items'] : '',
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'   => __( 'Popular %s', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'Popular Items', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns popular items text. Only used for non-hierarchical taxonomies.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Popular Tags', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'hierarchical',
						'operator' => '==',
						'value'    => '0',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'separate_items_with_commas',
					'name'         => 'separate_items_with_commas',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => isset( $acf_taxonomy['labels']['separate_items_with_commas'] ) ? $acf_taxonomy['labels']['separate_items_with_commas'] : '',
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'     => __( 'Separate %s with commas', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'Separate Items With Commas', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns the separate item with commas text used in the taxonomy meta box. Only used on non-hierarchical taxonomies.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Separate tags with commas', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'hierarchical',
						'operator' => '==',
						'value'    => '0',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'add_or_remove_items',
					'name'         => 'add_or_remove_items',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => isset( $acf_taxonomy['labels']['add_or_remove_items'] ) ? $acf_taxonomy['labels']['add_or_remove_items'] : '',
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'     => __( 'Add or remove %s', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'Add Or Remove Items', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns the add or remove items text used in the meta box when JavaScript is disabled. Only used on non-hierarchical taxonomies', 'secure-custom-fields' ),
					'placeholder'  => __( 'Add or remove tags', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'hierarchical',
						'operator' => '==',
						'value'    => '0',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'choose_from_most_used',
					'name'         => 'choose_from_most_used',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => isset( $acf_taxonomy['labels']['choose_from_most_used'] ) ? $acf_taxonomy['labels']['choose_from_most_used'] : '',
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'     => __( 'Choose from the most used %s', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'Choose From Most Used', 'secure-custom-fields' ),
					'instructions' => __( "Assigns the 'choose from most used' text used in the meta box when JavaScript is disabled. Only used on non-hierarchical taxonomies.", 'secure-custom-fields' ),
					'placeholder'  => __( 'Choose from the most used tags', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'hierarchical',
						'operator' => '==',
						'value'    => '0',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'most_used',
					'name'         => 'most_used',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['most_used'],
					'label'        => __( 'Most Used', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns text to the Title field of the Most Used tab.', 'secure-custom-fields' ),
					'default'      => __( 'Most Used', 'secure-custom-fields' ),
					'placeholder'  => __( 'Most Used', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'not_found',
					'name'         => 'not_found',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['not_found'],
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'     => __( 'No %s found', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'Not Found', 'secure-custom-fields' ),
					'instructions' => __( "Assigns the text displayed when clicking the 'choose from most used' text in the taxonomy meta box when no tags are available, and assigns the text used in the terms list table when there are no items for a taxonomy.", 'secure-custom-fields' ),
					'placeholder'  => __( 'No tags found', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'no_terms',
					'name'         => 'no_terms',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['no_terms'],
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'     => __( 'No %s', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'No Terms', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns the text displayed in the posts and media list tables when no tags or categories are available.', 'secure-custom-fields' ),
					'placeholder'  => __( 'No tags', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'name_field_description',
					'name'         => 'name_field_description',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['name_field_description'],
					'label'        => __( 'Name Field Description', 'secure-custom-fields' ),
					'instructions' => __( 'Describes the Name field on the Edit Tags screen.', 'secure-custom-fields' ),
					'placeholder'  => __( 'The name is how it appears on your site', 'secure-custom-fields' ),
					'default'      => __( 'The name is how it appears on your site', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'textarea',
					'key'          => 'slug_field_description',
					'name'         => 'slug_field_description',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['slug_field_description'],
					'label'        => __( 'Slug Field Description', 'secure-custom-fields' ),
					'instructions' => __( 'Describes the Slug field on the Edit Tags screen.', 'secure-custom-fields' ),
					'placeholder'  => __( 'The "slug" is the URL-friendly version of the name. It is usually all lower case and contains only letters, numbers, and hyphens.', 'secure-custom-fields' ),
					'default'      => __( 'The "slug" is the URL-friendly version of the name. It is usually all lower case and contains only letters, numbers, and hyphens.', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'parent_field_description',
					'name'         => 'parent_field_description',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => isset( $acf_taxonomy['labels']['parent_field_description'] ) ? $acf_taxonomy['labels']['parent_field_description'] : '',
					'label'        => __( 'Parent Field Description', 'secure-custom-fields' ),
					'instructions' => __( 'Describes the Parent field on the Edit Tags screen.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band', 'secure-custom-fields' ),
					'default'      => __( 'Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'hierarchical',
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
					'key'          => 'desc_field_description',
					'name'         => 'desc_field_description',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['desc_field_description'],
					'label'        => __( 'Description Field Description', 'secure-custom-fields' ),
					'instructions' => __( 'Describes the Description field on the Edit Tags screen.', 'secure-custom-fields' ),
					'placeholder'  => __( 'The description is not prominent by default; however, some themes may show it.', 'secure-custom-fields' ),
					'default'      => __( 'The description is not prominent by default; however, some themes may show it.', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'filter_by_item',
					'name'         => 'filter_by_item',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => isset( $acf_taxonomy['labels']['filter_by_item'] ) ? $acf_taxonomy['labels']['filter_by_item'] : '',
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'     => __( 'Filter by %s', 'secure-custom-fields' ),
						'replace'   => 'singular',
						'transform' => 'lower',
					),
					'label'        => __( 'Filter By Item', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns text to the filter button in the posts lists table.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Filter by category', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'hierarchical',
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
					'key'          => 'items_list_navigation',
					'name'         => 'items_list_navigation',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['items_list_navigation'],
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'   => __( '%s list navigation', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'Items List Navigation', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns text to the table pagination hidden heading.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Tags list navigation', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'items_list',
					'name'         => 'items_list',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['items_list'],
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'   => __( '%s list', 'secure-custom-fields' ),
						'replace' => 'plural',
					),
					'label'        => __( 'Items List', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns text to the table hidden heading.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Tags list', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'back_to_items',
					'name'         => 'back_to_items',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['back_to_items'],
					'data'         => array(
						/* translators: %s Plural form of taxonomy name */
						'label'     => __( '← Go to %s', 'secure-custom-fields' ),
						'replace'   => 'plural',
						'transform' => 'lower',
					),
					'label'        => __( 'Back To Items', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns the text used to link back to the main index after updating a term.', 'secure-custom-fields' ),
					'placeholder'  => __( '← Go to tags', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'item_link',
					'name'         => 'item_link',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['item_link'],
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'   => __( '%s Link', 'secure-custom-fields' ),
						'replace' => 'singular',
					),
					'label'        => __( 'Item Link', 'secure-custom-fields' ),
					'instructions' => __( 'Assigns a title for navigation link block variation used in the block editor.', 'secure-custom-fields' ),
					'placeholder'  => __( 'Tag Link', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'item_link_description',
					'name'         => 'item_link_description',
					'prefix'       => 'acf_taxonomy[labels]',
					'value'        => $acf_taxonomy['labels']['item_link_description'],
					'data'         => array(
						/* translators: %s Singular form of taxonomy name */
						'label'     => __( 'A link to a %s', 'secure-custom-fields' ),
						'replace'   => 'singular',
						'transform' => 'lower',
					),
					'label'        => __( 'Item Link Description', 'secure-custom-fields' ),
					'instructions' => __( 'Describes a navigation link block variation used in the block editor.', 'secure-custom-fields' ),
					'placeholder'  => __( 'A link to a tag', 'secure-custom-fields' ),
				),
				'div',
				'field'
			);
			break;
		case 'visibility':
			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'key'          => 'show_ui',
					'name'         => 'show_ui',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['show_ui'],
					'label'        => __( 'Show In UI', 'secure-custom-fields' ),
					'instructions' => __( 'Items can be edited and managed in the admin dashboard.', 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => true,
				),
				'div'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'key'          => 'show_in_menu',
					'name'         => 'show_in_menu',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['show_in_menu'],
					'label'        => __( 'Show In Admin Menu', 'secure-custom-fields' ),
					'instructions' => __( 'Admin editor navigation in the sidebar menu.', 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => true,
					'conditions'   => array(
						'field'    => 'show_ui',
						'operator' => '==',
						'value'    => '1',
					),
				)
			);

			$acf_tags_meta_box_text       = __( 'Tags Meta Box', 'secure-custom-fields' );
			$acf_categories_meta_box_text = __( 'Categories Meta Box', 'secure-custom-fields' );
			$acf_default_meta_box_text    = empty( $acf_taxonomy['hierarchical'] ) ? $acf_tags_meta_box_text : $acf_categories_meta_box_text;

			acf_render_field_wrap(
				array(
					'type'         => 'select',
					'key'          => 'meta_box',
					'name'         => 'meta_box',
					'class'        => 'meta_box',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['meta_box'],
					'label'        => __( 'Meta Box', 'secure-custom-fields' ),
					'instructions' => __( 'Controls the meta box on the content editor screen. By default, the Categories meta box is shown for hierarchical taxonomies, and the Tags meta box is shown for non-hierarchical taxonomies.', 'secure-custom-fields' ),
					'hide_search'  => true,
					'choices'      => array(
						'default'  => $acf_default_meta_box_text,
						'custom'   => __( 'Custom Meta Box', 'secure-custom-fields' ),
						'disabled' => __( 'No Meta Box', 'secure-custom-fields' ),
					),
					'data'         => array(
						'tags_meta_box'       => __( 'Tags Meta Box', 'secure-custom-fields' ),
						'categories_meta_box' => __( 'Categories Meta Box', 'secure-custom-fields' ),
					),
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
						array(
							'field'    => 'meta_box',
							'operator' => '!=',
							'value'    => 'custom',
						),
						array(
							'field'    => 'show_ui',
							'operator' => '==',
							'value'    => '1',
						),
					),
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'meta_box_cb',
					'key'          => 'meta_box_cb',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['meta_box_cb'],
					'label'        => __( 'Register Meta Box Callback', 'secure-custom-fields' ),
					'instructions' => __( 'A PHP function name to be called to handle the content of a meta box on your taxonomy. For security, this callback will be executed in a special context without access to any superglobals like $_POST or $_GET.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'meta_box',
						'operator' => '==',
						'value'    => 'custom',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'name'         => 'meta_box_sanitize_cb',
					'key'          => 'meta_box_sanitize_cb',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['meta_box_sanitize_cb'],
					'label'        => __( 'Meta Box Sanitization Callback', 'secure-custom-fields' ),
					'instructions' => __( 'A PHP function name to be called for sanitizing taxonomy data saved from a meta box.', 'secure-custom-fields' ),
					'conditions'   => array(
						'field'    => 'meta_box',
						'operator' => '==',
						'value'    => 'custom',
					),
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'       => 'seperator',
					'conditions' => array(
						'field'    => 'meta_box',
						'operator' => '==',
						'value'    => 'custom',
					),
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'key'          => 'show_in_nav_menus',
					'name'         => 'show_in_nav_menus',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['show_in_nav_menus'],
					'label'        => __( 'Appearance Menus Support', 'secure-custom-fields' ),
					'instructions' => __( "Allow items to be added to menus in the 'Appearance' > 'Menus' screen. Must be turned on in 'Screen options'.", 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => true,
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'key'          => 'show_tagcloud',
					'name'         => 'show_tagcloud',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['show_tagcloud'],
					'label'        => __( 'Tag Cloud', 'secure-custom-fields' ),
					'instructions' => __( 'List the taxonomy in the Tag Cloud Widget controls.', 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => true,
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'key'          => 'show_in_quick_edit',
					'name'         => 'show_in_quick_edit',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['show_in_quick_edit'],
					'label'        => __( 'Quick Edit', 'secure-custom-fields' ),
					'instructions' => __( 'Show the taxonomy in the quick/bulk edit panel.', 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => true,
				)
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'key'          => 'show_admin_column',
					'name'         => 'show_admin_column',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['show_admin_column'],
					'label'        => __( 'Show Admin Column', 'secure-custom-fields' ),
					'instructions' => __( 'Display a column for the taxonomy on post type listing screens.', 'secure-custom-fields' ),
					'ui'           => true,
				)
			);

			break;
		case 'urls':
			acf_render_field_wrap(
				array(
					'type'         => 'select',
					'name'         => 'permalink_rewrite',
					'key'          => 'permalink_rewrite',
					'prefix'       => 'acf_taxonomy[rewrite]',
					'value'        => isset( $acf_taxonomy['rewrite']['permalink_rewrite'] ) ? $acf_taxonomy['rewrite']['permalink_rewrite'] : 'taxonomy_key',
					'label'        => __( 'Permalink Rewrite', 'secure-custom-fields' ),
					'instructions' => __( 'Select the type of permalink to use for this taxonomy.', 'secure-custom-fields' ) . ' {slug}.',
					'choices'      => array(
						'taxonomy_key'     => __( 'Taxonomy Key', 'secure-custom-fields' ),
						'custom_permalink' => __( 'Custom Permalink', 'secure-custom-fields' ),
						'no_permalink'     => __( 'No Permalink (prevent URL rewriting)', 'secure-custom-fields' ),
					),
					'default'      => 'taxonomy_key',
					'hide_search'  => true,
					'data'         => array(
						/* translators: this string will be appended with the new permalink structure. */
						'taxonomy_key_instructions'     => __( 'Rewrite the URL using the taxonomy key as the slug. Your permalink structure will be', 'secure-custom-fields' ) . ' {slug}.',
						/* translators: this string will be appended with the new permalink structure. */
						'custom_permalink_instructions' => __( 'Rewrite the URL using a custom slug defined in the input below. Your permalink structure will be', 'secure-custom-fields' ) . ' {slug}.',
						'no_permalink_instructions'     => __( 'Permalinks for this taxonomy are disabled.', 'secure-custom-fields' ),
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
					'prefix'       => 'acf_taxonomy[rewrite]',
					'value'        => isset( $acf_taxonomy['rewrite']['slug'] ) ? $acf_taxonomy['rewrite']['slug'] : '',
					'label'        => __( 'URL Slug', 'secure-custom-fields' ),
					'instructions' => __( 'Customize the slug used in the URL', 'secure-custom-fields' ),
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
					'prefix'       => 'acf_taxonomy[rewrite]',
					'value'        => isset( $acf_taxonomy['rewrite']['with_front'] ) ? $acf_taxonomy['rewrite']['with_front'] : true,
					'label'        => __( 'Front URL Prefix', 'secure-custom-fields' ),
					'instructions' => __( 'Alters the permalink structure to add the `WP_Rewrite::$front` prefix to URLs.', 'secure-custom-fields' ),
					'ui'           => true,
					'default'      => 1,
					'conditions'   => array(
						'field'    => 'permalink_rewrite',
						'operator' => '!=',
						'value'    => 'no_permalink',
					),
				),
				'div'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'rewrite_hierarchical',
					'key'          => 'rewrite_hierarchical',
					'prefix'       => 'acf_taxonomy[rewrite]',
					'value'        => isset( $acf_taxonomy['rewrite']['rewrite_hierarchical'] ) ? $acf_taxonomy['rewrite']['rewrite_hierarchical'] : false,
					'label'        => __( 'Hierarchical', 'secure-custom-fields' ),
					'instructions' => __( 'Parent-child terms in URLs for hierarchical taxonomies.', 'secure-custom-fields' ),
					'ui'           => true,
					'default'      => 0,
					'conditions'   => array(
						'field'    => 'permalink_rewrite',
						'operator' => '!=',
						'value'    => 'no_permalink',
					),
				),
				'div'
			);

			acf_render_field_wrap( array( 'type' => 'seperator' ) );

			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'name'         => 'publicly_queryable',
					'key'          => 'publicly_queryable',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['publicly_queryable'],
					'label'        => __( 'Publicly Queryable', 'secure-custom-fields' ),
					'instructions' => __( 'URLs for an item and items can be accessed with a query string.', 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => true,
				)
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
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['query_var'],
					'label'        => __( 'Query Variable Support', 'secure-custom-fields' ),
					'instructions' => __( 'Terms can be accessed using the non-pretty permalink, e.g., {query_var}={term_slug}.', 'secure-custom-fields' ),
					'choices'      => array(
						'post_type_key'    => __( 'Taxonomy Key', 'secure-custom-fields' ),
						'custom_query_var' => __( 'Custom Query Variable', 'secure-custom-fields' ),
						'none'             => __( 'No Query Variable Support', 'secure-custom-fields' ),
					),
					'default'      => 1,
					'hide_search'  => true,
					'class'        => 'query_var',
					'conditions'   => array(
						'field'    => 'publicly_queryable',
						'operator' => '==',
						'value'    => 1,
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
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['query_var_name'],
					'label'        => __( 'Query Variable', 'secure-custom-fields' ),
					'instructions' => __( 'Customize the query variable name', 'secure-custom-fields' ),
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
		case 'permissions':
			$acf_all_caps = array();

			foreach ( wp_roles()->roles as $acf_role ) {
				$acf_all_caps = array_merge( $acf_all_caps, $acf_role['capabilities'] );
			}
			$acf_all_caps = array_unique( array_keys( $acf_all_caps ) );
			$acf_all_caps = array_combine( $acf_all_caps, $acf_all_caps );

			acf_render_field_wrap(
				array(
					'type'         => 'select',
					'name'         => 'manage_terms',
					'key'          => 'manage_terms',
					'prefix'       => 'acf_taxonomy[capabilities]',
					'value'        => $acf_taxonomy['capabilities']['manage_terms'],
					'label'        => __( 'Manage Terms Capability', 'secure-custom-fields' ),
					'instructions' => __( 'The capability name for managing terms of this taxonomy.', 'secure-custom-fields' ),
					'choices'      => $acf_all_caps,
					'default'      => 'manage_categories',
					'class'        => 'acf-taxonomy-manage_terms',
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'select',
					'name'         => 'edit_terms',
					'key'          => 'edit_terms',
					'prefix'       => 'acf_taxonomy[capabilities]',
					'value'        => $acf_taxonomy['capabilities']['edit_terms'],
					'label'        => __( 'Edit Terms Capability', 'secure-custom-fields' ),
					'instructions' => __( 'The capability name for editing terms of this taxonomy.', 'secure-custom-fields' ),
					'choices'      => $acf_all_caps,
					'default'      => 'manage_categories',
					'class'        => 'acf-taxonomy-edit_terms',
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'select',
					'name'         => 'delete_terms',
					'key'          => 'delete_terms',
					'prefix'       => 'acf_taxonomy[capabilities]',
					'value'        => $acf_taxonomy['capabilities']['delete_terms'],
					'label'        => __( 'Delete Terms Capability', 'secure-custom-fields' ),
					'instructions' => __( 'The capability name for deleting terms of this taxonomy.', 'secure-custom-fields' ),
					'choices'      => $acf_all_caps,
					'default'      => 'manage_categories',
					'class'        => 'acf-taxonomy-delete_terms',
				),
				'div',
				'field'
			);

			acf_render_field_wrap(
				array(
					'type'         => 'select',
					'name'         => 'assign_terms',
					'key'          => 'assign_terms',
					'prefix'       => 'acf_taxonomy[capabilities]',
					'value'        => $acf_taxonomy['capabilities']['assign_terms'],
					'label'        => __( 'Assign Terms Capability', 'secure-custom-fields' ),
					'instructions' => __( 'The capability name for assigning terms of this taxonomy.', 'secure-custom-fields' ),
					'choices'      => $acf_all_caps,
					'default'      => 'edit_posts',
					'class'        => 'acf-taxonomy-assign_terms',
				),
				'div',
				'field'
			);

			break;
		case 'rest_api':
			acf_render_field_wrap(
				array(
					'type'         => 'true_false',
					'key'          => 'show_in_rest',
					'name'         => 'show_in_rest',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['show_in_rest'],
					'label'        => __( 'Show In REST API', 'secure-custom-fields' ),
					'instructions' => __( 'Expose this post type in the REST API.', 'secure-custom-fields' ),
					'default'      => 1,
					'ui'           => true,
				),
				'div'
			);
			?>

			<?php
			acf_render_field_wrap(
				array(
					'type'         => 'text',
					'key'          => 'rest_base',
					'name'         => 'rest_base',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['rest_base'],
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
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['rest_namespace'],
					'label'        => __( 'Namespace Route', 'secure-custom-fields' ),
					'instructions' => __( 'The namespace part of the REST API URL.', 'secure-custom-fields' ),
					'placeholder'  => 'wp/v2',
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
					'key'          => 'rest_controller_class',
					'name'         => 'rest_controller_class',
					'prefix'       => 'acf_taxonomy',
					'value'        => $acf_taxonomy['rest_controller_class'],
					'label'        => __( 'Controller Class', 'secure-custom-fields' ),
					'instructions' => __( 'Optional custom controller to use instead of `WP_REST_Terms_Controller `.', 'secure-custom-fields' ),
					'placeholder'  => 'WP_REST_Terms_Controller',
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

	do_action( "acf/taxonomy/render_settings_tab/{$tab_key}", $acf_taxonomy );

	echo '</div>';
}
