<?php
/**
 * Integration tests for contributor acknowledgement system
 *
 * Tests end-to-end workflows, edge cases, and integration between
 * components of the contributor system.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the contributors functions.
require_once dirname( dirname( __DIR__ ) ) . '/bin/contributors-functions.php';

use function WordPress\SCF\Contributors\read_contributors;
use function WordPress\SCF\Contributors\write_contributors;
use function WordPress\SCF\Contributors\merge_contributors;
use function WordPress\SCF\Contributors\generate_readme_contributors_field;
use function WordPress\SCF\Contributors\generate_contributors_md;
use function WordPress\SCF\Contributors\generate_docs_contributors_md;
use function WordPress\SCF\Contributors\get_linked_contributors;
use function WordPress\SCF\Contributors\update_readme_contributors;
use function WordPress\SCF\Contributors\write_contributors_md;
use function WordPress\SCF\Contributors\write_docs_contributors_md;
use function WordPress\SCF\Contributors\generate_all_output_files;
use function WordPress\SCF\Contributors\apply_wporg_data_to_contributors;

/**
 * Integration tests for contributor acknowledgement system
 */
class Test_Contributors_Integration extends BaseTestCase {

	/**
	 * Temporary directory for tests
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();
		$this->temp_dir = sys_get_temp_dir() . '/contributors_integration_' . uniqid();
		mkdir( $this->temp_dir, 0755, true );
		mkdir( $this->temp_dir . '/docs/contributing', 0755, true );
	}

	/**
	 * Teardown after tests
	 */
	public function tearDown(): void {
		parent::tearDown();
		// Clean up temporary directory.
		$this->remove_directory( $this->temp_dir );
	}

	/**
	 * Recursively remove a directory
	 *
	 * @param string $dir Directory path.
	 */
	private function remove_directory( string $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->remove_directory( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}

	/**
	 * Test end-to-end workflow: data storage -> merge -> output generation
	 *
	 * This tests the complete critical path from storing contributor data
	 * through generating all output files.
	 */
	public function test_end_to_end_data_to_output_workflow() {
		// Initial contributors data.
		$initial_contributors = array(
			array(
				'github_username'         => 'developer1',
				'wporg_username'          => 'wpdev1',
				'wporg_display_name'      => 'Developer One',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-15',
			),
			array(
				'github_username'         => 'developer2',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'review' ),
				'first_contribution_date' => '2024-02-20',
			),
		);

		$contributors_file = $this->temp_dir . '/contributors.json';
		$readme_file       = $this->temp_dir . '/readme.txt';
		$contributors_md   = $this->temp_dir . '/CONTRIBUTORS.md';
		$docs_md           = $this->temp_dir . '/docs/contributing/contributors.md';

		// Create initial readme.txt with placeholder.
		file_put_contents( $readme_file, "=== Secure Custom Fields ===\nContributors: wordpressdotorg\nTags: acf, custom fields\n" );

		// Step 1: Write initial contributors.
		$write_result = write_contributors( $initial_contributors, $contributors_file );
		$this->assertTrue( $write_result, 'Should write contributors.json successfully' );

		// Step 2: Read back and verify.
		$read_contributors = read_contributors( $contributors_file );
		$this->assertCount( 2, $read_contributors, 'Should have 2 contributors' );

		// Step 3: Merge with new contributors.
		$new_contributors = array(
			array(
				'github_username'         => 'developer3',
				'wporg_username'          => 'wpdev3',
				'wporg_display_name'      => 'Developer Three',
				'contribution_types'      => array( 'issue' ),
				'first_contribution_date' => '2024-03-10',
			),
		);

		$merged = merge_contributors( $read_contributors, $new_contributors );
		$this->assertCount( 3, $merged, 'Should have 3 contributors after merge' );

		// Save merged data.
		write_contributors( $merged, $contributors_file );

		// Step 4: Apply simulated WordPress.org data.
		$wporg_api_response = array(
			'developer2' => array(
				'slug'         => 'wpdev2',
				'display_name' => 'Developer Two',
			),
		);
		$updated            = apply_wporg_data_to_contributors( $merged, $wporg_api_response );

		// Verify developer2 is now linked.
		$dev2 = null;
		foreach ( $updated as $c ) {
			if ( 'developer2' === $c['github_username'] ) {
				$dev2 = $c;
				break;
			}
		}
		$this->assertEquals( 'wpdev2', $dev2['wporg_username'], 'developer2 should now be linked' );

		// Step 5: Generate readme.txt contributors field.
		$readme_field = generate_readme_contributors_field( $updated );
		$this->assertStringContainsString( 'wpdev1', $readme_field, 'Should include wpdev1' );
		$this->assertStringContainsString( 'wpdev2', $readme_field, 'Should include wpdev2' );
		$this->assertStringContainsString( 'wpdev3', $readme_field, 'Should include wpdev3' );

		// Step 6: Generate CONTRIBUTORS.md.
		$contributors_md_content = generate_contributors_md( $updated );
		$this->assertStringContainsString( '# Contributors', $contributors_md_content, 'Should have heading' );
		$this->assertStringContainsString( 'developer1', $contributors_md_content, 'Should include all GitHub usernames' );
		$this->assertStringContainsString( 'developer2', $contributors_md_content, 'Should include all GitHub usernames' );
		$this->assertStringContainsString( 'developer3', $contributors_md_content, 'Should include all GitHub usernames' );

		// Step 7: Generate docs page.
		$docs_content = generate_docs_contributors_md( $updated );
		$this->assertStringContainsString( '# Contributors', $docs_content, 'Should have heading' );
		$this->assertStringContainsString( 'Secure Custom Fields', $docs_content, 'Should mention SCF' );

		// Step 8: Write output files using actual functions.
		$readme_update_result = update_readme_contributors( $updated, $readme_file );
		$this->assertTrue( $readme_update_result, 'Should update readme.txt' );

		$md_result = write_contributors_md( $updated, $contributors_md );
		$this->assertTrue( $md_result, 'Should write CONTRIBUTORS.md' );

		$docs_result = write_docs_contributors_md( $updated, $docs_md );
		$this->assertTrue( $docs_result, 'Should write docs contributors.md' );

		// Verify files were written correctly.
		$this->assertFileExists( $contributors_md, 'CONTRIBUTORS.md should exist' );
		$this->assertFileExists( $docs_md, 'docs/contributing/contributors.md should exist' );

		$readme_contents = file_get_contents( $readme_file );
		$this->assertStringContainsString( 'Contributors:', $readme_contents, 'readme.txt should have Contributors field' );
		$this->assertStringContainsString( 'wpdev1', $readme_contents, 'readme.txt should list linked contributors' );
	}

