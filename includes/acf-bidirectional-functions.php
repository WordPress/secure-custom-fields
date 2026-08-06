<?php

/**
 * General functions relating to the bidirectional feature of some fields.
 *
 * @package wordpress/secure-custom-fields
 */

/**
 * Process updating bidirectional fields.
 *
 * @since ACF 6.2
 *
 * @param array          $target_item_ids The post, user or term IDs which should be updated with the origin item ID.
 * @param integer|string $post_id         The ACF encoded origin post, user or term ID.
 * @param array          $field           The field being updated on the origin post, user or term ID.
 * @param string|false   $target_prefix   The ACF prefix for a post, user or term ID required for the update_field call for this field type.
 *
 * @return void
 */
function acf_update_bidirectional_values( $target_item_ids, $post_id, $field, $target_prefix = false ) {

	// Bail early if we're already updating a bidirectional relationship to prevent recursion.
	if ( acf_get_data( 'acf_doing_bidirectional_update' ) ) {
		return;
	}

	// Support disabling bidirectionality globally.
	if ( ! acf_get_setting( 'enable_bidirection' ) ) {
		return;
	}

	if ( empty( $field['bidirectional'] ) || empty( $field['bidirectional_target'] ) ) {
		return;
	}

	$update_plan   = _scf_prepare_bidirectional_update( $target_item_ids, $post_id, $field, $target_prefix );
	$valid_targets = $update_plan['target_fields'];
	$item_id       = $update_plan['item_id'];
	$additions     = $update_plan['additions'];
	$subtractions  = $update_plan['subtractions'];

	if ( ! empty( $valid_targets ) ) {
		acf_set_data( 'acf_doing_bidirectional_update', true );

		// Loop over each target, processing additions and removals.
		foreach ( $valid_targets as $target_field ) {
			foreach ( $additions as $addition ) {
				$current_value = acf_get_array( get_field( $target_field, $addition, false ) );
				update_field( $target_field, array_unique( array_merge( $current_value, array( $item_id ) ) ), $addition );
			}

			foreach ( $subtractions as $subtraction ) {
				$current_value = acf_get_array( get_field( $target_field, $subtraction, false ) );
				update_field( $target_field, array_unique( array_diff( $current_value, array( $item_id ) ) ), $subtraction );
			}
		}

		acf_set_data( 'acf_doing_bidirectional_update', false );
	}
}

/**
 * Prepare a side-effect-free bidirectional update plan.
 *
 * @since SCF 6.9.5
 * @internal
 *
 * @param array          $target_item_ids Target post, user, or term IDs.
 * @param integer|string $post_id         Encoded origin object ID.
 * @param array          $field           Field being updated on the origin object.
 * @param string|false   $target_prefix   Target object prefix, or false for posts.
 * @param array|null     $current_values  Optional current raw field values.
 * @return array
 */
function _scf_prepare_bidirectional_update( $target_item_ids, $post_id, $field, $target_prefix = false, $current_values = null ) {
	$decoded       = acf_decode_post_id( $post_id );
	$valid_types   = acf_get_valid_bidirectional_target_types( $decoded['type'] );
	$target_fields = array();
	foreach ( $field['bidirectional_target'] as $target_field ) {
		$target_field_object = get_field_object( $target_field );
		if ( is_array( $target_field_object ) && in_array( $target_field_object['type'], $valid_types, true ) ) {
			$target_fields[] = $target_field;
		}
	}
	if ( empty( $target_fields ) ) {
		$current_values = array();
	} elseif ( null === $target_prefix && empty( $decoded['id'] ) ) {
		// Creating the origin object: the saver applies the field default as the current
		// value once the object exists, so mirror that instead of treating current as empty.
		$current_values = acf_get_array( $field['default_value'] ?? array() );
	} elseif ( null === $current_values ) {
		$current_values = get_field( $field['key'], $post_id, false );
	}
	$target_item_ids = empty( $target_fields ) ? array() : $target_item_ids;
	$additions       = array_diff( array_filter( acf_get_array( $target_item_ids ) ), array_filter( acf_get_array( $current_values ) ) );
	$subtractions    = array_diff( array_filter( acf_get_array( $current_values ) ), array_filter( acf_get_array( $target_item_ids ) ) );
	// A null prefix means the REST preflight is inferring the destination context, which is
	// only reliable for core types. A third-party field type owns its own target context;
	// guessing it (e.g. checking the ID as post/user/term) would raise false 403s on ID
	// collisions and still miss custom contexts, so such fields are deliberately left out of
	// the preflight. They keep working via their explicit prefix on the actual write path.
	$infer         = null === $target_prefix;
	$known_type    = in_array( $field['type'], array( 'relationship', 'post_object', 'user', 'taxonomy' ), true );
	$target_prefix = $infer ? ( 'user' === $field['type'] ? 'user' : ( 'taxonomy' === $field['type'] ? 'term' : false ) ) : $target_prefix;
	if ( $target_prefix ) {
		$prefix       = fn( $value ) => $target_prefix . '_' . $value;
		$additions    = array_map( $prefix, $additions );
		$subtractions = array_map( $prefix, $subtractions );
	}
	if ( $infer && ! $known_type ) {
		$destinations = array();
	} else {
		$destination_exists      = static function ( $destination ) {
			$destination = acf_decode_post_id( $destination );
			return ! in_array( $destination['type'], array( 'post', 'user', 'term', 'comment' ), true ) || '' !== get_object_subtype( $destination['type'], $destination['id'] );
		};
		$permission_subtractions = array_filter( $subtractions, $destination_exists );
		$destinations            = array_values( array_unique( array_merge( $additions, $permission_subtractions ) ) );
	}
	$item_id = $decoded['id'];
	return compact( 'target_fields', 'item_id', 'additions', 'subtractions', 'destinations' );
}

