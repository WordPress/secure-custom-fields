<?php
/**
 * Deprecated SCF functions and hooks.
 *
 * @package wordpress/secure-custom-fields
 */

/**
 * Checks whether field values stored on an ACF post ID belong to a password-protected
 * post whose password has not been entered yet.
 *
 * @since SCF 6.9.7
 * @deprecated 6.9.8 Use acf_post_password_required().
 *
 * @param integer|string $post_id ACF encoded post ID.
 * @return boolean
 */
function scf_post_password_required( $post_id ) {
	_deprecated_function( __FUNCTION__, '6.9.8', 'acf_post_password_required()' );

	return acf_post_password_required( $post_id );
}

/**
 * Removes uploaded files for top-level `acf` input keys that are not allowed.
 *
 * @since SCF 6.9.6
 * @deprecated 6.9.8 Use acf_restrict_uploaded_field_keys().
 *
 * @param array $allowed_keys The allowed top-level `acf` input keys.
 * @return void
 */
function scf_restrict_uploaded_field_keys( array $allowed_keys ): void {
	_deprecated_function( __FUNCTION__, '6.9.8', 'acf_restrict_uploaded_field_keys()' );

	acf_restrict_uploaded_field_keys( $allowed_keys );
}

/**
 * Applies the deprecated `scf/bidirectional/allow_target` filter to references being added.
 *
 * @since 6.9.8
 * @access private
 *
 * @param boolean        $can_update Whether the target may be updated.
 * @param integer|string $target_id  The ACF encoded target post, user or term ID.
 * @param integer|string $post_id    The ACF encoded origin post, user or term ID.
 * @param array          $field      The field being updated on the origin.
 * @param string         $action     Either 'add' or 'remove'.
 * @return boolean
 */
function _scf_apply_deprecated_bidirectional_allow_target( $can_update, $target_id, $post_id, $field, $action ) {
	if ( 'add' !== $action ) {
		return $can_update;
	}

	return (bool) apply_filters_deprecated( 'scf/bidirectional/allow_target', array( $can_update, $target_id, $field ), '6.9.8', 'acf/bidirectional/can_update_target' );
}
add_filter( 'acf/bidirectional/can_update_target', '_scf_apply_deprecated_bidirectional_allow_target', 10, 5 );

/**
 * Applies the deprecated `scf/upload/generate_previews` filter.
 *
 * @since 6.9.8
 * @access private
 *
 * @param boolean $generate_previews Whether to generate preview images.
 * @param string  $file              Path to the uploaded file.
 * @param integer $id                Attachment ID.
 * @return boolean
 */
function _scf_apply_deprecated_generate_previews( $generate_previews, $file, $id ) {
	return apply_filters_deprecated( 'scf/upload/generate_previews', array( $generate_previews, $file, $id ), '6.9.8', 'acf/upload/generate_previews' );
}
add_filter( 'acf/upload/generate_previews', '_scf_apply_deprecated_generate_previews', 10, 3 );
