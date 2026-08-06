<?php
/**
 * Behavior tests for front-end form authorization grants.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

acf_include( 'includes/forms/form-front.php' );

/**
 * Class Test_Form_Front_Authorization.
 *
 * @group forms
 * @group security
 */
class Test_Form_Front_Authorization extends BaseTestCase {

	/**
	 * Isolated form registry used by each test.
	 *
	 * @var acf_form_front
	 */
	private $form_front;

	/**
	 * Local field keys registered by the current test.
	 *
	 * @var string[]
	 */
	private $field_keys = array();

	/**
	 * Post IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $post_ids = array();

	/**
	 * Hooks registered by the current test.
	 *
	 * @var array[]
	 */
	private $test_hooks = array();

	/**
	 * Set up an anonymous, isolated front-end request.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		$_FILES   = array();

		wp_set_current_user( 0 );
		acf_get_store( 'form' )->reset();
		acf_reset_validation_errors();

		$this->form_front = new acf_form_front();
		$this->detach_request_hooks( $this->form_front );
	}

	/**
	 * Clean up request state and fixtures.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->test_hooks as $hook ) {
			remove_filter( $hook['name'], $hook['callback'], $hook['priority'] );
		}

		foreach ( array_reverse( $this->field_keys ) as $field_key ) {
			acf_remove_local_field( $field_key );
		}

		foreach ( array_unique( $this->post_ids ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		$_FILES   = array();

		unset( $GLOBALS['acf_form'] );
		acf_get_store( 'form' )->reset();
		acf_reset_validation_errors();
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Provides inline and registered forms.
	 *
	 * @return array
	 */
	public function form_transport_provider(): array {
		return array(
			'inline form'     => array( false ),
			'registered form' => array( true ),
		);
	}

