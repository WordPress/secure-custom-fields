<?php
/**
 * Tests for acf_field_clone class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Ensure the clone field class is loaded.
acf_include( 'includes/fields/class-acf-field-clone.php' );

/**
 * Test acf_field_clone functionality.
 */
class Test_ACF_Field_Clone extends BaseTestCase {

	/**
	 * Clone field instance.
	 *
	 * @var acf_field_clone
	 */
	private $clone_field;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a new instance of the clone field.
		$this->clone_field = new acf_field_clone();
	}

	/**
	 * Test acf_get_fields filter handles valid fields array.
	 */
	public function test_acf_get_fields_handles_valid_fields() {
		$fields = array(
			array(
				'key'   => 'field_123',
				'name'  => 'text_field',
				'type'  => 'text',
				'label' => 'Text Field',
			),
			array(
				'key'   => 'field_456',
				'name'  => 'number_field',
				'type'  => 'number',
				'label' => 'Number Field',
			),
		);

		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( 'text', $result[0]['type'] );
		$this->assertEquals( 'number', $result[1]['type'] );
	}

	/**
	 * Test acf_get_fields filter handles invalid entries without PHP warnings.
	 *
	 * This tests the fix for PHP warnings reported at:
	 * https://wordpress.org/support/topic/php-warnings-207/
	 *
	 * - "Trying to access array offset on null"
	 * - "Undefined array key"
	 */
	public function test_acf_get_fields_handles_invalid_entries() {
		$fields = array(
			array(
				'key'   => 'field_123',
				'name'  => 'text_field',
				'type'  => 'text',
				'label' => 'Text Field',
			),
			null,             // Invalid null entry.
			'invalid_string', // Invalid string entry.
			false,            // Invalid boolean entry.
			42,               // Invalid integer entry.
			array(
				'key'   => 'field_456',
				'name'  => 'number_field',
				'type'  => 'number',
				'label' => 'Number Field',
			),
		);

		// This should not trigger PHP warnings.
		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertIsArray( $result );
	}

	/**
	 * Test acf_get_fields filter processes seamless clone fields correctly.
	 */
	public function test_acf_get_fields_expands_seamless_clone_fields() {
		$fields = array(
			array(
				'key'        => 'field_clone_1',
				'name'       => 'my_clone',
				'type'       => 'clone',
				'label'      => 'Clone Field',
				'display'    => 'seamless',
				'sub_fields' => array(
					array(
						'key'   => 'field_sub_1',
						'name'  => 'sub_text',
						'type'  => 'text',
						'label' => 'Sub Text',
					),
					array(
						'key'   => 'field_sub_2',
						'name'  => 'sub_number',
						'type'  => 'number',
						'label' => 'Sub Number',
					),
				),
			),
		);

		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertIsArray( $result );
		// Seamless clone should be replaced with its sub_fields.
		$this->assertCount( 2, $result );
		$this->assertEquals( 'text', $result[0]['type'] );
		$this->assertEquals( 'number', $result[1]['type'] );
	}

	/**
	 * Test acf_get_fields filter does not expand group display clone fields.
	 */
	public function test_acf_get_fields_preserves_group_display_clone_fields() {
		$fields = array(
			array(
				'key'        => 'field_clone_1',
				'name'       => 'my_clone',
				'type'       => 'clone',
				'label'      => 'Clone Field',
				'display'    => 'group', // Not seamless.
				'sub_fields' => array(
					array(
						'key'   => 'field_sub_1',
						'name'  => 'sub_text',
						'type'  => 'text',
						'label' => 'Sub Text',
					),
				),
			),
		);

		$result = $this->clone_field->acf_get_fields( $fields, array() );

		$this->assertIsArray( $result );
		// Group display clone should NOT be replaced.
		$this->assertCount( 1, $result );
		$this->assertEquals( 'clone', $result[0]['type'] );
	}
}
