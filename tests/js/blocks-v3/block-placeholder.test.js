/**
 * Unit tests for BlockPlaceholder component
 * Tests the placeholder UI shown when a block has no preview HTML
 */

import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

import { BlockPlaceholder } from '../../../assets/src/js/pro/blocks-v3/components/block-placeholder';

// Mock the acf global object
global.acf = {
	__: jest.fn( ( key ) => {
		const translations = {
			'Edit Block': 'Edit Block',
		};
		return translations[ key ] || key;
	} ),
};

describe( 'BlockPlaceholder Component', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders with block label', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		expect( screen.getByTestId( 'placeholder' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'placeholder-label' ) ).toHaveTextContent(
			'Test Block'
		);
	} );

	test( 'renders with instructions when provided', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
				instructions="Please fill in the block fields"
			/>
		);

		expect(
			screen.getByTestId( 'placeholder-instructions' )
		).toHaveTextContent( 'Please fill in the block fields' );
	} );

	test( 'does not render instructions when not provided', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		expect(
			screen.queryByTestId( 'placeholder-instructions' )
		).not.toBeInTheDocument();
	} );

	test( 'renders Edit Block button', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		const button = screen.getByRole( 'button' );
		expect( button ).toHaveTextContent( 'Edit Block' );
	} );

	test( 'calls setBlockFormModalOpen with true when Edit Block button is clicked', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		const button = screen.getByRole( 'button' );
		fireEvent.click( button );

		expect( mockSetModalOpen ).toHaveBeenCalledTimes( 1 );
		expect( mockSetModalOpen ).toHaveBeenCalledWith( true );
	} );

	test( 'renders icon in placeholder', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		expect( screen.getByTestId( 'icon' ) ).toBeInTheDocument();
	} );

	test( 'renders button with primary variant', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		const button = screen.getByRole( 'button' );
		expect( button ).toHaveAttribute( 'data-variant', 'primary' );
	} );

	test( 'uses acf.__ for translations', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		expect( global.acf.__ ).toHaveBeenCalledWith( 'Edit Block' );
	} );

	test( 'renders with different block labels', () => {
		const mockSetModalOpen = jest.fn();
		const testCases = [
			'Testimonial Block',
			'Image Gallery',
			'Contact Form',
			'Hero Section',
		];

		testCases.forEach( ( label ) => {
			const { unmount } = render(
				<BlockPlaceholder
					blockLabel={ label }
					setBlockFormModalOpen={ mockSetModalOpen }
				/>
			);

			expect(
				screen.getByTestId( 'placeholder-label' )
			).toHaveTextContent( label );
			unmount();
		} );
	} );
} );
