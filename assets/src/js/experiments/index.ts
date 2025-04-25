/**
 * Admin experiments.
 *
 * @package    Secure Custom Fields
 * @since      6.4.3
 */

import type { ExperimentsObject, ACF, ExperimentsData } from './types';

// Declare global variables that are defined elsewhere
declare const acf: ACF;
declare const acfExperiments: { data: ExperimentsData };

( function () {
	if ( typeof acf !== 'object' || acf === null ) {
		return;
	}

	acf.experiments = {
		isEnabled: function ( name: string ): boolean {
			return this.hasOwnProperty( name ) && this[ name ] === true;
		},
	} as ExperimentsObject;

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! acf.experiments || typeof acfExperiments === 'undefined' ) {
			return;
		}

		if ( acfExperiments && acfExperiments.data ) {
			Object.assign( acf.experiments, acfExperiments.data );
		}
	} );
} )();