/**
 * Collect the bidirectional destinations that a field update will write.
 *
 * @since SCF 6.9.5
 * @internal
 *
 * @param array          $field   Resolved root or nested field.
 * @param mixed          $value   Incoming value for the field.
 * @param integer|string $post_id Encoded origin object ID.
 * @param array|null     $current Optional current values, or null to load them.
 * @param boolean        $resave_paginated Whether paginated repeater rows will be re-saved.
 * @return array
 */
function _scf_collect_bidirectional_destinations( $field, $value, $post_id, $current = null, $resave_paginated = false ) {
	if ( ! empty( $field['bidirectional'] ) && ! empty( $field['bidirectional_target'] ) ) {
		return _scf_prepare_bidirectional_update( $value, $post_id, $field, null, $current )['destinations'];
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	if ( $resave_paginated && 'repeater' === $field['type'] && ! empty( $field['pagination'] ) ) {
		// A paginated save re-saves every surviving stored row plus submitted changes.
		// Client-controlled aliases can collapse onto the same row index and resurrect a
		// deleted-then-edited row, so collect the conservative union of everything it may write.
		$rows = null === $current ? acf_get_value( $post_id, $field ) : $current;
		$rows = is_array( $rows ) ? $rows : array();
		unset( $value['acfcloneindex'] );
		$submitted      = array();
		$reordered_rows = array();
		foreach ( $value as $key => $row ) {
			if ( is_array( $row ) && false !== strpos( (string) $key, 'row' ) && ! isset( $row['acf_deleted'] ) && isset( $row['acf_reordered'] ) ) {
				$reordered_rows[ (int) str_replace( 'row-', '', (string) $key ) ] = true;
			}
		}
		foreach ( $value as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( false !== strpos( (string) $key, 'row' ) && isset( $row['acf_deleted'] ) ) {
				// The saver drops the stored row this deletion targets and does not re-fire it.
				$row_number = (int) str_replace( 'row-', '', (string) $key );
				unset( $rows[ $row_number ] );
				if ( 'row-' . $row_number === (string) $key && isset( $reordered_rows[ $row_number ] ) ) {
					$submitted[] = $row;
				}
				continue;
			}
			$submitted[] = $row;
		}
		$value = array_merge( array_values( $rows ), $submitted );
	}
	$extract_sub_value = static function ( $row, $sub_field, $container_type ) {
		// Group and Clone use key/_name with isset(); Repeater and Flexible Content use key/name and preserve explicit nulls.
		$uses_isset = in_array( $container_type, array( 'group', 'clone' ), true );
		$selectors  = $uses_isset
			? array( $sub_field['key'] ?? null, $sub_field['_name'] ?? null )
			: array( $sub_field['key'] ?? null, $sub_field['name'] ?? null );
		foreach ( $selectors as $selector ) {
			if ( null !== $selector && ( $uses_isset ? isset( $row[ $selector ] ) : array_key_exists( $selector, $row ) ) ) {
				return array( true, $row[ $selector ] );
			}
		}
		return array( false, null );
	};
	$destinations      = array();
	if ( 'flexible_content' === $field['type'] && ! empty( $field['layouts'] ) ) {
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['acf_fc_layout'] ) ) {
				continue;
			}
			foreach ( $field['layouts'] as $layout ) {
				if ( empty( $layout['sub_fields'] ) || $layout['name'] !== $row['acf_fc_layout'] ) {
					continue;
				}
				foreach ( $layout['sub_fields'] as $sub_field ) {
					list( $exists, $sub_value ) = $extract_sub_value( $row, $sub_field, $field['type'] );
					if ( $exists ) {
						$destinations = array_merge( $destinations, _scf_collect_bidirectional_destinations( $sub_field, $sub_value, $post_id, $current, $resave_paginated ) );
					}
				}
				break;
			}
		}
		return $destinations;
	}
	// Only traverse the core container shapes mirrored here; third-party containers may save different value structures.
	if ( ! in_array( $field['type'], array( 'group', 'clone', 'repeater' ), true ) || empty( $field['sub_fields'] ) ) {
		return $destinations;
	}
	$rows = ( 'repeater' === $field['type'] ) ? $value : array( $value );
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		foreach ( $field['sub_fields'] as $sub_field ) {
			list( $exists, $sub_value ) = $extract_sub_value( $row, $sub_field, $field['type'] );
			if ( $exists ) {
				if ( 'clone' === $field['type'] && ! empty( $sub_field['_clone'] ) && isset( $sub_field['__key'] ) ) {
					$sub_field['key'] = $sub_field['__key'];
				}
				if ( 'clone' === $field['type'] && isset( $field['_name'] ) && $field['name'] !== $field['_name'] ) {
					$name_length = strlen( $field['_name'] );
					$name_prefix = substr( $field['name'], 0, -$name_length );
					if ( $name_prefix . $field['_name'] === $field['name'] ) {
						$sub_field['name'] = $name_prefix . $sub_field['name'];
					}
				}
				// A Group stores its children under a prefixed meta name; mirror that so a
				// nested paginated repeater reads its existing rows from the right key.
				if ( 'group' === $field['type'] ) {
					$sub_field['name'] = $field['name'] . '_' . ( $sub_field['_name'] ?? $sub_field['name'] );
				}
				$destinations = array_merge( $destinations, _scf_collect_bidirectional_destinations( $sub_field, $sub_value, $post_id, $current, $resave_paginated ) );
			}
		}
	}
	return $destinations;
}

