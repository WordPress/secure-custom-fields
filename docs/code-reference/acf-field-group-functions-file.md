# Acf Field Group Functions Global Functions

## `acf_get_field_group()`

acf_get_field_group

* Retrieves a field group for the given identifier.
* @date    30/09/13
* @since ACF 5.0.0
* @param   (int|string) $id The field group ID, key or name.
* @return (array|false) The field group array.

## `acf_get_raw_field_group()`

acf_get_raw_field_group

* Retrieves raw field group data for the given identifier.
* @date    18/1/19
* @since ACF 5.7.10
* @param   (int|string) $id The field ID, key or name.
* @return (array|false) The field group array.

## `acf_get_field_group_post()`

acf_get_field_group_post

* Retrieves the field group's WP_Post object.
* @date    18/1/19
* @since ACF 5.7.10
* @param   (int|string) $id The field group's ID, key or name.
* @return (array|false) The field group's array.

## `acf_is_field_group_key()`

acf_is_field_group_key

* Returns true if the given identifier is a field group key.
* @date    6/12/2013
* @since ACF 5.0.0
* @param   string $id The identifier.
* @return boolean

## `acf_validate_field_group()`

Ensures the given field group is valid.

* @date    18/1/19
* @since ACF 5.7.10
* @param array $field_group The field group array.
* @return array

## `acf_get_valid_field_group()`

acf_get_valid_field_group

* Ensures the given field group is valid.
* @date        28/09/13
* @since ACF 5.0.0
* @param   array $field_group The field group array.
* @return array

## `acf_translate_field_group()`

acf_translate_field_group

* Translates a field group's settings.
* @date    8/03/2016
* @since ACF 5.3.2
* @param   array $field_group The field group array.
* @return array

## `acf_get_field_groups()`

acf_get_field_groups

* Returns and array of field_groups for the given $filter.
* @date    30/09/13
* @since ACF 5.0.0
* @param   array $filter An array of args to filter results by.
* @return array

## `acf_get_raw_field_groups()`

acf_get_raw_field_groups

* Returns and array of raw field_group data.
* @date    18/1/19
* @since ACF 5.7.10
* @return  array

## `acf_filter_field_groups()`

acf_filter_field_groups

* Returns a filtered aray of field groups based on the given $args.
* @date    29/11/2013
* @since ACF 5.0.0
* @param   array $field_groups An array of field groups.
* @param array $args         An array of location args.
* @return array

## `acf_get_field_group_visibility()`

acf_get_field_group_visibility

* Returns true if the given field group's location rules match the given $args.
* @date    7/10/13
* @since ACF 5.0.0
* @param   array $field_groups An array of field groups.
* @param array $args         An array of location args.
* @return boolean

## `acf_update_field_group()`

acf_update_field_group

* Updates a field group in the database.
* @date    21/1/19
* @since ACF 5.7.10
* @param   array $field_group The field group array.
* @return array

## `_acf_apply_unique_field_group_slug()`

_acf_apply_unique_field_group_slug

* Allows full control over 'acf-field-group' slugs.
* @date    21/1/19
* @since ACF 5.7.10
* @param string  $slug          The post slug.
* @param integer $post_ID       Post ID.
* @param string  $post_status   The post status.
* @param string  $post_type     Post type.
* @param integer $post_parent   Post parent ID
* @param string  $original_slug The original post slug.

## `acf_flush_field_group_cache()`

acf_flush_field_group_cache

* Deletes all caches for this field group.
* @date    22/1/19
* @since ACF 5.7.10
* @param   array $field_group The field group array.
* @return void

## `acf_delete_field_group()`

acf_delete_field_group

* Deletes a field group from the database.
* @date    21/1/19
* @since ACF 5.7.10
* @param   (int|string) $id The field group ID, key or name.
* @return boolean True if field group was deleted.

## `acf_trash_field_group()`

acf_trash_field_group

* Trashes a field group from the database.
* @date    2/10/13
* @since ACF 5.0.0
* @param   (int|string) $id The field group ID, key or name.
* @return boolean True if field group was trashed.

## `acf_untrash_field_group()`

acf_untrash_field_group

* Restores a field_group from the trash.
* @date    2/10/13
* @since ACF 5.0.0
* @param   (int|string) $id The field_group ID, key or name.
* @return boolean True if field_group was trashed.

## `_acf_untrash_field_group_post_status()`

Filter callback which returns the previous post_status instead of "draft" for the "acf-field-group" post type.

* Prior to WordPress 5.6.0, this filter was not needed as restored posts were always assigned their original status.
* @since ACF 5.9.5
* @param string  $new_status      The new status of the post being restored.
* @param integer $post_id         The ID of the post being restored.
* @param string  $previous_status The status of the post at the point where it was trashed.
* @return string.

## `acf_is_field_group()`

acf_is_field_group

* Returns true if the given params match a field group.
* @date    21/1/19
* @since ACF 5.7.10
* @param   array $field_group The field group array.
* @param mixed $id          An optional identifier to search for.
* @return boolean

## `acf_duplicate_field_group()`

acf_duplicate_field_group

* Duplicates a field group.
* @date    16/06/2014
* @since ACF 5.0.0
* @param   (int|string) $id          The field_group ID, key or name.
* @param integer      $new_post_id Optional post ID to override.
* @return array The new field group.

## `acf_update_field_group_active_status()`

Activates or deactivates a field group.

* @param integer|string $id       The field_group ID, key or name.
* @param boolean        $activate True if the post should be activated.
* @return boolean

## `acf_get_field_group_style()`

acf_get_field_group_style

* Returns the CSS styles generated from field group settings.
* @date    20/10/13
* @since ACF 5.0.0
* @param   array $field_group The field group array.
* @return string.

## `acf_get_field_group_edit_link()`

acf_get_field_group_edit_link

* Checks if the current user can edit the field group and returns the edit url.
* @date    23/9/18
* @since ACF 5.7.7
* @param   integer $post_id The field group ID.
* @return string

## `acf_prepare_field_group_for_export()`

acf_prepare_field_group_for_export

* Returns a modified field group ready for export.
* @date    11/03/2014
* @since ACF 5.0.0
* @param   array $field_group The field group array.
* @return array

## `acf_prepare_field_group_for_import()`

acf_prepare_field_group_for_import

* Prepares a field group for the import process.
* @date    21/11/19
* @since ACF 5.8.8
* @param   array $field_group The field group array.
* @return array

## `acf_import_field_group()`

acf_import_field_group

* Imports a field group into the databse.
* @date    11/03/2014
* @since ACF 5.0.0
* @param   array $field_group The field group array.
* @return array The new field group.

## `acf_get_combined_field_group_settings_tabs()`

Returns an array of tabs for the field group settings.
We combine a list of default tabs with filtered tabs.
I.E. Default tabs should be static and should not be changed by the
filtered tabs.

* @since ACF 6.1
* @return array Key/value array of the default settings tabs for field group settings.

## `acf_field_group_has_location_type()`

Checks if a field group has the provided location rule.

* @since ACF 6.2.8
* @param integer $post_id  The post ID of the field group.
* @param string  $location The location type to check for.
* @return boolean

---
