<?php
/**
 * SCF REST Types Endpoint Extension
 *
 * @package SecureCustomFields
 * @subpackage REST_API
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SCF_Rest_Types_Endpoint
 *
 * Extends the /wp/v2/types endpoint to include SCF fields.
 *
 * @since 6.5.0
 */
class SCF_Rest_Types_Endpoint {

	/**
	 * Initialize the class.
	 *
	 * @since 6.5.0
	 */
	public function __construct() {
		add_filter( 'rest_pre_dispatch', array( $this, 'initialize' ), 10, 1 );
	}

	/**
	 * Initialize the endpoint extension.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $response The response object.
	 * @return mixed The response object.
	 */
	public function initialize( $response ) {
		if ( ! acf_get_setting( 'rest_api_enabled' ) ) {
			return $response;
		}

		$this->register_field();
		return $response;
	}

	/**
	 * Register the SCF fields for the post type.
	 *
	 * @since 6.5.0
	 *
	 * @return void
	 */
	public function register_field() {
		register_rest_field(
			'type',
			'scf_field_groups',
			array(
				'get_callback' => array( $this, 'get_scf_fields' ),
				'schema'       => $this->get_field_schema(),
			)
		);
	}

	/**
	 * Get SCF fields for a post type.
	 *
	 * @since 6.5.0
	 *
	 * @param array $object The post type object.
	 * @return array Array of field data.
	 */
	public function get_scf_fields( $post_type_object ) {
		// Get the post type from the object.
		$post_type = $post_type_object['slug'];

		// Get all field groups that are assigned to this post type.
		$field_groups = acf_get_field_groups(
			array(
				'post_type' => $post_type,
			)
		);

		// Initialize an array to store all field groups with their fields.
		$field_groups_data = array();

		// Loop through each field group.
		foreach ( $field_groups as $field_group ) {
			// Get all fields for this field group.
			$fields = acf_get_fields( $field_group );

			// Initialize an array to store fields for this group.
			$group_fields = array();

			// Loop through each field and extract label and type.
			foreach ( $fields as $field ) {
				$group_fields[] = array(
					'label' => $field['label'],
					'type'  => $field['type'],
				);
			}

			// Add this field group with its fields to the main array.
			$field_groups_data[] = array(
				'title'  => $field_group['title'],
				'fields' => $group_fields,
			);
		}

		// Return the array of field groups with their fields.
		return $field_groups_data;
	}

	/**
	 * Get the schema for the SCF fields.
	 *
	 * @since 6.5.0
	 *
	 * @return array The schema for the SCF fields.
	 */
	private function get_field_schema() {
		return array(
			'description' => 'Field groups attached to this post type.',
			'type'        => 'array',
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'title'  => array(
						'type'        => 'string',
						'description' => 'The field group title.',
					),
					'fields' => array(
						'type'        => 'array',
						'description' => 'The fields in this field group.',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'label' => array(
									'type'        => 'string',
									'description' => 'The field label.',
								),
								'type'  => array(
									'type'        => 'string',
									'description' => 'The field type.',
								),
							),
						),
					),
				),
			),
			'context'     => array( 'view', 'edit', 'embed' ),
		);
	}
}
