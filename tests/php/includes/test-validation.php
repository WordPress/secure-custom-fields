<?php
/**
 * Tests for includes/validation.php
 *
 * Covers the acf_validation class, the validation error API, acf_validate_value()
 * (required fields, field-type validate_value filters, nested repeater/group
 * sub-field validation) and the acf/validate_value + acf/validate_save_post
 * filter contracts.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for the validation API.
 */
class Test_Validation extends BaseTestCase {

	/**
	 * Field keys registered for each test, removed again in tear_down().
	 *
	 * @var array
	 */
	private $local_field_keys = array(
		'field_validation_req_text',
		'field_validation_opt_text',
		'field_validation_number',
		'field_validation_repeater',
		'field_validation_rep_sub',
		'field_validation_group',
		'field_validation_group_sub',
	);

	/**
	 * Registers the field types and local fields used by the tests.
	 */
	public function set_up() {
		parent::set_up();

		// Load the field types exercised by these tests. The classes register
		// their filters when first included, but WorDBless restores the hooks
		// snapshot after every test, so the filters needed here are explicitly
		// (re-)added below and are cleaned up automatically on tear down. This
		// keeps the field-type filters scoped to this test class only.
		acf_include( 'includes/fields/class-acf-field-text.php' );
		acf_include( 'includes/fields/class-acf-field-number.php' );
		acf_include( 'includes/fields/class-acf-repeater-table.php' );
		acf_include( 'includes/fields/class-acf-field-repeater.php' );
		acf_include( 'includes/fields/class-acf-field-group.php' );

		foreach ( array( 'text', 'number', 'repeater', 'group' ) as $type ) {
			$instance = acf_get_field_type( $type );

			// Merges the field type defaults (min, max, pagination, ...).
			add_filter( "acf/validate_field/type={$type}", array( $instance, 'validate_field' ), 10, 1 );

			// Loads sub fields for repeater/group fields.
			if ( is_callable( array( $instance, 'load_field' ) ) ) {
				add_filter( "acf/load_field/type={$type}", array( $instance, 'load_field' ), 10, 1 );
			}

			// Extracts inline sub_fields when registering local fields.
			if ( is_callable( array( $instance, 'prepare_field_for_import' ) ) ) {
				add_filter( "acf/prepare_field_for_import/type={$type}", array( $instance, 'prepare_field_for_import' ), 10, 1 );
			}

			// The field type validation under test.
			if ( is_callable( array( $instance, 'validate_value' ) ) ) {
				add_filter( "acf/validate_value/type={$type}", array( $instance, 'validate_value' ), 10, 4 );
			}
		}

		acf_add_local_field(
			array(
				'key'      => 'field_validation_req_text',
				'name'     => 'validation_req_text',
				'type'     => 'text',
				'label'    => 'Required Text',
				'required' => 1,
			)
		);

		acf_add_local_field(
			array(
				'key'      => 'field_validation_opt_text',
				'name'     => 'validation_opt_text',
				'type'     => 'text',
				'label'    => 'Optional Text',
				'required' => 0,
			)
		);

		acf_add_local_field(
			array(
				'key'      => 'field_validation_number',
				'name'     => 'validation_number',
				'type'     => 'number',
				'label'    => 'Bounded Number',
				'required' => 0,
				'min'      => 5,
				'max'      => 10,
			)
		);

		acf_add_local_field(
			array(
				'key'        => 'field_validation_repeater',
				'name'       => 'validation_repeater',
				'type'       => 'repeater',
				'label'      => 'Validation Repeater',
				'required'   => 0,
				'sub_fields' => array(
					array(
						'key'      => 'field_validation_rep_sub',
						'name'     => 'validation_rep_sub',
						'type'     => 'text',
						'label'    => 'Repeater Sub Text',
						'required' => 1,
					),
				),
			)
		);

		acf_add_local_field(
			array(
				'key'        => 'field_validation_group',
				'name'       => 'validation_group',
				'type'       => 'group',
				'label'      => 'Validation Group',
				'required'   => 0,
				'sub_fields' => array(
					array(
						'key'      => 'field_validation_group_sub',
						'name'     => 'validation_group_sub',
						'type'     => 'text',
						'label'    => 'Group Sub Text',
						'required' => 1,
					),
				),
			)
		);
	}

