/**
 * Admin experiments.
 *
 * @package    Secure Custom Fields
 * @since      6.4.3
 */

interface ExperimentsData {
	[ key: string ]: boolean;
}

interface ExperimentsObject {
	[ key: string ]: boolean | ( ( name: string ) => boolean );
	isEnabled: ( name: string ) => boolean;
}

interface ACF {
	experiments?: ExperimentsObject;
	[ key: string ]: any;
}

declare global {
	interface Window {
		acf: ACF;
		acfExperiments?: ExperimentsData; // Variable created by wp_localize_script in admin-experiments.php L150.
	}
	var acf: ACF;
	var acfExperiments: ExperimentsData | undefined;
}

// Create a module to avoid global scope augmentation issues.
export {};

( function () {
	if ( typeof acf !== 'object' || acf === null ) {
		return;
	}

	acf.experiments = {
		isEnabled: function ( name: string ): boolean {
			return this.hasOwnProperty( name ) && this[ name ] === true;
		},
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! acf.experiments || typeof acfExperiments === 'undefined' ) {
			return;
		}

		if ( acfExperiments && acfExperiments.data ) {
			Object.assign( acf.experiments, acfExperiments.data );
		}
	} );
} )();
