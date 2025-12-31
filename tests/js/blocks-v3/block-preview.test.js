/**
 * Unit tests for BlockPreview component
 * Tests the simple wrapper component that renders block preview HTML with block props
 */

/* global HTMLDivElement */

import React from 'react';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

import { BlockPreview } from '../../../assets/src/js/pro/blocks-v3/components/block-preview';

describe( 'BlockPreview Component', () => {
	test( 'renders children inside a div with block props', () => {
		const blockProps = {
			className: 'acf-block-preview',
			'data-testid': 'block-preview',
		};

		render(
			<BlockPreview blockProps={ blockProps }>
				<p>Preview content</p>
			</BlockPreview>
		);

		const previewElement = screen.getByTestId( 'block-preview' );
		expect( previewElement ).toBeInTheDocument();
		expect( previewElement ).toHaveClass( 'acf-block-preview' );
		expect( previewElement ).toContainHTML( '<p>Preview content</p>' );
	} );

	test( 'renders multiple children correctly', () => {
		const blockProps = {
			'data-testid': 'block-preview',
		};

		render(
			<BlockPreview blockProps={ blockProps }>
				<h1>Title</h1>
				<p>Paragraph 1</p>
				<p>Paragraph 2</p>
			</BlockPreview>
		);

		const previewElement = screen.getByTestId( 'block-preview' );
		expect( previewElement.querySelectorAll( 'p' ) ).toHaveLength( 2 );
		expect( screen.getByText( 'Title' ) ).toBeInTheDocument();
	} );

	test( 'renders empty when no children provided', () => {
		const blockProps = {
			'data-testid': 'block-preview',
		};

		render( <BlockPreview blockProps={ blockProps } /> );

		const previewElement = screen.getByTestId( 'block-preview' );
		expect( previewElement ).toBeEmptyDOMElement();
	} );

	test( 'passes through all block props to the wrapper div', () => {
		const blockProps = {
			className: 'custom-class',
			id: 'block-123',
			'data-block': 'acf/test-block',
			'data-testid': 'block-preview',
			style: { backgroundColor: 'red' },
		};

		render(
			<BlockPreview blockProps={ blockProps }>
				<span>Content</span>
			</BlockPreview>
		);

		const previewElement = screen.getByTestId( 'block-preview' );
		expect( previewElement ).toHaveClass( 'custom-class' );
		expect( previewElement ).toHaveAttribute( 'id', 'block-123' );
		expect( previewElement ).toHaveAttribute(
			'data-block',
			'acf/test-block'
		);
		expect( previewElement ).toHaveStyle( { backgroundColor: 'red' } );
	} );

	test( 'renders text content correctly', () => {
		const blockProps = {
			'data-testid': 'block-preview',
		};

		render(
			<BlockPreview blockProps={ blockProps }>
				Plain text content
			</BlockPreview>
		);

		expect( screen.getByText( 'Plain text content' ) ).toBeInTheDocument();
	} );

	test( 'renders nested components correctly', () => {
		const blockProps = {
			'data-testid': 'block-preview',
		};

		const NestedComponent = () => (
			<div data-testid="nested">Nested content</div>
		);

		render(
			<BlockPreview blockProps={ blockProps }>
				<NestedComponent />
			</BlockPreview>
		);

		expect( screen.getByTestId( 'nested' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Nested content' ) ).toBeInTheDocument();
	} );

	test( 'handles ref in block props', () => {
		const ref = React.createRef();
		const blockProps = {
			ref,
			'data-testid': 'block-preview',
		};

		render(
			<BlockPreview blockProps={ blockProps }>
				<span>Content</span>
			</BlockPreview>
		);

		expect( ref.current ).toBeInstanceOf( HTMLDivElement );
	} );
} );
