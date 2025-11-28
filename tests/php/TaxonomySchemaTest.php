<?php
/**
 * Tests for the SCF Taxonomy JSON Schema validation.
 *
 * @package SCF
 */

/**
 * Tests for the SCF Taxonomy JSON Schema validation.
 *
 * @package SCF
 */

require_once dirname( dirname( __DIR__ ) ) . '/includes/class-scf-json-schema-validator.php';

/**
 * Class TaxonomySchemaTest
 *
 * Tests JSON Schema validation for SCF taxonomies.
 */
class TaxonomySchemaTest extends \PHPUnit\Framework\TestCase {

	/**
	 * The schema validator instance.
	 *
	 * @var SCF_JSON_Schema_Validator
	 */
	private $validator;

	/**
	 * Path to test fixtures.
	 *
	 * @var string
	 */
	private $fixtures_path;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->validator     = new SCF_JSON_Schema_Validator();
		$this->fixtures_path = __DIR__ . '/fixtures/schemas/taxonomies/';
	}

	/**
	 * Test that the taxonomy schema loads correctly.
	 */
	public function test_taxonomy_schema_loads() {
		$schema = $this->validator->load_schema( 'taxonomy' );

		$this->assertNotNull( $schema, 'Taxonomy schema should load successfully' );
		$this->assertObjectHasProperty( 'oneOf', $schema, 'Schema should use oneOf for flexibility' );
		$this->assertObjectHasProperty( 'definitions', $schema, 'Schema should have definitions' );
		$this->assertObjectHasProperty( 'taxonomy', $schema->definitions, 'Schema should define taxonomy' );

		// Check that the taxonomy definition has the correct required fields.
		$taxonomy_def = $schema->definitions->taxonomy;
		$this->assertContains( 'key', $taxonomy_def->required, 'Key should be required' );
		$this->assertContains( 'title', $taxonomy_def->required, 'Title should be required' );
		$this->assertContains( 'taxonomy', $taxonomy_def->required, 'Taxonomy should be required' );

		// Verify that object_type is NOT required (it's optional with allow_null=1).
		$this->assertNotContains( 'object_type', $taxonomy_def->required, 'Object type should NOT be required' );
	}

	/**
	 * Data provider for valid taxonomies.
	 */
	public function validTaxonomiesProvider() {
		return array(
			'basic valid'                    => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'genre',
				),
				'Basic taxonomy should validate successfully',
			),
			'array with two items'           => array(
				array(
					array(
						'key'      => 'taxonomy_genre',
						'title'    => 'Genre',
						'taxonomy' => 'genre',
					),
					array(
						'key'      => 'taxonomy_topic',
						'title'    => 'Topic',
						'taxonomy' => 'topic',
					),
				),
				'Array of two taxonomies should validate successfully',
			),
			'with dashes'                    => array(
				array(
					'key'      => 'taxonomy_product_tag',
					'title'    => 'Product Tag',
					'taxonomy' => 'product-tag',
				),
				'Taxonomy with dashes should be valid',
			),
			'with underscores'               => array(
				array(
					'key'      => 'taxonomy_product_tag',
					'title'    => 'Product Tag',
					'taxonomy' => 'product_tag',
				),
				'Taxonomy with underscores should be valid',
			),
			'with numbers'                   => array(
				array(
					'key'      => 'taxonomy_tag123',
					'title'    => 'Tag',
					'taxonomy' => 'tag123',
				),
				'Taxonomy with numbers should be valid',
			),
			'hierarchical true'              => array(
				array(
					'key'          => 'taxonomy_category',
					'title'        => 'Category',
					'taxonomy'     => 'book_category',
					'hierarchical' => true,
				),
				'Hierarchical taxonomy should be valid',
			),
			'with object_type'               => array(
				array(
					'key'         => 'taxonomy_genre',
					'title'       => 'Genre',
					'taxonomy'    => 'genre',
					'object_type' => array( 'book', 'movie' ),
				),
				'Taxonomy with object_type should be valid',
			),
			'with empty object_type'         => array(
				array(
					'key'         => 'taxonomy_genre',
					'title'       => 'Genre',
					'taxonomy'    => 'genre',
					'object_type' => array(),
				),
				'Taxonomy with empty object_type array should be valid',
			),
			'without object_type'            => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'genre',
				),
				'Taxonomy without object_type should be valid (optional field)',
			),
			'with capabilities'              => array(
				array(
					'key'          => 'taxonomy_genre',
					'title'        => 'Genre',
					'taxonomy'     => 'genre',
					'capabilities' => array(
						'manage_terms' => 'manage_genres',
						'edit_terms'   => 'edit_genres',
						'delete_terms' => 'delete_genres',
						'assign_terms' => 'assign_genres',
					),
				),
				'Taxonomy with custom capabilities should be valid',
			),
			'rewrite false'                  => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'genre',
					'rewrite'  => false,
				),
				'Taxonomy with rewrite as false should validate',
			),
			'rewrite object'                 => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'genre',
					'rewrite'  => array(
						'slug'                 => 'genres',
						'with_front'           => true,
						'rewrite_hierarchical' => false,
					),
				),
				'Taxonomy with rewrite object should validate',
			),
			'with default_term'              => array(
				array(
					'key'          => 'taxonomy_genre',
					'title'        => 'Genre',
					'taxonomy'     => 'genre',
					'default_term' => array(
						'default_term_enabled'     => true,
						'default_term_name'        => 'Uncategorized',
						'default_term_slug'        => 'uncategorized',
						'default_term_description' => 'Default term',
					),
				),
				'Taxonomy with default_term should be valid',
			),
			'show_tagcloud false'            => array(
				array(
					'key'           => 'taxonomy_genre',
					'title'         => 'Genre',
					'taxonomy'      => 'genre',
					'show_tagcloud' => false,
				),
				'Taxonomy with show_tagcloud false should be valid',
			),
			'show_admin_column true'         => array(
				array(
					'key'               => 'taxonomy_genre',
					'title'             => 'Genre',
					'taxonomy'          => 'genre',
					'show_admin_column' => true,
				),
				'Taxonomy with show_admin_column true should be valid',
			),
			'max length taxonomy key 32'     => array(
				array(
					'key'      => 'taxonomy_max_len',
					'title'    => 'Max Length',
					'taxonomy' => 'abcdefghijklmnopqrstuvwxyz123456', // Exactly 32 chars.
				),
				'Taxonomy with 32 character key should be valid',
			),
			'meta_box custom'                => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'genre',
					'meta_box' => 'custom',
				),
				'Taxonomy with custom meta_box should be valid',
			),
			'sort null'                      => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'genre',
					'sort'     => null,
				),
				'Taxonomy with sort as null should be valid',
			),
			'sort true'                      => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'genre',
					'sort'     => true,
				),
				'Taxonomy with sort as true should be valid',
			),
			'advanced_configuration integer' => array(
				array(
					'key'                    => 'taxonomy_genre',
					'title'                  => 'Genre',
					'taxonomy'               => 'genre',
					'advanced_configuration' => 1,
				),
				'Taxonomy with advanced_configuration as integer should be valid',
			),
			'advanced_configuration boolean' => array(
				array(
					'key'                    => 'taxonomy_genre',
					'title'                  => 'Genre',
					'taxonomy'               => 'genre',
					'advanced_configuration' => true,
				),
				'Taxonomy with advanced_configuration as boolean should be valid',
			),
			'all booleans false'             => array(
				array(
					'key'                => 'taxonomy_genre',
					'title'              => 'Genre',
					'taxonomy'           => 'genre',
					'public'             => false,
					'publicly_queryable' => false,
					'hierarchical'       => false,
					'show_ui'            => false,
					'show_in_menu'       => false,
					'show_in_nav_menus'  => false,
					'show_tagcloud'      => false,
					'show_in_quick_edit' => false,
					'show_admin_column'  => false,
					'show_in_rest'       => false,
				),
				'Taxonomy with all booleans set to false should be valid',
			),
		);
	}

	/**
	 * Data provider for invalid taxonomies.
	 */
	public function invalidTaxonomiesProvider() {
		return array(
			'missing key'               => array(
				array(
					'title'    => 'Genre',
					'taxonomy' => 'genre',
				),
				'Taxonomy missing key should fail validation',
			),
			'missing title'             => array(
				array(
					'key'      => 'taxonomy_genre',
					'taxonomy' => 'genre',
				),
				'Taxonomy missing title should fail validation',
			),
			'missing taxonomy'          => array(
				array(
					'key'   => 'taxonomy_genre',
					'title' => 'Genre',
				),
				'Taxonomy missing taxonomy should fail validation',
			),
			'empty key'                 => array(
				array(
					'key'      => '',
					'title'    => 'Genre',
					'taxonomy' => 'genre',
				),
				'Taxonomy with empty key should fail validation',
			),
			'taxonomy key too long'     => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'very_long_taxonomy_key_exceeding_thirty_two_chars',
				),
				'Taxonomy with key over 32 chars should fail validation',
			),
			'invalid characters'        => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'Genre-Tag!',
				),
				'Taxonomy with invalid characters should fail validation',
			),
			'uppercase characters'      => array(
				array(
					'key'      => 'taxonomy_genre',
					'title'    => 'Genre',
					'taxonomy' => 'Genre',
				),
				'Taxonomy with uppercase characters should fail validation',
			),
			'reserved term category'    => array(
				array(
					'key'      => 'taxonomy_category',
					'title'    => 'Category',
					'taxonomy' => 'category',
				),
				'Taxonomy using WordPress reserved term should fail validation',
			),
			'reserved term post_tag'    => array(
				array(
					'key'      => 'taxonomy_post_tag',
					'title'    => 'Post Tag',
					'taxonomy' => 'post_tag',
				),
				'Taxonomy using WordPress reserved term post_tag should fail validation',
			),
			'wrong key prefix'          => array(
				array(
					'key'      => 'post_type_genre',
					'title'    => 'Genre',
					'taxonomy' => 'genre',
				),
				'Taxonomy with wrong key prefix should fail validation',
			),
			'object_type wrong type'    => array(
				array(
					'key'         => 'taxonomy_genre',
					'title'       => 'Genre',
					'taxonomy'    => 'genre',
					'object_type' => 'book',
				),
				'Taxonomy with string object_type instead of array should fail validation',
			),
			'additional properties'     => array(
				array(
					'key'              => 'taxonomy_genre',
					'title'            => 'Genre',
					'taxonomy'         => 'genre',
					'invalid_property' => 'some value',
				),
				'Taxonomy with additional properties should fail validation',
			),
			'invalid capabilities type' => array(
				array(
					'key'          => 'taxonomy_genre',
					'title'        => 'Genre',
					'taxonomy'     => 'genre',
					'capabilities' => array(
						'manage_terms' => 123,
					),
				),
				'Taxonomy with non-string capability value should fail validation',
			),
		);
	}

	/**
	 * Test validation of valid taxonomies using data provider.
	 *
	 * @dataProvider validTaxonomiesProvider
	 * @param mixed  $data        The taxonomy data to validate.
	 * @param string $description Test description.
	 */
	public function test_valid_taxonomies_from_data_provider( $data, $description ) {
		$result = $this->validator->validate( $data, 'taxonomy' );
		$this->assertTrue( $result, $description );
		$this->assertFalse( $this->validator->has_validation_errors(), 'Should have no validation errors' );
	}

	/**
	 * Test validation of invalid taxonomies using data provider.
	 *
	 * @dataProvider invalidTaxonomiesProvider
	 * @param mixed  $data        The taxonomy data to validate.
	 * @param string $description Test description.
	 */
	public function test_invalid_taxonomies_from_data_provider( $data, $description ) {
		$result = $this->validator->validate( $data, 'taxonomy' );
		$this->assertFalse( $result, $description );
		$this->assertTrue( $this->validator->has_validation_errors(), 'Should have validation errors' );
	}

	/**
	 * Test validation with valid fixture files (JSON file import scenarios).
	 */
	public function test_valid_taxonomies_from_fixture_files() {
		$valid_files = glob( $this->fixtures_path . 'valid/*.json' );
		$this->assertNotEmpty( $valid_files, 'Should have valid fixture files' );

		foreach ( $valid_files as $file_path ) {
			$filename = basename( $file_path );
			$result   = $this->validator->validate( $file_path, 'taxonomy' );

			if ( ! $result ) {
				$errors = $this->validator->get_validation_errors_string();
				$this->fail( "Valid fixture {$filename} should pass validation. Errors: {$errors}" );
			}

			$this->assertTrue( $result, "Valid fixture {$filename} should pass validation" );
		}
	}

	/**
	 * Test validation with invalid fixture files (JSON file import scenarios).
	 */
	public function test_invalid_taxonomies_from_fixture_files() {
		$invalid_files = glob( $this->fixtures_path . 'invalid/*.json' );
		$this->assertNotEmpty( $invalid_files, 'Should have invalid fixture files' );

		foreach ( $invalid_files as $file_path ) {
			$filename = basename( $file_path );
			$result   = $this->validator->validate( $file_path, 'taxonomy' );
			$this->assertFalse( $result, "Invalid fixture {$filename} should fail validation" );
		}
	}
}
