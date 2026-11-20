<?php
/**
 * Tests for the ACF Abilities port in src/AI (acf/* abilities).
 *
 * @package wordpress/secure-custom-fields
 */

use SCF\AI\AI;
use SCF\AI\Abilities\FieldGroup;
use SCF\AI\Abilities\PostType;
use SCF\AI\Abilities\Taxonomy;
use WorDBless\BaseTestCase;

// Load mock Abilities API functions so abilities can be registered without WordPress 6.9.
require_once dirname( __DIR__, 2 ) . '/includes/abilities/abilities-api-mocks.php';
require_once __DIR__ . '/class-test-scf-ai-schema-ability-group.php';

/**
 * Tests for the ACF Abilities port.
 */
class Test_SCF_AI_Abilities extends BaseTestCase {

	/**
	 * Post type and taxonomy keys registered during a test.
	 *
	 * @var array
	 */
	private $registered_objects = array(
		'post_types' => array(),
		'taxonomies' => array(),
	);

	/**
	 * Local field group keys added during a test.
	 *
	 * @var array
	 */
	private $local_field_groups = array();

	/**
	 * ACF post types and taxonomies returned by acf_get_acf_post_types() and acf_get_acf_taxonomies().
	 *
	 * WorDBless can't query posts, so they are provided through the acf/load_* filters.
	 *
	 * @var array
	 */
	private $acf_objects = array(
		'post_types' => array(),
		'taxonomies' => array(),
	);

