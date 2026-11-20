/**
 * Unit tests for getContentEditableText.
 */

import { getContentEditableText } from '../../../assets/src/js/pro/blocks-v3/utils/content-editable';

/**
 * Creates a contenteditable element with the given inner HTML.
 *
 * @param {string} html Inner HTML.
 * @return {Element} The element.
 */
function editable( html ) {
	const element = document.createElement( 'span' );
	element.setAttribute( 'contenteditable', 'true' );
	element.innerHTML = html;
	return element;
}

describe( 'getContentEditableText', () => {
	test( 'returns plain text with entities decoded', () => {
		expect( getContentEditableText( editable( 'Fish &amp; Chips' ) ) ).toBe(
			'Fish & Chips'
		);
		expect( getContentEditableText( editable( '1 &lt; 2' ) ) ).toBe(
			'1 < 2'
		);
	} );

	test( 'trims surrounding whitespace', () => {
		expect( getContentEditableText( editable( '  Hello  ' ) ) ).toBe(
			'Hello'
		);
	} );

	test( 'turns <br> into newlines', () => {
		expect( getContentEditableText( editable( 'One<br>Two' ) ) ).toBe(
			'One\nTwo'
		);
	} );

	test( 'turns <div> and <p> lines into newlines, ignoring trailing <br>', () => {
		expect(
			getContentEditableText(
				editable( 'One<div>Two</div><div><br></div><p>Three</p>' )
			)
		).toBe( 'One\nTwo\n\nThree' );
	} );

	test( 'drops markup and returns an empty string for an empty line', () => {
		expect( getContentEditableText( editable( '<b>Bold</b>' ) ) ).toBe(
			'Bold'
		);
		expect( getContentEditableText( editable( '<br>' ) ) ).toBe( '' );
	} );

	test( 'does not modify the original element', () => {
		const element = editable( 'One<br>Two' );
		getContentEditableText( element );
		expect( element.innerHTML ).toBe( 'One<br>Two' );
	} );
} );
