<?php
/**
 * Tests that field and field group settings are sanitized for users without unfiltered_html.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests the sanitization of ACF internal post settings on save, and the field wrapper attributes.
 *
 * @group security
 *
 * @covers ::acf_update_field
 * @covers ::acf_skip_internal_post_sanitization
 * @covers ::_acf_maybe_kses_internal_post_settings
 * @covers ::_acf_should_sanitize_internal_post_settings
 * @covers ::_acf_get_field_wrapper_attributes
 * @covers ACF_Internal_Post_Type::update_post
 */
class Test_Internal_Post_Settings_Sanitization extends BaseTestCase {

	private const SCRIPT = '<p>Help</p><script>alert(1)</script>';

	private const EVENT_HANDLER = '<img src="x.png" onerror="alert(1)">';

	/**
	 * Internal post IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $post_ids = array();

	/**
	 * Local field keys registered by the current test.
	 *
	 * @var string[]
	 */
	private $local_field_keys = array();

	/**
	 * Set up a clean state.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		acf_init();

		$this->post_ids         = array();
		$this->local_field_keys = array();
	}

	/**
	 * Remove the posts and local fields created by the test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		foreach ( $this->local_field_keys as $key ) {
			acf_remove_local_field( $key );
		}

		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Log in as a new user with the given role.
	 *
	 * @param string $role Role name.
	 * @return void
	 */
	private function log_in_as( string $role ): void {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'scf_sanitize_' . $role . '_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user_id );
		wp_set_current_user( $user_id );
	}

	/**
	 * Returns a field with HTML in its settings and extra wrapper attributes.
	 *
	 * @return array
	 */
	private function get_unsafe_field(): array {
		return array(
			'key'          => 'field_scf_sanitize_' . uniqid(),
			'label'        => 'Label ' . self::EVENT_HANDLER,
			'name'         => 'scf_sanitize',
			'type'         => 'text',
			'instructions' => self::SCRIPT,
			'wrapper'      => array(
				'width'   => '50',
				'class'   => 'half',
				'id'      => 'scf-half',
				'onclick' => 'alert(1)',
				'style'   => 'color:red',
			),
			'data'         => array( 'onmouseover' => 'alert(1)' ),
		);
	}

	/**
	 * Saves a field and returns its stored settings.
	 *
	 * @param array $field The field.
	 * @return array
	 */
	private function save_field( array $field ): array {
		return $this->capture_saved_settings( 'acf_update_field', $field );
	}

	/**
	 * Saves a field group and returns its stored settings.
	 *
	 * @param array $field_group The field group.
	 * @return array
	 */
	private function save_field_group( array $field_group ): array {
		return $this->capture_saved_settings( 'acf_update_field_group', $field_group );
	}

	/**
	 * Saves an internal post and returns the settings written to the database.
	 *
	 * WorDBless stores posts at `wp_insert_post_data` priority 10, before SCF restores the
	 * unfiltered `post_content` at PHP_INT_MAX, so the content is read at the end of that filter.
	 *
	 * @param callable $save     The save function.
	 * @param array    $settings The settings to save.
	 * @return array
	 */
	private function capture_saved_settings( callable $save, array $settings ): array {
		$content = null;
		$capture = function ( $data ) use ( &$content ) {
			$content = wp_unslash( $data['post_content'] );
			return $data;
		};

		add_filter( 'wp_insert_post_data', $capture, PHP_INT_MAX );
		$saved = $save( $settings );
		remove_filter( 'wp_insert_post_data', $capture, PHP_INT_MAX );

		$this->post_ids[] = $saved['ID'];

		$stored = maybe_unserialize( $content );
		$this->assertIsArray( $stored, 'The saved settings must unserialize.' );

		return $stored;
	}

	/**
	 * Returns a field group with HTML in its description.
	 *
	 * @return array
	 */
	private function get_unsafe_field_group(): array {
		return array(
			'key'         => 'group_scf_sanitize_' . uniqid(),
			'title'       => 'Sanitize Group',
			'description' => self::SCRIPT,
			'location'    => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
		);
	}

