# Scf Ui Options Page Functions Global Functions

## `acf_get_ui_options_page()`

Get an SCF UI options page as an array

* @since ACF 6.2
* @param integer|string $id The post ID being queried.
* @return array|false The UI options page array.

## `acf_get_raw_ui_options_page()`

Retrieves a raw SCF UI options page.

* @since   ACF 6.2
* @param integer|string $id The post ID.
* @return array|false The UI options page array.

## `acf_get_ui_options_page_post()`

Gets a post object for an SCF UI options page.

* @since ACF 6.2
* @param integer|string $id The post ID, key, or name.
* @return object|boolean The post object, or false on failure.

## `acf_is_ui_options_page_key()`

Returns true if the given identifier is an SCF UI options page key.

* @since ACF 6.2
* @param string $id The identifier.
* @return boolean

## `acf_validate_ui_options_page()`

Validates an SCF UI options page.

* @since ACF 6.2
* @param array $ui_options_page The SCF UI options page array to validate.
* @return array|boolean

## `acf_translate_ui_options_page()`

Translates the settings for an SCF UI options page.

* @since ACF 6.2
* @param array $ui_options_page The SCF UI options page array.
* @return array

## `acf_get_ui_options_pages()`

Returns and array of SCF UI options pages for the given $filter.

* @since ACF 6.2
* @param array $filter An array of args to filter results by.
* @return array

## `acf_get_raw_ui_options_pages()`

Returns an array of raw SCF UI options pages.

* @since ACF 6.2
* @return array

## `acf_filter_ui_options_pages()`

Returns a filtered array of SCF UI options pages based on the given $args.

* @since ACF 6.2
* @param array $ui_options_pages An array of SCF UI options pages.
* @param array $args             An array of args to filter by.
* @return array

## `acf_update_ui_options_page()`

Updates an SCF UI options page in the database.

* @since ACF 6.2
* @param array $ui_options_page The main ACF UI options page array.
* @return array

## `acf_flush_ui_options_page_cache()`

Deletes all caches for the provided ACF UI options page.

* @since ACF 6.2
* @param array $ui_options_page The SCF UI options page array.
* @return void

## `acf_delete_ui_options_page()`

Deletes an ACF UI options page from the database.

* @since ACF 6.2
* @param integer|string $id The ACF UI options page ID, key or name.
* @return boolean True if the options page was deleted.

## `acf_trash_ui_options_page()`

Trashes an ACF UI options page.

* @since ACF 6.2
* @param integer|string $id The UI options page ID, key, or name.
* @return boolean True if the options page was trashed.

## `acf_untrash_ui_options_page()`

Restores an ACF UI options page from the trash.

* @since ACF 6.2
* @param integer|string $id The UI options page ID, key, or name.
* @return boolean True if the options page was untrashed.

## `acf_is_ui_options_page()`

Returns true if the given params match an ACF UI options page.

* @since ACF 6.2
* @param array $ui_options_page The ACF UI options page array.
* @return boolean

## `acf_duplicate_ui_options_page()`

Duplicates an ACF UI options page.

* @since ACF 6.2
* @param integer|string $id          The ACF UI options page ID, key or name.
* @param integer        $new_post_id Optional ID to override.
* @return array|boolean The new ACF UI options page, or false on failure.

## `acf_update_ui_options_page_active_status()`

Activates or deactivates an ACF UI options page.

* @since ACF 6.2
* @param integer|string $id       The ACF UI options page ID, key or name.
* @param boolean        $activate True if the UI options page should be activated.
* @return boolean

## `acf_get_ui_options_page_edit_link()`

Checks if the current user can edit the UI options page and returns the edit URL.

* @since ACF 6.2
* @param integer $post_id The ACF UI options page ID.
* @return string

## `acf_prepare_ui_options_page_for_export()`

Returns a modified ACF UI options page ready for export.

* @since ACF 6.2
* @param array $ui_options_page The ACF UI options page array.
* @return array

## `acf_export_ui_options_page_as_php()`

Exports an ACF UI options page as PHP.

* @since ACF 6.2
* @param array $ui_options_page The ACF UI options page array.
* @return string|boolean

## `acf_prepare_ui_options_page_for_import()`

Prepares an ACF UI options page for the import process.

* @since ACF 6.2
* @param array $ui_options_page The ACF UI options page array.
* @return array

## `acf_import_ui_options_page()`

Imports an ACF UI options page into the database.

* @since ACF 6.2
* @param array $ui_options_page The ACF UI options page array.
* @return array The imported options page.

---