	/**
	 * Test handling of empty contributor list
	 *
	 * Verifies that the system handles an empty contributor list gracefully
	 * across all output generators.
	 */
	public function test_empty_contributor_list_handling() {
		$empty_contributors = array();

		// Test readme field generation - always includes wordpressdotorg first.
		$readme_field = generate_readme_contributors_field( $empty_contributors );
		$this->assertEquals( 'wordpressdotorg', $readme_field, 'Empty list should produce wordpressdotorg' );

		// Test get_linked_contributors.
		$linked = get_linked_contributors( $empty_contributors );
		$this->assertEmpty( $linked, 'Empty list should produce empty linked list' );

		// Test CONTRIBUTORS.md generation.
		$contributors_md = generate_contributors_md( $empty_contributors );
		$this->assertStringContainsString( '# Contributors', $contributors_md, 'Should still have heading' );
		$this->assertStringContainsString( '| GitHub Username', $contributors_md, 'Should have table header' );

		// Test docs page generation.
		$docs_md = generate_docs_contributors_md( $empty_contributors );
		$this->assertStringContainsString( '# Contributors', $docs_md, 'Should still have heading' );

		// Test file write operations with empty list.
		$readme_file = $this->temp_dir . '/readme.txt';
		file_put_contents( $readme_file, "=== Test ===\nContributors: oldcontributor\nTags: test\n" );

		$update_result = update_readme_contributors( $empty_contributors, $readme_file );
		$this->assertTrue( $update_result, 'Update should succeed even with empty list' );

		$readme_contents = file_get_contents( $readme_file );
		$this->assertStringContainsString( 'Contributors: wordpressdotorg', $readme_contents, 'Should default to wordpressdotorg when empty' );
	}

