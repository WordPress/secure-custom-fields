#!/usr/bin/env php
<?php
/**
 * Prepare a release of Secure Custom Fields
 *
 * @package wordpress/secure-custom-fields
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// phpcs:disable WordPress.WP.AlternativeFunctions

namespace WordPress\SCF\Scripts;

// Ensure we're in the right directory.
chdir( dirname( __DIR__ ) );

// Check if required PHP functions are available.
$required_functions = array( 'exec', 'passthru' );
foreach ( $required_functions as $function ) {
	if ( ! function_exists( $function ) || in_array( $function, explode( ',', ini_get( 'disable_functions' ) ), true ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "Error: {$function}() is required but not available\n";
		exit( 1 );
	}
}

/**
 * Handles the release preparation process for Secure Custom Fields.
 *
 * This class manages version updates, changelog verification, and creating
 * pull requests for new releases.
 */
class Release_Preparation {
	/**
	 * Main plugin file
	 *
	 * @var string
	 */
	const PLUGIN_FILE = 'secure-custom-fields.php';

	/**
	 * Run the release preparation
	 */
	public function run() {
		$this->check_requirements();
		$this->build_assets();
		$this->run_tests();
		$this->generate_docs();
		$this->commit_changes();

		$current_version = $this->get_current_version();
		$latest_tag      = $this->get_latest_tag();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\nCurrent version: {$current_version}";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\nLatest tag: {$latest_tag}\n";

		$new_version = $this->prompt_for_version();
		$this->validate_version( $new_version );

		$changelog = $this->get_changelog( $new_version );
		if ( $changelog ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\nChangelog found for {$new_version}:\n\n{$changelog}\n";
			if ( ! $this->confirm( 'Is this changelog correct?' ) ) {
				exit( 1 );
			}
		} elseif ( ! $this->confirm( "No changelog found for {$new_version}. Is this expected?" ) ) {
			exit( 1 );
		}

		$this->update_version( $new_version );
		$this->commit_version( $new_version );

		$current_stable = $this->get_stable_tag();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\nCurrent stable tag: {$current_stable}\n";
		if ( $this->confirm( "Update stable tag to {$new_version}?" ) ) {
			$this->update_stable_tag( $new_version );
			$this->commit_stable_tag( $new_version );
		}

		if ( $this->confirm( 'Create PR for this release?' ) ) {
			$this->create_pr( $new_version, $changelog );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\nRelease preparation complete!\n";
	}

	/**
	 * Check if required tools are available
	 */
	private function check_requirements() {
		$required = array( 'npm', 'composer', 'gh' );
		foreach ( $required as $tool ) {
			exec( "which {$tool}", $output, $return );
			if ( 0 !== $return ) {
				echo "Error: {$tool} is required but not found\n";
				exit( 1 );
			}
		}
	}

	/**
	 * Build assets using npm
	 */
	private function build_assets() {
		echo "Installing npm dependencies...\n";
		passthru( 'npm install', $return );
		if ( 0 !== $return ) {
			exit( $return );
		}

		echo "Building assets...\n";
		passthru( 'npm run build', $return );
		if ( 0 !== $return ) {
			exit( $return );
		}
	}

	/**
	 * Run tests
	 */
	private function run_tests() {
		echo "Running tests...\n";
		passthru( 'composer test', $return );
		if ( 0 !== $return ) {
			exit( $return );
		}
	}

	/**
	 * Generate documentation
	 */
	private function generate_docs() {
		echo "Generating documentation...\n";
		passthru( 'composer docs', $return );
		if ( 0 !== $return ) {
			exit( $return );
		}
	}

	/**
	 * Commit any changes from build/tests/docs
	 */
	private function commit_changes() {
		exec( 'git status --porcelain', $output );
		if ( ! empty( $output ) ) {
			echo "Committing changes...\n";
			passthru( 'git add .', $return );
			if ( 0 !== $return ) {
				exit( $return );
			}
			passthru( 'git commit -m "Build assets and documentation"', $return );
			if ( 0 !== $return ) {
				exit( $return );
			}
		}
	}

	/**
	 * Get current version from plugin file
	 */
	private function get_current_version() {
		$plugin_data = file_get_contents( self::PLUGIN_FILE );
		preg_match( '/\$version = \'([^\']+)\'/', $plugin_data, $matches );
		return $matches[1] ?? 'unknown';
	}

	/**
	 * Get latest git tag
	 */
	private function get_latest_tag() {
		exec( 'git describe --tags --abbrev=0', $output, $return );
		return 0 === $return ? $output[0] : 'none';
	}

	/**
	 * Prompt for new version
	 */
	private function prompt_for_version() {
		echo 'Enter new version number: ';
		$handle  = fopen( 'php://stdin', 'r' );
		$version = trim( fgets( $handle ) );
		fclose( $handle );
		return $version;
	}

	/**
	 * Validate version format
	 *
	 * @param string $version Version string to validate.
	 */
	private function validate_version( $version ) {
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:-beta\d+)?$/', $version ) ) {
			echo "Error: Invalid version format. Expected X.Y.Z or X.Y.Z-betaN\n";
			exit( 1 );
		}
	}

	/**
	 * Get changelog entry for version
	 *
	 * @param string $version Version to get changelog for.
	 * @return string Changelog entry or empty string if not found.
	 */
	private function get_changelog( $version ) {
		$readme = file_get_contents( 'readme.txt' );
		if ( preg_match( "/= {$version} =\s*\n(.*?)(?=\n=|$)/s", $readme, $matches ) ) {
			return trim( $matches[1] );
		}
		return '';
	}

	/**
	 * Update version in plugin file
	 *
	 * @param string $version New version number.
	 */
	private function update_version( $version ) {
		$plugin_data = file_get_contents( self::PLUGIN_FILE );

		// Update version in docblock using line-by-line replacement
		$lines = explode( "\n", $plugin_data );
		foreach ( $lines as &$line ) {
			if ( strpos( $line, '* Version:' ) !== false ) {
				$line = ' * Version:           ' . $version;
			}
		}
		$plugin_data = implode( "\n", $lines );

		// Update version property
		$plugin_data = preg_replace(
			'/public \$version = \'[^\']+\'/',
			'public $version = \'' . $version . '\'',
			$plugin_data
		);

		file_put_contents( self::PLUGIN_FILE, $plugin_data );
	}

	/**
	 * Commit version update
	 *
	 * @param string $version Version being committed.
	 */
	private function commit_version( $version ) {
		passthru( 'git add ' . self::PLUGIN_FILE );
		passthru( "git commit -m 'Update version to {$version}'" );
	}

	/**
	 * Get current stable tag from readme.txt
	 */
	private function get_stable_tag() {
		$readme = file_get_contents( 'readme.txt' );
		if ( preg_match( '/Stable tag: ([^\s\n]+)/', $readme, $matches ) ) {
			return $matches[1];
		}
		return 'unknown';
	}

	/**
	 * Update stable tag in readme.txt
	 *
	 * @param string $version Version to set as stable.
	 */
	private function update_stable_tag( $version ) {
		$readme = file_get_contents( 'readme.txt' );
		$readme = preg_replace(
			'/^Stable tag:.*$/m',
			'Stable tag: ' . $version,
			$readme
		);
		file_put_contents( 'readme.txt', $readme );
	}

	/**
	 * Commit stable tag update
	 *
	 * @param string $version Version being set as stable.
	 */
	private function commit_stable_tag( $version ) {
		passthru( 'git add readme.txt' );
		passthru( "git commit -m 'Update stable tag to {$version}'" );
	}

	/**
	 * Create PR using gh cli
	 *
	 * @param string $version   Version being released.
	 * @param string $changelog Changelog content for the PR body.
	 */
	private function create_pr( $version, $changelog ) {
		$title = "Prepare {$version} Release";
		$body  = $changelog ? $changelog : "Changelog entry pending for {$version}";

		passthru( "gh pr create --title \"{$title}\" --body \"{$body}\"" );
	}

	/**
	 * Prompt for confirmation
	 *
	 * @param string $message Message to display.
	 * @return bool True if confirmed, false otherwise.
	 */
	private function confirm( $message ) {
		echo "{$message} [y/N] ";
		$handle = fopen( 'php://stdin', 'r' );
		$line   = strtolower( trim( fgets( $handle ) ) );
		fclose( $handle );
		return 'y' === $line;
	}
}

// Run the script
$preparation = new Release_Preparation();
$preparation->run();
