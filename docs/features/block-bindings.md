# Block Bindings

Secure Custom Fields registers a WordPress block bindings source called `acf/field`. It lets a block attribute read its value from an SCF field instead of storing the value in the block itself. Update the field and every block bound to it updates too.

Block bindings require WordPress 6.5 or newer. The editor controls, which add a field picker to the block sidebar, require WordPress 6.7 or newer, and they are only shown when the SCF datastore is turned off, which is the default.

## Scenarios

It helps to separate three related ideas, because they are owned by different pieces of code:

1. **A block bound to an external data source.** Remote Data Blocks handles this. It registers its own binding source and renders remote records inside a block template.
2. **A block bound to an SCF field.** Secure Custom Fields handles this, through the `acf/field` source described on this page.
3. **A block bound to an SCF field that is backed by an external data source.** This is the combination the two plugins can build together. SCF provides the field and the binding, and a small piece of integration code resolves the field value from the external source.

This page covers scenario 2 in full and shows how to build scenario 3 on top of it.

## How a bound field is resolved

Registration happens in `includes/Blocks/Bindings.php` on the `acf/init` action, and only when the `enable_block_bindings` setting is on. The source declares `postId` and `postType` as its block context.

When WordPress renders a block that uses the source, SCF loads the field named in the binding args and returns its value for the bound attribute. Three conditions gate whether a field can be returned:

- The field type must allow bindings. Field types opt out with `'bindings' => false` in their `$supports` array. Repeater, flexible content, group, clone, gallery, tab, accordion and message all opt out.
- The field's **Allow Access to Value in Editor UI** setting (`allow_in_bindings`) must be on.
- The block attribute must be one that WordPress itself allows bindings on.

Text-like values are returned as they are. Arrays are JSON encoded, except for the attributes that expect a specific part of an array: `id`, `alt` and `title` read the key of the same name, `url` reads the `url` key, and `rel` joins checkbox values with a space.

Values are loaded with formatting on, so what a binding returns is the formatted value, not the raw stored value.

## Which blocks can be bound

WordPress keeps its own list of block attributes that support bindings, and that list is not filterable before WordPress 6.9. At the moment it covers `core/paragraph`, `core/heading`, `core/image` and `core/button`, which is why SCF's controls ship exactly those. Newer WordPress versions extend the list, and WordPress 6.9 adds a `block_bindings_supported_attributes` filter for it.

SCF keeps its own map of the same blocks, so the editor only offers field types that make sense for a given attribute. For example, `core/image` attributes accept only image fields, and `core/button` text accepts text-like fields.

Because core owns the list of bindable attributes, adding a block to SCF's map alone does not make a binding work. The block also has to be bindable in core, either from core's built-in list or through the WordPress 6.9 filter. Keep that in mind before extending the map.

## Extending the bindable blocks

A plugin can extend SCF's map with the `scf/block-bindings-config` JavaScript filter. The config is keyed by block name, and each entry maps an attribute name to the SCF field types allowed for that attribute.

```js
import { addFilter } from '@wordpress/hooks';

addFilter(
 'scf/block-bindings-config',
 'my-plugin/extra-bindings',
 ( config ) => ( {
  ...config,
  'core/post-date': {
   datetime: [ 'date_picker', 'date_time_picker' ],
  },
 } )
);
```

The filter is read on every lookup rather than once at load, so registering it after the SCF bundle has loaded still works. The map only applies to the editor controls, which are enqueued when the SCF datastore is off. A field also has to be exposed over REST and have **Allow Access to Value in Editor UI** turned on before it can be picked.

## Resolving a field value from another source

The `scf/blocks/binding_field_value` PHP filter runs after the field has loaded and before its value is mapped to the bound attribute. Use it to swap the stored value for data from somewhere else, such as an API.

```php
add_filter(
 'scf/blocks/binding_field_value',
 function ( $field_value, $field, $source_attrs, $block_instance, $attribute_name ) {
  if ( 'product_code' !== $field['name'] ) {
   return $field_value;
  }

  $product = my_plugin_fetch_product( $field_value );

  return $product['name'] ?? '';
 },
 10,
 5
);
```

