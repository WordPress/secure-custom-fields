<?php
/**
 * Options Page Class
 *
 * Handles the creation and management of global options pages.
 *
 * @package wordpress/secure-custom-fields
 */

 // phpcs:disable PEAR.NamingConventions.ValidClassName
 // phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- @todo
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'acf_options_page' ) ) :

	/**
	 * Manages the creation and configuration of WordPress admin options pages.
	 */
	class acf_options_page {

		/**
		 * Storage for registered options pages.
		 *
		 * @var array $pages Contains an array of options page settings.
		 */
		public $pages = array();


		/**
		 * Empty constructor.
		 *
		 * @return  void
		 */
		public function __construct() {

			/* do nothing */
		}

		/**
		 * Validates an Options Page settings array.
		 *
		 * @date    28/2/17
		 * @since   ACF 5.5.8
		 *
		 * @param   array|string $page The Options Page settings array or name.
		 * @return  array
		 */
		public function validate_page( $page ) {

			// Allow empty arg to generate the default Options Page.
			if ( empty( $page ) ) {
				$page_title = __( 'Options', 'secure-custom-fields' );
				$page       = array(
					'page_title' => $page_title,
					'menu_title' => $page_title,
					'menu_slug'  => 'acf-options',
				);

				// Allow string to define Options Page name.
			} elseif ( is_string( $page ) ) {
				$page_title = $page;
				$page       = array(
					'page_title' => $page_title,
					'menu_title' => $page_title,
				);
			}

			// Apply defaults.
			$page = wp_parse_args(
				$page,
				array(
					'page_title'      => '',
					'menu_title'      => '',
					'menu_slug'       => '',
					'capability'      => 'edit_posts',
					'parent_slug'     => '',
					'position'        => null,
					'icon_url'        => false,
					'redirect'        => true,
					'post_id'         => 'options',
					'autoload'        => false,
					'update_button'   => __( 'Update', 'secure-custom-fields' ),
					'updated_message' => __( 'Options Updated', 'secure-custom-fields' ),
				)
			);

			// Allow compatibility for changed settings.
			$migrate = array(
				'title'  => 'page_title',
				'menu'   => 'menu_title',
				'slug'   => 'menu_slug',
				'parent' => 'parent_slug',
			);
			foreach ( $migrate as $old => $new ) {
				if ( ! empty( $page[ $old ] ) ) {
					$page[ $new ] = $page[ $old ];
				}
			}

			// If no menu_title is set, use the page_title value.
			if ( empty( $page['menu_title'] ) ) {
				$page['menu_title'] = $page['page_title'];
			}

			// If no menu_slug is set, generate one using the menu_title value.
			if ( empty( $page['menu_slug'] ) ) {
				$page['menu_slug'] = 'acf-options-' . sanitize_title( $page['menu_title'] );
			}

			// Standardize on position being either null or int.
			$page['position'] = is_numeric( $page['position'] ) ? (int) $page['position'] : null;

			/**
			 * Filters the $page array after it has been validated.
			 *
			 * @since   ACF 5.5.8
			 * @param   array $page The Options Page settings array.
			 */
			return apply_filters( 'acf/validate_options_page', $page );
		}


		/**
		 * This function will store an options page settings
		 *
		 * @type    function
		 * @date    9/6/17
		 * @since   ACF 5.6.0
		 *
		 * @param   array $page The options page settings array.
		 * @return  array|bool The page settings array or false if already exists.
		 */
		public function add_page( $page ) {

			// validate
			$page = $this->validate_page( $page );
			$slug = $page['menu_slug'];

			// bail early if already exists
			if ( isset( $this->pages[ $slug ] ) ) {
				return false;
			}

			// append
			$this->pages[ $slug ] = $page;

			// return
			return $page;
		}


		/**
		 * Adds a sub page to an existing options page.
		 *
		 * @type    function
		 * @date    9/6/17
		 * @since   ACF 5.6.0
		 *
		 * @param   array $page The options sub page settings array.
		 * @return  array|bool The page settings array or false if parent doesn't exist.
		 */
		public function add_sub_page( $page ) {

			// validate
			$page = $this->validate_page( $page );

			// default parent
			if ( ! $page['parent_slug'] ) {
				$page['parent_slug'] = 'acf-options';
			}

			// create default parent if not yet exists
			if ( 'acf-options' === $page['parent_slug'] && ! $this->get_page( 'acf-options' ) ) {
				$this->add_page( '' );
			}

			// return
			return $this->add_page( $page );
		}


		/**
		 * Updates an existing options page settings.
		 *
		 * @type    function
		 * @date    9/6/17
		 * @since   ACF 5.6.0
		 *
		 * @param   string $slug The menu slug of the options page.
		 * @param   array  $data Array of options page settings to update.
		 * @return  array|bool The updated page settings array or false if page not found.
		 */
		public function update_page( $slug = '', $data = array() ) {

			// vars
			$page = $this->get_page( $slug );

			// bail early if no page
			if ( ! $page ) {
				return false;
			}

			// loop
			$page = array_merge( $page, $data );

			// set
			$this->pages[ $slug ] = $page;

			// return
			return $page;
		}


		/**
		 * Returns an options page settings array by slug.
		 *
		 * @type    function
		 * @date    6/07/2016
		 * @since   ACF 5.4.0
		 *
		 * @param   string $slug The menu slug of the options page.
		 * @return  array|null The options page settings array or null if not found.
		 */
		public function get_page( $slug ) {

			return isset( $this->pages[ $slug ] ) ? $this->pages[ $slug ] : null;
		}


		/**
		 * Returns all registered options page settings.
		 *
		 * @type    function
		 * @date    6/07/2016
		 * @since   ACF 5.4.0
		 *
		 * @return  array Array of all registered options pages.
		 */
		public function get_pages() {

			return $this->pages;
		}
	}


	/*
	 * acf_options_page
	 *
	 * This function will return the options page instance
	 *
	 * @type    function
	 * @date    9/6/17
	 * @since   ACF 5.6.0
	 *
	 * @param   n/a
	 * @return  (object)
	 */

	/**
	 * Returns the options page instance.
	 *
	 * @type    function
	 * @date    9/6/17
	 * @since   ACF 5.6.0
	 *
	 * @return  object The options page instance.
	 */
	function acf_options_page() {

		global $acf_options_page;

		if ( ! isset( $acf_options_page ) ) {
			$acf_options_page = new acf_options_page();
		}

		return $acf_options_page;
	}


	// remove Options Page add-on conflict
	unset( $GLOBALS['acf_options_page'] );


	// initialize
	acf_options_page();
