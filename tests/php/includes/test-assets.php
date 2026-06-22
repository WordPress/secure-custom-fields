<?php
/**
 * Tests for includes/assets.php.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test the assets registry.
 */
class Test_Assets extends BaseTestCase {

	/**
	 * Temporary command asset file path.
	 *
	 * @var string
	 */
	private $admin_commands_asset_file;

	/**
	 * Original command asset file contents.
	 *
	 * @var string|null
	 */
	private $original_admin_commands_asset_file;

	/**
	 * Original WordPress version.
	 *
	 * @var string
	 */
	private $original_wp_version;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_version;

		$this->admin_commands_asset_file          = acf_get_path( 'assets/build/js/commands/scf-admin.asset.php' );
		$this->original_admin_commands_asset_file = file_exists( $this->admin_commands_asset_file )
			? file_get_contents( $this->admin_commands_asset_file ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture preservation.
			: null;
		$this->original_wp_version                = $wp_version;

		wp_dequeue_script( 'acf-input' );
		wp_dequeue_script( 'acf-pro-input' );
		wp_dequeue_script( 'acf-pro-ui-options-page' );
		wp_dequeue_script( 'scf-bindings' );
		wp_deregister_script( 'react-jsx-runtime' );
		wp_deregister_script( 'scf-commands-admin' );
		wp_deregister_script( 'scf-bindings' );
	}

	/**
	 * Clean up test state.
	 */
	public function tear_down() {
		$this->set_wordpress_version( $this->original_wp_version );

		wp_dequeue_script( 'acf-input' );
		wp_dequeue_script( 'acf-pro-input' );
		wp_dequeue_script( 'acf-pro-ui-options-page' );
		wp_dequeue_script( 'scf-bindings' );
		wp_deregister_script( 'react-jsx-runtime' );
		wp_deregister_script( 'scf-commands-admin' );
		wp_deregister_script( 'scf-bindings' );

		if ( null !== $this->original_admin_commands_asset_file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture restoration.
			file_put_contents( $this->admin_commands_asset_file, $this->original_admin_commands_asset_file );
		} elseif ( file_exists( $this->admin_commands_asset_file ) ) {
			unlink( $this->admin_commands_asset_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture cleanup.
		}

		parent::tear_down();
	}

	/**
	 * Set the WordPress version for compatibility checks.
	 *
	 * @param string $version WordPress version to use.
	 */
	private function set_wordpress_version( $version ) {
		global $wp_version;

		$wp_version = $version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test simulates old core versions.
	}

	/**
	 * Test older WordPress installs get a React JSX runtime fallback.
	 */
	public function test_register_scripts_adds_react_jsx_runtime_polyfill_when_missing() {
		acf_get_instance( 'ACF_Assets' )->register_scripts();

		$wp_scripts = wp_scripts();

		$this->assertTrue( wp_script_is( 'react-jsx-runtime', 'registered' ) );
		$this->assertContains( 'wp-element', $wp_scripts->registered['react-jsx-runtime']->deps );
		$this->assertStringContainsString(
			'window.ReactJSXRuntime',
			implode( "\n", $wp_scripts->registered['react-jsx-runtime']->extra['after'] )
		);
	}

	/**
	 * Test command scripts include generated asset dependencies.
	 */
	public function test_command_scripts_include_generated_asset_dependencies() {
		wp_mkdir_p( dirname( $this->admin_commands_asset_file ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		file_put_contents(
			$this->admin_commands_asset_file,
			"<?php return array('dependencies' => array('react-jsx-runtime', 'wp-primitives', 'wp-url'), 'version' => 'test-version');\n"
		);

		acf_get_instance( 'ACF_Assets' )->register_scripts();

		$wp_scripts = wp_scripts();
		$script     = $wp_scripts->registered['scf-commands-admin'];

		$this->assertSame( 'test-version', $script->ver );
		$this->assertContains( 'react-jsx-runtime', $script->deps );
		$this->assertContains( 'wp-primitives', $script->deps );
		$this->assertContains( 'wp-url', $script->deps );
		$this->assertContains( 'wp-commands', $script->deps );
	}

	/**
	 * Test the legacy bindings editor script is not enqueued before WP 6.7.
	 */
	public function test_enqueue_scripts_skips_bindings_editor_script_before_wordpress_6_7() {
		$this->set_wordpress_version( '6.6' );

		$assets = acf_get_instance( 'ACF_Assets' );
		$assets->register_scripts();
		$assets->enqueue();
		$assets->enqueue_scripts();

		$this->assertFalse( wp_script_is( 'scf-bindings', 'enqueued' ) );
	}

	/**
	 * Test the legacy bindings editor script is enqueued on supported WP versions.
	 */
	public function test_enqueue_scripts_enqueues_bindings_editor_script_on_wordpress_6_7() {
		$this->set_wordpress_version( '6.7' );

		$assets = acf_get_instance( 'ACF_Assets' );
		$assets->register_scripts();
		$assets->enqueue();
		$assets->enqueue_scripts();

		$this->assertTrue( wp_script_is( 'scf-bindings', 'enqueued' ) );
	}
}
