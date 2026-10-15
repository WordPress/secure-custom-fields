/**
 * Unit tests for BlockEdit component
 * Tests the main block editing component for ACF blocks in Gutenberg
 */

// These globals are set up in the test environment
// eslint-disable-next-line no-redeclare
/* global acf, wp, MouseEvent */

import React from 'react';
import { render, screen, act, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock md5
jest.mock( 'md5', () => jest.fn( ( str ) => `md5-${ str.slice( 0, 10 ) }` ) );

// Mock post-locking utilities
jest.mock( '../../../assets/src/js/pro/blocks-v3/utils/post-locking', () => ( {
	lockPostSaving: jest.fn(),
	unlockPostSaving: jest.fn(),
	lockPostSavingByName: jest.fn(),
	unlockPostSavingByName: jest.fn(),
	sortObjectKeys: jest.fn( ( obj ) => {
		return Object.keys( obj )
			.sort()
			.reduce( ( sortedObj, key ) => {
				sortedObj[ key ] = obj[ key ];
				return sortedObj;
			}, {} );
	} ),
} ) );

// Mock child components
jest.mock(
	'../../../assets/src/js/pro/blocks-v3/components/block-placeholder',
	() => ( {
		BlockPlaceholder: ( { blockLabel } ) => (
			<div data-testid="block-placeholder">{ blockLabel }</div>
		),
	} )
);

jest.mock(
	'../../../assets/src/js/pro/blocks-v3/components/block-form',
	() => ( {
		BlockForm: ( { blockFormHtml } ) => (
			<div data-testid="block-form">
				{ blockFormHtml ? 'Form loaded' : 'No form' }
			</div>
		),
	} )
);

jest.mock(
	'../../../assets/src/js/pro/blocks-v3/components/block-preview',
	() => ( {
		BlockPreview: ( { children, blockProps } ) => (
			<div data-testid="block-preview" { ...blockProps }>
				{ children }
			</div>
		),
	} )
);

jest.mock(
	'../../../assets/src/js/pro/blocks-v3/components/error-boundary',
	() => ( {
		ErrorBoundary: ( { children } ) => (
			<div data-testid="error-boundary">{ children }</div>
		),
		BlockPreviewErrorFallback: ( { blockLabel, error } ) => (
			<div data-testid="error-fallback">
				Error: { blockLabel } - { error?.message }
			</div>
		),
	} )
);

jest.mock(
	'../../../assets/src/js/pro/blocks-v3/components/block-toolbar-fields',
	() => ( {
		BlockToolbarFields: () => (
			<div data-testid="block-toolbar-fields">Toolbar</div>
		),
	} )
);

jest.mock(
	'../../../assets/src/js/pro/blocks-v3/components/inline-editing-toolbar',
	() => ( {
		InlineEditingToolbar: () => (
			<div data-testid="inline-editing-toolbar">Inline Toolbar</div>
		),
	} )
);

jest.mock(
	'../../../assets/src/js/pro/blocks-v3/components/popover-wrapper',
	() => ( {
		PopoverWrapper: ( { children } ) => (
			<div data-testid="popover-wrapper">{ children }</div>
		),
	} )
);

// Mock @wordpress/element
jest.mock(
	'@wordpress/element',
	() => ( {
		...jest.requireActual( 'react' ),
		useState: jest.requireActual( 'react' ).useState,
		useEffect: jest.requireActual( 'react' ).useEffect,
		useRef: jest.requireActual( 'react' ).useRef,
		useMemo: jest.requireActual( 'react' ).useMemo,
		createPortal: ( children ) => children,
	} ),
	{ virtual: true }
);

// Mock @wordpress/block-editor
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		InspectorControls: ( { children } ) => (
			<div data-testid="inspector-controls">{ children }</div>
		),
		useBlockProps: jest.fn( ( props ) => ( {
			...props,
			'data-testid': 'block-props',
		} ) ),
		useBlockEditContext: jest.fn( () => ( {
			clientId: 'mock-client-id',
		} ) ),
	} ),
	{ virtual: true }
);

// Mock @wordpress/components
jest.mock(
	'@wordpress/components',
	() => {
		const mockReact = require( 'react' );
		return {
			Button: ( { children, onClick } ) =>
				mockReact.createElement(
					'button',
					{ onClick, 'data-testid': 'button' },
					children
				),
			Placeholder: ( { children } ) =>
				mockReact.createElement(
					'div',
					{ 'data-testid': 'placeholder' },
					children
				),
			Spinner: () =>
				mockReact.createElement( 'div', { 'data-testid': 'spinner' } ),
			Modal: ( { children, title, onRequestClose } ) =>
				mockReact.createElement(
					'div',
					{
						'data-testid': 'modal',
						role: 'dialog',
						'aria-label': title,
					},
					mockReact.createElement(
						'button',
						{
							onClick: onRequestClose,
							'data-testid': 'modal-close',
						},
						'Close'
					),
					children
				),
		};
	},
	{ virtual: true }
);

