# Global Functions

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

## `acf_format_value_for_rest()`

Format a given field's value for output in the REST API.

* @param        $value
* @param $post_id
* @param $field
* @param string  $format 'light' for normal REST API formatting or 'standard' to apply ACF's normal field formatting.
* @return mixed

---
