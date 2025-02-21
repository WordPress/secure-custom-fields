# API Template Global Functions

## `get_field()`

This function will return a custom field value for a specific field name/key + post_id.
There is a 3rd parameter to turn on/off formating. This means that an image field will not use
its 'return option' to format the value but return only what was saved in the database

* @since   ACF 3.6
* @param string  $selector     The field name or key.
* @param mixed   $post_id      The post_id of which the value is saved against.
* @param boolean $format_value Whether or not to format the value as described above.
* @param boolean $escape_html  If we're formatting the value, make sure it's also HTML safe.
* @return mixed

## `the_field()`

This function is the same as echo get_field(), but will escape the value for safe HTML output regardless of parameters.

* @since   ACF 1.0.3
* @param string  $selector     The field name or key.
* @param mixed   $post_id      The post_id of which the value is saved against.
* @param boolean $format_value Enable formatting of value. Default true.
* @return void

## `_acf_log_escaped_html()`

Logs instances of ACF successfully escaping unsafe HTML.

* @since ACF 6.2.5
* @param string $function The function that resulted in HTML being escaped.
* @param string $selector The selector (field key, name, etc.) passed to that function.
* @param array  $field    The field being queried when HTML was escaped.
* @param mixed  $post_id  The post ID the function was called on.
* @return void

## `_acf_get_escaped_html_log()`

Returns an array of instances where HTML was altered due to escaping in the_field or a shortcode.

* @since ACF 6.2.5
* @return array

## `_acf_update_escaped_html_log()`

Updates the array of instances where HTML was altered due to escaping in the_field or a shortcode.

* @since ACF 6.2.5
* @param array $escaped The array of instances.
* @return boolean True on success, or false on failure.

## `_acf_delete_escaped_html_log()`

Deletes the array of instances where HTML was altered due to escaping in the_field or a shortcode.
Since 6.2.7, also clears the legacy `acf_will_escape_html_log` option to clean up.

* @since ACF 6.2.5
* @return boolean True on success, or false on failure.

## `get_field_object()`

This function will return an array containing all the field data for a given field_name.

* @since ACF 3.6
* @param string  $selector     The field name or key.
* @param mixed   $post_id      The post_id of which the value is saved against.
* @param boolean $format_value Whether to format the field value.
* @param boolean $load_value   Whether to load the field value.
* @param boolean $escape_html  Should the field return a HTML safe formatted value if $format_value is true.
* @return array|false $field

## `acf_maybe_get_field()`

This function will return a field for the given selector.
It will also review the field_reference to ensure the correct field is returned which makes it useful for the template API

* @since   ACF 5.2.3
* @param   $selector (mixed) identifier of field. Can be an ID, key, name or post object
* @param $post_id (mixed) the post_id of which the value is saved against
* @param $strict (boolean) if true, return a field only when a field key is found.
* @return  $field (array)

## `acf_maybe_get_sub_field()`

This function will attempt to find a sub field

* @since   ACF 5.4.0
* @param   $post_id (int)
* @return $post_id (int)

## `get_fields()`

This function will return an array containing all the custom field values for a specific post_id.
The function is not very elegant and wastes a lot of PHP memory / SQL queries if you are not using all the values.

* @since   ACF 3.6
* @param mixed   $post_id      The post_id of which the value is saved against.
* @param boolean $format_value Whether or not to format the field value.
* @param boolean $escape_html  Should the field return a HTML safe formatted value if $format_value is true.
* @return array|false Associative array where field name => field value, or false on failure.

## `get_field_objects()`

This function will return an array containing all the custom field objects for a specific post_id.
The function is not very elegant and wastes a lot of PHP memory / SQL queries if you are not using all the fields / values.

* @since ACF 3.6
* @param mixed   $post_id      The post_id of which the value is saved against.
* @param boolean $format_value Whether or not to format the field value.
* @param boolean $load_value   Whether or not to load the field value.
* @param boolean $escape_html  Should the field return a HTML safe formatted value if $format_value is true.
* @return array|false Associative array where field name => field, or false on failure.

## `have_rows()`

Checks if a field (such as Repeater or Flexible Content) has any rows of data to loop over.
This function is intended to be used in conjunction with the_row() to step through available values.

* @since   ACF 4.3.0
* @param   string $selector The field name or field key.
* @param mixed  $post_id  The post ID where the value is saved. Defaults to the current post.
* @return boolean

## `the_row()`

This function will progress the global repeater or flexible content value 1 row

* @since   ACF 4.3.0
* @param   N/A
* @return (array) the current row data

## `get_row_sub_field()`

This function is used inside a 'has_sub_field' while loop to return a sub field object

* @since   ACF 5.3.8
* @param   $selector (string)
* @return (array)

