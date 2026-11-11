<?php
/**
 * Tests that block JSON-LD output follows the schema setting.
 *
 * @package wordpress/secure-custom-fields
 */

use SCF\AI\GEO\Outputs\Blocks;
use WorDBless\BaseTestCase;

/**
 * Tests the JSON-LD a block with auto JSON-LD prints.
 *
 * @group security
 */
class Test_Block_Jsonld_Output extends BaseTestCase {

	/**
	 * A published post.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Original enable_schema setting.
	 *
	 * @var bool
	 */
	private $original_schema_setting;

	/**
	 * Register a block with auto JSON-LD, as a logged-out visitor.
	 */
	public function set_up() {
		parent::set_up();

		acf_init();

		$this->post_id = wp_insert_post(
			array(
				'post_title'  => 'Block post',
				'post_status' => 'publish',
			)
		);

		acf_register_block_type(
			array(
				'name'        => 'jsonld-output-test',
				'title'       => 'JSON-LD output test',
				'auto_jsonld' => true,
			)
		);

		$this->original_schema_setting = acf_get_setting( 'enable_schema' );

		wp_set_current_user( 0 );
	}

	/**
	 * Remove test data.
	 */
	public function tear_down() {
		acf_update_setting( 'enable_schema', $this->original_schema_setting );
		acf_remove_block_type( 'acf/jsonld-output-test' );
		wp_delete_post( $this->post_id, true );

		parent::tear_down();
	}

	/**
	 * A block prints no JSON-LD while the schema setting is off.
	 */
	public function test_block_jsonld_is_silent_when_schema_is_disabled() {
		acf_update_setting( 'enable_schema', false );

		$this->assertSame( '', $this->render_block_jsonld() );
	}

	/**
	 * A block prints its mapped fields once the schema setting is on.
	 */
	public function test_block_jsonld_prints_mapped_fields_when_schema_is_enabled() {
		acf_update_setting( 'enable_schema', true );

		$output = $this->render_block_jsonld();

		$this->assertStringContainsString( 'application/ld+json', $output );
		$this->assertStringContainsString( 'Mapped value', $output );
	}

	/**
	 * Renders the JSON-LD the block prints before its template.
	 *
	 * @return string
	 */
	private function render_block_jsonld() {
		$fields = static function () {
			return array(
				'block_output_text' => array(
					'type'            => 'text',
					'name'            => 'block_output_text',
					'value'           => 'Mapped value',
					'schema_property' => 'description',
				),
			);
		};
		add_filter( 'acf/schema/block_field_objects', $fields );

		$block = array(
			'name' => 'acf/jsonld-output-test',
			'id'   => 'block_jsonld_output_test',
		);

		ob_start();
		( new Blocks() )->output_block_jsonld_data( $block, '', false, $this->post_id, null, array() );
		$output = ob_get_clean();

		remove_filter( 'acf/schema/block_field_objects', $fields );

		return $output;
	}
}
