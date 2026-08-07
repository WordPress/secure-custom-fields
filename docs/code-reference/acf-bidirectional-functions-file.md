# Acf Bidirectional Functions Global Functions

## `acf_update_bidirectional_values()`

Process updating bidirectional fields.

* @since ACF 6.2
* @param array          $target_item_ids The post, user or term IDs which should be updated with the origin item ID.
* @param integer|string $post_id         The ACF encoded origin post, user or term ID.
* @param array          $field           The field being updated on the origin post, user or term ID.
* @param string|false   $target_prefix   The ACF prefix for a post, user or term ID required for the update_field call for this field type.
* @return void

## `_scf_prepare_bidirectional_update()`

Prepare a side-effect-free bidirectional update plan.

* @since SCF 6.9.5
@internal
* @param array          $target_item_ids Target post, user, or term IDs.
* @param integer|string $post_id         Encoded origin object ID.
* @param array          $field           Field being updated on the origin object.
* @param string|false   $target_prefix   Target object prefix, or false for posts.
* @param array|null     $current_values  Optional current raw field values.
* @return array

## `_scf_collect_bidirectional_destinations()`

Collect the bidirectional destinations that a field update will write.

* @since SCF 6.9.5
@internal
* @param array          $field   Resolved root or nested field.
* @param mixed          $value   Incoming value for the field.
* @param integer|string $post_id Encoded origin object ID.
* @param array|null     $current Optional current values, or null to load them.
* @param boolean        $resave_paginated Whether paginated repeater rows will be re-saved.
* @return array

## `acf_get_valid_bidirectional_target_types()`

Allows third party fields to enable support as a target field type for a particular object type

* @since ACF 6.2
* @param string $object_type The object type that will be updated on the target field, such as 'term', 'user' or 'post'.
* @return array An array of valid field type names (slugs) for the target of the bidirectional field.

## `acf_build_bidirectional_target_current_choices()`

Build the complete choices argument for rendering the select2 field for bidirectional target based on the currently selected choices

* @since ACF 6.2
* @param array $choices The currently selected choices (as an array of field keys).
* @return array

## `acf_build_bidirectional_relationship_field_target_args()`

Build valid fields for a bidirectional relationship for select2 display

* @since ACF 6.2
* @param array $results The original results array.
* @param array $options The options provided to the select2 AJAX search.
* @return array

## `acf_render_bidirectional_field_settings()`

Renders the field settings required for bidirectional fields

* @since ACF 6.2
* @param array $field The field object passed into field setting functions.
* @return void

## `acf_get_bidirectional_field_settings_instruction_text()`

Returns the translated instructional text for the message field for the bidirectional field settings.

* @since ACF 6.2
* @return string The html containing the instructional message.

---
