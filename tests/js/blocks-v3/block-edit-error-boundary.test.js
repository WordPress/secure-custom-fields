/**
 * Unit tests for ErrorBoundary component in V3 Blocks
 * Tests the fallback behavior when block preview rendering fails due to invalid HTML
 */

import React from 'react';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock BlockPlaceholder before importing the components that use it
jest.mock(
	'../../../assets/src/js/pro/blocks-v3/components/block-placeholder',
	() => ( {
		BlockPlaceholder: ( { blockLabel, instructions } ) => (
			<div data-testid="block-placeholder">
				<div data-testid="block-label">{ blockLabel }</div>
				{ instructions && (
					<div data-testid="error-message">{ instructions }</div>
				) }
			</div>
		),
	} )
);

import {
	ErrorBoundary,
	BlockPreviewErrorFallback,
} from '../../../assets/src/js/pro/blocks-v3/components/error-boundary';

// Mock the acf global object
global.acf = {
	__: jest.fn( ( key ) => {
		const translations = {
			'Error previewing block v3':
				"The preview for this block couldn't be loaded. Review its content or settings for issues.",
			'ACF Block': 'ACF Block',
		};
		return translations[ key ] || key;
	} ),
	debug: jest.fn(),
	parseJSX: jest.fn(),
};

// Component that throws an error (simulating invalid HTML parsing)
const ThrowError = ( { shouldThrow } ) => {
	if ( shouldThrow ) {
		throw new Error( 'Invalid HTML: Unclosed tag detected' );
	}
	return <div>Valid preview content</div>;
};

describe( 'ErrorBoundary Component', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		// Suppress console.error for these tests since we're intentionally throwing errors
		jest.spyOn( console, 'error' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		console.error.mockRestore();
	} );

	test( 'renders children normally when no error occurs', () => {
		render(
			<ErrorBoundary>
				<ThrowError shouldThrow={ false } />
			</ErrorBoundary>
		);

		expect(
			screen.getByText( 'Valid preview content' )
		).toBeInTheDocument();
	} );

	test( 'catches error and renders fallback when child component throws', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<ErrorBoundary
				fallbackRender={ ( { error } ) => (
					<BlockPreviewErrorFallback
						blockLabel="Test Block"
						setBlockFormModalOpen={ mockSetModalOpen }
						error={ error }
					/>
				) }
			>
				<ThrowError shouldThrow={ true } />
			</ErrorBoundary>
		);

		// Should render the error placeholder
		expect( screen.getByTestId( 'block-placeholder' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'block-label' ) ).toHaveTextContent(
			'Test Block'
		);
		expect( screen.getByTestId( 'error-message' ) ).toHaveTextContent(
			"The preview for this block couldn't be loaded"
		);
	} );

	test( 'calls acf.debug when error is caught', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<ErrorBoundary
				fallbackRender={ ( { error } ) => (
					<BlockPreviewErrorFallback
						blockLabel="Test Block"
						setBlockFormModalOpen={ mockSetModalOpen }
						error={ error }
					/>
				) }
			>
				<ThrowError shouldThrow={ true } />
			</ErrorBoundary>
		);

		// Verify debug was called
		expect( global.acf.debug ).toHaveBeenCalledWith(
			'Block preview error caught:',
			expect.any( Error ),
			expect.any( Object )
		);

		expect( global.acf.debug ).toHaveBeenCalledWith(
			'Block preview error:',
			expect.any( Error )
		);
	} );

	test( 'displays correct error message from translation', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<ErrorBoundary
				fallbackRender={ ( { error } ) => (
					<BlockPreviewErrorFallback
						blockLabel="Movie Block"
						setBlockFormModalOpen={ mockSetModalOpen }
						error={ error }
					/>
				) }
			>
				<ThrowError shouldThrow={ true } />
			</ErrorBoundary>
		);

		// Verify translation function was called
		expect( global.acf.__ ).toHaveBeenCalledWith(
			'Error previewing block v3'
		);

		// Verify the translated message appears
		expect( screen.getByTestId( 'error-message' ) ).toHaveTextContent(
			"The preview for this block couldn't be loaded. Review its content or settings for issues."
		);
	} );

	test( 'uses fallback block label when blockType title is not available', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<ErrorBoundary
				fallbackRender={ ( { error } ) => (
					<BlockPreviewErrorFallback
						blockLabel={ global.acf.__( 'ACF Block' ) }
						setBlockFormModalOpen={ mockSetModalOpen }
						error={ error }
					/>
				) }
			>
				<ThrowError shouldThrow={ true } />
			</ErrorBoundary>
		);

		expect( screen.getByTestId( 'block-label' ) ).toHaveTextContent(
			'ACF Block'
		);
	} );
} );

