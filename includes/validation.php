<?php // phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed, PEAR.NamingConventions.ValidClassName

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'acf_validation' ) ) :
	#[AllowDynamicProperties]
	/**
	 * Validation Class
	 */
	class acf_validation {
		/**
		 * This function will setup the class functionality
		 *
		 * @type    function
		 * @date    5/03/2014
		 * @since   ACF 5.0.0
		 *
		 * @return  void
		 */
		public function __construct() {

			// vars
			$this->errors = array();

			// ajax
			add_action( 'wp_ajax_acf/validate_save_post', array( $this, 'ajax_validate_save_post' ) );
			add_action( 'wp_ajax_nopriv_acf/validate_save_post', array( $this, 'ajax_validate_save_post' ) );
			add_action( 'acf/validate_save_post', array( $this, 'acf_validate_save_post' ), 5 );
		}


		/**
		 * This function will add an error message for a field
		 *
		 * @type    function
		 * @date    25/11/2013
		 * @since   ACF 5.0.0
		 *
		 * @param   string $input name attribute of DOM elmenet.
		 * @param   string $message error message.
		 */
		public function add_error( $input, $message ) {

			// add to array
			$this->errors[] = array(
				'input'   => $input,
				'message' => $message,
			);
		}


		/**
		 * This function will return an error for a given input
		 *
		 * @type    function
		 * @date    5/03/2016
		 * @since   ACF 5.3.2
		 *
		 * @param   string $input name attribute of DOM elmenet.
		 * @return  array|bool
		 */
		public function get_error( $input ) {

			// bail early if no errors
			if ( empty( $this->errors ) ) {
				return false;
			}

			// loop
			foreach ( $this->errors as $error ) {
				if ( $error['input'] === $input ) {
					return $error;
				}
			}

			// return
			return false;
		}


		/**
		 * This function will return validation errors
		 *
		 * @type    function
		 * @date    25/11/2013
		 * @since   ACF 5.0.0
		 *
		 * @return  array|bool
		 */
		public function get_errors() {

			// bail early if no errors
			if ( empty( $this->errors ) ) {
				return false;
			}

			// return
			return $this->errors;
		}


		/**
		 * This function will remove all errors
		 *
		 * @type    function
		 * @date    4/03/2016
		 * @since   ACF 5.3.2
		 *
		 * @return  void
		 */
		public function reset_errors() {

			$this->errors = array();
		}

		/**
		 * Validates $_POST data via AJAX prior to save.
		 *
		 * @since   ACF 5.0.9
		 *
		 * @return void
		 */
		public function ajax_validate_save_post() {
			if ( ! acf_verify_ajax() ) {
				wp_send_json_success(
					array(
						'valid'  => 0,
						'errors' => array(
							array(
								'input'   => false,
								'message' => __( 'ACF was unable to perform validation due to an invalid security nonce being provided.', 'secure-custom-fields' ),
							),
						),
					)
				);
			}

			$json = array(
				'valid'  => 1,
				'errors' => 0,
			);

			if ( acf_validate_save_post() ) {
				wp_send_json_success( $json );
			}

			$json['valid']  = 0;
			$json['errors'] = acf_get_validation_errors();

			wp_send_json_success( $json );
		}

		/**
		 * Loops over $_POST data and validates ACF values.
		 *
		 * @since   ACF 5.4.0
		 */
		public function acf_validate_save_post() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified elsewhere.
			$post_type = acf_request_arg( 'post_type', false );
			$screen    = acf_request_arg( '_acf_screen', false );

			if ( in_array( $screen, array( 'post_type', 'taxonomy', 'ui_options_page' ), true ) && in_array( $post_type, array( 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page' ), true ) ) {
				acf_validate_internal_post_type_values( $post_type );
			} elseif ( acf_request_arg( 'acf_ui_options_page' ) ) {
				acf_validate_internal_post_type_values( 'acf-ui-options-page' );
			} else {
				// Bail early if no matching $_POST.
				if ( empty( $_POST['acf'] ) ) {
					return;
				}

				acf_validate_values( wp_unslash( $_POST['acf'] ), 'acf' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}
	}

	// initialize
	acf()->validation = new acf_validation();
endif; // class_exists check


/**
 * Add validation error.
 *
 * Alias of acf()->validation->add_error()
 *
 * @type    function
 * @date    6/10/13
 * @since   ACF 5.0.0
 *
 * @param   string $input name attribute of DOM elmenet.
 * @param   string $message error message.
 * @return  void
 */
function acf_add_validation_error( $input, $message = '' ) {
	acf()->validation->add_error( $input, $message );
}

/**
 * Retrieve validation errors.
 *
 * Alias of acf()->validation->function()
 *
 * @type    function
 * @date    6/10/13
 * @since   ACF 5.0.0
 *
 * @return  array|bool
 */
function acf_get_validation_errors() {
	return acf()->validation->get_errors();
}

/**
 * Get the validation error.
 *
 * Alias of acf()->validation->get_error()
 *
 * @type    function
 * @date    6/10/13
 * @since   ACF 5.0.0
 * @since   6.4.1 Added the $input parameter, which is required in the get_error method.
 *
 * @param   string $input name attribute of DOM elmenet.
 *
 * @return  string|bool
 */
function acf_get_validation_error( $input ) {
	return acf()->validation->get_error( $input );
}

/**
 * Reset Validation errors.
 *
 * Alias of acf()->validation->reset_errors()
 *
 * @type    function
 * @date    6/10/13
 * @since   ACF 5.0.0
 *
 * @return  void
 */
function acf_reset_validation_errors() {
	acf()->validation->reset_errors();
}


/**
 * This function will validate $_POST data and add errors
 *
 * @type    function
 * @date    25/11/2013
 * @since   ACF 5.0.0
 *
 * @param   bool $show_errors if true, errors will be shown via a wp_die screen.
 * @return  bool
 */
function acf_validate_save_post( $show_errors = false ) {

	// action
	do_action( 'acf/validate_save_post' );

	// vars
	$errors = acf_get_validation_errors();

	// bail early if no errors
	if ( ! $errors ) {
		return true;
	}

	// show errors
	if ( $show_errors ) {
		$message  = '<h2>' . __( 'Validation failed', 'secure-custom-fields' ) . '</h2>';
		$message .= '<ul>';
		foreach ( $errors as $error ) {
			$message .= '<li>' . $error['message'] . '</li>';
		}
		$message .= '</ul>';

		// die
		wp_die( acf_esc_html( $message ), esc_html__( 'Validation failed', 'secure-custom-fields' ) );
	}

	// return
	return false;
}


/**
 * This function will validate an array of field values
 *
 * @type    function
 * @date    6/10/13
 * @since   ACF 5.0.0
 *
 * @param   array  $values An array of field values.
 * @param   string $input_prefix The input element's name attribute.
 *
 * @return  void
 */
function acf_validate_values( $values, $input_prefix = '' ) {

	// bail early if empty
	if ( empty( $values ) ) {
		return;
	}

	// loop
	foreach ( $values as $key => $value ) {

		// vars
		$field = acf_get_field( $key );
		$input = $input_prefix . '[' . $key . ']';

		// bail early if not found
		if ( ! $field ) {
			continue;
		}

		// validate
		acf_validate_value( $value, $field, $input );
	}
}


/**
 * This function will validate a field's value
 *
 * @type    function
 * @date    6/10/13
 * @since   ACF 5.0.0
 *
 * @param   mixed  $value The field value to validate.
 * @param   array  $field The field array.
 * @param   string $input The input element's name attribute.
 *
 * @return  boolean
 */
function acf_validate_value( $value, $field, $input ) {

	// vars
	$valid = true;
	/* translators: %s: field label */
	$message = sprintf( __( '%s value is required', 'secure-custom-fields' ), $field['label'] );

	// valid
	if ( $field['required'] ) {

		// valid is set to false if the value is empty, but allow 0 as a valid value
		if ( empty( $value ) && ! is_numeric( $value ) ) {
			$valid = false;
		}
	}

	/**
	 * Filters whether the value is valid.
	 *
	 * @date    28/09/13
	 * @since   ACF 5.0.0
	 *
	 * @param   bool $valid The valid status. Return a string to display a custom error message.
	 * @param   mixed $value The value.
	 * @param   array $field The field array.
	 * @param   string $input The input element's name attribute.
	 */
	$valid = apply_filters( "acf/validate_value/type={$field['type']}", $valid, $value, $field, $input );
	$valid = apply_filters( "acf/validate_value/name={$field['_name']}", $valid, $value, $field, $input );
	$valid = apply_filters( "acf/validate_value/key={$field['key']}", $valid, $value, $field, $input );
	$valid = apply_filters( 'acf/validate_value', $valid, $value, $field, $input );

	// allow $valid to be a custom error message
	if ( ! empty( $valid ) && is_string( $valid ) ) {
		$message = $valid;
		$valid   = false;
	}

	if ( ! $valid ) {
		acf_add_validation_error( $input, $message );
		return false;
	}

	// return
	return true;
}
