/* global acf, DOMParser */
/**
 * Unit tests for the block preview script guard.
 *
 * Covers the shared helpers and how the v3 JSX parser (and its legacy v1/v2
 * delegation) applies them.
 */

import {
	previewScriptsAllowed,
	isExecutableScript,
	shouldRenderPreviewNode,
	removeExecutablePreviewScripts,
} from '../../../assets/src/js/pro/blocks-v3/utils/preview-scripts';
import { parseJSX } from '../../../assets/src/js/pro/blocks-v3/components/jsx-parser';

/**
 * Parses an HTML string and returns the first element in its body.
 *
 * @param {string} html HTML string.
 * @return {Element} The element.
 */
function el( html ) {
	return new DOMParser().parseFromString( html, 'text/html' ).body
		.firstElementChild;
}

/**
 * Minimal jQuery-like collection supporting the methods the guard uses.
 *
 * @param {Element[]} elements Elements in the collection.
 * @return {Object} The collection.
 */
function collection( elements ) {
	const matches = ( selectorOrFn ) =>
		typeof selectorOrFn === 'function'
			? ( element, index ) => selectorOrFn( index, element )
			: ( element ) => element.matches( selectorOrFn );

	return {
		elements,
		length: elements.length,
		filter: ( selectorOrFn ) =>
			collection( elements.filter( matches( selectorOrFn ) ) ),
		find: ( selector ) =>
			collection(
				elements.flatMap( ( element ) =>
					Array.from( element.querySelectorAll( selector ) )
				)
			),
		not: ( other ) =>
			collection(
				elements.filter(
					( element ) => ! other.elements.includes( element )
				)
			),
		remove() {
			elements.forEach( ( element ) => element.remove() );
			return this;
		},
	};
}

/**
 * Parses an HTML string into a collection of its top-level elements.
 *
 * @param {string} html HTML string.
 * @return {Object} The collection.
 */
function $parse( html ) {
	const doc = new DOMParser().parseFromString( html, 'text/html' );
	return collection(
		Array.from( doc.head.children ).concat(
			Array.from( doc.body.children )
		)
	);
}

