#!/usr/bin/env php
<?php
/**
 * Update plugin translations
 *
 * @package wordpress/secure-custom-fields
 */

namespace WordPress\SCF\Scripts;

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// phpcs:disable WordPress.WP.AlternativeFunctions

// Ensure we're in the right directory.
chdir( dirname( dirname( __DIR__ ) ) );

/**
 * Handles the translation update process for Secure Custom Fields.
 */
class Translation_Updater {
	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	private $plugin_slug = 'secure-custom-fields';

	/**
	 * Language directory
	 *
	 * @var string
	 */
	private $lang_dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->lang_dir = __DIR__ . '/../lang';
	}

	/**
	 * Run the translation update
	 */
	public function run() {
		if ( ! is_dir( $this->lang_dir ) ) {
			echo "Language directory not found: {$this->lang_dir}\n";
			return;
		}

		// Get available translations from WordPress.org
		$translations = $this->get_available_translations();
		if ( empty( $translations ) ) {
			echo "No translations available from WordPress.org\n";
			return;
		}

		// Get existing translation files
		$pattern  = $this->lang_dir . '/' . $this->plugin_slug . '-*.po';
		$po_files = glob( $pattern );

		if ( empty( $po_files ) ) {
			echo "No translation files found in {$this->lang_dir}\n";
			return;
		}

		foreach ( $po_files as $po_file ) {
			$basename = basename( $po_file );
			$locale   = str_replace( array( $this->plugin_slug . '-', '.po' ), '', $basename );

			echo "→ Checking $locale...\n";

			$po_path   = $this->lang_dir . '/' . $this->plugin_slug . '-' . $locale . '.po';
			$mo_path   = $this->lang_dir . '/' . $this->plugin_slug . '-' . $locale . '.mo';
			$l10n_path = $this->lang_dir . '/' . $this->plugin_slug . '-' . $locale . '.l10n.php';

			// If translation is in WordPress.org list, delete local files
			if ( isset( $translations[ $locale ] ) ) {
				if ( file_exists( $po_path ) ) {
					unlink( $po_path );
					echo "  ✓ Deleted .po file\n";
				}
				if ( file_exists( $mo_path ) ) {
					unlink( $mo_path );
					echo "  ✓ Deleted .mo file\n";
				}
				if ( file_exists( $l10n_path ) ) {
					unlink( $l10n_path );
					echo "  ✓ Deleted .l10n.php file\n";
				}
				echo "  ✓ Translation available on WordPress.org\n\n";
				continue;
			}

			echo "  ⚠️ Translation not available on WordPress.org\n";
		}
	}

	/**
	 * Get available translations from WordPress.org
	 *
	 * @return array
	 */
	private function get_available_translations() {
		$api_url = sprintf(
			'https://api.wordpress.org/translations/plugins/1.0/?slug=%s',
			$this->plugin_slug
		);

		$response = file_get_contents( $api_url );
		if ( false === $response ) {
			return array();
		}

		$data = json_decode( $response, true );
		if ( ! isset( $data['translations'] ) ) {
			return array();
		}

		$translations = array();
		foreach ( $data['translations'] as $translation ) {
			$translations[ $translation['language'] ] = array(
				'package' => $translation['package'],
			);
		}

		return $translations;
	}
}

// Run the script
$updater = new Translation_Updater();
$updater->run();
