<?php
/**
 * Integration tests for ACF_Internal_Post_Type and subclasses.
 *
 * Tests the internal post type functionality with real objects and database state.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

acf_include( '/includes/class-acf-internal-post-type.php' );
acf_include( '/includes/post-types/class-acf-post-type.php' );
acf_include( '/includes/post-types/class-acf-field-group.php' );

/**
 * Test ACF_Internal_Post_Type functionality.
 */
class Test_ACF_Internal_Post_Type extends BaseTestCase {

	/**
	 * Field group manager instance.
	 *
	 * @var ACF_Field_Group
	 */
	private $field_group;

	/**
	 * Test field group post ID.
	 *
	 * @var int
	 */
	private $field_group_id;

	/**
	 * Post type manager instance.
	 *
	 * @var ACF_Post_Type
	 */
	private $post_type;

	/**
	 * Test post type post ID.
	 *
	 * @var int
	 */
	private $post_type_id;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->field_group = new ACF_Field_Group();
		$this->post_type   = new ACF_Post_Type();

		// Create a real field group post in the database.
		$this->field_group_id = wp_insert_post(
			array(
				'post_type'    => 'acf-field-group',
				'post_title'   => 'Test Field Group',
				'post_status'  => 'publish',
				'post_content' => wp_json_encode(
					array(
						'fields'   => array(),
						'location' => array(),
					)
				),
			)
		);

		// Set required ACF post meta.
		update_post_meta( $this->field_group_id, 'acf-key', 'group_test_' . uniqid() );

		// Create a real post type post in the database.
		$this->post_type_id = wp_insert_post(
			array(
				'post_type'    => 'acf-post-type',
				'post_title'   => 'Test Post Type',
				'post_status'  => 'publish',
				'post_content' => wp_json_encode( array() ),
			)
		);

		// Set required ACF post meta.
		update_post_meta( $this->post_type_id, 'acf-key', 'post_type_test_' . uniqid() );
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		if ( $this->field_group_id ) {
			wp_delete_post( $this->field_group_id, true );
		}
		if ( $this->post_type_id ) {
			wp_delete_post( $this->post_type_id, true );
		}
		parent::tearDown();
	}

	/**
	 * Test field group duplication generates correct key prefix.
	 */
	public function test_duplicate_post_generates_group_prefix_key() {
		$duplicated = $this->field_group->duplicate_post( $this->field_group_id );

		$this->assertIsArray( $duplicated, 'duplicate_post should return an array' );
		$this->assertArrayHasKey( 'key', $duplicated, 'Duplicated post should have key' );
		$this->assertStringStartsWith(
			'group_',
			$duplicated['key'],
			'Duplicated field group key should start with group_ prefix'
		);
	}

	/**
	 * Test duplicated field group title contains (copy).
	 */
	public function test_duplicate_post_appends_copy_to_title() {
		$duplicated = $this->field_group->duplicate_post( $this->field_group_id );

		$this->assertStringContainsString(
			'(copy)',
			$duplicated['title'],
			'Duplicated field group title should contain (copy)'
		);
	}

	/**
	 * Test duplicate generates unique keys each time.
	 */
	public function test_duplicate_post_generates_unique_keys() {
		$duplicate1 = $this->field_group->duplicate_post( $this->field_group_id );
		$duplicate2 = $this->field_group->duplicate_post( $this->field_group_id );

		$this->assertNotEquals(
			$duplicate1['key'],
			$duplicate2['key'],
			'Each duplicate should have a unique key'
		);

		// Both should still have group_ prefix
		$this->assertStringStartsWith( 'group_', $duplicate1['key'] );
		$this->assertStringStartsWith( 'group_', $duplicate2['key'] );
	}

	/**
	 * Test duplicate does not add (copy) when new_post_id is provided.
	 */
	public function test_duplicate_post_skips_copy_suffix_with_new_post_id() {
		$original       = $this->field_group->get_post( $this->field_group_id );
		$original_title = $original['title'];

		$duplicated = $this->field_group->duplicate_post( $this->field_group_id, 999 );

		$this->assertEquals(
			$original_title,
			$duplicated['title'],
			'Title should not have (copy) appended when new_post_id is provided'
		);
	}

	/**
	 * Test duplicate returns false for non-existent post.
	 */
	public function test_duplicate_post_returns_false_for_invalid_post() {
		$result = $this->field_group->duplicate_post( 99999 );

		$this->assertFalse( $result, 'duplicate_post should return false for non-existent post' );
	}

	/**
	 * Test post type duplication uses correct prefix (not group_).
	 *
	 * Post types should use 'post_type_' prefix, not the 'group_' prefix used by field groups.
	 * See https://github.com/WordPress/secure-custom-fields/pull/240
	 */
	public function test_duplicate_post_type_uses_correct_prefix() {
		$duplicated = $this->post_type->duplicate_post( $this->post_type_id );

		$this->assertIsArray( $duplicated, 'duplicate_post should return an array' );
		$this->assertArrayHasKey( 'key', $duplicated, 'Duplicated post should have key' );
		$this->assertStringStartsWith(
			'post_type_',
			$duplicated['key'],
			'Duplicated post type key should start with post_type_ prefix'
		);
		$this->assertFalse(
			strpos( $duplicated['key'], 'group_' ) === 0,
			'Post type key should not start with group_ prefix'
		);
	}
}