	/**
	 * Resets validation errors, superglobals and local fields so that other
	 * suites are not poisoned by this test class.
	 */
	public function tear_down() {
		acf_reset_validation_errors();
		$_POST    = array();
		$_REQUEST = array();

		foreach ( $this->local_field_keys as $key ) {
			acf_remove_local_field( $key );
		}

		// Drop any fields cached by acf_get_field() during the test.
		$store = acf_get_store( 'fields' );
		if ( $store ) {
			$store->reset();
		}

		parent::tear_down();
	}

	// =========================================================================
	// Validation error API tests.
	// =========================================================================

	/**
	 * Test acf_add_validation_error() appends errors retrievable via acf_get_validation_errors().
	 */
	public function test_add_validation_error_appends_to_errors() {
		acf_add_validation_error( 'acf[field_a]', 'First message' );
		acf_add_validation_error( 'acf[field_b]', 'Second message' );

		$errors = acf_get_validation_errors();

		$this->assertIsArray( $errors, 'Should return an array of errors' );
		$this->assertCount( 2, $errors, 'Should contain both errors' );
		$this->assertSame(
			array(
				'input'   => 'acf[field_a]',
				'message' => 'First message',
			),
			$errors[0],
			'Errors should preserve input and message keys'
		);
		$this->assertSame( 'acf[field_b]', $errors[1]['input'], 'Second error should keep its input name' );
	}

	/**
	 * Test acf_get_validation_errors() returns false when no errors exist.
	 */
	public function test_get_validation_errors_returns_false_when_empty() {
		$this->assertFalse( acf_get_validation_errors(), 'Should return false when no errors were added' );
	}

	/**
	 * Test acf_get_validation_error() returns the error matching an input name.
	 */
	public function test_get_validation_error_returns_error_for_input() {
		acf_add_validation_error( 'acf[field_a]', 'First message' );
		acf_add_validation_error( 'acf[field_b]', 'Second message' );

		$error = acf_get_validation_error( 'acf[field_b]' );

		$this->assertIsArray( $error, 'Should return the matching error array' );
		$this->assertSame( 'Second message', $error['message'], 'Should return the error for the requested input' );
	}

	/**
	 * Test acf_get_validation_error() returns false for an unknown input.
	 */
	public function test_get_validation_error_returns_false_for_unknown_input() {
		acf_add_validation_error( 'acf[field_a]', 'First message' );

		$this->assertFalse( acf_get_validation_error( 'acf[missing]' ), 'Should return false for an unknown input' );
	}

	/**
	 * Test acf_reset_validation_errors() clears all stored errors.
	 */
	public function test_reset_validation_errors_clears_errors() {
		acf_add_validation_error( 'acf[field_a]', 'First message' );
		acf_reset_validation_errors();

		$this->assertFalse( acf_get_validation_errors(), 'Errors should be cleared after reset' );
	}

	// =========================================================================
	// acf_validate_value() required handling.
	// =========================================================================

	/**
	 * Test a required field with an empty string is invalid and records an error.
	 */
	public function test_validate_value_required_empty_string_fails() {
		$field = acf_get_field( 'field_validation_req_text' );

		$result = acf_validate_value( '', $field, 'acf[field_validation_req_text]' );

		$this->assertFalse( $result, 'Empty required value should be invalid' );

		$error = acf_get_validation_error( 'acf[field_validation_req_text]' );
		$this->assertIsArray( $error, 'An error should be recorded against the input name' );
		$this->assertSame( 'Required Text value is required', $error['message'], 'Should use the default required message with the field label' );
	}