	/**
	 * A valid anonymous grant saves a rendered field for inline and registered forms.
	 *
	 * @dataProvider form_transport_provider
	 *
	 * @param bool $registered Whether the form is registered by ID.
	 * @return void
	 */
	public function test_valid_anonymous_inline_and_registered_grants_save_rendered_field( bool $registered ): void {
		$suffix     = $registered ? 'registered' : 'inline';
		$field_key  = 'field_front_grant_valid_' . $suffix;
		$field_name = 'front_grant_valid_' . $suffix;
		$post_id    = $this->create_post( 'Valid anonymous ' . $suffix );

		$this->register_text_field( $field_key, $field_name );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-valid-' . $suffix,
				'post_id'  => $post_id,
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'return'   => '',
			),
			$registered
		);

		$grant = $this->decode_grant_token( $request['_acf_form_meta'][0] );
		$this->assertSame(
			array(
				'blog_id',
				'render_id',
				'issued_at',
				'form_anchor',
				'target_post_id',
				'allowed_field_keys',
				'post_title',
				'post_content',
			),
			array_keys( $grant )
		);
		$this->assertSame( get_current_blog_id(), $grant['blog_id'] );
		$this->assertSame( hash( 'sha256', $request['_acf_form'] ), $grant['form_anchor'] );
		$this->assertSame( (string) $post_id, $grant['target_post_id'] );
		$this->assertSame( array( $field_key ), $grant['allowed_field_keys'] );

		$this->submit_request( $request, array( $field_key => 'anonymous value' ) );

		$this->assertSame( 0, get_current_user_id() );
		$this->assertSame( 'anonymous value', get_post_meta( $post_id, $field_name, true ) );
	}

	/**
	 * Inline and registered new-post forms keep sensitive settings out of the
	 * grant and still use them when creating the post.
	 *
	 * @dataProvider form_transport_provider
	 *
	 * @param bool $registered Whether the form is registered by ID.
	 * @return void
	 */
	public function test_valid_new_post_grant_uses_server_side_settings_without_exposing_them( bool $registered ): void {
		$suffix          = $registered ? 'registered' : 'inline';
		$field_key       = 'field_front_grant_new_secret_' . $suffix;
		$field_name      = 'front_grant_new_secret_' . $suffix;
		$password_canary = 'front-grant-password-' . $suffix;
		$meta_canary     = 'front-grant-private-meta-' . $suffix;
		$created_id      = 0;

		$this->register_text_field( $field_key, $field_name );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-new-secret-' . $suffix,
				'post_id'  => 'new_post',
				'new_post' => array(
					'post_type'     => 'post',
					'post_status'   => 'draft',
					'post_title'    => 'Verified new-post settings',
					'post_password' => $password_canary,
					'meta_input'    => array(
						'_front_grant_private_meta' => $meta_canary,
					),
				),
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'return'   => '',
			),
			$registered
		);

		$grant = $this->decode_grant_token( $request['_acf_form_meta'][0] );
		$this->assertArrayHasKey( 'new_post_fingerprint', $grant );
		$this->assertArrayNotHasKey( 'new_post', $grant );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $grant['new_post_fingerprint'] );

		$token_payload = base64_decode( explode( '.', $request['_acf_form_meta'][0], 2 )[0], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Inspects the public transport for sensitive values.
		$this->assertIsString( $token_payload );
		$this->assertStringNotContainsString( $password_canary, $token_payload );
		$this->assertStringNotContainsString( $meta_canary, $token_payload );
		$this->assertStringNotContainsString( '"new_post":', $token_payload );

		$this->add_test_hook(
			'acf/submit_form',
			function ( $form, $post_id ) use ( &$created_id ) {
				$created_id       = (int) $post_id;
				$this->post_ids[] = $created_id;
			},
			10,
			2
		);

		$this->submit_request( $request, array( $field_key => 'verified field value' ) );

		$created_post = get_post( $created_id );
		$this->assertInstanceOf( WP_Post::class, $created_post );
		$this->assertSame( $password_canary, $created_post->post_password );
		$this->assertSame( $meta_canary, get_post_meta( $created_id, '_front_grant_private_meta', true ) );
		$this->assertSame( 'verified field value', get_post_meta( $created_id, $field_name, true ) );
	}

	/**
	 * A CBC-retargeted form is rejected while keeping its original authorization.
	 *
	 * @return void
	 */
	public function test_cbc_retargeted_form_is_rejected_without_side_effects(): void {
		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
			$this->markTestSkipped( 'OpenSSL is required to reproduce the CBC manipulation.' );
		}

		$field_key    = 'field_front_grant_cbc_retarget';
		$field_name   = 'front_grant_cbc_retarget';
		$victim_title = 'Private CBC victim';
		// The forgery below rewrites a single 16-byte block, so the retargeted
		// JSON has to leave room for at least one byte of padding.
		$victim_id  = $this->create_post_with_short_id( $victim_title );
		$front      = $this->create_probe();
		$updated_id = wp_update_post(
			array(
				'ID'          => $victim_id,
				'post_status' => 'private',
			)
		);

		$this->assertSame( $victim_id, $updated_id );
		$this->register_text_field( $field_key, $field_name );

		$request = $this->render_request(
			array(
				'post_id'  => 'new_post',
				'new_post' => array(
					'post_type'   => 'post',
					'post_status' => 'draft',
					'post_title'  => 'Authorized new post',
				),
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'kses'     => true,
				'return'   => 'https://example.test/must-not-redirect',
			),
			false,
			$front
		);

		$original_form  = $request['_acf_form'];
		$original_nonce = $request['_acf_nonce'];
		$original_grant = $request['_acf_form_meta'];
		$original_json  = acf_decrypt( $original_form );
		$known_block    = '{"id":"acf-form"';
		$block_size     = 16;

		$this->assertIsString( $original_json );
		$this->assertSame( $known_block, substr( $original_json, 0, $block_size ) );

		$retargeted_json = wp_json_encode( array( 'post_id' => $victim_id ) );
		$this->assertIsString( $retargeted_json );
		$this->assertLessThan( $block_size, strlen( $retargeted_json ) );

		$padding_length   = $block_size - strlen( $retargeted_json );
		$retargeted_block = $retargeted_json . str_repeat( chr( $padding_length ), $padding_length );
		$encrypted_form   = base64_decode( $original_form, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reproduces the documented form-token transport.
		$this->assertIsString( $encrypted_form );

		$encrypted_parts = explode( '::', $encrypted_form, 2 );
		$this->assertCount( 2, $encrypted_parts );
		$ciphertext = base64_decode( $encrypted_parts[0], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reproduces the documented CBC ciphertext truncation.
		$this->assertIsString( $ciphertext );
		$this->assertGreaterThanOrEqual( $block_size, strlen( $ciphertext ) );
		$this->assertSame( $block_size, strlen( $encrypted_parts[1] ) );

		$retargeted_iv   = $encrypted_parts[1] ^ $known_block ^ $retargeted_block;
		$truncated_block = base64_encode( substr( $ciphertext, 0, $block_size ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Reproduces the documented CBC ciphertext truncation.
		$retargeted_form = base64_encode( $truncated_block . '::' . $retargeted_iv ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Recreates the documented form-token transport.

		$this->assertSame( $retargeted_json, acf_decrypt( $retargeted_form ) );
		$this->assertNotSame( $original_form, $retargeted_form );

		$grant = $this->decode_grant_token( $original_grant[0] );
		$this->assertSame( hash( 'sha256', $original_form ), $grant['form_anchor'] );
		$this->assertNotSame( hash( 'sha256', $retargeted_form ), $grant['form_anchor'] );

		$request['_acf_form'] = $retargeted_form;
		$this->assertSame( $original_nonce, $request['_acf_nonce'] );
		$this->assertSame( $original_grant, $request['_acf_form_meta'] );

		$values = array( $field_key => '<script>victim overwrite</script>' );
		$this->assert_request_is_rejected_without_side_effects( $front, $request, $values, $victim_id, $field_name );

		$victim = get_post( $victim_id );
		$this->assertInstanceOf( WP_Post::class, $victim );
		$this->assertSame( $victim_title, $victim->post_title );
		$this->assertSame( 'private', $victim->post_status );
		$this->assertSame( '', get_post_meta( $victim_id, $field_name, true ) );
	}

	/**
	 * A form with form=false still emits and accepts a grant.
	 *
	 * @return void
	 */
	public function test_form_false_emits_and_accepts_a_grant(): void {
		$field_key  = 'field_front_grant_form_false';
		$field_name = 'front_grant_form_false';
		$post_id    = $this->create_post( 'Form false grant' );
		$html       = '';

		$this->register_text_field( $field_key, $field_name );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-form-false',
				'post_id'  => $post_id,
				'fields'   => array( $field_key ),
				'form'     => false,
				'honeypot' => false,
				'return'   => '',
			),
			false,
			$this->form_front,
			$html
		);

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertNotEmpty( $request['_acf_form_meta'] );

		$this->submit_request( $request, array( $field_key => 'wrapperless value' ) );

		$this->assertSame( 'wrapperless value', get_post_meta( $post_id, $field_name, true ) );
	}

	/**
	 * Sibling forms combine their allowed roots only when they share a target.
	 *
	 * @return void
	 */
	public function test_two_forms_in_one_render_combine_roots_for_the_same_target(): void {
		$field_a_key   = 'field_front_grant_union_a';
		$field_a_name  = 'front_grant_union_a';
		$field_b_key   = 'field_front_grant_union_b';
		$field_b_name  = 'front_grant_union_b';
		$other_key     = 'field_front_grant_union_other';
		$other_name    = 'front_grant_union_other';
		$post_id       = $this->create_post( 'Shared render target' );
		$other_post_id = $this->create_post( 'Other render target' );

		$this->register_text_field( $field_a_key, $field_a_name );
		$this->register_text_field( $field_b_key, $field_b_name );
		$this->register_text_field( $other_key, $other_name );

		$request = $this->render_multiple_request(
			array(
				array(
					'id'       => 'front-grant-union-other',
					'post_id'  => $other_post_id,
					'fields'   => array( $other_key ),
					'form'     => false,
					'honeypot' => false,
					'return'   => '',
				),
				array(
					'id'       => 'front-grant-union-a',
					'post_id'  => $post_id,
					'fields'   => array( $field_a_key ),
					'form'     => false,
					'honeypot' => false,
					'return'   => '',
				),
				array(
					'id'       => 'front-grant-union-b',
					'post_id'  => $post_id,
					'fields'   => array( $field_b_key ),
					'form'     => false,
					'honeypot' => false,
					'return'   => '',
				),
			)
		);

		$this->assertCount( 3, $request['_acf_form_meta'] );

		$this->submit_request(
			$request,
			array(
				$field_a_key => 'value from A',
				$field_b_key => 'value from B',
				$other_key   => 'value from another target',
			)
		);

		$this->assertSame( 'value from A', get_post_meta( $post_id, $field_a_name, true ) );
		$this->assertSame( 'value from B', get_post_meta( $post_id, $field_b_name, true ) );
		$this->assertSame( '', get_post_meta( $post_id, $other_name, true ) );
		$this->assertSame( '', get_post_meta( $other_post_id, $other_name, true ) );
	}

	/**
	 * A sibling grant enables the title field for a shared target even when the
	 * last rendered form left it off.
	 *
	 * @return void
	 */
	public function test_sibling_grant_raises_the_title_flag(): void {
		$titled_key = 'field_front_grant_flags_titled';
		$plain_key  = 'field_front_grant_flags_plain';
		$plain_name = 'front_grant_flags_plain';
		$post_id    = $this->create_post( 'Sibling flag target' );

		$this->register_text_field( $titled_key, 'front_grant_flags_titled' );
		$this->register_text_field( $plain_key, $plain_name );

		// The primary form is the last one rendered, and it leaves the title off.
		$request = $this->render_multiple_request(
			array(
				array(
					'id'         => 'front-grant-flags-titled',
					'post_id'    => $post_id,
					'fields'     => array( $titled_key ),
					'post_title' => true,
					'form'       => false,
					'honeypot'   => false,
					'return'     => '',
				),
				array(
					'id'       => 'front-grant-flags-plain',
					'post_id'  => $post_id,
					'fields'   => array( $plain_key ),
					'form'     => false,
					'honeypot' => false,
					'return'   => '',
				),
			)
		);

		$this->submit_request(
			$request,
			array(
				'_post_title' => 'Title from the sibling grant',
				$plain_key    => 'plain value',
			)
		);

		$this->assertSame( 'Title from the sibling grant', get_post_field( 'post_title', $post_id ) );
		$this->assertSame( 'plain value', get_post_meta( $post_id, $plain_name, true ) );
	}

	/**
	 * The grant decides whether the title and content are saved, even when the
	 * registered form enabled them after the page was rendered.
	 *
	 * @return void
	 */
	public function test_grant_flags_beat_settings_changed_after_render(): void {
		$field_key  = 'field_front_grant_flags_stale';
		$field_name = 'front_grant_flags_stale';
		$post_id    = $this->create_post( 'Stale flag target' );
		$front      = $this->create_probe();
		$form       = array(
			'id'           => 'front-grant-flags-stale',
			'post_id'      => $post_id,
			'fields'       => array( $field_key ),
			'post_title'   => false,
			'post_content' => false,
			'honeypot'     => false,
			'return'       => '',
		);

		$this->register_text_field( $field_key, $field_name );
		$this->ensure_wysiwyg_field_type();

		// Sign both roots into the grant while the form keeps the flags off, so
		// they survive pruning and only the signed flags can stop the save.
		$this->add_test_hook(
			'acf/form/allowed_field_keys',
			static function ( $keys ) {
				$keys[] = '_post_title';
				$keys[] = '_post_content';

				return $keys;
			},
			10,
			2
		);

		$request = $this->render_request( $form, true, $front );

		// The registration changes after the browser received the form.
		$form['post_title']   = true;
		$form['post_content'] = true;
		$front->add_form( $form );

		$this->submit_request(
			$request,
			array(
				'_post_title'   => 'Title from a stale registration',
				'_post_content' => 'Content from a stale registration',
				$field_key      => 'stale flag value',
			),
			$front
		);

		$this->assertSame( 'Stale flag target', get_post_field( 'post_title', $post_id ) );
		$this->assertSame( '', get_post_field( 'post_content', $post_id ) );
		$this->assertSame( 'stale flag value', get_post_meta( $post_id, $field_name, true ) );
	}

	/**
	 * A trusted pre-submit callback may change the destination without adding
	 * roots from a different rendered destination.
	 *
	 * @return void
	 */
	public function test_pre_submit_callback_can_change_new_post_destination(): void {
		$primary_key  = 'field_front_grant_new_primary';
		$primary_name = 'front_grant_new_primary';
		$other_key    = 'field_front_grant_new_other';
		$other_name   = 'front_grant_new_other';
		$created_id   = 0;

		$this->register_text_field( $primary_key, $primary_name );
		$this->register_text_field( $other_key, $other_name );

		$request = $this->render_multiple_request(
			array(
				array(
					'id'       => 'front-grant-new-other',
					'post_id'  => 'new_post',
					'new_post' => array(
						'post_type'   => 'page',
						'post_status' => 'publish',
						'post_title'  => 'Other signed destination',
					),
					'fields'   => array( $other_key ),
					'form'     => false,
					'honeypot' => false,
					'return'   => '',
				),
				array(
					'id'       => 'front-grant-new-primary',
					'post_id'  => 'new_post',
					'new_post' => array(
						'post_type'   => 'post',
						'post_status' => 'draft',
						'post_title'  => 'Primary signed destination',
					),
					'fields'   => array( $primary_key ),
					'form'     => false,
					'honeypot' => false,
					'return'   => '',
				),
			)
		);

		$this->add_test_hook(
			'acf/pre_submit_form',
			static function ( $form ) {
				$form['new_post'] = array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => 'Callback destination',
				);
				return $form;
			}
		);
		$this->add_test_hook(
			'acf/submit_form',
			function ( $form, $post_id ) use ( &$created_id ) {
				$created_id       = (int) $post_id;
				$this->post_ids[] = $created_id;
			},
			10,
			2
		);

		$this->submit_request(
			$request,
			array(
				$primary_key => 'primary value',
				$other_key   => 'other value',
			)
		);

		$created_post = get_post( $created_id );
		$this->assertInstanceOf( WP_Post::class, $created_post );
		$this->assertSame( 'page', $created_post->post_type );
		$this->assertSame( 'publish', $created_post->post_status );
		$this->assertSame( 'Callback destination', $created_post->post_title );
		$this->assertSame( 'primary value', get_post_meta( $created_id, $primary_name, true ) );
		$this->assertSame( '', get_post_meta( $created_id, $other_name, true ) );
	}

	/**
	 * Sibling new-post forms combine roots when their settings differ only in
	 * associative key order, including in nested arrays.
	 *
	 * @return void
	 */
	public function test_new_post_siblings_with_reordered_maps_combine_their_roots(): void {
		$field_a_key  = 'field_front_grant_new_order_a';
		$field_a_name = 'front_grant_new_order_a';
		$field_b_key  = 'field_front_grant_new_order_b';
		$field_b_name = 'front_grant_new_order_b';
		$created_id   = 0;

		$this->register_text_field( $field_a_key, $field_a_name );
		$this->register_text_field( $field_b_key, $field_b_name );

		$request = $this->render_multiple_request(
			array(
				array(
					'id'       => 'front-grant-new-order-a',
					'post_id'  => 'new_post',
					'new_post' => array(
						'post_type'   => 'post',
						'post_status' => 'draft',
						'post_title'  => 'Canonical new-post destination',
						'meta_input'  => array(
							'_front_grant_order_a' => 'a',
							'_front_grant_order_b' => 'b',
						),
						'order_probe' => array(
							1    => 'integer key',
							'01' => 'numeric-looking string key',
						),
					),
					'fields'   => array( $field_a_key ),
					'form'     => false,
					'honeypot' => false,
					'return'   => '',
				),
				array(
					'id'       => 'front-grant-new-order-b',
					'post_id'  => 'new_post',
					'new_post' => array(
						'post_status' => 'draft',
						'meta_input'  => array(
							'_front_grant_order_b' => 'b',
							'_front_grant_order_a' => 'a',
						),
						'order_probe' => array(
							'01' => 'numeric-looking string key',
							1    => 'integer key',
						),
						'post_title'  => 'Canonical new-post destination',
						'post_type'   => 'post',
					),
					'fields'   => array( $field_b_key ),
					'form'     => false,
					'honeypot' => false,
					'return'   => '',
				),
			)
		);

		$grant_a = $this->decode_grant_token( $request['_acf_form_meta'][0] );
		$grant_b = $this->decode_grant_token( $request['_acf_form_meta'][1] );
		$this->assertSame( $grant_a['new_post_fingerprint'], $grant_b['new_post_fingerprint'] );

		$this->add_test_hook(
			'acf/submit_form',
			function ( $form, $post_id ) use ( &$created_id ) {
				$created_id       = (int) $post_id;
				$this->post_ids[] = $created_id;
			},
			10,
			2
		);

		$this->submit_request(
			$request,
			array(
				$field_a_key => 'value from reordered A',
				$field_b_key => 'value from reordered B',
			)
		);

		$this->assertGreaterThan( 0, $created_id );
		$this->assertSame( 'value from reordered A', get_post_meta( $created_id, $field_a_name, true ) );
		$this->assertSame( 'value from reordered B', get_post_meta( $created_id, $field_b_name, true ) );
	}

	/**
	 * A registered form rejects new-post settings changed after rendering.
	 *
	 * @return void
	 */
	public function test_registered_new_post_rejects_settings_changed_after_render(): void {
		$field_key    = 'field_front_grant_new_drift';
		$field_name   = 'front_grant_new_drift';
		$form_id      = 'front-grant-new-drift';
		$canary_post  = $this->create_post( 'New-post drift canary' );
		$render_front = $this->create_probe();
		$submit_front = $this->create_probe();

		$this->register_text_field( $field_key, $field_name );

		$request = $this->render_request(
			array(
				'id'       => $form_id,
				'post_id'  => 'new_post',
				'new_post' => array(
					'post_type'   => 'post',
					'post_status' => 'draft',
					'post_title'  => 'Rendered destination',
				),
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'return'   => '',
			),
			true,
			$render_front
		);

		$submit_front->add_form(
			array(
				'id'       => $form_id,
				'post_id'  => 'new_post',
				'new_post' => array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => 'Submit-time destination',
				),
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'return'   => '',
			)
		);

		$this->assert_request_is_rejected_without_side_effects(
			$submit_front,
			$request,
			array( $field_key => 'must not persist' ),
			$canary_post,
			$field_name
		);
	}

	/**
	 * A nested Group submits its parent as the allowed top-level root.
	 *
	 * @return void
	 */
	public function test_nested_group_uses_its_parent_as_the_authorized_root(): void {
		$parent_key = 'field_front_grant_group';
		$child_key  = 'field_front_grant_group_child';
		$post_id    = $this->create_post( 'Nested Group target' );
		$observed   = null;

		$this->register_group_field( $parent_key, $child_key );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-group',
				'post_id'  => $post_id,
				'fields'   => array( $parent_key ),
				'honeypot' => false,
				'return'   => '',
			)
		);

		$this->add_test_hook(
			'acf/validate_save_post',
			function () use ( &$observed ) {
				$observed = $this->posted_fields();
			},
			20,
			0
		);

		$nested_value = array( $child_key => 'nested group value' );
		$this->submit_request(
			$request,
			array(
				$parent_key => $nested_value,
				$child_key  => 'unauthorized top-level child',
			)
		);

		$this->assertSame( array( $parent_key => $nested_value ), $observed );
	}

	/**
	 * Fields added by the render-time filter become part of the signed grant.
	 *
	 * @return void
	 */
	public function test_render_time_allowed_field_filter_extension_is_signed(): void {
		$base_key   = 'field_front_grant_render_filter_base';
		$extra_key  = 'field_front_grant_render_filter_extra';
		$extra_name = 'front_grant_render_filter_extra';
		$post_id    = $this->create_post( 'Render filter target' );
		$calls      = 0;
		$callback   = static function ( $keys ) use ( $extra_key, &$calls ) {
			++$calls;
			$keys[] = $extra_key;
			return $keys;
		};

		$this->register_text_field( $base_key, 'front_grant_render_filter_base' );
		$this->register_text_field( $extra_key, $extra_name );
		$this->add_test_hook( 'acf/form/allowed_field_keys', $callback, 10, 2 );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-render-filter',
				'post_id'  => $post_id,
				'fields'   => array( $base_key ),
				'honeypot' => false,
				'return'   => '',
			)
		);

		$this->submit_request(
			$request,
			array(
				$base_key  => 'base value',
				$extra_key => 'render-filter value',
			)
		);

		// The filter runs once, at render, and its extension is signed into the
		// grant. Submission reuses the grant without running the filter again.
		$this->assertSame( 1, $calls );
		$this->assertSame( 'render-filter value', get_post_meta( $post_id, $extra_name, true ) );
	}

	/**
	 * The signed grant is authoritative at submit. An acf/form/allowed_field_keys
	 * callback running during submission does not fire and cannot change the
	 * accepted roots; only the render-time result, folded into the grant, applies.
	 *
	 * @return void
	 */
	public function test_submit_time_allowed_field_filter_cannot_change_the_signed_grant(): void {
		$kept_key         = 'field_front_grant_submit_filter_kept';
		$kept_name        = 'front_grant_submit_filter_kept';
		$restricted_key   = 'field_front_grant_submit_filter_restricted';
		$restricted_name  = 'front_grant_submit_filter_restricted';
		$extra_key        = 'field_front_grant_submit_filter_extra';
		$extra_name       = 'front_grant_submit_filter_extra';
		$post_id          = $this->create_post( 'Submit filter target' );
		$ran_after_render = false;

		$this->register_text_field( $kept_key, $kept_name );
		$this->register_text_field( $restricted_key, $restricted_name );
		$this->register_text_field( $extra_key, $extra_name );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-submit-filter',
				'post_id'  => $post_id,
				'fields'   => array( $kept_key, $restricted_key ),
				'honeypot' => false,
				'return'   => '',
			)
		);

		$this->add_test_hook(
			'acf/pre_submit_form',
			static function ( $form ) {
				$form['submit_filter_marker'] = true;
				return $form;
			}
		);
		$this->add_test_hook(
			'acf/form/allowed_field_keys',
			static function ( $keys, $form ) use ( $kept_key, $extra_key, &$ran_after_render ) {
				if ( ! empty( $form['submit_filter_marker'] ) ) {
					$ran_after_render = true;
				}
				return array( $kept_key, $extra_key );
			},
			10,
			2
		);

		$this->submit_request(
			$request,
			array(
				$kept_key       => 'kept value',
				$restricted_key => 'restricted value',
				$extra_key      => 'unsigned extra value',
			)
		);

		$this->assertFalse( $ran_after_render, 'The allowlist filter must not run during submission.' );
		$this->assertSame( 'kept value', get_post_meta( $post_id, $kept_name, true ) );
		$this->assertSame( 'restricted value', get_post_meta( $post_id, $restricted_name, true ) );
		$this->assertSame( '', get_post_meta( $post_id, $extra_name, true ) );
	}

	/**
	 * Provides invalid grant cases.
	 *
	 * @return array
	 */
	public function invalid_grant_provider(): array {
		return array(
			'missing token'   => array( 'missing' ),
			'tampered token'  => array( 'tampered' ),
			'invalid sibling' => array( 'sibling' ),
			'expired token'   => array( 'expired' ),
			'future token'    => array( 'future' ),
			'zero TTL'        => array( 'zero_ttl' ),
			'wrong site'      => array( 'site' ),
			'wrong render'    => array( 'render' ),
			'wrong target'    => array( 'target' ),
			'wrong post_id'   => array( 'submitted_target' ),
			'wrong flags'     => array( 'flags' ),
			'wrong new_post'  => array( 'new_post' ),
			'extra grant key' => array( 'extra_key' ),
			'duplicate roots' => array( 'duplicate_roots' ),
		);
	}

	/**
	 * The handler rejects invalid grants before KSES, validation, submission
	 * hooks, saving, and redirects.
	 *
	 * @dataProvider invalid_grant_provider
	 *
	 * @param string $variant Invalid grant variant.
	 * @return void
	 */
	public function test_invalid_grants_stop_before_processing( string $variant ): void {
		$suffix     = str_replace( '_', '-', $variant );
		$field_key  = 'field_front_grant_invalid_' . $variant;
		$field_name = 'front_grant_invalid_' . $variant;
		$post_id    = $this->create_post( 'Invalid grant ' . $variant );
		$front      = $this->create_probe();
		$form       = array(
			'id'       => 'front-grant-invalid-' . $suffix,
			'post_id'  => $post_id,
			'fields'   => array( $field_key ),
			'honeypot' => false,
			'kses'     => true,
			'return'   => 'https://example.test/must-not-redirect',
		);

		if ( 'new_post' === $variant ) {
			$form['post_id']  = 'new_post';
			$form['new_post'] = array(
				'post_type'   => 'post',
				'post_status' => 'draft',
				'post_title'  => 'Signed new post',
			);
		}

		$this->register_text_field( $field_key, $field_name );
		$request = $this->render_request( $form, true, $front );
		$values  = array( $field_key => '<script>unsanitized canary</script>' );

		if ( 'missing' === $variant ) {
			unset( $request['_acf_form_meta'] );
		} elseif ( 'submitted_target' === $variant ) {
			// The grant still matches the resolved form, only the browser's copy moved.
			$request['_acf_post_id'] = (string) $this->create_post( 'Mismatched submitted target' );
		} elseif ( 'sibling' === $variant ) {
			$sibling_token                 = $request['_acf_form_meta'][0];
			$last_character                = substr( $sibling_token, -1 );
			$request['_acf_form_meta'][]   = substr( $sibling_token, 0, -1 );
			$request['_acf_form_meta'][1] .= 'a' === $last_character ? 'b' : 'a';
		} else {
			$token_parts = explode( '.', $request['_acf_form_meta'][0], 2 );
			$this->assertCount( 2, $token_parts );
			$grant = $this->decode_grant_token( $request['_acf_form_meta'][0] );

			if ( 'tampered' === $variant ) {
				$grant['allowed_field_keys'][] = 'field_front_grant_unsigned';
				$request['_acf_form_meta'][0]  = $this->encode_grant_token( $grant, $token_parts[1] );
			} else {
				if ( 'expired' === $variant ) {
					$grant['issued_at'] = time() - 61;
					$this->add_test_hook(
						'acf/form/meta_ttl',
						static function () {
							return 60;
						}
					);
				} elseif ( 'future' === $variant ) {
					$grant['issued_at'] = time() + 2 * MINUTE_IN_SECONDS;
				} elseif ( 'zero_ttl' === $variant ) {
					$grant['issued_at'] = time() + 30;
					$this->add_test_hook(
						'acf/form/meta_ttl',
						static function () {
							return 0;
						}
					);
				} elseif ( 'site' === $variant ) {
					$grant['blog_id'] = get_current_blog_id() + 1;
				} elseif ( 'render' === $variant ) {
					$grant['render_id'] .= '-wrong-render';
				} elseif ( 'target' === $variant ) {
					$grant['target_post_id'] = (string) $this->create_post( 'Wrong target' );
				} elseif ( 'flags' === $variant ) {
					$grant['post_title']   = 'not-a-boolean';
					$grant['post_content'] = 1;
				} elseif ( 'new_post' === $variant ) {
					$grant['new_post_fingerprint'] = str_repeat( '0', 64 );
				} elseif ( 'extra_key' === $variant ) {
					$grant['unexpected_claim'] = 'unsigned addition';
				} elseif ( 'duplicate_roots' === $variant ) {
					$grant['allowed_field_keys'][] = $grant['allowed_field_keys'][0];
				}

				// Re-sign these cases so they reach the schema or context check
				// instead of failing the HMAC check first.
				$request['_acf_form_meta'][0] = $this->encode_grant_token( $grant );
			}
		}

		$this->assert_request_is_rejected_without_side_effects( $front, $request, $values, $post_id, $field_name );
	}

	/**
	 * A grant dated 30 seconds in the future remains valid.
	 *
	 * @return void
	 */
	public function test_grant_with_minor_future_clock_skew_is_accepted(): void {
		$field_key  = 'field_front_grant_clock_skew';
		$field_name = 'front_grant_clock_skew';
		$post_id    = $this->create_post( 'Clock-skew target' );

		$this->register_text_field( $field_key, $field_name );
		$request = $this->render_request(
			array(
				'id'       => 'front-grant-clock-skew',
				'post_id'  => $post_id,
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'return'   => '',
			)
		);

		$grant              = $this->decode_grant_token( $request['_acf_form_meta'][0] );
		$grant['issued_at'] = time() + 30;

		$request['_acf_form_meta'][0] = $this->encode_grant_token( $grant );
		$this->submit_request( $request, array( $field_key => 'accepted across skew' ) );

		$this->assertSame( 'accepted across skew', get_post_meta( $post_id, $field_name, true ) );
	}

	/**
	 * Provides a normal ID swap and an ID pair that collides after sanitization.
	 *
	 * @return array
	 */
	public function registered_form_swap_provider(): array {
		return array(
			'ordinary IDs'           => array(
				'front-grant-rendered-a',
				'front-grant-unrendered-b',
				false,
			),
			'sanitization collision' => array(
				'front-grant-collision',
				'<b>front-grant-collision</b>',
				true,
			),
		);
	}

	/**
	 * Registered form A cannot authorize form B when B was not rendered.
	 *
	 * @dataProvider registered_form_swap_provider
	 *
	 * @param string $form_a_id         Rendered form ID.
	 * @param string $form_b_id         Unrendered form ID submitted by the browser.
	 * @param bool   $expect_collision  Whether legacy sanitization maps B to A.
	 * @return void
	 */
	public function test_registered_form_swap_is_rejected_even_for_an_exact_id_sanitization_collision(
		string $form_a_id,
		string $form_b_id,
		bool $expect_collision
	): void {
		$field_a_key  = 'field_front_grant_swap_a';
		$field_b_key  = 'field_front_grant_swap_b';
		$field_b_name = 'front_grant_swap_b';
		$post_id      = $this->create_post( 'Registered swap target' );
		$front        = $this->create_probe();

		$this->register_text_field( $field_a_key, 'front_grant_swap_a' );
		$this->register_text_field( $field_b_key, $field_b_name );

		$front->add_form(
			array(
				'id'         => $form_b_id,
				'post_id'    => $post_id,
				'fields'     => array( $field_b_key ),
				'post_title' => true,
				'honeypot'   => false,
				'return'     => 'https://example.test/must-not-redirect',
			)
		);

		$request = $this->render_request(
			array(
				'id'       => $form_a_id,
				'post_id'  => $post_id,
				'fields'   => array( $field_a_key ),
				'honeypot' => false,
				'return'   => 'https://example.test/must-not-redirect',
			),
			true,
			$front
		);

		if ( $expect_collision ) {
			$this->assertSame(
				$form_a_id,
				acf_sanitize_request_args( sanitize_text_field( $form_b_id ) ),
				'The fixture must collide after the legacy registered-form ID sanitization.'
			);
		}

		$request['_acf_form'] = $form_b_id;

		$this->assert_request_is_rejected_without_side_effects(
			$front,
			$request,
			array(
				$field_b_key  => '<script>unrendered form value</script>',
				'_post_title' => 'Unrendered form title',
			),
			$post_id,
			$field_b_name
		);
		$this->assertSame( 'Registered swap target', get_post_field( 'post_title', $post_id ) );
	}

	/**
	 * The save path removes a root reintroduced by acf/pre_save_post.
	 *
	 * @return void
	 */
	public function test_root_reintroduced_at_pre_save_post_is_pruned(): void {
		$allowed_key  = 'field_front_grant_reintroduced_allowed';
		$allowed_name = 'front_grant_reintroduced_allowed';
		$canary_key   = 'field_front_grant_reintroduced_canary';
		$canary_name  = 'front_grant_reintroduced_canary';
		$post_id      = $this->create_post( 'Reintroduced root target' );
		$save_keys    = null;

		$this->register_text_field( $allowed_key, $allowed_name );
		$this->register_text_field( $canary_key, $canary_name );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-reintroduced',
				'post_id'  => $post_id,
				'fields'   => array( $allowed_key ),
				'honeypot' => false,
				'return'   => '',
			)
		);

		$this->add_test_hook(
			'acf/pre_save_post',
			static function ( $submitted_post_id ) use ( $canary_key ) {
				$_POST['acf'][ $canary_key ] = 'reintroduced canary'; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Simulates a trusted callback mutating a verified request.
				return $submitted_post_id;
			},
			20,
			2
		);
		$this->add_test_hook(
			'acf/save_post',
			function () use ( &$save_keys ) {
				$save_keys = array_keys( $this->posted_fields() );
			},
			1,
			0
		);

		$this->submit_request( $request, array( $allowed_key => 'allowed value' ) );

		$this->assertSame( array( $allowed_key ), $save_keys );
		$this->assertSame( 'allowed value', get_post_meta( $post_id, $allowed_name, true ) );
		$this->assertSame( '', get_post_meta( $post_id, $canary_name, true ) );
	}

	/**
	 * File roots outside the grant are removed without changing an allowed root.
	 *
	 * @return void
	 */
	public function test_files_top_level_branches_are_restricted_by_the_grant(): void {
		$allowed_key = 'field_front_grant_file_allowed';
		$canary_key  = 'field_front_grant_file_canary';
		$post_id     = $this->create_post( 'File root target' );
		$observed    = null;

		$this->register_file_field( $allowed_key, 'front_grant_file_allowed' );
		$this->register_file_field( $canary_key, 'front_grant_file_canary' );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-files',
				'post_id'  => $post_id,
				'fields'   => array( $allowed_key ),
				'uploader' => 'basic',
				'honeypot' => false,
				'return'   => '',
			)
		);

		$allowed_branch = array(
			'name'     => '',
			'type'     => '',
			'tmp_name' => '',
			'error'    => UPLOAD_ERR_NO_FILE,
			'size'     => 0,
		);

		$_FILES['acf'] = array(
			'name'     => array(
				$allowed_key => $allowed_branch['name'],
				$canary_key  => 'canary.txt',
			),
			'type'     => array(
				$allowed_key => $allowed_branch['type'],
				$canary_key  => 'text/plain',
			),
			'tmp_name' => array(
				$allowed_key => $allowed_branch['tmp_name'],
				$canary_key  => '/tmp/canary',
			),
			'error'    => array(
				$allowed_key => $allowed_branch['error'],
				$canary_key  => UPLOAD_ERR_OK,
			),
			'size'     => array(
				$allowed_key => $allowed_branch['size'],
				$canary_key  => 12,
			),
		);

		$this->add_test_hook(
			'acf/pre_save_post',
			static function ( $submitted_post_id ) use ( $canary_key ) {
				// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reintroduces an unsigned file root after the first pruning pass.
				if ( isset( $_FILES['acf'] ) && is_array( $_FILES['acf'] ) ) {
					foreach ( $_FILES['acf'] as $attribute => $branches ) {
						if ( is_array( $branches ) ) {
							$_FILES['acf'][ $attribute ][ $canary_key ] = 'late-' . $attribute;
						}
					}
				}
				// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				return $submitted_post_id;
			},
			PHP_INT_MAX,
			2
		);

		$this->add_test_hook(
			'acf/save_post',
			static function () use ( &$observed ) {
				$observed = isset( $_FILES['acf'] ) && is_array( $_FILES['acf'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Captures the file data passed to acf/save_post.
					? $_FILES['acf'] // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- See above.
					: array();
			},
			1,
			0
		);

		$this->submit_request(
			$request,
			array(
				$allowed_key                 => '',
				$allowed_key . '_file_nonce' => wp_create_nonce( 'acf/file_uploader_nonce/' . $allowed_key ),
				$canary_key                  => '',
				$canary_key . '_file_nonce'  => wp_create_nonce( 'acf/file_uploader_nonce/' . $canary_key ),
			)
		);

		$this->assertIsArray( $observed );
		foreach ( $allowed_branch as $attribute => $value ) {
			$this->assertSame(
				array( $allowed_key => $value ),
				$observed[ $attribute ],
				"Only the authorized root should remain in the {$attribute} branch."
			);
		}
	}

	/**
	 * Direct submit_form($form) calls keep their existing global and hook behavior.
	 *
	 * @return void
	 */
	public function test_direct_submit_form_keeps_global_and_hook_behavior(): void {
		$field_key  = 'field_front_grant_direct';
		$field_name = 'front_grant_direct';
		$post_id    = $this->create_post( 'Direct submit target' );
		$pre_save   = null;
		$submitted  = null;

		$this->register_text_field( $field_key, $field_name );

		$form = $this->form_front->validate_form(
			array(
				'id'       => 'front-grant-direct',
				'post_id'  => $post_id,
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'return'   => '',
			)
		);

		$this->add_test_hook(
			'acf/pre_submit_form',
			static function ( $submitted_form ) {
				$submitted_form['direct_marker'] = 'filtered';
				return $submitted_form;
			}
		);
		$this->add_test_hook(
			'acf/pre_save_post',
			static function ( $submitted_post_id ) use ( &$pre_save ) {
				$pre_save = isset( $GLOBALS['acf_form']['direct_marker'] )
					? $GLOBALS['acf_form']['direct_marker']
					: null;
				return $submitted_post_id;
			},
			20,
			2
		);
		$this->add_test_hook(
			'acf/submit_form',
			static function ( $submitted_form, $submitted_post_id ) use ( &$submitted ) {
				$submitted = array(
					'marker'  => isset( $submitted_form['direct_marker'] ) ? $submitted_form['direct_marker'] : null,
					'post_id' => $submitted_post_id,
				);
			},
			10,
			2
		);

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Direct submit_form() retains its server-side contract.
		$_POST['acf'] = array( $field_key => 'direct value' );
		$_REQUEST     = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$this->form_front->submit_form( $form );

		$this->assertSame( 'filtered', $pre_save );
		$this->assertSame( 'filtered', $GLOBALS['acf_form']['direct_marker'] );
		$this->assertSame(
			array(
				'marker'  => 'filtered',
				'post_id' => $post_id,
			),
			$submitted
		);
		$this->assertSame( 'direct value', get_post_meta( $post_id, $field_name, true ) );
	}

	/**
	 * AJAX rejects an invalid grant before field validators run.
	 *
	 * @return void
	 * @throws RuntimeException If AJAX handling raises an unrelated exception.
	 */
	public function test_ajax_invalid_grant_stops_before_field_validation(): void {
		$field_key = 'field_front_grant_ajax';
		$this->register_text_field( $field_key, 'front_grant_ajax' );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-ajax',
				'post_id'  => 'new_post',
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'return'   => '',
			)
		);

		$token                         = $request['_acf_form_meta'][0];
		$last_character                = substr( $token, -1 );
		$request['_acf_form_meta'][0]  = substr( $token, 0, -1 );
		$request['_acf_form_meta'][0] .= 'a' === $last_character ? 'b' : 'a';

		$validator_calls = 0;
		$this->add_test_hook(
			'acf/validate_value/key=' . $field_key,
			static function ( $valid ) use ( &$validator_calls ) {
				++$validator_calls;
				return $valid;
			},
			PHP_INT_MAX
		);

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact rendered request data is submitted to the AJAX handler under test.
		$_POST           = $request;
		$_POST['action'] = 'acf/validate_save_post';
		$_POST['nonce']  = wp_create_nonce( 'acf_nonce' );
		$_POST['acf']    = array( $field_key => '' );
		$_REQUEST        = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$response = $this->invoke_ajax_validation();
		$this->assertTrue( $response['success'] );
		$this->assertSame( 0, $response['data']['valid'] );
		$this->assertFalse( $response['data']['errors'][0]['input'] );
		$this->assertSame( 0, $validator_calls );
	}

	/**
	 * AJAX can validate a form registered only during front-end rendering, and
	 * validators receive only its signed roots.
	 *
	 * @return void
	 * @throws RuntimeException If AJAX handling raises an unrelated exception.
	 */
	public function test_ajax_accepts_frontend_only_registered_form_grant(): void {
		$field_key           = 'field_front_grant_ajax_registered';
		$unsigned_key        = 'field_front_grant_ajax_unsigned';
		$post_id             = $this->create_post( 'Registered AJAX target' );
		$render_front        = $this->create_probe();
		$submit_filter_calls = 0;

		$this->register_text_field( $field_key, 'front_grant_ajax_registered' );
		$this->register_text_field( $unsigned_key, 'front_grant_ajax_unsigned' );

		$request = $this->render_request(
			array(
				'id'       => 'front-grant-ajax-registered',
				'post_id'  => $post_id,
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'return'   => '',
			),
			true,
			$render_front
		);

		$this->assertFalse( acf()->form_front->get_form( 'front-grant-ajax-registered' ) );

		$validator_calls          = 0;
		$unsigned_validator_calls = 0;
		$this->add_test_hook(
			'acf/validate_value/key=' . $field_key,
			static function ( $valid ) use ( &$validator_calls ) {
				++$validator_calls;
				return $valid;
			},
			PHP_INT_MAX
		);
		$this->add_test_hook(
			'acf/validate_value/key=' . $unsigned_key,
			static function ( $valid ) use ( &$unsigned_validator_calls ) {
				++$unsigned_validator_calls;
				return $valid;
			},
			PHP_INT_MAX
		);
		$this->add_test_hook(
			'acf/form/allowed_field_keys',
			static function ( $keys ) use ( &$submit_filter_calls ) {
				++$submit_filter_calls;
				return $keys;
			},
			10,
			2
		);

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact rendered request data is submitted to the AJAX handler under test.
		$_POST           = $request;
		$_POST['action'] = 'acf/validate_save_post';
		$_POST['nonce']  = wp_create_nonce( 'acf_nonce' );
		$_POST['acf']    = array(
			$field_key    => 'valid AJAX value',
			$unsigned_key => 'must not reach validators',
		);
		$_REQUEST        = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$response = $this->invoke_ajax_validation();
		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['valid'] );
		$this->assertSame( 0, $response['data']['errors'] );
		$this->assertSame( 1, $validator_calls );
		$this->assertSame( 0, $unsigned_validator_calls );
		$this->assertSame( 0, $submit_filter_calls );
		$this->assertSame( array( $field_key ), array_keys( $this->posted_fields() ) );
	}

	/**
	 * AJAX rejects a tampered grant when it cannot resolve the registered form.
	 *
	 * @return void
	 * @throws RuntimeException If AJAX handling raises an unrelated exception.
	 */
	public function test_ajax_frontend_only_registered_form_rejects_tampered_grant(): void {
		$field_key    = 'field_front_grant_ajax_frontend_tampered';
		$post_id      = $this->create_post( 'Registered AJAX tamper target' );
		$render_front = $this->create_probe();

		$this->register_text_field( $field_key, 'front_grant_ajax_frontend_tampered' );
		$request = $this->render_request(
			array(
				'id'       => 'front-grant-ajax-frontend-tampered',
				'post_id'  => $post_id,
				'fields'   => array( $field_key ),
				'honeypot' => false,
				'return'   => '',
			),
			true,
			$render_front
		);

		$token                         = $request['_acf_form_meta'][0];
		$last_character                = substr( $token, -1 );
		$request['_acf_form_meta'][0]  = substr( $token, 0, -1 );
		$request['_acf_form_meta'][0] .= 'a' === $last_character ? 'b' : 'a';

		$validator_calls = 0;
		$this->add_test_hook(
			'acf/validate_value/key=' . $field_key,
			static function ( $valid ) use ( &$validator_calls ) {
				++$validator_calls;
				return $valid;
			},
			PHP_INT_MAX
		);

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact rendered request data is submitted to the AJAX handler under test.
		$_POST           = $request;
		$_POST['action'] = 'acf/validate_save_post';
		$_POST['nonce']  = wp_create_nonce( 'acf_nonce' );
		$_POST['acf']    = array( $field_key => 'must not validate' );
		$_REQUEST        = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$response = $this->invoke_ajax_validation();
		$this->assertTrue( $response['success'] );
		$this->assertSame( 0, $response['data']['valid'] );
		$this->assertSame( 0, $validator_calls );
	}

	/**
	 * The AJAX grant gate applies only to acf_form() submissions. Requests from
	 * the post editor have no front-end markers and follow normal validation.
	 *
	 * @return void
	 * @throws RuntimeException If AJAX handling raises an unrelated exception.
	 */
	public function test_ajax_gate_ignores_validation_without_front_end_markers(): void {
		$field_key = 'field_front_grant_gate_scope';
		$this->register_text_field( $field_key, 'front_grant_gate_scope' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Simulated non-front-end AJAX validation request.
		$_POST    = array(
			'action'      => 'acf/validate_save_post',
			'nonce'       => wp_create_nonce( 'acf_nonce' ),
			'_acf_screen' => 'post',
			'acf'         => array( $field_key => 'post editor value' ),
		);
		$_REQUEST = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$response = $this->invoke_ajax_validation();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['valid'], 'Non-front-end validation must not be gated.' );
		$this->assertSame( 0, $response['data']['errors'] );
	}

	/**
	 * Register a local text field.
	 *
	 * @param string $key  Field key.
	 * @param string $name Field name.
	 * @return void
	 */
	private function register_text_field( string $key, string $name ): void {
		acf_add_local_field(
			array(
				'key'   => $key,
				'name'  => $name,
				'label' => $name,
				'type'  => 'text',
			)
		);
		$this->field_keys[] = $key;
	}

	/**
	 * Registers the wysiwyg field type backing the `_post_content` field.
	 *
	 * Field types normally register on 'init', which never fires in the WorDBless
	 * environment. The per-test hook restore also wipes their filters, so tests
	 * that validate a `_post_content` value re-register the type themselves.
	 *
	 * @return void
	 */
	private function ensure_wysiwyg_field_type(): void {
		acf_include( 'includes/fields/class-acf-field-wysiwyg.php' );

		if ( ! has_filter( 'acf/load_field/type=wysiwyg' ) ) {
			acf_register_field_type( 'acf_field_wysiwyg' );
		}
	}

	/**
	 * Register a local file field.
	 *
	 * @param string $key  Field key.
	 * @param string $name Field name.
	 * @return void
	 */
	private function register_file_field( string $key, string $name ): void {
		acf_add_local_field(
			array(
				'key'   => $key,
				'name'  => $name,
				'label' => $name,
				'type'  => 'file',
			)
		);
		$this->field_keys[] = $key;
	}

	/**
	 * Register a Group and one text child.
	 *
	 * @param string $parent_key Parent field key.
	 * @param string $child_key  Child field key.
	 * @return void
	 */
	private function register_group_field( string $parent_key, string $child_key ): void {
		acf_add_local_field(
			array(
				'key'        => $parent_key,
				'name'       => 'front_grant_group',
				'label'      => 'Grant Group',
				'type'       => 'group',
				'layout'     => 'block',
				'sub_fields' => array(
					array(
						'key'   => $child_key,
						'name'  => 'front_grant_group_child',
						'label' => 'Grant Group Child',
						'type'  => 'text',
					),
				),
			)
		);

		$this->field_keys[] = $parent_key;
		$this->field_keys[] = $child_key;
	}

	/**
	 * Create and track a draft post.
	 *
	 * @param string $title Post title.
	 * @return int
	 */
	private function create_post( string $title ): int {
		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => 'draft',
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * Create and track a draft post whose ID fits in three digits.
	 *
	 * WorDBless hands out post IDs from a counter that only ever increases for
	 * the lifetime of the process, so a post created late in the suite gets a
	 * four-digit ID. Callers that need the ID to stay short pin the counter for
	 * the duration of the insert.
	 *
	 * @param string $title Post title.
	 * @return int
	 */
	private function create_post_with_short_id( string $title ): int {
		$counter = \WorDBless\InsertId::$id;

		\WorDBless\InsertId::$id = 100;

		try {
			$post_id = $this->create_post( $title );
		} finally {
			\WorDBless\InsertId::$id = $counter;
		}

		$this->assertLessThan( 1000, $post_id );

		return $post_id;
	}

	/**
	 * Creates a form handler without duplicate request hooks.
	 *
	 * @return acf_form_front
	 */
	private function create_probe(): acf_form_front {
		$front = new acf_form_front();
		$this->detach_request_hooks( $front );

		return $front;
	}

	/**
	 * Detaches hooks already registered by the plugin's global form handler.
	 *
	 * @param acf_form_front $front Isolated form instance.
	 * @return void
	 */
	private function detach_request_hooks( acf_form_front $front ): void {
		remove_action( 'acf/validate_save_post', array( $front, 'validate_save_post' ), 1 );
		remove_filter( 'acf/pre_save_post', array( $front, 'pre_save_post' ), 5 );
	}

	/**
	 * Renders one form and collects its hidden request values.
	 *
	 * @param array          $form            Form configuration.
	 * @param bool           $registered      Whether to render by registered ID.
	 * @param acf_form_front $front           Form registry.
	 * @param string|null    $rendered_html   Rendered HTML, passed by reference.
	 * @return array
	 */
	private function render_request(
		array $form,
		bool $registered = false,
		?acf_form_front $front = null,
		&$rendered_html = null
	): array {
		$front           = null !== $front ? $front : $this->form_front;
		$render_argument = $form;

		if ( $registered ) {
			$front->add_form( $form );
			$render_argument = $form['id'];
		}

		ob_start();
		$front->render_form( $render_argument );
		$rendered_html = (string) ob_get_clean();

		return $this->request_from_html( $rendered_html );
	}

	/**
	 * Renders several inline forms and collects their shared request data.
	 *
	 * @param array $forms Form configurations.
	 * @return array
	 */
	private function render_multiple_request( array $forms ): array {
		ob_start();
		foreach ( $forms as $form ) {
			$this->form_front->render_form( $form );
		}
		$html = (string) ob_get_clean();

		return $this->request_from_html( $html );
	}

	/**
	 * Extracts hidden ACF form values from rendered HTML.
	 *
	 * @param string $html Rendered HTML.
	 * @return array
	 */
	private function request_from_html( string $html ): array {
		$inputs  = array();
		$request = array();

		preg_match_all( '/<input\b[^>]*type="hidden"[^>]*>/i', $html, $matches );
		foreach ( $matches[0] as $input ) {
			if ( ! preg_match( '/\bname="([^"]*)"/i', $input, $name_match ) ) {
				continue;
			}

			$value = '';
			if ( preg_match( '/\bvalue="([^"]*)"/i', $input, $value_match ) ) {
				$value = html_entity_decode( $value_match[1], ENT_QUOTES, 'UTF-8' );
			}

			$name              = html_entity_decode( $name_match[1], ENT_QUOTES, 'UTF-8' );
			$inputs[ $name ][] = $value;
		}

		foreach ( $inputs as $name => $values ) {
			if ( 0 !== strpos( $name, '_acf_' ) ) {
				continue;
			}

			if ( '_acf_form_meta[]' === $name ) {
				$request['_acf_form_meta'] = $values;
			} else {
				$request[ $name ] = end( $values );
			}
		}

		foreach ( array( '_acf_nonce', '_acf_post_id', '_acf_form', '_acf_render_id', '_acf_form_meta' ) as $required_key ) {
			$this->assertArrayHasKey( $required_key, $request, "Rendered form is missing {$required_key}." );
		}

		return $request;
	}

	/**
	 * Submits ACF values with the hidden inputs captured during rendering.
	 *
	 * @param array          $request Hidden request values.
	 * @param array          $values  Submitted ACF values.
	 * @param acf_form_front $front   Form registry.
	 * @return mixed
	 */
	private function submit_request( array $request, array $values, ?acf_form_front $front = null ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Copies rendered hidden inputs into the test request.
		$_POST        = $request;
		$_POST['acf'] = $values;
		$_REQUEST     = $_POST;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$front = null !== $front ? $front : $this->form_front;
		return $front->check_submit_form();
	}

	/**
	 * Calls the AJAX validation handler and reads its JSON response.
	 *
	 * @return array
	 * @throws RuntimeException If AJAX handling raises an unrelated exception.
	 */
	private function invoke_ajax_validation(): array {
		$halt_marker = 'front_grant_ajax_halt';
		$force_ajax  = static function () {
			return true;
		};
		$halt_ajax   = static function () use ( $halt_marker ) {
			return static function () use ( $halt_marker ) {
				throw new RuntimeException( esc_html( $halt_marker ) );
			};
		};

		add_filter( 'wp_doing_ajax', $force_ajax );
		add_filter( 'wp_die_ajax_handler', $halt_ajax, 100 );
		add_filter( 'wp_die_json_handler', $halt_ajax, 100 );

		ob_start();
		try {
			acf()->validation->ajax_validate_save_post();
		} catch ( RuntimeException $exception ) {
			if ( $halt_marker !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			$output = ob_get_clean();
			remove_filter( 'wp_die_json_handler', $halt_ajax, 100 );
			remove_filter( 'wp_die_ajax_handler', $halt_ajax, 100 );
			remove_filter( 'wp_doing_ajax', $force_ajax );
		}

		$response = json_decode( $output, true );
		$this->assertIsArray( $response );

		return $response;
	}

	/**
	 * Decodes a grant and checks its HMAC against the exact JSON bytes.
	 *
	 * @param string $token Encoded grant token.
	 * @return array
	 */
	private function decode_grant_token( string $token ): array {
		$parts = explode( '.', $token, 2 );
		$this->assertCount( 2, $parts );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $parts[1] );

		$json = base64_decode( $parts[0], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes the documented authorization-token transport.
		$this->assertIsString( $json );
		$this->assertSame(
			hash_hmac( 'sha256', $json, wp_salt( 'nonce' ) ),
			$parts[1],
			'The rendered grant must be HMAC-SHA256 over its exact JSON bytes.'
		);

		$grant = json_decode( $json, true );
		$this->assertIsArray( $grant );

		return $grant;
	}

	/**
	 * Encodes a grant in the token format used by production.
	 *
	 * The bad-HMAC test passes the original signature after changing the payload.
	 *
	 * @param array       $grant     Grant payload.
	 * @param string|null $signature Optional existing signature.
	 * @return string
	 */
	private function encode_grant_token( array $grant, ?string $signature = null ): string {
		$json = wp_json_encode( $grant );
		$this->assertIsString( $json );

		if ( null === $signature ) {
			$signature = hash_hmac( 'sha256', $json, wp_salt( 'nonce' ) );
		}

		return base64_encode( $json ) . '.' . $signature; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes the documented authorization-token transport.
	}

	/**
	 * Checks that rejecting an invalid grant skips submission hooks and leaves
	 * stored data unchanged.
	 *
	 * @param acf_form_front $front      Isolated form registry.
	 * @param array          $request    Hidden request values.
	 * @param array          $values     Submitted ACF values.
	 * @param int            $post_id    Existing target used for the no-save assertion.
	 * @param string         $field_name Field meta name used for the no-save assertion.
	 * @return void
	 * @throws RuntimeException Re-throws an unexpected redirect-filter exception.
	 */
	private function assert_request_is_rejected_without_side_effects(
		acf_form_front $front,
		array $request,
		array $values,
		int $post_id,
		string $field_name
	): void {
		$events    = array(
			'validate'   => 0,
			'pre_submit' => 0,
			'pre_save'   => 0,
			'save'       => 0,
			'submit'     => 0,
		);
		$redirects = 0;
		$marker    = 'front_form_authorization_redirect';

		$this->add_test_hook(
			'acf/validate_save_post',
			static function () use ( &$events ) {
				++$events['validate'];
			},
			20,
			0
		);
		$this->add_test_hook(
			'acf/pre_submit_form',
			static function ( $form ) use ( &$events ) {
				++$events['pre_submit'];
				return $form;
			}
		);
		$this->add_test_hook(
			'acf/pre_save_post',
			static function ( $submitted_post_id ) use ( &$events ) {
				++$events['pre_save'];
				return $submitted_post_id;
			},
			20,
			2
		);
		$this->add_test_hook(
			'acf/save_post',
			static function () use ( &$events ) {
				++$events['save'];
			},
			1,
			0
		);
		$this->add_test_hook(
			'acf/submit_form',
			function ( $form, $submitted_post_id ) use ( &$events ) {
				++$events['submit'];
				if ( is_int( $submitted_post_id ) && $submitted_post_id > 0 ) {
					$this->post_ids[] = $submitted_post_id;
				}
			},
			10,
			2
		);
		$this->add_test_hook(
			'wp_redirect',
			static function () use ( &$redirects, $marker ) {
				++$redirects;
				throw new RuntimeException( esc_html( $marker ) );
			}
		);

		try {
			$result = $this->submit_request( $request, $values, $front );
		} catch ( RuntimeException $exception ) {
			if ( $marker !== $exception->getMessage() ) {
				throw $exception;
			}
			$result = null;
		}

		$this->assertFalse( $result );
		$this->assertSame( $values, $this->posted_fields(), 'KSES or field filtering must not run for an invalid grant.' );
		$this->assertSame(
			array(
				'validate'   => 0,
				'pre_submit' => 0,
				'pre_save'   => 0,
				'save'       => 0,
				'submit'     => 0,
			),
			$events
		);
		$this->assertSame( 0, $redirects, 'Invalid authorization must stop before redirect handling.' );
		$this->assertSame( '', get_post_meta( $post_id, $field_name, true ) );
	}

	/**
	 * Returns the raw ACF values currently in the request.
	 *
	 * @return array
	 */
	private function posted_fields(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Reads the raw ACF payload from the test request.
		return isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) ? $_POST['acf'] : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	}

	/**
	 * Add and track a test hook.
	 *
	 * @param string   $name          Hook name.
	 * @param callable $callback      Hook callback.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return void
	 */
	private function add_test_hook( string $name, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_filter( $name, $callback, $priority, $accepted_args );
		$this->test_hooks[] = array(
			'name'     => $name,
			'callback' => $callback,
			'priority' => $priority,
		);
	}
}
