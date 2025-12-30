# Blocks Global Functions

## `acf_add_block_namespace()`

Prefix block names for SCF blocks registered through block.json

* @since ACF 6.0.0
* @param array $metadata The block metadata array.
* @return array The original array with a prefixed block name if it's an ACF block.

## `acf_handle_json_block_registration()`

Handle an SCF block registered through block.json

* @since ACF 6.0.0
* @param array $settings The compiled block settings.
* @param array $metadata The raw json metadata.
* @return array Block registration settings with ACF required additions.

## `acf_is_acf_block_json()`

Check if a block.json block is an SCF block.

* @since ACF 6.0.0
* @param array $metadata The raw block metadata array.
* @return boolean

## `acf_register_block_type()`

Registers a block type.

* @date    18/2/19
* @since ACF 5.8.0
* @param   array $block The block settings.
* @return (array|false)

## `acf_register_block()`

See acf_register_block_type().

* @date    18/2/19
* @since ACF 5.7.12
* @param   array $block The block settings.
* @return (array|false)

## `acf_has_block_type()`

Returns true if a block type exists for the given name.

* @since   ACF 5.7.12
* @param   string $name The block type name.
* @return boolean

## `acf_get_block_types()`

Returns an array of all registered block types.

* @since   ACF 5.7.12
* @return  array

## `acf_get_block_type()`

Returns a block type for the given name.

* @since   ACF 5.7.12
* @param   string $name The block type name.
* @return (array|null)

## `acf_remove_block_type()`

Removes a block type for the given name.

* @since   ACF 5.7.12
* @param   string $name The block type name.
* @return void

## `acf_get_block_type_default_attributes()`

Returns an array of default attribute settings for a block type.

* @date    19/11/18
* @since ACF 5.8.0
* @param array $block_type A block configuration array.
* @return array

## `acf_validate_block_type()`

Validates a block type ensuring all settings exist.

* @since   ACF 5.8.0
* @param   array $block The block settings.
* @return array

## `acf_prepare_block()`

Prepares a block for use in render_callback by merging in all settings and attributes.

* @since   ACF 5.8.0
* @param   array $block The block props.
* @return array|boolean

## `acf_add_back_compat_attributes()`

Add backwards compatible attribute values.

* @since ACF 6.0.0
* @param array $block The original block.
* @return array Modified block array with backwards compatibility attributes.

## `acf_get_block_back_compat_attribute_key_array()`

Get back compat new values and old values.

* @since ACF 6.0.0
* @return array back compat key array.

## `acf_render_block_callback()`

The render callback for all ACF blocks.

* @date    28/10/20
* @since ACF 5.9.2
* @param   array    $attributes The block attributes.
* @param string   $content    The block content.
* @param WP_Block $wp_block   The block instance (since WP 5.5).
* @return string The block HTML.

## `acf_rendered_block()`

Returns the rendered block HTML.

* @date    28/2/19
* @since ACF 5.7.13
* @param   array    $attributes     The block attributes.
* @param string   $content        The block content.
* @param boolean  $is_preview     Whether or not the block is being rendered for editing preview.
* @param integer  $post_id        The current post being edited or viewed.
* @param WP_Block $wp_block       The block instance (since WP 5.5).
* @param array    $context        The block context array.
* @param boolean  $is_ajax_render Whether or not this is an ACF AJAX render.
* @return string   The block HTML.

## `acf_render_block()`

Renders the block HTML.

* @since   ACF 5.7.12
* @param   array    $attributes The block attributes.
* @param string   $content    The block content.
* @param boolean  $is_preview Whether or not the block is being rendered for editing preview.
* @param integer  $post_id    The current post being edited or viewed.
* @param WP_Block $wp_block   The block instance (since WP 5.5).
* @param array    $context    The block context array.
* @return void|string

## `acf_block_render_template()`

Locate and include an ACF block's template.

* @since   ACF 6.0.4
* @param   array   $block      The block props.
* @param string  $content    The block content.
* @param boolean $is_preview Whether this is a preview render.
* @param int     $post_id    The post ID this block is saved to.
* @param object  $wp_block   The block instance object.
* @param array   $context    The block context array.

## `acf_get_block_fields()`

