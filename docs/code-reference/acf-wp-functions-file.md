# Acf Wp Functions Global Functions

## `acf_get_object_type()`

Returns a WordPress object type.

* @date    1/4/20
* @since ACF 5.9.0
* @param   string $object_type    The object type (post, term, user, etc).
* @param string $object_subtype Optional object subtype (post type, taxonomy).
* @return object

## `acf_decode_post_id()`

Decodes a post_id value such as 1 or "user_1" into an array containing the type and ID.

* @date    25/1/19
* @since ACF 5.7.11
* @param   (int|string) $post_id The post id.
* @return array

## `acf_get_object_type_rest_base()`

Determine the REST base for a post type or taxonomy object. Note that this is not intended for use
with term or post objects but is, instead, to be used with the underlying WP_Post_Type and WP_Taxonomy
instances.

* @param WP_Post_Type|WP_Taxonomy $type_object
* @return string|null

## `acf_get_object_id()`

Extract the ID of a given object/array. This supports all expected types handled by our update_fields() and
load_fields() callbacks.

* @param WP_Post|WP_User|WP_Term|WP_Comment|array $object
* @return integer|mixed|null

## `acf_insert_or_update_internal_post()`

Inserts or updates one of ACF's internal posts (fields, field groups, post types,
taxonomies and options pages), storing its serialized `post_content` exactly as given.

* Filters on `content_save_pre` (kses, `convert_invalid_entities`, etc.) treat the
serialized settings as HTML and can change string lengths, which corrupts them.
Settings must already be sanitized, see _acf_maybe_kses_internal_post_settings().
* @since ACF 6.8.11
* @param array $postarr Slashed post data, as passed to wp_insert_post().
* @return integer|WP_Error The post ID on success. The value 0 or WP_Error on failure.

## `_acf_preserve_internal_post_content()`

Private callback for the "wp_insert_post_data" filter.

* Restores the unsanitized `post_content` of ACF's internal posts when it's the content
being saved by acf_insert_or_update_internal_post(), or when an update leaves the content
unchanged, such as trashing or restoring. Both values are slashed at this point.
Follows WP_Customize_Manager::preserve_insert_changeset_post_content().
* @since ACF 6.8.11
@internal
* @param array   $data                An array of slashed, sanitized post data.
* @param array   $postarr             An array of sanitized post data.
* @param array   $unsanitized_postarr An array of slashed yet unsanitized post data.
* @param boolean $update              Whether this is an existing post being updated.
* @return array

## `acf_skip_internal_post_sanitization()`

Runs a callback that saves ACF internal posts from a trusted source, without
sanitizing their settings for the current user.

* Settings saved by users without the `unfiltered_html` capability are otherwise filtered
with wp_kses_post(). Use this only for settings that are already stored or sanitized,
or that come from the server, such as local JSON files. Never for user input.
* @since ACF 6.8.11
* @param callable $callback The callback that saves the posts.
* @param mixed    ...$args  Arguments to pass to the callback.
* @return mixed The callback's return value.

## `_acf_should_sanitize_internal_post_settings()`

Checks whether the settings of an ACF internal post being saved need sanitizing, because
the current user can't use unfiltered HTML and sanitization isn't skipped with
acf_skip_internal_post_sanitization().

* @since ACF 6.8.11
@internal
* @return boolean

## `_acf_maybe_kses_internal_post_settings()`

Sanitizes the settings of an ACF internal post being saved, when the current user can't
use unfiltered HTML and sanitization isn't skipped with acf_skip_internal_post_sanitization().

* Settings are stored exactly as given, so this does what kses on `post_content` would
otherwise do for these users, without corrupting the serialized data.
* @since ACF 6.8.11
@internal
* @param array $settings The unslashed settings.
* @return array

## `_acf_kses_post_deep_with_keys()`

Recursively runs wp_kses_post() over string keys and string values that contain HTML.

* Browsers only start a tag at "<" followed by a letter, "/", "!" or "?", so other text
can't contain HTML and is left exactly as typed. Otherwise kses can rewrite plain text,
such as "&" or entities, breaking choice keys and conditional logic operators like "<".
* @since ACF 6.8.11
@internal
* @param mixed $data The data to sanitize.
* @return mixed

---
