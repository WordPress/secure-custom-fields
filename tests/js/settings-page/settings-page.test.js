/**
 * Unit tests for the settings page UI: dirty state, saving and custom tabs.
 */

import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock(
	'@wordpress/components',
	() => {
		const mockReact = require( 'react' );
		return {
			ToggleControl: ( { label, checked, disabled, onChange } ) =>
				mockReact.createElement(
					'label',
					null,
					label,
					mockReact.createElement( 'input', {
						type: 'checkbox',
						checked,
						disabled,
						onChange: ( event ) => onChange( event.target.checked ),
					} )
				),
			SelectControl: ( { label, value, options, disabled, onChange } ) =>
				mockReact.createElement(
					'select',
					{
						'aria-label': label,
						value,
						disabled,
						onChange: ( event ) => onChange( event.target.value ),
					},
					options.map( ( option ) =>
						mockReact.createElement(
							'option',
							{ key: option.value, value: option.value },
							option.label
						)
					)
				),
			TextControl: ( { label, value, disabled, onChange } ) =>
				mockReact.createElement( 'input', {
					'aria-label': label,
					value,
					disabled,
					onChange: ( event ) => onChange( event.target.value ),
				} ),
			ExternalLink: ( { href, children, ...props } ) =>
				mockReact.createElement( 'a', { href, ...props }, children ),
			Tooltip: ( { children } ) => children,
			TabPanel: ( { tabs, children } ) =>
				mockReact.createElement(
					'div',
					null,
					tabs.map( ( tab ) =>
						mockReact.createElement(
							'button',
							{ key: tab.name, role: 'tab' },
							tab.title
						)
					),
					children( tabs[ 0 ] )
				),
			SnackbarList: ( { notices } ) =>
				mockReact.createElement(
					'ul',
					{ 'data-testid': 'snackbars' },
					notices.map( ( notice ) =>
						mockReact.createElement(
							'li',
							{ key: notice.id, 'data-status': notice.status },
							notice.content
						)
					)
				),
		};
	},
	{ virtual: true }
);

import SettingsApp from '../../../assets/src/js/settings-page/settings-app';
import {
	applyConfirmedSettings,
	getChangedSettings,
	toBoolean,
} from '../../../assets/src/js/settings-page/utils';
import { registerTab } from '../../../assets/src/js/settings-page/settings-tab-registry';

const strings = {
	save: 'Save',
	saving: 'Saving...',
	saveSuccessMessage: 'Your changes have been saved successfully.',
	saveErrorMessage: 'Something went wrong.',
	settingsNotSaved: 'These settings could not be saved: %s.',
	managedViaCode: 'Managed via code',
	experimental: 'Experimental',
	learnMore: 'Learn more',
	learnMoreAbout: 'Learn more about %s',
	opensInNewTab: '(opens in a new tab)',
	settingsTabs: 'Settings sections',
	unsavedChanges: 'Unsaved changes',
};

function makeConfig( overrides = {} ) {
	return {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'nonce123',
		action: 'acf-settings',
		activeTab: 'general',
		strings,
		tabs: [ { key: 'general', label: 'Features', url: 'features' } ],
		tabData: {
			sections: [
				{
					title: 'API & Scripts',
					fields: [
						{
							key: 'enable_shortcode',
							type: 'toggle',
							label: 'SCF Shortcode',
							value: false,
						},
						{
							key: 'rest_api_enabled',
							type: 'toggle',
							label: 'SCF REST API',
							value: true,
							managed: true,
						},
						{
							key: 'rest_api_format',
							type: 'select',
							label: 'REST API Format',
							value: 'light',
							options: [
								{ label: 'Light', value: 'light' },
								{ label: 'Standard', value: 'standard' },
							],
						},
						{
							key: 'enable_datastore',
							type: 'toggle',
							label: 'Datastore',
							value: false,
							experimental: true,
						},
					],
				},
			],
		},
		...overrides,
	};
}

function mockFetch( payload, ok = true ) {
	window.fetch = jest.fn( () =>
		Promise.resolve( {
			ok,
			json: () => Promise.resolve( payload ),
		} )
	);
}

