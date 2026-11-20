<?php
/**
 * Guards option-backed form saves.
 *
 * @package wordpress/secure-custom-fields
 */

namespace SCF\Forms;

use stdClass;

/**
 * Checks final option names while an untrusted form is being saved.
 *
 * @internal
 */
trait Option_Save_Guard {

	/**
	 * Saves values after checking every option they reach in custom storage.
	 *
	 * Default storage is not checked: its option names all start with "options_",
	 * a prefix that only field values use.
	 *
	 * The filters are present only for this save and apply only to submitted
	 * values. Values that code saves during the same request, such as from an
	 * acf/save_post callback, stay on their existing path.
	 *
	 * @since SCF 6.9.10
	 *
	 * @param int|string    $guarded_post_id Storage identifier authorized by the form.
	 * @param callable|null $on_blocked     Optional callback for a blocked submitted value.
	 * @return bool
	 */
	private function save_post_with_option_guard( $guarded_post_id, ?callable $on_blocked = null ): bool {
		$decoded = acf_decode_post_id( $guarded_post_id );

		if ( 'option' !== $decoded['type'] || 0 === strpos( $decoded['id'] . '_', 'options_' ) ) {
			return acf_save_post( $guarded_post_id );
		}

		$blocked_option_deletes  = array();
		$authorize_option_update = function ( $check, $value, $post_id, $field ) use ( $guarded_post_id, $on_blocked ) {
			if ( ! $this->can_update_guarded_option( $guarded_post_id, $post_id, $field ) ) {
				if ( null !== $on_blocked ) {
					$on_blocked();
				}

				return false;
			}

			return $check;
		};
		$capture_option_delete   = function ( $post_id, $field_name, $field ) use ( $guarded_post_id, $on_blocked, &$blocked_option_deletes ) {
			$blocked = ! $this->can_update_guarded_option( $guarded_post_id, $post_id, $field );

			$blocked_option_deletes[ (string) $post_id ][ $field_name ] = $blocked;

			if ( 'flexible_content' === ( $field['type'] ?? '' ) ) {
				$blocked_option_deletes[ (string) $post_id ][ '_' . $field_name . '_layout_meta' ] = $blocked;
			}

			// Key-less deletes are internal clean-up, not submitted values.
			if ( $blocked && null !== $on_blocked && ! empty( $field['key'] ) ) {
				$on_blocked();
			}
		};
		$authorize_option_delete = function ( $check, $post_id, $field_name ) use ( &$blocked_option_deletes ) {
			if ( ! empty( $blocked_option_deletes[ (string) $post_id ][ $field_name ] ) ) {
				return false;
			}

			return $check;
		};

		add_filter( 'acf/pre_update_value', $authorize_option_update, PHP_INT_MAX, 4 );
		add_action( 'acf/delete_value', $capture_option_delete, PHP_INT_MIN, 3 );
		add_filter( 'acf/pre_delete_metadata', $authorize_option_delete, PHP_INT_MAX, 3 );

		try {
			return acf_save_post( $guarded_post_id );
		} finally {
			remove_filter( 'acf/pre_update_value', $authorize_option_update, PHP_INT_MAX );
			remove_action( 'acf/delete_value', $capture_option_delete, PHP_INT_MIN );
			remove_filter( 'acf/pre_delete_metadata', $authorize_option_delete, PHP_INT_MAX );
		}
	}

