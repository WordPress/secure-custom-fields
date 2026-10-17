/**
 * Integration tests against real core-data resolvers and a fresh registry.
 * The tested path uses real selectors/resolvers with a mocked REST boundary.
 * Unrelated editor packages and the metadata side effect retain their mocks.
 */
jest.unmock( '@wordpress/data' );
jest.unmock( '@wordpress/core-data' );
jest.unmock( '@wordpress/element' );
jest.unmock( '@wordpress/compose' );
jest.unmock( '@wordpress/url' );
jest.unmock( '@wordpress/i18n' );
jest.unmock( '@wordpress/hooks' );

jest.mock( '../../../assets/src/js/bindings/fieldMetadataCache', () => ( {
	addFieldMetadata: jest.fn(),
} ) );

import './helpers/core-data-environment';
import { act, cleanup, render, waitFor } from '@testing-library/react';
import { createElement } from '@wordpress/element';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import apiFetch from '@wordpress/api-fetch';
import { useSiteEditorFields } from '../../../assets/src/js/bindings/hooks';

const productFields = { field1: { label: 'Field 1', type: 'text' } };
const bookFields = { bookField: { label: 'Book field', type: 'text' } };
const delay = () => new Promise( ( resolve ) => setTimeout( resolve, 20 ) );

function Consumer( { type, index, results, history } ) {
	const result = useSiteEditorFields( type );
	results[ index ] = result;
	history.push( result.fields );
	return null;
}

function mountConsumers( registry, types ) {
	const results = Array( types.length ).fill( null );
	const history = [];
	const tree = ( nextTypes ) =>
		createElement(
			RegistryProvider,
			{ value: registry },
			nextTypes.map( ( type, index ) =>
				createElement( Consumer, {
					key: index,
					type,
					index,
					results,
					history,
				} )
			)
		);
	const view = render( tree( types ) );
	return {
		...view,
		results,
		history,
		rerenderTypes: ( nextTypes ) => view.rerender( tree( nextTypes ) ),
	};
}

function holdNextRequest() {
	const fetchSchema = apiFetch.getMockImplementation();
	let release;
	const gate = new Promise( ( resolve ) => {
		release = resolve;
	} );
	apiFetch.mockImplementationOnce( async ( options ) => {
		await gate;
		return fetchSchema( options );
	} );
	return release;
}

async function expectFields( results, fields ) {
	await waitFor( () => {
		for ( const result of results ) {
			expect( result ).not.toBeNull();
			expect( result.isLoading ).toBe( false );
			expect( result.error ).toBeNull();
			expect( result.fields ).toEqual( fields );
		}
	} );
}

async function resolveRootConfiguration( registry ) {
	// Resolve root configuration normally, including its unrelated settings
	// OPTIONS request. No post-type record or resolution is preseeded.
	await registry.resolveSelect( coreDataStore ).getEntitiesConfig( 'root' );
	expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	expect( apiFetch ).toHaveBeenCalledWith( {
		path: '/wp/v2/settings',
		method: 'OPTIONS',
	} );
	apiFetch.mockClear();
}

