# Hooks

Class to handle legacy hook mappings

## Properties

### `$hook_mappings`

Hook mappings from new to legacy format.

* @var array

## Methods

### `__construct`

Constructor.

### `is_legacy_hooks_enabled`

Check if legacy hooks should be enabled.

* @return bool

### `setup_legacy_hooks`

Setup all legacy hook mappings.

### `setup_legacy_filter`

Setup a legacy filter mapping.

* @param string $new_hook New hook name.
* @param string $legacy_hook Legacy hook name.
* @param int    $accepted_args Number of arguments the filter accepts.

### `setup_legacy_action`

Setup a legacy action mapping.

* @param string $new_hook New hook name.
* @param string $legacy_hook Legacy hook name.
* @param int    $accepted_args Number of arguments the action accepts.
