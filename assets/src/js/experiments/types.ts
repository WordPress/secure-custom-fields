/**
 * Types for experiments.
 *
 * @package    Secure Custom Fields
 * @since      6.4.3
 */

export interface ExperimentsData {
	[ key: string ]: boolean;
}

export interface ExperimentsObject {
	[ key: string ]: boolean | ( ( name: string ) => boolean );
	isEnabled: ( name: string ) => boolean;
}

export interface ACF {
	experiments?: ExperimentsObject;
	[ key: string ]: any;
}

declare global {
	interface Window {
		acf: ACF;
		acfExperiments?: ExperimentsData; // Variable created by wp_localize_script in admin-experiments.php L150.
	}
}
