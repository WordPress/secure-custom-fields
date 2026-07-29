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

## `scf_rest_format_standard_value()`

Applies standard field formatting while keeping User values safe for REST output.

* This does not apply the public acf/rest/format_value_for_rest filter.
* @param mixed      $value   The raw field value.
* @param string|int $post_id The post ID of the current object.
* @param array      $field   The field array.
* @return mixed

## `acf_format_value_for_rest()`

Format a given field's value for output in the REST API.

* @param        $value
* @param $post_id
* @param $field
* @param string  $format 'light' for normal REST API formatting or 'standard' to apply ACF's normal field formatting.
* @return mixed

---
