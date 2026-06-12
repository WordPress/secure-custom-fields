<?php
/**
 * Tests for the ACF_Ajax_Local_JSON_Diff response.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_Ajax_Local_JSON_Diff_Response
 *
 * Tests the diff output of the local_json_diff AJAX handler against a
 * database field group and a local JSON file.
 *
 * @group ajax
 */
class Test_Ajax_Local_JSON_Diff_Response extends BaseTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Temporary local JSON directory.
	 *
	 * @var string
	 */
	private $json_dir;

	/**
	 * Field group key used by these tests.
	 *
	 * @var string
	 */
	private $group_key = 'group_test_json_diff';

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		acf_init();

		$this->admin_user_id = wp_insert_user(
			array(
				'user_login' => 'jd_admin_user',
				'user_pass'  => 'password',
				'user_email' => 'jd_admin@example.com',
				'role'       => 'administrator',
			)
		);

		$this->json_dir = sys_get_temp_dir() . '/scf-test-json-diff-' . uniqid();
		mkdir( $this->json_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture outside of WP_Filesystem context.

		$_REQUEST = array();
		$_POST    = array();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		// The handler calls acf_disable_filters() and never re-enables them.
		// Re-enable to avoid leaking disabled local/json filters into other tests.
		acf_enable_filters();

		foreach ( glob( $this->json_dir . '/*' ) as $file ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture cleanup.
		}
		rmdir( $this->json_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture cleanup.

		// Force the local JSON store to forget files found in the temp dir.
		acf_get_instance( 'ACF_Local_JSON' )->scan_files( 'acf-field-group' );

		wp_set_current_user( 0 );

		$_REQUEST = array();
		$_POST    = array();

		parent::tear_down();
	}

	/**
	 * Creates a field group saved in the database.
	 *
	 * @return array The saved field group.
	 */
	private function create_db_field_group(): array {
		return acf_update_field_group(
			array(
				'key'      => $this->group_key,
				'title'    => 'JSON Diff Group',
				'fields'   => array(),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);
	}

	/**
	 * Writes a local JSON file for the test field group and registers its load path.
	 *
	 * @param array $field_group The saved field group.
	 */
	private function create_local_json_file( array $field_group ) {
		$json             = acf_prepare_internal_post_type_for_export( $field_group, 'acf-field-group' );
		$json['fields']   = array();
		$json['title']    = 'JSON Diff Group (Newer)';
		$json['modified'] = time() + HOUR_IN_SECONDS;

		file_put_contents( $this->json_dir . '/' . $this->group_key . '.json', acf_json_encode( $json ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.

		add_filter(
			'acf/settings/load_json',
			function ( $paths ) {
				$paths[] = $this->json_dir;
				return $paths;
			}
		);

		// Rescan so the new file is picked up.
		acf_get_instance( 'ACF_Local_JSON' )->scan_files( 'acf-field-group' );
	}

	/**
	 * Test that a field group without a local JSON file cannot be compared.
	 */
	public function test_field_group_without_json_file_cannot_compare() {
		wp_set_current_user( $this->admin_user_id );

		$field_group = $this->create_db_field_group();

		$ajax   = new ACF_Ajax_Local_JSON_Diff();
		$result = $ajax->get_response( array( 'id' => $field_group['ID'] ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_cannot_compare', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	/**
	 * Test that a valid diff request returns the rendered diff HTML.
	 */
	public function test_valid_diff_request_returns_diff_html() {
		wp_set_current_user( $this->admin_user_id );

		$field_group = $this->create_db_field_group();
		$this->create_local_json_file( $field_group );

		$ajax   = new ACF_Ajax_Local_JSON_Diff();
		$result = $ajax->get_response( array( 'id' => $field_group['ID'] ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'html', $result );

		$html = $result['html'];
		$this->assertStringContainsString( 'acf-diff', $html );
		$this->assertStringContainsString( 'Original', $html );
		$this->assertStringContainsString( 'JSON (newer)', $html );
		$this->assertStringContainsString( 'Last updated:', $html );
	}

	/**
	 * Test that the diff HTML contains the changed value.
	 */
	public function test_diff_html_contains_changed_title() {
		wp_set_current_user( $this->admin_user_id );

		$field_group = $this->create_db_field_group();
		$this->create_local_json_file( $field_group );

		$ajax   = new ACF_Ajax_Local_JSON_Diff();
		$result = $ajax->get_response( array( 'id' => $field_group['ID'] ) );

		$this->assertIsArray( $result );

		// The changed portion of the title is wrapped in an <ins> tag by wp_text_diff().
		$this->assertStringContainsString( '<ins> (Newer)</ins>', $result['html'], 'Diff should mark the local JSON title change as added' );
	}

	/**
	 * Test that the id is accepted as a numeric string.
	 */
	public function test_numeric_string_id_is_accepted() {
		wp_set_current_user( $this->admin_user_id );

		$field_group = $this->create_db_field_group();
		$this->create_local_json_file( $field_group );

		$ajax   = new ACF_Ajax_Local_JSON_Diff();
		$result = $ajax->get_response( array( 'id' => (string) $field_group['ID'] ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'html', $result );
	}
}
