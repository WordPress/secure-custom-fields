/**
 * Unit tests for admin commands version-based registration
 */

let mockCommandStore = null;

jest.mock( '@wordpress/data', () => ( {
	dispatch: jest.fn( () => mockCommandStore ),
} ) );

jest.mock( '@wordpress/i18n', () => ( { __: ( str ) => str } ) );
jest.mock( '@wordpress/element', () => ( { createElement: jest.fn() } ) );
jest.mock( '@wordpress/components', () => ( { Icon: 'Icon' } ) );
jest.mock( '@wordpress/url', () => ( { addQueryArgs: jest.fn() } ) );
jest.mock( '@wordpress/icons', () => ( {
	layout: 'icon',
	plus: 'icon',
	postList: 'icon',
	category: 'icon',
	settings: 'icon',
	tool: 'icon',
	upload: 'icon',
	download: 'icon',
} ) );

describe( 'Admin Commands', () => {
	let mockRegisterCommand;
	let capturedCallback;

	beforeEach( () => {
		jest.clearAllMocks();
		jest.resetModules();
		mockRegisterCommand = jest.fn();
		mockCommandStore = { registerCommand: mockRegisterCommand };
		window.requestIdleCallback = jest.fn( ( cb ) => {
			capturedCallback = cb;
		} );
	} );

	// 7 viewCommands + 4 createCommands = 11 on WP < 6.9
	// 4 createCommands only on WP 6.9+ (viewCommands skipped)
	it.each( [
		[ '6.8', 11 ],
		[ '6.8.1', 11 ],
		[ '6.9', 4 ],
		[ '6.9-beta1', 4 ],
		[ '6.9.1', 4 ],
		[ '7.0', 4 ],
		[ undefined, 0 ],
	] )( 'WP %s registers %i commands', async ( wpVersion, expectedCount ) => {
		window.acf = {
			data: {
				admin_url: 'http://example.com/wp-admin/',
				wp_version: wpVersion,
			},
		};

		await import( '../../../assets/src/js/commands/admin-commands.js' );
		capturedCallback();

		expect( mockRegisterCommand ).toHaveBeenCalledTimes( expectedCount );
	} );

	it( 'WP 6.9+ skips view commands but keeps create commands', async () => {
		window.acf = {
			data: {
				admin_url: 'http://example.com/wp-admin/',
				wp_version: '6.9',
			},
		};

		await import( '../../../assets/src/js/commands/admin-commands.js' );
		capturedCallback();

		const names = mockRegisterCommand.mock.calls.map(
			( c ) => c[ 0 ].name
		);

		// View commands should NOT be registered
		expect( names ).not.toContain( 'scf/field-groups' );
		expect( names ).not.toContain( 'scf/post-types' );

		// Create commands should be registered
		expect( names ).toContain( 'scf/new-field-group' );
		expect( names ).toContain( 'scf/new-post-type' );
	} );
} );