endif; // class_exists check


if ( ! function_exists( 'acf_add_options_page' ) ) :
	/**
	 * Alias of acf_options_page()->add_page()
	 *
	 * @type    function
	 * @date    24/02/2014
	 * @since   ACF 5.0.0
	 *
	 * @param   mixed $page The options page settings.
	 * @return  array The page settings array.
	 */
	function acf_add_options_page( $page = '' ) {
		return acf_options_page()->add_page( $page );
	}

endif;


if ( ! function_exists( 'acf_add_options_sub_page' ) ) :

	/**
	 * Adds a sub page to an existing options page.
	 *
	 * @type    function
	 * @date    24/02/2014
	 * @since   ACF 5.0.0
	 *
	 * @param   mixed $page The options sub page settings.
	 * @return  array The page settings array.
	 */
	function acf_add_options_sub_page( $page = '' ) {

		return acf_options_page()->add_sub_page( $page );
	}

endif;


if ( ! function_exists( 'acf_update_options_page' ) ) :

	/**
	 * Alias of acf_options_page()->update_page()
	 *
	 * @type    function
	 * @date    24/02/2014
	 * @since   ACF 5.0.0
	 *
	 * @param   string $slug The menu slug of the options page.
	 * @param   array  $data Array of options page settings to update.
	 * @return  array The updated page settings array.
	 */
	function acf_update_options_page( $slug = '', $data = array() ) {

		return acf_options_page()->update_page( $slug, $data );
	}

endif;

