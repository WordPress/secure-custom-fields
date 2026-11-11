# Acf Meta Functions Global Functions

## `scf_is_protected_user_meta_key()`

Checks whether a user meta key holds data WordPress uses for roles, capabilities or sign-in.

* @since SCF 6.9.9
* @param string  $meta_key The user meta key.
* @param integer $user_id  Optional. The user the key is written to or deleted from. Default 0.
* @return boolean

## `_scf_is_protected_user_meta_name()`

Checks a user meta key against the keys WordPress uses for roles, capabilities or sign-in.

* @since SCF 6.9.9
@internal
* @param string $key The user meta key.
* @return boolean

## `_scf_user_meta_rows_hold_protected_key()`

Checks whether a write or delete for a user would change a row holding a protected key.

* @since SCF 6.9.9
@internal
* @param integer $user_id  The user ID.
* @param string  $meta_key The user meta key, unslashed.
* @return boolean

## `acf_get_meta()`

Returns an array of "ACF only" meta for the given post_id.

* @date    9/10/18
* @since ACF 5.8.0
* @param mixed $post_id The post_id for this data.
* @return array

## `acf_get_option_meta()`

acf_get_option_meta

* Returns an array of meta for the given wp_option name prefix in the same format as get_post_meta().
* @date    9/10/18
* @since ACF 5.8.0
* @param   string $prefix The wp_option name prefix.
* @return array

## `acf_get_metadata()`

Retrieves specific metadata from the database.

* @date    16/10/2015
* @since ACF 5.2.3
* @param   integer|string $post_id The post id.
* @param string         $name    The meta name.
* @param boolean        $hidden  If the meta is hidden (starts with an underscore).
* @return  mixed

## `acf_update_metadata()`

Updates metadata in the database.

* @date    16/10/2015
* @since ACF 5.2.3
* @param   integer|string $post_id The post id.
* @param string         $name    The meta name.
* @param mixed          $value   The meta value.
* @param boolean        $hidden  If the meta is hidden (starts with an underscore).
* @return  integer|boolean Meta ID if the key didn't exist, true on successful update, false on failure.

## `acf_delete_metadata()`

Deletes metadata from the database.

* @date    16/10/2015
* @since ACF 5.2.3
* @param   integer|string $post_id The post id.
* @param string         $name    The meta name.
* @param boolean        $hidden  If the meta is hidden (starts with an underscore).
* @return  boolean

## `acf_copy_metadata()`

Copies meta from one post to another. Useful for saving and restoring revisions.

* @since ACF 5.3.8
* @param integer|string $from_post_id The post id to copy from.
* @param integer|string $to_post_id   The post id to paste to.
* @return void

## `acf_copy_postmeta()`

acf_copy_postmeta

* Copies meta from one post to another. Useful for saving and restoring revisions.
* @date    25/06/2016
* @since ACF 5.3.8
* @deprecated 5.7.11
* @param   integer $from_post_id The post id to copy from.
* @param integer $to_post_id   The post id to paste to.
* @return void

## `acf_get_meta_field()`

acf_get_meta_field

* Returns a field using the provided $id and $post_id parameters.
Looks for a reference to help loading the correct field via name.
* @date    21/1/19
* @since ACF 5.7.10
* @param   string       $key     The meta name (field name).
* @param (int|string) $post_id The post_id where this field's value is saved.
* @return (array|false) The field array.

## `acf_get_metaref()`

acf_get_metaref

* Retrieves reference metadata from the database.
* @date    16/10/2015
* @since ACF 5.2.3
* @param   (int|string)                                   $post_id The post id.
* @param string type The reference type (fields|groups).
* @param string                                         $name    An optional specific name
* @return mixed

## `acf_update_metaref()`

acf_update_metaref

* Updates reference metadata in the database.
* @date    16/10/2015
* @since ACF 5.2.3
* @param   (int|string)                                   $post_id    The post id.
* @param string type The reference type (fields|groups).
* @param array                                          $references An array of references.
* @return (int|bool) Meta ID if the key didn't exist, true on successful update, false on failure.

