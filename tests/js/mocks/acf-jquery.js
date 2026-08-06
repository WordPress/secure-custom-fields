/**
 * Minimal jQuery stub for loading SCF core source modules
 * (_acf.js, _acf-hooks.js, _acf-model.js, etc.) in jsdom.
 *
 * Provides just enough of the jQuery API for the IIFE modules to load and
 * for tests to assert against. Wrappers are cached per target so that
 * repeated calls like jQuery( window ) return the same spy-able object.
 *
 * Not a real jQuery: DOM traversal is not implemented. Tests that need
 * real DOM behavior (e.g. acf.encode/decode) get it via the
 * '<tag/>' creation special-case which uses jsdom elements.
 */

/* global jest */

/**
 * Creates a fresh jQuery stub function. Call once per test (or test file)
 * and assign to global.jQuery / global.$ before requiring source modules.
 *
 * @return {Function} The jQuery stub.
 */
function createJQueryStub() {
	const wrappers = new Map();

	const createWrapper = ( target ) => {
		const dataStore = {};
		const classes = new Set();
		const wrapper = {
			target,
			length: 0,
			on: jest.fn( () => wrapper ),
			off: jest.fn( () => wrapper ),
			ready: jest.fn( () => wrapper ),
			trigger: jest.fn( () => wrapper ),
			triggerHandler: jest.fn( () => wrapper ),
			each: jest.fn( () => wrapper ),
			remove: jest.fn( () => wrapper ),
			find: jest.fn( () => createWrapper( 'find:' + target ) ),
			data( key, value ) {
				if ( value === undefined ) {
					return dataStore[ key ];
				}
				dataStore[ key ] = value;
				return wrapper;
			},
			hasClass: ( name ) => classes.has( name ),
			addClass( name ) {
				classes.add( name );
				return wrapper;
			},
			removeClass( name ) {
				classes.delete( name );
				return wrapper;
			},
		};
		return wrapper;
	};

	const jq = function ( selector ) {
		// Creation syntax, e.g. $('<textarea/>'): return a tiny wrapper
		// around a real jsdom element so .text()/.html() behave for real.
		if ( typeof selector === 'string' && selector.charAt( 0 ) === '<' ) {
			const tagName = selector.replace( /[<>/\s]/g, '' );
			const el = document.createElement( tagName );
			return {
				0: el,
				length: 1,
				text( value ) {
					if ( value === undefined ) {
						return el.textContent;
					}
					el.textContent = value;
					return this;
				},
				html( value ) {
					if ( value === undefined ) {
						return el.innerHTML;
					}
					el.innerHTML = value;
					return this;
				},
			};
		}

		// Return a cached wrapper per target for stable spying.
		if ( ! wrappers.has( selector ) ) {
			wrappers.set( selector, createWrapper( selector ) );
		}
		return wrappers.get( selector );
	};

	jq.fn = {};

	jq.extend = function ( ...args ) {
		// Ignore the deep flag; shallow merge is enough for tests.
		if ( typeof args[ 0 ] === 'boolean' ) {
			args.shift();
		}
		const target = args.shift() || {};
		args.forEach( ( source ) => {
			if ( source ) {
				Object.assign( target, source );
			}
		} );
		return target;
	};

	jq.proxy = ( fn, context ) => fn.bind( context );

	jq.each = function ( obj, callback ) {
		Object.keys( obj ).forEach( ( key ) => {
			callback.call( obj[ key ], key, obj[ key ] );
		} );
		return obj;
	};

	jq.isPlainObject = ( obj ) =>
		!! obj &&
		typeof obj === 'object' &&
		Object.getPrototypeOf( obj ) === Object.prototype;

	jq.isEmptyObject = ( obj ) => Object.keys( obj ).length === 0;

	jq.inArray = ( value, array ) => array.indexOf( value );

	jq.Event = function ( type, props ) {
		return { type, ...props };
	};

	jq.ajax = jest.fn();

	return jq;
}

module.exports = { createJQueryStub };
