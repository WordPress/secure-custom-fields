<?php
//phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included template file.

// Define input name prefix using unique identifier.
$input_prefix = 'acf_fields[' . $field['ID'] . ']';
$input_id     = acf_idify( $input_prefix );

// Update field props.
$field['prefix'] = $input_prefix;

// Elements.
$div_attrs = array(
	'class'     => 'acf-field-object acf-field-object-' . acf_slugify( $field['type'] ),
	'data-id'   => $field['ID'],
	'data-key'  => $field['key'],
	'data-type' => $field['type'],
);

// Add additional class if the field is an endpoint.
if ( isset( $field['endpoint'] ) && $field['endpoint'] ) {
	$div_attrs['class'] .= ' acf-field-is-endpoint';
}


// Misc template vars.
$field_label          = acf_get_field_label( $field, 'admin' );
$field_type_label     = acf_get_field_type_label( $field['type'] );
$field_type_supports  = acf_get_field_type_prop( $field['type'], 'supports' );
$inactive_field_class = '';
$inactive_field_title = '';

if ( ! isset( $num_field_groups ) ) {
	$num_field_groups = 0;
}

$conditional_logic_class = $conditional_logic_text = '';
if ( isset( $field['conditional_logic'] ) && is_array( $field['conditional_logic'] ) && count( $field['conditional_logic'] ) > 0 ) {
	$conditional_logic_class = ' is-enabled';
	$conditional_logic_text  = __( 'Active', 'secure-custom-fields' );
}