describe( 'useSiteEditorFields with real core-data', () => {
	let registry;

	beforeEach( async () => {
		registry = createRegistry();
		registry.register( coreDataStore );
		apiFetch.mockReset();
		apiFetch.mockImplementation( async ( { path, method, parse } ) => {
			await delay();
			if ( path === '/wp/v2/settings' && method === 'OPTIONS' ) {
				return { schema: { properties: {} } };
			}
			const url = new URL( path, 'https://example.test' );
			const type = url.pathname.split( '/' ).pop();
			if ( ! [ 'product', 'book' ].includes( type ) ) {
				throw new Error( `Unexpected REST path: ${ path }` );
			}
			const fields = type === 'book' ? bookFields : productFields;
			const record = {
				slug: type,
				scf_field_groups: [
					{
						title: 'Group',
						fields: Object.entries( fields ).map(
							( [ name, metadata ] ) => ( { name, ...metadata } )
						),
					},
				],
			};
			// The real resolver requests parse:false to read permission headers.
			return parse === false ? { json: async () => record } : record;
		} );
		await resolveRootConfiguration( registry );
	} );

	afterEach( cleanup );

	it.each( [ 1, 10, 200 ] )(
		'shares one edit-schema request across %i consumers',
		async ( count ) => {
			const { results } = mountConsumers(
				registry,
				Array( count ).fill( 'product' )
			);
			await expectFields( results, productFields );
			expect( apiFetch ).toHaveBeenCalledTimes( 1 );
			const url = new URL(
				apiFetch.mock.calls[ 0 ][ 0 ].path,
				'https://example.test'
			);
			expect( url.pathname ).toBe( '/wp/v2/types/product' );
			expect( url.searchParams.get( 'context' ) ).toBe( 'edit' );
		}
	);

	it( 'joins an already pending getPostType request', async () => {
		const fetchSchema = apiFetch.getMockImplementation();
		let release;
		const gate = new Promise( ( resolve ) => {
			release = resolve;
		} );
		apiFetch.mockImplementationOnce( async ( options ) => {
			await gate;
			return fetchSchema( options );
		} );
		const pending = registry
			.resolveSelect( coreDataStore )
			.getPostType( 'product' );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 1 ) );
		const { results } = mountConsumers(
			registry,
			Array( 10 ).fill( 'product' )
		);
		const loading = results.map( ( result ) => result.isLoading );
		await act( async () => {
			release();
			await pending;
		} );
		expect( loading ).toEqual( Array( 10 ).fill( true ) );
		await expectFields( results, productFields );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'should call apiFetch with correct parameters', async () => {
		const { results } = mountConsumers( registry, [ 'product' ] );
		expect( results[ 0 ].isLoading ).toBe( true );
		await expectFields( results, productFields );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wp/v2/types/product?context=edit',
			parse: false,
		} );
	} );

	// Unlike the former per-instance cancellation test, a shared request must
	// survive one consumer unmounting so the remaining consumers can finish.
	it( 'should preserve a pending shared fetch when one consumer unmounts', async () => {
		const release = holdNextRequest();
		const first = mountConsumers( registry, [ 'product' ] );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 1 ) );
		const request = apiFetch.mock.calls[ 0 ][ 0 ];
		const second = mountConsumers( registry, [ 'product' ] );
		expect( first.results[ 0 ].isLoading ).toBe( true );
		expect( second.results[ 0 ].isLoading ).toBe( true );
		first.unmount();
		const renderCount = first.history.length;
		expect( request.signal?.aborted ).not.toBe( true );
		const pending = registry
			.resolveSelect( coreDataStore )
			.getPostType( 'product' );
		await act( async () => {
			release();
			await pending;
		} );
		await expectFields( second.results, productFields );
		expect( first.history ).toHaveLength( renderCount );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		const remounted = mountConsumers( registry, [ 'product' ] );
		await expectFields( remounted.results, productFields );
		await act( delay );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'should update when post type changes in the same consumer', async () => {
		const consumer = mountConsumers( registry, [ 'product' ] );
		await expectFields( consumer.results, productFields );
		const release = holdNextRequest();
		consumer.history.length = 0;
		consumer.rerenderTypes( [ 'book' ] );
		expect( consumer.results[ 0 ].fields ).toEqual( {} );
		expect( consumer.results[ 0 ].isLoading ).toBe( true );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );
		const pending = registry
			.resolveSelect( coreDataStore )
			.getPostType( 'book' );
		await act( async () => {
			release();
			await pending;
		} );
		await expectFields( consumer.results, bookFields );
		for ( const fields of consumer.history ) {
			expect( fields ).not.toHaveProperty( 'field1' );
		}
		consumer.rerenderTypes( [ null ] );
		await expectFields( consumer.results, {} );
		await act( delay );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'should ignore a late response for the previous type in the same consumer', async () => {
		const release = holdNextRequest();
		const consumer = mountConsumers( registry, [ 'product' ] );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 1 ) );
		expect( consumer.results[ 0 ].isLoading ).toBe( true );
		const pending = registry
			.resolveSelect( coreDataStore )
			.getPostType( 'product' );
		consumer.history.length = 0;
		consumer.rerenderTypes( [ 'book' ] );
		expect( consumer.results[ 0 ].fields ).toEqual( {} );
		await expectFields( consumer.results, bookFields );
		await act( async () => {
			release();
			await pending;
		} );
		await expectFields( consumer.results, bookFields );
		for ( const fields of consumer.history ) {
			expect( fields ).not.toHaveProperty( 'field1' );
		}
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'reuses a resolved type on remount', async () => {
		const first = mountConsumers( registry, [ 'product' ] );
		await expectFields( first.results, productFields );
		first.unmount();
		const second = mountConsumers( registry, [ 'product' ] );
		await expectFields( second.results, productFields );
		await act( delay );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'resolves a different type once without mixing fields', async () => {
		const products = mountConsumers( registry, [ 'product', 'product' ] );
		await expectFields( products.results, productFields );
		const books = mountConsumers( registry, [ 'book', 'book' ] );
		await expectFields( books.results, bookFields );
		await expectFields( products.results, productFields );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		expect( apiFetch.mock.calls[ 1 ][ 0 ].path ).toContain(
			'/wp/v2/types/book'
		);
	} );

	it( 'keeps stored fields in every render during an explicit refetch', async () => {
		const { results, history } = mountConsumers(
			registry,
			Array( 10 ).fill( 'product' )
		);
		await expectFields( results, productFields );
		history.length = 0;
		act( () => results[ 0 ].refresh() );
		for ( const result of results ) {
			expect( result.fields ).toEqual( productFields );
			expect( result.isLoading ).toBe( true );
		}
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );
		await expectFields( results, productFields );
		expect( history.length ).toBeGreaterThan( 0 );
		for ( const fields of history ) {
			expect( fields ).toEqual( productFields );
		}
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'shares a failed resolution without automatically retrying', async () => {
		const error = new Error( 'Temporary REST failure' );
		apiFetch.mockImplementationOnce( async () => {
			await delay();
			throw error;
		} );
		const { results } = mountConsumers(
			registry,
			Array( 10 ).fill( 'product' )
		);
		await waitFor( () => {
			for ( const result of results ) {
				expect( result.isLoading ).toBe( false );
				expect( result.error ).toBe( error );
				expect( result.fields ).toEqual( {} );
			}
		} );
		await act( delay );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );
} );
