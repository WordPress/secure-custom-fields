# Acf Field Functions Global Functions

## `acf_get_field()`

acf_get_field

* Retrieves a field for the given identifier.
* @date    17/1/19
* @since ACF 5.7.10
* @param   (int|string) $id The field ID, key or name.
* @return (array|false) The field array.

## `acf_get_raw_field()`

acf_get_raw_field

* Retrieves raw field data for the given identifier.
* @date    18/1/19
* @since ACF 5.7.10
* @param   (int|string) $id The field ID, key or name.
* @return (array|false) The field array.

## `acf_get_field_post()`

acf_get_field_post

* Retrieves the field's WP_Post object.
* @date    18/1/19
* @since ACF 5.7.10
* @param   (int|string) $id The field ID, key or name.
* @return (array|false) The field array.

## `acf_is_field_key()`

acf_is_field_key

* Returns true if the given identifier is a field key.
* @date    6/12/2013
* @since ACF 5.0.0
* @param   string $id The identifier.
* @return boolean

## `acf_validate_field()`

acf_validate_field

* Ensures the given field valid.
* @date    18/1/19
* @since ACF 5.7.10
* @param   array $field The field array.
* @return array

## `acf_get_valid_field()`

acf_get_valid_field

* Ensures the given field valid.
* @date        28/09/13
* @since ACF 5.0.0
* @param   array $field The field array.
* @return array

## `acf_translate_field()`

acf_translate_field

* Translates a field's settings.
* @date    8/03/2016
* @since ACF 5.3.2
* @param   array $field The field array.
* @return array

## `acf_get_fields()`

acf_get_fields

* Returns and array of fields for the given $parent.
* @date    30/09/13
* @since ACF 5.0.0
* @param   (int|string|array) $parent The field group or field settings. Also accepts the field group ID or key.
* @return array

## `acf_get_raw_fields()`

acf_get_raw_fields

* Returns and array of raw field data for the given parent id.
* @date    18/1/19
* @since ACF 5.7.10
* @param   integer $id The field group or field id.
* @return array

## `acf_get_field_count()`

acf_get_field_count

* Return the number of fields for the given field group.
* @date    17/10/13
* @since ACF 5.0.0
* @param   array $parent The field group or field array.
* @return integer

## `acf_clone_field()`

acf_clone_field

* Allows customization to a field when it is cloned. Used by the clone field.
* @date    8/03/2016
* @since ACF 5.3.2
* @param   array $field       The field being cloned.
* @param array $clone_field The clone field.
* @return array

## `acf_prepare_field()`

acf_prepare_field

* Prepare a field for input.
* @date    20/1/19
* @since ACF 5.7.10
* @param   array $field The field array.
* @return array

## `acf_render_fields()`

acf_render_fields

* Renders an array of fields. Also loads the field's value.
* @date    8/10/13
* @since ACF 5.0.0
* @since ACF 5.6.9 Changed parameter order.
* @param   array        $fields      An array of fields.
* @param (int|string) $post_id     The post ID to load values from.
* @param string       $element     The wrapping element type.
* @param string       $instruction The instruction render position (label|field).
* @return void

## `acf_render_field_wrap()`

Render the wrapping element for a given field.

* @since   ACF 5.0.0
* @param   array   $field         The field array.
* @param string  $element       The wrapping element type.
* @param string  $instruction   The instruction render position (label|field).
* @param boolean $field_setting If a field setting is being rendered.
* @return void

## `acf_render_field()`

acf_render_field

* Render the input element for a given field.
* @date    21/1/19
* @since ACF 5.7.10
* @param   array $field The field array.
* @return void

## `acf_render_field_label()`

acf_render_field_label

* Renders the field's label.
* @date    19/9/17
* @since ACF 5.6.3
* @param   array $field The field array.
* @return void

## `acf_get_field_label()`

acf_get_field_label

* Returns the field's label with appropriate required label.
* @date    4/11/2013
* @since ACF 5.0.0
* @param   array  $field   The field array.
* @param string $context The output context (admin).
* @return string The field label in HTML format.

## `acf_render_field_instructions()`

Renders the field's instructions.

* @since   ACF 5.6.3
* @param array   $field   The field array.
* @param boolean $tooltip If the instructions are being rendered as a tooltip.
* @return void

## `acf_render_field_setting()`

acf_render_field_setting

* Renders a field setting used in the admin edit screen.
* @date    21/1/19
* @since ACF 5.7.10
* @param   array   $field   The field array.
* @param array   $setting The settings field array.
* @param boolean $global  Whether this setting is a global or field type specific one.
* @return void

## `acf_update_field()`

acf_update_field

* Updates a field in the database.
* @date    21/1/19
* @since ACF 5.7.10
* @param   array $field    The field array.
* @param array $specific An array of specific field attributes to update.
* @return array