	/**
	 * Messages passed to _doing_it_wrong() during a test.
	 *
	 * @var array
	 */
	private $doing_it_wrong = array();

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// WorDBless fires init before the plugin is loaded, so run it again to register the internal post types.
		if ( ! acf_get_internal_post_type_instance( 'acf-post-type' ) ) {
			do_action( 'plugins_loaded' );
			do_action( 'init' );
		}

		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		add_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10, 2 );
		add_filter( 'acf/load_post_types', array( $this, 'load_acf_post_types' ) );
		add_filter( 'acf/load_taxonomies', array( $this, 'load_acf_taxonomies' ) );
	}

	/**
	 * Returns the ACF post types created by the test.
	 *
	 * @param array $posts The loaded ACF post types.
	 * @return array
	 */
	public function load_acf_post_types( $posts ) {
		return array_merge( $posts, $this->acf_objects['post_types'] );
	}

	/**
	 * Returns the ACF taxonomies created by the test.
	 *
	 * @param array $posts The loaded ACF taxonomies.
	 * @return array
	 */
	public function load_acf_taxonomies( $posts ) {
		return array_merge( $posts, $this->acf_objects['taxonomies'] );
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		foreach ( $this->registered_objects['post_types'] as $post_type ) {
			unregister_post_type( $post_type );
		}
		foreach ( $this->registered_objects['taxonomies'] as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}
		foreach ( $this->local_field_groups as $key ) {
			acf_remove_local_field_group( $key );
		}

		foreach ( array( 'acf-field-group', 'acf-post-type', 'acf-taxonomy' ) as $internal_post_type ) {
			$instance = acf_get_internal_post_type_instance( $internal_post_type );
			$store    = $instance ? acf_get_store( $instance->store ) : false;
			if ( $store ) {
				$store->reset();
			}
		}

		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		remove_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10 );
		remove_all_filters( 'acf/load_field_group' );
		remove_filter( 'acf/load_post_types', array( $this, 'load_acf_post_types' ) );
		remove_filter( 'acf/load_taxonomies', array( $this, 'load_acf_taxonomies' ) );
		acf_update_setting( 'enable_post_types', true );

		global $mock_registered_abilities;
		$mock_registered_abilities = array();

		parent::tearDown();
	}

	/**
	 * Records _doing_it_wrong() calls.
	 *
	 * @param string $function_name The function that was called.
	 * @param string $message       The message.
	 * @return void
	 */
	public function record_doing_it_wrong( $function_name, $message ) {
		$this->doing_it_wrong[] = $message;
	}

	/**
	 * Creates an active ACF post type and registers it with WordPress.
	 *
	 * @param string $post_type The post type key.
	 * @param array  $settings  Extra ACF post type settings.
	 * @return void
	 */
	private function create_acf_post_type( string $post_type, array $settings = array() ) {
		$this->acf_objects['post_types'][] = acf_validate_post_type(
			array_merge(
				array(
					'key'          => 'post_type_' . uniqid(),
					'title'        => ucfirst( $post_type ),
					'post_type'    => $post_type,
					'active'       => true,
					'show_in_rest' => true,
				),
				$settings
			)
		);

		register_post_type(
			$post_type,
			array(
				'label'        => ucfirst( $post_type ),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => $settings['rest_base'] ?? $post_type,
				'supports'     => array( 'title', 'editor' ),
			)
		);
		$this->registered_objects['post_types'][] = $post_type;
	}

	/**
	 * Creates an active ACF taxonomy and registers it with WordPress.
	 *
	 * @param string $taxonomy  The taxonomy key.
	 * @param array  $settings  Extra ACF taxonomy settings.
	 * @param string $rest_base The REST base to register.
	 * @return void
	 */
	private function create_acf_taxonomy( string $taxonomy, array $settings = array(), string $rest_base = '' ) {
		$this->acf_objects['taxonomies'][] = acf_validate_taxonomy(
			array_merge(
				array(
					'key'          => 'taxonomy_' . uniqid(),
					'title'        => ucfirst( $taxonomy ),
					'taxonomy'     => $taxonomy,
					'active'       => true,
					'show_in_rest' => true,
				),
				$settings
			)
		);

		register_taxonomy(
			$taxonomy,
			'post',
			array(
				'label'        => ucfirst( $taxonomy ),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => $rest_base ? $rest_base : $taxonomy,
			)
		);
		$this->registered_objects['taxonomies'][] = $taxonomy;
	}

	/**
	 * Asserts that a schema has no boolean `required` and no REST-only keywords at any depth.
	 *
	 * @param mixed  $schema The schema.
	 * @param string $path   The path for failure messages.
	 * @return void
	 */
	private function assert_plain_json_schema( $schema, string $path = '' ) {
		if ( ! is_array( $schema ) ) {
			return;
		}

		foreach ( array( 'context', 'readonly', 'arg_options', 'sanitize_callback', 'validate_callback', 'links' ) as $keyword ) {
			$this->assertArrayNotHasKey( $keyword, $schema, "REST-only keyword '{$keyword}' found at {$path}" );
		}

		if ( isset( $schema['required'] ) ) {
			$this->assertIsArray( $schema['required'], "Boolean required found at {$path}" );
		}

		if ( isset( $schema['properties'] ) ) {
			$this->assertNotSame( array(), $schema['properties'], "Empty properties found at {$path}" );
		}

		foreach ( array( 'properties', 'patternProperties', 'definitions', '$defs' ) as $keyword ) {
			if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
				foreach ( $schema[ $keyword ] as $name => $subschema ) {
					$this->assert_plain_json_schema( $subschema, "{$path}.{$keyword}.{$name}" );
				}
			}
		}

		foreach ( array( 'anyOf', 'oneOf', 'allOf' ) as $keyword ) {
			if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
				foreach ( $schema[ $keyword ] as $index => $subschema ) {
					$this->assert_plain_json_schema( $subschema, "{$path}.{$keyword}.{$index}" );
				}
			}
		}

		foreach ( array( 'items', 'additionalProperties', 'not' ) as $keyword ) {
			if ( isset( $schema[ $keyword ] ) ) {
				$this->assert_plain_json_schema( $schema[ $keyword ], "{$path}.{$keyword}" );
			}
		}
	}

	/**
	 * Abilities are enabled globally and for each object type by default.
	 */
	public function test_abilities_are_enabled_by_default() {
		$this->assertTrue( acf_get_setting( 'enable_abilities' ) );
		$this->assertNull( acf_get_setting( 'enable_acf_ai' ), 'The old setting name should no longer exist' );
		$this->assertTrue( ( new AI() )->is_abilities_enabled() );

		$field_group = acf_validate_field_group( array( 'key' => 'group_defaults' ) );
		$post_type   = acf_validate_post_type( array( 'key' => 'post_type_defaults' ) );
		$taxonomy    = acf_validate_taxonomy( array( 'key' => 'taxonomy_defaults' ) );

		foreach ( array( $field_group, $post_type, $taxonomy ) as $settings ) {
			$this->assertTrue( $settings['enable_acf_abilities'] );
			$this->assertSame( '', $settings['ai_description'] );
			$this->assertArrayNotHasKey( 'allow_ai_access', $settings );
		}
	}

	/**
	 * Turning the global setting off disables abilities.
	 */
	public function test_is_abilities_enabled_follows_setting() {
		acf_update_setting( 'enable_abilities', false );
		$this->assertFalse( ( new AI() )->is_abilities_enabled() );

		acf_update_setting( 'enable_abilities', true );
		$this->assertTrue( ( new AI() )->is_abilities_enabled() );
	}

	/**
	 * Stored Abilities settings are kept when the submission doesn't include them.
	 */
	public function test_preserve_unsubmitted_abilities_settings_keeps_stored_values() {
		$post = new WP_Post(
			(object) array(
				'ID'           => 123,
				'post_content' => maybe_serialize(
					array(
						'enable_acf_abilities' => 0,
						'ai_description'       => 'A "quoted" description',
					)
				),
			)
		);

		$result = acf_preserve_unsubmitted_abilities_settings( array( 'title' => 'Books' ), $post );

		$this->assertSame( 'Books', $result['title'] );
		$this->assertSame( 0, $result['enable_acf_abilities'] );
		$this->assertSame( wp_slash( 'A "quoted" description' ), $result['ai_description'], 'Stored values should be slashed to match submitted data' );
		$this->assertSame( 'A "quoted" description', wp_unslash( $result['ai_description'] ) );
	}

	/**
	 * Submitted Abilities settings win over stored ones.
	 */
	public function test_preserve_unsubmitted_abilities_settings_keeps_submitted_values() {
		$post = new WP_Post(
			(object) array(
				'ID'           => 123,
				'post_content' => maybe_serialize(
					array(
						'enable_acf_abilities' => 0,
						'ai_description'       => 'Old',
					)
				),
			)
		);

		$submitted = array(
			'enable_acf_abilities' => '1',
			'ai_description'       => 'New',
		);

		$this->assertSame( $submitted, acf_preserve_unsubmitted_abilities_settings( $submitted, $post ) );
	}

	/**
	 * Invalid input is returned unchanged.
	 */
	public function test_preserve_unsubmitted_abilities_settings_ignores_invalid_input() {
		$post = new WP_Post(
			(object) array(
				'ID'           => 123,
				'post_content' => maybe_serialize( array( 'enable_acf_abilities' => 0 ) ),
			)
		);

		$this->assertSame( 'not an array', acf_preserve_unsubmitted_abilities_settings( 'not an array', $post ) );
		$this->assertSame( array( 'title' => 'x' ), acf_preserve_unsubmitted_abilities_settings( array( 'title' => 'x' ), null ) );

		// New posts have no stored settings, so nothing is added and the defaults apply on save.
		$new_post = new WP_Post(
			(object) array(
				'ID'           => 124,
				'post_content' => '',
			)
		);
		$this->assertSame( array( 'title' => 'x' ), acf_preserve_unsubmitted_abilities_settings( array( 'title' => 'x' ), $new_post ) );
	}

	/**
	 * Field group get_post() returns false when acf/load_field_group returns something unusable.
	 */
	public function test_field_group_get_post_guards_against_invalid_filter_result() {
		$this->local_field_groups[] = 'group_load_guard';
		acf_add_local_field_group(
			array(
				'key'    => 'group_load_guard',
				'title'  => 'Load guard',
				'fields' => array(),
			)
		);

		add_filter( 'acf/load_field_group', '__return_false' );
		$this->assertFalse( acf_get_field_group( 'group_load_guard' ) );

		remove_all_filters( 'acf/load_field_group' );
		add_filter(
			'acf/load_field_group',
			function ( $field_group ) {
				unset( $field_group['ID'] );
				return $field_group;
			}
		);
		$this->assertFalse( acf_get_field_group( 'group_load_guard' ) );

		remove_all_filters( 'acf/load_field_group' );
		$field_group = acf_get_field_group( 'group_load_guard' );
		$this->assertIsArray( $field_group );
		$this->assertSame( 'group_load_guard', $field_group['key'] );
	}

	/**
	 * Boolean `required` flags are lifted into arrays and REST-only keywords are removed.
	 */
	public function test_normalize_schema_produces_plain_json_schema() {
		$group = new Test_SCF_AI_Schema_Ability_Group();

		$schema = $group->public_normalize_schema(
			array(
				'type'       => 'object',
				'context'    => array( 'view' ),
				'links'      => array( array( 'rel' => 'self' ) ),
				'required'   => array( 'existing' ),
				'properties' => array(
					'existing' => array( 'type' => 'string' ),
					'id'       => array(
						'type'     => 'int',
						'required' => true,
						'readonly' => true,
						'context'  => array( 'view', 'edit' ),
					),
					'optional' => array(
						'type'     => array( 'float', 'null', 'number' ),
						'required' => false,
					),
					'empty'    => array(
						'type'       => 'object',
						'properties' => array(),
					),
					'list'     => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'name' => array(
									'type'        => 'string',
									'required'    => true,
									'arg_options' => array( 'sanitize_callback' => 'sanitize_text_field' ),
								),
							),
						),
					),
					'either'   => array(
						'anyOf' => array(
							true,
							array(
								'type'       => 'object',
								'properties' => array(
									'value' => array(
										'type'     => 'string',
										'required' => true,
									),
								),
							),
						),
					),
				),
			)
		);

		$this->assertSame( array( 'existing', 'id' ), $schema['required'] );
		$this->assertArrayNotHasKey( 'context', $schema );
		$this->assertArrayNotHasKey( 'links', $schema );
		$this->assertSame( array( 'type' => 'integer' ), $schema['properties']['id'] );
		$this->assertSame( array( 'type' => array( 'number', 'null' ) ), $schema['properties']['optional'] );
		$this->assertSame( array( 'type' => 'object' ), $schema['properties']['empty'], 'Empty properties would encode as a JSON list, so they are removed' );
		$this->assertSame( array( 'name' ), $schema['properties']['list']['items']['required'] );
		$this->assertSame( array( 'type' => 'string' ), $schema['properties']['list']['items']['properties']['name'] );
		$this->assertTrue( $schema['properties']['either']['anyOf'][0], 'Boolean subschemas are kept' );
		$this->assertSame( array( 'value' ), $schema['properties']['either']['anyOf'][1]['required'] );
		$this->assert_plain_json_schema( $schema );
	}

	/**
	 * The prepare_ability_args() helper normalizes both schemas and sets MCP defaults.
	 */
	public function test_prepare_ability_args_normalizes_schemas() {
		$group = new Test_SCF_AI_Schema_Ability_Group();

		$args = $group->public_prepare_ability_args(
			array(
				'label'         => 'Test',
				'input_schema'  => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'     => 'integer',
							'readonly' => true,
						),
					),
				),
			)
		);

		$this->assertSame( array( 'id' ), $args['input_schema']['required'] );
		$this->assertArrayNotHasKey( 'readonly', $args['output_schema']['properties']['id'] );
		$this->assertTrue( $args['meta']['mcp']['public'] );
	}

	/**
	 * Field value schemas carry the field identity in the description only.
	 */
	public function test_get_field_value_schema_moves_identity_into_description() {
		$group = new Test_SCF_AI_Schema_Ability_Group();

		$schema = $group->public_get_field_value_schema(
			array(
				'key'         => 'field_abc',
				'name'        => 'subtitle',
				'label'       => 'Subtitle',
				'field_type'  => 'text',
				'description' => 'Shown under the title',
				'type'        => array( 'string', 'null' ),
			)
		);

		$this->assertSame(
			array(
				'description' => 'Subtitle (text field). Shown under the title',
				'type'        => array( 'string', 'null' ),
			),
			$schema
		);
	}

	/**
	 * The list abilities keep SCF's object-or-null input schema, which survives normalization.
	 */
	public function test_list_abilities_accept_null_and_empty_input() {
		global $mock_registered_abilities;

		( new FieldGroup() )->register_abilities();
		( new PostType() )->register_abilities();
		( new Taxonomy() )->register_abilities();

		foreach ( array( 'acf/field-groups', 'acf/custom-post-types', 'acf/custom-taxonomies' ) as $ability ) {
			$this->assertArrayHasKey( $ability, $mock_registered_abilities );
			$schema = $mock_registered_abilities[ $ability ]['input_schema'];

			$this->assertSame(
				array(
					'type'                 => array( 'object', 'null' ),
					'additionalProperties' => false,
				),
				$schema,
				"{$ability} input schema"
			);

			// The JSON form must be an object schema, never a list.
			$this->assertSame( '{"type":["object","null"],"additionalProperties":false}', wp_json_encode( $schema ) );

			$this->assertTrue( rest_validate_value_from_schema( null, $schema, 'input' ), "{$ability} should accept null" );
			$this->assertTrue( rest_validate_value_from_schema( array(), $schema, 'input' ), "{$ability} should accept an empty object" );
			$this->assertInstanceOf( WP_Error::class, rest_validate_value_from_schema( array( 'foo' => 1 ), $schema, 'input' ), "{$ability} should reject unknown properties" );
		}
	}

	/**
	 * Every registered ability publishes plain JSON Schema.
	 */
	public function test_registered_ability_schemas_are_plain_json_schema() {
		global $mock_registered_abilities;

		$this->create_acf_post_type( 'book' );
		$this->create_acf_taxonomy( 'genre' );

		( new FieldGroup() )->register_abilities();
		( new PostType() )->register_abilities();
		( new Taxonomy() )->register_abilities();

		$this->assertNotEmpty( $mock_registered_abilities );

		foreach ( $mock_registered_abilities as $name => $args ) {
			foreach ( array( 'input_schema', 'output_schema' ) as $schema_key ) {
				if ( ! empty( $args[ $schema_key ] ) ) {
					$this->assert_plain_json_schema( $args[ $schema_key ], "{$name}.{$schema_key}" );
				}
			}
		}

		$register = $mock_registered_abilities['acf/register-custom-post-type']['input_schema'];
		$this->assertSame( array( 'post_type', 'label', 'plural_label' ), $register['required'] );
		$this->assertFalse( $register['additionalProperties'] );
		$this->assertArrayHasKey( 'enable_acf_abilities', $register['properties'] );
		$this->assertArrayNotHasKey( 'allow_ai_access', $register['properties'] );

		$this->assertSame( array( 'id' ), $mock_registered_abilities['acf/view-book-post']['input_schema']['required'] );
		$this->assertSame( array( 'title' ), $mock_registered_abilities['acf/create-book-post']['input_schema']['required'] );
		$this->assertSame( array( 'name' ), $mock_registered_abilities['acf/create-genre-term']['input_schema']['required'] );
	}

	/**
	 * Generated ability names include the object type.
	 */
	public function test_generated_ability_names_include_object_type() {
		global $mock_registered_abilities;

		$this->create_acf_post_type( 'my_book' );
		$this->create_acf_taxonomy( 'genre' );

		( new PostType() )->register_abilities();
		( new Taxonomy() )->register_abilities();

		foreach ( array( 'acf/list-my-book-posts', 'acf/create-my-book-post', 'acf/view-my-book-post', 'acf/update-my-book-post', 'acf/delete-my-book-post' ) as $ability ) {
			$this->assertArrayHasKey( $ability, $mock_registered_abilities );
		}

		foreach ( array( 'acf/list-genre-terms', 'acf/create-genre-term', 'acf/view-genre-term', 'acf/update-genre-term', 'acf/delete-genre-term' ) as $ability ) {
			$this->assertArrayHasKey( $ability, $mock_registered_abilities );
		}

		foreach ( array( 'acf/my-books', 'acf/create-my-book', 'acf/view-my-book', 'acf/genres', 'acf/create-genre', 'acf/delete-genre' ) as $old_name ) {
			$this->assertArrayNotHasKey( $old_name, $mock_registered_abilities, "Old ability name {$old_name} should not be registered" );
		}

		$this->assertStringContainsString( 'trash', $mock_registered_abilities['acf/delete-my-book-post']['description'] );
	}

	/**
	 * Objects with abilities turned off don't get abilities.
	 */
	public function test_objects_with_abilities_disabled_are_skipped() {
		global $mock_registered_abilities;

		$this->create_acf_post_type( 'movie', array( 'enable_acf_abilities' => false ) );
		$this->create_acf_taxonomy( 'mood', array( 'enable_acf_abilities' => 0 ) );

		( new PostType() )->register_abilities();
		( new Taxonomy() )->register_abilities();

		$this->assertArrayNotHasKey( 'acf/list-movie-posts', $mock_registered_abilities );
		$this->assertArrayNotHasKey( 'acf/list-mood-terms', $mock_registered_abilities );
	}

	/**
	 * Keys that become the same ability name only register abilities once.
	 */
	public function test_duplicate_ability_names_are_detected() {
		global $mock_registered_abilities;

		$this->create_acf_post_type( 'dup_type' );
		$this->create_acf_post_type( 'dup-type' );

		( new PostType() )->register_abilities();

		$this->assertArrayHasKey( 'acf/list-dup-type-posts', $mock_registered_abilities );
		$this->assertCount( 1, $this->doing_it_wrong );
		$this->assertStringContainsString( 'both become "dup-type"', html_entity_decode( $this->doing_it_wrong[0] ) );
	}

	/**
	 * Taxonomies whose REST base is used by a post type don't get term abilities.
	 */
	public function test_taxonomy_rest_base_collision_is_detected() {
		global $mock_registered_abilities;

		$this->create_acf_post_type( 'recipe', array( 'rest_base' => 'recipes' ) );
		$this->create_acf_taxonomy( 'recipe_tag', array(), 'recipes' );

		( new Taxonomy() )->register_abilities();

		$this->assertArrayNotHasKey( 'acf/list-recipe-tag-terms', $mock_registered_abilities );
		$this->assertCount( 1, $this->doing_it_wrong );
		$this->assertStringContainsString( 'REST base "recipes"', html_entity_decode( $this->doing_it_wrong[0] ) );
	}

	/**
	 * Only the list abilities are registered when ACF post types are turned off.
	 */
	public function test_post_type_and_taxonomy_abilities_bail_when_post_types_disabled() {
		global $mock_registered_abilities;

		acf_update_setting( 'enable_post_types', false );

		( new PostType() )->register_abilities();
		( new Taxonomy() )->register_abilities();

		$this->assertSame( array( 'acf/custom-post-types', 'acf/custom-taxonomies' ), array_keys( $mock_registered_abilities ) );
	}

	/**
	 * The acf/field-groups ability includes each field group's fields.
	 */
	public function test_field_groups_ability_includes_fields() {
		$this->local_field_groups[] = 'group_ai_fields';
		$this->local_field_groups[] = 'group_ai_disabled';

		acf_add_local_field_group(
			array(
				'key'    => 'group_ai_fields',
				'title'  => 'AI fields',
				'fields' => array(
					array(
						'key'   => 'field_ai_subtitle',
						'label' => 'Subtitle',
						'name'  => 'subtitle',
						'type'  => 'text',
					),
				),
			)
		);
		acf_add_local_field_group(
			array(
				'key'                  => 'group_ai_disabled',
				'title'                => 'Disabled',
				'enable_acf_abilities' => false,
				'fields'               => array(),
			)
		);

		$result = ( new FieldGroup() )->get_field_groups();
		$groups = wp_list_pluck( $result['field_groups'], 'fields', 'key' );

		$this->assertArrayHasKey( 'group_ai_fields', $groups );
		$this->assertArrayNotHasKey( 'group_ai_disabled', $groups );
		$this->assertCount( 1, $groups['group_ai_fields'] );
		$this->assertSame( 'subtitle', $groups['group_ai_fields'][0]['name'] );
		$this->assertArrayNotHasKey( 'ID', $groups['group_ai_fields'][0], 'Fields should be prepared for export' );
	}

	/**
	 * Imported field limits and layouts are sanitized.
	 */
	public function test_prepare_field_for_ability_import_sanitizes_limits_and_layouts() {
		$field_group = new FieldGroup();

		$number = $field_group->prepare_field_for_ability_import(
			array(
				'label' => 'Temperature',
				'type'  => 'number',
				'min'   => '-10.5',
				'max'   => 'abc',
			)
		);
		$this->assertSame( -10.5, $number['min'] );
		$this->assertSame( '', $number['max'] );

		$flexible = $field_group->prepare_field_for_ability_import(
			array(
				'label'   => 'Sections',
				'type'    => 'flexible_content',
				'min'     => '-2',
				'layouts' => array(
					array(
						'label' => 'Hero <b>Banner</b>',
						'min'   => '-1',
						'max'   => '3',
					),
					'not a layout',
				),
			)
		);
		$this->assertSame( 0, $flexible['min'] );
		$this->assertSame( 'Hero Banner', $flexible['layouts'][0]['label'] );
		$this->assertSame( 'hero_banner', $flexible['layouts'][0]['name'] );
		$this->assertSame( 0, $flexible['layouts'][0]['min'] );
		$this->assertSame( 3, $flexible['layouts'][0]['max'] );
		$this->assertSame( 'not a layout', $flexible['layouts'][1] );

		$select = $field_group->prepare_field_for_ability_import(
			array(
				'label'   => 'Colour',
				'type'    => 'select',
				'choices' => array(
					'Group' => array( 'red' => '<i>Red</i>' ),
				),
			)
		);
		$this->assertSame( array( 'Group' => array( 'red' => 'Red' ) ), $select['choices'] );
	}

	/**
	 * Fields without a JSON schema file require a label and type at the object level.
	 */
	public function test_default_field_creation_schema_uses_required_array() {
		$field_type = new class() extends acf_field {
			/**
			 * Sets up the field type.
			 */
			public function initialize() {
				$this->name = 'scf_test_no_schema_type';
			}
		};

		$schema = $field_type->get_field_creation_schema();

		$this->assertSame( array( 'label', 'type' ), $schema['required'] );
		$this->assertArrayNotHasKey( 'required', $schema['properties']['label'] );
		$this->assertArrayNotHasKey( 'required', $schema['properties']['type'] );
	}
}
