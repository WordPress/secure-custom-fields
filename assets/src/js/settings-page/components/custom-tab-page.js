/**
 * Renders a tab registered with acf.settingsPage.registerTab() inside SCF's page.
 *
 * @since SCF 6.9.10
 */

import { useEffect, useRef } from '@wordpress/element';

import { useHeaderSaveButton } from '../header-save-button';

/**
 * Mounts a registered tab into its container, and unmounts it when the tab is hidden.
 *
 * @param {Object} props        Props.
 * @param {Object} props.tab    The registered tab definition.
 * @param {Object} props.config The settings page config for this tab.
 */
export default function CustomTabPage( { tab, config } ) {
	const containerRef = useRef( null );
	// Read the latest config when mounting without remounting on every config change.
	const configRef = useRef( config );
	configRef.current = config;

	// The tab renders its own UI, so it saves with its own controls.
	useHeaderSaveButton( { visible: false, disabled: true } );

	useEffect( () => {
		const container = containerRef.current;
		if ( ! container ) {
			return;
		}

		try {
			tab.mount( container, configRef.current );
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( error );
		}

		return () => {
			try {
				tab.unmount?.( container );
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( error );
			}
			container.replaceChildren();
		};
	}, [ tab ] );

	return (
		<div
			ref={ containerRef }
			className="scf-settings-custom-tab"
			data-acf-settings-tab={ config.activeTab }
		/>
	);
}
