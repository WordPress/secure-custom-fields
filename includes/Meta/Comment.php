<?php
/**
 * Adds support for saving/retrieving values from comment meta.
 *
 * @package    SCF
 * @since      6.5
 * @subpackage Meta
 */

namespace SCF\Meta;

/**
 * A class to add support for saving to comment meta.
 */
class Comment extends MetaLocation {

	/**
	 * The unique slug/name of the meta location.
	 *
	 * @var string
	 */
	public string $location_type = 'comment';
}