## `_acf_apply_unique_field_slug()`

_acf_apply_unique_field_slug

* Allows full control over 'acf-field' slugs.
* @date    21/1/19
* @since ACF 5.7.10
* @param string  $slug          The post slug.
* @param integer $post_ID       Post ID.
* @param string  $post_status   The post status.
* @param string  $post_type     Post type.
* @param integer $post_parent   Post parent ID
* @param string  $original_slug The original post slug.

## `acf_flush_field_cache()`

acf_flush_field_cache

* Deletes all caches for this field.
* @date    22/1/19
* @since ACF 5.7.10
* @param   array $field The field array.
* @return void

## `acf_delete_field()`

acf_delete_field

* Deletes a field from the database.
* @date    21/1/19
* @since ACF 5.7.10
* @param   (int|string) $id The field ID, key or name.
* @return boolean True if field was deleted.

## `acf_trash_field()`

acf_trash_field

* Trashes a field from the database.
* @date    2/10/13
* @since ACF 5.0.0
* @param   (int|string) $id The field ID, key or name.
* @return boolean True if field was trashed.

## `acf_untrash_field()`

acf_untrash_field

* Restores a field from the trash.
* @date    2/10/13
* @since ACF 5.0.0
* @param   (int|string) $id The field ID, key or name.
* @return boolean True if field was trashed.

## `_acf_untrash_field_post_status()`

Filter callback which returns the previous post_status instead of "draft" for the "acf-field" post type.

* Prior to WordPress 5.6.0, this filter was not needed as restored posts were always assigned their original status.
* @since ACF 5.9.5
* @param string  $new_status      The new status of the post being restored.
* @param integer $post_id         The ID of the post being restored.
* @param string  $previous_status The status of the post at the point where it was trashed.
* @return string.

## `acf_prefix_fields()`

acf_prefix_fields

* Changes the prefix for an array of fields by reference.
* @date    5/9/17
* @since ACF 5.6.0
* @param   array  $fields An array of fields.
* @param string $prefix The new prefix.
* @return void

## `acf_get_sub_field()`

acf_get_sub_field

* Searches a field for sub fields matching the given selector.
* @date    21/1/19
* @since ACF 5.7.10
* @param   (int|string) $id    The field ID, key or name.
* @param array        $field The parent field array.
* @return (array|false)

## `acf_search_fields()`

acf_search_fields

* Searches an array of fields for one that matches the given identifier.
* @date    12/2/19
* @since ACF 5.7.11
* @param   (int|string) $id       The field ID, key or name.
* @param array        $haystack The array of fields.
* @return (int|false)

## `acf_is_field()`

acf_is_field

* Returns true if the given params match a field.
* @date    21/1/19
* @since ACF 5.7.10
* @param   array $field The field array.
* @param mixed $id    An optional identifier to search for.
* @return boolean

## `acf_get_field_ancestors()`

acf_get_field_ancestors

* Returns an array of ancestor field ID's or keys.
* @date    22/06/2016
* @since ACF 5.3.8
* @param   array $field The field array.
* @return array

## `acf_duplicate_fields()`

acf_duplicate_fields

* Duplicate an array of fields.
* @date    16/06/2014
* @since ACF 5.0.0
* @param   array   $fields    An array of fields.
* @param integer $parent_id The new parent ID.
* @return array

## `acf_duplicate_field()`

acf_duplicate_field

* Duplicates a field.
* @date    16/06/2014
* @since ACF 5.0.0
* @param   (int|string) $id        The field ID, key or name.
* @param integer      $parent_id The new parent ID.
* @return boolean True if field was duplicated.

## `acf_prepare_fields_for_export()`

acf_prepare_fields_for_export

* Returns a modified array of fields ready for export.
* @date    11/03/2014
* @since ACF 5.0.0
* @param   array $fields An array of fields.
* @return array

## `acf_prepare_field_for_export()`

acf_prepare_field_for_export

* Returns a modified field ready for export.
* @date    11/03/2014
* @since ACF 5.0.0
* @param   array $field The field array.
* @return array

## `acf_prepare_fields_for_import()`

acf_prepare_field_for_import

* Returns a modified array of fields ready for import.
* @date    11/03/2014
* @since ACF 5.0.0
* @param   array $fields An array of fields.
* @return array

## `acf_prepare_field_for_import()`

acf_prepare_field_for_import

* Returns a modified field ready for import.
Allows parent fields to modify themselves and also return sub fields.
* @date    11/03/2014
* @since ACF 5.0.0
* @param   array $field The field array.
* @return array

## `acf_get_field_json_schema()`

Retrieves the JSON schema for a field.

* @since 6.8.0
* @param string $field_type The field to get the JSON schema for.
* @return array

---
