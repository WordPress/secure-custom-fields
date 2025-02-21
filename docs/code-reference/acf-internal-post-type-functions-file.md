# Acf Internal Post Type Functions Global Functions

## `acf_get_internal_post_type_instance()`

Gets an instance of an ACF_Internal_Post_Type.

* @param string $post_type The ACF internal post type to get the instance for.
* @return ACF_Internal_Post_Type|bool The internal post type class instance, or false on failure.

## `acf_get_internal_post_type()`

Get an ACF CPT object as an array

* @param integer $id        The post ID being queried.
* @param string  $post_type The post type being queried.
* @return array|false The post type object.

## `acf_get_raw_internal_post_type()`

Retrieves raw internal post type data for the given identifier.

* @since   ACF 6.1
* @param   integer|string $id        The post ID.
* @param string         $post_type The post type name.
* @return array|false The internal post type array.

## `acf_get_internal_post_type_post()`

Gets a post object from an ACF internal post type.

* @since ACF 6.1
* @param integer|string $id        The post ID, key, or name.
* @param string         $post_type The post type name.
* @return object|boolean The post object, or false on failure.

## `acf_is_internal_post_type_key()`

Returns true if the given identifier is a ACF internal post type key.

* @since ACF 6.1
* @param string $id        The identifier.
* @param string $post_type The ACF post type the key is for.
* @return boolean

## `acf_validate_internal_post_type()`

Validates an ACF internal post type.

* @since ACF 6.1
* @param array  $internal_post_type The internal post type array.
* @param string $post_type_name     The post type name.
* @return array|boolean

## `acf_translate_internal_post_type()`

Translates the settings for an ACF internal post type.

* @since ACF 6.1
* @param array  $internal_post_type The ACF post array.
* @param string $post_type          The post type name.
* @return array

## `acf_get_internal_post_type_posts()`

Returns and array of ACF posts for the given $filter.

* @since ACF 6.1
* @param string $post_type The ACF post type to get posts for.
* @param array  $filter    An array of args to filter results by.
* @return array

## `acf_get_raw_internal_post_type_posts()`

Returns an array of raw/unvalidated ACF post data.

* @since ACF 6.1
* @param string $post_type The ACF post type to get post data for.
* @return array

## `acf_filter_internal_post_type_posts()`

Returns a filtered array of ACF posts based on the given $args.

* @since ACF 6.1
* @param array  $posts     An array of ACF posts.
* @param array  $args      An array of args to filter by.
* @param string $post_type The ACF post type of the posts being filtered.
* @return array

## `acf_update_internal_post_type()`

Updates a internal post type in the database.

* @since   ACF 6.1
* @param array  $internal_post_type Array of data to be saved.
* @param string $post_type_name     The internal post type being updated.
* @return array

## `acf_flush_internal_post_type_cache()`

Deletes all caches for the provided ACF post.

* @since ACF 6.1
* @param array  $post      The ACF post array.
* @param string $post_type The ACF post type the cache is being cleared for.
* @return void

## `acf_delete_internal_post_type()`

Deletes an internal post type from the database.

* @since ACF 6.1
* @param integer|string $id             The internal post type ID, key or name.
* @param string         $post_type_name The post type to be deleted.
* @return boolean True if field group was deleted.

## `acf_trash_internal_post_type()`

Trashes an internal post type.

* @since ACF 6.1
* @param integer|string $id             The internal post type ID, key, or name.
* @param string         $post_type_name The post type being trashed.
* @return boolean True if post was trashed.

## `acf_untrash_internal_post_type()`

Restores an ACF post from the trash.

* @since ACF 6.1
* @param integer|string $id             The internal post type ID, key, or name.
* @param string         $post_type_name The post type being untrashed.
* @return boolean True if post was untrashed.

## `acf_is_internal_post_type()`

Returns true if the given params match an ACF post.

* @since ACF 6.1
* @param array  $post      The ACF post array.
* @param string $post_type The ACF post type.
* @return boolean

## `acf_duplicate_internal_post_type()`

