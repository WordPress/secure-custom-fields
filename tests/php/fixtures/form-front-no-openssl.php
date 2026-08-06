<?php // phpcs:disable Squiz.Commenting.FileComment.Missing -- Standalone executable test fixture.
/**
 * Child-process fixture for front-end forms without OpenSSL.
 *
 * @package wordpress/secure-custom-fields
 */

require dirname( __DIR__ ) . '/bootstrap.php';

/**
 * Reads request data from the hidden inputs rendered by render_form().
 *
 * @param string $html Rendered form HTML.
 * @return array
 */
function scf_no_openssl_extract_request( $html ) {
	$inputs = array();
	preg_match_all( '/<input\b[^>]*type="hidden"[^>]*>/i', $html, $matches );

	foreach ( $matches[0] as $input ) {
		if ( ! preg_match( '/\bname="([^"]*)"/i', $input, $name_match ) ) {
			continue;
		}

		$value = '';
		if ( preg_match( '/\bvalue="([^"]*)"/i', $input, $value_match ) ) {
			$value = html_entity_decode( $value_match[1], ENT_QUOTES, 'UTF-8' );
		}

		$name = html_entity_decode( $name_match[1], ENT_QUOTES, 'UTF-8' );
		if ( 0 !== strpos( $name, '_acf_' ) ) {
			continue;
		}

		if ( ! isset( $inputs[ $name ] ) ) {
			$inputs[ $name ] = array();
		}
		$inputs[ $name ][] = $value;
	}

	$request = array();
	foreach ( $inputs as $name => $values ) {
		if ( '[]' === substr( $name, -2 ) ) {
			$request[ substr( $name, 0, -2 ) ] = $values;
		} else {
			$request[ $name ] = end( $values );
		}
	}

	return $request;
}

/**
 * Changes the JSON in the base64 fallback used without OpenSSL.
 *
 * The mutator leaves the separately rendered grant unchanged. This matches a
 * browser that can change readable form data but cannot issue a new grant.
 *
 * @param string   $payload Encoded fallback payload.
 * @param callable $mutator Payload mutator.
 * @return string
 * @throws RuntimeException If the rendered payload is not fallback JSON.
 */
