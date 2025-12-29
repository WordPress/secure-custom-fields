<?php
/**
 * Tests for ACF Form Functions.
 *
 * Tests functions from includes/acf-form-functions.php including:
 * - Form data storage and retrieval
 * - Form data HTML rendering
 * - Post save operations
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_ACF_Form_Functions
 *
 * Tests for ACF form handling functions.
 */
class Test_ACF_Form_Functions extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		acf_get_store( 'form' )->reset();
		$_POST = array();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		acf_get_store( 'form' )->reset();
		$_POST = array();
		parent::tear_down();
	}

	// =========================================================================
	// acf_set_form_data() / acf_get_form_data() Tests
	// =========================================================================

	/**
	 * Test form data storage and retrieval.
	 */
	public function test_acf_form_data_storage() {
		// Store and retrieve single value.
		acf_set_form_data( 'post_id', 123 );
		$this->assertEquals( 123, acf_get_form_data( 'post_id' ) );

		// Store multiple values.
		acf_set_form_data( 'screen', 'post' );
		acf_set_form_data( 'validation', true );
		$this->assertEquals( 'post', acf_get_form_data( 'screen' ) );
		$this->assertTrue( acf_get_form_data( 'validation' ) );

		// Missing key returns null.
		$this->assertNull( acf_get_form_data( 'nonexistent_key' ) );

		// Can store false values.
		acf_set_form_data( 'validation', false );
		$this->assertFalse( acf_get_form_data( 'validation' ) );

		// Can store arrays.
		$data = array(
			'screen'  => 'post',
			'post_id' => 456,
		);
		acf_set_form_data( 'settings', $data );
		$this->assertEquals( $data, acf_get_form_data( 'settings' ) );

		// Overwrites existing values.
		acf_set_form_data( 'post_id', 789 );
		$this->assertEquals( 789, acf_get_form_data( 'post_id' ) );
	}

	// =========================================================================
	// acf_form_data() Tests
	// =========================================================================

	/**
	 * Test that acf_form_data outputs expected HTML structure.
	 */
	public function test_acf_form_data_html_output() {
		ob_start();
		acf_form_data(
			array(
				'screen'  => 'post',
				'post_id' => 123,
			)
		);
		$output = ob_get_clean();

		// Container.
		$this->assertStringContainsString( '<div id="acf-form-data"', $output );
		$this->assertStringContainsString( 'class="acf-hidden"', $output );

		// Hidden inputs.
		$this->assertStringContainsString( 'type="hidden"', $output );
		$this->assertStringContainsString( 'name="_acf_screen"', $output );
		$this->assertStringContainsString( 'value="post"', $output );
		$this->assertStringContainsString( 'name="_acf_post_id"', $output );
		$this->assertStringContainsString( 'value="123"', $output );
	}

	/**
	 * Test that acf_form_data applies default values.
	 */
	public function test_acf_form_data_defaults() {
		ob_start();
		acf_form_data( array() );
		$output = ob_get_clean();

		// Default screen is 'post'.
		$this->assertStringContainsString( 'name="_acf_screen"', $output );
		$this->assertMatchesRegularExpression( '/name="_acf_screen"[^>]*value="post"/', $output );

		// Default post_id is 0.
		$this->assertStringContainsString( 'name="_acf_post_id"', $output );

		// Default validation is true (1).
		$this->assertStringContainsString( 'name="_acf_validation"', $output );

		// Nonce is generated.
		$this->assertStringContainsString( 'name="_acf_nonce"', $output );
		$this->assertMatchesRegularExpression( '/name="_acf_nonce"[^>]*value="[a-f0-9]+"/', $output );

		// Changed input included.
		$this->assertStringContainsString( 'name="_acf_changed"', $output );
	}

	/**
	 * Test that acf_form_data sets store and fires actions.
	 */
	public function test_acf_form_data_side_effects() {
		$action_called       = false;
		$input_action_called = false;

		add_action(
			'acf/form_data',
			function () use ( &$action_called ) {
				$action_called = true;
			}
		);

		add_action(
			'acf/input/form_data',
			function () use ( &$input_action_called ) {
				$input_action_called = true;
			}
		);

		ob_start();
		acf_form_data(
			array(
				'screen'  => 'user',
				'post_id' => 789,
			)
		);
		ob_get_clean();

		// Store is set.
		$this->assertEquals( 'user', acf_get_form_data( 'screen' ) );

		// Actions fired.
		$this->assertTrue( $action_called, 'acf/form_data action should be called' );
		$this->assertTrue( $input_action_called, 'acf/input/form_data action should be called' );
	}

	// =========================================================================
	// acf_save_post() Tests
	// =========================================================================

	/**
	 * Test acf_save_post return values and basic behavior.
	 */
	public function test_acf_save_post_basic() {
		// Returns false without ACF data.
		$this->assertFalse( acf_save_post( 123 ) );

		// Returns false with empty ACF array.
		$_POST['acf'] = array();
		$this->assertFalse( acf_save_post( 123 ) );

		// Returns true with ACF data.
		$_POST['acf'] = array( 'field_abc123' => 'test value' );
		$this->assertTrue( acf_save_post( 123 ) );
	}

	/**
	 * Test acf_save_post accepts values parameter override.
	 */
	public function test_acf_save_post_values_parameter() {
		$values          = array( 'field_test' => 'override value' );
		$received_values = null;

		add_action(
			'acf/save_post',
			function () use ( &$received_values ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Test intentionally reads raw $_POST to verify function behavior.
				$received_values = isset( $_POST['acf'] ) ? $_POST['acf'] : null;
			}
		);

		$result = acf_save_post( 456, $values );

		$this->assertTrue( $result );
		$this->assertEquals( $values, $received_values );
	}

	/**
	 * Test acf_save_post sets form data and fires action.
	 */
	public function test_acf_save_post_side_effects() {
		$_POST['acf'] = array( 'field_test' => 'value' );

		$received_post_id = null;
		add_action(
			'acf/save_post',
			function ( $post_id ) use ( &$received_post_id ) {
				$received_post_id = $post_id;
			}
		);

		acf_save_post( 999 );

		// Form data is set.
		$this->assertEquals( 999, acf_get_form_data( 'post_id' ) );

		// Action receives correct post_id.
		$this->assertEquals( 999, $received_post_id );
	}

	/**
	 * Test acf_save_post handles various post_id formats.
	 *
	 * @dataProvider postIdFormatsProvider
	 *
	 * @param mixed $post_id Post ID to test.
	 */
	public function test_acf_save_post_post_id_formats( $post_id ) {
		$_POST['acf'] = array( 'field_test' => 'value' );

		$received_post_id = null;
		add_action(
			'acf/save_post',
			function ( $id ) use ( &$received_post_id ) {
				$received_post_id = $id;
			}
		);

		$result = acf_save_post( $post_id );

		$this->assertTrue( $result );
		$this->assertEquals( $post_id, $received_post_id );
	}

	/**
	 * Data provider for post ID formats.
	 */
	public function postIdFormatsProvider() {
		return array(
			'numeric' => array( 123 ),
			'user'    => array( 'user_5' ),
			'options' => array( 'options' ),
			'term'    => array( 'term_10' ),
		);
	}

	// =========================================================================
	// _acf_do_save_post() Tests
	// =========================================================================

	/**
	 * Test _acf_do_save_post handles missing data gracefully.
	 */
	public function test_acf_do_save_post_without_data() {
		$_POST = array();

		// Should not throw any errors.
		_acf_do_save_post( 123 );
		$this->assertTrue( true ); // No exception means success.
	}

	// =========================================================================
	// Integration Tests
	// =========================================================================

	/**
	 * Test complete form data flow from render to save.
	 */
	public function test_form_data_integration() {
		// Render form data.
		ob_start();
		acf_form_data(
			array(
				'screen'  => 'post',
				'post_id' => 123,
			)
		);
		ob_get_clean();

		// Verify form data was set.
		$this->assertEquals( 'post', acf_get_form_data( 'screen' ) );

		// Simulate form submission.
		$_POST['acf'] = array( 'field_test' => 'submitted value' );

		$saved_post_id = null;
		add_action(
			'acf/save_post',
			function ( $post_id ) use ( &$saved_post_id ) {
				$saved_post_id = $post_id;
			}
		);

		acf_save_post( 123 );

		$this->assertEquals( 123, $saved_post_id );
	}

	/**
	 * Test that form data store is properly isolated between tests.
	 */
	public function test_form_data_store_isolation() {
		acf_set_form_data( 'key1', 'value1' );
		acf_get_store( 'form' )->reset();
		$this->assertNull( acf_get_form_data( 'key1' ) );
	}
}
