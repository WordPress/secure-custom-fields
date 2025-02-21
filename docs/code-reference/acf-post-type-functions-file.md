# Acf Post Type Functions Global Functions

## `acf_get_post_type()`

Get an ACF CPT as an array

* @param integer|string $id The post ID being queried.
* @return array|false The post type object.

## `acf_get_raw_post_type()`

Retrieves a raw ACF CPT.

* @since   ACF 6.1
* @param   integer|string $id The post ID.
* @return array|false The internal post type array.

## `acf_get_post_type_post()`

Gets a post object for an ACF CPT.

* @since ACF 6.1
* @param integer|string $id The post ID, key, or name.
* @return object|boolean The post object, or false on failure.

## `acf_is_post_type_key()`

Returns true if the given identifier is an ACF CPT key.

* @since ACF 6.1
* @param string $id The identifier.
* @return boolean

## `acf_validate_post_type()`

Validates an ACF CPT.

* @since ACF 6.1
* @param array $post_type The ACF post type array.
* @return array|boolean

## `acf_translate_post_type()`

Translates the settings for an ACF internal post type.

* @since ACF 6.1
* @param array $post_type The ACF post type array.
* @return array

## `acf_get_acf_post_types()`

Returns and array of ACF post types for the given $filter.

* @since ACF 6.1
* @param array $filter An array of args to filter results by.
* @return array

## `acf_get_raw_post_types()`

Returns an array of raw ACF post types.

* @since ACF 6.1
* @return array

## `acf_filter_post_types()`

Returns a filtered array of ACF post types based on the given $args.

* @since ACF 6.1
* @param array $post_types An array of ACF posts.
* @param array $args       An array of args to filter by.
* @return array

## `acf_update_post_type()`

Updates an ACF post type in the database.

* @since   ACF 6.1
* @param array $post_type The main ACF post type array.
* @return array

## `acf_flush_post_type_cache()`

Deletes all caches for the provided ACF post type.

* @since ACF 6.1
* @param array $post_type The ACF post type array.
* @return void

## `acf_delete_post_type()`

Deletes an ACF post type from the database.

* @since ACF 6.1
* @param integer|string $id The ACF post type ID, key or name.
* @return boolean True if post type was deleted.

## `acf_trash_post_type()`

Trashes an ACF post type.

* @since ACF 6.1
* @param integer|string $id The post type ID, key, or name.
* @return boolean True if post was trashed.

## `acf_untrash_post_type()`

Restores an ACF post type from the trash.

* @since ACF 6.1
* @param integer|string $id The post type ID, key, or name.
* @return boolean True if post was untrashed.

## `acf_is_post_type()`

Returns true if the given params match an ACF post type.

* @since ACF 6.1
* @param array $post_type The ACF post type array.
* @return boolean

## `acf_duplicate_post_type()`

Duplicates an ACF post type.

* @since ACF 6.1
* @param integer|string $id          The ACF post type ID, key or name.
* @param integer        $new_post_id Optional ID to override.
* @return array|boolean The new ACF post type, or false on failure.

## `acf_update_post_type_active_status()`

Activates or deactivates an ACF post type.

* @param integer|string $id       The ACF post type ID, key or name.
* @param boolean        $activate True if the post type should be activated.
* @return boolean

## `acf_get_post_type_edit_link()`

Checks if the current user can edit the post type and returns the edit url.

* @since ACF 6.1
* @param integer $post_id The ACF post type ID.
* @return string

## `acf_prepare_post_type_for_export()`

Returns a modified ACF post type ready for export.

* @since ACF 6.1
* @param array $post_type The ACF post type array.
* @return array

## `acf_export_post_type_as_php()`

Exports an ACF post type as PHP.

* @since ACF 6.1
* @param array $post_type The ACF post type array.
* @return string|boolean

## `acf_prepare_post_type_for_import()`

Prepares an ACF post type for the import process.

* @since ACF 6.1
* @param array $post_type The ACF post type array.
* @return array

## `acf_import_post_type()`

Imports an ACF post type into the database.

* @since ACF 6.1
* @param array $post_type The ACF post type array.
* @return array The imported post type.

## `acf_export_enter_title_here()`

Exports the "Enter Title Here" text for the provided ACF post types.

* @since ACF 6.2.1
* @param array $post_types The post types being exported.
* @return string

---
