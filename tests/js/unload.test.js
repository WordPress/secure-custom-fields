/**
 * Unit tests for the SCF unsaved-changes warning (_acf-unload.js)
 *
 * acf.unload is a Model that warns users before navigating away from a
 * page with unsaved field changes. These tests load the real Model class
 * and hook system, then verify:
 * - deferred initialization on the 'load' action
 * - beforeunload listener management (start/stop/disable)
 * - integration with validation actions
 */

const { createJQueryStub } = require( './mocks/acf-jquery' );

describe( 'SCF Unload Warning', () => {
	let acf;
	let addListenerSpy;
	let removeListenerSpy;

	const beforeUnloadCalls = ( spy ) =>
		spy.mock.calls.filter( ( call ) => call[ 0 ] === 'beforeunload' );

	beforeEach( () => {
		global.jQuery = createJQueryStub();
		global.$ = global.jQuery;
		delete window.acfL10n;

		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf.js' );
			require( '../../assets/src/js/_acf-hooks.js' );
			require( '../../assets/src/js/_acf-model.js' );
			require( '../../assets/src/js/_acf-unload.js' );
		} );

		acf = window.acf;

		// The model waits for the 'load' action before initializing.
		acf.doAction( 'load' );
		addListenerSpy = jest.spyOn( window, 'addEventListener' );
		removeListenerSpy = jest.spyOn( window, 'removeEventListener' );
	} );

	afterEach( () => {
		window.removeEventListener( 'beforeunload', acf.unload.onUnload );
		jest.restoreAllMocks();
		delete window.acf;
		delete window.acfL10n;
	} );

	it( 'should expose an active, unchanged model by default', () => {
		expect( acf.unload ).toBeDefined();
		expect( acf.unload.active ).toBe( true );
		expect( acf.unload.changed ).toBe( false );
	} );

	it( 'should wait for the load action before binding events', () => {
		// Re-require without firing 'load': events are not bound yet.
		let lateAcf;
		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf.js' );
			require( '../../assets/src/js/_acf-hooks.js' );
			require( '../../assets/src/js/_acf-model.js' );
			require( '../../assets/src/js/_acf-unload.js' );
			lateAcf = window.acf;
		} );

		expect( lateAcf.didAction( 'load' ) ).toBe( false );
		expect( lateAcf.unload.changed ).toBe( false );

		// Initialization is queued until the wait action fires.
		lateAcf.doAction( 'load' );
		lateAcf.doAction( 'validation_failure' );

		expect( lateAcf.unload.changed ).toBe( true );

		window.removeEventListener( 'beforeunload', lateAcf.unload.onUnload );
	} );

	describe( 'startListening()', () => {
		it( 'should mark as changed and bind beforeunload', () => {
			acf.unload.startListening();

			expect( acf.unload.changed ).toBe( true );
			expect( addListenerSpy ).toHaveBeenCalledWith(
				'beforeunload',
				acf.unload.onUnload
			);
		} );

		it( 'should not bind twice when already changed', () => {
			acf.unload.startListening();
			acf.unload.startListening();

			expect( beforeUnloadCalls( addListenerSpy ) ).toHaveLength( 1 );
		} );

		it( 'should do nothing while disabled', () => {
			acf.unload.disable();
			acf.unload.startListening();

			expect( acf.unload.changed ).toBe( false );
			expect( beforeUnloadCalls( addListenerSpy ) ).toHaveLength( 0 );

			acf.unload.enable();
			acf.unload.startListening();

			expect( acf.unload.changed ).toBe( true );
		} );
	} );

	describe( 'stopListening()', () => {
		it( 'should reset changed and unbind beforeunload', () => {
			acf.unload.startListening();
			acf.unload.stopListening();

			expect( acf.unload.changed ).toBe( false );
			expect( removeListenerSpy ).toHaveBeenCalledWith(
				'beforeunload',
				acf.unload.onUnload
			);
		} );

		it( 'reset() should behave like stopListening()', () => {
			acf.unload.startListening();
			acf.unload.reset();

			expect( acf.unload.changed ).toBe( false );
			expect( beforeUnloadCalls( removeListenerSpy ) ).toHaveLength( 1 );
		} );
	} );

	describe( 'validation actions', () => {
		it( 'validation_failure should start listening', () => {
			acf.doAction( 'validation_failure' );

			expect( acf.unload.changed ).toBe( true );
		} );

		it( 'validation_success should stop listening', () => {
			acf.doAction( 'validation_failure' );
			acf.doAction( 'validation_success' );

			expect( acf.unload.changed ).toBe( false );
		} );
	} );

	describe( 'onUnload()', () => {
		it( 'should return the warning message', () => {
			expect( acf.unload.onUnload() ).toBe(
				'The changes you made will be lost if you navigate away from this page'
			);
		} );

		it( 'should return the translated message when available', () => {
			window.acfL10n[
				'The changes you made will be lost if you navigate away from this page'
			] = 'Translated warning';

			expect( acf.unload.onUnload() ).toBe( 'Translated warning' );
		} );
	} );
} );
