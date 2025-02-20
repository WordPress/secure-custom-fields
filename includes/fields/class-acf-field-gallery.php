<?php
/**
 * The Gallery Field.
 *
 * @package wordpress/secure-custom-fields
 */

 // phpcs:disable PEAR.NamingConventions.ValidClassName

if ( ! class_exists( 'acf_field_gallery' ) ) :

	/**
	 * The Field Gallery class.
	 */
	class acf_field_gallery extends acf_field {
		/**
		 * This function will setup the field type data
		 *
		 * @type    function
		 * @date    5/03/2014
		 * @since   ACF 5.0.0
		 *
		 * @return  void
		 */
		public function initialize() {

			// vars
			$this->name          = 'gallery';
			$this->label         = __( 'Gallery', 'secure-custom-fields' );
			$this->category      = 'content';
			$this->description   = __( 'An interactive interface for managing a collection of attachments, such as images.', 'secure-custom-fields' );
			$this->preview_image = acf_get_url() . '/assets/images/field-type-previews/field-preview-gallery.png';
			$this->doc_url       = 'https://developer.wordpress.org/secure-custom-fields/features/fields/gallery/';
			$this->tutorial_url  = 'https://developer.wordpress.org/secure-custom-fields/features/fields/gallery/gallery-tutorial/';
			$this->pro           = true;
			$this->supports      = array( 'bindings' => false );
			$this->defaults      = array(
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'insert'        => 'append',
				'library'       => 'all',
				'min'           => 0,
				'max'           => 0,
				'min_width'     => 0,
				'min_height'    => 0,
				'min_size'      => 0,
				'max_width'     => 0,
				'max_height'    => 0,
				'max_size'      => 0,
				'mime_types'    => '',
			);

			// actions
			add_action( 'wp_ajax_acf/fields/gallery/get_attachment', array( $this, 'ajax_get_attachment' ) );
			add_action( 'wp_ajax_nopriv_acf/fields/gallery/get_attachment', array( $this, 'ajax_get_attachment' ) );

			add_action( 'wp_ajax_acf/fields/gallery/update_attachment', array( $this, 'ajax_update_attachment' ) );
			add_action( 'wp_ajax_nopriv_acf/fields/gallery/update_attachment', array( $this, 'ajax_update_attachment' ) );

			add_action( 'wp_ajax_acf/fields/gallery/get_sort_order', array( $this, 'ajax_get_sort_order' ) );
			add_action( 'wp_ajax_nopriv_acf/fields/gallery/get_sort_order', array( $this, 'ajax_get_sort_order' ) );
		}

		/**
		 * Enqueue admin scripts.
		 *
		 * @type    function
		 * @date    16/12/2015
		 * @since   ACF 5.3.2
		 */
		public function input_admin_enqueue_scripts() {

			// localize
			acf_localize_text(
				array(
					'Add Image to Gallery'      => __( 'Add Image to Gallery', 'secure-custom-fields' ),
					'Maximum selection reached' => __( 'Maximum selection reached', 'secure-custom-fields' ),
				)
			);
		}

		/**
		 * AJAX handler for retrieving and rendering an attachment.
		 *
		 * @since ACF 5.0.0
		 *
		 * @return void
		 */
		public function ajax_get_attachment() {
			// Get args.
			$args = acf_request_args(
				array(
					'id'        => 0,
					'field_key' => '',
					'nonce'     => '',
				)
			);

			// Validate request.
			if ( ! acf_verify_ajax( $args['nonce'], $args['field_key'] ) ) {
				die();
			}

			// Cast args.
			$args['id'] = (int) $args['id'];

			// Bail early if no id.
			if ( ! $args['id'] ) {
				die();
			}

			// Load field.
			$field = acf_get_field( $args['field_key'] );
			if ( ! $field ) {
				die();
			}

			// Render.
			$this->render_attachment( $args['id'], $field );
			die;
		}

		/**
		 * AJAX handler for updating an attachment.
		 *
		 * @since   ACF 5.0.0
		 *
		 * @return void
		 */
		public function ajax_update_attachment() {
			$args = acf_request_args(
				array(
					'nonce'     => '',
					'field_key' => '',
				)
			);

			if ( ! acf_verify_ajax( $args['nonce'], $args['field_key'] ) ) {
				wp_send_json_error();
			}

			// bail early if no attachments
			if ( empty( $_POST['attachments'] ) ) {
				wp_send_json_error();
			}

			// loop over attachments
			foreach ( wp_unslash( $_POST['attachments'] ) as $id => $changes ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by WP core when saved.

				if ( ! current_user_can( 'edit_post', $id ) ) {
					wp_send_json_error();
				}

				$post = get_post( $id, ARRAY_A );

				if ( 'attachment' !== $post['post_type'] ) {
					wp_send_json_error();
				}

				if ( isset( $changes['title'] ) ) {
					$post['post_title'] = $changes['title'];
				}

				if ( isset( $changes['caption'] ) ) {
					$post['post_excerpt'] = $changes['caption'];
				}

				if ( isset( $changes['description'] ) ) {
					$post['post_content'] = $changes['description'];
				}

				if ( isset( $changes['alt'] ) ) {
					$alt = wp_unslash( $changes['alt'] );
					if ( get_post_meta( $id, '_wp_attachment_image_alt', true ) !== $alt ) {
						$alt = wp_strip_all_tags( $alt, true );
						update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
					}
				}

				// save post
				wp_update_post( $post );

				/** This filter is documented in wp-admin/includes/media.php */
				// - seems off to run this filter AFTER the update_post function, but there is a reason
				// - when placed BEFORE, an empty post_title will be populated by WP
				// - this filter will still allow 3rd party to save extra image data!
				$post = apply_filters( 'attachment_fields_to_save', $post, $changes );

				// save meta
				acf_save_post( $id );
			}

			// return
			wp_send_json_success();
		}

		/**
		 * AJAX handler for getting the attachment sort order.
		 *
		 * @since ACF 5.0.0
		 *
		 * @return void
		 */
		public function ajax_get_sort_order() {
			$order = 'DESC';
			$args  = acf_parse_args(
				$_POST, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
				array(
					'ids'       => 0,
					'sort'      => 'date',
					'field_key' => '',
					'nonce'     => '',
				)
			);

			if ( ! acf_verify_ajax( $args['nonce'], $args['field_key'] ) ) {
				wp_send_json_error();
			}

			// Reverse order.
			if ( 'reverse' === $args['sort'] ) {
				$ids = array_reverse( $args['ids'] );
				wp_send_json_success( $ids );
			}

			// Ascending order.
			if ( 'title' === $args['sort'] ) {
				$order = 'ASC';
			}

			// Find attachments (DISTINCT POSTS).
			$ids = get_posts(
				array(
					'post_type'   => 'attachment',
					'numberposts' => -1,
					'post_status' => 'any',
					'post__in'    => $args['ids'],
					'order'       => $order,
					'orderby'     => $args['sort'],
					'fields'      => 'ids',
				)
			);

			if ( ! empty( $ids ) ) {
				wp_send_json_success( $ids );
			}

			wp_send_json_error();
		}

		/**
		 * Renders the sidebar HTML shown when selecting an attachmemnt.
		 *
		 * @date    13/12/2013
		 * @since   ACF 5.0.0
		 *
		 * @param   integer $id    The attachment ID.
		 * @param   array   $field The field array.
		 * @return  void
		 */
		public function render_attachment( $id, $field ) {
			// Load attachmenet data.
			$attachment = wp_prepare_attachment_for_js( $id );
			$compat     = get_compat_media_markup( $id );

			// Get attachment thumbnail (video).
			if ( isset( $attachment['thumb']['src'] ) ) {
				$thumb = $attachment['thumb']['src'];

				// Look for thumbnail size (image).
			} elseif ( isset( $attachment['sizes']['thumbnail']['url'] ) ) {
				$thumb = $attachment['sizes']['thumbnail']['url'];

				// Use url for svg.
			} elseif ( 'image' === $attachment['type'] ) {
				$thumb = $attachment['url'];

				// Default to icon.
			} else {
				$thumb = wp_mime_type_icon( $id );
			}

			// Get attachment dimensions / time / size.
			$dimensions = '';
			if ( 'audio' === $attachment['type'] ) {
				$dimensions = __( 'Length', 'secure-custom-fields' ) . ': ' . $attachment['fileLength'];
			} elseif ( ! empty( $attachment['width'] ) ) {
				$dimensions = $attachment['width'] . ' x ' . $attachment['height'];
			}
			if ( ! empty( $attachment['filesizeHumanReadable'] ) ) {
				$dimensions .= ' (' . $attachment['filesizeHumanReadable'] . ')';
			}

			?>
			<div class="acf-gallery-side-info">
				<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $attachment['alt'] ); ?>" />
				<p class="filename"><strong><?php echo esc_html( $attachment['filename'] ); ?></strong></p>
				<p class="uploaded"><?php echo esc_html( $attachment['dateFormatted'] ); ?></p>
				<p class="dimensions"><?php echo esc_html( $dimensions ); ?></p>
				<p class="actions">
					<a href="#" class="acf-gallery-edit" data-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Edit', 'secure-custom-fields' ); ?></a>
					<a href="#" class="acf-gallery-remove" data-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Remove', 'secure-custom-fields' ); ?></a>
				</p>
			</div>
			<table class="form-table">
				<tbody>
					<?php

					// Render fields.
					$prefix = 'attachments[' . $id . ']';

					acf_render_field_wrap(
						array(
							'name'   => 'title',
							'prefix' => $prefix,
							'type'   => 'text',
							'label'  => __( 'Title', 'secure-custom-fields' ),
							'value'  => $attachment['title'],
						),
						'tr'
					);

					acf_render_field_wrap(
						array(
							'name'   => 'caption',
							'prefix' => $prefix,
							'type'   => 'textarea',
							'label'  => __( 'Caption', 'secure-custom-fields' ),
							'value'  => $attachment['caption'],
						),
						'tr'
					);

					acf_render_field_wrap(
						array(
							'name'   => 'alt',
							'prefix' => $prefix,
							'type'   => 'text',
							'label'  => __( 'Alt Text', 'secure-custom-fields' ),
							'value'  => $attachment['alt'],
						),
						'tr'
					);

					acf_render_field_wrap(
						array(
							'name'   => 'description',
							'prefix' => $prefix,
							'type'   => 'textarea',
							'label'  => __( 'Description', 'secure-custom-fields' ),
							'value'  => $attachment['description'],
						),
						'tr'
					);

					?>
				</tbody>
			</table>
			<?php

			// Display compat fields.
			echo $compat['item']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside get_compat_media_markup().
		}

		/**
		 * Creates the HTML interface for the gallery field.
		 *
		 * @param   array $field The field array holding all the field's data.
		 *
		 * @type    action
		 * @since   ACF 3.6
		 * @date    23/01/13
		 * @return  void
		 */
		public function render_field( $field ) {

			// Enqueue uploader assets.
			acf_enqueue_uploader();

			// Control attributes.
			$attrs = array(
				'id'                => $field['id'],
				'class'             => "acf-gallery {$field['class']}",
				'data-library'      => $field['library'],
				'data-preview_size' => $field['preview_size'],
				'data-min'          => $field['min'],
				'data-max'          => $field['max'],
				'data-mime_types'   => $field['mime_types'],
				'data-insert'       => $field['insert'],
				'data-columns'      => 4,
				'data-nonce'        => wp_create_nonce( $field['key'] ),
			);

			// Set gallery height with deafult of 400px and minimum of 200px.
			$height         = acf_get_user_setting( 'gallery_height', 400 );
			$height         = max( $height, 200 );
			$attrs['style'] = "height:{$height}px";

			// Load attachments.
			$attachments = array();
			if ( $field['value'] ) {

				// Clean value into an array of IDs.
				$attachment_ids = array_map( 'intval', acf_array( $field['value'] ) );

				// Find posts in database (ensures all results are real).
				$posts = acf_get_posts(
					array(
						'post_type'              => 'attachment',
						'post__in'               => $attachment_ids,
						'update_post_meta_cache' => true,
						'update_post_term_cache' => false,
					)
				);

				// Load attatchment data for each post.
				$attachments = array_map( 'acf_get_attachment', $posts );
			}

			?>
			<div <?php echo acf_esc_attrs( $attrs ); ?>>
				<input type="hidden" name="<?php echo esc_attr( $field['name'] ); ?>" value="" />
				<div class="acf-gallery-main">
					<div class="acf-gallery-attachments">
						<?php if ( $attachments ) : ?>
							<?php
							foreach ( $attachments as $i => $attachment ) :

								// Vars
								$a_id       = $attachment['ID'];
								$a_title    = $attachment['title'];
								$a_type     = $attachment['type'];
								$a_filename = $attachment['filename'];
								$a_class    = "acf-gallery-attachment -{$a_type}";

								// Get thumbnail.
								$a_thumbnail = acf_get_post_thumbnail( $a_id, $field['preview_size'] );
								$a_class    .= ( 'icon' === $a_thumbnail['type'] ) ? ' -icon' : '';

								?>
								<div class="<?php echo esc_attr( $a_class ); ?>" data-id="<?php echo esc_attr( $a_id ); ?>">
									<input type="hidden" name="<?php echo esc_attr( $field['name'] ); ?>[]" value="<?php echo esc_attr( $a_id ); ?>" />
									<div class="margin">
										<div class="thumbnail">
											<img src="<?php echo esc_url( $a_thumbnail['url'] ); ?>" alt="" />
										</div>
										<?php if ( 'image' !== $a_type ) : ?>
											<div class="filename"><?php echo acf_esc_html( acf_get_truncated( $a_filename, 30 ) ); ?></div>
										<?php endif; ?>
									</div>
									<div class="actions">
										<a class="acf-icon -cancel dark acf-gallery-remove" href="#" data-id="<?php echo esc_attr( $a_id ); ?>" title="<?php esc_html_e( 'Remove', 'secure-custom-fields' ); ?>"></a>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<div class="acf-gallery-toolbar">
						<ul class="acf-hl">
							<li>
								<a href="#" class="acf-button button button-primary acf-gallery-add"><?php esc_html_e( 'Add to gallery', 'secure-custom-fields' ); ?></a>
							</li>
							<li class="acf-fr">
								<select class="acf-gallery-sort">
									<option value=""><?php esc_html_e( 'Bulk actions', 'secure-custom-fields' ); ?></option>
									<option value="date"><?php esc_html_e( 'Sort by date uploaded', 'secure-custom-fields' ); ?></option>
									<option value="modified"><?php esc_html_e( 'Sort by date modified', 'secure-custom-fields' ); ?></option>
									<option value="title"><?php esc_html_e( 'Sort by title', 'secure-custom-fields' ); ?></option>
									<option value="reverse"><?php esc_html_e( 'Reverse current order', 'secure-custom-fields' ); ?></option>
								</select>
							</li>
						</ul>
					</div>
				</div>
				<div class="acf-gallery-side">
					<div class="acf-gallery-side-inner">
						<div class="acf-gallery-side-data"></div>
						<div class="acf-gallery-toolbar">
							<ul class="acf-hl">
								<li>
									<a href="#" class="acf-button button acf-gallery-close"><?php esc_html_e( 'Close', 'secure-custom-fields' ); ?></a>
								</li>
								<li class="acf-fr">
									<a class="acf-button button button-primary acf-gallery-update" href="#"><?php esc_html_e( 'Update', 'secure-custom-fields' ); ?></a>
								</li>
							</ul>
						</div>
					</div>
				</div>
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
		 * @param   array $field  an array holding all the field's data.
		 */
		public function render_field_settings( $field ) {
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Return Format', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'radio',
					'name'         => 'return_format',
					'layout'       => 'horizontal',
					'choices'      => array(
						'array' => __( 'Image Array', 'secure-custom-fields' ),
						'url'   => __( 'Image URL', 'secure-custom-fields' ),
						'id'    => __( 'Image ID', 'secure-custom-fields' ),
					),
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Library', 'secure-custom-fields' ),
					'instructions' => __( 'Limit the media library choice', 'secure-custom-fields' ),
					'type'         => 'radio',
					'name'         => 'library',
					'layout'       => 'horizontal',
					'choices'      => array(
						'all'        => __( 'All', 'secure-custom-fields' ),
						'uploadedTo' => __( 'Uploaded to post', 'secure-custom-fields' ),
					),
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
		public function render_field_validation_settings( $field ) {
			// Clear numeric settings.
			$clear = array(
				'min',
				'max',
				'min_width',
				'min_height',
				'min_size',
				'max_width',
				'max_height',
				'max_size',
			);

			foreach ( $clear as $k ) {
				if ( empty( $field[ $k ] ) ) {
					$field[ $k ] = '';
				}
			}

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Minimum Selection', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'number',
					'name'         => 'min',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Maximum Selection', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'number',
					'name'         => 'max',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'   => __( 'Minimum', 'secure-custom-fields' ),
					'hint'    => __( 'Restrict which images can be uploaded', 'secure-custom-fields' ),
					'type'    => 'text',
					'name'    => 'min_width',
					'prepend' => __( 'Width', 'secure-custom-fields' ),
					'append'  => 'px',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'   => '',
					'type'    => 'text',
					'name'    => 'min_height',
					'prepend' => __( 'Height', 'secure-custom-fields' ),
					'append'  => 'px',
					'_append' => 'min_width',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'   => '',
					'type'    => 'text',
					'name'    => 'min_size',
					'prepend' => __( 'File size', 'secure-custom-fields' ),
					'append'  => 'MB',
					'_append' => 'min_width',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'   => __( 'Maximum', 'secure-custom-fields' ),
					'hint'    => __( 'Restrict which images can be uploaded', 'secure-custom-fields' ),
					'type'    => 'text',
					'name'    => 'max_width',
					'prepend' => __( 'Width', 'secure-custom-fields' ),
					'append'  => 'px',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'   => '',
					'type'    => 'text',
					'name'    => 'max_height',
					'prepend' => __( 'Height', 'secure-custom-fields' ),
					'append'  => 'px',
					'_append' => 'max_width',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'   => '',
					'type'    => 'text',
					'name'    => 'max_size',
					'prepend' => __( 'File size', 'secure-custom-fields' ),
					'append'  => 'MB',
					'_append' => 'max_width',
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label' => __( 'Allowed File Types', 'secure-custom-fields' ),
					'hint'  => __( 'Comma separated list. Leave blank for all types', 'secure-custom-fields' ),
					'type'  => 'text',
					'name'  => 'mime_types',
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
		public function render_field_presentation_settings( $field ) {
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Insert', 'secure-custom-fields' ),
					'instructions' => __( 'Specify where new attachments are added', 'secure-custom-fields' ),
					'type'         => 'select',
					'name'         => 'insert',
					'choices'      => array(
						'append'  => __( 'Append to the end', 'secure-custom-fields' ),
						'prepend' => __( 'Prepend to the beginning', 'secure-custom-fields' ),
					),
				)
			);

			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Preview Size', 'secure-custom-fields' ),
					'instructions' => '',
					'type'         => 'select',
					'name'         => 'preview_size',
					'choices'      => acf_get_image_sizes(),
				)
			);
		}

		/**
		 * Filters the value after it is loaded from the database and before it is returned to the template.
		 *
		 * @type    filter
		 * @since   ACF 3.6
		 * @date    23/01/13
		 *
		 * @param   mixed $value   The value which was loaded from the database.
		 * @param   mixed $post_id The post ID from which the value was loaded.
		 * @param   array $field   The field array holding all the field options.
		 *
		 * @return  mixed The modified value.
		 */
		public function format_value( $value, $post_id, $field ) {

			// Bail early if no value.
			if ( ! $value ) {
				return false;
			}

			// Clean value into an array of IDs.
			$attachment_ids = array_map( 'intval', acf_array( $value ) );

			// Find posts in database (ensures all results are real).
			$posts = acf_get_posts(
				array(
					'post_type'              => 'attachment',
					'post__in'               => $attachment_ids,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => false,
				)
			);

			// Bail early if no posts found.
			if ( ! $posts ) {
				return false;
			}

			// Format values using field settings.
			$value = array();
			foreach ( $posts as $post ) {
				switch ( $field['return_format'] ) {
					case 'object':
						$item = $post;
						break;
					case 'array':
						$item = acf_get_attachment( $post );
						break;
					case 'url':
						$item = wp_get_attachment_url( $post->ID );
						break;
					default:
						$item = $post->ID;
				}

				// Append item.
				$value[] = $item;
			}

			// Return.
			return $value;
		}


		/**
		 * Validates the value for this field.
		 *
		 * @type    function
		 * @date    11/02/2014
		 * @since   ACF 5.0.0
		 *
		 * @param   bool   $valid Whether the value is valid.
		 * @param   mixed  $value The field value.
		 * @param   array  $field The field array.
		 * @param   string $input The input element's name attribute.
		 * @return  bool|string True if valid, error message if not.
		 */
		public function validate_value( $valid, $value, $field, $input ) {

			if ( empty( $value ) || ! is_array( $value ) ) {
				$value = array();
			}

			if ( count( $value ) < $field['min'] ) {
				/* translators: 1: field label, 2: minimum number of selections */
				$valid = _n( '%1$s requires at least %2$s selection', '%1$s requires at least %2$s selections', $field['min'], 'secure-custom-fields' );
				$valid = sprintf( $valid, $field['label'], $field['min'] );
			}

			return $valid;
		}


		/**
		 * This filter is appied to the $value before it is updated in the db
		 *
		 * @type    filter
		 * @since   ACF 3.6
		 * @date    23/01/13
		 *
		 * @param   mixed $value   The value which will be saved in the database.
		 * @param   mixed $post_id The post_id of which the value will be saved.
		 * @param   array $field   The field array holding all the field options.
		 *
		 * @return  $value - the modified value
		 */
		public function update_value( $value, $post_id, $field ) {

			// Bail early if no value.
			if ( empty( $value ) ) {
				return $value;
			}

			// Convert to array.
			$value = acf_array( $value );

			// Format array of values.
			// - ensure each value is an id.
			// - Parse each id as string for SQL LIKE queries.
			$value = array_map( 'acf_idval', $value );
			$value = array_map( 'strval', $value );

			// Return value.
			return $value;
		}

		/**
		 * Validates file fields updated via the REST API.
		 *
		 * @param  boolean $valid The current validity booleean.
		 * @param  integer $value The value of the field.
		 * @param  array   $field The field array.
		 * @return boolean|WP
		 */
		public function validate_rest_value( $valid, $value, $field ) {
			if ( ! $valid || ! is_array( $value ) ) {
				return $valid;
			}

			foreach ( $value as $attachment_id ) {
				$file_valid = acf_get_field_type( 'file' )->validate_rest_value( $valid, $attachment_id, $field );

				if ( is_wp_error( $file_valid ) ) {
					return $file_valid;
				}
			}

			return $valid;
		}

		/**
		 * Return the schema array for the REST API.
		 *
		 * @param array $field The field array.
		 * @return array
		 */
		public function get_rest_schema( array $field ) {
			$schema = array(
				'type'     => array( 'array', 'null' ),
				'required' => ! empty( $field['required'] ),
				'items'    => array(
					'type' => 'number',
				),
			);

			if ( ! empty( $field['min'] ) ) {
				$schema['minItems'] = (int) $field['min'];
			}

			if ( ! empty( $field['max'] ) ) {
				$schema['maxItems'] = (int) $field['max'];
			}

			return $schema;
		}

		/**
		 * Returns an array of links for this field's value.
		 *
		 * @see \acf_field::get_rest_links()
		 * @param mixed          $value   The raw (unformatted) field value.
		 * @param integer|string $post_id The post ID.
		 * @param array          $field   The field array.
		 * @return array
		 */
		public function get_rest_links( $value, $post_id, array $field ) {
			$links = array();

			if ( empty( $value ) ) {
				return $links;
			}

			foreach ( (array) $value as $object_id ) {
				$links[] = array(
					'rel'        => 'acf:attachment',
					'href'       => rest_url( '/wp/v2/media/' . $object_id ),
					'embeddable' => true,
				);
			}

			return $links;
		}

		/**
		 * Apply basic formatting to prepare the value for default REST output.
		 *
		 * @param mixed          $value   The field value.
		 * @param string|integer $post_id The post ID.
		 * @param array          $field   The field array.
		 * @return mixed
		 */
		public function format_value_for_rest( $value, $post_id, array $field ) {
			return acf_format_numerics( $value );
		}
	}


	// initialize
	acf_register_field_type( 'acf_field_gallery' );
endif; // class_exists check

?>