Returns an array of all fields for the given block.

* @date    24/10/18
* @since ACF 5.8.0
* @param   array $block The block props.
* @return array

## `acf_enqueue_block_assets()`

Enqueues and localizes block scripts and styles.

* @since   ACF 5.7.13
* @return  void

## `acf_enqueue_in_iframe_styles()`

Enqueues scripts and styles to load inside the block editor iframe.
This allows us to do things like style contenteditable, and other inline editing elements.

* @since ACF 6.7

## `acf_enqueue_block_type_assets()`

Enqueues scripts and styles for a specific block type.

* @since   ACF 5.7.13
* @param   array $block_type The block type settings.
* @return void

## `acf_ajax_fetch_block()`

Handles the ajax request for block data.

* @since   ACF 5.7.13
* @return  void

## `acf_get_empty_block_form_html()`

Render the empty block form for when a block has no fields assigned.

* @since   ACF 6.0.0
* @param   string $block_name The block name current being rendered.
* @return string The html that makes up a block form with no fields.

## `acf_parse_save_blocks()`

Parse content that may contain HTML block comments and saves ACF block meta.

* @since   ACF 5.7.13
* @param   string $text Content that may contain HTML block comments.
* @return string

## `acf_parse_save_blocks_callback()`

Callback used in preg_replace to modify ACF Block comment.

* @since   ACF 5.7.13
* @param   array $matches The preg matches.
* @return string

## `acf_get_block_id()`

Return or generate a block ID.

* @since ACF 6.0.0
* @param array   $attributes A block attributes array.
* @param array   $context    The block context array, defaults to an empty array.
* @param boolean $force      If we should generate a new block ID even if one exists.
* @return string A block ID.

## `acf_ensure_block_id_prefix()`

Ensure a block ID always has a block_ prefix for post meta internals.

* @since ACF 6.0.0
* @param string $block_id A possibly non-prefixed block ID.
* @return string A prefixed block ID.

## `acf_serialize_block_attributes()`

This directly copied from the WordPress core `serialize_block_attributes()` function.

* We need this in order to make sure that block attributes are stored in a way that is
consistent with how Gutenberg sends them over from JS, and so that things like wp_kses()
work as expected. Copied from core to get around a bug that was fixed in 5.8.1 or on the off chance
that folks are still using WP 5.3 or below.
* TODO: Remove this when we refactor `acf_parse_save_blocks_callback()` to use `serialize_block()`,
or when we're confident that folks aren't using WP versions prior to 5.8.
* @since ACF 5.12
* @param array $block_attributes Attributes object.
* @return string Serialized attributes.

## `acf_get_block_validation_state()`

Handle validating a block's fields and return the validity, and any errors.

* This function can use values loaded into Local Meta, which means they have to be
converted back to the data format before they can be validated.
* @since ACF 6.3
* @param array   $block          An array of the block's data attribute.
* @param boolean $using_defaults True if the block is currently being generated with default values. Default false.
* @param boolean $use_post_data  True if we should validate the POSTed data rather than local meta values. Default false.
* @param boolean $on_load        True if we're validating as part of a render. This is essentially the same as a first load. Default false.
* @return array An array containing a valid boolean, and an errors array.

## `acf_validate_block_from_post_data()`

Handle the specific validation for a block from POSTed values.

* @since ACF 6.3.1
* @param array $block The block object containing the POSTed values and other block data.
* @return array|boolean An array containing the validation errors, or false if there are no errors.

## `acf_validate_block_from_local_meta()`

Handle the specific validation for a block from local meta.

* This function uses the values loaded into Local Meta, which means they have to be
converted back to the data format because they can be validated.
* @since ACF 6.3.1
* @param string  $block_id       The block ID.
* @param array   $field_objects  The field objects in local meta to be validated.
* @param boolean $using_defaults True if this is the first load of the block, when special validation may apply.
* @return array|boolean An array containing the validation errors, or false if there are no errors.

## `acf_set_after_rest_media_enqueue_reset_flag()`

Set ACF data before a rest call if media scripts have not been enqueued yet for after REST reset.

* @date    07/06/22
* @since ACF 6.0
* @param   WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response The WordPress response object.
* @return mixed