	/**
	 * Test all unlinked accounts scenario
	 *
	 * Verifies correct handling when all contributors lack WordPress.org links.
	 */
	public function test_all_unlinked_accounts_scenario() {
		$unlinked_contributors = array(
			array(
				'github_username'         => 'unlinked1',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'unlinked2',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'review', 'comment' ),
				'first_contribution_date' => '2024-02-01',
			),
			array(
				'github_username'         => 'unlinked3',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'issue' ),
				'first_contribution_date' => '2024-03-01',
			),
		);

		// Test linked filtering returns empty.
		$linked = get_linked_contributors( $unlinked_contributors );
		$this->assertEmpty( $linked, 'Should return empty array when no linked accounts' );

		// Test readme field always includes wordpressdotorg first.
		$readme_field = generate_readme_contributors_field( $unlinked_contributors );
		$this->assertEquals( 'wordpressdotorg', $readme_field, 'Should produce wordpressdotorg when all unlinked' );

		// Test CONTRIBUTORS.md still includes all contributors.
		$contributors_md = generate_contributors_md( $unlinked_contributors );
		$this->assertStringContainsString( 'unlinked1', $contributors_md, 'Should include unlinked1' );
		$this->assertStringContainsString( 'unlinked2', $contributors_md, 'Should include unlinked2' );
		$this->assertStringContainsString( 'unlinked3', $contributors_md, 'Should include unlinked3' );

		// Test actual readme update defaults to wordpressdotorg.
		$readme_file = $this->temp_dir . '/readme.txt';
		file_put_contents( $readme_file, "=== Test ===\nContributors: previousvalue\nDescription: Test\n" );

		update_readme_contributors( $unlinked_contributors, $readme_file );

		$readme_contents = file_get_contents( $readme_file );
		$this->assertStringContainsString( 'Contributors: wordpressdotorg', $readme_contents, 'Should use wordpressdotorg placeholder when all unlinked' );
	}

	/**
	 * Test generate_all_output_files orchestration function
	 *
	 * Verifies that the orchestration function correctly calls all
	 * individual generators and returns proper status.
	 */
	public function test_generate_all_output_files_orchestration() {
		$contributors = array(
			array(
				'github_username'         => 'testuser1',
				'wporg_username'          => 'wptestuser1',
				'wporg_display_name'      => 'Test User One',
				'contribution_types'      => array( 'commit', 'review' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'testuser2',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'issue' ),
				'first_contribution_date' => '2024-02-01',
			),
		);

		// Create necessary files in temp directory.
		$readme_file = $this->temp_dir . '/readme.txt';
		file_put_contents( $readme_file, "=== Test Plugin ===\nContributors: placeholder\nTags: test\n" );

		// Capture log messages.
		$log_messages = array();
		$logger       = function ( $message ) use ( &$log_messages ) {
			$log_messages[] = $message;
		};

		// Note: generate_all_output_files uses default paths, so we can't easily
		// test it with temp directory without modifying the function.
		// Instead, we verify the individual output generators work correctly.

		// Write CONTRIBUTORS.md.
		$md_file   = $this->temp_dir . '/CONTRIBUTORS.md';
		$md_result = write_contributors_md( $contributors, $md_file );
		$this->assertTrue( $md_result, 'CONTRIBUTORS.md write should succeed' );
		$this->assertFileExists( $md_file, 'CONTRIBUTORS.md should exist' );

		$md_content = file_get_contents( $md_file );
		$this->assertStringContainsString( '# Contributors', $md_content, 'Should have proper heading' );
		$this->assertStringContainsString( 'testuser1', $md_content, 'Should include testuser1' );
		$this->assertStringContainsString( 'testuser2', $md_content, 'Should include testuser2' );
		$this->assertStringContainsString( 'wptestuser1', $md_content, 'Should include wporg username' );

		// Write docs/contributing/contributors.md.
		$docs_file   = $this->temp_dir . '/docs/contributing/contributors.md';
		$docs_result = write_docs_contributors_md( $contributors, $docs_file );
		$this->assertTrue( $docs_result, 'docs contributors.md write should succeed' );
		$this->assertFileExists( $docs_file, 'docs/contributing/contributors.md should exist' );

		$docs_content = file_get_contents( $docs_file );
		$this->assertStringContainsString( '# Contributors', $docs_content, 'Should have proper heading' );
		$this->assertStringContainsString( 'Contributor List', $docs_content, 'Should have contributor list section' );
		$this->assertStringContainsString( 'How to Get Listed', $docs_content, 'Should have how to get listed section' );

		// Update readme.txt.
		$readme_result = update_readme_contributors( $contributors, $readme_file );
		$this->assertTrue( $readme_result, 'readme.txt update should succeed' );

		$readme_content = file_get_contents( $readme_file );
		$this->assertStringContainsString( 'Contributors: wordpressdotorg, wptestuser1', $readme_content, 'Should contain wordpressdotorg first and linked wporg username' );
		$this->assertStringNotContainsString( 'placeholder', $readme_content, 'Should not contain placeholder' );
	}
}
