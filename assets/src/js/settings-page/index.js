/**
 * Settings page, built with @wordpress/components.
 *
 * Reads the acfSettingsPage data localized by SCF\Settings_Page\Settings_Page and exposes
 * acf.settingsPage.registerTab() for tabs that render their own UI.
 *
 * @since SCF 6.9.10
 */

/* global acfSettingsPage */

import { createRoot, render } from '@wordpress/element';

import SettingsApp from './settings-app';
import { exposeRegistry } from './settings-tab-registry';

// Tab scripts load after this one and register their UI straight away.
exposeRegistry( window );

const mount = () => {
	const container = document.getElementById( 'acf-settings-app' );
	if ( ! container || typeof acfSettingsPage === 'undefined' ) {
		return;
	}

	const app = <SettingsApp initialConfig={ acfSettingsPage } />;

	if ( createRoot ) {
		createRoot( container ).render( app );
	} else {
		render( app, container );
	}
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
