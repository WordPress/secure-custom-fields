<?php
/**
 * Tests for the SCF\Site_Health\Site_Health class.
 *
 * @package wordpress/secure-custom-fields
 */

use SCF\Site_Health\Site_Health;
use WorDBless\BaseTestCase;

/**
 * Class Test_SCF_Site_Health
 */
class Test_SCF_Site_Health extends BaseTestCase {

	/**
	 * The Site_Health instance under test.
	 *
	 * @var Site_Health
	 */
	private $site_health;

	/**
	 * Field group keys registered by the current test.
	 *
	 * @var string[]
	 */
	private $group_keys = array();

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		acf_init();

		$this->site_health = acf_get_instance( Site_Health::class );

		delete_option( $this->site_health->option_name );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		foreach ( $this->group_keys as $group_key ) {
			acf_remove_local_field_group( $group_key );
		}
		$this->group_keys = array();

		delete_option( $this->site_health->option_name );

		parent::tear_down();
	}

	/**
	 * Field groups are counted per post type from "post type is equal to" rules.
	 */
	public function test_field_groups_by_post_type_counts_equal_rules() {
		$this->add_group(
			'group_sh_post_and_page',
			array(
				array( $this->rule( 'post_type', '==', 'post' ) ),
				array( $this->rule( 'post_type', '==', 'page' ) ),
			)
		);
		$this->add_group(
			'group_sh_post_twice',
			array(
				array( $this->rule( 'post_type', '==', 'post' ) ),
				array(
					$this->rule( 'post_type', '==', 'post' ),
					$this->rule( 'post_status', '==', 'publish' ),
				),
			)
		);
		$this->add_group(
			'group_sh_ignored',
			array(
				array( $this->rule( 'post_type', '!=', 'book' ) ),
				array( $this->rule( 'post_type', '==', '' ) ),
				array( $this->rule( 'page_type', '==', 'front_page' ) ),
			)
		);

		$values = $this->site_health->get_site_health_values();

		$this->assertSame( 'Field Groups by Post Type', $values['field_groups_by_post_type']['label'] );
		$this->assertSame(
			array(
				array(
					'post_type'         => 'page',
					'field_group_count' => 1,
				),
				array(
					'post_type'         => 'post',
					'field_group_count' => 2,
				),
			),
			$values['field_groups_by_post_type']['value']
		);
	}

	/**
	 * The count is stored with the site health data but not shown on the Info tab.
	 */
	public function test_field_groups_by_post_type_is_hidden_from_info_tab() {
		$this->add_group( 'group_sh_hidden', array( array( $this->rule( 'post_type', '==', 'post' ) ) ) );

		$debug_info = $this->site_health->render_tab_content( array() );

		$this->assertArrayNotHasKey( 'field_groups_by_post_type', $debug_info['secure-custom-fields']['fields'] );
		$this->assertArrayHasKey( 'field_groups_by_post_type', $this->site_health->get_site_health() );
	}

	/**
	 * Builds a location rule.
	 *
	 * @param string $param    The rule param.
	 * @param string $operator The rule operator.
	 * @param string $value    The rule value.
	 * @return array
	 */
	private function rule( $param, $operator, $value ) {
		return array(
			'param'    => $param,
			'operator' => $operator,
			'value'    => $value,
		);
	}

	/**
	 * Registers a local field group with the given location.
	 *
	 * @param string $group_key The field group key.
	 * @param array  $location  The location rule groups.
	 */
	private function add_group( $group_key, array $location ) {
		acf_add_local_field_group(
			array(
				'key'      => $group_key,
				'title'    => $group_key,
				'fields'   => array(),
				'location' => $location,
			)
		);

		$this->group_keys[] = $group_key;
	}
}
