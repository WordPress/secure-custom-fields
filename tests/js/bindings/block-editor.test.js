/**
 * Unit tests for block-editor.js - filter registration and module behavior
 *
 * Note: Testing React HOCs that use hooks requires careful mocking.
 * These tests focus on verifying module setup and filter registration.
 */

import { addFilter } from '@wordpress/hooks';

// Mock WordPress dependencies
jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
} ) );

jest.mock( '@wordpress/element', () => ( {
	useCallback: jest.fn( ( fn ) => fn ),
	useMemo: jest.fn( ( fn ) => fn() ),
} ) );

jest.mock( '@wordpress/compose', () => ( {
	createHigherOrderComponent: jest.fn( ( fn, name ) => {
		const hoc = fn;
		hoc.displayName = name;
		return hoc;
	} ),
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: 'InspectorControls',
	useBlockBindingsUtils: jest.fn( () => ( {
		updateBlockBindings: jest.fn(),
		removeAllBlockBindings: jest.fn(),
	} ) ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	ComboboxControl: 'ComboboxControl',
	__experimentalToolsPanel: 'ToolsPanel',
	__experimentalToolsPanelItem: 'ToolsPanelItem',
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

jest.mock( '../../../assets/src/js/bindings/constants', () => ( {
	BINDING_SOURCE: 'acf/field',
} ) );

jest.mock( '../../../assets/src/js/bindings/utils', () => ( {
	getBindableAttributes: jest.fn( () => [] ),
	getFilteredFieldOptions: jest.fn( () => [] ),
	canUseUnifiedBinding: jest.fn( () => false ),
	fieldsToOptions: jest.fn( () => [] ),
} ) );

jest.mock( '../../../assets/src/js/bindings/hooks', () => ( {
	useSiteEditorContext: jest.fn( () => ( {
		isSiteEditor: false,
		templatePostType: null,
	} ) ),
	usePostEditorFields: jest.fn( () => ( {} ) ),
	useSiteEditorFields: jest.fn( () => ( { fields: {}, isLoading: false } ) ),
	useBoundFields: jest.fn( () => ( {
		boundFields: {},
		setBoundFields: jest.fn(),
	} ) ),
} ) );

describe( 'Block Editor Module', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'Filter Registration', () => {
		it( 'should register the editor.BlockEdit filter on module load', () => {
			jest.isolateModules( () => {
				require( '../../../assets/src/js/bindings/block-editor' );
			} );

			expect( addFilter ).toHaveBeenCalledWith(
				'editor.BlockEdit',
				'secure-custom-fields/with-custom-controls',
				expect.any( Function )
			);
		} );

		it( 'should register filter with correct hook name', () => {
			jest.isolateModules( () => {
				require( '../../../assets/src/js/bindings/block-editor' );
			} );

			const [ hookName ] = addFilter.mock.calls[ 0 ];
			expect( hookName ).toBe( 'editor.BlockEdit' );
		} );

		it( 'should register filter with correct namespace', () => {
			jest.isolateModules( () => {
				require( '../../../assets/src/js/bindings/block-editor' );
			} );

			const [ , namespace ] = addFilter.mock.calls[ 0 ];
			expect( namespace ).toBe(
				'secure-custom-fields/with-custom-controls'
			);
		} );

		it( 'should register filter with a function callback', () => {
			jest.isolateModules( () => {
				require( '../../../assets/src/js/bindings/block-editor' );
			} );

			const [ , , callback ] = addFilter.mock.calls[ 0 ];
			expect( typeof callback ).toBe( 'function' );
		} );

		it( 'should only register the filter once', () => {
			jest.isolateModules( () => {
				require( '../../../assets/src/js/bindings/block-editor' );
			} );

			expect( addFilter ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'HOC Creation', () => {
		it( 'should create a higher order component', () => {
			const {
				createHigherOrderComponent,
			} = require( '@wordpress/compose' );

			jest.isolateModules( () => {
				require( '../../../assets/src/js/bindings/block-editor' );
			} );

			expect( createHigherOrderComponent ).toHaveBeenCalledWith(
				expect.any( Function ),
				'withCustomControls'
			);
		} );

		it( 'should name the HOC "withCustomControls"', () => {
			const {
				createHigherOrderComponent,
			} = require( '@wordpress/compose' );

			jest.isolateModules( () => {
				require( '../../../assets/src/js/bindings/block-editor' );
			} );

			const [ , hocName ] = createHigherOrderComponent.mock.calls[ 0 ];
			expect( hocName ).toBe( 'withCustomControls' );
		} );
	} );

	describe( 'Filter Callback', () => {
		let filterCallback;

		beforeEach( () => {
			jest.isolateModules( () => {
				require( '../../../assets/src/js/bindings/block-editor' );
			} );
			filterCallback = addFilter.mock.calls[ 0 ][ 2 ];
		} );

		it( 'should return a function when passed a BlockEdit component', () => {
			const MockBlockEdit = () => null;
			const result = filterCallback( MockBlockEdit );

			expect( typeof result ).toBe( 'function' );
		} );

		it( 'should wrap different BlockEdit components independently', () => {
			const BlockEdit1 = () => 'edit1';
			const BlockEdit2 = () => 'edit2';

			const wrapped1 = filterCallback( BlockEdit1 );
			const wrapped2 = filterCallback( BlockEdit2 );

			expect( wrapped1 ).not.toBe( wrapped2 );
		} );
	} );
} );
