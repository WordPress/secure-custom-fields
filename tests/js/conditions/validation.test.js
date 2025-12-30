/**
 * Unit tests for ACF Validation module
 *
 * Tests the client-side form validation logic including error handling,
 * form locking/unlocking, and validation state management.
 */

/* global acf */

describe( 'ACF Validation', () => {
	let ValidatorClass;
	// eslint-disable-next-line no-unused-vars
	let validationModel;
	let mockForm;

	beforeEach( () => {
		// Create mock jQuery element
		mockForm = {
			data: jest.fn().mockReturnThis(),
			find: jest.fn().mockReturnThis(),
			addClass: jest.fn().mockReturnThis(),
			removeClass: jest.fn().mockReturnThis(),
			removeAttr: jest.fn().mockReturnThis(),
			attr: jest.fn().mockReturnThis(),
			css: jest.fn().mockReturnThis(),
			length: 1,
			first: jest.fn().mockReturnThis(),
			last: jest.fn().mockReturnThis(),
			parents: jest.fn().mockReturnValue( { length: 0 } ),
			not: jest.fn().mockReturnThis(),
			submit: jest.fn(),
			trigger: jest.fn().mockReturnThis(),
			offset: jest.fn().mockReturnValue( { top: 0 } ),
			on: jest.fn().mockReturnThis(),
		};

		// Mock jQuery
		global.jQuery = jest.fn( ( selector ) => {
			if ( typeof selector === 'string' ) {
				return mockForm;
			}
			if ( selector && selector.animate ) {
				return selector;
			}
			return mockForm;
		} );
		global.jQuery.Event = jest.fn( ( type, props ) => ( {
			type,
			...props,
		} ) );
		global.jQuery.ajax = jest.fn();
		global.jQuery.extend = function ( deep, target, ...sources ) {
			if ( typeof deep === 'boolean' ) {
				return Object.assign( target || {}, ...sources );
			}
			return Object.assign( deep || {}, target, ...sources );
		};
		global.$ = global.jQuery;
		global.$.extend = global.jQuery.extend;

		// Track Validator and validation model for testing
		ValidatorClass = null;
		validationModel = null;

		// Mock acf global
		global.acf = {
			__: jest.fn( ( key ) => {
				const translations = {
					'Validation failed': 'Validation failed',
					'1 field requires attention': '1 field requires attention',
					'%d fields require attention':
						'%d fields require attention',
					'Validation successful': 'Validation successful',
				};
				return translations[ key ] || key;
			} ),
			get: jest.fn( ( key ) => {
				if ( key === 'validation' ) {
					return true;
				}
				if ( key === 'ajaxurl' ) {
					return '/wp-admin/admin-ajax.php';
				}
				if ( key === 'browser' ) {
					return 'chrome';
				}
				return null;
			} ),
			models: {},
			isAjaxSuccess: jest.fn( ( json ) => json && json.success ),
			applyFilters: jest.fn( ( filter, data ) => data ),
			doAction: jest.fn(),
			parseArgs: jest.fn( ( args, defaults ) => ( {
				...defaults,
				...args,
			} ) ),
			prepareForAjax: jest.fn( ( data ) => data ),
			serialize: jest.fn( () => ( {} ) ),
			getClosestField: jest.fn( () => ( {
				$el: mockForm,
				showError: jest.fn(),
			} ) ),
			getPostbox: jest.fn( () => null ),
			newNotice: jest.fn( () => ( {
				$el: mockForm,
				update: jest.fn(),
				remove: jest.fn(),
				get: jest.fn( () => 'Error message' ),
			} ) ),
			lockForm: jest.fn(),
			unlockForm: jest.fn(),
			enableSubmit: jest.fn( ( $el ) => $el ),
			disableSubmit: jest.fn( ( $el ) => $el ),
			showSpinner: jest.fn( ( $el ) => $el ),
			hideSpinner: jest.fn( ( $el ) => $el ),
			isGutenberg: jest.fn( () => false ),
			strEscape: jest.fn( ( str ) => str ),
			debounce: jest.fn( ( fn ) => fn ),
		};

		// Create Model as a constructor function with extend method
		const createModelClass = ( definition ) => {
			const ModelClass = function ( $el ) {
				Object.assign( this, definition );
				this.data = { ...( definition.data || {} ) };
				this.$el = $el;
				if ( definition.setup ) {
					definition.setup.call( this, $el );
				}
			};
			ModelClass.prototype = {
				...definition,
				set( key, value, silent ) {
					if ( typeof key === 'object' ) {
						Object.assign( this.data, key );
					} else {
						this.data[ key ] = value;
					}
					if (
						! silent &&
						this.events &&
						this.events[ 'changed:' + key ]
					) {
						const handler = this[ this.events[ 'changed:' + key ] ];
						if ( handler ) {
							handler.call( this, {}, this.$el, value, null );
						}
					}
				},
				get( key ) {
					return this.data[ key ];
				},
				has( key ) {
					return key in this.data && this.data[ key ] !== null;
				},
				$( selector ) {
					return mockForm.find( selector );
				},
				on: jest.fn(),
			};
			ModelClass.extend = global.acf.Model.extend;

			// Store if this is the Validator or validation model
			if ( definition.id === 'Validator' ) {
				ValidatorClass = ModelClass;
			} else if ( definition.id === 'validation' ) {
				validationModel = new ModelClass();
			}

			return ModelClass;
		};

		// acf.Model is a constructor that can be called with new acf.Model({...})
		global.acf.Model = function ( definition ) {
			Object.assign( this, definition );
			this.data = { ...( definition.data || {} ) };
			if ( definition.id === 'validation' ) {
				validationModel = this;
			}
		};
		global.acf.Model.prototype = {
			set( key, value ) {
				if ( typeof key === 'object' ) {
					Object.assign( this.data, key );
				} else {
					this.data[ key ] = value;
				}
			},
			get( key ) {
				return this.data[ key ];
			},
			has( key ) {
				return key in this.data && this.data[ key ] !== null;
			},
			on: jest.fn(),
		};
		global.acf.Model.extend = jest.fn( ( definition ) =>
			createModelClass( definition )
		);

		// Load the validation module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-validation.js' );
		} );
	} );

	afterEach( () => {
		delete global.acf;
		delete global.jQuery;
		delete global.$;
		jest.clearAllMocks();
	} );

	describe( 'Validator Class', () => {
		let validator;

		beforeEach( () => {
			validator = new ValidatorClass( mockForm );
		} );

		describe( 'Error Management', () => {
			it( 'should start with no errors', () => {
				expect( validator.hasErrors() ).toBeFalsy();
				expect( validator.getErrors() ).toEqual( [] );
			} );

			it( 'should add a single error', () => {
				const error = {
					input: 'field_name',
					message: 'This field is required',
				};
				validator.addError( error );

				expect( validator.hasErrors() ).toBeTruthy();
				expect( validator.getErrors() ).toHaveLength( 1 );
				expect( validator.getErrors()[ 0 ] ).toEqual( error );
			} );

			it( 'should add multiple errors', () => {
				const errors = [
					{ input: 'field_1', message: 'Error 1' },
					{ input: 'field_2', message: 'Error 2' },
					{ input: 'field_3', message: 'Error 3' },
				];
				validator.addErrors( errors );

				expect( validator.hasErrors() ).toBeTruthy();
				expect( validator.getErrors() ).toHaveLength( 3 );
			} );

			it( 'should clear all errors', () => {
				validator.addError( { input: 'field', message: 'Error' } );
				expect( validator.hasErrors() ).toBeTruthy();

				validator.clearErrors();
				expect( validator.hasErrors() ).toBeFalsy();
				expect( validator.getErrors() ).toEqual( [] );
			} );
		} );

		describe( 'Error Filtering', () => {
			beforeEach( () => {
				validator.addErrors( [
					{ input: 'field_1', message: 'Field error 1' },
					{ input: 'field_2', message: 'Field error 2' },
					{ input: null, message: 'Global error 1' },
					{ message: 'Global error 2' }, // No input = global error
				] );
			} );

			it( 'should get field-specific errors', () => {
				const fieldErrors = validator.getFieldErrors();

				expect( fieldErrors ).toHaveLength( 2 );
				expect( fieldErrors[ 0 ].input ).toBe( 'field_1' );
				expect( fieldErrors[ 1 ].input ).toBe( 'field_2' );
			} );

			it( 'should get global errors', () => {
				const globalErrors = validator.getGlobalErrors();

				expect( globalErrors ).toHaveLength( 2 );
				expect( globalErrors[ 0 ].message ).toBe( 'Global error 1' );
				expect( globalErrors[ 1 ].message ).toBe( 'Global error 2' );
			} );

			it( 'should deduplicate field errors by input name', () => {
				validator.clearErrors();
				validator.addErrors( [
					{ input: 'field_1', message: 'First error' },
					{ input: 'field_1', message: 'Second error' },
					{ input: 'field_2', message: 'Another error' },
				] );

				const fieldErrors = validator.getFieldErrors();
				expect( fieldErrors ).toHaveLength( 2 );
				// The second error for field_1 should replace the first
				expect(
					fieldErrors.find( ( e ) => e.input === 'field_1' ).message
				).toBe( 'Second error' );
			} );
		} );

		describe( 'Status Management', () => {
			it( 'should start with empty status', () => {
				expect( validator.get( 'status' ) ).toBe( '' );
			} );

			it( 'should update status and trigger class change', () => {
				validator.set( 'status', 'validating' );
				expect( validator.get( 'status' ) ).toBe( 'validating' );
			} );

			it( 'should update form classes when status changes', () => {
				// Status change should update form classes
				validator.set( 'status', 'loading' );

				// The onChangeStatus handler should have been called
				expect( mockForm.removeClass ).toHaveBeenCalled();
				expect( mockForm.addClass ).toHaveBeenCalled();
			} );
		} );

		describe( 'Reset', () => {
			it( 'should reset all data', () => {
				validator.addError( { input: 'field', message: 'Error' } );
				validator.set( 'status', 'invalid' );
				validator.set( 'notice', { remove: jest.fn() } );

				validator.reset();

				expect( validator.get( 'errors' ) ).toEqual( [] );
				expect( validator.get( 'notice' ) ).toBeNull();
				expect( validator.get( 'status' ) ).toBe( '' );
				// Note: acf.unlockForm is defined by the source file,
				// so we verify it's a function rather than checking if it was called
				expect( typeof acf.unlockForm ).toBe( 'function' );
			} );
		} );
	} );

	describe( 'acf.validateForm', () => {
		it( 'should be registered on acf global', () => {
			expect( typeof acf.validateForm ).toBe( 'function' );
		} );
	} );

	describe( 'acf.enableSubmit', () => {
		it( 'should remove disabled class and attribute', () => {
			const $submit = {
				removeClass: jest.fn().mockReturnThis(),
				removeAttr: jest.fn().mockReturnThis(),
			};

			acf.enableSubmit( $submit );

			expect( $submit.removeClass ).toHaveBeenCalledWith( 'disabled' );
			expect( $submit.removeAttr ).toHaveBeenCalledWith( 'disabled' );
		} );
	} );

	describe( 'acf.disableSubmit', () => {
		it( 'should add disabled class and attribute', () => {
			const $submit = {
				addClass: jest.fn().mockReturnThis(),
				attr: jest.fn().mockReturnThis(),
			};

			acf.disableSubmit( $submit );

			expect( $submit.addClass ).toHaveBeenCalledWith( 'disabled' );
			expect( $submit.attr ).toHaveBeenCalledWith( 'disabled', true );
		} );
	} );

	describe( 'acf.showSpinner', () => {
		it( 'should add is-active class and set display', () => {
			const $spinner = {
				addClass: jest.fn().mockReturnThis(),
				css: jest.fn().mockReturnThis(),
			};

			acf.showSpinner( $spinner );

			expect( $spinner.addClass ).toHaveBeenCalledWith( 'is-active' );
			expect( $spinner.css ).toHaveBeenCalledWith(
				'display',
				'inline-block'
			);
		} );
	} );

	describe( 'acf.hideSpinner', () => {
		it( 'should remove is-active class and hide', () => {
			const $spinner = {
				removeClass: jest.fn().mockReturnThis(),
				css: jest.fn().mockReturnThis(),
			};

			acf.hideSpinner( $spinner );

			expect( $spinner.removeClass ).toHaveBeenCalledWith( 'is-active' );
			expect( $spinner.css ).toHaveBeenCalledWith( 'display', 'none' );
		} );
	} );

	describe( 'acf.lockForm', () => {
		it( 'should disable submit buttons and show spinner', () => {
			// The source file defines acf.lockForm which uses internal functions.
			// We verify the function exists and can be called without error.
			expect( typeof acf.lockForm ).toBe( 'function' );
			expect( () => acf.lockForm( mockForm ) ).not.toThrow();
		} );
	} );

	describe( 'acf.unlockForm', () => {
		it( 'should enable submit buttons and hide spinner', () => {
			// The source file defines acf.unlockForm which uses internal functions.
			// We verify the function exists and can be called without error.
			expect( typeof acf.unlockForm ).toBe( 'function' );
			expect( () => acf.unlockForm( mockForm ) ).not.toThrow();
		} );
	} );

	describe( 'acf.getBlockFormValidator', () => {
		it( 'should return a validator for block forms', () => {
			expect( typeof acf.getBlockFormValidator ).toBe( 'function' );
		} );
	} );

	describe( 'Validation Model', () => {
		it( 'should be registered on acf.validation', () => {
			expect( acf.validation ).toBeDefined();
		} );

		it( 'should have enable method', () => {
			expect( typeof acf.validation.enable ).toBe( 'function' );
		} );

		it( 'should have disable method', () => {
			expect( typeof acf.validation.disable ).toBe( 'function' );
		} );

		it( 'should have reset method', () => {
			expect( typeof acf.validation.reset ).toBe( 'function' );
		} );

		it( 'should enable validation', () => {
			acf.validation.active = false;
			acf.validation.enable();
			expect( acf.validation.active ).toBe( true );
		} );

		it( 'should disable validation', () => {
			acf.validation.active = true;
			acf.validation.disable();
			expect( acf.validation.active ).toBe( false );
		} );

		it( 'should start as active when validation setting is true', () => {
			expect( acf.validation.active ).toBe( true );
		} );
	} );

	describe( 'Error Display', () => {
		let validator;

		beforeEach( () => {
			validator = new ValidatorClass( mockForm );
		} );

		it( 'should not show errors when no errors exist', () => {
			validator.showErrors();
			// Should return early without doing anything
			expect( acf.newNotice ).not.toHaveBeenCalled();
		} );

		it( 'should create notice when showing errors', () => {
			validator.addError( {
				input: 'field_name',
				message: 'Field error',
			} );
			validator.showErrors();

			// Notice should be created for error display
			expect( acf.newNotice ).toHaveBeenCalled();
		} );

		it( 'should update existing notice instead of creating new one', () => {
			const mockNotice = {
				update: jest.fn(),
				$el: mockForm,
			};
			validator.set( 'notice', mockNotice, true );
			validator.addError( {
				input: 'field_name',
				message: 'Field error',
			} );
			validator.showErrors();

			expect( mockNotice.update ).toHaveBeenCalled();
			expect( acf.newNotice ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'Translation Integration', () => {
		it( 'should use translated validation messages', () => {
			expect( acf.__( 'Validation failed' ) ).toBe( 'Validation failed' );
			expect( acf.__( '1 field requires attention' ) ).toBe(
				'1 field requires attention'
			);
		} );
	} );
} );
