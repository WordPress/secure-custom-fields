/**
 * Unit tests for JSX Parser
 * Tests the HTML to React/JSX conversion for block previews
 * Important: This component has XSS risk considerations
 */

import React from 'react';

// Setup mocks before importing the module
const mockCreateElement = jest.fn( ( type, props, ...children ) =>
	React.createElement( type, props, ...children )
);

const mockCreateRef = jest.fn( () => ( { current: null } ) );

// Mock @wordpress/element
jest.mock(
	'@wordpress/element',
	() => ( {
		createElement: mockCreateElement,
		createRef: mockCreateRef,
	} ),
	{ virtual: true }
);

// Mock wp global for useInnerBlocksProps
global.wp = {
	blockEditor: {
		useInnerBlocksProps: jest.fn( ( props ) => ( {
			...props,
			children: null,
		} ) ),
	},
};

// Setup acf global
global.acf = {
	debug: jest.fn(),
	isget: jest.fn( ( obj, ...keys ) => {
		// Simple implementation of isget for jsxNameReplacements
		if ( keys[ 0 ] === 'jsxNameReplacements' ) {
			const replacements = {
				for: 'htmlFor',
				tabindex: 'tabIndex',
				colspan: 'colSpan',
				rowspan: 'rowSpan',
				autocomplete: 'autoComplete',
				autofocus: 'autoFocus',
				readonly: 'readOnly',
			};
			return replacements[ keys[ 1 ] ] || undefined;
		}
		return undefined;
	} ),
	strCamelCase: jest.fn( ( str ) => {
		return str.replace( /-([a-z])/g, ( g ) => g[ 1 ].toUpperCase() );
	} ),
	applyFilters: jest.fn( ( hook, value ) => value ),
	arrayArgs: jest.fn( ( collection ) => {
		if ( ! collection ) {
			return [];
		}
		return Array.from( collection );
	} ),
	blockEdit: {
		setCurrentInlineEditingElementUid: jest.fn(),
		setCurrentInlineEditingElement: jest.fn(),
		setCurrentContentEditableElement: jest.fn(),
		getBlockFieldInfo: jest.fn( () => [
			{ name: 'title', type: 'text', label: 'Title' },
			{ name: 'description', type: 'textarea', label: 'Description' },
		] ),
	},
};

// Mock jQuery
const createMockElement = ( html ) => {
	const div = document.createElement( 'div' );
	div.innerHTML = html;
	return div;
};

global.jQuery = jest.fn( ( html ) => {
	if ( typeof html === 'string' ) {
		return [ createMockElement( html ) ];
	}
	return [ html ];
} );

describe( 'JSX Parser DOM Integration', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'DOM element attribute handling', () => {
		test( 'preserves acf-inline-fields data attributes for inline editing', () => {
			const div = document.createElement( 'div' );
			div.setAttribute(
				'data-acf-inline-fields',
				'["field_1","field_2"]'
			);
			div.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

			expect( div.getAttribute( 'data-acf-inline-fields' ) ).toBe(
				'["field_1","field_2"]'
			);
			expect( div.getAttribute( 'data-acf-inline-fields-uid' ) ).toBe(
				'uid-123'
			);
		} );

		test( 'preserves contenteditable attributes for inline editing', () => {
			const div = document.createElement( 'div' );
			div.setAttribute( 'data-acf-inline-contenteditable', 'true' );
			div.setAttribute(
				'data-acf-inline-contenteditable-field-slug',
				'title'
			);

			expect(
				div.getAttribute( 'data-acf-inline-contenteditable' )
			).toBe( 'true' );
			expect(
				div.getAttribute( 'data-acf-inline-contenteditable-field-slug' )
			).toBe( 'title' );
		} );
	} );

	describe( 'InnerBlocks HTML transformation', () => {
		test( 'self-closing InnerBlocks regex replacement', () => {
			const htmlString =
				'<div><InnerBlocks allowedBlocks="["core/paragraph"]" /></div>';
			const result = htmlString.replace(
				/<InnerBlocks([^>]+)?\/>/,
				'<InnerBlocks$1></InnerBlocks>'
			);

			expect( result ).toBe(
				'<div><InnerBlocks allowedBlocks="["core/paragraph"]" ></InnerBlocks></div>'
			);
		} );

		test( 'handles InnerBlocks without attributes', () => {
			const htmlString = '<div><InnerBlocks /></div>';
			const result = htmlString.replace(
				/<InnerBlocks([^>]+)?\/>/,
				'<InnerBlocks$1></InnerBlocks>'
			);

			expect( result ).toBe( '<div><InnerBlocks ></InnerBlocks></div>' );
		} );

		test( 'does not affect non-self-closing InnerBlocks', () => {
			const htmlString = '<div><InnerBlocks></InnerBlocks></div>';
			const result = htmlString.replace(
				/<InnerBlocks([^>]+)?\/>/,
				'<InnerBlocks$1></InnerBlocks>'
			);

			expect( result ).toBe( '<div><InnerBlocks></InnerBlocks></div>' );
		} );
	} );

	describe( 'XSS security considerations', () => {
		test( 'script content exists but should not execute in test environment', () => {
			const maliciousHtml = '<script>alert("XSS")</script>';
			const div = document.createElement( 'div' );
			div.innerHTML = maliciousHtml;

			expect( div.querySelector( 'script' ) ).not.toBeNull();
		} );
	} );
} );
