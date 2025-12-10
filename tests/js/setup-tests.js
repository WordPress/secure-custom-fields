/**
 * Jest test setup file
 * Runs before all tests to set up the testing environment
 */

// Add React to global scope
import React from 'react';
global.React = React;

// Mock jQuery for parseJSX tests
global.jQuery = jest.fn( ( html ) => {
	if ( typeof html === 'string' ) {
		// Simple mock that returns an array-like object
		return [ { innerHTML: html, tagName: 'DIV' } ];
	}
	return [];
} );
global.$ = global.jQuery;

// Mock WordPress packages that are externalized
jest.mock(
	'@wordpress/data',
	() => ( {
		useSelect: jest.fn(),
		useDispatch: jest.fn(),
		createReduxStore: jest.fn( ( name, config ) => ( {
			name,
			...config,
		} ) ),
		register: jest.fn(),
	} ),
	{ virtual: true }
);

jest.mock(
	'@wordpress/blocks',
	() => ( {
		registerBlockBindingsSource: jest.fn(),
	} ),
	{ virtual: true }
);

jest.mock(
	'@wordpress/i18n',
	() => ( {
		__: jest.fn( ( text ) => text ),
		_x: jest.fn( ( text ) => text ),
		_n: jest.fn( ( single ) => single ),
	} ),
	{ virtual: true }
);

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );

jest.mock(
	'@wordpress/url',
	() => ( {
		addQueryArgs: jest.fn( ( path, params ) => {
			const query = new URLSearchParams( params ).toString();
			return `${ path }?${ query }`;
		} ),
	} ),
	{ virtual: true }
);

jest.mock(
	'@wordpress/element',
	() => ( {
		...jest.requireActual( 'react' ),
		useState: jest.requireActual( 'react' ).useState,
		useEffect: jest.requireActual( 'react' ).useEffect,
		useCallback: jest.requireActual( 'react' ).useCallback,
		useMemo: jest.requireActual( 'react' ).useMemo,
	} ),
	{ virtual: true }
);

jest.mock(
	'@wordpress/compose',
	() => ( {
		createHigherOrderComponent: jest.fn( ( fn ) => fn ),
	} ),
	{ virtual: true }
);

jest.mock(
	'@wordpress/block-editor',
	() => ( {
		InspectorControls: 'InspectorControls',
		useBlockBindingsUtils: jest.fn(),
	} ),
	{ virtual: true }
);

jest.mock(
	'@wordpress/components',
	() => {
		const mockReact = require( 'react' );
		return {
			ComboboxControl: 'ComboboxControl',
			__experimentalToolsPanel: 'ToolsPanel',
			__experimentalToolsPanelItem: 'ToolsPanelItem',
			Placeholder: ( { icon, label, instructions, children } ) =>
				mockReact.createElement(
					'div',
					{ 'data-testid': 'placeholder' },
					[
						icon &&
							mockReact.createElement(
								'div',
								{ key: 'icon' },
								icon
							),
						label &&
							mockReact.createElement(
								'div',
								{
									key: 'label',
									'data-testid': 'placeholder-label',
								},
								label
							),
						instructions &&
							mockReact.createElement(
								'div',
								{
									key: 'instructions',
									'data-testid': 'placeholder-instructions',
								},
								instructions
							),
						children &&
							mockReact.createElement(
								'div',
								{
									key: 'children',
									'data-testid': 'placeholder-actions',
								},
								children
							),
					]
				),
			Button: ( { children, onClick, variant } ) =>
				mockReact.createElement(
					'button',
					{ onClick, 'data-variant': variant },
					children
				),
			Icon: ( { icon } ) =>
				mockReact.createElement(
					'span',
					{ 'data-testid': 'icon' },
					icon
				),
		};
	},
	{ virtual: true }
);

jest.mock(
	'@wordpress/hooks',
	() => ( {
		addFilter: jest.fn(),
		applyFilters: jest.fn( ( hook, value ) => value ),
	} ),
	{ virtual: true }
);

jest.mock(
	'@wordpress/core-data',
	() => ( {
		store: 'core',
	} ),
	{ virtual: true }
);

jest.mock(
	'@wordpress/editor',
	() => ( {
		store: 'core/editor',
	} ),
	{ virtual: true }
);