/**
 * Allows third party fields to enable support as a target field type for a particular object type
 *
 * @since ACF 6.2
 *
 * @param string $object_type The object type that will be updated on the target field, such as 'term', 'user' or 'post'.
 *
 * @return array An array of valid field type names (slugs) for the target of the bidirectional field.
 */
function acf_get_valid_bidirectional_target_types( $object_type ) {
	$valid_target_types = array();
	switch ( $object_type ) {
		case 'term':
			$valid_target_types = array( 'taxonomy' );
			break;
		case 'user':
			$valid_target_types = array( 'user' );
			break;
		case 'post':
			$valid_target_types = array( 'relationship', 'post_object' );
			break;
	}
	return apply_filters( 'acf/bidirectional/supported_field_types_for_post', $valid_target_types, $object_type );
}

/**
 * Build the complete choices argument for rendering the select2 field for bidirectional target based on the currently selected choices
 *
 * @since ACF 6.2
 *
 * @param array $choices The currently selected choices (as an array of field keys).
 *
 * @return array
 */
function acf_build_bidirectional_target_current_choices( $choices ) {
	if ( empty( $choices ) ) {
		return array();
	}

	$results = array();
	foreach ( $choices as $choice ) {
		if ( empty( $choice ) || ! is_string( $choice ) ) {
			continue;
		}

		$field_object = get_field_object( $choice );
		if ( is_array( $field_object ) && ! empty( $field_object['label'] ) ) {
			$results[ $choice ] = $field_object['label'];
		} else {
			$results[ $choice ] = $choice;
		}
	}

	return $results;
}

/**
 * Build valid fields for a bidirectional relationship for select2 display
 *
 * @since ACF 6.2
 *
 * @param array $results The original results array.
 * @param array $options The options provided to the select2 AJAX search.
 *
 * @return array
 */
