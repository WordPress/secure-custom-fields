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
