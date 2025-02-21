# Deprecated Global Functions

## `acf_render_field_wrap_label()`

acf_render_field_wrap_label

* Renders the field's label.
* @date    19/9/17
* @since ACF 5.6.3
* @deprecated 5.6.5
* @param   array $field The field array.
* @return void

## `acf_render_field_wrap_description()`

acf_render_field_wrap_description

* Renders the field's instructions.
* @date    19/9/17
* @since ACF 5.6.3
* @deprecated 5.6.5
* @param   array $field The field array.
* @return void

## `acf_get_fields_by_id()`

Returns and array of fields for the given $parent_id.

* @date    27/02/2014
* @since ACF 5.0.0.
* @deprecated 5.7.11
* @param   integer $parent_id The parent ID.
* @return array

## `acf_update_option()`

acf_update_option

* A wrapper for the WP update_option but provides logic for a 'no' autoload
* @date    4/01/2014
* @since ACF 5.0.0
* @deprecated 5.7.11
* @param   string $option   The option name.
* @param string $value    The option value.
* @param string $autoload An optional autoload value.
* @return boolean

## `acf_get_field_reference()`

acf_get_field_reference

* Finds the field key for a given field name and post_id.
* @date    26/1/18
* @since ACF 5.6.5
* @deprecated 5.6.8
* @param   string $field_name The name of the field. eg 'sub_heading'
* @param mixed  $post_id    The post_id of which the value is saved against
* @return string  $reference  The field key

## `acf_get_dir()`

acf_get_dir

* Returns the plugin url to a specified file.
* @date    28/09/13
* @since ACF 5.0.0
* @deprecated 5.6.8
* @param   string $filename The specified file.
* @return string

---