	/**
	 * A field saved by an author has scripts and event handlers stripped from its settings.
	 *
	 * @return void
	 */
	public function test_field_settings_are_sanitized_for_user_without_unfiltered_html(): void {
		$this->log_in_as( 'author' );

		$stored = $this->save_field( $this->get_unsafe_field() );

		$this->assertSame( '<p>Help</p>alert(1)', $stored['instructions'] );
		$this->assertStringNotContainsString( 'onerror', get_post_field( 'post_title', $this->post_ids[0], 'raw' ) );
	}

	/**
	 * A field saved by an author only keeps the width, class and id wrapper settings, and no data attributes.
	 *
	 * @return void
	 */
	public function test_field_wrapper_is_limited_for_user_without_unfiltered_html(): void {
		$this->log_in_as( 'author' );

		$stored = $this->save_field( $this->get_unsafe_field() );

		$this->assertSame(
			array(
				'width' => '50',
				'class' => 'half',
				'id'    => 'scf-half',
			),
			$stored['wrapper']
		);
		$this->assertArrayNotHasKey( 'data', $stored );
	}

	/**
	 * The restriction also applies when unfiltered_html is removed from an administrator.
	 *
	 * @return void
	 */
	public function test_field_settings_are_sanitized_when_unfiltered_html_is_denied(): void {
		$this->log_in_as( 'administrator' );
		$deny = function ( $caps, $cap ) {
			if ( 'unfiltered_html' === $cap ) {
				$caps[] = 'do_not_allow';
			}
			return $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 2 );

		$stored = $this->save_field( $this->get_unsafe_field() );

		remove_filter( 'map_meta_cap', $deny, 10 );

		$this->assertSame( '<p>Help</p>alert(1)', $stored['instructions'] );
		$this->assertArrayNotHasKey( 'onclick', $stored['wrapper'] );
	}

	/**
	 * A field saved by a user with unfiltered_html keeps its settings as given.
	 *
	 * @return void
	 */
	public function test_field_settings_are_kept_for_user_with_unfiltered_html(): void {
		$this->log_in_as( 'administrator' );
		$this->assertTrue( current_user_can( 'unfiltered_html' ) );

		$field  = $this->get_unsafe_field();
		$stored = $this->save_field( $field );

		$this->assertSame( self::SCRIPT, $stored['instructions'] );
		$this->assertSame( $field['wrapper'], $stored['wrapper'] );
		$this->assertSame( $field['data'], $stored['data'] );
	}

	/**
	 * Saving through acf_skip_internal_post_sanitization() keeps settings for any user.
	 *
	 * @return void
	 */
	public function test_skip_internal_post_sanitization_bypasses_sanitization(): void {
		$this->log_in_as( 'author' );

		$field  = $this->get_unsafe_field();
		$stored = acf_skip_internal_post_sanitization( fn( $settings ) => $this->save_field( $settings ), $field );

		$this->assertSame( self::SCRIPT, $stored['instructions'] );
		$this->assertSame( $field['wrapper'], $stored['wrapper'] );
	}

	/**
	 * Sanitization is skipped only while the callback runs.
	 *
	 * @return void
	 */
	public function test_skip_internal_post_sanitization_is_restored_after_the_callback(): void {
		$this->log_in_as( 'author' );

		acf_skip_internal_post_sanitization( '__return_null' );
		$stored = $this->save_field( $this->get_unsafe_field() );

		$this->assertSame( '<p>Help</p>alert(1)', $stored['instructions'] );
	}

	/**
	 * Text without HTML is stored exactly as typed, such as choices and conditional logic operators.
	 *
	 * @return void
	 */
	public function test_plain_text_settings_are_not_changed(): void {
		$this->log_in_as( 'author' );

		$field                 = $this->get_unsafe_field();
		$field['instructions'] = 'Fish & chips < 5 &amp; more';
		$stored                = $this->save_field( $field );

		$this->assertSame( 'Fish & chips < 5 &amp; more', $stored['instructions'] );
	}

