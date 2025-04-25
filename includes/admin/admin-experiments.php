<?php // phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
/**
 * Admin Experiments
 *
 * This file contains the admin experiments functionality for Secure Custom Fields.
 *
 * @package    Secure Custom Fields
 * @since      6.4.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Experiments' ) ) :
	/**
	 * Class SCF_Admin_Experiments
	 *
	 * This class provides different experiments that eventually will land on secure custom fields.
	 */
	class SCF_Admin_Experiments {

		/**
		 * Contains an array of admin experiment instances.
		 *
		 * @var array
		 */
		private $experiments = array();

		/**
		 * The active experiment.
		 *
		 * @var string
		 */
		private $active = '';

		/**
		 * This function will setup the class functionality
		 *
		 * @since   SCF 6.4.2
		 *
		 * @return  void
		 */
		public function __construct() {
			// Temporarily disabled - will be enabled when experiments feature is ready
			// add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		}

		/**
		 * This function will store an experiment class instance in the experiments array.
		 *
		 * @since   SCF 6.4.2
		 *
		 * @param   string $experiment Class name.
		 * @return  void
		 */
		public function register_experiment( $experiment ) {
			$instance                             = new $experiment();
			$this->experiments[ $instance->name ] = $instance;
		}

		/**
		 * This function will return an experiment class or null if not found.
		 *
		 * @since   SCF 6.4.2
		 *
		 * @param   string $name Name of experiment.
		 * @return  mixed (SCF_Admin_Experiment|null)
		 */
		public function get_experiment( $name ) {
			return isset( $this->experiments[ $name ] ) ? $this->experiments[ $name ] : null;
		}

		/**
		 * This function will return an array of all experiment instances.
		 *
		 * @since   SCF 6.4.2
		 *
		 * @return  array
		 */
		public function get_experiments() {
			return $this->experiments;
		}

		/**
		 * This function will add the SCF experiments menu item to the WP admin
		 *
		 * @type    action (admin_menu)
		 * @since   SCF 6.4.2
		 *
		 * @return  void
		 */
		public function admin_menu() {
			// bail early if no show_admin
			if ( ! acf_get_setting( 'show_admin' ) ) {
				return;
			}

			$page = add_submenu_page( 'edit.php?post_type=acf-field-group', __( 'Experiments', 'secure-custom-fields' ), __( 'Experiments', 'secure-custom-fields' ), acf_get_setting( 'capability' ), 'scf-experiments', array( $this, 'html' ) );

			add_action( 'load-' . $page, array( $this, 'load' ) );
		}

		/**
		 * Loads the admin experiments page.
		 *
		 * @since   SCF 6.4.2
		 *
		 * @return  void
		 */
		public function load() {
			add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

			// disable filters (default to raw data)
			acf_disable_filters();

			// Temporarily disabled - will be enabled when experiments feature is ready
			// $this->include_experiments();

			$this->check_submit();
			// Temporarily disabled - Localize experiments data. Uncomment to enable in production when ready.
			// $this->enqueue_and_localize_experiments_script();
		}

		/**
		 * Enqueues the experiments JavaScript file and localizes data.
		 *
		 * @since   SCF 6.4.2
		 *
		 * @return  void
		 */
		public function enqueue_and_localize_experiments_script() {
			acf_enqueue_scripts();
			acf_enqueue_script( 'acf-experiments' );
			$experiments_data = array();
			foreach ( $this->get_experiments() as $name => $experiment ) {
				$experiments_data[ $name ] = $experiment->is_enabled();
			}

			wp_localize_script(
				'acf-experiments',
				'acfExperiments',
				array(
					'data' => $experiments_data,
				)
			);
		}

		/**
		 * Modifies the admin body class.
		 *
		 * @since SCF 6.4.2
		 *
		 * @param string $classes Space-separated list of CSS classes.
		 * @return string
		 */
		public function admin_body_class( $classes ) {
			$classes .= ' acf-admin-page';
			return $classes;
		}

		/**
		 * Includes various experiment-related files.
		 *
		 * @since   SCF 6.4.2
		 *
		 * @return  void
		 */
		public function include_experiments() {
			acf_include( 'includes/admin/experiments/class-scf-admin-experiment.php' );
			acf_include( 'includes/admin/experiments/class-scf-admin-experiment-editor-sidebar.php' );

			add_action( 'scf/include_admin_experiments', array( $this, 'register_experiments' ) );

			do_action( 'scf/include_admin_experiments' );
		}

		/**
		 * Register default experiments.
		 *
		 * @since   SCF 6.4.2
		 *
		 * @return  void
		 */
		public function register_experiments() {
			scf_register_admin_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );
		}

		/**
		 * Verifies the nonces and submits the value if it passes.
		 *
		 * @since   SCF 6.4.2
		 *
		 * @return  void
		 */
		public function check_submit() {
			// Check if form was submitted.
			if ( ! isset( $_POST['scf_experiments_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['scf_experiments_nonce'] ), 'scf_experiments_update' ) ) {
				return;
			}

			$experiments = $this->get_experiments();
			$updated     = false;

			foreach ( $experiments as $experiment ) {
				$enabled = isset( $_POST['scf_experiments'][ $experiment->name ] );
				if ( $experiment->is_enabled() !== $enabled ) {
					$experiment->set_enabled( $enabled );
					$updated = true;
				}
			}

			if ( $updated ) {
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}
		}

		/**
		 * Display admin notices.
		 *
		 * @since   SCF 6.4.2
		 *
		 * @return  void
		 */
		public function admin_notices() {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Experiment settings updated successfully.', 'secure-custom-fields' ); ?></p>
			</div>
			<?php
		}

		/**
		 * Admin Experiments html
		 *
		 * @since   SCF 6.4.2
		 *
		 * @return  void
		 */
		public function html() {
			// vars
			$screen = get_current_screen();
			$active = acf_maybe_get_GET( 'experiment' );

			// view
			$view = array(
				'screen_id' => $screen->id,
				'active'    => $active,
			);

			foreach ( $this->get_experiments() as $experiment ) {
				if ( $active && $active !== $experiment->name ) {
					continue;
				}

				add_meta_box( 'scf-admin-experiment-' . $experiment->name, acf_esc_html( $experiment->title ), array( $this, 'metabox_html' ), $screen->id, 'normal', 'default', array( 'experiment' => $experiment->name ) );
			}

			acf_get_view( 'experiments/experiments', $view );
		}

		/**
		 * Output the metabox HTML for specific experiments
		 *
		 * @since SCF 6.4.2
		 *
		 * @param mixed $post    The post this metabox is being displayed on, should be an empty string always for us on an experiments page.
		 * @param array $metabox An array of the metabox attributes.
		 */
		public function metabox_html( $post, $metabox ) {
			$experiment = $this->get_experiment( $metabox['args']['experiment'] );
			$form_attrs = array( 'method' => 'post' );

			printf( '<form %s>', acf_esc_attrs( $form_attrs ) );
			$experiment->html();
			acf_nonce_input( $experiment->name );
			echo '</form>';
		}
	}

	// initialize
	acf()->admin_experiments = new SCF_Admin_Experiments();
endif; // class_exists check

/**
 * Alias of acf()->admin_experiments->register_experiment()
 *
 * @type    function
 * @date    31/5/17
 * @since   SCF 6.4.2
 *
 * @param   string $experiment The experiment class.
 * @return  void
 */
function scf_register_admin_experiment( $experiment ) {
	acf()->admin_experiments->register_experiment( $experiment );
}

/**
 * This function will return the admin URL to the experiments page
 *
 * @type    function
 * @date    31/5/17
 * @since   SCF 6.4.2
 *
 * @return  string The URL to the experiments page.
 */
function scf_get_admin_experiments_url() {
	return admin_url( 'edit.php?post_type=acf-field-group&page=scf-experiments' );
}

/**
 * This function will return the admin URL to a specific experiment page
 *
 * @type    function
 * @date    31/5/17
 * @since   SCF 6.4.2
 *
 * @param   string $experiment The experiment name.
 * @return  string The URL to a particular experiment's page.
 */
function scf_get_admin_experiment_url( $experiment = '' ) {
	return scf_get_admin_experiments_url() . '&experiment=' . $experiment;
}
