<?php
/**
 * Test helper that exposes the protected schema helpers of AbstractAbilityGroup.
 *
 * @package wordpress/secure-custom-fields
 */

use SCF\AI\Abilities\AbstractAbilityGroup;

/**
 * Exposes the protected schema helpers of AbstractAbilityGroup.
 */
class Test_SCF_AI_Schema_Ability_Group extends AbstractAbilityGroup {

	/**
	 * Registers nothing.
	 *
	 * @return void
	 */
	public function register_abilities() {}

	/**
	 * Public wrapper for normalize_schema().
	 *
	 * @param array $schema The schema.
	 * @return array
	 */
	public function public_normalize_schema( array $schema ): array {
		return $this->normalize_schema( $schema );
	}

	/**
	 * Public wrapper for prepare_ability_args().
	 *
	 * @param array $args The ability args.
	 * @return array
	 */
	public function public_prepare_ability_args( array $args ): array {
		return $this->prepare_ability_args( $args );
	}

	/**
	 * Public wrapper for get_field_value_schema().
	 *
	 * @param array $field The formatted field.
	 * @return array
	 */
	public function public_get_field_value_schema( array $field ): array {
		return $this->get_field_value_schema( $field );
	}
}
