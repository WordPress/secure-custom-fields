# ACF_Rest_Embed_Links

Class ACF_Rest_Embed_Links

* Manage the addition of embed links on supported REST endpoints.

## Properties

### `$links`

* @var array Links to add to the response. These can be flagged as embeddable and expanded when _embed is passed with the request.

## Methods

### `hook_link_handlers`

Hook into all REST-enabled post type, taxonomy, and the user controllers in order to prepare links.

### `prepare_links`

Add links to internal property for subsequent use in \ACF_Rest_Embed_Links::load_item_links().

* @param       $post_id
* @param array   $field

### `load_item_links`

Hook into the rest_prepare_{$type} filters and add links for the object being prepared.

* @param WP_REST_Response        $response
* @param WP_Post|WP_User|WP_Term $item
* @param WP_REST_Request         $request
* @return WP_REST_Response
