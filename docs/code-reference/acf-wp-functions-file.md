# Acf Wp Functions Global Functions

## `acf_get_object_type()`

Returns a WordPress object type.

* @date    1/4/20
* @since ACF 5.9.0
* @param   string $object_type    The object type (post, term, user, etc).
* @param string $object_subtype Optional object subtype (post type, taxonomy).
* @return object

## `acf_decode_post_id()`

Decodes a post_id value such as 1 or "user_1" into an array containing the type and ID.

* @date    25/1/19
* @since ACF 5.7.11
* @param   (int|string) $post_id The post id.
* @return array

## `acf_get_object_type_rest_base()`

Determine the REST base for a post type or taxonomy object. Note that this is not intended for use
with term or post objects but is, instead, to be used with the underlying WP_Post_Type and WP_Taxonomy
instances.

* @param WP_Post_Type|WP_Taxonomy $type_object
* @return string|null

## `acf_get_object_id()`

Extract the ID of a given object/array. This supports all expected types handled by our update_fields() and
load_fields() callbacks.

* @param WP_Post|WP_User|WP_Term|WP_Comment|array $object
* @return integer|mixed|null

---
