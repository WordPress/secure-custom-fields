/**
 * Unit tests for the settings page tab registry.
 */

describe( 'settings tab registry', () => {
	let registry;

	beforeEach( () => {
		jest.resetModules();
		registry = require( '../../../assets/src/js/settings-page/settings-tab-registry' );
	} );

	it( 'registers and returns a tab', () => {
		const definition = { mount: jest.fn(), unmount: jest.fn() };

		registry.registerTab( 'demo', definition );

		expect( registry.getTab( 'demo' ) ).toBe( definition );
		expect( registry.getTab( 'missing' ) ).toBeUndefined();
	} );

	it( 'rejects a tab without a mount function', () => {
		const error = jest.spyOn( console, 'error' ).mockImplementation();

		registry.registerTab( 'broken', { unmount: jest.fn() } );
		registry.registerTab( 'empty' );

		expect( registry.getTab( 'broken' ) ).toBeUndefined();
		expect( registry.getTab( 'empty' ) ).toBeUndefined();
		expect( error ).toHaveBeenCalledTimes( 2 );
		error.mockRestore();
	} );

	it( 'notifies subscribers until they unsubscribe', () => {
		const listener = jest.fn();
		const unsubscribe = registry.subscribe( listener );

		registry.registerTab( 'one', { mount: jest.fn() } );
		expect( listener ).toHaveBeenCalledTimes( 1 );

		unsubscribe();
		registry.registerTab( 'two', { mount: jest.fn() } );
		expect( listener ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'replaces a tab registered twice', () => {
		const first = { mount: jest.fn() };
		const second = { mount: jest.fn() };

		registry.registerTab( 'demo', first );
		registry.registerTab( 'demo', second );

		expect( registry.getTab( 'demo' ) ).toBe( second );
	} );

	it( 'exposes acf.settingsPage.registerTab without replacing window.acf', () => {
		const win = { acf: { existing: true } };

		registry.exposeRegistry( win );

		expect( win.acf.existing ).toBe( true );
		expect( win.acf.settingsPage.registerTab ).toBe( registry.registerTab );

		const definition = { mount: jest.fn() };
		win.acf.settingsPage.registerTab( 'exposed', definition );
		expect( registry.getTab( 'exposed' ) ).toBe( definition );
	} );

	it( 'creates window.acf when it does not exist', () => {
		const win = {};

		registry.exposeRegistry( win );

		expect( typeof win.acf.settingsPage.registerTab ).toBe( 'function' );
	} );
} );
