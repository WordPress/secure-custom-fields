<?php
/**
 * ACF Data Class
 *
 * Handles data storage and retrieval with support for aliases and multisite.
 *
 * @package wordpress/secure-custom-fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'ACF_Data' ) ) :
	#[AllowDynamicProperties]
	/**
	 * ACF Data Class
	 *
	 * Provides a flexible data storage system with support for aliases and multisite.
	 *
	 * @since   ACF 5.7.10
	 */
	class ACF_Data {

		/**
		 * Unique identifier.
		 *
		 * @var string Unique identifier for this instance.
		 */
		public $cid = '';

		/**
		 * Data storage.
		 *
		 * @var array Storage for data values.
		 */
		public $data = array();

		/**
		 * Data aliases.
		 *
		 * @var array Storage for data key aliases.
		 */
		public $aliases = array();

		/**
		 * Site-specific data storage.
		 *
		 * @var array Storage for data values per site.
		 */
		public $site_data = array();

		/**
		 * Site-specific aliases storage.
		 *
		 * @var array Storage for data key aliases per site.
		 */
		public $site_aliases = array();

		/**
		 * Multisite support.
		 *
		 * @var boolean Whether to enable unique data per site.
		 */
		public $multisite = false;

		/**
		 * __construct
		 *
		 * Sets up the class functionality.
		 *
		 * @date    9/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   array $data Optional data to set.
		 * @return  void
		 */
		public function __construct( $data = false ) {

			// Set cid.
			$this->cid = acf_uniqid();

			// Set data.
			if ( $data ) {
				$this->set( $data );
			}

			// Initialize.
			$this->initialize();
		}

		/**
		 * Initialize
		 *
		 * Called during constructor to setup class functionality.
		 *
		 * @date    9/1/19
		 * @since   ACF 5.7.10
		 *
		 * @return  void
		 */
		public function initialize() {
			// Do nothing.
		}

		/**
		 * Prop
		 *
		 * Sets a property for the given name and returns $this for chaining.
		 *
		 * @date    9/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   (string|array) $name  The data name or an array of data.
		 * @param   mixed          $value The data value.
		 * @return  ACF_Data
		 */
		public function prop( $name = '', $value = null ) {

			// Update property.
			$this->{$name} = $value;

			// Return this for chaining.
			return $this;
		}

		/**
		 * Key
		 *
		 * Returns a key for the given name allowing aliases to work.
		 *
		 * @date    18/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   string $name The name to get key for.
		 * @return  string The key for the given name.
		 */
		public function _key( $name = '' ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
			return isset( $this->aliases[ $name ] ) ? $this->aliases[ $name ] : $name;
		}

		/**
		 * Has
		 *
		 * Returns true if this has data for the given name.
		 *
		 * @date    9/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   string $name The data name.
		 * @return  boolean
		 */
		public function has( $name = '' ) {
			$key = $this->_key( $name );
			return isset( $this->data[ $key ] );
		}

		/**
		 * Is
		 *
		 * Similar to has() but does not check aliases.
		 *
		 * @date    7/2/19
		 * @since   ACF 5.7.11
		 *
		 * @param   string $key The key to check.
		 * @return  boolean True if key exists.
		 */
		public function is( $key = '' ) {
			return isset( $this->data[ $key ] );
		}

		/**
		 * Get
		 *
		 * Returns data for the given name of null if doesn't exist.
		 *
		 * @date    9/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   string $name The data name.
		 * @return  mixed
		 */
		public function get( $name = false ) {

			// Get all.
			if ( false === $name ) {
				return $this->data;

				// Get specific.
			} else {
				$key = $this->_key( $name );
				return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
			}
		}

		/**
		 * Get Data
		 *
		 * Returns an array of all data.
		 *
		 * @date    9/1/19
		 * @since   ACF 5.7.10
		 *
		 * @return  array
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Set
		 *
		 * Sets data for the given name and returns $this for chaining.
		 *
		 * @date    9/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   (string|array) $name  The data name or an array of data.
		 * @param   mixed          $value The data value.
		 * @return  ACF_Data
		 */
		public function set( $name = '', $value = null ) {

			// Set multiple.
			if ( is_array( $name ) ) {
				$this->data = array_merge( $this->data, $name );

				// Set single.
			} else {
				$this->data[ $name ] = $value;
			}

			// Return this for chaining.
			return $this;
		}

		/**
		 * Append
		 *
		 * Appends data for the given name and returns $this for chaining.
		 *
		 * @date    9/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   mixed $value The data value.
		 * @return  ACF_Data
		 */
		public function append( $value = null ) {

			// Append.
			$this->data[] = $value;

			// Return this for chaining.
			return $this;
		}

		/**
		 * Remove
		 *
		 * Removes data for the given name.
		 *
		 * @date    9/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   string $name The data name.
		 * @return  ACF_Data
		 */
		public function remove( $name = '' ) {

			// Remove data.
			unset( $this->data[ $name ] );

			// Return this for chaining.
			return $this;
		}

		/**
		 * Reset
		 *
		 * Resets the data.
		 *
		 * @date    22/1/19
		 * @since   ACF 5.7.10
		 *
		 * @return  void
		 */
		public function reset() {
			$this->data    = array();
			$this->aliases = array();
		}

		/**
		 * Count
		 *
		 * Returns the data count.
		 *
		 * @date    23/1/19
		 * @since   ACF 5.7.10
		 *
		 * @return  integer
		 */
		public function count() {
			return count( $this->data );
		}

		/**
		 * Query
		 *
		 * Returns a filtered array of data based on the set of key => value arguments.
		 *
		 * @date    23/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   array  $args     The query arguments.
		 * @param   string $operator The logical operator. Accepts 'AND' or 'OR'.
		 * @return  array
		 */
		public function query( $args, $operator = 'AND' ) {
			return wp_list_filter( $this->data, $args, $operator );
		}

		/**
		 * Alias
		 *
		 * Sets an alias for the given name allowing data to be found via multiple identifiers.
		 *
		 * @date    18/1/19
		 * @since   ACF 5.7.10
		 *
		 * @param   string $name     The name to create aliases for.
		 * @param   string ...$args  Additional aliases to map to the name.
		 * @return  ACF_Data
		 */
		public function alias( $name = '', ...$args ) {
			// Loop over aliases and add to data.
			foreach ( $args as $alias ) {
				$this->aliases[ $alias ] = $name;
			}

			// Return this for chaining.
			return $this;
		}

		/**
		 * Switch Site
		 *
		 * Triggered when switching between sites on a multisite installation.
		 *
		 * @date    13/2/19
		 * @since   ACF 5.7.11
		 *
		 * @param   integer $site_id      New blog ID.
		 * @param   integer $prev_site_id Previous blog ID.
		 * @return  void
		 */
		public function switch_site( $site_id, $prev_site_id ) {

			// Bail early if not multisite compatible.
			if ( ! $this->multisite ) {
				return;
			}

			// Bail early if no change in blog ID.
			if ( $site_id === $prev_site_id ) {
				return;
			}

			// Create storage.
			if ( ! isset( $this->site_data ) ) {
				$this->site_data    = array();
				$this->site_aliases = array();
			}

			// Save state.
			$this->site_data[ $prev_site_id ]    = $this->data;
			$this->site_aliases[ $prev_site_id ] = $this->aliases;

			// Reset state.
			$this->data    = array();
			$this->aliases = array();

			// Load state.
			if ( isset( $this->site_data[ $site_id ] ) ) {
				$this->data    = $this->site_data[ $site_id ];
				$this->aliases = $this->site_aliases[ $site_id ];
				unset( $this->site_data[ $site_id ] );
				unset( $this->site_aliases[ $site_id ] );
			}
		}
	}

endif; // class_exists check