function renderApp( config = makeConfig() ) {
	document.body.innerHTML =
		'<button id="acf-settings-save" disabled hidden>Save</button><div id="root"></div>';
	return render( <SettingsApp initialConfig={ config } />, {
		container: document.getElementById( 'root' ),
	} );
}

const saveButton = () => document.getElementById( 'acf-settings-save' );
const toggleFor = ( label ) =>
	screen
		.getByText( label, { selector: '.screen-reader-text' } )
		.parentElement.querySelector( 'input' );

describe( 'settings page utils', () => {
	it( 'reads toggle values like wp_validate_boolean()', () => {
		expect( toBoolean( '0' ) ).toBe( false );
		expect( toBoolean( 'false' ) ).toBe( false );
		expect( toBoolean( '' ) ).toBe( false );
		expect( toBoolean( '1' ) ).toBe( true );
		expect( toBoolean( 1 ) ).toBe( true );
		expect( toBoolean( null ) ).toBe( false );
	} );

	it( 'only sends editable fields that changed', () => {
		const sections = makeConfig().tabData.sections;
		const saved = {
			enable_shortcode: false,
			rest_api_enabled: true,
			rest_api_format: 'light',
		};

		expect(
			getChangedSettings(
				sections,
				{
					enable_shortcode: true,
					rest_api_enabled: false,
					rest_api_format: 'light',
				},
				saved
			)
		).toEqual( { enable_shortcode: true } );
	} );

	it( 'keeps newer edits when applying confirmed values', () => {
		const sections = makeConfig().tabData.sections;
		const result = applyConfirmedSettings(
			sections,
			{ enable_shortcode: true, rest_api_format: 'light' },
			{ enable_shortcode: true, rest_api_format: 'standard' },
			{ enable_shortcode: '1', rest_api_format: 'standard' }
		);

		expect( result.saved ).toEqual( {
			enable_shortcode: true,
			rest_api_format: 'standard',
		} );
		// The format was edited back while saving, so the newer edit stays.
		expect( result.values.rest_api_format ).toBe( 'light' );
		expect( result.values.enable_shortcode ).toBe( true );
	} );
} );

