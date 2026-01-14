<?php
/**
 * Tests for contributor data operations
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the contributors functions.
require_once dirname( dirname( __DIR__ ) ) . '/bin/contributors-functions.php';

use function WordPress\SCF\Contributors\read_contributors;
use function WordPress\SCF\Contributors\write_contributors;
use function WordPress\SCF\Contributors\validate_contributor;
use function WordPress\SCF\Contributors\merge_contributors;

/**
 * Test contributor data operations
 */
class Test_Contributors_Data extends BaseTestCase {

	/**
	 * Temporary file path for tests
	 *
	 * @var string
	 */
	private $temp_file;

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();
		$this->temp_file = tempnam( sys_get_temp_dir(), 'contributors_test_' ) . '.json';
	}

	/**
	 * Teardown after tests
	 */
	public function tearDown(): void {
		parent::tearDown();
		if ( file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}
	}

	/**
	 * Test valid contributor entry structure validation
	 *
	 * Validates that a complete contributor entry with all required fields
	 * (github_username, wporg_username, wporg_display_name, contribution_types[],
	 * first_contribution_date) passes validation.
	 */
	public function test_valid_contributor_entry_structure() {
		$valid_contributor = array(
			'github_username'         => 'testuser',
			'wporg_username'          => 'wporguser',
			'wporg_display_name'      => 'Test User',
			'contribution_types'      => array( 'commit', 'review' ),
			'first_contribution_date' => '2024-01-15',
		);

		$this->assertTrue(
			validate_contributor( $valid_contributor ),
			'Valid contributor entry should pass validation'
		);

		// Test with null optional fields.
		$contributor_with_nulls = array(
			'github_username'         => 'anotheruser',
			'wporg_username'          => null,
			'wporg_display_name'      => null,
			'contribution_types'      => array( 'issue' ),
			'first_contribution_date' => '2024-06-01',
		);

		$this->assertTrue(
			validate_contributor( $contributor_with_nulls ),
			'Contributor with null optional fields should pass validation'
		);

		// Test invalid contributor - missing github_username.
		$invalid_no_github = array(
			'wporg_username'          => 'wporguser',
			'contribution_types'      => array( 'commit' ),
			'first_contribution_date' => '2024-01-15',
		);

		$this->assertFalse(
			validate_contributor( $invalid_no_github ),
			'Contributor without github_username should fail validation'
		);

		// Test invalid contributor - invalid contribution type.
		$invalid_contribution_type = array(
			'github_username'         => 'testuser',
			'contribution_types'      => array( 'invalid_type' ),
			'first_contribution_date' => '2024-01-15',
		);

		$this->assertFalse(
			validate_contributor( $invalid_contribution_type ),
			'Contributor with invalid contribution type should fail validation'
		);

		// Test invalid contributor - bad date format.
		$invalid_date = array(
			'github_username'         => 'testuser',
			'contribution_types'      => array( 'commit' ),
			'first_contribution_date' => '01-15-2024',
		);

		$this->assertFalse(
			validate_contributor( $invalid_date ),
			'Contributor with invalid date format should fail validation'
		);

		// Test all valid contribution types.
		$all_types = array(
			'github_username'         => 'fullcontributor',
			'contribution_types'      => array( 'commit', 'review', 'comment', 'issue' ),
			'first_contribution_date' => '2024-01-01',
		);

		$this->assertTrue(
			validate_contributor( $all_types ),
			'All valid contribution types should be accepted'
		);
	}

	/**
	 * Test contributor sorting is alphabetical by GitHub username
	 *
	 * Verifies that when writing contributors, they are sorted
	 * alphabetically by github_username (case-insensitive).
	 */
	public function test_contributor_sorting_alphabetical_by_github_username() {
		$unsorted_contributors = array(
			array(
				'github_username'         => 'zebra',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'Alpha',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'review' ),
				'first_contribution_date' => '2024-01-02',
			),
			array(
				'github_username'         => 'beta',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'comment' ),
				'first_contribution_date' => '2024-01-03',
			),
		);

		// Write the contributors.
		$write_result = write_contributors( $unsorted_contributors, $this->temp_file );
		$this->assertTrue( $write_result, 'Write operation should succeed' );

		// Read them back.
		$sorted_contributors = read_contributors( $this->temp_file );

		// Verify order is alphabetical (case-insensitive).
		$this->assertCount( 3, $sorted_contributors, 'Should have 3 contributors' );
		$this->assertEquals( 'Alpha', $sorted_contributors[0]['github_username'], 'First should be Alpha' );
		$this->assertEquals( 'beta', $sorted_contributors[1]['github_username'], 'Second should be beta' );
		$this->assertEquals( 'zebra', $sorted_contributors[2]['github_username'], 'Third should be zebra' );
	}

	/**
	 * Test JSON file read/write operations
	 *
	 * Tests that:
	 * - Writing to a new file creates valid JSON
	 * - Reading from a file returns the correct data
	 * - Merging contributors handles deduplication correctly
	 */
	public function test_json_file_read_write_operations() {
		// Test writing to a new file.
		$contributors = array(
			array(
				'github_username'         => 'developer1',
				'wporg_username'          => 'wpdev1',
				'wporg_display_name'      => 'Developer One',
				'contribution_types'      => array( 'commit', 'review' ),
				'first_contribution_date' => '2024-01-15',
			),
			array(
				'github_username'         => 'developer2',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'issue' ),
				'first_contribution_date' => '2024-02-20',
			),
		);

		// Write.
		$write_result = write_contributors( $contributors, $this->temp_file );
		$this->assertTrue( $write_result, 'Write operation should succeed' );
		$this->assertFileExists( $this->temp_file, 'File should be created' );

		// Read back and verify.
		$read_data = read_contributors( $this->temp_file );
		$this->assertIsArray( $read_data, 'Read data should be an array' );
		$this->assertCount( 2, $read_data, 'Should have 2 contributors' );

		// Verify content is valid JSON.
		$file_contents = file_get_contents( $this->temp_file );
		$json_data     = json_decode( $file_contents, true );
		$this->assertNotNull( $json_data, 'File should contain valid JSON' );

		// Test merge functionality - add a new contributor and update an existing one.
		$new_contributors = array(
			array(
				'github_username'         => 'developer1', // Existing - should merge.
				'wporg_username'          => 'wpdev1_updated',
				'wporg_display_name'      => 'Developer One Updated',
				'contribution_types'      => array( 'comment' ), // New type.
				'first_contribution_date' => '2024-03-01', // Later date - should keep original.
			),
			array(
				'github_username'         => 'newdeveloper', // New contributor.
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-03-15',
			),
		);

		$merged = merge_contributors( $read_data, $new_contributors );

		// Write merged data.
		$merge_write_result = write_contributors( $merged, $this->temp_file );
		$this->assertTrue( $merge_write_result, 'Merge write operation should succeed' );

		// Read merged data.
		$merged_data = read_contributors( $this->temp_file );
		$this->assertCount( 3, $merged_data, 'Should have 3 contributors after merge' );

		// Find developer1 and verify merge logic.
		$dev1 = null;
		foreach ( $merged_data as $contributor ) {
			if ( 'developer1' === $contributor['github_username'] ) {
				$dev1 = $contributor;
				break;
			}
		}

		$this->assertNotNull( $dev1, 'developer1 should exist in merged data' );
		$this->assertEquals( '2024-01-15', $dev1['first_contribution_date'], 'Should keep earlier date' );
		$this->assertContains( 'commit', $dev1['contribution_types'], 'Should have commit type' );
		$this->assertContains( 'review', $dev1['contribution_types'], 'Should have review type' );
		$this->assertContains( 'comment', $dev1['contribution_types'], 'Should have new comment type' );
		$this->assertEquals( 'wpdev1_updated', $dev1['wporg_username'], 'Should update wporg_username' );

		// Test reading from non-existent file.
		$nonexistent = read_contributors( '/nonexistent/path/contributors.json' );
		$this->assertIsArray( $nonexistent, 'Reading non-existent file should return array' );
		$this->assertEmpty( $nonexistent, 'Reading non-existent file should return empty array' );

		// Test reading from empty file.
		file_put_contents( $this->temp_file, '' );
		$empty_read = read_contributors( $this->temp_file );
		$this->assertIsArray( $empty_read, 'Reading empty file should return array' );
		$this->assertEmpty( $empty_read, 'Reading empty file should return empty array' );

		// Test reading from file with empty array.
		file_put_contents( $this->temp_file, '[]' );
		$empty_array_read = read_contributors( $this->temp_file );
		$this->assertIsArray( $empty_array_read, 'Reading file with empty array should return array' );
		$this->assertEmpty( $empty_array_read, 'Reading file with empty array should return empty array' );
	}
}
