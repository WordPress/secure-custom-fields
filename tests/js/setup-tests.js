/**
 * Jest test setup file
 * Runs before all tests to set up the testing environment
 */

// Add React to global scope
import React from 'react';
global.React = React;

// Mock jQuery for parseJSX tests - includes DOM parsing capability
global.jQuery = jest.fn( ( html ) => {
	if ( typeof html === 'string' ) {
		// Parse HTML string to DOM
		const parser = new DOMParser();
		const doc = parser.parseFromString( html, 'text/html' );
		const element = doc.body.firstChild || doc.body;
		return [ element ];
	}
	return [];
} );
global.$ = global.jQuery;

// Mock wp global for blocks-v3 components
global.wp = {
	element: {
		Fragment: React.Fragment,
		Component: React.Component,
		createElement: React.createElement,
		createRef: React.createRef,
	},
	blockEditor: {
		useInnerBlocksProps: jest.fn( ( props ) => ( {
			...props,
			children: null,
		} ) ),
		__experimentalUseInnerBlocksProps: jest.fn( ( props ) => ( {
			...props,
			children: null,
		} ) ),
		BlockControls: ( { children } ) =>
			React.createElement(
				'div',
				{ 'data-testid': 'block-controls' },
				children
			),
		BlockVerticalAlignmentToolbar: ( { value, onChange } ) =>
			React.createElement( 'button', {
				'data-testid': 'vertical-alignment-toolbar',
				'data-value': value,
				onClick: () => onChange( 'center' ),
			} ),
		__experimentalBlockAlignmentMatrixControl: ( { value, onChange } ) =>
			React.createElement( 'button', {
				'data-testid': 'alignment-matrix-control',
				'data-value': value,
				onClick: () => onChange( 'center center' ),
			} ),
		BlockAlignmentMatrixControl: ( { value, onChange } ) =>
			React.createElement( 'button', {
				'data-testid': 'alignment-matrix-control',
				'data-value': value,
				onClick: () => onChange( 'center center' ),
			} ),
		AlignmentToolbar: ( { value, onChange } ) =>
			React.createElement( 'button', {
				'data-testid': 'alignment-toolbar',
				'data-value': value,
				onClick: () => onChange( 'center' ),
			} ),
		__experimentalBlockFullHeightAligmentControl: ( {
			isActive,
			onToggle,
		} ) =>
			React.createElement( 'button', {
				'data-testid': 'full-height-control',
				'data-active': isActive ? 'true' : 'false',
				onClick: () => onToggle( ! isActive ),
			} ),
		BlockFullHeightAlignmentControl: ( { isActive, onToggle } ) =>
			React.createElement( 'button', {
				'data-testid': 'full-height-control',
				'data-active': isActive ? 'true' : 'false',
				onClick: () => onToggle( ! isActive ),
			} ),
		InspectorControls: 'InspectorControls',
		useBlockBindingsUtils: jest.fn(),
	},
	data: {
		dispatch: jest.fn( ( store ) => {
			if ( store === 'core/editor' ) {
				return {
					lockPostSaving: jest.fn(),
					unlockPostSaving: jest.fn(),
				};
			}
			return null;
		} ),
		select: jest.fn( ( store ) => {
			if ( store === 'core/editor' ) {
				return {
					isPostSavingLocked: jest.fn( () => false ),
				};
			}
			return null;
		} ),
	},
};

// Mock ACF global for field type tests
global.acf = {
	Field: {
		extend: jest.fn( ( def ) => def ),
	},
	registerFieldType: jest.fn(),
	__: jest.fn( ( text ) => text ),
	get: jest.fn( ( key ) => {
		if ( key === 'rtl' ) return false;
		return undefined;
	} ),
	isget: jest.fn( ( obj, ...keys ) => {
		let value = obj;
		for ( const key of keys ) {
			if ( value && typeof value === 'object' && key in value ) {
				value = value[ key ];
			} else {
				return undefined;
			}
		}
		return value;
	} ),
	applyFilters: jest.fn( ( hook, value ) => value ),
	arrayArgs: jest.fn( ( arrayLike ) =>
		arrayLike ? Array.from( arrayLike ) : []
	),
	strCamelCase: jest.fn( ( str ) => {
		return str.replace( /-([a-z])/g, ( _, letter ) => letter.toUpperCase() );
	} ),
	debug: jest.fn(),
	blockEdit: null,
	jsxNameReplacements: {
		for: 'htmlFor',
		class: 'className',
		tabindex: 'tabIndex',
		readonly: 'readOnly',
		maxlength: 'maxLength',
		colspan: 'colSpan',
		rowspan: 'rowSpan',
		cellpadding: 'cellPadding',
		cellspacing: 'cellSpacing',
		autocomplete: 'autoComplete',
	},
};

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
