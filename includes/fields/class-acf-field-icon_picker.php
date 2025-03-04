<?php
/**
 * This is a PHP file containing the code for the acf_field_icon_picker class.
 *
 * @package Advanced_Custom_Fields_Pro
 */

if ( ! class_exists( 'acf_field_icon_picker' ) ) :

	/**
	 * Class acf_field_icon_picker.
	 */
	class acf_field_icon_picker extends acf_field {
		/**
		 * Initialize icon picker field
		 *
		 * @since ACF 6.3
		 *
		 * @return void
		 */
		public function initialize() {
			$this->name          = 'icon_picker';
			$this->label         = __( 'Icon Picker', 'secure-custom-fields' );
			$this->public        = true;
			$this->category      = 'advanced';
			$this->description   = __( 'An interactive UI for selecting an icon. Select from Dashicons, the media library, or a standalone URL input.', 'secure-custom-fields' );
			$this->preview_image = acf_get_url() . '/assets/images/field-type-previews/field-preview-icon-picker.png';
			$this->doc_url       = 'https://developer.wordpress.org/secure-custom-fields/features/fields/icon-picker/';
			$this->tutorial_url  = 'https://developer.wordpress.org/secure-custom-fields/features/fields/icon-picker/icon-picker-tutorial/';
			$this->defaults      = array(
				'library'       => 'all',
				'tabs'          => array_keys( $this->get_tabs() ),
				'return_format' => 'string',
				'default_value' => array(
					'type'  => null,
					'value' => null,
				),
			);
		}

		/**
		 * Gets the available tabs for the icon picker field.
		 *
		 * @since ACF 6.3
		 *
		 * @return array
		 */
		public function get_tabs() {
			$tabs = array(
				'dashicons' => esc_html__( 'Dashicons', 'secure-custom-fields' ),
			);

			if ( current_user_can( 'upload_files' ) ) {
				$tabs['media_library'] = esc_html__( 'Media Library', 'secure-custom-fields' );
			}

			$tabs['url'] = esc_html__( 'URL', 'secure-custom-fields' );

			/**
			 * Allows filtering the tabs used by the icon picker.
			 *
			 * @since ACF 6.3
			 *
			 * @param array $tabs An associative array of tabs in key => label format.
			 * @return array
			 */
			return apply_filters( 'acf/fields/icon_picker/tabs', $tabs );
		}

		/**
		 * Renders icon picker field
		 *
		 * @since ACF 6.3
		 *
		 * @param object $field The ACF Field
		 * @return void
		 */
		public function render_field( $field ) {
			$uploader = acf_get_setting( 'uploader' );

			// Enqueue uploader scripts
			if ( $uploader === 'wp' ) {
				acf_enqueue_uploader();
			}

			$div = array(
				'id'    => $field['id'],
				'class' => $field['class'] . ' acf-icon-picker',
			);

			echo '<div ' . acf_esc_attrs( $div ) . '>';

			acf_hidden_input(
				array(
					'name'             => $field['name'] . '[type]',
					'value'            => $field['value']['type'],
					'data-hidden-type' => 'type',
				)
			);
			acf_hidden_input(
				array(
					'name'             => $field['name'] . '[value]',
					'value'            => $field['value']['value'],
					'data-hidden-type' => 'value',
				)
			);

			if ( ! is_array( $field['tabs'] ) ) {
				$field['tabs'] = array();
			}

			$tabs  = $this->get_tabs();
			$shown = array_filter(
				$tabs,
				function ( $tab ) use ( $field ) {
					return in_array( $tab, $field['tabs'], true );
				},
				ARRAY_FILTER_USE_KEY
			);

			foreach ( $shown as $name => $label ) {
				if ( count( $shown ) > 1 ) {
					acf_render_field_wrap(
						array(
							'type'           => 'tab',
							'label'          => $label,
							'key'            => 'acf_icon_picker_tabs',
							'selected'       => $name === $field['value']['type'],
							'unique_tab_key' => $name,
						)
					);
				}

				$wrapper_class = str_replace( '_', '-', $name );
				echo '<div class="acf-icon-picker-tabs acf-icon-picker-' . esc_attr( $wrapper_class ) . '-tabs">';

				switch ( $name ) {
					case 'dashicons':
						echo '<div class="acf-dashicons-search-wrap">';
							acf_text_input(
								array(
									'class'       => 'acf-dashicons-search-input',
									'placeholder' => esc_html__( 'Search icons...', 'secure-custom-fields' ),
									'type'        => 'search',
								)
							);
						echo '</div>';
						echo '<div class="acf-dashicons-list"></div>';
						?>
						<div class="acf-dashicons-list-empty">
							<img src="<?php echo esc_url( acf_get_url( 'assets/images/face-sad.svg' ) ); ?>" />
							<p class="acf-no-results-text">
								<?php
								printf(
									/* translators: %s: The invalid search term */
									esc_html__( "No search results for '%s'", 'secure-custom-fields' ),
									'<span class="acf-invalid-dashicon-search-term"></span>'
								);
								?>
							</p>
						</div>

						<?php
						break;
					case 'media_library':
						?>
						<div class="acf-icon-picker-tab" data-category="<?php echo esc_attr( $name ); ?>">
							<div class="acf-icon-picker-media-library">
								<?php
								$button_style = 'display: none;';

								if ( in_array( $field['value']['type'], array( 'media_library', 'dashicons' ), true ) && ! empty( $field['value']['value'] ) ) {
									$button_style = '';
								}
								?>
								<button
									aria-label="<?php esc_attr_e( 'Click to change the icon in the Media Library', 'secure-custom-fields' ); ?>"
									class="acf-icon-picker-media-library-preview"
									style="<?php echo esc_attr( $button_style ); ?>"
								>
									<div class="acf-icon-picker-media-library-preview-img" style="<?php echo esc_attr( 'media_library' !== $field['value']['type'] ? 'display: none;' : '' ); ?>">
										<?php
											$img_url = wp_get_attachment_image_url( $field['value']['value'], 'thumbnail' );
											// If the type is media_library, then we need to show the media library preview.
										?>
											<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php esc_attr_e( 'The currently selected image preview', 'secure-custom-fields' ); ?>" />
									</div>
									<div class="acf-icon-picker-media-library-preview-dashicon" style="<?php echo esc_attr( 'dashicons' !== $field['value']['type'] ? 'display: none;' : '' ); ?>">
										<div class="dashicons <?php echo esc_attr( $field['value']['value'] ); ?>">
										</div>
									</div>
								</button>
								<button class="acf-icon-picker-media-library-button">
									<div class="acf-icon-picker-media-library-button-icon dashicons dashicons-admin-media"></div>
									<span><?php esc_html_e( 'Browse Media Library', 'secure-custom-fields' ); ?></span>
								</button>
							</div>
						</div>
						<?php
						break;
					case 'url':
						echo '<div class="acf-icon-picker-url">';
						acf_text_input(
							array(
								'class' => 'acf-icon_url',
								'value' => $field['value']['type'] === 'url' ? $field['value']['value'] : '',
							)
						);

						// Helper Text
						?>
						<p class="description"><?php esc_html_e( 'The URL to the icon you\'d like to use, or svg as Data URI', 'secure-custom-fields' ); ?></p>
						<?php
						echo '</div>';
						break;
					default:
						do_action( 'acf/fields/icon_picker/tab/' . $name, $field );
				}

				echo '</div>';
			}

			echo '</div>';
		}

		/**
		 * Renders field settings for the icon picker field.
		 *
		 * @since ACF 6.3
		 *
		 * @param array $field The icon picker field object.
		 * @return void
		 */
		public function render_field_settings( $field ) {
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Tabs', 'secure-custom-fields' ),
					'instructions' => __( 'Select where content editors can choose the icon from.', 'secure-custom-fields' ),
					'type'         => 'checkbox',
					'name'         => 'tabs',
					'choices'      => $this->get_tabs(),
				)
			);

			$return_format_doc = sprintf(
				'<a href="%s" target="_blank">%s</a>',
				$this->doc_url,
				__( 'Learn More', 'secure-custom-fields' )
			);

			$return_format_instructions = sprintf(
				/* translators: %s - link to documentation */
				__( 'Specify the return format for the icon. %s', 'secure-custom-fields' ),
				$return_format_doc
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Return Format', 'secure-custom-fields' ),
					'instructions' => $return_format_instructions,
					'type'         => 'radio',
					'name'         => 'return_format',
					'choices'      => array(
						'string' => __( 'String', 'secure-custom-fields' ),
						'array'  => __( 'Array', 'secure-custom-fields' ),
					),
					'layout'       => 'horizontal',
				)
			);
		}

		/**
		 * Localizes text for Icon Picker
		 *
		 * @since ACF 6.3
		 *
		 * @return void
		 */
		public function input_admin_enqueue_scripts() {
			acf_localize_data(
				array(
					'iconPickerA11yStrings' => array(
						'noResultsForSearchTerm'       => esc_html__( 'No results found for that search term', 'secure-custom-fields' ),
						'newResultsFoundForSearchTerm' => esc_html__( 'The available icons matching your search query have been updated in the icon picker below.', 'secure-custom-fields' ),
					),
					'iconPickeri10n'        => $this->get_dashicons(),
				)
			);
		}

		/**
		 * Validates the field value before it is saved into the database.
		 *
		 * @since ACF 6.3
		 *
		 * @param  integer $valid The current validation status.
		 * @param  mixed   $value The value of the field.
		 * @param  array   $field The field array holding all the field options.
		 * @param  string  $input The corresponding input name for $_POST value.
		 * @return boolean true If the value is valid, false if not.
		 */
		public function validate_value( $valid, $value, $field, $input ) {
			// If the value is empty, return true. You're allowed to save nothing.
			if ( empty( $value ) && empty( $field['required'] ) ) {
				return true;
			}

			// If the value is not an array, return $valid status.
			if ( ! is_array( $value ) ) {
				return $valid;
			}

			// If the value is an array, but the type is not set, fail validation.
			if ( ! isset( $value['type'] ) ) {
				return __( 'Icon picker requires an icon type.', 'secure-custom-fields' );
			}

			// If the value is an array, but the value is not set, fail validation.
			if ( ! isset( $value['value'] ) ) {
				return __( 'Icon picker requires a value.', 'secure-custom-fields' );
			}

			return true;
		}

		/**
		 * format_value()
		 *
		 * This filter is appied to the $value after it is loaded from the db and before it is returned to the template
		 *
		 * @since ACF 6.3
		 *
		 * @param  mixed   $value   The value which was loaded from the database.
		 * @param  integer $post_id The $post_id from which the value was loaded.
		 * @param  array   $field   The field array holding all the field options.
		 * @return mixed   $value   The modified value.
		 */
		public function format_value( $value, $post_id, $field ) {
			// Handle empty values.
			if ( empty( $value ) ) {
				// Return the default value if there is one.
				if ( isset( $field['default_value'] ) ) {
					return $field['default_value'];
				} else {
					// Otherwise return false.
					return false;
				}
			}

			// If media_library, behave the same as an image field.
			if ( $value['type'] === 'media_library' ) {
				// convert to int
				$value['value'] = intval( $value['value'] );

				// format
				if ( $field['return_format'] === 'string' ) {
					return wp_get_attachment_url( $value['value'] );
				} elseif ( $field['return_format'] === 'array' ) {
					$value['value'] = acf_get_attachment( $value['value'] );
					return $value;
				}
			}

			// If the desired return format is a string
			if ( $field['return_format'] === 'string' ) {
				return $value['value'];
			}

			// If nothing specific matched the return format, just return the value.
			return $value;
		}

		/**
		 * get_dashicons()
		 *
		 * This function will return an array of dashicons.
		 *
		 * @since ACF 6.3
		 *
		 * @return  array $dashicons an array of dashicons.
		 */
		public function get_dashicons() {
			$dashicons = array(
				'dashicons-admin-appearance'          => esc_html__( 'Appearance Icon', 'secure-custom-fields' ),
				'dashicons-admin-collapse'            => esc_html__( 'Collapse Icon', 'secure-custom-fields' ),
				'dashicons-admin-comments'            => esc_html__( 'Comments Icon', 'secure-custom-fields' ),
				'dashicons-admin-customizer'          => esc_html__( 'Customizer Icon', 'secure-custom-fields' ),
				'dashicons-admin-generic'             => esc_html__( 'Generic Icon', 'secure-custom-fields' ),
				'dashicons-admin-home'                => esc_html__( 'Home Icon', 'secure-custom-fields' ),
				'dashicons-admin-links'               => esc_html__( 'Links Icon', 'secure-custom-fields' ),
				'dashicons-admin-media'               => esc_html__( 'Media Icon', 'secure-custom-fields' ),
				'dashicons-admin-multisite'           => esc_html__( 'Multisite Icon', 'secure-custom-fields' ),
				'dashicons-admin-network'             => esc_html__( 'Network Icon', 'secure-custom-fields' ),
				'dashicons-admin-page'                => esc_html__( 'Page Icon', 'secure-custom-fields' ),
				'dashicons-admin-plugins'             => esc_html__( 'Plugins Icon', 'secure-custom-fields' ),
				'dashicons-admin-post'                => esc_html__( 'Post Icon', 'secure-custom-fields' ),
				'dashicons-admin-settings'            => esc_html__( 'Settings Icon', 'secure-custom-fields' ),
				'dashicons-admin-site'                => esc_html__( 'Site Icon', 'secure-custom-fields' ),
				'dashicons-admin-site-alt'            => esc_html__( 'Site (alt) Icon', 'secure-custom-fields' ),
				'dashicons-admin-site-alt2'           => esc_html__( 'Site (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-admin-site-alt3'           => esc_html__( 'Site (alt3) Icon', 'secure-custom-fields' ),
				'dashicons-admin-tools'               => esc_html__( 'Tools Icon', 'secure-custom-fields' ),
				'dashicons-admin-users'               => esc_html__( 'Users Icon', 'secure-custom-fields' ),
				'dashicons-airplane'                  => esc_html__( 'Airplane Icon', 'secure-custom-fields' ),
				'dashicons-album'                     => esc_html__( 'Album Icon', 'secure-custom-fields' ),
				'dashicons-align-center'              => esc_html__( 'Align Center Icon', 'secure-custom-fields' ),
				'dashicons-align-full-width'          => esc_html__( 'Align Full Width Icon', 'secure-custom-fields' ),
				'dashicons-align-left'                => esc_html__( 'Align Left Icon', 'secure-custom-fields' ),
				'dashicons-align-none'                => esc_html__( 'Align None Icon', 'secure-custom-fields' ),
				'dashicons-align-pull-left'           => esc_html__( 'Align Pull Left Icon', 'secure-custom-fields' ),
				'dashicons-align-pull-right'          => esc_html__( 'Align Pull Right Icon', 'secure-custom-fields' ),
				'dashicons-align-right'               => esc_html__( 'Align Right Icon', 'secure-custom-fields' ),
				'dashicons-align-wide'                => esc_html__( 'Align Wide Icon', 'secure-custom-fields' ),
				'dashicons-amazon'                    => esc_html__( 'Amazon Icon', 'secure-custom-fields' ),
				'dashicons-analytics'                 => esc_html__( 'Analytics Icon', 'secure-custom-fields' ),
				'dashicons-archive'                   => esc_html__( 'Archive Icon', 'secure-custom-fields' ),
				'dashicons-arrow-down'                => esc_html__( 'Arrow Down Icon', 'secure-custom-fields' ),
				'dashicons-arrow-down-alt'            => esc_html__( 'Arrow Down (alt) Icon', 'secure-custom-fields' ),
				'dashicons-arrow-down-alt2'           => esc_html__( 'Arrow Down (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-arrow-left'                => esc_html__( 'Arrow Left Icon', 'secure-custom-fields' ),
				'dashicons-arrow-left-alt'            => esc_html__( 'Arrow Left (alt) Icon', 'secure-custom-fields' ),
				'dashicons-arrow-left-alt2'           => esc_html__( 'Arrow Left (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-arrow-right'               => esc_html__( 'Arrow Right Icon', 'secure-custom-fields' ),
				'dashicons-arrow-right-alt'           => esc_html__( 'Arrow Right (alt) Icon', 'secure-custom-fields' ),
				'dashicons-arrow-right-alt2'          => esc_html__( 'Arrow Right (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-arrow-up'                  => esc_html__( 'Arrow Up Icon', 'secure-custom-fields' ),
				'dashicons-arrow-up-alt'              => esc_html__( 'Arrow Up (alt) Icon', 'secure-custom-fields' ),
				'dashicons-arrow-up-alt2'             => esc_html__( 'Arrow Up (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-art'                       => esc_html__( 'Art Icon', 'secure-custom-fields' ),
				'dashicons-awards'                    => esc_html__( 'Awards Icon', 'secure-custom-fields' ),
				'dashicons-backup'                    => esc_html__( 'Backup Icon', 'secure-custom-fields' ),
				'dashicons-bank'                      => esc_html__( 'Bank Icon', 'secure-custom-fields' ),
				'dashicons-beer'                      => esc_html__( 'Beer Icon', 'secure-custom-fields' ),
				'dashicons-bell'                      => esc_html__( 'Bell Icon', 'secure-custom-fields' ),
				'dashicons-block-default'             => esc_html__( 'Block Default Icon', 'secure-custom-fields' ),
				'dashicons-book'                      => esc_html__( 'Book Icon', 'secure-custom-fields' ),
				'dashicons-book-alt'                  => esc_html__( 'Book (alt) Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-activity'        => esc_html__( 'Activity Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-bbpress-logo'    => esc_html__( 'bbPress Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-buddypress-logo' => esc_html__( 'BuddyPress Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-community'       => esc_html__( 'Community Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-forums'          => esc_html__( 'Forums Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-friends'         => esc_html__( 'Friends Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-groups'          => esc_html__( 'Groups Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-pm'              => esc_html__( 'PM Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-replies'         => esc_html__( 'Replies Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-topics'          => esc_html__( 'Topics Icon', 'secure-custom-fields' ),
				'dashicons-buddicons-tracking'        => esc_html__( 'Tracking Icon', 'secure-custom-fields' ),
				'dashicons-building'                  => esc_html__( 'Building Icon', 'secure-custom-fields' ),
				'dashicons-businessman'               => esc_html__( 'Businessman Icon', 'secure-custom-fields' ),
				'dashicons-businessperson'            => esc_html__( 'Businessperson Icon', 'secure-custom-fields' ),
				'dashicons-businesswoman'             => esc_html__( 'Businesswoman Icon', 'secure-custom-fields' ),
				'dashicons-button'                    => esc_html__( 'Button Icon', 'secure-custom-fields' ),
				'dashicons-calculator'                => esc_html__( 'Calculator Icon', 'secure-custom-fields' ),
				'dashicons-calendar'                  => esc_html__( 'Calendar Icon', 'secure-custom-fields' ),
				'dashicons-calendar-alt'              => esc_html__( 'Calendar (alt) Icon', 'secure-custom-fields' ),
				'dashicons-camera'                    => esc_html__( 'Camera Icon', 'secure-custom-fields' ),
				'dashicons-camera-alt'                => esc_html__( 'Camera (alt) Icon', 'secure-custom-fields' ),
				'dashicons-car'                       => esc_html__( 'Car Icon', 'secure-custom-fields' ),
				'dashicons-carrot'                    => esc_html__( 'Carrot Icon', 'secure-custom-fields' ),
				'dashicons-cart'                      => esc_html__( 'Cart Icon', 'secure-custom-fields' ),
				'dashicons-category'                  => esc_html__( 'Category Icon', 'secure-custom-fields' ),
				'dashicons-chart-area'                => esc_html__( 'Chart Area Icon', 'secure-custom-fields' ),
				'dashicons-chart-bar'                 => esc_html__( 'Chart Bar Icon', 'secure-custom-fields' ),
				'dashicons-chart-line'                => esc_html__( 'Chart Line Icon', 'secure-custom-fields' ),
				'dashicons-chart-pie'                 => esc_html__( 'Chart Pie Icon', 'secure-custom-fields' ),
				'dashicons-clipboard'                 => esc_html__( 'Clipboard Icon', 'secure-custom-fields' ),
				'dashicons-clock'                     => esc_html__( 'Clock Icon', 'secure-custom-fields' ),
				'dashicons-cloud'                     => esc_html__( 'Cloud Icon', 'secure-custom-fields' ),
				'dashicons-cloud-saved'               => esc_html__( 'Cloud Saved Icon', 'secure-custom-fields' ),
				'dashicons-cloud-upload'              => esc_html__( 'Cloud Upload Icon', 'secure-custom-fields' ),
				'dashicons-code-standards'            => esc_html__( 'Code Standards Icon', 'secure-custom-fields' ),
				'dashicons-coffee'                    => esc_html__( 'Coffee Icon', 'secure-custom-fields' ),
				'dashicons-color-picker'              => esc_html__( 'Color Picker Icon', 'secure-custom-fields' ),
				'dashicons-columns'                   => esc_html__( 'Columns Icon', 'secure-custom-fields' ),
				'dashicons-controls-back'             => esc_html__( 'Back Icon', 'secure-custom-fields' ),
				'dashicons-controls-forward'          => esc_html__( 'Forward Icon', 'secure-custom-fields' ),
				'dashicons-controls-pause'            => esc_html__( 'Pause Icon', 'secure-custom-fields' ),
				'dashicons-controls-play'             => esc_html__( 'Play Icon', 'secure-custom-fields' ),
				'dashicons-controls-repeat'           => esc_html__( 'Repeat Icon', 'secure-custom-fields' ),
				'dashicons-controls-skipback'         => esc_html__( 'Skip Back Icon', 'secure-custom-fields' ),
				'dashicons-controls-skipforward'      => esc_html__( 'Skip Forward Icon', 'secure-custom-fields' ),
				'dashicons-controls-volumeoff'        => esc_html__( 'Volume Off Icon', 'secure-custom-fields' ),
				'dashicons-controls-volumeon'         => esc_html__( 'Volume On Icon', 'secure-custom-fields' ),
				'dashicons-cover-image'               => esc_html__( 'Cover Image Icon', 'secure-custom-fields' ),
				'dashicons-dashboard'                 => esc_html__( 'Dashboard Icon', 'secure-custom-fields' ),
				'dashicons-database'                  => esc_html__( 'Database Icon', 'secure-custom-fields' ),
				'dashicons-database-add'              => esc_html__( 'Database Add Icon', 'secure-custom-fields' ),
				'dashicons-database-export'           => esc_html__( 'Database Export Icon', 'secure-custom-fields' ),
				'dashicons-database-import'           => esc_html__( 'Database Import Icon', 'secure-custom-fields' ),
				'dashicons-database-remove'           => esc_html__( 'Database Remove Icon', 'secure-custom-fields' ),
				'dashicons-database-view'             => esc_html__( 'Database View Icon', 'secure-custom-fields' ),
				'dashicons-desktop'                   => esc_html__( 'Desktop Icon', 'secure-custom-fields' ),
				'dashicons-dismiss'                   => esc_html__( 'Dismiss Icon', 'secure-custom-fields' ),
				'dashicons-download'                  => esc_html__( 'Download Icon', 'secure-custom-fields' ),
				'dashicons-drumstick'                 => esc_html__( 'Drumstick Icon', 'secure-custom-fields' ),
				'dashicons-edit'                      => esc_html__( 'Edit Icon', 'secure-custom-fields' ),
				'dashicons-edit-large'                => esc_html__( 'Edit Large Icon', 'secure-custom-fields' ),
				'dashicons-edit-page'                 => esc_html__( 'Edit Page Icon', 'secure-custom-fields' ),
				'dashicons-editor-aligncenter'        => esc_html__( 'Align Center Icon', 'secure-custom-fields' ),
				'dashicons-editor-alignleft'          => esc_html__( 'Align Left Icon', 'secure-custom-fields' ),
				'dashicons-editor-alignright'         => esc_html__( 'Align Right Icon', 'secure-custom-fields' ),
				'dashicons-editor-bold'               => esc_html__( 'Bold Icon', 'secure-custom-fields' ),
				'dashicons-editor-break'              => esc_html__( 'Break Icon', 'secure-custom-fields' ),
				'dashicons-editor-code'               => esc_html__( 'Code Icon', 'secure-custom-fields' ),
				'dashicons-editor-contract'           => esc_html__( 'Contract Icon', 'secure-custom-fields' ),
				'dashicons-editor-customchar'         => esc_html__( 'Custom Character Icon', 'secure-custom-fields' ),
				'dashicons-editor-expand'             => esc_html__( 'Expand Icon', 'secure-custom-fields' ),
				'dashicons-editor-help'               => esc_html__( 'Help Icon', 'secure-custom-fields' ),
				'dashicons-editor-indent'             => esc_html__( 'Indent Icon', 'secure-custom-fields' ),
				'dashicons-editor-insertmore'         => esc_html__( 'Insert More Icon', 'secure-custom-fields' ),
				'dashicons-editor-italic'             => esc_html__( 'Italic Icon', 'secure-custom-fields' ),
				'dashicons-editor-justify'            => esc_html__( 'Justify Icon', 'secure-custom-fields' ),
				'dashicons-editor-kitchensink'        => esc_html__( 'Kitchen Sink Icon', 'secure-custom-fields' ),
				'dashicons-editor-ltr'                => esc_html__( 'LTR Icon', 'secure-custom-fields' ),
				'dashicons-editor-ol'                 => esc_html__( 'Ordered List Icon', 'secure-custom-fields' ),
				'dashicons-editor-ol-rtl'             => esc_html__( 'Ordered List RTL Icon', 'secure-custom-fields' ),
				'dashicons-editor-outdent'            => esc_html__( 'Outdent Icon', 'secure-custom-fields' ),
				'dashicons-editor-paragraph'          => esc_html__( 'Paragraph Icon', 'secure-custom-fields' ),
				'dashicons-editor-paste-text'         => esc_html__( 'Paste Text Icon', 'secure-custom-fields' ),
				'dashicons-editor-paste-word'         => esc_html__( 'Paste Word Icon', 'secure-custom-fields' ),
				'dashicons-editor-quote'              => esc_html__( 'Quote Icon', 'secure-custom-fields' ),
				'dashicons-editor-removeformatting'   => esc_html__( 'Remove Formatting Icon', 'secure-custom-fields' ),
				'dashicons-editor-rtl'                => esc_html__( 'RTL Icon', 'secure-custom-fields' ),
				'dashicons-editor-spellcheck'         => esc_html__( 'Spellcheck Icon', 'secure-custom-fields' ),
				'dashicons-editor-strikethrough'      => esc_html__( 'Strikethrough Icon', 'secure-custom-fields' ),
				'dashicons-editor-table'              => esc_html__( 'Table Icon', 'secure-custom-fields' ),
				'dashicons-editor-textcolor'          => esc_html__( 'Text Color Icon', 'secure-custom-fields' ),
				'dashicons-editor-ul'                 => esc_html__( 'Unordered List Icon', 'secure-custom-fields' ),
				'dashicons-editor-underline'          => esc_html__( 'Underline Icon', 'secure-custom-fields' ),
				'dashicons-editor-unlink'             => esc_html__( 'Unlink Icon', 'secure-custom-fields' ),
				'dashicons-editor-video'              => esc_html__( 'Video Icon', 'secure-custom-fields' ),
				'dashicons-ellipsis'                  => esc_html__( 'Ellipsis Icon', 'secure-custom-fields' ),
				'dashicons-email'                     => esc_html__( 'Email Icon', 'secure-custom-fields' ),
				'dashicons-email-alt'                 => esc_html__( 'Email (alt) Icon', 'secure-custom-fields' ),
				'dashicons-email-alt2'                => esc_html__( 'Email (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-embed-audio'               => esc_html__( 'Embed Audio Icon', 'secure-custom-fields' ),
				'dashicons-embed-generic'             => esc_html__( 'Embed Generic Icon', 'secure-custom-fields' ),
				'dashicons-embed-photo'               => esc_html__( 'Embed Photo Icon', 'secure-custom-fields' ),
				'dashicons-embed-post'                => esc_html__( 'Embed Post Icon', 'secure-custom-fields' ),
				'dashicons-embed-video'               => esc_html__( 'Embed Video Icon', 'secure-custom-fields' ),
				'dashicons-excerpt-view'              => esc_html__( 'Excerpt View Icon', 'secure-custom-fields' ),
				'dashicons-exit'                      => esc_html__( 'Exit Icon', 'secure-custom-fields' ),
				'dashicons-external'                  => esc_html__( 'External Icon', 'secure-custom-fields' ),
				'dashicons-facebook'                  => esc_html__( 'Facebook Icon', 'secure-custom-fields' ),
				'dashicons-facebook-alt'              => esc_html__( 'Facebook (alt) Icon', 'secure-custom-fields' ),
				'dashicons-feedback'                  => esc_html__( 'Feedback Icon', 'secure-custom-fields' ),
				'dashicons-filter'                    => esc_html__( 'Filter Icon', 'secure-custom-fields' ),
				'dashicons-flag'                      => esc_html__( 'Flag Icon', 'secure-custom-fields' ),
				'dashicons-food'                      => esc_html__( 'Food Icon', 'secure-custom-fields' ),
				'dashicons-format-aside'              => esc_html__( 'Aside Icon', 'secure-custom-fields' ),
				'dashicons-format-audio'              => esc_html__( 'Audio Icon', 'secure-custom-fields' ),
				'dashicons-format-chat'               => esc_html__( 'Chat Icon', 'secure-custom-fields' ),
				'dashicons-format-gallery'            => esc_html__( 'Gallery Icon', 'secure-custom-fields' ),
				'dashicons-format-image'              => esc_html__( 'Image Icon', 'secure-custom-fields' ),
				'dashicons-format-quote'              => esc_html__( 'Quote Icon', 'secure-custom-fields' ),
				'dashicons-format-status'             => esc_html__( 'Status Icon', 'secure-custom-fields' ),
				'dashicons-format-video'              => esc_html__( 'Video Icon', 'secure-custom-fields' ),
				'dashicons-forms'                     => esc_html__( 'Forms Icon', 'secure-custom-fields' ),
				'dashicons-fullscreen-alt'            => esc_html__( 'Fullscreen (alt) Icon', 'secure-custom-fields' ),
				'dashicons-fullscreen-exit-alt'       => esc_html__( 'Fullscreen Exit (alt) Icon', 'secure-custom-fields' ),
				'dashicons-games'                     => esc_html__( 'Games Icon', 'secure-custom-fields' ),
				'dashicons-google'                    => esc_html__( 'Google Icon', 'secure-custom-fields' ),
				'dashicons-grid-view'                 => esc_html__( 'Grid View Icon', 'secure-custom-fields' ),
				'dashicons-groups'                    => esc_html__( 'Groups Icon', 'secure-custom-fields' ),
				'dashicons-hammer'                    => esc_html__( 'Hammer Icon', 'secure-custom-fields' ),
				'dashicons-heading'                   => esc_html__( 'Heading Icon', 'secure-custom-fields' ),
				'dashicons-heart'                     => esc_html__( 'Heart Icon', 'secure-custom-fields' ),
				'dashicons-hidden'                    => esc_html__( 'Hidden Icon', 'secure-custom-fields' ),
				'dashicons-hourglass'                 => esc_html__( 'Hourglass Icon', 'secure-custom-fields' ),
				'dashicons-html'                      => esc_html__( 'HTML Icon', 'secure-custom-fields' ),
				'dashicons-id'                        => esc_html__( 'ID Icon', 'secure-custom-fields' ),
				'dashicons-id-alt'                    => esc_html__( 'ID (alt) Icon', 'secure-custom-fields' ),
				'dashicons-image-crop'                => esc_html__( 'Crop Icon', 'secure-custom-fields' ),
				'dashicons-image-filter'              => esc_html__( 'Filter Icon', 'secure-custom-fields' ),
				'dashicons-image-flip-horizontal'     => esc_html__( 'Flip Horizontal Icon', 'secure-custom-fields' ),
				'dashicons-image-flip-vertical'       => esc_html__( 'Flip Vertical Icon', 'secure-custom-fields' ),
				'dashicons-image-rotate'              => esc_html__( 'Rotate Icon', 'secure-custom-fields' ),
				'dashicons-image-rotate-left'         => esc_html__( 'Rotate Left Icon', 'secure-custom-fields' ),
				'dashicons-image-rotate-right'        => esc_html__( 'Rotate Right Icon', 'secure-custom-fields' ),
				'dashicons-images-alt'                => esc_html__( 'Images (alt) Icon', 'secure-custom-fields' ),
				'dashicons-images-alt2'               => esc_html__( 'Images (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-index-card'                => esc_html__( 'Index Card Icon', 'secure-custom-fields' ),
				'dashicons-info'                      => esc_html__( 'Info Icon', 'secure-custom-fields' ),
				'dashicons-info-outline'              => esc_html__( 'Info Outline Icon', 'secure-custom-fields' ),
				'dashicons-insert'                    => esc_html__( 'Insert Icon', 'secure-custom-fields' ),
				'dashicons-insert-after'              => esc_html__( 'Insert After Icon', 'secure-custom-fields' ),
				'dashicons-insert-before'             => esc_html__( 'Insert Before Icon', 'secure-custom-fields' ),
				'dashicons-instagram'                 => esc_html__( 'Instagram Icon', 'secure-custom-fields' ),
				'dashicons-laptop'                    => esc_html__( 'Laptop Icon', 'secure-custom-fields' ),
				'dashicons-layout'                    => esc_html__( 'Layout Icon', 'secure-custom-fields' ),
				'dashicons-leftright'                 => esc_html__( 'Left Right Icon', 'secure-custom-fields' ),
				'dashicons-lightbulb'                 => esc_html__( 'Lightbulb Icon', 'secure-custom-fields' ),
				'dashicons-linkedin'                  => esc_html__( 'LinkedIn Icon', 'secure-custom-fields' ),
				'dashicons-list-view'                 => esc_html__( 'List View Icon', 'secure-custom-fields' ),
				'dashicons-location'                  => esc_html__( 'Location Icon', 'secure-custom-fields' ),
				'dashicons-location-alt'              => esc_html__( 'Location (alt) Icon', 'secure-custom-fields' ),
				'dashicons-lock'                      => esc_html__( 'Lock Icon', 'secure-custom-fields' ),
				'dashicons-marker'                    => esc_html__( 'Marker Icon', 'secure-custom-fields' ),
				'dashicons-media-archive'             => esc_html__( 'Archive Icon', 'secure-custom-fields' ),
				'dashicons-media-audio'               => esc_html__( 'Audio Icon', 'secure-custom-fields' ),
				'dashicons-media-code'                => esc_html__( 'Code Icon', 'secure-custom-fields' ),
				'dashicons-media-default'             => esc_html__( 'Default Icon', 'secure-custom-fields' ),
				'dashicons-media-document'            => esc_html__( 'Document Icon', 'secure-custom-fields' ),
				'dashicons-media-interactive'         => esc_html__( 'Interactive Icon', 'secure-custom-fields' ),
				'dashicons-media-spreadsheet'         => esc_html__( 'Spreadsheet Icon', 'secure-custom-fields' ),
				'dashicons-media-text'                => esc_html__( 'Text Icon', 'secure-custom-fields' ),
				'dashicons-media-video'               => esc_html__( 'Video Icon', 'secure-custom-fields' ),
				'dashicons-megaphone'                 => esc_html__( 'Megaphone Icon', 'secure-custom-fields' ),
				'dashicons-menu'                      => esc_html__( 'Menu Icon', 'secure-custom-fields' ),
				'dashicons-menu-alt'                  => esc_html__( 'Menu (alt) Icon', 'secure-custom-fields' ),
				'dashicons-menu-alt2'                 => esc_html__( 'Menu (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-menu-alt3'                 => esc_html__( 'Menu (alt3) Icon', 'secure-custom-fields' ),
				'dashicons-microphone'                => esc_html__( 'Microphone Icon', 'secure-custom-fields' ),
				'dashicons-migrate'                   => esc_html__( 'Migrate Icon', 'secure-custom-fields' ),
				'dashicons-minus'                     => esc_html__( 'Minus Icon', 'secure-custom-fields' ),
				'dashicons-money'                     => esc_html__( 'Money Icon', 'secure-custom-fields' ),
				'dashicons-money-alt'                 => esc_html__( 'Money (alt) Icon', 'secure-custom-fields' ),
				'dashicons-move'                      => esc_html__( 'Move Icon', 'secure-custom-fields' ),
				'dashicons-nametag'                   => esc_html__( 'Nametag Icon', 'secure-custom-fields' ),
				'dashicons-networking'                => esc_html__( 'Networking Icon', 'secure-custom-fields' ),
				'dashicons-no'                        => esc_html__( 'No Icon', 'secure-custom-fields' ),
				'dashicons-no-alt'                    => esc_html__( 'No (alt) Icon', 'secure-custom-fields' ),
				'dashicons-open-folder'               => esc_html__( 'Open Folder Icon', 'secure-custom-fields' ),
				'dashicons-palmtree'                  => esc_html__( 'Palm Tree Icon', 'secure-custom-fields' ),
				'dashicons-paperclip'                 => esc_html__( 'Paperclip Icon', 'secure-custom-fields' ),
				'dashicons-pdf'                       => esc_html__( 'PDF Icon', 'secure-custom-fields' ),
				'dashicons-performance'               => esc_html__( 'Performance Icon', 'secure-custom-fields' ),
				'dashicons-pets'                      => esc_html__( 'Pets Icon', 'secure-custom-fields' ),
				'dashicons-phone'                     => esc_html__( 'Phone Icon', 'secure-custom-fields' ),
				'dashicons-pinterest'                 => esc_html__( 'Pinterest Icon', 'secure-custom-fields' ),
				'dashicons-playlist-audio'            => esc_html__( 'Playlist Audio Icon', 'secure-custom-fields' ),
				'dashicons-playlist-video'            => esc_html__( 'Playlist Video Icon', 'secure-custom-fields' ),
				'dashicons-plugins-checked'           => esc_html__( 'Plugins Checked Icon', 'secure-custom-fields' ),
				'dashicons-plus'                      => esc_html__( 'Plus Icon', 'secure-custom-fields' ),
				'dashicons-plus-alt'                  => esc_html__( 'Plus (alt) Icon', 'secure-custom-fields' ),
				'dashicons-plus-alt2'                 => esc_html__( 'Plus (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-podio'                     => esc_html__( 'Podio Icon', 'secure-custom-fields' ),
				'dashicons-portfolio'                 => esc_html__( 'Portfolio Icon', 'secure-custom-fields' ),
				'dashicons-post-status'               => esc_html__( 'Post Status Icon', 'secure-custom-fields' ),
				'dashicons-pressthis'                 => esc_html__( 'Pressthis Icon', 'secure-custom-fields' ),
				'dashicons-printer'                   => esc_html__( 'Printer Icon', 'secure-custom-fields' ),
				'dashicons-privacy'                   => esc_html__( 'Privacy Icon', 'secure-custom-fields' ),
				'dashicons-products'                  => esc_html__( 'Products Icon', 'secure-custom-fields' ),
				'dashicons-randomize'                 => esc_html__( 'Randomize Icon', 'secure-custom-fields' ),
				'dashicons-reddit'                    => esc_html__( 'Reddit Icon', 'secure-custom-fields' ),
				'dashicons-redo'                      => esc_html__( 'Redo Icon', 'secure-custom-fields' ),
				'dashicons-remove'                    => esc_html__( 'Remove Icon', 'secure-custom-fields' ),
				'dashicons-rest-api'                  => esc_html__( 'REST API Icon', 'secure-custom-fields' ),
				'dashicons-rss'                       => esc_html__( 'RSS Icon', 'secure-custom-fields' ),
				'dashicons-saved'                     => esc_html__( 'Saved Icon', 'secure-custom-fields' ),
				'dashicons-schedule'                  => esc_html__( 'Schedule Icon', 'secure-custom-fields' ),
				'dashicons-screenoptions'             => esc_html__( 'Screen Options Icon', 'secure-custom-fields' ),
				'dashicons-search'                    => esc_html__( 'Search Icon', 'secure-custom-fields' ),
				'dashicons-share'                     => esc_html__( 'Share Icon', 'secure-custom-fields' ),
				'dashicons-share-alt'                 => esc_html__( 'Share (alt) Icon', 'secure-custom-fields' ),
				'dashicons-share-alt2'                => esc_html__( 'Share (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-shield'                    => esc_html__( 'Shield Icon', 'secure-custom-fields' ),
				'dashicons-shield-alt'                => esc_html__( 'Shield (alt) Icon', 'secure-custom-fields' ),
				'dashicons-shortcode'                 => esc_html__( 'Shortcode Icon', 'secure-custom-fields' ),
				'dashicons-slides'                    => esc_html__( 'Slides Icon', 'secure-custom-fields' ),
				'dashicons-smartphone'                => esc_html__( 'Smartphone Icon', 'secure-custom-fields' ),
				'dashicons-smiley'                    => esc_html__( 'Smiley Icon', 'secure-custom-fields' ),
				'dashicons-sort'                      => esc_html__( 'Sort Icon', 'secure-custom-fields' ),
				'dashicons-sos'                       => esc_html__( 'Sos Icon', 'secure-custom-fields' ),
				'dashicons-spotify'                   => esc_html__( 'Spotify Icon', 'secure-custom-fields' ),
				'dashicons-star-empty'                => esc_html__( 'Star Empty Icon', 'secure-custom-fields' ),
				'dashicons-star-filled'               => esc_html__( 'Star Filled Icon', 'secure-custom-fields' ),
				'dashicons-star-half'                 => esc_html__( 'Star Half Icon', 'secure-custom-fields' ),
				'dashicons-sticky'                    => esc_html__( 'Sticky Icon', 'secure-custom-fields' ),
				'dashicons-store'                     => esc_html__( 'Store Icon', 'secure-custom-fields' ),
				'dashicons-superhero'                 => esc_html__( 'Superhero Icon', 'secure-custom-fields' ),
				'dashicons-superhero-alt'             => esc_html__( 'Superhero (alt) Icon', 'secure-custom-fields' ),
				'dashicons-table-col-after'           => esc_html__( 'Table Col After Icon', 'secure-custom-fields' ),
				'dashicons-table-col-before'          => esc_html__( 'Table Col Before Icon', 'secure-custom-fields' ),
				'dashicons-table-col-delete'          => esc_html__( 'Table Col Delete Icon', 'secure-custom-fields' ),
				'dashicons-table-row-after'           => esc_html__( 'Table Row After Icon', 'secure-custom-fields' ),
				'dashicons-table-row-before'          => esc_html__( 'Table Row Before Icon', 'secure-custom-fields' ),
				'dashicons-table-row-delete'          => esc_html__( 'Table Row Delete Icon', 'secure-custom-fields' ),
				'dashicons-tablet'                    => esc_html__( 'Tablet Icon', 'secure-custom-fields' ),
				'dashicons-tag'                       => esc_html__( 'Tag Icon', 'secure-custom-fields' ),
				'dashicons-tagcloud'                  => esc_html__( 'Tagcloud Icon', 'secure-custom-fields' ),
				'dashicons-testimonial'               => esc_html__( 'Testimonial Icon', 'secure-custom-fields' ),
				'dashicons-text'                      => esc_html__( 'Text Icon', 'secure-custom-fields' ),
				'dashicons-text-page'                 => esc_html__( 'Text Page Icon', 'secure-custom-fields' ),
				'dashicons-thumbs-down'               => esc_html__( 'Thumbs Down Icon', 'secure-custom-fields' ),
				'dashicons-thumbs-up'                 => esc_html__( 'Thumbs Up Icon', 'secure-custom-fields' ),
				'dashicons-tickets'                   => esc_html__( 'Tickets Icon', 'secure-custom-fields' ),
				'dashicons-tickets-alt'               => esc_html__( 'Tickets (alt) Icon', 'secure-custom-fields' ),
				'dashicons-tide'                      => esc_html__( 'Tide Icon', 'secure-custom-fields' ),
				'dashicons-translation'               => esc_html__( 'Translation Icon', 'secure-custom-fields' ),
				'dashicons-trash'                     => esc_html__( 'Trash Icon', 'secure-custom-fields' ),
				'dashicons-twitch'                    => esc_html__( 'Twitch Icon', 'secure-custom-fields' ),
				'dashicons-twitter'                   => esc_html__( 'Twitter Icon', 'secure-custom-fields' ),
				'dashicons-twitter-alt'               => esc_html__( 'Twitter (alt) Icon', 'secure-custom-fields' ),
				'dashicons-undo'                      => esc_html__( 'Undo Icon', 'secure-custom-fields' ),
				'dashicons-universal-access'          => esc_html__( 'Universal Access Icon', 'secure-custom-fields' ),
				'dashicons-universal-access-alt'      => esc_html__( 'Universal Access (alt) Icon', 'secure-custom-fields' ),
				'dashicons-unlock'                    => esc_html__( 'Unlock Icon', 'secure-custom-fields' ),
				'dashicons-update'                    => esc_html__( 'Update Icon', 'secure-custom-fields' ),
				'dashicons-update-alt'                => esc_html__( 'Update (alt) Icon', 'secure-custom-fields' ),
				'dashicons-upload'                    => esc_html__( 'Upload Icon', 'secure-custom-fields' ),
				'dashicons-vault'                     => esc_html__( 'Vault Icon', 'secure-custom-fields' ),
				'dashicons-video-alt'                 => esc_html__( 'Video (alt) Icon', 'secure-custom-fields' ),
				'dashicons-video-alt2'                => esc_html__( 'Video (alt2) Icon', 'secure-custom-fields' ),
				'dashicons-video-alt3'                => esc_html__( 'Video (alt3) Icon', 'secure-custom-fields' ),
				'dashicons-visibility'                => esc_html__( 'Visibility Icon', 'secure-custom-fields' ),
				'dashicons-warning'                   => esc_html__( 'Warning Icon', 'secure-custom-fields' ),
				'dashicons-welcome-add-page'          => esc_html__( 'Add Page Icon', 'secure-custom-fields' ),
				'dashicons-welcome-comments'          => esc_html__( 'Comments Icon', 'secure-custom-fields' ),
				'dashicons-welcome-learn-more'        => esc_html__( 'Learn More Icon', 'secure-custom-fields' ),
				'dashicons-welcome-view-site'         => esc_html__( 'View Site Icon', 'secure-custom-fields' ),
				'dashicons-welcome-widgets-menus'     => esc_html__( 'Widgets Menus Icon', 'secure-custom-fields' ),
				'dashicons-welcome-write-blog'        => esc_html__( 'Write Blog Icon', 'secure-custom-fields' ),
				'dashicons-whatsapp'                  => esc_html__( 'WhatsApp Icon', 'secure-custom-fields' ),
				'dashicons-wordpress'                 => esc_html__( 'WordPress Icon', 'secure-custom-fields' ),
				'dashicons-wordpress-alt'             => esc_html__( 'WordPress (alt) Icon', 'secure-custom-fields' ),
				'dashicons-xing'                      => esc_html__( 'Xing Icon', 'secure-custom-fields' ),
				'dashicons-yes'                       => esc_html__( 'Yes Icon', 'secure-custom-fields' ),
				'dashicons-yes-alt'                   => esc_html__( 'Yes (alt) Icon', 'secure-custom-fields' ),
				'dashicons-youtube'                   => esc_html__( 'YouTube Icon', 'secure-custom-fields' ),
			);

			return apply_filters( 'acf/fields/icon_picker/dashicons', $dashicons );
		}

		/**
		 * Returns the schema used by the REST API.
		 *
		 * @since ACF 6.3
		 *
		 * @param array $field The main field array.
		 * @return array
		 */
		public function get_rest_schema( array $field ): array {
			return array(
				'type'       => array( 'object', 'null' ),
				'required'   => ! empty( $field['required'] ),
				'properties' => array(
					'type'  => array(
						'description' => esc_html__( 'The type of icon to save.', 'secure-custom-fields' ),
						'type'        => array( 'string' ),
						'required'    => true,
						'enum'        => array_keys( $this->get_tabs() ),
					),
					'value' => array(
						'description' => esc_html__( 'The value of icon to save.', 'secure-custom-fields' ),
						'type'        => array( 'string', 'int' ),
						'required'    => true,
					),
				),
			);
		}

		/**
		 * Validates a value sent via the REST API.
		 *
		 * @since ACF 6.3
		 *
		 * @param boolean    $valid The current validity boolean.
		 * @param array|null $value The value of the field.
		 * @param array      $field The main field array.
		 * @return boolean|WP_Error
		 */
		public function validate_rest_value( $valid, $value, $field ) {
			if ( is_null( $value ) ) {
				if ( ! empty( $field['required'] ) ) {
					return new WP_Error(
						'rest_property_required',
						/* translators: %s - field name */
						sprintf( __( '%s is a required property of acf.', 'secure-custom-fields' ), $field['name'] )
					);
				} else {
					return $valid;
				}
			}

			if ( ! empty( $value['type'] ) && 'media_library' === $value['type'] ) {
				$param = sprintf( '%s[%s][value]', $field['prefix'], $field['name'] );
				$data  = array(
					'param' => $param,
					'value' => (int) $value['value'],
				);

				if ( ! is_int( $value['value'] ) || 'attachment' !== get_post_type( $value['value'] ) ) {
					/* translators: %s - field/param name */
					$error = sprintf( __( '%s requires a valid attachment ID when type is set to media_library.', 'secure-custom-fields' ), $param );
					return new WP_Error( 'rest_invalid_param', $error, $data );
				}
			}

			return $valid;
		}
	}

	acf_register_field_type( 'acf_field_icon_picker' );
endif;