Duplicates an ACF post.

* @since ACF 6.1
* @param integer|string $id          The field_group ID, key or name.
* @param integer        $new_post_id Optional ID to override.
* @param string         $post_type   The post type of the post being duplicated.
* @return array|boolean The new ACF post, or false on failure.

## `acf_update_internal_post_type_active_status()`

Activates or deactivates an ACF post.

* @param integer|string $id        The field_group ID, key or name.
* @param boolean        $activate  True if the post should be activated.
* @param string         $post_type The post type being activated/deactivated.
* @return boolean

## `acf_get_internal_post_type_edit_link()`

Checks if the current user can edit the field group and returns the edit url.

* @since ACF 6.1
* @param integer $post_id   The ACF post ID.
* @param string  $post_type The ACF post type to get the edit link for.
* @return string

## `acf_prepare_internal_post_type_for_export()`

Returns a modified field group ready for export.

* @since ACF 6.1
* @param array  $post      The ACF post array.
* @param string $post_type The post type of the ACF post being exported.
* @return array

## `acf_export_internal_post_type_as_php()`

Exports an ACF post as PHP.

* @since ACF 6.1
* @param array  $post      The ACF post array.
* @param string $post_type The post type of the ACF post being exported.
* @return string|boolean

## `acf_prepare_internal_post_type_for_import()`

Prepares an ACF post for the import process.

* @since ACF 6.1
* @param array  $post      The ACF post array.
* @param string $post_type The post type of the ACF post being imported.
* @return array

## `acf_import_internal_post_type()`

Imports an ACF post into the database.

* @since ACF 6.1
* @param array  $post      The ACF post array.
* @param string $post_type The post type of the ACF post being imported.
* @return array The imported post.

## `acf_determine_internal_post_type()`

Tries to determine the ACF post type for the provided key.

* @param string $key The key to check.
* @return string|boolean

## `acf_is_valid_internal_post_type_key()`

Check if the provided key is an identifiable ACF post type.

* @since ACF 6.2.8
* @param string $key The key to check.
* @return boolean

## `acf_internal_post_object_contains_valid_key()`

Check if the provided post type object contains a valid internal post type key.

* @since ACF 6.2.8
* @param array $internal_post_type The post type object array to check it's key.
* @return boolean

## `acf_get_combined_post_type_settings_tabs()`

Returns an array of tabs for the post type advanced settings.

* @since ACF 6.1
* @return array

## `acf_get_combined_taxonomy_settings_tabs()`

Returns an array of tabs for the taxonomy advanced settings.

* @since ACF 6.1
* @return array

## `acf_get_combined_options_page_settings_tabs()`

Returns an array of tabs for the options page advanced settings

* @since ACF 6.2
* @return array

## `acf_get_post_type_from_screen_value()`

Converts an _acf_screen or hook value into a post type.

* @since ACF 6.1
* @param string $screen The ACF screen being viewed.
* @return string The post type matching the screen or hook value.

## `acf_validate_internal_post_type_values()`

Calls the ajax validator for a post type

* @since ACF 6.1
* @param string $post_type The post type being validated.
* @return mixed

## `acf_add_internal_post_type_validation_error()`

Adds a validation error for ACF internal post types.

* @since ACF 6.1
* @param string $name      The name of the input.
* @param string $message   An optional error message to display.
* @param string $post_type Optional post type the error message is for.
* @return void

## `acf_get_post_type_from_request_args()`

Gets an ACF post type from request args and verifies nonce based on action.

* @since ACF 6.1.5
* @param string $action The action being performed.
* @return array|boolean

## `acf_get_taxonomy_from_request_args()`

Gets an ACF taxonomy from request args and verifies nonce based on action.

* @since ACF 6.1.5
* @param string $action The action being performed.
* @return array|boolean

## `acf_get_ui_options_page_from_request_args()`

Gets an ACF options page from request args and verifies nonce based on action.

* @since ACF 6.2
* @param string $action The action being performed.
* @return array|boolean

---
