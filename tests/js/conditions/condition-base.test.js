/**
 * Unit tests for ACF Base Condition class and registration functions
 *
 * Tests the core condition infrastructure including:
 * - acf.Condition base class
 * - acf.newCondition factory
 * - acf.registerConditionType, acf.getConditionType, acf.getConditionTypes
 * - acf.registerConditionForFieldType
 */

/* global acf */

describe( 'ACF Base Condition', () => {
	beforeEach( () => {
		// Mock jQuery
		global.jQuery = jest.fn( () => ( {
			data: jest.fn().mockReturnThis(),
		} ) );
		global.$ = global.jQuery;
		global.jQuery.extend = function ( deep, target, ...sources ) {
			if ( typeof deep === 'boolean' ) {
				return Object.assign( target || {}, ...sources );
			}
			return Object.assign( deep || {}, target, ...sources );
		};
		global.$.extend = global.jQuery.extend;

		// Mock acf global
		global.acf = {
			__: jest.fn( ( key ) => key ),
			models: {},
			parseArgs: jest.fn( ( args, defaults ) => ( {
				...defaults,
				...args,
			} ) ),
			strPascalCase: jest.fn( ( str ) => {
				if ( ! str ) {
					return '';
				}
				return str
					.split( /[-_\s]/ )
					.map(
						( word ) =>
							word.charAt( 0 ).toUpperCase() + word.slice( 1 )
					)
					.join( '' );
			} ),
			Model: {
				extend: jest.fn( ( definition ) => {
					const ModelClass = function ( props ) {
						Object.assign( this, definition );
						this.data = { ...definition.data };
						if ( props ) {
							Object.assign( this.data, props );
						}
						if ( definition.setup ) {
							definition.setup.call( this, props );
						}
					};
					ModelClass.prototype = {
						...definition,
						set( key, value ) {
							this.data[ key ] = value;
						},
						get( key ) {
							return this.data[ key ];
						},
					};
					ModelClass.extend = ( childDef ) => {
						const merged = { ...definition, ...childDef };
						const ChildClass = function ( props ) {
							Object.assign( this, merged );
							this.data = { ...merged.data };
							if ( props ) {
								Object.assign( this.data, props );
							}
							if ( merged.setup ) {
								merged.setup.call( this, props );
							}
						};
						ChildClass.prototype = {
							...merged,
							set( key, value ) {
								this.data[ key ] = value;
							},
							get( key ) {
								return this.data[ key ];
							},
						};
						ChildClass.extend = ModelClass.extend;
						return ChildClass;
					};

					// Store the base Condition class on acf
					if ( definition.type === '' ) {
						acf.Condition = ModelClass;
					}

					return ModelClass;
				} ),
			},
		};

		// Load the condition base module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-condition.js' );
		} );
	} );

	afterEach( () => {
		delete global.acf;
		delete global.jQuery;
		delete global.$;
		jest.clearAllMocks();
	} );

	describe( 'acf.Condition Base Class', () => {
		it( 'should be defined on acf global', () => {
			expect( acf.Condition ).toBeDefined();
		} );

		it( 'should have default properties', () => {
			expect( acf.Condition.prototype.type ).toBe( '' );
			expect( acf.Condition.prototype.operator ).toBe( '==' );
			expect( acf.Condition.prototype.label ).toBe( '' );
			expect( acf.Condition.prototype.choiceType ).toBe( 'input' );
			expect( acf.Condition.prototype.fieldTypes ).toEqual( [] );
		} );

		it( 'should have default data properties', () => {
			expect( acf.Condition.prototype.data.conditions ).toBe( false );
			expect( acf.Condition.prototype.data.field ).toBe( false );
			expect( acf.Condition.prototype.data.rule ).toEqual( {} );
		} );

		it( 'should have event handlers defined', () => {
			expect( acf.Condition.prototype.events.change ).toBe( 'change' );
			expect( acf.Condition.prototype.events.keyup ).toBe( 'change' );
			expect( acf.Condition.prototype.events.enableField ).toBe(
				'change'
			);
			expect( acf.Condition.prototype.events.disableField ).toBe(
				'change'
			);
		} );

		it( 'should have match method that returns false by default', () => {
			const condition = new acf.Condition();
			expect( condition.match( {}, {} ) ).toBe( false );
		} );

		it( 'should have calculate method', () => {
			expect( typeof acf.Condition.prototype.calculate ).toBe(
				'function'
			);
		} );

		it( 'should have choices method that returns input by default', () => {
			const condition = new acf.Condition();
			expect( condition.choices( {} ) ).toBe( '<input type="text" />' );
		} );

		it( 'should have setup method that extends data', () => {
			const props = { rule: { value: 'test' }, field: { name: 'test' } };
			const condition = new acf.Condition( props );

			expect( condition.data.rule ).toEqual( props.rule );
			expect( condition.data.field ).toEqual( props.field );
		} );

		it( 'should support extending to create custom conditions', () => {
			const CustomCondition = acf.Condition.extend( {
				type: 'custom',
				operator: '!=',
				label: 'Custom Condition',
				fieldTypes: [ 'text', 'textarea' ],
				match( rule, field ) {
					return rule.value !== field.val();
				},
			} );

			expect( CustomCondition.prototype.type ).toBe( 'custom' );
			expect( CustomCondition.prototype.operator ).toBe( '!=' );
			expect( CustomCondition.prototype.fieldTypes ).toContain( 'text' );
		} );
	} );

	describe( 'acf.registerConditionType', () => {
		it( 'should be defined', () => {
			expect( typeof acf.registerConditionType ).toBe( 'function' );
		} );

		it( 'should register a condition type in acf.models', () => {
			const TestCondition = acf.Condition.extend( {
				type: 'testCondition',
				operator: '==',
			} );

			acf.registerConditionType( TestCondition );

			expect( acf.models.TestConditionCondition ).toBe( TestCondition );
		} );
	} );

	describe( 'acf.getConditionType', () => {
		it( 'should be defined', () => {
			expect( typeof acf.getConditionType ).toBe( 'function' );
		} );

		it( 'should return a registered condition type', () => {
			const TestCondition = acf.Condition.extend( {
				type: 'myCondition',
			} );

			acf.registerConditionType( TestCondition );

			const retrieved = acf.getConditionType( 'myCondition' );
			expect( retrieved ).toBe( TestCondition );
		} );

		it( 'should return false for unregistered types', () => {
			const result = acf.getConditionType( 'nonExistentCondition' );
			expect( result ).toBe( false );
		} );
	} );

	describe( 'acf.getConditionTypes', () => {
		it( 'should be defined', () => {
			expect( typeof acf.getConditionTypes ).toBe( 'function' );
		} );

		it( 'should filter by field type', () => {
			const TextCondition = acf.Condition.extend( {
				type: 'textCondition',
				fieldTypes: [ 'text' ],
				operator: '==',
			} );
			const NumberCondition = acf.Condition.extend( {
				type: 'numberCondition',
				fieldTypes: [ 'number' ],
				operator: '==',
			} );

			acf.registerConditionType( TextCondition );
			acf.registerConditionType( NumberCondition );

			const textConditions = acf.getConditionTypes( {
				fieldType: 'text',
			} );
			expect( textConditions ).toContain( TextCondition );
			expect( textConditions ).not.toContain( NumberCondition );
		} );

		it( 'should filter by operator', () => {
			const EqualCondition = acf.Condition.extend( {
				type: 'equalCond',
				fieldTypes: [ 'text' ],
				operator: '==',
			} );
			const NotEqualCondition = acf.Condition.extend( {
				type: 'notEqualCond',
				fieldTypes: [ 'text' ],
				operator: '!=',
			} );

			acf.registerConditionType( EqualCondition );
			acf.registerConditionType( NotEqualCondition );

			const equalConditions = acf.getConditionTypes( { operator: '==' } );
			expect( equalConditions ).toContain( EqualCondition );
			expect( equalConditions ).not.toContain( NotEqualCondition );
		} );

		it( 'should filter by both field type and operator', () => {
			const MatchCondition = acf.Condition.extend( {
				type: 'matchCond',
				fieldTypes: [ 'text' ],
				operator: '==',
			} );
			const NoMatchCondition = acf.Condition.extend( {
				type: 'noMatchCond',
				fieldTypes: [ 'number' ],
				operator: '!=',
			} );

			acf.registerConditionType( MatchCondition );
			acf.registerConditionType( NoMatchCondition );

			const conditions = acf.getConditionTypes( {
				fieldType: 'text',
				operator: '==',
			} );
			expect( conditions ).toContain( MatchCondition );
			expect( conditions ).not.toContain( NoMatchCondition );
		} );

		it( 'should return all conditions when no filters provided', () => {
			const Cond1 = acf.Condition.extend( { type: 'cond1' } );
			const Cond2 = acf.Condition.extend( { type: 'cond2' } );

			acf.registerConditionType( Cond1 );
			acf.registerConditionType( Cond2 );

			const allConditions = acf.getConditionTypes( {} );
			expect( allConditions ).toContain( Cond1 );
			expect( allConditions ).toContain( Cond2 );
		} );
	} );

	describe( 'acf.registerConditionForFieldType', () => {
		it( 'should be defined', () => {
			expect( typeof acf.registerConditionForFieldType ).toBe(
				'function'
			);
		} );

		it( 'should add field type to existing condition', () => {
			const TestCondition = acf.Condition.extend( {
				type: 'registerTest',
				fieldTypes: [ 'text' ],
			} );

			acf.registerConditionType( TestCondition );
			acf.registerConditionForFieldType( 'registerTest', 'textarea' );

			expect( TestCondition.prototype.fieldTypes ).toContain( 'text' );
			expect( TestCondition.prototype.fieldTypes ).toContain(
				'textarea'
			);
		} );

		it( 'should handle non-existent condition gracefully', () => {
			// Should not throw
			expect( () => {
				acf.registerConditionForFieldType( 'nonExistent', 'text' );
			} ).not.toThrow();
		} );
	} );

	describe( 'acf.newCondition', () => {
		it( 'should be defined', () => {
			expect( typeof acf.newCondition ).toBe( 'function' );
		} );

		it( 'should return false if target field is missing', () => {
			// The source tries target.getField() before null check,
			// so we need to provide a mock that returns null from getField
			const conditions = {
				get: jest.fn( ( key ) => {
					if ( key === 'field' ) {
						return {
							getField: jest.fn( () => null ),
						};
					}
					return null;
				} ),
			};
			const rule = { field: 'field_key', operator: '==', value: 'test' };

			const result = acf.newCondition( rule, conditions );
			expect( result ).toBe( false );
		} );

		it( 'should return false if trigger field is missing', () => {
			const conditions = {
				get: jest.fn( ( key ) => {
					if ( key === 'field' ) {
						return {
							getField: jest.fn( () => false ),
						};
					}
					return null;
				} ),
			};
			const rule = { field: 'field_key', operator: '==', value: 'test' };

			const result = acf.newCondition( rule, conditions );
			expect( result ).toBe( false );
		} );

		it( 'should create condition instance when fields exist', () => {
			const mockTriggerField = {
				get: jest.fn( ( key ) => {
					if ( key === 'type' ) {
						return 'text';
					}
					return null;
				} ),
			};
			const mockTargetField = {
				getField: jest.fn( () => mockTriggerField ),
			};
			const conditions = {
				get: jest.fn( ( key ) => {
					if ( key === 'field' ) {
						return mockTargetField;
					}
					return null;
				} ),
			};
			const rule = { field: 'field_key', operator: '==', value: 'test' };

			// Register a matching condition type
			const TextEqualCondition = acf.Condition.extend( {
				type: 'textEqual',
				fieldTypes: [ 'text' ],
				operator: '==',
			} );
			acf.registerConditionType( TextEqualCondition );

			const result = acf.newCondition( rule, conditions );

			// Should return a condition instance (or base Condition if no match)
			expect( result ).toBeDefined();
			expect( result ).not.toBe( false );
		} );

		it( 'should use base Condition if no matching type found', () => {
			const mockTriggerField = {
				get: jest.fn( ( key ) => {
					if ( key === 'type' ) {
						return 'unknown_type';
					}
					return null;
				} ),
			};
			const mockTargetField = {
				getField: jest.fn( () => mockTriggerField ),
			};
			const conditions = {
				get: jest.fn( ( key ) => {
					if ( key === 'field' ) {
						return mockTargetField;
					}
					return null;
				} ),
			};
			const rule = {
				field: 'field_key',
				operator: '==unknown',
				value: 'test',
			};

			const result = acf.newCondition( rule, conditions );

			// Should return base Condition instance
			expect( result ).toBeDefined();
			expect( result ).not.toBe( false );
		} );
	} );

	describe( 'Condition.calculate', () => {
		it( 'should call match with rule and field', () => {
			const mockRule = { value: 'test' };
			const mockField = { val: jest.fn( () => 'test' ) };

			const condition = new acf.Condition( {
				rule: mockRule,
				field: mockField,
			} );
			condition.match = jest.fn( () => true );

			condition.calculate();

			expect( condition.match ).toHaveBeenCalledWith(
				mockRule,
				mockField
			);
		} );
	} );

	describe( 'Condition.change', () => {
		it( 'should trigger conditions change', () => {
			const mockConditions = {
				change: jest.fn(),
			};
			const condition = new acf.Condition( {
				conditions: mockConditions,
			} );
			const event = { type: 'change' };

			condition.change( event );

			expect( mockConditions.change ).toHaveBeenCalledWith( event );
		} );
	} );

	describe( 'Condition.getEventTarget', () => {
		it( 'should return provided element if given', () => {
			const condition = new acf.Condition();
			const $el = { jquery: true };

			const result = condition.getEventTarget( $el );
			expect( result ).toBe( $el );
		} );

		it( 'should return field $el if no element provided', () => {
			const mockField = { $el: { fieldElement: true } };
			const condition = new acf.Condition( { field: mockField } );

			const result = condition.getEventTarget( null );
			expect( result ).toEqual( mockField.$el );
		} );
	} );
} );
