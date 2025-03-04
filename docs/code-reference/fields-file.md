# Fields Global Functions

## `acf_register_field_type()`

alias of acf()->fields->register_field_type()

* @type    function
* @date 31/5/17
* @since ACF 5.6.0
* @param   n/a
* @return n/a

## `acf_register_field_type_info()`

alias of acf()->fields->register_field_type_info()

* @type    function
* @date 31/5/17
* @since ACF 5.6.0
* @param   n/a
* @return n/a

## `acf_get_field_type()`

alias of acf()->fields->get_field_type()

* @type    function
* @date 31/5/17
* @since ACF 5.6.0
* @param   n/a
* @return n/a

## `acf_get_field_types()`

alias of acf()->fields->get_field_types()

* @type    function
* @date 31/5/17
* @since ACF 5.6.0
* @param   n/a
* @return n/a

## `acf_get_field_types_info()`

acf_get_field_types_info

* Returns an array containing information about each field type
* @date    18/6/18
* @since ACF 5.6.9
* @param   type $var Description. Default.
* @return type Description.

## `acf_is_field_type()`

alias of acf()->fields->is_field_type()

* @type    function
* @date 31/5/17
* @since ACF 5.6.0
* @param   n/a
* @return n/a

## `acf_get_field_type_prop()`

This function will return a field type's property

* @type    function
* @date 1/10/13
* @since ACF 5.0.0
* @param   n/a
* @return (array)

## `acf_get_field_type_label()`

This function will return the label of a field type

* @type    function
* @date 1/10/13
* @since ACF 5.0.0
* @param   n/a
* @return (array)

## `acf_field_type_supports()`

Returns the value of a field type "supports" property.

* @since ACF 6.2.5
* @param string $name    The name of the field type.
* @param string $prop    The name of the supports property.
* @param mixed  $default The default value if the property is not set.
* @return mixed The value of the supports property which may be false, or $default on failure.

## `acf_field_type_exists()`

* @deprecated
@see acf_is_field_type()
* @type    function
* @date 1/10/13
* @since ACF 5.0.0
* @param   $type (string)
* @return (boolean)

## `acf_get_field_categories_i18n()`

Returns an array of localised field categories.

* @since ACF 6.1
* @return array

## `acf_get_grouped_field_types()`

Returns an multi-dimentional array of field types "name => label" grouped by category

* @since   ACF 5.0.0
* @return  array

## `acf_get_combined_field_type_settings_tabs()`

Returns an array of tabs for a field type.
We combine a list of default tabs with filtered tabs.
I.E. Default tabs should be static and should not be changed by the
filtered tabs.

* @since   ACF 6.1
* @return array Key/value array of the default settings tabs for field type settings.

## `acf_get_pro_field_types()`

Get the PRO only fields and their core metadata.

* @since ACF 6.1
* @return array An array of all the pro field types and their field type selection required meta data.

---