## `acf_reset_media_enqueue_after_rest()`

Reset wp_enqueue_media action count after REST call so it can happen inside the main execution if required.

* @date    07/06/22
* @since ACF 6.0
* @param   WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response The WordPress response object.
* @return mixed

## `acf_block_uses_post_meta()`

Checks if the provided block is configured to save/load post meta.

* @since ACF 6.3
* @param array $block The block to check.
* @return boolean

## `acf_add_block_meta_values()`

Loads ACF field values from the post meta if the block is configured to do so.

* @since ACF 6.3
* @param array   $block   The block to get values for.
* @param integer $post_id The ID of the post to retrieve meta from.
* @return array

## `acf_save_block_meta_values()`

Stores ACF field values in post meta for any blocks configured to do so.

* @since ACF 6.3
* @param integer $post_id The ID of the post being saved.
* @param WP_Post $post    The post object.
* @return void

## `acf_get_block_meta_values_to_save()`

Iterates over blocks in post content and retrieves values
that need to be saved to post meta.

* @since ACF 6.3
* @param string $content The content saved for the post.
* @return array An array containing the field values that need to be saved.

## `acf_inline_toolbar_editing_attrs()`

Helper function that returns the HTML attributes required for toolbar inline editing as a string, escaped and ready for output.

* Required. A list of the fields, each of which will be displayed in the popup toolbar.
Each field can be passed as one of the following.
  * A string (e.g. `'my_field_name'`)
  * An associative array with specific keys:

* @type string  $field_name  The name of the field to display in the toolbar.
* @type string  $field_icon  An html tag, can be an svg, to be used as the toolbar icon. If not passed, the icon of the first field will be used.
* @type string  $field_label A string to use as the label for the button in the toolbar.
* @type boolean $use_expanded_editor Default is false, which opens the field in the popover. Set to true to open in the expanded editor.
* @type string  $popover_min_width Enter the CSS width value to use for the popover. Default is "300px".
* @param array $fields List of fields.
* @param array $args   Additional options controlling toolbar display and behavior.
* @type string $toolbar_icon  Optional. An html tag, can be an svg, to be used as the toolbar icon. If not passed, the icon of the first field will be used.
* @type string $toolbar_title Optional. A string to be used as the toolbar title. If not passed, the name of the first field will be used.
* @type string $uid           Optional. A unique identifier that isn't used by any other inline fields in this block. Pass if you have 2 elements that conflict.
* @return string A string containing the attributes.

## `acf_inline_text_editing_attrs()`

Helper function that returns the HTML attributes required for inline text editing as a string, escaped and ready for output.

* @param string $field_name A string which is the name of the field to update when the user types into the HTML element.
* @param array  $args       Array {
Optional. An array of additional args which can control how the popover identifier is displayed.
* @type string $toolbar_icon  Optional. An html tag, can be an svg, to be used as the toolbar icon. If not passed, the icon of the first field will be used.
* @type string $toolbar_title Optional. A string to be used as the toolbar title. If not passed, the name of the first field will be used.
* @type string $placeholder   Optional. Optional. A string which will be used as the placeholder in the typable text area.
}
* @return string A string containing the attributes.

## `acf_process_block_toolbar_fields()`

This function prepares a fields array for being localized and used on the frontend as block toolbar fields.

* Required. A list of the fields, each of which will be displayed in the popup toolbar.
Each field can be passed as one of the following.
  * A string (e.g. `'my_field_name'`)
  * An associative array with specific keys:

* @type string $field_name  The name of the field to display in the toolbar.
* @type string $field_icon  An html tag, can be an svg, to be used as the toolbar icon. If not passed, the icon of the first field will be used.
* @type string $field_label A string to use as the label for the button in the toolbar.
* @param array $fields List of fields.
* @return array The array of fields, prepared for JS localization.

## `acf_inline_editing_field_is_empty()`

Helper function for block render templates to check if an acf field has a value.
This is relevant when autoInlineEditing is enabled for a block, because empty fields
will have acf_auto_inline_editing_field_name_ + field_name as their value if they are empty.

* @param string $field_name Field name.
* @return boolean True if the field is empty, false if it has a value.

---
