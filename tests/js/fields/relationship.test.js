/**
 * Unit tests for the relationship field's choice paging.
 */

describe( 'Relationship Field paging', () => {
	let fieldDefinition;
	let originalJQuery;
	let ajaxOptions;
	let onceInViewCallback;
	let field;
	let state;
	let choicesList;

	const createChoicesList = ( { visible = true, items = 0 } = {} ) => ( {
		is: jest.fn( ( selector ) => ':visible' === selector && visible ),
		scrollTop: jest.fn( () => 0 ),
		innerHeight: jest.fn( () => 100 ),
		0: { scrollHeight: 100 },
		html: jest.fn(),
		append: jest.fn(),
		find: jest.fn( () => ( { length: items } ) ),
	} );

	beforeEach( () => {
		originalJQuery = global.jQuery;
		ajaxOptions = null;
		onceInViewCallback = null;

		const jQueryMock = jest.fn( () => ( { remove: jest.fn() } ) );
		jQueryMock.ajax = jest.fn( ( options ) => {
			ajaxOptions = options;
			return { abort: jest.fn() };
		} );
		global.jQuery = jQueryMock;

		global.acf = {
			Field: {
				extend: jest.fn( ( definition ) => {
					fieldDefinition = definition;
					return definition;
				} ),
			},
			registerFieldType: jest.fn(),
			onceInView: jest.fn( ( $el, callback ) => {
				onceInViewCallback = callback;
			} ),
			__: ( text ) => text,
			get: () => 'ajaxurl',
			prepareForAjax: ( data ) => data,
		};

		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-relationship.js' );
		} );

		state = { paged: 1, more: false, loading: false };
		choicesList = createChoicesList();
		field = {
			...fieldDefinition,
			get: jest.fn( ( key ) => state[ key ] ),
			set: jest.fn( ( key, value ) => {
				state[ key ] = value;
			} ),
			$list: jest.fn( () => choicesList ),
			getAjaxData: jest.fn( () => ( { paged: state.paged } ) ),
			proxy( callback ) {
				return callback.bind( this );
			},
		};
		field.fetch = jest.fn( fieldDefinition.fetch );
		field.maybeFetchMore = jest.fn( fieldDefinition.maybeFetchMore );
	} );

	afterEach( () => {
		global.jQuery = originalJQuery;
		delete global.acf;
	} );

	it( 'loads more choices when the field is shown', () => {
		expect( fieldDefinition.events.showField ).toBe( 'maybeFetchMore' );
	} );

	it( 'fetches the next page when the list is scrolled to the bottom', () => {
		state.more = true;

		field.maybeFetchMore();

		expect( state.paged ).toBe( 2 );
		expect( field.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'waits for a hidden list to come into view before fetching', () => {
		state.more = true;
		choicesList = createChoicesList( { visible: false } );

		field.maybeFetchMore();
		field.maybeFetchMore();

		expect( field.fetch ).not.toHaveBeenCalled();
		expect( global.acf.onceInView ).toHaveBeenCalledTimes( 1 );

		choicesList = createChoicesList();
		onceInViewCallback();

		expect( state.waitingForView ).toBe( false );
		expect( field.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'keeps paging when a page has no readable posts but more exist', () => {
		field.fetch();
		ajaxOptions.success.call( field, { results: [], more: true } );

		expect( state.more ).toBe( true );
		expect( choicesList.append ).toHaveBeenCalledTimes( 1 );

		ajaxOptions.complete.call( field, {}, 'success' );

		expect( state.paged ).toBe( 2 );
		expect( field.fetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does not keep paging when the query changed while loading', () => {
		field.fetch();
		ajaxOptions.success.call( field, { results: [], more: true } );
		state.paged = 5;

		ajaxOptions.complete.call( field, {}, 'success' );

		expect( field.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'shows "No matches found" only when the list has no choices', () => {
		field.fetch();
		ajaxOptions.success.call( field, { results: [], more: false } );

		expect( state.more ).toBe( false );
		expect( choicesList.append ).toHaveBeenLastCalledWith(
			'<li>No matches found</li>'
		);

		choicesList = createChoicesList( { items: 3 } );
		field.fetch();
		ajaxOptions.success.call( field, { results: [], more: false } );

		expect( choicesList.append ).not.toHaveBeenCalledWith(
			'<li>No matches found</li>'
		);
	} );
} );
