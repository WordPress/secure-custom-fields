/**
 * Unit tests for the Gallery field type.
 */

describe( 'Gallery Field', () => {
	let ajaxOptions;
	let fieldDefinition;
	let mockField;

	beforeEach( () => {
		ajaxOptions = null;
		fieldDefinition = null;

		// Generic chainable element stub; the sidebar DOM work itself is
		// covered by the closeSidebar tests, not the selectAttachment ones.
		const $el = {};
		Object.assign( $el, {
			addClass: () => $el,
			removeClass: () => $el,
			hasClass: () => false,
			find: () => $el,
			trigger: () => $el,
			remove: () => $el,
			append: () => $el,
			html: () => $el,
		} );

		global.jQuery = {
			ajax: jest.fn( ( options ) => {
				ajaxOptions = options;
				return {};
			} ),
		};

		global.acf = {
			Field: {
				extend: ( definition ) => ( fieldDefinition = definition ),
			},
			disable: jest.fn(),
			doAction: jest.fn(),
			get: () => '/wp-admin/admin-ajax.php',
			prepareForAjax: ( data ) => data,
			registerConditionForFieldType: jest.fn(),
			registerFieldType: jest.fn(),
			showLoading: jest.fn(),
		};

		jest.isolateModules( () => {
			require( '../../../assets/src/js/pro/_acf-field-gallery.js' );
		} );

		mockField = {
			...fieldDefinition,
			$: () => $el,
			$active: () => $el,
			$attachment: () => $el,
			$control: () => $el,
			$main: () => $el,
			$side: () => $el,
			$sideData: () => $el,
			get: ( key ) =>
				( { key: 'field_test_gallery', nonce: 'gallery-nonce' } )[
					key
				],
			has: () => false,
			set: jest.fn(),
			proxy( callback ) {
				return callback.bind( this );
			},
			openSidebar: jest.fn(),
			closeSidebar: jest.fn(),
		};
	} );

	afterEach( () => {
		delete global.acf;
		delete global.jQuery;
	} );

	describe( 'selectAttachment', () => {
		it( 'closes the sidebar when the attachment response is empty', () => {
			mockField.selectAttachment( 42 );
			ajaxOptions.success( '' );

			expect( mockField.closeSidebar ).toHaveBeenCalled();
		} );

		it( 'keeps the sidebar open when the attachment response has content', () => {
			mockField.selectAttachment( 42 );
			ajaxOptions.success( '<div class="acf-gallery-side-info"></div>' );

			expect( mockField.closeSidebar ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'closeSidebar', () => {
		it( 'closes, disables, and clears the sidebar', () => {
			const control = { removeClass: jest.fn() };
			const active = { removeClass: jest.fn() };
			const main = { animate: jest.fn() };
			const sideData = { html: jest.fn() };
			const side = {
				animate: jest.fn( ( properties, duration, complete ) =>
					complete()
				),
			};

			const field = {
				...fieldDefinition,
				$: () => sideData,
				$active: () => active,
				$control: () => control,
				$main: () => main,
				$side: () => side,
			};

			field.closeSidebar();

			expect( control.removeClass ).toHaveBeenCalledWith( '-open' );
			expect( active.removeClass ).toHaveBeenCalledWith( 'active' );
			expect( global.acf.disable ).toHaveBeenCalledWith( side );
			expect( main.animate ).toHaveBeenCalledWith( { right: 0 }, 250 );
			expect( sideData.html ).toHaveBeenCalledWith( '' );
		} );
	} );
} );