function acf_build_bidirectional_relationship_field_target_args( $results, $options ) {
	$valid_field_types = apply_filters( 'acf/bidirectional/supported_target_field_types', array( 'relationship', 'post_object', 'user', 'taxonomy' ) );

	$field_groups = array_filter(
		acf_get_field_groups(),
		function ( $field_group ) {
			return $field_group['active'];
		}
	);

	$valid_fields = array();
	foreach ( $field_groups as $field_group ) {
		$fields = acf_get_fields( $field_group );
		foreach ( $fields as $field ) {
			if ( in_array( $field['type'], $valid_field_types, true ) ) {
				if ( empty( $valid_fields[ $field_group['title'] ] ) ) {
					$valid_fields[ $field_group['title'] ] = array();
				}
				$valid_fields[ $field_group['title'] ][ $field['key'] ] = array(
					'type'  => $field['type'],
					'label' => $field['label'],
				);

				if ( isset( $options['parent_key'] ) && $options['parent_key'] === $field['key'] ) {
					$valid_fields[ $field_group['title'] ][ $field['key'] ]['this_field'] = true;
				}
			}
		}
	}

	foreach ( $valid_fields as $field_group_name => $fields ) {
		$field_group = array(
			'text'     => $field_group_name,
			'children' => array(),
		);
		foreach ( $fields as $key => $data ) {
			$field_group['children'][] = array(
				'id'               => $key,
				'text'             => $data['label'],
				'field_type'       => $data['type'],
				/* translators: %s A field type name, such as "Relationship" */
				'human_field_type' => sprintf( __( '%s Field', 'secure-custom-fields' ), acf_get_field_type_prop( $data['type'], 'label' ) ),
				'this_field'       => ! empty( $data['this_field'] ),
			);
		}
		$results['results'][] = $field_group;
	}

	return $results;
}

add_filter( 'acf/fields/select/query/key=_acf_bidirectional_target', 'acf_build_bidirectional_relationship_field_target_args', 10, 2 );

/**
 * Renders the field settings required for bidirectional fields
 *
 * @since ACF 6.2
 *
 * @param array $field The field object passed into field setting functions.
 *
 * @return void
 */
function acf_render_bidirectional_field_settings( $field ) {
	if ( ! acf_get_setting( 'enable_bidirection' ) ) {
		return;
	}

	acf_render_field_setting(
		$field,
		array(
			'label'        => __( 'Bidirectional', 'secure-custom-fields' ),
			'instructions' => __( 'Update a field on the selected values, referencing back to this ID', 'secure-custom-fields' ),
			'type'         => 'true_false',
			'name'         => 'bidirectional',
			'ui'           => 1,
		)
	);

	acf_render_field_setting(
		$field,
		array(
			'name'       => 'bidirectional_notes',
			'type'       => 'message',
			'message'    => acf_get_bidirectional_field_settings_instruction_text(),
			'conditions' => array(
				'field'    => 'bidirectional',
				'operator' => '==',
				'value'    => 1,
			),
		)
	);

	acf_render_field_setting(
		$field,
		array(
			'type'         => 'select',
			'name'         => 'bidirectional_target',
			'label'        => __( 'Target Field', 'secure-custom-fields' ),
			'instructions' => __( 'Select field(s) to store the reference back to the item being updated. You may select this field. Target fields must be compatible with where this field is being displayed. For example, if this field is displayed on a Taxonomy, your target field should be of type Taxonomy', 'secure-custom-fields' ),
			'class'        => 'bidrectional_target',
			'choices'      => acf_build_bidirectional_target_current_choices( $field['bidirectional_target'] ),
			'conditions'   => array(
				'field'    => 'bidirectional',
				'operator' => '==',
				'value'    => 1,
			),
			'ui'           => 1,
			'multiple'     => 1,
			'ajax'         => 1,
		)
	);
}

/**
 * Returns the translated instructional text for the message field for the bidirectional field settings.
 *
 * @since ACF 6.2
 *
 * @return string The html containing the instructional message.
 */
function acf_get_bidirectional_field_settings_instruction_text() {
	/* translators: %s the URL to ACF's bidirectional relationship documentation */
	$message = '<p class="acf-feature-notice with-warning-icon">' . __( 'Enabling the bidirectional setting allows you to update a value in the target fields for each value selected for this field, adding or removing the Post ID, Taxonomy ID or User ID of the item being updated.', 'secure-custom-fields' ) . '</p>';
	return $message;
}