describe( 'Preview script guard', () => {
	const originalGet = acf.get.getMockImplementation();
	let globalAllowed;
	let warnSpy;

	beforeEach( () => {
		globalAllowed = false;
		acf.get.mockImplementation( ( key ) =>
			key === 'allowDangerousPreviewScriptsInBlocks'
				? globalAllowed
				: originalGet( key )
		);
		warnSpy = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		acf.get.mockImplementation( originalGet );
		warnSpy.mockRestore();
	} );

	describe( 'previewScriptsAllowed', () => {
		test( 'is false by default', () => {
			expect( previewScriptsAllowed( false ) ).toBe( false );
			expect( previewScriptsAllowed( { name: 'acf/a' } ) ).toBe( false );
		} );

		test( 'is true when the block type opts in', () => {
			expect(
				previewScriptsAllowed( {
					allow_dangerous_preview_scripts: true,
				} )
			).toBe( true );
		} );

		test( 'is true when the site opts in', () => {
			globalAllowed = true;
			expect( previewScriptsAllowed( false ) ).toBe( true );
		} );
	} );

	describe( 'isExecutableScript', () => {
		test.each( [
			[ '<script></script>', true ],
			[ '<script type="text/javascript"></script>', true ],
			[ '<script type=" module "></script>', true ],
			[ '<script type="application/ecmascript"></script>', true ],
			[ '<script type="application/ld+json"></script>', false ],
			[ '<script type="text/template"></script>', false ],
		] )( '%s => %s', ( html, expected ) => {
			const doc = new DOMParser().parseFromString( html, 'text/html' );
			expect( isExecutableScript( doc.querySelector( 'script' ) ) ).toBe(
				expected
			);
		} );
	} );

	describe( 'shouldRenderPreviewNode', () => {
		test( 'renders non-script nodes', () => {
			expect(
				shouldRenderPreviewNode( el( '<p>x</p>' ), false, 'acf/a' )
			).toBe( true );
		} );

		test( 'drops script nodes and warns once per block', () => {
			const script = el( '<div><script>1</script></div>' ).firstChild;

			expect( shouldRenderPreviewNode( script, false, 'acf/warn' ) ).toBe(
				false
			);
			expect( shouldRenderPreviewNode( script, false, 'acf/warn' ) ).toBe(
				false
			);
			expect( warnSpy ).toHaveBeenCalledTimes( 1 );
			expect( warnSpy.mock.calls[ 0 ][ 0 ] ).toContain(
				'SCF: A <script> in the preview of the "acf/warn" block was not run.'
			);
		} );

		test( 'drops non-executable script nodes without warning', () => {
			const script = el(
				'<div><script type="application/ld+json">{}</script></div>'
			).firstChild;

			expect(
				shouldRenderPreviewNode( script, false, 'acf/json-only' )
			).toBe( false );
			expect( warnSpy ).not.toHaveBeenCalled();
		} );

		test( 'renders script nodes when the block type opts in', () => {
			const script = el( '<div><script>1</script></div>' ).firstChild;
			expect(
				shouldRenderPreviewNode(
					script,
					{ allow_dangerous_preview_scripts: true },
					'acf/a'
				)
			).toBe( true );
		} );
	} );

	describe( 'removeExecutablePreviewScripts (jQuery render path)', () => {
		test( 'removes top-level and nested executable scripts only', () => {
			const $el = $parse(
				'<script>top()</script>' +
					'<div class="wrap"><script>nested()</script>' +
					'<script type="application/ld+json">{}</script><p>Text</p></div>'
			);

			const $result = removeExecutablePreviewScripts(
				$el,
				false,
				'acf/jquery'
			);

			expect( $result.length ).toBe( 1 );
			const wrap = $result.elements[ 0 ];
			expect( wrap.matches( '.wrap' ) ).toBe( true );
			expect( wrap.querySelectorAll( 'script' ) ).toHaveLength( 1 );
			expect(
				wrap.querySelector( 'script' ).getAttribute( 'type' )
			).toBe( 'application/ld+json' );
			expect( wrap.querySelector( 'p' ).textContent ).toBe( 'Text' );
			expect( warnSpy ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'returns the collection untouched without executable scripts', () => {
			const $el = $parse( '<div><p>Text</p></div>' );
			expect(
				removeExecutablePreviewScripts( $el, false, 'acf/clean' )
			).toBe( $el );
			expect( warnSpy ).not.toHaveBeenCalled();
		} );

		test( 'keeps scripts when the block type opts in', () => {
			const $el = $parse( '<script>top()</script><div></div>' );
			expect(
				removeExecutablePreviewScripts(
					$el,
					{ allow_dangerous_preview_scripts: true },
					'acf/a'
				)
			).toBe( $el );
		} );

		test( 'keeps scripts when the site opts in', () => {
			globalAllowed = true;
			const $el = $parse( '<script>top()</script><div></div>' );
			expect(
				removeExecutablePreviewScripts( $el, false, 'acf/a' )
			).toBe( $el );
		} );
	} );

	describe( 'v3 parseJSX', () => {
		const html = '<div class="block"><script>run()</script><p>Hi</p></div>';

		test( 'drops <script> nodes by default', () => {
			const result = parseJSX( html );
			expect( result.type ).toBe( 'div' );
			expect( result.props.children[ 0 ] ).toBeNull();
			expect( result.props.children[ 1 ].type ).toBe( 'p' );
		} );

		test( 'renders <script> nodes when the block type opts in', () => {
			const result = parseJSX( html, undefined, null, null, null, null, {
				name: 'acf/opt-in',
				allow_dangerous_preview_scripts: true,
			} );
			expect( result.props.children[ 0 ].type.name ).toBe(
				'ScriptComponent'
			);
		} );

		test( 'renders <script> nodes when the site opts in', () => {
			globalAllowed = true;
			const result = parseJSX( html );
			expect( result.props.children[ 0 ].type.name ).toBe(
				'ScriptComponent'
			);
		} );

		test( 'passes the block type through the jQuery parser path', () => {
			const parser = jest.fn( ( markup ) => [
				new DOMParser().parseFromString( markup, 'text/html' ).body
					.firstChild,
			] );
			parser.fn = { jquery: '3.7.1' };

			const blocked = parseJSX( html, parser, null, null, null, null, {
				name: 'acf/jquery-blocked',
			} );
			expect( blocked.props.children[ 0 ] ).toBeNull();

			const allowed = parseJSX( html, parser, null, null, null, null, {
				name: 'acf/jquery-allowed',
				allow_dangerous_preview_scripts: true,
			} );
			expect( allowed.props.children[ 0 ].type.name ).toBe(
				'ScriptComponent'
			);
			expect( parser ).toHaveBeenCalledTimes( 2 );
		} );
	} );

	describe( 'legacy v1/v2 parseJSX delegation', () => {
		test( 'forwards the block name to the legacy parser', () => {
			const originalParseJSX = acf.parseJSX;
			const legacyParseJSX = jest.fn( () => 'legacy-result' );

			jest.isolateModules( () => {
				acf.parseJSX = legacyParseJSX;
				const {
					parseJSX: isolatedParseJSX,
				} = require( '../../../assets/src/js/pro/blocks-v3/components/jsx-parser' );

				expect(
					isolatedParseJSX( '<p>Legacy</p>', 2, 'acf/legacy-block' )
				).toBe( 'legacy-result' );
			} );

			expect( legacyParseJSX ).toHaveBeenCalledWith(
				'<p>Legacy</p>',
				2,
				'acf/legacy-block'
			);

			acf.parseJSX = originalParseJSX;
		} );
	} );
} );
