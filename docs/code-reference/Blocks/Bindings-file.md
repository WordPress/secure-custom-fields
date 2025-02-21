# Bindings

The core SCF Blocks binding class.

## Methods

### `__construct`

Block Bindings constructor.

### `register_binding_sources`

Hooked to acf/init, register our binding sources.

### `get_value`

Handle returing the block binding value for an ACF meta value.

* @since ACF 6.2.8
* @param array     $source_attrs   An array of the source attributes requested.
* @param \WP_Block $block_instance The block instance.
* @param string    $attribute_name The block's bound attribute name.
* @return string|null The block binding value or an empty string on failure.
