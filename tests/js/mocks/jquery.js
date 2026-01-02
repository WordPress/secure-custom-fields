/* global DOMParser */
/**
 * jQuery mock for Jest tests
 * Provides minimal DOM parsing functionality needed by jsx-parser
 *
 * @param {string} html - HTML string to parse
 * @return {Array} Array containing the parsed DOM element
 */
const jQuery = ( html ) => {
	if ( typeof html === 'string' ) {
		const parser = new DOMParser();
		const doc = parser.parseFromString( html, 'text/html' );
		const element = doc.body.firstChild || doc.body;
		return [ element ];
	}
	return [];
};

jQuery.fn = jQuery.prototype = {};
jQuery.extend = Object.assign;

export default jQuery;
