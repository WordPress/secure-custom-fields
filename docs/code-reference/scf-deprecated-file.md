# Scf Deprecated Global Functions

## `scf_post_password_required()`

Checks whether field values stored on an ACF post ID belong to a password-protected
post whose password has not been entered yet.

* @since SCF 6.9.7
* @deprecated 6.9.8 Use acf_post_password_required().
* @param integer|string $post_id ACF encoded post ID.
* @return boolean

## `scf_restrict_uploaded_field_keys()`

Removes uploaded files for top-level `acf` input keys that are not allowed.

* @since SCF 6.9.6
* @deprecated 6.9.8 Use acf_restrict_uploaded_field_keys().
* @param array $allowed_keys The allowed top-level `acf` input keys.
* @return void

## `_scf_apply_deprecated_bidirectional_allow_target()`

Applies the deprecated `scf/bidirectional/allow_target` filter to references being added.

* @since 6.9.8
@access private
* @param boolean        $can_update Whether the target may be updated.
* @param integer|string $target_id  The ACF encoded target post, user or term ID.
* @param integer|string $post_id    The ACF encoded origin post, user or term ID.
* @param array          $field      The field being updated on the origin.
* @param string         $action     Either 'add' or 'remove'.
* @return boolean

## `_scf_apply_deprecated_generate_previews()`

Applies the deprecated `scf/upload/generate_previews` filter.

* @since 6.9.8
@access private
* @param boolean $generate_previews Whether to generate preview images.
* @param string  $file              Path to the uploaded file.
* @param integer $id                Attachment ID.
* @return boolean

---
