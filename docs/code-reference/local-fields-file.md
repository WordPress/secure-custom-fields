# Local Fields Global Functions

## `acf_enable_local()`

acf_enable_local

* Enables the local filter.
* @date    22/1/19
* @since ACF 5.7.10
* @return  void

## `acf_disable_local()`

acf_disable_local

* Disables the local filter.
* @date    22/1/19
* @since ACF 5.7.10
* @return  void

## `acf_is_local_enabled()`

acf_is_local_enabled

* Returns true if local fields are enabled.
* @date    23/1/19
* @since ACF 5.7.10
* @return  boolean

## `acf_get_local_store()`

Returns either local store or a dummy store for the given name or post type.

* @date 23/1/19
* @since ACF 5.7.10
* @param string $name      The store name.
* @param string $post_type The post type for the desired store.
* @return ACF_Data

## `acf_reset_local()`

acf_reset_local

* Resets the local data.
* @date    22/1/19
* @since ACF 5.7.10
* @return  void

## `acf_get_local_field_groups()`

acf_get_local_field_groups

* Returns all local field groups.
* @date    22/1/19
* @since ACF 5.7.10
* @return  array

## `acf_get_local_internal_posts()`

Returns local ACF posts with the provided post type.

* @since ACF 6.1
* @param string $post_type The post type to check for.
* @return array|mixed

## `acf_have_local_field_groups()`

acf_have_local_field_groups

* description
* @date    22/1/19
* @since ACF 5.7.10
* @param   type $var Description. Default.
* @return type Description.

## `acf_count_local_field_groups()`

acf_count_local_field_groups

* description
* @date    22/1/19
* @since ACF 5.7.10
* @param   type $var Description. Default.
* @return type Description.

## `acf_add_local_field_group()`

acf_add_local_field_group

* Adds a local field group.
* @date    22/1/19
* @since ACF 5.7.10
* @param   array $field_group The field group array.
* @return boolean

## `acf_add_local_internal_post_type()`

Adds a local ACF internal post type.

* @since ACF 6.1
* @param array  $post      The main ACF post array.
* @param string $post_type The post type being added.
* @return boolean

## `register_field_group()`

register_field_group

* See acf_add_local_field_group().
* @date    22/1/19
* @since ACF 5.7.10
* @param   array $field_group The field group array.
* @return void

## `acf_remove_local_field_group()`

acf_remove_local_field_group

* Removes a field group for the given key.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $key The field group key.
* @return boolean

## `acf_remove_local_internal_post_type()`

Removes a local ACF post with the given key and post type.

* @since ACF 6.1
* @param string $key       The ACF key.
* @param string $post_type The ACF post type.
* @return boolean

## `acf_is_local_field_group()`

acf_is_local_field_group

* Returns true if a field group exists for the given key.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $key The field group key.
* @return boolean

## `acf_is_local_internal_post_type()`

Returns true if an ACF post exists for the given key.

* @since ACF 6.1
* @param string $key       The ACF key.
* @param string $post_type The ACF post type.
* @return boolean

## `acf_is_local_field_group_key()`

acf_is_local_field_group_key

* Returns true if a field group exists for the given key.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $key The field group key.
* @return boolean

## `acf_is_local_internal_post_type_key()`

Returns true if a local ACF post exists for the given key.

* @since ACF 6.1
* @param string $key       The ACF post key.
* @param string $post_type The post type to check.
* @return boolean

## `acf_get_local_field_group()`

acf_get_local_field_group

* Returns a field group for the given key.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $key The field group key.
* @return (array|null)

## `acf_get_local_internal_post_type()`

Returns an ACF post for the given key.

* @since ACF 6.1
* @param string $key       The field group key.
* @param string $post_type The ACF post type.
* @return array|null

## `acf_add_local_fields()`

acf_add_local_fields

* Adds an array of local fields.
* @date    22/1/19
* @since ACF 5.7.10
* @param   array $fields An array of un prepared fields.
* @return void|array

## `acf_get_local_fields()`

acf_get_local_fields

* Returns all local fields for the given parent.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $parent The parent key.
* @return array

## `acf_have_local_fields()`

acf_have_local_fields

* Returns true if local fields exist.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $parent The parent key.
* @return boolean

## `acf_count_local_fields()`

acf_count_local_fields

* Returns the number of local fields for the given parent.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $parent The parent key.
* @return integer

## `acf_add_local_field()`

acf_add_local_field

* Adds a local field.
* @date    22/1/19
* @since ACF 5.7.10
* @param   array   $field    The field array.
* @param boolean $prepared Whether or not the field has already been prepared for import.
* @return void

## `_acf_generate_local_key()`

_acf_generate_local_key

* Generates a unique key based on the field's parent.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $key The field key.
* @return boolean

## `acf_remove_local_field()`

acf_remove_local_field

* Removes a field for the given key.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $key The field key.
* @return boolean

## `acf_is_local_field()`

acf_is_local_field

* Returns true if a field exists for the given key or name.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $key The field group key.
* @return boolean

## `acf_is_local_field_key()`

acf_is_local_field_key

* Returns true if a field exists for the given key.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $key The field group key.
* @return boolean

## `acf_get_local_field()`

acf_get_local_field

* Returns a field for the given key.
* @date    22/1/19
* @since ACF 5.7.10
* @param   string $key The field group key.
* @return (array|null)

## `_acf_apply_get_local_field_groups()`

_acf_apply_get_local_field_groups

* Appends local field groups to the provided array.
* @date    23/1/19
* @since ACF 5.7.10
* @param   array $field_groups An array of field groups.
* @return array

## `_acf_apply_get_local_internal_posts()`

Appends local ACF internal post types to the provided array.

* @since ACF 6.1
* @param array  $posts     An array of ACF posts.
* @param string $post_type The ACF internal post type being loaded.
* @return array

## `_acf_apply_is_local_field_key()`

_acf_apply_is_local_field_key

* Returns true if is a local key.
* @date    23/1/19
* @since ACF 5.7.10
* @param   boolean $bool The result.
* @param string  $id   The identifier.
* @return boolean

## `_acf_apply_is_local_field_group_key()`

_acf_apply_is_local_field_group_key

* Returns true if is a local key.
* @date    23/1/19
* @since ACF 5.7.10
* @param   boolean $bool The result.
* @param string  $id   The identifier.
* @return boolean

## `_acf_apply_is_local_internal_post_type_key()`

Returns true if is a local key.

* @since ACF 6.1
* @param boolean $bool      The result.
* @param string  $id        The identifier.
* @param string  $post_type The post type.
* @return boolean

## `_acf_do_prepare_local_fields()`

_acf_do_prepare_local_fields

* Local fields that are added too early will not be correctly prepared by the field type class.
* @date    23/1/19
* @since ACF 5.7.10
* @return  void

---
