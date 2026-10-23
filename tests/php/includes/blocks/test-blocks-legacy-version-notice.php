<?php
/**
 * Tests for the legacy block version admin notice.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_Blocks_Legacy_Version_Notice
 */
class Test_Blocks_Legacy_Version_Notice extends BaseTestCase {

	/**
	 * Block names registered by the current test.
	 *
	 * @var string[]
	 */
	private $block_names = array();

	/**
	 * The real WordPress version, restored after each test.
	 *
	 * @var string
	 */
	private $wp_version;

	/**
	 * Set up an administrator on an SCF admin screen.
	 */
	public function set_up() {
		parent::set_up();

		acf_init();
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		$this->wp_version = $GLOBALS['wp_version'];

		$admin_id = wp_insert_user(
			array(
				'user_login' => 'legacy_notice_admin_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );

		$this->set_screen( 'acf-field-group' );

		delete_option( acf_get_legacy_block_version_notice_option( 'pre_upgrade' ) );
		delete_option( acf_get_legacy_block_version_notice_option( 'post_upgrade' ) );
	}

	/**
	 * Clean up registered blocks, options, the screen and request data.
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( $this->block_names as $name ) {
			acf_remove_block_type( $name );
			if ( $registry->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		$this->block_names = array();

		delete_option( acf_get_legacy_block_version_notice_option( 'pre_upgrade' ) );
		delete_option( acf_get_legacy_block_version_notice_option( 'post_upgrade' ) );

		$GLOBALS['wp_version']     = $this->wp_version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the real version.
		$GLOBALS['current_screen'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting globals in tests.
		$_GET                      = array();

		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Only blocks pinned to version 2 or lower are listed.
	 */
	public function test_get_legacy_version_block_types() {
		$this->register_block( 'legacy-v1', 'Legacy V1', 1 );
		$this->register_block( 'legacy-v2', 'Legacy V2', 2 );
		$this->register_block( 'modern-v3', 'Modern V3', 3 );

		$legacy = acf_get_legacy_version_block_types();

		$this->assertSame( 'Legacy V1', $legacy['acf/legacy-v1'] );
		$this->assertSame( 'Legacy V2', $legacy['acf/legacy-v2'] );
		$this->assertArrayNotHasKey( 'acf/modern-v3', $legacy );
	}

	/**
	 * Before WordPress 7.1 the notice warns about the upcoming change.
	 */
	public function test_notice_before_wp_7_1() {
		$this->register_block( 'legacy-notice', 'Legacy Notice', 2 );
		$this->set_wp_version( '7.0' );

		$html = $this->render_notice();

		$this->assertStringContainsString( 'When you update to WordPress 7.1', $html );
		$this->assertStringContainsString( 'Legacy Notice <code>acf/legacy-notice</code>', $html );
		$this->assertStringContainsString( 'acf-legacy-notice-context=pre_upgrade', $html );
	}

	/**
	 * On WordPress 7.1+ the notice explains what has changed.
	 */
	public function test_notice_on_wp_7_1() {
		$this->register_block( 'legacy-notice', 'Legacy Notice', 2 );
		$this->set_wp_version( '7.1' );

		$html = $this->render_notice();

		$this->assertStringContainsString( 'WordPress 7.1 has changed how the block editor works', $html );
		$this->assertStringContainsString( 'acf-legacy-notice-context=post_upgrade', $html );
	}

	/**
	 * The notice is not shown without legacy blocks.
	 */
	public function test_no_notice_without_legacy_blocks() {
		$this->register_block( 'modern-only', 'Modern Only', 3 );

		$this->assertSame( '', $this->render_notice() );
	}

	/**
	 * The notice is only shown to administrators on SCF admin screens, and can
	 * be turned off with a filter.
	 */
	public function test_notice_visibility_rules() {
		$this->register_block( 'legacy-visibility', 'Legacy Visibility', 2 );

		$this->set_screen( 'post' );
		$this->assertSame( '', $this->render_notice(), 'Not shown outside SCF admin screens.' );

		$this->set_screen( 'acf-field-group' );
		add_filter( 'acf/blocks/show_legacy_block_version_notice', '__return_false' );
		$this->assertSame( '', $this->render_notice(), 'Not shown when filtered off.' );
		remove_filter( 'acf/blocks/show_legacy_block_version_notice', '__return_false' );

		$editor_id = wp_insert_user(
			array(
				'user_login' => 'legacy_notice_editor_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'editor',
			)
		);
		wp_set_current_user( $editor_id );
		$this->assertSame( '', $this->render_notice(), 'Not shown to users who cannot manage options.' );
	}

	/**
	 * A dismissed variant stays hidden, while the other variant still shows.
	 */
	public function test_dismissal_is_per_variant() {
		$this->register_block( 'legacy-dismissed', 'Legacy Dismissed', 2 );
		update_option( acf_get_legacy_block_version_notice_option( 'pre_upgrade' ), true );

		$this->set_wp_version( '7.0' );
		$this->assertSame( '', $this->render_notice() );

		$this->set_wp_version( '7.1' );
		$this->assertStringContainsString( 'post_upgrade', $this->render_notice() );
	}

	/**
	 * The dismiss link stores the dismissal and redirects.
	 */
	public function test_dismiss_handler_stores_dismissal() {
		$_GET['acf-dismiss-legacy-block-notice'] = wp_create_nonce( 'acf/dismiss_legacy_block_version_notice' );
		$_GET['acf-legacy-notice-context']       = 'post_upgrade';

		/**
		 * Stops the redirect before the handler exits.
		 *
		 * @param string $location The redirect location.
		 * @return never
		 * @throws RuntimeException Always.
		 */
		$redirect = function ( $location ) {
			throw new RuntimeException( 'redirect:' . esc_url_raw( $location ) );
		};
		add_filter( 'wp_redirect', $redirect ); // @phpstan-ignore return.missing (The callback throws to stop the handler before it exits.)

		try {
			acf_dismiss_legacy_block_version_notice();
			$this->fail( 'Expected a redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringStartsWith( 'redirect:', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $redirect );
		}

		$this->assertTrue( (bool) get_option( acf_get_legacy_block_version_notice_option( 'post_upgrade' ) ) );
		$this->assertFalse( get_option( acf_get_legacy_block_version_notice_option( 'pre_upgrade' ) ) );
	}

	/**
	 * Invalid nonces, unknown contexts and non-administrators are ignored.
	 */
	public function test_dismiss_handler_rejects_invalid_requests() {
		$_GET['acf-dismiss-legacy-block-notice'] = 'invalid';
		$_GET['acf-legacy-notice-context']       = 'post_upgrade';
		acf_dismiss_legacy_block_version_notice();

		$_GET['acf-dismiss-legacy-block-notice'] = wp_create_nonce( 'acf/dismiss_legacy_block_version_notice' );
		$_GET['acf-legacy-notice-context']       = 'unknown';
		acf_dismiss_legacy_block_version_notice();

		$subscriber_id = wp_insert_user(
			array(
				'user_login' => 'legacy_notice_subscriber_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			)
		);
		wp_set_current_user( $subscriber_id );
		$_GET['acf-dismiss-legacy-block-notice'] = wp_create_nonce( 'acf/dismiss_legacy_block_version_notice' );
		$_GET['acf-legacy-notice-context']       = 'post_upgrade';
		acf_dismiss_legacy_block_version_notice();

		$this->assertFalse( get_option( acf_get_legacy_block_version_notice_option( 'post_upgrade' ) ) );
	}

	/**
	 * Renders the notice and returns its output.
	 *
	 * @return string
	 */
	private function render_notice() {
		ob_start();
		acf_maybe_show_legacy_block_version_notice();
		return ob_get_clean();
	}

	/**
	 * Registers a block type with the given version.
	 *
	 * @param string  $name    The block name, without namespace.
	 * @param string  $title   The block title.
	 * @param integer $version The block version.
	 */
	private function register_block( $name, $title, $version ) {
		acf_register_block_type(
			array(
				'name'              => $name,
				'title'             => $title,
				'acf_block_version' => $version,
			)
		);
		$this->block_names[] = 'acf/' . $name;
	}

	/**
	 * Sets the current admin screen for the given post type.
	 *
	 * @param string $post_type The screen's post type.
	 */
	private function set_screen( $post_type ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		$screen            = WP_Screen::get( 'edit-' . $post_type );
		$screen->post_type = $post_type;
		$screen->set_current_screen();
	}

	/**
	 * Simulates a WordPress version.
	 *
	 * @param string $version The version.
	 */
	private function set_wp_version( $version ) {
		$GLOBALS['wp_version'] = $version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates a WordPress version.
	}
}