// Mock jQuery
const mockAjax = jest.fn( () => ( {
	done: jest.fn( function ( callback ) {
		callback( {
			data: {
				form: '<div>Form HTML</div>',
				preview: '<div>Preview HTML</div>',
				validation: { valid: true },
			},
		} );
		return this;
	} ),
	fail: jest.fn( function () {
		return this;
	} ),
	abort: jest.fn(),
} ) );

const mockJQuery = jest.fn( () => ( {
	find: jest.fn( () => ( { length: 0 } ) ),
	ajax: mockAjax,
} ) );
mockJQuery.ajax = mockAjax;

// Mock acf global
global.acf = {
	__: jest.fn( ( key ) => key ),
	debug: jest.fn(),
	get: jest.fn( ( key ) => {
		if ( key === 'ajaxurl' ) {
			return '/wp-admin/admin-ajax.php';
		}
		if ( key === 'preloadedBlocks' ) {
			return {};
		}
		return undefined;
	} ),
	prepareForAjax: jest.fn( ( data ) => data ),
	applyFilters: jest.fn( ( hook, value ) => value ),
	doAction: jest.fn(),
	serialize: jest.fn( () => ( { field_1: 'value_1' } ) ),
	parseJSX: jest.fn( ( html ) => <div>{ html }</div> ),
	normalizeFlexibleContentData: jest.fn( ( data ) => data ),
	blockEdit: {},
};

// Mock wp.data
global.wp = {
	data: {
		dispatch: jest.fn( () => ( {
			lockPostSaving: jest.fn(),
			unlockPostSaving: jest.fn(),
		} ) ),
		select: jest.fn( () => ( {
			getBlockParents: jest.fn( () => [] ),
			getBlocksByClientId: jest.fn( () => [] ),
		} ) ),
	},
};

// Import after all mocks
import { BlockEdit } from '../../../assets/src/js/pro/blocks-v3/components/block-edit';

