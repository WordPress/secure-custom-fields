<?php
/**
 * Admin Experiments Functions
 *
 * This file contains helper functions for the admin experiments functionality.
 *
 * @package    Secure Custom Fields
 * @subpackage Admin
 * @since      6.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

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
