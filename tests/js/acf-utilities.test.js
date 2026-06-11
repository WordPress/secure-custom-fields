/**
 * Unit tests for SCF core utility functions (_acf.js)
 *
 * Loads the real _acf.js module (plus _acf-hooks.js for the hook wrappers)
 * with a minimal jQuery stub and tests the pure utility API:
 * - data accessors (get/set/has)
 * - id generators (uniqueId/uniqid)
 * - string utilities (case conversion, sanitization, escaping)
 * - translation helpers (__ , _x, _n)
 * - object/array helpers (isset, isget, objectToArray, uniqueArray)
 * - function utilities (debounce, throttle, once)
 * - ajax response helpers (isAjaxSuccess, getAjaxMessage, getXhrError)
 * - action history wrappers (doingAction, didAction, currentAction)
 */

const { createJQueryStub } = require( './mocks/acf-jquery' );

describe( 'SCF Core Utilities', () => {
	let acf;

	beforeEach( () => {
		global.jQuery = createJQueryStub();
		global.$ = global.jQuery;
		delete window.acfL10n;

		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf.js' );
			require( '../../assets/src/js/_acf-hooks.js' );
		} );

		acf = window.acf;
	} );

	afterEach( () => {
		delete window.acf;
		delete window.acfL10n;
	} );

	describe( 'Data accessors', () => {
		it( 'should set and get data values', () => {
			acf.set( 'my_key', 'my_value' );

			expect( acf.get( 'my_key' ) ).toBe( 'my_value' );
		} );

		it( 'should return null for unknown keys', () => {
			expect( acf.get( 'unknown' ) ).toBeNull();
		} );

		it( 'should return null for falsy stored values', () => {
			// NOTE: documents current behavior — acf.get() uses `|| null`,
			// so stored falsy values (0, '', false) are unreadable.
			acf.set( 'zero', 0 );

			expect( acf.get( 'zero' ) ).toBeNull();
			expect( acf.has( 'zero' ) ).toBe( false );
		} );

		it( 'should support chaining on set()', () => {
			expect( acf.set( 'a', 1 ) ).toBe( acf );
		} );

		it( 'has() should reflect existence of truthy values', () => {
			acf.set( 'present', 'yes' );

			expect( acf.has( 'present' ) ).toBe( true );
			expect( acf.has( 'absent' ) ).toBe( false );
		} );
	} );

	describe( 'ID generators', () => {
		it( 'uniqueId() should increment on each call', () => {
			const first = acf.uniqueId();
			const second = acf.uniqueId();

			expect( parseInt( second, 10 ) ).toBe( parseInt( first, 10 ) + 1 );
		} );

		it( 'uniqueId() should apply a prefix', () => {
			expect( acf.uniqueId( 'acf' ) ).toMatch( /^acf\d+$/ );
		} );

		it( 'uniqid() should return a 13 character id by default', () => {
			expect( acf.uniqid() ).toHaveLength( 13 );
		} );

		it( 'uniqid() should prepend the prefix', () => {
			const id = acf.uniqid( 'foo' );

			expect( id ).toHaveLength( 16 );
			expect( id.startsWith( 'foo' ) ).toBe( true );
		} );

		it( 'uniqid() should add entropy when requested', () => {
			expect( acf.uniqid( 'bar', true ) ).toHaveLength( 23 + 3 );
		} );

		it( 'uniqid() should not generate duplicate ids consecutively', () => {
			expect( acf.uniqid() ).not.toBe( acf.uniqid() );
		} );
	} );

	describe( 'String utilities', () => {
		it( 'strReplace() should replace all occurrences', () => {
			expect( acf.strReplace( '_', '-', 'a_b_c' ) ).toBe( 'a-b-c' );
		} );

		it( 'strCamelCase() should convert snake_case', () => {
			expect( acf.strCamelCase( 'date_time_picker' ) ).toBe(
				'dateTimePicker'
			);
		} );

		it( 'strCamelCase() should convert kebab-case', () => {
			expect( acf.strCamelCase( 'show-field' ) ).toBe( 'showField' );
		} );

		it( 'strCamelCase() should return empty string for no matches', () => {
			expect( acf.strCamelCase( '___' ) ).toBe( '' );
		} );

		it( 'strPascalCase() should uppercase the first letter', () => {
			expect( acf.strPascalCase( 'google_map' ) ).toBe( 'GoogleMap' );
		} );

		it( 'strSlugify() should lowercase and replace underscores', () => {
			expect( acf.strSlugify( 'Field_Type_Name' ) ).toBe(
				'field-type-name'
			);
		} );

		it( 'strSanitize() should transliterate accented characters', () => {
			expect( acf.strSanitize( 'Café Münü' ) ).toBe( 'cafe_munu' );
		} );

		it( 'strSanitize() should strip punctuation and replace spaces', () => {
			expect( acf.strSanitize( "it's a (test)?" ) ).toBe( 'its_a_test' );
		} );

		it( 'strSanitize() should keep case when toLowerCase is false', () => {
			expect( acf.strSanitize( 'My Field', false ) ).toBe( 'My_Field' );
		} );

		it( 'strMatch() should count leading matching characters', () => {
			expect( acf.strMatch( 'hello', 'help' ) ).toBe( 3 );
			expect( acf.strMatch( 'abc', 'xyz' ) ).toBe( 0 );
			expect( acf.strMatch( 'same', 'same' ) ).toBe( 4 );
		} );

		it( 'strEscape() should escape HTML special characters', () => {
			expect( acf.strEscape( '<script>"a" & \'b\'</script>' ) ).toBe(
				'&lt;script&gt;&quot;a&quot; &amp; &#39;b&#39;&lt;/script&gt;'
			);
		} );

		it( 'strEscape() should cast non-strings to string', () => {
			expect( acf.strEscape( 123 ) ).toBe( '123' );
		} );

		it( 'strUnescape() should reverse strEscape()', () => {
			const original = '<b>Tom & "Jerry"</b>';

			expect( acf.strUnescape( acf.strEscape( original ) ) ).toBe(
				original
			);
		} );

		it( 'escAttr should be an alias of strEscape', () => {
			expect( acf.escAttr ).toBe( acf.strEscape );
		} );
	} );

	describe( 'parseArgs()', () => {
		it( 'should merge args over defaults', () => {
			const result = acf.parseArgs( { b: 2 }, { a: 1, b: 'default' } );

			expect( result ).toEqual( { a: 1, b: 2 } );
		} );

		it( 'should treat non-object args as empty', () => {
			expect( acf.parseArgs( 'nope', { a: 1 } ) ).toEqual( { a: 1 } );
			expect( acf.parseArgs( undefined, { a: 1 } ) ).toEqual( { a: 1 } );
		} );

		it( 'should not mutate the defaults object', () => {
			const defaults = { a: 1 };
			acf.parseArgs( { a: 2 }, defaults );

			expect( defaults.a ).toBe( 1 );
		} );
	} );

	describe( 'Translation helpers', () => {
		it( '__() should return the text when no translation exists', () => {
			expect( acf.__( 'Hello' ) ).toBe( 'Hello' );
		} );

		it( '__() should return the translation from acfL10n', () => {
			window.acfL10n.Hello = 'Hola';

			expect( acf.__( 'Hello' ) ).toBe( 'Hola' );
		} );

		it( '_x() should prefer the contextual translation', () => {
			window.acfL10n[ 'Post.noun' ] = 'Entrada';
			window.acfL10n.Post = 'Publicar';

			expect( acf._x( 'Post', 'noun' ) ).toBe( 'Entrada' );
			expect( acf._x( 'Post', 'verb' ) ).toBe( 'Publicar' );
		} );

		it( '_n() should select singular or plural by number', () => {
			expect( acf._n( 'One item', 'Many items', 1 ) ).toBe( 'One item' );
			expect( acf._n( 'One item', 'Many items', 0 ) ).toBe(
				'Many items'
			);
			expect( acf._n( 'One item', 'Many items', 5 ) ).toBe(
				'Many items'
			);
		} );
	} );

	describe( 'Type checks and object helpers', () => {
		it( 'isArray() should detect arrays only', () => {
			expect( acf.isArray( [] ) ).toBe( true );
			expect( acf.isArray( {} ) ).toBe( false );
			expect( acf.isArray( 'a' ) ).toBe( false );
		} );

		it( 'isObject() should detect objects', () => {
			expect( acf.isObject( {} ) ).toBe( true );
			expect( acf.isObject( [] ) ).toBe( true );
			expect( acf.isObject( 'a' ) ).toBe( false );
			// NOTE: documents current behavior — possible bug:
			// typeof null === 'object', so isObject( null ) returns true.
			expect( acf.isObject( null ) ).toBe( true );
		} );

		it( 'isNumeric() should detect numbers and numeric strings', () => {
			expect( acf.isNumeric( 12 ) ).toBe( true );
			expect( acf.isNumeric( '12' ) ).toBe( true );
			expect( acf.isNumeric( '1.5' ) ).toBe( true );
			expect( acf.isNumeric( '-3' ) ).toBe( true );
			expect( acf.isNumeric( 'abc' ) ).toBe( false );
			expect( acf.isNumeric( '' ) ).toBe( false );
			expect( acf.isNumeric( null ) ).toBe( false );
			expect( acf.isNumeric( Infinity ) ).toBe( false );
		} );

		it( 'isset() should check nested property existence', () => {
			const obj = { a: { b: { c: false } } };

			expect( acf.isset( obj, 'a', 'b', 'c' ) ).toBe( true );
			expect( acf.isset( obj, 'a', 'x' ) ).toBe( false );
			expect( acf.isset( null, 'a' ) ).toBe( false );
		} );

		it( 'isget() should return the nested value or null', () => {
			const obj = { a: { b: 'value' } };

			expect( acf.isget( obj, 'a', 'b' ) ).toBe( 'value' );
			expect( acf.isget( obj, 'a', 'missing' ) ).toBeNull();
		} );

		it( 'objectToArray() should return object values', () => {
			expect( acf.objectToArray( { a: 1, b: 2 } ) ).toEqual( [ 1, 2 ] );
		} );

		it( 'uniqueArray() should remove duplicate values', () => {
			expect( acf.uniqueArray( [ 1, 2, 2, 3, 1 ] ) ).toEqual( [
				1, 2, 3,
			] );
		} );

		it( 'arrayArgs() should convert array-like objects', () => {
			const args = ( function () {
				return acf.arrayArgs( arguments );
			} )( 'a', 'b' );

			expect( args ).toEqual( [ 'a', 'b' ] );
			expect( Array.isArray( args ) ).toBe( true );
		} );
	} );

	describe( 'Function utilities', () => {
		beforeEach( () => {
			jest.useFakeTimers();
		} );

		afterEach( () => {
			jest.useRealTimers();
		} );

		it( 'debounce() should run only the last call after the wait', () => {
			const callback = jest.fn();
			const debounced = acf.debounce( callback, 100 );

			debounced( 'first' );
			debounced( 'second' );
			jest.advanceTimersByTime( 99 );
			expect( callback ).not.toHaveBeenCalled();

			jest.advanceTimersByTime( 1 );
			expect( callback ).toHaveBeenCalledTimes( 1 );
			expect( callback ).toHaveBeenCalledWith( 'second' );
		} );

		it( 'throttle() should run immediately then ignore calls within the limit', () => {
			const callback = jest.fn();
			const throttled = acf.throttle( callback, 100 );

			throttled( 'first' );
			throttled( 'ignored' );
			expect( callback ).toHaveBeenCalledTimes( 1 );
			expect( callback ).toHaveBeenCalledWith( 'first' );

			jest.advanceTimersByTime( 100 );
			throttled( 'second' );
			expect( callback ).toHaveBeenCalledTimes( 2 );
		} );

		it( 'once() should only invoke the function a single time', () => {
			const callback = jest.fn( () => 'result' );
			const onced = acf.once( callback );

			expect( onced() ).toBe( 'result' );
			expect( onced() ).toBeUndefined();
			expect( onced() ).toBeUndefined();
			expect( callback ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'Ajax helpers', () => {
		it( 'isAjaxSuccess() should require a truthy success property', () => {
			expect( acf.isAjaxSuccess( { success: true } ) ).toBe( true );
			expect( acf.isAjaxSuccess( { success: false } ) ).toBe( false );
			expect( acf.isAjaxSuccess( null ) ).toBeFalsy();
		} );

		it( 'getAjaxMessage() should read data.message', () => {
			expect( acf.getAjaxMessage( { data: { message: 'Saved' } } ) ).toBe(
				'Saved'
			);
			expect( acf.getAjaxMessage( {} ) ).toBeNull();
		} );

		it( 'getAjaxError() should read data.error', () => {
			expect( acf.getAjaxError( { data: { error: 'Bad' } } ) ).toBe(
				'Bad'
			);
			expect( acf.getAjaxError( {} ) ).toBeNull();
		} );

		it( 'getXhrError() should prefer responseJSON.message', () => {
			expect(
				acf.getXhrError( {
					responseJSON: { message: 'WP_Error message' },
				} )
			).toBe( 'WP_Error message' );
		} );

		it( 'getXhrError() should fall back to responseJSON.data.error', () => {
			expect(
				acf.getXhrError( {
					responseJSON: { data: { error: 'json error' } },
				} )
			).toBe( 'json error' );
		} );

		it( 'getXhrError() should fall back to statusText', () => {
			expect( acf.getXhrError( { statusText: 'Not Found' } ) ).toBe(
				'Not Found'
			);
		} );

		it( 'getXhrError() should return empty string when nothing matches', () => {
			expect( acf.getXhrError( {} ) ).toBe( '' );
		} );
	} );

	describe( 'Action history wrappers', () => {
		it( 'didAction() should be false before and true after doAction()', () => {
			expect( acf.didAction( 'my_action' ) ).toBe( false );

			acf.doAction( 'my_action' );

			expect( acf.didAction( 'my_action' ) ).toBe( true );
		} );

		it( 'doingAction() should only be true while the action runs', () => {
			let doingDuringCallback = null;

			acf.addAction( 'my_action', () => {
				doingDuringCallback = acf.doingAction( 'my_action' );
			} );

			expect( acf.doingAction( 'my_action' ) ).toBe( false );
			acf.doAction( 'my_action' );

			expect( doingDuringCallback ).toBe( true );
			expect( acf.doingAction( 'my_action' ) ).toBe( false );
		} );

		it( 'currentAction() should return the running action name', () => {
			let current = null;

			acf.addAction( 'running_action', () => {
				current = acf.currentAction();
			} );
			acf.doAction( 'running_action' );

			expect( current ).toBe( 'running_action' );
			expect( acf.currentAction() ).toBe( false );
		} );

		it( 'addAction()/doAction() should pass arguments through acf.hooks', () => {
			const callback = jest.fn();

			acf.addAction( 'pass_args', callback );
			acf.doAction( 'pass_args', 'a', 'b' );

			expect( callback ).toHaveBeenCalledWith( 'a', 'b' );
		} );

		it( 'applyFilters() should chain through registered filters', () => {
			acf.addFilter( 'my_filter', ( value ) => value + 1 );
			acf.addFilter( 'my_filter', ( value ) => value * 10, 5 );

			expect( acf.applyFilters( 'my_filter', 2 ) ).toBe( 21 );
		} );

		it( 'removeAction() should unregister a callback', () => {
			const callback = jest.fn();

			acf.addAction( 'removable', callback );
			acf.removeAction( 'removable', callback );
			acf.doAction( 'removable' );

			expect( callback ).not.toHaveBeenCalled();
		} );

		it( 'removeFilter() should unregister a filter', () => {
			const filter = ( value ) => value + '-changed';

			acf.addFilter( 'removable_filter', filter );
			acf.removeFilter( 'removable_filter', filter );

			expect( acf.applyFilters( 'removable_filter', 'value' ) ).toBe(
				'value'
			);
		} );
	} );

	describe( 'encode() / decode()', () => {
		it( 'encode() should convert HTML to entities', () => {
			expect( acf.encode( '<b>bold</b>' ) ).toBe(
				'&lt;b&gt;bold&lt;/b&gt;'
			);
		} );

		it( 'decode() should convert entities back to HTML', () => {
			expect( acf.decode( '&lt;b&gt;bold&lt;/b&gt;' ) ).toBe(
				'<b>bold</b>'
			);
		} );

		it( 'decode() should reverse encode()', () => {
			const original = 'Fish & "Chips" <i>now</i>';

			expect( acf.decode( acf.encode( original ) ) ).toBe( original );
		} );
	} );
} );
