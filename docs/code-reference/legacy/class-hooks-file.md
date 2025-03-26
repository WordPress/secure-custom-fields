# Hooks

Class to handle legacy hook mappings.

* @since 6.5.0

## Properties

### `$hook_mappings`

Hook mappings from new to legacy format.

* @since 6.5.0
* @var array

## Methods

### `__construct`

Constructor.

* @since 6.5.0

### `is_legacy_hooks_enabled`

Check if legacy hooks should be enabled.

* @since 6.5.0
* @return bool

### `setup_legacy_hooks`

Setup all legacy hook mappings.

* @since 6.5.0

### `setup_legacy_filter`

Setup a legacy filter mapping.

* @since 6.5.0
* @param string $new_hook New hook name.
* @param string $legacy_hook Legacy hook name.
* @param int    $accepted_args Number of arguments the filter accepts.

### `setup_legacy_action`

Setup a legacy action mapping.

* @since 6.5.0
* @param string $new_hook New hook name.
* @param string $legacy_hook Legacy hook name.
* @param int    $accepted_args Number of arguments the action accepts.
