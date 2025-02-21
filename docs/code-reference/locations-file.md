# Global Functions

## `acf_register_location_type()`

Registers a location type.

* @date    8/4/20
* @since ACF 5.9.0
* @param   string $class_name The location class name.
* @return (ACF_Location|false)

## `acf_get_location_types()`

Returns an array of all registered location types.

* @date    8/4/20
* @since ACF 5.9.0
* @return  array

## `acf_get_location_type()`

Returns a location type for the given name.

* @date    18/2/19
* @since ACF 5.7.12
* @param   string $name The location type name.
* @return (ACF_Location|null)

## `acf_get_location_rule_types()`

Returns a grouped array of all location rule types.

* @date    8/4/20
* @since ACF 5.9.0
* @return  array

## `acf_validate_location_rule()`

Returns a validated location rule with all props.

* @date    8/4/20
* @since ACF 5.9.0
* @param   array $rule The location rule.
* @return array

## `acf_get_location_rule_operators()`

Returns an array of operators for a given rule.

* @date    30/5/17
* @since ACF 5.6.0
* @param   array $rule The location rule.
* @return array

## `acf_get_location_rule_values()`

Returns an array of values for a given rule.

* @date    30/5/17
* @since ACF 5.6.0
* @param   array $rule The location rule.
* @return array

## `acf_match_location_rule()`

Returns true if the provided rule matches the screen args.

* @date    30/5/17
* @since ACF 5.6.0
* @param   array $rule   The location rule.
* @param array $screen The screen args.
* @param array $field  The field group array.
* @return boolean

## `acf_get_location_screen()`

Returns ann array of screen args to be used against matching rules.

* @date    8/4/20
* @since ACF 5.9.0
* @param   array $screen     The screen args.
* @param array $deprecated The field group array.
* @return array

## `acf_register_location_rule()`

Alias of acf_register_location_type().

* @date    31/5/17
* @since ACF 5.6.0
* @param   string $class_name The location class name.
* @return (ACF_Location|false)

## `acf_get_location_rule()`

Alias of acf_get_location_type().

* @date    31/5/17
* @since ACF 5.6.0
* @param   string $class_name The location class name.
* @return (ACF_Location|false)

## `acf_get_valid_location_rule()`

Alias of acf_validate_location_rule().

* @date    30/5/17
* @since ACF 5.6.0
* @param   array $rule The location rule.
* @return array

---