function scf_no_openssl_tamper_json( $payload, $mutator ) {
	$separator     = strpos( $payload, '.' );
	$encoded_json  = false === $separator ? $payload : substr( $payload, 0, $separator );
	$opaque_suffix = false === $separator ? '' : substr( $payload, $separator );
	$json          = base64_decode( $encoded_json, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Changes readable fallback data for this test.
	$data          = is_string( $json ) ? json_decode( $json, true ) : null;

	if ( ! is_array( $data ) ) {
		throw new RuntimeException( 'Rendered fallback payload did not contain JSON.' );
	}

	$data = $mutator( $data );

	return base64_encode( wp_json_encode( $data ) ) . $opaque_suffix; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Re-encodes the changed fallback data.
}

/**
 * Writes the fixture result as JSON.
 *
 * @param array $result Fixture result.
 * @return void
 */
function scf_no_openssl_emit_result( array $result ) {
	echo 'SCF_NO_OPENSSL_RESULT=' . wp_json_encode( $result );
}

$scenario = isset( $argv[1] ) ? $argv[1] : '';
$result   = array(
	'scenario'                => $scenario,
	'openssl_functions_exist' => array(
		'openssl_encrypt'             => function_exists( 'openssl_encrypt' ),
		'openssl_decrypt'             => function_exists( 'openssl_decrypt' ),
		'openssl_random_pseudo_bytes' => function_exists( 'openssl_random_pseudo_bytes' ),
		'openssl_cipher_iv_length'    => function_exists( 'openssl_cipher_iv_length' ),
	),
);

try {
	$valid_scenarios = array( 'valid', 'form', 'return', 'target', 'new_post', 'flags', 'metadata' );
	if ( ! in_array( $scenario, $valid_scenarios, true ) ) {
		throw new RuntimeException( 'Unknown fixture scenario: ' . $scenario );
	}

	foreach ( $result['openssl_functions_exist'] as $function_name => $exists ) {
		if ( $exists ) {
			throw new RuntimeException( $function_name . ' was not disabled in the child process.' );
		}
	}

	$field_key          = 'field_scf_no_openssl';
	$attacker_field_key = 'field_scf_no_openssl_attacker';

	acf_add_local_field(
		array(
			'key'   => $field_key,
			'name'  => 'scf_no_openssl',
			'label' => 'No OpenSSL field',
			'type'  => 'text',
		)
	);
	acf_add_local_field(
		array(
			'key'   => $attacker_field_key,
			'name'  => 'scf_no_openssl_attacker',
			'label' => 'No OpenSSL attacker field',
			'type'  => 'text',
		)
	);

	$primary_post_id = wp_insert_post(
		array(
			'post_title'  => 'Original primary title',
			'post_status' => 'publish',
			'post_type'   => 'post',
		)
	);
	$victim_post_id  = wp_insert_post(
		array(
			'post_title'  => 'Original victim title',
			'post_status' => 'publish',
			'post_type'   => 'post',
		)
	);

	if ( ! is_int( $primary_post_id ) || $primary_post_id <= 0 || ! is_int( $victim_post_id ) || $victim_post_id <= 0 ) {
		throw new RuntimeException( 'Unable to create fixture posts.' );
	}

	update_field( $field_key, 'original primary value', $primary_post_id );
	update_field( $field_key, 'original victim value', $victim_post_id );
	update_field( $attacker_field_key, 'original attacker value', $primary_post_id );

	$form_args = array(
		'id'              => 'scf-no-openssl-form',
		'post_id'         => $primary_post_id,
		'return'          => '',
		'fields'          => array( $field_key ),
		'field_groups'    => false,
		'post_title'      => false,
		'post_content'    => false,
		'honeypot'        => false,
		'updated_message' => false,
	);

	if ( 'new_post' === $scenario ) {
		$form_args['post_id']  = 'new_post';
		$form_args['new_post'] = array(
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => 'Authorized draft title',
		);
	}

	ob_start();
	acf()->form_front->render_form( $form_args );
	$html    = ob_get_clean();
	$request = scf_no_openssl_extract_request( $html );

	foreach ( array( '_acf_nonce', '_acf_form', '_acf_render_id', '_acf_form_meta' ) as $required_key ) {
		if ( ! array_key_exists( $required_key, $request ) ) {
			throw new RuntimeException( 'Rendered form is missing ' . $required_key . '.' );
		}
	}

	switch ( $scenario ) {
		case 'form':
			$request['_acf_form'] = scf_no_openssl_tamper_json(
				$request['_acf_form'],
				static function ( $form ) {
					$form['id'] = 'attacker-controlled-form-id';
					return $form;
				}
			);
			break;

		case 'return':
			$request['_acf_form'] = scf_no_openssl_tamper_json(
				$request['_acf_form'],
				static function ( $form ) {
					$form['return'] = 'https://front-form-attacker.invalid/complete';
					return $form;
				}
			);
			break;

		case 'target':
			$request['_acf_form'] = scf_no_openssl_tamper_json(
				$request['_acf_form'],
				static function ( $form ) use ( $victim_post_id ) {
					$form['post_id'] = $victim_post_id;
					return $form;
				}
			);
			break;

		case 'new_post':
			$request['_acf_form'] = scf_no_openssl_tamper_json(
				$request['_acf_form'],
				static function ( $form ) {
					$form['new_post']['post_type']   = 'page';
					$form['new_post']['post_status'] = 'publish';
					$form['new_post']['post_title']  = 'Attacker-created page';
					return $form;
				}
			);
			break;

		case 'flags':
			$request['_acf_form'] = scf_no_openssl_tamper_json(
				$request['_acf_form'],
				static function ( $form ) {
					$form['post_title']   = true;
					$form['post_content'] = true;
					$form['honeypot']     = false;
					return $form;
				}
			);
			break;

		case 'metadata':
			$request['_acf_form_meta'][0] = scf_no_openssl_tamper_json(
				$request['_acf_form_meta'][0],
				static function ( $meta ) use ( $attacker_field_key ) {
					$meta['allowed_field_keys'][] = $attacker_field_key;

					$meta['post_title'] = true;
					return $meta;
				}
			);
			break;
	}

	$submitted_raw_value = '<script>window.scfTampered=1;</script><em>raw value</em>';
	if ( 'valid' === $scenario ) {
		$submitted_raw_value = 'valid submitted value';
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The fixture intentionally submits exact rendered and attacker-modified request data.
	$_POST                         = $request;
	$_POST['acf']                  = array(
		$field_key          => $submitted_raw_value,
		$attacker_field_key => 'attacker-only value',
	);
	$_POST['acf']['_post_title']   = 'Attacker-controlled title';
	$_POST['acf']['_post_content'] = 'Attacker-controlled content';
	$_REQUEST                      = $_POST;
	// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	$validation_action_calls = 0;
	$field_validator_calls   = 0;
	$form_hook_calls         = array(
		'pre_submit_form' => 0,
		'pre_save_post'   => 0,
		'save_post'       => 0,
		'submit_form'     => 0,
	);
	$new_post_insertions     = 0;
	$redirects               = array();

	add_action(
		'acf/validate_save_post',
		static function () use ( &$validation_action_calls ) {
			++$validation_action_calls;
		},
		PHP_INT_MAX
	);
	add_filter(
		'acf/validate_value/key=' . $field_key,
		static function ( $valid ) use ( &$field_validator_calls ) {
			++$field_validator_calls;
			return $valid;
		},
		PHP_INT_MAX
	);
	add_filter(
		'acf/pre_submit_form',
		static function ( $form ) use ( &$form_hook_calls ) {
			++$form_hook_calls['pre_submit_form'];
			return $form;
		},
		PHP_INT_MAX
	);
	add_filter(
		'acf/pre_save_post',
		static function ( $post_id ) use ( &$form_hook_calls ) {
			++$form_hook_calls['pre_save_post'];
			return $post_id;
		},
		PHP_INT_MAX
	);
	add_action(
		'acf/save_post',
		static function () use ( &$form_hook_calls ) {
			++$form_hook_calls['save_post'];
		},
		PHP_INT_MAX
	);
	add_action(
		'acf/submit_form',
		static function () use ( &$form_hook_calls ) {
			++$form_hook_calls['submit_form'];
		},
		PHP_INT_MAX
	);
	add_action(
		'wp_insert_post',
		static function ( $post_id, $post, $update ) use ( &$new_post_insertions ) {
			if ( ! $update ) {
				++$new_post_insertions;
			}
		},
		PHP_INT_MAX,
		3
	);

	add_filter(
		'wp_redirect',
		static function ( $location ) use ( &$redirects ) {
			$redirects[] = $location;
			return false;
		},
		PHP_INT_MAX
	);

	$handler_result   = acf()->form_front->check_submit_form();
	$handler_returned = true;

	$primary_post = get_post( $primary_post_id );
	$victim_post  = get_post( $victim_post_id );

	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Observing whether the rejected request remained byte-for-byte untouched.
	$raw_value_after = isset( $_POST['acf'][ $field_key ] ) ? $_POST['acf'][ $field_key ] : null;
	// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	$result += array(
		'handler_returned'        => $handler_returned,
		'handler_result'          => $handler_result,
		'validation_action_calls' => $validation_action_calls,
		'field_validator_calls'   => $field_validator_calls,
		'form_hook_calls'         => $form_hook_calls,
		'submitted_raw_value'     => $submitted_raw_value,
		'raw_value_after'         => $raw_value_after,
		'primary_value'           => get_field( $field_key, $primary_post_id, false ),
		'victim_value'            => get_field( $field_key, $victim_post_id, false ),
		'primary_title'           => $primary_post ? $primary_post->post_title : null,
		'victim_title'            => $victim_post ? $victim_post->post_title : null,
		'new_post_insertions'     => $new_post_insertions,
		'redirects'               => $redirects,
		'global_form_set'         => isset( $GLOBALS['acf_form'] ),
	);
} catch ( Throwable $throwable ) {
	$result['fixture_error'] = get_class( $throwable ) . ': ' . $throwable->getMessage();
	scf_no_openssl_emit_result( $result );
	exit( 1 );
}

scf_no_openssl_emit_result( $result );