## `acf_get_meta_instance()`

Retrieves an ACF meta instance for the provided meta type.

* @since 6.5
* @param string $type The meta type as decoded from the post ID.
* @return object|null

## `acf_get_metadata_by_field()`

Gets metadata from the database.

* @since 6.5
* @param integer|string $post_id The post id.
* @param array          $field   The field array.
* @param boolean        $hidden  True if we should return the reference key.
* @return mixed

## `acf_update_metadata_by_field()`

Updates metadata in the database.

* @since 6.5
* @param integer|string $post_id The post id.
* @param array          $field   The field array.
* @param mixed          $value   The meta value.
* @param boolean        $hidden  True if we should update the reference key.
* @return integer|boolean Meta ID if the key didn't exist, true on successful update, false on failure.

## `acf_delete_metadata_by_field()`

Deletes metadata from the database.

* @since 6.5
* @param integer|string $post_id The post id.
* @param array          $field   The field array.
* @param boolean        $hidden  True if we should update the reference key.
* @return boolean

## `_acf_is_field_meta_key()`

Returns true if a post meta key stores the value of an ACF field.

* A key belongs to a field if the post has a field key reference for it, or if it is a name ACF loads a value
from without a reference: a top-level field's name, the name of a group or clone sub field (such as
`{group}_{sub_field}`), or a repeater or flexible content row's sub field (such as `{repeater}_0_{sub_field}`).
* @since ACF 6.8.11
@access private
* @param integer $post_id  The post ID.
* @param string  $meta_key The meta key.
* @return boolean

## `_acf_get_field_storage_names()`

Returns the meta names ACF loads field values from without a field key reference.

* These are the names of top-level fields (in field groups, or local fields registered without one) and of
group and clone sub fields (as array keys in `names`), and
patterns for the sub fields of repeater and flexible content rows, whose names include the row number
(in `patterns`). They are worked out as the field types name sub fields when loading their values.
* Cached for the request, until local fields or posts (including database fields) change.
* @since ACF 6.8.11
@access private
* @return array

## `_acf_add_field_storage_names()`

Adds the meta name a field is loaded from without a reference, and those of its sub fields.

* Group and clone sub field names are worked out with the field types' own prepare_field_for_db(), as when
their values are loaded. Repeater and flexible content rows are named `{field}_{row}_{sub_field}`; the row
number is held as a placeholder and turned into a pattern.
* @since ACF 6.8.11
@access private
* @param array $storage The names and patterns, as array keys.
* @param array $field   The field array, with its meta name in `name`.
* @return void

## `_acf_get_field_storage_sub_fields()`

Returns a field's sub fields with their meta names in `name`, as the field type names them when loading.

* @since ACF 6.8.11
@access private
* @param array $field The field array, with its meta name in `name`.
* @return array

## `_acf_get_prepared_sub_fields()`

Returns a group or clone field's sub fields named by the field type's prepare_field_for_db().

* @since ACF 6.8.11
@access private
* @param array  $field The field array.
* @param string $type  The field type.
* @return array

## `_acf_get_row_sub_fields()`

Returns a repeater's sub fields, or those of every flexible content layout, named for their rows.

* Rows are named `{field}_{row}_{sub_field}`, with the row number held as a placeholder.
* @since ACF 6.8.11
@access private
* @param array  $field The field array.
* @param string $type  The field type.
* @return array

## `acf_map_post_meta_caps()`

Prevents WordPress's own custom field editing from changing post meta that belongs to an ACF field.

* The Custom Fields meta box (`metakeyinput` and `meta[]` in the post form), `wp_ajax_add_meta` and XML-RPC
`custom_fields` check these capabilities. Without this, a user who can edit a post could write values for ACF
fields that aren't shown to them, bypassing ACF's save checks. ACF saves its own values without these checks.
Meta keys registered with register_meta() and protected meta keys are left to WordPress.
* @since ACF 6.8.11
* @param string[] $caps       The primitive capabilities required.
* @param string   $cap        The capability being checked.
* @param mixed    ...$context The user ID, and the post ID and meta key.
* @return string[]

---