describe( 'SettingsApp', () => {
	afterEach( () => {
		delete window.fetch;
	} );

	it( 'renders fields, badges and a disabled Save button', () => {
		renderApp();

		expect( screen.getByText( 'API & Scripts' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Managed via code' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Experimental' ) ).toBeInTheDocument();
		expect( toggleFor( 'SCF REST API' ) ).toBeDisabled();
		expect( saveButton().hidden ).toBe( false );
		expect( saveButton().disabled ).toBe( true );
	} );

	it( 'enables Save when a field changes and disables it when changed back', () => {
		renderApp();

		fireEvent.click( toggleFor( 'SCF Shortcode' ) );
		expect( saveButton().disabled ).toBe( false );

		fireEvent.click( toggleFor( 'SCF Shortcode' ) );
		expect( saveButton().disabled ).toBe( true );
	} );

	it( 'saves changed settings and shows a success notice', async () => {
		mockFetch( {
			success: true,
			data: {
				settings: { enable_shortcode: true },
				skipped: [],
				failed: [],
			},
		} );
		renderApp();

		fireEvent.click( toggleFor( 'SCF Shortcode' ) );
		await act( async () => {
			fireEvent.click( saveButton() );
		} );

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		const body = window.fetch.mock.calls[ 0 ][ 1 ].body;
		expect( body.get( 'action' ) ).toBe( 'acf-settings' );
		expect( body.get( 'nonce' ) ).toBe( 'nonce123' );
		expect( body.get( 'tab' ) ).toBe( 'general' );
		expect( JSON.parse( body.get( 'acfSettings' ) ) ).toEqual( {
			enable_shortcode: true,
		} );

		expect(
			screen.getByText( 'Your changes have been saved successfully.' )
		).toHaveAttribute( 'data-status', 'success' );
		expect( saveButton().disabled ).toBe( true );
		expect( toggleFor( 'SCF Shortcode' ) ).toBeChecked();
	} );

	it( 'shows the value the server saved', async () => {
		mockFetch( {
			success: true,
			data: {
				settings: { rest_api_format: 'light' },
				skipped: [],
				failed: [],
			},
		} );
		renderApp();

		fireEvent.change( screen.getByLabelText( 'REST API Format' ), {
			target: { value: 'standard' },
		} );
		await act( async () => {
			fireEvent.click( saveButton() );
		} );

		expect( screen.getByLabelText( 'REST API Format' ) ).toHaveValue(
			'light'
		);
		expect( saveButton().disabled ).toBe( true );
	} );

	it( 'shows an error notice and keeps changes when saving fails', async () => {
		mockFetch( { success: false, data: 'Invalid nonce.' }, false );
		renderApp();

		fireEvent.click( toggleFor( 'SCF Shortcode' ) );
		await act( async () => {
			fireEvent.click( saveButton() );
		} );

		expect( screen.getByText( 'Invalid nonce.' ) ).toHaveAttribute(
			'data-status',
			'error'
		);
		expect( saveButton().disabled ).toBe( false );
		expect( toggleFor( 'SCF Shortcode' ) ).toBeChecked();
	} );

	it( 'shows an error notice when the request fails', async () => {
		window.fetch = jest.fn( () =>
			Promise.reject( new Error( 'offline' ) )
		);
		renderApp();

		fireEvent.click( toggleFor( 'SCF Shortcode' ) );
		await act( async () => {
			fireEvent.click( saveButton() );
		} );

		expect( screen.getByText( 'Something went wrong.' ) ).toHaveAttribute(
			'data-status',
			'error'
		);
	} );

	it( 'warns about settings the server skipped', async () => {
		mockFetch( {
			success: true,
			data: {
				settings: { enable_shortcode: true },
				skipped: [ 'rest_api_format' ],
				failed: [],
			},
		} );
		renderApp();

		fireEvent.click( toggleFor( 'SCF Shortcode' ) );
		fireEvent.change( screen.getByLabelText( 'REST API Format' ), {
			target: { value: 'standard' },
		} );
		await act( async () => {
			fireEvent.click( saveButton() );
		} );

		expect(
			screen.getByText(
				'These settings could not be saved: rest_api_format.'
			)
		).toHaveAttribute( 'data-status', 'error' );
		// The skipped setting is still unsaved.
		expect( saveButton().disabled ).toBe( false );
	} );

	it( 'stacks notices', async () => {
		mockFetch( {
			success: true,
			data: { settings: { enable_shortcode: true } },
		} );
		renderApp();

		fireEvent.click( toggleFor( 'SCF Shortcode' ) );
		await act( async () => {
			fireEvent.click( saveButton() );
		} );
		mockFetch( {
			success: true,
			data: { settings: { enable_shortcode: false } },
		} );
		fireEvent.click( toggleFor( 'SCF Shortcode' ) );
		await act( async () => {
			fireEvent.click( saveButton() );
		} );

		expect( screen.getByTestId( 'snackbars' ).children ).toHaveLength( 2 );
	} );

	it( 'mounts a registered custom tab and hides Save', () => {
		const mount = jest.fn( ( container, config ) => {
			container.textContent = `Custom ${ config.activeTab }`;
		} );
		const unmount = jest.fn();
		registerTab( 'custom', { mount, unmount } );

		const { unmount: unmountApp } = renderApp(
			makeConfig( {
				activeTab: 'custom',
				tabs: [
					{ key: 'general', label: 'Features', url: 'features' },
					{ key: 'custom', label: 'Custom', url: 'custom', count: 3 },
				],
				tabData: {},
			} )
		);

		expect( mount ).toHaveBeenCalledTimes( 1 );
		expect( screen.getByText( 'Custom custom' ) ).toBeInTheDocument();
		expect( screen.getByText( '3' ) ).toBeInTheDocument();
		expect( saveButton().hidden ).toBe( true );

		unmountApp();
		expect( unmount ).toHaveBeenCalledTimes( 1 );
	} );
} );
