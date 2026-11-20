<?php // phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
/**
 * Admin Beta Features
 *
 * This file contains the admin beta features functionality for Secure Custom Fields.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Features' ) ) :
	/**
	 * Class SCF_Admin_Beta_Features
	 *
	 * This class provides different beta features that eventually will land on secure custom fields.
	 *
	 * The Beta Features admin page was removed in SCF 6.9.10, and its URL redirects to the Settings page.
	 * Beta features keep their stored "scf_beta_feature_{$name}_enabled" options.
	 */
	class SCF_Admin_Beta_Features {

		/**
		 * Contains an array of admin beta feature instances.
		 *
		 * @var array
		 */
		private $beta_features = array();

		/**
		 * This function will setup the class functionality
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'add_beta_features_script' ), 20 );
		}

		/**
		 * This function will store an beta feature class instance in the beta features array.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @param   string $beta_feature Class name.
		 * @return  void
		 */
		public function register_beta_feature( $beta_feature ) {
			if ( ! class_exists( $beta_feature ) ) {
				return;
			}

			$instance                               = new $beta_feature();
			$this->beta_features[ $instance->name ] = $instance;
		}

		/**
		 * This function will return an beta feature class or null if not found.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @param   string $name Name of beta feature.
		 * @return  mixed (SCF_Admin_Beta_Feature|null)
		 */
		public function get_beta_feature( $name ) {
			return isset( $this->beta_features[ $name ] ) ? $this->beta_features[ $name ] : null;
		}

		/**
		 * This function will return an array of all beta feature instances.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  array
		 */
		public function get_beta_features() {
			// Include beta features
			$this->include_beta_features();

			return $this->beta_features;
		}

		/**
		 * Includes various beta feature-related files.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		private function include_beta_features() {
			acf_include( 'includes/admin/beta-features/class-scf-beta-feature.php' );
			acf_include( 'includes/admin/beta-features/class-scf-beta-feature-connect-fields.php' );

			add_action( 'scf/include_admin_beta_features', array( $this, 'register_beta_features' ) );

			do_action( 'scf/include_admin_beta_features' );
		}

		/**
		 * Register default beta features.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		public function register_beta_features() {
		}

		/**
		 * Adds the editor sidebar script to the page.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		public function add_beta_features_script() {
			// Bail early if no show_admin.
			if ( ! acf_get_setting( 'show_admin' ) ) {
				return;
			}

			$script = 'window.scf = window.scf || {};
window.scf.betaFeatures = window.scf.betaFeatures || {};';
			foreach ( $this->get_beta_features() as $name => $beta_feature ) {
				if ( $beta_feature->is_enabled() ) {
					$script .= sprintf( 'window.scf.betaFeatures.%s = true;', esc_js( $name ) );
				}
			}

			wp_add_inline_script( 'wp-block-editor', $script, 'before' );
		}
	}

	// initialize
	acf()->admin_beta_features = new SCF_Admin_Beta_Features();
endif; // class_exists check

/**
 * Alias of acf()->admin_beta_features->register_beta_feature()
 *
 * @type    function
 * @since   SCF 6.5.0
 *
 * @param   string $beta_feature The beta feature class.
 * @return  void
 */
function scf_register_admin_beta_feature( $beta_feature ) {
	acf()->admin_beta_features->register_beta_feature( $beta_feature );
}

/**
 * This function will return the admin URL where beta features are managed.
 *
 * @type    function
 * @since   SCF 6.5.0
 * @since   SCF 6.9.10 Returns the Settings page URL, as the Beta Features page was removed.
 *
 * @return  string The URL to the settings page.
 */
function scf_get_admin_beta_features_url() {
	return admin_url( 'edit.php?post_type=acf-field-group&page=acf-settings-tab-general' );
}
