# SCF_Rest_Types_Endpoint

Class SCF_Rest_Types_Endpoint

* Extends the /wp/v2/types endpoint to include SCF fields and source filtering.
* @since SCF 6.5.0

## Methods

### `__construct`

Initialize the class.

* @since SCF 6.5.0

### `filter_types_request`

Filter post types requests, fires for both collection and individual requests.
We only want to handle individual requets to ensure the post type requested matches the source.

* @since SCF 6.5.0
* @param mixed           $response The current response, either response or null.
* @param array           $handler  The handler for the route.
* @param WP_REST_Request $request  The request object.
* @return mixed The response or null.

### `filter_post_type`

Filter individual post type in the response.

* @since SCF 6.5.0
* @param WP_REST_Response $response The response object.
* @param WP_Post_Type     $post_type The post type object.
* @param WP_REST_Request  $request The request object.
* @return WP_REST_Response|null The filtered response or null to filter it out.

### `get_source_post_types`

Get an array of post types for each source.

* @since SCF 6.5.0
* @param string $source The source to get post types for.
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
