<?php

// global
global $field_group;

// UI needs at lease 1 location rule
if ( empty( $field_group['location'] ) ) {
	$field_group['location'] = array(
		// Group 0.
		array(
			// Rule 0.
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => 'post',
			),
		),
	);

	$acf_use_post_type    = acf_get_post_type_from_request_args( 'add-fields' );
	$acf_use_taxonomy     = acf_get_taxonomy_from_request_args( 'add-fields' );
	$acf_use_options_page = acf_get_ui_options_page_from_request_args( 'add-fields' );

	if ( $acf_use_post_type && ! empty( $acf_use_post_type['post_type'] ) ) {
		$field_group['location'] = array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => $acf_use_post_type['post_type'],
				),
			),
		);
	}

	if ( $acf_use_taxonomy && ! empty( $acf_use_taxonomy['taxonomy'] ) ) {
		$field_group['location'] = array(
			array(
				array(
					'param'    => 'taxonomy',
					'operator' => '==',
					'value'    => $acf_use_taxonomy['taxonomy'],
				),
			),
		);
	}

	if ( $acf_use_options_page && ! empty( $acf_use_options_page['menu_slug'] ) ) {
		$field_group['location'] = array(
			array(
				array(
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => $acf_use_options_page['menu_slug'],
				),
			),
		);
	}
}

