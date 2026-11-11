<?php
/**
 * Adds support for saving/retrieving values from user meta.
 *
 * @package    SCF
 * @subpackage Meta
 * @since      SCF 6.5
 */

namespace SCF\Meta;

/**
 * A class to add support for saving to user meta.
 */
class User extends MetaLocation {

	/**
	 * The unique slug/name of the meta location.
	 *
	 * @var string
	 */
	public string $location_type = 'user';

	/**
	 * Retrieves all SCF meta for the provided user, without protected user meta keys.
	 *
	 * @since SCF 6.9.9
	 *
	 * @param integer|string $object_id The ID of the object to get meta from.
	 * @return array
	 */
	public function get_meta( $object_id = 0 ): array {
		return $this->without_protected_keys( parent::get_meta( $object_id ) );
	}

	/**
	 * Retrieves a field value, unless the field name is a protected user meta key.
	 *
	 * @since SCF 6.9.9
	 *
	 * @param integer|string $object_id The ID of the object the metadata is for.
	 * @param array          $field     The field array.
	 * @return mixed
	 */
	public function get_value( $object_id = 0, array $field = array() ) {
		if ( scf_is_protected_user_meta_key( $field['name'] ?? '' ) ) {
			return null;
		}

		return parent::get_value( $object_id, $field );
	}

	/**
	 * Updates an object's metadata, skipping protected user meta keys.
	 *
	 * @since SCF 6.9.9
	 *
	 * @param integer|string $object_id The ID of the object the metadata is for.
	 * @param array          $meta      The metadata to save to the object.
	 * @return void
	 */
	public function update_meta( $object_id = 0, array $meta = array() ) {
		parent::update_meta( $object_id, $this->without_protected_keys( $meta, (int) $object_id ) );
	}

	/**
	 * Updates a field value, unless the field name is a protected user meta key.
	 *
	 * @since SCF 6.9.9
	 *
	 * @param integer|string $object_id The ID of the object the metadata is for.
	 * @param array          $field     The field array.
	 * @param mixed          $value     The metadata value.
	 * @return integer|boolean
	 */
	public function update_value( $object_id = 0, array $field = array(), $value = '' ) {
		if ( scf_is_protected_user_meta_key( $field['name'] ?? '', (int) $object_id ) ) {
			return false;
		}

		return parent::update_value( $object_id, $field, $value );
	}

	/**
	 * Deletes a field value, unless the field name is a protected user meta key.
	 *
	 * @since SCF 6.9.9
	 *
	 * @param integer|string $object_id The ID of the object the metadata is for.
	 * @param array          $field     The field array.
	 * @return boolean
	 */
	public function delete_value( $object_id = 0, array $field = array() ): bool {
		if ( scf_is_protected_user_meta_key( $field['name'] ?? '', (int) $object_id ) ) {
			return false;
		}

		return parent::delete_value( $object_id, $field );
	}

	/**
	 * Gets a reference key, unless the key is a protected user meta key.
	 *
	 * @since SCF 6.9.9
	 *
	 * @param integer|string $object_id  The ID of the object to get the reference key from.
	 * @param string         $field_name The name of the field to get the reference for.
	 * @return string|null
	 */
	public function get_reference( $object_id = 0, $field_name = '' ) {
		if ( scf_is_protected_user_meta_key( $this->reference_prefix . $field_name ) ) {
			return null;
		}

		return parent::get_reference( $object_id, $field_name );
	}

	/**
	 * Updates a reference key, unless the key is a protected user meta key.
	 *
	 * @since SCF 6.9.9
	 *
	 * @param integer|string $object_id  The ID of the object the metadata is for.
	 * @param string         $field_name The name of the field to update the reference for.
	 * @param string         $value      The value of the reference key.
	 * @return integer|boolean
	 */
	public function update_reference( $object_id = 0, string $field_name = '', string $value = '' ) {
		if ( scf_is_protected_user_meta_key( $this->reference_prefix . $field_name, (int) $object_id ) ) {
			return false;
		}

		return parent::update_reference( $object_id, $field_name, $value );
	}

	/**
	 * Deletes a reference key, unless the key is a protected user meta key.
	 *
	 * @since SCF 6.9.9
	 *
	 * @param integer|string $object_id  The ID of the object the metadata is for.
	 * @param string         $field_name The name of the field to delete the reference from.
	 * @return boolean
	 */
	public function delete_reference( $object_id = 0, string $field_name = '' ): bool {
		if ( scf_is_protected_user_meta_key( $this->reference_prefix . $field_name, (int) $object_id ) ) {
			return false;
		}

		return parent::delete_reference( $object_id, $field_name );
	}

	/**
	 * Removes protected user meta keys from an array of metadata.
	 *
	 * @since SCF 6.9.9
	 *
	 * @param array   $meta    The metadata, keyed by meta key.
	 * @param integer $user_id Optional. The user the metadata is written to. Default 0.
	 * @return array
	 */
	private function without_protected_keys( array $meta, int $user_id = 0 ): array {
		return array_filter(
			$meta,
			static function ( $name ) use ( $user_id ) {
				return ! scf_is_protected_user_meta_key( $name, $user_id );
			},
			ARRAY_FILTER_USE_KEY
		);
	}
}
