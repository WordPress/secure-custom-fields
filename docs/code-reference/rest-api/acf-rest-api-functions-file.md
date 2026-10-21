# Acf Rest API Functions Global Functions

## `acf_get_field_rest_schema()`

Get the REST API schema for a given field.

* @param array $field
* @return array

## `acf_get_field_rest_links()`

Get the REST API field links for a given field. The links are appended to the REST response under the _links property
and provide API resource links to related objects. If a link is marked as 'embeddable', WordPress can load the resource
in the main request under the_embedded property when the request contains the _embed URL parameter.

* @see \acf_field::get_rest_links()
@see <https://developer.wordpress.org/rest-api/using-the-rest-api/linking-and-embedding/>
* @param string|integer $post_id
* @param array          $field
* @return array

## `scf_rest_sanitize_user_sub_fields()`

Replaces User subfield data with REST-safe values.

* @param mixed  $formatted_value The formatted parent value.
* @param mixed  $raw_value       The raw parent value.
* @param array  $sub_fields      The parent field's subfields.
* @param string $output_property The subfield property used as the output key.
* @return mixed

## `scf_rest_sanitize_user_data()`

Replaces formatted User field data with REST-safe values based on the field definition.

* @param mixed $formatted_value The formatted field value.
* @param mixed $raw_value       The raw field value.
* @param array $field           The field array.
* @return mixed

## `acf_rest_reducible_reference_leaf_types()`

Field types the reference sanitiser reduces at the leaf level. Each of these
expands a stored post/attachment ID into a full resource in
`?acf_format=standard` mode, so the leaf reducer must gate them on the
caller's read permission for the referenced resource.

* @since SCF 6.9.6
* @return string[]

## `acf_rest_sanitize_reference_sub_fields()`

Walks the subfields of a container field (Group, Clone, Repeater layout row,
or Flexible Content layout row), delegating each subfield's value to
{@see acf_rest_sanitize_reference_data()} so that any nested reference
field data pointing at resources the current user cannot read is reduced to
its light-format shape.

* @since SCF 6.9.6
* @param mixed  $formatted_value The formatted parent value.
* @param mixed  $raw_value       The raw parent value.
* @param array  $sub_fields      The parent field's subfields.
* @param string $output_property The subfield property used as the output key.
* @return mixed

## `acf_rest_sanitize_reference_data()`

Reduces reference field data (Relationship, Post Object, Image, Gallery,
File) to light-format IDs when the current user cannot read one or more of
the referenced resources.

* Recurses into Group, Clone, Repeater, and Flexible Content containers so
nested reference fields are handled the same way as top-level ones.
* @since SCF 6.9.6
* @param mixed $formatted_value The formatted field value.
* @param mixed $raw_value       The raw field value.
* @param array $field           The field array.
* @return mixed

## `acf_rest_reduce_reference()`

All-or-nothing reduction for reference field values.

* If every referenced target passes {@see acf_rest_reference_is_exposable()},
the fully-expanded formatted value is returned unchanged. As soon as any one
target fails the check, the whole field value is reduced to its light-format
shape (integer ID for single-value fields, integer ID array for multi-value
fields). This matches how User fields are reduced and gives
consumers a uniform response shape: they see either the full expansion or
the compact ID shape they would have got from `?acf_format=light`, not a
mixed array.
* IDs are taken from the stored raw value (which is always the resource ID or
IDs for these field types), with a defensive fallback that extracts IDs from
the formatted value if the raw value is unusable.
* @since SCF 6.9.6
* @param mixed $formatted_value The formatted field value.
* @param mixed $raw_value       The raw field value (stored ID or ID array).
* @param array $field           The field array.
* @return mixed

## `acf_rest_reduce_icon_picker_reference()`

All-or-nothing reduction for Icon Picker fields.

* Icon Picker stores a compound value `{ type: string, value: mixed }` and
only its `media_library` variant expands a stored attachment ID into a full
attachment array in standard-format mode. The other variants (`dashicons`,
`url`, `svg`) carry user-provided data with no permission gate needed.
* When the type is `media_library` and the caller cannot read the target
attachment, the field is reduced to the same shape
`?acf_format=light` would return: `{ type: 'media_library', value: <int> }`.
Everything else passes through unchanged.
* @since SCF 6.9.6
* @param mixed $formatted_value The formatted field value.
* @param mixed $raw_value       The raw field value.
* @param array $field           The field array.
* @return mixed

## `acf_rest_reference_is_exposable()`

Determines whether the current user should see the fully-expanded target of
a reference field (Relationship, Post Object, Image, Gallery, File).

* Delegates to the target post type's REST controller (usually
`WP_REST_Posts_Controller`, or `WP_REST_Attachments_Controller` for
attachments, or a custom class declared via `rest_controller_class`) so this
stays in lockstep with what `/wp/v2/<post_type>/<id>` returns for the same
caller. Targets whose post type is not REST-exposed
(`show_in_rest === false`) are treated as non-exposable, matching core.
* Fail-closed contract: if the resolved REST controller does not implement
`check_read_permission()`, the target is treated as non-exposable. A
bare `current_user_can( 'read_post', $id )` fallback is intentionally NOT
used because it evaluates to false for anonymous callers on published posts
(anon lacks the `read` cap on standard installs), so the fallback would
silently reduce publicly readable posts. Sites running a non-standard REST
controller for a post type can re-expose targets via the
`acf/rest/expose_reference_target` filter after making an informed decision.
* @since SCF 6.9.6
* @param integer $target_id The referenced resource ID (post or attachment).
* @param array   $field     The field array (context for filter subscribers).
* @return boolean

## `scf_rest_get_reference_controller()`

Returns a shared REST controller instance for checking reference targets.

* Reference checks can run for every referenced post on a page, so the
controller for each post type is only instantiated once per request.
* @since SCF 6.9.7
* @param string $controller_class The controller class name.
* @param string $post_type        The post type the controller handles.
* @return object

## `scf_rest_format_standard_value()`

Applies standard field formatting while keeping User values and references to
resources the current user cannot read safe for REST output.

* This does not apply the public acf/rest/format_value_for_rest filter, so it
reduces references itself. The `{field}_source` REST output relies on that.
* @param mixed      $value   The raw field value.
* @param string|int $post_id The post ID of the current object.
* @param array      $field   The field array.
* @return mixed

## `acf_format_value_for_rest()`

Format a given field's value for output in the REST API.

* @param        $value
* @param $post_id
* @param $field
* @param string $format 'light' for normal REST API formatting or 'standard' to apply ACF's normal field formatting.
* @return mixed

## `acf_rest_apply_reference_sanitizer()`

Reduces reference-field REST responses (Relationship, Post Object, Image,
Gallery, File) to light-format IDs when the current caller cannot read one
or more of the referenced resources. Hooked into
`acf/rest/format_value_for_rest` so the sanitizer only runs for field types
that can carry reference data (either directly or via containers).

* @since SCF 6.9.6
* @param mixed          $value_formatted The formatted field value.
* @param string|integer $post_id         The post ID of the current object.
* @param array          $field           The field array.
* @param mixed          $value           The raw/unformatted value.
* @param string         $format          The format applied to the field value.
* @return mixed

---