## `get_row_sub_value()`

This function is used inside a 'has_sub_field' while loop to return a sub field value

* @since   ACF 5.3.8
* @param   $selector (string)
* @return (mixed)

## `reset_rows()`

This function will find the current loop and unset it from the global array.
To be used when loop finishes or a break is used

* @since   ACF 5.0.0
* @param   $hard_reset (boolean) completely wipe the global variable, or just unset the active row
* @return (boolean)

## `has_sub_field()`

This function is used inside a while loop to return either true or false (loop again or stop).
When using a repeater or flexible content field, it will loop through the rows until
there are none left or a break is detected

* @since   ACF 1.0.3
* @param   $field_name (string) the field name
* @param $post_id (mixed) the post_id of which the value is saved against
* @return (boolean)

## `has_sub_fields()`

Alias of has_sub_field

## `get_sub_field()`

This function is used inside a 'has_sub_field' while loop to return a sub field value

* @since ACF 1.0.3
* @param string  $selector     The field name or key.
* @param boolean $format_value Whether or not to format the value as described above.
* @param boolean $escape_html  If we're formatting the value, make sure it's also HTML safe.
* @return mixed

## `the_sub_field()`

This function is the same as echo get_sub_field(), but will escape the value for safe HTML output.

* @since   ACF 1.0.3
* @param string  $field_name   The field name.
* @param boolean $format_value Enable formatting of value. When false, the field value will be escaped at this level with `acf_esc_html`. Default true.
* @return void

## `get_sub_field_object()`

This function is used inside a 'has_sub_field' while loop to return a sub field object

* @since ACF 3.5.8.1
* @param string  $selector     The field name or key.
* @param boolean $format_value Whether to format the field value.
* @param boolean $load_value   Whether to load the field value.
* @param boolean $escape_html  Should the field return a HTML safe formatted value.
* @return mixed

## `get_row_layout()`

This function will return a string representation of the current row layout within a 'have_rows' loop

* @since   ACF 3.0.6
* @return mixed

## `acf_shortcode()`

This function is used to add basic shortcode support for the ACF plugin
eg. [acf field="heading" post_id="123" format_value="1"]

* @since ACF 1.1.1
* @param array $atts The shortcode attributes.
* @return string|void

## `update_field()`

This function will update a value in the database

* @since   ACF 3.1.9
* @param string $selector The field name or key.
* @param mixed  $value    The value to save in the database.
* @param mixed  $post_id  The post_id of which the value is saved against.
* @return boolean

## `update_sub_field()`

This function will update a value of a sub field in the database

* @since   ACF 5.0.0
* @param   $selector (mixed) the sub field name or key, or an array of ancestors
* @param $value (mixed) the value to save in the database
* @param $post_id (mixed) the post_id of which the value is saved against
* @return  boolean

## `delete_field()`

This function will remove a value from the database

* @since   ACF 3.1.9
* @param   $selector (string) the field name or key
* @param $post_id (mixed) the post_id of which the value is saved against
* @return  boolean

## `delete_sub_field()`

This function will delete a value of a sub field in the database

* @since   ACF 5.0.0
* @param   $selector (mixed) the sub field name or key, or an array of ancestors
* @param $value (mixed) the value to save in the database
* @param $post_id (mixed) the post_id of which the value is saved against
* @return (boolean)

## `add_row()`

This function will add a row of data to a field

* @since   ACF 5.2.3
* @param   $selector (string)
* @param $row (array)
* @param $post_id (mixed)
* @return (boolean)

## `add_sub_row()`

This function will add a row of data to a field

* @since   ACF 5.2.3
* @param   $selector (string)
* @param $row (array)
* @param $post_id (mixed)
* @return (boolean)

## `update_row()`

This function will update a row of data to a field

* @since   ACF 5.2.3
* @param   $selector (string)
* @param $i (int)
* @param $row (array)
* @param $post_id (mixed)
* @return (boolean)

## `update_sub_row()`

This function will add a row of data to a field

* @since   ACF 5.2.3
* @param   $selector (string)
* @param $row (array)
* @param $post_id (mixed)
* @return (boolean)

## `delete_row()`

This function will delete a row of data from a field

* @since   ACF 5.2.3
* @param   $selector (string)
* @param $i (int)
* @param $post_id (mixed)
* @return (boolean)

## `delete_sub_row()`

This function will add a row of data to a field

* @since   ACF 5.2.3
* @param   $selector (string)
* @param $row (array)
* @param $post_id (mixed)
* @return (boolean)

## `create_field()`

Depreceated Functions

* These functions are outdated
* @since   ACF 1.0.0
* @param   n/a
* @return n/a

---