?>
<div <?php echo acf_esc_attrs( $div_attrs ); ?>>

	<div class="meta">
		<?php
		$meta_inputs = array(
			'ID'         => $field['ID'],
			'key'        => $field['key'],
			'parent'     => $field['parent'],
			'menu_order' => $i,
			'save'       => '',
		);
		foreach ( $meta_inputs as $k => $v ) :
			acf_hidden_input(
				array(
					'name'  => $input_prefix . '[' . $k . ']',
					'value' => $v,
					'id'    => $input_id . '-' . $k,
				)
			);
		endforeach;
		?>
	</div>

	<div class="handle">
		<ul class="acf-hl acf-tbody">
			<li class="li-field-order">
				<span class="acf-icon acf-sortable-handle" title="<?php esc_attr_e( 'Drag to reorder', 'secure-custom-fields' ); ?>"><?php echo intval( $i + 1 ); ?></span>
			</li>
			<li class="li-field-label">
				<strong class="<?php echo esc_attr( $inactive_field_class ); ?>" title="<?php echo esc_attr( $inactive_field_title ); ?>">
					<a class="edit-field" title="<?php esc_attr_e( 'Edit field', 'secure-custom-fields' ); ?>" href="#"><?php echo acf_esc_html( $field_label ); ?></a>
				</strong>
				<div class="row-options">
					<a class="edit-field" title="<?php esc_attr_e( 'Edit field', 'secure-custom-fields' ); ?>" href="#"><?php esc_html_e( 'Edit', 'secure-custom-fields' ); ?></a>
					<a class="duplicate-field" title="<?php esc_attr_e( 'Duplicate field', 'secure-custom-fields' ); ?>" href="#"><?php esc_html_e( 'Duplicate', 'secure-custom-fields' ); ?></a>
					<?php if ( $num_field_groups > 1 ) : ?>
					<a class="move-field" title="<?php esc_attr_e( 'Move field to another group', 'secure-custom-fields' ); ?>" href="#"><?php esc_html_e( 'Move', 'secure-custom-fields' ); ?></a>
					<?php endif; ?>
					<a class="delete-field" title="<?php esc_attr_e( 'Delete field', 'secure-custom-fields' ); ?>" href="#"><?php esc_html_e( 'Delete', 'secure-custom-fields' ); ?></a>
				</div>
			</li>
			<li class="li-field-name"><span class="copyable"><?php echo esc_html( $field['name'] ); ?></span></li>
			<li class="li-field-key"><span class="copyable"><?php echo esc_html( $field['key'] ); ?></span></li>
			<li class="li-field-type">
				<i class="field-type-icon field-type-icon-<?php echo esc_attr( acf_slugify( $field['type'] ) ); ?>"></i>
				<span class="field-type-label">
					<?php echo acf_esc_html( $field_type_label ); ?>
				</span>
			</li>
		</ul>
	</div>

	<div class="settings">
		<div class="acf-field-editor">
			<div class="acf-field-settings acf-fields">

				<?php
				foreach ( acf_get_combined_field_type_settings_tabs() as $tab_key => $tab_label ) {
					$field_to_render = array(
						'type'          => 'tab',
						'label'         => $tab_label,
						'key'           => 'acf_field_settings_tabs',
						'settings-type' => $tab_key,
					);

					if ( $tab_key === 'conditional_logic' ) {
						$field_to_render['label'] = __( 'Conditional Logic', 'secure-custom-fields' ) . '<i class="conditional-logic-badge' . $conditional_logic_class . '">' . $conditional_logic_text . '</i>';
					}

					acf_render_field_wrap( $field_to_render );
					?>
					<?php
					$wrapper_class = str_replace( '_', '-', $tab_key );
					?>
					<div class="acf-field-settings-main acf-field-settings-main-<?php echo esc_attr( $wrapper_class ); ?>">
						<?php
						switch ( $tab_key ) {
							case 'general':
								$field_type_select_class = 'field-type';
								if ( ! apply_filters( 'acf/field_group/enable_field_type_select2', true ) ) {
									$field_type_select_class .= ' disable-select2';
								}
								// type
								acf_render_field_setting(
									$field,
									array(
										'label'        => __( 'Field Type', 'secure-custom-fields' ),
										'instructions' => '',
										'type'         => 'select',
										'name'         => 'type',
										'choices'      => acf_get_grouped_field_types(),
										'class'        => $field_type_select_class,
									),
									true
								);

								if ( apply_filters( 'acf/field_group/enable_field_browser', true ) ) {
									?>
									<div class="acf-field acf-field-setting-browse-fields" data-append="type">
										<div class="acf-input">
											<button class="acf-btn browse-fields">
												<i class="acf-icon acf-icon-dots-grid"></i>
												<?php esc_html_e( 'Browse Fields', 'secure-custom-fields' ); ?>
											</button>
										</div>
									</div>
									<?php
								}

								// label
								acf_render_field_setting(
									$field,
									array(
										'label'        => __( 'Field Label', 'secure-custom-fields' ),
										'instructions' => __( 'This is the name which will appear on the EDIT page', 'secure-custom-fields' ),
										'name'         => 'label',
										'type'         => 'text',
										'class'        => 'field-label',
									),
									true
								);

								// name
								acf_render_field_setting(
									$field,
									array(
										'label'        => __( 'Field Name', 'secure-custom-fields' ),
										'instructions' => __( 'Single word, no spaces. Underscores and dashes allowed', 'secure-custom-fields' ),
										'name'         => 'name',
										'type'         => 'text',
										'class'        => 'field-name',
									),
									true
								);

								// 3rd party settings
								do_action( 'acf/render_field_settings', $field );
								do_action( "acf/field_group/render_field_settings_tab/{$tab_key}", $field );
								?>
								<div class="acf-field-type-settings" data-parent-tab="<?php echo esc_attr( $tab_key ); ?>">
									<?php
									do_action( "acf/render_field_settings/type={$field['type']}", $field );
									do_action( "acf/field_group/render_field_settings_tab/{$tab_key}/type={$field['type']}", $field );
									do_action( "acf/render_field_{$tab_key}_settings/type={$field['type']}", $field );
									?>
								</div>
								<?php
								break;
							case 'validation':
								do_action( "acf/field_group/render_field_settings_tab/{$tab_key}", $field );
								?>
								<div class="acf-field-type-settings" data-parent-tab="<?php echo esc_attr( $tab_key ); ?>">
									<?php
									do_action( "acf/field_group/render_field_settings_tab/{$tab_key}/type={$field['type']}", $field );
									do_action( "acf/render_field_{$tab_key}_settings/type={$field['type']}", $field );
									?>
								</div>
								<?php
								break;
							case 'presentation':
								acf_render_field_setting(
									$field,
									array(
										'label'        => __( 'Instructions', 'secure-custom-fields' ),
										'instructions' => __( 'Instructions for content editors. Shown when submitting data.', 'secure-custom-fields' ),
										'type'         => 'textarea',
										'name'         => 'instructions',
										'rows'         => 5,
									),
									true
								);

								acf_render_field_wrap(
									array(
										'label'        => '',
										'instructions' => '',
										'type'         => 'text',
										'name'         => 'class',
										'prefix'       => $field['prefix'] . '[wrapper]',
										'value'        => $field['wrapper']['class'],
										'prepend'      => __( 'class', 'secure-custom-fields' ),
										'wrapper'      => array(
											'data-append' => 'wrapper',
										),
									),
									'div'
								);

								acf_render_field_wrap(
									array(
										'label'        => '',
										'instructions' => '',
										'type'         => 'text',
										'name'         => 'id',
										'prefix'       => $field['prefix'] . '[wrapper]',
										'value'        => $field['wrapper']['id'],
										'prepend'      => __( 'id', 'secure-custom-fields' ),
										'wrapper'      => array(
											'data-append' => 'wrapper',
										),
									),
									'div'
								);

								do_action( "acf/field_group/render_field_settings_tab/{$tab_key}", $field );
								?>
								<div class="acf-field-type-settings" data-parent-tab="<?php echo esc_attr( $tab_key ); ?>">
									<?php
									do_action( "acf/field_group/render_field_settings_tab/{$tab_key}/type={$field['type']}", $field );
									do_action( "acf/render_field_{$tab_key}_settings/type={$field['type']}", $field );
									?>
								</div>
								<?php

								acf_render_field_wrap(
									array(
										'label'        => __( 'Wrapper Attributes', 'secure-custom-fields' ),
										'instructions' => '',
										'type'         => 'number',
										'name'         => 'width',
										'prefix'       => $field['prefix'] . '[wrapper]',
										'value'        => $field['wrapper']['width'],
										'prepend'      => __( 'width', 'secure-custom-fields' ),
										'append'       => '%',
										'wrapper'      => array(
											'data-name' => 'wrapper',
											'class'     => 'acf-field-setting-wrapper',
										),
									),
									'div'
								);
								break;
							case 'conditional_logic':
								acf_get_view( 'acf-field-group/conditional-logic', array( 'field' => $field ) );

								do_action( "acf/field_group/render_field_settings_tab/{$tab_key}", $field );
								?>
								<div class="acf-field-type-settings" data-parent-tab="<?php echo esc_attr( $tab_key ); ?>">
									<?php
									do_action( "acf/field_group/render_field_settings_tab/{$tab_key}/type={$field['type']}", $field );
									do_action( "acf/render_field_{$tab_key}_settings/type={$field['type']}", $field );
									?>
								</div>
								<?php
								break;
							default:
								// Global action hook for custom tabs.
								do_action( "acf/field_group/render_field_settings_tab/{$tab_key}", $field );
								?>
								<div class="acf-field-type-settings" data-parent-tab="<?php echo esc_attr( $tab_key ); ?>">
									<?php
									// Type-specific action hook for custom tabs.
									do_action( "acf/field_group/render_field_settings_tab/{$tab_key}/type={$field['type']}", $field );
									do_action( "acf/render_field_{$tab_key}_settings/type={$field['type']}", $field );
									?>
								</div>
								<?php
						}
						?>
					</div>
					<?php
				}

				?>
				<div class="acf-field-settings-footer">
					<a class="button close-field edit-field" title="<?php esc_attr_e( 'Close Field', 'secure-custom-fields' ); ?>" href="#"><?php esc_html_e( 'Close Field', 'secure-custom-fields' ); ?></a>
				</div>
			</div>
		</div>
	</div>

</div>
