<?php
/**
 * Tests for the SCF Post Type JSON Schema validation.
 *
 * @package SCF
 */

/**
 * Tests for the SCF Post Type JSON Schema validation.
 *
 * @package SCF
 */

require_once dirname( dirname( __DIR__ ) ) . '/includes/class-scf-json-schema-validator.php';

/**
 * Class PostTypeSchemaTest
 *
 * Tests JSON Schema validation for SCF post types.
 */
class PostTypeSchemaTest extends \PHPUnit\Framework\TestCase {

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
		$this->fixtures_path = __DIR__ . '/fixtures/schemas/post-types/';
	}

	/**
	 * Test that the post-type schema loads correctly.
	 */
	public function test_post_type_schema_loads() {
		$schema = $this->validator->load_schema( 'post-type' );

		$this->assertNotNull( $schema, 'Post type schema should load successfully' );
		$this->assertObjectHasProperty( 'oneOf', $schema, 'Schema should use oneOf for flexibility' );
		$this->assertObjectHasProperty( 'definitions', $schema, 'Schema should have definitions' );
		$this->assertObjectHasProperty( 'postType', $schema->definitions, 'Schema should define postType' );

		// Check that the postType definition has the correct required fields
		$post_type_def = $schema->definitions->postType;
		$this->assertContains( 'key', $post_type_def->required, 'Key should be required' );
		$this->assertContains( 'title', $post_type_def->required, 'Title should be required' );
		$this->assertContains( 'post_type', $post_type_def->required, 'Post type should be required' );
	}

	/**
	 * Data provider for valid post types.
	 */
	public function validPostTypesProvider() {
		return array(
			'basic valid'                  => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'book',
				),
				'Basic post type should validate successfully',
			),
			'array with two items'         => array(
				array(
					array(
						'key'       => 'post_type_book',
						'title'     => 'Book',
						'post_type' => 'book',
					),
					array(
						'key'       => 'post_type_movie',
						'title'     => 'Movie',
						'post_type' => 'movie',
					),
				),
				'Array of two post types should validate successfully',
			),
			'with dashes'                  => array(
				array(
					'key'       => 'post_type_my_product',
					'title'     => 'My Product',
					'post_type' => 'my-product',
				),
				'Post type with dashes should be valid',
			),
			'with underscores'             => array(
				array(
					'key'       => 'post_type_my_product',
					'title'     => 'My Product',
					'post_type' => 'my_product',
				),
				'Post type with underscores should be valid',
			),
			'with numbers'                 => array(
				array(
					'key'       => 'post_type_product123',
					'title'     => 'Product',
					'post_type' => 'product123',
				),
				'Post type with numbers should be valid',
			),
			'custom supports'              => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'book',
					'supports'  => array( 'title', 'custom_support_feature' ),
				),
				'Post type with custom supports should be valid',
			),
			'rewrite false'                => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'book',
					'rewrite'   => false,
				),
				'Post type with rewrite as false should validate',
			),
			'rewrite object'               => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'book',
					'rewrite'   => array(
						'slug'       => 'books',
						'with_front' => true,
						'feeds'      => true,
						'pages'      => true,
					),
				),
				'Post type with rewrite object should validate',
			),
			'with capabilities'            => array(
				array(
					'key'          => 'post_type_book',
					'title'        => 'Book',
					'post_type'    => 'book',
					'capabilities' => array(
						'edit_post'   => 'edit_books',
						'read_post'   => 'read_books',
						'delete_post' => 'delete_books',
					),
				),
				'Post type with valid capabilities should validate',
			),
			'taxonomies empty array'       => array(
				array(
					'key'        => 'post_type_test',
					'title'      => 'Test',
					'post_type'  => 'test',
					'taxonomies' => array(),
				),
				'Post type with empty taxonomies array should validate',
			),
			'taxonomies empty string'      => array(
				array(
					'key'        => 'post_type_test',
					'title'      => 'Test',
					'post_type'  => 'test',
					'taxonomies' => '',
				),
				'Post type with empty taxonomies string should validate',
			),
			'taxonomies array with values' => array(
				array(
					'key'        => 'post_type_test',
					'title'      => 'Test',
					'post_type'  => 'test',
					'taxonomies' => array( 'category', 'post_tag' ),
				),
				'Post type with taxonomies array should validate',
			),
			'menu_position null'           => array(
				array(
					'key'           => 'post_type_test',
					'title'         => 'Test',
					'post_type'     => 'test',
					'menu_position' => null,
				),
				'Post type with null menu_position should validate',
			),
			'menu_position empty string'   => array(
				array(
					'key'           => 'post_type_test',
					'title'         => 'Test',
					'post_type'     => 'test',
					'menu_position' => '',
				),
				'Post type with empty string menu_position should validate',
			),
			'menu_position integer'        => array(
				array(
					'key'           => 'post_type_test',
					'title'         => 'Test',
					'post_type'     => 'test',
					'menu_position' => 20,
				),
				'Post type with integer menu_position should validate',
			),
		);
	}

	/**
	 * Data provider for invalid post types.
	 */
	public function invalidPostTypesProvider() {
		return array(
			'missing key'           => array(
				array(
					'title'     => 'Book',
					'post_type' => 'book',
				),
				'Post type missing key should fail validation',
			),
			'missing title'         => array(
				array(
					'key'       => 'post_type_book',
					'post_type' => 'book',
				),
				'Post type missing title should fail validation',
			),
			'missing post_type'     => array(
				array(
					'key'   => 'post_type_book',
					'title' => 'Book',
				),
				'Post type missing post_type should fail validation',
			),
			'empty key'             => array(
				array(
					'key'       => '',
					'title'     => 'Book',
					'post_type' => 'book',
				),
				'Post type with empty key should fail validation',
			),
			'key too long'          => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'very_long_post_type_key_exceeding_twenty_chars',
				),
				'Post type with key over 20 chars should fail validation',
			),
			'invalid characters'    => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'Book-Post!',
				),
				'Post type with invalid characters should fail validation',
			),
			'reserved term'         => array(
				array(
					'key'       => 'post_type_post',
					'title'     => 'Post',
					'post_type' => 'post',
				),
				'Post type using WordPress reserved term should fail validation',
			),
			'invalid supports type' => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'book',
					'supports'  => array( 'title', 123 ),
				),
				'Post type with non-string supports should fail validation',
			),
			'additional properties' => array(
				array(
					'key'              => 'post_type_book',
					'title'            => 'Book',
					'post_type'        => 'book',
					'invalid_property' => 'some value',
				),
				'Post type with additional properties should fail validation',
			),
		);
	}

	/**
	 * Test validation of valid post types using data provider.
	 *
	 * @dataProvider validPostTypesProvider
	 * @param mixed  $data        The post type data to validate.
	 * @param string $description Test description.
	 */
	public function test_valid_post_types_from_data_provider( $data, $description ) {
		$result = $this->validator->validate( $data, 'post-type' );
		$this->assertTrue( $result, $description );
		$this->assertFalse( $this->validator->has_validation_errors(), 'Should have no validation errors' );
	}

	/**
	 * Test validation of invalid post types using data provider.
	 *
	 * @dataProvider invalidPostTypesProvider
	 * @param mixed  $data        The post type data to validate.
	 * @param string $description Test description.
	 */
	public function test_invalid_post_types_from_data_provider( $data, $description ) {
		$result = $this->validator->validate( $data, 'post-type' );
		$this->assertFalse( $result, $description );
		$this->assertTrue( $this->validator->has_validation_errors(), 'Should have validation errors' );
	}

	/**
	 * Test validation with valid fixture files (JSON file import scenarios).
	 */
	public function test_valid_post_types_from_fixture_files() {
		$valid_files = glob( $this->fixtures_path . 'valid/*.json' );
		$this->assertNotEmpty( $valid_files, 'Should have valid fixture files' );

		foreach ( $valid_files as $file_path ) {
			$filename = basename( $file_path );
			$result   = $this->validator->validate( $file_path, 'post-type' );

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
	public function test_invalid_post_types_from_fixture_files() {
		$invalid_files = glob( $this->fixtures_path . 'invalid/*.json' );
		$this->assertNotEmpty( $invalid_files, 'Should have invalid fixture files' );

		foreach ( $invalid_files as $file_path ) {
			$filename = basename( $file_path );
			$result   = $this->validator->validate( $file_path, 'post-type' );
			$this->assertFalse( $result, "Invalid fixture {$filename} should fail validation" );
		}
	}
}
