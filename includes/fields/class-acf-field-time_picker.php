<?php

if ( ! class_exists( 'acf_field_time_picker' ) ) :

	class acf_field_time_picker extends acf_field {


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
			$this->name          = 'time_picker';
			$this->label         = __( 'Time Picker', 'secure-custom-fields' );
			$this->category      = 'advanced';
			$this->description   = __( 'An interactive UI for picking a time. The time format can be customized using the field settings.', 'secure-custom-fields' );
			$this->preview_image = acf_get_url() . '/assets/images/field-type-previews/field-preview-time.png';
			$this->doc_url       = 'https://developer.wordpress.org/secure-custom-fields/features/fields/time-picker/';
			$this->tutorial_url  = 'https://developer.wordpress.org/secure-custom-fields/features/fields/time-picker/time-picker-tutorial/';
			$this->defaults      = array(
				'display_format' => 'g:i a',
				'return_format'  => 'g:i a',
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

			// Set value.
			$display_value = '';

			if ( $field['value'] ) {
				$display_value = acf_format_date( $field['value'], $field['display_format'] );
			}

			// The native time input uses the ISO format and cannot express a custom display format.
			if ( $this->scf_use_native_picker() ) {
				$this->render_native_field( $field );
				return;
			}

			// Elements.
			$div          = array(
				'class'            => 'acf-time-picker acf-input-wrap',
				'data-time_format' => acf_convert_time_to_js( $field['display_format'] ),
			);
			$hidden_input = array(
				'id'    => $field['id'],
				'class' => 'input-alt',
				'type'  => 'hidden',
				'name'  => $field['name'],
				'value' => $field['value'],
			);
			$text_input   = array(
				'class' => $field['class'] . ' input',
				'type'  => 'text',
				'value' => $display_value,
			);
			foreach ( array( 'readonly', 'disabled' ) as $k ) {
				if ( ! empty( $field[ $k ] ) ) {
					$hidden_input[ $k ] = $k;
					$text_input[ $k ]   = $k;
				}
			}

			// Output.
			?>
		<div <?php echo acf_esc_attrs( $div ); ?>>
			<?php acf_hidden_input( $hidden_input ); ?>
			<?php acf_text_input( $text_input ); ?>
		</div>
			<?php
		}


		/**
		 * Renders the field using the browser's native time input.
		 *
		 * The hidden input keeps the same `H:i:s` value the JavaScript picker
		 * would have saved, so stored data and return formats are unchanged.
		 *
		 * @since SCF 6.9.6
		 *
		 * @param array $field The field array.
		 * @return void
		 */
		protected function render_native_field( $field ) {
			$native_value = '';

			if ( ! empty( $field['value'] ) ) {
				$native_value = acf_format_date( $field['value'], 'H:i:s' );
			}

			$div          = array(
				'class'       => 'acf-time-picker acf-input-wrap acf-native-picker',
				'data-native' => '1',
			);
			$hidden_input = array(
				'id'    => $field['id'],
				'class' => 'input-alt',
				'name'  => $field['name'],
				'value' => $field['value'],
			);
			$native_input = array(
				'class' => $field['class'] . ' input',
				'type'  => 'time',
				// Without a step the input hides seconds and reports HH:MM, which is not the H:i:s format we save.
				'step'  => 1,
				'value' => $native_value,
			);

			foreach ( array( 'readonly', 'disabled' ) as $k ) {
				if ( ! empty( $field[ $k ] ) ) {
					$hidden_input[ $k ] = $k;
					$native_input[ $k ] = $k;
				}
			}

			?>
		<div <?php echo acf_esc_attrs( $div ); ?>>
			<?php acf_hidden_input( $hidden_input ); ?>
			<?php echo acf_get_text_input( $native_input ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the input helper. ?>
		</div>
			<?php
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
			$g_i_a = date_i18n( 'g:i a' );
			$H_i_s = date_i18n( 'H:i:s' );

			$native_picker = $this->scf_use_native_picker();

			echo '<div class="acf-field-settings-split">';

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Display Format', 'secure-custom-fields' ),
					'hint'         => __( 'The format displayed when editing a post', 'secure-custom-fields' ),
					'type'         => 'radio',
					'name'         => 'display_format',
					'other_choice' => 1,
					'choices'      => array(
						'g:i a' => '<span>' . $g_i_a . '</span><code>g:i a</code>',
						'H:i:s' => '<span>' . $H_i_s . '</span><code>H:i:s</code>',
						'other' => '<span>' . __( 'Custom:', 'secure-custom-fields' ) . '</span>',
					),
				)
			);

			if ( $native_picker ) {
				printf(
					'<p class="description">%s</p>',
					esc_html__( 'The native time input is in use, so this format is only used by older picker fields. The browser decides how the time is displayed.', 'secure-custom-fields' )
				);
			}

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Return Format', 'secure-custom-fields' ),
					'hint'         => __( 'The format returned via template functions', 'secure-custom-fields' ),
					'type'         => 'radio',
					'name'         => 'return_format',
					'other_choice' => 1,
					'choices'      => array(
						'g:i a' => '<span>' . $g_i_a . '</span><code>g:i a</code>',
						'H:i:s' => '<span>' . $H_i_s . '</span><code>H:i:s</code>',
						'other' => '<span>' . __( 'Custom:', 'secure-custom-fields' ) . '</span>',
					),
				)
			);

			echo '</div>';
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
		 * @return  $value (mixed) the modified value
		 */
		public function format_value( $value, $post_id, $field ) {
			return acf_format_date( $value, $field['return_format'] );
		}

		/**
		 * This filter is applied to the $field after it is loaded from the database
		 * and ensures the return and display values are set.
		 *
		 * @type  filter
		 * @since ACF 5.11.0
		 *
		 * @param  array $field The field array holding all the field options.
		 * @return array
		 */
		public function load_field( $field ) {
			if ( empty( $field['display_format'] ) ) {
				$field['display_format'] = $this->defaults['display_format'];
			}

			if ( empty( $field['return_format'] ) ) {
				$field['return_format'] = $this->defaults['return_format'];
			}

			return $field;
		}

		/**
		 * Return the schema array for the REST API.
		 *
		 * @param  array $field The field array.
		 * @return array
		 */
		public function get_rest_schema( array $field ) {
			return array(
				'type'        => array( 'string', 'null' ),
				'description' => 'A `H:i:s` formatted time string.',
				'required'    => ! empty( $field['required'] ),
			);
		}

		/**
		 * Returns an array of JSON-LD Property output types that are supported by this field type.
		 *
		 * @since 6.8
		 *
		 * @return string[]
		 */
		public function get_jsonld_output_types(): array {
			return array( 'Time' );
		}

		/**
		 * Formats the field value for JSON-LD output.
		 *
		 * Returns the stored H:i:s format which is already ISO 8601 compliant.
		 *
		 * @since 6.8.0
		 *
		 * @param mixed          $value   The value of the field.
		 * @param integer|string $post_id The ID of the post.
		 * @param array          $field   The field array.
		 * @return string|null ISO 8601 formatted time or null.
		 */
		public function format_value_for_jsonld( $value, $post_id, $field ) {
			if ( empty( $value ) || ! is_string( $value ) ) {
				return null;
			}

			// ACF stores time_picker internally as 'H:i:s' which is ISO 8601 compliant.
			return $value;
		}
	}


	// initialize
	acf_register_field_type( 'acf_field_time_picker' );
endif; // class_exists check

?>