	/**
	 * Test a required field with a value passes validation.
	 */
	public function test_validate_value_required_filled_passes() {
		$field = acf_get_field( 'field_validation_req_text' );

		$this->assertTrue( acf_validate_value( 'hello', $field, 'acf[field_validation_req_text]' ), 'Filled required value should be valid' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded' );
	}

	/**
	 * Test a required field accepts "0" even though it is empty() in PHP.
	 */
	public function test_validate_value_required_accepts_zero() {
		$field = acf_get_field( 'field_validation_req_text' );

		$this->assertTrue( acf_validate_value( '0', $field, 'acf[field_validation_req_text]' ), 'The string "0" should be a valid required value' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded for "0"' );
	}

	/**
	 * Test an optional field with an empty value passes validation.
	 */
	public function test_validate_value_optional_empty_passes() {
		$field = acf_get_field( 'field_validation_opt_text' );

		$this->assertTrue( acf_validate_value( '', $field, 'acf[field_validation_opt_text]' ), 'Empty optional value should be valid' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded' );
	}

	// =========================================================================
	// acf/validate_value filter contract.
	// =========================================================================

	/**
	 * Test the four acf/validate_value filter variants fire in the documented order.
	 */
	public function test_validate_value_filters_fire_in_documented_order() {
		$field = acf_get_field( 'field_validation_opt_text' );
		$fired = array();

		$recorder = function ( $name ) use ( &$fired ) {
			return function ( $valid ) use ( &$fired, $name ) {
				$fired[] = $name;
				return $valid;
			};
		};

		add_filter( 'acf/validate_value/type=text', $recorder( 'type' ), 20 );
		add_filter( 'acf/validate_value/name=validation_opt_text', $recorder( 'name' ), 20 );
		add_filter( 'acf/validate_value/key=field_validation_opt_text', $recorder( 'key' ), 20 );
		add_filter( 'acf/validate_value', $recorder( 'generic' ), 20 );

		acf_validate_value( 'value', $field, 'acf[field_validation_opt_text]' );

		$this->assertSame( array( 'type', 'name', 'key', 'generic' ), $fired, 'Filters should fire as type, name, key, then generic' );
	}

	/**
	 * Test the generic acf/validate_value filter receives value, field and input args.
	 */
	public function test_validate_value_filter_receives_expected_arguments() {
		$field    = acf_get_field( 'field_validation_opt_text' );
		$received = array();

		add_filter(
			'acf/validate_value',
			function ( $valid, $value, $filter_field, $input ) use ( &$received ) {
				$received = array( $value, $filter_field['key'], $input );
				return $valid;
			},
			20,
			4
		);

		acf_validate_value( 'some value', $field, 'acf[field_validation_opt_text]' );

		$this->assertSame(
			array( 'some value', 'field_validation_opt_text', 'acf[field_validation_opt_text]' ),
			$received,
			'Filter should receive the value, field array and input name'
		);
	}

	/**
	 * Test a filter returning false marks the value invalid with the default message.
	 */
	public function test_validate_value_filter_can_invalidate() {
		$field = acf_get_field( 'field_validation_opt_text' );

		add_filter( 'acf/validate_value/key=field_validation_opt_text', '__return_false', 20 );

		$result = acf_validate_value( 'anything', $field, 'acf[field_validation_opt_text]' );

		$this->assertFalse( $result, 'A filter returning false should invalidate the value' );

		$error = acf_get_validation_error( 'acf[field_validation_opt_text]' );
		$this->assertSame( 'Optional Text value is required', $error['message'], 'The default required message should be used when no custom message is returned' );
	}

	/**
	 * Test a filter returning a string is treated as a custom error message.
	 */
	public function test_validate_value_filter_string_becomes_custom_error_message() {
		$field = acf_get_field( 'field_validation_opt_text' );

		add_filter(
			'acf/validate_value/key=field_validation_opt_text',
			function () {
				return 'Custom failure reason';
			},
			20
		);

		$result = acf_validate_value( 'anything', $field, 'acf[field_validation_opt_text]' );

		$this->assertFalse( $result, 'A string returned from the filter should invalidate the value' );

		$error = acf_get_validation_error( 'acf[field_validation_opt_text]' );
		$this->assertSame( 'Custom failure reason', $error['message'], 'The returned string should be used as the error message' );
	}

	// =========================================================================
	// Number field type validate_value filter.
	// =========================================================================

	/**
	 * Test a number below the configured minimum is invalid.
	 */
	public function test_number_value_below_min_is_invalid() {
		$field = acf_get_field( 'field_validation_number' );

		$result = acf_validate_value( '3', $field, 'acf[field_validation_number]' );

		$this->assertFalse( $result, 'A number below min should be invalid' );

		$error = acf_get_validation_error( 'acf[field_validation_number]' );
		$this->assertSame( 'Value must be equal to or higher than 5', $error['message'], 'Should report the min violation' );
	}

	/**
	 * Test a number above the configured maximum is invalid.
	 */
	public function test_number_value_above_max_is_invalid() {
		$field = acf_get_field( 'field_validation_number' );

		$result = acf_validate_value( '42', $field, 'acf[field_validation_number]' );

		$this->assertFalse( $result, 'A number above max should be invalid' );

		$error = acf_get_validation_error( 'acf[field_validation_number]' );
		$this->assertSame( 'Value must be equal to or lower than 10', $error['message'], 'Should report the max violation' );
	}

	/**
	 * Test a number within range is valid.
	 */
	public function test_number_value_within_range_is_valid() {
		$field = acf_get_field( 'field_validation_number' );

		$this->assertTrue( acf_validate_value( '7', $field, 'acf[field_validation_number]' ), 'A number within range should be valid' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded' );
	}

	/**
	 * Test a non-numeric value is rejected by the number field.
	 */
	public function test_number_non_numeric_value_is_invalid() {
		$field = acf_get_field( 'field_validation_number' );

		$result = acf_validate_value( 'not-a-number', $field, 'acf[field_validation_number]' );

		$this->assertFalse( $result, 'A non-numeric value should be invalid' );

		$error = acf_get_validation_error( 'acf[field_validation_number]' );
		$this->assertSame( 'Value must be a number', $error['message'], 'Should report the non-numeric value' );
	}

	/**
	 * Test an empty number value is allowed when the field is not required.
	 */
	public function test_number_empty_value_is_valid_when_not_required() {
		$field = acf_get_field( 'field_validation_number' );

		$this->assertTrue( acf_validate_value( '', $field, 'acf[field_validation_number]' ), 'Blank should be allowed on an optional number field' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded' );
	}

	// =========================================================================
	// Repeater field nested validation.
	// =========================================================================

	/**
	 * Test a required repeater with no rows is invalid.
	 */
	public function test_repeater_required_with_no_rows_is_invalid() {
		$field             = acf_get_field( 'field_validation_repeater' );
		$field['required'] = 1;

		$result = acf_validate_value( array(), $field, 'acf[field_validation_repeater]' );

		$this->assertFalse( $result, 'A required repeater without rows should be invalid' );
		$this->assertIsArray( acf_get_validation_error( 'acf[field_validation_repeater]' ), 'An error should be recorded against the repeater input' );
	}

	/**
	 * Test the repeater min rows custom error message.
	 */
	public function test_repeater_min_rows_not_reached_produces_custom_message() {
		$field        = acf_get_field( 'field_validation_repeater' );
		$field['min'] = 2;

		$value = array(
			'row-0' => array( 'field_validation_rep_sub' => 'filled' ),
		);

		$result = acf_validate_value( $value, $field, 'acf[field_validation_repeater]' );

		$this->assertFalse( $result, 'A repeater below min rows should be invalid' );

		$error = acf_get_validation_error( 'acf[field_validation_repeater]' );
		$this->assertSame( 'Minimum rows not reached (2 rows)', $error['message'], 'Should use the min rows message with the row count' );
	}

	/**
	 * Test an empty required sub-field inside a repeater row adds a nested error.
	 */
	public function test_repeater_required_sub_field_empty_adds_nested_error() {
		$field = acf_get_field( 'field_validation_repeater' );

		$value = array(
			'row-0' => array( 'field_validation_rep_sub' => '' ),
		);

		$result = acf_validate_value( $value, $field, 'acf[field_validation_repeater]' );

		// The repeater itself reports valid (sub-field errors do not bubble up
		// to the repeater's return value); each sub-field error is recorded
		// separately with a nested input name instead.
		$this->assertTrue( $result, 'The repeater itself returns valid; errors are recorded per sub-field' );

		$error = acf_get_validation_error( 'acf[field_validation_repeater][row-0][field_validation_rep_sub]' );
		$this->assertIsArray( $error, 'The sub-field error should be recorded with a nested input name' );
		$this->assertSame( 'Repeater Sub Text value is required', $error['message'], 'Should use the sub-field label in the message' );
	}

	/**
	 * Test repeater rows with filled required sub-fields pass.
	 */
	public function test_repeater_valid_rows_pass() {
		$field = acf_get_field( 'field_validation_repeater' );

		$value = array(
			'row-0' => array( 'field_validation_rep_sub' => 'first' ),
			'row-1' => array( 'field_validation_rep_sub' => 'second' ),
		);

		$this->assertTrue( acf_validate_value( $value, $field, 'acf[field_validation_repeater]' ), 'Filled rows should be valid' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded' );
	}

	// =========================================================================
	// Group field nested validation.
	// =========================================================================

	/**
	 * Test an empty required sub-field inside a group adds a nested error.
	 */
	public function test_group_required_sub_field_empty_adds_nested_error() {
		$field = acf_get_field( 'field_validation_group' );

		$value = array( 'field_validation_group_sub' => '' );

		acf_validate_value( $value, $field, 'acf[field_validation_group]' );

		$error = acf_get_validation_error( 'acf[field_validation_group][field_validation_group_sub]' );
		$this->assertIsArray( $error, 'The group sub-field error should be recorded with a nested input name' );
		$this->assertSame( 'Group Sub Text value is required', $error['message'], 'Should use the sub-field label in the message' );
	}

	/**
	 * Test a filled group sub-field passes validation.
	 */
	public function test_group_filled_sub_field_passes() {
		$field = acf_get_field( 'field_validation_group' );

		$value = array( 'field_validation_group_sub' => 'filled' );

		$this->assertTrue( acf_validate_value( $value, $field, 'acf[field_validation_group]' ), 'A filled group should be valid' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded' );
	}

	// =========================================================================
	// acf_validate_save_post() with simulated $_POST data.
	// =========================================================================

	/**
	 * Test acf_validate_save_post() returns true when no $_POST['acf'] data exists.
	 */
	public function test_validate_save_post_returns_true_without_post_data() {
		$this->assertTrue( acf_validate_save_post(), 'Should be valid without form data' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded' );
	}

	/**
	 * Test an empty required text field submitted via $_POST fails validation.
	 */
	public function test_validate_save_post_fails_for_empty_required_field() {
		$_POST['acf'] = array(
			'field_validation_req_text' => '',
		);

		$this->assertFalse( acf_validate_save_post(), 'Empty required field should fail validation' );

		$error = acf_get_validation_error( 'acf[field_validation_req_text]' );
		$this->assertIsArray( $error, 'Error should use the form input name prefix' );
		$this->assertSame( 'Required Text value is required', $error['message'], 'Should use the required message' );
	}

	/**
	 * Test a filled required text field submitted via $_POST passes validation.
	 */
	public function test_validate_save_post_passes_for_filled_required_field() {
		$_POST['acf'] = array(
			'field_validation_req_text' => 'filled value',
		);

		$this->assertTrue( acf_validate_save_post(), 'Filled required field should pass validation' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded' );
	}

	/**
	 * Test number min/max violations submitted via $_POST fail validation.
	 */
	public function test_validate_save_post_number_min_violation() {
		$_POST['acf'] = array(
			'field_validation_number' => '2',
		);

		$this->assertFalse( acf_validate_save_post(), 'Number below min should fail validation' );

		$error = acf_get_validation_error( 'acf[field_validation_number]' );
		$this->assertSame( 'Value must be equal to or higher than 5', $error['message'], 'Should report the min violation' );
	}

	/**
	 * Test number max violation submitted via $_POST fails validation.
	 */
	public function test_validate_save_post_number_max_violation() {
		$_POST['acf'] = array(
			'field_validation_number' => '100',
		);

		$this->assertFalse( acf_validate_save_post(), 'Number above max should fail validation' );

		$error = acf_get_validation_error( 'acf[field_validation_number]' );
		$this->assertSame( 'Value must be equal to or lower than 10', $error['message'], 'Should report the max violation' );
	}

	/**
	 * Test a repeater with an empty required sub-field submitted via $_POST fails validation.
	 */
	public function test_validate_save_post_repeater_required_sub_field() {
		$_POST['acf'] = array(
			'field_validation_repeater' => array(
				'row-0' => array( 'field_validation_rep_sub' => '' ),
			),
		);

		$this->assertFalse( acf_validate_save_post(), 'Repeater with empty required sub-field should fail validation' );

		$error = acf_get_validation_error( 'acf[field_validation_repeater][row-0][field_validation_rep_sub]' );
		$this->assertIsArray( $error, 'The nested sub-field input name should carry the error' );
	}

	/**
	 * Test unknown field keys in $_POST are skipped without errors.
	 */
	public function test_validate_save_post_skips_unknown_field_keys() {
		$_POST['acf'] = array(
			'field_does_not_exist' => '',
		);

		$this->assertTrue( acf_validate_save_post(), 'Unknown field keys should be skipped' );
		$this->assertFalse( acf_get_validation_errors(), 'No errors should be recorded for unknown keys' );
	}

	/**
	 * Test multiple invalid fields all collect errors in submission order.
	 */
	public function test_validate_save_post_collects_multiple_errors() {
		$_POST['acf'] = array(
			'field_validation_req_text' => '',
			'field_validation_number'   => '3',
			'field_validation_repeater' => array(
				'row-0' => array( 'field_validation_rep_sub' => '' ),
			),
		);

		$this->assertFalse( acf_validate_save_post(), 'All invalid fields should fail validation' );

		$errors = acf_get_validation_errors();
		$this->assertCount( 3, $errors, 'Each invalid field should add one error' );
		$this->assertSame( 'acf[field_validation_req_text]', $errors[0]['input'], 'Errors should follow submission order' );
		$this->assertSame( 'acf[field_validation_number]', $errors[1]['input'], 'Number error should be second' );
		$this->assertSame( 'acf[field_validation_repeater][row-0][field_validation_rep_sub]', $errors[2]['input'], 'Repeater sub-field error should be third' );
	}

	/**
	 * Test the acf/validate_save_post action fires and can add custom errors.
	 */
	public function test_validate_save_post_fires_validate_save_post_action() {
		add_action(
			'acf/validate_save_post',
			function () {
				acf_add_validation_error( 'acf[custom_input]', 'Custom action error' );
			}
		);

		$this->assertFalse( acf_validate_save_post(), 'Errors added by the action should fail validation' );

		$error = acf_get_validation_error( 'acf[custom_input]' );
		$this->assertSame( 'Custom action error', $error['message'], 'The custom error should be recorded' );
	}

	/**
	 * Test acf_validate_save_post( true ) calls wp_die() when errors exist.
	 */
	public function test_validate_save_post_show_errors_calls_wp_die() {
		add_filter(
			'wp_die_handler',
			function () {
				return function ( $message, $title = '' ) {
					throw new RuntimeException( 'wp_die: ' . esc_html( $title ) );
				};
			}
		);

		$_POST['acf'] = array(
			'field_validation_req_text' => '',
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Validation failed' );

		acf_validate_save_post( true );
	}
}
