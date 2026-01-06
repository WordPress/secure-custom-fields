/* global acf, DOMParser */
/**
 * Unit tests for JSX Parser
 * Tests HTML parsing, attribute handling, and edge cases
 */

import '@testing-library/jest-dom';

import { parseJSX } from '../../../assets/src/js/pro/blocks-v3/components/jsx-parser';

describe( 'parseJSX', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'Basic HTML Parsing', () => {
		test( 'parses simple div with text content', () => {
			const result = parseJSX( '<div>Hello</div>' );
			expect( result.type ).toBe( 'div' );
			expect( result.props.children ).toBe( 'Hello' );
		} );

		test( 'parses paragraph element', () => {
			const result = parseJSX( '<p>Some text content</p>' );
			expect( result.type ).toBe( 'p' );
			expect( result.props.children ).toBe( 'Some text content' );
		} );

		test( 'parses nested elements', () => {
			const result = parseJSX( '<div><span>Nested</span></div>' );
			expect( result.type ).toBe( 'div' );
			expect( result.props.children.type ).toBe( 'span' );
			expect( result.props.children.props.children ).toBe( 'Nested' );
		} );

		test( 'parses multiple siblings as array', () => {
			const result = parseJSX( '<div>First</div><div>Second</div>' );
			expect( Array.isArray( result ) ).toBe( true );
			expect( result ).toHaveLength( 2 );
			expect( result[ 0 ].type ).toBe( 'div' );
			expect( result[ 0 ].props.children ).toBe( 'First' );
			expect( result[ 1 ].type ).toBe( 'div' );
			expect( result[ 1 ].props.children ).toBe( 'Second' );
		} );

		test( 'returns undefined for empty string', () => {
			const result = parseJSX( '' );
			expect( result ).toBeUndefined();
		} );

		test( 'parses text-only content', () => {
			const result = parseJSX( 'Just text' );
			expect( result ).toBe( 'Just text' );
		} );

		test( 'parses whitespace-only content', () => {
			const result = parseJSX( '   ' );
			expect( result ).toBe( '   ' );
		} );
	} );

	describe( 'Attribute Handling', () => {
		test( 'converts class to className', () => {
			const result = parseJSX( '<div class="my-class">Content</div>' );
			expect( result.props.className ).toBe( 'my-class' );
			expect( result.props.class ).toBeUndefined();
		} );

		test( 'converts style string to object with camelCase properties', () => {
			parseJSX(
				'<div style="background-color: blue; font-weight: bold;">Content</div>'
			);
			expect( acf.strCamelCase ).toHaveBeenCalledWith(
				'background-color'
			);
			expect( acf.strCamelCase ).toHaveBeenCalledWith( 'font-weight' );
		} );

		test( 'converts for attribute to htmlFor', () => {
			parseJSX( '<label for="input-id">Label</label>' );
			expect( acf.isget ).toHaveBeenCalledWith(
				acf,
				'jsxNameReplacements',
				'for'
			);
		} );

		test( 'preserves data- attributes as-is', () => {
			const result = parseJSX( '<div data-custom="value">Content</div>' );
			expect( result.props[ 'data-custom' ] ).toBe( 'value' );
		} );

		test( 'calls applyFilters for custom attribute handling', () => {
			parseJSX( '<div custom-attr="value">Content</div>' );
			expect( acf.applyFilters ).toHaveBeenCalledWith(
				'acf_blocks_parse_node_attr',
				false,
				expect.objectContaining( { name: expect.any( String ) } )
			);
		} );

		test( 'uses filter result when provided', () => {
			acf.applyFilters.mockImplementationOnce( ( hook, value, attr ) => {
				if ( attr.name === 'custom-attr' ) {
					return { name: 'customAttr', value: 'transformed' };
				}
				return value;
			} );

			const result = parseJSX(
				'<div custom-attr="original">Content</div>'
			);
			expect( result.props.customAttr ).toBe( 'transformed' );
		} );

		test( 'converts boolean string "true" to boolean true', () => {
			const result = parseJSX( '<input disabled="true" />' );
			expect( result.props.disabled ).toBe( true );
		} );

		test( 'converts boolean string "false" to boolean false', () => {
			const result = parseJSX( '<input disabled="false" />' );
			expect( result.props.disabled ).toBe( false );
		} );
	} );

	describe( 'JSON Attribute Parsing', () => {
		test( 'parses valid JSON array attribute', () => {
			const result = parseJSX(
				'<div items=\'["a","b","c"]\'>Content</div>'
			);
			expect( result.props.items ).toEqual( [ 'a', 'b', 'c' ] );
		} );

		test( 'parses valid JSON object attribute', () => {
			const result = parseJSX(
				'<div config=\'{"key":"value"}\'>Content</div>'
			);
			expect( result.props.config ).toEqual( { key: 'value' } );
		} );

		test( 'throws SyntaxError on invalid JSON array attribute', () => {
			expect( () => {
				parseJSX( '<div items="[invalid json">Content</div>' );
			} ).toThrow( SyntaxError );
		} );

		test( 'throws SyntaxError on invalid JSON object attribute', () => {
			expect( () => {
				parseJSX( '<div config="{not: valid}">Content</div>' );
			} ).toThrow( SyntaxError );
		} );
	} );

	describe( 'InnerBlocks Handling', () => {
		test( 'converts InnerBlocks to ACFInnerBlocksComponent', () => {
			const result = parseJSX( '<InnerBlocks />' );
			// ACFInnerBlocksComponent is a function component
			expect( typeof result.type ).toBe( 'function' );
			expect( result.type.name ).toBe( 'ACFInnerBlocksComponent' );
		} );

		test( 'passes attributes to InnerBlocks component', () => {
			const result = parseJSX(
				'<InnerBlocks allowedBlocks=\'["core/paragraph"]\' />'
			);
			// DOM parser lowercases attribute names, so allowedBlocks becomes allowedblocks
			expect( result.props.allowedblocks ).toEqual( [
				'core/paragraph',
			] );
		} );

		test( 'handles InnerBlocks inside container', () => {
			const result = parseJSX(
				'<div class="container"><InnerBlocks /></div>'
			);
			expect( result.type ).toBe( 'div' );
			expect( result.props.className ).toBe( 'container' );
			expect( typeof result.props.children.type ).toBe( 'function' );
		} );
	} );

	describe( 'Script Tag Handling', () => {
		test( 'converts script tags to ScriptComponent', () => {
			const result = parseJSX( '<script>console.log("test");</script>' );
			// ScriptComponent is a class component
			expect( typeof result.type ).toBe( 'function' );
			expect( result.type.name ).toBe( 'ScriptComponent' );
		} );

		test( 'passes script content as children', () => {
			const result = parseJSX( '<script>var x = 1;</script>' );
			expect( result.props.children ).toBe( 'var x = 1;' );
		} );
	} );

	describe( 'Malformed Input Handling', () => {
		test( 'handles unclosed tags gracefully', () => {
			const result = parseJSX( '<div><p>Unclosed' );
			expect( result.type ).toBe( 'div' );
			// Browser's DOMParser auto-closes tags
			expect( result.props.children.type ).toBe( 'p' );
		} );

		test( 'handles deeply nested elements (50 levels)', () => {
			const html =
				'<div>'.repeat( 50 ) + 'Content' + '</div>'.repeat( 50 );
			const result = parseJSX( html );
			expect( result.type ).toBe( 'div' );
		} );

		test( 'handles null bytes in content', () => {
			const result = parseJSX(
				'<div>Content\u0000with\u0000nulls</div>'
			);
			expect( result.type ).toBe( 'div' );
			// Null bytes are preserved in the content
			expect( result.props.children ).toContain( 'Content' );
		} );
	} );

	describe( 'XSS Vector Documentation', () => {
		// These tests document that parseJSX processes XSS vectors without sanitizing.
		// Sanitization must happen at the PHP/template level before HTML reaches the parser.

		test( 'processes javascript: URL (sanitization happens elsewhere)', () => {
			const result = parseJSX(
				'<a href="javascript:alert(1)">Click</a>'
			);
			expect( result.type ).toBe( 'a' );
			expect( result.props.href ).toBe( 'javascript:alert(1)' );
		} );

		test( 'processes event handlers (sanitization happens elsewhere)', () => {
			const result = parseJSX( '<img src="x" onerror="alert(1)" />' );
			expect( result.type ).toBe( 'img' );
			expect( result.props.onerror ).toBe( 'alert(1)' );
		} );
	} );

	describe( 'Custom jQuery Instance', () => {
		test( 'accepts and uses custom jQuery instance', () => {
			const customJQuery = jest.fn( ( html ) => {
				const parser = new DOMParser();
				const doc = parser.parseFromString( html, 'text/html' );
				return [ doc.body.firstChild ];
			} );

			const result = parseJSX( '<div>Test</div>', customJQuery );

			expect( customJQuery ).toHaveBeenCalled();
			expect( result.type ).toBe( 'div' );
			expect( result.props.children ).toBe( 'Test' );
		} );
	} );

	describe( 'ACF Global Integration', () => {
		test( 'parseJSX is exposed on acf global object', () => {
			expect( acf.parseJSX ).toBe( parseJSX );
		} );
	} );

	describe( 'ACF Inline Editing Attributes', () => {
		beforeEach( () => {
			acf.blockEdit = {
				setCurrentInlineEditingElementUid: jest.fn(),
				setCurrentInlineEditingElement: jest.fn(),
				setCurrentContentEditableElement: jest.fn(),
				getBlockFieldInfo: jest.fn( () => [
					{ name: 'title', type: 'text' },
				] ),
			};
		} );

		afterEach( () => {
			acf.blockEdit = null;
		} );

		test( 'adds interaction props for inline editing elements', () => {
			const result = parseJSX(
				'<div data-acf-inline-fields="true" data-acf-inline-fields-uid="field_123">Content</div>'
			);
			expect( result.props.role ).toBe( 'button' );
			expect( result.props.tabIndex ).toBe( 0 );
			expect( typeof result.props.onFocus ).toBe( 'function' );
			expect( typeof result.props.onClick ).toBe( 'function' );
			expect( typeof result.props.onKeyDown ).toBe( 'function' );
		} );

		test( 'adds pointerEvents style for inline editing elements', () => {
			const result = parseJSX(
				'<div data-acf-inline-fields="true" data-acf-inline-fields-uid="field_123">Content</div>'
			);
			expect( result.props.style.pointerEvents ).toBe( 'all' );
		} );

		test( 'adds contentEditable for valid editable fields', () => {
			const result = parseJSX(
				'<span data-acf-inline-contenteditable="true" data-acf-inline-contenteditable-field-slug="title">Text</span>'
			);
			expect( result.props.contentEditable ).toBe( true );
			expect( result.props.suppressContentEditableWarning ).toBe( true );
		} );

		test( 'removes contentEditable attrs for non-existent fields', () => {
			acf.blockEdit.getBlockFieldInfo.mockReturnValue( [] );
			const result = parseJSX(
				'<span data-acf-inline-contenteditable="true" data-acf-inline-contenteditable-field-slug="nonexistent">Text</span>'
			);
			expect( result.props.contentEditable ).toBeUndefined();
			expect(
				result.props[ 'data-acf-inline-contenteditable' ]
			).toBeUndefined();
		} );
	} );
} );
