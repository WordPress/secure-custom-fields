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
			// Temporarily disabled - will be enabled when beta feature is ready
			// add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
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
		 * Localizes the beta features data.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		public function localize_beta_features() {
			$beta_features = array();
			foreach ( $this->get_beta_features() as $name => $beta_feature ) {
				$beta_features[ $name ] = $beta_feature->is_enabled();
			}

			acf_localize_data(
				array(
					'betaFeatures' => $beta_features,
				)
			);
		}

		/**
		 * This function will add the SCF beta features menu item to the WP admin
		 *
		 * @type    action (admin_menu)
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		public function admin_menu() {
			// bail early if no show_admin
			if ( ! acf_get_setting( 'show_admin' ) ) {
				return;
			}

			$page = add_submenu_page( 'edit.php?post_type=acf-field-group', __( 'Beta Features', 'secure-custom-fields' ), __( 'Beta Features', 'secure-custom-fields' ), acf_get_setting( 'capability' ), 'scf-beta-features', array( $this, 'html' ) );

			add_action( 'load-' . $page, array( $this, 'load' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'localize_beta_features' ), 20 );
		}

		/**
		 * Loads the admin beta features page.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		public function load() {
			add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
			// Include and register beta features before checking submit
			$this->include_beta_features();

			$this->check_submit();
		}

		/**
		 * Modifies the admin body class.
		 *
		 * @since SCF 6.5.0
		 *
		 * @param string $classes Space-separated list of CSS classes.
		 * @return string
		 */
		public function admin_body_class( $classes ) {
			$classes .= ' acf-admin-page';
			return $classes;
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
			acf_include( 'includes/admin/beta-features/class-scf-beta-feature-editor-sidebar.php' );

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
			scf_register_admin_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );
		}

		/**
		 * Verifies the nonces and submits the value if it passes.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		public function check_submit() {
			// Check if form was submitted.
			if ( ! isset( $_POST['scf_beta_features_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['scf_beta_features_nonce'] ), 'scf_beta_features_update' ) ) {
				return;
			}

			$beta_features = $this->get_beta_features();
			$updated       = false;

			foreach ( $beta_features as $name => $beta_feature ) {
				$enabled = isset( $_POST['scf_beta_features'][ $name ] ) && '1' === $_POST['scf_beta_features'][ $name ];
				$beta_feature->set_enabled( $enabled );
				$updated = true;
			}

			if ( $updated ) {
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}
		}

		/**
		 * Display admin notices.
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		public function admin_notices() {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Beta feature settings updated successfully.', 'secure-custom-fields' ); ?></p>
			</div>
			<?php
		}

		/**
		 * Admin Beta Features html
		 *
		 * @since   SCF 6.5.0
		 *
		 * @return  void
		 */
		public function html() {
			// vars
			$screen = get_current_screen();

			// view
			$view = array(
				'screen_id' => $screen->id,
			);

			foreach ( $this->get_beta_features() as $name => $beta_feature ) {
				add_meta_box( 'scf-admin-beta-feature-' . $name, acf_esc_html( $beta_feature->title ), array( $this, 'metabox_html' ), $screen->id, 'normal', 'default', array( 'beta_feature' => $name ) );
			}

			acf_get_view( 'beta-features/beta-features', $view );
		}

		/**
		 * Output the metabox HTML for specific beta features
		 *
		 * @since SCF 6.5.0
		 *
		 * @param mixed $post    The post this metabox is being displayed on, should be an empty string always for us on an beta features page.
		 * @param array $metabox An array of the metabox attributes.
		 */
		public function metabox_html( $post, $metabox ) {
			$beta_feature = $this->get_beta_feature( $metabox['args']['beta_feature'] );
			$form_attrs   = array( 'method' => 'post' );

			printf( '<form %s>', acf_esc_attrs( $form_attrs ) );
			$beta_feature->html();
			acf_nonce_input( $beta_feature->name );
			echo '</form>';
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
 * This function will return the admin URL to the beta features page
 *
 * @type    function
 * @since   SCF 6.5.0
 *
 * @return  string The URL to the beta features page.
 */
function scf_get_admin_beta_features_url() {
	return admin_url( 'edit.php?post_type=acf-field-group&page=scf-beta-features' );
}
