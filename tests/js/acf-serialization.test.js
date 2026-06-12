/**
 * Unit tests for SCF serialization, normalization and stateful helpers
 * in _acf.js:
 * - acf.serialize() name parsing into nested objects/arrays
 * - acf.serializeForAjax()
 * - flexible content / numeric-key normalization (SCF 6.6.0 additions)
 * - acf.prepareForAjax()
 * - user preferences stored in localStorage
 * - element locks (acf.lock / unlock / isLocked)
 * - visibility helpers (acf.show / hide) and acf.val()
 * - acf.renderSelect() output escaping
 * - acf.escHtml() sanitization through DOMPurify
 */

const { createJQueryStub } = require( './mocks/acf-jquery' );

/**
 * Builds a fake jQuery element whose find().serializeArray() returns the
 * given name/value pairs, mimicking form inputs.
 *
 * @param {Array} inputs Array of { name, value } objects.
 * @return {Object} Fake jQuery element.
 */
const createFormStub = ( inputs ) => ( {
	find: jest.fn( () => ( {
		serializeArray: () => inputs.map( ( input ) => ( { ...input } ) ),
	} ) ),
} );

describe( 'SCF Serialization and State Helpers', () => {
	let acf;

	beforeEach( () => {
		window.localStorage.clear();
		global.jQuery = createJQueryStub();
		global.$ = global.jQuery;

		jest.isolateModules( () => {
			require( '../../assets/src/js/_acf.js' );
			require( '../../assets/src/js/_acf-hooks.js' );
		} );

		acf = window.acf;
	} );

	afterEach( () => {
		delete window.acf;
		delete window.acfL10n;
		window.localStorage.clear();
	} );

	describe( 'serialize()', () => {
		it( 'should build a nested object from bracketed names', () => {
			const $el = createFormStub( [
				{ name: 'acf[field_1]', value: 'one' },
				{ name: 'acf[field_2]', value: 'two' },
			] );

			expect( acf.serialize( $el ) ).toEqual( {
				acf: { field_1: 'one', field_2: 'two' },
			} );
		} );

		it( 'should collect repeated [] names into arrays', () => {
			const $el = createFormStub( [
				{ name: 'acf[field_1][]', value: 'a' },
				{ name: 'acf[field_1][]', value: 'b' },
			] );

			expect( acf.serialize( $el ) ).toEqual( {
				acf: { field_1: [ 'a', 'b' ] },
			} );
		} );

		it( 'should handle deeply nested names', () => {
			const $el = createFormStub( [
				{ name: 'acf[row_0][sub_field]', value: 'deep' },
			] );

			expect( acf.serialize( $el ) ).toEqual( {
				acf: { row_0: { sub_field: 'deep' } },
			} );
		} );

		it( 'should strip the prefix and ignore other inputs', () => {
			const $el = createFormStub( [
				{ name: 'acf[field_1]', value: 'kept' },
				{ name: 'other[field_2]', value: 'dropped' },
			] );

			expect( acf.serialize( $el, 'acf' ) ).toEqual( {
				field_1: 'kept',
			} );
		} );
	} );

	describe( 'serializeForAjax()', () => {
		it( 'should map names to values', () => {
			const $el = createFormStub( [
				{ name: 'post_title', value: 'Hello' },
				{ name: 'post_status', value: 'draft' },
			] );

			expect( acf.serializeForAjax( $el ) ).toEqual( {
				post_title: 'Hello',
				post_status: 'draft',
			} );
		} );

		it( 'should group [] suffixed names into arrays', () => {
			const $el = createFormStub( [
				{ name: 'terms[]', value: '1' },
				{ name: 'terms[]', value: '2' },
				{ name: 'single', value: 'x' },
			] );

			expect( acf.serializeForAjax( $el ) ).toEqual( {
				'terms[]': [ '1', '2' ],
				single: 'x',
			} );
		} );
	} );

	describe( 'isFlexibleContentData()', () => {
		it( 'should detect rows containing acf_fc_layout', () => {
			const value = {
				row_0: { acf_fc_layout: 'hero', title: 'Hi' },
			};

			expect( acf.isFlexibleContentData( value ) ).toBe( true );
		} );

		it( 'should skip the acfcloneindex key', () => {
			const value = {
				acfcloneindex: { acf_fc_layout: 'clone' },
			};

			expect( acf.isFlexibleContentData( value ) ).toBe( false );
		} );

		it( 'should return false for plain objects and primitives', () => {
			expect( acf.isFlexibleContentData( { a: 1 } ) ).toBe( false );
			expect( acf.isFlexibleContentData( 'string' ) ).toBe( false );
			expect( acf.isFlexibleContentData( 42 ) ).toBe( false );
		} );
	} );

	describe( 'normalizeFlexibleContentData()', () => {
		it( 'should convert numeric-keyed objects to sorted arrays', () => {
			const result = acf.normalizeFlexibleContentData( {
				checkboxes: { 0: 'one', 2: 'three', 1: 'two' },
			} );

			expect( result.checkboxes ).toEqual( [ 'one', 'two', 'three' ] );
		} );

		it( 'should order by numeric value, supporting leading zeros', () => {
			const result = acf.normalizeFlexibleContentData( {
				values: { '010': 'ten', 2: 'two', '0001': 'one' },
			} );

			expect( result.values ).toEqual( [ 'one', 'two', 'ten' ] );
		} );

		it( 'should not convert objects with mixed keys', () => {
			const result = acf.normalizeFlexibleContentData( {
				mixed: { 0: 'zero', name: 'keep' },
			} );

			expect( result.mixed ).toEqual( { 0: 'zero', name: 'keep' } );
		} );

		it( 'should convert flexible content rows to arrays and drop acfcloneindex', () => {
			const result = acf.normalizeFlexibleContentData( {
				flex: {
					acfcloneindex: { acf_fc_layout: 'clone' },
					unique_id_a: { acf_fc_layout: 'hero', title: 'A' },
					unique_id_b: { acf_fc_layout: 'text', body: 'B' },
				},
			} );

			expect( result.flex ).toEqual( [
				{ acf_fc_layout: 'hero', title: 'A' },
				{ acf_fc_layout: 'text', body: 'B' },
			] );
		} );

		it( 'should recurse into nested structures', () => {
			const result = acf.normalizeFlexibleContentData( {
				group: {
					inner: { 1: 'b', 0: 'a' },
				},
			} );

			expect( result.group.inner ).toEqual( [ 'a', 'b' ] );
		} );

		it( 'should pass primitives through unchanged', () => {
			const result = acf.normalizeFlexibleContentData( {
				title: 'Hello',
				count: 5,
			} );

			expect( result ).toEqual( { title: 'Hello', count: 5 } );
		} );

		it( 'should return non-object input unchanged', () => {
			expect( acf.normalizeFlexibleContentData( 'text' ) ).toBe( 'text' );
		} );
	} );

	describe( 'prepareForAjax()', () => {
		beforeEach( () => {
			acf.set( 'nonce', 'global-nonce' );
			acf.set( 'post_id', 123 );
		} );

		it( 'should add the global nonce when none is provided', () => {
			const data = acf.prepareForAjax( { action: 'scf/test' } );

			expect( data.nonce ).toBe( 'global-nonce' );
			expect( data.post_id ).toBe( 123 );
		} );

		it( 'should keep an existing nonce by default', () => {
			const data = acf.prepareForAjax( { nonce: 'custom' } );

			expect( data.nonce ).toBe( 'custom' );
		} );

		it( 'should force the global nonce when requested', () => {
			const data = acf.prepareForAjax( { nonce: 'custom' }, true );

			expect( data.nonce ).toBe( 'global-nonce' );
		} );

		it( 'should add language when set', () => {
			expect( acf.prepareForAjax( {} ).lang ).toBeUndefined();

			acf.set( 'language', 'en_US' );

			expect( acf.prepareForAjax( {} ).lang ).toBe( 'en_US' );
		} );

		it( 'should run data through the prepare_for_ajax filter', () => {
			acf.addFilter( 'prepare_for_ajax', ( data ) => {
				data.filtered = true;
				return data;
			} );

			expect( acf.prepareForAjax( {} ).filtered ).toBe( true );
		} );
	} );

	describe( 'Preferences (localStorage)', () => {
		it( 'should store and retrieve a preference', () => {
			acf.setPreference( 'sidebar', 'open' );

			expect( acf.getPreference( 'sidebar' ) ).toBe( 'open' );
			expect(
				JSON.parse( window.localStorage.getItem( 'acf' ) ).sidebar
			).toBe( 'open' );
		} );

		it( 'should return null for unknown preferences', () => {
			expect( acf.getPreference( 'unknown' ) ).toBeNull();
		} );

		it( 'should remove a preference', () => {
			acf.setPreference( 'temp', 'value' );
			acf.removePreference( 'temp' );

			expect( acf.getPreference( 'temp' ) ).toBeNull();
			expect(
				JSON.parse( window.localStorage.getItem( 'acf' ) ).temp
			).toBeUndefined();
		} );

		it( 'should scope "this." preferences to the current post id', () => {
			acf.set( 'post_id', 99 );
			acf.setPreference( 'this.collapsed', [ 'field_1' ] );

			expect(
				JSON.parse( window.localStorage.getItem( 'acf' ) )[
					'collapsed-99'
				]
			).toEqual( [ 'field_1' ] );
			expect( acf.getPreference( 'this.collapsed' ) ).toEqual( [
				'field_1',
			] );
		} );
	} );

	describe( 'Locks', () => {
		let $el;

		beforeEach( () => {
			$el = global.jQuery( { fake: 'element' } );
		} );

		it( 'should report no lock initially', () => {
			expect( acf.isLocked( $el, 'hidden' ) ).toBe( false );
		} );

		it( 'should lock and unlock with a key', () => {
			acf.lock( $el, 'hidden', 'conditional_logic' );
			expect( acf.isLocked( $el, 'hidden' ) ).toBe( true );

			const unlocked = acf.unlock( $el, 'hidden', 'conditional_logic' );
			expect( unlocked ).toBe( true );
			expect( acf.isLocked( $el, 'hidden' ) ).toBe( false );
		} );

		it( 'should stay locked until all keys are removed', () => {
			acf.lock( $el, 'hidden', 'key1' );
			acf.lock( $el, 'hidden', 'key2' );

			expect( acf.unlock( $el, 'hidden', 'key1' ) ).toBe( false );
			expect( acf.isLocked( $el, 'hidden' ) ).toBe( true );
			expect( acf.unlock( $el, 'hidden', 'key2' ) ).toBe( true );
		} );

		it( 'should not add the same key twice', () => {
			acf.lock( $el, 'hidden', 'key1' );
			acf.lock( $el, 'hidden', 'key1' );

			expect( acf.unlock( $el, 'hidden', 'key1' ) ).toBe( true );
		} );

		it( 'should track lock types independently', () => {
			acf.lock( $el, 'hidden', 'key1' );

			expect( acf.isLocked( $el, 'disabled' ) ).toBe( false );
		} );
	} );

	describe( 'Visibility helpers', () => {
		let $el;

		beforeEach( () => {
			$el = global.jQuery( { visible: 'element' } );
		} );

		it( 'hide() should add the acf-hidden class and report change', () => {
			expect( acf.hide( $el ) ).toBe( true );
			expect( $el.hasClass( 'acf-hidden' ) ).toBe( true );
			expect( acf.isHidden( $el ) ).toBe( true );
			expect( acf.isVisible( $el ) ).toBe( false );
		} );

		it( 'hide() should return false when already hidden', () => {
			acf.hide( $el );

			expect( acf.hide( $el ) ).toBe( false );
		} );

		it( 'show() should remove the acf-hidden class', () => {
			acf.hide( $el );

			expect( acf.show( $el ) ).toBe( true );
			expect( acf.isVisible( $el ) ).toBe( true );
		} );

		it( 'show() should return false when not hidden', () => {
			expect( acf.show( $el ) ).toBe( false );
		} );

		it( 'show() should bail while another lock key remains', () => {
			acf.hide( $el, 'lock_a' );
			acf.hide( $el, 'lock_b' );

			expect( acf.show( $el, 'lock_a' ) ).toBe( false );
			expect( acf.isHidden( $el ) ).toBe( true );

			expect( acf.show( $el, 'lock_b' ) ).toBe( true );
			expect( acf.isHidden( $el ) ).toBe( false );
		} );
	} );

	describe( 'val()', () => {
		const createInput = ( initial, options ) => {
			let current = initial;
			const input = {
				isSelect: !! ( options && options.isSelect ),
				allowed: options && options.allowed,
				val( value ) {
					if ( value === undefined ) {
						return current;
					}
					if (
						this.isSelect &&
						this.allowed &&
						this.allowed.indexOf( value ) === -1
					) {
						current = null;
						return this;
					}
					current = value;
					return this;
				},
				is: ( selector ) => selector === 'select' && input.isSelect,
				trigger: jest.fn(),
			};
			return input;
		};

		it( 'should update the value and trigger change', () => {
			const $input = createInput( 'old' );

			expect( acf.val( $input, 'new' ) ).toBe( true );
			expect( $input.val() ).toBe( 'new' );
			expect( $input.trigger ).toHaveBeenCalledWith( 'change' );
		} );

		it( 'should bail when the value is unchanged', () => {
			const $input = createInput( 'same' );

			expect( acf.val( $input, 'same' ) ).toBe( false );
			expect( $input.trigger ).not.toHaveBeenCalled();
		} );

		it( 'should not trigger change when silent', () => {
			const $input = createInput( 'old' );

			expect( acf.val( $input, 'new', true ) ).toBe( true );
			expect( $input.trigger ).not.toHaveBeenCalled();
		} );

		it( 'should revert selects when the option does not exist', () => {
			const $input = createInput( 'a', {
				isSelect: true,
				allowed: [ 'a', 'b' ],
			} );

			expect( acf.val( $input, 'missing' ) ).toBe( false );
			expect( $input.val() ).toBe( 'a' );
			expect( $input.trigger ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'renderSelect()', () => {
		const createSelect = ( initial ) => {
			const state = { value: initial, html: '' };
			return {
				state,
				val( value ) {
					if ( value === undefined ) {
						return state.value;
					}
					state.value = value;
					return this;
				},
				html( markup ) {
					state.html = markup;
					return this;
				},
			};
		};

		it( 'should render option elements for choices', () => {
			const $select = createSelect( '1' );

			acf.renderSelect( $select, [
				{ id: '1', text: 'One' },
				{ id: '2', text: 'Two' },
			] );

			expect( $select.state.html ).toBe(
				'<option value="1">One</option><option value="2">Two</option>'
			);
		} );

		it( 'should escape HTML in values and labels', () => {
			const $select = createSelect( '' );

			acf.renderSelect( $select, [
				{ id: '"><script>', text: '<b>Bold & Co</b>' },
			] );

			expect( $select.state.html ).toContain(
				'value="&quot;&gt;&lt;script&gt;"'
			);
			expect( $select.state.html ).toContain(
				'&lt;b&gt;Bold &amp; Co&lt;/b&gt;'
			);
			expect( $select.state.html ).not.toContain( '<script>' );
		} );

		it( 'should render optgroups for nested choices', () => {
			const $select = createSelect( '' );

			acf.renderSelect( $select, [
				{
					text: 'Group',
					children: [ { id: 'a', text: 'A' } ],
				},
			] );

			expect( $select.state.html ).toBe(
				'<optgroup label="Group"><option value="a">A</option></optgroup>'
			);
		} );

		it( 'should keep the current value when it still exists', () => {
			const $select = createSelect( '2' );

			const value = acf.renderSelect( $select, [
				{ id: '1', text: 'One' },
				{ id: '2', text: 'Two' },
			] );

			expect( value ).toBe( '2' );
		} );

		it( 'should mark disabled choices', () => {
			const $select = createSelect( '' );

			acf.renderSelect( $select, [
				{ id: '1', text: 'One', disabled: true },
			] );

			expect( $select.state.html ).toContain( 'disabled="disabled"' );
		} );
	} );

	describe( 'escHtml() (DOMPurify)', () => {
		it( 'should strip script tags', () => {
			expect(
				acf.escHtml( '<p>safe</p><script>alert(1)</script>' )
			).toBe( '<p>safe</p>' );
		} );

		it( 'should strip iframes and forms', () => {
			expect(
				acf.escHtml( '<iframe src="x"></iframe>ok' )
			).not.toContain( '<iframe' );
			expect( acf.escHtml( '<form><input></form>ok' ) ).not.toContain(
				'<form'
			);
		} );

		it( 'should strip event handler attributes', () => {
			const result = acf.escHtml( '<a href="#" onclick="evil()">x</a>' );

			expect( result ).not.toContain( 'onclick' );
			expect( result ).toContain( '<a' );
		} );

		it( 'should strip style attributes', () => {
			expect( acf.escHtml( '<p style="color:red">x</p>' ) ).toBe(
				'<p>x</p>'
			);
		} );

		it( 'should keep basic formatting markup', () => {
			const markup = '<strong>bold</strong> and <em>italic</em>';

			expect( acf.escHtml( markup ) ).toBe( markup );
		} );

		it( 'should allow customization via the esc_html_dompurify_config filter', () => {
			acf.addFilter( 'esc_html_dompurify_config', ( config ) => {
				config.FORBID_TAGS = config.FORBID_TAGS.concat( [ 'em' ] );
				return config;
			} );

			expect( acf.escHtml( 'a <em>b</em>' ) ).toBe( 'a b' );
		} );
	} );
} );
