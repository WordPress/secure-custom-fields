/**
 * Unit tests for custom post type commands version-based registration
 */

let mockCommandStore = null;

jest.mock( '@wordpress/data', () => ( {
	dispatch: jest.fn( () => mockCommandStore ),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	sprintf: ( str, ...args ) => str.replace( /%s/g, () => args.shift() ),
} ) );
jest.mock( '@wordpress/element', () => ( { createElement: jest.fn() } ) );
jest.mock( '@wordpress/components', () => ( { Icon: 'Icon' } ) );
jest.mock( '@wordpress/url', () => ( { addQueryArgs: jest.fn() } ) );
jest.mock( '@wordpress/icons', () => ( {
	page: 'icon',
	plus: 'icon',
	edit: 'icon',
} ) );

describe( 'Custom Post Type Commands', () => {
	let mockRegisterCommand;
	let capturedCallback;

	const mockPostTypes = [
		{
			name: 'book',
			label: 'Books',
			all_items: 'All Books',
			add_new_item: 'Add New Book',
			id: 123,
		},
		{
			name: 'movie',
			label: 'Movies',
			all_items: 'All Movies',
			add_new_item: 'Add New Movie',
			id: 456,
		},
	];

	beforeEach( () => {
		jest.clearAllMocks();
		jest.resetModules();
		mockRegisterCommand = jest.fn();
		mockCommandStore = { registerCommand: mockRegisterCommand };
		window.requestIdleCallback = jest.fn( ( cb ) => {
			capturedCallback = cb;
		} );
	} );

	it.each( [
		[ '6.8', 6 ], // 3 commands × 2 CPTs
		[ '6.8.1', 6 ],
		[ '6.9', 2 ], // Only Edit × 2 CPTs
		[ '6.9-RC1', 2 ],
		[ '6.9.1', 2 ],
		[ '7.0', 2 ],
		[ undefined, 0 ],
	] )(
		'WP %s registers %i commands for 2 CPTs',
		async ( wpVersion, expectedCount ) => {
			window.acf = {
				data: {
					admin_url: 'http://example.com/wp-admin/',
					wp_version: wpVersion,
					customPostTypes: mockPostTypes,
				},
			};

			await import(
				'../../../assets/src/js/commands/custom-post-type-commands.js'
			);
			capturedCallback();

			expect( mockRegisterCommand ).toHaveBeenCalledTimes(
				expectedCount
			);
		}
	);

	it( 'WP 6.9+ skips navigation commands but keeps Edit command', async () => {
		window.acf = {
			data: {
				admin_url: 'http://example.com/wp-admin/',
				wp_version: '6.9',
				customPostTypes: mockPostTypes,
			},
		};

		await import(
			'../../../assets/src/js/commands/custom-post-type-commands.js'
		);
		capturedCallback();

		const names = mockRegisterCommand.mock.calls.map(
			( c ) => c[ 0 ].name
		);

		// Navigation commands should NOT be registered
		expect( names ).not.toContain( 'scf/cpt-book' );
		expect( names ).not.toContain( 'scf/new-book' );

		// Edit commands should be registered
		expect( names ).toContain( 'scf/edit-book' );
		expect( names ).toContain( 'scf/edit-movie' );
	} );
} );
