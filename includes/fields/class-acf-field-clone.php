<?php
/**
 * Clone Field Class
 *
 * This class handles the clone field type, which allows users to select and display existing fields.
 *
 * @package wordpress/secure-custom-fields
 * @since 5.0.0
 */

// phpcs:disable PEAR.NamingConventions.ValidClassName
if ( ! class_exists( 'acf_field_clone' ) ) :
	/**
	 * Class acf_field_clone
	 *
	 * Handles the functionality for the clone field type.
	 *
	 * @since 5.0.0
	 */
	class acf_field_clone extends acf_field {
		/**
		 * Initialize the field type.
		 *
		 * @type    function
		 * @date    5/03/2014
		 * @since   ACF 5.0
		 *
		 * @return  void
		 */
		public function initialize() {

			// vars
			$this->name          = 'clone';
			$this->label         = _x( 'Clone', 'noun', 'secure-custom-fields' );
			$this->category      = 'layout';
			$this->description   = __( 'Allows you to select and display existing fields. It does not duplicate any fields in the database, but loads and displays the selected fields at run-time. The Clone field can either replace itself with the selected fields or display the selected fields as a group of subfields.', 'secure-custom-fields' );
			$this->preview_image = acf_get_url() . '/assets/images/field-type-previews/field-preview-clone.png';
			$this->doc_url       = 'https://developer.wordpress.org/secure-custom-fields/features/fields/clone/';
			$this->tutorial_url  = 'https://developer.wordpress.org/secure-custom-fields/features/fields/clone/clone-tutorial/';
			$this->pro           = true;
			$this->supports      = array( 'bindings' => false );
			$this->defaults      = array(
				'clone'        => '',
				'prefix_label' => 0,
				'prefix_name'  => 0,
				'display'      => 'seamless',
				'layout'       => 'block',
			);
			$this->cloning       = array();
			$this->have_rows     = 'single';

			// register filter
			acf_enable_filter( 'clone' );

			// ajax
			add_action( 'wp_ajax_acf/fields/clone/query', array( $this, 'ajax_query' ) );

			// filters
			add_filter( 'acf/get_fields', array( $this, 'acf_get_fields' ), 5, 2 );
			add_filter( 'acf/prepare_field', array( $this, 'acf_prepare_field' ), 10, 1 );
			add_filter( 'acf/clone_field', array( $this, 'acf_clone_field' ), 10, 2 );
		}


		/**
		 * Returns true if ACF local functionality is enabled.
		 *
		 * @type    function
		 * @date    14/07/2016
		 * @since   ACF 5.4.0
		 *
		 * @return  bool True if clone filter is enabled.
		 */
		public function is_enabled() {
			return acf_is_filter_enabled( 'clone' );
		}


		/**
		 * Filter applied to the field after it is loaded from the database.
		 *
		 * @type    filter
		 * @since   ACF 3.6
		 * @date    23/01/13
		 *
		 * @param   array $field The field array holding all the field options.
		 * @return  array The modified field array.
		 */
		public function load_field( $field ) {

			// bail early if not enabled
			if ( ! $this->is_enabled() ) {
				return $field;
			}

			// load sub fields
			// - sub field name's will be modified to include prefix_name settings
			$field['sub_fields'] = $this->get_cloned_fields( $field );

			// return
			return $field;
		}


		/**
		 * Hooks into 'acf/get_fields' filter to inject/replace seamless clone fields.
		 *
		 * @param   array $fields Field list.
		 * @param   array $parent_field Parent field.
		 * @return  array Modified field list.
		 */
		public function acf_get_fields( $fields, $parent_field ) {
			// bail early if empty.
			if ( empty( $fields ) ) {
				return $fields;
			}

			// bail early if not enabled.
			if ( ! $this->is_enabled() ) {
				return $fields;
			}

			// vars.
			$i = 0;

			// loop.
			$count = count( $fields );
			while ( $i < $count ) {

				// vars.
				$field = $fields[ $i ];

				// Increment $i.
				++$i;

				// Bail early if not a clone field.
				if ( 'clone' !== $field['type'] ) {
					continue;
				}

				// Bail early if not seamless.
				if ( 'seamless' !== $field['display'] ) {
					continue;
				}

				// bail early if sub_fields isn't set or not an array
				if ( ! isset( $field['sub_fields'] ) || ! is_array( $field['sub_fields'] ) ) {
					continue;
				}

				// replace this clone field with sub fields
				--$i;
				array_splice( $fields, $i, 1, $field['sub_fields'] );
			}

			// return
			return $fields;
		}


		/**
		 * Returns an array of fields for a given clone field.
		 *
		 * @param   array $field The clone field array.
		 * @return  array Array of cloned fields.
		 */
		public function get_cloned_fields( $field ) {
			// vars.
			$fields = array();

			// bail early if no clone setting.
			if ( empty( $field['clone'] ) ) {
				return $fields;
			}

			// bail early if already cloning this field (avoid infinite looping).
			if ( isset( $this->cloning[ $field['key'] ] ) ) {
				return $fields;
			}

			// update local ref.
			$this->cloning[ $field['key'] ] = 1;

			// Loop over selectors and load fields.
			foreach ( $field['clone'] as $selector ) {

				// Field Group selector.
				if ( acf_is_field_group_key( $selector ) ) {
					$field_group = acf_get_field_group( $selector );
					if ( ! $field_group ) {
						continue;
					}

					$field_group_fields = acf_get_fields( $field_group );
					if ( ! $field_group_fields ) {
						continue;
					}

					$fields = array_merge( $fields, $field_group_fields );

					// Field selector.
				} elseif ( acf_is_field_key( $selector ) ) {
					$fields[] = acf_get_field( $selector );
				}
			}

			// field has ve been loaded for this $parent, time to remove cloning ref.
			unset( $this->cloning[ $field['key'] ] );

			// clear false values (fields that don't exist).
			$fields = array_filter( $fields );

			// bail early if no sub fields.
			if ( empty( $fields ) ) {
				return array();
			}

			// loop.
			// run acf_clone_field() on each cloned field to modify name, key, etc.
			foreach ( array_keys( $fields ) as $i ) {
				$fields[ $i ] = acf_clone_field( $fields[ $i ], $field );
			}

			return $fields;
		}

		/**
		 * This function is run when cloning a clone field
		 * Important to run the acf_clone_field function on sub fields to pass on settings such as 'parent_layout'
		 *
		 * @type    function
		 * @date    28/06/2016
		 * @since   5.3.8
		 *
		 * @param   array $field The field array.
		 * @param   array $clone_field The clone field array.
		 * @return  array $field
		 */
		public function acf_clone_field( $field, $clone_field ) {

			// bail early if this field is being cloned by some other kind of field (future proof)
			if ( 'clone' !== $clone_field['type'] ) {
				return $field;
			}

			// backup (used later)
			// - backup only once (cloned clone fields can cause issues)
			if ( ! isset( $field['__key'] ) ) {
				$field['__key']   = $field['key'];
				$field['__name']  = $field['_name'];
				$field['__label'] = $field['label'];
			}

			// seamless
			if ( 'seamless' === $clone_field['display'] ) {

				// modify key
				// - this will allow sub clone fields to correctly load values for the same cloned field
				// - the original key will later be restored by acf/prepare_field allowing conditional logic JS to work
				$field['key'] = $clone_field['key'] . '_' . $field['key'];

				// modify prefix allowing clone field to save sub fields
				// - only used for parent seamless fields. Block or sub field's prefix will be overriden which also works
				$field['prefix'] = $clone_field['prefix'] . '[' . $clone_field['key'] . ']';

				// modify parent
				$field['parent'] = $clone_field['parent'];

				// label_format
				if ( $clone_field['prefix_label'] ) {
					$field['label'] = $clone_field['label'] . ' ' . $field['label'];
				}
			}

			// prefix_name
			if ( $clone_field['prefix_name'] ) {

				// modify the field name
				// - this will allow field to load / save correctly
				$field['name'] = $clone_field['name'] . '_' . $field['_name'];

				// modify the field _name (orig name)
				// - this will allow fields to correctly understand the modified field
				if ( 'seamless' === $clone_field['display'] ) {
					$field['_name'] = $clone_field['_name'] . '_' . $field['_name'];
				}
			}

			// required
			if ( $clone_field['required'] ) {
				$field['required'] = 1;
			}

			// type specific
			// note: seamless clone fields will not be triggered
			if ( 'clone' === $field['type'] ) {
				$field = $this->acf_clone_clone_field( $field, $clone_field );
			}

			// return
			return $field;
		}
		/**
		 * This function is run when cloning a clone field
		 * Important to run the acf_clone_field function on sub fields to pass on settings such as 'parent_layout'
		 * Do not delete! Removing this logic causes major issues with cloned clone fields within a flexible content layout.
		 *
		 * @param array $field The field being cloned.
		 * @param array $clone_field The clone field.
		 * @return array The modified field.
		 */
		public function acf_clone_clone_field( $field, $clone_field ) {

			// Modify the $clone_field name.
			// This seems odd, however, the $clone_field is later passed into the acf_clone_field() function.
			// Do not delete!
			// When cloning a clone field, it is important to also change the _name too.
			// This allows sub clone fields to appear correctly in get_row() row array.
			if ( $field['prefix_name'] ) {
				$clone_field['name']  = $field['_name'];
				$clone_field['_name'] = $field['_name'];
			}

			// bail early if no sub fields
			if ( empty( $field['sub_fields'] ) ) {
				return $field;
			}

			// loop
			foreach ( $field['sub_fields'] as &$sub_field ) {

				// clone
				$sub_field = acf_clone_field( $sub_field, $clone_field );
			}

			// return
			return $field;
		}


		/**
		 * Prepares the field for database storage.
		 *
		 * @param array $field The field array.
		 * @return array The prepared field array.
		 */
		public function prepare_field_for_db( $field ) {

			// bail early if no sub fields
			if ( empty( $field['sub_fields'] ) ) {
				return $field;
			}

			// Bail early if name == _name.
			// This is a parent clone field and does not require any modification to sub field names.
			if ( $field['name'] === $field['_name'] ) {
				return $field;
			}

			// This is a sub field.
			// _name = 'my_field' phpcs:ignore
			// name = 'rep_0_my_field' phpcs:ignore
			// Modify all sub fields to add 'rep_0_' name prefix (prefix_name setting has already been applied).
			$length = strlen( $field['_name'] );
			$prefix = substr( $field['name'], 0, -$length );

			// bail early if _name is not found at the end of name (unknown potential error)
			if ( $prefix . $field['_name'] !== $field['name'] ) {
				return $field;
			}

			// Loop through each sub field and modify its name.
			foreach ( $field['sub_fields'] as &$sub_field ) {
				$sub_field['name'] = $prefix . $sub_field['name'];
			}

			return $field;
		}


		/**
		 * This filter is applied to the $value after it is loaded from the db.
		 *
		 * @param   mixed $value The value found in the database.
		 * @param   mixed $post_id The post_id from which the value was loaded.
		 * @param   array $field The field array holding all the field options.
		 * @return  mixed
		 */
		public function load_value( $value, $post_id, $field ) {

			// bail early if no sub fields
			if ( empty( $field['sub_fields'] ) ) {
				return $value;
			}

			// modify names
			$field = $this->prepare_field_for_db( $field );

			// load sub fields
			$value = array();

			// loop
			foreach ( $field['sub_fields'] as $sub_field ) {

				// add value
				$value[ $sub_field['key'] ] = acf_get_value( $post_id, $sub_field );
			}

			// return
			return $value;
		}


		/**
		 * This filter is appied to the $value after it is loaded from the db and before it is returned to the template
		 *
		 * @type  filter
		 * @since ACF 3.6 3.6
		 *
		 * @param mixed   $value       The value which was loaded from the database.
		 * @param mixed   $post_id     The $post_id from which the value was loaded.
		 * @param array   $field       The field array holding all the field options.
		 * @param boolean $escape_html Should the field return a HTML safe formatted value.
		 * @return mixed $value The modified value.
		 */
		public function format_value( $value, $post_id, $field, $escape_html = false ) {

			// bail early if no value
			if ( empty( $value ) ) {
				return false;
			}

			// modify names
			$field = $this->prepare_field_for_db( $field );

			// loop
			foreach ( $field['sub_fields'] as $sub_field ) {

				// extract value
				$sub_value = acf_extract_var( $value, $sub_field['key'] );

				// format value
				$sub_value = acf_format_value( $sub_value, $post_id, $sub_field, $escape_html );

				// append to $row
				$value[ $sub_field['__name'] ] = $sub_value;
			}

			// return
			return $value;
		}

		/**
		 * Formats the value for REST API output.
		 *
		 * @param mixed          $value   The field value.
		 * @param string|integer $post_id The post ID.
		 * @param array          $field   The field array.
		 * @return mixed The formatted value.
		 */
		public function format_value_for_rest( $value, $post_id, array $field ) {
			if ( empty( $value ) || ! is_array( $value ) ) {
				return $value;
			}

			if ( ! is_array( $field ) || ! isset( $field['sub_fields'] ) || ! is_array( $field['sub_fields'] ) ) {
				return $value;
			}

			// Loop through each row and within that, each sub field to process sub fields individually.
			foreach ( $field['sub_fields'] as $sub_field ) {

				// Extract the sub field 'field_key'=>'value' pair from the $value and format it.
				$sub_value = acf_extract_var( $value, $sub_field['key'] );
				$sub_value = acf_format_value_for_rest( $sub_value, $post_id, $sub_field );

				// Add the sub field value back to the $value but mapped to the field name instead
				// of the key reference.
				$value[ $sub_field['name'] ] = $sub_value;
			}

			return $value;
		}

		/**
		 * Updates the field value in the database.
		 *
		 * @param mixed $value   The value to save.
		 * @param int   $post_id The post ID where the value is saved.
		 * @param array $field   The field array.
		 * @return string|null Empty string on success, null on failure.
		 */
		public function update_value( $value, $post_id, $field ) {

			// bail early if no value
			if ( ! acf_is_array( $value ) ) {
				return null;
			}

			// bail early if no sub fields
			if ( empty( $field['sub_fields'] ) ) {
				return null;
			}

			// modify names
			$field = $this->prepare_field_for_db( $field );

			// loop
			foreach ( $field['sub_fields'] as $sub_field ) {

				// vars
				$v = false;

				// key (backend)
				if ( isset( $value[ $sub_field['key'] ] ) ) {
					$v = $value[ $sub_field['key'] ];

					// name (frontend)
				} elseif ( isset( $value[ $sub_field['_name'] ] ) ) {
					$v = $value[ $sub_field['_name'] ];

					// empty
				} else {

					// input is not set (hidden by conditioanl logic)
					continue;
				}

				// restore original field key
				$sub_field = $this->acf_prepare_field( $sub_field );

				// update value
				acf_update_value( $v, $post_id, $sub_field );
			}

			// return
			return '';
		}


		/**
		 * Renders the field input HTML.
		 *
		 * @param array $field The field array.
		 * @return void
		 */
		public function render_field( $field ) {

			// bail early if no sub fields
			if ( empty( $field['sub_fields'] ) ) {
				return;
			}

			// load values
			foreach ( $field['sub_fields'] as &$sub_field ) {

				// add value
				if ( isset( $field['value'][ $sub_field['key'] ] ) ) {

					// this is a normal value
					$sub_field['value'] = $field['value'][ $sub_field['key'] ];
				} elseif ( isset( $sub_field['default_value'] ) ) {

					// no value, but this sub field has a default value
					$sub_field['value'] = $sub_field['default_value'];
				}

				// update prefix to allow for nested values
				$sub_field['prefix'] = $field['name'];

				// restore label
				$sub_field['label'] = $sub_field['__label'];

				// restore required
				if ( $field['required'] ) {
					$sub_field['required'] = 0;
				}
			}

			// Render the field based on the layout setting.
			if ( 'table' === $field['layout'] ) {
				$this->render_field_table( $field );
			} else {
				$this->render_field_block( $field );
			}
		}


		/**
		 * Renders the clone fields in a block layout.
		 *
		 * @param array $field The field array.
		 * @return void
		 */
		public function render_field_block( $field ) {

			// vars
			$label_placement = 'block' === $field['layout'] ? 'top' : 'left';

			// html
			echo '<div class="acf-clone-fields acf-fields -' . esc_attr( $label_placement ) . ' -border">';

			foreach ( $field['sub_fields'] as $sub_field ) {
				acf_render_field_wrap( $sub_field );
			}

			echo '</div>';
		}


		/**
		 * Renders the clone fields in a table layout.
		 *
		 * @param array $field The field array.
		 * @return void
		 */
		public function render_field_table( $field ) {
			?>
			<table class="acf-table">
				<thead>
					<tr>
						<?php
						foreach ( $field['sub_fields'] as $sub_field ) :

							// Prepare field (allow sub fields to be removed).
							$sub_field = acf_prepare_field( $sub_field );
							if ( ! $sub_field ) {
								continue;
							}

							// Define attrs.
							$attrs              = array();
							$attrs['class']     = 'acf-th';
							$attrs['data-name'] = $sub_field['_name'];
							$attrs['data-type'] = $sub_field['type'];
							$attrs['data-key']  = $sub_field['key'];

							if ( $sub_field['wrapper']['width'] ) {
								$attrs['data-width'] = $sub_field['wrapper']['width'];
								$attrs['style']      = 'width: ' . $sub_field['wrapper']['width'] . '%;';
							}

							?>
							<th <?php echo acf_esc_attrs( $attrs ); ?>>
								<?php acf_render_field_label( $sub_field ); ?>
								<?php acf_render_field_instructions( $sub_field ); ?>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<tr class="acf-row">
						<?php

						foreach ( $field['sub_fields'] as $sub_field ) {
							acf_render_field_wrap( $sub_field, 'td' );
						}

						?>
					</tr>
				</tbody>
			</table>
			<?php
		}


		/**
		 * Renders the field settings HTML.
		 *
		 * @param array $field The field settings array.
		 * @return void
		 */
		public function render_field_settings( $field ) {

			// temp enable 'local' to allow .json fields to be displayed
			acf_enable_filter( 'local' );

			// default_value
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Fields', 'secure-custom-fields' ),
					'instructions' => __( 'Select one or more fields you wish to clone', 'secure-custom-fields' ),
					'type'         => 'select',
					'name'         => 'clone',
					'multiple'     => 1,
					'allow_null'   => 1,
					'choices'      => $this->get_clone_setting_choices( $field['clone'] ),
					'ui'           => 1,
					'ajax'         => 1,
					'ajax_action'  => 'acf/fields/clone/query',
					'placeholder'  => '',
					'nonce'        => wp_create_nonce( 'acf/fields/clone/query' ),
				)
			);

			acf_disable_filter( 'local' );

			// display
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Display', 'secure-custom-fields' ),
					'instructions' => __( 'Specify the style used to render the clone field', 'secure-custom-fields' ),
					'type'         => 'select',
					'name'         => 'display',
					'class'        => 'setting-display',
					'choices'      => array(
						'group'    => __( 'Group (displays selected fields in a group within this field)', 'secure-custom-fields' ),
						'seamless' => __( 'Seamless (replaces this field with selected fields)', 'secure-custom-fields' ),
					),
				)
			);

			// layout
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Layout', 'secure-custom-fields' ),
					'instructions' => __( 'Specify the style used to render the selected fields', 'secure-custom-fields' ),
					'type'         => 'radio',
					'name'         => 'layout',
					'layout'       => 'horizontal',
					'choices'      => array(
						'block' => __( 'Block', 'secure-custom-fields' ),
						'table' => __( 'Table', 'secure-custom-fields' ),
						'row'   => __( 'Row', 'secure-custom-fields' ),
					),
				)
			);

			// prefix_label
			/* translators: %s: field label */
			$instructions = __( 'Labels will be displayed as %s', 'secure-custom-fields' );
			$instructions = sprintf( $instructions, '<code class="prefix-label-code-1"></code>' );
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Prefix Field Labels', 'secure-custom-fields' ),
					'instructions' => $instructions,
					'name'         => 'prefix_label',
					'class'        => 'setting-prefix-label',
					'type'         => 'true_false',
					'ui'           => 1,
				)
			);

			// prefix_name
			/* translators: %s: field name */
			$instructions = __( 'Values will be saved as %s', 'secure-custom-fields' );
			$instructions = sprintf( $instructions, '<code class="prefix-name-code-1"></code>' );
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Prefix Field Names', 'secure-custom-fields' ),
					'instructions' => $instructions,
					'name'         => 'prefix_name',
					'class'        => 'setting-prefix-name',
					'type'         => 'true_false',
					'ui'           => 1,
				)
			);
		}


		/**
		 * Returns an array of field choices for Select2.
		 *
		 * @param mixed $value The field value.
		 * @return array Array of choices for Select2.
		 */
		public function get_clone_setting_choices( $value ) {

			// vars
			$choices = array();

			// bail early if no $value
			if ( empty( $value ) ) {
				return $choices;
			}

			// force value to array
			$value = acf_get_array( $value );

			// loop
			foreach ( $value as $v ) {
				$choices[ $v ] = $this->get_clone_setting_choice( $v );
			}

			// return
			return $choices;
		}


		/**
		 * Returns the label for a given clone choice.
		 *
		 * @param mixed $selector The field selector.
		 * @return string The choice label.
		 */
		public function get_clone_setting_choice( $selector = '' ) {

			// bail early no selector
			if ( ! $selector ) {
				return '';
			}

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified elsewhere.
			// ajax_fields
			if ( isset( $_POST['fields'][ $selector ] ) ) {
				return $this->get_clone_setting_field_choice( acf_sanitize_request_args( wp_unslash( $_POST['fields'][ $selector ] ) ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			// field
			if ( acf_is_field_key( $selector ) ) {
				return $this->get_clone_setting_field_choice( acf_get_field( $selector ) );
			}

			// group
			if ( acf_is_field_group_key( $selector ) ) {
				return $this->get_clone_setting_group_choice( acf_get_field_group( $selector ) );
			}

			// return
			return $selector;
		}


		/**
		 * Returns the text label for a field choice.
		 *
		 * @param array|false $field The field array.
		 * @return string The formatted choice text.
		 */
		public function get_clone_setting_field_choice( $field ) {

			// bail early if no field
			if ( ! $field ) {
				return __( 'Unknown field', 'secure-custom-fields' );
			}

			// title
			$title = $field['label'] ? $field['label'] : __( '(no title)', 'secure-custom-fields' );

			// append type
			$title .= ' (' . $field['type'] . ')';

			// ancestors
			// - allow for AJAX to send through ancestors count
			$ancestors = isset( $field['ancestors'] ) ? $field['ancestors'] : count( acf_get_field_ancestors( $field ) );
			$title     = str_repeat( '- ', $ancestors ) . $title;

			// return
			return $title;
		}


		/**
		 * Returns the text label for a field group choice.
		 *
		 * @param array|false $field_group The field group array.
		 * @return string The formatted choice text.
		 */
		public function get_clone_setting_group_choice( $field_group ) {

			// bail early if no field group
			if ( ! $field_group ) {
				return __( 'Unknown field group', 'secure-custom-fields' );
			}

			// return
			/* translators: %s: field group title */
			return sprintf( __( 'All fields from %s field group', 'secure-custom-fields' ), $field_group['title'] );
		}


		/**
		 * AJAX handler for getting potential fields to clone.
		 *
		 * @since ACF 5.3.8.3.8
		 *
		 * @return void
		 */
		public function ajax_query() {
			$nonce = acf_request_arg( 'nonce', '' );

			if ( ! acf_verify_ajax( $nonce, 'acf/fields/clone/query' ) ) {
				die();
			}

			// disable field to allow clone fields to appear selectable
			acf_disable_filter( 'clone' );

			// options
			$options = acf_parse_args(
				$_POST,
				array(
					'post_id' => 0,
					'paged'   => 0,
					's'       => '',
					'title'   => '',
					'fields'  => array(),
				)
			);

			// vars
			$results     = array();
			$s           = false;
			$i           = -1;
			$limit       = 20;
			$range_start = $limit * ( $options['paged'] - 1 );  // 0,  20, 40
			$range_end   = $range_start + ( $limit - 1 );         // 19, 39, 59

			// search
			if ( '' !== $options['s'] ) {

				// strip slashes (search may be integer)
				$s = wp_unslash( strval( $options['s'] ) );
			}

			// load groups
			$field_groups = acf_get_field_groups();
			$field_group  = false;

			// bail early if no field groups
			if ( empty( $field_groups ) ) {
				die();
			}

			// move current field group to start
			foreach ( array_keys( $field_groups ) as $j ) {

				// check ID
				if ( $field_groups[ $j ]['ID'] !== $options['post_id'] ) {
					continue;
				}

				// extract field group and move to start
				$field_group = acf_extract_var( $field_groups, $j );

				// field group found, stop looking
				break;
			}

			// if field group was not found, this is a new field group (not yet saved)
			if ( ! $field_group ) {
				$field_group = array(
					'ID'    => $options['post_id'],
					'title' => $options['title'],
					'key'   => '',
				);
			}

			// move current field group to start of list
			array_unshift( $field_groups, $field_group );

			// loop
			foreach ( $field_groups as $field_group ) {

				// vars
				$fields   = false;
				$ignore_s = false;
				$data     = array(
					'text'     => $field_group['title'],
					'children' => array(),
				);

				// get fields
				if ( (int) $field_group['ID'] === (int) $options['post_id'] ) {
					$fields = $options['fields'];
				} else {
					$fields = acf_get_fields( $field_group );
					$fields = acf_prepare_fields_for_import( $fields );
				}

				// bail early if no fields
				if ( ! $fields ) {
					continue;
				}

				// show all children for field group search match
				if ( false !== $s && stripos( $data['text'], $s ) !== false ) {
					$ignore_s = true;
				}

				// populate children
				$children   = array();
				$children[] = $field_group['key'];
				foreach ( $fields as $field ) {
					$children[] = $field['key'];
				}

				// loop
				foreach ( $children as $child ) {

					// bail early if no key (fake field group or corrupt field)
					if ( ! $child ) {
						continue;
					}

					// vars
					$text = false;

					// bail early if is search, and $text does not contain $s
					if ( false !== $s && ! $ignore_s ) {

						// get early
						$text = $this->get_clone_setting_choice( $child );

						// search
						if ( stripos( $text, $s ) === false ) {
							continue;
						}
					}

					// $i
					++$i;

					// bail early if $i is out of bounds
					if ( $i < $range_start || $i > $range_end ) {
						continue;
					}

					// load text
					if ( false === $text ) {
						$text = $this->get_clone_setting_choice( $child );
					}

					// append
					$data['children'][] = array(
						'id'   => $child,
						'text' => $text,
					);
				}

				// bail early if no children
				// - this group contained fields, but none shown on this page
				if ( empty( $data['children'] ) ) {
					continue;
				}

				// append
				$results[] = $data;

				// end loop if $i is out of bounds
				// - no need to look further
				if ( $i > $range_end ) {
					break;
				}
			}

			// return
			acf_send_ajax_results(
				array(
					'results' => $results,
					'limit'   => $limit,
				)
			);
		}


		/**
		 * Restores a field's key ready for input.
		 *
		 * @since   ACF 5.4.0
		 *
		 * @param   array $field The field array.
		 * @return  array The modified field array.
		 */
		public function acf_prepare_field( $field ) {

			// bail early if not cloned
			if ( empty( $field['_clone'] ) ) {
				return $field;
			}

			// restore key
			if ( isset( $field['__key'] ) ) {
				$field['key'] = $field['__key'];
			}

			// return
			return $field;
		}


		/**
		 * Validates the value of a clone field.
		 *
		 * @since   ACF 5.0.0
		 *
		 * @param   bool   $valid  Whether the value is valid.
		 * @param   mixed  $value  The field value.
		 * @param   array  $field  The field array.
		 * @param   string $input  The input element's name attribute.
		 * @return  bool   Whether the value is valid.
		 */
		public function validate_value( $valid, $value, $field, $input ) {

			// bail early if no $value
			if ( empty( $value ) ) {
				return $valid;
			}

			// bail early if no sub fields
			if ( empty( $field['sub_fields'] ) ) {
				return $valid;
			}

			// loop
			foreach ( array_keys( $field['sub_fields'] ) as $i ) {

				// get sub field
				$sub_field = $field['sub_fields'][ $i ];
				$k         = $sub_field['key'];

				// bail early if value not set (conditional logic?)
				if ( ! isset( $value[ $k ] ) ) {
					continue;
				}

				// validate
				acf_validate_value( $value[ $k ], $sub_field, "{$input}[{$k}]" );
			}

			// return
			return $valid;
		}

		/**
		 * Returns the schema array for the REST API.
		 *
		 * @param   array $field The field array.
		 * @return  array The schema array for the REST API.
		 */
		public function get_rest_schema( array $field ) {
			$schema = array(
				'type'     => array( 'object', 'null' ),
				'required' => ! empty( $field['required'] ) ? array() : false,
				'items'    => array(
					'type'       => 'object',
					'properties' => array(),
				),
			);

			foreach ( $field['sub_fields'] as $sub_field ) {
				/**
				 * Field type instance.
				 *
				 * @var acf_field $type
				 */
				$type = acf_get_field_type( $sub_field['type'] );

				if ( ! $type ) {
					continue;
				}

				$sub_field_schema = $type->get_rest_schema( $sub_field );

				// Passing null to nested fields has no effect. Remove this as a possible type to prevent
				// confusion in the schema.
				$null_type_index = array_search( 'null', $sub_field_schema['type'], true );
				if ( false !== $null_type_index ) {
					unset( $sub_field_schema['type'][ $null_type_index ] );
				}

				$schema['items']['properties'][ $sub_field['name'] ] = $sub_field_schema;

				/**
				 * If the clone field itself is marked as required, all subfields are required,
				 * regardless of the status of the original fields.
				 */
				if ( is_array( $schema['required'] ) ) {
					$schema['required'][] = $sub_field['name'];
				}
			}

			return $schema;
		}
	}


	// initialize
	acf_register_field_type( 'acf_field_clone' );
endif; // class_exists check

?>