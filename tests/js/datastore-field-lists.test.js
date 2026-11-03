/**
 * Unit tests for the field lists the datastore sends with block editor saves.
 */

describe( 'Datastore field lists', () => {
	let editPost;
	let originalData;

	const renderInputs = ( inputs ) => {
		document.body.innerHTML = inputs
			.map(
				( { name, value, disabled } ) =>
					`<input type="hidden" name="${ name }" value="${ value }"${
						disabled ? ' disabled' : ''
					}>`
			)
			.join( '' );
	};

	const savedMeta = () =>
		JSON.parse( editPost.mock.calls[ 0 ][ 0 ].meta._acf );

	beforeEach( () => {
		editPost = jest.fn();
		originalData = global.wp.data;

		global.acf = {
			isGutenbergPostEditor: () => true,
		};

		global.wp.data = {};
		jest.isolateModules( () => {
			require( '../../assets/src/js/pro/acf-datastore.js' );
		} );

		global.wp.data = {
			select: () => ( {
				isInitialized: () => true,
				getAllValues: () => ( { field_text: 'Value' } ),
				getField: () => ( { type: 'text' } ),
			} ),
			dispatch: () => ( { editPost } ),
		};
	} );

	afterEach( () => {
		global.wp.data = originalData;
		delete global.acf;
		document.body.innerHTML = '';
	} );

	it( 'sends the rendered field lists keyed by render ID', () => {
		renderInputs( [
			{ name: '_acf_fields[123][]', value: 'field_text,nonce' },
			{ name: '_acf_fields[123][]', value: 'field_other,nonce' },
			{ name: '_acf_fields[acf_form/abc][]', value: 'field_form,nonce' },
		] );

		global.acf.gutenbergEditPost();

		expect( savedMeta() ).toEqual( {
			field_text: 'Value',
			_acf_fields: {
				123: [ 'field_text,nonce', 'field_other,nonce' ],
				'acf_form/abc': [ 'field_form,nonce' ],
			},
		} );
	} );

	it( 'skips disabled, empty and malformed list inputs', () => {
		renderInputs( [
			{ name: '_acf_fields[123][]', value: 'kept,nonce' },
			{
				name: '_acf_fields[123][]',
				value: 'disabled,nonce',
				disabled: true,
			},
			{ name: '_acf_fields[123][]', value: '' },
			{ name: '_acf_fields[][]', value: 'no-render-id,nonce' },
			{ name: '_acf_fields[a]b][]', value: 'bracket,nonce' },
			{ name: '_acf_fields[123]', value: 'no-suffix,nonce' },
			{ name: '_acf_fields[123][]x', value: 'trailing,nonce' },
		] );

		global.acf.gutenbergEditPost();

		expect( savedMeta()._acf_fields ).toEqual( {
			123: [ 'kept,nonce' ],
		} );
	} );

	it( 'leaves the field lists out when none are rendered', () => {
		renderInputs( [] );

		global.acf.gutenbergEditPost();

		expect( savedMeta() ).toEqual( { field_text: 'Value' } );
	} );
} );
