/**
 * Unit tests for the SCF backwards-compatibility layer (_acf-compatibility.js)
 *
 * The compatibility layer keeps legacy snake_case APIs working by injecting
 * a prototype between the acf object and its original prototype. It is a
 * common regression point during backports, so the pure parts are covered:
 * - acf.newCompatibility() / acf.getCompatibility() prototype wiring
 * - legacy function aliases (acf.update, acf.add_action, ...)
 * - legacy selector building (acf.get_selector)
 * - legacy translation helper (acf._e)
 * - maybe_get() dot-path lookups
 * - add_action() multi-action registration and $field argument conversion
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { createJQueryStub } = require( './mocks/acf-jquery' );

const compatibilitySource = fs.readFileSync(
	path.resolve( __dirname, '../../assets/src/js/_acf-compatibility.js' ),
	'utf8'
);

describe( 'SCF Compatibility Layer', () => {
	let acf;

	beforeEach( () => {
		global.jQuery = createJQueryStub();
		global.$ = global.jQuery;

		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf.js' );
			require( '../../assets/src/js/_acf-hooks.js' );
			require( '../../assets/src/js/_acf-model.js' );
		} );

		// _acf-compatibility.js wraps these objects at load time; in the
		// real bundle they come from modules out of scope for this test.
		window.acf.validation = {};
		window.acf.screen = { check: jest.fn(), set: jest.fn() };

		// The production webpack bundle executes this module in sloppy
		// (non-strict) mode and the legacy code relies on it: maybe_get()
		// assigns to an undeclared `keys` variable and add_action() relies
		// on `arguments` aliasing its named parameters. babel-jest compiles
		// required modules to strict mode which breaks both, so evaluate
		// the raw source instead to match production semantics.
		// eslint-disable-next-line no-eval
		( 0, eval )( compatibilitySource );

		acf = window.acf;

		// In production acf.Field is defined by _acf-field.js before any
		// legacy callback runs; the compatibility wrapper checks
		// `arg instanceof acf.Field` at call time.
		acf.Field = function () {};
	} );

	afterEach( () => {
		delete window.acf;
		delete window.acfL10n;
	} );

	describe( 'newCompatibility() / getCompatibility()', () => {
		it( 'should inject the layer into the prototype chain', () => {
			const instance = { own: true };
			const layer = acf.newCompatibility( instance, {
				legacy: 'value',
			} );

			expect( instance.legacy ).toBe( 'value' );
			expect( instance.own ).toBe( true );
			expect( Object.getPrototypeOf( instance ) ).toBe( layer );
		} );

		it( 'should not shadow own properties of the instance', () => {
			const instance = { name: 'real' };
			acf.newCompatibility( instance, { name: 'legacy' } );

			expect( instance.name ).toBe( 'real' );
		} );

		it( 'getCompatibility() should return the registered layer', () => {
			const instance = {};
			const layer = acf.newCompatibility( instance, {} );

			expect( acf.getCompatibility( instance ) ).toBe( layer );
			expect( acf.getCompatibility( {} ) ).toBeNull();
		} );

		it( 'should register a compatibility layer on the acf object itself', () => {
			expect( acf.getCompatibility( acf ) ).not.toBeNull();
		} );
	} );

	describe( 'Legacy aliases', () => {
		it( 'should alias renamed functions to their new versions', () => {
			expect( acf.update ).toBe( acf.set );
			expect( acf.add_action ).not.toBeUndefined();
			expect( acf.do_action ).toBe( acf.doAction );
			expect( acf.apply_filters ).toBe( acf.applyFilters );
			expect( acf.parse_args ).toBe( acf.parseArgs );
			expect( acf.str_replace ).toBe( acf.strReplace );
			expect( acf.esc_html ).toBe( acf.strEscape );
			expect( acf.str_sanitize ).toBe( acf.strSanitize );
			expect( acf.get_uniqid ).toBe( acf.uniqid );
			expect( acf.serialize_form ).toBe( acf.serialize );
			expect( acf.is_ajax_success ).toBe( acf.isAjaxSuccess );
		} );

		it( 'legacy storage objects should exist', () => {
			expect( acf.l10n ).toEqual( {} );
			expect( acf.o ).toEqual( {} );
		} );
	} );

	describe( '_e()', () => {
		it( 'should translate known compatibility keys', () => {
			expect( acf._e( 'image', 'select' ) ).toBe( 'Select Image' );
			expect( acf._e( 'image', 'edit' ) ).toBe( 'Edit Image' );
			expect( acf._e( 'image', 'update' ) ).toBe( 'Update Image' );
		} );

		it( 'should read from the legacy l10n storage', () => {
			acf.l10n.address = 'Address';

			expect( acf._e( 'address' ) ).toBe( 'Address' );
		} );

		it( 'should read nested l10n values with two keys', () => {
			acf.l10n.relationship = { max: 'Maximum reached' };

			expect( acf._e( 'relationship', 'max' ) ).toBe( 'Maximum reached' );
		} );

		it( 'should return an empty string for unknown keys', () => {
			expect( acf._e( 'missing' ) ).toBe( '' );
			expect( acf._e( 'missing', 'nope' ) ).toBe( '' );
			// NOTE: documents current behavior — possible bug: when k1 is
			// unknown, _e() indexes into the empty string, so a k2 naming a
			// String.prototype method (e.g. 'sub') returns that function
			// instead of ''.
			expect( typeof acf._e( 'missing', 'sub' ) ).toBe( 'function' );
		} );
	} );

	describe( 'get_selector()', () => {
		it( 'should return the base selector with no argument', () => {
			expect( acf.get_selector() ).toBe( '.acf-field' );
		} );

		it( 'should append the field type', () => {
			expect( acf.get_selector( 'image' ) ).toBe( '.acf-field-image' );
		} );

		it( 'should convert underscores to dashes', () => {
			expect( acf.get_selector( 'date_picker' ) ).toBe(
				'.acf-field-date-picker'
			);
		} );

		it( 'should de-duplicate the field- prefix for field keys', () => {
			expect( acf.get_selector( 'field_123abc' ) ).toBe(
				'.acf-field-123abc'
			);
		} );

		it( 'should accept a legacy object argument', () => {
			expect( acf.get_selector( { type: 'select' } ) ).toBe(
				'.acf-field-select'
			);
			expect( acf.get_selector( {} ) ).toBe( '.acf-field' );
		} );
	} );

	describe( 'maybe_get()', () => {
		const obj = { a: { b: { c: 'found' } }, top: 'level' };

		it( 'should resolve dot-separated paths', () => {
			expect( acf.maybe_get( obj, 'a.b.c' ) ).toBe( 'found' );
			expect( acf.maybe_get( obj, 'top' ) ).toBe( 'level' );
		} );

		it( 'should return null by default for missing paths', () => {
			expect( acf.maybe_get( obj, 'a.missing' ) ).toBeNull();
		} );

		it( 'should return the provided default for missing paths', () => {
			expect( acf.maybe_get( obj, 'a.missing', 'fallback' ) ).toBe(
				'fallback'
			);
		} );
	} );

	describe( 'add_action()', () => {
		it( 'should register and fire a legacy action', () => {
			const callback = jest.fn();

			acf.add_action( 'legacy_action', callback );
			acf.doAction( 'legacy_action', 'arg' );

			expect( callback ).toHaveBeenCalledWith( 'arg' );
		} );

		it( 'should register multiple space-separated actions', () => {
			const callback = jest.fn();

			acf.add_action( 'ready append', callback );
			acf.doAction( 'ready', 'a' );
			acf.doAction( 'append', 'b' );

			expect( callback ).toHaveBeenCalledTimes( 2 );
		} );

		it( 'should convert acf.Field instances to their $el', () => {
			// The legacy API expected jQuery elements, not Field objects.
			acf.Field = function () {
				this.$el = 'the-element';
			};
			const field = new acf.Field();
			const callback = jest.fn();

			acf.add_action( 'show_field', callback );
			acf.doAction( 'show_field', field );

			expect( callback ).toHaveBeenCalledWith( 'the-element' );
		} );

		it( 'should pass $(document) when the action has no arguments', () => {
			const callback = jest.fn();

			acf.add_action( 'ready', callback );
			acf.doAction( 'ready' );

			expect( callback ).toHaveBeenCalledWith(
				global.jQuery( document )
			);
		} );
	} );

	describe( 'add_filter()', () => {
		it( 'should register and apply a legacy filter', () => {
			acf.add_filter( 'legacy_filter', ( value ) => value + 1 );

			expect( acf.applyFilters( 'legacy_filter', 1 ) ).toBe( 2 );
		} );
	} );

	describe( 'Legacy model', () => {
		it( 'extend() should merge properties and register actions', () => {
			const onReady = jest.fn();

			const model = acf.model.extend( {
				label: 'legacy',
				actions: { custom_event: 'onCustom' },
				onCustom: onReady,
			} );

			expect( model.label ).toBe( 'legacy' );

			acf.doAction( 'custom_event', 'payload' );
			expect( onReady ).toHaveBeenCalledWith( 'payload' );
		} );

		it( 'get()/set() should read and write model properties', () => {
			const model = acf.model.extend( {} );

			expect( model.get( 'missing' ) ).toBeNull();
			expect( model.get( 'missing', 'fallback' ) ).toBe( 'fallback' );

			model.set( 'name', 'value' );
			expect( model.get( 'name' ) ).toBe( 'value' );
		} );

		it( 'set() should invoke the matching _set_ callback', () => {
			const model = acf.model.extend( {
				_set_status: jest.fn(),
			} );

			model.set( 'status', 'ready' );

			expect( model._set_status ).toHaveBeenCalled();
		} );
	} );
} );
