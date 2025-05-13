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
}
