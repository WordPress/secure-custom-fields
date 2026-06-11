<?php
/**
 * Tests for SCF_Abilities_Integration class
 *
 * Tests the registration wiring for the WordPress Abilities API integration:
 * bootstrap hook, dependency-gated includes, and that the full ability and
 * category surface is registered when the Abilities API init actions fire.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load mock Abilities API functions before loading the classes.
require_once __DIR__ . '/abilities-api-mocks.php';

// Load SCF_Field_Manager adapter class (needed by SCF_Field_Abilities).
require_once dirname( __DIR__, 4 ) . '/includes/post-types/class-scf-field-manager.php';

// Load all abilities classes so every provider instance exists for wiring tests.
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-internal-post-type-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-post-type-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-taxonomy-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-ui-options-page-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-field-group-abilities.php';
require_once dirname( __DIR__, 4 ) . '/includes/abilities/class-scf-field-abilities.php';

/**
 * Tests for SCF_Abilities_Integration class
 */
class Test_SCF_Abilities_Integration extends BaseTestCase {

	/**
	 * Expected ability categories across all SCF entity types.
	 *
	 * @var array
	 */
	private $expected_categories = array(
		'scf-fields',
		'scf-field-groups',
		'scf-post-types',
		'scf-taxonomies',
		'scf-ui-options-pages',
	);

	/**
	 * Teardown test fixtures
	 */
	public function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Builds the full list of expected ability names.
	 *
	 * @return array
	 */
	private function expected_abilities() {
		$abilities = array();

		// Field abilities (no trash/untrash; fields are deleted directly).
		foreach ( array( 'get', 'create', 'update', 'delete', 'duplicate', 'export', 'import' ) as $action ) {
			$abilities[] = "scf/{$action}-field";
		}
		$abilities[] = 'scf/list-fields';

		// Internal post type abilities.
		$entities = array(
			'field-group'     => 'field-groups',
			'post-type'       => 'post-types',
			'taxonomy'        => 'taxonomies',
			'ui-options-page' => 'ui-options-pages',
		);
		foreach ( $entities as $singular => $plural ) {
			foreach ( array( 'get', 'create', 'update', 'delete', 'duplicate', 'export', 'import', 'trash', 'untrash' ) as $action ) {
				$abilities[] = "scf/{$action}-{$singular}";
			}
			$abilities[] = "scf/list-{$plural}";
		}

		return $abilities;
	}

	/**
	 * Test the integration instance is created during plugin bootstrap
	 */
	public function test_integration_instance_exists() {
		$this->assertInstanceOf(
			SCF_Abilities_Integration::class,
			acf_get_instance( 'SCF_Abilities_Integration' )
		);
	}

	/**
	 * Test constructor hooks init into plugins_loaded at priority 20
	 */
	public function test_constructor_hooks_plugins_loaded() {
		$fresh_instance = new SCF_Abilities_Integration();

		$this->assertEquals(
			20,
			has_action( 'plugins_loaded', array( $fresh_instance, 'init' ) ),
			'init should be hooked to plugins_loaded at priority 20'
		);
	}

	/**
	 * Test init loads every abilities class when the Abilities API is available
	 *
	 * The Abilities API surface is provided by the mocks loaded above, so
	 * dependencies_available() must report true and init must include all
	 * ability provider classes.
	 */
	public function test_init_loads_all_abilities_classes() {
		$integration = acf_get_instance( 'SCF_Abilities_Integration' );
		$integration->init();

		$expected_classes = array(
			'SCF_Internal_Post_Type_Abilities',
			'SCF_Post_Type_Abilities',
			'SCF_Taxonomy_Abilities',
			'SCF_UI_Options_Page_Abilities',
			'SCF_Field_Group_Abilities',
			'SCF_Field_Abilities',
		);

		foreach ( $expected_classes as $class_name ) {
			$this->assertTrue( class_exists( $class_name ), "Class $class_name should be loaded by init" );
		}
	}

	/**
	 * Test every provider instance is registered in the ACF instance container
	 */
	public function test_all_provider_instances_registered() {
		$expected_instances = array(
			'SCF_Post_Type_Abilities',
			'SCF_Taxonomy_Abilities',
			'SCF_UI_Options_Page_Abilities',
			'SCF_Field_Group_Abilities',
			'SCF_Field_Abilities',
		);

		foreach ( $expected_instances as $class_name ) {
			$this->assertInstanceOf(
				$class_name,
				acf_get_instance( $class_name ),
				"Instance of $class_name should be registered"
			);
		}
	}

	/**
	 * Test all ability categories are registered when the categories action fires
	 */
	public function test_all_categories_registered_on_action() {
		global $mock_registered_ability_categories;
		$mock_registered_ability_categories = array();

		do_action( 'wp_abilities_api_categories_init' );

		foreach ( $this->expected_categories as $category ) {
			$this->assertArrayHasKey(
				$category,
				$mock_registered_ability_categories,
				"Category $category should be registered"
			);
			$this->assertArrayHasKey( 'label', $mock_registered_ability_categories[ $category ] );
			$this->assertArrayHasKey( 'description', $mock_registered_ability_categories[ $category ] );
		}
	}

	/**
	 * Test the full ability surface is registered when the init action fires
	 */
	public function test_all_abilities_registered_on_action() {
		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		do_action( 'wp_abilities_api_init' );

		$expected = $this->expected_abilities();

		foreach ( $expected as $ability_name ) {
			$this->assertArrayHasKey(
				$ability_name,
				$mock_registered_abilities,
				"Ability $ability_name should be registered"
			);
		}

		$this->assertCount(
			count( $expected ),
			$mock_registered_abilities,
			'No unexpected abilities should be registered'
		);
	}

	/**
	 * Test every registered ability references a registered category and is well-formed
	 */
	public function test_registered_abilities_reference_registered_categories() {
		global $mock_registered_abilities, $mock_registered_ability_categories;
		$mock_registered_abilities          = array();
		$mock_registered_ability_categories = array();

		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );

		$this->assertNotEmpty( $mock_registered_abilities );

		foreach ( $mock_registered_abilities as $name => $args ) {
			$this->assertStringStartsWith( 'scf/', $name, "Ability $name should use the scf namespace" );
			$this->assertArrayHasKey(
				$args['category'],
				$mock_registered_ability_categories,
				"Ability $name references unregistered category {$args['category']}"
			);
			$this->assertIsCallable( $args['execute_callback'], "Ability $name execute_callback is not callable" );
			$this->assertEquals(
				'scf_current_user_has_capability',
				$args['permission_callback'],
				"Ability $name should gate access via scf_current_user_has_capability"
			);
			$this->assertArrayHasKey( 'input_schema', $args, "Ability $name missing input_schema" );
			$this->assertArrayHasKey( 'output_schema', $args, "Ability $name missing output_schema" );
		}
	}
}