	/**
	 * A field group saved by an author has scripts stripped from its settings.
	 *
	 * @return void
	 */
	public function test_field_group_settings_are_sanitized_for_user_without_unfiltered_html(): void {
		$this->log_in_as( 'author' );

		$stored = $this->save_field_group( $this->get_unsafe_field_group() );

		$this->assertSame( '<p>Help</p>alert(1)', $stored['description'] );
	}

	/**
	 * A field group saved by a user with unfiltered_html keeps its settings as given.
	 *
	 * @return void
	 */
	public function test_field_group_settings_are_kept_for_user_with_unfiltered_html(): void {
		$this->log_in_as( 'administrator' );

		$stored = $this->save_field_group( $this->get_unsafe_field_group() );

		$this->assertSame( self::SCRIPT, $stored['description'] );
	}

	/**
	 * A field group saved through acf_skip_internal_post_sanitization() keeps its settings for any user.
	 *
	 * @return void
	 */
	public function test_field_group_skip_internal_post_sanitization_bypasses_sanitization(): void {
		$this->log_in_as( 'author' );

		$stored = acf_skip_internal_post_sanitization( fn( $settings ) => $this->save_field_group( $settings ), $this->get_unsafe_field_group() );

		$this->assertSame( self::SCRIPT, $stored['description'] );
	}

	/**
	 * Event handler wrapper settings are never output as wrapper attributes, whatever their case.
	 *
	 * @return void
	 */
	public function test_wrapper_attributes_never_include_event_handlers(): void {
		$field            = acf_validate_field( $this->get_unsafe_field() );
		$field['wrapper'] = array(
			'onclick'     => 'alert(1)',
			'OnMouseOver' => 'alert(1)',
			'data-extra'  => 'kept',
			'class'       => 'half',
		);

		$attributes = _acf_get_field_wrapper_attributes( acf_prepare_field( $field ), 'div' );

		$this->assertArrayNotHasKey( 'onclick', $attributes );
		$this->assertArrayNotHasKey( 'OnMouseOver', $attributes );
		$this->assertSame( 'kept', $attributes['data-extra'] );
		$this->assertStringContainsString( 'half', $attributes['class'] );
	}

	/**
	 * Non-scalar wrapper settings are dropped when a field is validated.
	 *
	 * @return void
	 */
	public function test_non_scalar_wrapper_settings_are_dropped(): void {
		$field            = $this->get_unsafe_field();
		$field['wrapper'] = array(
			'class'  => 'half',
			'nested' => array( 'onclick' => 'alert(1)' ),
		);

		$validated = acf_validate_field( $field );

		$this->assertArrayNotHasKey( 'nested', $validated['wrapper'] );
		$this->assertSame( 'half', $validated['wrapper']['class'] );
	}

	/**
	 * A field registered in PHP with event handler wrapper settings renders without them.
	 *
	 * @return void
	 */
	public function test_php_registered_field_wrapper_does_not_render_event_handlers(): void {
		$field                       = $this->get_unsafe_field();
		$field['wrapper']['onfocus'] = 'alert(2)';
		$field['parent']             = 'group_scf_sanitize_php';
		acf_add_local_field( $field );
		$this->local_field_keys[] = $field['key'];

		ob_start();
		acf_render_field_wrap( acf_get_field( $field['key'] ) );
		$html = ob_get_clean();

		$processor = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag( array( 'class_name' => 'acf-field' ) ) );
		$this->assertNull( $processor->get_attribute( 'onclick' ) );
		$this->assertNull( $processor->get_attribute( 'onfocus' ) );
		$this->assertSame( 'scf-half', $processor->get_attribute( 'id' ) );
	}
}
