# Validation Global Functions

## `acf_add_validation_error()`

Add validation error.

* Alias of acf()->validation->add_error()
* @type    function
* @date 6/10/13
* @since ACF 5.0.0
* @param   string $input name attribute of DOM elmenet.
* @param string $message error message.
* @return void

## `acf_get_validation_errors()`

Retrieve validation errors.

* Alias of acf()->validation->function()
* @type    function
* @date 6/10/13
* @since ACF 5.0.0
* @return  array|bool

## `acf_get_validation_error()`

Get the validation error.

* Alias of acf()->validation->get_error()
* @type    function
* @date 6/10/13
* @since ACF 5.0.0
* @since 6.4.1 Added the $input parameter, which is required in the get_error method.
* @param   string $input name attribute of DOM elmenet.
* @return  string|bool

## `acf_reset_validation_errors()`

Reset Validation errors.

* Alias of acf()->validation->reset_errors()
* @type    function
* @date 6/10/13
* @since ACF 5.0.0
* @return  void

## `acf_validate_save_post()`

This function will validate $_POST data and add errors

* @type    function
* @date 25/11/2013
* @since ACF 5.0.0
* @param   bool $show_errors if true, errors will be shown via a wp_die screen.
* @return bool

## `acf_validate_values()`

This function will validate an array of field values

* @type    function
* @date 6/10/13
* @since ACF 5.0.0
* @param   array  $values An array of field values.
* @param string $input_prefix The input element's name attribute.
* @return  void

## `acf_validate_value()`

This function will validate a field's value

* @type    function
* @date 6/10/13
* @since ACF 5.0.0
* @param   mixed  $value The field value to validate.
* @param array  $field The field array.
* @param string $input The input element's name attribute.
* @return  boolean

---
