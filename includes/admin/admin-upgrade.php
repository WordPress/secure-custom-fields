<?php
/**
 * Admin upgrade class.
 *
 * @package wordpress/secure-custom-fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'ACF_Admin_Upgrade' ) ) :
	/**
	 * Class responsible for handling the upgrade process for the admin section
	 * of the SCF plugin.
	 *
	 * This class contains methods and properties that manage the upgrade routines
	 * and ensure that the plugin's data and settings are properly updated when
	 * a new version is installed.
	 */
	class ACF_Admin_Upgrade {

		/**
		 * The name of the transient to store the network update check status.
		 *
		 * @var string
		 */
		public $network_upgrade_needed_transient;

		/**
		 * __construct
		 *
		 * Sets up the class functionality.
		 *
		 * @date    31/7/18
		 * @since   ACF 5.7.2
		 *
		 * @return  void
		 */
		public function __construct() {

			$this->network_upgrade_needed_transient = 'acf_network_upgrade_needed_' . ACF_UPGRADE_VERSION;

			add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
			if ( is_multisite() ) {
				add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ), 20 );
			}
		}

		/**
		 * Function admin_menu
		 *
		 * Setus up logic if DB Upgrade is needed on a single site.
		 *
		 * @date    24/8/18
		 * @since   ACF 5.7.4
		 *
		 * @return  void
		 */
		public function admin_menu() {

			// check if upgrade is avaialble
			if ( acf_has_upgrade() ) {

				// add notice
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );

				// add page
				$page = add_submenu_page( 'index.php', __( 'Upgrade Database', 'secure-custom-fields' ), __( 'Upgrade Database', 'secure-custom-fields' ), acf_get_setting( 'capability' ), 'acf-upgrade', array( $this, 'admin_html' ) );

				// actions
				add_action( 'load-' . $page, array( $this, 'admin_load' ) );
			}
		}

		/**
		 * Displays a “Database Upgrade Required” network admin notice and adds
		 * the “Upgrade Database” submenu under the “Dashboard” network admin
		 * menu item if an ACF upgrade needs to run on any network site.
		 *
		 * @since   ACF 5.7.4
		 * @since   ACF 6.0.0 Reduce memory usage, cache network upgrade checks.
		 */
		public function network_admin_menu() {
			$network_upgrade_needed = get_site_transient( $this->network_upgrade_needed_transient );

			// No transient value exists, so run the upgrade check.
			if ( false === $network_upgrade_needed ) {
				$network_upgrade_needed = $this->check_for_network_upgrades();
			}

			if ( 'no' === $network_upgrade_needed ) {
				return;
			}

			add_action( 'network_admin_notices', array( $this, 'network_admin_notices' ) );

			$page = add_submenu_page(
				'index.php',
				__( 'Upgrade Database', 'secure-custom-fields' ),
				__( 'Upgrade Database', 'secure-custom-fields' ),
				acf_get_setting( 'capability' ),
				'acf-upgrade-network',
				array( $this, 'network_admin_html' )
			);

			add_action( "load-$page", array( $this, 'network_admin_load' ) );
		}

		/**
		 * Checks if an ACF database upgrade is required on any site in the
		 * multisite network.
		 *
		 * Stores the result in `$this->network_upgrade_needed_transient`,
		 * which is version-linked to ACF_UPGRADE_VERSION: the highest ACF
		 * version that requires an upgrade function to run. Bumping
		 * ACF_UPGRADE_VERSION will trigger new upgrade checks but incrementing
		 * ACF_VERSION alone will not.
		 *
		 * @since ACF 6.0.0
		 * @return string 'yes' if any site in the network requires an upgrade,
		 *               otherwise 'no'. String instead of boolean so that
		 *               `false` returned from a get_site_transient check can
		 *               denote that an upgrade check is needed.
		 */
		public function check_for_network_upgrades() {
			$network_upgrade_needed = 'no';

			$sites = get_sites(
				array(
					'number' => 0,
					'fields' => 'ids', // Reduces PHP memory usage.
				)
			);

			if ( $sites ) {
				// Reduces memory usage (same pattern used in wp-includes/ms-site.php).
				remove_action( 'switch_blog', 'wp_switch_roles_and_user', 1 );

				foreach ( $sites as $site_id ) {
					switch_to_blog( $site_id );

					$site_needs_upgrade = acf_has_upgrade();

					restore_current_blog(); // Restores global vars.

					if ( $site_needs_upgrade ) {
						$network_upgrade_needed = 'yes';
						break;
					}
				}

				add_action( 'switch_blog', 'wp_switch_roles_and_user', 1, 2 );
			}

			set_site_transient(
				$this->network_upgrade_needed_transient,
				$network_upgrade_needed,
				3 * MONTH_IN_SECONDS
			);

			return $network_upgrade_needed;
		}

		/**
		 * Function admin_load
		 *
		 * Runs during the loading of the admin page.
		 *
		 * @date    24/8/18
		 * @since   ACF 5.7.4
		 *
		 * @return  void
		 */
		public function admin_load() {

			add_action( 'admin_body_class', array( $this, 'admin_body_class' ) );

			// remove prompt
			remove_action( 'admin_notices', array( $this, 'admin_notices' ) );

			// Enqueue core script.
			acf_enqueue_script( 'acf' );
		}

		/**
		 * Function network_admin_load
		 *
		 * Runs during the loading of the network admin page.
		 *
		 * @date    24/8/18
		 * @since   ACF 5.7.4
		 *
		 * @return  void
		 */
		public function network_admin_load() {

			add_action( 'admin_body_class', array( $this, 'admin_body_class' ) );

			// remove prompt
			remove_action( 'network_admin_notices', array( $this, 'network_admin_notices' ) );

			// Enqueue core script.
			acf_enqueue_script( 'acf' );
		}

		/**
		 * Modifies the admin body class.
		 *
		 * @since ACF 6.0.0
		 *
		 * @param string $classes Space-separated list of CSS classes.
		 * @return string
		 */
		public function admin_body_class( $classes ) {
			$classes .= ' acf-admin-page';
			return $classes;
		}

		/**
		 * Function admin_notices
		 *
		 * Displays the DB Upgrade prompt.
		 *
		 * @date    23/8/18
		 * @since   ACF 5.7.3
		 *
		 * @return  void
		 */
		public function admin_notices() {

			// vars
			$view = array(
				'button_text' => __( 'Upgrade Database', 'secure-custom-fields' ),
				'button_url'  => admin_url( 'index.php?page=acf-upgrade' ),
				'confirm'     => true,
			);

			// view
			acf_get_view( 'upgrade/notice', $view );
		}

		/**
		 * Function network_admin_notices
		 *
		 * Displays the DB Upgrade prompt on a multi site.
		 *
		 * @date    23/8/18
		 * @since   ACF 5.7.3
		 *
		 * @return  void
		 */
		public function network_admin_notices() {

			// vars
			$view = array(
				'button_text' => __( 'Review sites & upgrade', 'secure-custom-fields' ),
				'button_url'  => network_admin_url( 'index.php?page=acf-upgrade-network' ),
				'confirm'     => false,
			);

			// view
			acf_get_view( 'upgrade/notice', $view );
		}

		/**
		 * Function admin_html
		 *
		 * Displays the HTML for the admin page.
		 *
		 * @date    24/8/18
		 * @since   ACF 5.7.4
		 *
		 * @return  void
		 */
		public function admin_html() {
			acf_get_view( 'upgrade/upgrade' );
		}

		/**
		 * Function network_admin_html
		 *
		 * Displays the HTML for the network upgrade admin page.
		 *
		 * @date    24/8/18
		 * @since   ACF 5.7.4
		 *
		 * @return  void
		 */
		public function network_admin_html() {
			acf_get_view( 'upgrade/network' );
		}
	}

	// instantiate
	acf_new_instance( 'ACF_Admin_Upgrade' );
endif; // class_exists check
