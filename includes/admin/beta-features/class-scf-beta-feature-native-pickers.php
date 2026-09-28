<?php
/**
 * Native Pickers Beta Feature
 *
 * This beta feature replaces the JavaScript date, time and color pickers with
 * the native HTML inputs provided by the browser.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.9.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Native_Pickers' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Native_Pickers
	 *
	 * Uses the browser's own date, time and color inputs instead of the bundled
	 * jQuery UI and Iris pickers.
	 *
	 * @package    Secure Custom Fields
	 * @since      SCF 6.9.6
	 */
	class SCF_Admin_Beta_Feature_Native_Pickers extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @since SCF 6.9.6
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'native_pickers';
			$this->title       = __( 'Native Date, Time and Color Inputs', 'secure-custom-fields' );
			$this->description = __( 'Uses the date, time and color inputs built into the browser instead of the bundled JavaScript pickers. The display format of date and time fields is then controlled by the browser. Fields using transparency, a custom color palette or a custom save format keep the existing pickers.', 'secure-custom-fields' );
		}
	}
endif;
