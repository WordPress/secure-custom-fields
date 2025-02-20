<?php

if ( ! class_exists( 'acf_field_number' ) ) :

	class acf_field_number extends acf_field {



		/**
		 * This function will setup the field type data
		 *
		 * @type    function
		 * @date    5/03/2014
		 * @since   ACF 5.0.0
		 *
		 * @param   n/a
		 * @return  n/a
		 */
		function initialize() {

			// vars
			$this->name          = 'number';
			$this->label         = __( 'Number', 'secure-custom-fields' );
			$this->description   = __( 'An input limited to numerical values.', 'secure-custom-fields' );
			$this->preview_image = acf_get_url() . '/assets/images/field-type-previews/field-preview-number.png';
			$this->doc_url       = 'https://developer.wordpress.org/secure-custom-fields/features/fields/number/';
			$this->tutorial_url  = 'https://developer.wordpress.org/secure-custom-fields/features/fields/number/number-tutorial/';
			$this->defaults      = array(
				'default_value' => '',
				'min'           => '',
				'max'           => '',
				'step'          => '',
				'placeholder'   => '',
				'prepend'       => '',
				'append'        => '',
			);
		}


		/**
		 * Create the HTML interface for your field
		 *
		 * @param   $field - an array holding all the field's data
		 *
		 * @type    action
		 * @since   ACF 3.6
		 * @date    23/01/13
		 */
		function render_field( $field ) {

			// vars
			$atts  = array();
			$keys  = array( 'type', 'id', 'class', 'name', 'value', 'min', 'max', 'step', 'placeholder', 'pattern' );
			$keys2 = array( 'readonly', 'disabled', 'required' );
			$html  = '';

			// step
			if ( ! $field['step'] ) {
				$field['step'] = 'any';
			}

			// prepend
			if ( $field['prepend'] !== '' ) {
				$field['class'] .= ' acf-is-prepended';
				$html           .= '<div class="acf-input-prepend">' . acf_esc_html( $field['prepend'] ) . '</div>';
			}

			// append
			if ( $field['append'] !== '' ) {
				$field['class'] .= ' acf-is-appended';
				$html           .= '<div class="acf-input-append">' . acf_esc_html( $field['append'] ) . '</div>';
			}

			// atts (value="123")
			foreach ( $keys as $k ) {
				if ( isset( $field[ $k ] ) ) {
					$atts[ $k ] = $field[ $k ];
				}
			}

			// atts2 (disabled="disabled")
			foreach ( $keys2 as $k ) {
				if ( ! empty( $field[ $k ] ) ) {
					$atts[ $k ] = $k;
				}
			}

			// remove empty atts
			$atts = acf_clean_atts( $atts );

			// render
			$html .= '<div class="acf-input-wrap">' . acf_get_text_input( $atts ) . '</div>';

			// return
			echo $html; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by individual html functions above.
		}


		/**
		 * Create extra options for your field. This is rendered when editing a field.
		 * The value of $field['name'] can be used (like bellow) to save extra data to the $field
		 *
		 * @type    action
		 * @since   ACF 3.6
		 * @date    23/01/13
		 *
		 * @param   $field  - an array holding all the field's data
		 */
		function render_field_settings( $field ) {

			// default_value
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Default Value', 'secure-custom-fields' ),
					'instructions' => __( 'Appears when creating a new post', 'secure-custom-fields' ),
					'type'         => 'text',
					'name'         => 'default_value',
				)
			);
		}

		/**
		 * Renders the field settings used in the "Validation" tab.
		 *
		 * @since ACF 6.0
		 *
		 * @param array $field The field settings array.
		 * @return void
		 */
		function render_field_validation_settings( $field ) {
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Minimum Value', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'number',
					'name'         => 'min',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Maximum Value', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'number',
					'name'         => 'max',
				)
			);
		}

		/**
		 * Renders the field settings used in the "Presentation" tab.
		 *
		 * @since ACF 6.0
		 *
		 * @param array $field The field settings array.
		 * @return void
		 */
		function render_field_presentation_settings( $field ) {
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Placeholder Text', 'secure-custom-fields' ),
					'instructions' => __( 'Appears within the input', 'secure-custom-fields' ),
					'type'         => 'text',
					'name'         => 'placeholder',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Step Size', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'number',
					'name'         => 'step',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Prepend', 'secure-custom-fields' ),
					'instructions' => __( 'Appears before the input', 'secure-custom-fields' ),
					'type'         => 'text',
					'name'         => 'prepend',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Append', 'secure-custom-fields' ),
					'instructions' => __( 'Appears after the input', 'secure-custom-fields' ),
					'type'         => 'text',
					'name'         => 'append',
				)
			);
		}

		/**
		 * description
		 *
		 * @type    function
		 * @date    11/02/2014
		 * @since   ACF 5.0.0
		 *
		 * @param   $post_id (int)
		 * @return  $post_id (int)
		 */
		function validate_value( $valid, $value, $field, $input ) {

			// remove ','
			if ( acf_str_exists( ',', $value ) ) {
				$value = str_replace( ',', '', $value );
			}

			// if value is not numeric...
			if ( ! is_numeric( $value ) ) {

				// allow blank to be saved
				if ( ! empty( $value ) ) {
					$valid = __( 'Value must be a number', 'secure-custom-fields' );
				}

				// return early
				return $valid;
			}

			// convert
			$value = floatval( $value );

			// min
			if ( is_numeric( $field['min'] ) && $value < floatval( $field['min'] ) ) {
				/* translators: %d: the minimum value */
				$valid = sprintf( __( 'Value must be equal to or higher than %d', 'secure-custom-fields' ), $field['min'] );
			}

			// max
			if ( is_numeric( $field['max'] ) && $value > floatval( $field['max'] ) ) {
				/* translators: %d: the maximum value */
				$valid = sprintf( __( 'Value must be equal to or lower than %d', 'secure-custom-fields' ), $field['max'] );
			}

			// return
			return $valid;
		}


		/**
		 * This filter is appied to the $value before it is updated in the db
		 *
		 * @type    filter
		 * @since   ACF 3.6
		 * @date    23/01/13
		 *
		 * @param   $value - the value which will be saved in the database
		 * @param   $field - the field array holding all the field options
		 * @param   $post_id - the post_id of which the value will be saved
		 *
		 * @return  $value - the modified value
		 */
		function update_value( $value, $post_id, $field ) {

			// no formatting needed for empty value
			if ( empty( $value ) ) {
				return $value;
			}

			// remove ','
			if ( acf_str_exists( ',', $value ) ) {
				$value = str_replace( ',', '', $value );
			}

			// return
			return $value;
		}

		/**
		 * Return the schema array for the REST API.
		 *
		 * @param array $field
		 * @return array
		 */
		public function get_rest_schema( array $field ) {
			$schema = array(
				'type'     => array( 'number', 'null' ),
				'required' => ! empty( $field['required'] ),
			);

			if ( ! empty( $field['min'] ) ) {
				$schema['minimum'] = (float) $field['min'];
			}

			if ( ! empty( $field['max'] ) ) {
				$schema['maximum'] = (float) $field['max'];
			}

			if ( isset( $field['default_value'] ) && is_numeric( $field['default_value'] ) ) {
				$schema['default'] = (float) $field['default_value'];
			}

			return $schema;
		}

		/**
		 * Apply basic formatting to prepare the value for default REST output.
		 *
		 * @param mixed          $value
		 * @param string|integer $post_id
		 * @param array          $field
		 * @return mixed
		 */
		public function format_value_for_rest( $value, $post_id, array $field ) {
			return acf_format_numerics( $value );
		}
	}


	// initialize
	acf_register_field_type( 'acf_field_number' );
endif; // class_exists check
