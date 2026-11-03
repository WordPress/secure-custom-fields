# Acf Form Functions Global Functions

## `acf_set_form_data()`

acf_set_form_data

* Sets data about the current form.
* @date    6/10/13
* @since ACF 5.0.0
* @param   string $name The store name.
* @param array  $data Array of data to start the store with.
* @return ACF_Data

## `acf_get_form_data()`

acf_get_form_data

* Gets data about the current form.
* @date    6/10/13
* @since ACF 5.0.0
* @param   string $name The store name.
* @return mixed

## `acf_form_data()`

acf_form_data

* Called within a form to set important information and render hidden inputs.
* @date    15/10/13
* @since ACF 5.0.0
* @return  void

## `acf_save_post()`

acf_save_post

* Saves the $_POST data.
* @date    15/10/13
* @since ACF 5.0.0
* @param   integer|string $post_id The post id.
* @param array          $values  An array of values to override $_POST.
* @return boolean True if save was successful.

## `acf_is_trusted_save_context()`

Returns whether ACF is saving from a trusted context, WP-CLI or cron, where saved values come from
code rather than from a request.

* @since ACF 6.8.11
* @return boolean

## `_acf_save_submitted_values()`

The internal ACF function to run a callback while saving values submitted through a form or the
REST API. Unless the context is trusted, those values are permission checked.

* @since ACF 6.8.11
* @param callable $callback The callback that saves the values.
* @return mixed The callback's return value.

## `_acf_do_save_post()`

_acf_do_save_post

* Private function hooked into 'acf/save_post' to actually save the $_POST data.
This allows developers to hook in before and after ACF has actually saved the data.
* @date    11/1/19
* @since ACF 5.7.10
* @param   integer|string $post_id The post id.
* @return void

## `acf_get_field_root_key()`

Returns the top-level key a field's input is submitted under, relative to a form's base prefix.

* Fields rendered with the base prefix submit under their own key. Seamless clone sub fields
are rendered with the prefix `{base}[{clone_key}]`, so they submit under the clone's key.
Fields nested deeper (repeater rows, group sub fields) don't have a top-level key of their own.
* @since ACF 6.8.11
* @param array  $field The field array, before acf_prepare_field() (which can change the key of clone sub fields).
* @param string $base  The form's base input prefix, such as `acf`, `widget-text[2][acf]` or `menu-item-acf[12]`.
* @return string The top-level key, or an empty string if the field doesn't submit directly under the base prefix.

## `_acf_get_field_list_nonce_action()`

Returns the nonce action that signs a list of rendered field keys.

* @since ACF 6.8.11
@access private
* @param string $render_id The render ID the list was rendered for.
* @param string $csv       The comma separated, sorted field keys.
* @return string

## `_acf_field_render_state()`

Returns the render state shared by acf_render_fields() and acf_render_field_wrap(), by reference.

* `depth` counts the nested render calls in progress, so only the outermost call outputs a field
list. `render_id` is the render ID of the outermost call, used by inputs that sign themselves for
the object (such as the basic uploader nonce).
* @since ACF 6.8.11
@access private
* @return array

## `_acf_in_field_render()`

Runs a render callback, tracking whether it is the outermost render call.

* @since ACF 6.8.11
@access private
* @param string|false $render_id The render ID, set for the outermost call only.
* @param callable     $callback  The callback that renders.
* @return boolean True if this was the outermost render call.

## `acf_get_current_render_id()`

Returns the render ID of the fields being rendered, for inputs that sign themselves for the object.

* Falls back to the post ID set by acf_form_data() when fields are rendered outside acf_render_fields().
* @since ACF 6.8.11
* @return string

## `_acf_render_field_list()`

Outputs the hidden input listing the top-level field keys a render call output.

* The list is signed for the current user and the render ID (the object the fields were rendered
for), so save handlers can tell which submitted keys were rendered without reconstructing the
form. It is output outside the field wrappers so conditional logic doesn't disable it. Postboxes
hidden by the location rule check disable it with their fields.
* The signature is a nonce, valid for the same 12–24 hours as `_acf_nonce`. If ACF ever refreshes
`_acf_nonce` on long-lived screens, these lists must be refreshed with it.
* @since ACF 6.8.11
@access private
* @param array  $keys      The top-level keys.
* @param string $render_id The render ID.
* @param string $list_name The root name of the input, such as `_acf_fields`.
* @return void

## `acf_get_verified_field_keys()`

Returns the top-level field keys from submitted field lists that verify for any of the given render IDs.

* A verified key proves the server rendered that field for the current user and object. It is
evidence of rendering, not authorisation: the save handler still decides which render IDs and
keys it accepts. No field lookups are made, so fields registered late (after the save handler
runs) are still accepted.
* @since ACF 6.8.11
* @param mixed $lists      The submitted lists, keyed by render ID (usually `$_POST['_acf_fields']`, unslashed).
* @param array $render_ids The render IDs the save accepts.
* @return array The verified top-level keys.

## `_acf_get_verified_list_keys()`

Returns the keys of a submitted field list (`{csv}|{nonce}`) if it verifies for the render ID.

* @since ACF 6.8.11
@access private
* @param mixed  $field_list The submitted list.
* @param string $render_id  The render ID.
* @return array The keys, or an empty array if the list doesn't verify.

## `_acf_get_flat_clone_keys()`

Returns the keys a seamless clone sub field may be submitted under when values are keyed per field
rather than like $_POST['acf']: its cloned key (`{clone_key}_{field_key}`, as from acf_get_fields())
and its original key, which the clone field restores in acf/prepare_field and which the datastore uses.

