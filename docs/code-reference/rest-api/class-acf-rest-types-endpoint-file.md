# SCF_Rest_Types_Endpoint

Class SCF_Rest_Types_Endpoint

* Extends the /wp/v2/types endpoint to include SCF fields and source filtering.
* @since SCF 6.5.0

## Methods

### `__construct`

Initialize the class and register hooks.

* @since SCF 6.5.0
* @since 6.7.0 Simplified hook registration.

### `is_valid_source`

Validate source parameter.

* @since 6.7.0
* @param string $source The source value to validate.
* @return bool True if valid, false otherwise.

### `filter_types_request`

Filter post types requests for individual post type requests.

* @since SCF 6.5.0
* @since 6.7.0 Use is_valid_source() helper method.
* @param mixed           $response The current response.
* @param array           $handler  The handler for the route.
* @param WP_REST_Request $request  The request object.
* @return mixed The response or WP_Error.

### `filter_post_type`

Filter individual post type in the response.

* @since SCF 6.5.0
* @since 6.7.0 Use is_valid_source() helper method.
* @param WP_REST_Response $response  The response object.
* @param WP_Post_Type     $post_type The post type object.
* @param WP_REST_Request  $request   The request object.
* @return WP_REST_Response|null The filtered response or null.

### `get_source_post_types`

Get post types for a specific source.

* @since SCF 6.5.0
* @param string $source The source to get post types for (core, scf, other).
* @return array An array of post type names for the specified source.

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

### `register_parameters`

Register the source parameter for the post types endpoint.

* @since SCF 6.5.0

### `get_source_param_definition`

Get the source parameter definition

* @since SCF 6.5.0
* @since 6.7.0 Use VALID_SOURCES constant.
* @return array Parameter definition

### `add_parameter_to_endpoints`

Add source parameter directly to the endpoints for proper documentation

* @since SCF 6.5.0
* @param array $endpoints The REST API endpoints.
* @return array Modified endpoints

### `add_collection_params`

Add source parameter to the collection parameters for the types endpoint.

* @since SCF 6.5.0
* @param array $query_params JSON Schema-formatted collection parameters.
* @return array Modified collection parameters.

### `clean_types_response`

Clean up null entries from the response

* @since SCF 6.5.0
* @param array           $response The response data.
* @param WP_REST_Server  $server   The REST server instance.
* @param WP_REST_Request $request  The original request.
* @return array            The filtered response data.
