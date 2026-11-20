/**
 * Registry for settings tabs that render their own UI inside the settings page.
 *
 * A tab registered with a 'js' URL in the 'acf/settings_page/tabs' filter has that script loaded after
 * SCF's settings app. The script calls acf.settingsPage.registerTab() with mount and unmount callbacks,
 * and SCF calls them when the tab is shown or hidden, keeping its own page header and tab bar.
 *
 * Definitions:
 * - mount( container, config ): renders the tab into the container. Called each time the tab is shown.
 *   Use the config passed here rather than the global acfSettingsPage: when switching tabs in-page,
 *   the global still holds the data for the tab the page was loaded on.
 * - unmount( container ): optional. Cleans up when the tab is hidden, e.g. stopping timers and removing
 *   document event listeners. SCF empties the container afterwards.
 *
 * @since SCF 6.9.10
 */

const tabs = new Map();
const listeners = new Set();

/**
 * Registers a tab's UI.
 *
 * @param {string} key        The tab key, as registered with the 'acf/settings_page/tabs' filter.
 * @param {Object} definition The tab definition, with mount() and optional unmount().
 */
export function registerTab( key, definition ) {
	if ( typeof definition?.mount !== 'function' ) {
		// eslint-disable-next-line no-console
		console.error(
			`acf.settingsPage.registerTab: the "${ key }" tab needs a mount() function.`
		);
		return;
	}

	tabs.set( key, definition );
	listeners.forEach( ( listener ) => listener() );
}

/**
 * Returns a registered tab definition.
 *
 * @param {string} key The tab key.
 * @return {Object|undefined} The definition, if registered.
 */
export function getTab( key ) {
	return tabs.get( key );
}

/**
 * Calls the listener whenever a tab is registered. Returns a function that unsubscribes.
 *
 * @param {Function} listener Called after each registration.
 * @return {Function} Unsubscribes the listener.
 */
export function subscribe( listener ) {
	listeners.add( listener );
	return () => {
		listeners.delete( listener );
	};
}

/**
 * Exposes the registry as acf.settingsPage.registerTab() for tab scripts. SCF's acf.js (which assigns
 * window.acf) may load first, so extend the object rather than replacing it.
 *
 * @param {Object} win The window object.
 */
export function exposeRegistry( win = window ) {
	win.acf = win.acf || {};
	win.acf.settingsPage = { ...( win.acf.settingsPage || {} ), registerTab };
}
