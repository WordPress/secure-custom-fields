/**
 * Unit tests for ACF Conditions Manager
 *
 * Tests the conditional logic system that controls field visibility
 * based on the values of other fields. This includes group management,
 * rule processing, and visibility calculations.
 */

/* global acf */

describe( 'ACF Conditions Manager', () => {
	let ConditionsClass;
	let mockField;
	let conditionsManagerModel;

	beforeEach( () => {
		// Track classes for testing
		ConditionsClass = null;
		conditionsManagerModel = null;

		// Create mock field
		mockField = {
			$el: {
				parent: jest.fn().mockReturnThis(),
				parents: jest.fn().mockReturnValue( [] ),
			},
			get: jest.fn( ( key ) => {
				if ( key === 'conditions' ) {
					return [
						[
							{
								field: 'field_abc',
								operator: '==',
								value: 'test',
							},
						],
					];
				}
				if ( key === 'type' ) {
					return 'text';
				}
				return null;
			} ),
			has: jest.fn( ( key ) => key === 'conditions' ),
			showEnable: jest.fn(),
			hideDisable: jest.fn(),
			getField: jest.fn( () => ( {
				$el: {},
				val: jest.fn( () => 'test' ),
				get: jest.fn( ( key ) => {
					if ( key === 'type' ) {
						return 'text';
					}
					return null;
				} ),
			} ) ),
			parents: jest.fn( () => [] ),
			conditions: null,
		};

		// Mock jQuery
		global.jQuery = jest.fn( () => ( {
			data: jest.fn().mockReturnThis(),
			length: 1,
			parent: jest.fn().mockReturnThis(),
			parents: jest.fn().mockReturnValue( { length: 0 } ),
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
			getFields: jest.fn( () => [] ),
			parseArgs: jest.fn( ( args, defaults ) => ( {
				...defaults,
				...args,
			} ) ),
			strPascalCase: jest.fn( ( str ) => {
				if ( ! str ) {
					return '';
				}
				return str.charAt( 0 ).toUpperCase() + str.slice( 1 );
			} ),
		};

		// Create Model class as a constructor with extend method
		const createModelClass = ( definition ) => {
			const ModelClass = function ( ...args ) {
				Object.assign( this, definition );
				this.data = { ...( definition.data || {} ) };
				if ( definition.setup ) {
					definition.setup.apply( this, args );
				}
			};
			ModelClass.prototype = {
				...definition,
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
			};
			ModelClass.extend = global.acf.Model.extend;

			// Track specific models
			if ( definition.id === 'conditionsManager' ) {
				conditionsManagerModel = new ModelClass();
			} else if ( definition.id === 'Conditions' ) {
				ConditionsClass = ModelClass;
			}

			return ModelClass;
		};

		// acf.Model is a constructor that can be called with new acf.Model({...})
		global.acf.Model = function ( definition ) {
			Object.assign( this, definition );
			this.data = { ...( definition.data || {} ) };
			if ( definition.id === 'conditionsManager' ) {
				conditionsManagerModel = this;
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
		};
		global.acf.Model.extend = jest.fn( ( definition ) =>
			createModelClass( definition )
		);

		// Continue with other acf properties
		Object.assign( global.acf, {
			Field: {
				prototype: {
					getField: jest.fn(),
					getConditions: null, // Will be set after module load
				},
			},
			Condition: null, // Will be set after condition module load
			newCondition: jest.fn( ( rule ) => {
				// Return a mock condition that always matches
				return {
					calculate: jest.fn( () => true ),
					get: jest.fn( ( key ) => {
						if ( key === 'field' ) {
							return mockField;
						}
						if ( key === 'rule' ) {
							return rule;
						}
						return null;
					} ),
				};
			} ),
			registerConditionType: jest.fn(),
			getConditionType: jest.fn(),
			getConditionTypes: jest.fn( () => [] ),
		} );

		// Load the conditions module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-conditions.js' );
		} );
	} );

	afterEach( () => {
		delete global.acf;
		delete global.jQuery;
		delete global.$;
		jest.clearAllMocks();
	} );

	describe( 'Conditions Manager Model', () => {
		it( 'should be registered with priority 20', () => {
			expect( conditionsManagerModel ).toBeDefined();
			expect( conditionsManagerModel.priority ).toBe( 20 );
		} );

		it( 'should listen to new_field action', () => {
			expect( conditionsManagerModel.actions ).toHaveProperty(
				'new_field'
			);
			expect( conditionsManagerModel.actions.new_field ).toBe(
				'onNewField'
			);
		} );

		it( 'should have onNewField method', () => {
			expect( typeof conditionsManagerModel.onNewField ).toBe(
				'function'
			);
		} );

		it( 'should render conditions for fields that have them', () => {
			// Mock a field with conditions
			const fieldWithConditions = {
				has: jest.fn( ( key ) => key === 'conditions' ),
				getConditions: jest.fn( () => ( {
					render: jest.fn(),
				} ) ),
			};

			conditionsManagerModel.onNewField( fieldWithConditions );

			expect( fieldWithConditions.getConditions ).toHaveBeenCalled();
		} );

		it( 'should not render conditions for fields without them', () => {
			const fieldWithoutConditions = {
				has: jest.fn( () => false ),
				getConditions: jest.fn(),
			};

			conditionsManagerModel.onNewField( fieldWithoutConditions );

			expect(
				fieldWithoutConditions.getConditions
			).not.toHaveBeenCalled();
		} );
	} );

	describe( 'Field.prototype.getConditions', () => {
		it( 'should be added to acf.Field.prototype', () => {
			expect( acf.Field.prototype.getConditions ).toBeDefined();
			expect( typeof acf.Field.prototype.getConditions ).toBe(
				'function'
			);
		} );

		it( 'should return a Conditions instance', () => {
			const field = {
				conditions: null,
				get: jest.fn( () => [
					[ { field: 'field_abc', operator: '==', value: 'test' } ],
				] ),
			};

			const result = acf.Field.prototype.getConditions.call( field );

			expect( result ).toBeDefined();
			expect( field.conditions ).toBeDefined();
		} );

		it( 'should return cached instance on subsequent calls', () => {
			const cachedConditions = { cached: true };
			const field = {
				conditions: cachedConditions,
			};

			const result = acf.Field.prototype.getConditions.call( field );

			expect( result ).toBe( cachedConditions );
		} );
	} );

	describe( 'Conditions Class', () => {
		let conditions;

		beforeEach( () => {
			conditions = new ConditionsClass( mockField );
		} );

		describe( 'Initialization', () => {
			it( 'should store the field reference', () => {
				expect( conditions.get( 'field' ) ).toBe( mockField );
			} );

			it( 'should initialize groups array', () => {
				expect( conditions.hasGroups() ).toBe( true );
			} );
		} );

		describe( 'Group Management', () => {
			it( 'should add a new group', () => {
				const group = conditions.addGroup();

				expect( Array.isArray( group ) ).toBe( true );
				expect( conditions.getGroups().length ).toBeGreaterThan( 0 );
			} );

			it( 'should check if group exists', () => {
				conditions.addGroup();

				expect( conditions.hasGroup( 0 ) ).toBe( true );
				expect( conditions.hasGroup( 999 ) ).toBe( false );
			} );

			it( 'should get a specific group', () => {
				conditions.addGroup();

				expect( conditions.getGroup( 0 ) ).toBeDefined();
			} );

			it( 'should get all groups', () => {
				conditions.addGroup();
				conditions.addGroup();

				const groups = conditions.getGroups();
				expect( groups.length ).toBeGreaterThanOrEqual( 2 );
			} );
		} );

		describe( 'Rule Management', () => {
			it( 'should add a rule to default group', () => {
				const rule = {
					field: 'field_abc',
					operator: '==',
					value: 'test',
				};
				conditions.addRule( rule );

				expect( conditions.hasGroup( 0 ) ).toBe( true );
				expect( acf.newCondition ).toHaveBeenCalledWith(
					rule,
					conditions
				);
			} );

			it( 'should add a rule to a specific group', () => {
				const rule = {
					field: 'field_abc',
					operator: '==',
					value: 'test',
				};
				conditions.addRule( rule, 1 );

				expect( conditions.hasGroup( 1 ) ).toBe( true );
			} );

			it( 'should add multiple rules', () => {
				const rules = [
					{ field: 'field_1', operator: '==', value: 'a' },
					{ field: 'field_2', operator: '!=', value: 'b' },
				];
				conditions.addRules( rules );

				expect( acf.newCondition ).toHaveBeenCalledTimes(
					rules.length + 1 // +1 for initial setup
				);
			} );

			it( 'should get a specific rule', () => {
				const rule = {
					field: 'field_abc',
					operator: '==',
					value: 'test',
				};
				conditions.addRule( rule, 0 );

				const retrievedRule = conditions.getRule( 0, 0 );
				expect( retrievedRule ).toBeDefined();
			} );
		} );

		describe( 'Visibility Calculation', () => {
			it( 'should call show when calculate returns true', () => {
				// Mock all conditions to pass
				conditions.calculate = jest.fn( () => true );

				conditions.render();

				expect( mockField.showEnable ).toHaveBeenCalled();
			} );

			it( 'should call hide when calculate returns false', () => {
				// Mock conditions to fail
				conditions.calculate = jest.fn( () => false );

				conditions.render();

				expect( mockField.hideDisable ).toHaveBeenCalled();
			} );
		} );

		describe( 'Change Handling', () => {
			it( 'should prevent duplicate triggers for same event', () => {
				const event1 = { timeStamp: 12345 };
				const event2 = { timeStamp: 12345 }; // Same timestamp

				conditions.render = jest.fn();

				conditions.change( event1 );
				conditions.change( event2 );

				// Should only render once due to timestamp check
				expect( conditions.render ).toHaveBeenCalledTimes( 1 );
			} );

			it( 'should allow triggers for different events', () => {
				const event1 = { timeStamp: 12345 };
				const event2 = { timeStamp: 12346 }; // Different timestamp

				conditions.render = jest.fn();

				conditions.change( event1 );
				conditions.change( event2 );

				expect( conditions.render ).toHaveBeenCalledTimes( 2 );
			} );
		} );
	} );

	describe( 'Field.prototype.getField (sibling field lookup)', () => {
		it( 'should be added to acf.Field.prototype', () => {
			expect( acf.Field.prototype.getField ).toBeDefined();
		} );
	} );

	describe( 'Condition Setup Patterns', () => {
		it( 'should handle array of arrays (groups of rules)', () => {
			const groupedConditions = [
				[
					{ field: 'field_1', operator: '==', value: 'a' },
					{ field: 'field_2', operator: '==', value: 'b' },
				],
				[ { field: 'field_3', operator: '==', value: 'c' } ],
			];

			mockField.get = jest.fn( ( key ) => {
				if ( key === 'conditions' ) {
					return groupedConditions;
				}
				return null;
			} );

			const conditions = new ConditionsClass( mockField );

			// Should have created 2 groups
			expect( conditions.getGroups().length ).toBe( 2 );
		} );

		it( 'should handle array of rules (single group)', () => {
			const singleGroupConditions = [
				{ field: 'field_1', operator: '==', value: 'a' },
				{ field: 'field_2', operator: '==', value: 'b' },
			];

			mockField.get = jest.fn( ( key ) => {
				if ( key === 'conditions' ) {
					return singleGroupConditions;
				}
				return null;
			} );

			const conditions = new ConditionsClass( mockField );

			// Should have created 1 group with 2 rules
			expect( conditions.getGroups().length ).toBe( 1 );
		} );

		it( 'should handle single rule object', () => {
			const singleRule = {
				field: 'field_1',
				operator: '==',
				value: 'a',
			};

			mockField.get = jest.fn( ( key ) => {
				if ( key === 'conditions' ) {
					return singleRule;
				}
				return null;
			} );

			const conditions = new ConditionsClass( mockField );

			// Should have created 1 group with 1 rule
			expect( conditions.getGroups().length ).toBe( 1 );
		} );
	} );

	describe( 'Calculate Logic (AND/OR)', () => {
		it( 'should return true when any group passes (OR between groups)', () => {
			const conditions = new ConditionsClass( mockField );

			// Create groups with mock conditions
			const group1 = [
				{ calculate: jest.fn( () => false ) },
				{ calculate: jest.fn( () => false ) },
			];
			const group2 = [
				{ calculate: jest.fn( () => true ) },
				{ calculate: jest.fn( () => true ) },
			];

			conditions.data.groups = [ group1, group2 ];

			// Group 1 fails (not all pass), but Group 2 passes (all pass)
			// Result should be true (OR logic between groups)
			expect( conditions.calculate() ).toBe( true );
		} );

		it( 'should return false when no groups pass', () => {
			const conditions = new ConditionsClass( mockField );

			const group1 = [
				{ calculate: jest.fn( () => false ) },
				{ calculate: jest.fn( () => true ) },
			];
			const group2 = [
				{ calculate: jest.fn( () => true ) },
				{ calculate: jest.fn( () => false ) },
			];

			conditions.data.groups = [ group1, group2 ];

			// Neither group has all conditions passing
			expect( conditions.calculate() ).toBe( false );
		} );

		it( 'should require all conditions in a group to pass (AND within group)', () => {
			const conditions = new ConditionsClass( mockField );

			const group = [
				{ calculate: jest.fn( () => true ) },
				{ calculate: jest.fn( () => true ) },
				{ calculate: jest.fn( () => true ) },
			];

			conditions.data.groups = [ group ];

			expect( conditions.calculate() ).toBe( true );
		} );

		it( 'should fail group if any condition fails', () => {
			const conditions = new ConditionsClass( mockField );

			const group = [
				{ calculate: jest.fn( () => true ) },
				{ calculate: jest.fn( () => false ) }, // One fails
				{ calculate: jest.fn( () => true ) },
			];

			conditions.data.groups = [ group ];

			expect( conditions.calculate() ).toBe( false );
		} );
	} );
} );
