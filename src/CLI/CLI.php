<?php
/**
 * ACF 6.8.0 feature port.
 *
 * @package wordpress/secure-custom-fields
 */

// phpcs:disable -- Upstream ACF 6.8.0 feature-port files are kept close to source.

namespace SCF\CLI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Bootstrapper for ACF WP-CLI commands.
 */
class CLI {

	/**
	 * Registers all free ACF WP-CLI commands.
	 *
	 * @since 6.8
	 */
	public function __construct() {
		\WP_CLI::add_command( 'acf json', JsonCommand::class );
		\WP_CLI::add_command( 'scf json', JsonCommand::class );
	}
}
