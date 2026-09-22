import {
	getTaxonomyEdits,
	getTaxonomyFields,
	getTaxonomyFormData,
	getTaxonomyInputName,
} from '../../assets/src/js/scf-taxonomy-editor-adapter';

describe( 'taxonomy editor adapter', () => {
	test( 'maps DataForm field ids to taxonomy input names', () => {
		expect( getTaxonomyInputName( 'plural_label' ) ).toBe(
			'acf_taxonomy[labels][name]'
		);
		expect( getTaxonomyInputName( 'singular_label' ) ).toBe(
			'acf_taxonomy[labels][singular_name]'
		);
		expect( getTaxonomyInputName( 'taxonomy' ) ).toBe(
			'acf_taxonomy[taxonomy]'
		);
		expect( getTaxonomyInputName( 'public' ) ).toBe(
			'acf_taxonomy[public]'
		);
		expect( getTaxonomyInputName( 'hierarchical' ) ).toBe(
			'acf_taxonomy[hierarchical]'
		);
	} );

	test( 'ignores unknown field ids', () => {
		expect( getTaxonomyInputName( 'unknown' ) ).toBeUndefined();
		expect( getTaxonomyEdits( { unknown: 'value' } ) ).toEqual( {} );
	} );

	test( 'maps DataForm edits to the existing save payload', () => {
		expect(
			getTaxonomyEdits( {
				plural_label: 'Genres',
				singular_label: 'Genre',
				taxonomy: 'genre',
				public: true,
				hierarchical: false,
			} )
		).toEqual( {
			'acf_taxonomy[labels][name]': 'Genres',
			'acf_taxonomy[labels][singular_name]': 'Genre',
			'acf_taxonomy[taxonomy]': 'genre',
			'acf_taxonomy[public]': true,
			'acf_taxonomy[hierarchical]': false,
		} );
	} );

	test( 'flattens stored taxonomy settings for DataForm', () => {
		expect(
			getTaxonomyFormData( {
				labels: { name: 'Genres', singular_name: 'Genre' },
				taxonomy: 'genre',
				public: '1',
				hierarchical: '',
			} )
		).toEqual( {
			plural_label: 'Genres',
			singular_label: 'Genre',
			taxonomy: 'genre',
			public: true,
			hierarchical: false,
		} );
	} );

	test( 'handles missing taxonomy data', () => {
		expect( getTaxonomyFormData( undefined ) ).toEqual( {
			plural_label: '',
			singular_label: '',
			taxonomy: '',
			public: false,
			hierarchical: false,
		} );
	} );

	test( 'defines the basic taxonomy fields', () => {
		const fields = getTaxonomyFields( ( text ) => text );

		expect( fields.map( ( field ) => field.id ) ).toEqual( [
			'plural_label',
			'singular_label',
			'taxonomy',
			'public',
			'hierarchical',
		] );
	} );

	test( 'preserves false when the toggle is turned off', () => {
		expect( getTaxonomyEdits( { public: false } ) ).toEqual( {
			'acf_taxonomy[public]': false,
		} );
	} );
} );
