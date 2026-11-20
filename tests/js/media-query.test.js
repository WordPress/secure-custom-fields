/**
 * Unit tests for the media Query customization in _acf-media.js.
 */

describe( 'Media customizeQuery()', () => {
	let customizePrototypes;
	let originalInitialize;
	let Query;
	let originalWp;

	beforeEach( () => {
		customizePrototypes = null;
		originalWp = global.wp;

		// Mock acf.Model so the module can be loaded and the
		// customizePrototypes definition captured.
		const Model = jest.fn( function ( definition ) {
			customizePrototypes = definition;
		} );
		Model.extend = jest.fn( () => ( { extend: jest.fn() } ) );

		global.acf = {
			Model,
			models: {},
			isset: ( obj, ...keys ) => {
				for ( const key of keys ) {
					if ( ! obj || ! obj.hasOwnProperty( key ) ) {
						return false;
					}
					obj = obj[ key ];
				}
				return true;
			},
			isget: ( obj, ...keys ) => {
				for ( const key of keys ) {
					if ( ! obj || ! obj.hasOwnProperty( key ) ) {
						return null;
					}
					obj = obj[ key ];
				}
				return obj;
			},
		};

		originalInitialize = jest.fn( function ( models, options ) {
			// Record the args as the original initialize sees them.
			this.seenArgs = { ...options.args };
		} );
		Query = function () {};
		Query.prototype.initialize = originalInitialize;
		global.wp = { media: { model: { Query } } };

		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf-media.js' );
		} );

		customizePrototypes.customizeQuery();
	} );

	afterEach( () => {
		delete global.acf;
		global.wp = originalWp;
		jest.resetModules();
	} );

	it( 'should wrap the Query initialize method', () => {
		expect( Query.prototype.initialize ).not.toBe( originalInitialize );
	} );

	it( 'should hide _acfuploader from the original initialize', () => {
		const query = new Query();
		const options = { args: { s: 'cat', _acfuploader: 'field_123' } };

		query.initialize( [], options );

		expect( originalInitialize ).toHaveBeenCalledTimes( 1 );
		expect( query.seenArgs ).toEqual( { s: 'cat' } );
	} );

	it( 'should restore _acfuploader after initialize', () => {
		const query = new Query();
		const options = { args: { s: 'cat', _acfuploader: 'field_123' } };

		query.initialize( [], options );

		expect( options.args ).toEqual( {
			s: 'cat',
			_acfuploader: 'field_123',
		} );
	} );

	it( 'should leave args without _acfuploader untouched', () => {
		const query = new Query();
		const options = { args: { s: 'cat' } };

		query.initialize( [], options );

		expect( query.seenArgs ).toEqual( { s: 'cat' } );
		expect( options.args ).toEqual( { s: 'cat' } );
	} );

	it( 'should not fail when the Query model is missing', () => {
		global.wp = { media: { model: {} } };

		expect( () => customizePrototypes.customizeQuery() ).not.toThrow();
	} );
} );