describe( 'BlockPreviewErrorFallback Component', () => {
	test( 'renders placeholder with error message when error is provided', () => {
		const mockError = new Error( 'Invalid HTML' );
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPreviewErrorFallback
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
				error={ mockError }
			/>
		);

		expect( screen.getByTestId( 'block-placeholder' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'error-message' ) ).toBeInTheDocument();
	} );

	test( 'does not render error message when error is null', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPreviewErrorFallback
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
				error={ null }
			/>
		);

		expect( screen.getByTestId( 'block-placeholder' ) ).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'error-message' )
		).not.toBeInTheDocument();
	} );
} );

describe( 'Invalid HTML Scenarios', () => {
	test( 'handles error from parseJSX with malformed HTML', () => {
		// Simulate parseJSX throwing an error with invalid HTML
		global.acf.parseJSX.mockImplementation( ( html ) => {
			if ( html.includes( '<div><p>Unclosed' ) ) {
				throw new Error( 'jQuery parsing error: Unclosed tag' );
			}
			return <div>{ html }</div>;
		} );

		const InvalidHTMLComponent = () => {
			const html = '<div><p>Unclosed';
			return global.acf.parseJSX( html );
		};

		const mockSetModalOpen = jest.fn();

		render(
			<ErrorBoundary
				fallbackRender={ ( { error } ) => (
					<BlockPreviewErrorFallback
						blockLabel="Block with Invalid HTML"
						setBlockFormModalOpen={ mockSetModalOpen }
						error={ error }
					/>
				) }
			>
				<InvalidHTMLComponent />
			</ErrorBoundary>
		);

		expect( screen.getByTestId( 'block-placeholder' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'error-message' ) ).toHaveTextContent(
			"The preview for this block couldn't be loaded"
		);
	} );

	test( 'handles error from parseJSX with script injection attempt', () => {
		global.acf.parseJSX.mockImplementation( ( html ) => {
			if ( html.includes( '<script>' ) ) {
				throw new Error( 'Security error: Script tag detected' );
			}
			return <div>{ html }</div>;
		} );

		const MaliciousHTMLComponent = () => {
			const html = '<div><script>alert("XSS")</script></div>';
			return global.acf.parseJSX( html );
		};

		const mockSetModalOpen = jest.fn();

		render(
			<ErrorBoundary
				fallbackRender={ ( { error } ) => (
					<BlockPreviewErrorFallback
						blockLabel="Block with Malicious Content"
						setBlockFormModalOpen={ mockSetModalOpen }
						error={ error }
					/>
				) }
			>
				<MaliciousHTMLComponent />
			</ErrorBoundary>
		);

		expect( screen.getByTestId( 'block-placeholder' ) ).toBeInTheDocument();
		expect( global.acf.debug ).toHaveBeenCalled();
	} );

	test( 'renders successfully with valid HTML', () => {
		global.acf.parseJSX.mockImplementation( ( html ) => {
			return <div dangerouslySetInnerHTML={ { __html: html } } />;
		} );

		const ValidHTMLComponent = () => {
			const html = '<p>This is valid HTML</p>';
			return global.acf.parseJSX( html );
		};

		render(
			<ErrorBoundary
				fallbackRender={ ( { error } ) => (
					<BlockPreviewErrorFallback
						blockLabel="Valid Block"
						setBlockFormModalOpen={ jest.fn() }
						error={ error }
					/>
				) }
			>
				<ValidHTMLComponent />
			</ErrorBoundary>
		);

		// Should not show error placeholder
		expect(
			screen.queryByTestId( 'block-placeholder' )
		).not.toBeInTheDocument();
	} );
} );
