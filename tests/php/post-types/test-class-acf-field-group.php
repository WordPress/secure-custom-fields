<?php
/**
 * Tests for ACF_Field_Group::filter_posts() and ignore_location_rules flag.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

acf_include( '/includes/class-acf-internal-post-type.php' );
acf_include( '/includes/post-types/class-acf-field-group.php' );

/**
 * Test ACF_Field_Group functionality.
 */
class Test_ACF_Field_Group extends BaseTestCase {

	/**
	 * Field group manager instance.
	 *
	 * @var ACF_Field_Group
	 */
	private $field_group;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->field_group = new ACF_Field_Group();
	}

	/**
	 * Helper to create an in-memory field group array.
	 *
	 * @param string $key      Unique key for the field group.
	 * @param array  $location Location rules array.
	 * @param bool   $active   Whether the field group is active.
	 * @return array The field group data.
	 */
	private function make_field_group( $key, $location = array(), $active = true ) {
		return array(
			'key'      => $key,
			'title'    => 'Test Field Group ' . $key,
			'fields'   => array(),
			'location' => $location,
			'active'   => $active,
		);
	}

	/**
	 * Test that ignore_location_rules returns all field groups.
	 *
	 * Location rules are a UX feature for edit screens, not access control.
	 * When ignore_location_rules is true, all field groups should be returned
	 * regardless of their location rules.
	 */
	public function test_ignore_location_rules_returns_all_field_groups() {
		$fg_posts = $this->make_field_group(
			'group_posts',
			array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			)
		);

		$fg_pages = $this->make_field_group(
			'group_pages',
			array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'page',
					),
				),
			)
		);

		$fg_no_location = $this->make_field_group( 'group_no_location', array() );

		$all_groups = array( $fg_posts, $fg_pages, $fg_no_location );

		$result = $this->field_group->filter_posts(
			$all_groups,
			array( 'ignore_location_rules' => true )
		);

		$this->assertCount(
			3,
			$result,
			'All field groups should be returned when ignore_location_rules is true'
		);
	}

	/**
	 * Test that active filter still works with ignore_location_rules.
	 *
	 * Even when bypassing location rules, the active filter should still apply.
	 */
	public function test_ignore_location_rules_respects_active_filter() {
		$fg_active   = $this->make_field_group( 'group_active', array(), true );
		$fg_inactive = $this->make_field_group( 'group_inactive', array(), false );

		$all_groups = array( $fg_active, $fg_inactive );

		$result = $this->field_group->filter_posts(
			$all_groups,
			array(
				'ignore_location_rules' => true,
				'active'                => true,
			)
		);

		$this->assertCount( 1, $result, 'Only active field groups should be returned' );
		$this->assertEquals( 'group_active', array_values( $result )[0]['key'] );
	}

	/**
	 * Test that without ignore_location_rules, empty context filters out field groups.
	 *
	 * By default, filter_posts checks visibility based on location rules.
	 * With no context provided, field groups with location rules won't match.
	 */
	public function test_default_behavior_filters_by_location_rules() {
		$fg_with_location = $this->make_field_group(
			'group_with_loc',
			array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			)
		);

		// Without ignore_location_rules and no matching context, visibility check fails.
		$result = $this->field_group->filter_posts( array( $fg_with_location ), array() );

		$this->assertCount(
			0,
			$result,
			'Field groups should be filtered out when location rules do not match context'
		);
	}
}
