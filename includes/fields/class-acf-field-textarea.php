<?php

if ( ! class_exists( 'acf_field_textarea' ) ) :

	class acf_field_textarea extends acf_field {



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
			$this->name          = 'textarea';
			$this->label         = __( 'Text Area', 'secure-custom-fields' );
			$this->description   = __( 'A basic textarea input for storing paragraphs of text.', 'secure-custom-fields' );
			$this->preview_image = acf_get_url() . '/assets/images/field-type-previews/field-preview-textarea.png';
			$this->doc_url       = 'https://developer.wordpress.org/secure-custom-fields/features/fields/textarea/';
			$this->tutorial_url  = 'https://developer.wordpress.org/secure-custom-fields/features/fields/textarea/textarea-tutorial/';
			$this->defaults      = array(
				'default_value' => '',
				'new_lines'     => '',
				'maxlength'     => '',
				'placeholder'   => '',
				'rows'          => '',
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
			$keys  = array( 'id', 'class', 'name', 'value', 'placeholder', 'rows', 'maxlength' );
			$keys2 = array( 'readonly', 'disabled', 'required' );

			// rows
			if ( ! $field['rows'] ) {
				$field['rows'] = 8;
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

			// return
			acf_textarea_input( $atts );
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
					'label'        => __( 'Default Value', 'secure-custom-fields' ),
					'instructions' => __( 'Appears when creating a new post', 'secure-custom-fields' ),
					'type'         => 'textarea',
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
					'label'        => __( 'Character Limit', 'secure-custom-fields' ),
					'instructions' => __( 'Leave blank for no limit', 'secure-custom-fields' ),
					'type'         => 'number',
					'name'         => 'maxlength',
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
					'label'        => __( 'Rows', 'secure-custom-fields' ),
					'instructions' => __( 'Sets the textarea height', 'secure-custom-fields' ),
					'type'         => 'number',
					'name'         => 'rows',
					'placeholder'  => 8,
				)
			);

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
					'label'        => __( 'New Lines', 'secure-custom-fields' ),
					'instructions' => __( 'Controls how new lines are rendered', 'secure-custom-fields' ),
					'type'         => 'select',
					'name'         => 'new_lines',
					'choices'      => array(
						'wpautop' => __( 'Automatically add paragraphs', 'secure-custom-fields' ),
						'br'      => __( 'Automatically add &lt;br&gt;', 'secure-custom-fields' ),
						''        => __( 'No Formatting', 'secure-custom-fields' ),
					),
				)
			);
		}

		/**
		 * This filter is applied to the $value after it is loaded from the db and before it is returned to the template
		 *
		 * @type    filter
		 * @since   ACF 3.6
		 * @date    23/01/13
		 *
		 * @param   $value (mixed) the value which was loaded from the database
		 * @param   $post_id (mixed) the post_id from which the value was loaded
		 * @param   $field (array) the field array holding all the field options
		 *
		 * @return  $value (mixed) the modified value
		 */
		function format_value( $value, $post_id, $field ) {

			// bail early if no value or not for template
			if ( empty( $value ) || ! is_string( $value ) ) {
				return $value;
			}

			// new lines
			if ( $field['new_lines'] == 'wpautop' ) {
				$value = wpautop( $value );
			} elseif ( $field['new_lines'] == 'br' ) {
				$value = nl2br( $value );
			}

			// return
			return $value;
		}

		/**
		 * validate_value
		 *
		 * Validates a field's value.
		 *
		 * @date    29/1/19
		 * @since   ACF 5.7.11
		 *
		 * @param   (bool|string) Whether the value is vaid or not.
		 * @param   mixed                                          $value The field value.
		 * @param   array                                          $field The field array.
		 * @param   string                                         $input The HTML input name.
		 * @return  (bool|string)
		 */
		function validate_value( $valid, $value, $field, $input ) {

			// Check maxlength.
			if ( $field['maxlength'] && ( acf_strlen( $value ) > $field['maxlength'] ) ) {
				/* translators: %d: the maximum number of characters */
				return sprintf( __( 'Value must not exceed %d characters', 'secure-custom-fields' ), $field['maxlength'] );
			}

			// Return.
			return $valid;
		}

		/**
		 * Return the schema array for the REST API.
		 *
		 * @param array $field
		 * @return array
		 */
		function get_rest_schema( array $field ) {
			$schema = parent::get_rest_schema( $field );

			if ( ! empty( $field['maxlength'] ) ) {
				$schema['maxLength'] = (int) $field['maxlength'];
			}

			return $schema;
		}
	}


	// initialize
	acf_register_field_type( 'acf_field_textarea' );
endif; // class_exists check