if ( ! function_exists( 'acf_get_options_page' ) ) :
	/**
	 * Returns an options page settings array.
	 *
	 * @type    function
	 * @date    24/02/2014
	 * @since   ACF 5.0.0
	 *
	 * @param   string $slug The menu slug of the options page.
	 * @return  array|bool The options page settings array or false if not found.
	 */
	function acf_get_options_page( $slug ) {

		// vars
		$page = acf_options_page()->get_page( $slug );

		// bail early if no page
		if ( ! $page ) {
			return false;
		}

		// filter
		$page = apply_filters( 'acf/get_options_page', $page, $slug );

		// return
		return $page;
	}

endif;

if ( ! function_exists( 'acf_get_options_pages' ) ) :
	/**
	 * This function will return all options page settings
	 *
	 * @type    function
	 * @date    24/02/2014
	 * @since   ACF 5.0.0
	 *
	 * @return  array|bool The options page settings array or false if no pages are registered.
	 */
	function acf_get_options_pages() {

		// global
		global $_wp_last_utility_menu;

		// vars
		$pages = acf_options_page()->get_pages();

		// bail early if no pages
		if ( empty( $pages ) ) {
			return false;
		}

		// apply filter to each page
		foreach ( $pages as $slug => &$page ) {
			$page = acf_get_options_page( $slug );
		}

		// calculate parent => child redirectes
		foreach ( $pages as $slug => &$page ) {

			// bail early if is child
			if ( $page['parent_slug'] ) {
				continue;
			}

			// add missing position
			if ( ! $page['position'] ) {
				++$_wp_last_utility_menu;
				$page['position'] = $_wp_last_utility_menu;
			}

			// bail early if no redirect
			if ( ! $page['redirect'] ) {
				continue;
			}

			// vars
			$parent = $page['menu_slug'];
			$child  = '';

			// update children
			foreach ( $pages as &$sub_page ) {

				// bail early if not child of this parent
				if ( $sub_page['parent_slug'] !== $parent ) {
					continue;
				}

				// set child (only once)
				if ( ! $child ) {
					$child = $sub_page['menu_slug'];
				}

				// update parent_slug to the first child
				$sub_page['parent_slug'] = $child;
			}

			// finally update parent menu_slug
			if ( $child ) {
				$page['_menu_slug'] = $page['menu_slug'];
				$page['menu_slug']  = $child;
			}
		}

		// filter
		$pages = apply_filters( 'acf/get_options_pages', $pages );

		// return
		return $pages;
	}

endif;



if ( ! function_exists( 'acf_set_options_page_title' ) ) :

	/**
	 * This function is used to customize the options page admin menu title
	 *
	 * @type    function
	 * @date    13/07/13
	 * @since   ACF 4.0.0
	 *
	 * @param   string $title The title of the options page.
	 * @return  void
	 */
	function acf_set_options_page_title( $title = 'Options' ) {

		acf_update_options_page(
			'acf-options',
			array(
				'page_title' => $title,
				'menu_title' => $title,
			)
		);
	}

endif;



if ( ! function_exists( 'acf_set_options_page_menu' ) ) :
	/**
	 * This function is used to customize the options page admin menu name
	 *
	 * @type    function
	 * @date    13/07/13
	 * @since   ACF 4.0.0
	 *
	 * @param   string $title The title of the options page.
	 * @return  void
	 */
	function acf_set_options_page_menu( $title = 'Options' ) {

		acf_update_options_page(
			'acf-options',
			array(
				'menu_title' => $title,
			)
		);
	}

endif;



if ( ! function_exists( 'acf_set_options_page_capability' ) ) :
	/**
	 * This function is used to customize the options page capability. Defaults to 'edit_posts'
	 *
	 * @type    function
	 * @date    13/07/13
	 * @since   ACF 4.0.0
	 *
	 * @param   string $capability The capability of the options page.
	 * @return  void
	 */
	function acf_set_options_page_capability( $capability = 'edit_posts' ) {

		acf_update_options_page(
			'acf-options',
			array(
				'capability' => $capability,
			)
		);
	}

endif;



if ( ! function_exists( 'register_options_page' ) ) :
	/**
	 * This is an old function which is now referencing the new 'acf_add_options_sub_page' function
	 *
	 * @type    function
	 * @since   ACF 3.0.0
	 * @date    29/01/13
	 *
	 * @param   string $page The options sub page settings array.
	 * @return  void
	 */
	function register_options_page( $page = '' ) {

		acf_add_options_sub_page( $page );
	}

endif;
