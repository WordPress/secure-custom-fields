<?php
/**
 * A custom meta location helper class used by the meta location tests.
 *
 * @package wordpress/secure-custom-fields
 */

/**
 * A custom meta location used to test self-registration.
 */
class SCF_Test_Custom_Meta_Location extends \SCF\Meta\MetaLocation {

	/**
	 * The unique slug/name of the meta location.
	 *
	 * @var string
	 */
	public string $location_type = 'scf_test_location';

	/**
	 * The prefix to use for SCF reference keys/hidden meta.
	 *
	 * @var string
	 */
	public string $reference_prefix = '_ref_';
}
