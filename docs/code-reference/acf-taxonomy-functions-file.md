# Acf Taxonomy Functions Global Functions

## `acf_get_taxonomy()`

Get an ACF taxonomy as an array

* @param integer|string $id The post ID being queried.
* @return array|false The taxonomy object.

## `acf_get_raw_taxonomy()`

Retrieves a raw ACF taxonomy.

* @since   ACF 6.1
* @param   integer|string $id The post ID.
* @return array|false The taxonomy array.

## `acf_get_taxonomy_post()`

Gets a post object for an ACF taxonomy.

* @since ACF 6.1
* @param integer|string $id The post ID, key, or name.
* @return object|boolean The post object, or false on failure.

## `acf_is_taxonomy_key()`

Returns true if the given identifier is an ACF taxonomy key.

* @since ACF 6.1
* @param string $id The identifier.
* @return boolean

## `acf_validate_taxonomy()`

Validates an ACF taxonomy.

* @since ACF 6.1
* @param array $taxonomy The ACF taxonomy array.
* @return array|boolean

## `acf_translate_taxonomy()`

Translates the settings for an ACF taxonomy.

* @since ACF 6.1
* @param array $taxonomy The ACF taxonomy array.
* @return array

## `acf_get_acf_taxonomies()`

Returns an array of ACF taxonomies for the given $filter.

* @since ACF 6.1
* @param array $filter An array of args to filter results by.
* @return array

## `acf_get_raw_taxonomies()`

Returns an array of raw ACF taxonomies.

* @since ACF 6.1
* @return array

## `acf_filter_taxonomies()`

Returns a filtered array of ACF taxonomies based on the given $args.

* @since ACF 6.1
* @param array $taxonomies An array of ACF taxonomies.
* @param array $args       An array of args to filter by.
* @return array

## `acf_update_taxonomy()`

Updates an ACF taxonomy in the database.

* @since   ACF 6.1
* @param array $taxonomy The main ACF taxonomy array.
* @return array

## `acf_flush_taxonomy_cache()`

Deletes all caches for the provided ACF taxonomy.

* @since ACF 6.1
* @param array $taxonomy The ACF taxonomy array.
* @return void

## `acf_delete_taxonomy()`

Deletes an ACF taxonomy from the database.

* @since ACF 6.1
* @param integer|string $id The ACF taxonomy ID, key or name.
* @return boolean True if taxonomy was deleted.

## `acf_trash_taxonomy()`

Trashes an ACF taxonomy.

* @since ACF 6.1
* @param integer|string $id The taxonomy ID, key, or name.
* @return boolean True if taxonomy was trashed.

## `acf_untrash_taxonomy()`

Restores an ACF taxonomy from the trash.

* @since ACF 6.1
* @param integer|string $id The taxonomy ID, key, or name.
* @return boolean True if taxonomy was untrashed.

## `acf_is_taxonomy()`

Returns true if the given params match an ACF taxonomy.

* @since ACF 6.1
* @param array $taxonomy The ACF taxonomy array.
* @return boolean

## `acf_duplicate_taxonomy()`

Duplicates an ACF taxonomy.

* @since ACF 6.1
* @param integer|string $id          The ACF taxonomy ID, key or name.
* @param integer        $new_post_id Optional ID to override.
* @return array|boolean The new ACF taxonomy, or false on failure.

## `acf_update_taxonomy_active_status()`

Activates or deactivates an ACF taxonomy.

* @param integer|string $id       The ACF taxonomy ID, key or name.
* @param boolean        $activate True if the taxonomy should be activated.
* @return boolean

## `acf_get_taxonomy_edit_link()`

Checks if the current user can edit the taxonomy and returns the edit url.

* @since ACF 6.1
* @param integer $post_id The ACF taxonomy ID.
* @return string

## `acf_prepare_taxonomy_for_export()`

Returns a modified ACF taxonomy ready for export.

* @since ACF 6.1
* @param array $taxonomy The ACF taxonomy array.
* @return array

## `acf_export_taxonomy_as_php()`

Exports an ACF taxonomy as PHP.

* @since ACF 6.1
* @param array $taxonomy The ACF taxonomy array.
* @return string|boolean

## `acf_prepare_taxonomy_for_import()`

Prepares an ACF taxonomy for the import process.

* @since ACF 6.1
* @param array $taxonomy The ACF taxonomy array.
* @return array

## `acf_import_taxonomy()`

Imports an ACF taxonomy into the database.

* @since ACF 6.1
* @param array $taxonomy The ACF taxonomy array.
* @return array The imported taxonomy.

---
