/**
 * Unit tests for the google_map field type's result parsing.
 */

describe( 'Google Map Field', () => {
	let fieldDefinition;
	let mockField;

	/**
	 * Builds a GeocoderResult-like object.
	 *
	 * @param {Array} components Address components.
	 * @return {Object} The result object.
	 */
	const makeResult = ( components ) => ( {
		formatted_address: 'Formatted address',
		geometry: {
			location: {
				lat: () => 1.5,
				lng: () => 2.5,
			},
		},
		address_components: components,
	} );

	beforeEach( () => {
		fieldDefinition = null;

		global.acf = {
			Field: {
				extend: jest.fn( ( definition ) => {
					fieldDefinition = definition;
					return definition;
				} ),
			},
			registerFieldType: jest.fn(),
			applyFilters: jest.fn( ( name, value ) => value ),
		};

		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-google-map.js' );
		} );

		mockField = {
			...fieldDefinition,
			map: {
				getZoom: () => 14,
			},
		};
	} );

	afterEach( () => {
		delete global.acf;
		jest.resetModules();
	} );

	describe( 'parseResult()', () => {
		it( 'should parse the basic location data', () => {
			const result = mockField.parseResult( makeResult( [] ) );

			expect( result ).toEqual( {
				address: 'Formatted address',
				lat: 1.5,
				lng: 2.5,
				zoom: 14,
			} );
		} );

		it( 'should use the locality as the city', () => {
			const result = mockField.parseResult(
				makeResult( [
					{
						long_name: 'Springfield',
						short_name: 'Springfield',
						types: [ 'locality', 'political' ],
					},
				] )
			);

			expect( result.city ).toBe( 'Springfield' );
		} );

		it( 'should use a sublocality as the city when there is no locality', () => {
			const result = mockField.parseResult(
				makeResult( [
					{
						long_name: 'Brooklyn',
						short_name: 'Brooklyn',
						types: [
							'political',
							'sublocality',
							'sublocality_level_1',
						],
					},
				] )
			);

			expect( result.city ).toBe( 'Brooklyn' );
		} );

		it( 'should use a neighborhood as the city when there is no locality', () => {
			const result = mockField.parseResult(
				makeResult( [
					{
						long_name: 'Soho',
						short_name: 'Soho',
						types: [ 'neighborhood', 'political' ],
					},
				] )
			);

			expect( result.city ).toBe( 'Soho' );
		} );

		it( 'should prefer the locality when it comes after a neighborhood', () => {
			const result = mockField.parseResult(
				makeResult( [
					{
						long_name: 'Soho',
						short_name: 'Soho',
						types: [ 'neighborhood', 'political' ],
					},
					{
						long_name: 'London',
						short_name: 'London',
						types: [ 'postal_town' ],
					},
				] )
			);

			expect( result.city ).toBe( 'London' );
		} );

		it( 'should match component types other than the first one', () => {
			const result = mockField.parseResult(
				makeResult( [
					{
						long_name: 'Ontario',
						short_name: 'ON',
						types: [ 'political', 'administrative_area_level_1' ],
					},
				] )
			);

			expect( result.state ).toBe( 'Ontario' );
			expect( result.state_short ).toBe( 'ON' );
		} );

		it( 'should ignore components with unknown types', () => {
			const result = mockField.parseResult(
				makeResult( [
					{
						long_name: 'Somewhere',
						short_name: 'Somewhere',
						types: [ 'political', 'colloquial_area' ],
					},
				] )
			);

			expect( result.city ).toBeUndefined();
			expect( result.state ).toBeUndefined();
		} );

		it( 'should pass the result through the google_map_result filter', () => {
			const obj = makeResult( [] );
			mockField.parseResult( obj );

			expect( global.acf.applyFilters ).toHaveBeenCalledWith(
				'google_map_result',
				expect.any( Object ),
				obj,
				mockField.map,
				mockField
			);
		} );
	} );
} );
