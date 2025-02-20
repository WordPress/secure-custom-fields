<?php

if ( ! class_exists( 'acf_field__accordion' ) ) :

	class acf_field__accordion extends acf_field {

		public $show_in_rest = false;

		/**
		 * initialize
		 *
		 * This function will setup the field type data
		 *
		 * @date  30/10/17
		 * @since ACF 5.6.3
		 *
		 * @param  n/a
		 * @return n/a
		 */
		function initialize() {

			// vars
			$this->name          = 'accordion';
			$this->label         = __( 'Accordion', 'secure-custom-fields' );
			$this->category      = 'layout';
			$this->description   = __( 'Allows you to group and organize custom fields into collapsable panels that are shown while editing content. Useful for keeping large datasets tidy.', 'secure-custom-fields' );
			$this->preview_image = acf_get_url() . '/assets/images/field-type-previews/field-preview-accordion.png';
			$this->doc_url       = 'https://developer.wordpress.org/secure-custom-fields/features/fields/accordion/';
			$this->tutorial_url  = 'https://developer.wordpress.org/secure-custom-fields/features/fields/accordion/accordion-tutorial/';
			$this->supports      = array(
				'required' => false,
				'bindings' => false,
			);
			$this->defaults      = array(
				'open'         => 0,
				'multi_expand' => 0,
				'endpoint'     => 0,
			);
		}


		/**
		 * render_field
		 *
		 * Create the HTML interface for your field
		 *
		 * @date  30/10/17
		 * @since ACF 5.6.3
		 *
		 * @param  array $field
		 * @return n/a
		 */
		function render_field( $field ) {

			// vars
			$atts = array(
				'class'             => 'acf-fields',
				'data-open'         => $field['open'],
				'data-multi_expand' => $field['multi_expand'],
				'data-endpoint'     => $field['endpoint'],
			);

			?>
		<div <?php echo acf_esc_attrs( $atts ); ?>></div>
			<?php
		}



		/**
		 * Create extra options for your field. This is rendered when editing a field.
		 * The value of $field['name'] can be used (like bellow) to save extra data to the $field
		 *
		 * @param   $field  - an array holding all the field's data
		 *
		 * @type    action
		 * @since   ACF 3.6
		 * @date    23/01/13
		 */
		function render_field_settings( $field ) {
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Open', 'secure-custom-fields' ),
					'instructions' => __( 'Display this accordion as open on page load.', 'secure-custom-fields' ),
					'name'         => 'open',
					'type'         => 'true_false',
					'ui'           => 1,
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Multi-Expand', 'secure-custom-fields' ),
					'instructions' => __( 'Allow this accordion to open without closing others.', 'secure-custom-fields' ),
					'name'         => 'multi_expand',
					'type'         => 'true_false',
					'ui'           => 1,
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Endpoint', 'secure-custom-fields' ),
					'instructions' => __( 'Define an endpoint for the previous accordion to stop. This accordion will not be visible.', 'secure-custom-fields' ),
					'name'         => 'endpoint',
					'type'         => 'true_false',
					'ui'           => 1,
				)
			);
		}


		/**
		 * This filter is appied to the $field after it is loaded from the database
		 *
		 * @type    filter
		 * @since   ACF 3.6
		 * @date    23/01/13
		 *
		 * @param   $field - the field array holding all the field options
		 *
		 * @return  $field - the field array holding all the field options
		 */
		function load_field( $field ) {

			// remove name to avoid caching issue
			$field['name'] = '';

			// remove required to avoid JS issues
			$field['required'] = 0;

			// set value other than 'null' to avoid ACF loading / caching issue
			$field['value'] = false;

			// return
			return $field;
		}
	}


	// initialize
	acf_register_field_type( 'acf_field__accordion' );
endif; // class_exists check

?>