describe( 'BlockEdit Component', () => {
	const defaultProps = {
		attributes: {
			name: 'acf/test-block',
			data: { field_1: 'value_1' },
		},
		setAttributes: jest.fn(),
		context: {},
		isSelected: false,
		$: mockJQuery,
		blockType: {
			title: 'Test Block',
			validate: true,
			icon: 'edit',
		},
	};

	beforeEach( () => {
		jest.clearAllMocks();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'renders block preview container', async () => {
		await act( async () => {
			render( <BlockEdit { ...defaultProps } /> );
		} );

		// BlockEdit uses useBlockProps which adds block-props testid
		// The preview content is rendered inside the block-props container
		expect( screen.getByTestId( 'block-props' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'block-props' ) ).toHaveClass(
			'acf-block-preview'
		);
	} );

	test( 'renders error boundary wrapper', async () => {
		await act( async () => {
			render( <BlockEdit { ...defaultProps } /> );
		} );

		expect( screen.getByTestId( 'error-boundary' ) ).toBeInTheDocument();
	} );

	test( 'renders toolbar fields component', async () => {
		await act( async () => {
			render( <BlockEdit { ...defaultProps } /> );
		} );

		expect(
			screen.getByTestId( 'block-toolbar-fields' )
		).toBeInTheDocument();
	} );

	test( 'renders inspector controls', async () => {
		await act( async () => {
			render( <BlockEdit { ...defaultProps } /> );
		} );

		expect(
			screen.getByTestId( 'inspector-controls' )
		).toBeInTheDocument();
	} );

	test( 'initializes preview state correctly', async () => {
		// Mock no preloaded data - component will fetch via AJAX
		acf.get.mockReturnValue( undefined );

		await act( async () => {
			render( <BlockEdit { ...defaultProps } /> );
		} );

		// Component should render with block-props container
		// The AJAX mock immediately returns data, so no spinner is shown
		expect( screen.getByTestId( 'block-props' ) ).toBeInTheDocument();
	} );

	test( 'initializes acf.blockEdit namespace', async () => {
		await act( async () => {
			render( <BlockEdit { ...defaultProps } /> );
		} );

		expect( acf.blockEdit ).toBeDefined();
		expect( typeof acf.blockEdit.setCurrentInlineEditingElementUid ).toBe(
			'function'
		);
		expect( typeof acf.blockEdit.setCurrentInlineEditingElement ).toBe(
			'function'
		);
		expect( typeof acf.blockEdit.setCurrentContentEditableElement ).toBe(
			'function'
		);
		expect( typeof acf.blockEdit.getBlockFieldInfo ).toBe( 'function' );
	} );

	test( 'calls setAttributes when validation errors change', async () => {
		const setAttributes = jest.fn();

		await act( async () => {
			render(
				<BlockEdit
					{ ...defaultProps }
					setAttributes={ setAttributes }
				/>
			);
		} );

		// setAttributes should be called with hasAcfError
		await waitFor( () => {
			expect( setAttributes ).toHaveBeenCalledWith(
				expect.objectContaining( {
					hasAcfError: expect.any( Boolean ),
				} )
			);
		} );
	} );

	test( 'handles isSelected prop change', async () => {
		const { rerender } = render(
			<BlockEdit { ...defaultProps } isSelected={ false } />
		);

		await act( async () => {
			jest.runAllTimers();
		} );

		rerender( <BlockEdit { ...defaultProps } isSelected={ true } /> );

		await act( async () => {
			jest.runAllTimers();
		} );

		// Component should handle selection change without errors
		// Check for block-props which contains the preview container
		expect( screen.getByTestId( 'block-props' ) ).toBeInTheDocument();
	} );

	test( 'handles context prop', async () => {
		const context = { postId: 123, postType: 'post' };

		await act( async () => {
			render( <BlockEdit { ...defaultProps } context={ context } /> );
		} );

		// Context should be used for hash generation
		// Check for component rendering via block-props
		expect( screen.getByTestId( 'block-props' ) ).toBeInTheDocument();
	} );

	test( 'handles blockType without validate', async () => {
		const blockType = {
			title: 'Simple Block',
			validate: false,
			icon: 'star',
		};

		await act( async () => {
			render( <BlockEdit { ...defaultProps } blockType={ blockType } /> );
		} );

		// Check component renders without errors
		expect( screen.getByTestId( 'block-props' ) ).toBeInTheDocument();
	} );

	test( 'mounts the block form on the first inline editing click', async () => {
		await act( async () => {
			render( <BlockEdit { ...defaultProps } isSelected={ false } /> );
		} );
		await act( async () => {
			jest.advanceTimersByTime( 250 );
		} );

		expect( screen.queryByTestId( 'block-form' ) ).not.toBeInTheDocument();

		// The preview passes this handler to inline editable elements.
		const onInlineEditingElementSelected =
			acf.parseJSX.mock.calls.at( -1 )[ 1 ];

		await act( async () => {
			onInlineEditingElementSelected( 'inline-uid' );
		} );

		expect( screen.getByTestId( 'block-form' ) ).toBeInTheDocument();
	} );

	describe( 'renderPreview', () => {
		beforeEach( () => {
			// Like the server, only return a preview when one is requested.
			mockAjax.mockImplementation( ( options ) => ( {
				done( callback ) {
					callback( {
						data: {
							form: '<div>Form HTML</div>',
							preview: options.data.query.preview
								? '<div>Preview HTML</div>'
								: undefined,
							validation: { valid: true },
						},
					} );
					return this;
				},
				fail() {
					return this;
				},
				abort: jest.fn(),
			} ) );
		} );

		/**
		 * Renders the block, runs the debounced fetch and returns the query
		 * sent to the server.
		 *
		 * @param {Object} blockType The block type configuration.
		 * @return {Object} The AJAX query options.
		 */
		const renderAndFetch = async ( blockType ) => {
			await act( async () => {
				render(
					<BlockEdit
						{ ...defaultProps }
						blockType={ {
							...defaultProps.blockType,
							...blockType,
						} }
					/>
				);
			} );
			await act( async () => {
				jest.advanceTimersByTime( 250 );
			} );

			expect( mockAjax ).toHaveBeenCalled();
			return mockAjax.mock.calls.at( -1 )[ 0 ].data.query;
		};

		test( 'requests and renders the preview by default', async () => {
			const query = await renderAndFetch( {} );

			expect( query.preview ).toBe( true );
			expect( acf.parseJSX.mock.calls.at( -1 )[ 0 ] ).toBe(
				'<div>Preview HTML</div>'
			);
			expect(
				screen.queryByTestId( 'block-placeholder' )
			).not.toBeInTheDocument();
		} );

		test( 'skips the preview when render_preview is false', async () => {
			const query = await renderAndFetch( { render_preview: false } );

			expect( query ).not.toHaveProperty( 'preview' );
			expect( acf.parseJSX ).not.toHaveBeenCalled();
			expect(
				screen.getByTestId( 'block-placeholder' )
			).toBeInTheDocument();
		} );

		test( 'skips the preview for edit-only blocks', async () => {
			const query = await renderAndFetch( {
				mode: 'edit',
				supports: { mode: false },
			} );

			expect( query ).not.toHaveProperty( 'preview' );
			expect(
				screen.getByTestId( 'block-placeholder' )
			).toBeInTheDocument();
		} );

		test( 'render_preview true keeps the preview for edit-only blocks', async () => {
			const query = await renderAndFetch( {
				mode: 'edit',
				supports: { mode: false },
				render_preview: true,
			} );

			expect( query.preview ).toBe( true );
			expect(
				screen.queryByTestId( 'block-placeholder' )
			).not.toBeInTheDocument();
		} );
	} );
} );
