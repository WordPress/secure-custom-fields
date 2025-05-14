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
 * @since SCF 6.5.0
 */
class SCF_Rest_Types_Endpoint {

	/**
	 * Initialize the class.
	 *
	 * @since SCF 6.5.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_extra_fields' ) );
	}

	/**
	 * Register extra SCF fields for the post types endpoint.
	 *
	 * @since SCF 6.5.0
	 *
	 * @return void
	 */
	public function register_extra_fields() {
		if ( ! (bool) get_option( 'scf_beta_feature_editor_sidebar_enabled', false ) ) {
			return;
		}
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
	 * @since SCF 6.5.0
	 *
	 * @param array $post_type_object The post type object.
	 * @return array Array of field data.
	 */
	public function get_scf_fields( $post_type_object ) {
		$post_type         = $post_type_object['slug'];
		$field_groups      = acf_get_field_groups( array( 'post_type' => $post_type ) );
		$field_groups_data = array();

		foreach ( $field_groups as $field_group ) {
			$fields       = acf_get_fields( $field_group );
			$group_fields = array();

			foreach ( $fields as $field ) {
				$group_fields[] = array(
					'label' => $field['label'],
					'type'  => $field['type'],
				);
			}

			$field_groups_data[] = array(
				'title'  => $field_group['title'],
				'fields' => $group_fields,
			);
		}

		return $field_groups_data;
	}

	/**
	 * Get the schema for the SCF fields.
	 *
	 * @since SCF 6.5.0
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
