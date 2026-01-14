<?php
/**
 * Tests for contributor automation (workflow and composer integration)
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test contributor automation setup
 */
class Test_Contributors_Automation extends BaseTestCase {

	/**
	 * Test composer script invocation
	 *
	 * Verifies that the composer.json file contains the contributors:update script
	 * and that it's properly configured to execute the backfill script.
	 */
	public function test_composer_script_invocation() {
		$composer_path = dirname( dirname( __DIR__ ) ) . '/composer.json';

		$this->assertFileExists( $composer_path, 'composer.json should exist' );

		$composer_content = file_get_contents( $composer_path );
		$composer_data    = json_decode( $composer_content, true );

		$this->assertNotNull( $composer_data, 'composer.json should be valid JSON' );
		$this->assertArrayHasKey( 'scripts', $composer_data, 'composer.json should have scripts section' );
		$this->assertArrayHasKey( 'contributors:update', $composer_data['scripts'], 'composer.json should have contributors:update script' );

		// Verify the script executes the backfill script.
		$script = $composer_data['scripts']['contributors:update'];
		$this->assertStringContainsString( 'backfill-contributors.php', $script, 'Script should reference backfill-contributors.php' );

		// Verify the backfill script exists.
		$backfill_script_path = dirname( dirname( __DIR__ ) ) . '/bin/backfill-contributors.php';
		$this->assertFileExists( $backfill_script_path, 'Backfill script should exist at bin/backfill-contributors.php' );
	}

	/**
	 * Test update-contributors workflow file syntax validation
	 *
	 * Verifies that the update-contributors.yml workflow file exists
	 * and contains valid YAML syntax with required workflow elements.
	 */
	public function test_update_contributors_workflow_file_syntax_validation() {
		$workflow_path = dirname( dirname( __DIR__ ) ) . '/.github/workflows/update-contributors.yml';

		$this->assertFileExists( $workflow_path, 'Workflow file should exist' );

		$workflow_content = file_get_contents( $workflow_path );

		// Verify required YAML structure elements.
		$this->assertStringContainsString( 'name:', $workflow_content, 'Workflow should have a name' );
		$this->assertStringContainsString( 'on:', $workflow_content, 'Workflow should have an on trigger' );
		$this->assertStringContainsString( 'workflow_dispatch', $workflow_content, 'Workflow should have workflow_dispatch trigger for manual execution' );
		$this->assertStringContainsString( 'jobs:', $workflow_content, 'Workflow should have jobs section' );

		// Verify the update-contributor-list job exists.
		$this->assertStringContainsString( 'update-contributor-list:', $workflow_content, 'Workflow should have update-contributor-list job' );

		// Verify key steps are present.
		$this->assertStringContainsString( 'actions/checkout', $workflow_content, 'Workflow should checkout repository' );
		$this->assertStringContainsString( 'backfill-contributors.php', $workflow_content, 'Workflow should run the backfill script' );
		$this->assertStringContainsString( 'GITHUB_TOKEN', $workflow_content, 'Workflow should use GITHUB_TOKEN for authentication' );

		// Verify commit and push functionality.
		$this->assertStringContainsString( 'git', $workflow_content, 'Workflow should have git commands for committing changes' );

		// Verify permissions are set for write access.
		$this->assertStringContainsString( 'permissions:', $workflow_content, 'Workflow should have permissions section' );
		$this->assertStringContainsString( 'contents:', $workflow_content, 'Workflow should specify contents permission' );

		// Verify valid YAML by checking for proper indentation patterns.
		$lines = explode( "\n", $workflow_content );
		foreach ( $lines as $line ) {
			// Skip empty lines and comments.
			if ( empty( trim( $line ) ) || strpos( trim( $line ), '#' ) === 0 ) {
				continue;
			}
			// Check that lines don't have tab characters (YAML uses spaces).
			$this->assertStringNotContainsString( "\t", $line, 'YAML should not contain tabs, only spaces for indentation' );
		}
	}

	/**
	 * Test props-bot workflow file syntax validation
	 *
	 * Verifies that the props-bot.yml workflow file exists
	 * and contains valid YAML syntax with required workflow elements.
	 */
	public function test_props_bot_workflow_file_syntax_validation() {
		$workflow_path = dirname( dirname( __DIR__ ) ) . '/.github/workflows/props-bot.yml';

		$this->assertFileExists( $workflow_path, 'Props bot workflow file should exist' );

		$workflow_content = file_get_contents( $workflow_path );

		// Verify required YAML structure elements.
		$this->assertStringContainsString( 'name:', $workflow_content, 'Workflow should have a name' );
		$this->assertStringContainsString( 'on:', $workflow_content, 'Workflow should have an on trigger' );
		$this->assertStringContainsString( 'pull_request_target:', $workflow_content, 'Workflow should have pull_request_target trigger' );
		$this->assertStringContainsString( 'issue_comment:', $workflow_content, 'Workflow should have issue_comment trigger' );
		$this->assertStringContainsString( 'pull_request_review:', $workflow_content, 'Workflow should have pull_request_review trigger' );
		$this->assertStringContainsString( 'jobs:', $workflow_content, 'Workflow should have jobs section' );

		// Verify it uses the WordPress props-bot-action.
		$this->assertStringContainsString( 'WordPress/props-bot-action', $workflow_content, 'Workflow should use WordPress/props-bot-action' );

		// Verify permissions are set correctly.
		$this->assertStringContainsString( 'pull-requests: write', $workflow_content, 'Workflow should have pull-requests write permission' );

		// Verify valid YAML by checking for proper indentation patterns.
		$lines = explode( "\n", $workflow_content );
		foreach ( $lines as $line ) {
			// Skip empty lines and comments.
			if ( empty( trim( $line ) ) || strpos( trim( $line ), '#' ) === 0 ) {
				continue;
			}
			// Check that lines don't have tab characters (YAML uses spaces).
			$this->assertStringNotContainsString( "\t", $line, 'YAML should not contain tabs, only spaces for indentation' );
		}
	}
}