The value arriving here is already formatted and escaped, because the field is loaded with formatting on. If you need the raw stored value, use the `acf/load_value` filter instead and let the binding read that.

Values returned here still pass through the normal attribute mapping, so an array with `url`, `alt` and `title` keys resolves correctly for image bindings. The `acf/blocks/binding_value` filter runs afterwards if you need to adjust the final output.

This filter does not run for the editor preview. The editor reads field values from the REST API, so a value resolved here shows on the front end only.

## Connecting to Remote Data Blocks

[Remote Data Blocks](https://wordpress.org/plugins/remote-data-blocks/) stores a reference to a remote query in a block attribute rather than in post meta. To let SCF fields carry that reference, store it in the field and resolve it during rendering.

Remote Data Blocks exposes a `remote_data_blocks_loaded` action and a `REMOTE_DATA_BLOCKS__LOADED` constant so dependents can wait for it. Its `BlockBindings::get_value()` method is public and accepts a plain array in place of a `WP_Block`, which is what makes the bridge possible.

Sketch of the integration. The action only fires while Remote Data Blocks is loading, so also check the constant for the case where your code loads after it.

```php
if ( defined( 'REMOTE_DATA_BLOCKS__LOADED' ) ) {
 my_plugin_register_remote_binding();
} else {
 add_action( 'remote_data_blocks_loaded', 'my_plugin_register_remote_binding' );
}
```

Inside `my_plugin_register_remote_binding`, hook the SCF filter. In the callback, read the raw stored reference, pass it as the `remote-data-blocks/remoteData` context of a synthetic block, and return the resolved string. Read the raw value rather than the filtered one, because the value arriving at the filter is already escaped and a stored JSON reference would not decode:

```php
add_filter( 'scf/blocks/binding_field_value', 'my_plugin_resolve_remote_field', 10, 5 );

function my_plugin_resolve_remote_field( $field_value, $field, $source_attrs, $block_instance, $attribute_name ) {
 if ( ! class_exists( '\RemoteDataBlocks\Editor\DataBinding\BlockBindings' ) ) {
  return $field_value;
 }

 $post_id = $block_instance->context['postId'] ?? null;

 if ( ! $post_id ) {
  return $field_value;
 }

 $raw       = get_field( $field['name'], $post_id, false );
 $reference = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

 if ( ! is_array( $reference ) ) {
  return $field_value;
 }

 $resolved = \RemoteDataBlocks\Editor\DataBinding\BlockBindings::get_value(
  array( 'field' => 'title' ),
  array(
   'name'       => 'my-plugin/remote-field',
   'attributes' => array(),
   'context'    => array(
    'remote-data-blocks/remoteData' => $reference,
   ),
  ),
  $attribute_name
 );

 return is_string( $resolved ) ? esc_html( $resolved ) : '';
}
```

Guard every one of these calls with `class_exists()` and a version check. Remote Data Blocks requires WordPress 6.7 and PHP 8.1, while Secure Custom Fields supports WordPress 6.2 and PHP 7.4, so the bridge must degrade quietly when the plugin is absent or older than expected.

Because the filter runs on the front end only, a value resolved this way does not appear in the editor preview unless you also expose it over REST. The `acf/pre_load_value` and `acf/format_value` filters are the right place to make the REST response and the binding agree on the same value.

## Limits

- Blocks that WordPress has not given binding support to cannot be bound from SCF's side. That is a WordPress constraint, not an SCF one. The `block_bindings_supported_attributes` filter in WordPress 6.9 is the way to widen it.
- The `acf/field` source reads its context from `postId` and `postType` only. Binding to option, term or user values is not part of this source.
- The editor controls need WordPress 6.7 or newer, and they only load when the SCF datastore is off. On older WordPress versions, or with the datastore on, a binding still resolves on the front end if it is added through the code editor, but there is no picker.
