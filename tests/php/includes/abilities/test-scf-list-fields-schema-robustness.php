<?php
/**
 * Schema robustness repro for the scf/list-fields ability output
 *
 * The scf/list-fields output_schema is `{ type: array, items: { oneOf: [...] } }`
 * with strict variants for built-in field types and a permissive fallback for
 * extension field types. Local fields legitimately use `ID => 0`, while
 * database-backed fields use positive post IDs.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load mock Abilities API functions before loading the class.
require_once __DIR__ . '/abilities-api-mocks.php';

// Load SCF_Field_Manager adapter class.
require_once dirname( __DIR__, 4 ) . '/includes/post-types/class-scf-field-manager.php';

// Load abilities class for testing.
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-field-abilities.php';

/**
 * Tests validating real scf/list-fields output against the registered output schema
 */
class Test_SCF_List_Fields_Schema_Robustness extends BaseTestCase {

	/**
	 * Instance of SCF_Field_Abilities for testing
	 *
	 * @var SCF_Field_Abilities
	 */
	private $abilities;

	/**
	 * Key of the local (code-registered) field group created in setUp
	 *
	 * @var string
	 */
	private $local_group_key;

	/**
	 * Key of the local valid text field
	 *
	 * @var string
	 */
	private $local_text_key;

	/**
	 * Key of the local field stored with an unknown type
	 *
	 * @var string
	 */
	private $local_drifted_key;

	/**
	 * Database-backed valid text field in its runtime shape (real post ID)
	 *
	 * @var array
	 */
	private $db_text_field;

	/**
	 * IDs of posts created during the test, deleted in tearDown
	 *
	 * @var array
	 */
	private $cleanup_post_ids = array();

	/**
	 * Setup test fixtures.
	 *
	 * Two sources of fields are used:
	 *
	 * - A local (code-registered) field group holding a valid text field and a
	 *   field with an unknown type. Local fields flow through the real, unmocked
	 *   list_callback aggregation (acf_get_field_groups + acf_get_fields), and
	 *   an unknown type is exactly what a field from a third-party or removed
	 *   field type plugin looks like.
	 * - A database-backed text field, so a field with a real post ID (>= 1) is
	 *   available for the schema comparisons. (Database-stored field settings
	 *   are serialized into post_content, which does not survive a WorDBless
	 *   round-trip, so the unknown type itself must live in the local group.)
	 */
	public function setUp(): void {
		parent::setUp();

		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();

		// Use a fresh instance so manager mocks injected into the shared
		// acf_get_instance() singleton by other test files cannot leak in.
		$this->abilities = new SCF_Field_Abilities();

		$suffix                  = uniqid();
		$this->local_group_key   = 'group_robustness_' . $suffix;
		$this->local_text_key    = 'field_robust_text_' . $suffix;
		$this->local_drifted_key = 'field_robust_drift_' . $suffix;

		acf_add_local_field_group(
			array(
				'key'    => $this->local_group_key,
				'title'  => 'Schema Robustness Group',
				'fields' => array(
					array(
						'key'   => $this->local_text_key,
						'label' => 'Valid Text Field',
						'name'  => 'robust_valid_text_' . $suffix,
						'type'  => 'text',
					),
					array(
						'key'   => $this->local_drifted_key,
						'label' => 'Drifted Field',
						'name'  => 'robust_drifted_' . $suffix,
						'type'  => 'phpunit_unknown_type',
					),
				),
			)
		);

		// Database-backed group and text field for an item with a real ID.
		$field_group = acf_update_field_group(
			array(
				'key'    => 'group_robustness_db_' . $suffix,
				'title'  => 'Schema Robustness DB Group',
				'active' => true,
			)
		);

		$db_field = acf_update_field(
			array(
				'key'    => 'field_robust_db_' . $suffix,
				'label'  => 'DB Text Field',
				'name'   => 'robust_db_text_' . $suffix,
				'type'   => 'text',
				'parent' => $field_group['ID'],
			)
		);

		$this->cleanup_post_ids[] = $field_group['ID'];
		$this->cleanup_post_ids[] = $db_field['ID'];

		acf_get_store( 'fields' )->reset();
		$this->db_text_field = $this->load_runtime_field( $db_field['ID'] );
	}

	/**
	 * Teardown test fixtures
	 */
	public function tearDown(): void {
		acf_remove_local_field( $this->local_text_key );
		acf_remove_local_field( $this->local_drifted_key );
		acf_remove_local_field_group( $this->local_group_key );

		foreach ( $this->cleanup_post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->cleanup_post_ids = array();

		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		$_POST = array();

		parent::tearDown();
	}

	/**
	 * Loads a database-stored field in its runtime shape.
	 *
	 * WorDBless does not round-trip serialized post_content, so
	 * acf_get_raw_field()'s `(array) maybe_unserialize(...)` leaves a spurious
	 * `0 => false` entry that real WordPress never produces. Strip it so the
	 * fixture matches the shape a real site's list callback returns.
	 *
	 * @param int $field_id The field post ID.
	 * @return array The field as the list callback would return it.
	 */
	private function load_runtime_field( $field_id ) {
		$field = acf_get_field( $field_id );

		$this->assertIsArray( $field, 'Stored field should be loadable' );
		unset( $field[0] );

		return $field;
	}

	/**
	 * Gets the output_schema registered for the scf/list-fields ability.
	 *
	 * @return array
	 */
	private function get_list_fields_output_schema() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		$this->abilities->register_abilities();

		$this->assertArrayHasKey( 'scf/list-fields', $mock_registered_abilities );

		return $mock_registered_abilities['scf/list-fields']['output_schema'];
	}

