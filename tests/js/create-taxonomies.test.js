/**
 * Unit tests for the create taxonomy sidebar helpers.
 *
 * Covers the pure functions that build and validate the REST payload, which is
 * where a mismatch with the server rules would cause a silent failure.
 */

import {
	buildTaxonomyPayload,
	slugifyTaxonomyKey,
	validateTaxonomyForm,
} from '../../assets/src/js/create-taxonomies/helpers';

describe( 'slugifyTaxonomyKey', () => {
	it( 'lowercases and replaces spaces with dashes', () => {
		expect( slugifyTaxonomyKey( 'Product Genre' ) ).toBe( 'product-genre' );
	} );

	it( 'strips characters that are not valid in a taxonomy key', () => {
		expect( slugifyTaxonomyKey( 'Genre & Tag!' ) ).toBe( 'genre-tag' );
	} );

	it( 'collapses repeated separators', () => {
		expect( slugifyTaxonomyKey( 'genre   --  tag' ) ).toBe( 'genre-tag' );
	} );

	it( 'trims leading and trailing separators', () => {
		expect( slugifyTaxonomyKey( '  -genre-' ) ).toBe( 'genre' );
	} );

	it( 'caps the key at 32 characters', () => {
		const long = 'a'.repeat( 60 );
		expect( slugifyTaxonomyKey( long ) ).toHaveLength( 32 );
	} );

	it( 'returns an empty string for empty input', () => {
		expect( slugifyTaxonomyKey( '' ) ).toBe( '' );
		expect( slugifyTaxonomyKey( null ) ).toBe( '' );
	} );
} );

describe( 'validateTaxonomyForm', () => {
	const valid = {
		singularLabel: 'Genre',
		pluralLabel: 'Genres',
		taxonomy: 'genre',
		hierarchical: false,
	};

	it( 'accepts a complete valid form', () => {
		expect( validateTaxonomyForm( valid ) ).toEqual( {} );
	} );

	it( 'requires a singular label', () => {
		const errors = validateTaxonomyForm( {
			...valid,
			singularLabel: '  ',
		} );
		expect( errors.singularLabel ).toBeDefined();
	} );

	it( 'requires a plural label', () => {
		const errors = validateTaxonomyForm( { ...valid, pluralLabel: '' } );
		expect( errors.pluralLabel ).toBeDefined();
	} );

	it( 'requires a taxonomy key', () => {
		const errors = validateTaxonomyForm( { ...valid, taxonomy: '' } );
		expect( errors.taxonomy ).toBeDefined();
	} );

	it( 'rejects a key longer than 32 characters', () => {
		const errors = validateTaxonomyForm( {
			...valid,
			taxonomy: 'a'.repeat( 33 ),
		} );
		expect( errors.taxonomy ).toBeDefined();
	} );

	it( 'accepts a key of exactly 32 characters', () => {
		expect(
			validateTaxonomyForm( { ...valid, taxonomy: 'a'.repeat( 32 ) } )
		).toEqual( {} );
	} );

	it( 'rejects a key with invalid characters', () => {
		const errors = validateTaxonomyForm( {
			...valid,
			taxonomy: 'Genre Tag!',
		} );
		expect( errors.taxonomy ).toBeDefined();
	} );

	it( 'accepts underscores and dashes in the key', () => {
		expect(
			validateTaxonomyForm( { ...valid, taxonomy: 'product_genre-tag' } )
		).toEqual( {} );
	} );
} );

describe( 'buildTaxonomyPayload', () => {
	it( 'maps form values to the REST body', () => {
		const payload = buildTaxonomyPayload(
			{
				singularLabel: 'Genre',
				pluralLabel: 'Genres',
				taxonomy: 'genre',
				hierarchical: true,
			},
			'post'
		);

		expect( payload ).toEqual( {
			singular_label: 'Genre',
			plural_label: 'Genres',
			taxonomy: 'genre',
			hierarchical: true,
			object_type: [ 'post' ],
		} );
	} );

	it( 'trims whitespace from the label and key', () => {
		const payload = buildTaxonomyPayload(
			{
				singularLabel: '  Genre ',
				pluralLabel: ' Genres ',
				taxonomy: ' genre ',
				hierarchical: false,
			},
			'post'
		);

		expect( payload.singular_label ).toBe( 'Genre' );
		expect( payload.plural_label ).toBe( 'Genres' );
		expect( payload.taxonomy ).toBe( 'genre' );
	} );

	it( 'coerces hierarchical to a boolean', () => {
		const payload = buildTaxonomyPayload(
			{ singularLabel: 'G', pluralLabel: 'Gs', taxonomy: 'g' },
			'post'
		);
		expect( payload.hierarchical ).toBe( false );
	} );

	it( 'sends an empty object_type when no post type is available', () => {
		const payload = buildTaxonomyPayload(
			{ singularLabel: 'G', pluralLabel: 'Gs', taxonomy: 'g' },
			''
		);
		expect( payload.object_type ).toEqual( [] );
	} );
} );
