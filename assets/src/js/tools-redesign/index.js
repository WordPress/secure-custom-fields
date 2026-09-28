/**
 * SCF Tools Redesign
 *
 * Prototype of the Tools screen built from WordPress core components.
 * Actions are submitted through hidden forms to the existing Tools page
 * handlers, so import and export keep their server side logic and nonces.
 *
 * @since SCF 6.5.0
 */

import { render, createRoot } from '@wordpress/element';
import { ToolsScreen } from './components/tools-screen';

const container = document.getElementById( 'scf-tools-redesign-root' );

if ( container ) {
	const config = window.scfToolsRedesign || {};

	if ( createRoot ) {
		createRoot( container ).render( <ToolsScreen config={ config } /> );
	} else {
		render( <ToolsScreen config={ config } />, container );
	}
}
