<?php
/**
 * Schema robustness repro for the scf/list-fields ability output
 *
 * The scf/list-fields output_schema is `{ type: array, items: { oneOf: [...] } }`
 * where every oneOf variant pins `type` to a known field type enum, sets
 * `additionalProperties: false`, and requires `ID >= 1`. JSON Schema array
 * validation is all-or-nothing: if ANY stored field drifts from its type schema
 * (unknown type, stray property, local field with ID 0), that item matches no
 * oneOf variant and the WHOLE array output is rejected by output-schema
 * validation.
 *
 * NOTE: documents current behavior — possible bug: one invalid field makes the
 * entire listing unusable.
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
	 * Sanity check: a field stored with an unknown type fails every oneOf
	 * type variant of the item schema.
	 */
	public function test_drifted_field_alone_fails_item_schema() {
		$schema      = $this->get_list_fields_output_schema();
		$item_schema = $schema['items'];

		$drifted_field = acf_get_field( $this->local_drifted_key );
		$this->assertEquals( 'phpunit_unknown_type', $drifted_field['type'] );

		$validator = $this->validate_against_schema( $drifted_field, $item_schema );

		$this->assertFalse(
			$validator->isValid(),
			'A field with an unknown stored type should match no oneOf variant'
		);
	}

	/**
	 * Repro: one drifted field invalidates the ENTIRE list-fields output.
	 *
	 * The list output schema is `array` of `oneOf` variants, each pinned to a
	 * known type enum with additionalProperties: false. Because array schema
	 * validation rejects the whole array when any single item fails, a single
	 * stored field that drifted from its type schema (here: an unknown field
	 * type, e.g. from a third-party or removed field type plugin) makes
	 * output-schema validation fail for the complete listing - including all
	 * perfectly valid fields in it.
	 *
	 * NOTE: documents current behavior - possible bug: one invalid field makes
	 * the entire listing unusable. A consumer validating scf/list-fields output
	 * against its registered output_schema (as the WordPress Abilities API does
	 * for ability results) receives a validation error instead of the valid
	 * fields.
	 */
	public function test_single_drifted_field_invalidates_entire_list_output() {
		$schema        = $this->get_list_fields_output_schema();
		$drifted_field = acf_get_field( $this->local_drifted_key );
		$output        = $this->build_list_output( array( $this->db_text_field, $drifted_field ) );

		$this->assertCount( 2, $output, 'The callback itself happily returns both fields' );

		// The valid field on its own passes...
		$valid_only = $this->validate_against_schema( array( $this->db_text_field ), $schema );
		$this->assertTrue( $valid_only->isValid(), 'The valid field alone passes the output schema' );

		// ...but the combined listing is rejected wholesale.
		$combined = $this->validate_against_schema( $output, $schema );
		$this->assertFalse(
			$combined->isValid(),
			'One drifted field should (currently) cause the whole list output to fail validation'
		);

		// The failure is reported against the drifted item, confirming the
		// rejection is caused by the single bad field poisoning the array.
		$error_properties    = wp_list_pluck( $combined->getErrors(), 'property' );
		$drifted_item_errors = array_filter(
			$error_properties,
			function ( $property ) {
				return 0 === strpos( (string) $property, '[1]' );
			}
		);
		$this->assertNotEmpty(
			$drifted_item_errors,
			'Validation errors should point at the drifted item ([1]). Errors: '
				. wp_json_encode( $combined->getErrors() )
		);
	}

	/**
	 * Repro: ordinary local (code-registered) fields fail the output schema
	 * because their ID is 0.
	 *
	 * This run uses the REAL, unmocked list_callback: local field groups are
	 * aggregated by the production code path. Local fields legitimately have
	 * `ID => 0` (they have no backing post), but the internal-properties schema
	 * merged into every oneOf variant requires `ID` with `minimum: 1`, so a
	 * perfectly ordinary code-registered text field - the most common kind of
	 * field on real sites - fails the item schema, and with it the whole list.
	 *
	 * NOTE: documents current behavior - possible bug: any site registering
	 * fields in code (acf_add_local_field_group / local JSON) produces
	 * scf/list-fields output that fails its own output_schema validation.
	 */
	public function test_local_text_field_fails_output_schema_due_to_id_zero() {
		$schema = $this->get_list_fields_output_schema();

		// Real list_callback, no mocks: aggregates the local field group.
		$output = $this->abilities->list_callback(
			array( 'filter' => array( 'name' => 'robust_valid_text_' . substr( $this->local_text_key, strlen( 'field_robust_text_' ) ) ) )
		);

		$this->assertCount( 1, $output, 'The real list callback should return the local text field' );
		$this->assertEquals( 'text', $output[0]['type'] );
		$this->assertSame( 0, $output[0]['ID'], 'Local fields have no backing post, so ID is 0' );

		$validator = $this->validate_against_schema( $output, $schema );

		$this->assertFalse(
			$validator->isValid(),
			'A listing containing a local field should (currently) fail the output schema'
		);

		// Confirm the ID minimum constraint is among the failures.
		$minimum_errors = array_filter(
			$validator->getErrors(),
			function ( $error ) {
				return 'minimum' === $error['constraint'] && '[0].ID' === $error['property'];
			}
		);
		$this->assertNotEmpty(
			$minimum_errors,
			'Validation should fail on the ID >= 1 requirement. Errors: '
				. wp_json_encode( $validator->getErrors() )
		);
	}
}
