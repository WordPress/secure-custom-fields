# ACF_Rest_Request

Class ACF_Rest_Request

## Properties

### `$readonly_props`

Define which private/protected class properties are allowed read access. Access to these is controlled in
\ACF_Rest_Request::__get();

* @var string[]

### `$http_method`

* @var string The HTTP request method for the current request. i.e; GET, POST, PATCH, PUT, DELETE, OPTIONS, HEAD

### `$current_route`

* @var string The current route being requested.

### `$supported_routes`

* @var array Route URL patterns we support.

### `$url_params`

* @var array Parameters matched from the URL. e.g; object IDs.

### `$object_type`

* @var string The underlying object type. e.g; post, term, user, etc.

### `$object_sub_type`

* @var string The requested object type.

### `$child_object_type`

* @var string The object type for a child object. e.g. post-revision, autosaves, etc.

## Methods

### `parse_request`

Determine all required information from the current request.

### `__get`

Magic getter for accessing read-only properties. Should we ever need to enforce a getter method, we can do so here.

* @param string $name The desired property name.
* @return string|null

### `get_url_param`

Get a URL parameter if found on the request URL.

* @param $param
* @return mixed|null

### `set_http_method`

Determine the HTTP method of the current request.

### `set_current_route`

Get the current REST route as determined by WordPress.

### `build_supported_routes`

Build an array of route match patterns that we handle. These are the same as WordPress' core patterns except
we are also matching the object type here as well.

### `set_url_params`

Loop through supported routes to find matching pattern. Use matching pattern to determine any URL parameters.

### `set_object_types`

Determine the object type and sub type from the requested route. We need to know both the underlying WordPress
object type as well as post type or taxonomy in order to provide the right context when getting/updating fields.

### `get_post_type_by_rest_base`

Find the REST enabled post type object that matches the given REST base.

* @param string $rest_base
* @return WP_Post_Type|null

### `get_taxonomy_by_rest_base`

Find the REST enabled taxonomy object that matches the given REST base.

* @param $rest_base
* @return WP_Taxonomy|null
