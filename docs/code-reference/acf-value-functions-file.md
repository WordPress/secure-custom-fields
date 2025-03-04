# Acf Value Functions Global Functions

## `acf_get_reference()`

acf_get_reference

* Retrieves the field key for a given field name and post_id.
* @date    26/1/18
* @since ACF 5.6.5
* @param   string $field_name The name of the field. eg 'sub_heading'.
* @param mixed  $post_id    The post_id of which the value is saved against.
* @return string The field key.

## `acf_get_value()`

Retrieves the value for a given field and post_id.

* @date    28/09/13
* @since ACF 5.0.0
* @param   integer|string $post_id The post id.
* @param array          $field   The field array.
* @return mixed

## `acf_format_value()`

Returns a formatted version of the provided value.

* @since   ACF 5.0.0
* @param mixed          $value       The field value.
* @param integer|string $post_id     The post id.
* @param array          $field       The field array.
* @param boolean        $escape_html Ask the field for a HTML safe version of it's output.
* @return mixed

## `acf_update_value()`

acf_update_value

* Updates the value for a given field and post_id.
* @date    28/09/13
* @since ACF 5.0.0
* @param   mixed        $value   The new value.
* @param (int|string) $post_id The post id.
* @param array        $field   The field array.
* @return boolean

## `acf_update_values()`

acf_update_values

* Updates an array of values.
* @date    26/2/19
* @since ACF 5.7.13
* @param   array values The array of values.
* @param (int|string)                     $post_id The post id.
* @return void

## `acf_flush_value_cache()`

acf_flush_value_cache

* Deletes all cached data for this value.
* @date    22/1/19
* @since ACF 5.7.10
* @param   (int|string) $post_id    The post id.
* @param string       $field_name The field name.
* @return void

## `acf_delete_value()`

acf_delete_value

* Deletes the value for a given field and post_id.
* @date    28/09/13
* @since ACF 5.0.0
* @param   (int|string) $post_id The post id.
* @param array        $field   The field array.
* @return boolean

## `acf_preview_value()`

acf_preview_value

* Return a human friendly 'preview' for a given field value.
* @date    28/09/13
* @since ACF 5.0.0
* @param   mixed        $value   The new value.
* @param (int|string) $post_id The post id.
* @param array        $field   The field array.
* @return boolean

## `acf_log_invalid_field_notice()`

Potentially log an error if a field doesn't exist when we expect it to.

* @param array  $field    An array representing the field that a value was requested for.
* @param string $function The function that noticed the problem.
* @return void

---