* @since ACF 6.8.11
@access private
* @param array $field The field array.
* @return array

## `_acf_get_located_field_keys()`

Returns the submitted keys that belong to a top-level field of a field group located on the object.

* Keys are matched by top-level input key only. Field names aren't accepted: acf_update_values() looks a
name up across every field, so it could save as a different field with the same name.
* @since ACF 6.8.11
@access private
* @param array   $candidates      The submitted top-level keys to check.
* @param array   $screen          The location screen args describing the object. Empty matches nothing.
* @param boolean $flat_clone_keys Whether seamless clone sub fields may also be submitted under their own keys (the datastore).
* @return array

## `_acf_get_verified_flat_clone_keys()`

Returns the flat keys of the sub fields of verified seamless clones that weren't submitted under the clone's key.

* @since ACF 6.8.11
@access private
* @param array $verified  The verified top-level keys.
* @param array $submitted The submitted top-level keys.
* @return array

## `_acf_is_seamless_clone()`

Returns true if a field is a seamless clone with sub fields.

* @since ACF 6.8.11
@access private
* @param array $field The field array.
* @return boolean

## `_acf_get_seamless_clone_leaf_keys()`

Returns the flat keys of a seamless clone's sub fields, through any seamless clones it clones.

* The acf_get_fields() function flattens nested seamless clones to their innermost fields, which is what the datastore sends.
* @since ACF 6.8.11
@access private
* @param array $sub_fields The clone's sub fields.
* @return array

## `acf_normalize_field_keys()`

Normalizes a list of allowed field keys, typically after it has been filtered.

* Drops non-scalar and empty values, casts to strings and removes duplicates so a
misbehaving filter callback can't break array_flip() downstream.
* @since ACF 6.8.11
* @param mixed $keys The keys to normalize.
* @return array

## `_acf_get_allowed_save_keys()`

Returns the submitted top-level keys a save may accept for an object.

* A key is accepted if it is in a verified field list for one of the render IDs (it was rendered
for this user and object), or, without a list, if it belongs to a top-level field of a field group
whose location rules match the object. Everything else is dropped, and logged when WP_DEBUG is on.
* Fields hidden only by acf/prepare_field can still be submitted without a list. Conditional logic is
not evaluated.
* @since ACF 6.8.11
@access private
* @param array $submitted The submitted top-level keys.
* @param array $args      See acf_filter_save_values().
* @return array The allowed top-level keys.

## `_acf_log_dropped_field_keys()`

Logs dropped keys when WP_DEBUG is on.

* @since ACF 6.8.11
@access private
* @param array $submitted The submitted top-level keys.
* @param array $allowed   The allowed top-level keys.
* @param array $args      The save args.
* @return void

## `_acf_get_post_save_args()`

Returns the save args for a post. Previews and autosaves save to a revision, so they are matched as the post they belong to.

* @since ACF 6.8.11
@access private
* @param WP_Post $post    The post or revision being saved.
* @param string  $context The save context.
* @return array See acf_filter_save_values().

## `_acf_get_attachment_save_args()`

Returns the save args for an attachment.

* @since ACF 6.8.11
@access private
* @param integer $attachment_id The attachment ID.
* @return array See acf_filter_save_values().

## `acf_filter_save_values()`

Returns submitted values restricted to the top-level keys a save may accept for an object.

* Use for saves that pass values to acf_save_post() rather than saving $_POST['acf'].
* @since ACF 6.8.11
* @param mixed $values The submitted values, keyed by top-level key.
* @param array $args {
The save args.
*     @type string         $context         The save context, used in the `acf/form/{$context}/allowed_field_keys` filter.
* @type integer|string $post_id         The ACF post ID being saved.
* @type array          $render_ids      The render IDs whose field lists the save accepts.
* @type array|null     $lists           The submitted field lists, keyed by render ID. Defaults to `$_POST['_acf_fields']`.
* @type array          $screen          The location screen args for keys submitted without a list. Empty accepts listed keys only.
* @type mixed          $filter_arg      The second argument of the filter. Defaults to `post_id`.
* @type boolean        $flat_clone_keys Whether seamless clone sub fields may be submitted under their own keys (the datastore).
}
* @return array

## `acf_restrict_save_input()`

Restricts $_POST['acf'] and $_FILES['acf'] to the top-level keys a save may accept for an object, in place.

* Call after verifying the nonce and before acf_validate_save_post(), so dropped keys can't cause
validation errors. Also records the render IDs that basic uploader nonces are verified against.
* @since ACF 6.8.11
* @param array $args See acf_filter_save_values().
* @return void

## `acf_set_upload_render_ids()`

Sets the render IDs that basic uploader nonces are verified against in this save.

* @since ACF 6.8.11
* @param array $render_ids The render IDs.
* @return void

## `acf_restrict_uploaded_field_keys()`

Restricts $_FILES['acf'] to the given top-level keys, in place.

* ACF_Media::save_files() uploads every file in $_FILES['acf'] on each acf/save_post and
writes the attachment ID back into $_POST['acf'], so uploads must be restricted to the
same keys as $_POST['acf'].
* @since ACF 6.8.11
* @param array $allowed_keys The allowed top-level keys.
* @return void

## `acf_discard_uploaded_field_values()`

Empties $_FILES['acf'] for saves that take their values from an array rather than $_POST['acf'].

* @since ACF 6.8.11
* @return void

---
