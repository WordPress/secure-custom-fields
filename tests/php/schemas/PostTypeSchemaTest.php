<?php
/**
 * Tests for the SCF Post Type JSON Schema validation.
 *
 * @package wordpress/secure-custom-fields
 */

use PHPUnit\Framework\TestCase;

require_once 'BaseSchemaTestCase.php';

/**
 * Class PostTypeSchemaTest
 *
 * Tests JSON Schema validation for SCF post types.
 */
class PostTypeSchemaTest extends BaseSchemaTestCase {

	/**
	 * Get the schema type to test.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'post-type';
	}

	/**
	 * Get the path to the fixtures directory.
	 *
	 * @return string
	 */
	protected function get_fixtures_path(): string {
		return dirname( __DIR__ ) . '/fixtures/schemas/post-types/';
	}

	/**
	 * Get the definition name in the schema.
	 *
	 * @return string
	 */
	protected function get_definition_name(): string {
		return 'postType';
	}

	/**
	 * Get the required fields for this schema.
	 *
	 * @return array
	 */
	protected function get_required_fields(): array {
		return array( 'key', 'title', 'post_type' );
	}

	/**
	 * Data provider for valid post types.
	 *
	 * @return array
	 */
	public function validEntitiesProvider(): array {
		return array(
			'basic valid'                    => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'book',
				),
				'Basic post type should validate successfully',
			),
			'array with two items'           => array(
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
			'with dashes'                    => array(
				array(
					'key'       => 'post_type_my_product',
					'title'     => 'My Product',
					'post_type' => 'my-product',
				),
				'Post type with dashes should be valid',
			),
			'with underscores'               => array(
				array(
					'key'       => 'post_type_my_product',
					'title'     => 'My Product',
					'post_type' => 'my_product',
				),
				'Post type with underscores should be valid',
			),
			'with numbers'                   => array(
				array(
					'key'       => 'post_type_product123',
					'title'     => 'Product',
					'post_type' => 'product123',
				),
				'Post type with numbers should be valid',
			),
			'custom supports'                => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'book',
					'supports'  => array( 'title', 'custom_support_feature' ),
				),
				'Post type with custom supports should be valid',
			),
			'rewrite false'                  => array(
				array(
					'key'       => 'post_type_book',
					'title'     => 'Book',
					'post_type' => 'book',
					'rewrite'   => false,
				),
				'Post type with rewrite as false should validate',
			),
			'rewrite object'                 => array(
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
			'with capabilities'              => array(
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
			'taxonomies empty array'         => array(
				array(
					'key'        => 'post_type_test',
					'title'      => 'Test',
					'post_type'  => 'test',
					'taxonomies' => array(),
				),
				'Post type with empty taxonomies array should validate',
			),
			'taxonomies empty string'        => array(
				array(
					'key'        => 'post_type_test',
					'title'      => 'Test',
					'post_type'  => 'test',
					'taxonomies' => '',
				),
				'Post type with empty taxonomies string should validate',
			),
			'taxonomies array with values'   => array(
				array(
					'key'        => 'post_type_test',
					'title'      => 'Test',
					'post_type'  => 'test',
					'taxonomies' => array( 'category', 'post_tag' ),
				),
				'Post type with taxonomies array should validate',
			),
			'menu_position null'             => array(
				array(
					'key'           => 'post_type_test',
					'title'         => 'Test',
					'post_type'     => 'test',
					'menu_position' => null,
				),
				'Post type with null menu_position should validate',
			),
			'menu_position empty string'     => array(
				array(
					'key'           => 'post_type_test',
					'title'         => 'Test',
					'post_type'     => 'test',
					'menu_position' => '',
				),
				'Post type with empty string menu_position should validate',
			),
			'menu_position integer'          => array(
				array(
					'key'           => 'post_type_test',
					'title'         => 'Test',
					'post_type'     => 'test',
					'menu_position' => 20,
				),
				'Post type with integer menu_position should validate',
			),
			'advanced_configuration integer' => array(
				array(
					'key'                    => 'post_type_book',
					'title'                  => 'Book',
					'post_type'              => 'book',
					'advanced_configuration' => 1,
				),
				'Post type with advanced_configuration as integer should be valid',
			),
			'advanced_configuration boolean' => array(
				array(
					'key'                    => 'post_type_book',
					'title'                  => 'Book',
					'post_type'              => 'book',
					'advanced_configuration' => true,
				),
				'Post type with advanced_configuration as boolean should be valid',
			),
		);
	}

	/**
	 * Data provider for invalid post types.
	 *
	 * @return array
	 */
	public function invalidEntitiesProvider(): array {
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
}
