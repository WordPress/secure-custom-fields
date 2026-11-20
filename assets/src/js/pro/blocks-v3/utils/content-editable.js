/**
 * Contenteditable helpers for SCF blocks auto inline editing.
 */

/**
 * Returns the plain text of a contenteditable element.
 *
 * Line breaks the browser inserts while typing (`<br>`, `<div>` and `<p>`) become
 * newlines, and HTML entities are decoded, so the field is saved as plain text
 * rather than HTML-encoded text.
 *
 * @since SCF 6.9.10
 *
 * @param {Element} element The contenteditable element.
 * @return {string} The element's text.
 */
export const getContentEditableText = ( element ) => {
	const clone = element.cloneNode( true );

	clone.querySelectorAll( 'br' ).forEach( ( br ) => {
		// A trailing <br> in a line wrapper only keeps the line open; it isn't a line break.
		const isTrailing =
			! br.nextSibling && br.parentNode.matches( 'div, p' );
		br.replaceWith( isTrailing ? '' : '\n' );
	} );

	clone
		.querySelectorAll( 'div, p' )
		.forEach( ( line ) => line.before( '\n' ) );

	return clone.textContent.trim();
};

export default getContentEditableText;
