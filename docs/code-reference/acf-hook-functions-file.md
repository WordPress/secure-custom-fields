# Global Functions

## `acf_add_filter_variations()`

acf_add_filter_variations

* Registers variations for the given filter.
* @date    26/1/19
* @since ACF 5.7.11
* @param   string  $filter     The filter name.
* @param array   $variations An array variation keys.
* @param integer $index      The param index to find variation values.
* @return void

## `acf_add_action_variations()`

acf_add_action_variations

* Registers variations for the given action.
* @date    26/1/19
* @since ACF 5.7.11
* @param   string  $action     The action name.
* @param array   $variations An array variation keys.
* @param integer $index      The param index to find variation values.
* @return void

## `_acf_apply_hook_variations()`

_acf_apply_hook_variations

* Applies hook variations during apply_filters() or do_action().
* @date    25/1/19
* @since ACF 5.7.11
* @param   mixed
* @return mixed

## `acf_add_deprecated_filter()`

acf_add_deprecated_filter

* Registers a deprecated filter to run during the replacement.
* @date    25/1/19
* @since ACF 5.7.11
* @param   string $deprecated  The deprecated hook.
* @param string $version     The version this hook was deprecated.
* @param string $replacement The replacement hook.
* @return void

## `acf_add_deprecated_action()`

acf_add_deprecated_action

* Registers a deprecated action to run during the replacement.
* @date    25/1/19
* @since ACF 5.7.11
* @param   string $deprecated  The deprecated hook.
* @param string $version     The version this hook was deprecated.
* @param string $replacement The replacement hook.
* @return void

## `_acf_apply_deprecated_hook()`

Applies a deprecated filter during apply_filters() or do_action().

* @date    25/1/19
* @since ACF 5.7.11
* @param   mixed
* @return mixed

---