	/**
	 * Builds the list ability output for a given set of stored fields.
	 *
	 * Only get_posts() is stubbed (its acf_get_field_groups() aggregation cannot
	 * discover database groups under WorDBless); filter_posts() and the ability
	 * callback itself stay real.
	 *
	 * @param array $fields The stored fields the manager would aggregate.
	 * @return array The list_callback output.
	 */
	private function build_list_output( array $fields ) {
		$manager = $this->getMockBuilder( SCF_Field_Manager::class )
			->onlyMethods( array( 'get_posts' ) )
			->getMock();
		$manager->method( 'get_posts' )->willReturn( $fields );

		$reflection = new ReflectionClass( SCF_Field_Abilities::class );
		$property   = $reflection->getProperty( 'manager' );
		$property->setAccessible( true );
		$property->setValue( $this->abilities, $manager );

		return $this->abilities->list_callback( array() );
	}

	/**
	 * Validates data against a schema with the same JSON Schema library the
	 * in-repo validator (SCF_JSON_Schema_Validator) uses.
	 *
	 * @param mixed $data   The data to validate.
	 * @param array $schema The schema (associative array form).
	 * @return JsonSchema\Validator The validator after running validation.
	 */
	private function validate_against_schema( $data, array $schema ) {
		$validator = new JsonSchema\Validator();
		$validator->validate(
			json_decode( wp_json_encode( $data ) ),
			json_decode( wp_json_encode( $schema ) )
		);
		return $validator;
	}

	/**
	 * Sanity check: a listing of well-formed database-backed fields satisfies
	 * the output schema.
	 */
	public function test_list_output_with_only_valid_db_fields_passes_output_schema() {
		$schema = $this->get_list_fields_output_schema();
		$output = $this->build_list_output( array( $this->db_text_field ) );

		$this->assertCount( 1, $output );

		$validator = $this->validate_against_schema( $output, $schema );

		$this->assertTrue(
			$validator->isValid(),
			'A listing of valid fields should pass the output schema. Errors: '
				. wp_json_encode( $validator->getErrors() )
		);
	}

	/**
	 * A field stored with an unknown type matches the fallback item schema.
	 */
	public function test_unknown_field_alone_passes_item_schema() {
		$schema      = $this->get_list_fields_output_schema();
		$item_schema = $schema['items'];

		$drifted_field = acf_get_field( $this->local_drifted_key );
		$this->assertEquals( 'phpunit_unknown_type', $drifted_field['type'] );

		$validator = $this->validate_against_schema( $drifted_field, $item_schema );

		$this->assertTrue(
			$validator->isValid(),
			'A field with an unknown stored type should match the fallback variant. Errors: '
				. wp_json_encode( $validator->getErrors() )
		);
	}

	/**
	 * A third-party field type does not invalidate the complete list output.
	 */
	public function test_unknown_field_type_keeps_entire_list_output_valid() {
		$schema        = $this->get_list_fields_output_schema();
		$drifted_field = acf_get_field( $this->local_drifted_key );
		$output        = $this->build_list_output( array( $this->db_text_field, $drifted_field ) );

		$this->assertCount( 2, $output, 'The callback itself happily returns both fields' );

		// The valid field on its own passes.
		$valid_only = $this->validate_against_schema( array( $this->db_text_field ), $schema );
		$this->assertTrue( $valid_only->isValid(), 'The valid field alone passes the output schema' );

		// The fallback keeps the combined listing valid as well.
		$combined = $this->validate_against_schema( $output, $schema );
		$this->assertTrue(
			$combined->isValid(),
			'A third-party field type should not invalidate the list output. Errors: '
				. wp_json_encode( $combined->getErrors() )
		);
	}

	/**
	 * Ordinary local (code-registered) fields pass with an ID of 0.
	 *
	 * This run uses the REAL, unmocked list_callback: local field groups are
	 * aggregated by the production code path. Local fields legitimately have
	 * `ID => 0` because they have no backing post. The field-specific internal
	 * properties schema accepts that value while database-backed entities retain
	 * the positive-ID constraint.
	 */
	public function test_local_text_field_passes_output_schema_with_id_zero() {
		$schema = $this->get_list_fields_output_schema();

		// Real list_callback, no mocks: aggregates the local field group.
		$output = $this->abilities->list_callback(
			array( 'filter' => array( 'name' => 'robust_valid_text_' . substr( $this->local_text_key, strlen( 'field_robust_text_' ) ) ) )
		);

		$this->assertCount( 1, $output, 'The real list callback should return the local text field' );
		$this->assertEquals( 'text', $output[0]['type'] );
		$this->assertSame( 0, $output[0]['ID'], 'Local fields have no backing post, so ID is 0' );

		$validator = $this->validate_against_schema( $output, $schema );

		$this->assertTrue(
			$validator->isValid(),
			'A listing containing a local field should pass the output schema. Errors: '
				. wp_json_encode( $validator->getErrors() )
		);
	}
}
