/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockBindingsSource } from '@wordpress/blocks';

/**
 * Register SCF Custom Fields block binding source.
 *
 * This allows blocks to bind to custom fields managed by
 * the Secure Custom Fields plugin.
 */

( function () {
	registerBlockBindingsSource( {
		name: 'acf/field',
		label: 'SCF Custom Fields',
		getValues: function ( block ) {
			console.log( block );
		},
	} );
} )();
