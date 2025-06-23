# SCF_Rest_Types_Endpoint

Class SCF_Rest_Types_Endpoint

* Extends the /wp/v2/types endpoint to include SCF fields.
* @since SCF 6.5.0

## Methods

### `__construct`

Initialize the class.

* @since SCF 6.5.0

### `register_extra_fields`

Register extra SCF fields for the post types endpoint.

* @since SCF 6.5.0
* @return void

### `get_scf_fields`

Get SCF fields for a post type.

* @since SCF 6.5.0
* @param array $post_type_object The post type object.
* @return array Array of field data.

### `get_field_schema`

Get the schema for the SCF fields.

* @since SCF 6.5.0
* @return array The schema for the SCF fields.
