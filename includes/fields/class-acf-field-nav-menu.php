<?php
/**
 * This is a PHP file containing the code for the acf_field_nav_menu class.
 *
 * @package wordpress/secure-custom-fields
 * @since 6.5.0
 */

if ( ! class_exists( 'Acf_Field_Nav_Menu' ) ) :
	/**
	 * Acf Nav menu field class
	 */
	class Acf_Field_Nav_Menu extends acf_field {

		/**
		 * This function will setup the field type data
		 *
		 * @type    function
		 * @date    5/03/2014
		 * @since  6.5.0
		 */
		public function initialize() {

			// vars
			$this->name          = 'nav_menu';
			$this->label         = _x( 'Nav Menu', 'noun', 'secure-custom-fields' );
			$this->category      = 'choice';
			$this->description   = __( 'A dropdown list with a selection of menu choices that you can chose.', 'secure-custom-fields' );
			$this->preview_image = acf_get_url() . '/assets/images/field-type-previews/field-preview-select.png';
			$this->doc_url       = 'https://developer.wordpress.org/secure-custom-fields/features/fields/nav_menu/';
			$this->tutorial_url  = 'https://developer.wordpress.org/secure-custom-fields/features/fields/select/nav_menu-tutorial/';
			$this->defaults      = array(
				'save_format' => 'id',
				'allow_null'  => 0,
				'container'   => 'div',
			);
		}

		/**
		 * Renders the Nav Menu Field options seen when editing a Nav Menu Field.
		 *
		 * @param array $field The array representation of the current Nav Menu Field.
		 */
		public function render_field_settings( $field ) {
			// Register the Return Value format setting
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Return Value', 'secure-custom-fields' ),
					'instructions' => __( 'Specify the returned value on front end', 'secure-custom-fields' ),
					'type'         => 'radio',
					'name'         => 'save_format',
					'layout'       => 'horizontal',
					'choices'      => array(
						'object' => __( 'Nav Menu Object', 'secure-custom-fields' ),
						'menu'   => __( 'Nav Menu HTML', 'secure-custom-fields' ),
						'id'     => __( 'Nav Menu ID', 'secure-custom-fields' ),
					),
				)
			);

			// Register the Menu Container setting
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Menu Container', 'secure-custom-fields' ),
					'instructions' => __( "What to wrap the Menu's ul with (when returning HTML only)", 'secure-custom-fields' ),
					'type'         => 'select',
					'name'         => 'container',
					'choices'      => $this->get_allowed_nav_container_tags(),
				)
			);

			// Register the Allow Null setting
			acf_render_field_setting(
				$field,
				array(
					'label'   => __( 'Allow Null?', 'secure-custom-fields' ),
					'type'    => 'radio',
					'name'    => 'allow_null',
					'layout'  => 'horizontal',
					'choices' => array(
						1 => __( 'Yes', 'secure-custom-fields' ),
						0 => __( 'No', 'secure-custom-fields' ),
					),
				)
			);
		}
		/**
		 * Get the allowed wrapper tags for use with wp_nav_menu().
		 *
		 * @return array An array of allowed wrapper tags.
		 */
		private function get_allowed_nav_container_tags() {
			$tags           = apply_filters( 'wp_nav_menu_container_allowed_tags', array( 'div', 'nav' ) );
			$formatted_tags = array(
				'0' => 'None',
			);

			foreach ( $tags as $tag ) {
				$formatted_tags[ $tag ] = $tag;
			}

			return $formatted_tags;
		}
		/**
		 * Renders the Nav Menu Field.
		 *
		 * @param array $field The array representation of the current Nav Menu Field.
		 */
		public function render_field( $field ) {
			$allow_null = $field['allow_null'];
			$nav_menus  = wp_get_nav_menus( $allow_null );
			if ( current_theme_supports( 'menus' ) ) {
				if ( empty( $nav_menus ) ) {
					?>
					<div class="acf-notice" >
						<p>
							<?php esc_html_e( '⚠️ Warning: You don\'t have any menus created please visit', 'secure-custom-fields' ); ?> <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'this', 'secure-custom-fields' ); ?></a> <?php esc_html_e( 'link to create menus.', 'secure-custom-fields' ); ?>
						</p>
					</div>

					<?php
				} else {
					?>
						<select id="<?php esc_attr( $field['id'] ); ?>" class="<?php echo esc_attr( $field['class'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>">
							<?php
							if ( $allow_null ) {
								?>
								<option value="">
									<?php esc_html_e( '- Select -', 'secure-custom-fields' ); ?> 
								</option>
								<?php
							}
							foreach ( $nav_menus as $nav_menu_name ) {
								?>
								<option value="<?php echo esc_attr( $nav_menu_name->term_id ); ?>" <?php selected( $field['value'], $nav_menu_name->term_id ); ?>>
									<?php echo esc_html( $nav_menu_name->name ); ?>
								</option>
							<?php } ?>
						</select>
					<?php
				}
			} else {
				?>
				<div class="acf-notice">
					<p>
						<?php esc_html_e( '⚠️ Warning: The theme does not support navigation menus.', 'secure-custom-fields' ); ?>
					</p>
				</div>
				<?php
			}
		}

		/**
		 * Renders the Nav Menu Field.
		 *
		 * @param int   $value   The Nav Menu ID selected for this Nav Menu Field.
		 * @param int   $post_id The Post ID this $value is associated with.
		 * @param array $field   The array representation of the current Nav Menu Field.
		 *
		 * @return mixed The Nav Menu ID, or the Nav Menu HTML, or the Nav Menu Object, or false.
		 */
		public function format_value( $value, $post_id, $field ) {
			// bail early if no value
			if ( empty( $value ) ) {
				return false;
			}

			// check format
			if ( 'object' === $field['save_format'] ) {
				$wp_menu_object = wp_get_nav_menu_object( $value );

				if ( empty( $wp_menu_object ) ) {
					return false;
				}

				$menu_object = new stdClass();

				$menu_object->ID    = $wp_menu_object->term_id;
				$menu_object->name  = $wp_menu_object->name;
				$menu_object->slug  = $wp_menu_object->slug;
				$menu_object->count = $wp_menu_object->count;

				return $menu_object;
			} elseif ( 'menu' === $field['save_format'] ) {
				ob_start();

				wp_nav_menu(
					array(
						'menu'      => $value,
						'container' => $field['container'],
					)
				);

				return ob_get_clean();
			}

			// Just return the Nav Menu ID
			return $value;
		}
	}


	// initialize
	acf_register_field_type( 'Acf_Field_Nav_Menu' );
endif; // class_exists check
