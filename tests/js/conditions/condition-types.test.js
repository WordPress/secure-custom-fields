/**
 * Unit tests for ACF condition types
 *
 * Tests the condition matching logic for conditional field visibility.
 * These conditions determine when fields should be shown or hidden based on other field values.
 */

/* global acf */

describe( 'ACF Condition Types', () => {
	let registeredConditions;
	let mockField;

	/**
	 * Helper to create a mock field with a specific value.
	 *
	 * @param {*} value The value the field should return.
	 * @return {Object} A mock field object.
	 */
	const createMockField = ( value ) => ( {
		val: jest.fn( () => value ),
		$el: {},
		get: jest.fn( ( key ) => {
			if ( key === 'type' ) {
				return 'text';
			}
			return null;
		} ),
	} );

	/**
	 * Helper to create a mock rule.
	 *
	 * @param {*} value The rule value to match against.
	 * @return {Object} A mock rule object.
	 */
	const createMockRule = ( value ) => ( { value } );

	beforeEach( () => {
		registeredConditions = {};

		// Mock acf global with the necessary infrastructure
		global.acf = {
			__: jest.fn( ( key ) => key ),
			models: {},
			isNumeric: jest.fn(
				( val ) => ! isNaN( parseFloat( val ) ) && isFinite( val )
			),
			escAttr: jest.fn( ( str ) => str ),
			strEscape: jest.fn( ( str ) => str ),
			escHtml: jest.fn( ( str ) => str ),
			prepareForAjax: jest.fn( ( data ) => data ),
			parseArgs: jest.fn( ( args, defaults ) => ( {
				...defaults,
				...args,
			} ) ),
			Condition: {
				extend: jest.fn( function createExtend( definition ) {
					const ConditionClass = function ( props ) {
						Object.assign( this, definition );
						this.data = { ...( definition.data || {} ) };
						if ( props ) {
							Object.assign( this.data, props );
						}
					};
					ConditionClass.prototype = {
						...definition,
						get( key ) {
							return this.data ? this.data[ key ] : undefined;
						},
					};
					// Preserve parent definition for proper inheritance chain
					ConditionClass.extend = ( childDef ) => {
						// Merge parent prototype with child definition
						const merged = {
							...ConditionClass.prototype,
							...childDef,
						};
						return createExtend( merged );
					};
					return ConditionClass;
				} ),
			},
			registerConditionType: jest.fn( ( model ) => {
				const type = model.prototype.type;
				registeredConditions[ type ] = model;
				acf.models[ type + 'Condition' ] = model;
			} ),
		};

		// Mock jQuery
		global.jQuery = jest.fn( ( selector ) => {
			if (
				typeof selector === 'string' &&
				selector === '<select></select>'
			) {
				return {
					data: jest.fn().mockReturnThis(),
				};
			}
			return {
				data: jest.fn().mockReturnThis(),
			};
		} );
		global.$ = global.jQuery;
		global.jQuery.isNumeric = ( val ) =>
			! isNaN( parseFloat( val ) ) && isFinite( val );
		global.jQuery.extend = function ( deep, target, ...sources ) {
			// Handle deep copy signature: $.extend(true, target, source)
			if ( typeof deep === 'boolean' ) {
				return Object.assign( target || {}, ...sources );
			}
			// Handle simple signature: $.extend(target, source)
			return Object.assign( deep || {}, target, ...sources );
		};
		global.$.extend = global.jQuery.extend;

		// Load the condition types module
		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-condition-types.js' );
		} );
	} );

	afterEach( () => {
		delete global.acf;
		delete global.jQuery;
		delete global.$;
		jest.clearAllMocks();
	} );

	describe( 'Condition Registration', () => {
		it( 'should register all condition types', () => {
			expect( acf.registerConditionType ).toHaveBeenCalled();

			// Check some key conditions are registered
			const registeredTypes = Object.keys( registeredConditions );
			expect( registeredTypes ).toContain( 'hasValue' );
			expect( registeredTypes ).toContain( 'hasNoValue' );
			expect( registeredTypes ).toContain( 'equalTo' );
			expect( registeredTypes ).toContain( 'notEqualTo' );
			expect( registeredTypes ).toContain( 'patternMatch' );
			expect( registeredTypes ).toContain( 'contains' );
			expect( registeredTypes ).toContain( 'greaterThan' );
			expect( registeredTypes ).toContain( 'lessThan' );
		} );

		it( 'should register page link conditions', () => {
			const registeredTypes = Object.keys( registeredConditions );
			expect( registeredTypes ).toContain( 'hasPageLink' );
			expect( registeredTypes ).toContain( 'hasPageLinkNotEqual' );
			expect( registeredTypes ).toContain( 'containsPageLink' );
			expect( registeredTypes ).toContain( 'containsNotPageLink' );
			expect( registeredTypes ).toContain( 'hasAnyPageLink' );
			expect( registeredTypes ).toContain( 'hasNoPageLink' );
		} );

		it( 'should register user conditions', () => {
			const registeredTypes = Object.keys( registeredConditions );
			expect( registeredTypes ).toContain( 'hasUser' );
			expect( registeredTypes ).toContain( 'hasUserNotEqual' );
			expect( registeredTypes ).toContain( 'containsUser' );
			expect( registeredTypes ).toContain( 'hasAnyUser' );
			expect( registeredTypes ).toContain( 'hasNoUser' );
		} );

		it( 'should register relationship conditions', () => {
			const registeredTypes = Object.keys( registeredConditions );
			expect( registeredTypes ).toContain( 'hasRelationship' );
			expect( registeredTypes ).toContain( 'hasRelationshipNotEqual' );
			expect( registeredTypes ).toContain( 'containsRelationship' );
			expect( registeredTypes ).toContain( 'containsNotRelationship' );
			expect( registeredTypes ).toContain( 'hasAnyRelation' );
			expect( registeredTypes ).toContain( 'hasNoRelation' );
		} );

		it( 'should register post object conditions', () => {
			const registeredTypes = Object.keys( registeredConditions );
			expect( registeredTypes ).toContain( 'hasPostObject' );
			expect( registeredTypes ).toContain( 'hasPostObjectNotEqual' );
			expect( registeredTypes ).toContain( 'containsPostObject' );
			expect( registeredTypes ).toContain( 'containsNotPostObject' );
			expect( registeredTypes ).toContain( 'hasAnyPostObject' );
			expect( registeredTypes ).toContain( 'hasNoPostObject' );
		} );

		it( 'should register taxonomy conditions', () => {
			const registeredTypes = Object.keys( registeredConditions );
			expect( registeredTypes ).toContain( 'hasTerm' );
			expect( registeredTypes ).toContain( 'hasTermNotEqual' );
			expect( registeredTypes ).toContain( 'containsTerm' );
			expect( registeredTypes ).toContain( 'containsNotTerm' );
			expect( registeredTypes ).toContain( 'hasAnyTerm' );
			expect( registeredTypes ).toContain( 'hasNoTerm' );
		} );

		it( 'should register select conditions', () => {
			const registeredTypes = Object.keys( registeredConditions );
			expect( registeredTypes ).toContain( 'selectEqualTo' );
			expect( registeredTypes ).toContain( 'selectNotEqualTo' );
		} );

		it( 'should register true/false conditions', () => {
			const registeredTypes = Object.keys( registeredConditions );
			expect( registeredTypes ).toContain( 'trueFalseEqualTo' );
			expect( registeredTypes ).toContain( 'trueFalseNotEqualTo' );
		} );

		it( 'should register selection count conditions', () => {
			const registeredTypes = Object.keys( registeredConditions );
			expect( registeredTypes ).toContain( 'selectionGreaterThan' );
			expect( registeredTypes ).toContain( 'selectionLessThan' );
		} );
	} );

	describe( 'HasValue Condition', () => {
		it( 'should return true when field has a string value', () => {
			const HasValue = registeredConditions.hasValue;
			const instance = new HasValue();
			mockField = createMockField( 'some value' );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return true when field has a numeric value', () => {
			const HasValue = registeredConditions.hasValue;
			const instance = new HasValue();
			mockField = createMockField( 42 );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when field is empty string', () => {
			const HasValue = registeredConditions.hasValue;
			const instance = new HasValue();
			mockField = createMockField( '' );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should return false when field is null', () => {
			const HasValue = registeredConditions.hasValue;
			const instance = new HasValue();
			mockField = createMockField( null );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should return true when field has non-empty array', () => {
			const HasValue = registeredConditions.hasValue;
			const instance = new HasValue();
			mockField = createMockField( [ 'item1', 'item2' ] );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when field has empty array', () => {
			const HasValue = registeredConditions.hasValue;
			const instance = new HasValue();
			mockField = createMockField( [] );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should have correct operator', () => {
			const HasValue = registeredConditions.hasValue;
			expect( HasValue.prototype.operator ).toBe( '!=empty' );
		} );

		it( 'should have correct field types', () => {
			const HasValue = registeredConditions.hasValue;
			expect( HasValue.prototype.fieldTypes ).toContain( 'text' );
			expect( HasValue.prototype.fieldTypes ).toContain( 'textarea' );
			expect( HasValue.prototype.fieldTypes ).toContain( 'number' );
			expect( HasValue.prototype.fieldTypes ).toContain( 'select' );
			expect( HasValue.prototype.fieldTypes ).toContain( 'checkbox' );
		} );
	} );

	describe( 'HasNoValue Condition', () => {
		it( 'should return true when field is empty', () => {
			const HasNoValue = registeredConditions.hasNoValue;
			const instance = new HasNoValue();
			mockField = createMockField( '' );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when field has value', () => {
			const HasNoValue = registeredConditions.hasNoValue;
			const instance = new HasNoValue();
			mockField = createMockField( 'some value' );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should return true when field has empty array', () => {
			const HasNoValue = registeredConditions.hasNoValue;
			const instance = new HasNoValue();
			mockField = createMockField( [] );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should have correct operator', () => {
			const HasNoValue = registeredConditions.hasNoValue;
			expect( HasNoValue.prototype.operator ).toBe( '==empty' );
		} );
	} );

	describe( 'EqualTo Condition', () => {
		it( 'should return true when string values match (case insensitive)', () => {
			const EqualTo = registeredConditions.equalTo;
			const instance = new EqualTo();
			mockField = createMockField( 'Hello World' );

			expect(
				instance.match( createMockRule( 'hello world' ), mockField )
			).toBe( true );
		} );

		it( 'should return true when numeric values match', () => {
			const EqualTo = registeredConditions.equalTo;
			const instance = new EqualTo();
			mockField = createMockField( '42' );

			expect( instance.match( createMockRule( '42' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return true when comparing numeric string to number', () => {
			const EqualTo = registeredConditions.equalTo;
			const instance = new EqualTo();
			mockField = createMockField( 42 );

			expect( instance.match( createMockRule( '42' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when values do not match', () => {
			const EqualTo = registeredConditions.equalTo;
			const instance = new EqualTo();
			mockField = createMockField( 'foo' );

			expect( instance.match( createMockRule( 'bar' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should return false when numeric values do not match', () => {
			const EqualTo = registeredConditions.equalTo;
			const instance = new EqualTo();
			mockField = createMockField( 10 );

			expect( instance.match( createMockRule( '20' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should have correct operator', () => {
			const EqualTo = registeredConditions.equalTo;
			expect( EqualTo.prototype.operator ).toBe( '==' );
		} );

		it( 'should have correct field types', () => {
			const EqualTo = registeredConditions.equalTo;
			expect( EqualTo.prototype.fieldTypes ).toContain( 'text' );
			expect( EqualTo.prototype.fieldTypes ).toContain( 'textarea' );
			expect( EqualTo.prototype.fieldTypes ).toContain( 'number' );
			expect( EqualTo.prototype.fieldTypes ).toContain( 'email' );
		} );
	} );

	describe( 'NotEqualTo Condition', () => {
		it( 'should return true when values do not match', () => {
			const NotEqualTo = registeredConditions.notEqualTo;
			const instance = new NotEqualTo();
			mockField = createMockField( 'foo' );

			expect( instance.match( createMockRule( 'bar' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when values match', () => {
			const NotEqualTo = registeredConditions.notEqualTo;
			const instance = new NotEqualTo();
			mockField = createMockField( 'hello' );

			expect(
				instance.match( createMockRule( 'HELLO' ), mockField )
			).toBe( false );
		} );

		it( 'should have correct operator', () => {
			const NotEqualTo = registeredConditions.notEqualTo;
			expect( NotEqualTo.prototype.operator ).toBe( '!=' );
		} );
	} );

	describe( 'PatternMatch Condition', () => {
		it( 'should return true when pattern matches', () => {
			const PatternMatch = registeredConditions.patternMatch;
			const instance = new PatternMatch();
			mockField = createMockField( 'test123' );

			expect(
				instance.match( createMockRule( '[a-z]+[0-9]+' ), mockField )
			).toBeTruthy();
		} );

		it( 'should return false when pattern does not match', () => {
			const PatternMatch = registeredConditions.patternMatch;
			const instance = new PatternMatch();
			mockField = createMockField( '123test' );

			expect(
				instance.match( createMockRule( '^[a-z]+$' ), mockField )
			).toBeFalsy();
		} );

		it( 'should be case insensitive', () => {
			const PatternMatch = registeredConditions.patternMatch;
			const instance = new PatternMatch();
			mockField = createMockField( 'HELLO' );

			expect(
				instance.match( createMockRule( 'hello' ), mockField )
			).toBeTruthy();
		} );

		it( 'should have correct operator', () => {
			const PatternMatch = registeredConditions.patternMatch;
			expect( PatternMatch.prototype.operator ).toBe( '==pattern' );
		} );

		it( 'should have correct field types', () => {
			const PatternMatch = registeredConditions.patternMatch;
			expect( PatternMatch.prototype.fieldTypes ).toContain( 'text' );
			expect( PatternMatch.prototype.fieldTypes ).toContain( 'textarea' );
			expect( PatternMatch.prototype.fieldTypes ).toContain( 'email' );
			expect( PatternMatch.prototype.fieldTypes ).toContain( 'wysiwyg' );
		} );
	} );

	describe( 'Contains Condition', () => {
		it( 'should return true when string contains substring', () => {
			const Contains = registeredConditions.contains;
			const instance = new Contains();
			mockField = createMockField( 'hello world' );

			expect(
				instance.match( createMockRule( 'world' ), mockField )
			).toBe( true );
		} );

		it( 'should return false when string does not contain substring', () => {
			const Contains = registeredConditions.contains;
			const instance = new Contains();
			mockField = createMockField( 'hello world' );

			expect( instance.match( createMockRule( 'foo' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should be case sensitive', () => {
			const Contains = registeredConditions.contains;
			const instance = new Contains();
			mockField = createMockField( 'Hello World' );

			// Note: contains uses indexOf which is case sensitive
			expect(
				instance.match( createMockRule( 'Hello' ), mockField )
			).toBe( true );
		} );

		it( 'should have correct operator', () => {
			const Contains = registeredConditions.contains;
			expect( Contains.prototype.operator ).toBe( '==contains' );
		} );
	} );

	describe( 'GreaterThan Condition', () => {
		it( 'should return true when field value is greater', () => {
			const GreaterThan = registeredConditions.greaterThan;
			const instance = new GreaterThan();
			mockField = createMockField( 10 );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when field value is less', () => {
			const GreaterThan = registeredConditions.greaterThan;
			const instance = new GreaterThan();
			mockField = createMockField( 3 );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should return false when values are equal', () => {
			const GreaterThan = registeredConditions.greaterThan;
			const instance = new GreaterThan();
			mockField = createMockField( 5 );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should work with float values', () => {
			const GreaterThan = registeredConditions.greaterThan;
			const instance = new GreaterThan();
			mockField = createMockField( 5.5 );

			expect( instance.match( createMockRule( '5.4' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should use array length when field value is array', () => {
			const GreaterThan = registeredConditions.greaterThan;
			const instance = new GreaterThan();
			mockField = createMockField( [ 1, 2, 3, 4, 5 ] );

			expect( instance.match( createMockRule( '3' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should have correct operator', () => {
			const GreaterThan = registeredConditions.greaterThan;
			expect( GreaterThan.prototype.operator ).toBe( '>' );
		} );

		it( 'should have correct field types', () => {
			const GreaterThan = registeredConditions.greaterThan;
			expect( GreaterThan.prototype.fieldTypes ).toContain( 'number' );
			expect( GreaterThan.prototype.fieldTypes ).toContain( 'range' );
		} );
	} );

	describe( 'LessThan Condition', () => {
		it( 'should return true when field value is less', () => {
			const LessThan = registeredConditions.lessThan;
			const instance = new LessThan();
			mockField = createMockField( 3 );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when field value is greater', () => {
			const LessThan = registeredConditions.lessThan;
			const instance = new LessThan();
			mockField = createMockField( 10 );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should return false when values are equal', () => {
			const LessThan = registeredConditions.lessThan;
			const instance = new LessThan();
			mockField = createMockField( 5 );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should return true when field value is undefined', () => {
			const LessThan = registeredConditions.lessThan;
			const instance = new LessThan();
			mockField = createMockField( undefined );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return true when field value is null', () => {
			const LessThan = registeredConditions.lessThan;
			const instance = new LessThan();
			mockField = createMockField( null );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return true when field value is false', () => {
			const LessThan = registeredConditions.lessThan;
			const instance = new LessThan();
			mockField = createMockField( false );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should have correct operator', () => {
			const LessThan = registeredConditions.lessThan;
			expect( LessThan.prototype.operator ).toBe( '<' );
		} );
	} );

	describe( 'SelectEqualTo Condition', () => {
		it( 'should return true when single value matches', () => {
			const SelectEqualTo = registeredConditions.selectEqualTo;
			const instance = new SelectEqualTo();
			mockField = createMockField( 'option1' );

			expect(
				instance.match( createMockRule( 'option1' ), mockField )
			).toBe( true );
		} );

		it( 'should return true when array contains matching value', () => {
			const SelectEqualTo = registeredConditions.selectEqualTo;
			const instance = new SelectEqualTo();
			mockField = createMockField( [ 'option1', 'option2', 'option3' ] );

			expect(
				instance.match( createMockRule( 'option2' ), mockField )
			).toBe( true );
		} );

		it( 'should return false when array does not contain value', () => {
			const SelectEqualTo = registeredConditions.selectEqualTo;
			const instance = new SelectEqualTo();
			mockField = createMockField( [ 'option1', 'option2' ] );

			expect(
				instance.match( createMockRule( 'option3' ), mockField )
			).toBe( false );
		} );

		it( 'should have correct field types', () => {
			const SelectEqualTo = registeredConditions.selectEqualTo;
			expect( SelectEqualTo.prototype.fieldTypes ).toContain( 'select' );
			expect( SelectEqualTo.prototype.fieldTypes ).toContain(
				'checkbox'
			);
			expect( SelectEqualTo.prototype.fieldTypes ).toContain( 'radio' );
			expect( SelectEqualTo.prototype.fieldTypes ).toContain(
				'button_group'
			);
		} );
	} );

	describe( 'SelectNotEqualTo Condition', () => {
		it( 'should return true when values do not match', () => {
			const SelectNotEqualTo = registeredConditions.selectNotEqualTo;
			const instance = new SelectNotEqualTo();
			mockField = createMockField( 'option1' );

			expect(
				instance.match( createMockRule( 'option2' ), mockField )
			).toBe( true );
		} );

		it( 'should return false when values match', () => {
			const SelectNotEqualTo = registeredConditions.selectNotEqualTo;
			const instance = new SelectNotEqualTo();
			mockField = createMockField( 'option1' );

			expect(
				instance.match( createMockRule( 'option1' ), mockField )
			).toBe( false );
		} );
	} );

	describe( 'SelectionGreaterThan Condition', () => {
		it( 'should compare array length against rule value', () => {
			const SelectionGreaterThan =
				registeredConditions.selectionGreaterThan;
			const instance = new SelectionGreaterThan();
			mockField = createMockField( [ 'a', 'b', 'c', 'd' ] );

			expect( instance.match( createMockRule( '3' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when array length is not greater', () => {
			const SelectionGreaterThan =
				registeredConditions.selectionGreaterThan;
			const instance = new SelectionGreaterThan();
			mockField = createMockField( [ 'a', 'b' ] );

			expect( instance.match( createMockRule( '3' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should have correct field types', () => {
			const SelectionGreaterThan =
				registeredConditions.selectionGreaterThan;
			expect( SelectionGreaterThan.prototype.fieldTypes ).toContain(
				'checkbox'
			);
			expect( SelectionGreaterThan.prototype.fieldTypes ).toContain(
				'select'
			);
			expect( SelectionGreaterThan.prototype.fieldTypes ).toContain(
				'post_object'
			);
			expect( SelectionGreaterThan.prototype.fieldTypes ).toContain(
				'relationship'
			);
			expect( SelectionGreaterThan.prototype.fieldTypes ).toContain(
				'taxonomy'
			);
			expect( SelectionGreaterThan.prototype.fieldTypes ).toContain(
				'user'
			);
		} );
	} );

	describe( 'SelectionLessThan Condition', () => {
		it( 'should compare array length against rule value', () => {
			const SelectionLessThan = registeredConditions.selectionLessThan;
			const instance = new SelectionLessThan();
			mockField = createMockField( [ 'a', 'b' ] );

			expect( instance.match( createMockRule( '3' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when array length is not less', () => {
			const SelectionLessThan = registeredConditions.selectionLessThan;
			const instance = new SelectionLessThan();
			mockField = createMockField( [ 'a', 'b', 'c', 'd' ] );

			expect( instance.match( createMockRule( '3' ), mockField ) ).toBe(
				false
			);
		} );
	} );

	describe( 'HasPageLink Condition', () => {
		it( 'should return true when page link value matches', () => {
			const HasPageLink = registeredConditions.hasPageLink;
			const instance = new HasPageLink();
			mockField = createMockField( '123' );

			expect( instance.match( createMockRule( '123' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when page link value does not match', () => {
			const HasPageLink = registeredConditions.hasPageLink;
			const instance = new HasPageLink();
			mockField = createMockField( '123' );

			expect( instance.match( createMockRule( '456' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should have correct field types', () => {
			const HasPageLink = registeredConditions.hasPageLink;
			expect( HasPageLink.prototype.fieldTypes ).toContain( 'page_link' );
		} );
	} );

	describe( 'ContainsPageLink Condition', () => {
		it( 'should return true when array contains page link', () => {
			const ContainsPageLink = registeredConditions.containsPageLink;
			const instance = new ContainsPageLink();
			mockField = createMockField( [ '123', '456', '789' ] );

			expect( instance.match( createMockRule( '456' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when array does not contain page link', () => {
			const ContainsPageLink = registeredConditions.containsPageLink;
			const instance = new ContainsPageLink();
			mockField = createMockField( [ '123', '456' ] );

			expect( instance.match( createMockRule( '789' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should work with single value (not array)', () => {
			const ContainsPageLink = registeredConditions.containsPageLink;
			const instance = new ContainsPageLink();
			mockField = createMockField( '123' );

			expect( instance.match( createMockRule( '123' ), mockField ) ).toBe(
				true
			);
		} );
	} );

	describe( 'HasAnyPageLink Condition', () => {
		it( 'should return true when page link has value', () => {
			const HasAnyPageLink = registeredConditions.hasAnyPageLink;
			const instance = new HasAnyPageLink();
			mockField = createMockField( '123' );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return true when page link array has items', () => {
			const HasAnyPageLink = registeredConditions.hasAnyPageLink;
			const instance = new HasAnyPageLink();
			mockField = createMockField( [ '123', '456' ] );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when page link is empty', () => {
			const HasAnyPageLink = registeredConditions.hasAnyPageLink;
			const instance = new HasAnyPageLink();
			mockField = createMockField( '' );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should return false when page link array is empty', () => {
			const HasAnyPageLink = registeredConditions.hasAnyPageLink;
			const instance = new HasAnyPageLink();
			mockField = createMockField( [] );

			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				false
			);
		} );
	} );

	describe( 'HasUser Condition', () => {
		it( 'should return true when user ID matches', () => {
			const HasUser = registeredConditions.hasUser;
			const instance = new HasUser();
			mockField = createMockField( '5' );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return true when comparing number to string', () => {
			const HasUser = registeredConditions.hasUser;
			const instance = new HasUser();
			mockField = createMockField( 5 );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return true when array contains single matching value', () => {
			const HasUser = registeredConditions.hasUser;
			const instance = new HasUser();
			mockField = createMockField( [ 5 ] );

			expect( instance.match( createMockRule( '5' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when user ID does not match', () => {
			const HasUser = registeredConditions.hasUser;
			const instance = new HasUser();
			mockField = createMockField( '5' );

			expect( instance.match( createMockRule( '10' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should have correct field types', () => {
			const HasUser = registeredConditions.hasUser;
			expect( HasUser.prototype.fieldTypes ).toContain( 'user' );
		} );
	} );

	describe( 'HasRelationship Condition', () => {
		it( 'should return true when relationship ID matches', () => {
			const HasRelationship = registeredConditions.hasRelationship;
			const instance = new HasRelationship();
			mockField = createMockField( '10' );

			expect( instance.match( createMockRule( '10' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should have correct field types', () => {
			const HasRelationship = registeredConditions.hasRelationship;
			expect( HasRelationship.prototype.fieldTypes ).toContain(
				'relationship'
			);
		} );
	} );

	describe( 'ContainsRelationship Condition', () => {
		it( 'should return true when array contains relationship (as integer)', () => {
			const ContainsRelationship =
				registeredConditions.containsRelationship;
			const instance = new ContainsRelationship();
			// Note: Relationship values are stored as integers in arrays
			mockField = createMockField( [ 10, 20, 30 ] );

			expect( instance.match( createMockRule( '20' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when array does not contain relationship', () => {
			const ContainsRelationship =
				registeredConditions.containsRelationship;
			const instance = new ContainsRelationship();
			mockField = createMockField( [ 10, 20, 30 ] );

			expect( instance.match( createMockRule( '40' ), mockField ) ).toBe(
				false
			);
		} );
	} );

	describe( 'HasPostObject Condition', () => {
		it( 'should return true when post object ID matches', () => {
			const HasPostObject = registeredConditions.hasPostObject;
			const instance = new HasPostObject();
			mockField = createMockField( '15' );

			expect( instance.match( createMockRule( '15' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should have correct field types', () => {
			const HasPostObject = registeredConditions.hasPostObject;
			expect( HasPostObject.prototype.fieldTypes ).toContain(
				'post_object'
			);
		} );
	} );

	describe( 'HasTerm Condition', () => {
		it( 'should return true when term ID matches', () => {
			const HasTerm = registeredConditions.hasTerm;
			const instance = new HasTerm();
			mockField = createMockField( '8' );

			expect( instance.match( createMockRule( '8' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should have correct field types', () => {
			const HasTerm = registeredConditions.hasTerm;
			expect( HasTerm.prototype.fieldTypes ).toContain( 'taxonomy' );
		} );
	} );

	describe( 'TrueFalseEqualTo Condition', () => {
		it( 'should return true when true/false value is checked (1)', () => {
			const TrueFalseEqualTo = registeredConditions.trueFalseEqualTo;
			const instance = new TrueFalseEqualTo();
			mockField = createMockField( '1' );

			expect( instance.match( createMockRule( '1' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should return false when true/false value is unchecked', () => {
			const TrueFalseEqualTo = registeredConditions.trueFalseEqualTo;
			const instance = new TrueFalseEqualTo();
			mockField = createMockField( '' );

			expect( instance.match( createMockRule( '1' ), mockField ) ).toBe(
				false
			);
		} );

		it( 'should have correct field types', () => {
			const TrueFalseEqualTo = registeredConditions.trueFalseEqualTo;
			expect( TrueFalseEqualTo.prototype.fieldTypes ).toContain(
				'true_false'
			);
		} );
	} );

	describe( 'Edge Cases', () => {
		it( 'should handle null and undefined values gracefully', () => {
			const EqualTo = registeredConditions.equalTo;
			const instance = new EqualTo();

			mockField = createMockField( null );
			expect(
				instance.match( createMockRule( 'test' ), mockField )
			).toBe( false );

			mockField = createMockField( undefined );
			expect(
				instance.match( createMockRule( 'test' ), mockField )
			).toBe( false );
		} );

		it( 'should handle empty string rule values', () => {
			const EqualTo = registeredConditions.equalTo;
			const instance = new EqualTo();

			mockField = createMockField( '' );
			expect( instance.match( createMockRule( '' ), mockField ) ).toBe(
				true
			);
		} );

		it( 'should handle special characters in pattern matching', () => {
			const PatternMatch = registeredConditions.patternMatch;
			const instance = new PatternMatch();

			mockField = createMockField( 'test@example.com' );
			expect(
				instance.match(
					createMockRule( '[a-z]+@[a-z]+\\.[a-z]+' ),
					mockField
				)
			).toBeTruthy();
		} );
	} );
} );