foreach ( acf_get_combined_field_group_settings_tabs() as $tab_key => $tab_label ) {
	acf_render_field_wrap(
		array(
			'type'          => 'tab',
			'label'         => $tab_label,
			'key'           => 'acf_field_group_settings_tabs',
			'settings-type' => $tab_key,
		)
	);

	switch ( $tab_key ) {
		case 'location_rules':
			echo '<div class="field-group-locations field-group-settings-tab">';
				acf_get_view( 'acf-field-group/locations' );
			echo '</div>';
			break;
		case 'presentation':
			echo '<div class="field-group-setting-split-container field-group-settings-tab">';
			echo '<div class="field-group-setting-split">';

			// style
			acf_render_field_wrap(
				array(
					'label'        => __( 'Style', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'button_group',
					'name'         => 'style',
					'prefix'       => 'acf_field_group',
					'value'        => $field_group['style'],
					'choices'      => array(
						'default'  => __( 'Standard (WP metabox)', 'secure-custom-fields' ),
						'seamless' => __( 'Seamless (no metabox)', 'secure-custom-fields' ),
					),
				)
			);


			// position
			acf_render_field_wrap(
				array(
					'label'         => __( 'Position', 'secure-custom-fields' ),
					'instructions'  => __( "'High' position not supported in the Block Editor", 'secure-custom-fields' ),
					'type'          => 'button_group',
					'name'          => 'position',
					'prefix'        => 'acf_field_group',
					'value'         => $field_group['position'],
					'choices'       => array(
						'acf_after_title' => __( 'High (after title)', 'secure-custom-fields' ),
						'normal'          => __( 'Normal (after content)', 'secure-custom-fields' ),
						'side'            => __( 'Side', 'secure-custom-fields' ),
					),
					'default_value' => 'normal',
				),
				'div',
				'field'
			);


			// label_placement
			acf_render_field_wrap(
				array(
					'label'        => __( 'Label Placement', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'button_group',
					'name'         => 'label_placement',
					'prefix'       => 'acf_field_group',
					'value'        => $field_group['label_placement'],
					'choices'      => array(
						'top'  => __( 'Top aligned', 'secure-custom-fields' ),
						'left' => __( 'Left aligned', 'secure-custom-fields' ),
					),
				)
			);


			// instruction_placement
			acf_render_field_wrap(
				array(
					'label'        => __( 'Instruction Placement', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'button_group',
					'name'         => 'instruction_placement',
					'prefix'       => 'acf_field_group',
					'value'        => $field_group['instruction_placement'],
					'choices'      => array(
						'label' => __( 'Below labels', 'secure-custom-fields' ),
						'field' => __( 'Below fields', 'secure-custom-fields' ),
					),
				)
			);


			// menu_order
			acf_render_field_wrap(
				array(
					'label'        => __( 'Order No.', 'secure-custom-fields' ),
					'instructions' => __( 'Field groups with a lower order will appear first', 'secure-custom-fields' ),
					'type'         => 'number',
					'name'         => 'menu_order',
					'prefix'       => 'acf_field_group',
					'value'        => $field_group['menu_order'],
				),
				'div',
				'field'
			);

			echo '</div>';
			echo '<div class="field-group-setting-split">';

			// hide on screen
			$choices = array(
				'permalink'       => __( 'Permalink', 'secure-custom-fields' ),
				'the_content'     => __( 'Content Editor', 'secure-custom-fields' ),
				'excerpt'         => __( 'Excerpt', 'secure-custom-fields' ),
				'custom_fields'   => __( 'Custom Fields', 'secure-custom-fields' ),
				'discussion'      => __( 'Discussion', 'secure-custom-fields' ),
				'comments'        => __( 'Comments', 'secure-custom-fields' ),
				'revisions'       => __( 'Revisions', 'secure-custom-fields' ),
				'slug'            => __( 'Slug', 'secure-custom-fields' ),
				'author'          => __( 'Author', 'secure-custom-fields' ),
				'format'          => __( 'Format', 'secure-custom-fields' ),
				'page_attributes' => __( 'Page Attributes', 'secure-custom-fields' ),
				'featured_image'  => __( 'Featured Image', 'secure-custom-fields' ),
				'categories'      => __( 'Categories', 'secure-custom-fields' ),
				'tags'            => __( 'Tags', 'secure-custom-fields' ),
				'send-trackbacks' => __( 'Send Trackbacks', 'secure-custom-fields' ),
			);
			if ( acf_get_setting( 'remove_wp_meta_box' ) ) {
				unset( $choices['custom_fields'] );
			}

			acf_render_field_wrap(
				array(
					'label'        => __( 'Hide on screen', 'secure-custom-fields' ),
					'instructions' => __( '<b>Select</b> items to <b>hide</b> them from the edit screen.', 'secure-custom-fields' ) . '<br /><br />' . __( "If multiple field groups appear on an edit screen, the first field group's options will be used (the one with the lowest order number)", 'secure-custom-fields' ),
					'type'         => 'checkbox',
					'name'         => 'hide_on_screen',
					'prefix'       => 'acf_field_group',
					'value'        => $field_group['hide_on_screen'],
					'toggle'       => true,
					'choices'      => $choices,
				),
				'div',
				'label',
				true
			);

			echo '</div>';
			echo '</div>';
			break;
		case 'group_settings':
			echo '<div class="field-group-settings field-group-settings-tab">';

			// active
			acf_render_field_wrap(
				array(
					'label'        => __( 'Active', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'true_false',
					'name'         => 'active',
					'prefix'       => 'acf_field_group',
					'value'        => $field_group['active'],
					'ui'           => 1,
				// 'ui_on_text'  => __('Active', 'secure-custom-fields'),
				// 'ui_off_text' => __('Inactive', 'secure-custom-fields'),
				)
			);

			// Show fields in REST API.
			if ( acf_get_setting( 'rest_api_enabled' ) ) {
				acf_render_field_wrap(
					array(
						'label'        => __( 'Show in REST API', 'secure-custom-fields' ),
						'instructions' => '',
						'type'         => 'true_false',
						'name'         => 'show_in_rest',
						'prefix'       => 'acf_field_group',
						'value'        => $field_group['show_in_rest'],
						'ui'           => 1,
					// 'ui_on_text'  => __('Active', 'secure-custom-fields'),
					// 'ui_off_text' => __('Inactive', 'secure-custom-fields'),
					)
				);
			}

			// description
			acf_render_field_wrap(
				array(
					'label'        => __( 'Description', 'secure-custom-fields' ),
					'instructions' => __( 'Shown in field group list', 'secure-custom-fields' ),
					'type'         => 'text',
					'name'         => 'description',
					'prefix'       => 'acf_field_group',
					'value'        => $field_group['description'],
				),
				'div',
				'field'
			);

			/* translators: 1: Post creation date 2: Post creation time */
			$acf_created_on = sprintf( __( 'Created on %1$s at %2$s', 'secure-custom-fields' ), get_the_date(), get_the_time() );
			?>
			<div class="acf-field-group-settings-footer">
				<span class="acf-created-on"><?php echo esc_html( $acf_created_on ); ?></span>
				<a href="<?php echo get_delete_post_link(); ?>" class="acf-btn acf-btn-tertiary  acf-delete-field-group">
					<i class="acf-icon acf-icon-trash"></i>
					<?php esc_html_e( 'Delete Field Group', 'secure-custom-fields' ); ?>
				</a>
			</div>
			<?php
			echo '</div>';
			break;
		default:
			echo '<div class="field-group-' . esc_attr( $tab_key ) . ' field-group-settings-tab">';
			do_action( 'acf/field_group/render_group_settings_tab/' . $tab_key, $field_group );
			echo '</div>';
			break;
	}
}

// 3rd party settings
do_action( 'acf/render_field_group_settings', $field_group );
?>

<div class="acf-hidden">
	<input type="hidden" name="acf_field_group[key]" value="<?php echo esc_attr( $field_group['key'] ); ?>" />
</div>
<script type="text/javascript">
if( typeof acf !== 'undefined' ) {

	acf.newPostbox({
		'id': 'acf-field-group-options',
		'label': 'top'
	});

}
</script>
