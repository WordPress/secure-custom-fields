# Global Functions

## `acf_is_empty()`

Returns true if the value provided is considered "empty". Allows numbers such as 0.

* @date    6/7/16
* @since ACF 5.4.0
* @param   mixed $var The value to check.
* @return boolean

## `acf_not_empty()`

Returns true if the value provided is considered "not empty". Allows numbers such as 0.

* @date    15/7/19
* @since ACF 5.8.1
* @param   mixed $var The value to check.
* @return boolean

## `acf_uniqid()`

Returns a unique numeric based id.

* @date    9/1/19
* @since ACF 5.7.10
* @param   string $prefix The id prefix. Defaults to 'acf'.
* @return string

## `acf_merge_attributes()`

Merges together two arrays but with extra functionality to append class names.

* @date    22/1/19
* @since ACF 5.7.10
* @param   array $array1 An array of attributes.
* @param array $array2 An array of attributes.
* @return array

## `acf_cache_key()`

acf_cache_key

* Returns a filtered cache key.
* @date    25/1/19
* @since ACF 5.7.11
* @param   string $key The cache key.
* @return string

## `acf_request_args()`

acf_request_args

* Returns an array of $_REQUEST values using the provided defaults.
* @date    28/2/19
* @since ACF 5.7.13
* @param   array $args An array of args.
* @return array

## `acf_request_arg()`

Returns a single $_REQUEST arg with fallback.

* @date    23/10/20
* @since ACF 5.9.2
* @param   string $key     The property name.
* @param mixed  $default The default value to fallback to.
* @return mixed

## `acf_enable_filter()`

acf_enable_filter

* Enables a filter with the given name.
* @date    14/7/16
* @since ACF 5.4.0
* @param   string name The modifer name.
* @return void

## `acf_disable_filter()`

acf_disable_filter

* Disables a filter with the given name.
* @date    14/7/16
* @since ACF 5.4.0
* @param   string name The modifer name.
* @return void

## `acf_is_filter_enabled()`

acf_is_filter_enabled

* Returns the state of a filter for the given name.
* @date    14/7/16
* @since ACF 5.4.0
* @param   string name The modifer name.
* @return array

## `acf_get_filters()`

acf_get_filters

* Returns an array of filters in their current state.
* @date    14/7/16
* @since ACF 5.4.0
* @return  array

## `acf_set_filters()`

acf_set_filters

* Sets an array of filter states.
* @date    14/7/16
* @since ACF 5.4.0
* @param   array $filters An Array of modifers
* @return array

## `acf_disable_filters()`

acf_disable_filters

* Disables all filters and returns the previous state.
* @date    14/7/16
* @since ACF 5.4.0
* @return  array

## `acf_enable_filters()`

acf_enable_filters

* Enables all or an array of specific filters and returns the previous state.
* @date    14/7/16
* @since ACF 5.4.0
* @param   array $filters An Array of modifers
* @return array

## `acf_idval()`

acf_idval

* Parses the provided value for an ID.
* @date    29/3/19
* @since ACF 5.7.14
* @param   mixed $value A value to parse.
* @return integer

## `acf_maybe_idval()`

acf_maybe_idval

* Checks value for potential id value.
* @date    6/4/19
* @since ACF 5.7.14
* @param   mixed $value A value to parse.
* @return mixed

## `acf_format_numerics()`

Convert any numeric strings into their equivalent numeric type. This function will
work with both single values and arrays.

* @param mixed $value Either a single value or an array of values.
* @return mixed

## `acf_numval()`

acf_numval

* Casts the provided value as eiter an int or float using a simple hack.
* @date    11/4/19
* @since ACF 5.7.14
* @param   mixed $value A value to parse.
* @return (int|float)

## `acf_idify()`

acf_idify

* Returns an id attribute friendly string.
* @date    24/12/17
* @since ACF 5.6.5
* @param   string $str The string to convert.
* @return string

## `acf_slugify()`

Returns a slug friendly string.

* @date    24/12/17
* @since ACF 5.6.5
* @param   string $str  The string to convert.
* @param string $glue The glue between each slug piece.
* @return string

## `acf_punctify()`

Returns a string with correct full stop punctuation.

* @date    12/7/19
* @since ACF 5.8.2
* @param   string $str The string to format.
* @return string

## `acf_did()`

acf_did

* Returns true if ACF already did an event.
* @date    30/8/19
* @since ACF 5.8.1
* @param   string $name The name of the event.
* @return boolean

## `acf_strlen()`

Returns the length of a string that has been submitted via $_POST.

* Uses the following process:

1. Unslash the string because posted values will be slashed.
2. Decode special characters because wp_kses() will normalize entities.
3. Treat line-breaks as a single character instead of two.
4. Use mb_strlen() to accomodate special characters.

* @date    04/06/2020
* @since ACF 5.9.0
* @param   string $str The string to review.
* @return integer

## `acf_with_default()`

Returns a value with default fallback.

* @date    6/4/20
* @since ACF 5.9.0
* @param   mixed $value         The value.
* @param mixed $default_value The default value.
* @return mixed

## `acf_doing_action()`

Returns the current priority of a running action.

* @date    14/07/2020
* @since ACF 5.9.0
* @param   string $action The action name.
* @return integer|boolean

## `acf_get_current_url()`

Returns the current URL.

* @date    23/01/2015
* @since ACF 5.1.5
* @return  string

## `acf_sanitize_request_args()`

Sanitizes request arguments.

* @param mixed $args The data to sanitize.
* @return array|boolean|float|integer|mixed|string

## `acf_sanitize_files_array()`

Sanitizes file upload arrays.

* @since ACF 6.0.4
* @param array $args The file array.
* @return array

## `acf_sanitize_files_value_array()`

Sanitizes file upload values within the array.

* This addresses nested file fields within repeaters and groups.
* @since ACF 6.0.5
* @param array  $array             The file upload array.
* @param string $sanitize_function Callback used to sanitize array value.
* @return array

## `acf_maybe_unserialize()`

Maybe unserialize, but don't allow any classes.

* @since ACF 6.1
* @param string $data String to be unserialized, if serialized.
* @return mixed The unserialized, or original data.

## `acf_is_beta()`

Check if ACF is a beta-like release.

* @since ACF 6.3
* @return boolean True if the current install version contains a dash, indicating a alpha, beta or RC release.

## `acf_get_version_when_first_activated()`

Returns the version of ACF when it was first activated.
However, if ACF was first activated prior to the introduction of the acf_first_activated_version option,
this function returns false (boolean) to indicate that the version could not be determined.

* @since ACF 6.3
* @return string|boolean The (string) version of ACF when it was first activated, or false (boolean) if the version could not be determined.

---
