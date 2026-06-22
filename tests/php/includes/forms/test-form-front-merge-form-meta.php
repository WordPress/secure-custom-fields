<?php
/**
 * Tests for acf_form_front::merge_form_meta().
 *
 * Covers the upstream 6.8.4 backport fixing silent data loss when multiple
 * acf_form() instances share a single outer form tag: per-form metadata
 * tokens are folded into the primary form configuration when they validate.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

acf_include( 'includes/forms/form-front.php' );

/**
 * Class Test_Form_Front_Merge_Form_Meta
 *
 * @group forms
 * @group security
 */
class Test_Form_Front_Merge_Form_Meta extends BaseTestCase {

	/**
	 * The render id used by tokens in these tests.
	 *
	 * @var string
	 */
	private $render_id = 'render-id-test';

	/**
	 * The raw primary _acf_form value submitted with the request.
	 *
	 * @var string
	 */
	private $primary_form_value = 'encrypted-primary-form-value';

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$_POST = array();
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Invokes the protected merge_form_meta() method.
	 *
	 * @param array $form The primary form configuration.
	 * @return array
	 */
	private function merge_form_meta( array $form ): array {
		$method = new ReflectionMethod( 'acf_form_front', 'merge_form_meta' );
		$method->setAccessible( true );

		return $method->invoke( acf()->form_front, $form );
	}

	/**
	 * Builds an encrypted _acf_form_meta token.
	 *
	 * @param array $overrides Values overriding the valid defaults.
	 * @return string
	 */
	private function build_token( array $overrides = array() ): string {
		$meta = array_merge(
			array(
				'render_id'          => $this->render_id,
				'form_anchor'        => hash( 'sha256', $this->primary_form_value ),
				'target_post_id'     => '123',
				'issued_at'          => time(),
				'allowed_field_keys' => array(),
				'post_title'         => false,
				'post_content'       => false,
			),
			$overrides
		);

		return acf_encrypt( wp_json_encode( $meta ) );
	}

	/**
	 * Populates $_POST with a submission carrying the given meta tokens.
	 *
	 * @param array $tokens The _acf_form_meta token list.
	 * @return void
	 */
	private function set_up_request( array $tokens ): void {
		$_POST['_acf_form']      = $this->primary_form_value;
		$_POST['_acf_render_id'] = $this->render_id;
		$_POST['_acf_form_meta'] = $tokens;
	}

	/**
	 * Sibling form keys are folded into the primary form when tokens validate.
	 *
	 * @return void
	 */
	public function test_folds_sibling_keys_and_flags_into_primary_form() {
		$this->set_up_request(
			array(
				// Token anchoring the primary form.
				$this->build_token( array( 'allowed_field_keys' => array( 'field_primary' ) ) ),
				// Sibling form on the same page targeting the same post.
				$this->build_token(
					array(
						'form_anchor'        => hash( 'sha256', 'sibling-form-value' ),
						'allowed_field_keys' => array( 'field_sibling' ),
						'post_title'         => true,
					)
				),
			)
		);

		$form = $this->merge_form_meta(
			array(
				'post_id'    => '123',
				'post_title' => false,
			)
		);

		$this->assertSame(
			array( 'field_primary', 'field_sibling' ),
			$form['_additional_allowed_field_keys']
		);
		$this->assertTrue( $form['post_title'] );
	}

	/**
	 * Tokens are ignored when the request render id does not match.
	 *
	 * @return void
	 */
	public function test_ignores_tokens_with_mismatched_render_id() {
		$this->set_up_request(
			array(
				$this->build_token(
					array(
						'render_id'          => 'some-other-render-id',
						'allowed_field_keys' => array( 'field_injected' ),
					)
				),
			)
		);

		$form = $this->merge_form_meta( array( 'post_id' => '123' ) );

		$this->assertArrayNotHasKey( '_additional_allowed_field_keys', $form );
	}

	/**
	 * Nothing is merged when no token anchors the submitted primary form.
	 *
	 * @return void
	 */
	public function test_requires_a_token_anchoring_the_primary_form() {
		$this->set_up_request(
			array(
				$this->build_token(
					array(
						'form_anchor'        => hash( 'sha256', 'unrelated-form-value' ),
						'allowed_field_keys' => array( 'field_injected' ),
					)
				),
			)
		);

		$form = $this->merge_form_meta( array( 'post_id' => '123' ) );

		$this->assertArrayNotHasKey( '_additional_allowed_field_keys', $form );
	}

	/**
	 * Tokens targeting a different post id contribute nothing.
	 *
	 * @return void
	 */
	public function test_skips_tokens_for_other_target_post_id() {
		$this->set_up_request(
			array(
				$this->build_token( array( 'allowed_field_keys' => array( 'field_primary' ) ) ),
				$this->build_token(
					array(
						'form_anchor'        => hash( 'sha256', 'sibling-form-value' ),
						'target_post_id'     => '456',
						'allowed_field_keys' => array( 'field_other_post' ),
						'post_content'       => true,
					)
				),
			)
		);

		$form = $this->merge_form_meta(
			array(
				'post_id'      => '123',
				'post_content' => false,
			)
		);

		$this->assertSame(
			array( 'field_primary' ),
			$form['_additional_allowed_field_keys']
		);
		$this->assertFalse( $form['post_content'] );
	}

	/**
	 * Expired tokens are ignored.
	 *
	 * @return void
	 */
	public function test_ignores_expired_tokens() {
		$this->set_up_request(
			array(
				$this->build_token(
					array(
						'issued_at'          => time() - ( 2 * DAY_IN_SECONDS ),
						'allowed_field_keys' => array( 'field_primary' ),
					)
				),
			)
		);

		$form = $this->merge_form_meta( array( 'post_id' => '123' ) );

		$this->assertArrayNotHasKey( '_additional_allowed_field_keys', $form );
	}

	/**
	 * The form is returned unchanged when no meta inputs are present.
	 *
	 * @return void
	 */
	public function test_returns_form_unchanged_without_meta_inputs() {
		$form = array( 'post_id' => '123' );

		$this->assertSame( $form, $this->merge_form_meta( $form ) );
	}
}
