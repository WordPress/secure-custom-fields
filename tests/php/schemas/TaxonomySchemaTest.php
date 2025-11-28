<?php
/**
 * Tests for the SCF Taxonomy JSON Schema validation.
 *
 * @package wordpress/secure-custom-fields
 */

use PHPUnit\Framework\TestCase;

require_once 'BaseSchemaTestCase.php';

/**
 * Class TaxonomySchemaTest
 *
 * Tests JSON Schema validation for SCF taxonomies.
 */
class TaxonomySchemaTest extends BaseSchemaTestCase {

	/**
	 * Get the schema type to test.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'taxonomy';
	}

	/**
	 * Get the path to the fixtures directory.
	 *
	 * @return string
	 */
	protected function get_fixtures_path(): string {
		return dirname( __DIR__ ) . '/fixtures/schemas/taxonomies/';
	}

	/**
	 * Get the definition name in the schema.
	 *
	 * @return string
	 */
	protected function get_definition_name(): string {
		return 'taxonomy';
	}

	/**
	 * Get the required fields for this schema.
	 *
	 * @return array
	 */
	protected function get_required_fields(): array {
		return array( 'key', 'title', 'taxonomy' );
	}

	/**
	 * Test that object_type is NOT required (taxonomy-specific).
	 */
	public function test_object_type_is_optional() {
		$schema       = $this->validator->load_schema( 'taxonomy' );
		$taxonomy_def = $schema->definitions->taxonomy;

		$this->assertNotContains( 'object_type', $taxonomy_def->required, 'Object type should NOT be required' );
	}

	/**
	 * Data provider for valid taxonomies.
	 *
	 * @return array
	 */
	public function validEntitiesProvider(): array {
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
	 *
	 * @return array
	 */
	public function invalidEntitiesProvider(): array {
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
}