	/**
	 * Checks the final option name used by a field update.
	 *
	 * @since SCF 6.9.10
	 *
	 * @param int|string $guarded_post_id Storage identifier authorized by the form.
	 * @param int|string $post_id         Storage identifier for this field update.
	 * @param array      $field           Field being updated.
	 * @return bool
	 */
	private function can_update_guarded_option( $guarded_post_id, $post_id, array $field ): bool {
		if ( $post_id !== $guarded_post_id ) {
			return true;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Only submitted values are checked. update_field() clears this flag for its own writes.
		if ( ! acf_get_data( 'acf_saving_submitted_values' ) ) {
			return true;
		}

		// A field without a name stores no option of its own, and its sub fields are checked
		// individually. Flexible Content is the exception because it deletes its layout state directly.
		if ( empty( $field['name'] ) && 'flexible_content' !== ( $field['type'] ?? '' ) ) {
			return true;
		}

		// The guard is only installed for option storage, so the decoded ID is the option name prefix.
		$decoded = acf_decode_post_id( $post_id );

		if ( empty( $decoded['id'] ) || empty( $field['name'] ) ) {
			return false;
		}

		$option_name = $decoded['id'] . '_' . $field['name'];
		if ( $this->is_protected_wordpress_option( $option_name ) ) {
			return false;
		}

		$ownership = $this->get_guarded_option_ownership( $option_name );
		if ( 'field' !== $ownership && 'new' !== $ownership ) {
			return false;
		}

		if ( 'flexible_content' !== ( $field['type'] ?? '' ) ) {
			return true;
		}

		// Flexible Content keeps layout state in a sibling option. The parent reference covers both.
		$layout_meta_name = $decoded['id'] . '__' . $field['name'] . '_layout_meta';
		if ( $this->is_protected_wordpress_option( $layout_meta_name ) ) {
			return false;
		}

		$layout_meta_ownership = $this->get_guarded_option_ownership( $layout_meta_name );

		return 'new' === $layout_meta_ownership
			|| ( 'field' === $ownership && 'unreferenced' === $layout_meta_ownership );
	}

	/**
	 * Determines whether an option is field storage, from its field reference.
	 *
	 * @since SCF 6.9.10
	 *
	 * @param string $option_name Final option name.
	 * @return string "field" when a field reference marks the option as field storage, "new" when
	 *                neither exists, "unreferenced" when the option exists without a reference,
	 *                and "foreign" otherwise.
	 */
	private function get_guarded_option_ownership( string $option_name ): string {
		$missing             = new stdClass();
		$trimmed_option_name = trim( $option_name );
		$reference_name      = '_' . $option_name;
		$option_value        = get_option( $option_name, $missing );
		$reference           = get_option( $reference_name, $missing );
		$reference_reference = get_option( '_' . $reference_name, $missing );

		// Do not let a field value act as another option's ownership marker.
		if ( 0 === strpos( $trimmed_option_name, '_' ) && get_option( substr( $trimmed_option_name, 1 ), $missing ) !== $missing ) {
			return 'foreign';
		}

		// A reference holds a field key, or an empty string when code saved the value by field name.
		if ( $missing === $reference_reference && is_string( $reference ) && ( '' === $reference || acf_is_field_key( $reference ) ) ) {
			return 'field';
		}

		if ( $missing === $option_value && $missing === $reference ) {
			return 'new';
		}

		if ( $missing === $reference ) {
			return 'unreferenced';
		}

		return 'foreign';
	}

	/**
	 * Checks options that remain owned by WordPress even if an earlier
	 * vulnerable save added a matching field reference.
	 *
	 * @since SCF 6.9.10
	 *
	 * @param string $option_name Final option name.
	 * @return bool
	 */
	private function is_protected_wordpress_option( string $option_name ): bool {
		global $wpdb;

		$lookup_name     = trim( $option_name );
		$protected_names = array(
			'default_role',
			strtolower( remove_accents( $wpdb->get_blog_prefix() . 'user_roles' ) ),
		);
		$normalized_name = strtolower( remove_accents( $lookup_name ) );

		if ( in_array( $normalized_name, $protected_names, true ) ) {
			return true;
		}

		// Resolve any other aliases using the options table's own collation.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This reads the canonical option name, not its value.
		$stored_name = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT option_name FROM %i WHERE option_name = %s LIMIT 1',
				$wpdb->options,
				$lookup_name
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_string( $stored_name )
			&& in_array( strtolower( remove_accents( trim( $stored_name ) ) ), $protected_names, true );
	}
}
