<?php
/**
 * Tests for SCF_Field_Manager class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once dirname( __DIR__, 3 ) . '/includes/post-types/class-scf-field-manager.php';

/**
 * SCF_Field_Manager test case.
 */
class SCFFieldManagerTest extends BaseTestCase {

	/**
	 * Manager instance.
	 *
	 * @var SCF_Field_Manager
	 */
	private $manager;

	/**
	 * Sample fields.
	 *
	 * @var array
	 */
	private $sample_fields;

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->manager       = new SCF_Field_Manager();
		$this->sample_fields = array(
			array(
				'key'    => 'field_1',
				'name'   => 'text_field',
				'type'   => 'text',
				'parent' => 'group_123',
			),
			array(
				'key'    => 'field_2',
				'name'   => 'image_field',
				'type'   => 'image',
				'parent' => 'group_123',
			),
			array(
				'key'    => 'field_3',
				'name'   => 'another_text',
				'type'   => 'text',
				'parent' => 'group_456',
			),
			array(
				'key'    => 'field_4',
				'name'   => 'email_field',
				'type'   => 'email',
				'parent' => 'group_456',
			),
		);
	}

	/**
	 * Create a test field.
	 *
	 * @param string $key Field key suffix.
	 * @return array
	 */
	private function create_test_field( $key ) {
		return acf_update_field(
			array(
				'key'   => 'field_' . $key,
				'name'  => $key,
				'type'  => 'text',
				'label' => ucfirst( $key ),
			)
		);
	}

	/**
	 * Test filter_posts with no filters.
	 */
	public function test_filter_posts_no_filters() {
		$result = $this->manager->filter_posts( $this->sample_fields );
		$this->assertCount( 4, $result );
	}

	/**
	 * Test filter_posts by parent.
	 */
	public function test_filter_posts_by_parent() {
		$result = $this->manager->filter_posts( $this->sample_fields, array( 'parent' => 'group_123' ) );

		$this->assertCount( 2, $result );
		foreach ( $result as $field ) {
			$this->assertEquals( 'group_123', $field['parent'] );
		}
	}

	/**
	 * Test filter_posts by type.
	 */
	public function test_filter_posts_by_type() {
		$result = $this->manager->filter_posts( $this->sample_fields, array( 'type' => 'text' ) );

		$this->assertCount( 2, $result );
		foreach ( $result as $field ) {
			$this->assertEquals( 'text', $field['type'] );
		}
	}

	/**
	 * Test filter_posts by name.
	 */
	public function test_filter_posts_by_name() {
		$result = $this->manager->filter_posts( $this->sample_fields, array( 'name' => 'email_field' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'email_field', $result[0]['name'] );
	}

	/**
	 * Test filter_posts with multiple filters.
	 */
	public function test_filter_posts_multiple_filters() {
		$result = $this->manager->filter_posts(
			$this->sample_fields,
			array(
				'parent' => 'group_123',
				'type'   => 'text',
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( 'field_1', $result[0]['key'] );
	}

	/**
	 * Test filter_posts with no matches.
	 */
	public function test_filter_posts_no_matches() {
		$result = $this->manager->filter_posts( $this->sample_fields, array( 'type' => 'nonexistent' ) );
		$this->assertCount( 0, $result );
	}

	/**
	 * Test filter_posts reindexes keys.
	 */
	public function test_filter_posts_reindexes_keys() {
		$result = $this->manager->filter_posts( $this->sample_fields, array( 'parent' => 'group_456' ) );

		$this->assertArrayHasKey( 0, $result );
		$this->assertArrayHasKey( 1, $result );
		$this->assertArrayNotHasKey( 2, $result );
	}

	/**
	 * Test filter_posts with empty input.
	 */
	public function test_filter_posts_empty_input() {
		$result = $this->manager->filter_posts( array(), array( 'type' => 'text' ) );
		$this->assertCount( 0, $result );
	}

	/**
	 * Test post_type property.
	 */
	public function test_post_type_property() {
		$this->assertEquals( 'acf-field', $this->manager->post_type );
	}

	/**
	 * Test post_key_prefix property.
	 */
	public function test_post_key_prefix_property() {
		$this->assertEquals( 'field_', $this->manager->post_key_prefix );
	}

	/**
	 * Test duplicate_post returns false when field not found.
	 */
	public function test_duplicate_post_returns_false_when_field_not_found() {
		$this->assertFalse( $this->manager->duplicate_post( 'nonexistent_field' ) );
	}

	/**
	 * Test get_post calls acf_get_field.
	 */
	public function test_get_post_calls_acf_get_field() {
		$field       = $this->create_test_field( 'get_test' );
		$called_with = null;

		add_filter(
			'acf/load_field',
			function ( $f ) use ( &$called_with ) {
				$called_with = $f;
				return $f;
			}
		);

		$this->manager->get_post( $field['ID'] );
		$this->assertEquals( 'field_get_test', $called_with['key'] );
	}

	/**
	 * Test update_post calls acf_update_field.
	 */
	public function test_update_post_calls_acf_update_field() {
		$called_with = null;

		add_filter(
			'acf/update_field',
			function ( $field ) use ( &$called_with ) {
				$called_with = $field;
				return $field;
			}
		);

		$this->manager->update_post(
			array(
				'key'   => 'field_update_test',
				'name'  => 'update_test',
				'type'  => 'text',
				'label' => 'Update Test',
			)
		);

		$this->assertEquals( 'field_update_test', $called_with['key'] );
	}

	/**
	 * Test delete_post calls acf_delete_field.
	 */
	public function test_delete_post_calls_acf_delete_field() {
		$field  = $this->create_test_field( 'delete_test' );
		$called = false;

		add_action(
			'acf/delete_field',
			function () use ( &$called ) {
				$called = true;
			}
		);

		$this->manager->delete_post( $field['ID'] );
		$this->assertTrue( $called );
	}

	/**
	 * Test get_posts returns fields from all field groups.
	 */
	public function test_get_posts_returns_fields_from_field_groups() {
		acf_add_local_field_group(
			array(
				'key'    => 'group_local_test',
				'title'  => 'Local Test Group',
				'fields' => array(
					array(
						'key'   => 'field_local_1',
						'name'  => 'local_field_1',
						'type'  => 'text',
						'label' => 'Local Field 1',
					),
					array(
						'key'   => 'field_local_2',
						'name'  => 'local_field_2',
						'type'  => 'text',
						'label' => 'Local Field 2',
					),
				),
			)
		);

		$result = $this->manager->get_posts();

		$this->assertIsArray( $result );
		$keys = array_column( $result, 'key' );
		$this->assertContains( 'field_local_1', $keys );
		$this->assertContains( 'field_local_2', $keys );
	}

	/**
	 * Test duplicate_post duplicates a field.
	 */
	public function test_duplicate_post_duplicates_field() {
		$group = acf_update_field_group(
			array(
				'key'    => 'group_dup_test',
				'title'  => 'Dup Test Group',
				'fields' => array(),
			)
		);

		$field = acf_update_field(
			array(
				'key'    => 'field_to_dup',
				'name'   => 'to_dup',
				'type'   => 'text',
				'label'  => 'To Duplicate',
				'parent' => $group['key'],
			)
		);

		$duplicate = $this->manager->duplicate_post( $field['ID'] );

		$this->assertIsArray( $duplicate );
		$this->assertNotEquals( $field['key'], $duplicate['key'] );
		$this->assertEquals( 'to_dup', $duplicate['name'] );
	}

	/**
	 * Test prepare_post_for_export strips internal fields.
	 */
	public function test_prepare_post_for_export_strips_internal_fields() {
		$field = array(
			'key'    => 'field_export_test',
			'name'   => 'export_test',
			'type'   => 'text',
			'label'  => 'Export Test',
			'ID'     => 123,
			'local'  => true,
			'_valid' => 1,
			'_name'  => 'internal_name',
			'prefix' => 'acf',
			'value'  => 'some value',
			'id'     => 'acf-field_export_test',
			'class'  => 'acf-field',
		);

		$result = $this->manager->prepare_post_for_export( $field );

		$this->assertArrayNotHasKey( 'ID', $result );
		$this->assertArrayNotHasKey( 'local', $result );
		$this->assertArrayNotHasKey( '_valid', $result );
		$this->assertArrayHasKey( 'key', $result );
		$this->assertArrayHasKey( 'name', $result );
	}

	/**
	 * Test import_post imports a field.
	 */
	public function test_import_post_imports_field() {
		$group = acf_update_field_group(
			array(
				'key'    => 'group_import_test',
				'title'  => 'Import Test Group',
				'fields' => array(),
			)
		);

		$called = false;
		add_action(
			'acf/import_field',
			function () use ( &$called ) {
				$called = true;
			}
		);

		$result = $this->manager->import_post(
			array(
				'key'    => 'field_imported',
				'name'   => 'imported_field',
				'type'   => 'text',
				'label'  => 'Imported Field',
				'parent' => $group['key'],
			)
		);

		$this->assertTrue( $called );
		$this->assertIsArray( $result );
		$this->assertEquals( 'field_imported', $result['key'] );
	}
}
